<?php
namespace api\modules\v2\modules\users\controllers;

use common\models\ManualSession;
use common\models\MarketType;
use common\models\User;
use common\models\PlaceBetOption;
use common\models\Event;
use common\models\FavoriteMarket;
use common\models\PlaceBet;
use yii\helpers\ArrayHelper;
use common\models\ManualSessionLottery;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionMatchOddData;
use common\models\EventsRunner;
use common\models\EventsPlayList;
use common\models\GlobalCommentary;
use common\models\Setting;

class UserDataControllerBK extends \common\controllers\aController
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
    
    // User Data
    public function actionIndex()
    {
        $dataArr = [];

        $t1 = time();

        if( null != \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            $dataArr['balance'] = $this->getBalanceValUpdate($uid);
            $dataArr['globalCommentary'] = $this->getGlobalCommentary();
            $dataArr['betOption'] = $this->getBetOptions($uid);
            $dataArr['matchUnmatchData'] = $this->matchUnmatchData($uid,null);
            
            if( null != \Yii::$app->request->get( 'id' ) ){
                $eventId = \Yii::$app->request->get( 'id' );
                $dataArr['eventCommentary'] = $this->getEventCommentary($eventId);
                $dataArr['matchUnmatchData'] = $this->matchUnmatchData($uid,$eventId);
            }

            $t2 = time();

            $response = [ "status" => 1 , "data" => $dataArr , "time" => $t2-$t1 ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
        
    }
    
    public function getBetOptions( $uid ){
        
        $model = PlaceBetOption::findOne(['user_id'=>$uid]);
        
        if( $model != null ){
            return $model->bet_option;
        }else{
            
            $setting = Setting::findOne(['key'=>'DEFAULT_STACK_OPTION','status'=>1]);
            if( $setting != null ){
                return $setting->value;
            }else{
                return '10000,25000,50000,100000,200000';
            }
            
        }
        
    }
    
    public function matchUnmatchData($uid,$eventId)
    {
        $matchedDataArr = $unMatchedDataArr = [
            "data" => [],
            "count" => 0
        ];

        if( $eventId != null ){
            $where = [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => $uid ,'event_id' => $eventId ];
        }else{
            $where = [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => $uid ];
        }


        $betList = (new \yii\db\Query())
        ->from('place_bet')
        ->select([ 'id','runner' , 'bet_type' , 'price' , 'size' , 'rate' , 'session_type' , 'match_unmatch' , 'description' ])
        ->where( $where )
        ->orderBy( [ 'created_at' => SORT_DESC ] )
        ->all();
        
        $matchData = $unMatchData = [];
        if( $betList != null ){
            $i = 0;
            foreach ( $betList as $betData ){
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
                    
                }else if( $betData['session_type'] == 'match_odd2' ){
                    if( $betData['bet_type'] != 'back' ){
                        if( $betData['price'] > 0 ){
                            $betData['profit'] = round( ( $betData['size']*( $betData['price']/100 ) ) , 2 );
                        }
                    }else{
                        if( $betData['price'] > 0 ){
                            $betData['profit'] = round( ( $betData['size']*( $betData['price']/100 ) ) , 2 );
                        }
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
            
            $items = [
                'matched'   =>  $matchedDataArr,
                'unmatched' =>  $unMatchedDataArr
            ];
            
        }else{
            $items = [
                'matched'   =>  $matchedDataArr,
                'unmatched' =>  $unMatchedDataArr
            ];
        }
        
        return $items;
    }
    
    
    public function getBalanceValUpdate($uid)
    {
        $user = User::find()->select(['balance','expose_balance','profit_loss_balance'])->where(['id' => $uid ])->one();
        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];

        if( $user != null ){
            $mywallet = $user->balance;
            $profit_loss_balance = $user->profit_loss_balance;
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;
            
            //Match Odd Expose
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            if( $marketList != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    //$event = EventsPlayList::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->one();

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        //$runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);

                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->all();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($marketId,$eventId,$runners['selection_id'],'match_odd');
                                
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
                $query = new \yii\db\Query();
                foreach ( $marketList2 as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    //$manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $manualMatchOdd = $query->select(['event_id'])
                        ->from('manual_session_match_odd')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->one();

                    if( $manualMatchOdd != null ){
                        $eventId = $manualMatchOdd['event_id'];
                        //$runnersData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);

                        $runnersData = $query->select(['sec_id'])
                            ->from('manual_session_match_odd_data')
                            ->where(['market_id'=>$marketId])
                            ->all();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($marketId,$eventId,$runners['sec_id'],'match_odd2');
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

            if( $marketList3 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList3 as $market ){
                    
                    $marketId = $market['market_id'];

                    //$manualFancy = ManualSession::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $manualFancy = $query->select(['market_id'])
                        ->from('manual_session')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->one();

                    if( $manualFancy != null ) {

                        $profitLossData = $this->getProfitLossFancyOnZero($marketId, 'fancy');

                        if ($profitLossData != null) {
                            $maxBal['expose'][] = min($profitLossData);
                        }
                        if ($profitLossData != null) {
                            $maxBal['plus'][] = max($profitLossData);
                        }
                    }
                }
            }
            
            // Fancy 2 Expose
            $marketList4 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy2' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            
            //var_dump($marketList4);die;
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList4 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList4 as $market ){
                    
                    $marketId = $market['market_id'];

                    //$fancy2 = MarketType::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $fancy2 = $query->select(['market_id'])
                        ->from('market_type')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->one();

                    if( $fancy2 != null ) {

                        $profitLossData = $this->getProfitLossFancyOnZero($marketId, 'fancy2');

                        if ($profitLossData != null) {
                            $maxBal['expose'][] = min($profitLossData);
                        }
                        if ($profitLossData != null) {
                            $maxBal['plus'][] = max($profitLossData);
                        }
                    }
                }
            }
            
            // Lottery Expose
            $marketList5 = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'lottery' , 'status' => 1 ] )
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);die;
            
            if( $marketList5 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList5 as $market ){
                    
                    $marketId = $market['market_id'];
                    //$lottery = ManualSessionLottery::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $lottery = $query->select(['event_id'])
                        ->from('manual_session_lottery')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->one();

                    if( $lottery != null ){

                        $eventId = $lottery['event_id'];
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

//            if( $uid == 961 ){
//                echo $expose_balance;die;
//            }

            \Yii::$app->db->createCommand()
            ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
            ->execute();
            
//            if( $user_balance >= $expose_balance ){
//                $user_balance = $user_balance-$expose_balance+$profit_loss_balance;
//            }else{
//                $user_balance = 0;
//            }

            $user_balance = $user_balance-$expose_balance+$profit_loss_balance;

            return [ "balance" => round($user_balance) , "expose" => round($expose_balance) , "mywallet" => round($mywallet) ];
        }
        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        
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
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $totalWin = $backWin + $layWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'market_id' => $marketId ,'session_type' => $sessionType ,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            $totalLoss = $backLoss + $layLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        return $total;
        
    }
    
    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancyOnZero($marketId,$sessionType)
    {
        $userId = \Yii::$app->user->id;

        $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ,'status' => 1];

        $betList = PlaceBet::find()
            ->select(['bet_type','price','win','loss'])
            ->where( $where )->asArray()->all();

        if( $betList != null ){
            $result = [];

//        $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->one();
//
//        $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->one();
//
//        if( isset( $betMinRun->price ) ){
//            $minRun = $betMinRun->price-1;
//        }
//
//        if( isset( $betMaxRun->price ) ){
//            $maxRun = $betMaxRun->price+1;
//        }

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

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'no','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                $betList1 = PlaceBet::find()
                    ->select('SUM( win ) as winVal')
                    ->where( $where )->andWhere(['>','price',(float)$i ])
                    ->asArray()->all();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                $betList2 = PlaceBet::find()
                    ->select('SUM( win ) as winVal')
                    ->where( $where )->andWhere(['<=','price',(float)$i ])
                    ->asArray()->all();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                $betList3 = PlaceBet::find()
                    ->select('SUM( loss ) as lossVal')
                    ->where( $where )->andWhere(['>','price',(float)$i ])
                    ->asArray()->all();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'no','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                $betList4 = PlaceBet::find()
                    ->select('SUM( loss ) as lossVal')
                    ->where( $where )->andWhere(['<=','price',(float)$i ])
                    ->asArray()->all();

                if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }

                $profit = ( $winVal1 + $winVal2 );
                $loss = ( $lossVal1 + $lossVal2 );

                $result[$i] = $profit-$loss;
            }

        }

        return $result;
    }

    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLoss($eventId,$marketId ,$selectionId)
    {
        $total = 0;
        $userId = \Yii::$app->user->id;
        $where = [ 'session_type' => 'lottery', 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];
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
    
    // Event: Commentary
    public function getEventCommentary($eventId){
        
        $eventCommentary = 'No data!';
        
        if( $eventId != null ){
            
            $id = \Yii::$app->request->get( 'id' );
            
            $commentaryEvent = GlobalCommentary::findOne(['event_id'=>$id]);
            
            if( $commentaryEvent != null ){
                $eventCommentary = $commentaryEvent->title;
            }
            
        }
        
        return $eventCommentary;
    }
    
    // Event: Commentary
    public function getGlobalCommentary(){
        
        $globalCommentary = 'No data!';
        
        $commentary = Setting::findOne(['key'=>'GLOBAL_COMMENTARY' , 'status'=>1 ]);
        
        if( $commentary != null ){
            $globalCommentary = $commentary->value;
        }
        
        return $globalCommentary;
    }
    
}
