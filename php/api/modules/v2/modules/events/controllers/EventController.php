<?php
namespace api\modules\v2\modules\events\controllers;

use yii\helpers\ArrayHelper;
use common\models\Event;
use common\models\PlaceBet;
use common\models\TransactionHistory;
use yii\helpers\Url;
use common\models\MarketType;
use common\models\User;
use common\models\Setting;
use common\models\EventsPlayList;
use common\models\ManualSession;
use common\models\BallToBallSession;
use common\models\GlobalCommentary;
use common\models\ManualSessionLottery;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionLotteryNumbers;
use common\models\ManualSessionMatchOddData;
use common\models\FavoriteMarket;

class EventController extends \common\controllers\aController  // \yii\rest\Controller
{
    protected $apiUserToken = '15727-8puafDrdScO1Rn';//'13044-CgPWGpYSAOn7aV';
    protected $apiUserId = '5bf52bb732f91';//'5bcb17c84f03a';
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'client' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    public function actionList()
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
        
        $query = Event::find()
            ->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'event_slug' , 'img' , 'icon' ,  'created_at' , 'updated_at' , 'status' ] )
            //->from( Events::tableName() . ' e' )
            ->andWhere( [ 'status' => 1 ] );
        
        $countQuery = clone $query; $count =  $countQuery->count();

        $models = $query->orderBy( [ "event_slug" => SORT_ASC ] )->asArray()->all();
        
        $i = 0;
        foreach ( $models as $img ){
            $imgpath = Url::base(true).'/uploads/events/default.jpg';
            if( $img['img'] != '' ){
                $imgpath = Url::base(true).'/uploads/events/'.$img['img'];
            }
            if( $img['icon'] != '' ){
                $iconpath = Url::base(true).'/uploads/events/icon/'.$img['icon'];
            }
            $models[$i]['img'] = $imgpath;
            $models[$i]['icon'] = $iconpath;
            $i++;
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models ] ];
        
    }
    
    /*
     * Event Setting
     */
    
    public function actionEventSetting()
    {
        $response = [ "status" => 1 , "data" => [] ];
        
        $event = Event::find()
        ->select(['event_type_id','event_type_name','event_slug','min_stack','max_stack','max_profit','bet_delay'])
        ->where(['status'=>1 ])->asArray()->all();
        if( $event != null ){
            $response = [ "status" => 1 , "data" => $event ];
        }
        return $response;
    }
    
    // action Check BetDelay
    public function actionCheckBetDelay()
    {
        $response = [ "status" => 1 , "data" => ['bet_delay' => '0' ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $betDelay = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $uid = \Yii::$app->user->id;
            $sportId = $r_data['sport_id'];
            $eventId = $r_data['event_id'];
            $marketId = $r_data['market_id'];
            
            $sport = (new \yii\db\Query())
            ->select(['bet_delay'])->from('events')
            ->where(['event_type_id'=>$sportId ])->one();
            
            if( $sport != null ){
                array_push($betDelay, $sport['bet_delay']);
            }
            
            $user = (new \yii\db\Query())
            ->select(['bet_delay'])->from('user')
            ->where(['id'=>$uid ])->one();
            
            if( $user != null ){
                array_push($betDelay, $user['bet_delay']);
            }
            
            $event = (new \yii\db\Query())
            ->select(['bet_delay'])->from('events_play_list')
            ->where(['event_id'=>$eventId ])->one();
            
            if( $event != null ){
                array_push($betDelay, $event['bet_delay']);
            }
            
            $matchOdd2 = (new \yii\db\Query())
            ->select(['bet_delay'])->from('manual_session_match_odd')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $matchOdd2 != null ){
                array_push($betDelay, $matchOdd2['bet_delay']);
            }
            
            $fancy = (new \yii\db\Query())
            ->select(['bet_delay'])->from('manual_session')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $fancy != null ){
                array_push($betDelay, $fancy['bet_delay']);
            }
            
            $fancy2 = (new \yii\db\Query())
            ->select(['bet_delay'])->from('market_type')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $fancy2 != null ){
                array_push($betDelay, $fancy2['bet_delay']);
            }
            
            $response = [ "status" => 1 , "data" => ['bet_delay' => max($betDelay) ] ];
            
        }
        
        return $response;
    }
    
    /*
     * My Profit Loss
     */
    
    public function actionMyProfitLoss()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $pagination = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( null !== \Yii::$app->user->id ){
            $uid = \Yii::$app->user->id;
        }else{
            return $response;
        }
        
        $betList = PlaceBet::find()->select(['event_id'])
        //->select(['id','sport_id','event_id','runner','bet_type','price','size','win','loss','ccr','bet_status'])
        ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>['Win','Loss'] ])
        ->groupBy(['event_id'])->asArray()->all();
        
        //echo '<pre>';print_r($betList);die;
        $eventList = [];
        if( $betList != null ){
            
            $eventList = EventsPlayList::find()
             ->select( [ 'id' , 'sport_id' , 'event_id' , 'event_name','win_result','created_at','updated_at'] )
             ->where( [ 'status' => 1 ] )->andWhere( ['IN','event_id',$betList] )
             ->orderBy( [ 'id' => SORT_DESC ] )->asArray()->all();
             
             $eventMarketList = [];
             $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','6423'=>'Football'];
             
             if( $eventList != null ){
                 $betListArr = [];
                 foreach ( $eventList as $event ){
                     
                     $betListArr = PlaceBet::find()
                     ->select(['id','sport_id','event_id','market_name','runner','bet_type','price','size','win','loss','ccr','bet_status','created_at'])
                     ->where(['user_id'=>$uid , 'session_type'=>'match_odd','bet_status'=>['Win','Loss'] ])
                     ->asArray()->all();
                     
                     $betArr = $backTotal = $layTotal = $commission = [];
                     $total = $backTotalVal = $layTotalVal = $commissionVal = 0;
                     if( $betListArr != null ){
                         
                         foreach ( $betListArr as $bet ){
                             
                             if( $bet['bet_status'] == 'Loss' ){
                                 $pl = (-1)*$bet['loss'];   
                             }else{
                                 $pl = $bet['win'];
                             }
                             
                             $betArr[] = [
                                 'bet_id' => $bet['id'],
                                 'runner_name' => $bet['runner'],
                                 'odds' => $bet['price'],
                                 'stake' => $bet['size'],
                                 'side' => $bet['bet_type'],
                                 'profit_loss' => $pl,
                                 'placed_date' => $bet['created_at']
                             ];
                             
                             if( $bet['bet_type'] == 'back' ){
                                 
                                 if( $bet['bet_status'] == 'Loss' ){
                                     $backTotal[] = (-1)*$bet['loss'];
                                 }else{
                                     $backTotal[] = $bet['win'];
                                 }
                                 
                             }
                             
                             if( $bet['bet_type'] == 'lay' ){
                                 if( $bet['bet_status'] == 'Loss' ){
                                     $layTotal[] = (-1)*$bet['loss'];
                                 }else{
                                     $layTotal[] = $bet['win'];
                                 }
                             }
                             
                             if( $bet['bet_status'] != 'Loss' ){
                                 $commission[] = $bet['ccr'];
                             }
                             
                             
                         }
                         
                         if( count($backTotal) > 0 ){
                             $backTotalVal = array_sum($backTotal);
                             $total = $total+$backTotalVal;
                         }
                         
                         if( count($layTotal) > 0 ){
                             $layTotalVal = array_sum($layTotal);
                             $total = $total+$backTotalVal;
                         }
                         
                         if( count($commission) > 0 ){
                             $commissionVal = array_sum($commission);
                             $total = $total+$backTotalVal;
                         }
                         
                     }
                     
                     //$total = $backTotalVal+$layTotalVal+$commissionVal;
                     
                     // Market Match Odd
                     $eventMarketList[] = [
                         'sport' => $sportsArr[$event['sport_id']],
                         'event_name' => $event['event_name'],
                         'market_name' => 'Match Odd',
                         'winner' => $event['win_result'],
                         'start_time' => $event['created_at'],
                         'settled_time' => $event['updated_at'],
                         'bet_list' => $betArr,
                         'back_total' => $backTotalVal,
                         'lay_total' => $layTotalVal,
                         'commission' => $commissionVal,
                         'total' => $total,
                     ];
                 }
             }
             //echo '<pre>';print_r($eventMarketList);die;
             $response = [ "status" => 1 , "data" => [ "items" => $eventMarketList , "count" => count($eventMarketList) ] ];
            
        }else{
            $response = [ "status" => 1 , "data" => [] ];
        }
        
        return $response;
        
    }
    
    //Event - My Market
    public function actionMyFavoriteList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            //echo $_GET['id'];die;
            if( isset( $_GET['id'] ) && $_GET['id'] != null ){
                $where = ['user_id'=>$uid,'event_id' => $_GET['id'] ];
            }else{
                $where = ['user_id'=>$uid , 'market_type'=>'match_odd' ];
            }
            
            $favoriteList = FavoriteMarket::find()->select(['market_id'])
            ->where($where)->asArray()->all();
            
            if( $favoriteList != null ){
                $items = [];
                foreach ( $favoriteList as $list ){
                    $items[] = $list['market_id'];
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $items ] ];
            }else{
                $response = [ "status" => 1 , "data" => [ "items" => [] ] ];
            }
            
        }
        
        return $response;
        
    }
    
    //Event - My Market
    public function actionMyMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $playTypeArr = ['IN_PLAY'=>'In Play','UPCOMING'=>'Upcoming'];
        $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','6423'=>'Football'];
        $dataArr = [];
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            //Match Odd
            $betListMatchOdd = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'status'=>1,'bet_status'=>'Pending' , 'session_type' => 'match_odd' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            //echo '<pre>';print_r($betListMatchOdd);die;
            if( $betListMatchOdd != null ){
                
                $dataArr = $runnersArr = $market = [];
                
                $eventList = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['IN','event_id',$betListMatchOdd])->asArray()->all();
                foreach ( $eventList as $event ){
                    
                    $marketId = $event['market_id'];
                    $eventId = $event['event_id'];
                    $title = $event['event_name'];
                    $time = $event['event_time'];
                    $suspended = $event['suspended'];
                    $ballRunning = $event['ball_running'];
                    
                    $runnerData = (new \yii\db\Query())
                    ->select(['selection_id','runner'])
                    ->from('events_runners')
                    ->where(['event_id' => $eventId ])
                    ->all();
                    
                    if( $runnerData != null ){
                        
                        //CODE for live call api
                        $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $responseData = curl_exec($ch);
                        curl_close($ch);
                        $responseData = json_decode($responseData);
                        
                        foreach( $runnerData as $runner ){
                            $back = $lay = [];
                            $runnerName = $runner['runner'];
                            $selectionId = $runner['selection_id'];
                            
                            if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                
                                foreach ( $responseData->runners as $runners ){
                                    
                                    if( $runners->selectionId == $selectionId ){
                                        if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                            foreach ( $runners->ex->availableToBack as $backArr ){
                                                $back[] = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                                //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                            }
                                        }
                                        
                                        if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                            foreach ( $runners->ex->availableToLay as $layArr ){
                                                $lay[] = [
                                                    'price' => number_format($layArr->price,2),
                                                    'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                                //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                            }
                                        }
                                    }
                                    
                                }
                                
                            }
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'runnerName' => $runnerName,
                                'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                                'exchange' => [
                                    'back' => $back,
                                    'lay' => $lay
                                ]
                            ];
                            
                        }
                        
                        
                    }
                    
                    $market = [
                        'title' => $title,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'time' => $time,
                        'marketName'=>'Match Odds',
                        'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                    ];
                    
                    $eventName = $event['event_name'];
                    
                    if( isset( $sportsArr[$event['sport_id']] ) ){
                        $eventName = $sportsArr[$event['sport_id']].' - '.$eventName;
                    }
                    
                    if( isset( $playTypeArr[$event['play_type']] ) ){
                        $eventName = $playTypeArr[$event['play_type']].' - '.$eventName;
                    }
                    
                    $dataArr['matchodd'][] = [
                        'event_id' => $event['event_id'],
                        'event_name'=> $eventName,
                        'event_time'=> $event['event_time'],
                        'suspended' => $event['suspended'],
                        'ballRunning' => $event['ball_running'],
                        'market' => $market
                    ];
                }
                
            }
            
            //Manual Match Odd
            
            $betListMatchOdd2 = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' , 'session_type' => 'match_odd2' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            if( $betListMatchOdd2 != null ){
                
                $eventList2 = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['IN','event_id',$betListMatchOdd2])->asArray()->all();
                foreach ( $eventList2 as $event ){
                    
                    $models = ManualSessionMatchOdd::find()->select(['id','event_id','market_id','suspended','ball_running'])
                    ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $event['event_id'] ] )->asArray()->one();
                    
                    $runners = [];
                    if($models != null){
                        
                        $suspended = $ballRunning = 'N';
                        if( isset( $models['suspended'] ) && $models['suspended'] != null ){
                            $suspended = $models['suspended'];
                        }
                        if( isset( $models['ball_running'] ) && $models['ball_running'] != null ){
                            $ballRunning = $models['ball_running'];
                        }
                        
                        $modelsData = ManualSessionMatchOddData::find()->select(['id','market_id','sec_id','runner','lay','back'])
                        ->andWhere( [ 'market_id' => $models['market_id'] ] )->asArray()->all();
                        
                        foreach($modelsData as $data){
                            
                            $runners[] = [
                                'id' => $data['id'],
                                'event_id' => $models['event_id'],
                                'market_id' => $models['market_id'],
                                'sec_id' => $data['sec_id'],
                                'runner' => $data['runner'],
                                'profitloss' => '',
                                'lay' => [
                                    'price' => $data['lay'],
                                    'size' => '',
                                ],
                                'back' => [
                                    'price' => $data['back'],
                                    'size' => '',
                                ]
                            ];
                        }
                    }
                    
                    $eventName = $event['event_name'];
                    
                    if( isset( $sportsArr[$event['sport_id']] ) ){
                        $eventName = $sportsArr[$event['sport_id']].' - '.$eventName;
                    }
                    
                    if( isset( $playTypeArr[$event['play_type']] ) ){
                        $eventName = $playTypeArr[$event['play_type']].' - '.$eventName;
                    }
                    
                    $dataArr['matchodd2'][] = [
                        'event_id' => $event['event_id'],
                        'event_name'=>$eventName,
                        'event_time'=>$event['event_time'],
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'market' => $runners
                    ];
                } 
            }
            
            //Manual Session Fancy
            
            $betListFancy = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' , 'session_type' => 'fancy' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            if( $betListFancy != null ){
                
                $eventList3 = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['IN','event_id',$betListFancy])->asArray()->all();
                
                foreach ( $eventList3 as $event ){
                    
                    $models = ManualSession::find()
                    ->where( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $event['event_id'] ] )->asArray()->all();
                    
                    $runners = [];
                    if($models != null){
                        foreach($models as $data){
                            
                            $no1 = $yes1 = $no2 = $yes2 = '-';
                            $dataVal = [];
                            
                            if( $data['no_yes_val_2'] != null && $data['no_yes_val_2'] != 0 ){
                                
                                $no_yes2 = explode('/', $data['no_yes_val_2']);
                                
                                if( count($no_yes2) > 1 ){
                                    $no2 = $no_yes2[0];
                                    $yes2 = $no_yes2[1];
                                }
                                
                                $rate2 = explode('/', $data['rate_2']);
                                
                                if( count($rate2) > 1 ){
                                    $yes_rate2 = $rate2[0];
                                    $no_rate2 = $rate2[1];
                                }
                                
                                $dataVal[0] = [
                                    'no' => $no2,
                                    'no_rate' => $no_rate2,
                                    'yes' => $yes2,
                                    'yes_rate' => $yes_rate2
                                ];
                                
                            }
                            
                            //$profitLoss = $this->getManualSessionProfitLossOnBet($data['event_id'], $data['id'],$data['id'],'fancy2');
                            
                            $dataArr['fancy'][] = [
                                'id' => $data['id'],
                                'event_id' => $data['event_id'],
                                'title' => $data['title'],
                                'suspended' => $data['suspended'],
                                'ballRunning' => $data['ball_running'],
                                'profitloss' => '',
                                'data' => $dataVal,
                            ];
                        }
                    }
                    
                }
                
            }
            
            //Fancy2
            
            $betListFancy2 = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' , 'session_type' => 'fancy2' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            if( $betListFancy2 != null ){
                
                $eventList3 = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['IN','event_id',$betListFancy2])->asArray()->all();
                
                foreach ( $eventList3 as $event ){
                    
                    $models = MarketType::find()
                    ->where( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $event['event_id'] ] )->asArray()->all();
                    
                    //CODE for live call api
                    $url = $this->apiUrlFancy.'?eventId='.$event['event_id'];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    
                    
                    $runners = [];
                    if($models != null){
                        foreach($models as $data){
                            
                            $no1 = $yes1 = $no2 = $yes2 = '-';
                            $dataVal = [];
                            
                            if( $data['no_yes_val_2'] != null && $data['no_yes_val_2'] != 0 ){
                                
                                $no_yes2 = explode('/', $data['no_yes_val_2']);
                                
                                if( count($no_yes2) > 1 ){
                                    $no2 = $no_yes2[0];
                                    $yes2 = $no_yes2[1];
                                }
                                
                                $rate2 = explode('/', $data['rate_2']);
                                
                                if( count($rate2) > 1 ){
                                    $yes_rate2 = $rate2[0];
                                    $no_rate2 = $rate2[1];
                                }
                                
                                $dataVal[0] = [
                                    'no' => $no2,
                                    'no_rate' => $no_rate2,
                                    'yes' => $yes2,
                                    'yes_rate' => $yes_rate2
                                ];
                                
                            }
                            
                            //$profitLoss = $this->getManualSessionProfitLossOnBet($data['event_id'], $data['id'],$data['id'],'fancy2');
                            
                            $dataArr['fancy'][] = [
                                'id' => $data['id'],
                                'event_id' => $data['event_id'],
                                'title' => $data['title'],
                                'suspended' => $data['suspended'],
                                'ballRunning' => $data['ball_running'],
                                'profitloss' => '',
                                'data' => $dataVal,
                            ];
                        }
                    }
                    
                }
                
                
            }
            
            //Manual Session Lottery
            $betListLottery = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' , 'session_type' => 'lottery' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            if( $betListLottery != null ){
                
                $eventList4 = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['IN','event_id',$betListLottery])->asArray()->all();
                
                foreach ( $eventList4 as $event ){
                    
                    $models = ManualSessionLottery::find()->select(['id','event_id','title'])
                    ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $event['event_id'] ] )->asArray()->all();
                    
                    $runners = [];
                    if($models != null){
                        foreach($models as $data){
                            
                            $lotteryNumbers = ManualSessionLotteryNumbers::find()->select(['id','number','rate'])
                            ->where( [ 'manual_session_lottery_id' => $data['id'] ] )->asArray()->all();
                            
                            $numbers = [];
                            if( $lotteryNumbers != null ){
                                
                                foreach($lotteryNumbers as $lottery){
                                    
                                    $profitLoss = $this->getLotteryProfitLossOnBet($data['event_id'], $data['id'] , $lottery['id'] );
                                    
                                    $numbers[] = [
                                        'id' => $lottery['id'],
                                        'number' => $lottery['number'],
                                        'rate' => $lottery['rate'],
                                        'profitloss' => $profitLoss
                                        
                                    ];
                                }
                            }
                            
                            $dataArr['lottery'][] = [
                                'id' => $data['id'],
                                'event_id' => $data['event_id'],
                                'title' => $data['title'],
                                'numbers' => $numbers,
                            ];
                            
                        }
                    }
                    
                    
                }
                
                
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Cricket
    public function actionMyMarketCricket()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 4,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = $marketArr['fancy2'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 4,
                                    'slug' => 'cricket',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        $fancy2Data = (new \yii\db\Query())
                        ->select(['market_id'])->from('place_bet')
                        ->where(['event_id' => $eventId,'sport_id' => 4,'user_id'=>$uid ,'session_type' => 'fancy2', 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
                        ->groupBy(['market_id'])->all();
                        
                        if( $fancy2Data != null ){
                            
                            foreach( $fancy2Data as $fancy2 ){
                                
                                //CODE for live call api
                                $url = $this->apiUrlFancy.'?eventId='.$eventId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                //echo '<pre>';print_r($responseData);die;
                                
                                if( $responseData->status == 200 ){
                                    
                                    if( isset( $responseData->data ) && !empty( $responseData->data ) ){
                                        
                                        foreach ( $responseData->data as $key=>$data ){
                                            
                                            if( $key != 'active' ){
                                                
                                                $marketId = $data->market_id;
                                                $titleFancy = $data->headname;
                                                
                                                if( $fancy2['market_id'] == $marketId ){
                                                
                                                    //$isBook = $this->isBookOn($marketId,'fancy2');
                                                    
                                                    $dataVal[0] = [
                                                        'no' => $data->SessInptNo,
                                                        'no_rate' => $data->NoValume,
                                                        'yes' => $data->SessInptYes,
                                                        'yes_rate' => $data->YesValume,
                                                    ];
                                                    
                                                    $marketArr['fancy2'][] = [
                                                        'market_id' => $marketId,
                                                        'event_id' => $eventId,
                                                        'title' => $titleFancy,
                                                        'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                                        'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                                        'data' => $dataVal,
                                                        'sportId' => 4,
                                                        'slug' => 'cricket',
                                                        'sessionType' => 'fancy2',
                                                        'is_book' => 1
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }   
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
    }
    
    //Event - My Market Cricket
    public function actionMyMarketCricketNew()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = PlaceBet::find()->select(['event_id'])
            ->where(['sport_id' => 4,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            $eventArr = $marketArr = $marketArr['match_odd'] = $marketArr['fancy'] = $marketArr['fancy2'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'][] = [
                                    'sportId' => 4,
                                    'slug' => 'cricket',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                                
                            }
                        }
                        
                        $fancy2Data = PlaceBet::find()->select(['market_id'])
                        ->where(['event_id' => $eventId,'sport_id' => 4,'user_id'=>$uid ,'session_type' => 'fancy2', 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
                        ->groupBy(['market_id'])->asArray()->all();
                        
                        if( $fancy2Data != null ){
                            
                            foreach( $fancy2Data as $fancy2 ){
                                
                                //CODE for live call api
                                $url = $this->apiUrlFancy.'?eventId='.$eventId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                //echo '<pre>';print_r($responseData);die;
                                
                                if( $responseData->status == 200 ){
                                    
                                    if( isset( $responseData->data ) && !empty( $responseData->data ) ){
                                        
                                        foreach ( $responseData->data as $key=>$data ){
                                            
                                            if( $key != 'active' ){
                                                
                                                $marketId = $data->market_id;
                                                $titleFancy = $data->headname;
                                                
                                                if( $fancy2['market_id'] == $marketId ){
                                                    
                                                    $isBook = $this->isBookOn($marketId,'fancy2');
                                                    
                                                    $dataVal[0] = [
                                                        'no' => $data->SessInptNo,
                                                        'no_rate' => $data->NoValume,
                                                        'yes' => $data->SessInptYes,
                                                        'yes_rate' => $data->YesValume,
                                                    ];
                                                    
                                                    $marketArr['fancy2'][] = [
                                                        'market_id' => $marketId,
                                                        'event_id' => $eventId,
                                                        'title' => $titleFancy,
                                                        'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                                        'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                                        'data' => $dataVal,
                                                        'sportId' => 4,
                                                        'slug' => 'cricket',
                                                        'is_book' => $isBook
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                        $fancyData = PlaceBet::find()->select(['market_id'])
                        ->where(['event_id' => $eventId,'sport_id' => 4,'user_id'=>$uid ,'session_type' => 'fancy', 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
                        ->groupBy(['market_id'])->asArray()->all();
                        
                        if( $fancyData != null ){
                            $marketIds = [];
                            foreach( $fancyData as $fancy ){
                                $marketIds[] = $fancy['market_id'];
                            }
                            
                            $manualSessionData = ManualSession::find()
                            ->select(['id' , 'event_id','market_id', 'title' , 'no_yes_val_2' , 'rate_2','suspended','ball_running' ])
                            ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'market_id' => $marketIds ] )
                            ->asArray()->all();
                            
                            if( $manualSessionData != null ){
                                
                                foreach($manualSessionData as $data){
                                    
                                    $no1 = $yes1 = $no2 = $yes2 = '-';
                                    $dataVal = [];
                                    
                                    if( $data['no_yes_val_2'] != null && $data['no_yes_val_2'] != 0 ){
                                        
                                        $no_yes2 = explode('/', $data['no_yes_val_2']);
                                        
                                        if( count($no_yes2) > 1 ){
                                            $no2 = $no_yes2[0];
                                            $yes2 = $no_yes2[1];
                                        }
                                        
                                        $rate2 = explode('/', $data['rate_2']);
                                        
                                        if( count($rate2) > 1 ){
                                            $yes_rate2 = $rate2[0];
                                            $no_rate2 = $rate2[1];
                                        }
                                        
                                        $dataVal[0] = [
                                            'no' => $no2,
                                            'no_rate' => $no_rate2,
                                            'yes' => $yes2,
                                            'yes_rate' => $yes_rate2
                                        ];
                                        
                                    }
                                    
                                    //$profitLoss = $this->getManualSessionProfitLossOnBet($data['event_id'], $data['market_id'],'fancy');
                                    $isBook = $this->isBookOn($data['market_id'],'fancy');
                                    
                                    $marketArr['fancy'][] = [
                                        'id' => $data['id'],
                                        'event_id' => $data['event_id'],
                                        'market_id' => $data['market_id'],
                                        'title' => $data['title'],
                                        'suspended' => $data['suspended'],
                                        'ballRunning' => $data['ball_running'],
                                        'data' => $dataVal,
                                        'sportId' => 4,
                                        'slug' => 'cricket',
                                        'is_book' => $isBook
                                    ];
                                }
                                
                            }
                            
                        }
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
    }
    
    // Cricket: isBookOn
    public function isBookOn($marketId,$sessionType)
    {
        $uid = \Yii::$app->user->id;
        
        $findBet = (new \yii\db\Query())
        ->select(['id'])->from('place_bet')
        ->where(['user_id'=>$uid,'market_id' => $marketId,'session_type' => $sessionType,'status'=>1 ])
        ->one();
        
        if( $findBet != null ){
            return '1';
        }
        return '0';
        
    }
    
    //Event - My Market Cricket
    public function actionMyMarketCricketOLDUNUSED()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['market_id'])
            ->where(['sport_id'=>4,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending','session_type' => 'match_odd' ])
            ->groupBy(['market_id'])->asArray()->all();
            
            $marketArr = $runnersArr = [];
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                $runnersArr = [];
                
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO','sport_id'=>4,'status'=>1])
                ->andWhere(['IN','market_id',$betList])->asArray()->all();
                
                if( $eventList != null ){
                    foreach ( $eventList as $event ){
                        
                        $marketId = $event['market_id'];
                        $eventId = $event['event_id'];
                        $title = $event['event_name'];
                        $time = $event['event_time'];
                        $suspended = $event['suspended'];
                        $ballRunning = $event['ball_running'];
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = [];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                foreach ( $runners->ex->availableToBack as $backArr ){
                                                    $back[] = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                }
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                foreach ( $runners->ex->availableToLay as $layArr ){
                                                    $lay[] = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr[] = [
                                'sportId' => 4,
                                'slug' => 'cricket',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        
                    }
                }
                
            }
            
            $betList2 = PlaceBet::find()->select(['market_id'])
            ->where(['sport_id'=>4,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending','session_type' => 'fancy2' ])
            ->groupBy(['market_id'])->asArray()->all();
            
            if( $betList2 != null ){
                
                //CODE for live call api
                $url = $this->apiUrlFancy.'?eventId='.$eventId;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $responseData = curl_exec($ch);
                curl_close($ch);
                $responseData = json_decode($responseData);
                
                
            }
            
            
            $response = [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Cricket
    public function actionMyFavoriteCricket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = $marketArr['fancy2'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $playType = $eventData['play_type'];
                        
                        /*$matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();*/
                        
                        //if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 4,
                                    'slug' => 'cricket',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'playType' => $playType,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        //}
                        
                        // Fancy 2
                        
                        $fancy2Data = (new \yii\db\Query())
                        ->select(['market_id'])->from('favorite_market')
                        ->where(['user_id'=>$uid,'market_type' => 'fancy2' ])
                        ->groupBy(['market_id'])->all();
                        
                        
                        if( $fancy2Data != null ){
                            
                            foreach( $fancy2Data as $fancy2 ){
                                
                                //CODE for live call api
                                $url = $this->apiUrlFancy.'?eventId='.$eventId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                //echo '<pre>';print_r($responseData);die;
                                
                                if( $responseData->status == 200 ){
                                    
                                    if( isset( $responseData->data ) && !empty( $responseData->data ) ){
                                        
                                        foreach ( $responseData->data as $key=>$data ){
                                            
                                            if( $key != 'active' ){
                                                
                                                $marketId = $data->market_id;
                                                $titleFancy = $data->headname;
                                                
                                                if( $fancy2['market_id'] == $marketId ){
                                                    
                                                    $isBook = $this->isBookOn($marketId,'fancy2');
                                                    
                                                    $dataVal[0] = [
                                                        'no' => $data->SessInptNo,
                                                        'no_rate' => $data->NoValume,
                                                        'yes' => $data->SessInptYes,
                                                        'yes_rate' => $data->YesValume,
                                                    ];
                                                    
                                                    $marketArr['fancy2'][] = [
                                                        'market_id' => $marketId,
                                                        'event_id' => $eventId,
                                                        'title' => $titleFancy,
                                                        'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                                        'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                                        'data' => $dataVal,
                                                        'sportId' => 4,
                                                        'slug' => 'cricket',
                                                        'sessionType' => 'fancy2',
                                                        'is_book' => $isBook
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Tennis
    public function actionMyMarketTennis()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 2,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 2,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                        ];
                                                        //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 2,
                                    'slug' => 'tennis',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
    }
    
    //Event - My Market Tennis
    public function actionMyMarketTennisUNUSED()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['market_id'])
            ->where(['sport_id'=>2,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending','session_type' => 'match_odd' ])
            ->groupBy(['market_id'])->asArray()->all();
            $marketArr = $runnersArr = [];
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO','sport_id'=>2,'status'=>1])
                ->andWhere(['IN','market_id',$betList])->asArray()->all();
                
                if( $eventList != null ){
                    foreach ( $eventList as $event ){
                        
                        $marketId = $event['market_id'];
                        $eventId = $event['event_id'];
                        $title = $event['event_name'];
                        $time = $event['event_time'];
                        $suspended = $event['suspended'];
                        $ballRunning = $event['ball_running'];
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = [];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                foreach ( $runners->ex->availableToBack as $backArr ){
                                                    $back[] = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                }
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                foreach ( $runners->ex->availableToLay as $layArr ){
                                                    $lay[] = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr[] = [
                                'sportId' => 2,
                                'slug' => 'tennis',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        
                    }
                }
                
            }
            $response = [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Tennis
    public function actionMyFavoriteTennis()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 2,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $playType = $eventData['play_type'];
                        /*$matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();*/
                        
                        //if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),
                                                        ];
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),
                                                        ];
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 2,
                                    'slug' => 'tennis',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'playType' => $playType,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        //}
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Football
    public function actionMyMarketFootball()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 1,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 1,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    foreach ( $runners->ex->availableToBack as $backArr ){
                                                        $back[] = [
                                                            'price' => number_format($backArr->price , 2),
                                                            'size' => number_format($backArr->size , 2),
                                                        ];
                                                    }
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    foreach ( $runners->ex->availableToLay as $layArr ){
                                                        $lay[] = [
                                                            'price' => number_format($layArr->price,2),
                                                            'size' => number_format($layArr->size,2),
                                                        ];
                                                    }
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 1,
                                    'slug' => 'football',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Football
    public function actionMyMarketFootballUNUSED()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['market_id'])
            ->where(['sport_id'=>1,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending','session_type' => 'match_odd' ])
            ->groupBy(['market_id'])->asArray()->all();
            $marketArr = $runnersArr = [];
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO','sport_id'=>1,'status'=>1])
                ->andWhere(['IN','market_id',$betList])->asArray()->all();
                
                if( $eventList != null ){
                    foreach ( $eventList as $event ){
                        
                        $marketId = $event['market_id'];
                        $eventId = $event['event_id'];
                        $title = $event['event_name'];
                        $time = $event['event_time'];
                        $suspended = $event['suspended'];
                        $ballRunning = $event['ball_running'];
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = [];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                foreach ( $runners->ex->availableToBack as $backArr ){
                                                    $back[] = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                                }
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                foreach ( $runners->ex->availableToLay as $layArr ){
                                                    $lay[] = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                    ];
                                                    //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr[] = [
                                'sportId' => 1,
                                'slug' => 'football',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        
                    }
                }
                
            }
            $response = [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Football
    public function actionMyFavoriteFootball()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 1,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $playType = $eventData['play_type'];
                        /*$matchOddData = (new \yii\db\Query())
                         ->select(['id'])->from('place_bet')
                         ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                         ->one();*/
                        
                        //if( $matchOddData != null ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = [];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                foreach ( $runners->ex->availableToBack as $backArr ){
                                                    $back[] = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                foreach ( $runners->ex->availableToLay as $layArr ){
                                                    $lay[] = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 1,
                                'slug' => 'football',
                                'sessionType' => 'match_odd',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'playType' => $playType,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        //}
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market HorseRacing
    public function actionMyMarketHorseRacing()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['market_id'])
            ->where(['sport_id'=>7,'user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending','session_type' => 'match_odd' ])
            ->groupBy(['market_id'])->asArray()->all();
            $marketArr = $runnersArr = [];
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO', 'status'=>1,'sport_id'=>7])
                ->andWhere(['IN','market_id',$betList])->asArray()->all();
                if( $eventList != null ){
                    foreach ( $eventList as $event ){
                        
                        $marketId = $event['market_id'];
                        $eventId = $event['event_id'];
                        $title = $event['event_name'];
                        $time = $event['event_time'];
                        $suspended = $event['suspended'];
                        $ballRunning = $event['ball_running'];
                        
                        //CODE for live call api
                        $url = 'http://appleexch.uk:3000/getMarket?id='.$marketId;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $responseData = curl_exec($ch);
                        curl_close($ch);
                        $responseData = json_decode($responseData);
                        
                        //echo '<pre>';print_r($responseData);die;
                        $runnerNameArr = explode(' v ', $title);
                        $runner[0] = 'Runner 1';
                        $runner[1] = 'Runner 2';
                        $runner[2] = 'The Draw';
                        if( is_array($runnerNameArr) && count( $runnerNameArr ) > 0 ){
                            $runner[0] = trim($runnerNameArr[0]);
                            //$runner[1] = $runnerNameArr[1];
                            if( isset( $runnerNameArr[1] ) ){
                                $runnerNameArr1 = explode('(', $runnerNameArr[1]);
                                if( is_array($runnerNameArr1) && count( $runnerNameArr1 ) > 0 ){
                                    $runner[1] = trim($runnerNameArr1[0]);
                                }
                            }
                        }
                        
                        if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                            $i = 0;
                            foreach ( $responseData->runners as $runners ){
                                $back = $lay = [];
                                $selectionId = $runners->selectionId;
                                $runnerName = $runner[$i];
                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                    $backArr = $runners->ex->availableToBack[0];
                                    $back = [
                                        'price' => number_format($backArr->price , 2),
                                        'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                    ];
                                }
                                
                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                    $layArr = $runners->ex->availableToLay[0];
                                    $lay = [
                                        'price' => number_format($layArr->price,2),
                                        'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                    ];
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                                    'back' => $back,
                                    'lay' => $lay
                                ];
                                $i++;
                            }
                        }
                        
                        $marketArr[] = [
                            'title' => $title,
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'suspended' => $suspended,
                            'ballRunning' => $ballRunning,
                            'time' => $time,
                            'marketName'=>'Match Odds',
                            'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                            'runners' => $runnersArr,
                        ];
                        
                    }
                }
                
            }
            $response = [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
            
        }
        
        return $response;
        
    }
    
    // Cricket: get Profit Loss Match Odds
    public function getProfitLossMatchOdds($marketId,$eventId,$selId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){
            
            $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'match_unmatch'=>1, 'sec_id' => $selId,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $totalWin = $backWin + $layWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $totalLoss = $backLoss + $layLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
        
    }
    
    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLossOnBet($eventId,$marketId ,$selectionId)
    {
        $total = 0;
        $userId = \Yii::$app->user->id;
        $where = [ 'session_type' => 'lottery', 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId ];
        // IF RUNNER WIN
        $betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where( $where )
        ->andWhere( ['sec_id' => $selectionId] )->asArray()->all();
        // IF RUNNER LOSS
        $betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where( $where )
        ->andWhere( ['!=','sec_id' , $selectionId] )->asArray()->all();
        if( $betWinList == null ){
            $totalWin = 0;
        }else{ $totalWin = $betWinList[0]['totalWin']; }
        
        if( $betLossList == null ){
            $totalLoss = 0;
        }else{ $totalLoss = (-1)*$betLossList[0]['totalLoss']; }
        
        $total = $totalWin+$totalLoss;
        
        return $total;
    }
    
    // Cricket: get ManualSession Profit Loss On Bet
    public function getManualSessionProfitLossOnBet($eventId,$marketId ,$selectionId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $where = [ 'session_type' => $sessionType, 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId , 'sec_id' => $selectionId ];
        // IF RUNNER WIN
        $betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where( $where )->asArray()->all();
        // IF RUNNER LOSS
        $betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where( $where )->asArray()->all();
        if( $betWinList == null ){
            $totalWin = 0;
        }else{ $totalWin = $betWinList[0]['totalWin']; }
        
        if( $betLossList == null ){
            $totalLoss = 0;
        }else{ $totalLoss = (-1)*$betLossList[0]['totalLoss']; }
        
        $total = $totalWin+$totalLoss;
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
    }
    
    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBet($eventId,$runner,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        
        if( $runner != 'The Draw' ){
            
            // IF RUNNER WIN
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layDrwWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layDrwWin[0]['val']);die;
            
            if( $layDrwWin == null || !isset($layDrwWin[0]['val']) || $layDrwWin[0]['val'] == '' ){
                $layDrwWin = 0;
            }else{ $layDrwWin = $layDrwWin[0]['val']; }
            
            $totalWin = $backWin + $layWin + $layDrwWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backDrwLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backDrwLoss[0]['val']);die;
            
            if( $backDrwLoss == null || !isset($backDrwLoss[0]['val']) || $backDrwLoss[0]['val'] == '' ){
                $backDrwLoss = 0;
            }else{ $backDrwLoss = $backDrwLoss[0]['val']; }
            
            $totalLoss = $backLoss + $layLoss + $backDrwLoss;
            
            $total = $totalWin-$totalLoss;
            
        }else{
            
            // IF RUNNER WIN
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $totalWin = $backWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)
            ->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $otherLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($otherLoss[0]['val']);die;
            
            if( $otherLoss == null || !isset($otherLoss[0]['val']) || $otherLoss[0]['val'] == '' ){
                $otherLoss = 0;
            }else{ $otherLoss = $otherLoss[0]['val']; }
            
            $totalLoss = $layLoss + $otherLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
        
    }
    
    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrow_UnUse()
    {
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];
        
        //$eventList = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])->asArray()->all();
        
        $marketArr = [];
        
        //CODE for live call api
        $url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseDataCricket = json_decode($responseData);
        
        //echo '<pre>';print_r($responseDataCricket);die;
        
        if( isset($responseDataCricket->result) && !empty($responseDataCricket->result) ){
        
            foreach ( $responseDataCricket->result as $result ){
                
                if( $result->inPlay == true ){
                    
                    if( $result->eventTypeId == 4 ){
                        
                        $runnersArr = $marketArr = [];
                        //echo '<pre>';print_r($responseDataCricket);die;
                        //if( isset($responseDataCricket->result) && !empty($responseDataCricket->result) ){
                            
                             //foreach ( $responseDataCricket->result as $result ){
                            
                                $marketId = $result->id;
                                $eventId = $result->groupById;
                                //if( $event['event_id'] == $result->event->id ){
                                    foreach ( $result->runners as $runners ){
                                        
                                        $back = $lay = [];
                                        
                                        $selectionId = $runners->id;
                                        $runnerName = $runners->name;
                                        if( isset( $runners->back ) && !empty( $runners->back ) ){
                                            foreach ( $runners->back as $backArr ){
                                                $back[] = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                            }
                                        }
                                        
                                        if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                                            foreach ( $runners->lay as $layArr ){
                                                $lay[] = [
                                                    'price' => number_format($layArr->price,2),
                                                    'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                            }
                                        }
                                        
                                        $runnersArr[] = [
                                            'selectionId' => $selectionId,
                                            'runnerName' => $runnerName,
                                            'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                                            'exchange' => [
                                                'back' => $back,
                                                'lay' => $lay
                                            ]
                                        ];
                                        
                                    }
                                    
                                    $marketArr[0] = [
                                        'title' => $result->event->name,
                                        'marketId' => $marketId,
                                        'eventId' => $eventId,
                                        'suspended' => 'N',
                                        'ballRunning' => 'N',
                                        'time' => $result->start,
                                        'marketName'=>'Match Odds',
                                        'matched' => $result->matched,//$this->getMatchTotalVal($marketId,$eventId),
                                        'runners' => $runnersArr,
                                    ];
                                //}
                            //}
                            
                        //}
                        
                        $cricketInplay[] = [
                            'slug' => 'cricket',
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                            'market' => $marketArr
                        ];
                    }
                    if( $result->eventTypeId == 2 ){
                        
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        $tennisInplay[] = [
                            'slug' => 'tennis',
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'market' => $marketArr
                        ];
                    }
                    if( $result->eventTypeId == 6423 ){
                        
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        $footballInplay[] = [
                            'slug' => 'football',
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'market' => $marketArr
                        ];
                    }
                    if( $result->eventTypeId == 7 ){
                        
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        $horseracingInplay[] = [
                            'slug' => 'horse-racing',
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'market' => $marketArr
                        ];
                    }
                }else{
                    
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start/1000 ));
                    
                    if( $today == $eventDate ){
                        if( $result->eventTypeId == 4 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $cricketToday[] = [
                                'slug' => 'cricket',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                                'suspended' => 'N',
                                'ballRunning' => 'N',
                            ];
                        }
                        if( $result->eventTypeId == 2 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $tennisToday[] = [
                                'slug' => 'tennis',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                        if( $result->eventTypeId == 6423 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $footballToday[] = [
                                'slug' => 'football',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                        if( $result->eventTypeId == 7 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $horseracingToday[] = [
                                'slug' => 'horse-racing',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                    }
                    if( $tomorrow == $eventDate ){
                        if( $result->eventTypeId == 4 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $cricketTomorrow[] = [
                                'slug' => 'cricket',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                                'suspended' => 'N',
                                'ballRunning' => 'N',
                            ];
                        }
                        if( $result->eventTypeId == 2 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $tennisTomorrow[] = [
                                'slug' => 'tennis',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                        if( $result->eventTypeId == 6423 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $footballTomorrow[] = [
                                'slug' => 'football',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                        if( $result->eventTypeId == 7 ){
                            
                            $marketId = $result->id;
                            $eventId = $result->groupById;
                            
                            $horseracingTomorrow[] = [
                                'slug' => 'horse-racing',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                            ];
                        }
                    }
                }
                
            }
        }
        
        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }
    
    public function getMatchTotalVal($mId,$eId)
    {
        $where = [ 'event_id' => $eId , 'market_id' => $mId , 'session_type' => 'match_odds', 'status' => 1 , 'match_unmatch' => 1, 'bet_status' => 'Pending' ];
        
        $matchTotal = PlaceBet::find()->select(['SUM(size) as val'])->where($where)->asArray()->all();
        
        if( $matchTotal == null || !isset($matchTotal[0]['val']) || $matchTotal[0]['val'] == '' ){
            $matchTotalVal = 0;
        }else{ $matchTotalVal = $matchTotal[0]['val']; }
        
        return $matchTotalVal;
    }
    
    public function getSizeTotalVal($mId,$eId,$sId)
    {
        $where = [ 'sec_id' => $sId , 'event_id' => $eId , 'market_id' => $mId , 'session_type' => 'match_odds', 'status' => 1 , 'match_unmatch' => 1, 'bet_status' => 'Pending' ];
        
        $sizeTotal = PlaceBet::find()->select(['SUM(size) as val'])->where($where)->asArray()->all();
        
        if( $sizeTotal == null || !isset($sizeTotal[0]['val']) || $sizeTotal[0]['val'] == '' ){
            $sizeTotalVal = 0;
        }else{ $sizeTotalVal = $sizeTotal[0]['val']; }
        
        return $sizeTotalVal;
    }
    
    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrowOLD()
    {
        
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];
        
        $eventList = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])->asArray()->all();
        
        $basePath = \Yii::$app->basePath;
        
        $marketArr = [];
        
        foreach ( $eventList as $event ){
            
            if( $event['play_type'] == 'IN_PLAY' ){
                
                $fileEx = $basePath.'/uploads/json/cricket/'.$event['event_id'].'_live.json';
                if( file_exists($fileEx) ){
                    $responseExData = json_decode(file_get_contents( $fileEx ));
                    foreach ( $responseExData->data->items as $market ){
                        $market->suspended = $event['suspended'];
                        $market->ballRunning = $event['ball_running'];
                        $marketArr[] = $market;
                    }
                }
                
                if( $event['sport_id'] == 4 ){
                    $cricketInplay[] = [
                        'slug' => 'cricket',
                        'event_id' => $event['event_id'],
                        'event_name'=>$event['event_name'],
                        'event_time'=>$event['event_time'],
                        'market' => $marketArr
                    ];
                }
                if( $event['sport_id'] == 2 ){
                    $tennisInplay[] = [
                        'slug' => 'tennis',
                        'event_id' => $event['event_id'],
                        'event_name'=>$event['event_name'],
                        'event_time'=>$event['event_time'],
                        'market' => $marketArr
                    ];
                }
                if( $event['sport_id'] == 6423 ){
                    $footballInplay[] = [
                        'slug' => 'football',
                        'event_id' => $event['event_id'],
                        'event_name'=>$event['event_name'],
                        'event_time'=>$event['event_time'],
                        'market' => $marketArr
                    ];
                }
                if( $event['sport_id'] == 7 ){
                    $horseracingInplay[] = [
                        'slug' => 'horse-racing',
                        'event_id' => $event['event_id'],
                        'event_name'=>$event['event_name'],
                        'event_time'=>$event['event_time'],
                        'market' => $marketArr
                    ];
                }
            }else{
                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                $eventDate = date('Y-m-d',$event['event_time']);
                
                if( $today == $eventDate ){
                    if( $event['sport_id'] == 4 ){
                        $cricketToday[] = [
                            'slug' => 'cricket',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 2 ){
                        $tennisToday[] = [
                            'slug' => 'tennis',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 6423 ){
                        $footballToday[] = [
                            'slug' => 'football',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 7 ){
                        $horseracingToday[] = [
                            'slug' => 'horse-racing',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                }
                if( $tomorrow == $eventDate ){
                    if( $event['sport_id'] == 4 ){
                        $cricketTomorrow[] = [
                            'slug' => 'cricket',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 2 ){
                        $tennisTomorrow[] = [
                            'slug' => 'tennis',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 6423 ){
                        $footballTomorrow[] = [
                            'slug' => 'football',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                    if( $event['sport_id'] == 7 ){
                        $horseracingTomorrow[] = [
                            'slug' => 'horse-racing',
                            'event_id' => $event['event_id'],
                            'event_name'=>$event['event_name'],
                            'event_time'=>$event['event_time'],
                        ];
                    }
                }
            }
            
        }
        
        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }
    
    //Event - Results
    public function actionResults()
    {
        $dataArr = [];
        $eventList = EventsPlayList::find()->select(['sport_id','event_id','event_name','win_result','updated_at'])->where(['status'=>1,'game_over'=>'YES','status'=>1])->asArray()->all();
        
        foreach ( $eventList as $event ){
            
            $sportData = $this->getSportData($event['sport_id']);
            $sportName = '';
            if( $sportData != null ){
                $sportName = $sportData['event_type_name'];
            }
            
            $eventName = $sportName.' - '.$event['event_name'];
            
            $dataArr[] = [
                'setteled_time' => $event['updated_at'],
                'event_name' => $eventName,
                'market_name' => 'Match Odds',
                'winner' => $event['win_result']
            ];
            
            $marketList = MarketType::find()->select(['market_name','win_result','updated_at'])->where(['event_id'=>$event['event_id'],'game_over'=>'YES','status'=>1])->asArray()->all();
            
            foreach ( $marketList as $market ){
                $dataArr[] = [
                    'setteled_time' => $market['updated_at'],
                    'event_name' => $eventName,
                    'market_name' => $market['market_name'],
                    'winner' => $market['win_result']
                ];
            }
            
            $manualSessionList = ManualSession::find()->select(['title','win_result','updated_at'])->where(['event_id'=>$event['event_id'],'game_over'=>'YES','status'=>1])->asArray()->all();
            
            foreach ( $manualSessionList as $manual ){
                $dataArr[] = [
                    'setteled_time' => $manual['updated_at'],
                    'event_name' => $eventName,
                    'market_name' => $manual['title'],
                    'winner' => $manual['win_result']
                ];
            }
            
            $manualLotteryList = ManualSessionLottery::find()->select(['title','win_result','updated_at'])->where(['event_id'=>$event['event_id'],'game_over'=>'YES','status'=>1])->asArray()->all();
            
            foreach ( $manualLotteryList as $lottery ){
                $dataArr[] = [
                    'setteled_time' => $lottery['updated_at'],
                    'event_name' => $eventName,
                    'market_name' => $lottery['title'],
                    'winner' => $lottery['win_result']
                ];
            }
            
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
        
    }
    
    public function getSportData($id)
    {
        $event = Event::find()->select(['event_type_name','event_slug'])->where(['event_type_id'=>$id,'status'=>1])->asArray()->one();
        if( $event != null ){
            return $event;
        }else{
            return null;
        }
    }
    
    
    // Cricket: Get data inplay and upcoming list from API
    
    public function actionCricket()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                
                }
                
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }

    // Football: Get data inplay and upcoming list from API
    
    public function actionFootball()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=6423&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
                
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=6423&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }
                
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    
    // Tennis: Get data inplay and upcoming list from API
    
    public function actionTennis()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=2&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=2&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }
                
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    // HorseRacing: Get data inplay and upcoming list from API
    
    public function actionHorseRacing()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=7&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=7&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }
                
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    /*public function actionInplay()
    {
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        
        foreach ( $response->results as $items ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $response1 = json_decode(curl_exec($ch));
            curl_close($ch);
            
            //$event = $response1->results[0]->event;
            $marketsArr = $response1->results[0]->markets[0];
            
            $responseArr[] = [
                'item' => $items,
                'market' => $marketsArr->runners
            ];
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    public function actionUpcoming()
    {
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        
        foreach ( $response->results as $items ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $response1 = json_decode(curl_exec($ch));
            curl_close($ch);
            
            //$event = $response1->results[0]->event;
            $marketsArr = $response1->results[0]->markets[0];
            
            $responseArr[] = [
                'item' => $items,
                'market' => $marketsArr->runners
            ];
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }*/
    
    public function actionExchange()
    {
        $eID = $_GET['id'];
        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        $event = $response->results[0]->event;
        $marketsArr = $response->results[0]->markets;
        
        //Test data
        
        //$response = json_decode(file_get_contents( Url::base(true).'/data.json'));
        //$event = $response->data->items->results[0]->event;
        //$marketsArr = $response->data->items->results[0]->markets;
        
        //echo '<pre>';print_r($response);die;
        
        $marketsNew = [];
        
        foreach ( $marketsArr as $markets ){
            
            $marketType = MarketType::findOne([ 'market_type'=> $markets->description->marketType,'status' => 1 ]);
            
            if( $marketType != null ){
                $marketsNew[] = [
                    'rules'     => $markets->licence->rules,
                    'market'    => $markets->market,
                    'runners'   => $markets->runners
                ];
            }
            
        }
        //echo '<pre>';print_r($markets);die;
        $items = [
            'event' => $event,
            //'rules' => $rules,
            'markets' => $marketsNew
            //'runners' => $runners
        ];
        
        //echo '<pre>';print_r($markets);die;
        
        return [ "status" => 1 , "data" => [ "items" => $items ] ];
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
        
        $query = Event::find()
        ->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'event_slug' , 'img' , 'icon' , 'market_count', 'created_at' , 'updated_at' , 'status' ] )
            //->from( Events::tableName() . ' e' )
            ->andWhere( [ 'status' => [1,2] ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "event_type_name" , $filters[ "title" ] ] );
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
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    // Event: Commentary
    public function actionCommentary(){
        
        $eventCommentary = $globalCommentary = 'No data!';
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
        
            $id = \Yii::$app->request->get( 'id' );
            
            $commentaryEvent = GlobalCommentary::findOne(['event_id'=>$id]);
            
            if( $commentaryEvent != null ){
                $eventCommentary = $commentaryEvent->title;
            }
            
        }
            
        $commentary = Setting::findOne(['key'=>'GLOBAL_COMMENTARY' , 'status'=>1 ]);
        
        if( $commentary != null ){
            $globalCommentary = $commentary->value;
        }
        
        $data = [ 'event_commentary'=>$eventCommentary, 'global_commentary' => $globalCommentary ];
        $response = [
            "status" => 1 ,
            "data" => $data,
        ];
        
        return $response;
    }
    
    public function actionManualSession()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eID = \Yii::$app->request->get( 'id' );
            $query = ManualSession::find()->select(['id' , 'event_id', 'title' , 'yes' , 'no' ])
                ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $eID ] );
            
            $countQuery = clone $query; $count =  $countQuery->count();
            
            $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
            
            $response =  [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    public function actionManualSessionBalltoball()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $msId = \Yii::$app->request->get( 'id' );
            
            $manualSession = ManualSession::find()->select(['id' , 'event_id', 'title' ])
            ->where( [ 'status_ball_to_ball' => 1 , 'status' => 1 , 'game_over' => 'NO' , 'id' => $msId ] )->one();
            
            if( $manualSession != null ){
                
                $query = BallToBallSession::find()->select(['id' , 'event_id', 'manual_session_id' , 'over' , 'ball' , 'yes' , 'no' ])
                ->where( [ 'status' => 1 , 'manual_session_id' => $msId ] );
                
                $countQuery = clone $query; $count =  $countQuery->count();
                
                $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
                
                if( $models != null ){
                    $response =  [ "status" => 1 , "data" => [ "items" => $models ,"title" => $manualSession->title , "count" => $count ] ];
                }else{
                    $response =  [ "status" => 1 , "data" => [ "items" => [] ,"title" => $manualSession->title , "count" => 0 ] ];
                }
                
            }else{
                $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            }
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    public function getMasterName($id){
        
        if( $id != null ){
            
            $user = User::find()->select(['username'])->where([ 'id' => $id ])->one();
            if( $user != null ){
                return $user->username;
            }else{
                return 'undefine';
            }
            
        }
        return 'undefine';
        
    }
    
    public function getMasterId($id){
        
        if( $id != null ){
            
            $user = User::find()->select(['parent_id'])->where([ 'id' => $id ])->one();
            if( $user != null ){
                return $user->parent_id;
            }else{
                return '1';
            }
            
        }
        return '1';
        
    }
    
    public function actionCreateplacebet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( $r_data['session_type'] != 'fancy' ){
                
                $data['PlaceBet'] = $r_data;
                $model = new PlaceBet();
                
                if ($model->load($data)) {
                    
                    if( $model->event_id != null ){
                        
                        $play = EventsPlayList::findOne(['event_id' => $model->event_id , 'game_over' => 'NO' ,'status' => 1 ]);
                        if( $play == null ){
                            $response[ "error" ] = [
                                "message" => "This event is already closed!" ,
                                "data" => $model->errors
                            ];
                            return $response;
                        }
                        
                    }
                    
                    if( $this->defaultMinStack() != 0 && $this->defaultMinStack() > $model->size ){
                        $minStack = $this->defaultMinStack();
                        $response[ "error" ] = [
                            "message" => "Minimum stack value is ".$minStack ,
                            "data" => $model->errors
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxStack() != 0 && $this->defaultMaxStack() < $model->size ){
                        $maxStack = $this->defaultMaxStack();
                        $response[ "error" ] = [
                            "message" => "Maximum stack value is ".$maxStack ,
                            "data" => $model->errors
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxProfit() != 0 ){
                        
                        $maxProfit = $this->defaultMaxProfit();
                        
                        if( $model->bet_type == 'back' ){
                            $profit = ( $model->size*$model->price ) - $model->size;
                        }else{
                            $profit = $model->size;
                        }
                        
                        if( $maxProfit < $profit){
                            $response[ "error" ] = [
                                "message" => "Maximum profit value is ".$maxProfit ,
                                "data" => $model->errors
                            ];
                            return $response;
                        }
                    }
                    
                    /*if( isset(\Yii::$app->user->id) ){
                     $uid = \Yii::$app->user->id;
                     }else{
                     $user = User::find()->select(['id'])->where(['username' => $data['PlaceBet']['username']])->one();
                     $uid = $user->id;
                     }*/
                    
                    $uid = \Yii::$app->user->id;
                    
                    if( $model->bet_type == 'back' ){
                        $model->win = ( $model->size*$model->price ) - $model->size;
                        $model->loss = $model->size;
                    }else{
                        $model->win = $model->size;
                        if( $model->price > 1 ){
                            $model->loss = ($model->price-1)*$model->size;
                        }else{
                            $model->loss = $model->price*$model->size;
                        }
                    }
                    
                    if( $this->currentBalance(false,$uid) < ( $model->loss+1)  ){
                        $response[ "error" ] = [
                            "message" => "Insufficient funds!" ,
                            "data" => $model->errors
                        ];
                        return $response;
                    }
                    
                    $model->bet_status = 'Pending';
                    $model->user_id = $uid;
                    $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                    $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                    
                    if( $model->session_type == 'match_odd' ){
                        
                        if( $model->bet_type == 'back' ){
                            $model->ccr = round ( ( ($model->win)*$this->clientCommissionRate() )/100 );
                        }else{
                            $model->ccr = round ( ( ($model->size)*$this->clientCommissionRate() )/100 );
                        }
                        
                    }else{
                        $model->ccr = 0;
                    }
                    
                    $model->sport_id = 4;
                    $model->status = 1;
                    $model->created_at = $model->updated_at = time();
                    $model->ip_address = $this->get_client_ip();
                    
                    if( $model->save() ){
                        
                        $bet = $model;
                        
                        $model = new TransactionHistory();
                        
                        $model->user_id = $uid;
                        $model->bet_id = $bet->id;
                        $model->client_name = $bet->client_name;
                        $model->transaction_type = 'DEBIT';
                        $model->transaction_amount = $bet->loss;
                        $model->current_balance = $this->currentBalance($bet->loss , $uid);
                        $model->status = 1;
                        $model->created_at = $model->updated_at = time();
                        $model->description = $this->getDescription($bet->id,$bet->event_id);
                        
                        if( $model->save() ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Place bet successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $bet->delete();
                            
                            $response[ "error" ] = [
                                "message" => "somthing wrong!" ,
                                "data" => $model->errors
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
                
            }else{
                
                //echo '<pre>';print_r($r_data);die;
                $rnr = explode(':', $r_data['runner']);
                
                if( !is_array( $rnr ) ){
                    
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                    
                }
                
                $rnr_val = explode('&', $rnr[1]);
                
                if( !is_array( $rnr_val ) ){
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                }
                
                
                if( $r_data['bet_type'] == 'yes' && $r_data['price'] != $rnr_val[0] ){
                    
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                    
                }
                if( $r_data['bet_type'] == 'no' && $r_data['price'] != $rnr_val[1] ){
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                }
                
                $data['PlaceBet'] = $r_data;
                $model = new PlaceBet();
                
                if ($model->load($data)) {
                    
                    if( $model->event_id != null ){
                        
                        $manualSession = ManualSession::findOne([ 'id' => $model->market_id , 'event_id' => $model->event_id , 'game_over' => 'NO' , 'status' => 1 ]);
                        if( $manualSession == null ){
                            $response[ "error" ] = [
                                "message" => "This session is already closed!"
                            ];
                            return $response;
                        }
                        
                        $play = EventsPlayList::findOne(['event_id' => $model->event_id , 'game_over' => 'NO' , 'status' => 1 ]);
                        if( $play == null ){
                            $response[ "error" ] = [
                                "message" => "This event is already closed!"
                            ];
                            return $response;
                        }
                        
                    }
                    
                    if( $this->defaultMinStack() != 0 && $this->defaultMinStack() > $model->size ){
                        $minStack = $this->defaultMinStack();
                        $response[ "error" ] = [
                            "message" => "Minimum stack value is ".$minStack
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxStack() != 0 && $this->defaultMaxStack() < $model->size ){
                        $maxStack = $this->defaultMaxStack();
                        $response[ "error" ] = [
                            "message" => "Maximum stack value is ".$maxStack
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxProfit() != 0 ){
                        
                        $maxProfit = $this->defaultMaxProfit();
                        
                        $profit = $model->size;
                        
                        if( $maxProfit < $profit){
                            $response[ "error" ] = [
                                "message" => "Maximum profit value is ".$maxProfit
                            ];
                            return $response;
                        }
                    }
                    
                    /*if( isset(\Yii::$app->user->id) ){
                     $uid = \Yii::$app->user->id;
                     }else{
                     $user = User::find()->select(['id'])->where(['username' => $data['PlaceBet']['username']])->one();
                     $uid = $user->id;
                     }*/
                    
                    $uid = \Yii::$app->user->id;
                    
                    $model->win = $model->size;
                    $model->loss = $model->size;
                    
                    if( $this->currentBalance(false,$uid) < ( $model->loss+1)  ){
                        $response[ "error" ] = [
                            "message" => "Insufficient funds!"
                        ];
                        return $response;
                    }
                    
                    $model->bet_status = 'Pending';
                    $model->user_id = $uid;
                    $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                    $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                    
                    $model->ccr = 0;
                    $model->sport_id = 4;
                    $model->status = 1;
                    $model->created_at = $model->updated_at = time();
                    $model->ip_address = $this->get_client_ip();
                    
                    if( $model->save() ){
                        
                        $bet = $model;
                        
                        $model = new TransactionHistory();
                        
                        $model->user_id = $uid;
                        $model->bet_id = $bet->id;
                        $model->client_name = $bet->client_name;
                        $model->transaction_type = 'DEBIT';
                        $model->transaction_amount = $bet->loss;
                        $model->current_balance = $this->currentBalance($bet->loss , $uid);
                        $model->status = 1;
                        $model->created_at = $model->updated_at = time();
                        $model->description = $this->getDescription($bet->id,$bet->event_id);
                        
                        if( $model->save() ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Place bet successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $bet->delete();
                            
                            $response[ "error" ] = [
                                "message" => "somthing wrong!" ,
                                "data" => $model->errors
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
                
            }
            
        }
        
        return $response;
    }
    
    public function getDescription($betId,$eventId)
    {
        $runner = $type = $session = $event_name = $size = '';
        
        $betData = PlaceBet::find()->select(['runner','bet_type','session_type' , 'size'])
        ->where([ 'id' => $betId,'status'=>1, 'bet_status' => 'Pending' ])->one();
        if( $betData != null ){
            $runner = $betData->runner;
            $type = $betData->bet_type;
            $session = $betData->session_type;
            $size = $betData->size;
        }
        
        $eventData = EventsPlayList::find()->select(['event_name'])
        ->where([ 'event_id' => $eventId, 'status'=>1 ])->one();
        
        if( $eventData != null ){
            $event_name = $eventData->event_name;
        }
        
        return 'Cricket | '.$event_name.' | '.$runner.' | '.$session.' | '.$type.' | '.$size;
    }
    
    // Function to get the client Commission Rate
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
    }
    
    // Function to check max stack val
    public function defaultMaxStack()
    {
        $max_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_stack;
        }
    }
    
    // Function to check min stack val
    public function defaultMinStack()
    {
        $min_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MIN_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $min_stack;
        }
    }
    
    // Function to check max profit limit val
    public function defaultMaxProfit()
    {
        $max_profit = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_PROFIT_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_profit;
        }
    }
    
    // Function to get the client current Balance
    public function currentBalance($price = false , $uid)
    {
        $user = User::findOne( $uid );
        
        if( $price != false ){
            $user->balance = ( $user->balance - $price );
            if( $user->save(['balance']) ){
                return $user->balance;
            }else{
                return $user->balance;
            }
        }else{
            return $user->balance;
        }
    }
    
    // Function to get the client IP address
    function get_client_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
