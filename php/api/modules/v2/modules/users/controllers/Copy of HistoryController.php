<?php
namespace api\modules\v2\modules\users\controllers;

use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use yii\data\ActiveDataProvider;
use common\models\User;
use common\models\TransactionHistory;
use common\models\Setting;
use common\models\TempTransactionHistory;
use common\models\EventsPlayList;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSession;
use common\models\MarketType;
use common\models\ManualSessionLottery;
use common\models\EventsRunner;
use common\models\Event;
use common\models\ManualSessionMatchOddData;

class HistoryController extends \common\controllers\aController // \yii\rest\Controller
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
    
    /*
    public function actionGetExposeBalance()
    {
        $model = PlaceBet::find()->select(['SUM(loss) as expose'])
            ->where(['user_id' => \Yii::$app->user->id , 'bet_status' => 'Pending' ] )->asArray()->all();
        
        if( $model != null ){
            return [ "status" => 1 , "data" => [ "expose_balance" => $model[0]['expose'] ] ];
        }
        return [ "status" => 1 , "data" => [ "expose_balance" => 0 ] ];
        
    }*/
    
    public function actionGetBalanceOLD()
    {
        /*$request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        if( json_last_error() == JSON_ERROR_NONE ){
            
            //echo \Yii::$app->user->id;die;
            
            $r_data = ArrayHelper::toArray( $request_data );
        
            //$user = User::find()->select(['balance'])->where(['username' => $r_data['username'] ])->one();
            
            $user = User::find()->select(['balance'])->where(['id' => \Yii::$app->user->id ])->one();
            
            if( $user != null ){
                return [ "status" => 1 , "data" => [ "balance" => $user->balance ] ];
            }
            return [ "status" => 1 , "data" => [ "balance" => 0 ] ];
        }
        return [ "status" => 1 , "data" => [ "balance" => 0 ] ];
        */
        $uid = \Yii::$app->user->id;
        $user = User::find()->select(['balance'])->where(['id' => $uid ])->one();
        
        if( $user != null ){
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = 0;
            
            $exposeLoss = PlaceBet::find()->select(['SUM(loss) as exposeLoss'])
            ->where(['bet_type'=>['back','yes'],'user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1 ] )->asArray()->all();
            
            if( $exposeLoss != null && isset( $exposeLoss[0]['exposeLoss'] )){
                $exposeLossVal = $exposeLoss[0]['exposeLoss'];
            }
            
            $exposeWin = PlaceBet::find()->select(['SUM(win) as exposeWin'])
            ->where(['bet_type'=>['lay','no'],'user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1 ] )->asArray()->all();
            
            if( $exposeWin != null && isset( $exposeWin[0]['exposeWin'] )){
                $exposeWinVal = $exposeWin[0]['exposeWin'];
            }
            
            $expose_balance = $exposeWinVal-$exposeLossVal;
            
            if( $expose_balance < 0 ){
                $expose_balance = (-1)*$expose_balance;
            }
            
            if( $user_balance >= $expose_balance ){
                $user_balance = $user_balance-$expose_balance;
            }else{
                $user_balance = 0;
            }
            
            return [ "status" => 1 , "data" => [ "balance" => $user_balance , "expose" => $expose_balance ] ];
        }
        return [ "status" => 1 , "data" => [ "balance" => 0 , "expose" => 0 ] ];
        
    }
    
    public function actionGetBalance()
    {
        $uid = \Yii::$app->user->id;
        $data = $this->getBalanceVal($uid);
        return [ "status" => 1 , "data" => $data ];
        
    }
    
    public function getBalanceVal($uid)
    {
        $user = User::find()->select(['balance','expose_balance'])->where(['id' => $uid ])->one();
        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];
        if( $user != null ){
            $mywallet = $user->balance;
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;
            
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ] )
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
                                $profitLossUnMatch = $this->getLossUnMatchOdds($marketId,$runners->selection_id,'match_odd');
                                //echo $profitLossUnMatch;die;
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus1[] = $profitLoss;
                                }
                                /*if( $profitLossUnMatch < 0 ){
                                    $balExpose2[] = $profitLossUnMatch;
                                }else{
                                    $balPlus[] = $profitLossUnMatch;
                                }*/
                                
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                        }
                        
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                        }
                        
                        /*$balExposeVal = $balExpose2Val = $balPlus1Val = 0; 
                        if( $balPlus1 != null ){
                            $balPlus1Val = max($balPlus1);
                        }
                        
                        if( $balExpose2 != null ){
                            $balExpose2Val = min($balExpose2)+$balPlus1Val;
                        }
                        
                        if( $balExpose != null ){
                            $balExposeVal = min($balExpose);
                        }
                        
                        if( $balExpose2Val < $balExposeVal ){
                            $maxBal['expose'][] = $balExpose2Val;
                        }else{
                            $maxBal['expose'][] = $balExposeVal;
                        }*/
                        
                    }
                    
                    //Match Odd 2
                    $manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId]);
                    if( $manualMatchOdd != null ){
                        $eventId = $manualMatchOdd->event_id;
                        $runnersData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);
                        if( $runnersData != null ){
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->sec_id,'match_odd2');
                                $profitLossUnMatch = $this->getProfitLossUmMatchOdds($marketId,$eventId,$runners->sec_id,'match_odd2');
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus1[] = $profitLoss;
                                }
                                if( $profitLossUnMatch < 0 ){
                                    $balExpose2[] = $profitLossUnMatch;
                                }else{
                                    $balPlus[] = $profitLossUnMatch;
                                }
                            }
                        }
                        
                        $balExposeVal = $balExpose2Val = $balPlus1Val = 0;
                        if( $balPlus1 != null ){
                            $balPlus1Val = max($balPlus1);
                        }
                        
                        if( $balExpose2 != null ){
                            $balExpose2Val = min($balExpose2)+$balPlus1Val;
                        }
                        
                        if( $balExpose != null ){
                            $balExposeVal = min($balExpose);
                        }
                        
                        if( $balExpose2Val < $balExposeVal ){
                            $maxBal['expose'][] = $balExpose2Val;
                        }else{
                            $maxBal['expose'][] = $balExposeVal;
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
                    
                }
            }
            
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
            
            /*if( $expose_balance >= $mywallet ){
             $expose_balance = $mywallet;
             }*/
            
            return [ "balance" => round($user_balance) , "expose" => round($expose_balance) , "mywallet" => round($mywallet) ];
        }
        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        
    }
    
    public function getBalanceVal_old($uid)
    {
        $user = User::find()->select(['balance','expose_balance'])->where(['id' => $uid ])->one();
        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balExpose = $profitLossNew = [];
        if( $user != null ){
            $mywallet = $user->balance;
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;
            
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ] )
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
                                
                                $profitLossNew[] = [
                                    'profitLoss' => $profitLoss,
                                    'secId' => $runners->selection_id
                                ];
                                
                            }
                        }
                        
                        if( $balExpose != null ){
                            $maxBal['expose'][] = min($balExpose);
                            $tempExpose = (-1)*(min($balExpose));
                        }
                        if( $balPlus != null ){
                            $maxBal['plus'][] = max($balPlus);
                            $tempPlus = max($balPlus);
                        }
                        
                        if( $profitLossNew != null ){
                            
                            foreach ( $profitLossNew as $plData ){
                                
                                if( $plData['profitLoss'] > 0 ){
                                    
                                    $profitLossUnmatchLay = $this->getLossUnMatchOdds($marketId,$plData['secId'],'lay');
                                    
                                    if( $profitLossUnmatchLay > 0 ){
                                        $tempExposeUnmatch = $profitLossUnmatchLay;
                                        if( $tempExpose != 0 && $tempPlus != 0 ){
                                            $totalLimit = $tempExpose+$tempPlus;
                                            if( $totalLimit < $tempExposeUnmatch ){
                                                $maxBal['expose'][] = $totalLimit-$tempExposeUnmatch;
                                            }
                                        }
                                    }
                                    
                                }else if( $plData['profitLoss'] < 0 ){
                                    
                                    $profitLossUnmatchBack = $this->getLossUnMatchOdds($marketId,$plData['secId'],'back');
                                    
                                    if( $profitLossUnmatchBack > 0 ){
                                        
                                        $tempExposeUnmatch = $profitLossUnmatchBack;
                                        if( $tempExpose != 0 && $tempPlus != 0 ){
                                            
                                            $totalLimit = $tempExpose;
                                            if( $totalLimit < $tempExposeUnmatch ){
                                                $maxBal['expose'][] = $totalLimit-$tempExposeUnmatch;
                                            }
                                        }
                                    }
                                    
                                }else{
                                    
                                    $profitLossUnmatchBack1 = $this->getLossUnMatchOdds($marketId,$plData['secId'],'back');
                                    $profitLossUnmatchLay1 = $this->getLossUnMatchOdds($marketId,$plData['secId'],'lay');
                                    
                                    if( $profitLossUnmatchBack1 > 0 ){
                                        $maxBal['expose'][] = (-1)*$profitLossUnmatchBack1;
                                    }
                                    if( $profitLossUnmatchLay1 > 0 ){
                                        $maxBal['expose'][] = (-1)*$profitLossUnmatchLay1;
                                    }
                                    
                                }
                                
                            }
                            
                        }else{
                           
                            $profitLossUnmatch = $this->getLossUnMatchOdds($marketId,false,false);
                            
                            if( $profitLossUnmatch > 0 ){
                                $maxBal['expose'][] = (-1)*$profitLossUnmatch;
                            }
                            
                        }
                        
                    }
                
                    //Match Odd 2
                    /*$manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId]);
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
                    }*/
                    
                    // Fancy and Fancy 2
                    
                    //$eventId = $manualFancy->event_id;
                    /*$profitLossData = $this->getProfitLossFancyOnZero($marketId);
                    
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
                    }*/
                    
                    //Lottery
                    /*$lottery = ManualSessionLottery::findOne(['market_id'=>$marketId]);
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
                        
                    }*/
                    
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
    
    // Tennis: getLossUnMatchOdds
    public function getLossUnMatchOddsOLD($marketId,$secId,$typ)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        if( $secId == false && $typ == false ){
            $where = [ 'market_id'=>$marketId,'match_unmatch' => 0,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => 'match_odd' ];
        }else{
            $where = [ 'bet_type' => $typ, 'sec_id' => $secId,'market_id'=>$marketId,'match_unmatch' => 0,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => 'match_odd' ];
        }
        
        $lossUnMatch = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
        
        if( $lossUnMatch == null || !isset($lossUnMatch[0]['val']) || $lossUnMatch[0]['val'] == '' ){
            $total = 0;
        }else{ $total = $lossUnMatch[0]['val']; }
        
        return $total;
        
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
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
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
    
    // Tennis: getLossUnMatchOdds
    public function getLossUnMatchOdds($marketId,$secId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        $where = [ 'sec_id'=>$secId,'market_id'=>$marketId,'match_unmatch' => 0,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => $sessionType ];
        $lossUnMatch = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
        
        if( $lossUnMatch == null || !isset($lossUnMatch[0]['val']) || $lossUnMatch[0]['val'] == '' ){
            $total = 0;
        }else{ $total = $lossUnMatch[0]['val']; }
        
        if( $total > 0 ){
            return (-1)*$total;
        }else{
            return $total;
        }
        
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
            
            //$where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $totalLoss = $backLoss + $layLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        return $total;
        
    }
    
    // getLossMatchOdds
    public function getLossMatchOdds($marketId,$secId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        $where = [ 'sec_id'=>$secId,'market_id'=>$marketId,'match_unmatch' => 1,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => $sessionType ];
        $lossUnMatch = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
        
        if( $lossUnMatch == null || !isset($lossUnMatch[0]['val']) || $lossUnMatch[0]['val'] == '' ){
            $total = 0;
        }else{ $total = $lossUnMatch[0]['val']; }
        
        if( $total > 0 ){
            return (-1)*$total;
        }else{
            return $total;
        }
        
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
            "data" => null,
            "count" => 0
        ];
        
        $currentBet = PlaceBet::find()
        ->select( [ 'id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'description','status' , 'created_at' ] )
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
        ->select( [ 'id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' ,'session_type','description', 'status' , 'created_at' ] )
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
                'match_odd' => [],
                'match_odd2' => [],
                'fancy' => [],
                'fancy2' => [],
                'lottery' => []
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
                
                $matchDataNew = [
                    'match_odd' => $matchOddData,
                    'match_odd2' => $matchOddData2,
                    'fancy' => $fancyData,
                    'fancy2' => $fancyData2,
                    'lottery' => $lotteryData
                ];
            }
            
            $matchedDataArr = [
                "data" => $matchDataNew,
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
                
                $unMatchDataNew = [
                    'match_odd' => $unMatchOddData,
                    'match_odd2' => $unMatchOddData2,
                    'fancy' => $unMatchFancyData,
                    'fancy2' => $unMatchFancyData2,
                    'lottery' => $unMatchLotteryData
                ];
            }
            
            $unMatchedDataArr = [
                "data" => $unMatchDataNew,
                "count" => count($unMatchData)
            ];
            
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
    
    /*
     * Bet History
     */
    
    public function actionBetHistory()
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
        
        //$response = [ "status" => 1 , "data" => []];
        
        $query = PlaceBet::find()
        ->select( [ 'id','runner' , 'bet_type' , 'price' , 'size' , 'bet_status' , 'description' ] )
        ->andWhere( [ 'status' => 1 , 'bet_status' => ['Win','Loss'] , 'user_id' => \Yii::$app->user->id  ] );
        
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
        
        if( $models != null ){
            $response = [ "status" => 1 , "data" => [ "items" => $models , 'count' => $count ] ];
        }else{
            $response = [ "status" => 1 , "data" => [ "items" => [] ] ];
        }
        
        return $response;
        
    }
    
    /*
     * Transaction History
     */
    public function actionTransactionOLD()
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
        
        $query = TempTransactionHistory::find()
        ->select( [ 'id' , 'user_id' , 'event_id','parent_id' ,'bet_id' , 'username' , 'transaction_type' , 'transaction_amount' , 'current_balance' , 'description','status' , 'created_at' ] )
        ->where( [ 'status' => [1,2] ] )->andWhere( [ 'user_id' => \Yii::$app->user->id ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "username" , $filters[ "title" ] ] );
                $query->orFilterWhere( [ "like" , "transaction_type" , $filters[ "title" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        
        //echo '<pre>';print_r($models);die;
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    /*
     * Transaction History
     */
    public function actionTransaction()
    {
        $transArr = [];$uId = \Yii::$app->user->id;
        $eventList = EventsPlayList::findAll(['status'=>[1,2]]);
        $sport = ['1'=>'Football','2'=>'Tennis','4'=>'Cricket','7'=>'Horse Racing'];
        if( $eventList != null ){
            foreach ( $eventList as $event ){
                
                $eventId = $event->event_id;
                $sportId = $event->sport_id;
                $titleEvent = $sport[$sportId].' | '.$event->event_name;
                
                // Match Odd
                if( $event->game_over == 'YES' ){
                    $date = $this->lastTineTrans($event->market_id,$uId);
                    $amount = $this->totalAmount($event->market_id,$uId);
                    
                    if( $amount >= 0 ){ $transType = 'CREDIT'; }else{ $transType = 'DEBIT'; $amount = (-1)*$amount;}
                    
                    $transArr[] = [
                        'created_at' => $date,
                        'transaction_amount' => $amount,
                        'transaction_type' => $transType,
                        'current_balance' => $this->lastBalanceUpdate($event->market_id,$uId),
                        'description' => $titleEvent.' | Match Odd'
                    ];
                }
                
                // Match Odd 2
                $matchOdd = ManualSessionMatchOdd::findAll(['event_id'=>$eventId , 'game_over' => 'YES' ]);
                if( $matchOdd != null ){
                    foreach ( $matchOdd as $data ){
                        
                        $date = $this->lastTineTrans($data->market_id,$uId);
                        $amount = $this->totalAmount($data->market_id,$uId);
                        
                        if( $amount >= 0 ){ $transType = 'CREDIT'; }else{ $transType = 'DEBIT'; $amount = (-1)*$amount;}
                        
                        $transArr[] = [
                            'created_at' => $date,
                            'transaction_amount' => $amount,
                            'transaction_type' => $transType,
                            'current_balance' => $this->lastBalanceUpdate($data->market_id,$uId),
                            'description' => $titleEvent.' | Match Odd 2'
                        ];
                    }
                }
                
                // Fancy
                $manualSession = ManualSession::findAll(['event_id'=>$eventId , 'game_over' => 'YES' ]);
                if( $manualSession != null ){
                    foreach ( $manualSession as $data ){
                        
                        $date = $this->lastTineTrans($data->market_id,$uId);
                        $amount = $this->totalAmount($data->market_id,$uId);
                        
                        if( $amount >= 0 ){ $transType = 'CREDIT'; }else{ $transType = 'DEBIT'; $amount = (-1)*$amount;}
                        
                        $transArr[] = [
                            'created_at' => $date,
                            'transaction_amount' => $amount,
                            'transaction_type' => $transType,
                            'current_balance' => $this->lastBalanceUpdate($data->market_id,$uId),
                            'description' => $titleEvent.' | Fancy | '.$data->title
                        ];
                    }
                }
                
                // Fancy 2
                $marketData = MarketType::findAll(['event_id'=>$eventId , 'game_over' => 'YES' ]);
                if( $marketData != null ){
                    foreach ( $marketData as $market ){
                        
                        $date = $this->lastTineTrans($market->market_id,$uId);
                        $amount = $this->totalAmount($market->market_id,$uId);
                        
                        if( $amount >= 0 ){ $transType = 'CREDIT'; }else{ $transType = 'DEBIT'; $amount = (-1)*$amount;}
                        
                        $transArr[] = [
                            'created_at' => $date,
                            'transaction_amount' => $amount,
                            'transaction_type' => $transType,
                            'current_balance' => $this->lastBalanceUpdate($market->market_id,$uId),
                            'description' => $titleEvent.' | Fancy 2 | '.$market->market_name
                        ];
                    }
                }
                
                // Lottery
                $lotteryData = ManualSessionLottery::findAll(['event_id'=>$eventId , 'game_over' => 'YES' ]);
                if( $lotteryData != null ){
                    foreach ( $lotteryData as $lottery ){
                        
                        $date = $this->lastTineTrans($lottery->market_id,$uId);
                        $amount = $this->totalAmount($lottery->market_id,$uId);
                        
                        if( $amount >= 0 ){ $transType = 'CREDIT'; }else{ $transType = 'DEBIT'; $amount = (-1)*$amount;}
                        
                        $transArr[] = [
                            'created_at' => $date,
                            'transaction_amount' => $amount,
                            'transaction_type' => $transType,
                            'current_balance' => $this->lastBalanceUpdate($lottery->market_id,$uId),
                            'description' => $titleEvent.' | Lottery | '.$lottery->title
                        ];
                    }
                }
                
            }
            
            //Other Transection
            $transDataArr = TempTransactionHistory::find()
            ->select(['created_at','transaction_amount','transaction_type','current_balance','description'])
            ->where(['user_id'=>$uId,'bet_id'=>0])->asArray()->all();
            if( $transDataArr != null ){
                foreach ( $transDataArr as $trans ){
                    $transArr[] = [
                        'created_at' => $trans['created_at'],
                        'transaction_amount' => $trans['transaction_amount'],
                        'transaction_type' => $trans['transaction_type'],
                        'current_balance' => $trans['current_balance'],
                        'description' => $trans['description'],
                    ];
                }
            }
            
        }
        //echo '<pre>';print_r($transArr);die;
        return [ "status" => 1 , "data" => [ "items" => $transArr , "count" => COUNT($transArr) ] ];
        
    }
    
    public function lastTineTrans($marketId,$uId)
    {
        $trans = TempTransactionHistory::find()
        ->select(['created_at'])->where( ['user_id'=>$uId,'market_id'=>$marketId,'status'=>1] )
        ->orderBy(['created_at' => SORT_DESC ])->one();
        if( $trans != null ){
            return $trans->created_at;
        }
        
    }
    
    public function totalAmount($marketId,$uId)
    {
        $amout = [];$totalAmount = 0;
        $transArr = TempTransactionHistory::find()
        ->select(['transaction_amount','transaction_type'])->where( ['user_id'=>$uId,'market_id'=>$marketId,'status'=>1] )
        ->all();
        if( $transArr != null ){
            foreach ( $transArr as $trans ){
                if( $trans->transaction_type == 'CREDIT' ){
                    $amout[] = $trans->transaction_amount;
                }else{
                    $amout[] = (-1)*$trans->transaction_amount;
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
        $trans = TempTransactionHistory::find()
        ->select(['current_balance'])->where( ['user_id'=>$uId,'market_id'=>$marketId,'status'=>1] )
        ->orderBy(['created_at' => SORT_ASC ])->one();
        if( $trans != null ){
            return $trans->current_balance;
        }
    }
    
    
    /*
     * Profit Loss History
     */
    
    public function actionProfitLoss()
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
                'event_name' => 'Bangladesh Vs India',
                'profti_loss' => '10000',
                'status' => 'win',
                'commission' => '100'
            ],
            1 => [
                'event_name' => 'India Vs Pakistan',
                'profti_loss' => '5000',
                'status' => 'loss',
                'commission' => '00'
            ],
        ];
        
        $total = '5000.00';
        
        return [ "status" => 1 , "data" => [ "items" => $models , "total" => $total, "count" => count($models) ] ];
    }
    
    /*
     * Profit Loss History
     */
    
    public function actionMarketResult()
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
                'result' => 'Bangladesh',
            ],
        ];
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => count($models) ] ];
    }
    
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = PlaceBet::findOne( ['id'=>$r_data[ 'id' ],'match_unmatch' => 0] );
                if( $event != null ){
                    $event->status = 0;
                    
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Bet Deleted Successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet Not Deleted!"
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
                            "message" => "Bet status not changed!" ,
                            "data" => $model->errors
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
