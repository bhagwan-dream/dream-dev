<?php
namespace api\modules\v1\modules\users\controllers;

use common\models\UserProfitLoss;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use common\models\User;
use common\models\TransactionHistory;
use common\models\EventsPlayList;
use common\models\ManualSession;

class HistoryController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','subadmin','agent1','agent2','client' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    //actionIndex
    public function actionIndex()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $userName = 'User Not Found!';
        $uid = \Yii::$app->user->id;
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }

        //$uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $AllClients = [];

        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }elseif(isset($role['subadmin']) && $role['subadmin'] != null){
            $uid = 1;
            $AllClients = $this->getAllClientForMaster($uid);
        }

        $user = User::find()->select(['name','username'])
            ->where(['id' => $uid , 'status' => [1,2] ])->asArray()->one();

        if( $user != null && $AllClients != null && count($AllClients) > 0){

            $userName = $user['name'].' [ '.$user['username'].' ]';

            if( json_last_error() == JSON_ERROR_NONE ){
                $filter_args = ArrayHelper::toArray( $data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;
            }

            $query = PlaceBet::find()
                ->select( [ 'id','market_id','sec_id','user_id','session_type','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate', 'size' , 'win' , 'loss' , 'bet_status' , 'status' ,'description', 'match_unmatch', 'created_at' ] )
                ->andWhere( [ 'status' => 1 , 'bet_status' => ['Win', 'Loss' , 'Canceled' ] ] )
                ->andWhere(['user_id' => $AllClients]);

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "description" , $filters[ "title" ] ] );
                }
                if( isset( $filters[ "user" ] ) && $filters[ "user" ] != '' ){
                    $query->andFilterWhere( [ "like" , "client_name" , $filters[ "user" ] ] );
                }
                if( isset( $filters[ "master" ] ) && $filters[ "master" ] != '' ){
                    $query->andFilterWhere( [ "like" , "master" , $filters[ "master" ] ] );
                }
                if( isset( $filters[ "type" ] ) && $filters[ "type" ] != '' ){
                    $query->andFilterWhere( [ "like" , "bet_type" , $filters[ "type" ] ] );
                }
                if( isset( $filters[ "start" ] ) && $filters[ "start" ] != '' ){
                    $startDate = strtotime($filters[ "start" ]);
                    $query->andFilterWhere( ['>=','created_at',$startDate] );
                }
                if( isset( $filters[ "end" ] ) && $filters[ "end" ] != '' ){
                    $endDate = strtotime($filters[ "end" ] . ' 23:59:59');
                    $query->andFilterWhere( ['<=','created_at',$endDate] );
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

                    if( $data['session_type'] == 'teenpatti' || $data['session_type'] == 'poker' || $data['session_type'] == 'andarbahar' ){
                        $round = $this->getRoundId($data['market_id']);
                        if( $data['session_type'] == 'poker' ){
                            $models[$i]['description'] = 'Poker > '.$data['runner'].' > Round #'.$round;
                        } elseif ( $data['session_type'] == 'andarbahar' ){
                            $models[$i]['description'] = 'Andar Bahar > '.$data['runner'].' > Round #'.$round;
                        } elseif ( $data['session_type'] == 'teenpatti' ){
                            $models[$i]['description'] = 'Teen Patti > '.$data['runner'].' > Round #'.$round;
                        }else{
                            $models[$i]['description'] = 'Teen Patti > '.$data['runner'].' > Round #'.$round;
                        }

                    }else{
                        //$description = str_replace('/','>',$data['description']);
                        $session = str_replace('_' , ' ',$data['session_type']);
                        $session = ucfirst($session);
                        $description = str_replace($data['runner'],$session.' > '.$data['runner'],$data['description']);
                        //$description = str_replace('>  >','>',$description);
                        $models[$i]['description'] = $description;
                        $models[$i]['rate'] = '0';
                    }
                    if( $data['session_type'] == 'fancy' || $data['session_type'] == 'fancy2' ){
                        $models[$i]['runner'] = 'RUN';
                        $models[$i]['rate'] = $data['rate'];
                    }

                    $i++;
                }

            }

            return [ "status" => 1 , "data" => [ "items" => $models , "user" => $userName , "count" => $count ] ];

        }else{

            return [ "status" => 1 , "data" => [ "items" => [] ,"user" => $userName ,  "count" => 0 ] ];

        }
    }

    //actionIndex
    public function getRoundId($marketId)
    {
        $round = 'not found';
        $event = (new \yii\db\Query())
            ->select(['round_id'])
            ->from('teen_patti_result')
            ->where(['market_id' => $marketId ])
            ->one();

        if( $event != null && $event['round_id'] != 0 ){
            $round = $event['round_id'];
        }
        return $round;
    }

    //actionIndex
    public function actionTrash()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $userName = 'User Not Found!';
        $uid = \Yii::$app->user->id;

        $user = User::find()->select(['name','username'])
            ->where(['id' => $uid , 'status' => [1,2] ])->asArray()->one();

        if( $user != null ){

            $userName = $user['name'].' [ '.$user['username'].' ]';

            if( json_last_error() == JSON_ERROR_NONE ){
                $filter_args = ArrayHelper::toArray( $data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;
            }

            $query = PlaceBet::find()
                ->select( [ 'id','market_id','sec_id','user_id','session_type','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' , 'status' ,'description', 'match_unmatch', 'created_at' ] )
                ->andWhere( [ 'status' => 0 , 'bet_status' => 'Pending' ] );

//            if( null !== \Yii::$app->request->get( 'id' ) ){
//                $query->andWhere( [ 'user_id' => \Yii::$app->request->get( 'id' ) ] );
//            }
//
//            $role = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->id);
//            if( !isset( $role['admin'] ) ){
//                $user = User::findOne(\Yii::$app->user->id);
//                if( $user != null ){
//                    $query->andWhere( [ 'master' => $user->username ] );
//                }
//            }

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

            $query->andWhere( [ 'status' => 0 , 'bet_status' => 'Pending' ] );

            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

            if( $models != null ){
                $i = 0;
                foreach ( $models as $data ){
                    $description = str_replace('/','>',$data['description']);
                    $description = str_replace('> ' .$data['session_type']. ' >',' > ',$description);
                    //$description = str_replace('>  >','>',$description);
                    $models[$i]['description'] = $description;
                    if( $data['session_type'] == 'fancy' || $data['session_type'] == 'fancy2' ){
                        $models[$i]['runner'] = 'RUN';
                    }

                    $i++;
                }

            }

            return [ "status" => 1 , "data" => [ "items" => $models , "user" => $userName , "count" => $count ] ];

        }else{

            return [ "status" => 1 , "data" => [ "items" => [] ,"user" => $userName ,  "count" => 0 ] ];

        }
    }

    // BookMarket
    public function actionBookMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            
            $pagination = $filter_args;
        }
        
        $eventId = $marketId = $marketName = null;
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            
            $event = EventsPlayList::findOne(['event_id' => \Yii::$app->request->get( 'id' ) , 'play_type' => 'IN_PLAY' , 'game_over' => 'NO' , 'status' => [1,2] ]);
            
            if( $event != null ){
                $eventId = \Yii::$app->request->get( 'id' );
                $where = ['event_id' => $eventId];
                
                $marketName = $event->event_name.'('.$event->event_league.')';
                
            }else{
                $manualSession = ManualSession::findOne(['id' => \Yii::$app->request->get( 'id' ) , 'game_over' => 'NO' , 'status' => [1,2] ]);
                if( $manualSession != null ){
                    $eventId = $manualSession->event_id;
                    $marketId = \Yii::$app->request->get( 'id' );
                    $where = ['event_id' => $eventId , 'market_id' => $marketId];
                    
                    $marketName = $manualSession->title;
                    
                    $event = EventsPlayList::findOne(['event_id' => $eventId ]);
                    
                    if( $event != null ){
                        $marketName = $marketName.' - '.$event->event_name.'('.$event->event_league.')';
                    }
                    
                }else{
                    return $response;
                }
            }
            
        }else{
            return $response;
        }
        
        $uid = \Yii::$app->user->id;
        
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);    
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            $AllClients = [];
        }
        
        $query = PlaceBet::find()
        ->select( [ 'id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' , 'status' , 'created_at' ] )
        ->where( [ 'user_id' => $AllClients,'status' => [1,2] ,'bet_status' => 'Pending' ] )
        ->andWhere($where);
        
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
        
        return [ "status" => 1 , "data" => [ "items" => $models , 'title' => $marketName , "count" => $count ] ];
        
    }
    
    //actionDelete
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( !isset($role['admin']) ){
            return $response;
        }

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $betData = PlaceBet::findOne( $r_data[ 'id' ] );
                if( $betData != null ){
                    $betData->status = 0;

                    if( $betData->save( [ 'status' ] ) ){
                        $this->newUpdateUserExpose($betData->user_id, $betData->market_id , $betData->session_type );
                        //UserProfitLoss::balanceValUpdate($event->user_id);
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Bet deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! history not deleted!" ,
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //actionStatus
    public function actionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) ){
                $model = PlaceBet::findOne( $r_data[ 'id' ] );
                if( $model != null ){
                    
                    if( $model->status == 1 ){
                        $model->status = 2;
                    }else{
                        $model->status = 1;
                    }
                    
                    if( $model->save( [ 'status' ] ) ){
                        
                        $sts = $model->status == 1 ? 'allow' : 'deny';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Bet $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet status not changed!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //actionChangeBetStatus
    public function actionChangeBetStatus(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $model = PlaceBet::findOne( ['id' => $r_data[ 'id' ] , 'bet_status' => 'PENNING' ] );
                if( $model != null ){
                    
                    $model->bet_status = $r_data[ 'sts' ];
                    
                    if( $model->save( [ 'bet_status' ] ) ){
                        
                        $sts = $model->bet_status;
                        
                        if( $sts == 'WIN' ){
                        
                            $this->commissionTransWin($model->id);
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Bet $sts successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $this->commissionTransLoss($model->id);
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Bet $sts successfully!"
                                ]
                            ];
                            
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet status not changed!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    public function actionView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        if( $id != null ){
            $model = PlaceBet::find()->select( [  'id' , 'user_id' , 'sec_id' , 'market_id' , 'price' , 'size', 'status' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    //AllClientForAdmin
    public function getAllClientForAdmin_NOTUSED($uid)
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
                                                $client[] = $c->id+0;
                                            }
                                        }
                                        
                                    }
                                }
                                
                                // get all client
                                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m1->id , 'role'=> 4])->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c->id+0;
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$sm->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id+0;
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
                                        $client[] = $c->id+0;
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m1->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id+0;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$sm->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id+0;
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
                                $client[] = $c->id+0;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id+0;
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c->id+0;
            }
        }
        
        return $client;
        
    }
    
    //AllClientForSuperMaster
    public function getAllClientForSuperMaster_NOTUSED($uid)
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
                                        $client[] = $c->id+0;
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m1->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id+0;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$sm->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id+0;
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
                                $client[] = $c->id+0;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id+0;
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c->id+0;
            }
        }
        
        return $client;
        
    }
    
    //AllClientForMaster
    public function getAllClientForMaster_NOTUSED($uid)
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
                                $client[] = $c->id+0;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id+0;
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c->id+0;
            }
        }
        
        return $client;
        
    }
    
    public function currentBalance($price = false)
    {
        $id = \Yii::$app->user->id;
        $user = User::findOne($id);
        
        $user->balance = ( $user->balance + $price );
        
        if( $user->save(['balance']) ){
            return $user->balance;
        }else{
            return $user->balance;
        }
    }
    
    /*public function actionCommissionTrans()
    {
        $this->commissionTransWin('1');
    }
    
    public function clientCommissionRate()
    {
        $CCR = 1;//$CCR = Client Commission Rate
        $setting = Setting::findOne([ 'key' => 'CLIENT_COMMISSION_RATE' , 'status' => 1 ]);
        if( $setting != null ){
            $CCR = $setting->value;
            return $CCR;
        }else{
            return $CCR;
        }
    }*/
    
    public function commissionTransWin($betID)
    {
        
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = ($model->win - $model->size) - $model->ccr;
        
        $client = User::findOne( $model->user_id );
        
        $client->balance = ( ( $client->balance + $model->win ) - $model->ccr );
        
        if( $client != null && $client->save(['balance']) ){
            
            $bet = $model;
            
            $trans = new TransactionHistory();
            
            $trans->user_id = \Yii::$app->user->id;
            $trans->bet_id = $bet->id;
            $trans->client_name = $bet->client_name;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $model->win-$model->ccr;
            $trans->current_balance = $client->balance;
            $trans->status = 1;
            $trans->updated_at = time();
            
            if( $client->parent_id === 1 ){
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance-$commission );
                
                if( $admin->save( ['balance'] ) && $trans->save() ){
                    return true;
                }else{
                    return false;
                }
                
            }else{
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null && $agent2->commission != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent2->commission )/100 );
                    
                    $agent2->balance = ( $agent2->balance-$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent2->save(['balance']) ){
                        
                        if( $agent2->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent2->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance-$commission );
                            
                            if( $admin->save( ['balance'] ) && $trans->save() ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null && $agent1->commission != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                
                                $agent1->balance = ( $agent1->balance-$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent1->save(['balance']) ){
                                    
                                    if( $agent1->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent1->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance-$commission );
                                        
                                        if( $admin->save( ['balance'] ) && $trans->save() ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        $agent = User::findOne( $agent1->parent_id );
                                        
                                        if( $agent != null && $agent->commission != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent->commission )/100 );
                                            
                                            $agent->balance = ( $agent->balance-$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent->save(['balance']) ){
                                                
                                                $admin = User::findOne( $agent->parent_id );
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance-$commission );
                                                
                                                if( $admin->save( ['balance'] ) && $trans->save() ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                            
                                        }else{
                                            return false;
                                        }
                                        
                                    }
                                    
                                }else{
                                    return false;
                                }
                                
                                
                            }else{
                                return false;
                            }
                            
                        }
                        
                        
                    }else{
                        return false;
                    }
                    
                }else{
                    return false;
                }
                
            }
            
        }
    }
    
    public function commissionTransLoss($betID)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = $model->loss;
        
        $client = User::findOne( $model->user_id );
        
        if( $client != null ){
            
            if( $client->parent_id === 1 ){
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance+$commission );
                
                if( $admin->save( ['balance'] ) ){
                    return true;
                }else{
                    return false;
                }
                
            }else{
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null && $agent2->commission != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent2->commission )/100 );
                    
                    $agent2->balance = ( $agent2->balance+$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent2->save(['balance']) ){
                        
                        if( $agent2->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent2->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance+$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null && $agent1->commission != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                
                                $agent1->balance = ( $agent1->balance+$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent1->save(['balance']) ){
                                    
                                    if( $agent1->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent1->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance+$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        $agent = User::findOne( $agent1->parent_id );
                                        
                                        if( $agent != null && $agent->commission != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent->commission )/100 );
                                            
                                            $agent->balance = ( $agent->balance+$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent->save(['balance']) ){
                                                
                                                $admin = User::findOne( $agent->parent_id );
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance+$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                            
                                        }else{
                                            return false;
                                        }
                                        
                                    }
                                    
                                }else{
                                    return false;
                                }
                                
                                
                            }else{
                                return false;
                            }
                            
                        }
                        
                        
                    }else{
                        return false;
                    }
                    
                }else{
                    return false;
                }
                
            }
            
        }
    }
    
    /*public function commissionTrans($betID)
    {
        
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        if( $model->bet_status == 'WIN' ){
            
            $amount = ($model->win - $model->size);
            
            $client = User::findOne( $model->user_id );
            
            $client->balance = ( $client->balance+$model->win );
            
            if( $client != null && $client->save(['balance']) ){
                
                if( $client->parent_id === 1 ){
                    
                    $admin = User::findOne( $client->parent_id );
                    
                    if( $admin != null ){
                        
                        $commission = $amount;
                        
                        $admin->balance = ( $admin->balance-$commission );
                        
                        if( $admin->save( ['balance'] ) ){
                            return true;
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null ){
                    
                    /*if( $agent2->parent_id === 1 && $agent2->commission != 0 ){
                        
                        $admin = User::findOne( $agent2->parent_id );
                        
                        if( $admin != null ){
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance-$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }*/
                    
                    /*if( $agent2->commission != 0 ){
                    
                        $commission1 = round ( ( $amount*$agent2->commission )/100 );
                        
                        $agent2->balance = ( $agent2->balance-$commission1 );
                        
                        $amount = ( $amount-$commission1 );
                        
                        if( $agent2->save( ['balance'] ) ){
                        
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null ){
                                
                                if( $agent1->parent_id == 1 && $agent1->commission != 0 ){
                                    
                                    $admin = User::findOne( $agent1->parent_id );
                                    
                                    if( $admin != null ){
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance-$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                }
                                
                                if( $agent1->commission != 0 ){
                                
                                    $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                    
                                    $agent1->balance = ( $agent1->balance-$commission2 );
                                    
                                    $amount = ( $amount-$commission2 );
                                    
                                    if( $agent1->save( ['balance'] ) ){
                                    
                                        if( $agent1->parent_id === 1 ){
                                        
                                            $admin = User::findOne( $agent1->parent_id );
                                            
                                            if( $admin != null ){
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance-$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                        }else{
                                            
                                            $agent1 = User::findOne( $agent1->parent_id );
                                            
                                            if( $agent1 != null ){
                                                
                                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                                
                                                $agent1->balance = ( $agent1->balance-$commission2 );
                                                
                                                $amount = ( $amount-$commission2 );
                                                
                                                if( $agent1->save( ['balance'] ) ){
                                                    
                                                    $admin = User::findOne( $agent1->parent_id );
                                                    
                                                    if( $admin != null ){
                                                        
                                                        $commission = $amount;
                                                        
                                                        $admin->balance = ( $admin->balance-$commission );
                                                        
                                                        if( $admin->save( ['balance'] ) ){
                                                            return true;
                                                        }else{
                                                            return false;
                                                        }
                                                        
                                                    }else{
                                                        return false;
                                                    }
                                                    
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                        }
                                    }else{
                                        return false;
                                    }
                                }else{
                                    return false;
                                }
                            }else{
                                return false;
                            }
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }
                        
            }else{
                return false;
            }
            
        }else{
            
            $amount = $model->loss;
            
            $client = User::findOne( $model->user_id );
            
            if( $client != null ){
                
                if( $client->parent_id === 1 ){
                    
                    $admin = User::findOne( $client->parent_id );
                    
                    if( $admin != null ){
                        
                        $commission = $amount;
                        
                        $admin->balance = ( $admin->balance+$commission );
                        
                        if( $admin->save( ['balance'] ) ){
                            return true;
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null ){
                    
                    if( $agent2->parent_id === 1 && $agent2->commission != 0 ){
                        
                        $admin = User::findOne( $agent2->parent_id );
                        
                        if( $admin != null ){
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance+$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }
                    
                    if( $agent2->commission != 0 ){
                        
                        $commission1 = round ( ( $amount*$agent2->commission )/100 );
                        
                        $agent2->balance = ( $agent2->balance+$commission1 );
                        
                        $amount = ( $amount-$commission1 );
                        
                        if( $agent2->save( ['balance'] ) ){
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null ){
                                
                                if( $agent1->parent_id === 1 && $agent1->commission != 0 ){
                                    
                                    $admin = User::findOne( $agent1->parent_id );
                                    
                                    if( $admin != null ){
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance+$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                }
                                
                                if( $agent1->commission != 0 ){
                                    
                                    $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                    
                                    $agent1->balance = ( $agent1->balance+$commission2 );
                                    
                                    $amount = ( $amount-$commission2 );
                                    
                                    if( $agent1->save( ['balance'] ) ){
                                        
                                        if( $agent1->parent_id === 1 ){
                                            
                                            $admin = User::findOne( $agent1->parent_id );
                                            
                                            if( $admin != null ){
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance+$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                        }else{
                                            
                                            $agent1 = User::findOne( $agent1->parent_id );
                                            
                                            if( $agent1 != null ){
                                                
                                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                                
                                                $agent1->balance = ( $agent1->balance+$commission2 );
                                                
                                                $amount = ( $amount-$commission2 );
                                                
                                                if( $agent1->save( ['balance'] ) ){
                                                    
                                                    $admin = User::findOne( $agent1->parent_id );
                                                    
                                                    if( $admin != null ){
                                                        
                                                        $commission = $amount;
                                                        
                                                        $admin->balance = ( $admin->balance+$commission );
                                                        
                                                        if( $admin->save( ['balance'] ) ){
                                                            return true;
                                                        }else{
                                                            return false;
                                                        }
                                                        
                                                    }else{
                                                        return false;
                                                    }
                                                    
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                        }
                                    }else{
                                        return false;
                                    }
                                }else{
                                    return false;
                                }
                            }else{
                                return false;
                            }
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }
                
            }else{
                return false;
            }
            
        }
    }*/
}
