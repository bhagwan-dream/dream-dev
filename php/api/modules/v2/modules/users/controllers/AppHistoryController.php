<?php
namespace api\modules\v2\modules\users\controllers;

use common\models\UserProfitLoss;
use Yii;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use yii\data\ActiveDataProvider;
use common\models\User;
use common\models\TransactionHistory;
use common\models\Setting;
use common\models\TempTransactionHistory;
use common\models\EventsPlayList;
use common\models\ManualSessionMatchOddData;
use common\models\ManualSessionMatchOdd;
use common\models\EventsRunner;
use common\models\ManualSessionLottery;
use common\models\MarketType;
use common\models\ManualSession;

class AppHistoryController extends \common\controllers\aController // \yii\rest\Controller
{
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
    
    public function actionGetBalance()
    {
        $uid = \Yii::$app->user->id;
        $data = $this->getBalanceValUpdate($uid);
        return [ "status" => 1 , "data" => $data ];
        
    }
    
    public function getBalanceValUpdate($uid)
    {
        $user = User::find()->select(['balance','expose_balance','profit_loss_balance'])->where(['id' => $uid ])->one();
        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];
        if( $user != null ){
            $mywallet = $user->balance+$user->profit_loss_balance;
            $user_balance = $user->balance+$user->profit_loss_balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;
            
            //Match Odd Expose
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            if( $marketList != null ){
                
                foreach ( $marketList as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $event = EventsPlayList::findOne(['market_id'=>$marketId]);
                    if( $event != null ){
                        $eventId = $event->event_id;
                        $runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);
                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($marketId,$eventId,$runners->selection_id,'match_odd');
                                
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                                
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                        
                    }
                    
                }
            }
            
            
            //Match Odd 2 Expose
            $marketList2 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd2' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList2 != null ){
                
                foreach ( $marketList2 as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId]);
                    if( $manualMatchOdd != null ){
                        $eventId = $manualMatchOdd->event_id;
                        $runnersData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);
                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($marketId,$eventId,$runners->sec_id,'match_odd2');
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                    }
                    
                }
            }
            
            // Fancy Expose
            $marketList3 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList3 != null ){
                
                foreach ( $marketList3 as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $profitLossData = $this->getProfitLossFancyOnZero($marketId,'fancy');
                    
                    if( $profitLossData != null ){
                        $maxBal['expose'][] = min($profitLossData);
                    }
                    if( $profitLossData != null ){
                        $maxBal['plus'][] = max($profitLossData);
                    }
                }
            }
            
            // Fancy 2 Expose
            $marketList4 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy2' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList4 != null ){
                
                foreach ( $marketList4 as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $profitLossData = $this->getProfitLossFancyOnZero($marketId,'fancy2');
                    
                    if( $profitLossData != null ){
                        $maxBal['expose'][] = min($profitLossData);
                    }
                    if( $profitLossData != null ){
                        $maxBal['plus'][] = max($profitLossData);
                    }
                }
            }
            
            // Lottery Expose
            $marketList5 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'lottery' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList5 != null ){
                
                foreach ( $marketList5 as $market ){
                    
                    $marketId = $market['market_id'];
                    $lottery = ManualSessionLottery::findOne(['market_id'=>$marketId]);
                    
                    if( $lottery != null ){
                        
                        $eventId = $lottery->event_id;
                        $balExpose = $balPlus = [];
                        for($n=0;$n<10;$n++){
                            $profitLoss = $this->getLotteryProfitLoss($eventId,$marketId,$n);
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                        
                    }
                    
                }
            }
            
            // All Expose
            
            if( isset( $maxBal['expose'] ) && $maxBal['expose'] != null && array_sum( $maxBal['expose'] ) < 0 ){
                $expose_balance = (-1)*( array_sum( $maxBal['expose'] ) );
            }
            
            \Yii::$app->db->createCommand()
            ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
            ->execute();
            
            if( $user_balance >= $expose_balance ){
                $user_balance = $user_balance-$expose_balance;
            }else{
                $user_balance = 0;
            }
            
            return [ "balance" => round($user_balance) , "expose" => round($expose_balance) , "mywallet" => round($mywallet) ];
        }
        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        
    }
    
    public function getBalanceVal_Old($uid)
    {
        $user = User::find()->select(['balance','expose_balance'])->where(['id' => $uid ])->one();
        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balExpose = [];
        if( $user != null ){
            $mywallet = $user->balance;
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = 0;
            
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            if( $marketList != null ){
                
                foreach ( $marketList as $market ){
                    
                    $marketId = $market['market_id'];
                    //Match Odd PL
                    $event = EventsPlayList::findOne(['market_id'=>$marketId]);
                    if( $event != null ){
                        $eventId = $event->event_id;
                        $runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);
                        if( $runnersData != null ){
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->selection_id,'match_odd');
                                
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                            }
                        }
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                    }
                    
                    //Match Odd 2
                    $manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId]);
                    if( $manualMatchOdd != null ){
                        $eventId = $manualMatchOdd->event_id;
                        $runnersData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);
                        if( $runnersData != null ){
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchUmMatchOdds($marketId,$eventId,$runners->sec_id,'match_odd2');
                                
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                            }
                        }
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                    }
                    
                    // Fancy and Fancy 2
                    
                    //$eventId = $manualFancy->event_id;
                    $profitLossData = $this->getProfitLossFancyOnZero($marketId);
                    
                    if( $profitLossData != null ){
                        foreach ( $profitLossData as $profitLoss ){
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                    }
                    
                    //Lottery
                    $lottery = ManualSessionLottery::findOne(['market_id'=>$marketId]);
                    if( $lottery != null ){
                        
                        $eventId = $lottery->event_id;
                        
                        for($n=0;$n<10;$n++){
                            $profitLoss = $this->getLotteryProfitLoss($eventId,$marketId,$n);
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                        
                    }
                    
                    // Fancy Manual Session
                    /*$manualFancy = ManualSession::findOne(['market_id'=>$marketId]);
                     if( $manualFancy != null ){
                     $eventId = $manualFancy->event_id;
                     $profitLossData = $this->getProfitLossFancyOnZero($eventId,$marketId,'fancy');
                     foreach ( $profitLossData as $profitLoss ){
                     if( $profitLoss < 0 ){
                     $balExpose[] = $profitLoss;
                     }else{
                     $balPlus[] = $profitLoss;
                     }
                     
                     }
                     
                     if( $balExpose != null ){
                     $maxBal['expose'][] = min($balExpose);
                     }
                     if( $balPlus != null ){
                     $maxBal['plus'][] = max($balPlus);
                     }
                     }*/
                    
                    // Fancy 2 API
                    /*$fancy2 = MarketType::findOne(['market_id'=>$marketId]);
                     if( $fancy2 != null ){
                     $eventId = $fancy2->event_id;
                     $profitLossData = $this->getProfitLossFancyOnZero($eventId,$marketId,'fancy2');
                     foreach ( $profitLossData as $profitLoss ){
                     if( $profitLoss < 0 ){
                     $balExpose[] = $profitLoss;
                     }else{
                     $balPlus[] = $profitLoss;
                     }
                     
                     }
                     
                     if( $balExpose != null ){
                     $maxBal['expose'][] = min($balExpose);
                     }
                     if( $balPlus != null ){
                     $maxBal['plus'][] = max($balPlus);
                     }
                     }*/
                }
            }
            
            /*$marketUnMatchList = PlaceBet::find()->select(['market_id'])
             ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 0 ] )
             ->groupBy(['market_id'])->asArray()->all();
             
             if( $marketUnMatchList != null ){
             
             foreach ( $marketUnMatchList as $market ){
             
             $marketId = $market['market_id'];
             
             $where = [ 'match_unmatch' => 0,'market_id' => $marketId , 'user_id' => $uid, 'status' => 1 , 'bet_status' => 'Pending', 'bet_type' => ['back','lay'] ];
             $unMatchExpose = PlaceBet::find()->select(['SUM(loss) as val'])
             ->where($where)->asArray()->all();
             
             if( $unMatchExpose == null || !isset($unMatchExpose[0]['val']) || $unMatchExpose[0]['val'] == '' ){
             $unMatchExposeVal = 0;
             }else{ $unMatchExposeVal = $unMatchExpose[0]['val']; }
             
             $maxBal['expose'][] = (-1)*$unMatchExposeVal;
             
             }
             }*/
            
            if( isset( $maxBal['expose'] ) && $maxBal['expose'] != null && array_sum( $maxBal['expose'] ) < 0 ){
                $expose_balance = (-1)*( array_sum( $maxBal['expose'] ) );
            }
            
            //$user->expose_balance = $expose_balance;
            
            /*if( $user->save(['expose_balance']) ){
             echo 'asd';
             }else{
             echo '<pre>';print_r($user->getErrors());die;
             }*/
            
            \Yii::$app->db->createCommand()
            ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
            ->execute();
            
            if( $user_balance >= $expose_balance ){
                $user_balance = $user_balance-$expose_balance;
            }else{
                $user_balance = 0;
            }
            
            /*if( $expose_balance >= $mywallet ){
             $expose_balance = $mywallet;
             }*/
            
            return [ "balance" => $user_balance , "expose" => $expose_balance , "mywallet" => $mywallet ];
        }
        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        
    }
    
    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLoss($eventId,$marketId ,$selectionId)
    {
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
        /*if( $total != 0 ){
            return $total;
        }else{
            return '';
        }*/
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
    
    public function getProfitLossMatchOddsNewAll($marketId,$eventId,$selId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        //$sessionType = ['match_odd','match_odd2'];
        
        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){
            
            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'sec_id' => $selId,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $totalWin = $backWin + $layWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'market_id' => $marketId ,'session_type' => $sessionType ,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
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
        
        return $total;
        
    }
    
    // Cricket: get ProfitLoss UmMatch Odds
    public function getProfitLossUmMatchOdds($marketId,$eventId,$selId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){
            
            $where = [ 'match_unmatch'=>0, 'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'match_unmatch'=>0, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'match_unmatch'=>0, 'sec_id' => $selId,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $totalWin = $backWin + $layWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'match_unmatch'=>0, 'market_id' => $marketId ,'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'match_unmatch'=>0, 'market_id' => $marketId ,'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            //$where = [ 'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $totalLoss = $backLoss + $layLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        return $total;
        /*if( $total != 0 ){
         return $total;
         }else{
         return '';
         }*/
        
    }
    
    // Cricket: get ProfitLoss Fancy
    public function getProfitLossFancyOnZero($marketId)
    {
        $userId = \Yii::$app->user->id;
        $dataReturn = [];
        //$total = $totalLoss = $totalWin = 0;
        //if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
        $priceVal = 10;
        $where = [ 'session_type' => ['fancy','fancy2'],'user_id' => $userId,'market_id' => $marketId ];
        
        $betList = PlaceBet::find()
        ->select(['bet_type','price','win','loss'])
        ->where( $where )->asArray()->all();
        
        $betMax = PlaceBet::find()
        ->select(['price'])
        ->where( $where )->orderBy(['price'=>SORT_DESC])->asArray()->one();
        if( $betMax != null ){
            $priceVal = $betMax['price'];
        }
        
        if( $betList != null ){
            
            $priceStart = $priceVal-10;
            
            if( $priceStart < 0 ){
                $priceStart = 0;
            }
            
            $priceEnd = $priceVal+10;
            
            foreach ( $betList as $bet ){
                
                $type = $bet['bet_type'];
                $price = $bet['price'];
                $win = $bet['win'];
                $loss = $bet['loss'];
                
                for($i=$priceStart;$i<=$priceEnd;$i++){
                    if( $type == 'no' && $i < $price ){
                        $data[$i][] = $win;
                    }else if( $type == 'yes' && $i >= $price ){
                        $data[$i][] = $win;
                    }else{
                        $data[$i][] = (-1)*$loss;
                    }
                    
                }
                
            }
            
            for($i=$priceStart;$i<=$priceEnd;$i++){
                $dataReturn[] = array_sum($data[$i]);
            }
            
        }
        
        //}
        
        return $dataReturn;
    }
    
    // actionGetUserDetails
    public function actionGetUserDetailsOLD()
    {
        $uid = \Yii::$app->user->id;
        $select = ['parent_id' , 'name' , 'username' , 'balance' , 'role' , 'created_at'];
        $user = User::find()->select($select)->where(['id' => \Yii::$app->user->id ])->one();
        $userArr = [];
        if( $user != null ){
            
            $data = $this->getBalanceVal($uid);
            
            $userArr = [
                'name' => $user->name,
                'username' => $user->name,
                'parent' => $this->getMasterName($user->parent_id),
                'balance' => $data['balance'],
                'expose_balance' => $data['expose'],
                'join_on' => $user->created_at
            ];
            
            //return [ "status" => 1 , "data" => [ "user" => $userArr ] ];
        }
        return [ "status" => 1 , "data" => [ "user" => $userArr ] ];
        
    }
    
    public function actionGetUserDetails()
    {
        $uid = \Yii::$app->user->id;
        $select = ['parent_id' , 'name' , 'username' , 'balance' , 'role' , 'created_at'];
        $user = User::find()->select($select)->where(['id' => \Yii::$app->user->id ])->one();
        $userArr = [];
        if( $user != null ){
            
            $data = $this->getBalanceVal($uid);
            
            $userArr = [
                'name' => $user->name,
                'username' => $user->name,
                'parent' => $this->getMasterName($user->parent_id),
                'balance' => $data['balance'],
                'expose_balance' => $data['expose'],
                'mywallet' => $data['mywallet'],
                'join_on' => $user->created_at
            ];
            
            //return [ "status" => 1 , "data" => [ "user" => $userArr ] ];
        }
        return [ "status" => 1 , "data" => [ "user" => $userArr ] ];
        
    }
    
    // getMasterName
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
        
        $query = PlaceBet::find()
            ->select( [ 'id' , 'user_id' , 'sec_id' , 'market_id' , 'price' , 'size', 'status' ] )
            ->andWhere( [ 'status' => [1,2] , 'user_id' => \Yii::$app->user->id ] );
        
        if( $filters != null ){
            
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

    /*
     * Bet History All List
     */
    
    public function actionIndex()
    {
        $query = PlaceBet::find()
            ->select( [ 'id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' , 'status' , 'created_at' ] )
            ->andWhere( [ 'status' => 1 , 'user_id' => \Yii::$app->user->id  ] );
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        if( $models != null ){
            $response = [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        }else{
            $response = [ "status" => 1 , "data" => [ "items" => [] , "count" => 0 ] ];
        }
        return $response;
        
    }
    
    /*
     * My Bet Current Bet AND Old Bet History
     */
    
    public function actionMyBet()
    {
        $currentBetDataArr = $oldBetDataArr = [
            "data" => [],
            "count" => 0
        ];
        
        $currentBet = PlaceBet::find()
        ->select( [ 'id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' , 'created_at' ] )
        ->andWhere( [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => \Yii::$app->user->id  ] );
        
        $countQuery = clone $currentBet; $count =  $countQuery->count();
        
        $currentBetData = $currentBet->orderBy( [ "created_at" => SORT_DESC ] )->asArray()->all();
        
        if( $currentBetData != null ){
            $currentBetDataArr = [
                "data" => $currentBetData,
                "count" => $count
            ];
        }
        
        $oldBet = PlaceBet::find()
        ->select( [ 'id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' , 'created_at' ] )
        ->andWhere( [ 'status' => 1 , 'bet_status' => ['Win','Loss'] , 'user_id' => \Yii::$app->user->id  ] );
        
        $countQuery = clone $oldBet; $count =  $countQuery->count();
        
        $oldBetData = $oldBet->orderBy( [ "created_at" => SORT_DESC ] )->asArray()->all();
        
        if( $oldBetData != null ){
            $oldBetDataArr = [
                "data" => $oldBetData,
                "count" => $count
            ];
        }
        
        $items = [
            'current_bet'=>$currentBetDataArr,
            'old_bet'=>$oldBetDataArr
        ];
        
        $response = [ "status" => 1 , "data" => [ "items" => $items ] ];
        
        return $response;
        
    }
    
    /*
     * Current Bet
     */
    
    public function actionCurrentBet()
    {
        $matchedDataArr = $unMatchedDataArr = [
            "data" => [],
            "count" => 0
        ];
        
        //$response = [ "status" => 1 , "data" => []];
        
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            
            $pagination = $filter_args;
        }
        
        $query = PlaceBet::find()
        ->select( [ 'id','runner' , 'bet_type' , 'price' , 'size' , 'rate' , 'session_type' , 'match_unmatch' , 'description' ] )
        ->andWhere( [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => \Yii::$app->user->id  ] );
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $query->andWhere([ 'event_id' => \Yii::$app->request->get( 'id' ) ]);
        }
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "created_at" => SORT_DESC ] )->asArray()->all();
        $matchData = $unMatchData = [];
        if( $models != null ){
            
                $i = 0;
                foreach ( $models as $betData ){
                    $betData['profit'] = $betData['size'];
                    if( $betData['session_type'] == 'fancy' || $betData['session_type'] == 'fancy2' ){
                        if( $betData['rate'] != 0 ){
                            $betData['profit'] = ( $betData['size']*$betData['rate'] ) / 100;
                        }else{
                            $betData['profit'] = 0;
                        }
                    }else if( $betData['session_type'] == 'lottery' ){
                        
                        if( $betData['rate'] != 0 ){
                            $betData['profit'] = ( $betData['size']*($betData['rate']-1) );
                        }else{
                            $betData['profit'] = 0;
                        }
                        
                    }else{
                        if( $betData['bet_type'] != 'back' ){
                            if( $betData['price'] > 0 ){
                                $betData['profit'] = round( ( ($betData['size']*$betData['price'])-$betData['size'] ) , 2 );
                            }
                        }else{
                            if( $betData['price'] > 0 ){
                                $betData['profit'] = round( ( ($betData['size']*$betData['price'])-$betData['size'] ) , 2 );
                            }
                        }
                    }
                    
                    if( $betData['match_unmatch'] == 1  ){
                        $matchData[] = $betData;
                    }else{
                        $unMatchData[] = $betData;
                    }
                    $i++;
                }
                
                $matchDataNew = $unMatchDataNew = [
                    [ 'title' => 'Match Odd','dataList' => [] ],
                    [ 'title' => 'Match Odd 2','dataList' => [] ],
                    [ 'title' => 'Fancy','dataList' => [] ],
                    [ 'title' => 'Fancy 2','dataList' => [] ],
                    [ 'title' => 'Lottery','dataList' => [] ]
                ];
                
                if( $matchData != null ){
                    $matchOddData = $matchOddData2 = $fancyData = $fancyData2 = $lotteryData = [];
                    foreach ( $matchData as $mData ){
                        
                        if( $mData['session_type'] == "match_odd"  ){
                            $matchOddData[] = $mData;
                        }
                        
                        if( $mData['session_type'] == "match_odd2"  ){
                            $matchOddData2[] = $mData;
                        }
                        
                        if( $mData['session_type'] == "fancy"  ){
                            $fancyData[] = $mData;
                        }
                        
                        if( $mData['session_type'] == "fancy2"  ){
                            $fancyData2[] = $mData;
                        }
                        
                        if( $mData['session_type'] == "lottery"  ){
                            $lotteryData[] = $mData;
                        }
                        
                    }
                    
                    /*$matchDataNew = [
                     'match_odd' => $matchOddData,
                     'match_odd2' => $matchOddData2,
                     'fancy' => $fancyData,
                     'fancy2' => $fancyData2,
                     'lottery' => $lotteryData
                     ];*/
                    $matchDataNew = [
                        [ 'title' => 'Match Odd','dataList' => $matchOddData ],
                        [ 'title' => 'Match Odd 2','dataList' => $matchOddData2 ],
                        [ 'title' => 'Fancy','dataList' => $fancyData ],
                        [ 'title' => 'Fancy 2','dataList' => $fancyData2 ],
                        [ 'title' => 'Lottery','dataList' => $lotteryData ]
                    ];
                }
                
                $matchedDataArr = [
                    "dataItems" => $matchDataNew,
                    "count" => count($matchData)
                ];
                
                if( $unMatchData != null ){
                    $unMatchOddData = $unMatchOddData2 = $unMatchFancyData = $unMatchFancyData2 = $unMatchLotteryData = [];
                    foreach ( $unMatchData as $unData ){
                        
                        if( $unData['session_type'] == "match_odd"  ){
                            $unMatchOddData[] = $unData;
                        }
                        
                        if( $unData['session_type'] == "match_odd2"  ){
                            $unMatchOddData2[] = $unData;
                        }
                        
                        if( $unData['session_type'] == "fancy"  ){
                            $unMatchFancyData[] = $unData;
                        }
                        
                        if( $unData['session_type'] == "fancy2"  ){
                            $unMatchFancyData2[] = $unData;
                        }
                        
                        if( $unData['session_type'] == "lottery"  ){
                            $unMatchLotteryData[] = $unData;
                        }
                        
                    }
                    
                    /*$unMatchDataNew = [
                     'match_odd' => $unMatchOddData,
                     'match_odd2' => $unMatchOddData2,
                     'fancy' => $unMatchFancyData,
                     'fancy2' => $unMatchFancyData2,
                     'lottery' => $unMatchLotteryData
                     ];*/
                    $unMatchDataNew = [
                        [ 'title' => 'Match Odd','dataList' => $unMatchOddData ],
                        [ 'title' => 'Match Odd 2','dataList' => $unMatchOddData2 ],
                        [ 'title' => 'Fancy','dataList' => $unMatchFancyData ],
                        [ 'title' => 'Fancy 2','dataList' => $unMatchFancyData2 ],
                        [ 'title' => 'Lottery','dataList' => $unMatchLotteryData ]
                    ];
                }
                
                $unMatchedDataArr = [
                    "dataItems" => $unMatchDataNew,
                    "count" => count($unMatchData)
                ];
            //}
            
            $items = [
                'matched'   =>  $matchedDataArr,
                'unmatched' =>  $unMatchedDataArr
            ];
            
            $response = [ "status" => 1 , "data" => $items ];
            
        }else{
            
            $items = [
                'matched'   =>  $matchedDataArr,
                'unmatched' =>  $unMatchedDataArr
            ];
            
            $response = [ "status" => 1 , "data" => $items];
        }
        
        return $response;
    }
    
    public function actionCurrentBetOLD()
    {
        $matchedDataArr = $unMatchedDataArr = [
            "data" => null,
            "count" => 0
        ];
        
        $response = [ "status" => 1 , "data" => []];
        
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        $query = PlaceBet::find()
        ->select( [ 'id','runner' , 'bet_type' , 'price' , 'size' , 'match_unmatch' ] )
        ->andWhere( [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => \Yii::$app->user->id  ] );
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $query->andWhere([ 'event_id' => \Yii::$app->request->get( 'id' ) ]);
        }
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "created_at" => SORT_DESC ] )->asArray()->all();
        $matchData = $unMatchData = [];
        if( $models != null ){
            foreach ( $models as $betData ){
                if( $betData['match_unmatch'] == 1  ){
                    $matchData[] = $betData;
                }else{
                    $unMatchData[] = $betData;
                }
            }
            
            $matchedDataArr = [
                "data" => $matchData,
                "count" => count($matchData)
            ];
            $unMatchedDataArr = [
                "data" => $unMatchData,
                "count" => count($unMatchData)
            ];
            
            $items = [
                'matched'   =>  $matchedDataArr,
                'unmatched' =>  $unMatchedDataArr
            ];
            
            $response = [ "status" => 1 , "data" => $items ];
        }
        
        return $response;
    }
    
    /*
     * Bet History New
     */
    
    public function actionBetHistory()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = [];
        $uid = \Yii::$app->user->id;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data['startDate'] ) && isset( $r_data['endDate'] )
                && ( $r_data['startDate'] != '' ) && ( $r_data['endDate'] != '' )
                && ( $r_data['startDate'] != null ) && ( $r_data['endDate'] != null ) ){

                if( isset($r_data['format']) && $r_data['format'] == 'timestamp'){
                    $startDate = $r_data['startDate']/1000;
                    $endDate = $r_data['endDate']/1000;
                }else{
                    $startDate = strtotime($r_data['startDate']);
                    $endDate = strtotime($r_data['endDate']  . ' 23:59:59');
                }

            }else{
                $today = date('d-m-Y');
                $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
                $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
                
                $endDate = strtotime($startday);
                $startDate = strtotime($lastday);
            }
            
        }else{

            $today = date('d-m-Y');
            $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
            $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
            
            $endDate = strtotime($startday);
            $startDate = strtotime($lastday);
            
        }

        if( isset( $r_data['type'] ) && $r_data['type'] != '' ){
            $type = $r_data['type'];
            $where = [ 'session_type' => $type , 'user_id' => $uid , 'status' => [0,1] , 'bet_status' => ['Win','Loss'] ];
        }else{
            $where = [ 'user_id' => $uid , 'status' => [0,1] , 'bet_status' => ['Win','Loss'] ];
        }

        $models = (new \yii\db\Query())
        ->select([ 'id','sport_id','client_name' , 'master' , 'runner' ,'market_name', 'bet_type' , 'match_unmatch', 'ip_address' , 'rate','price' , 'size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'description','status' , 'created_at' ])
        ->from('place_bet')
        ->where( $where )
        ->andWhere([ 'between' , 'created_at' , $startDate , $endDate ])
        ->orderBy([ 'created_at' => SORT_DESC ])
        ->all();
        
        //echo '<pre>';print_r($models);die;
        
        $sport = ['1' => 'Football' , '2' => 'Tennis', '4' => 'Cricket' , '999999' => 'Teen Patti', '99999' => 'Teen Patti'];
        
        $session = ['match_odd' => 'Match Odds' , 'fancy' => 'Fancy' , 'match_odd2' => 'Match Odd 2' , 'fancy2' => 'Fancy 2' , 'lottery' => 'Lottery' , 'jackpot' => 'Jackpot' , 'teenpatti' => 'Teen Patti','poker' => 'Poker','andarbahar' => 'Andar Bahar',];
        
        if( $models != null ){
            $i=0;
            foreach ( $models as $data ){
                
                $models[$i]['sport'] = $sport[$data['sport_id']];
                $models[$i]['session'] = $session[$data['session_type']];
                //$models[$i]['data'] = date('d-m-Y',$data['created_at']);
                
                $i++;
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $models , "count" => COUNT($models) , "date" => ["start" => $startDate, "end" => $endDate ] ] ];
        }else{
            $response = [ "status" => 1 , "data" => [ "items" => [] , 'count' => 0 ] ];
        }
        
        return $response;
    }
    
    /*
     * Transaction History
     */
    public function actionTransaction()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $uId = \Yii::$app->user->id;
        $transArr = [];
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data['startDate'] ) && isset( $r_data['endDate'] )
                && ( $r_data['startDate'] != '' ) && ( $r_data['endDate'] != '' )
                && ( $r_data['startDate'] != null ) && ( $r_data['endDate'] != null ) ){

                if( isset($r_data['format']) && $r_data['format'] == 'timestamp'){
                    $startDate = $r_data['startDate']/1000;
                    $endDate = $r_data['endDate']/1000;
                }else{
                    $startDate = strtotime($r_data['startDate']);
                    $endDate = strtotime($r_data['endDate']  . ' 23:59:59');
                }

            }else{

                $today = date('d-m-Y');
                $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
                $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );

                $endDate = strtotime($startday);
                $startDate = strtotime($lastday);

            }


            
        }else{
            
            $today = date('d-m-Y');
            $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
            $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
            
            $endDate = strtotime($startday);
            $startDate = strtotime($lastday);
            
        }

        $marketArr = [];

        if( isset( $r_data['type'] ) && $r_data['type'] != '' && $r_data['type'] != 'jackpot' ){

            $type = $r_data['type'];
            $where1 = [ 'session_type' => $type , 'user_id' => $uId , 'status' => 1 , 'bet_status' => ['Win','Loss'] ];

            $marketArr = (new \yii\db\Query())
                ->select(['market_id'])
                ->from('place_bet')
                ->where($where1)
                ->andWhere(['!=', 'event_id', 0])
                ->orderBy(['id' => SORT_DESC])
                ->groupBy(['market_id'])
                ->all();

            if( $marketArr != null ){
                $where = ['user_id'=>$uId , 'status' => 1 , 'market_id' => $marketArr ];
            }else{
                $where = ['user_id'=>$uId , 'status' => 1 ];
            }

        }elseif( isset( $r_data['type'] ) && $r_data['type'] != '' && $r_data['type'] == 'jackpot' ){

            $type = $r_data['type'];
            $where1 = [ 'session_type' => 'jackpot' , 'user_id' => $uId , 'status' => 1 , 'bet_status' => ['Win','Loss'] ];

            $marketArr = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('place_bet')
                ->where($where1)
                ->andWhere(['!=', 'event_id', 0])
                ->orderBy(['id' => SORT_DESC])
                ->groupBy(['event_id'])
                ->all();

            if( $marketArr != null ){
                $where = ['user_id' => $uId , 'status' => 1 , 'event_id' => $marketArr ];
            }else{
                $where = ['user_id' => $uId , 'status' => 1 ];
            }

        }else{
            $where = ['user_id'=>$uId , 'status' => 1];
        }

        $transDataArr = (new \yii\db\Query())
        ->select(['created_at','transaction_amount','transaction_type','current_balance','description','is_cash','is_commission'])
        ->from('transaction_history')
        ->where( $where )
        ->andWhere([ 'between' , 'created_at' , $startDate , $endDate ])
        ->orderBy([ 'id' => SORT_ASC ])
        ->all();
            
        if( $transDataArr != null ){

            $currentBlnc = 0;

            $user = User::find()->select(['balance','profit_loss_balance','expose_balance'])->where([ 'id' => $uId ])->asArray()->one();

            if( $user != null ){
                $currentBlnc = $user['balance']+$user['profit_loss_balance'];
            }

            foreach ( $transDataArr as $trans ){
                if( $trans['transaction_amount'] != 0 ){

                    if( $trans['is_cash'] != 1 ){
                        if( $trans['transaction_type'] == 'CREDIT' ){
                            $currentBlnc += $trans['transaction_amount'];
                        }else{
                            $currentBlnc -= $trans['transaction_amount'];
                        }
                    }

                    $description = $trans['description'];

                    if( $trans['is_commission'] != 0 ){
                        $description = $trans['description'].' ( Comm. )';
                    }

                    $transArr[] = [
                        'created_at' => $trans['created_at'],
                        'transaction_amount' => round($trans['transaction_amount'],2),
                        'transaction_type' => $trans['transaction_type'],
                        'current_balance' => $trans['current_balance'],//round($currentBlnc,2),
                        'description' => $description,
                    ];
                }
            }
        }

        $response = [ "status" => 1 , "data" => [ "items" =>  array_reverse($transArr), "count" => COUNT($transArr) , "date" => ["start" => $startDate, "end" => $endDate ] ] ];
        
        return $response;
    }
    
    public function lastTineTrans($marketId,$uId)
    {
        $trans = (new \yii\db\Query())
        ->select(['created_at'])
        ->from('temp_transaction_history')
        ->where(['user_id'=>$uId,'market_id'=>$marketId,'status'=>1])
        ->orderBy(['created_at' => SORT_DESC ])->one();
        
        if( $trans != null ){
            return $trans['created_at'];
        }
        
    }
    
    public function totalAmount($marketId,$uId)
    {
        $amout = [];$totalAmount = 0;
        $transArr = (new \yii\db\Query())
        ->select(['transaction_amount','transaction_type'])
        ->from('temp_transaction_history')
        ->where(['user_id'=>$uId,'market_id'=>$marketId,'status'=>1])
        ->all();
        
        if( $transArr != null ){
            foreach ( $transArr as $trans ){
                if( $trans['transaction_type'] == 'CREDIT' ){
                    $amout[] = $trans['transaction_amount'];
                }else{
                    $amout[] = (-1)*$trans['transaction_amount'];
                }
            }
        }
        
        if( $amout != null ){
            $totalAmount = array_sum($amout);
        }
        
        return $totalAmount;
        
    }
    
    public function lastBalanceUpdate($marketId,$uId)
    {
        $trans = (new \yii\db\Query())
        ->select(['current_balance'])
        ->from('temp_transaction_history')
        ->where(['user_id'=>$uId,'market_id'=>$marketId,'status'=>1])
        ->orderBy(['id' => SORT_ASC ])->one();
        
        if( $trans != null ){
            return $trans['current_balance'];
        }
    }
    
    
    /*
     * Profit Loss History
     */
    
    public function actionProfitLossHistory()
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
        $eventId = 0;
        //$response = [ "status" => 1 , "data" => []];
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );
        }
        
        $query = PlaceBet::find()
        ->select( [ 'id','runner' , 'description', 'bet_type' , 'price' , 'size' , 'bet_status','win','loss' ] )
        ->andWhere( [ 'event_id'=>$eventId, 'status' => 1 , 'bet_status' => ['Win','Loss'] , 'user_id' => \Yii::$app->user->id  ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "description" , $filters[ "title" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "created_at" => SORT_DESC ] )->asArray()->all();
        
        if( $models != null ){
            $response = [ "status" => 1 , "data" => [ "items" => $models , 'count' => $count ] ];
        }else{
            $response = [ "status" => 1 , "data" => [ "items" => [] , 'count' => 0 ] ];
        }
        
        return $response;
    }
    
    /*
     * Profit Loss History
     */
    
    public function actionMarketResult()
    {
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $userId = \Yii::$app->user->id;
        $items = [];
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data['startDate'] ) && isset( $r_data['endDate'] )
                && ( $r_data['startDate'] != '' ) && ( $r_data['endDate'] != '' )
                && ( $r_data['startDate'] != null ) && ( $r_data['endDate'] != null ) ){
                    if( isset($r_data['format']) && $r_data['format'] == 'timestamp'){
                        $startDate = $r_data['startDate']/1000;
                        $endDate = $r_data['endDate']/1000;
                    }else{
                        $startDate = strtotime($r_data['startDate']);
                        $endDate = strtotime($r_data['endDate']  . ' 23:59:59');
                    }
            }else{
                
                $today = date('d-m-Y');
                $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
                $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
                
                $endDate = strtotime($startday);
                $startDate = strtotime($lastday);
                
            }
            
        }else{
            
            $today = date('d-m-Y');
            $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
            $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
            
            $endDate = strtotime($startday);
            $startDate = strtotime($lastday);
            
        }

        if( isset( $r_data['type'] ) && $r_data['type'] != '' && $r_data['type'] != 'jackpot' ){
            $type = $r_data['type'];
            $where = [ 'session_type' => $type , 'user_id' => $userId , 'status' => 1 , 'bet_status' => ['Win','Loss'] ];
        }else{
            $where = ['user_id' => $userId , 'bet_status' => ['Win','Loss'] , 'status' => 1 ];
        }

        $marketList = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where( $where )
            ->groupBy(['market_id'])->all();

        if( $marketList != null ){
            $marketJackpot = [];
            if( isset( $r_data['type'] ) && ( $r_data['type'] == '' || $r_data['type'] == 'jackpot' ) ) {
                $marketJackpot = (new \yii\db\Query())
                    ->select(['event_id'])->from('place_bet')
                    ->where(['user_id' => $userId, 'session_type' => 'jackpot', 'bet_status' => ['Win', 'Loss'], 'status' => 1])
                    ->groupBy(['event_id'])->all();
            }
            if( $marketJackpot != null ){
                $eventData = (new \yii\db\Query())
                    ->select('*')
                    ->from('market_result')
                    ->where(['market_id' => $marketList ])
                    ->orWhere(['event_id' => $marketJackpot ])
                    ->andWhere(['between','updated_at',$startDate,$endDate])
                    ->orderBy(['updated_at' => SORT_DESC ])
                    ->all();
            }else{
                $eventData = (new \yii\db\Query())
                    ->select('*')
                    ->from('market_result')
                    ->where(['market_id' => $marketList ])
                    ->andWhere(['between','updated_at',$startDate,$endDate])
                    ->orderBy(['updated_at' => SORT_DESC ])
                    ->all();
            }

            
            if( $eventData != null ){
                
                foreach ( $eventData as $event ){

                    $eventName = $event['event_name'];

                    if( $event['session_type'] == 'jackpot' ){
                        $marketName = 'Jackpot';
                    }else if( $event['session_type'] == 'fancy' ){
                        $marketName = 'Fancy : '.$event['market_name'];
                    }else if( $event['session_type'] == 'fancy2' ){
                        $marketName = 'Fancy 2 : '.$event['market_name'];
                    }else if( $event['session_type'] == 'andarbahar' ){
                        $marketName = $event['market_name'];
                    }else if( $event['session_type'] == 'teenpatti' ){
                        $marketName = $event['market_name'];
                    }else if( $event['session_type'] == 'poker' ){
                        $marketName = $event['market_name'];
                    }else{
                        $marketName = $event['market_name'];
                    }

                    $items[] = [
                        'market_name' => $marketName,
                        'event_name' => $eventName,
                        'time' => $event['updated_at']*1000,
                        'result' => $event['result'],
                    ];
                    
                }
                
            }
            
        }

        return [ "status" => 1 , "data" => [ "items" => $items , "date" => ["start" => $startDate, "end" => $endDate ] ] ];
    }
    
    /*
     * Profit Loss History
     */
    
    public function actionProfitLoss()
    {
        $response = [ "status" => 1 , "data" => [ "items" => null , "count" => '0' ] ];
        
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $uid = \Yii::$app->user->id;
        $items = [];
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data['startDate'] ) && isset( $r_data['endDate'] )
                && ( $r_data['startDate'] != '' ) && ( $r_data['endDate'] != '' )
                && ( $r_data['startDate'] != null ) && ( $r_data['endDate'] != null ) ){

                if( isset($r_data['format']) && $r_data['format'] == 'timestamp'){
                    $startDate = $r_data['startDate']/1000;
                    $endDate = $r_data['endDate']/1000;
                }else{
                    $startDate = strtotime($r_data['startDate']);
                    $endDate = strtotime($r_data['endDate']  . ' 23:59:59');
                }

            }else{
                
                $today = date('d-m-Y');
                $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
                $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
                
                $endDate = strtotime($startday);
                $startDate = strtotime($lastday);
                
            }
            
        }else{
            
            $today = date('d-m-Y');
            $startday = date('d-m-Y' , strtotime($today . ' +1 day') );
            $lastday = date('d-m-Y' , strtotime($today . ' -5 day') );
            
            $endDate = strtotime($startday);
            $startDate = strtotime($lastday);
            
        }

        if( isset( $r_data['type'] ) && $r_data['type'] != '' && $r_data['type'] != 'jackpot' ){
            $type = $r_data['type'];
            $where = [ 'session_type' => $type , 'user_id' => $uid , 'status' => 1 , 'bet_status' => ['Win','Loss'] ];
        }else{
            $where = ['user_id' => $uid , 'bet_status' => ['Win','Loss'] , 'status' => 1 ];
        }


        $sportArr = ['1' => 'Football' , '2'=> 'Tennis', '4' => 'Cricket' , '7' => 'Horse Racing' , '999999' => 'Teen Patti' , '99999' => 'Teen Patti'];
        $marketTypeArr = ['match_odd' => 'Match Odd' , 'match_odd2'=> 'Book Maker', 'fancy' => 'Fancy' , 'fancy2' => 'Fancy 2' , 'lottery' => 'Lottery' , 'jackpot' => 'Jackpot' , 'teenpatti' => 'Teen Patti' , 'poker' => 'Poker' , 'andarbahar' => 'Andar Bahar'];
        
        $marketList = (new \yii\db\Query())
        ->select(['market_id'])->from('place_bet')
        ->where( $where )
        ->groupBy(['market_id'])->all();
        
        if( $marketList != null ){
            $marketJackpot = [];
            if( isset( $r_data['type'] ) && ( $r_data['type'] == '' || $r_data['type'] == 'jackpot' ) ) {
                $marketJackpot = (new \yii\db\Query())
                    ->select('event_id')->from('place_bet')
                    ->where(['user_id' => $uid, 'session_type' => 'jackpot', 'bet_status' => ['Win', 'Loss'], 'status' => 1])
                    ->groupBy(['event_id'])->all();
            }
            if ($marketJackpot != null) {
                $eventData = (new \yii\db\Query())
                    ->select('*')
                    ->from('market_result')
                    ->where(['market_id' => $marketList])
                    ->orWhere(['event_id' => $marketJackpot])
                    ->andWhere(['between', 'updated_at', $startDate, $endDate])
                    ->orderBy(['id' => SORT_DESC])
                    ->all();
            } else {
                $eventData = (new \yii\db\Query())
                    ->select('*')
                    ->from('market_result')
                    ->where(['market_id' => $marketList])
                    ->andWhere(['between', 'updated_at', $startDate, $endDate])
                    ->orderBy(['id' => SORT_DESC])
                    ->all();
            }


            
            $items = [];
            if( $eventData != null ){
                
                foreach ( $eventData as $event ){

                    $eventId = $event['event_id'];
                    $marketId = $event['market_id'];
                    
                    $sport = $sportArr[$event['sport_id']];
                    
                    $commission = $this->getCommissionVal($uid,$eventId,$marketId);
                    $profitLoss = $this->getProfitLossVal($uid,$eventId,$marketId);
                    $startTime = $this->getEventStartTime($eventId);
                    
                    if( $startTime == 'undefined' ){
                        $startTime = $event['updated_at'];
                    }

                    $eventName = $event['event_name'];
                    $marketType = isset( $marketTypeArr[ trim($event['session_type']) ] ) ? $marketTypeArr[ trim($event['session_type']) ] : '';

                    if( $event['session_type'] == 'jackpot' ){
                        $marketName = 'Jackpot';
                    }else if( $event['session_type'] == 'teenpatti' || $event['session_type'] == 'poker' || $event['session_type'] == 'andarbahar' ){

                        $roundId = $this->getRoundId($marketId);

                        $marketName = 'Teen Patti';
                        $sport = $marketType;
                        $eventName = 'Round #'.$roundId;
                        $marketType = '';
                    }else{
                        $marketName = $event['market_name'];
                        $marketNameArr = explode('(', $event['market_name']);

                        if( is_array( $marketNameArr ) && count( $marketNameArr ) > 0 ){
                            $marketName = $marketNameArr[0];
                        }
                    }

                    $items[] = [
                        'sport' => $sport,
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'event_name' => $eventName,
                        'market_name' => $marketName,
                        'market_type' => $marketType,
                        'profitLoss' => round($profitLoss,2),
                        'commission' => round($commission,2),
                        'winner' => $event['result'],
                        'start_time' => $startTime,
                        'settled_time' => $event['updated_at']*1000,
                        'betList' => $this->getBetList($uid,$eventId,$marketId,$event['session_type']),
                    ];
                    
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $items , "count" => count($items) , "date" => ["start" => $startDate, "end" => $endDate ] ] ];
                
            }
            
        }
        
        return $response;
        
    }

    //getCommissionVal
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

    //getBetList
    public function getBetList($userId,$eventId,$marketId,$sessionType)
    {

        if( $sessionType != 'jackpot' ){

            $betList = (new \yii\db\Query())
                ->select(['bet_type','runner','bet_type','price','size','rate','win','loss','bet_status','updated_at'])
                ->from('place_bet')
                ->where(['bet_status'=>['Win','Loss'],'user_id' => $userId,'event_id' => $eventId,'market_id' => $marketId,'session_type' => $sessionType ])
                ->all();

            if( $betList != null ){
                $i = 0;
                foreach ( $betList as $bets ){

                    $marketName = $bets['runner'];
                    $marketNameArr = explode('(', $bets['runner']);

                    if( is_array( $marketNameArr ) && count( $marketNameArr ) > 0 ){
                        $marketName = $marketNameArr[0];
                    }

                    if( $sessionType != 'match_odd' || $sessionType != 'match_odd2' ){
                        $betList[$i]['runner'] = $marketName;
                    }else{
                        $betList[$i]['runner'] = $marketName;
                    }
                    $i++;}

                return ['list' => $betList , 'count' => count($betList)];
            }else{
                return ['list' => null , 'count' => 0];
            }

        }else{

            $betList = (new \yii\db\Query())
                ->select(['bet_type','runner','bet_type','price','size','rate','win','loss','bet_status','updated_at'])
                ->from('place_bet')
                ->where(['bet_status'=>['Win','Loss'],'user_id' => $userId,'event_id' => $eventId,'session_type' => $sessionType ])
                ->all();

            if( $betList != null ){
                $i = 0;
                foreach ( $betList as $bets ){

                    $marketName = $bets['runner'];

//                    $marketNameArr = explode('(', $bets['runner']);
//
//                    if( is_array( $marketNameArr ) && count( $marketNameArr ) > 0 ){
//                        $marketName = $marketNameArr[0];
//                    }
                    $betList[$i]['runner'] = $marketName;

                    $i++;}

                return ['list' => $betList , 'count' => count($betList)];
            }else{
                return ['list' => null , 'count' => 0];
            }

        }


        
    }
    
    //getCommissionVal
    public function getCommissionVal($userId,$eventId,$marketId)
    {
        $totalComm = (new \yii\db\Query())
        ->select('SUM(transaction_amount) as comm')
        ->from('transaction_history')
        ->where(['is_commission' => 1,'transaction_type' => 'DEBIT','user_id' => $userId,'event_id' => $eventId , 'market_id' => $marketId ])
        ->one();
        
        if( isset( $totalComm['comm'] ) ){
            return $totalComm['comm'];
        }else{
            return '0';
        }
        
    }
    
    //getProfitLossVal
    public function getProfitLossVal($userId,$eventId,$marketId)
    {
        $totalWin = (new \yii\db\Query())
        ->select('SUM(transaction_amount) as win')
        ->from('transaction_history')
        ->where(['status' => 1,'transaction_type' => 'CREDIT','user_id' => $userId,'event_id' => $eventId , 'market_id' => $marketId ])
        ->one();
        
        if( isset( $totalWin['win'] ) ){
            $win = $totalWin['win'];
        }else{ $win = 0; }
        
        $totalLoss = (new \yii\db\Query())
        ->select('SUM(transaction_amount) as loss')
        ->from('transaction_history')
        ->where(['status' => 1,'transaction_type' => 'DEBIT','user_id' => $userId,'event_id' => $eventId , 'market_id' => $marketId ])
        ->one();
        
        if( isset( $totalLoss['loss'] ) ){
            $loss = $totalLoss['loss'];
        }else{ $loss = 0; }
        
        $total = $win-$loss;
        return $total;
    }
    
    //getCommissionVal
    public function getEventStartTime($eventId)
    {
        $time = 'undefined';
        $event = (new \yii\db\Query())
        ->select(['event_time'])
        ->from('events_play_list')
        ->where(['event_id' => $eventId ])
        ->one();
        
        if( $event != null ){
            $time = $event['event_time'];
        }
        return $time;
    }
    
    /*
     * Profit Loss History
     */
    
    public function actionMarketResultOLD()
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
        
        /*$query = TransactionHistory::find()
         ->select( [ 'id' , 'user_id' , 'bet_id' , 'client_name' , 'transaction_type' , 'transaction_amount' , 'current_balance' , 'description','status' , 'created_at' ] )
         ->where( [ 'status' => [1,2] ] )->andWhere( [ 'user_id' => \Yii::$app->user->id ] );
         
         if( $filters != null ){
         if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
         $query->andFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
         $query->orFilterWhere( [ "like" , "transaction_type" , $filters[ "title" ] ] );
         }
         }*/
        
        //$countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            //$query->offset( $offset )->limit( $limit );
        }
        
        //$models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        
        $models = [
            0 => [
                'market_name' => 'Session 1 to 3 Over BAN',
                'event_name' => 'Bangladesh Vs India(3rd ODI)',
                'time' => '1544796184',
                'result' => '21',
            ],
            1 => [
                'market_name' => 'Session 7 to 10 Over BAN',
                'event_name' => 'Bangladesh Vs India(3rd ODI)',
                'time' => '1544796184',
                'result' => '63',
            ],
            2 => [
                'market_name' => 'Session 50 Over BAN',
                'event_name' => 'Bangladesh Vs India(3rd ODI)',
                'time' => '1544796184',
                'result' => '321',
            ],
            3 => [
                'market_name' => 'Match Odds',
                'event_name' => 'Bangladesh Vs India(3rd ODI)',
                'time' => '1544796184',
                'result' => 'India',
            ],
        ];
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => count($models) ] ];
    }
    
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $uid = \Yii::$app->user->id;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $betData = PlaceBet::findOne( ['id' => $r_data[ 'id' ] , 'user_id' => $uid , 'match_unmatch' => 0 ] );
                if( $betData != null ){
                    $betData->status = 0;
                    
                    if( $betData->save( [ 'status' ] ) ){

                        $this->newUpdateUserExpose($uid,$betData->market_id,$betData->session_type);
                        //UserProfitLoss::balanceValUpdate($uid);

                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "History deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "history not deleted!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }

    /*public function updateRadisCash($uid,$eventId,$marketId,$betId){
        $cache = Yii::$app->cache;
        $betList = $betListData = [];
        $key = $uid.':PendingBets';
        if( $cache->exists($key) ){
            $betList = $cache->get($key);
            $betListData = json_decode($betList,true);
            if( $betListData != null && isset( $betListData[$eventId] )){
                if( isset( $betListData[$eventId][$marketId] ) ){
                    if( isset( $betListData[$eventId][$marketId][$betId] ) ){
                        unset($betListData[$eventId][$marketId][$betId]);
                    }
                }
            }
        }
        $cache->set( $key , json_encode($betListData) );

    }*/


    public function actionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $model = PlaceBet::findOne( ['id' => $r_data[ 'id' ] , 'user_id' => \Yii::$app->user->id ] );
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
                            "message" => "Bet status not changed!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
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
