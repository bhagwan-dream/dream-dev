<?php
namespace api\modules\v1\modules\users\controllers;

use yii\db\Transaction;
use yii\helpers\ArrayHelper;

use common\models\User;
use common\models\TransactionHistory;

class AgentController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];

        return $behaviors;
    }

    private function actionCreateRole(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $role_name = isset( $r_data[ 'role' ] ) ? $r_data[ 'role' ] : '';

            if( $role_name != '' ){
                $auth = \Yii::$app->authManager;
                $role = $auth->createRole( $role_name );
                $auth->add( $role );

                return $response =  [ "status" => 1 , "success" => [ "message" => "Role '" . $role_name . "' Created successfully!" ] ];
            }
        }

        return $response;
    }

    // Function to get the client Commission Rate
    public function clientCommissionRate()
    {
        $CCR = 1;//$CCR = Client Commission Rate

        $setting = (new \yii\db\Query())
            ->select(['value'])
            ->from('setting')
            ->where([ 'key' => 'CLIENT_COMMISSION_RATE' , 'status' => 1 ])
            ->one();

        if( $setting != null ){
            $CCR = $setting['value'];
            return $CCR;
        }else{
            return $CCR;
        }
    }

    public function actionIndex()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }

            $pagination = $filter_args;
        }

        $uid = \Yii::$app->user->id;

        $whare = [ 'u.status' => [1,2] , 'u.parent_id' => $uid ];

        $role = \Yii::$app->authManager->getRolesByUser( $uid );
        //echo '<pre>';print_r($role);die();
        if( isset($role['admin']) && $role['admin'] != null ){
            $whare = [ 'u.status' => [1,2] ];
        }

        $query = User::find()
            ->select( [ 'u.id' , 'u.parent_id' , 'name' , 'username' , 'profit_loss' , 'balance' , 'profit_loss_balance' , 'commission' , 'u.created_at' , 'u.updated_at' , 'u.status' ] )
            ->from( User::tableName() . ' u' )
            ->innerJoin( 'auth_assignment auth' , "u.id=auth.user_id" )
            ->andOnCondition( "auth.item_name like 'agent'" )
            ->andWhere( $whare )
            ->asArray();

        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "u.username" , $filters[ "title" ] ] );
                $query->orFilterWhere( [ "like" , "u.name" , $filters[ "title" ] ] );
            }

            if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "u.status" => $filters[ "status" ] ] );
            }
        }

        $countQuery = clone $query; $count =  $countQuery->count();

        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];

            $query->offset( $offset )->limit( $limit );
        }

        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

        if( $models != null ){
            $i = 0;
            foreach( $models as $usr ){
                $models[$i]['parent'] = $this->parentUserName($usr['parent_id']);
                $i++;
            }

        }
        //echo '<pre>';print_r($models);die();
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];

    }

    public function parentUserName($uid)
    {
        $user = User::find()->select(['name'])->where(['id'=>$uid])->one();

        if( $user != null ){ return $user->name; }else{ return 'Not Set'; }
    }

    public function profitLossAmountBYId($uid)
    {
        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['user_id'=>$uid,'transaction_type' => 'CREDIT'])
            ->andWhere('event_id != 0 AND bet_id != 0')
            ->asArray()->all();
        $profitVal = 0;
        if( $profit[0]['profit'] > 0 ){
            $profitVal = $profit[0]['profit'];
        }

        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['user_id'=>$uid,'transaction_type' => 'DEBIT'])
            ->andWhere('event_id != 0 AND bet_id != 0')
            ->asArray()->all();
        $lossVal = 0;

        if( $loss[0]['loss'] > 0 ){
            $lossVal = $loss[0]['loss'];
        }

        $total = $profitVal-$lossVal;

        return $total;
    }

    public function actionCreate(){

        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

            $r_data = ArrayHelper::toArray( $request_data );

            if( null !== \Yii::$app->request->get( 'id' ) ){
                $parentId = \Yii::$app->request->get( 'id' );
            }else{
                $parentId = \Yii::$app->user->id;
            }

            $user = User::findOne($parentId);

            if( $user != null && $user->role == 1 ){

                if( json_last_error() == JSON_ERROR_NONE ){

                    $model = new User();

                    $model->username            = $r_data[ 'username' ];
                    $model->name                = $r_data[ 'name' ];
                    $model->profit_loss         = 0;
                    $model->balance    = 0;
                    $model->role    = 7;
                    $model->parent_id       = $parentId;
                    $model->auth_key        = \Yii::$app->security->generateRandomString( 32 );
                    $model->password_hash   = \Yii::$app->security->generatePasswordHash( $r_data[ 'password' ] );
                    $model->status          = 1;

                    //$transaction = new Transaction();
                    //$transaction->begin();

                    if( $model->validate() && $model->save() ){

                        $this->assignRole( $model , 'agent' );
                        $this->addBlockMarket( $model->id,$model->parent_id );
                        $this->addBlockSport( $model->id,$model->parent_id );
                        //$transaction->commit();
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "New super admin saved successfully!"
                            ]
                        ];

                    }else{

                        $model->delete();
                        $response[ "error" ] = [
                            "message" => "Something wrong! username or data invalid!"
                        ];
                    }
                }
            }else{
                $response[ "error" ] = [
                    "message" => "Something wrong! username or data invalid!"
                ];
            }

            return $response;
        }else{
            return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
    }

    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){

                    if( $user->profit_loss_balance != 0 ){

                        $response[ "error" ] = [
                            "message" => "Please Cleare Balance!"
                        ];
                        return $response;exit;

                    }

                    $parentUser = User::findOne( $user->id );

                    if( $parentUser != null ){

                        $uType = 'DEBIT'; $pType = 'CREDIT';

                        $parentUser->balance = $parentUser->balance + $user->balance;
                        $user->balance = 0;

                        $user->updated_at = $parentUser->updated_at = time();
                        $attr = [ 'balance' , 'updated_at' ];

                        $description1 = 'Withdrawal By '.$parentUser->username;
                        $description2 = 'Withdrawal From '.$user->username;

                        if( $user->validate( $attr ) && $parentUser->validate( $attr ) ){
                            if( $user->save( $attr ) && $parentUser->save( $attr )
                                && $this->updateTransactionHistory($user->id,$user->balance,$uType,$description1)
                                && $this->updateTransactionHistory($parentUser->id,$user->balance,$pType,$description2)
                            ){
                                $user->status = 0;
                                if( $user->save( [ 'status' ] ) ){
                                    $response = [
                                        'status' => 1 ,
                                        "success" => [
                                            "message" => "Super Admin deleted successfully!"
                                        ]
                                    ];
                                }else{
                                    $response[ "error" ] = [
                                        "message" => "Something wrong! user not deleted!"
                                    ];
                                }

                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! balance not updated!"
                                ];
                            }
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! balance not updated!"
                            ];
                        }
                    }

                }
            }
        }

        return $response;
    }

    public function actionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){

                    if( $user->status == 1 ){
                        $user->status = 2;
                    }else{
                        $user->status = 1;
                    }

                    if( $user->save( [ 'status' ] ) ){

                        $sts = $user->status == 1 ? 'active' : 'inactive';

                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Super Admin $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! user not updeted!" ,
                            "data" => $user->errors
                        ];
                    }

                }
            }
        }

        return $response;
    }

    public function actionUpdate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $user = User::findOne( $id );

            if( $user->parent_id != \Yii::$app->user->id ){
                $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
                return $response;
            }

            if( $user != null ){
                $user->username         = $r_data[ 'username' ];
                $user->name             = $r_data[ 'name' ];
                $user->profit_loss      = $r_data[ 'profit_loss' ];
                $user->updated_at   = time();

                $attr = [ 'name' , 'profit_loss' , 'updated_at' ];

                if( $user->validate( $attr ) ){
                    if( $user->save( $attr ) ){

                        $this->addBlockMarket( $user->id,$user->parent_id );

                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Super admin updated successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! username or data invalid!" ,
                            "data" => $user->errors
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! username or data invalid!" ,
                        "data" => $user->errors
                    ];
                }
            }
        }

        return $response;
    }

    public function actionResetPassword(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $user = User::findOne( $id );

            if( $user != null ){

                $user->auth_key = \Yii::$app->security->generateRandomString( 32 );
                $user->password_hash = \Yii::$app->security->generatePasswordHash( $r_data['password'] );

                if( $user->save( [ 'password_hash' , 'auth_key' ] ) ){
                    $response =  [ "status" => 1 , "success" => [ "message" => "Password changed successfully" ] ];
                }else{
                    $response =  [ "status" => 0 , "error" => $user->errors ];
                }
            }

        }

        return $response;
    }

    //actionChipData
    public function actionChipData(){

        $userData = $parentData = null;
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );

            $user = User::find()->select(['parent_id','balance','name','username'])
                ->where(['status' => 1,'id' => $uid ])->asArray()->one();
            if( $user != null ){

                $userData = [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'balance' => $user['balance'],
                ];

                $parentUser = User::find()->select(['balance','name','username'])
                    ->where(['status' => 1,'id' => $user['parent_id'] ])->asArray()->one();

                if( $parentUser != null ){
                    $parentData = [
                        'name' => $parentUser['name'],
                        'username' => $parentUser['username'],
                        'balance' => $parentUser['balance'],
                    ];
                }

            }

        }

        if( $userData != null && $userData != null ){
            $response =  [ "status" => 1 , "data" => [ "userData" => $userData , "parentData" => $parentData ] ];
        }else{
            $response =  [ "status" => 0 , "data" => null , "message" => "This user is not valid!" ];
        }
        return $response;
    }

    //actionDepositChips
    public function actionDepositChips(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( $id != null && json_last_error() == JSON_ERROR_NONE ){

            $r_data = ArrayHelper::toArray( $request_data );

            $user = User::findOne( $id );

            $parentUser = User::findOne( $user->parent_id );

            $role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );

            if( $user != null && $parentUser != null
                && ( $user->parent_id == \Yii::$app->user->id || isset($role['admin']) ) ){

                $uType = 'CREDIT'; $pType = 'DEBIT';
                if( $parentUser->balance >= $r_data['chips'] ){

                    $parentUser->balance = $parentUser->balance - $r_data['chips'];

                    $user->balance = $user->balance + $r_data['chips'];
                }else{
                    $response[ "error" ] = [
                        "message" => "Insufficient balance!" ,
                        "data" => $user->errors
                    ];
                    return $response;
                }

                $user->updated_at = $parentUser->updated_at = time();

                $attr = [ 'balance' , 'updated_at' ];

                $description1 = 'Deposit By '.$parentUser->username;
                $description2 = 'Deposit To '.$user->username;

                if( $user->validate( $attr ) && $parentUser->validate( $attr ) ){
                    if( $user->save( $attr ) && $parentUser->save( $attr )
                        && $this->updateTransactionHistory($user->id,$r_data['chips'],$uType,$description1)
                        && $this->updateTransactionHistory($parentUser->id,$r_data['chips'],$pType,$description2)
                    ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Super admin balance updated successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! balance not updated!" ,
                            "data" => $user->errors
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! balance not updated!" ,
                        "data" => $user->errors
                    ];
                }
            }
        }

        return $response;
    }

    //actionWithdrawalChips
    public function actionWithdrawalChips(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $user = User::findOne( $id );

            $parentUser = User::findOne( $user->parent_id );

            $role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );

            if( $user->balance <= $r_data['chips'] ){
                $response[ "error" ] = [
                    "message" => "Insufficient balance!" ,
                    "data" => $user->errors
                ];
                return $response;
            }

            if( $user != null && $parentUser != null &&
                ( $user->parent_id == \Yii::$app->user->id || isset($role['admin']) ) ){

                $uType = 'DEBIT'; $pType = 'CREDIT';

                $parentUser->balance = $parentUser->balance + $r_data['chips'];
                $user->balance = $user->balance - $r_data['chips'];

                $user->updated_at = $parentUser->updated_at = time();
                $attr = [ 'balance' , 'updated_at' ];

                $description1 = 'Withdrawal By '.$parentUser->username;
                $description2 = 'Withdrawal From '.$user->username;

                if( $user->validate( $attr ) && $parentUser->validate( $attr ) ){
                    if( $user->save( $attr ) && $parentUser->save( $attr )
                        && $this->updateTransactionHistory($user->id,$r_data['chips'],$uType,$description1)
                        && $this->updateTransactionHistory($parentUser->id,$r_data['chips'],$pType,$description2)
                    ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Super admin balance updated successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! balance not updated!" ,
                            "data" => $user->errors
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! balance not updated!" ,
                        "data" => $user->errors
                    ];
                }
            }
        }

        return $response;
    }

    // Update Transaction History
    public function updateTransactionHistory($uid,$amount,$type,$desc)
    {
        $cUser = User::find()->select(['parent_id'])->where(['id'=>$uid])->asArray()->one();
        $pId = $cUser['parent_id'];

        $resultArr = [
            'user_id' => $uid,
            'parent_id' => $pId,
            'child_id' => 0,
            'event_id' => 0,
            'market_id' => 0,
            'transaction_type' => $type,
            'transaction_amount' => $amount,
            'current_balance' => $this->getCurrentBalance($uid,$amount,$type),
            'description' => $desc,
            'status' => 1,
            'updated_at' => time(),
            'created_at' => time(),
        ];

        \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
        \Yii::$app->db->close();
        return true;
    }

    // get Current Balance
    public function getCurrentBalance($uid,$amount,$type)
    {
        $user = User::findOne(['id' => $uid ]);
        if( $user != null ){
            $user->balance = $user->balance + $user->profit_loss_balance;
        }

        return $user->balance;
    }


    public function actionView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );

        if( $id != null ){
            $model = User::find()->select( [ 'id' , 'username' , 'name' , 'profit_loss' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }

        return $response;
    }

    public function addBlockMarket( $uid , $pid ){
        $dataUsr = [];
        $blockList = (new \yii\db\Query())
            ->select(['event_id','market_id','byuser'])
            ->from('event_market_status')
            ->where(['user_id' => $pid ])
            ->all();

        if( $blockList != null ){
            foreach ( $blockList as $data ){
                $dataUsr[] = [
                    'user_id' => $uid,
                    'event_id' => $data['event_id'],
                    'market_id' => $data['market_id'],
                    'market_type' => 'all',
                    'byuser' => $data['byuser'],
                ];
            }
        }

        if( $dataUsr != null ){
            \Yii::$app->db->createCommand()->batchInsert('event_market_status',
                ['user_id', 'event_id','market_id','market_type','byuser'], $dataUsr )->execute();
        }

    }

    public function addBlockSport( $uid , $pid ){
        $dataUsr = [];
        $blockList = (new \yii\db\Query())
            ->select(['sport_id','byuser'])
            ->from('event_status')
            ->where(['user_id' => $pid ])
            ->all();

        if( $blockList != null ){
            foreach ( $blockList as $data ){
                $dataUsr[] = [
                    'user_id' => $uid,
                    'sport_id' => $data['sport_id'],
                    'byuser' => $data['byuser'],
                ];
            }
        }

        if( $dataUsr != null ){
            \Yii::$app->db->createCommand()->batchInsert('event_status',
                ['user_id', 'sport_id','byuser'], $dataUsr )->execute();
        }

    }

    private function assignRole( $user , $role ){

        $auth = \Yii::$app->authManager;

        $role = $auth->getRole( $role );

        if( $role != null ){
            $user_assign = $auth->assign( $role , $user->id );
        }
    }

}
