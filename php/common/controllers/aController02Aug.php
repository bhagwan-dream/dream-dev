<?php

namespace common\controllers;

//use api\modules\v1\modules\users\models\PlaceBet;
use common\auth\HttpBearerAuth;
use Yii;
use yii\filters\auth\CompositeAuth;
use common\models\ManualSessionLottery;
use common\models\PlaceBet;
use common\models\ManualSessionMatchOddData;
use common\models\ManualSessionMatchOdd;
use common\models\EventsRunner;
use common\models\EventsPlayList;
use common\models\User;
use common\models\ManualSession;
use common\models\MarketType;
use common\models\Event;

/**
 * Auth controller
 */
class aController02Aug extends cController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => CompositeAuth::className(),
            'authMethods' => [
                ['class' => HttpBearerAuth::className()],
            ]
        ];

        return $behaviors;
    }

    public $PLAY_TYPE = [1 => 'IN_PLAY', 2 => 'UPCOMING', 3 => 'CLOSED'];
    public $PLAY_TYPE_IN_PLAY = 1;
    //public $PLAY_TYPE_IN_PLAY = 1;
    //public $PLAY_TYPE_IN_PLAY = 1;
    public $FANCY_KEY = 'ManualFancy-';
    public $BOOKMAKER_KEY = 'BookMaker-';


    //checkMaxProfitLimit
    public function checkMaxProfitLimit($cBet, $profitLimitData)
    {

        if ($cBet != null) {
            $profitMatchOddVal = $profitMatchOdd2Val = $profitFancyVal = $profitFancy2Val = $profitLotteryVal = 0;
            $maxProfitLimitMatchOdd = $maxProfitLimitMatchOdd2 = $maxProfitLimitFancy = $maxProfitLimitFancy2 = $maxProfitLimitLottery = 1000000;
            $eventId = $cBet->event_id;
            $marketId = $cBet->market_id;

            //MatchOdd
            //$event = EventsPlayList::findOne(['market_id' => $marketId, 'event_id' => $eventId]);

            $event = (new \yii\db\Query())
                ->select(['max_profit_limit'])
                ->from('events_play_list')->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {

                $maxProfitLimitMatchOdd = $event['max_profit_limit'];

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['marketProfitLimit']) && isset($profitLimitData['marketProfitLimit'][$marketId])) {
                        $marketProfitLimit = $profitLimitData['marketProfitLimit'][$marketId];
                        foreach ($marketProfitLimit as $val) {
                            $profitMatchOddVal += $val;
                        }
                    }

                }


                /*$runnersData = EventsRunner::findAll(['market_id' => $marketId, 'event_id' => $eventId]);
                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLimitMatchOdds($marketId, $eventId, $runners->selection_id, 'match_odd', $cBet);
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        } else {
                            $balPlus[] = $profitLoss;
                        }

                    }
                }

                if ($balPlus != null) {
                    $profitMatchOddVal = max($balPlus);
                }*/

            }

            //MatchOdd2
            //$event = ManualSessionMatchOdd::findOne(['market_id' => $marketId, 'event_id' => $eventId]);
            $event = (new \yii\db\Query())
                ->select(['max_profit_limit'])
                ->from('manual_session_match_odd')->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {

                $maxProfitLimitMatchOdd2 = $event['max_profit_limit'];

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['marketProfitLimit']) && isset($profitLimitData['marketProfitLimit'][$marketId])) {
                        $marketProfitLimit = $profitLimitData['marketProfitLimit'][$marketId];
                        foreach ($marketProfitLimit as $val) {
                            $profitMatchOdd2Val += $val;
                        }
                    }

                }

                /*$runnersData = ManualSessionMatchOddData::findAll(['market_id' => $marketId]);
                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLimitMatchOdds($marketId, $eventId, $runners->sec_id, 'match_odd2', $cBet);
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        } else {
                            $balPlus[] = $profitLoss;
                        }
                    }
                }

                if ($balPlus != null) {
                    $profitMatchOdd2Val = max($balPlus);
                }*/

            }

            //Fancy
            //$event = ManualSession::findOne(['market_id' => $marketId, 'event_id' => $eventId]);

            $event = (new \yii\db\Query())
                ->select(['max_profit_limit'])
                ->from('manual_session')->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {

                $maxProfitLimitFancy = $event['max_profit_limit'];

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['marketProfitLimit']) && isset($profitLimitData['marketProfitLimit'][$marketId])) {
                        $marketProfitLimit = $profitLimitData['marketProfitLimit'][$marketId];
                        foreach ($marketProfitLimit as $val) {
                            $profitFancyVal += $val;
                        }
                    }

                }

                /*$profitFancyMarket = $this->getProfitLossFancyOnZeroNew1($marketId, 'fancy', $cBet);

                if ($profitFancyMarket != null) {
                    $profitFancyVal = max($profitFancyMarket);
                }*/
            }

            //Fancy2
            //$event = MarketType::findOne(['market_id' => $marketId, 'event_id' => $eventId]);

            $event = (new \yii\db\Query())
                ->select(['max_profit_limit'])
                ->from('market_type')->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {
                $maxProfitLimitFancy2 = $event['max_profit_limit'];

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['marketProfitLimit']) && isset($profitLimitData['marketProfitLimit'][$marketId])) {
                        $marketProfitLimit = $profitLimitData['marketProfitLimit'][$marketId];
                        foreach ($marketProfitLimit as $val) {
                            $profitFancy2Val += $val;
                        }
                    }

                }

                /*$profitFancy2Market = $this->getProfitLossFancyOnZeroNew1($marketId, 'fancy2', $cBet);

                if ($profitFancy2Market != null) {
                    $profitFancy2Val = max($profitFancy2Market);
                }*/
            }

            //Lottery
            //$event = ManualSessionLottery::findOne(['market_id' => $marketId, 'event_id' => $eventId]);

            $event = (new \yii\db\Query())
                ->select(['max_profit_limit'])
                ->from('manual_session_lottery')->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {

                $maxProfitLimitLottery = $event['max_profit_limit'];

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['marketProfitLimit']) && isset($profitLimitData['marketProfitLimit'][$marketId])) {
                        $marketProfitLimit = $profitLimitData['marketProfitLimit'][$marketId];
                        foreach ($marketProfitLimit as $val) {
                            $profitLotteryVal += $val;
                        }
                    }

                }

                /*for ($n = 0; $n < 10; $n++) {
                    $profitLoss = $this->getLotteryProfitLossNew1($eventId, $marketId, $n, $cBet);
                    if ($profitLoss < 0) {
                        $balExpose[] = $profitLoss;
                    } else {
                        $balPlus[] = $profitLoss;
                    }
                }

                if ($balPlus != null) {
                    $profitLotteryVal = max($balPlus);
                }*/

            }

            if ($cBet->session_type == 'match_odd') {

                if ($profitMatchOddVal > $maxProfitLimitMatchOdd) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            } else if ($cBet->session_type == 'match_odd2') {

                if ($profitMatchOdd2Val > $maxProfitLimitMatchOdd2) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            } else if ($cBet->session_type == 'fancy') {

                if ($profitFancyVal > $maxProfitLimitFancy) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            } else if ($cBet->session_type == 'fancy2') {

                if ($profitFancy2Val > $maxProfitLimitFancy2) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            } else if ($cBet->session_type == 'lottery') {

                if ($profitLotteryVal > $maxProfitLimitLottery) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            }

            $type = 'Market';
            if ($isTrue == true) {
                //Event
                //$event = EventsPlayList::findOne(['event_id' => $eventId]);
                $event = (new \yii\db\Query())
                    ->select(['max_profit_all_limit','sport_id'])
                    ->from('events_play_list')->where(['event_id' => $eventId])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $profitLimitEvent = 0;

                if ($profitLimitData != null) {

                    if (isset($profitLimitData['eventProfitLimit']) && isset($profitLimitData['eventProfitLimit'][$eventId])) {
                        $eventProfitLimit = $profitLimitData['eventProfitLimit'][$eventId];
                        foreach ($eventProfitLimit as $val) {
                            $profitLimitEvent += $val;
                        }
                    }

                }

                //echo $profitLimitEvent;die;

                $maxProfitLimitEvent = $event['max_profit_all_limit'];
                //$profitLimitEvent = $profitLotteryVal + $profitFancy2Val + $profitFancyVal + $profitMatchOdd2Val + $profitMatchOddVal;

                //$sport = Event::findOne(['event_type_id' => $event->sport_id]);
                $sport = (new \yii\db\Query())
                    ->select(['max_profit_all_limit'])
                    ->from('events')->where(['event_type_id' => $event['sport_id']])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $sportPl = 5000000;
                $profitLimitSport = 0;
                if ($sport != null) {
                    $sportPl = $sport['max_profit_all_limit'];
                }

                if ($profitLimitEvent > $maxProfitLimitEvent) {
                    $isTrue = false;
                    $type = 'Event';
                } else {
                    //$isTrue = true;

                    if ($profitLimitData != null) {

                        if (isset($profitLimitData['sportProfitLimit']) && isset($profitLimitData['sportProfitLimit'][$event['sport_id']])) {
                            $sportProfitLimit = $profitLimitData['sportProfitLimit'][$event['sport_id']];
                            foreach ($sportProfitLimit as $val) {
                                $profitLimitSport += $val;
                            }
                        }

                    }

                    if ($profitLimitSport > $sportPl) {
                        $isTrue = false;
                        $type = 'Sport';
                    } else {
                        $isTrue = true;
                    }
                    //$key = 'user:' . $uid;

                    //$cache = \Yii::$app->cache;
                    //$data = $cache->get($key);

                    /*if ($data != null) {
                        $totalPl = 0;
                        $userPlList = json_decode($data);

                        if ($userPlList != null) {
                            foreach ($userPlList as $pl) {
                                if ($pl->event_id != $eventId) {

                                    $check = EventsPlayList::findOne(['event_id' => $pl->event_id, 'status' => 1, 'game_over' => 'NO']);
                                    if ($check != null) {
                                        $totalPl += $pl->profit;
                                    }

                                }
                            }

                        }

                        $totalPl += $profitLimitEvent;

                        if ($sportPl > $totalPl) {

                            if ($userPlList != null) {
                                $f = 0;
                                foreach ($userPlList as $pl) {

                                    if ($pl->event_id == $eventId) {
                                        $pl->profit = $profitLimitEvent;
                                        $f = 1;
                                    }

                                }

                                if (!$f) {

                                    array_push($userPlList,
                                        ['event_id' => $eventId, 'profit' => $profitLimitEvent]);

                                }

                                $cache = \Yii::$app->cache;
                                $cache->set($key, json_encode($userPlList));

                            }

                        } else {
                            $isTrue = false;
                        }

                    } else {

                        $userPlList = [['event_id' => $eventId, 'profit' => $profitLimitEvent]];
                        $cache = \Yii::$app->cache;
                        $cache->set($key, json_encode($userPlList));

                    }*/


                }

            }

        }

        return ['is_true' => $isTrue, 'msg' => 'Your maximum profit limit for ' . $type . ' is over! Bet can not placed!!'];

    }


    //getBalanceVal
    public function checkAvailableBalance($uid, $cBet)
    {
        //$user = User::find()->select(['balance', 'expose_balance', 'profit_loss_balance'])->where(['id' => $uid])->one();

        $user = (new \yii\db\Query())
            ->select(['balance', 'expose_balance', 'profit_loss_balance'])
            ->from('user')->where(['id' => $uid])
            ->createCommand(Yii::$app->db2)->queryOne();

        $exposeBalVal = $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];
        if ($user != null) {
            $mywallet = $user['balance'];
            $user_balance = $user['balance'];
            $user_profit_loss = $user['profit_loss_balance'];
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = $minExpose = 0;
            $eventProfitLimit = $marketProfitLimit = $sportProfitLimit = [];
            $sportId = $cBet->sport_id;
            //Match Odd

            $marketList = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'match_odd', 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

//            $marketList = PlaceBet::find()->select(['market_id'])
//                ->where(['user_id' => $uid, 'session_type' => 'match_odd', 'bet_status' => 'Pending', 'status' => 1])
//                ->andWhere(['!=', 'market_id', $cBet->market_id])
//                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

            //echo '<pre>';print_r($marketList);
            if ($marketList != null) {
                //$maxBal['expose'] = [];

                $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])
                    ->from('user_event_expose')
                    ->where($where)->createCommand(Yii::$app->db2)->queryOne();

                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }

//                foreach ($marketList as $market) {
//                    $marketId = $market['market_id']; $minExpose = 0;
//                    $event = EventsPlayList::findOne(['market_id' => $marketId]);
//                    if ($event != null) {
//                        $eventId = $event->event_id;
//                        $runnersData = EventsRunner::findAll(['market_id' => $marketId, 'event_id' => $eventId]);
//                        if ($runnersData != null) {
//                            $balExpose = $balPlus = [];
//                            foreach ($runnersData as $runners) {
//                                $profitLoss = $this->getProfitLossMatchOddsNewAll1($uid,$marketId, $eventId, $runners->selection_id, 'match_odd');
//                                if ($profitLoss < 0) {
//                                    $balExpose[] = $profitLoss;
//                                } else {
//                                    $balPlus[] = $profitLoss;
//                                }
//
//                            }
//                        }
//
//                        if ($balExpose != null) {
//                            $minExpose = min($balExpose);
//                            $maxBal['expose'][] = $minExpose;
//                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                        }
//
//                        if ($balPlus != null) {
//                            $maxBal['plus'][] = max($balPlus);
//                            $eventProfitLimit[$eventId][] = max($balPlus);
//                            $marketProfitLimit[$marketId][] = max($balPlus);
//                            $sportProfitLimit[$sportId][] = max($balPlus);
//                        }
//
//                    }
//                }

            }

            //$eventNew = EventsPlayList::findOne(['market_id' => $cBet->market_id]);

            $eventNew = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('events_play_list')
                ->where(['market_id' => $cBet->market_id])->createCommand(Yii::$app->db2)->queryOne();

            if ($eventNew != null) {

                $eventId = $eventNew['event_id'];
                $marketId = $cBet->market_id;

                //$runnersData = EventsRunner::findAll(['market_id' => $marketId, 'event_id' => $eventId]);

                $runnersData = (new \yii\db\Query())
                    ->select(['selection_id'])->from('events_runners')
                    ->where(['market_id' => $marketId, 'event_id' => $eventId])
                    ->createCommand(Yii::$app->db2)->queryAll();

                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLossMatchOddsNew1($uid,$marketId, $eventId, $runners['selection_id'], 'match_odd', $cBet);
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        } else {
                            $balPlus[] = $profitLoss;
                        }

                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $maxBal['expose'][] = $minExpose;
                    $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }else{
                    $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                }

                if ($balPlus != null) {
                    $maxBal['plus'][] = max($balPlus);
                    $eventProfitLimit[$eventId][] = max($balPlus);
                    $marketProfitLimit[$marketId][] = max($balPlus);
                    $sportProfitLimit[$sportId][] = max($balPlus);
                }

            }

            //Match Odd 2
            //$marketList2 = PlaceBet::find()->select(['market_id'])
            //    ->where(['user_id' => $uid, 'session_type' => 'match_odd2', 'bet_status' => 'Pending', 'status' => 1])
            //    ->andWhere(['!=', 'market_id', $cBet->market_id])
            //    ->groupBy(['market_id'])->asArray()->all();

            $marketList2 = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'match_odd2', 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

            //echo '<pre>';print_r($marketList);
            if ($marketList2 != null) {
                //$maxBal['expose'] = [];

                $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList2 ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])
                    ->from('user_event_expose')
                    ->where($where)->createCommand(Yii::$app->db2)->queryOne();

                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }

//                foreach ($marketList2 as $market) {
//                    $marketId = $market['market_id']; $minExpose = 0;
//                    $event = ManualSessionMatchOdd::findOne(['market_id' => $marketId]);
//                    if ($event != null) {
//                        $eventId = $event->event_id;
//                        $runnersData = ManualSessionMatchOddData::findAll(['market_id' => $marketId]);
//                        if ($runnersData != null) {
//                            $balExpose = $balPlus = [];
//                            foreach ($runnersData as $runners) {
//                                $profitLoss = $this->getProfitLossMatchOddsNewAll1($uid,$marketId, $eventId, $runners->sec_id, 'match_odd2');
//                                if ($profitLoss < 0) {
//                                    $balExpose[] = $profitLoss;
//                                } else {
//                                    $balPlus[] = $profitLoss;
//                                }
//
//                            }
//                        }
//
//                        if ($balExpose != null) {
//                            $minExpose = min($balExpose);
//                            $maxBal['expose'][] = $minExpose;
//                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                        }
//
//                        if ($balPlus != null) {
//                            $maxBal['plus'][] = max($balPlus);
//                            $eventProfitLimit[$eventId][] = max($balPlus);
//                            $marketProfitLimit[$marketId][] = max($balPlus);
//                            $sportProfitLimit[$sportId][] = max($balPlus);
//                        }
//
//                    }
//                }

            }

            //$eventNew2 = ManualSessionMatchOdd::findOne(['market_id' => $cBet->market_id]);

            $eventNew2 = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('manual_session_match_odd')
                ->where(['market_id' => $cBet->market_id])->createCommand(Yii::$app->db2)->queryOne();

            if ($eventNew2 != null) {
                $eventId = $eventNew2['event_id'];
                $marketId = $cBet->market_id;

                //$runnersData = ManualSessionMatchOddData::findAll(['market_id' => $marketId]);

                $runnersData = (new \yii\db\Query())
                    ->select(['sec_id'])->from('manual_session_match_odd_data')
                    ->where(['market_id' => $marketId])
                    ->createCommand(Yii::$app->db2)->queryAll();

                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLossMatchOddsNew1($uid,$marketId, $eventId, $runners['sec_id'], 'match_odd2', $cBet);
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        } else {
                            $balPlus[] = $profitLoss;
                        }

                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $maxBal['expose'][] = $minExpose;
                    $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }else{
                    $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                }

                if ($balPlus != null) {
                    $maxBal['plus'][] = max($balPlus);
                    $eventProfitLimit[$eventId][] = max($balPlus);
                    $marketProfitLimit[$marketId][] = max($balPlus);
                    $sportProfitLimit[$sportId][] = max($balPlus);
                }

            }

            // Fancy Market
            //$marketList3 = PlaceBet::find()->select(['market_id'])
             //   ->where(['user_id' => $uid, 'session_type' => 'fancy', 'bet_status' => 'Pending', 'status' => 1])
            //    ->andWhere(['!=', 'market_id', $cBet->market_id])
            //    ->groupBy(['market_id'])->asArray()->all();

            $marketList3 = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'fancy', 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

            if ($marketList3 != null) {

                $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList3 ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])
                    ->from('user_event_expose')
                    ->where($where)->createCommand(Yii::$app->db2)->queryOne();

                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }

//                foreach ($marketList3 as $market) {
//
//                    $marketId = $market['market_id']; $minExpose = 0;
//
//                    $event = ManualSession::findOne(['market_id' => $marketId]);
//
//                    if ($event != null) {
//                        $eventId = $event->event_id;
//                        $profitLossData = $this->getProfitLossFancyOnZeroNewAll1($uid,$marketId, 'fancy');
//
//                        if ($profitLossData != null) {
//                            $balExpose = $balPlus = [];
//                            foreach ($profitLossData as $profitLoss) {
//                                if ($profitLoss < 0) {
//                                    $balExpose[] = $profitLoss;
//                                } else {
//                                    $balPlus[] = $profitLoss;
//                                }
//                            }
//
//                            if ($balExpose != null) {
//                                $minExpose = min($balExpose);
//                                $maxBal['expose'][] = $minExpose;
//                                $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                            }
//                            if ($balPlus != null) {
//                                $maxBal['plus'][] = max($balPlus);
//                                $eventProfitLimit[$eventId][] = max($balPlus);
//                                $marketProfitLimit[$marketId][] = max($balPlus);
//                                $sportProfitLimit[$sportId][] = max($balPlus);
//                            }
//
//                        }
//
//                    }
//
//                }

            }

            if ($cBet != null && $cBet->session_type == 'fancy') {

                $marketId = $cBet->market_id;
                //$eventNew2 = ManualSession::findOne(['market_id' => $marketId]);

                $eventNew2 = (new \yii\db\Query())
                    ->select(['event_id'])
                    ->from('manual_session')
                    ->where(['market_id' => $marketId])->createCommand(Yii::$app->db2)->queryOne();

                if ($eventNew2 != null) {
                    $eventId = $eventNew2['event_id'];
                    $profitLossData = $this->getProfitLossFancyOnZeroNew1($uid,$marketId, 'fancy', $cBet);

                    if ($profitLossData != null) {
                        $balExpose = $balPlus = [];
                        foreach ($profitLossData as $profitLoss) {
                            if ($profitLoss < 0) {
                                $balExpose[] = $profitLoss;
                            } else {
                                $balPlus[] = $profitLoss;
                            }
                        }

                        if ($balExpose != null) {
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }
                        if ($balPlus != null) {
                            $maxBal['plus'][] = max($balPlus);
                            $eventProfitLimit[$eventId][] = max($balPlus);
                            $marketProfitLimit[$marketId][] = max($balPlus);
                            $sportProfitLimit[$sportId][] = max($balPlus);
                        }

                    }

                }


            }

            // Fancy 2 Market
            //$marketList4 = PlaceBet::find()->select(['market_id'])
            //    ->where(['user_id' => $uid, 'session_type' => 'fancy2', 'bet_status' => 'Pending', 'status' => 1])
            //    ->andWhere(['!=', 'market_id', $cBet->market_id])
            //    ->groupBy(['market_id'])->asArray()->all();

            $marketList4 = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'fancy2', 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

            //echo '<pre>';print_r($marketList);

            if ($marketList4 != null) {

                $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList4 ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])
                    ->from('user_event_expose')
                    ->where($where)->createCommand(Yii::$app->db2)->queryOne();
                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }

//                foreach ($marketList4 as $market) {
//
//                    $marketId = $market['market_id']; $minExpose = 0;
//                    $event = MarketType::findOne(['market_id' => $marketId]);
//
//                    if ($event != null) {
//                        $eventId = $event->event_id;
//                        $profitLossData = $this->getProfitLossFancyOnZeroNewAll1($uid,$marketId, 'fancy2');
//
//                        if ($profitLossData != null) {
//                            $balExpose = $balPlus = [];
//                            foreach ($profitLossData as $profitLoss) {
//                                if ($profitLoss < 0) {
//                                    $balExpose[] = $profitLoss;
//                                } else {
//                                    $balPlus[] = $profitLoss;
//                                }
//                            }
//
//                            if ($balExpose != null) {
//                                $minExpose = min($balExpose);
//                                $maxBal['expose'][] = $minExpose;
//                                $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                            }
//                            if ($balPlus != null) {
//                                $maxBal['plus'][] = max($balPlus);
//                                $eventProfitLimit[$eventId][] = max($balPlus);
//                                $marketProfitLimit[$marketId][] = max($balPlus);
//                                $sportProfitLimit[$sportId][] = max($balPlus);
//                            }
//
//                        }
//                    }
//
//                }

            }

            if ($cBet != null && $cBet->session_type == 'fancy2') {

                $marketId = $cBet->market_id; $minExpose = 0;
                //$eventNew = MarketType::findOne(['market_id' => $marketId]);

                $eventNew = (new \yii\db\Query())
                    ->select(['event_id'])
                    ->from('market_type')
                    ->where(['market_id' => $marketId])->createCommand(Yii::$app->db2)->queryOne();

                if ($eventNew != null) {
                    $eventId = $eventNew['event_id'];
                    $profitLossData = $this->getProfitLossFancyOnZeroNew1($uid,$marketId, 'fancy2', $cBet);

                    if ($profitLossData != null) {
                        $balExpose = $balPlus = [];
                        foreach ($profitLossData as $profitLoss) {
                            if ($profitLoss < 0) {
                                $balExpose[] = $profitLoss;
                            } else {
                                $balPlus[] = $profitLoss;
                            }
                        }

                        if ($balExpose != null) {
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }
                        if ($balPlus != null) {
                            $maxBal['plus'][] = max($balPlus);
                            $eventProfitLimit[$eventId][] = max($balPlus);
                            $marketProfitLimit[$marketId][] = max($balPlus);
                            $sportProfitLimit[$sportId][] = max($balPlus);
                        }

                    }
                }

            }

            // Lottery Market
            //$marketList5 = PlaceBet::find()->select(['market_id'])
            //    ->where(['user_id' => $uid, 'session_type' => 'lottery', 'bet_status' => 'Pending', 'status' => 1])
            //    ->andWhere(['!=', 'market_id', $cBet->market_id])
            //    ->groupBy(['market_id'])->asArray()->all();

            $marketList5 = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'lottery', 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

            if ($marketList5 != null) {

                $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList5 ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])->from('user_event_expose')->where($where)->one();
                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }

//                foreach ($marketList5 as $market) {
//
//                    $marketId = $market['market_id']; $minExpose = 0;
//
//                    $lottery = ManualSessionLottery::findOne(['market_id' => $marketId]);
//
//                    if ($lottery != null) {
//                        $eventId = $lottery->event_id;
//                        $balExpose = $balPlus = [];
//                        for ($n = 0; $n < 10; $n++) {
//                            $profitLoss = $this->getLotteryProfitLossNewAll1($uid,$eventId, $marketId, $n);
//                            if ($profitLoss < 0) {
//                                $balExpose[] = $profitLoss;
//                            } else {
//                                $balPlus[] = $profitLoss;
//                            }
//                        }
//
//                        if ($balExpose != null) {
//                            $minExpose = min($balExpose);
//                            $maxBal['expose'][] = $minExpose;
//                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                        }
//                        if ($balPlus != null) {
//                            $maxBal['plus'][] = max($balPlus);
//                            $eventProfitLimit[$eventId][] = max($balPlus);
//                            $marketProfitLimit[$marketId][] = max($balPlus);
//                            $sportProfitLimit[$sportId][] = max($balPlus);
//                        }
//
//                    }
//
//                }
            }


            if ($cBet != null) {

                $marketId = $cBet->market_id; $minExpose = 0;

                //$lottery = ManualSessionLottery::findOne(['market_id' => $marketId]);

                $lottery = (new \yii\db\Query())
                    ->select(['event_id'])
                    ->from('manual_session_lottery')
                    ->where(['market_id' => $marketId])->createCommand(Yii::$app->db2)->queryOne();

                if ($lottery != null) {
                    $eventId = $lottery['event_id'];
                    $balExpose = $balPlus = [];
                    for ($n = 0; $n < 10; $n++) {
                        $profitLoss = $this->getLotteryProfitLossNew1($uid,$eventId, $marketId, $n, $cBet);
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        } else {
                            $balPlus[] = $profitLoss;
                        }
                    }

                    if ($balExpose != null) {
                        $minExpose = min($balExpose);
                        $maxBal['expose'][] = $minExpose;
                        $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                    }else{
                        $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                    }
                    if ($balPlus != null) {
                        $maxBal['plus'][] = max($balPlus);
                        $eventProfitLimit[$eventId][] = max($balPlus);
                        $marketProfitLimit[$marketId][] = max($balPlus);
                        $sportProfitLimit[$sportId][] = max($balPlus);
                    }

                }

            }

            if (isset($maxBal['expose']) && $maxBal['expose'] != null && array_sum($maxBal['expose']) < 0) {
                $expose_balance = (-1) * (array_sum($maxBal['expose']));
            }

            $mywallet = $mywallet + $user_profit_loss;

            $profitLimitData = ['eventProfitLimit' => $eventProfitLimit, 'marketProfitLimit' => $marketProfitLimit, 'sportProfitLimit' => $sportProfitLimit];

            return $data = ['balance' => $mywallet, 'expose' => $expose_balance, 'plus' => 0, 'profitLimitData' => $profitLimitData];


        }
        return $data = ['balance' => 0, 'available' => 0, 'expose' => 0, 'plus' => 0, 'profitLimitData' => 0];

    }

    // getProfitLossMatchOddsNewAll
    public function getProfitLossMatchOddsNewAll1( $userId, $marketId, $eventId, $selId, $sessionType)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;

        //$sessionType = ['match_odd','match_odd2'];

        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'back', 'session_type' => $sessionType];

//            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
//
//            if ($backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '') {
//                $backWin = 0;
//            } else {
//                $backWin = $backWin[0]['val'];
//            }

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($backWin[0]['val']);die;
            if ($backWin == null || !isset($backWin['val']) || $backWin['val'] == '') {
                $backWin = 0;
            } else {
                $backWin = $backWin['val'];
            }


            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];
            $andWhere = ['!=', 'sec_id', $selId];

//            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
//                ->where($where)->andWhere($andWhere)->asArray()->all();
//
//            if ($layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '') {
//                $layWin = 0;
//            } else {
//                $layWin = $layWin[0]['val'];
//            }

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)
                ->andWhere($andWhere)->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layWin[0]['val']);die;

            if ($layWin == null || !isset($layWin['val']) || $layWin['val'] == '') {
                $layWin = 0;
            } else {
                $layWin = $layWin['val'];
            }

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = ['market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'lay'];

//            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
//
//            if ($layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '') {
//                $layLoss = 0;
//            } else {
//                $layLoss = $layLoss[0]['val'];
//            }

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layLoss[0]['val']);die;

            if ($layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '') {
                $layLoss = 0;
            } else {
                $layLoss = $layLoss['val'];
            }

            $where = ['market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'back'];
            $andWhere = ['!=', 'sec_id', $selId];

//            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
//                ->where($where)->andWhere($andWhere)->asArray()->all();
//
//            if ($backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '') {
//                $backLoss = 0;
//            } else {
//                $backLoss = $backLoss[0]['val'];
//            }

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->andWhere($andWhere)->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($backLoss[0]['val']);die;

            if ($backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '') {
                $backLoss = 0;
            } else {
                $backLoss = $backLoss['val'];
            }

            // IF UNMATCH BET LOSS
            $where = ['match_unmatch' => 0, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => ['back', 'lay']];

//            $unmatchLoss = PlaceBet::find()->select(['SUM(loss) as val'])
//                ->where($where)->asArray()->all();
//
//            if ($unmatchLoss == null || !isset($unmatchLoss[0]['val']) || $unmatchLoss[0]['val'] == '') {
//                $unmatchLoss = 0;
//            } else {
//                $unmatchLoss = $unmatchLoss[0]['val'];
//            }

            $unmatchLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($unmatchLoss == null || !isset($unmatchLoss['val']) || $unmatchLoss['val'] == '') {
                $unmatchLoss = 0;
            } else {
                $unmatchLoss = $unmatchLoss['val'];
            }

            $totalLoss = $backLoss + $layLoss + $unmatchLoss;

            $total = $totalWin - $totalLoss;

        }

        return $total;

    }

    // getProfitLossMatchOddsNew
    public function getProfitLossMatchOddsNew1($userId,$marketId, $eventId, $selId, $sessionType, $cBet)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;

        //$sessionType = ['match_odd','match_odd2'];
        //echo '<pre>';print_r($cBet);die;
        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'back', 'session_type' => $sessionType];
            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

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
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layWin[0]['val']);die;

            if ($layWin == null || !isset($layWin['val']) || $layWin['val'] == '') {
                $layWin = 0;
            } else {
                $layWin = $layWin['val'];
            }

            //$where = [ 'sec_id' => $selId,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'lay'];

            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layLoss[0]['val']);die;

            if ($layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '') {
                $layLoss = 0;
            } else {
                $layLoss = $layLoss['val'];
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'back'];
            $andWhere = ['!=', 'sec_id', $selId];

            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($backLoss[0]['val']);die;

            if ($backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '') {
                $backLoss = 0;
            } else {
                $backLoss = $backLoss['val'];
            }

            // IF UNMATCH BET LOSS
            $where = ['match_unmatch' => 0, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => ['back', 'lay']];

            //$unmatchLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->asArray()->all();

            $unmatchLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($unmatchLoss == null || !isset($unmatchLoss[0]['val']) || $unmatchLoss[0]['val'] == '') {
                $unmatchLoss = 0;
            } else {
                $unmatchLoss = $unmatchLoss[0]['val'];
            }

            $totalLoss = $backLoss + $layLoss + $unmatchLoss;

            if ($cBet->market_id == $marketId) {

                if ($selId == $cBet->sec_id) {

                    if ($cBet->bet_type == 'back') {

                        if ($cBet->match_unmatch == 1)
                            $totalWin = $totalWin + $cBet->win;
                        else
                            $totalLoss = $totalLoss + $cBet->loss;

                    } else {
                        $totalLoss = $totalLoss + $cBet->loss;

                    }

                } else {

                    if ($cBet->bet_type == 'back') {
                        $totalLoss = $totalLoss + $cBet->loss;

                    } else {
                        if ($cBet->match_unmatch == 1)
                            $totalWin = $totalWin + $cBet->win;
                        else
                            $totalLoss = $totalLoss + $cBet->loss;

                    }

                }

            }

            $total = $totalWin - $totalLoss;

        }

        return $total;

    }

    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancyOnZeroNewAll1($userId,$marketId, $sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = ['bet_status' => 'Pending', 'bet_status' => 'Pending', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

        //$betList = PlaceBet::find()
         //   ->select(['bet_type', 'price', 'win', 'loss'])
         //   ->where($where)->asArray()->all();

        $betList = (new \yii\db\Query())
            ->select(['bet_type', 'price', 'win', 'loss'])
            ->from('place_bet')->where($where)
            ->createCommand(Yii::$app->db2)->queryAll();

//	    $betMinRun = PlaceBet::find()
//	    ->select(['MIN( price ) as price'])
//	    ->where( $where )->one();
//	    //->orderBy(['price'=>SORT_ASC])
//	    //->asArray()->one();
//
//	    $betMaxRun = PlaceBet::find()
//	    ->select(['MAX( price ) as price'])
//	    ->where( $where )->one();
//	    //->orderBy(['price'=>SORT_DESC])
//	    //->asArray()->one();
//
//	    if( isset( $betMinRun->price ) ){
//	        $minRun = $betMinRun->price-1;
//	    }
//
//	    if( isset( $betMaxRun->price ) ){
//	        $maxRun = $betMaxRun->price+1;
//	    }

        if ($betList != null) {
            $result = [];

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

            $min = $min - 1;
            $max = $max + 1;

            for ($i = $min; $i <= $max; $i++) {

                $where = ['bet_status' => 'Pending', 'bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList1 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where($where)->andWhere(['>', 'price', (int)$i])
//                    ->asArray()->all();

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();


                $where = ['bet_status' => 'Pending', 'bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList2 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where($where)->andWhere(['<=', 'price', (int)$i])
//                    ->asArray()->all();

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['<=', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList3 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where($where)->andWhere(['>', 'price', (int)$i])
//                    ->asArray()->all();

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList4 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where($where)->andWhere(['<=', 'price', (int)$i])
//                    ->asArray()->all();

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['<=', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

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

                $result[$i] = $profit - $loss;
            }

        }

        return $result;
    }

    // getProfitLossFancyOnZeroNew
    public function getProfitLossFancyOnZeroNew1($userId,$marketId, $sessionType, $cBet)
    {
        //$userId = \Yii::$app->user->id;

        $where = ['bet_status' => 'Pending', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];

//        $betList = PlaceBet::find()
//            ->select(['bet_type', 'price', 'win', 'loss'])
//            ->where($where)->asArray()->all();

        $betList = (new \yii\db\Query())
            ->select(['bet_type', 'price', 'win', 'loss'])
            ->from('place_bet')->where($where)
            ->createCommand(Yii::$app->db2)->queryAll();

//	    $betMinRun = PlaceBet::find()
//	    ->select(['MIN( price ) as price'])
//	    ->where( $where )->one();
//
//	    //echo '<pre>';print_r($betMinRun);die;
//
//	    $betMaxRun = PlaceBet::find()
//	    ->select(['MAX( price ) as price'])
//	    ->where( $where )->one();
//	    //->orderBy(['price'=>SORT_DESC])
//	    //->asArray()->one();
//
//	    if( isset( $betMinRun->price ) ){
//	        $minRun = $betMinRun->price-1;
//	        if( $cBet->price <  $minRun )
//	            $minRun = $cBet->price-1;
//	    }else{
//	        $minRun = $cBet->price-1;
//	    }
//
//	    if( isset( $betMaxRun->price ) ){
//	        $maxRun = $betMaxRun->price+1;
//	        if( $cBet->price >  $maxRun )
//	            $maxRun = $cBet->price+1;
//	    }else{
//	        $maxRun = $cBet->price+1;
//	    }

        $result = [];
        if ($betList != null) {

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

            if ($cBet->price < $min) {
                $min = $cBet->price;
            }
            if ($cBet->price > $max) {
                $max = $cBet->price;
            }

            $min = $min - 1;
            $max = $max + 1;

            for ($i = $min; $i <= $max; $i++) {

                $where = ['bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList1 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where($where)->andWhere(['>', 'price', (int)$i])
//                    ->asArray()->all();

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList2 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where($where)->andWhere(['<=', 'price', (int)$i])
//                    ->asArray()->all();

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['<=', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'yes', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList3 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where($where)->andWhere(['>', 'price', (int)$i])
//                    ->asArray()->all();

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

                $where = ['bet_status' => 'Pending', 'bet_type' => 'no', 'session_type' => $sessionType, 'user_id' => $userId, 'market_id' => $marketId, 'status' => 1];
//                $betList4 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where($where)->andWhere(['<=', 'price', (int)$i])
//                    ->asArray()->all();

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['<=', 'price', (int)$i])
                    ->createCommand(Yii::$app->db2)->queryOne();

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
//                if ($marketId == '1.1559458055-MFY')
                //echo '<br />1-old profit - ' . $profit . ' <br />old loss -' . $loss . ' ' . $marketId . '<br />';
                if ($marketId == $cBet->market_id) {
                    if ($i < $cBet->price && $cBet->bet_type == 'no') {
                        $profit = $profit + $cBet->win;
                    } else if ($i >= $cBet->price && $cBet->bet_type == 'yes') {
                        $profit = $profit + $cBet->win;
                    } else {
                        $loss = $loss + $cBet->loss;
                    }
//                    if ($i < $cBet->price && $cBet->bet_type == 'yes') {
//                        $loss = $loss + $cBet->loss;
//                    }
//                    if ($i > $cBet->price && $cBet->bet_type == 'no') {
//                        $loss = $loss + $cBet->loss;
//                    }

                }
//                if ($marketId == '1.1559458055-MFY')
                //echo '<br />2-old profit - ' . $profit . ' <br />old loss -' . $loss . ' ' . $marketId . '<br />';
                $result[$i] = $profit - $loss;
            }

        } else {
            for ($i = ($cBet->price - 1); $i <= ($cBet->price + 1); $i++) {
                if ($i < $cBet->price && $cBet->bet_type == 'no') {
                    $result[$i] = $cBet->win;
                } else {
                    $result[$i] = (-1) * $cBet->loss;
                }
                if ($i >= $cBet->price && $cBet->bet_type == 'yes') {
                    $result[$i] = $cBet->win;
                } else {
                    $result[$i] = (-1) * $cBet->loss;
                }
            }

        }
        //echo print_r($result);
        return $result;

    }

    // getLotteryProfitLossNewAll
    public function getLotteryProfitLossNewAll1($userId,$eventId, $marketId, $selectionId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'lottery', 'user_id' => $userId, 'event_id' => $eventId, 'market_id' => $marketId, 'status' => 1];
        // IF RUNNER WIN
        //$betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where($where)
        //    ->andWhere(['sec_id' => $selectionId])->asArray()->all();

        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['sec_id' => $selectionId])
            ->createCommand(Yii::$app->db2)->queryOne();

        // IF RUNNER LOSS
        //$betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where($where)
        //    ->andWhere(['!=', 'sec_id', $selectionId])->asArray()->all();

        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=', 'sec_id', $selectionId])
            ->createCommand(Yii::$app->db2)->queryOne();

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

        $total = $totalWin + $totalLoss;

        return $total;
    }

    // getLotteryProfitLossNew
    public function getLotteryProfitLossNew1($userId,$eventId, $marketId, $selectionId, $cBet)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'lottery', 'user_id' => $userId, 'event_id' => $eventId, 'market_id' => $marketId];
        // IF RUNNER WIN
        //$betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where($where)
         //   ->andWhere(['sec_id' => $selectionId])->asArray()->all();

        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['sec_id' => $selectionId])
            ->createCommand(Yii::$app->db2)->queryOne();

        // IF RUNNER LOSS
        //$betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where($where)
         //   ->andWhere(['!=', 'sec_id', $selectionId])->asArray()->all();

        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=', 'sec_id', $selectionId])
            ->createCommand(Yii::$app->db2)->queryOne();

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

        if ($selectionId == $cBet->sec_id) {
            $totalWin = $totalWin + $cBet->win;
        } else {
            $totalLoss = $totalLoss - $cBet->loss;
        }

        $total = $totalWin + $totalLoss;

        return $total;
    }


    // getProfitLimitMatchOdds
    public function getProfitLimitMatchOdds($userId,$marketId, $eventId, $selId, $sessionType, $cBet)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;

        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'back', 'session_type' => $sessionType];
            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

            $backWin = (new \yii\db\Query())
                ->select('SUM(win) as val')
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($backWin[0]['val']);die;

            if ($backWin == null || !isset($backWin['val']) || $backWin['val'] == '') {
                $backWin = 0;
            } else {
                $backWin = $backWin['val'];
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];
            $andWhere = ['!=', 'sec_id', $selId];

            //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
             //   ->where($where)->andWhere($andWhere)->asArray()->all();

            $layWin = (new \yii\db\Query())
                ->select('SUM(win) as val')
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layWin[0]['val']);die;

            if ($layWin == null || !isset($layWin['val']) || $layWin['val'] == '') {
                $layWin = 0;
            } else {
                $layWin = $layWin['val'];
            }

            $where = ['sec_id' => $selId, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = ['market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'lay'];

            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select('SUM(loss) as val')
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($layLoss[0]['val']);die;

            if ($layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '') {
                $layLoss = 0;
            } else {
                $layLoss = $layLoss['val'];
            }

            $where = ['market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'back'];
            $andWhere = ['!=', 'sec_id', $selId];

            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select('SUM(loss) as val')
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            //echo '<pre>';print_r($backLoss[0]['val']);die;

            if ($backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '') {
                $backLoss = 0;
            } else {
                $backLoss = $backLoss[0]['val'];
            }

            $totalLoss = $backLoss + $layLoss;

            if ($cBet->market_id == $marketId) {

                if ($selId == $cBet->sec_id) {

                    if ($cBet->bet_type == 'back') {
                        $totalWin = $totalWin + $cBet->win;
                    } else {
                        $totalLoss = $totalLoss + $cBet->loss;
                    }

                } else {

                    if ($cBet->bet_type == 'back') {
                        $totalLoss = $totalLoss + $cBet->loss;
                    } else {
                        $totalWin = $totalWin + $cBet->win;
                    }

                }

            }

            $total = $totalWin - $totalLoss;

        }

        return $total;

    }


    //AllUserForAdmin
    public function getAllUserForAdmin($uid)
    {
        $userList = [];
        $smdata = (new \yii\db\Query())
            ->select(['id', 'role'])->from('user')
            ->where(['parent_id' => $uid, 'role' => [2, 3]])
            ->createCommand(\Yii::$app->db1)->queryAll();

        if ($smdata != null) {

            foreach ($smdata as $sm) {

                $userList[] = $sm['id'];
            }
        }

        return $userList;

    }


    //AllClientForSuperMaster
    public function getAllUserForSuperMaster($uid)
    {
        $userList = [];
        $smdata = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$uid, 'role' => 2])->all();

        if ($smdata != null) {
            foreach ($smdata as $sm) {
                $userList[] = (int)$sm->id;
                // get all master
                $m1data = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$sm->id, 'role' => 3])->all();
                if ($m1data != null) {
                    foreach ($m1data as $m1) {
                        $userList[] = (int)$m1->id;
                        // get all master
                        $m2data = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$m1->id, 'role' => 3])->all();
                        if ($m2data != null) {
                            foreach ($m2data as $m2) {
                                $userList[] = (int)$m2->id;
                            }
                        }

                    }
                }

            }
        }

        // get all master
        $mdata = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$uid, 'role' => 3])->all();
        if ($mdata != null) {

            foreach ($mdata as $m) {
                $userList[] = (int)$m->id;
                $m2data = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$m->id, 'role' => 3])->all();
                if ($m2data != null) {
                    foreach ($m2data as $m2) {
                        $userList[] = (int)$m2->id;
                    }
                }

            }

        }

        return $userList;

    }

    //AllUserForMaster
    public function getAllUserForMaster($uid)
    {
        $userList = [];
        // get all master
        $mdata = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$uid, 'role' => 3])->all();
        if ($mdata != null) {

            foreach ($mdata as $m) {
                $userList[] = (int)$m->id;
                $m2data = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$m->id, 'role' => 3])->all();
                if ($m2data != null) {
                    foreach ($m2data as $m2) {
                        $userList[] = (int)$m2->id;

                    }
                }

            }

        }

        return $userList;

    }


    //AllClientForAdmin
    public function getAllClientForAdmin($uid)
    {
        $client = [];

        $cdata = User::find()->select(['id'])
            ->where(['role' => 4, 'status' => 1])->asArray()->all();
        if ($cdata != null) {
            foreach ($cdata as $c) {
                $client[] = (int)$c['id'];
            }
        }

        return $client;

    }

    //AllClientForSuperMaster
    public function getAllClientForSuperMaster($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id', 'role'])
            ->where(['parent_id' => (int)$uid, 'role' => 2])->asArray()->all();

        if ($smdata != null) {
            foreach ($smdata as $sm) {
                // get all master
                $m1data = User::find()->select(['id', 'role'])
                    ->where(['parent_id' => (int)$sm['id'], 'role' => 3])->asArray()->all();
                if ($m1data != null) {
                    foreach ($m1data as $m1) {
                        // get all master
                        $m2data = User::find()->select(['id', 'role'])
                            ->where(['parent_id' => (int)$m1['id'], 'role' => 3])->asArray()->all();
                        if ($m2data != null) {
                            foreach ($m2data as $m2) {

                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id' => (int)$m2['id'], 'role' => 4])->asArray()->all();
                                if ($cdata != null) {
                                    foreach ($cdata as $c) {
                                        $client[] = (int)$c['id'];
                                    }
                                }

                            }
                        }

                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id' => (int)$m1['id'], 'role' => 4])->asArray()->all();
                        if ($cdata != null) {
                            foreach ($cdata as $c) {
                                $client[] = (int)$c['id'];
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id' => (int)$sm['id'], 'role' => 4])->asArray()->all();
                if ($cdata != null) {
                    foreach ($cdata as $c) {
                        $client[] = (int)$c['id'];
                    }
                }

            }
        }

        // get all master
        $mdata = User::find()->select(['id', 'role'])
            ->where(['parent_id' => (int)$uid, 'role' => 3])->asArray()->all();
        if ($mdata != null) {

            foreach ($mdata as $m) {

                $m2data = User::find()->select(['id', 'role'])
                    ->where(['parent_id' => (int)$m['id'], 'role' => 3])->asArray()->all();
                if ($m2data != null) {
                    foreach ($m2data as $m2) {

                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id' => (int)$m2['id'], 'role' => 4])->asArray()->all();
                        if ($cdata != null) {
                            foreach ($cdata as $c) {
                                $client[] = (int)$c['id'];
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id' => (int)$m['id'], 'role' => 4])->asArray()->all();
                if ($cdata != null) {
                    foreach ($cdata as $c) {
                        $client[] = (int)$c['id'];
                    }
                }

            }

        }

        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$uid, 'role' => 4])->asArray()->all();
        if ($cdata != null) {
            foreach ($cdata as $c) {
                $client[] = (int)$c['id'];
            }
        }

        return $client;

    }

    //AllClientForMaster
    public function getAllClientForMaster($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id', 'role'])
            ->where(['parent_id' => (int)$uid, 'role' => 2])->asArray()->all();

        if ($smdata != null) {
            foreach ($smdata as $sm) {
                // get all master
                $m1data = User::find()->select(['id', 'role'])
                    ->where(['parent_id' => (int)$sm['id'], 'role' => 3])->asArray()->all();
                if ($m1data != null) {
                    foreach ($m1data as $m1) {
                        // get all master
                        $m2data = User::find()->select(['id', 'role'])
                            ->where(['parent_id' => (int)$m1['id'], 'role' => 3])->asArray()->all();
                        if ($m2data != null) {
                            foreach ($m2data as $m2) {

                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id' => (int)$m2['id'], 'role' => 4])->asArray()->all();
                                if ($cdata != null) {
                                    foreach ($cdata as $c) {
                                        $client[] = (int)$c['id'];
                                    }
                                }

                            }
                        }

                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$m1['id'], 'role' => 4])->asArray()->all();
                        if ($cdata != null) {
                            foreach ($cdata as $c) {
                                $client[] = (int)$c['id'];
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$sm['id'], 'role' => 4])->asArray()->all();
                if ($cdata != null) {
                    foreach ($cdata as $c) {
                        $client[] = (int)$c['id'];
                    }
                }

            }
        }

        // get all master
        $mdata = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$uid, 'role' => 3])->asArray()->all();
        if ($mdata != null) {

            foreach ($mdata as $m) {

                $m2data = User::find()->select(['id', 'role'])->where(['parent_id' => (int)$m['id'], 'role' => 3])->asArray()->all();
                if ($m2data != null) {
                    foreach ($m2data as $m2) {

                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$m2['id'], 'role' => 4])->asArray()->all();
                        if ($cdata != null) {
                            foreach ($cdata as $c) {
                                $client[] = (int)$c['id'];
                            }
                        }

                    }
                }

                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$m['id'], 'role' => 4])->asArray()->all();
                if ($cdata != null) {
                    foreach ($cdata as $c) {
                        $client[] = (int)$c['id'];
                    }
                }

            }

        }

        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id' => (int)$uid, 'role' => 4])->asArray()->all();
        if ($cdata != null) {
            foreach ($cdata as $c) {
                $client[] = (int)$c['id'];
            }
        }

        return $client;

    }

    /*
     * Common Functions
     */

    //gameover Result MatchOdds
    public function commonGameoverResultMatchOdds($eventId, $marketId, $winResult)
    {

        if (isset($eventId) && isset($winResult) && ($winResult != null) && ($eventId != null)) {

            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id', 'event_id'])
                ->where(['market_id' => $marketId, 'event_id' => $eventId, 'sec_id' => $winResult])
                ->andWhere(['bet_status' => 'Pending', 'bet_type' => 'back', 'status' => 1, 'match_unmatch' => 1])
                ->asArray()->all();

            //echo '<pre>';print_r($backWinList);die;

            $layWinList = PlaceBet::find()->select(['id', 'event_id'])
                ->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->andwhere(['!=', 'sec_id', $winResult])
                ->andWhere(['bet_status' => 'Pending', 'bet_type' => 'lay', 'status' => 1, 'match_unmatch' => 1])
                ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if ($backWinList != null) {
                foreach ($backWinList as $list) {
                    $win = PlaceBet::findOne(['id' => $list['id'], 'event_id' => $list['event_id']]);
                    //echo '<pre>';print_r($win);die;
                    if ($win != null) {
                        $win->bet_status = 'Win';
                        if ($win->save(['bet_status'])) {
                            $this->commonTransectionWin($win->id, $win->event_id);
                        }
                    }
                }
            }

            if ($layWinList != null) {

                foreach ($layWinList as $list) {
                    $win = PlaceBet::findOne(['id' => $list['id'], 'event_id' => $list['event_id']]);
                    if ($win != null) {
                        $win->bet_status = 'Win';
                        if ($win->save(['bet_status'])) {
                            $this->commonTransectionWin($win->id, $win->event_id);
                        }
                    }
                }

            }

            /* User Loss calculation */

            $lossList = PlaceBet::find()->select(['id', 'event_id'])
                ->where(['market_id' => $marketId, 'event_id' => $eventId])
                ->where(['!=', 'bet_status', 'Win'])
                ->andWhere(['bet_status' => 'Pending', 'bet_type' => ['back', 'lay'], 'status' => 1, 'match_unmatch' => 1])
                ->asArray()->all();

            if ($lossList != null) {

                foreach ($lossList as $list) {
                    $loss = PlaceBet::findOne(['id' => $list['id'], 'event_id' => $list['event_id']]);
                    if ($loss != null) {
                        $loss->bet_status = 'Loss';
                        if ($loss->save(['bet_status'])) {
                            $this->commonTransactionLoss($loss->id, $loss->event_id);
                        }
                    }
                }

            }
        }

    }

    // transection Win
    public function commonTransectionWin($betID, $eventId)
    {
        $model = PlaceBet::findOne(['id' => $betID]);
        $clientId = $model->user_id;
        $marketId = $model->market_id;
        $ccr = $model->ccr;
        $amount = $model->win;
        $client = User::findOne($model->user_id);
        $client->balance = $client->balance + $amount;
        //$updateTransArr = [];
        if ($client != null && $client->save(['balance'])
            && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $client->id, $client->parent_id, $betID, $eventId, $marketId, 'F', $client->username, ($amount - $ccr), $ccr, $client->balance)) {

            $parent = User::findOne($client->parent_id);
            if ($parent->role === 1) {
                //if client parent admin
                $admin = $parent;//User::findOne( $client->parent_id );
                $commission = $amount;
                $admin->profit_loss_balance = ($admin->profit_loss_balance - $amount);
                //$admin->balance = ( $admin->balance - $commission );
                if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance - $admin->profit_loss_balance))) {
                    return true;
                } else {
                    return false;
                }

            } else {
                //client to master
                $agent21 = User::findOne($client->parent_id);
                if ($agent21 != null && $agent21->profit_loss != 0) {
                    $profitLoss1 = $agent21->profit_loss;
                    //$commission1 = round ( ( $amount*$profitLoss1 )/100 ,1);
                    $commission1 = $amount;
                    //$agent21->balance = ( $agent21->balance-$commission1 );
                    //$amount = ( $amount-$commission1 );
                    $agent21->profit_loss_balance = ($agent21->profit_loss_balance - $commission1);

                    if ($agent21->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $agent21->id, $agent21->parent_id, $betID, $eventId, $marketId, 'E', $agent21->username, $commission1, $ccr, ($agent21->balance - $agent21->profit_loss_balance))) {

                        $parent = User::findOne($agent21->parent_id);

                        if ($parent->role === 1) {
                            $ccrRate = 1;
                            $admin = $parent;//User::findOne( $agent21->parent_id );
                            $profitLoss = 100 - $profitLoss1;
                            $commission = round(($amount * $profitLoss) / 100, 1);
                            $ccr = round(($commission * $ccrRate) / 100, 1);

                            $admin->profit_loss_balance = ($admin->profit_loss_balance - $commission);

                            //$admin->balance = ( $admin->balance-$commission );
                            if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance - $admin->profit_loss_balance))) {
                                //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission,$ccr,$agent21->profit_loss_balance)){
                                return true;
                            } else {
                                return false;
                            }

                        } else {

                            //master to master
                            $agent22 = User::findOne($agent21->parent_id);
                            if ($agent22 != null && $agent22->profit_loss != 0) {
                                $ccrRate = 1;
                                $profitLoss2 = 100 - $agent21->profit_loss;//$agent22->profit_loss-$agent21->profit_loss;
                                $commission2 = round(($amount * $profitLoss2) / 100, 1);
                                $ccr = round(($commission2 * $ccrRate) / 100, 1);
                                $agent22->profit_loss_balance = ($agent22->profit_loss_balance - $commission2);

                                //$agent22->balance = ( $agent22->balance-$commission2 );
                                //$amount = ( $amount-$commission2 );
                                if ($agent22->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $agent22->id, $agent22->parent_id, $betID, $eventId, $marketId, 'D', $agent22->username, $commission2, $ccr, ($agent22->balance - $agent22->profit_loss_balance))) {
                                    //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission2,$ccr,$agent21->profit_loss_balance)){
                                    $parent = User::findOne($agent22->parent_id);
                                    if ($parent->role === 1) {
                                        $ccrRate = 1;
                                        $admin = $parent;//User::findOne( $agent22->parent_id );
                                        $profitLoss = 100 - $agent22->profit_loss;
                                        $commission = round(($amount * $profitLoss) / 100, 1);
                                        $ccr = round(($commission * $ccrRate) / 100, 1);
                                        $admin->profit_loss_balance = ($admin->profit_loss_balance - $commission);
                                        //$admin->balance = ( $admin->balance-$commission );
                                        if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance - $admin->profit_loss_balance))) {
                                            //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission,$ccr,$agent22->profit_loss_balance) ){
                                            return true;
                                        } else {
                                            return false;
                                        }

                                    } else {

                                        //master to super master

                                        $agent11 = User::findOne($agent22->parent_id);

                                        if ($agent11 != null && $agent11->profit_loss != 0) {
                                            $ccrRate = 1;
                                            $profitLoss3 = 100 - $agent22->profit_loss;//$agent11->profit_loss-$agent22->profit_loss;
                                            $commission3 = round(($amount * $profitLoss3) / 100, 1);
                                            $ccr = round(($commission3 * $ccrRate) / 100, 1);
                                            //$agent11->balance = ( $agent11->balance-$commission3 );
                                            $agent11->profit_loss_balance = ($agent11->profit_loss_balance - $commission3);
                                            //$amount = ( $amount-$commission3 );
                                            if ($agent11->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $agent11->id, $agent11->parent_id, $betID, $eventId, $marketId, 'C', $agent11->username, $commission3, $ccr, ($agent11->balance - $agent11->profit_loss_balance))) {
                                                //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission3,$ccr,$agent22->profit_loss_balance)){

                                                $parent = User::findOne($agent11->parent_id);
                                                if ($parent->role === 1) {
                                                    $ccrRate = 1;
                                                    $admin = $parent;//User::findOne( $agent->parent_id );
                                                    $profitLoss = 100 - $agent11->profit_loss;
                                                    $commission = round(($amount * $profitLoss) / 100, 1);
                                                    $ccr = round(($commission * $ccrRate) / 100, 1);
                                                    $admin->profit_loss_balance = ($admin->profit_loss_balance - $commission);


                                                    //$admin->balance = ( $admin->balance-$commission );
                                                    if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance - $admin->profit_loss_balance))) {
                                                        //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission,$ccr,$agent11->profit_loss_balance) ){
                                                        return true;
                                                    } else {
                                                        return false;
                                                    }
                                                } else {

                                                    //super master to super master
                                                    $agent12 = User::findOne($agent11->parent_id);
                                                    if ($agent12 != null && $agent12->profit_loss != 0) {
                                                        $ccrRate = 1;
                                                        $profitLoss4 = 100 - $agent11->profit_loss;//$agent12->profit_loss-$agent11->profit_loss;
                                                        $commission4 = round(($amount * $profitLoss4) / 100, 1);
                                                        $ccr = round(($commission4 * $ccrRate) / 100, 1);
                                                        //$agent12->balance = ( $agent12->balance-$commission4 );
                                                        //$amount = ( $amount-$commission4 );
                                                        $agent12->profit_loss_balance = ($agent12->profit_loss_balance - $commission4);

                                                        if ($agent12->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $agent12->id, $agent12->parent_id, $betID, $eventId, $marketId, 'B', $agent12->username, $commission4, $ccr, ($agent12->profit_loss_balance - $agent12->profit_loss_balance))) {
                                                            //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission4,$ccr,$agent11->profit_loss_balance) ){
                                                            $admin = User::findOne($agent12->parent_id);
                                                            $ccrRate = 1;
                                                            $profitLoss = 100 - $agent12->profit_loss;
                                                            $commission = round(($amount * $profitLoss) / 100, 1);
                                                            $ccr = round(($commission * $ccrRate) / 100, 1);
                                                            $admin->profit_loss_balance = ($admin->profit_loss_balance - $commission);
                                                            //$admin->balance = ( $admin->balance-$commission );
                                                            if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance - $admin->profit_loss_balance))) {
                                                                //&& $this->commonUpdateTempTransactionHistory('CREDIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission,$ccr,$agent12->profit_loss_balance) ){
                                                                return true;
                                                            } else {
                                                                return false;
                                                            }

                                                        } else {
                                                            return false;
                                                        }

                                                    } else {
                                                        return false;
                                                    }
                                                }

                                            } else {
                                                return false;
                                            }


                                        } else {
                                            return false;
                                        }

                                    }

                                } else {
                                    return false;
                                }


                            } else {
                                return false;
                            }

                        }

                    } else {
                        return false;
                    }

                } else {
                    return false;
                }

            }

        }
    }

    // transaction Loss
    public function commonTransactionLoss($betID, $eventId)
    {
        $model = PlaceBet::findOne(['id' => $betID]);
        $clientId = $model->user_id;
        $marketId = $model->market_id;
        $amount = $model->loss;
        $ccr = 0;
        $client = User::findOne($model->user_id);
        $client->balance = ($client->balance - $amount);

        if ($client != null && $client->save(['balance'])
            && $this->commonUpdateTempTransactionHistory('DEBIT', $clientId, $client->id, $client->parent_id, $betID, $eventId, $marketId, 'F', $client->username, $amount, $ccr, $client->balance)) {

            $parent = User::findOne($client->parent_id);
            if ($parent->role === 1) {
                $admin = $parent;//User::findOne( $client->parent_id );
                $commission = $amount;
                $admin->profit_loss_balance = $admin->profit_loss_balance + $amount;
                //$admin->balance = ( $admin->balance+$commission );
                if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $amount, $ccr, ($admin->balance + $admin->profit_loss_balance))) {
                    return true;
                } else {
                    return false;
                }

            } else {

                //client to master
                $agent21 = User::findOne($client->parent_id);
                if ($agent21 != null && $agent21->profit_loss != 0) {
                    $profitLoss1 = $agent21->profit_loss;
                    $commission1 = $amount;
                    $agent21->profit_loss_balance = $agent21->profit_loss_balance + $amount;
                    //$agent21->balance = ( $agent21->balance+$commission1 );
                    //$amount = ( $amount-$commission1 );
                    if ($agent21->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $agent21->id, $agent21->parent_id, $betID, $eventId, $marketId, 'E', $agent21->username, $commission1, $ccr, ($agent21->balance + $agent21->profit_loss_balance))) {
                        $parent = User::findOne($agent21->parent_id);
                        if ($parent->role === 1) {

                            $admin = $parent;//User::findOne( $agent21->parent_id );
                            $profitLoss = 100 - $profitLoss1;
                            $commission = round(($amount * $profitLoss) / 100, 1);
                            //$admin->balance = ( $admin->balance+$commission );
                            $admin->profit_loss_balance = $admin->profit_loss_balance + $commission;
                            if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance + $admin->profit_loss_balance))) {
                                //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission,$ccr,$agent21->balance)){
                                return true;
                            } else {
                                return false;
                            }

                        } else {

                            //master to master
                            $agent22 = User::findOne($agent21->parent_id);
                            if ($agent22 != null && $agent22->profit_loss != 0) {
                                $profitLoss2 = 100 - $agent21->profit_loss;//$agent22->profit_loss-$agent21->profit_loss;
                                $commission2 = round(($amount * $profitLoss2) / 100, 1);
                                //$agent22->balance = ( $agent22->balance+$commission2 );
                                //$amount = ( $amount-$commission2 );
                                $agent22->profit_loss_balance = $agent22->profit_loss_balance + $commission2;
                                if ($agent22->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $agent22->id, $agent22->parent_id, $betID, $eventId, $marketId, 'D', $agent22->username, $commission2, $ccr, ($agent22->balance + $agent22->profit_loss_balance))) {
                                    //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission2,$ccr,$agent21->balance)){
                                    $parent = User::findOne($agent22->parent_id);
                                    if ($parent->role === 1) {
                                        $admin = $parent;//User::findOne( $agent22->parent_id );
                                        $profitLoss = 100 - $agent22->profit_loss;
                                        $commission = round(($amount * $profitLoss) / 100, 1);
                                        //$admin->balance = ( $admin->balance+$commission );
                                        $admin->profit_loss_balance = $admin->profit_loss_balance + $commission;
                                        if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance + $admin->profit_loss_balance))) {
                                            //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission,$ccr,$agent22->balance)){
                                            return true;
                                        } else {
                                            return false;
                                        }

                                    } else {

                                        //master to super master
                                        $agent11 = User::findOne($agent22->parent_id);

                                        if ($agent11 != null && $agent11->profit_loss != 0) {

                                            $profitLoss3 = 100 - $agent22->profit_loss;//$agent11->profit_loss-$agent22->profit_loss;

                                            $commission3 = round(($amount * $profitLoss3) / 100, 1);
                                            $agent11->profit_loss_balance = $agent11->profit_loss_balance + $commission3;
                                            //$agent11->balance = ( $agent11->balance+$commission3 );
                                            //$amount = ( $amount-$commission3 );
                                            if ($agent11->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $agent11->id, $agent11->parent_id, $betID, $eventId, $marketId, 'C', $agent11->username, $commission3, $ccr, ($agent11->balance + $agent11->profit_loss_balance))) {
                                                //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission3,$ccr,$agent22->balance) ){
                                                $parent = User::findOne($agent11->parent_id);
                                                if ($parent->role === 1) {

                                                    $admin = $parent;//User::findOne( $agent11->parent_id );
                                                    $profitLoss = 100 - $agent11->profit_loss;
                                                    $commission = round(($amount * $profitLoss) / 100, 1);
                                                    //$admin->balance = ( $admin->balance+$commission );
                                                    $admin->profit_loss_balance = $admin->profit_loss_balance + $commission;
                                                    if ($admin->save(['profit_loss_balance']) && $admin->save(['profit_loss_balance']) && $admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance + $admin->profit_loss_balance))) {
                                                        //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission,$ccr,$agent11->balance) ){
                                                        return true;
                                                    } else {
                                                        return false;
                                                    }
                                                } else {

                                                    //super master to super master
                                                    $agent12 = User::findOne($agent11->parent_id);
                                                    if ($agent12 != null && $agent12->profit_loss != 0) {
                                                        $profitLoss4 = 100 - $agent11->profit_loss;//$agent12->profit_loss-$agent11->profit_loss;
                                                        $commission4 = round(($amount * $profitLoss4) / 100, 1);
                                                        //$agent12->balance = ( $agent12->balance+$commission4 );
                                                        //$amount = ( $amount-$commission4 );
                                                        $agent12->profit_loss_balance = $agent12->profit_loss_balance + $commission4;
                                                        if ($agent12->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $agent12->id, $agent12->parent_id, $betID, $eventId, $marketId, 'B', $agent12->username, $commission4, $ccr, ($agent12->balance + $agent12->profit_loss_balance))) {
                                                            //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission4,$ccr,$agent11->balance) ){
                                                            $admin = User::findOne($agent12->parent_id);
                                                            $profitLoss = 100 - $agent12->profit_loss;
                                                            $commission = round(($amount * $profitLoss) / 100, 1);
                                                            //$admin->balance = ( $admin->balance+$commission );
                                                            $admin->profit_loss_balance = $admin->profit_loss_balance + $commission;
                                                            if ($admin->save(['profit_loss_balance']) && $this->commonUpdateTempTransactionHistory('CREDIT', $clientId, $admin->id, $admin->parent_id, $betID, $eventId, $marketId, 'A', $admin->username, $commission, $ccr, ($admin->balance + $admin->profit_loss_balance))) {
                                                                //&& $this->commonUpdateTempTransactionHistory('DEBIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission,$ccr,$agent12->balance)){
                                                                return true;
                                                            } else {
                                                                return false;
                                                            }

                                                        } else {
                                                            return false;
                                                        }

                                                    } else {
                                                        return false;
                                                    }

                                                }

                                            } else {
                                                return false;
                                            }


                                        } else {
                                            return false;
                                        }

                                    }

                                } else {
                                    return false;
                                }


                            } else {
                                return false;
                            }

                        }

                    } else {
                        return false;
                    }

                } else {
                    return false;
                }

            }

        }
    }

    // Update Temp Transaction History
    public function commonUpdateTempTransactionHistory($type, $clientId, $uId, $parentId, $betId, $eventId, $marketId, $parentType, $uName, $amount, $ccr, $balance)
    {
        /*$trans = new TempTransactionHistory();
        $trans->client_id = $clientId;
        $trans->user_id = $uId;
        $trans->parent_id = $parentId;
        $trans->bet_id = $betId;
        $trans->event_id = $eventId;
        $trans->market_id = $marketId;
        $trans->parent_type = $parentType;
        $trans->username = $uName;
        $trans->transaction_type = $type;
        $trans->transaction_amount = $amount;
        $trans->commission = $ccr;
        $trans->current_balance = $balance;
        $trans->description = $this->getDescription($betId,$eventId);
        $trans->status = 1;
        $trans->updated_at = $trans->created_at = time();*/

        $resultArr = [
            'client_id' => $clientId,
            'user_id' => $uId,
            'parent_id' => $parentId,
            'bet_id' => $betId,
            'event_id' => $eventId,
            'market_id' => $marketId,
            'parent_type' => $parentType,
            'username' => $uName,
            'transaction_type' => $type,
            'transaction_amount' => $amount,
            'commission' => $ccr,
            'current_balance' => $balance,
            'description' => $this->commonDescription($betId, $eventId),
            'status' => 1,
            'updated_at' => time(),
            'created_at' => time(),
        ];

        if (\Yii::$app->db->createCommand()->insert('temp_transaction_history', $resultArr)->execute()) {
            return true;
        } else {
            return false;
        }

    }

    public function commonRunnerName($eventId, $marketId, $selectionId)
    {

        $runnerName = 'undefined';
        $runners = EventsRunner::find()->select(['runner'])
            ->where(['event_id' => $eventId, 'market_id' => $marketId, 'selection_id' => $selectionId])
            ->asArray()->one();
        if ($runners != null) {
            $runnerName = $runners['runner'];
        } else {
            $runners = ManualSessionMatchOddData::find()->select(['runner'])
                ->where(['market_id' => $marketId, 'sec_id' => $selectionId])
                ->asArray()->one();
            if ($runners != null) {
                $runnerName = $runners['runner'];
            }
        }
        return $runnerName;
    }

    // Function to get the Tras Description
    public function commonDescription($betId, $eventId)
    {
        $type = $size = 'NoData';

        $betData = PlaceBet::find()->select(['bet_type', 'size', 'rate', 'description'])
            ->where(['id' => $betId, 'event_id' => $eventId, 'status' => 1, 'bet_status' => ['Win', 'Loss']])->one();

        if ($betData != null) {
            $type = $betData->bet_type;
            $size = $betData->size;
            $rate = $betData->rate;
            $description = $betData->description;
        }

        $description = $description . ' > ' . $rate . ' > ' . $type . ' > ' . $size;

        return $description;
    }

    // Function Update User Expose
    public function updateUserExpose( $uid, $eventId, $marketId, $minExpose )
    {
        $minExpose = (-1)*($minExpose);
        $where = [ 'user_id' => $uid , 'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];

        $userExpose = (new \yii\db\Query())
            ->select(['id'])->from('user_event_expose')
            ->where($where)->createCommand(Yii::$app->db2)->queryOne();

        if( $userExpose != null ){

            $updateData = [
                'expose' => $minExpose,
                'updated_at' => time(),
            ];

            \Yii::$app->db->createCommand()->update('user_event_expose', $updateData , $where )->execute();

        }else{

            $addData = [
                'user_id' => $uid,
                'event_id' => $eventId,
                'market_id' => $marketId,
                'expose' => $minExpose,
                'updated_at' => time(),
                'status' => 1,
            ];

            \Yii::$app->db->createCommand()->insert('user_event_expose', $addData )->execute();
        }

    }


    //newUpdateUserExpose
    public function newUpdateUserExpose($uid,$marketId,$sessionType)
    {

        $exposeBalance = 0;

//        $marketList = PlaceBet::find()->select(['market_id'])
//            ->where(['user_id' => $uid, 'bet_status' => 'Pending', 'status' => 1])
//            ->andWhere(['!=', 'market_id', $marketId ])
//            ->groupBy(['market_id'])->asArray()->all();

        $marketList = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['user_id' => $uid, 'bet_status' => 'Pending', 'status' => 1])
            ->andWhere(['!=', 'market_id', $marketId ])
            ->groupBy(['market_id'])->createCommand(Yii::$app->db2)->queryAll();

        if ($marketList != null) {
            $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList ];
            $userExpose = (new \yii\db\Query())
                ->select(['sum(expose) as exposeVal'])
                ->from('user_event_expose')->where($where)->createCommand(Yii::$app->db2)->queryOne();

            if( $userExpose != null ){
                $balExpose = (-1)*($userExpose['exposeVal']);

                if( $balExpose != null ){
                    $exposeBalance += $balExpose;
                }

            }

        }

        // Match Odds
        if( $sessionType == 'match_odd' ){
            //$event = EventsPlayList::findOne(['market_id' => $marketId]);

            $event = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('events_play_list')->where(['market_id' => $marketId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {
                $eventId = $event['event_id'];
                //$runnersData = EventsRunner::findAll(['market_id' => $marketId, 'event_id' => $eventId]);

                $runnersData = (new \yii\db\Query())
                    ->select(['selection_id'])
                    ->from('events_runners')->where(['market_id' => $marketId, 'event_id' => $eventId])
                    ->createCommand(Yii::$app->db2)->queryAll();

                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLossMatchOddsNewAll1($uid,$marketId, $eventId, $runners['selection_id'], 'match_odd');

                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        }

                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $exposeBalance += $minExpose;
                    $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }else{
                    $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                }
            }
        }

        // Book Maker
        if( $sessionType == 'match_odd2' ){
            //$event = ManualSessionMatchOdd::findOne(['market_id' => $marketId]);

            $event = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('manual_session_match_odd')->where(['market_id' => $marketId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {
                $eventId = $event['event_id'];
                //$runnersData = ManualSessionMatchOddData::findAll(['market_id' => $marketId]);

                $runnersData = (new \yii\db\Query())
                    ->select(['sec_id'])
                    ->from('manual_session_match_odd_data')->where(['market_id' => $marketId])
                    ->createCommand(Yii::$app->db2)->queryAll();

                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = $this->getProfitLossMatchOddsNewAll1($uid,$marketId, $eventId, $runners['sec_id'], 'match_odd2');
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        }

                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $exposeBalance += $minExpose;
                    $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }else{
                    $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                }
            }
        }

        // Fancy
        if( $sessionType == 'fancy' ){
            //$event = ManualSession::findOne(['market_id' => $marketId]);

            $event = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('manual_session')->where(['market_id' => $marketId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {
                $eventId = $event['event_id'];
                $profitLossData = $this->getProfitLossFancyOnZeroNewAll1($uid,$marketId, 'fancy');

                if ($profitLossData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($profitLossData as $profitLoss) {
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        }
                    }

                    if ($balExpose != null) {
                        $minExpose = min($balExpose);
                        $exposeBalance += $minExpose;
                        $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                    }else{
                        $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                    }
                }
            }
        }

        // Fancy 2
        if( $sessionType == 'fancy2' ){
            //$event = MarketType::findOne(['market_id' => $marketId]);

            $event = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('market_type')->where(['market_id' => $marketId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($event != null) {
                $eventId = $event['event_id'];
                $profitLossData = $this->getProfitLossFancyOnZeroNewAll1($uid,$marketId, 'fancy2');

                if ($profitLossData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($profitLossData as $profitLoss) {
                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        }
                    }

                    if ($balExpose != null) {
                        $minExpose = min($balExpose);
                        $exposeBalance += $minExpose;
                        $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                    }else{
                        $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                    }

                }
            }
        }

        // Lottery
        if( $sessionType == 'fancy2' ){
            //$lottery = ManualSessionLottery::findOne(['market_id' => $marketId]);

            $lottery = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('manual_session_lottery')->where(['market_id' => $marketId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($lottery != null) {
                $eventId = $lottery['event_id'];
                $balExpose = $balPlus = [];
                for ($n = 0; $n < 10; $n++) {
                    $profitLoss = $this->getLotteryProfitLossNewAll1($uid,$eventId, $marketId, $n);
                    if ($profitLoss < 0) {
                        $balExpose[] = $profitLoss;
                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $exposeBalance += $minExpose;
                    $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }else{
                    $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                }

            }
        }

        if( isset( $exposeBalance ) && $exposeBalance < 0 ){
            $exposeBalance = (-1)*( $exposeBalance );
        }

        //echo $exposeBalance;die;

        \Yii::$app->db->createCommand()
            ->update('user', ['expose_balance' => $exposeBalance], ['id' => $uid])
            ->execute();

        //return true;

    }

}
