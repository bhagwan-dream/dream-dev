<?php
namespace api\modules\v1\modules\users\controllers;

use yii\db\Transaction;
use yii\helpers\ArrayHelper;

use common\models\User;
use common\models\TransactionHistory;

class Session2UserController extends \common\controllers\aController
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

        $whare = [ 'u.status' => [1,2] , 'u.parent_id' => \Yii::$app->user->id ];

        $role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );
        //echo '<pre>';print_r($role);die();
        if( isset($role['admin']) && $role['admin'] != null ){
            $whare = [ 'u.status' => [1,2] ];
        }

        $query = User::find()
            ->select( [ 'u.id' , 'u.parent_id' , 'name' , 'username' , 'profit_loss' , 'balance' , 'profit_loss_balance' , 'commission' , 'u.created_at' , 'u.updated_at' , 'u.status' ] )
            ->from( User::tableName() . ' u' )
            ->innerJoin( 'auth_assignment auth' , "u.id=auth.user_id" )
            ->andOnCondition( "auth.item_name like 'sessionuser2'" )
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

        //$commRate = $this->clientCommissionRate();

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

    public function superMasterConntBYId($uid)
    {
        $count = User::find()
            ->select( ['id'] )
            ->where( [ 'parent_id' => $uid, 'status' => [1,2] , 'role' => 2 ] )
            ->asArray()->count();

        return $count;
    }

    public function masterConntBYId($uid)
    {
        $count = User::find()
            ->select( ['id'] )
            ->where( [ 'parent_id' => $uid, 'status' => [1,2] , 'role' => 3 ] )
            ->asArray()->count();

        return $count;
    }

    public function clientConntBYId($uid)
    {
        $count = User::find()
            ->select( ['id'] )
            ->where( [ 'parent_id' => $uid, 'status' => [1,2] , 'role' => 4 ] )
            ->asArray()->count();

        return $count;
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
                    $model->role    = 8;
                    $model->parent_id       = $parentId;
                    $model->auth_key        = \Yii::$app->security->generateRandomString( 32 );
                    $model->password_hash   = \Yii::$app->security->generatePasswordHash( $r_data[ 'password' ] );
                    $model->status          = 1;

                    //$transaction = new Transaction();
                    //$transaction->begin();

                    if( $model->validate() && $model->save() ){

                        $this->assignRole( $model , 'sessionuser2' );
                        $this->addBlockMarket( $model->id,$model->parent_id );
                        $this->addBlockSport( $model->id,$model->parent_id );
                        //$transaction->commit();
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "New session user saved successfully!"
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

    public function checkSuperMasterCreateLimit($user){

        $return = 'NoError';

        if( $user != null ){
            $role = \Yii::$app->authManager->getRolesByUser( $user->parent_id );

            if( isset($role['agent1']) && $role['agent1'] != null ){
                $return = 'Error1';
            }

        }else{

            $subUser = User::find()->select(['id'])
                ->where(['parent_id'=> $user->id , 'role' => '2' , 'status'=>[1,2]])
                ->asArray()->all();

            if( $subUser != null && count($subUser) >= 3 ){
                $return =  'Error2';
            }
        }

        return $return;

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

//                     if( $this->checkUserBalance($r_data[ 'id' ]) ){

//                         $response[ "error" ] = [
//                             "message" => "Please Cleare Balance!"
//                         ];
//                         return $response;exit;

//                     }

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
                                            "message" => "Super master deleted successfully!"
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

    public function checkUserBalance($uid){

        $client = [];
        $smdata = User::find()->select(['id','role'])->where(['parent_id'=>$uid , 'role'=> 2])->all();

        if($smdata != null){
            foreach ( $smdata as $sm ){
                // get all master
                $m1data = User::find()->select(['id','role'])->where(['parent_id'=>$sm->id , 'role'=> 3])->all();
                if($m1data != null){
                    foreach ( $m1data as $m1 ){
                        // get all master
                        $m2data = User::find()->select(['id','role'])->where(['parent_id'=>$m1->id , 'role'=> 3])->all();
                        if($m2data != null){
                            foreach ( $m2data as $m2 ){

                                // get all client
                                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m2->id , 'role'=> 4])->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c->id;
                                    }
                                }

                            }
                        }

                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m1->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id;
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$sm->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id;
                    }
                }

            }
        }

        // get all master
        $mdata = User::find()->select(['id','role'])->where(['parent_id'=>$uid , 'role'=> 3])->all();
        if($mdata != null){

            foreach ( $mdata as $m ){

                $m2data = User::find()->select(['id','role'])->where(['parent_id'=>$m->id , 'role'=> 3])->all();
                if($m2data != null){
                    foreach ( $m2data as $m2 ){

                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m2->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id;
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id;
                    }
                }

            }

        }

        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c->id;
            }
        }

        return $client;

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
                                "message" => "Super master $sts successfully!"
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

                $attr = [ 'name' , 'profit_loss' , /*'commission', 'max_stack', 'min_stack', 'max_profit_limit',*/ 'updated_at' ];

                if( $user->validate( $attr ) ){
                    if( $user->save( $attr ) ){

                        $this->addBlockMarket( $user->id,$user->parent_id );

                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Super master updated successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! username or data invalid!" ,
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! username or data invalid!" ,
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
                                "message" => "Super master balance updated successfully!"
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
                                "message" => "Super master balance updated successfully!"
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
            /*if( $type == 'CREDIT' ){
                $user->balance = $user->balance+$amount;
            }else{
                $user->balance = $user->balance-$amount;
            }*/
            //if( $user->save(['balance']) ){
            $user->balance = $user->balance + $user->profit_loss_balance;
            //}
        }

        return $user->balance;
    }


    public function actionView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );

        if( $id != null ){
            $model = User::find()->select( [ 'id' , 'username' , 'name' , 'profit_loss' /*, 'commission' , 'max_stack' , 'min_stack' , 'max_profit_limit'*/ ] )->where( [ 'id' => $id ] )->asArray()->one();
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
