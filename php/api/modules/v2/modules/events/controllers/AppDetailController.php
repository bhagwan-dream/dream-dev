<?php

namespace api\modules\v2\modules\events\controllers;

use Yii;
use yii\helpers\ArrayHelper;

class AppDetailController extends \common\controllers\aController  // \yii\rest\Controller
{
    private $marketIdsArr = [];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors ['access'] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => ['client'],
                ],
            ],
            "denyCallback" => [\common\controllers\cController::className(), 'accessControlCallBack']
        ];

        return $behaviors;
    }

    //Event: Index
    public function actionIndex()
    {
        $response = ["status" => 0, "error" => ["code" => 400, "message" => "Bad request!"]];

        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get('id');

            if (in_array($eventId, $this->checkUnBlockList($uid))) {
                $response = ["status" => 1, "data" => null, "msg" => "This event is closed !!"];
                return $response;
                exit;
            }

            $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

            $firstLoad = false;
            if (json_last_error() == JSON_ERROR_NONE) {
                $r_data = ArrayHelper::toArray($request_data);
                if( isset( $r_data['first'] ) && $r_data['first'] != 0 ){
                    $firstLoad = true;
                }
            }

            $eventData = (new \yii\db\Query())
                ->select(['sport_id', 'market_id', 'event_id', 'event_name', 'event_time', 'play_type', 'suspended', 'ball_running', 'min_stack', 'max_stack', 'max_profit', 'max_profit_limit', 'max_profit_all_limit', 'bet_delay'])
                ->from('events_play_list')
                ->where(['event_id' => $eventId, 'game_over' => 'NO', 'status' => 1])
                ->createCommand(Yii::$app->db3)->queryOne();

            $eventArr = [];
            if ($eventData != null) {
                $rnr1 = $rnr2 = 'Undefined';
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];

                $eventRunnerData = (new \yii\db\Query())
                    ->select(['selection_id','runner'])
                    ->from('events_runners')
                    ->where(['event_id' => $eventId, 'market_id' => $marketId])
                    ->orderBy([ 'id' => SORT_DESC ])
                    ->createCommand(Yii::$app->db3)->queryAll();

                if ($eventRunnerData != null) {
                    $rnr1 = $eventRunnerData[0]['runner'];
                    $rnr2 = $eventRunnerData[1]['runner'];
                }

                if (in_array($sportId, $this->checkUnBlockSportList($uid))) {
                    $response = ["status" => 1, "data" => null, "msg" => "This Sport Block by Parent!!"];
                    return $response;
                    exit;
                }

                $scoreData = $this->getScoreData($sportId, $eventId);

                if ($sportId == '1') {
                    $matchoddData = $this->getDataMatchOdd($uid, $eventData,$eventRunnerData,$firstLoad);
                    //$matchoddData = $this->getDataMatchOdd($uid, $marketId, $eventId);
                    $eventArr = [
                        'title' => $title,
                        'rnr1' => $rnr1,
                        'rnr2' => $rnr2,
                        'sport' => 'Football',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'score' => $scoreData
                    ];

                } else if ($sportId == '2') {
                    //$matchoddData = $this->getDataMatchOdd($uid, $marketId, $eventId);
                    $matchoddData = $this->getDataMatchOdd($uid, $eventData, $eventRunnerData,$firstLoad);
                    $eventArr = [
                        'title' => $title,
                        'rnr1' => $rnr1,
                        'rnr2' => $rnr2,
                        'sport' => 'Tennis',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'score' => $scoreData
                    ];
                } else if ($sportId == '4') {
                    //$matchoddData = $this->getDataMatchOdd($uid, $marketId, $eventId);
                    $matchoddData = $this->getDataMatchOdd($uid, $eventData, $eventRunnerData,$firstLoad);
                    $matchodd2Data = $this->getDataManualMatchOdd($uid, $eventId,$firstLoad);
                    $fancy2Data = $this->getDataFancy($uid, $eventId , $firstLoad);
                    $fancyData = $this->getDataManualSessionFancy($uid, $eventId , $firstLoad);
                    $lotteryData = $this->getDataLottery($uid, $eventId);
                    $eventArr = [
                        'title' => $title,
                        'rnr1' => $rnr1,
                        'rnr2' => $rnr2,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'match_odd2' => $matchodd2Data,
                        'fancy2' => $fancy2Data,
                        'fancy' => $fancyData,
                        'lottery' => $lotteryData,
                        'score' => $scoreData
                    ];
                }

                $this->marketIdsArr[] = [
                    'type' => 'match_odd',
                    'market_id' => $marketId,
                    'event_id' => $eventId
                ];

                $response = ["status" => 1, "data" => ["items" => $eventArr, 'marketIdsArr' => $this->marketIdsArr]];

            } else {
                $response = ["status" => 1, "data" => null, "msg" => "This event is closed !!"];
            }


        }

        return $response;

    }


    //Event: Jackpot Data
    public function actionJackpot()
    {
        $response = ["status" => 0, "error" => ["code" => 400, "message" => "Bad request!"]];

        if ( null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get('id');

            if (in_array($eventId, $this->checkUnBlockList($uid))) {
                $response = ["status" => 1, "data" => null, "msg" => "This event is closed !!"];
                return $response;
                exit;
            }

            $eventArr = [];

            $jackpotData = (new \yii\db\Query())
                ->select('*')
                ->from('cricket_jackpot')
                ->where(['event_id' => $eventId,'game_over' => 'NO','status' => 1])
                ->one();

            if( $jackpotData == null ){
                $response = ["status" => 1, "data" => ["items" => [] , "betList" => [] , "count" => 0 ], "msg" => "This event is closed !!"];
                return $response;
                exit;
            }

            $eventData = (new \yii\db\Query())
                ->select(['sport_id', 'market_id', 'event_id', 'event_name', 'event_time', 'play_type', 'suspended', 'ball_running','event_time'])
                ->from('events_play_list')
                ->where(['event_id' => $eventId, 'game_over' => 'NO', 'status' => 1])
                ->createCommand(Yii::$app->db3)->queryOne();


            if ($eventData != null) {
                $rnr1 = $rnr2 = 'Undefined';
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];
                $event_time = $eventData['event_time'];

                $eventRunnerData = (new \yii\db\Query())
                    ->select(['runner'])
                    ->from('events_runners')
                    ->where(['event_id' => $eventId, 'market_id' => $marketId])
                    ->createCommand(Yii::$app->db3)->queryAll();

                if ($eventRunnerData != null) {

                    $rnr1 = $eventRunnerData[0]['runner'];
                    $rnr2 = $eventRunnerData[1]['runner'];
                }

                if (in_array($sportId, $this->checkUnBlockSportList($uid))) {
                    $response = ["status" => 1, "data" => null, "msg" => "This Sport Block by Parent!!"];
                    return $response;
                    exit;
                }

                $jackpotData = $this->getDataJackpot($uid, $eventId);
                $jackpotSetting = $this->getDataJackpotSetting($eventId);

                $suspend_time = $jackpotSetting['suspend_time'];
                $differenceTime = 0;
                if(!empty($suspend_time)){
                     $seconds = $event_time / 1000;
                     $time = strtotime( date("Y-m-d H:i:s", $seconds) );
                     $time = $time - ($suspend_time * 60);
                     $date = date("Y-m-d H:i:s", $time);
                     $differenceTime = strtotime($date);
                     $differenceTime = $differenceTime * 1000;
                }

                $eventArr = [
                    'title' => $title,
                    'rnr1' => $rnr1,
                    'rnr2' => $rnr2,
                    'sport' => 'Cricket',
                    'event_id' => $eventId,
                    'sport_id' => $sportId,
                    'jackpot' => $jackpotData,
                    'suspend_time' => $suspend_time,
                    'event_time' => $event_time,
                    'difference_time' => $differenceTime,
                    'setting' => $jackpotSetting
                ];

//                $this->marketIdsArr[] = [
//                    'type' => 'match_odd',
//                    'market_id' => $marketId,
//                    'event_id' => $eventId
//                ];

                $where = [ 'status' => [0,1] , 'session_type' => 'jackpot','bet_status' => 'Pending' , 'user_id' => $uid ,'event_id' => $eventId ];

                $betList = (new \yii\db\Query())
                    ->from('place_bet')
                    ->select([ 'id','runner' , 'bet_type' , 'price' , 'size' , 'rate' , 'session_type' , 'match_unmatch' , 'description' , 'status' ])
                    ->where( $where )
                    ->orderBy( [ 'created_at' => SORT_DESC ] )
                    ->createCommand(\Yii::$app->db3)->queryAll();

                if( $betList != null ){
                    $response = ["status" => 1, "data" => ["items" => $eventArr , "betList" => $betList , "count" => count($betList) ]];
                }else{
                    $response = ["status" => 1, "data" => ["items" => $eventArr , "betList" => [] , "count" => 0 ]];
                }

            } else {
                $response = ["status" => 1, "data" => null, "msg" => "This event is closed !!"];
            }


        }

        return $response;

    }


    //get ScoreData
    public function getScoreData($sportId, $eventId)
    {

        $socre = [];
        if ($sportId == 4) {

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

    //check database function
    public function checkUnBlockList($uId)
    {
        $user = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where(['id' => $uId])->createCommand(Yii::$app->db3)->queryOne();

        $pId = 1;
        if ($user != null) {
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
            ->select(['event_id'])->from('event_market_status')
            ->where(['user_id' => $pId, 'market_type' => 'all'])->createCommand(Yii::$app->db3)->queryAll();

        if ($listArr != null) {

            foreach ($listArr as $list) {
                $newList[] = $list['event_id'];
            }

            return $newList;
        } else {
            return [];
        }

    }

    //check database function
    public function checkUnBlockSportList($uId)
    {
        //$uId = \Yii::$app->user->id;

        $newList = [];

        $listArr = (new \yii\db\Query())
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id' => $uId])
            ->andWhere(['!=', 'byuser', $uId])->createCommand(Yii::$app->db3)->queryAll();

        if ($listArr != null) {
            foreach ($listArr as $list) {
                $newList[] = $list['sport_id'];
            }
        }
        return $newList;

    }

    //Event: getDataMatchOdd //getDataMatchOdd($uid, $marketId, $eventId)
    public function getDataMatchOdd($uid, $event, $runnerData,$firstLoad)
    {
        $marketListArr = null;

//        $event = (new \yii\db\Query())
//            ->select(['sport_id', 'market_id', 'event_id', 'event_name', 'event_time', 'play_type', 'suspended', 'ball_running', 'min_stack', 'max_stack', 'max_profit', 'max_profit_limit', 'max_profit_all_limit', 'bet_delay'])
//            ->from('events_play_list')
//            ->where(['game_over' => 'NO', 'status' => 1])
//            ->andWhere(['market_id' => $marketId])
//            ->createCommand(Yii::$app->db3)->queryOne();

        //echo '<pre>';print_r($event);die;
        if ($event != null) {

            $slug = ['1' => 'football', '2' => 'tennis', '4' => 'cricket'];

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
            $betDelay = 0;//$this->getBetDelay($sportId, $eventId, $marketId, 'match_odd');
            $isFavorite = ( $firstLoad == false ? $this->isFavorite($uid,$eventId, $marketId, 'match_odd') : 0 );

//            $runnerData = (new \yii\db\Query())
//                ->select(['selection_id', 'runner'])
//                ->from('events_runners')
//                ->where(['event_id' => $eventId])
//                ->createCommand(Yii::$app->db3)->queryAll();

            if ($runnerData != null) {

                if( $firstLoad == false ) {
                    $cache = \Yii::$app->cache;
                    $oddsData = $cache->get($marketId);
                    $oddsData = json_decode($oddsData);
                    if ($oddsData != null && isset($oddsData->odds)) {
                        if ($suspended != 'Y') {
                            if (isset($oddsData->status) && $oddsData->status != 'OPEN') {
                                $suspended = 'Y';
                            }
                        }

                        $i = 0;
                        foreach ($oddsData->odds as $odds) {

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
                }

                $i = 0;
                foreach ($runnerData as $runner) {
                    if( $firstLoad == false ) {
                        if (!isset($back[$i])) {
                            $back[$i] = [
                                'price' => '-',
                                'size' => ''
                            ];
                        }
                        if (!isset($lay[$i])) {
                            $lay[$i] = [
                                'price' => '-',
                                'size' => ''
                            ];
                        }
                    }else{
                        $back[$i] = $lay[$i] = [
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
                        'is_favorite' => $isFavorite,
                        'runnerName' => $runnerName,
                        'profit_loss' => ( $firstLoad == false ? $this->getProfitLossMatchOdds($uid,$marketId, $eventId, $selectionId, 'match_odd') : 0 ),
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
                'marketId' => $marketId,
                'eventId' => $eventId,
                'suspended' => $suspended,
                'ballRunning' => $ballRunning,
                'is_favorite' => $isFavorite,
                'time' => $time,
                'marketName' => 'Match Odds',
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
    public function getDataManualMatchOdd($uid, $eventId , $firstLoad)
    {
        $items = null;

        $market = (new \yii\db\Query())
            ->select(['id', 'event_id', 'market_id', 'suspended', 'ball_running', 'min_stack', 'max_stack', 'max_profit', 'max_profit_limit', 'bet_delay','info'])
            ->from('manual_session_match_odd')
            ->where(['status' => 1, 'game_over' => 'NO', 'event_id' => $eventId])
            ->createCommand(Yii::$app->db3)->queryOne();
        //echo '<pre>';print_r($market);die;
        $runners = []; $info = '';
        if ($market != null) {

            $marketId = $market['market_id'];
            $minStack = $market['min_stack'];
            $maxStack = $market['max_stack'];
            $maxProfit = $market['max_profit'];
            $maxProfitLimit = $market['max_profit_limit'];
            $suspended = $market['suspended'];
            $ballRunning = $market['ball_running'];
            $info = $market['info'];
            //$betDelay = $market['bet_delay'];
            $betDelay = 0;//$this->getBetDelay(4, $eventId, $marketId, 'match_odd2');

            $matchOddData = (new \yii\db\Query())
                ->select(['id', 'sec_id', 'runner', 'lay', 'back'])
                ->from('manual_session_match_odd_data')
                ->andWhere(['market_id' => $marketId])
                ->createCommand(Yii::$app->db3)->queryAll();

            if ($matchOddData != null) {
                foreach ($matchOddData as $data) {

                    $profitLoss = ( $firstLoad == false ? $this->getProfitLossMatchOdds($uid,$marketId, $eventId, $data['sec_id'], 'match_odd2') : 0 );
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
                'suspended' => $suspended,
                'ballRunning' => $ballRunning,
                'is_favorite' => ( $firstLoad == false ? $this->isFavorite($uid,$eventId, $marketId, 'match_odd2') : 0 ),
                'minStack' => $minStack,
                'maxStack' => $maxStack,
                'maxProfit' => $maxProfit,
                'maxProfitLimit' => $maxProfitLimit,
                'info' => $info,
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
    public function getDataFancy($uid, $eventId , $firstLoad)
    {
        $items = [];

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('market_type')
            ->where(['event_id' => $eventId, 'game_over' => 'NO', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryAll();
        //echo '<pre>';print_r($marketList);die;
        $items = [];


        if ($marketList != null) {
            $suspended = $ballRunning = 'N';

            $cache = \Yii::$app->cache;

            foreach ($marketList as $market) {

                $suspended = $ballRunning = 'N';

                $marketId = $market['market_id'];
                if ($market['suspended'] == 'Y') {
                    $suspended = 'Y';
                }
                if ($market['ball_running'] == 'Y') {
                    $ballRunning = 'Y';
                }

                $minStack = $market['min_stack'];
                $maxStack = $market['max_stack'];
                $maxProfit = $market['max_profit'];
                $maxProfitLimit = $market['max_profit_limit'];
                //$betDelay = $market['bet_delay'];
                $betDelay = 0;//$this->getBetDelay(4, $eventId, $marketId, 'fancy2');
                $key = $marketId;

                if( $firstLoad == false ){
                    $data = $cache->get($key);
                    $data = json_decode($data);
                    //echo '<pre>';print_r($data);die;
                    if ($data != null && isset($data->data)) {

                        if ($data->ballRunning == 'Y') {
                            $ballRunning = 'Y';
                        }
                        if ($data->suspended == 'Y') {
                            $suspended = 'Y';
                        }

                        $dataVal = $data->data;

                    } else {
                        $suspended = 'Y';
                        $dataVal = [
                            'no' => 0,
                            'no_rate' => 0,
                            'yes' => 0,
                            'yes_rate' => 0,
                        ];
                    }
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
                    'is_book' => ( $firstLoad == false ? $this->isBookOn($uid,$market['market_id'], 'fancy2') : 0 ),
                    'is_favorite' => ( $firstLoad == false ? $this->isFavorite( $uid,$market['event_id'], $market['market_id'] ,'fancy2') : 0 ),
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
    public function getDataManualSessionFancy($uid, $eventId , $firstLoad)
    {
        $items = [];

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('manual_session')
            ->where(['event_id' => $eventId, 'game_over' => 'NO', 'status' => 1])
            ->all();
        //echo '<pre>';print_r($models);die;
        $items = [];
        if ($marketList != null) {
            $suspended = $ballRunning = 'N';

            $dataVal = []; $info = '';
            foreach ($marketList as $data) {

                $suspended = $ballRunning = 'N';

                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $info = $data['info'];

                if ($suspended != 'Y') {
                    $suspended = $data['suspended'];
                }
                if ($ballRunning != 'Y') {
                    $ballRunning = $data['ball_running'];
                }

                //$betDelay = $data['bet_delay'];
                $betDelay = 0;//$this->getBetDelay(4, $eventId, $data['market_id'], 'fancy');

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
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    'sportId' => 4,
                    'slug' => 'cricket',
                    'is_book' => ( $firstLoad == false ? $this->isBookOn($uid,$data['market_id'], 'fancy') : 0 ),
                    'is_favorite' => ( $firstLoad == false ? $this->isFavorite($uid,$data['event_id'], $data['market_id'], 'fancy') : 0 ),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'info' => $info,
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
    public function getDataLottery($uid, $eventId)
    {
        $items = [];

        $marketList = (new \yii\db\Query())
            ->select(['id', 'market_id', 'event_id', 'title', 'rate', 'min_stack', 'max_stack', 'max_profit', 'max_profit_limit', 'bet_delay'])
            ->from('manual_session_lottery')
            ->where(['event_id' => $eventId, 'game_over' => 'NO', 'status' => 1])
            ->all();
        //echo '<pre>';print_r($marketList);die;
        $items = [];
        if ($marketList != null) {
            foreach ($marketList as $data) {

                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];

                $numbers = [];
                for ($n = 0; $n < 10; $n++) {

                    $profitLoss = $this->getLotteryProfitLossOnBet($uid,$data['event_id'], $data['market_id'], $n);

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
                    'is_book' => $this->isBookOn($uid,$data['market_id'], 'lottery'),
                    'is_favorite' => $this->isFavorite($uid,$data['event_id'], $data['market_id'], 'lottery'),
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



      //Event: getDataJackpot
    public function getDataJackpotSetting($eventId)
    {
        $items = [];

        $data = (new \yii\db\Query())
            ->select('*')
            ->from('cricket_jackpot_setting')
            ->where(['event_id' => $eventId,'status' => 1])->one();
        //echo '<pre>';print_r($marketList);die;
        $items = [];
        if ($data != null) {


                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];
                $items = [
                    'id' => $data['id'],
                    'event_id' => $data['event_id'],
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'suspend_time' =>  $data['suspend_timer'],
                    'rules' =>  $data['rules'],
                    'highlight_msg' =>  $data['highlight_msg'],
                ];
        }

        return $items;
    }


      //Event: getDataJackpot
    public function getDataJackpot($uid, $eventId)
    {
        $items = [];

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('cricket_jackpot')
            ->where(['event_id' => $eventId,'status' => 1])
            ->all();
        //echo '<pre>';print_r($marketList);die;
        $items = [];
        if ($marketList != null) {
            foreach ($marketList as $data) {
                $rate = $data['rate'];

                $profitLoss = $this->getJackpotProfitLossOnBet($uid,$data['event_id'], $data['market_id']);

                $items[] = [
                    'id' => $data['id'],
                    'event_id' => $data['event_id'],
                    'market_id' => $data['market_id'],
                    'team_a' => $data['team_a'],
                    'team_b' => $data['team_b'],
                    'team_a_player' => $data['team_a_player'],
                    'team_b_player' => $data['team_b_player'],
                    'sportId' => 4,
                    'slug' => 'jackpot',
                    'is_book' => 0,
                    'is_favorite' => 0,
                    'rate' => $rate,
                    'profitloss' => $profitLoss,
                    'suspended' => $data['suspended'],

                ];

            }
        }

        return $items;
    }

    //Event: isBookOn
    public function isBookOn($uid,$marketId, $sessionType)
    {
        //$uid = \Yii::$app->user->id;

        $findBet = (new \yii\db\Query())
            ->select(['id'])->from('place_bet')
            ->where(['bet_status' => 'Pending', 'user_id' => $uid, 'market_id' => $marketId, 'session_type' => $sessionType, 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryAll();

        if ($findBet != null) {
            return '1';
        }
        return '0';

    }

    //Event: isFavorite
    public function isFavorite($uid,$eventId, $marketId, $sessionType)
    {
        //$uid = \Yii::$app->user->id;

        $find = (new \yii\db\Query())
            ->select(['id'])->from('favorite_market')
            ->where(['user_id' => $uid, 'event_id' => $eventId, 'market_id' => $marketId, 'market_type' => $sessionType])
            ->createCommand(Yii::$app->db3)->queryAll();

        if ($find != null) {
            return '1';
        }
        return '0';

    }

    //Event: get Profit Loss Match Odds
    public function getProfitLossMatchOdds($userId,$marketId, $eventId, $selId, $sessionType)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;
        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'back', 'session_type' => $sessionType];
            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])->from('place_bet')
                ->where($where)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($backWin[0]['val']);die;

            if ($backWin == null || !isset($backWin['val']) || $backWin['val'] == '') {
                $backWin = 0;
            } else {
                $backWin = $backWin['val'];
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];
            $andWhere = ['!=', 'sec_id', $selId];

            //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])->from('place_bet')
                ->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layWin[0]['val']);die;

            if ($layWin == null || !isset($layWin['val']) || $layWin['val'] == '') {
                $layWin = 0;
            } else {
                $layWin = $layWin['val'];
            }

            //$where = ['match_unmatch' => 1, 'sec_id' => $selId, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = ['market_id' => $marketId, 'match_unmatch' => 1, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'lay'];

            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])->from('place_bet')
                ->where($where)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layLoss[0]['val']);die;

            if ($layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '') {
                $layLoss = 0;
            } else {
                $layLoss = $layLoss['val'];
            }

            $where = ['market_id' => $marketId, 'match_unmatch' => 1, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'back'];
            $andWhere = ['!=', 'sec_id', $selId];

            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])->from('place_bet')
                ->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($backLoss[0]['val']);die;

            if ($backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '') {
                $backLoss = 0;
            } else {
                $backLoss = $backLoss['val'];
            }

            $totalLoss = $backLoss + $layLoss;

            $total = round($totalWin - $totalLoss);

        }

        return $total;

    }

    //Event: get Lottery Profit Loss On Bet
    public function getLotteryProfitLossOnBet($userId,$eventId, $marketId, $selectionId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'lottery', 'user_id' => $userId, 'event_id' => $eventId, 'market_id' => $marketId];
        // IF RUNNER WIN
        //$betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where($where)
        //    ->andWhere(['sec_id' => $selectionId])->asArray()->all();

        $betWinList = (new \yii\db\Query())
            ->select(['SUM(win) as totalWin'])->from('place_bet')
            ->where($where)->andWhere(['sec_id' => $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        // IF RUNNER LOSS
        //$betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where($where)
        //    ->andWhere(['!=', 'sec_id', $selectionId])->asArray()->all();

        $betLossList = (new \yii\db\Query())
            ->select(['SUM(loss) as totalLoss'])->from('place_bet')
            ->where($where)->andWhere(['!=', 'sec_id', $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        if ($betWinList == null) {
            $totalWin = 0;
        } else {
            $totalWin = $betWinList['totalWin'];
        }

        if ($betLossList == null) {
            $totalLoss = 0;
        } else {
            $totalLoss = (-1) * $betLossList['totalLoss'];
        }

        $total = round($totalWin + $totalLoss);

        return $total;
    }

    //Event: get Jackpot Profit Loss On Bet
    public function getJackpotProfitLossOnBet($userId,$eventId, $marketId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'jackpot', 'user_id' => $userId, 'event_id' => $eventId , 'status' => 1 ];
        // IF RUNNER WIN
        $betWinList = (new \yii\db\Query())
            ->select(['SUM(win) as totalWin'])->from('place_bet')
            ->where($where)->andWhere(['market_id' => $marketId])
            ->createCommand(Yii::$app->db3)->queryOne();

        // IF RUNNER LOSS
        $betLossList = (new \yii\db\Query())
            ->select(['SUM(loss) as totalLoss'])->from('place_bet')
            ->where($where)->andWhere(['!=', 'market_id', $marketId])
            ->createCommand(Yii::$app->db3)->queryOne();

        if ($betWinList == null) {
            $totalWin = 0;
        } else {
            $totalWin = $betWinList['totalWin'];
        }

        if ($betLossList == null) {
            $totalLoss = 0;
        } else {
            $totalLoss = (-1) * $betLossList['totalLoss'];
        }

        $total = round($totalWin + $totalLoss);

        return $total;
    }

    // Cricket: ProfitLossFancy API
    public function actionProfitLossFancyBook()
    {
        $response = ["status" => 0, "error" => ["code" => 400, "message" => "Bad request!"]];
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        if (json_last_error() == JSON_ERROR_NONE) {
            $r_data = ArrayHelper::toArray($request_data);
            //echo '<pre>';print_r($r_data);die;

            $userId = \Yii::$app->user->id;

            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];
            $betList = null;

            $where = ['event_id' => $eventId, 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

            //$betListData = PlaceBet::find()
            //    ->select(['runner', 'bet_type', 'price', 'win', 'size','rate', 'loss'])
            //    ->where($where)->createCommand(Yii::$app->db3)->queryAll();

            $betListData = (new \yii\db\Query())
                ->select(['runner', 'bet_type', 'price', 'win', 'size','rate', 'loss'])->from('place_bet')
                ->where($where)->orderBy(['id' => SORT_DESC])->createCommand(Yii::$app->db3)->queryAll();


            if ($betListData != null) {
                $betList = $betListData;
            }

            $profitLossData = $this->getProfitLossFancy($userId,$eventId, $marketId, $sessionType);
            $response = ['status' => 1, 'data' => $profitLossData, 'betList' => $betList];

        }

        return $response;
    }

    // getProfitLossFancy (by bhagwan)
    public function getProfitLossFancy($userId,$eventId, $marketId, $sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = ['bet_status' => 'Pending', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

        $betList = (new \yii\db\Query())
            ->select(['bet_type', 'price', 'win', 'loss'])->from('place_bet')
            ->where($where)->createCommand(Yii::$app->db3)->queryAll();

        $dataReturn = null;
        $result = []; $newbetresult=[];
        if( $betList != null ){
            $result = [];
            $betresult = [];
            $min = 0;
            $max = 0;


            foreach ($betList as $index => $bet) {
                $betresult[]= array('price'=>$bet['price'],'bet_type'=>$bet['bet_type'],'loss'=>$bet['loss'],'win'=>$bet['win']);
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
            $betarray=[]; $bet_type='';
            $win=0; $loss=0; $count= $min;
            $totalbetcount=count($betresult);
            foreach ($betresult as $key => $value) {
                $val=$value['price']- $count;
                $minval= $value['price'] -$min;
                $maxval= $max-$value['price'];
                $bet_type= $value['bet_type'];
                $loss= $value['loss'];
                $newresult=[];
                $top=0;
                $bottom=0;
                $profitcount=0; $losscount=0;
                for($i= 0;$i<$minval;$i++){
                    if($bet_type=='no'){
                        $top= $top+$value['win'];
                        $profitcount++;
                        $newresult[]= array('count'=>$count,'price'=>$value['price'],'bet_type'=>$value['bet_type'],'totalbetcount'=>$totalbetcount,'expose'=>$value['win']);
                    }else{
                        $bottom=$bottom + $value['loss'];
                        $losscount++;
                        $newresult[]= array('count'=>$count,'price'=>$value['price'],'bet_type'=>$value['bet_type'],'totalbetcount'=>$totalbetcount,'expose'=>-$value['loss']);

                    }
                    $count++;
                }

                for($i= 0;$i<=$maxval;$i++){
                    if($bet_type=='no'){
                        $newresult[]= array('count'=>$count,'price'=>$value['price'],'bet_type'=>$value['bet_type'],'totalbetcount'=>$totalbetcount,'expose'=>-$value['loss']);
                        $bottom=$bottom+ $value['loss'];
                        $losscount++;
                    }else{
                        $top= $top+$value['win'];
                        $profitcount++;
                        $newresult[]= array('count'=>$count,'price'=>$value['price'],'bet_type'=>$value['bet_type'],'totalbetcount'=>$totalbetcount,'expose'=>$value['win']);
                    }

                    $count++;
                }
                $result[]= array('count'=>$value['price'],'bet_type'=>$value['bet_type'],'profit'=>$top,'loss'=>$bottom,'profitcount'=>$profitcount,'losscount'=>$losscount,'newarray'=>$newresult);
            }

            $newbetarray=[]; $newbetresult=[];
            $totalmaxcount= $max-$min;
            if($totalmaxcount>0){
                $minstart=$min;
                for($i=0;$i<$totalmaxcount;$i++){

                    $newbetarray1=[]; $finalexpose=0;
                    for($x=0;$x<$totalbetcount;$x++){
                        // echo "<pre>"; print_r($result[$x]['newarray']);echo "<br>";echo "<br>"; exit;

                        $expose=$result[$x]['newarray'][$i]['expose'];
                        $finalexpose=$finalexpose+$expose;

                        $newbetarray1[]=array('bet_price'=>$result[$x]['count'],'bet_type'=>$result[$x]['bet_type'],'expose'=>$expose);
                    }
                    //$newbetresult[] = $finalexpose;
                    $dataReturn[] = [
                        'price' => $minstart,
                        'profitLoss' => round($finalexpose),
                    ];

                    $minstart++;
                    $newbetarray[]=array('exposearray'=>$newbetarray1,'finalexpose'=>$finalexpose);
                }
            }

        }

        //echo "totalmaxcount---->".$totalmaxcount;
//echo "<pre>"; print_r($dataReturn ); exit;
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
                    if ( $index == (count($dataReturn) - 1)) {
                        $dataReturnNew[] = [ 'price' => $start . ' or more' , 'profitLoss' => $d['profitLoss'] ];
                    }

                }

                $startPl = $d['profitLoss'];
                $i++;

            }

            $dataReturn = $dataReturnNew;

        }

        return $dataReturn;
    }

    // getProfitLossFancy
    public function getProfitLossFancyOLDUNUSED($userId,$eventId, $marketId, $sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = ['bet_status' => 'Pending', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

        $betList = (new \yii\db\Query())
            ->select(['bet_type', 'price', 'win', 'loss'])->from('place_bet')
            ->where($where)->createCommand(Yii::$app->db3)->queryAll();

        $dataReturn = null;

        if ($betList != null) {
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


            for ($i = $min; $i <= $max; $i++) {

                $where = ['bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')->from('place_bet')
                    ->where($where)->andWhere(['>', 'price', $i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')->from('place_bet')
                    ->where($where)->andWhere(['<=', 'price', $i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')->from('place_bet')
                    ->where($where)->andWhere(['>', 'price', $i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')->from('place_bet')
                    ->where($where)->andWhere(['<=', 'price', $i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                if (!isset($betList1['winVal'])) {
                    $winVal1 = 0;
                } else {
                    $winVal1 = $betList1['winVal'];
                }
                if (!isset($betList2['winVal'])) {
                    $winVal2 = 0;
                } else {
                    $winVal2 = $betList2['winVal'];
                }
                if (!isset($betList3['lossVal'])) {
                    $lossVal1 = 0;
                } else {
                    $lossVal1 = $betList3['lossVal'];
                }
                if (!isset($betList4['lossVal'])) {
                    $lossVal2 = 0;
                } else {
                    $lossVal2 = $betList4['lossVal'];
                }

                $profit = ($winVal1 + $winVal2);
                $loss = ($lossVal1 + $lossVal2);

                $dataReturn[] = [
                    'price' => $i,
                    'profitLoss' => round($profit - $loss),
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
    public function getBetDelay($sportId, $eventId, $marketId, $type)
    {
        return 0;
        exit;
        $betDelay = [];
        $betDelayVal = 0;
        $uid = \Yii::$app->user->id;

        if ($type == 'match_odd2') {
            $matchOdd2 = (new \yii\db\Query())
                ->select(['bet_delay'])->from('manual_session_match_odd')
                ->where(['market_id' => $marketId])->createCommand(Yii::$app->db3)->queryOne();

            if ($matchOdd2 != null) {
                //array_push($betDelay, $matchOdd2['bet_delay']);
                return $matchOdd2['bet_delay'];
                exit;
            }
        }

        if ($type == 'fancy') {
            $fancy = (new \yii\db\Query())
                ->select(['bet_delay'])->from('manual_session')
                ->where(['market_id' => $marketId])->createCommand(Yii::$app->db3)->queryOne();

            if ($fancy != null) {
                //array_push($betDelay, $fancy['bet_delay']);
                return $fancy['bet_delay'];
                exit;
            }
        }

        if ($type == 'fancy2') {

            $fancy2 = (new \yii\db\Query())
                ->select(['bet_delay'])->from('market_type')
                ->where(['market_id' => $marketId])->createCommand(Yii::$app->db3)->queryOne();

            if ($fancy2 != null) {
                //array_push($betDelay, $fancy2['bet_delay']);
                return $fancy2['bet_delay'];
                exit;
            }

        }

        $sport = (new \yii\db\Query())
            ->select(['bet_delay'])->from('events')
            ->where(['event_type_id' => $sportId])->createCommand(Yii::$app->db3)->queryOne();

        if ($sport != null) {
            array_push($betDelay, $sport['bet_delay']);
        }

        $user = (new \yii\db\Query())
            ->select(['bet_delay'])->from('user')
            ->where(['id' => $uid])->createCommand(Yii::$app->db3)->queryOne();

        if ($user != null) {
            array_push($betDelay, $user['bet_delay']);
        }

        $event = (new \yii\db\Query())
            ->select(['bet_delay'])->from('events_play_list')
            ->where(['event_id' => $eventId])->createCommand(Yii::$app->db3)->queryOne();

        if ($event != null) {
            array_push($betDelay, $event['bet_delay']);
        }

        if ($betDelay != null) {
            $betDelayVal = max($betDelay);
        }

        return $betDelayVal;
    }

}
