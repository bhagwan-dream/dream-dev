<?php
namespace api\modules\v1\modules\users\controllers;

use Yii;
use yii\helpers\ArrayHelper;

use common\models\User;
use common\models\EventsPlayList;
use common\models\MarketType;
use common\models\ManualSession;
//use common\models\PlaceBet;
use api\modules\v1\modules\users\models\PlaceBet;
use common\models\TransactionHistory;
use common\models\UserProfitLoss;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionMatchOddData;
use common\models\EventsRunner;
use yii\db\Query;

class DashbordControllerOLD extends \common\controllers\aController
{
    private $marketIdsArr = [];
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent1','agent2' , 'sessionuser' , 'sessionuser2' , 'subadmin'],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    //action Market Suspend
    public function actionMarketSuspend(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ] ) 
                && isset( $r_data[ 'suspended' ] )){
                
                    if( $r_data[ 'type' ] == 'match_odd' ){
                        $event = EventsPlayList::findOne( ['market_id' => $r_data[ 'id' ]] );
                    }else if( $r_data[ 'type' ] == 'match_odd2' ){
                        $event = ManualSessionMatchOdd::findOne( ['market_id' => $r_data[ 'id' ]] );
                    }else if( $r_data[ 'type' ] == 'fancy' ){
                        $event = ManualSession::findOne( ['market_id' => $r_data[ 'id' ]] );
                    }else if( $r_data[ 'type' ] == 'fancy2' ){
                        $event = MarketType::findOne( ['market_id' => $r_data[ 'id' ]] );
                    }else{
                        $event = null;
                    }
                
                if( $event != null ){
                    $event->suspended = 'N';
                    if( $r_data[ 'suspended' ] == 'N' ){
                        $event->suspended = 'Y';
                    }
                    $event->updated_at = time();
                    if( $event->save() ){
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Save successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Save not changed!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //actionEventList New
    public function actionEventList()
    {

        $today = date('Ymd');
        $tomorrow = date('Ymd' , strtotime($today . ' +1 day') );

        $eventData = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['game_over'=>'NO','status'=>1 ])
            ->andWhere(['>' , 'event_time' , strtotime($today)*1000 ])
            ->orderBy(['event_time' => SORT_ASC])
            ->all();

        $dataList = [];
        if( $eventData != null ){
            $blockList = $this->checkUnBlockStatus();
            
            $today = date('Ymd');
            $tomorrow = date('Ymd' , strtotime($today . ' +1 day') );
            $dataList = $data = [];
            
            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            
            if( !isset($role['admin']) && !isset($role['subadmin']) && !isset($role['sessionuser']) ){
                $dataList['isFootball'] = ['sportId'=> 1 , 'status' => $this->checkUnBlockSport(1)];
                $dataList['isTennis'] = ['sportId'=> 2 , 'status' => $this->checkUnBlockSport(2)];
                $dataList['isCricket'] = ['sportId'=> 4 , 'status' => $this->checkUnBlockSport(4)];
            }else{
                $dataList['isFootball'] = ['sportId'=> 1 , 'status' => $this->checkUnBlockSport(1)];
                $dataList['isTennis'] = ['sportId'=> 2 , 'status' => $this->checkUnBlockSport(2)];
                $dataList['isCricket'] = ['sportId'=> 4 , 'status' => $this->checkUnBlockSport(4)];
            }
            
            foreach ( $eventData as $event ){
                
                $eventDate = date('Ymd',( $event['event_time']/1000 ));
                if( $today == $eventDate || $tomorrow == $eventDate ){
                    
                    $status = 'unblock';
                    
                    //print_r($blockList);die;
                    
                    if( in_array( $event['event_id'], $blockList )){
                        $status = 'block';
                    }
                    
                    if( !isset($role['admin']) && !in_array( $event['event_id'], $this->checkUnBlockList() ) ){

                        $betCount = 0;$clients = [];

                        if( isset($role['agent1']) ){
                            $clients = $this->getAllClientForSuperMaster($uid);
                        }elseif( isset($role['agent2']) ){
                            $clients = $this->getAllClientForMaster($uid);
                        }

                        $betCount = (new \yii\db\Query())
                            ->select(['id'])
                            ->from('place_bet')
                            ->where(['event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
                            ->andWhere(['IN','user_id',$clients])
                            ->count();

                        $eventName = $event['event_name'];

                        if( $betCount > 0 ){
                            $eventName = $event['event_name'] . ' ( Total Bets: '.$betCount.' )';
                        }


                        $cache = \Yii::$app->cache;
                        $data = $cache->get($event['market_id']);
                        $data = json_decode($data);
                        
                        if( $event['sport_id'] == '1' ){
                            $dataList['football'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '2' ){
                            $dataList['tennis'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '4' ){
                            $dataList['cricket'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                    }
                    if( isset($role['admin']) || isset($role['subadmin']) ){

                        $betCount = 0;

                        $betCount = (new \yii\db\Query())
                            ->select(['id'])
                            ->from('place_bet')
                            ->where(['event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
                            ->count();

                        $eventName = $event['event_name'];

                        if( $betCount > 0 ){
                            $eventName = $event['event_name'] . ' ( Total Bets: '.$betCount.' )';
                        }


                        $cache = \Yii::$app->cache;
                        $data = $cache->get($event['market_id']);
                        $data = json_decode($data);
                        
                        if( $event['sport_id'] == '1' ){
                            $dataList['football'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '2' ){
                            $dataList['tennis'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '4' ){
                            $dataList['cricket'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                    }
                    
                    if( !isset($role['admin']) && in_array( 1, $this->checkUnBlockSportList() ) ){
                        $dataList['football'] = [];
                        $dataList['isFootballBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                    if( !isset($role['admin']) && in_array( 2, $this->checkUnBlockSportList() ) ){
                        $dataList['tennis'] = [];
                        $dataList['isTennisBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                    if( !isset($role['admin']) && in_array( 4, $this->checkUnBlockSportList() ) ){
                        $dataList['cricket'] = [];
                        $dataList['isCricketBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                }
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $dataList ] ];
        
    }
    
    //actionEventDetails New
    public function actionEventDetails()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $isBookButton = true;
            $eventId = \Yii::$app->request->get( 'id' );

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( isset($role['subadmin']) ){
                $uid = 1;
            }

            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                $uid = $r_data['id'];

                $role = \Yii::$app->authManager->getRolesByUser($uid);

                if( isset($role['client']) ){
                    $isBookButton = false;
                }

            }

            if( in_array($eventId, $this->checkUnBlockList() ) ){
                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];
                return $response;exit;
            }

            $cUser = User::find()->select(['name','username'])->where(['id'=>$uid])->asArray()->one();
            $cUserName = 'No User Found!';
            if( $cUser != null ){
                //$cUserName = $cUser['name'].' [ '.$cUser['username'].' ] ';
                $cUserName = $cUser['username'];
            }

            $eventData = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id'=> $eventId,'game_over'=>'NO','status'=>1])
            ->createCommand(Yii::$app->db1)->queryOne();

            $eventArr = $betList = [];
            if( $eventData != null ){
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];
                if( $sportId == '1' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Football',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];
                    
                }else if( $sportId == '2' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Tennis',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];
                }else if( $sportId == '4' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $matchodd2Data = $this->getDataManualMatchOdd($uid,$eventId);
                    $fancy2Data = $this->getDataFancy($uid,$eventId);
                    $fancyData = $this->getDataManualSessionFancy($uid,$eventId);
                    $lotteryData = $this->getDataLottery($uid,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'match_odd2' => $matchodd2Data,
                        'fancy2' => $fancy2Data,
                        'fancy' => $fancyData,
                        'lottery' => $lotteryData
                    ];
                }
                
                $betList = $this->getBetList($uid,$eventId);
                
                $this->marketIdsArr[] = [
                    'type' => 'match_odd',
                    'market_id' => $marketId,
                    'event_id' => $eventId
                ];
                
                $response = [ "status" => 1 , "data" => [ "items" => $eventArr , 'betList' => $betList,  'marketIdsArr' => $this->marketIdsArr , 'cUserName' => $cUserName , 'isBookButton' => $isBookButton ] ];
                
            }else{
                
                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];
                
            }
            
            
        }
        
        return $response;
    }
    
    //actionEventBetList
    public function actionEventBetList()
    {
        $pagination = []; $filters = [];

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $models = [];$type = null;$title = 'No Title';
        if( null != \Yii::$app->request->get( 'id' ) ){

            $eventId = \Yii::$app->request->get( 'id' );
            $marketTypeArr = ['match_odd'=>'Match Odd' , 'match_odd2'=>'Book Maker' , 'fancy'=>'Fancy' , 'fancy2'=>'Fancy 2' , 'lottery' => 'Lottery'];
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                $type = trim($r_data['type']);

                $filter_args = ArrayHelper::toArray( $request_data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;

            }
            
            $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eventId , 'status' => 1])->asArray()->one();
            
            if( $event != null ){
                $title = $event['event_name'].' - '.$marketTypeArr[$type];
                if( $type == null ){
                    $title = $event['event_name'];
                }
            }
            
            $where = ['session_type' => $type,'event_id' => $eventId , 'bet_status' => 'Pending'];
            
            $query = PlaceBet::find()
            ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
            ->where($where);
            
            //$user = User::findOne(\Yii::$app->user->id);
            //$role = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->id);
            
            //if( !isset( $role['admin'] ) ){

                $uid = \Yii::$app->user->id;

                if( isset($r_data['uid']) && $r_data['uid'] != null ){
                    $uid = trim($r_data['uid']);
                }

                $role = \Yii::$app->authManager->getRolesByUser($uid);

                if( isset($role['subadmin']) ){
                    $uid = 1;
                }
                
            //}

            $countQuery = clone $query; $count =  $countQuery->count();
            
            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                    $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
                }
            }

            if( isset($role['agent1']) ){
                $allUser = $this->getAllClientForSuperMaster($uid);

                $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

            }else if( isset($role['agent2']) ){

                $allUser = $this->getAllClientForSuperMaster($uid);

                $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

            }else if( isset($role['client']) ){
                $query->andWhere( [ 'user_id' => $uid ] );
            }

            $query->andWhere([ "status" => 1 , 'session_type' => $type,'event_id' => $eventId ]);

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

            $uid = \Yii::$app->user->id;

            if( isset($r_data['uid']) && $r_data['uid'] != null ){
                $uid = trim($r_data['uid']);
            }

            $user = User::find()->select(['name','username'])->where(['id' => $uid])->asArray()->one();

            if( $user != null ){
                $title = $user['name'].' [ '.$user['name'].' ] - '.$title;
            }

            $response = [ "status" => 1 , "data" => [ "title" => $title,"items" => $models , "count" => $count ] ];
        }
        
        return $response;
        
    }

    //actionTrashBetList
    public function actionTrashBetList()
    {
        $pagination = []; $filters = [];

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $models = [];$type = null;$title = 'No Title';
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );
            $marketTypeArr = ['match_odd'=>'Match Odd' , 'match_odd2'=>'Book Maker' , 'fancy'=>'Fancy' , 'fancy2'=>'Fancy 2' , 'lottery' => 'Lottery'];
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                $type = trim($r_data['type']);

                $filter_args = ArrayHelper::toArray( $request_data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;

            }

            $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eventId , 'status' => 1])->asArray()->one();

            if( $event != null ){
                $title = $event['event_name'].' - '.$marketTypeArr[$type];
                if( $type == null ){
                    $title = $event['event_name'];
                }
            }


            $query = PlaceBet::find()
                ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                ->where(['event_id' => $eventId]);

            //$user = User::findOne(\Yii::$app->user->id);
            $role = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->id);

            if( !isset( $role['admin'] ) ){

                $uid = \Yii::$app->user->id;
                if(isset($role['agent1']) && $role['agent1'] != null){
                    $allUser = $this->getAllClientForSuperMaster($uid);
                }else{
                    $allUser = $this->getAllClientForMaster($uid);
                }

                $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

            }

            $countQuery = clone $query; $count =  $countQuery->count();

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                    $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
                }
            }

            $query->andWhere([ "status" => 0 , 'bet_status' => 'Pending' ]);

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

            $response = [ "status" => 1 , "data" => [ "title" => $title,"items" => $models , "count" => $count ] ];
        }

        return $response;

    }

    // BookMarket for Event Id
    public function getBetList($uid,$eventId)
    {
        $items = [];
        
        $where = ['event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
        
        $query = PlaceBet::find()
        ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
        ->where($where);
        
        //$user = User::findOne(\Yii::$app->user->id);
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        
        if( !isset( $role['admin'] ) ){
            
            //$uid = \Yii::$app->user->id;
            $allUser = [];
            if(isset($role['agent1']) && $role['agent1'] != null) {
                $allUser = $this->getAllClientForSuperMaster($uid);
            }else if(isset($role['agent2']) && $role['agent2'] != null){
                $allUser = $this->getAllClientForMaster($uid);
            }else{
                array_push( $allUser  , $uid );
            }
            
            $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );
            
        }
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        
        $matchOddMatchData = $matchOddUnMatchData = $matchOdd2MatchData = $matchOdd2UnMatchData = $fancyMatchData = $fancyUnMatchData = $fancy2MatchData = $fancy2UnMatchData = $lotteryData =[];
        $matchOddMatchData2 = $matchOddUnMatchData2 = $matchOdd2MatchData2 = $matchOdd2UnMatchData2 = $fancyMatchData2 = $fancyUnMatchData2 = $fancy2MatchData2 = $fancy2UnMatchData2 = $lotteryData2 =[];
        
        if( $models != null ){
            $i = 0;
            foreach ( $models as $data ){
                
                if( count($matchOddMatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 1 ){
                        $matchOddMatchData[] = $data;
                    }
                }
                if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 1 ){
                    $matchOddMatchData2[] = $data;
                }
                
                
                if( count($matchOddUnMatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 0 ){
                        $matchOddUnMatchData[] = $data;
                    }
                }
                
                if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 0 ){
                    $matchOddUnMatchData2[] = $data;
                }
                
                
                if( count($matchOdd2MatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 1 ){
                        $matchOdd2MatchData[] = $data;
                    }
                }
                
                if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 1 ){
                    $matchOdd2MatchData2[] = $data;
                }
                
                
//                 if( count($matchOdd2UnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 0 ){
//                         $matchOdd2UnMatchData[] = $data;
//                     }
//                 }
//                 $matchOdd2UnMatchData2[] = $data;
                
                if( count($fancyMatchData) < 10 ){
                    if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 1 ){
                        $fancyMatchData[] = $data;
                    }
                }
                if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 1 ){
                    $fancyMatchData2[] = $data;
                }
                
                
//                 if( count($fancyUnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 0 ){
//                         $fancyUnMatchData[] = $data;
//                     }
//                 }
//                 $fancyUnMatchData2[] = $data;
                
                if( count($fancy2MatchData) < 10 ){
                    if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 1 ){
                        $fancy2MatchData[] = $data;
                    }
                }
                if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 1 ){
                    $fancy2MatchData2[] = $data;
                }
                
                
//                 if( count($fancy2UnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 0 ){
//                         $fancy2UnMatchData[] = $data;
//                     }
//                 }
//                 $fancy2UnMatchData2[] = $data;
                
                if( count($lotteryData) < 10 ){
                    if( $data['session_type'] == 'lottery' ){
                        $lotteryData[] = $data;
                    }
                }
                if( $data['session_type'] == 'lottery' ){
                    $lotteryData2[] = $data;
                }
                
                
                $i++;
            }
            
        }
        
        $matchOddMatchDataArr = $matchOddUnMatchDataArr = $matchOdd2MatchDataArr = $matchOdd2UnMatchDataArr = $fancyMatchDataArr = $fancyUnMatchDataArr = $fancy2MatchDataArr = $fancy2UnMatchDataArr = $lotteryDataArr = [];
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = 0;
        if( $matchOddMatchData != null ){
            $count1 = count( $matchOddMatchData2 );
            $matchOddMatchDataArr = [ 'data'=>$matchOddMatchData , 'count' => $count1  ];
        }
        if( $matchOddUnMatchData != null ){
            $count2 = count( $matchOddUnMatchData2 );
            $matchOddUnMatchDataArr = [ 'data'=>$matchOddUnMatchData , 'count' => $count2  ];
        }
        if( $matchOdd2MatchData != null ){
            $count3 = count( $matchOdd2MatchData2 );
            $matchOdd2MatchDataArr = [ 'data'=>$matchOdd2MatchData , 'count' => $count3  ];
        }
//         if( $matchOdd2UnMatchData != null ){
//             $matchOdd2UnMatchDataArr = [ 'data'=>$matchOdd2UnMatchData , 'count' => count( $matchOdd2UnMatchData2 )  ];
//         }
        if( $fancyMatchData != null ){
            $count4 = count( $fancyMatchData2 );
            $fancyMatchDataArr = [ 'data'=>$fancyMatchData , 'count' => $count4  ];
        }
//         if( $fancyUnMatchData != null ){
//             $fancyUnMatchDataArr = [ 'data'=>$fancyUnMatchData , 'count' => count( $fancyUnMatchData2 )  ];
//         }
        if( $fancy2MatchData != null ){
            $count5 = count( $fancy2MatchData2 );
            $fancy2MatchDataArr = [ 'data'=>$fancy2MatchData , 'count' => $count5  ];
        }
//         if( $fancy2UnMatchData != null ){
//             $fancy2UnMatchDataArr = [ 'data'=>$fancy2UnMatchData , 'count' => count( $fancy2UnMatchData2 )  ];
//         }
        if( $lotteryData != null ){
            $count6 = count( $lotteryData2 );
            $lotteryDataArr = [ 'data'=>$lotteryData , 'count' => $count6  ];
        }
        
        $items = [
            'matchOddMatchData' => $matchOddMatchDataArr,
            'matchOddUnMatchData' => $matchOddUnMatchDataArr,
            'matchOdd2MatchData' => $matchOdd2MatchDataArr,
            //'matchOdd2UnMatchData' => $matchOdd2UnMatchDataArr,
            'fancyMatchData' => $fancyMatchDataArr,
            //'fancyUnMatchData' => $fancyUnMatchDataArr,
            'fancy2MatchData' => $fancy2MatchDataArr,
            //'fancy2UnMatchData' => $fancy2UnMatchDataArr,
            'lotteryData' => $lotteryDataArr,
        ];
        
        return $items;
        
    }
    
    //Event: getDataMatchOdd
    public function getDataMatchOdd($uid,$marketId,$eventId)
    {
        $marketListArr = null;
        
        $event = (new \yii\db\Query())
        ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit','bet_delay'])
        ->from('events_play_list')
        ->where(['game_over'=>'NO','status'=>1])
        ->andWhere(['market_id' => $marketId])->createCommand(Yii::$app->db1)->queryOne();
        //echo '<pre>';print_r($event);die;
        if( $event != null ){
            
            $slug = ['1' => 'football' , '2' => 'tennis' , '4' => 'cricket'];
            
            $marketId = $event['market_id'];
            $eventId = $event['event_id'];
            $time = $event['event_time'];
            $sportId = $event['sport_id'];
            $suspended = $event['suspended'];
            $ballRunning = $event['ball_running'];
            $minStack = $event['min_stack'];
            $maxStack = $event['max_stack'];
            $maxProfit = $event['max_profit'];
            $maxProfitLimit = $event['max_profit_limit'];
            $maxProfitAllLimit = $event['max_profit_all_limit'];
            $betDelay = $event['bet_delay'];
            
            //$isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
            
            $runnerData = (new \yii\db\Query())
            ->select(['selection_id','runner'])
            ->from('events_runners')
            ->where(['event_id' => $eventId ])
            ->createCommand(Yii::$app->db1)->queryAll();
            
            if( $runnerData != null ){
                
                $cache = \Yii::$app->cache;
                $oddsData = $cache->get($marketId);
                $oddsData = json_decode($oddsData);
                if( $oddsData != null && isset($oddsData->odds) ){
                    $i=0;
                    foreach ( $oddsData->odds as $odds ){
                        
                        $back[$i] = [
                            'price' => $odds->backPrice1,
                            'size' => $odds->backSize1,
                        ];
                        $lay[$i] = [
                            'price' => $odds->layPrice1,
                            'size' => $odds->laySize1,
                        ];
                        $i++;
                    }
                }
                
                $i=0;
                foreach( $runnerData as $runner ){
                    if( !isset( $back[$i] ) ){
                        $back[$i] = [
                            'price' => '-',
                            'size' => ''
                        ];
                    }
                    if( !isset( $lay[$i] ) ){
                        $lay[$i] = [
                            'price' => '-',
                            'size' => ''
                        ];
                    }
                    
                    //$back = $lay = ['price' => ' - ', 'size' => ' - '];
                    $runnerName = $runner['runner'];
                    $selectionId = $runner['selection_id'];
                    
                    $runnersArr[] = [
                        'slug' => $slug[$sportId],
                        'sportId' => $sportId,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'selectionId' => $selectionId,
                        //'is_favorite' => $isFavorite,
                        'runnerName' => $runnerName,
                        'profit_loss' => $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$selectionId,'match_odd'),
                        'is_profitloss' => 0,
                        'sessionType' => 'match_odd',
                        'exchange' => [
                            'back' => $back[$i],
                            'lay' => $lay[$i],
                        ]
                    ];
                    $i++;
                }
                
            }
            
            $marketListArr = [
                'sportId' => $sportId,
                'slug' => $slug[$sportId],
                'sessionType' => 'match_odd',
                'marketId' => $marketId,
                'eventId' => $eventId,
                'suspended' => $suspended,
                'ballRunning' => $ballRunning,
                //'is_favorite' => $isFavorite,
                'time' => $time,
                'marketName'=>'Match Odds',
                'matched' => '',
                'minStack' => $minStack,
                'maxStack' => $maxStack,
                'maxProfit' => $maxProfit,
                'maxProfitLimit' => $maxProfitLimit,
                'maxProfitAllLimit' => $maxProfitAllLimit,
                'betDelay' => $betDelay,
                'runners' => $runnersArr,
            ];
            
            
        }
        
        return $marketListArr;
    }

    //Event: getDataManualMatchOdd
    public function getDataManualMatchOdd($uid,$eventId)
    {
        $items = null;
        
        $market = (new \yii\db\Query())
        ->select(['id','event_id','market_id','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
        ->from('manual_session_match_odd')
        ->where(['status' => 1 , 'game_over' => 'NO' , 'event_id' => $eventId])
        ->createCommand(Yii::$app->db1)->queryOne();
        //echo '<pre>';print_r($market);die;
        $runners = [];
        if($market != null){
            
            $marketId = $market['market_id'];
            $minStack = $market['min_stack'];
            $maxStack = $market['max_stack'];
            $maxProfit = $market['max_profit'];
            $maxProfitLimit = $market['max_profit_limit'];
            $betDelay = $market['bet_delay'];

            $suspended = $market['suspended'];
            $ballRunning = $market['ball_running'];

            
            $matchOddData = (new \yii\db\Query())
            ->select(['id','sec_id','runner','lay','back','suspended','ball_running'])
            ->from('manual_session_match_odd_data')
            ->andWhere( [ 'market_id' => $marketId ] )
            ->createCommand(Yii::$app->db1)->queryAll();
            
            if( $matchOddData != null ){
                foreach( $matchOddData as $data ){

                    if( $suspended != 'Y' ){
                        $suspended = $data['suspended'];
                    }else{
                        $suspended = 'N';
                    }
                    if( $ballRunning != 'Y' ){
                        $ballRunning = $data['suspended'];
                    }else{
                        $ballRunning = 'N';
                    }

                    $profitLoss = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$data['sec_id'],'match_odd2');
                    $runners[] = [
                        'id' => $data['id'],
                        'sportId' => 4,
                        'market_id' => $marketId,
                        'event_id' => $eventId,
                        'sec_id' => $data['sec_id'],
                        'runner' => $data['runner'],
                        'profitloss' => $profitLoss,
                        'is_profitloss' => 0,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'lay' => [
                            'price' => $data['lay'],
                            'size' => $data['lay'] == 0 ? '-' : rand(1234,9999),
                        ],
                        'back' => [
                            'price' => $data['back'],
                            'size' => $data['back'] == 0 ? '-' : rand(1234,9999),
                        ]
                    ];
                }
            }
            
            $items = [
                'marketName' => 'Book Maker Market 0% Commission ',
                'sportId' => 4,
                'market_id' => $marketId,
                'event_id' => $eventId,
                'suspended' => $market['suspended'],
                'ballRunning' => $market['ball_running'],
                //'is_favorite' => $this->isFavorite($eventId,$marketId,'match_odd2'),
                'minStack' => $minStack,
                'maxStack' => $maxStack,
                'maxProfit' => $maxProfit,
                'maxProfitLimit' => $maxProfitLimit,
                'betDelay' => $betDelay,
                'runners' => $runners,
            ];
            
            $this->marketIdsArr[] = [
                'type' => 'match_odd2',
                'market_id' => $marketId,
                'event_id' => $eventId
            ];
        }
        
        return $items;
    }
    
    //Event: getDataFancy
    public function getDataFancy($uid,$eventId)
    {
        $items = [];
        
        $marketList = (new \yii\db\Query())
        ->select('*')
        ->from('market_type')
        ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
        ->createCommand(Yii::$app->db1)->queryAll();
        //echo '<pre>';print_r($marketList);die;
        $items = [];
        
        if( $marketList != null ){
            
            foreach ( $marketList as $market ){
                
                $marketId = $market['market_id'];
                $suspended = $market['suspended'];
                $ballRunning = $market['ball_running'];
                $minStack = $market['min_stack'];
                $maxStack = $market['max_stack'];
                $maxProfit = $market['max_profit'];
                $maxProfitLimit = $market['max_profit_limit'];
                $betDelay = $market['bet_delay'];
                $isBook = $this->isBookOn($uid,$marketId,'fancy2');
                $maxLoss = $this->getMaxLossOnFancy($uid,$isBook,$eventId,$marketId,'fancy2');
                
                $key = $marketId;
                $cache = \Yii::$app->cache;
                $data = $cache->get($key);
                $data = json_decode($data);
                //echo '<pre>';print_r($data);die;
                
                if( $data != null && isset( $data->data ) ){

                    if( $ballRunning != 'Y' ){
                        $ballRunning = $data->ballRunning;
                    }
                    if( $suspended != 'Y' ){
                        $suspended = $data->suspended;
                    }


                    $dataVal = $data->data;
                    
                }else{
                    $suspended = 'Y';
                    $dataVal = [
                        'no' => 0,
                        'no_rate' => 0,
                        'yes' => 0,
                        'yes_rate' => 0,
                    ];
                }
                
                $items[] = [
                    'market_id' => $market['market_id'],
                    'event_id' => $market['event_id'],
                    'marketName' => $market['market_name'],
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    //'status' => $status,
                    'sportId' => 4,
                    'slug' => 'cricket',
                    'is_book' => $isBook,
                    //'is_favorite' => $this->isFavorite($market['event_id'],$market['market_id'],'fancy2'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'maxLoss' => $maxLoss,
                    'data' => $dataVal,
                ];
                
                
                $this->marketIdsArr[] = [
                    'type' => 'fancy2',
                    'market_id' => $market['market_id'],
                    'event_id' => $eventId
                ];
            }
            
        }
        
        
        return $items;
    }
    
    //Event: getDataManualSessionFancy
    public function getDataManualSessionFancy($uid,$eventId)
    {
        $items = [];

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('manual_session')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->all();
        //echo '<pre>';print_r($models);die;
        $items = [];
        if($marketList != null){
            $dataVal = [];
            foreach($marketList as $data){

                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];
                $isBook = $this->isBookOn($uid,$data['market_id'],'fancy');
                $maxLoss = $this->getMaxLossOnFancy($uid,$isBook,$eventId,$data['market_id'],'fancy');

                $dataVal = [
                    'no' => $data['no'],
                    'no_rate' => $data['no_rate'],
                    'yes' => $data['yes'],
                    'yes_rate' => $data['yes_rate'],
                ];

                $items[] = [
                    'id' => $data['id'],
                    'event_id' => $data['event_id'],
                    'market_id' => $data['market_id'],
                    'title' => $data['title'],
                    'suspended' => $data['suspended'],
                    'ballRunning' => $data['ball_running'],
                    'sportId' => 4,
                    'slug' => 'cricket',
                    'is_book' => $this->isBookOn($uid,$data['market_id'],'fancy'),
                    //'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'fancy'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'maxLoss' => $maxLoss,
                    'data' => $dataVal,

                ];

                $this->marketIdsArr[] = [
                    'type' => 'fancy',
                    'market_id' => $data['market_id'],
                    'event_id' => $eventId
                ];
            }
        }

        return $items;
    }

    //Event: getDataLottery
    public function getDataLottery($uid,$eventId)
    {
        $items = [];
        
        $marketList = (new \yii\db\Query())
        ->select(['id','market_id','event_id','title','rate','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
        ->from('manual_session_lottery')
        ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
        ->all();
        //echo '<pre>';print_r($marketList);die;
        $items = [];
        if($marketList != null){
            foreach($marketList as $data){
                
                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];
                
                $numbers = [];
                for($n=0;$n<10;$n++){
                    
                    $profitLoss = $this->getProfitLossOnBetLottery($uid,$data['event_id'], $data['market_id'] , $n );
                    
                    $numbers[] = [
                        'id' => $n,
                        'sec_id' => $n,
                        'number' => $n,
                        'rate' => $data['rate'],
                        'profitloss' => $profitLoss
                    ];
                }
                
                $items[] = [
                    'id' => $data['id'],
                    'sportId' => 4,
                    'event_id' => $data['event_id'],
                    'market_id' => $data['market_id'],
                    'title' => $data['title'],
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    //'is_book' => $this->isBookOn($data['market_id'],'lottery'),
                    //'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'lottery'),
                    'numbers' => $numbers,
                ];
                
                /*$this->marketIdsArr[] = [
                    'type' => 'lottery',
                    'market_id' => $data['market_id'],
                    'event_id' => $eventId
                ];*/
                
            }
        }
        
        return $items;
    }
    
    //action BlockUnblock Event
    public function actionBlockUnblockEvent()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $AllUser = [];
            $uId = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uId);
            
            if( isset($role['admin']) && $role['admin'] != null ){
                $AllUser = $this->getAllUserForAdmin($uId);
            }elseif(isset($role['agent1']) && $role['agent1'] != null){
                $AllUser = $this->getAllUserForSuperMaster($uId);
            }elseif(isset($role['agent2']) && $role['agent2'] != null){
                $AllUser = $this->getAllUserForMaster($uId);
            }
            array_push($AllUser,$uId);
            if( $AllUser != null ){
                //echo '<pre>';print_r($AllUser);die;

                $marketId = $r_data['market_id'];
                $eventId = $r_data['event_id'];
                $type = $r_data['type'];


                foreach ( $AllUser as $user ){
                    
                    $where = [
                        'user_id' => $user,
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'market_type' => $type,
                        'byuser' => $uId
                    ];
                    
                    $check = (new \yii\db\Query())
                    ->select('*')->from('event_market_status')
                    ->where($where)->one();
                    
                    if( $check != null ){
                        
                        \Yii::$app->db->createCommand()
                        ->delete('event_market_status', $where)
                        ->execute();
                        $message = "Event Block successfully!";
                        
                    }else{
                        
                        \Yii::$app->db->createCommand()
                        ->insert('event_market_status', $where )->execute();
                        
                        $message = "Event Unblock successfully!";
                        
                    }
                    
                }

                $url = 'http://52.208.223.36/api/betfair/activate_b/'.$marketId;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $responseData = curl_exec($ch);
                curl_close($ch);

                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => $message
                    ]
                ];
                
            }
            
        }
        
        return $response;
    }
    
    //action BlockUnblock Sport
    public function actionBlockUnblockSport()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $AllUser = [];
            $uId = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uId);
            
            if( isset($role['admin']) && $role['admin'] != null ){
                $AllUser = $this->getAllUserForAdmin($uId);
            }elseif(isset($role['agent1']) && $role['agent1'] != null){
                $AllUser = $this->getAllUserForSuperMaster($uId);
            }elseif(isset($role['agent2']) && $role['agent2'] != null){
                $AllUser = $this->getAllUserForMaster($uId);
            }
            array_push($AllUser,$uId);
            if( $AllUser != null ){
                //echo '<pre>';print_r($AllUser);die;
                foreach ( $AllUser as $user ){
                    
                    $sportId = $r_data['sport_id'];
                    
                    $where = [
                        'user_id' => $user,
                        'sport_id' => $sportId,
                        'byuser' => $uId
                    ];
                    
                    $check = (new \yii\db\Query())
                    ->select('*')->from('event_status')
                    ->where($where)->one();
                    
                    if( $check != null ){
                        
                        \Yii::$app->db->createCommand()
                        ->delete('event_status', $where)
                        ->execute();
                        $message = "Sport Block successfully!";
                        
                    }else{
                        
                        \Yii::$app->db->createCommand()
                        ->insert('event_status', $where )->execute();
                        
                        $message = "Sport Unblock successfully!";
                        
                    }
                    
                }
                
                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => $message
                    ]
                ];
                
            }
            
        }
        
        return $response;
    }
    
    //check database function
    public function checkUnBlockList()
    {
        $uId = \Yii::$app->user->id;

        $newList = [];
        
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$uId,'market_type' => 'all' ])
        ->andWhere(['!=','byuser',$uId])->all();
        
        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
        }
        return $newList;
        
    }
    
    //check database function
    public function checkUnBlockStatus()
    {
        $uId = \Yii::$app->user->id;

        $role = \Yii::$app->authManager->getRolesByUser($uId);
        if( isset( $role['subadmin'] ) || isset( $role['sessionuser'] ) ){
            $uId = 1;
        }

        $newList = [];
        
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$uId,'market_type' => 'all' , 'byuser' => $uId ])
        ->all();
        
        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
        }
        return $newList;
        
    }
    
    //check database function
    public function checkUnBlocMarketkList($uid,$eventId,$marketId,$type)
    {
        //$uid = \Yii::$app->user->id;
        $market = (new \yii\db\Query())
        ->select(['market_id'])->from('event_market_status')
        ->where(['user_id'=>$uid,'event_id'=>$eventId,'market_id'=>$marketId,'market_type' => $type,'byuser'=>$uid ])->one();
        
        if( $market != null ){
            return true;
        }else{
            return false;
        }
        
    }
    
    //check database function
    public function checkUnBlockSport($sportId)
    {
        $uid = \Yii::$app->user->id;
        $market = (new \yii\db\Query())
        ->select(['sport_id'])->from('event_status')
        ->where(['user_id'=>$uid,'sport_id'=>$sportId,'byuser'=>$uid ])->one();
        
        if( $market != null ){
            return 'block';
        }else{
            return 'unblock';
        }
        
    }
    
    //check database function
    public function checkUnBlockSportList()
    {
        $uId = \Yii::$app->user->id;
        
        $newList = [];
        
        $listArr = (new \yii\db\Query())
        ->select(['sport_id'])->from('event_status')
        ->where(['user_id'=>$uId ])
        ->andWhere(['!=','byuser',$uId])->all();
        
        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }
        }
        return $newList;
        
    }
    
    //check database function
    public function checkUnBlockListUser()
    {
        $uId = \Yii::$app->user->id;
        
        $newList = [];
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$uId,'market_type' => 'all','byuser'=>$uId ])->all();
        
        if( $listArr != null ){
            
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
            
        }
        
        return $newList;
        
    }
    
    // Cricket: isBookOn
    public function isBookOn($uid,$marketId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $AllClients = [];
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            array_push( $AllClients , $uid );
        }
        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $where = [ 'bet_status' => 'Pending','status' => 1,'session_type' => $sessionType,'market_id' => $marketId ];
            $andWhere = ['IN','user_id', $AllClients];
            
            $findBet = (new \yii\db\Query())
            ->select(['id'])->from('place_bet')
            ->where($where)->andWhere($andWhere)
            ->one();
            
            if( $findBet != null ){
                return '1';
            }
            return '0';
            
        }
        
    }
    
    // Cricket: User Market BookList
    public function actionUserMarketBookList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $titleData = $userData = $totalData = [];
            if( isset( $r_data['event_id'] ) && isset( $r_data['user_id'] ) ){
                
                if( $r_data['user_id'] != '' || $r_data['user_id'] != null ){
                    $uid = $r_data['user_id'];
                }else{
                    $uid = \Yii::$app->user->id;
                    $role = \Yii::$app->authManager->getRolesByUser($uid);
                    if( isset( $role['subadmin'] ) ){
                        $uid = 1;
                    }
                }
                
                $eventId = $r_data['event_id'];
                $userList = User::findAll([ 'parent_id' => $uid , 'status' => 1 ]);
                //echo '<pre>';print_r($userList);die;

                if( isset( $r_data['typ'] ) && $r_data['typ'] == 'match_odd' ){

                    $sessionTyp = $r_data['typ'];

                    $eventRunner = EventsRunner::findAll(['event_id' => $eventId]);
                    $runners = [];$marketId = '';
                    if( $eventRunner != null ){

                        foreach ( $eventRunner as $runner ){

                            $marketId = $runner->market_id;

                            $runners[] = [
                                'secid' => $runner->selection_id,
                                'name' => $runner->runner
                            ];

                        }

                    }

                }

                if( isset( $r_data['typ'] ) && $r_data['typ'] == 'match_odd2' ){

                    $sessionTyp = $r_data['typ'];

                    $bookMaker = ManualSessionMatchOdd::find()->select(['market_id'])->where(['event_id' => $eventId])->one();

                    if( $bookMaker != null ){

                        $eventRunner = ManualSessionMatchOddData::findAll(['market_id' => $bookMaker->market_id ]);
                        $runners = [];$marketId = '';
                        if( $eventRunner != null ){

                            foreach ( $eventRunner as $runner ){

                                $marketId = $runner->market_id;

                                $runners[] = [
                                    'secid' => $runner->sec_id,
                                    'name' => $runner->runner
                                ];

                            }

                        }

                    }

                }

                
                if( $userList != null && $runners != null ){
                    $runner1 = $runner2 = $runner3 = 0;
                    $runnerName1 = $runnerName2 = $runnerName3 = '';
                    
                    if( isset( $runners[0]['name'] ) ){
                        $runnerName1 = $runners[0]['name'];
                    }
                    if( isset( $runners[1]['name'] ) ){
                        $runnerName2 = $runners[1]['name'];
                    }
                    if( isset( $runners[2]['name'] ) ){
                        $runnerName3 = $runners[2]['name'];
                    }
                    
                    $titleData = [
                        '0' => 'User Name',
                        '1' => $runnerName1,
                        '2' => $runnerName2,
                        '3' => $runnerName3,
                    ];
                    $t1 = $t2 = $t3 = null;
                    foreach ( $userList as $user ){
                        
                        if( isset( $runners[0]['secid'] ) ){
                            $runner1 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                            $t1[] = round($runner1,2);
                        }
                        if( isset( $runners[1]['secid'] ) ){
                            $runner2 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                            $t2[] = round($runner2,2);
                        }
                        if( isset( $runners[2]['secid'] ) ){
                            $runner3 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                            $t3[] = round($runner3,2);
                        }
                        
                        if( !($runner1 == 0 && $runner2 == 0 && $runner3 == 0) ){
                            $userData[] = [
                                //'username' => $user->name.'  ( '.$user->username.' )',
                                'username' => $user->username,
                                'id' => $user->id,
                                'runner1' => round($runner1,2),
                                'runner2' => round($runner2,2),
                                'runner3' => round($runner3,2),
                            ];
                        }
                        
                    }
                    
                    if( $t1 != null ){ $t1 = array_sum($t1); $t1 = round( $t1 , 2 );}
                    if( $t2 != null ){ $t2 = array_sum($t2); $t2 = round( $t2 , 2 );}
                    if( $t3 != null ){ $t3 = array_sum($t3); $t3 = round( $t3 , 2 );}
                    
                    //My P&L
                    $m1 = $m2 = $m3 = null;

                    if( isset( $runners[0]['secid'] ) ){
                        $runner1 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                        $m1[] = round($runner1,2);
                    }
                    if( isset( $runners[1]['secid'] ) ){
                        $runner2 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                        $m2[] = round($runner2,2);
                    }
                    if( isset( $runners[2]['secid'] ) ){
                        $runner3 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                        $m3[] = round($runner3,2);
                    }
                    
                    if( $m1 != null ){ $m1 = array_sum($m1); $m1 = round( $m1 , 2 ); }
                    if( $m2 != null ){ $m2 = array_sum($m2); $m2 = round( $m2 , 2 ); }
                    if( $m3 != null ){ $m3 = array_sum($m3); $m3 = round( $m3 , 2 ); }

                    //Parent P&L
                    $parent = User::findOne(['id' => $uid , 'status' => 1]);

                    $p1 = $p2 = $p3 = null;
                    if($parent != null){

                        if( isset( $runners[0]['secid'] ) ){
                            $runner1 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                            $p1[] = round($runner1,2);
                        }
                        if( isset( $runners[1]['secid'] ) ){
                            $runner2 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                            $p2[] = round($runner2,2);
                        }
                        if( isset( $runners[2]['secid'] ) ){
                            $runner3 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                            $p3[] = round($runner3,2);
                        }
                    }

                    if( $p1 != null ){ $p1 = array_sum($p1); $p1 = round( $p1 , 2 ); }
                    if( $p2 != null ){ $p2 = array_sum($p2); $p2 = round( $p2 , 2 ); }
                    if( $p3 != null ){ $p3 = array_sum($p3); $p3 = round( $p3 , 2 ); }


//                    $totalData = [
//                        '0' => ['My P&L' , $m1 , $m2 , $m3],
//                        '1' => ['Parent P&L' , $p1 , $p2 , $p3],
//                        '2' => ['User P&L' , ( $t1-$m1 ) , ( $t2-$m2 ) , ( $t3-$m3 ) ],
//                        '3' => [ 'Total' , $t1 , $t2 , $t3 ],
//                    ];

//                    $role = \Yii::$app->authManager->getRolesByUser($uid);
//
//                    if( isset( $role['admin'] )){
//                        $totalData = [
//                            '0' => ['My P&L' , $m1 , $m2 , $m3],
//                            //'1' => ['Parent P&L' , $p1 , $p2 , $p3],
//                            '1' => ['User P&L' , ( $t1-$m1 ) , ( $t2-$m2 ) , ( $t3-$m3 ) ],
//                            '2' => [ 'Total' , $t1 , $t2 , $t3 ],
//                        ];
//                    }

                }
                
                $response = [ "status" => 1 , "data" => [ 'titalData' => $titleData , 'userData' => $userData , 'totalData' => $totalData ] ];
                
            }
            
        }
        return $response;
    }


    // Cricket: Get ProfitLossFancy API
    public function actionGetProfitLossFancyBook()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];

            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['subadmin']) || isset($role['sessionuser'])){
                $uid = 1;
            }
            
            if( isset( $r_data['user_id'] ) ){
                $uid = $r_data['user_id'];
            }
            
            
            $profitLossData = $this->getProfitLossFancy($uid,$eventId,$marketId,$sessionType);
            
            $response = [ 'status' => 1 , 'data' => $profitLossData ];
            
        }
        
        return $response;
    }
    
    // Cricket: Get ProfitLossManualFancy API
    public function actionGetProfitLossManualFancyBook()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];
            
            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['subadmin']) || isset($role['sessionuser'])){
                $uid = 1;
            }
            
            if( isset( $r_data['user_id'] ) ){
                $uid = $r_data['user_id'];
            }
            
            $profitLossData = $this->getProfitLossFancy($uid,$eventId,$marketId,$sessionType);
            
            $response = [ 'status' => 1 , 'data' => $profitLossData ];
            
        }
        
        return $response;
    }
    
    public function getMaxLossOnFancy($uid,$isBook,$eventId, $marketId, $sessionType)
    {
        $maxLoss = 0;
        if( $isBook == 1 ){
            $dataArr = $this->getProfitLossFancy($uid,$eventId, $marketId, $sessionType);
            if( $dataArr != null ){
                $maxLossArr = [];
                foreach ( $dataArr as $data ){
                    if( $data['profitLoss'] < 0 ){
                        $maxLossArr[] = $data['profitLoss'];
                    }
                }
                
                if( $maxLossArr != null ){
                    $maxLoss = min($maxLossArr);
                }
            }
        }
        
        return round($maxLoss,2);
        
    }
    
    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancy($uid,$eventId,$marketId,$sessionType)
    {
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $dataReturn = null;
        $AllClients = [];
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            array_push( $AllClients , $uid );
        }
        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            $total = 0;
            
            //echo $marketId;die;
            $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
            $andWhere = ['IN','user_id', $AllClients];

            $betList = PlaceBet::find()
                ->select(['bet_type', 'price', 'win', 'loss'])
                ->where($where)->andWhere($andWhere)->asArray()->all();

//            $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            $minRun = $maxRun = 0;
//            if( isset( $betMinRun['price'] ) ){
//                $minRun = $betMinRun['price']-1;
//            }
//
//            if( isset( $betMaxRun['price'] ) ){
//                $maxRun = $betMaxRun['price']+1;
//            }
            
            //echo $minRun.' - '.$maxRun;die;
            
            if( $betList != null ){
                //if( $minRun != 0 && $maxRun != 0 ){

                $min = 0;
                $max = 0;

                foreach ($betList as $index => $bet) {
                    if ($index == 0) {
                        $min = $bet['price'];
                        $max = $bet['price'];
                    }
                    if ($min > $bet['price'])
                        $min = $bet['price'];
                    if ($max < $bet['price'])
                        $max = $bet['price'];
                }

                $min = $min-1;
                $max = $max+1;

                for($i=$min;$i<=$max;$i++){

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList1 = $command->queryAll();


                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList2 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList3 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList4 = $command->queryAll();

                    if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                    if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                    if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                    if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }

                    $profit = ( $winVal1 + $winVal2 );
                    $loss = ( $lossVal1 + $lossVal2 );

                    $total = $loss-$profit;

                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => round($total,2),
                    ];
                }

            }
                
            
        }

        if( $dataReturn != null ){

            $dataReturnNew = [];

            $i = 0;
            $start = 0;
            $startPl = 0;
            $end = 0;

            foreach ( $dataReturn as $index => $d) {

                if ($index == 0) {
                    $dataReturnNew[] = [ 'price' => $d['price'] . ' or less' , 'profitLoss' => $d['profitLoss'] ];

                } else {
                    if ($startPl != $d['profitLoss']) {
                        if ($end != 0) {

                            if( $start == $end ){
                                $priceVal = $start;
                            }else{
                                $priceVal = $start . ' - ' . $end;
                            }

                            $dataReturnNew[] = [ 'price' => $priceVal , 'profitLoss' => $startPl ];
                        }

                        $start = $d['price'];
                        $end = $d['price'];

                    } else {
                        $end = $d['price'];
                    }
                    if ($index == (count($dataReturn) - 1)) {
                        $dataReturnNew[] = [ 'price' => $start . ' or more' , 'profitLoss' => $startPl ];
                    }

                }

                $startPl = $d['profitLoss'];
                $i++;

            }

            $dataReturn = $dataReturnNew;

        }

        
        return $dataReturn;
    }
    
    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetMatchOddsOLDDD($uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $AllClients = [];
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            //array_push($AllClients , $uid);
            $AllClients = [];
        }
        
        //echo '<pre>';print_r($userId);die;
        
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){
                
                if( $marketId != null && $eventId != null && $selId != null){
                    
                    // IF RUNNER WIN
                    
                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == null ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($layWin[0]['val']);die;
                    
                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == null ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }
                    
                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
                    //echo '<pre>';print_r($layLoss[0]['val']);die;
                    
                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == null ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    //echo '<pre>';print_r($backLoss[0]['val']);die;
                    
                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == null ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }
                    
                    $totalLoss = $backLoss + $layLoss;
                    $total = $totalWin-$totalLoss;
                    
                }

                
                if( $total != null && $total != 0 ){

                    $role = \Yii::$app->authManager->getRolesByUser($client);

                    if((isset($role['client']) && $role['client'] != null) ){
                        $totalArr[] = $total;
                    }else{
                        $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                            ->where(['client_id' => $client, 'user_id' => $uid, 'status' => 1])->one();

                        if ($profitLoss != null && $profitLoss->actual_profit_loss > 0) {
                            $total = ($total * $profitLoss->actual_profit_loss) / 100;
                            $totalArr[] = $total;
                        }
                    }
                    
                }
                
            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){

                $role = \Yii::$app->authManager->getRolesByUser($client);

                if(isset($role['client']) && $role['client'] != null){
                    return array_sum($totalArr);
                }else{
                    return (-1)*array_sum($totalArr);
                }


            }else{
                return '0';
            }
            
        }
        
        
    }
    
    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        
        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            array_push( $AllClients , $uid );
        }
        
        //echo '<pre>';print_r($AllClients);die;

        $AllClients = array_unique($AllClients);

        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            $total = 0;

            foreach ( $AllClients as $client ){
                
                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){
                    
                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($backWin[0]['val']);die;
                    
                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    //echo '<pre>';print_r($layWin[0]['val']);die;
                    
                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }
                    
                    $totalWin = $backWin + $layWin;
                    
                    // IF RUNNER LOSS
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($layLoss[0]['val']);die;
                    
                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    //echo '<pre>';print_r($backLoss[0]['val']);die;
                    
                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }
                    
                    $totalLoss = $backLoss + $layLoss;
                    
                    $total = $totalWin-$totalLoss;
                    
                }

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                    ->where(['client_id'=>(int)$client,'user_id'=>(int)$uid,'status'=>1])->one();

//                    if( isset($role['admin']) ){
//                        if($total > 0){
//                            $total_temp = ($total*(float)$profitLoss->actual_profit_loss)/100;
//                            echo ' ** '.(float)$profitLoss->actual_profit_loss . ' -> ' . $client . ' * ' .$total.' -> '.$total_temp . ' -> '.$selId.' || ';
//                        }
//
////                        var_dump( $profitLoss);
////                        echo $client . '<br />';
//                    }

                    if( $profitLoss != null && (float)$profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*(float)$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }


                    
                }
                
            }
            
            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return round((-1)*array_sum($totalArr),2);
            }else{
                return '0';
            }
            
        }

    }

    // Cricket: get Profit Loss On Bet User Parent
    public function getProfitLossOnBetMatchOddsUserParent($pid,$uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        if( $pid == 0 ){
            return '0';
        }

        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['admin']) && $role['admin'] != null ){
            $AllClients = $this->getAllClientForAdmin($uid);
        }elseif(isset($role['agent1']) && $role['agent1'] != null){
            $AllClients = $this->getAllClientForSuperMaster($uid);
        }elseif(isset($role['agent2']) && $role['agent2'] != null){
            $AllClients = $this->getAllClientForMaster($uid);
        }else{
            array_push( $AllClients , $uid );
        }

        //echo '<pre>';print_r($AllClients);die;
        $AllClients = array_unique($AllClients);
        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($backLoss[0]['val']);die;

                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }

                    $totalLoss = $backLoss + $layLoss;

                    $total = $totalWin-$totalLoss;

                }

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>$client,'user_id'=>$pid,'status'=>1])->one();

                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        // TODO changed by guest
                        //$total = $total;
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }

        }


    }

    // Cricket: get Profit Loss On Bet Lottery
    public function getProfitLossOnBetLottery($uid,$eventId,$marketId,$selId)
    {
        //$uid = \Yii::$app->user->id;
        
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
        
        //echo '<pre>';print_r($userId);die;
        
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){
                
                // IF RUNNER WIN
                if( $marketId != null && $eventId != null && $selId != null){
                    
                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => 'lottery' ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($backWin[0]['val']);die;
                    
                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    // IF RUNNER LOSS
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => 'lottery','user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    //echo '<pre>';print_r($backLoss[0]['val']);die;
                    
                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }
                    
                    $total = $backWin-$backLoss;
                    
                }
                
                if( $total != null && $total != 0 ){
                    
                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                    ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();
                    
                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }
                    
                }
                
            }
            
            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }
            
        }
        
        
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
    
    //actionGetBalance
    public function actionGetBalance()
    {
        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset( $role['sessionuser'] ) ){
            return [ "status" => 1 , "data" => [ "balance" => 0 , "profit_loss" =>  0 ] ];
        }

        if( isset( $role['subadmin'] ) ){
            $uid = 1;
        }

        $profitLoss = 0;

        //Own Comm
        $OwnComm = 0;

        $pComm = TransactionHistory::find()->select(['SUM(transaction_amount) as pComm'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        if (isset($pComm['pComm'])) {
            $OwnComm = $pComm['pComm'];
        }

        //Own Cash
        $OwnCash = 0;

        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->andWhere(['event_id'=> 0])
            ->asArray()->one();

        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->andWhere(['event_id'=> 0])
            ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }

        $pProfit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['parent_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->andWhere(['event_id'=> 0])
            ->asArray()->one();

        $pLoss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['parent_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->andWhere(['event_id'=> 0])
            ->asArray()->one();

        $pProfitVal = $pLossVal = 0;
        if (isset($pProfit['profit'])) {
            $pProfitVal = $pProfit['profit'];
        }

        if (isset($pLoss['loss'])) {
            $pLossVal = $pLoss['loss'];
        }

        $OwnCash = $profitVal - $lossVal + $pProfitVal - $pLossVal;

        //Own PL
        $OwnPl = 0;

        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }

        $OwnPl = $profitVal - $lossVal;

        if( $OwnPl > 0 ){
            $profitLoss = $OwnPl - $OwnComm;
        }else{
            $profitLoss = $OwnPl + $OwnComm;
        }

        //$profitLoss = $OwnPl-$OwnComm+$OwnCash;
        //$profitLoss = $OwnPl;

        $user = User::findOne($uid);

        if( $user != null ){
            $user->profit_loss_balance = $profitLoss;
            $user->save();
            return [ "status" => 1 , "data" => [ "balance" => $user->balance , "profit_loss" => round($profitLoss) ] ];
        }
        return [ "status" => 1 , "data" => [ "balance" => 0 , "profit_loss" =>  0 ] ];
        
    }
}
