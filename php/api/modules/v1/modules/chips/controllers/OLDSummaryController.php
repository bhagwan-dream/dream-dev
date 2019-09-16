<?php
namespace api\modules\v1\modules\chips\controllers;

use yii\helpers\ArrayHelper;
use common\models\ChipsAllocation;
use yii\data\ActiveDataProvider;
use common\models\User;
use common\models\TransactionHistory;
use common\models\PlaceBet;
use common\models\TempTransactionHistory;

class OLDSummaryController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent1','agent2' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
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
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }else{
            $uid = \Yii::$app->user->id;
        }
        
        /*$user = User::findOne($uid);
        if( $user != null ){
            if( $user->profit_loss != 0 && $user->profit_loss != null ){
                $profit_loss = $user->profit_loss;
            }
        }*/
        $profit_loss = 100;
        
        $query = User::find()
        ->select( [ 'id' , 'name' , 'username' , 'profit_loss' ] )
        ->where( [ 'status' => [1,2] , 'parent_id'=> $uid , 'role' => 2 ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
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
                $profit_loss = $profit_loss-$usr['profit_loss'];
                $models[$i]['balance'] = $this->getAllClient($usr['id'],$profit_loss);
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    public function getAllClient($uid , $profit_loss)
    {
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
        //echo '<pre>';print_r($client);die;
        return $this->getProfitLoss($client,$profit_loss);
        
    }
    
    public function getProfitCash($uid){
        
        $cash = TempTransactionHistory::find()->select(['SUM(transaction_amount) as cash'])
        ->where(['user_id'=>$uid , 'transaction_type' => 'W/CASH'])
        ->asArray()->all();
        
        $cashVal = 0;
        
        if( $cash[0]['cash'] > 0 && $cash[0]['cash'] != null ){
            $cashVal = $cash[0]['cash'];
        }
        
        return $cashVal;
        
    }
    
    public function getMasterProfitLoss($pid){
        
        $role = \Yii::$app->authManager->getRolesByUser($pid);
        
        $total = 0;
        
        if( isset($role['agent2']) && $role['agent2'] != null ){
            
            $AllClients = $this->getAllClientForMaster($pid);
            
            $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['client_id'=>$AllClients ,'parent_id'=>$pid ,'transaction_type' => 'DEBIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $lossVal = 0;
            
            if( $loss[0]['loss'] > 0 && $loss[0]['loss'] != null ){
                $lossVal = $loss[0]['loss'];
            }
            
            $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['client_id'=>$AllClients,'parent_id'=>$pid ,'transaction_type' => 'CREDIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $profitVal = 0;
            
            if( $profit[0]['profit'] > 0 && $profit[0]['profit'] != null ){
                $profitVal = $profit[0]['profit'];
            }
            
            $total = $profitVal-$lossVal;
        }
        
        return $total;
        
    }
    
    public function getSuperMasterProfitLoss($pid){
        
        $role = \Yii::$app->authManager->getRolesByUser($pid);
        
        $total = 0;
        
        if( isset($role['agent1']) && $role['agent1'] != null ){
            
            $AllClients = $this->getAllClientForSuperMaster($pid);
            
            $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['client_id'=>$AllClients ,'parent_id'=>$pid ,'transaction_type' => 'DEBIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $lossVal = 0;
            
            if( $loss[0]['loss'] > 0 && $loss[0]['loss'] != null ){
                $lossVal = $loss[0]['loss'];
            }
            
            $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['client_id'=>$AllClients,'parent_id'=>$pid ,'transaction_type' => 'CREDIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $profitVal = 0;
            
            if( $profit[0]['profit'] > 0 && $profit[0]['profit'] != null ){
                $profitVal = $profit[0]['profit'];
            }
            
            $total = $profitVal-$lossVal;
        }
        
        return $total;
        
    }
    
    public function getAdminProfitLoss($pid){
        
        $role = \Yii::$app->authManager->getRolesByUser($pid);
        
        $total = 0;
        
        if( isset($role['admin']) && $role['admin'] != null ){
        
            $AllClients = $this->getAllClientForAdmin($pid);
            
            $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['client_id'=>$AllClients ,'user_id'=>$pid ,'transaction_type' => 'DEBIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $lossVal = 0;
            
            if( $loss[0]['loss'] > 0 && $loss[0]['loss'] != null ){
                $lossVal = $loss[0]['loss'];
            }
            
            $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['client_id'=>$AllClients ,'user_id'=>$pid ,'transaction_type' => 'CREDIT'])
            ->andWhere(['!=','event_id',0])
            ->asArray()->all();
            
            $profitVal = 0;
            
            if( $profit[0]['profit'] > 0 && $profit[0]['profit'] != null ){
                $profitVal = $profit[0]['profit'];
            }
            
            $total = $profitVal-$lossVal;
        }
        
        return $total;
        
    }
    
    public function getLossCash($uid){
        
        $cash = TempTransactionHistory::find()->select(['SUM(transaction_amount) as cash'])
        ->where(['user_id'=>$uid , 'transaction_type' => 'D/CASH'])
        ->asArray()->all();
        
        $cashVal = 0;
        
        if( $cash[0]['cash'] > 0 && $cash[0]['cash'] != null ){
            $cashVal = $cash[0]['cash'];
        }
        
        return $cashVal;
        
    }
    
    public function getProfitLoss($uid, $profit_loss){
        
        $pId = \Yii::$app->user->id;
        
        $profit_loss = (100-$profit_loss);
        
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            $AllClients = [$uid];
        }
        
        $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
        ->where(['user_id'=>$AllClients ,'transaction_type' => 'DEBIT'])
        ->andWhere(['!=','event_id',0])
        ->asArray()->all();
        
        $profitVal = 0;
        
        if( $profit[0]['profit'] > 0 && $profit[0]['profit'] != null ){
            $profitVal = $profit[0]['profit'];
        }
        
        $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
        ->where(['user_id'=>$AllClients,'transaction_type' => 'CREDIT'])
        ->andWhere(['!=','event_id',0])
        ->asArray()->all();
        
        $lossVal = 0;
        
        if( $loss[0]['loss'] > 0 && $loss[0]['loss'] != null ){
            $lossVal = $loss[0]['loss'];
        }
        
        $total = (-1)*( ($profitVal-$lossVal)*$profit_loss )/100;
        
        return $total;
        
    }
    
    // Get Commission
    public function getCommission($pid){
        
        $role = \Yii::$app->authManager->getRolesByUser($pid);
        
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($pid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($pid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($pid);
        }else{
            $AllClients = null;
        }
        
        $total = 0;
        
        if( $AllClients != null ){
        
        $loss = TempTransactionHistory::find()->select(['SUM(commission) as loss'])
        ->where(['client_id'=>$AllClients ,'user_id'=>$pid ,'transaction_type' => 'DEBIT'])
        ->andWhere(['!=','event_id',0])
        ->asArray()->all();
        
        $lossVal = 0;
        
        if( $loss[0]['loss'] > 0 && $loss[0]['loss'] != null ){
            $lossVal = $loss[0]['loss'];
        }
        
        $total = $lossVal;
        
        }
        
        return $total;
        
    }
    
    //AllClientForAdmin
    public function getAllClientForAdmin($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id','role'])->where(['parent_id'=>$uid , 'role'=> 2])->all();
        
        if($smdata != null){
            
            foreach ( $smdata as $sm ){
                
                // get all master
                $sm2data = User::find()->select(['id','role'])->where(['parent_id'=>$sm->id , 'role'=> 2])->all();
                if($sm2data != null){
                    
                    foreach ( $sm2data as $sm2 ){
                        // get all master
                        $m1data = User::find()->select(['id','role'])->where(['parent_id'=>$sm2->id , 'role'=> 3])->all();
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
    
    //AllClientForSuperMaster
    public function getAllClientForSuperMaster($uid)
    {
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
    
    //AllClientForMaster
    public function getAllClientForMaster($uid)
    {
        $client = [];
        
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
    
    
    // Super Master
    public function actionSuperMaster()
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
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }else{
            $uid = \Yii::$app->user->id;
        }
        
        $query = User::find()
        ->select( [ 'id' , 'name' , 'username' , 'profit_loss' ] )
        ->where( [ 'status' => [1,2] , 'parent_id'=> $uid , 'role' => 2 ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
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
                $balance = $this->getProfitLoss($usr['id'],$usr['profit_loss']);
                $profit_cash = $this->getProfitCash($usr['id']);
                $loss_cash = $this->getLossCash($usr['id']);
                $commission = $this->getCommission($uid);
                $admin_profit_loss = $this->getAdminProfitLoss($uid);
                $sm_profit_loss = $this->getSuperMasterProfitLoss($uid);
                $models[$i]['balance'] = $balance;
                $models[$i]['profit_cash'] = $profit_cash;
                $models[$i]['commission'] = $commission;
                $models[$i]['sm_profit_loss'] = $sm_profit_loss;
                $models[$i]['admin_profit_loss'] = $admin_profit_loss;
                $models[$i]['loss_cash'] = (-1)*$loss_cash;
                $i++;
            }
            
        }
        //echo '<pre>';print_r($models);die;
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    // Master
    public function actionMaster()
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
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }else{
            $uid = \Yii::$app->user->id;
        }
        
        $query = User::find()
        ->select( [ 'id' , 'name' , 'username' , 'profit_loss' ] )
        ->where( [ 'status' => [1,2] , 'parent_id'=> $uid , 'role' => 3 ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
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
                $balance = $this->getProfitLoss($usr['id'],$usr['profit_loss']);
                $profit_cash = $this->getProfitCash($usr['id']);
                $loss_cash = $this->getLossCash($usr['id']);
                $commission = $this->getCommission($uid);
                $admin_profit_loss = $this->getAdminProfitLoss($uid);
                $sm_profit_loss = $this->getSuperMasterProfitLoss($uid);
                $m_profit_loss = $this->getMasterProfitLoss($uid);
                $models[$i]['balance'] = $balance;
                $models[$i]['profit_cash'] = $profit_cash;
                $models[$i]['commission'] = $commission;
                $models[$i]['m_profit_loss'] = $m_profit_loss;
                $models[$i]['sm_profit_loss'] = $sm_profit_loss;
                $models[$i]['admin_profit_loss'] = $admin_profit_loss;
                $models[$i]['loss_cash'] = (-1)*$loss_cash;
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    // Client
    public function actionClient()
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
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }else{
            $uid = \Yii::$app->user->id;
        }
        
        $query = User::find()
        ->select( [ 'id' , 'name' , 'username' , 'profit_loss' ] )
        ->where( [ 'status' => [1,2] , 'parent_id'=> $uid , 'role' => 4 ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
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
                $balance = $this->getProfitLoss($usr['id'],$usr['profit_loss']);
                $profit_cash = $this->getProfitCash($usr['id']);
                $loss_cash = $this->getLossCash($usr['id']);
                $commission = $this->getCommission($uid);
                $admin_profit_loss = $this->getAdminProfitLoss($uid);
                $sm_profit_loss = $this->getSuperMasterProfitLoss($uid);
                $m_profit_loss = $this->getMasterProfitLoss($uid);
                $models[$i]['balance'] = $balance;
                $models[$i]['profit_cash'] = $profit_cash;
                $models[$i]['commission'] = $commission;
                $models[$i]['m_profit_loss'] = $m_profit_loss;
                $models[$i]['sm_profit_loss'] = $sm_profit_loss;
                $models[$i]['admin_profit_loss'] = $admin_profit_loss;
                $models[$i]['loss_cash'] = (-1)*$loss_cash;
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    // Clear Chips
    public function actionClearChips(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){
                    //echo '<pre>';print_r($user);die;
                    $amount = $r_data[ 'amount' ];
                    if( $r_data[ 'amount' ] < 0 ){
                        $amount = (-1)*$r_data[ 'amount' ];
                    }
                    
                    if( $r_data[ 'typ' ] == 'loss' ){
                        $user->profit_loss_balance = ($user->profit_loss_balance+$amount);
                    }else{
                        $user->profit_loss_balance = ($user->profit_loss_balance-$amount);
                    }
                    
                    if( $user->save( [ 'profit_loss_balance' ] ) ){
                        if( $this->clearChipsTransaction( $user , $amount , $r_data[ 'typ' ] ) == true ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Cash Deposit Successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! user not updeted!" ,
                                "data" => $user->errors
                            ];
                        }
                        
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
    
    
    // Clear Chips Transaction
    public function clearChipsTransaction($user , $amount , $typ){
        
        $pUser = User::findOne( $user->parent_id );
        $parentUserName = 'Not Set';
        if( $pUser != null ){
            $parentUserName = $pUser->name;
        }
        
        $userName = 'Not Set';
        if( $user != null ){
            $userName = $user->name;
        }
        
        $trans = new TransactionHistory();
        
        $trans->user_id = $user->id;
        $trans->child_id = 0;
        $trans->parent_id = $user->parent_id;
        $trans->event_id = $trans->market_id = 0;
        $trans->transaction_amount = $amount;
        $trans->is_cash = 1;
        
        if( $typ == 'loss' ){
            $trans->current_balance = $user->balance+$user->profit_loss_balance;
            $trans->transaction_type = 'CREDIT';
            $trans->description = 'Cash Deposit to '.$parentUserName.' by '.$userName;
        }else{
            $trans->current_balance = $user->balance-$user->profit_loss_balance;
            $trans->transaction_type = 'DEBIT';
            $trans->description = 'Cash Withdraw from '.$parentUserName.' by '.$userName;
        }
        
        $trans->status = 1;
        $trans->created_at = $trans->updated_at = time();
        
        if( $trans->save() ){
            return true;
        }else{
            return false;
        }
        
    }
    
    // Clear Chips Parent Transaction
    public function clearChipsParentTransaction($user , $amount , $typ){
        
        $parentUser = User::findOne( $user->parent_id );
        
        $trans = new TempTransactionHistory();
        
        $trans->user_id = $parentUser->id;
        $trans->client_id = 0;
        $trans->parent_id = $parentUser->parent_id;
        $trans->bet_id = $trans->event_id = 0;
        $trans->parent_type = 'F';
        $trans->username = $parentUser->name;
        $trans->transaction_amount = $amount;
        $trans->commission = 0;
        
        if( $typ == 'loss' ){
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance+$amount;
            
            $trans->current_balance = $parentUser->profit_loss_balance;
            $trans->transaction_type = 'W/CASH';
            $trans->description = 'Cash Withdraw from '.$user->name.' Rs.'.$amount;
        }else{
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance-$amount;
            
            $trans->current_balance = $parentUser->profit_loss_balance;
            $trans->transaction_type = 'D/CASH';
            $trans->description = 'Cash Deposit to '.$user->name.' Rs.'.$amount;
        }
        
        $trans->status = 1;
        $trans->created_at = $trans->updated_at = time();
        
        if( $trans->save() && $parentUser->save(['profit_loss_balance'])  ){
            return true;
        }else{
            return false;
        }
        
    }
    
    
}
