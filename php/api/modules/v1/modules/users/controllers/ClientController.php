<?php
namespace api\modules\v1\modules\users\controllers;

use common\models\PlaceBet;
use yii\helpers\ArrayHelper;
use common\models\User;
use common\models\TransactionHistory;

class ClientController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin' , 'subadmin', 'agent1' ,'agent2' ],
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
        
        $whare = [ 'u.status' => [1,2] , 'u.parent_id' => \Yii::$app->user->id ];

        $role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );

        if( ( isset($role['admin']) && $role['admin'] != null ) || ( isset($role['subadmin']) && $role['subadmin'] != null ) ){
            $whare = [ 'u.status' => [1,2] ];
        }

        $query = User::find()
        ->select( [ 'u.id' ,'u.parent_id', 'name' , 'username' , 'remark', 'profit_loss' ,'expose_balance' ,'profit_loss_balance','commission' ,'balance' , 'u.created_at' , 'u.updated_at' , 'u.status' , 'u.isbet' ] )
            ->from( User::tableName() . ' u' )
            ->innerJoin( 'auth_assignment auth' , "u.id=auth.user_id" )
            ->andOnCondition( "auth.item_name like 'client'" )
            ->andWhere( $whare );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "username" , $filters[ "title" ] ] );
                $query->orFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
            }
            
            if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
            }
        }

        $countQuery = clone $query; $count =  $countQuery->count();

        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->andWhere( $whare )->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        $commRate = $this->clientCommissionRate();
        
        if( $models != null ){
            $i = 0;
            foreach( $models as $usr ){
                
                $models[$i]['available_balance'] = round( ( $usr['balance']-$usr['expose_balance']+$usr['profit_loss_balance'] ) , 2 );
                $models[$i]['profit_loss_balance'] = round( $usr['profit_loss_balance'] , 2 );
                $models[$i]['expose_balance'] = round( $usr['expose_balance'] , 2 );
                $models[$i]['balance'] = round( $usr['balance'] , 2 );
                $models[$i]['parent'] = $this->parentUserName($usr['parent_id']);
                $models[$i]['comm_rate'] = $commRate;
                $models[$i]['userAct'] = $this->userAct($usr['id']);
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }

    public function userAct($uid)
    {
        //logout 3,login 2,active 1
        $act = 3;
        $user = User::find()->select(['is_login'])->where(['id'=>$uid])->asArray()->one();

        $t1 = time();
        $t2 = time() - 60*60;

        $userBet = PlaceBet::find()->select(['id'])
            ->where(['user_id'=>$uid])
            ->andWhere(['>=','created_at',$t2])
            ->one();

        if( $user != null ){
            if( $user['is_login'] == 1 ){
                $act = 2;
                if( $userBet != null ){
                    $act = 1;
                }
            }
        }
        return $act;
    }

    public function parentUserName($uid)
    {
        $parentUserName = 'Not Set!';
        $user = User::find()->select(['name','username'])->where(['id'=>$uid])->asArray()->one();

        if( $user != null ){
            $parentUserName = $user['name'].'[ '.$user['username'].' ]';
        }

        return $parentUserName;
    }
    
    public function profitLossAmountBYId($uid)
    {
        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
        ->where(['user_id'=>$uid,'transaction_type' => 'CREDIT'])
        ->andWhere('event_id != 0')
        ->asArray()->all();
        $profitVal = 0;
        if( $profit[0]['profit'] > 0 ){
            $profitVal = $profit[0]['profit'];
        }
        
        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
        ->where(['user_id'=>$uid,'transaction_type' => 'DEBIT'])
        ->andWhere('event_id != 0')
        ->asArray()->all();
        $lossVal = 0;
        
        if( $loss[0]['loss'] > 0 ){
            $lossVal = $loss[0]['loss'];
        }
        
        $total = $profitVal-$lossVal;
        
        return $total;
    }
    
    public function actionReference()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( null == \Yii::$app->request->get( 'id' ) ){
            return [ "status" => 1 , "data" => [ "items" => [] , "count" => 0 ] ];
        }

        $id = \Yii::$app->request->get( 'id' );

        $parentUserName = $this->parentUserName($id);

//        $cUser = User::find()->select(['username','name'])->where(['id' => $id])->asArray()->one();
//        if( $cUser != null ){
//            $parentUserName = $cUser['name'].' ['.$cUser['username'].']';
//        }

        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        //$whare = [ 'u.status' => [1,2] , 'u.parent_id' => $id ];
        
        /*$role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );
        
        if( isset($role['admin']) && $role['admin'] != null ){
            $whare = [ 'u.status' => [1,2] ];
        }*/
        
        $query = User::find()
        ->select( [ 'u.id' , 'name' , 'username' , 'profit_loss' ,'commission' ,'balance' ,'remark','profit_loss_balance', 'expose_balance','u.created_at' , 'u.updated_at' , 'u.status' , 'isbet' ] )
        ->from( User::tableName() . ' u' )
        ->innerJoin( 'auth_assignment auth' , "u.id=auth.user_id" )
        ->andOnCondition( "auth.item_name like 'client'" )
        ->andWhere( [ 'u.status' => [1,2] , 'u.parent_id' => $id ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "username" , $filters[ "title" ] ] );
                $query->orFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
            }
            
            if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        $commRate = $this->clientCommissionRate();
        if( $models != null ){
            $i = 0;
            foreach( $models as $usr ){

                $models[$i]['available_balance'] = round( ( $usr['balance']-$usr['expose_balance']+$usr['profit_loss_balance'] ) , 2 );
                $models[$i]['profit_loss_balance'] = round( $usr['profit_loss_balance'] , 2 );
                $models[$i]['expose_balance'] = round( $usr['expose_balance'] , 2 );
                $models[$i]['balance'] = round( $usr['balance'] , 2 );

                //$models[$i]['available_balance'] = ( $usr['balance']-$usr['expose_balance'] );
                //$models[$i]['profit_loss_amount'] = $this->profitLossAmountBYId($usr['id']);
                $models[$i]['userAct'] = $this->userAct($usr['id']);
                $models[$i]['comm_rate'] = $commRate;
                
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count , "parentUserName" => $parentUserName ] ];
        
        
    }
    
    public function actionCreate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $r_data = ArrayHelper::toArray( $request_data );
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $parentId = \Yii::$app->request->get( 'id' );
        }else{
            $parentId = \Yii::$app->user->id;
        }
        
        $user = User::findOne($parentId);
        
        if( $user != null ){
        
            if( $user->balance < $r_data[ 'balance' ] ){
                return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Balance must be less then ".$user->balance." !!" ] ];
                exit;
            }
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $model = new User();

                $remark = null;
                if( isset( $r_data['remark'] ) ){
                    $remark = $r_data['remark'];
                }

                $model->username            = $r_data[ 'username' ];
                $model->name                = $r_data[ 'name' ];
                $model->profit_loss         = 0;
                $model->balance             = $r_data[ 'balance' ];
                $model->remark              = $remark;
                $model->role                = 4;
                $model->parent_id           = \Yii::$app->user->id;
                $model->auth_key            = \Yii::$app->security->generateRandomString( 32 );
                $model->password_hash       = \Yii::$app->security->generatePasswordHash( $r_data[ 'password' ] );
                $model->status              = 1;
            
                $user->balance = $user->balance-$r_data[ 'balance' ];

                //$transaction = new Transaction();
                //$transaction->begin();

                if( $model->validate() && $model->save() ){

                    if( $user->save() ){

                        $uType = 'CREDIT'; $pType = 'DEBIT';
                        $description1 = 'Deposit By '.$user->username;
                        $description2 = 'Deposit To '.$model->username;

                        if( $this->updateTransactionHistory($model->id,$r_data['balance'],$uType,$description1,$r_data[ 'remark' ])
                            && $this->updateTransactionHistory($model->parent_id,$r_data['balance'],$pType,$description2,$r_data[ 'remark' ])
                             ){

                            $this->assignRole( $model , 'client' );
                            $this->addProfitLossLevel($model);
                            //$transaction->commit();
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "New client saved successfully!"
                                ]
                            ];

                        }else{
                            //$transaction->rollBack();
                            $response[ "error" ] = [
                                "message" => "Something wrong! username or data invalid!" ,

                            ];
                        }

                    }else{
                        //$transaction->rollBack();
                        $model->delete();
                        $response[ "error" ] = [
                            "message" => "Something wrong! username or data invalid!" ,

                        ];
                    }


                }else{
                    $model->delete();
                    $response[ "error" ] = [
                        "message" => "Something wrong! username or data invalid!" ,
                        "data" => $model->errors
                    ];
                }
            }
        }
        
        return $response;
    }
    
    // Add Profit Loss Level
    public function addProfitLossLevel($model){
        
        $userProfitLossArr = [];
        
        $userProfitLoss = [
            'client_id' => $model->id,
            'user_id' => $model->id,
            'parent_id' => $model->parent_id,
            'level' => 0,
            'profit_loss' => 0,
            'actual_profit_loss' => 0,
            'updated_at' => time(),
            'status' => 1
        ];
        
        array_push($userProfitLossArr, $userProfitLoss);
        
        $parentUser1 = User::findOne(['id'=>$model->parent_id]);
        
        if( $parentUser1 != null ){
            $profit_loss1 = $parentUser1->profit_loss;
            $actual_profit_loss1 = $parentUser1->profit_loss;
            if( $parentUser1->role == 1 && $parentUser1->profit_loss == 0 ){
                $profit_loss1 = 0;
                $actual_profit_loss1 = 100;
            }
            
            $userProfitLoss1 = [
                'client_id' => $model->id,
                'user_id' => $parentUser1->id,
                'parent_id' => $parentUser1->parent_id,
                'level' => 1,
                'profit_loss' => $profit_loss1,
                'actual_profit_loss' => $actual_profit_loss1,
                'updated_at' => time(),
                'status' => 1
            ];
            
            array_push($userProfitLossArr, $userProfitLoss1);
            
            if( $parentUser1->role != 1 ){
                
                $parentUser2 = User::findOne(['id'=>$parentUser1->parent_id]);
                
                if( $parentUser2 != null ){
                    
                    $profit_loss2 = $parentUser2->profit_loss;
                    //$actual_profit_loss2 = ($profit_loss1*$parentUser2->profit_loss)/100;
                    $actual_profit_loss2 = $parentUser2->profit_loss-$profit_loss1;
                    if( $parentUser2->role == 1 && $parentUser2->profit_loss == 0 ){
                        $profit_loss2 = 0;
                        $actual_profit_loss2 = 100-$profit_loss1;
                    }
                    
                    $userProfitLoss2 = [
                        'client_id' => $model->id,
                        'user_id' => $parentUser2->id,
                        'parent_id' => $parentUser2->parent_id,
                        'level' => 2,
                        'profit_loss' => $profit_loss2,
                        'actual_profit_loss' => $actual_profit_loss2,
                        'updated_at' => time(),
                        'status' => 1
                    ];
                    
                    array_push($userProfitLossArr, $userProfitLoss2);
                    
                    if( $parentUser2->role != 1 ){
                        
                        $parentUser3 = User::findOne(['id'=>$parentUser2->parent_id]);
                        
                        if( $parentUser3 != null ){
                            
                            $profit_loss3 = $parentUser3->profit_loss;
                            //$actual_profit_loss3 = ($profit_loss2*$parentUser3->profit_loss)/100;
                            $actual_profit_loss3 = $parentUser3->profit_loss-$profit_loss2;
                            if( $parentUser3->role == 1 && $parentUser3->profit_loss == 0 ){
                                $profit_loss3 = 0;
                                $actual_profit_loss3 = 100-$profit_loss2;
                            }
                            
                            $userProfitLoss3 = [
                                'client_id' => $model->id,
                                'user_id' => $parentUser3->id,
                                'parent_id' => $parentUser3->parent_id,
                                'level' => 3,
                                'profit_loss' => $profit_loss3,
                                'actual_profit_loss' => $actual_profit_loss3,
                                'updated_at' => time(),
                                'status' => 1
                            ];
                            
                            array_push($userProfitLossArr, $userProfitLoss3);
                            
                            if( $parentUser3->role != 1 ){
                                
                                $parentUser4 = User::findOne(['id'=>$parentUser3->parent_id]);
                                
                                if( $parentUser4 != null ){
                                    
                                    $profit_loss4 = $parentUser4->profit_loss;
                                    //$actual_profit_loss4 = ($profit_loss3*$parentUser4->profit_loss)/100;
                                    $actual_profit_loss4 = $parentUser4->profit_loss-$profit_loss3;
                                    if( $parentUser4->role == 1 && $parentUser4->profit_loss == 0 ){
                                        $profit_loss4 = 0;
                                        $actual_profit_loss4 = 100-$profit_loss3;
                                    }
                                    
                                    $userProfitLoss4 = [
                                        'client_id' => $model->id,
                                        'user_id' => $parentUser4->id,
                                        'parent_id' => $parentUser4->parent_id,
                                        'level' => 4,
                                        'profit_loss' => $profit_loss4,
                                        'actual_profit_loss' => $actual_profit_loss4,
                                        'updated_at' => time(),
                                        'status' => 1
                                    ];
                                    
                                    array_push($userProfitLossArr, $userProfitLoss4);
                                    
                                    if( $parentUser4->role != 1 ){
                                        
                                        $parentUser5 = User::findOne(['id'=>$parentUser4->parent_id]);
                                        
                                        if( $parentUser5 != null ){
                                            
                                            $profit_loss5 = $parentUser5->profit_loss;
                                            //$actual_profit_loss5 = ($profit_loss4*$parentUser5->profit_loss)/100;
                                            $actual_profit_loss5 = $parentUser5->profit_loss-$profit_loss4;
                                            if( $parentUser5->role == 1 && $parentUser5->profit_loss == 0 ){
                                                $profit_loss5 = 0;
                                                $actual_profit_loss5 = 100-$profit_loss4;
                                            }
                                            
                                            $userProfitLoss5 = [
                                                'client_id' => $model->id,
                                                'user_id' => $parentUser5->id,
                                                'parent_id' => $parentUser5->parent_id,
                                                'level' => 5,
                                                'profit_loss' => $profit_loss5,
                                                'actual_profit_loss' => $actual_profit_loss5,
                                                'updated_at' => time(),
                                                'status' => 1
                                            ];
                                            
                                            array_push($userProfitLossArr, $userProfitLoss5);
                                            
                                        }
                                        
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                    }
                    
                    
                }
                
            }
            
        }
        
        $command = \Yii::$app->db->createCommand();
        $attrArr = ['client_id','user_id' , 'parent_id' , 'level' , 'profit_loss' , 'actual_profit_loss' , 'updated_at' , 'status'];
        $qry = $command->batchInsert('user_profit_loss', $attrArr, $userProfitLossArr);
        if( $qry->execute() ){
            return true;
        }else{
            return false;
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
                    $user->status = 0;
                    
                    if( $user->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Client deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! user not deleted!" ,
                            "data" => $user->errors
                        ];
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

                    if( $user->parent_id != 0 ){

                        $pUser = User::findOne( $user->parent_id );

                        if( $pUser->status != 1 ){

                            $response = [
                                "status" => 1 ,
                                "error" => [
                                    "message" => "Your Super Master Or Master is InActive!"
                                ]
                            ];
                            return $response;

                        }

                    }

                    if( $user->status == 1 ){
                        $user->status = 2;
                    }else{
                        $user->status = 1;
                    }
                    $user->is_login = 0;

                    if( $user->save( [ 'status','is_login'] ) ){

                        \Yii::$app->db->createCommand()
                            ->delete('auth_token', ['user_id' => $r_data[ 'id' ] ])
                            ->execute();

                        $this->updateChildStatus($r_data[ 'id' ] , $user->status);

                        $sts = $user->status == 1 ? 'active' : 'inactive';

                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Client $sts successfully!"
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

    public function updateChildStatus($uid,$status){

        $childUser = User::find()->select('id')->where( ['parent_id' => $uid ] )->asArray()->all();

        if( $childUser != null ){

            $isbet = 0;
            if( $status == 1 ){ $isbet = 1; }

            if( User::updateAll(['is_login' => 0 , 'status' => $status , 'isbet' => $isbet ] , [ 'IN' , 'id' , $childUser ]) ){

                if( $status != 1 ){
                    foreach ( $childUser as $child ){

                        $this->updateChildStatus($child,$status);

                        \Yii::$app->db->createCommand()
                            ->delete('auth_token', [ 'user_id'=> $child ])
                            ->execute();

                    }
                }else{
                    foreach ( $childUser as $child ){
                        $this->updateChildStatus($child,$status);
                    }

                }

                return true;

            }else{
                return false;
            }

        }else{ return false; }

    }

    public function actionBetStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){

                    if( $user->parent_id != 0 ){

                        $pUser = User::findOne( $user->parent_id );

                        if( $pUser->status != 1 ){

                            $response = [
                                "status" => 1 ,
                                "error" => [
                                    "message" => "Your Super Master Or Master is InActive!"
                                ]
                            ];
                            return $response;

                        }

                    }

                    if( $user->isbet == 1 ){
                        $user->isbet = 2;
                    }else{
                        $user->isbet = 1;
                    }

                    if( $user->save( [ 'isbet' ] ) ){
                        $sts = $user->isbet == 1 ? 'Allow' : 'Not Allow';
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Client $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! user not updated!" ,
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
                $user->updated_at = time();
                
                $attr = [ 'username' , 'updated_at' ];
                
                if( $user->validate( $attr ) ){
                    if( $user->save( $attr ) ){
                        //$this->addProfitLossLevel($user);
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Client updated successfully!"
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

    //actionIndex
    public function actionHistory()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $userName = 'User Not Found!';
        $uid = \Yii::$app->user->id;
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }

        $user = User::find()->select(['name','username'])
            ->where(['id' => $uid , 'status' => [1,2] ])->asArray()->one();

            $userName = $user['name'].' [ '.$user['username'].' ]';

            if( json_last_error() == JSON_ERROR_NONE ){
                $filter_args = ArrayHelper::toArray( $data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;
            }

            $query = PlaceBet::find()
                ->select( [ 'id','market_id','sec_id','user_id','session_type','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate', 'size' , 'win' , 'loss' , 'bet_status' , 'status' ,'description', 'match_unmatch', 'created_at' , 'updated_at' ] )
                ->andWhere(['user_id' => $uid ]);

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                    $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
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
                foreach ( $models as $data ){
                    //$description = str_replace('/','>',$data['description']);
                    $session = str_replace('_' , ' ',$data['session_type']);
                    $session = ucfirst($session);
                    $description = str_replace($data['runner'],$session.' > '.$data['runner'],$data['description']);
                    //$description = str_replace('>  >','>',$description);
                    $models[$i]['description'] = $description;
                    $models[$i]['rate'] = '0';
                    if( $data['session_type'] == 'fancy' || $data['session_type'] == 'fancy2' ){
                        $models[$i]['runner'] = 'RUN';
                        $models[$i]['rate'] = $data['rate'];
                    }

                    $i++;
                }

            }

            return [ "status" => 1 , "data" => [ "items" => $models , "user" => $userName , "count" => $count ] ];

    }

    public function actionSetting(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $user = User::findOne( $id );

            if( $user != null ){
                $user->max_stack         = $r_data[ 'max_stack' ];
                $user->min_stack             = $r_data[ 'min_stack' ];
                $user->max_profit_limit             = $r_data[ 'max_profit' ];
                $user->bet_delay             = $r_data[ 'bet_delay' ];
                $user->updated_at = time();

                $attr = [ 'max_stack' , 'min_stack' , 'max_profit_limit', 'bet_delay', 'updated_at' ];

                if( $user->validate( $attr ) ){
                    if( $user->save( $attr ) ){
                        //$this->addProfitLossLevel($user);
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Client updated successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! username or data invalid!"
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! username or data invalid!"
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
                $user->is_login = 0;

                \Yii::$app->db->createCommand()
                    ->delete('auth_token', ['user_id' => $id ])
                    ->execute();

                if( $user->save( [ 'password_hash' , 'auth_key' , 'is_login' ] ) ){
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

            $user = User::find()->select(['parent_id','balance','name','username','expose_balance','profit_loss_balance'])
                ->where(['status' => 1,'id' => $uid ])->asArray()->one();
            if( $user != null ){

                $userData = [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'balance' => ($user['balance']-$user['expose_balance']+$user['profit_loss_balance']),
                ];

                $parentUser = User::find()->select(['balance','name','username','expose_balance','profit_loss_balance'])
                    ->where(['status' => 1,'id' => $user['parent_id'] ])->asArray()->one();

                if( $parentUser != null ){
                    $parentData = [
                        'name' => $parentUser['name'],
                        'username' => $parentUser['username'],
                        'balance' => $parentUser['balance'],
                        //'balance' => ($parentUser['balance']-$parentUser['expose_balance']+$parentUser['profit_loss_balance']),
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
            
            if( $user != null && $parentUser != null &&
                ( $user->parent_id == \Yii::$app->user->id || isset($role['admin']) ) ){
                
                $uType = 'CREDIT'; $pType = 'DEBIT';
                if( $parentUser->balance > $r_data['chips'] ){
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
                
                $description1 = 'Deposit From '.$parentUser->username;
                $description2 = 'Deposit To '.$user->username;

                $remark = null;
                if( isset( $r_data['remark'] ) ){
                    $remark = $r_data['remark'];
                }

                if( $user->validate( $attr ) && $parentUser->validate( $attr ) ){
                    if( $user->save( $attr ) && $parentUser->save( $attr )
                        && $this->updateTransactionHistory($user->id,$r_data['chips'],$uType,$description1,$remark)
                        && $this->updateTransactionHistory($parentUser->id,$r_data['chips'],$pType,$description2,$remark)
                        ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Client balance updated successfully!"
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
            
            if( $user->balance < $r_data['chips'] ){
                $response[ "error" ] = [
                    "message" => "Insufficient Balance!" ,
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
                
                $description1 = 'Deposit To '.$parentUser->username;
                $description2 = 'Deposit From '.$user->username;

                $remark = null;
                if( isset( $r_data['remark'] ) ){
                    $remark = $r_data['remark'];
                }

                if( $user->validate( $attr ) && $parentUser->validate( $attr ) ){
                    if( $user->save( $attr ) && $parentUser->save( $attr )
                        && $this->updateTransactionHistory($user->id,$r_data['chips'],$uType,$description1,$remark)
                        && $this->updateTransactionHistory($parentUser->id,$r_data['chips'],$pType,$description2,$remark)
                        ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Client balance updated successfully!"
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
    public function updateTransactionHistory($uid,$amount,$type,$desc,$remark)
    {
        $cUser = User::find()->select(['parent_id','balance','profit_loss_balance','role'])->where(['id'=>$uid])->asArray()->one();
        $pId = $cUser['parent_id'];

        if( $cUser['role'] == 4 ){
            $currentBalance = ($cUser['balance']+$cUser['profit_loss_balance']);
        }else{
            $currentBalance = $cUser['balance'];
        }

        $resultArr = [
            'user_id' => $uid,
            'parent_id' => $pId,
            'child_id' => 0,
            'event_id' => 0,
            'market_id' => 0,
            'transaction_type' => $type,
            'transaction_amount' => $amount,
            'current_balance' => $currentBalance,//$this->getCurrentBalance($uid,$amount,$type),
            'description' => $desc,
            'remark' => $remark,
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
            $model = User::find()->select( [ 'id' , 'username' , 'name' , 'max_stack' , 'min_stack' , 'max_profit_limit' , 'bet_delay'] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    private function assignRole( $user , $role ){
        $auth = \Yii::$app->authManager;
        
        $role = $auth->getRole( $role );
        
        if( $role != null ){
            $user_assign = $auth->assign( $role , $user->id );
        }
    }
}
