<?php
namespace api\modules\v2\modules\events\controllers;

use common\models\PlaceBet;
use yii\helpers\ArrayHelper;

class MyMarketController extends \common\controllers\aController  // \yii\rest\Controller
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

    //Event: My Market Event List
    public function actionEventList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null !== \Yii::$app->user->id ){

            $uid = \Yii::$app->user->id;
            $eventArr = [];
            $betListEvent = (new \yii\db\Query())
                ->select(['event_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->groupBy(['event_id'])->all();

            if( $betListEvent != null ){

                foreach ( $betListEvent as $event ){
                    $eventId = $event['event_id'];

                    $eventData = (new \yii\db\Query())
                        ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                        ->from('events_play_list')
                        ->where(['event_id'=> $eventId,'game_over'=>'NO','status'=>1])
                        ->andWhere(['!=','play_type' , 'CLOSED'])
                        ->one();

                    if( $eventData != null ){
                        $marketId = $eventData['market_id'];
                        $title = $eventData['event_name'];
                        $sportId = $eventData['sport_id'];
                        if( $sportId == '1' ){
                            $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                            $eventArr[] = [
                                'title' => $title,
                                'sport' => 'Football',
                                'event_id' => $eventId,
                                'sport_id' => $sportId,
                                'match_odd' => $matchoddData
                            ];

                        }else if( $sportId == '2' ){
                            $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                            $eventArr[] = [
                                'title' => $title,
                                'sport' => 'Tennis',
                                'event_id' => $eventId,
                                'sport_id' => $sportId,
                                'match_odd' => $matchoddData
                            ];
                        }else if( $sportId == '4' ){
                            $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                            $matchodd2Data = $this->getDataManualMatchOdd($uid,$eventId,$title);
                            $fancy2Data = $this->getDataFancy($uid,$eventId,$title);
                            $fancyData = $this->getDataManualSessionFancy($uid,$eventId,$title);
                            $lotteryData = $this->getDataLottery($uid,$eventId,$title);
                            $eventArr[] = [
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

                        $this->marketIdsArr[] = [
                            'type' => 'match_odd',
                            'sport_id' => $sportId,
                            'market_id' => $marketId,
                            'event_id' => $eventId
                        ];

                    }

                }

            }

            $response = [ "status" => 1 , "data" => [ "items" => $eventArr , 'marketIdsArr' => $this->marketIdsArr ] ];
        }

        return $response;

    }

    //Event: getDataMatchOdd
    public function getDataMatchOdd($uid,$marketId,$eventId)
    {
        $marketListArr = null;

        $betMatchOdd = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id'=>$uid,'market_id' => $marketId,'event_id' => $eventId,'session_type' => 'match_odd','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->one();
        //echo '<pre>';print_r($betMatchOdd);die;
        if( $betMatchOdd != null ){
            $marketId = $betMatchOdd['market_id'];

            $event = (new \yii\db\Query())
                ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit','bet_delay'])
                ->from('events_play_list')
                ->where(['game_over'=>'NO','status'=>1])
                ->andWhere(['market_id' => $marketId])
                ->one();
            //echo '<pre>';print_r($event);die;
            if( $event != null ){

                $slug = ['1' => 'football' , '2' => 'tennis' , '4' => 'cricket'];

                $marketId = $event['market_id'];
                $eventId = $event['event_id'];
                $title = $event['event_name'];
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
                    ->all();

                if( $runnerData != null ){

                    $cache = \Yii::$app->cache;
                    $oddsData = $cache->get($marketId);
                    $oddsData = json_decode($oddsData);
                    if( $oddsData != null && $oddsData->odds != null ){
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
                            'isFavorite' => $isFavorite,
                            'runnerName' => $runnerName,
                            'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                            'is_profitloss' => 0,
                            'betDelay' => $betDelay,
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
                    'title' => $title,
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'maxProfitAllLimit' => $maxProfitAllLimit,
                    'isFavorite' => $isFavorite,
                    'time' => $time,
                    'marketName'=>'Match Odds',
                    'matched' => '',
                    'runners' => $runnersArr,
                ];


            }

        }

        return $marketListArr;
    }

    //Event: getDataManualMatchOdd
    public function getDataManualMatchOdd($uid,$eventId,$title)
    {
        $betMatchOdd = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'match_odd2','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->one();
        $items = null;
        if( $betMatchOdd != null ){
            $marketId = $betMatchOdd['market_id'];

            $market = (new \yii\db\Query())
                ->select(['id','event_id','market_id','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
                ->from('manual_session_match_odd')
                ->where(['status' => 1 , 'game_over' => 'NO' , 'market_id' => $marketId, 'event_id' => $eventId])
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

                foreach( $matchOddData as $data ){

                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$data['sec_id'],'match_odd2');
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

                $items = [
                    'marketName' => 'Book Maker Market 0% Commission ',
                    'sportId' => 4,
                    'event_title' => $title,
                    'market_id' => $marketId,
                    'event_id' => $eventId,
                    'suspended' => $market['suspended'],
                    'ballRunning' => $market['ball_running'],
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'is_favorite' => $this->isFavorite($eventId,$marketId,'match_odd2'),
                    'runners' => $runners,
                ];
            }

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
    public function getDataFancy($uid,$eventId,$title)
    {
        $betListFancy = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'fancy2','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['market_id'])->all();
        //echo '<pre>';print_r($betListFancy);die;
        $items = [];
        if( $betListFancy != null ){
            $marketArr = [];

            foreach ( $betListFancy as $market ){
                $marketArr[] = $market['market_id'];
            }

            $marketList = (new \yii\db\Query())
                ->select('*')
                ->from('market_type')
                ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                ->andWhere(['IN','market_id',$marketArr])
                ->all();
            //echo '<pre>';print_r($marketList);die;
            $items = [];

            if( $marketList != null ){

                $status = 'N';
                foreach ( $marketList as $market ){
                    $marketId = $market['market_id'];
                    $suspended = $market['suspended'];
                    $ballRunning = $market['ball_running'];

                    $minStack = $market['min_stack'];
                    $maxStack = $market['max_stack'];
                    $maxProfit = $market['max_profit'];
                    $maxProfitLimit = $market['max_profit_limit'];
                    //$betDelay = $market['bet_delay'];
                    $betDelay = $this->getBetDelay(4, $eventId, $marketId,'fancy2');

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
                        'event_title' => $title,
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

        }

        return $items;
    }

    //Event: getDataManualSessionFancy
    public function getDataManualSessionFancy($uid,$eventId,$title)
    {
        $betListFancy = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'fancy','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['market_id'])->all();
        //echo '<pre>';print_r($betListFancy);die;
        $items = [];
        if( $betListFancy != null ){
            $marketArr = [];

            foreach ( $betListFancy as $market ){
                $marketArr[] = $market['market_id'];
            }

            $marketList = (new \yii\db\Query())
                ->select('*')
                ->from('manual_session')
                ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                ->andWhere(['IN','market_id',$marketArr])
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
                        'event_title' => $title,
                        'suspended' => $data['suspended'],
                        'ballRunning' => $data['ball_running'],
                        'data' => $dataVal,
                        'sportId' => 4,
                        'slug' => 'cricket',
                        'is_book' => $this->isBookOn($data['market_id'],'fancy'),
                        'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'fancy'),
                        'minStack' => $minStack,
                        'maxStack' => $maxStack,
                        'maxProfit' => $maxProfit,
                        'maxProfitLimit' => $maxProfitLimit,
                        'betDelay' => $betDelay,
                    ];

                    $this->marketIdsArr[] = [
                        'type' => 'fancy',
                        'sport_id' => 4,
                        'market_id' => $data['market_id'],
                        'event_id' => $eventId
                    ];
                }
            }

        }
        return $items;
    }

    //Event: getDataLottery
    public function getDataLottery($uid,$eventId,$title)
    {
        $betListLottery = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'lottery','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['market_id'])->all();
        //echo '<pre>';print_r($betListLottery);die;
        $items = [];
        if( $betListLottery != null ){

            $marketArr = [];
            foreach ( $betListLottery as $market ){
                $marketArr[] = $market['market_id'];
            }

            $marketList = (new \yii\db\Query())
                ->select(['id','market_id','event_id','title','rate','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
                ->from('manual_session_lottery')
                ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                ->andWhere(['IN','market_id',$marketArr])
                ->all();
            //echo '<pre>';print_r($marketList);die;
            $items = [];
            if($marketList != null){
                foreach($marketList as $data){
                    $numbers = [];

                    $minStack = $data['min_stack'];
                    $maxStack = $data['max_stack'];
                    $maxProfit = $data['max_profit'];
                    $maxProfitLimit = $data['max_profit_limit'];
                    $betDelay = $data['bet_delay'];

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
                        'event_title' => $title,
                        'minStack' => $minStack,
                        'maxStack' => $maxStack,
                        'maxProfit' => $maxProfit,
                        'maxProfitLimit' => $maxProfitLimit,
                        'betDelay' => $betDelay,
                        'numbers' => $numbers,
                        'is_book' => $this->isBookOn($data['market_id'],'lottery'),
                        'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'lottery'),
                    ];

                    /*$this->marketIdsArr[] = [
                        'type' => 'lottery',
                        'market_id' => $data['market_id'],
                        'event_id' => $eventId
                    ];*/

                }
            }

        }

        return $items;
    }

    //Event: My Market Event List
    public function actionEventListUNUSED()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null !== \Yii::$app->user->id ){

            $uid = \Yii::$app->user->id;
            $eventArr = [];
            $betListEvent = (new \yii\db\Query())
                ->select(['event_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->groupBy(['event_id'])->all();

            if( $betListEvent != null ){
                $eventListArr = [];
                foreach ( $betListEvent as $event ){
                    $eventListArr[] = $event['event_id'];
                }

                $eventData = (new \yii\db\Query())
                    ->select(['sport_id','event_id','event_name'])
                    ->from('events_play_list')
                    ->where(['game_over'=>'NO','status'=>1])
                    ->andWhere(['IN','event_id',$eventListArr])
                    ->all();

                if( $eventData != null ){
                    foreach ( $eventData as $event ){

                        $eventId = $event['event_id'];
                        $title = $event['event_name'];
                        $sportId = $event['sport_id'];
                        if( $sportId == '1' ){
                            $title = 'Football - '.$title;
                        }else if( $sportId == '2' ){
                            $title = 'Tennis - '.$title;
                        }else if( $sportId == '4' ){
                            $title = 'Cricket - '.$title;
                        }


                        $eventArr[] = [
                            'title' => $title,
                            'event_id' => $eventId,
                            'sport_id' => $sportId
                        ];

                    }
                }

            }

            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
        }

        return $response;

    }

    //Event: My Market Cricket
    public function actionMatchOdd()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null !== \Yii::$app->user->id && null != \Yii::$app->request->get( 'id' ) ){

            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );
            $marketListArr = [];

            $betMatchOdd = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'match_odd','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->one();
            //echo '<pre>';print_r($betMatchOdd);die;
            if( $betMatchOdd != null ){
                $marketId = $betMatchOdd['market_id'];

                $event = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['game_over'=>'NO','status'=>1])
                    ->andWhere(['market_id' => $marketId])
                    ->one();
                //echo '<pre>';print_r($event);die;
                if( $event != null ){

                    $marketId = $event['market_id'];
                    $eventId = $event['event_id'];
                    //$title = $event['event_name'];
                    $time = $event['event_time'];
                    $suspended = $event['suspended'];
                    $ballRunning = $event['ball_running'];
                    $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');

                    $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();

                    if( $runnerData != null ){

                        foreach( $runnerData as $runner ){
                            $back = $lay = ['price' => ' - ', 'size' => ' - '];
                            $runnerName = $runner['runner'];
                            $selectionId = $runner['selection_id'];

                            $runnersArr[] = [
                                'slug' => 'cricket',
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'selectionId' => $selectionId,
                                'isFavorite' => $isFavorite,
                                'runnerName' => $runnerName,
                                'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                'exchange' => [
                                    'back' => $back,
                                    'lay' => $lay
                                ]
                            ];

                        }

                    }

                    $marketListArr = [
                        'sportId' => 4,
                        'slug' => 'cricket',
                        'sessionType' => 'match_odd',
                        //'title' => $title,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'isFavorite' => $isFavorite,
                        'time' => $time,
                        'marketName'=>'Match Odds',
                        'matched' => '',
                        'runners' => $runnersArr,
                    ];


                }

            }

            $response = [ "status" => 1 , "data" => [ "items" => $marketListArr ] ];
        }

        return $response;
    }

    //Event: action MyMarket Manual Session MatchOdd
    public function actionManualSessionMatchOdd()
    {
        if( null !== \Yii::$app->user->id && null !== \Yii::$app->request->get( 'id' ) ){

            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );
            $items = [];
            $betMatchOdd = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'match_odd2','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->one();

            if( $betMatchOdd != null ){
                $marketId = $betMatchOdd['market_id'];

                $market = (new \yii\db\Query())
                    ->select(['id','event_id','market_id','suspended','ball_running'])
                    ->from('manual_session_match_odd')
                    ->where(['status' => 1 , 'game_over' => 'NO' , 'market_id' => $marketId, 'event_id' => $eventId])
                    ->one();
                //echo '<pre>';print_r($market);die;
                $items = $runners = [];
                if($market != null){

                    $matchOddData = (new \yii\db\Query())
                        ->select(['id','sec_id','runner','lay','back'])
                        ->from('manual_session_match_odd_data')
                        ->andWhere( [ 'market_id' => $marketId ] )
                        ->all();

                    foreach( $matchOddData as $data ){

                        $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$data['sec_id'],'match_odd2');
                        $runners[] = [
                            'id' => $data['id'],
                            'market_id' => $marketId,
                            'event_id' => $eventId,
                            'sec_id' => $data['sec_id'],
                            'runner' => $data['runner'],
                            'profitloss' => $profitLoss,
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

                    $items = [
                        'marketName' => 'Book Market 0% Commission',
                        'market_id' => $marketId,
                        'event_id' => $eventId,
                        'suspended' => $market['suspended'],
                        'ballRunning' => $market['ball_running'],
                        'is_favorite' => $this->isFavorite($eventId,$marketId,'match_odd'),
                        'runners' => $runners,

                    ];
                }

            }

            $response =  [ "status" => 1 , "data" => [ "items" => $items ] ];

        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }

        return $response;
    }

    //Event: My Market Fancy
    public function actionFancy()
    {
        if( null !== \Yii::$app->user->id && null !== \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );
            $uid = \Yii::$app->user->id;
            $items = [];
            $betListFancy = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'fancy2','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->groupBy(['market_id'])->all();
            //echo '<pre>';print_r($betListFancy);die;
            if( $betListFancy != null ){
                $marketArr = [];

                foreach ( $betListFancy as $market ){
                    $marketArr[] = $market['market_id'];
                }

                $marketList = (new \yii\db\Query())
                    ->select('*')
                    ->from('market_type')
                    ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                    ->andWhere(['IN','market_id',$marketArr])
                    ->all();
                //echo '<pre>';print_r($marketList);die;
                $items = [];

                if( $marketList != null ){

                    $url = $this->apiUrlFancy.'?eventId='.$eventId;
                    if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
                        $url = $this->apiUrlFancyLive.'/'.$eventId;
                    }

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    //echo '<pre>';print_r($responseData);die;
                    $dataArr = [];

                    if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){

                        if( isset( $responseData->result ) ){

                            foreach ( $responseData->result as $result ){

                                if( $result->mtype == 'INNINGS_RUNS' ){

                                    if( isset( $result->runners ) ){
                                        $noVal = $noRate = $yesVal = $yesRate = 0;
                                        foreach ( $result->runners as $runners ){

                                            if( isset( $runners->back ) ){
                                                $back = $runners->back[0];
                                                $yesRate = $back->price;
                                                $yesVal = $back->line;
                                            }
                                            if( isset( $runners->lay ) ){
                                                $lay = $runners->lay[0];
                                                $noRate = $lay->price;
                                                $noVal = $lay->line;
                                            }

                                        }

                                    }

                                    $marketId = $result->id;
                                    $status = $result->status;
                                    $dataVal[0] = [
                                        'no' => $noVal,
                                        'no_rate' => $noRate,
                                        'yes' => $yesVal,
                                        'yes_rate' => $yesRate,
                                    ];

                                    $dataArr[] = [
                                        'market_id' => $marketId,
                                        'status' => $status,
                                        'data' => $dataVal
                                    ];

                                }

                            }

                        }
                    }else{

                        if( isset( $responseData->status ) && ( $responseData->status == '200' ) ){
                            //echo '<pre>';print_r($responseData);die;
                            if( isset( $responseData->data ) ){

                                foreach ( $responseData->data as $key=>$data ){

                                    if( $key != 'active' ){

                                        $marketId = $data->market_id;
                                        $status = $data->DisplayMsg == 'Suspended' ? 'Y' : ( $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N' );
                                        $noVal = $noRate = $yesVal = $yesRate = 0;
                                        if( isset( $data->NoValume ) ){ $noRate = $data->NoValume; }
                                        if( isset( $data->YesValume ) ){ $yesRate = $data->YesValume; }
                                        if( isset( $data->SessInptYes ) ){ $yesVal = $data->SessInptYes; }
                                        if( isset( $data->SessInptNo ) ){ $noVal = $data->SessInptNo; }

                                        $dataVal[0] = [
                                            'no' => $noVal,
                                            'no_rate' => $noRate,
                                            'yes' => $yesVal,
                                            'yes_rate' => $yesRate,
                                        ];

                                        $dataArr[] = [
                                            'market_id' => $marketId,
                                            'status' => $status,
                                            'data' => $dataVal
                                        ];

                                    }

                                }

                            }

                        }


                    }

                    $items = [];$status = 'N';
                    foreach ( $marketList as $market ){

                        if( $dataArr != null ){

                            foreach ( $dataArr as $d ){

                                if( $d['market_id'] == $market['market_id'] ){
                                    $dataVal = $d['data'];
                                    $status = $d['status'];
                                }
                            }

                        }else{
                            $dataVal[0] = [
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
                            'suspended' => $market['suspended'],
                            'ballRunning' => $market['ball_running'],
                            'status' => $status,
                            'sportId' => 4,
                            'slug' => 'cricket',
                            'is_book' => $this->isBookOn($market['market_id'],'fancy2'),
                            'is_favorite' => $this->isFavorite($market['event_id'],$market['market_id'],'fancy2'),
                            'data' => $dataVal,
                        ];
                    }

                }

            }

            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => count($items) ] ];

        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }

        return $response;
    }

    //Event: My Market Manual Session Fancy
    public function actionManualSessionFancy()
    {
        if( null !== \Yii::$app->user->id && null !== \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );
            $uid = \Yii::$app->user->id;
            $items = [];
            $betListFancy = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'fancy','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->groupBy(['market_id'])->all();
            //echo '<pre>';print_r($betListFancy);die;
            if( $betListFancy != null ){
                $marketArr = [];

                foreach ( $betListFancy as $market ){
                    $marketArr[] = $market['market_id'];
                }

                $marketList = (new \yii\db\Query())
                    ->select('*')
                    ->from('manual_session')
                    ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                    ->andWhere(['IN','market_id',$marketArr])
                    ->all();

                //echo '<pre>';print_r($models);die;
                $items = [];
                if($marketList != null){
                    $dataVal = [];
                    foreach($marketList as $data){

                        $dataVal[0] = [
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
                            'data' => $dataVal,
                            'sportId' => 4,
                            'slug' => 'cricket',
                            'is_book' => $this->isBookOn($data['market_id'],'fancy'),
                            'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'fancy'),

                        ];
                    }
                }

            }

            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => count($items) ] ];

        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }

        return $response;
    }

    //Event: MyMarket Manual Session Lottery
    public function actionManualSessionLottery()
    {
        if( null !== \Yii::$app->user->id && null !== \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );
            $uid = \Yii::$app->user->id;
            $items = [];
            $betListLottery = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id'=>$uid,'event_id' => $eventId,'session_type' => 'lottery','match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
                ->groupBy(['market_id'])->all();
            //echo '<pre>';print_r($betListLottery);die;
            if( $betListLottery != null ){

                $marketArr = [];
                foreach ( $betListLottery as $market ){
                    $marketArr[] = $market['market_id'];
                }

                $marketList = (new \yii\db\Query())
                    ->select(['id','market_id','event_id','title','rate'])
                    ->from('manual_session_lottery')
                    ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                    ->andWhere(['IN','market_id',$marketArr])
                    ->all();

                //echo '<pre>';print_r($marketList);die;
                $items = $numbers = [];
                if($marketList != null){
                    foreach($marketList as $data){

                        for($n=0;$n<10;$n++){

                            $profitLoss = $this->getLotteryProfitLossOnBet($data['event_id'], $data['market_id'] , $n );

                            $numbers[] = [
                                'id' => $n,
                                'number' => $n,
                                'rate' => $data['rate'],
                                'profitloss' => $profitLoss

                            ];
                        }

                        $items[] = [
                            'id' => $data['id'],
                            'event_id' => $data['event_id'],
                            'market_id' => $data['market_id'],
                            'title' => $data['title'],
                            'numbers' => $numbers,
                            'is_book' => $this->isBookOn($data['market_id'],'lottery'),
                            'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'lottery'),
                        ];

                    }
                }

            }

            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => COUNT($items) ] ];

        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }

        return $response;
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

            $total = round($totalWin-$totalLoss);

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

        $total = round($totalWin+$totalLoss);

        return $total;
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
