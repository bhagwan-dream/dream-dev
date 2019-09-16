<?php
namespace api\modules\v2\modules\users\controllers;

use Yii;

class UserDataController extends \common\controllers\aController
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
            $dataArr['betOption'] = $this->getBetOptions($uid);
            $dataArr['globalCommentary'] = $this->getGlobalCommentary();
            $dataArr['globalTimeOut'] = $this->getGlobalTimeOutVal();
            $dataArr['eventListTiming'] = $this->getEventListTiming();
            $dataArr['eventDetailTiming'] = $this->getEventDetailTiming();
            $dataArr['oddsTiming'] = $this->getOddsTiming();
            $dataArr['balanceRefreshTiming'] = $this->getBalanceRefreshTiming();
            $dataArr['teenPattiStatus'] = $this->getTeenPattiStatus();
            $dataArr['teenPattiData'] = $this->getTeenPattiData();

            if( null != \Yii::$app->request->get( 'id' ) ){
                $eventId = \Yii::$app->request->get( 'id' );
                $dataArr['eventCommentary'] = $this->getEventCommentary($eventId);
                $dataArr['matchUnmatchData'] = $this->matchUnmatchData($uid,$eventId);
            }else{
                $dataArr['matchUnmatchData'] = $this->matchUnmatchData($uid,null);
            }

            $t2 = time();

            $response = [ "status" => 1 , "data" => $dataArr , "time" => $t2-$t1 ];

        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }

        return $response;

    }

    public function getGlobalTimeOutVal(){

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'GLOBAL_TIME_OUT', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();
        if( $setting != null ){
            return ($setting['value']+0);
        }else{
            return 60;
        }

    }

    public function getEventListTiming(){

        $data = ['web' => 2000 , 'app' => 2000 ];
        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'EVENT_LIST_TIMING', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null ){
            $setting = json_decode( $setting['value'],true );
            if( $setting != null ){
                $web = $app = 2000;
                if( isset($setting['web']) && ($setting['web']+0) >= 1000  ){
                    $web = ($setting['web']+0);
                }
                if ( isset($setting['app']) && ($setting['app']+0) >= 1000 ){
                    $app = ($setting['app']+0);
                }
                $data = ['web' => $web , 'app' => $app ];
            }
        }

        return $data;

    }

    public function getEventDetailTiming(){

        $data = ['web' => 2000 , 'app' => 2000 ];

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'EVENT_DETAIL_TIMING', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null ){
            $setting = json_decode( $setting['value'],true );
            if( $setting != null ){
                $web = $app = 2000;
                if( isset($setting['web']) && ($setting['web']+0) >= 1000  ){
                    $web = ($setting['web']+0);
                }
                if ( isset($setting['app']) && ($setting['app']+0) >= 1000 ){
                    $app = ($setting['app']+0);
                }
                $data = ['web' => $web , 'app' => $app ];
            }
        }

        return $data;

    }

    public function getOddsTiming(){

        $data = ['web' => 500 , 'app' => 500 ];

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'ODDS_TIMING', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null ){
            $setting = json_decode( $setting['value'],true );
            if( $setting != null ){
                $web = $app = 500;
                if( isset($setting['web']) && ($setting['web']+0) >= 300  ){
                    $web = ($setting['web']+0);
                }
                if ( isset($setting['app']) && ($setting['app']+0) >= 300 ){
                    $app = ($setting['app']+0);
                }
                $data = ['web' => $web , 'app' => $app ];
            }
        }

        return $data;

    }

    public function getTeenPattiStatus(){

        $status = 0;

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'TEEN_PATTI_STATUS', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null && $setting['value'] != null ){
            $status = $setting['value'];
        }

        return $status;

    }

    public function getTeenPattiData(){

        $teenPattiData = null;

        $teenPattiData = (new \yii\db\Query())
            ->select(['event_id','event_name','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit'])
            ->from('events_play_list')
            ->where(['sport_id' => 999999, 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryAll();

        if( $teenPattiData != null ){
            return $teenPattiData;
        }
        return $teenPattiData;

    }

    public function getBalanceRefreshTiming(){

        $data = ['web' => 5000 , 'app' => 5000 ];

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'BALANCE_REFRESH_TIMING', 'status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null ){
            $setting = json_decode( $setting['value'],true );
            if( $setting != null ){
                $web = $app = 5000;
                if( isset($setting['web']) && ($setting['web']+0) >= 1000  ){
                    $web = ($setting['web']+0);
                }
                if ( isset($setting['app']) && ($setting['app']+0) >= 1000 ){
                    $app = ($setting['app']+0);
                }
                $data = ['web' => $web , 'app' => $app ];
            }
        }

        return $data;

    }

    public function getBetOptions( $uid ){

        //$model = PlaceBetOption::findOne(['user_id'=>$uid]);

        $model = (new \yii\db\Query())
            ->select(['bet_option'])
            ->from('place_bet_option')->where(['user_id'=>$uid])
            ->createCommand(\Yii::$app->db3)->queryOne();

        if( $model != null ){
            return $model['bet_option'];
        }else{

            //$setting = Setting::findOne(['key'=>'DEFAULT_STACK_OPTION','status'=>1]);

            $setting = (new \yii\db\Query())
                ->select('value')
                ->from('setting')->where(['key'=>'DEFAULT_STACK_OPTION','status'=>1])
                ->createCommand(\Yii::$app->db3)->queryOne();

            if( $setting != null ){
                return $setting['value'];
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
        $betList = $betListNew = [];
//        $cache = Yii::$app->cache;
//
//        $key = $uid.':PendingBets';
//        $betList = $betListNew = [];
//        if( $cache->exists($key) ) {
//
//            //$cache->delete($key);
//
//            $betList = $cache->get($key);
//            $betList = json_decode($betList, true);
//
//            if( $betList != null ){
//                if( $eventId != null ){
//                    foreach ( $betList[$eventId] as $mkdata ){
//                        foreach ( $mkdata as $data ){
//                            $betListNew[] = $data;
//                        }
//                    }
//                }else{
//                    foreach ( $betList as $edata ){
//                        foreach ( $edata as $mkdata ){
//                            foreach ( $mkdata as $data ){
//                                $betListNew[] = $data;
//                            }
//                        }
//                    }
//                }
//            }
//
//        }


        if( $eventId != null ){
            $where = [ 'status' => [0,1] , 'bet_status' => 'Pending' , 'user_id' => $uid ,'event_id' => $eventId ];
        }else{
            $where = [ 'status' => 1 , 'bet_status' => 'Pending' , 'user_id' => $uid ];
        }

        $betList = (new \yii\db\Query())
        ->from('place_bet')
        ->select([ 'id','runner' , 'bet_type' , 'price' , 'size' , 'rate' , 'session_type' , 'match_unmatch' , 'description' , 'status' ])
        ->where( $where )
        ->orderBy( [ 'created_at' => SORT_DESC ] )
        ->createCommand(\Yii::$app->db3)->queryAll();

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
                    if( $betData['status'] == 1  ){
                        $unMatchData[] = $betData;
                    }

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

        /*$marketList = (new \yii\db\Query())
            ->select(['market_id'])
            ->from('place_bet')->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ])
            ->createCommand(\Yii::$app->db)->queryAll();

        if( $marketList != null ){

            //echo '<pre>';print_r($marketList);die;

            $where = [ 'user_id' => $uid, 'market_id' => $marketList, 'status' => 1 ];

            $userExpose = (new \yii\db\Query())
                ->select(['sum(expose) as exposeVal'])
                ->from('user_event_expose')->where($where)
                ->createCommand(\Yii::$app->db)->queryOne();

            if( $userExpose != null ){
                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $userExpose['exposeVal']], ['id' => $uid])
                    ->execute();
            }

        }*/

        $user = (new \yii\db\Query())
            ->select(['balance', 'expose_balance', 'profit_loss_balance','updated_at'])
            ->from('user')->where(['id'=>$uid])
            ->createCommand(\Yii::$app->db)->queryOne();

        if( $user != null ){

            $user_balance = (int)$user['balance']-(int)$user['expose_balance']+(int)$user['profit_loss_balance'];
            $exposeBalance = round($user['expose_balance']);

            if( $user_balance < 0 ){

                $marketList = (new \yii\db\Query())
                    ->select(['market_id'])
                    ->from('place_bet')->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ])
                    ->createCommand(\Yii::$app->db)->queryAll();

                if( $marketList != null ){

                    //echo '<pre>';print_r($marketList);die;

                    $where = [ 'user_id' => $uid, 'market_id' => $marketList, 'status' => 1 ];

                    $userExpose = (new \yii\db\Query())
                        ->select(['sum(expose) as exposeVal'])
                        ->from('user_event_expose')->where($where)
                        ->createCommand(\Yii::$app->db)->queryOne();

                    if( $userExpose != null ){

                        $exposeBalance = $userExpose['exposeVal'];

                        \Yii::$app->db->createCommand()
                            ->update('user', ['expose_balance' => $userExpose['exposeVal']], ['id' => $uid])
                            ->execute();
                    }

                }

            }

            $updatedTime = ($user['updated_at']+0);

            return [ "balance" => round($user_balance) , "expose" => round($exposeBalance) , "mywallet" => round($user['balance']) , "updated_time" => $updatedTime ];
        }

        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 , "updated_time" => 0];

    }

    public function getBalanceValUpdateOLD($uid)
    {

        $user = (new \yii\db\Query())
            ->select(['balance', 'expose_balance', 'profit_loss_balance','updated_at'])
            ->from('user')->where(['id'=>$uid])
            ->createCommand(\Yii::$app->db3)->queryOne();

        if( $user != null ){

            $user_balance = $user['balance']-$user['expose_balance']+$user['profit_loss_balance'];

            $updatedTime = ($user['updated_at']+0);

            return [ "balance" => round($user_balance) , "expose" => round( $user['expose_balance']) , "mywallet" => round($user['balance']) , "updated_time" => $updatedTime ];
        }

        return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 , "updated_time" => 0];

    }

    public function actionBalanceRefresh()
    {
        $uid = \Yii::$app->user->id;

        $balanceData = $this->getUserBalanceRefresh($uid);

        if( $balanceData['status'] == 1 ){
        //if( $this->getBalanceRefreshNew($uid) ){
            $response = [
                'status' => 1,
                'time' => $balanceData['time'],
                "success" => [
                    "message" => "Balance refresh successfully!"
                ]
            ];
        }else{
            $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong please try again!" ] ];
        }

        return $response;
    }

    public function getBalanceRefreshNew($uid)
    {

//        $marketList = PlaceBet::find()->select(['market_id'])
//            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ] )
//            ->groupBy(['market_id'])->asArray()->all();

        $marketList = (new \yii\db\Query())
            ->select(['market_id'])
            ->from('place_bet')->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ])
            ->createCommand(\Yii::$app->db3)->queryAll();

        if( $marketList != null ){

            $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList ];

            $userExpose = (new \yii\db\Query())
                ->select(['sum(expose) as exposeVal'])
                ->from('user_event_expose')->where($where)
                ->createCommand(\Yii::$app->db3)->queryOne();

            if( $userExpose != null ){

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $userExpose['exposeVal']], ['id' => $uid])
                    ->execute();
            }

        }

    }

    public function getUserBalanceRefresh($uid)
    {
        //die;
        //$user = User::find()->select(['balance','expose_balance','profit_loss_balance'])->where(['id' => $uid ])->one();

        $user = (new \yii\db\Query())->select(['balance','expose_balance','profit_loss_balance'])->from('user')
            ->where(['id' => $uid , 'status' => 1 ] )->createCommand(Yii::$app->db3)->queryOne();

        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];

        $timeArr = [];

        if( $user != null ){
            $mywallet = $user['balance'];
            $profit_loss_balance = $user['profit_loss_balance'];
            $user_balance = $user['balance'];
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;

            //Match Odd Expose
            $t = time();

            $marketList = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            //echo '<pre>';print_r($marketList);die;
            if( $marketList != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList as $market ){

                    $marketId = $market['market_id'];

                    //$event = EventsPlayList::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    //echo '<pre>';print_r($event);die;

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        //$runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);

                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->createCommand(Yii::$app->db3)->queryAll();

                        //echo '<pre>';print_r($runnersData);die;

                        if( $runnersData != null ){

                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($uid,$marketId,$eventId,$runners['selection_id'],'match_odd');

                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }

                            }
                        }


                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            //echo $marketId.' => '.$minExpose.' , ';
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            $t2 = time();
            $timeArr['matchodd'] = $t2-$t;

            //Match Odd 2 Expose

            $marketList2 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd2' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList2 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList2 as $market ){

                    $marketId = $market['market_id'];

                    //$manualMatchOdd = ManualSessionMatchOdd::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $manualMatchOdd = $query->select(['event_id'])
                        ->from('manual_session_match_odd')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
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
                                $profitLoss = $this->getProfitLossMatchOddsNewAll($uid,$marketId,$eventId,$runners['sec_id'],'match_odd2');
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            //echo $marketId.' => '.$minExpose.' , ';
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
                        }
                    }

                }
            }

            $t3 = time();
            $timeArr['bookmaker'] = $t3-$t2;
            // Fancy Expose

            $marketList3 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList3 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList3 as $market ){

                    $marketId = $market['market_id'];

                    //$manualFancy = ManualSession::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $manualFancy = $query->select(['market_id','event_id'])
                        ->from('manual_session')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $manualFancy != null ) {
                        $eventId = $manualFancy['event_id'];
                        $profitLossData = $this->getProfitLossFancyOnZero($uid,$marketId, 'fancy');
                        if ($profitLossData != null) {
                            $minExpose = min($profitLossData);
                            $maxBal['expose'][] = $minExpose;
                            //echo $marketId.' => '.$minExpose.' , ';
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $profitLossData != null ){
                            $maxProfit = max($profitLossData);
                            $maxBal['plus'][] = $maxProfit;
                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
                        }
                    }
                }
            }

            $t4 = time();
            $timeArr['fancy'] = $t4-$t3;
            // Fancy 2 Expose

            $marketList4 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy2' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            //echo '<pre>';print_r($marketList);die;

            if( $marketList4 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList4 as $market ){

                    $marketId = $market['market_id'];

                    //$fancy2 = MarketType::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $fancy2 = $query->select(['market_id','event_id'])
                        ->from('market_type')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $fancy2 != null ) {
                        $eventId = $fancy2['event_id'];
                        $profitLossData = $this->getProfitLossFancyOnZero($uid,$marketId, 'fancy2');

                        if ($profitLossData != null) {
                            $minExpose = min($profitLossData);
                            $maxBal['expose'][] = $minExpose;
                            //echo $marketId.' => '.$minExpose.' , ';
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $profitLossData != null ){
                            $maxProfit = max($profitLossData);
                            $maxBal['plus'][] = $maxProfit;
                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
                        }
                    }
                }
            }
            $t5 = time();
            $timeArr['fancy2'] = $t5-$t4;
            // Lottery Expose

            $marketList5 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'lottery' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            //echo '<pre>';print_r($marketList);die;

            if( $marketList5 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList5 as $market ){

                    $marketId = $market['market_id'];
                    //$lottery = ManualSessionLottery::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);

                    $lottery = $query->select(['event_id'])
                        ->from('manual_session_lottery')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $lottery != null ){

                        $eventId = $lottery['event_id'];
                        $balExpose = $balPlus = [];
                        for($n=0;$n<10;$n++){
                            $profitLoss = $this->getLotteryProfitLoss($uid,$eventId,$marketId,$n);
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            $t6 = time();
            $timeArr['lottery'] = $t6-$t5;
            // Jackpot Expose
            $marketList6 = (new \yii\db\Query())
                ->select(['market_id','event_id','win','loss'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'jackpot', 'status' => 1] )
                ->createCommand(Yii::$app->db)->queryAll();// ->groupBy(['market_id','event_id','win','loss'])

            $eventId = 0;
            $balExpose = $balPlus = $betmarkrtID = [];
            if( $marketList6 != null ){
                $query = new \yii\db\Query(); $betarray = [];

                foreach ( $marketList6 as $market ){
                    $betarray[] = $market;
                    $betmarkrtID[] = $market['market_id'];
                    $eventId = $market['event_id'];
                }

                if(!empty( $betmarkrtID ) &&  $eventId != 0 ){
                    $jackpotMarket = $query->select(['*'])
                        ->from('cricket_jackpot')
                        ->where(['event_id'=>$eventId , 'game_over' => 'NO' , 'status' => 1 ])
                        ->createCommand(Yii::$app->db3)->queryAll();

                    $total = $expose = $count = 0;
                    $final_bet_array = $expose_array = $jackpot_array = [];
                    foreach ($jackpotMarket as $key => $jackpotarray ) {
                        $rate = $jackpotarray['rate'];
                        $market_id = $jackpotarray['market_id'];
                        $event_id = $jackpotarray['event_id'];
                        $totalWin = $totalLoss = 0;

                        foreach ($betarray as $key => $betarrays) {
                            if($market_id== $betarrays['market_id']){
                                $totalWin= $totalWin+$betarrays['win'];
                            }
                        }

                        foreach ($betarray as $key => $betarrays) {
                            if($market_id!= $betarrays['market_id']){
                                $totalLoss= $totalLoss+$betarrays['loss'];
                            }
                        }

                        //$final_bet_array[] = array( 'count' => $count,'market_id' => $market_id,'event_id' => $event_id,'rate' => $rate,'profit' => $totalWin,'expose' => (-1)*$totalLoss);
                        $total = $totalWin-$totalLoss;
                        //$expose_array[] = $totalWin-$totalLoss;
                        //$jackpot_array[] = array( 'count' => $count,'market_id' => $market_id,'event_id' => $event_id,'profitloss' => $total);
                        //$count++;

                        if( $total < 0 ){
                            $balExpose[] = $total;
                        }else{
                            $balPlus[] = $total;
                        }

                    }

                    if( $balExpose != null ){
                        $minExpose = min($balExpose);
                        $maxBal['expose'][] = $minExpose;
                        $this->updateUserExpose( $uid,$eventId,$eventId.'-JKPT',$minExpose );
                    }else{
                        $this->updateUserExpose( $uid,$eventId,$eventId.'-JKPT',0 );
                    }

                    if( $balPlus != null ){
                        $maxProfit = max($balPlus);
                        $maxBal['plus'][] = $maxProfit;
                        $this->updateUserProfit( $uid,$eventId,$eventId.'-JKPT',$maxProfit );
                    }else{
                        $this->updateUserProfit( $uid,$eventId,$eventId.'-JKPT',0 );
                    }
                }
            }

            $t7 = time();
            $timeArr['jackpot'] = $t7-$t6;

            //Teen Patti
            $marketList7 = (new \yii\db\Query())
                ->select(['event_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'event_id' => [56767,67564,87564], 'bet_status' => 'Pending', 'status' => 1])
                ->groupBy(['event_id'])->createCommand(Yii::$app->db2)->queryAll();

            if ( $marketList7 != null ) {

                $where = [ 'user_id' => $uid , 'status' => 1 , 'event_id' => $marketList7 ];
                $userExpose = (new \yii\db\Query())
                    ->select(['sum(expose) as exposeVal'])->from('user_event_expose')->where($where)
                    ->createCommand(Yii::$app->db2)->queryOne();
                if( $userExpose != null ){
                    $balExpose = (-1)*($userExpose['exposeVal']);

                    if( $balExpose != null ){
                        $maxBal['expose'][] = $balExpose;
                    }

                }
            }

//            $marketList7 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
//                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ] )
//                ->andWhere(['IN','event_id',[56767,67564,87564]])
//                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();
//
//            //echo '<pre>';print_r($marketList);die;
//            if( $marketList7 != null ){
//                $query = new \yii\db\Query();
//                foreach ( $marketList7 as $market ){
//
//                    $marketId = $market['market_id'];
//
//                    $event = $query->select(['event_id'])
//                        ->from('place_bet')
//                        ->andWhere(['market_id' => $marketId ])
//                        ->createCommand(Yii::$app->db3)->queryOne();
//
//                    if( $event != null ){
//                        $eventId = $event['event_id'];
//
//                        $runnersData = $query->select(['selection_id'])
//                            ->from('events_runners')
//                            ->where(['event_id' => $eventId])
//                            ->createCommand(Yii::$app->db3)->queryAll();
//
//                        //echo '<pre>';print_r($runnersData);die;
//
//                        if( $runnersData != null ){
//
//                            $balExpose = $balPlus = [];
//                            foreach ( $runnersData as $runners ){
//                                $profitLoss = $this->getProfitLossTeenPattiNew($uid,$marketId,$eventId,$runners['selection_id']);
//
//                                if( $profitLoss < 0 ){
//                                    $balExpose[] = $profitLoss;
//                                }else{
//                                    $balPlus[] = $profitLoss;
//                                }
//
//                            }
//                        }
//
//                        if( $balExpose != null ){
//                            $minExpose = min($balExpose);
//                            $maxBal['expose'][] = $minExpose;
//                            //echo $marketId.' => '.$minExpose.' , ';
//                            $this->updateUserExpose( $uid,$eventId,$marketId,$minExpose );
//                        }else{
//                            $this->updateUserExpose( $uid,$eventId,$marketId,0 );
//                        }
//
//                        if( $balPlus != null ){
//                            $maxProfit = max($balPlus);
//                            $maxBal['plus'][] = $maxProfit;
//                            $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
//                        }else{
//                            $this->updateUserProfit( $uid,$eventId,$marketId,0 );
//                        }
//
//                    }
//
//                }
//            }

            // All Expose
            if( isset( $maxBal['expose'] ) && $maxBal['expose'] != null && array_sum( $maxBal['expose'] ) < 0 ){

                $expose_balance = (-1)*( array_sum( $maxBal['expose'] ) );

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
                    ->execute();

                //return true;
                return [ "status" => 1 , "time" => $timeArr ];
            }else{
                $expose_balance = 0;

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
                    ->execute();

                return [ "status" => 1 , "time" => $timeArr ];
            }

            //$user_balance = $user_balance-$expose_balance+$profit_loss_balance;

            //return [ "balance" => round($user_balance) , "expose" => round($expose_balance) , "mywallet" => round($mywallet) ];
        }
        //return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        return [ "status" => 0 , "time" => $timeArr ];

    }

    public function getProfitLossMatchOddsNewAll_UNUSED($marketId,$eventId,$selId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;

        //$sessionType = ['match_odd','match_odd2'];

        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){

            $cache = Yii::$app->cache;

            $key = $userId.':PendingBets';
            $betList = $betListNew = [];
            if( $cache->exists($key) ) {

                //$cache->delete($key);

                $betList = $cache->get($key);
                $betList = json_decode($betList, true);

                if( $betList != null ){
                    if( $eventId != null ){
                        foreach ( $betList[$eventId] as $mkdata ){
                            foreach ( $mkdata as $data ){
                                $betListNew[] = $data;
                            }
                        }
                    }else{
                        foreach ( $betList as $edata ){
                            foreach ( $edata as $mkdata ){
                                foreach ( $mkdata as $data ){
                                    $betListNew[] = $data;
                                }
                            }
                        }
                    }
                }

            }


            if( $betListNew != null ){

                //var_dump($betListNew);die;
                //echo '<pre>';print_r($betListNew);die;

                $totalWin = $backWin = $layWin = $totalLoss = $backLoss = $layLoss = $unmatchLoss = 0;

                foreach ( $betListNew as $betData ){

                    //echo '<pre>';print_r($betData);

                    //Back Win
                    if( $betData['match_unmatch'] == 1 && $betData['bet_status'] == 'Pending' && $betData['sec_id'] == $selId
                        && $betData['status'] == 1 && $betData['bet_type'] == 'back' && $betData['session_type'] == $sessionType){

                        $backWin += $betData['win'];
                    }

                    //Lay Win
                    if( $betData['match_unmatch'] == 1 && $betData['status'] == 1 && $betData['bet_status'] == 'Pending'
                        && $betData['sec_id'] != $selId && $betData['bet_type'] == 'lay' && $betData['session_type'] == $sessionType){

                        $layWin += $betData['win'];
                    }

                    //Lay Loss
                    if( $betData['match_unmatch'] == 1 && $betData['status'] == 1 && $betData['bet_status'] == 'Pending'
                        && $betData['sec_id'] == $selId && $betData['bet_type'] == 'lay' && $betData['session_type'] == $sessionType){

                        $layLoss += $betData['loss'];
                    }

                    //Back Loss
                    if( $betData['match_unmatch'] == 1 && $betData['status'] == 1 && $betData['bet_status'] == 'Pending'
                        && $betData['sec_id'] != $selId && $betData['bet_type'] == 'back' && $betData['session_type'] == $sessionType){

                        $backLoss += $betData['loss'];
                    }

                    //Unmatch Loss

                    if( $betData['session_type'] == 'match_odd' && $betData['match_unmatch'] == 0){
                        if( $betData['status'] == 1 && $betData['bet_status'] == 'Pending' && $betData['sec_id'] == $selId ){
                            $unmatchLoss += $betData['loss'];
                        }
                    }

                }

                $totalWin = $backWin + $layWin;
                $totalLoss = $backLoss + $layLoss + $unmatchLoss;
                $total = $totalWin - $totalLoss;
                return $total;

            }

        }

    }


    // getProfitLossTeenPattiNew
    public function getProfitLossTeenPattiNew($userId,$marketId,$eventId, $selId)
    {
        $total = 0;

        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {
            $sessionType = 'teenpatti';
            if( $eventId == 56767 ){
                $sessionType = 'teenpatti';
            }elseif ( $eventId == 67564 ){
                $sessionType = 'poker';
            }elseif ( $eventId == 87564 ){
                $sessionType = 'andarbahar';
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'back', 'session_type' => $sessionType];

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($backWin == null || !isset($backWin['val']) || $backWin['val'] == '') {
                $backWin = 0;
            } else {
                $backWin = $backWin['val'];
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'lay'];
            $andWhere = ['!=', 'sec_id', $selId];

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($layWin == null || !isset($layWin['val']) || $layWin['val'] == '') {
                $layWin = 0;
            } else {
                $layWin = $layWin['val'];
            }

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS
            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'sec_id' => $selId, 'event_id' => $eventId, 'bet_type' => 'lay'];

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '') {
                $layLoss = 0;
            } else {
                $layLoss = $layLoss['val'];
            }

            $where = ['match_unmatch' => 1, 'market_id' => $marketId, 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1, 'bet_status' => 'Pending', 'event_id' => $eventId, 'bet_type' => 'back'];
            $andWhere = ['!=', 'sec_id', $selId];

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)
                ->createCommand(Yii::$app->db2)->queryOne();

            if ($backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '') {
                $backLoss = 0;
            } else {
                $backLoss = $backLoss['val'];
            }

            $totalLoss = $backLoss + $layLoss;
            $total = $totalWin - $totalLoss;

        }

        return $total;

    }

    public function getProfitLossMatchOddsNewAll($userId,$marketId,$eventId,$selId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;

        //$sessionType = ['match_odd','match_odd2'];

        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($backWin);
            if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layWin);
            if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin['val']; }

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType ,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($layLoss);
            if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();
            //echo '<pre>';print_r($backLoss);
            if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss['val']; }

            // IF UNMATCH BET LOSS
            $where = [ 'match_unmatch' => 0,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];

            //$unmatchLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->asArray()->all();

            $unmatchLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $unmatchLoss == null || !isset($unmatchLoss['val']) || $unmatchLoss['val'] == '' ){
                $unmatchLoss = 0;
            }else{ $unmatchLoss = $unmatchLoss['val']; }

            $totalLoss = $backLoss + $layLoss + $unmatchLoss;

            $total = $totalWin-$totalLoss;
            //echo $total;die;
        }

        return $total;

    }

    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancyOnZeroOLDUNUSED($marketId,$sessionType)
    {
        $userId = \Yii::$app->user->id;

        $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ,'status' => 1];

        //$betList = PlaceBet::find()
        //    ->select(['bet_type','price','win','loss'])
        //    ->where( $where )->asArray()->all();

        $betList = (new \yii\db\Query())
            ->select(['bet_type','price','win','loss'])
            ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryAll();

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
                //$betList1 = PlaceBet::find()
                //    ->select('SUM( win ) as winVal')
                //    ->where( $where )->andWhere(['>','price',(int)$i])
                //    ->asArray()->all();

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList2 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'yes','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList3 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['>','price',(int)$i])
//                    ->asArray()->all();

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['>','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_status' => 'Pending','bet_type' => 'no','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList4 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( !isset($betList1['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1['winVal']; }
                if( !isset($betList2['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2['winVal']; }
                if( !isset($betList3['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3['lossVal']; }
                if( !isset($betList4['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4['lossVal']; }

                $profit = ( $winVal1 + $winVal2 );
                $loss = ( $lossVal1 + $lossVal2 );

                $result[$i] = $profit-$loss;
            }

        }

        return $result;
    }


    // getJackpotProfitLoss
    public function getJackpotProfitLoss($userId,$eventId,$marketId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'jackpot', 'user_id' => $userId, 'event_id' => $eventId , 'status' => 1];
        // IF RUNNER WIN

        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['market_id' => $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betWinList == null) {
            $totalWin = 0;
        } else {
            $totalWin = $betWinList['totalWin'];
        }

        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=', 'market_id', $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betLossList == null) {
            $totalLoss = 0;
        } else {
           $totalLoss = (-1) * $betLossList['totalLoss'];
        }

       $total = $totalWin + $totalLoss;

        return $total;
    }

    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLoss($userId,$eventId,$marketId ,$selectionId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = [ 'session_type' => 'lottery', 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];
        // IF RUNNER WIN

        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['sec_id' => $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        // IF RUNNER LOSS
        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=','sec_id' , $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $betWinList == null ){
            $totalWin = 0;
        }else{ $totalWin = $betWinList['totalWin']; }

        if( $betLossList == null ){
            $totalLoss = 0;
        }else{ $totalLoss = (-1)*$betLossList['totalLoss']; }

        $total = $totalWin+$totalLoss;

        return $total;

    }

    // Event: Commentary
    public function getEventCommentary($eventId){

        $eventCommentary = 'No data!';

        if( $eventId != null ){

            $id = \Yii::$app->request->get( 'id' );

            $commentaryEvent = (new \yii\db\Query())
                ->select('title')
                ->from('global_commentary')->where(['event_id'=>$id])
                ->createCommand(\Yii::$app->db3)->queryOne();

            if( $commentaryEvent != null ){
                $eventCommentary = $commentaryEvent['title'];
            }

        }

        return $eventCommentary;
    }

    // Event: Commentary
    public function getGlobalCommentary(){

        $globalCommentary = 'No data!';

        $commentary = (new \yii\db\Query())
            ->select('value')
            ->from('setting')->where(['key'=>'GLOBAL_COMMENTARY' , 'status'=>1 ])
            ->createCommand(\Yii::$app->db3)->queryOne();

        if( $commentary != null ){
            $globalCommentary = $commentary['value'];
        }

        return $globalCommentary;
    }

    // getProfitLossFancyOnZero (by bhagvan)
    public function getProfitLossFancyOnZero($userId,$marketId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = [ 'bet_status' => 'Pending', 'bet_status' => 'Pending','session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId ,'status' => 1];
        $betList = (new \yii\db\Query())
            ->select(['bet_type','price','win','loss'])
            ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryAll();
        $result = $newbetresult = [];
        if( $betList != null ){
            $result = [];
            $betresult = [];
            $min = 0;
            $max = 0;


            foreach ($betList as $index => $bet) {
                $betresult[] = array('price'=>$bet['price'],'bet_type'=>$bet['bet_type'],'loss'=>$bet['loss'],'win'=>$bet['win']);
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
            $betarray = []; $bet_type='';
            $win = $loss=0;
            $count = $min;
            $totalbetcount = count($betresult);
            foreach ($betresult as $key => $value) {
                $val = $value['price']- $count;
                $minval = $value['price'] -$min;
                $maxval = $max-$value['price'];
                $bet_type = $value['bet_type'];
                $loss = $value['loss'];
                $newresult = [];
                $top = $bottom = $profitcount = $losscount = 0;

                for( $i= 0; $i < $minval; $i++){
                    if($bet_type == 'no'){
                        $top = $top+$value['win'];
                        $profitcount++;
                        $newresult[] = array( 'count' => $count, 'price' => $value['price'], 'bet_type' => $value['bet_type'], 'totalbetcount' => $totalbetcount, 'expose' => $value['win'] );
                    }else{
                        $bottom = $bottom + $value['loss'];
                        $losscount++;
                        $newresult[] = array( 'count'=>$count, 'price' => $value['price'], 'bet_type' => $value['bet_type'], 'totalbetcount' => $totalbetcount, 'expose'=> (-1)*$value['loss'] );
                    }
                    $count++;
                }

                for( $i= 0; $i <= $maxval; $i++){
                    if($bet_type == 'no'){
                        $newresult[] = array( 'count' => $count,'price' => $value['price'], 'bet_type' => $value['bet_type'], 'totalbetcount' => $totalbetcount, 'expose' => (-1)*$value['loss'] );
                        $bottom = $bottom + $value['loss'];
                        $losscount++;
                    }else{
                        $top = $top + $value['win'];
                        $profitcount++;
                        $newresult[] = array( 'count' => $count, 'price' => $value['price'], 'bet_type' => $value['bet_type'], 'totalbetcount' => $totalbetcount, 'expose' => $value['win'] );
                    }

                    $count++;
                }
                $result[] = array( 'count' => $value['price'], 'bet_type' => $value['bet_type'], 'profit'=>$top, 'loss'=>$bottom, 'profitcount' => $profitcount, 'losscount' => $losscount, 'newarray' => $newresult );
            }

            $newbetarray = $newbetresult = [];
            $totalmaxcount = $max-$min;
            if( $totalmaxcount > 0 ){
                for( $i = 0; $i < $totalmaxcount; $i++ ){
                    $newbetarray1 = []; $finalexpose = 0;
                    for( $x = 0; $x < $totalbetcount; $x++ ){
                        // echo "<pre>"; print_r($result[$x]['newarray']);echo "<br>";echo "<br>"; exit;
                        $expose = $result[$x]['newarray'][$i]['expose'];
                        $finalexpose = $finalexpose+$expose;

                        $newbetarray1[] = array( 'bet_price' => $result[$x]['count'], 'bet_type' => $result[$x]['bet_type'], 'expose' => $expose );
                    }
                    $newbetresult[] = $finalexpose;
                    $newbetarray[] = array( 'exposearray' => $newbetarray1, 'finalexpose' => $finalexpose );
                }
            }


            return $newbetresult;
        }
    }

}
