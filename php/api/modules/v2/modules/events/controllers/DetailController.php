<?php
namespace api\modules\v2\modules\events\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\Event;
use common\models\PlaceBet;
use common\models\TransactionHistory;
use yii\helpers\Url;
use common\models\MarketType;
use common\models\User;
use common\models\Setting;
use common\models\ManualSession;
use common\models\EventsPlayList;
use common\models\BallToBallSession;
use common\models\GlobalCommentary;
use common\models\FavoriteMarket;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionMatchOddData;

class DetailController extends \common\controllers\aController  // \yii\rest\Controller
{
    private $marketIdsArr = [];
    
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
    
    //Event: Index
    public function actionIndex()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );
            
            if( in_array($eventId, $this->checkUnBlockList($uid) ) ){
                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];
                return $response;exit;
            }

            
            $eventData = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id'=> $eventId,'game_over'=>'NO','status'=>1])
            ->createCommand(Yii::$app->db3)->queryOne();

            $eventArr = [];
            if( $eventData != null ){
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];

                if( in_array( $sportId, $this->checkUnBlockSportList($uid) ) ){
                    $response = [ "status" => 1 , "data" => null , "msg" => "This Sport Block by Parent!!" ];
                    return $response;exit;
                }

                $scoreData = $this->getScoreData($sportId,$eventId);
                if( $sportId == '1' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $commentaryData = $this->getCommentaryData($eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Football',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'score' => $scoreData,
                        'commentary' => $commentaryData
                    ];
                    
                }else if( $sportId == '2' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $commentaryData = $this->getCommentaryData($eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Tennis',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'score' => $scoreData,
                        'commentary' => $commentaryData
                    ];
                }else if( $sportId == '4' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $matchodd2Data = $this->getDataManualMatchOdd($uid,$eventId);
                    $fancy2Data = $this->getDataFancy($uid,$eventId);
                    $fancyData = $this->getDataManualSessionFancy($uid,$eventId);
                    $lotteryData = $this->getDataLottery($uid,$eventId);
                    $commentaryData = $this->getCommentaryData($eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'match_odd2' => $matchodd2Data,
                        'fancy2' => $fancy2Data,
                        'fancy' => $fancyData,
                        'lottery' => $lotteryData,
                        'score' => $scoreData,
                        'commentary' => $commentaryData
                    ];
                }
                
                $this->marketIdsArr[] = [
                    'type' => 'match_odd',
                    'market_id' => $marketId,
                    'event_id' => $eventId
                ];
                
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr , 'marketIdsArr' => $this->marketIdsArr ] ];
        }
        
        return $response;
        
    }
    
    //get ScoreData
    public function getScoreData($sportId,$eventId){
        
        $socre = [];
        if( $sportId == 4 ){
            
            $socre = [
                '0' => [
                    'name' => 'RCB',
                    'run' => '134',
                    'wkt' => '7',
                    'over' => '20'
                ],
                '1' => [
                    'name' => 'KKR',
                    'run' => '130',
                    'wkt' => '6',
                    'over' => '19.5'
                ]
            ];
            
        }
        
        return $socre;
        
    }

    //get ScoreData
    public function getCommentaryData($eventId){

        $eventCommentary = 'No data!';

        if( $eventId != null ){

            $commentary = (new \yii\db\Query())
                ->select(['title'])->from('global_commentary')
                ->where(['event_id'=>$eventId])->one();

            if( $commentary != null ){
                $eventCommentary = $commentary['title'];
            }

        }


        return $eventCommentary;

    }

    //check database function
    public function checkUnBlockSportList($uId)
    {
        //$uId = \Yii::$app->user->id;

        $newList = [];

        $listArr = (new \yii\db\Query())
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id'=>$uId ])
            ->andWhere(['!=','byuser',$uId])->createCommand(Yii::$app->db3)->queryAll();

        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }
        }
        return $newList;

    }

    //check database function
    public function checkUnBlockList($uId)
    {
        //$uId = \Yii::$app->user->id;
        //$user = User::find()->select( ['parent_id'] )
        //->where(['id'=>$uId])->one();

        $user = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where(['id' => $uId])->createCommand(Yii::$app->db3)->queryOne();

        $pId = 1;
        if( $user != null ){
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$pId,'market_type' => 'all' ])->createCommand(Yii::$app->db3)->queryAll();
        
        if( $listArr != null ){
            
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
            
            return $newList;
        }else{
            return [];
        }
        
    }
    
    //Event: getDataMatchOdd
    public function getDataMatchOdd($uid,$marketId,$eventId)
    {
        $marketListArr = null;
        
        $event = (new \yii\db\Query())
        ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit','bet_delay'])
        ->from('events_play_list')
        ->where(['game_over'=>'NO','status'=>1])
        ->andWhere(['market_id' => $marketId])
        ->createCommand(Yii::$app->db3)->queryOne();
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
            //$betDelay = $event['bet_delay'];
            $betDelay = $this->getBetDelay($sportId, $eventId, $marketId,'match_odd');
            $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
            
            $runnerData = (new \yii\db\Query())
            ->select(['selection_id','runner'])
            ->from('events_runners')
            ->where(['event_id' => $eventId ])
            ->createCommand(Yii::$app->db3)->queryAll();
            
            if( $runnerData != null ){
                
                $cache = \Yii::$app->cache;
                $oddsData = $cache->get($marketId);
                $oddsData = json_decode($oddsData);
//                if( $oddsData != null && $oddsData->odds != null ){
//                    $i=0;
//                    foreach ( $oddsData->odds as $odds ){
//
//                        if( $i == 0 ){
//                            $back[$odds->selectionId][0] = [
//                                'price' => $odds->backPrice1,
//                                'size' => $odds->backSize1,
//                            ];
//                        }
//                        if( $i == 1 ){
//                            $back[$odds->selectionId][1] = [
//                                'price' => $odds->backPrice2,
//                                'size' => $odds->backSize2,
//                            ];
//                        }
//                        if( $i == 2 ){
//                            $back[$odds->selectionId][2] = [
//                                'price' => $odds->backPrice3,
//                                'size' => $odds->backSize3,
//                            ];
//                        }
//
//                        if( $i == 0 ){
//                            $lay[$odds->selectionId][0] = [
//                                'price' => $odds->layPrice1,
//                                'size' => $odds->laySize1,
//                            ];
//                        }
//                        if( $i == 1 ) {
//                            $lay[$odds->selectionId][1] = [
//                                'price' => $odds->layPrice2,
//                                'size' => $odds->laySize2,
//                            ];
//                        }
//                        if( $i == 2 ){
//                            $lay[$odds->selectionId][2] = [
//                                'price' => $odds->layPrice3,
//                                'size' => $odds->laySize3,
//                            ];
//                        }
//
//                        $i++;
//                    }
//                }
                
                $i=0;
                foreach( $runnerData as $runner ){
                    $exchange = [];
                    $runnerName = $runner['runner'];
                    $selectionId = $runner['selection_id'];

                    if( $oddsData != null && $oddsData->odds != null ){
                        $i=0;
                        foreach ( $oddsData->odds as $odds ){
                            if( $selectionId == $odds->selectionId ){
                                $exchange = $odds;
                            }
                        }
                    }

                    $runnersArr[] = [
                        'slug' => $slug[$sportId],
                        'sportId' => $sportId,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'selectionId' => $selectionId,
                        'is_favorite' => $isFavorite,
                        'runnerName' => $runnerName,
                        'profit_loss' => $this->getProfitLossMatchOdds($uid,$marketId,$eventId,$selectionId,'match_odd'),
                        'is_profitloss' => 0,
                        'betDelay' => $betDelay,
                        'sessionType' => 'match_odd',
                        'exchange' => $exchange
//                        'exchange' => [
//                            'back' => $back[$selectionId],
//                            'lay' => $lay[$selectionId],
//                        ]
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
                'is_favorite' => $isFavorite,
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
        ->select(['id','event_id','market_id','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','bet_delay','info'])
        ->from('manual_session_match_odd')
        ->where(['status' => 1 , 'game_over' => 'NO' , 'event_id' => $eventId])
        ->one();
        //echo '<pre>';print_r($market);die;
        $runners = [];
        if($market != null){
            
            $marketId = $market['market_id'];
            $minStack = $market['min_stack'];
            $maxStack = $market['max_stack'];
            $maxProfit = $market['max_profit'];
            $maxProfitLimit = $market['max_profit_limit'];
            //$betDelay = $market['bet_delay'];
            $betDelay = $this->getBetDelay(4, $eventId, $marketId,'match_odd2');
            
            $matchOddData = (new \yii\db\Query())
            ->select(['id','sec_id','runner','lay','back'])
            ->from('manual_session_match_odd_data')
            ->andWhere( [ 'market_id' => $marketId ] )
            ->all();
            
            if( $matchOddData != null ){
                foreach( $matchOddData as $data ){
                    
                    $profitLoss = $this->getProfitLossMatchOdds($uid,$marketId,$eventId,$data['sec_id'],'match_odd2');
                    $runners[] = [
                        'id' => $data['id'],
                        'sportId' => 4,
                        'market_id' => $marketId,
                        'event_id' => $eventId,
                        'sec_id' => $data['sec_id'],
                        'runner' => $data['runner'],
                        'profitloss' => $profitLoss,
                        'is_profitloss' => 0,
                        'suspended' => $market['suspended'],
                        'ballRunning' => $market['ball_running'],
                        'betDelay' => $betDelay,
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
            
            $items = [
                'marketName' => 'Book Maker Market 0% Commission ',
                'sportId' => 4,
                'market_id' => $marketId,
                'event_id' => $eventId,
                'suspended' => $market['suspended'],
                'ballRunning' => $market['ball_running'],
                'is_favorite' => $this->isFavorite($eventId,$marketId,'match_odd2'),
                'minStack' => $minStack,
                'maxStack' => $maxStack,
                'maxProfit' => $maxProfit,
                'maxProfitLimit' => $maxProfitLimit,
                'info' => $market['info'],
                'runners' => $runners,
            ];
            
            $this->marketIdsArr[] = [
                'type' => 'match_odd2',
                'sport_id' => 4,
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
        ->all();
        //echo '<pre>';print_r($marketList);die;
        $items = [];

        if( $marketList != null ){

            $suspended = $ballRunning = 'N';

//            $suspendedCommtry = $ballRunningCommtry = false;
//            $commentary = (new \yii\db\Query())
//                ->select(['title'])
//                ->from('global_commentary')
//                ->where(['sport_id' => 4 , 'event_id'=>$eventId])
//                ->one();
//
//            if( $commentary != null ){
//                if( $commentary['title'] == 'Suspended' ){
//                    $suspendedCommtry = true;
//                }
//                if( $commentary['title'] == 'Ball Running' ){
//                    $ballRunningCommtry = true;
//                }
//            }

            $cache = \Yii::$app->cache;

            foreach ( $marketList as $market ){
                $suspended = $ballRunning = 'N';
                $marketId = $market['market_id'];

                if( $market['suspended'] == 'Y' ){
                    $suspended = 'Y';
                }
                if( $market['ball_running'] == 'Y' ){
                    $ballRunning = 'Y';
                }

                $minStack = $market['min_stack'];
                $maxStack = $market['max_stack'];
                $maxProfit = $market['max_profit'];
                $maxProfitLimit = $market['max_profit_limit'];
                //$betDelay = $market['bet_delay'];
                $betDelay = $this->getBetDelay(4, $eventId, $marketId,'fancy2');
                $key = $marketId;

                $data = $cache->get($key);
                $data = json_decode($data);
                //echo '<pre>';print_r($data);die;
                
                if( $data != null && isset( $data->data ) ){
                    
                    if( $data->ballRunning == 'Y' ){
                        $ballRunning = 'Y';
                    }
                    if( $data->suspended == 'Y' ){
                        $suspended = 'Y';
                    }

//                    if( $suspendedCommtry == true ){
//                        $suspended = 'Y';
//                    }
//                    if( $ballRunningCommtry == true ){
//                        $ballRunning = 'Y';
//                    }

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
                    'is_book' => $this->isBookOn($market['market_id'],'fancy2'),
                    'is_favorite' => $this->isFavorite($market['event_id'],$market['market_id'],'fancy2'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'data' => $dataVal,
                ];
                
                
                $this->marketIdsArr[] = [
                    'type' => 'fancy2',
                    'sport_id' => 4,
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
                //$betDelay = $data['bet_delay'];
                $betDelay = $this->getBetDelay(4, $eventId, $data['market_id'],'fancy');
                
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
                    'is_book' => $this->isBookOn($data['market_id'],'fancy'),
                    'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'fancy'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'info' => $data['info'],
                    'data' => $dataVal,
                    
                ];
                
                $this->marketIdsArr[] = [
                    'type' => 'fancy',
                    'sport_id' => 4,
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
                    
                    $profitLoss = $this->getLotteryProfitLossOnBet($data['event_id'], $data['market_id'] , $n );
                    
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
                    'is_book' => $this->isBookOn($data['market_id'],'lottery'),
                    'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'lottery'),
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
    
    //Event: isBookOn
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
    
    //Event: isFavorite
    public function isFavorite($eventId,$marketId,$sessionType)
    {
        $uid = \Yii::$app->user->id;
        
        $find = (new \yii\db\Query())
        ->select(['id'])->from('favorite_market')
        ->where(['user_id'=>$uid,'event_id' => $eventId,'market_id' => $marketId,'market_type' => $sessionType ])
        ->one();
        
        if( $find != null ){
            return '1';
        }
        return '0';
        
    }
    
    //Event: get Profit Loss Match Odds
    public function getProfitLossMatchOdds($userId,$marketId,$eventId,$selId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;
        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){
            
            $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];

            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])->from('place_bet')
                ->where($where)->createCommand(Yii::$app->db3)->queryOne();
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin['val']; }
            
            $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
            //->where($where)->andWhere($andWhere)->asArray()->all();

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])->from('place_bet')
                ->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin['val']; }
            
            $where = [ 'match_unmatch'=>1, 'sec_id' => $selId,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $totalWin = $backWin + $layWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])->from('place_bet')
                ->where($where)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss['val']; }
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])->from('place_bet')
                ->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss['val']; }
            
            $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $totalLoss = $backLoss + $layLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        return $total;
        
    }
    
    //Event: get Lottery Profit Loss On Bet
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
    
    // Cricket: ProfitLossFancy API
    public function actionProfitLossFancyBook()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];
            
            $profitLossData = $this->getProfitLossFancy($eventId,$marketId,$sessionType);
            
            $response = [ 'status' => 1 , 'data' => $profitLossData ];
            
        }
        
        return $response;
    }
    
    // getProfitLossFancy
    public function getProfitLossFancy($eventId,$marketId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        
        $where = [ 'session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ];
        
        $betList = PlaceBet::find()
        ->select(['bet_type','price','win','loss'])
        ->where( $where )->asArray()->all();
        
//        $betMinRun = PlaceBet::find()
//        ->select(['MIN( price ) as price'])
//        ->where( $where )->one();
//
//        $betMaxRun = PlaceBet::find()
//        ->select(['MAX( price ) as price'])
//        ->where( $where )->one();
//
//        if( isset( $betMinRun->price ) ){
//            $minRun = $betMinRun->price-1;
//        }
//
//        if( isset( $betMaxRun->price ) ){
//            $maxRun = $betMaxRun->price+1;
//        }

        $dataReturn = null;
        if( $betList != null ){
            $dataReturn = [];

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
                
                $where = [ 'bet_type' => 'no','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ];
                $betList1 = PlaceBet::find()
                ->select('SUM( win ) as winVal')
                ->where( $where )->andWhere(['>','price',$i])
                ->asArray()->all();
                
                $where = [ 'bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ];
                $betList2 = PlaceBet::find()
                ->select('SUM( win ) as winVal')
                ->where( $where )->andWhere(['<=','price',$i])
                ->asArray()->all();
                
                $where = [ 'bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ];
                $betList3 = PlaceBet::find()
                ->select('SUM( loss ) as lossVal')
                ->where( $where )->andWhere(['>','price',$i])
                ->asArray()->all();
                
                $where = [ 'bet_type' => 'no','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ];
                $betList4 = PlaceBet::find()
                ->select('SUM( loss ) as lossVal')
                ->where( $where )->andWhere(['<=','price',$i])
                ->asArray()->all();
                
                if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }
                
                $profit = ( $winVal1 + $winVal2 );
                $loss = ( $lossVal1 + $lossVal2 );
                
                $dataReturn[] = [
                    'price' => $i,
                    'profitLoss' => $profit-$loss,
                ];
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
    
    // action Check BetDelay
    public function getBetDelay($sportId,$eventId,$marketId,$type)
    {
        $betDelay = [];$betDelayVal = 0;
        $uid = \Yii::$app->user->id;
        
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
        
        if( $type == 'match_odd2' ){
            $matchOdd2 = (new \yii\db\Query())
            ->select(['bet_delay'])->from('manual_session_match_odd')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $matchOdd2 != null ){
                array_push($betDelay, $matchOdd2['bet_delay']);
            }
        }
        
        if( $type == 'fancy' ){
            $fancy = (new \yii\db\Query())
            ->select(['bet_delay'])->from('manual_session')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $fancy != null ){
                array_push($betDelay, $fancy['bet_delay']);
            }
        }
        if( $type == 'fancy2' ){
            
            $fancy2 = (new \yii\db\Query())
            ->select(['bet_delay'])->from('market_type')
            ->where(['market_id'=>$marketId ])->one();
            
            if( $fancy2 != null ){
                array_push($betDelay, $fancy2['bet_delay']);
            }
            
        }
        
        if( $betDelay != null ){
            $betDelayVal = max($betDelay);
        }
        
        return $betDelayVal;
    }
    
}
