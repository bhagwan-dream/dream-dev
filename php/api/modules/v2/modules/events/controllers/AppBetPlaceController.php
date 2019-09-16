<?php
namespace api\modules\v2\modules\events\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;

class AppBetPlaceController extends \common\controllers\aController  // \yii\rest\Controller
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


    public function actionIndex(){

        $t1 = round(microtime(true) * 1000);

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = $timeArr = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $uid = \Yii::$app->user->id;

            // Check User
            $user = (new \yii\db\Query())
            ->select(['name','username','parent_id','balance', 'expose_balance' , 'profit_loss_balance', 'max_stack','min_stack','max_profit_limit' ])
            ->from('user')
            ->where([ 'id' => $uid , 'role' => 4 ,'is_login' => 1 , 'status' => 1 , 'isbet' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $user == null ){
                $response[ "error" ] = [
                    "message" => "Bet cancelled can not place!"
                ];
                return $response;
            }

            $userBalance = $user['balance'];
            $userExposeBalance = $user['expose_balance'];
            $userProfitLossBalance = $user['profit_loss_balance'];

            $userAvailableBalance = ( $user['balance'] - $user['expose_balance'] + $user['profit_loss_balance'] );

//            if( $user['balance'] == 0 ){
//                $response[ "error" ] = [
//                    "message" => "Insufficient funds!"
//                ];
//                return $response;
//            }

            if( $user['balance'] == 0 && $user['profit_loss_balance'] == 0 ){
                $response[ "error" ] = [
                    "message" => "Insufficient funds!"
                ];
                return $response;
            }


            if( $user['max_stack'] != 0 && $r_data['size'] > $user['max_stack'] ){
                $response[ "error" ] = [
                    "message" => "Maximum stack value is ".$user['max_stack']
                ];
                return $response;
            }

            if( $user['min_stack'] != 0 && $r_data['size'] < $user['min_stack'] ){
                $response[ "error" ] = [
                    "message" => "Minimum stack value is ".$user['min_stack']
                ];
                return $response;
            }

            // Check Event unblock
            if( in_array($r_data['event_id'], $this->checkUnBlockList($uid) ) ){
                $response[ "error" ] = [
                    "message" => "This event is already closed!"
                ];
                return $response;
            }

            if( in_array( $r_data['sport_id'], $this->checkUnBlockSportList($uid) ) ){
                $response[ "error" ] = [
                    "message" => "This Sport Block by Parent!"
                ];
                return $response;
            }

            //check market game over
            $checkMarketStatus = $this->checkMarketStatus($r_data['market_id'] , $r_data['session_type'] , $r_data['sec_id'] );
            if( ( $checkMarketStatus['is_true'] == false ) ){
                $response[ "error" ] = [
                    "message" => $checkMarketStatus['msg']
                ];
                return $response;
            }

//            $event = (new \yii\db\Query())
//            ->select(['event_name','game_over','suspended','ball_running'])
//            ->from('events_play_list')
//            ->where(['sport_id' => $r_data['sport_id'],'event_id' => $r_data['event_id'] , 'status' => 1 ])
//            ->one();
//
//            if( $event != null && $event['game_over'] == 'YES'){
//                $response[ "error" ] = [
//                    "message" => "This event is already closed!"
//                ];
//                return $response;
//            }
//
//            if( $event != null && $event['suspended'] == 'Y' && $event['ball_running'] == 'Y'){
//                $response[ "error" ] = [
//                    "message" => "Bet cancelled can not place!" ,
//                ];
//                return $response;
//            }

            //check Max Min Stack On Single Bet
            $checkBetMinMaxLimit = $this->checkBetMinMaxLimit($r_data);
            if( ( $checkBetMinMaxLimit['is_true'] == false ) ){
                $response[ "error" ] = [
                    "message" => $checkBetMinMaxLimit['msg']
                ];
                return $response;
            }

            // if session_type is [ match_odd , match_odd2  ]
            if( $r_data['session_type'] == 'match_odd'
                || $r_data['session_type'] == 'match_odd2' ){

                    $sportArr = ['1'=>'Football' , '2' => 'Tennis' , '4' => 'Cricket'];

                    $data['PlaceBet'] = $r_data;
                    $model = new PlaceBet();
                    if ($model->load($data)) {
                        $model->match_unmatch = 0;
                        $price = $model->price;

                        //check Bet Accepted
                        $checkBetAccepted = $this->checkBetAccepted($uid,$t1,$r_data);
                        if( ( $checkBetAccepted['is_true'] == false ) ){
                            $response[ "error" ] = [
                                "message" => $checkBetAccepted['msg']
                            ];
                            return $response;

                        }else{

                            if( isset( $checkBetAccepted['rate'] ) && $checkBetAccepted['rate'] != 0 ){
                                $model->rate = $checkBetAccepted['rate'];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Invalid Odd, Bet can not place!"
                                ];
                                return $response;
                            }

                            if( $r_data['session_type'] == 'match_odd2' ){

                                if( $model->bet_type == 'back' ){
                                    $model->win = ( $model->size*$model->price )/100;
                                    $model->loss = $model->size;
                                }else{
                                    $model->win = $model->size;
                                    $model->loss = ($model->price*$model->size)/100;
                                }

                                $model->match_unmatch = 1;

                            }else{

                                if( $model->bet_type == 'back' && trim($model->rate) >= trim($model->price) ){
                                    $model->match_unmatch = 1;
                                    $model->price = $model->rate;
                                }
                                if( $model->bet_type == 'lay' && trim($model->rate) <= trim($model->price) ){
                                    $model->match_unmatch = 1;
                                    $model->price = $model->rate;
                                }

                                if( $model->bet_type == 'back' ){
                                    if( $model->price > 1 ){
                                        $model->win = ($model->price-1)*$model->size;
                                    }else{
                                        $model->win = 0;
                                    }
                                    $model->loss = $model->size;
                                }else{
                                    $model->win = $model->size;
                                    if( $model->price > 1 ){
                                        $model->loss = ($model->price-1)*$model->size;
                                    }else{
                                        $model->loss = $model->size;
                                    }
                                }

                                // check accept_unmatch_bet and max_odd_limit
                                $eventCheck = (new \yii\db\Query())
                                    ->select(['accept_unmatch_bet','max_odd_limit'])
                                    ->from('events_play_list')
                                    ->where(['sport_id' => $r_data['sport_id'],'event_id' => $r_data['event_id'] , 'status' => 1 ])
                                    ->createCommand(Yii::$app->db2)->queryOne();

                                if( $eventCheck != null ){

                                    if( $model->match_unmatch != 1 && $eventCheck['accept_unmatch_bet'] != 1 ){

                                        $response[ "error" ] = [
                                            "message" => "Odd change, unmatch bet can not accepted."
                                        ];
                                        return $response;

                                    }

                                    if( $model->rate > $eventCheck['max_odd_limit'] ){

                                        $response[ "error" ] = [
                                            "message" => "Your max odd limit is over! Bet can not placed!!"
                                        ];
                                        return $response;

                                    }

                                }
                            }

                        }

                        //check Max Profit Limit New Bet
                        $model->user_id = $uid;
                        $checkMaxProfitLimitForNewBet = $this->checkMaxProfitLimitNewBet($model);
                        //check User Balance
                        if( ( $userAvailableBalance < $model->loss ) || ( $checkMaxProfitLimitForNewBet['is_true'] == false ) ){ //|| $userAvailableBalance < $model->size
                            $getUserBalance = $this->checkAvailableBalance($uid,$model);
                            //echo '<pre>';print_r($getUserBalance);die;
                            $exposeBalance = $getUserBalance['expose'];

                            if( ( $getUserBalance['expose'] > $getUserBalance['balance'] ) ){
                                $response[ "error" ] = [
                                    "message" => "Insufficient funds!!"
                                ];
                                return $response;
                            }
                        }else{
                            $exposeBalance = $userExposeBalance+$model->loss;
                        }

//                        if( isset( $getUserBalance['profitLimitData'] ) ){
//                            $profitLimitData = $getUserBalance['profitLimitData'];
//                        }else{ $profitLimitData = []; }

                        //$model->sport_id = 4;
                        $model->bet_status = 'Pending';
                        $model->user_id = $uid;
                        $model->client_name = $user['name'].' ['.$user['username'].']';
                        $model->master = $this->getUserName( $user['parent_id'] );

                        $model->description = $sportArr[$model->sport_id].' > '.$model->market_name.' > '.$model->runner;

                        if( $r_data['session_type'] == 'match_odd' ){
                            if( $model->bet_type == 'back' ){
                                $model->ccr = round ( ( ($model->win)*$this->clientCommissionRate() )/100 );
                            }else{
                                $model->ccr = round ( ( ($model->size)*$this->clientCommissionRate() )/100 );
                            }
                        }else{
                            $model->ccr = 0;
                        }

                        $model->status = 1;
                        $model->created_at = $model->updated_at = time();
                        $model->ip_address = $this->get_client_ip();

                        //check Max Profit On Single Bet
                        $checkBetMaxProfitLimit = $this->checkBetMaxProfitLimit($model);
                        if( ( $checkBetMaxProfitLimit['is_true'] == false ) ){
                            $response[ "error" ] = [
                                "message" => $checkBetMaxProfitLimit['msg']
                            ];
                            return $response;
                        }

                        //check Max Profit Limit All
                        //$checkMaxProfitLimit = $this->checkMaxProfitLimit($model,$profitLimitData);
                        $checkMaxProfitLimit = $this->checkMaxProfitLimitNew($model);
                        if( ( $checkMaxProfitLimit['is_true'] == false ) ){
                            $response[ "error" ] = [
                                "message" => $checkMaxProfitLimit['msg']
                            ];
                            return $response;
                        }

                        //check market game over
                        $checkMarketStatus = $this->checkMarketStatus($r_data['market_id'] , $r_data['session_type'] , $r_data['sec_id'] );
                        if( ( $checkMarketStatus['is_true'] == false ) ){
                            $response[ "error" ] = [
                                "message" => $checkMarketStatus['msg']
                            ];
                            return $response;
                        }


                        if( $model->save() ){

                            $betId = $model->id;
                            $type = $model->bet_type;
                            $runner = $model->runner;
                            $size = $model->size;
                            $rate = $model->rate;

                            //echo $expose_balance;die;

                            \Yii::$app->db->createCommand()
                                ->update('user', ['expose_balance' => $exposeBalance], ['id' => $uid])
                                ->execute();

                            //UserProfitLoss::balanceValUpdate($uid);

                            if( $model->match_unmatch != 0 ){
                                $msg = "Bet ".$type." ".$runner.",<br>Placed ".$size." @ ".$price." Odds <br> Matched ".$size." @ ".$rate." Odds";
                                $response = [
                                    'status' => 1 ,
                                    "success" => [
                                        "message" => $msg,
                                        "betId" => md5($betId)
                                    ]
                                ];
                            }else{
                                $msg = "Bet ".$type." ".$runner.",<br>Placed ".$size." @ ".$price." Odds <br> UnMatched ".$size." @ ".$rate." Odds";
                                $response[ "error" ] = [
                                    "message" => $msg
                                ];
                            }

                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! Bet can not place!"
                            ];
                        }

                    }

            }

            // if session_type is [ fancy , fancy2 , lottery , jackpot ]
            if( $r_data['session_type'] == 'fancy2' || $r_data['session_type'] == 'fancy' || $r_data['session_type'] == 'lottery'
             ||   $r_data['session_type'] == 'jackpot'){

                $model = new PlaceBet();

                $data['PlaceBet'] = $r_data;
                $model->load($data);

                $model->match_unmatch = 1;
                $model->description = 'Cricket > '.$model->market_name.' > '.$model->runner;

                //check Bet Accepted
                $checkBetAccepted = $this->checkBetAccepted($uid,$t1,$r_data);
                if( ( $checkBetAccepted['is_true'] == false ) ){
                    $response[ "error" ] = [
                        "message" => $checkBetAccepted['msg']
                    ];
                    return $response;

                }else{

                    if( isset( $checkBetAccepted['rate'] ) && $checkBetAccepted['rate'] != 0 ){
                        $model->rate = $checkBetAccepted['rate'];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet cancelled can not place!" ,
                        ];
                        return $response;
                    }

                    if( isset( $checkBetAccepted['price'] ) && $checkBetAccepted['price'] != 0 ){
                        $model->price = $checkBetAccepted['price'];
                    }else{

                        if( $r_data['session_type'] != 'lottery' ||  $r_data['session_type'] != 'jackpot' ){
                            $response[ "error" ] = [
                                "message" => "Bet cancelled can not place!" ,
                            ];
                            return $response;
                        }else{
                            $model->price = $checkBetAccepted['price'];
                        }

                    }

                    if( $model->bet_type == 'yes' && $model->rate != null ){
                        $model->win = round(( $model->size*$model->rate )/100);
                        $model->loss = $model->size;
                    }elseif( $model->bet_type == 'no' && $model->rate != null ){
                        $model->win = $model->size;
                        $model->loss = round(( $model->size*$model->rate )/100);
                    }else{
                        if($model->session_type == 'lottery'){
                            $model->win = round(( $model->size*( $model->rate-1 ) ));
                            $model->loss = $model->size;
                        }else if($model->session_type == 'jackpot'){
                            $model->win = round(( $model->size*( $model->rate-1 ) ));
                            $model->loss = $model->size;
                        }else{
                            $model->win = $model->size;
                            $model->loss = $model->size;
                        }
                    }
                }

                //check Max Profit Limit New Bet
                $model->user_id = $uid;
                $checkMaxProfitLimitForNewBet = $this->checkMaxProfitLimitNewBet($model);

                //check User Balance
                if( ( $userAvailableBalance < $model->loss ) || ( $checkMaxProfitLimitForNewBet['is_true'] == false ) ) {
                    $getUserBalance = $this->checkAvailableBalance($uid, $model);
                    //echo '<pre>';print_r($getUserBalance);die;
                    $exposeBalance = $getUserBalance['expose'];

                    if (($getUserBalance['expose'] > $getUserBalance['balance'])) {
                        $response["error"] = [
                            "message" => "Insufficient funds!!!"
                        ];
                        return $response;
                    }

                    //check market game over
                    $checkMarketStatus = $this->checkMarketStatus($r_data['market_id'] , $r_data['session_type'] , $r_data['sec_id'] );
                    if( ( $checkMarketStatus['is_true'] == false ) ){
                        $response[ "error" ] = [
                            "message" => $checkMarketStatus['msg']
                        ];
                        return $response;
                    }

                }else{
                    $exposeBalance = $userExposeBalance+$model->loss;
                }

//                if( isset( $getUserBalance['profitLimitData'] ) ){
//                    $profitLimitData = $getUserBalance['profitLimitData'];
//                }else{ $profitLimitData = []; }

                $model->sport_id = 4;
                $model->bet_status = 'Pending';
                $model->user_id = $uid;
                $model->client_name = $user['name'].' ['.$user['username'].']';
                $model->master = $this->getUserName( $user['parent_id'] );

                $model->ccr = 0;
                $model->status = 1;
                $model->created_at = $model->updated_at = time();
                $model->ip_address = $this->get_client_ip();

                //check Max Profit On Single Bet
                $checkBetMaxProfitLimit = $this->checkBetMaxProfitLimit($model);
                if( ( $checkBetMaxProfitLimit['is_true'] == false ) ){
                    $response[ "error" ] = [
                        "message" => $checkBetMaxProfitLimit['msg']
                    ];
                    return $response;
                }

                //check Max Profit Limit
                $checkMaxProfitLimit = $this->checkMaxProfitLimitNew($model);
                if( ( $checkMaxProfitLimit['is_true'] == false ) ){
                    $response[ "error" ] = [
                        "message" => $checkMaxProfitLimit['msg']
                    ];
                    return $response;
                }

                if( $model->save() ){

                    $betId = $model->id;
                    $type = $model->bet_type;
                    $runner = $model->runner;
                    $size = $model->size;
                    $price = $model->price;
                    $rate = $model->rate;

                    //$this->betPlaceRadisCash($model);

                    \Yii::$app->db->createCommand()
                        ->update('user', ['expose_balance' => $exposeBalance], ['id' => $uid])
                        ->execute();

                    //UserProfitLoss::balanceValUpdate($uid);

                    $msg = "Bet ".$type." RUN,<br>Placed ".$size." @ ".$price." Odds <br> Matched ".$size." @ ".$rate." Odds";

                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => $msg,
                            "betId" => md5($betId)
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Bet can not place!"
                    ];
                }

            }

        }

        return $response;
    }


    //actionBalanceRefresh
    public function actionBalanceRefresh()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = $timeArr = [];
        if( json_last_error() == JSON_ERROR_NONE ) {
            $r_data = ArrayHelper::toArray($request_data);
            $uid = \Yii::$app->user->id;
            $model = PlaceBet::findOne([ 'md5(id)' => $r_data['bet_id'] , 'bet_status' => 'Pending' , 'status' => 1 ]);
            $getUserBalance = $this->getBalanceRefreshAfterBet($uid,$model);
            $exposeBalance = $getUserBalance['expose'];

            if( ( $getUserBalance['expose'] > $getUserBalance['balance'] ) ){

                $model->description = "Your bet was canceled due to Insufficient funds!!";
                $model->bet_status = "Canceled";
                $model->status = 0;
                if( $model->save() ){
                    $response[ "error" ] = [
                        "message" => "Your bet was canceled due to Insufficient funds!!"
                    ];
                    return $response;
                }

            }else{

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $exposeBalance], ['id' => $uid])
                    ->execute();

                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => "Balance refresh successfully!"
                    ]
                ];
            }

        }


        return $response;
    }

    public function betPlaceRadisCash($model){

        $betData = [
            'id' => $model->id,
            'user_id' => $model->user_id,
            'price' => $model->price,
            'size' => $model->size,
            'runner' => $model->runner,
            'bet_type' => $model->bet_type,
            'sec_id' => $model->sec_id,
            'market_id' => $model->market_id,
            'event_id' => $model->event_id,
            'session_type' => $model->session_type,
            'market_name' => $model->market_name,
            'rate' => $model->rate,
            'sport_id' => $model->sport_id,
            'match_unmatch' => $model->match_unmatch,
            'win' => $model->win,
            'loss' => $model->loss,
            'bet_status' => $model->bet_status,
            'client_name' => $model->client_name,
            'master' => $model->master,
            'description' => $model->description,
            'ccr' => $model->ccr,
            'status' => $model->status,
            'updated_at' => $model->updated_at,
            'created_at' => $model->created_at,
            'ip_address' => $model->ip_address,
        ];


        $cache = Yii::$app->cache;
        $betList = $betListData = [];
        $key = $model->user_id.':PendingBets';

        if( $cache->exists($key) ){
            $betList = $cache->get($key);
            $betListData = json_decode($betList,true);

            if( $betListData != null && isset( $betListData[$model->event_id] )){

                if( isset( $betListData[$model->event_id][$model->market_id] ) ){
                    $betListData[$model->event_id][$model->market_id][$model->id] = $betData;
                }else{
                    $betListData[$model->event_id][$model->market_id][$model->id] = $betData;
                }

            }else{
                $betListData[$model->event_id][$model->market_id][$model->id] = $betData;
            }

        }else{
            $betListData[$model->event_id][$model->market_id][$model->id] = $betData;
        }

        $cache->set( $key , json_encode($betListData) );

    }

    public function checkMarketStatus($marketId,$sessionType,$secId){

        $res = [ 'is_true' => true ];

        // Check Match odd Session
        if( $sessionType == 'match_odd' ){

            $matchOdd = (new \yii\db\Query())
                ->select(['game_over','suspended','ball_running'])
                ->from('events_play_list')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $matchOdd != null && $matchOdd['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

            if( $matchOdd != null && ( $matchOdd['suspended'] == 'Y' || $matchOdd['ball_running'] == 'Y' ) ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $matchOdd == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

        }

        // Check Book Maker Session
        if( $sessionType == 'match_odd2' ){

            $bookMaket = (new \yii\db\Query())
                ->select(['game_over'])
                ->from('manual_session_match_odd')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $bookMaket == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $bookMaket != null && $bookMaket['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

            $bookMaketData = (new \yii\db\Query())
                ->select(['suspended','ball_running'])
                ->from('manual_session_match_odd_data')
                ->where([ 'market_id' => $marketId ,'sec_id' => $secId])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $bookMaketData == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $bookMaketData != null && ( $bookMaketData['suspended'] == 'Y' || $bookMaketData['ball_running'] == 'Y' ) ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

        }

        // Check Manual Fancy Session
        if( $sessionType == 'fancy' ){

            $manualSession = (new \yii\db\Query())
                ->select(['game_over','suspended','ball_running'])
                ->from('manual_session')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $manualSession == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $manualSession != null && $manualSession['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

            if( $manualSession != null && ( $manualSession['suspended'] == 'Y' || $manualSession['ball_running'] == 'Y' ) ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

        }

        // Check Fancy Session
        if( $sessionType == 'fancy2'){

            $marketType = (new \yii\db\Query())
                ->select(['game_over','suspended','ball_running'])
                ->from('market_type')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $marketType == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $marketType != null && $marketType['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

            if( $marketType != null && ( $marketType['suspended'] == 'Y' || $marketType['ball_running'] == 'Y' ) ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

        }

        // Check Lottery Session
        if( $sessionType == 'lottery'){
            $lottery = (new \yii\db\Query())
                ->select(['game_over'])
                ->from('manual_session_lottery')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $lottery == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $lottery != null && $lottery['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

        }

        // Check Jackpot Session
        if( $sessionType == 'jackpot'){
            $jackpot = (new \yii\db\Query())
                ->select(['game_over'])
                ->from('cricket_jackpot')
                ->where([ 'market_id' => $marketId , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $jackpot == null ){
                $res = [ 'is_true' => false , 'msg' => 'Bet cancelled can not place!' ];
            }

            if( $jackpot != null && $jackpot['game_over'] == 'YES' ){
                $res = [ 'is_true' => false , 'msg' => 'This session is already closed!' ];
            }

        }

        return $res;

    }

    //check database function
    public function checkUnBlockList($uId)
    {
        $user = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where(['id'=>$uId])->createCommand(Yii::$app->db2)->queryOne();


        $pId = 1;
        if( $user != null ){
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
            ->select(['event_id'])->from('event_market_status')
            ->where(['user_id'=>$pId,'market_type' => 'all' ])
            ->createCommand(Yii::$app->db2)->queryAll();

        if( $listArr != null ){

            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }

            return $newList;
        }else{
            return [];
        }

    }

    //check sport database function
    public function checkUnBlockSportList($uId)
    {
        //$uId = \Yii::$app->user->id;
        //$user = User::find()->select( ['parent_id'] )
        //    ->where(['id'=>$uId])->one();

        $user = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where(['id'=>$uId])->createCommand(Yii::$app->db2)->queryOne();

        $pId = 1;
        if( $user != null ){
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id'=>$pId ])
            ->createCommand(Yii::$app->db2)->queryAll();

        if( $listArr != null ){

            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }

            return $newList;
        }else{
            return [];
        }

    }


    //checkBetMaxProfitLimit
    public function checkBetMaxProfitLimit($model){

        $res = [ 'is_true' => true ];

        // Check User
        $uid = \Yii::$app->user->id;
        $user = (new \yii\db\Query())
            ->select(['max_profit_limit' ])
            ->from('user')
            ->where([ 'id' => $uid , 'role' => 4 ,'is_login' => 1 , 'status' => 1 , 'isbet' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

        if( $user != null ){
            if( $user['max_profit_limit'] != 0 && $user['max_profit_limit'] < $model->win ){
                return [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$user['max_profit_limit'] ];
            }
        }


        if( $model->session_type == 'match_odd' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit','upcoming_max_profit','play_type'])
            ->from('events_play_list')
            ->where([ 'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['play_type'] == 'IN_PLAY' ){
                    if( $event['max_profit'] < $model->win ){
                        $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                    }
                }else{

                    if( $event['upcoming_max_profit'] < $model->win ){
                        $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['upcoming_max_profit'] ];
                    }

                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }


        }else if( $model->session_type == 'match_odd2' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit'])
            ->from('manual_session_match_odd')
            ->where([ 'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['max_profit'] < $model->win ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model->session_type == 'fancy' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit'])
            ->from('manual_session')
            ->where([ 'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['max_profit'] < $model->win ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model->session_type == 'fancy2' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit'])
            ->from('market_type')
            ->where([ 'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['max_profit'] < $model->win ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model->session_type == 'lottery' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit'])
            ->from('manual_session_lottery')
            ->where([ 'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['max_profit'] < $model->win ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model->session_type == 'jackpot' ){

            $event = (new \yii\db\Query())
            ->select(['max_profit'])
            ->from('cricket_jackpot_setting')
            ->where([ 'event_id' => $model->event_id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['max_profit'] < $model->win ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum profit value is'.$event['max_profit'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed 1!!' ];
            }

        }else{
            $res = [ 'is_true' => false , 'msg' => 'Bet can not placed 2!!' ];
        }

        return $res;


    }

    //checkBetMinMaxLimit
    public function checkBetMinMaxLimit($model){

        $res = [ 'is_true' => true ];

        if( $model['session_type'] == 'match_odd' ){

            $event = (new \yii\db\Query())
                ->select(['min_stack','max_stack','upcoming_min_stake','upcoming_max_stake','play_type'])
                ->from('events_play_list')
                ->where([ 'event_id' => $model['event_id'] , 'market_id' => $model['market_id'] , 'status' => 1 ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['play_type'] == 'IN_PLAY' ){

                    if( $event['min_stack'] > $model['size'] ){
                        $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is'.$event['min_stack'] ];
                    }
                    if( $event['max_stack'] < $model['size'] ){
                        $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is'.$event['max_stack'] ];
                    }

                }else{

                    if( $event['upcoming_min_stake'] > $model['size'] ){
                        $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is'.$event['upcoming_min_stake'] ];
                    }

                    if( $event['upcoming_max_stake'] < $model['size'] ){
                        $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is'.$event['upcoming_max_stake'] ];
                    }

                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }


        }else if( $model['session_type'] == 'match_odd2' ){

            $event = (new \yii\db\Query())
            ->select(['min_stack','max_stack'])
            ->from('manual_session_match_odd')
            ->where([ 'event_id' => $model['event_id'] , 'market_id' => $model['market_id'] , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['min_stack'] > $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is '.$event['min_stack'] ];
                }
                if( $event['max_stack'] < $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is '.$event['max_stack'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model['session_type'] == 'fancy' ){

            $event = (new \yii\db\Query())
            ->select(['min_stack','max_stack'])
            ->from('manual_session')
            ->where([ 'event_id' => $model['event_id'] , 'market_id' => $model['market_id'] , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['min_stack'] > $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is '.$event['min_stack'] ];
                }
                if( $event['max_stack'] < $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is '.$event['max_stack'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model['session_type'] == 'fancy2' ){

            $event = (new \yii\db\Query())
            ->select(['min_stack','max_stack'])
            ->from('market_type')
            ->where([ 'event_id' => $model['event_id'] , 'market_id' => $model['market_id'] , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['min_stack'] > $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is '.$event['min_stack'] ];
                }
                if( $event['max_stack'] < $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is '.$event['max_stack'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model['session_type'] == 'lottery' ){

            $event = (new \yii\db\Query())
            ->select(['min_stack','max_stack'])
            ->from('manual_session_lottery')
            ->where([ 'event_id' => $model['event_id'] , 'market_id' => $model['market_id'] , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['min_stack'] > $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is '.$event['min_stack'] ];
                }
                if( $event['max_stack'] < $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is '.$event['max_stack'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }else if( $model['session_type'] == 'jackpot' ){

            $event = (new \yii\db\Query())
            ->select(['min_stack','max_stack'])
            ->from('cricket_jackpot_setting')
            ->where([ 'event_id' => $model['event_id'] , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){

                if( $event['min_stack'] > $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Minimum stack value is '.$event['min_stack'] ];
                }
                if( $event['max_stack'] < $model['size'] ){
                    $res = [ 'is_true' => false , 'msg' => 'Maximum stack value is '.$event['max_stack'] ];
                }

            }else{
                $res = [ 'is_true' => false , 'msg' => 'Bet can not placed 1!!' ];
            }

        }else{
            $res = [ 'is_true' => false , 'msg' => 'Bet can not placed 2!!' ];
        }

        return $res;

    }

    //checkBetAccepted
    public function checkBetAccepted($uid,$t1,$model){

        $maxDelay = 1000000;

        $t2 = round(microtime(true) * 1000);

        $betDelay = $this->getBetDelay($uid,$model['sport_id'], $model['event_id'], $model['market_id'],$model['session_type']);
        //echo $betDelay;die;
        if( $betDelay != 0 ){
            $betDelay = ( $betDelay*1000000 - ( $t2-$t1 ) );
            usleep($betDelay);
        }else{
            $betDelay = 0;
        }

        if( $model['session_type'] == 'fancy' ){

            if( $model['bet_type'] == 'no' ){

                $manualSession = (new \yii\db\Query())
                    ->select(['id'])->from('manual_session')
                    ->where([ 'market_id' => $model['market_id'] , 'no' => $model['price'] , 'no_rate' => $model['rate'] ])
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $manualSession != null ){
                    return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
                }else{
                    return [ 'is_true' => false , 'msg' => 'Rate changed! Bet can not placed !!' ];
                }

            }else{

                $manualSession = (new \yii\db\Query())
                    ->select(['id'])->from('manual_session')
                    ->where( [ 'market_id' => $model['market_id'] , 'yes' => $model['price'] , 'yes_rate' => $model['rate'] ] )
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $manualSession != null ){
                    return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
                }else{
                    return [ 'is_true' => false , 'msg' => 'Rate changed! Bet can not placed !!' ];
                }
            }

        }

        if( $model['session_type'] == 'match_odd2' ){

            if( $model['bet_type'] == 'lay' ){

                $matchOdd2 = (new \yii\db\Query())
                    ->select(['id'])->from('manual_session_match_odd_data')
                    ->where( [ 'market_id' => $model['market_id'] , 'sec_id' => $model['sec_id'] ,'lay' => $model['price'] ] )
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $matchOdd2 != null ){
                    return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
                }else{
                    return [ 'is_true' => false , 'msg' => 'Rate changed! Bet can not placed !!' ];
                }
            }else{

                $matchOdd2 = (new \yii\db\Query())
                    ->select(['id'])->from('manual_session_match_odd_data')
                    ->where( [ 'market_id' => $model['market_id'] , 'sec_id' => $model['sec_id'] ,'back' => $model['price'] ] )
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $matchOdd2 != null ){
                    return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
                }else{
                    return [ 'is_true' => false , 'msg' => 'Rate changed! Bet can not placed !!' ];
                }
            }
        }

        if( $model['session_type'] == 'lottery' ){
            return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
        }

        if( $model['session_type'] == 'jackpot' ){

            $jackpot = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')
                ->where( [ 'market_id' => $model['market_id'] , 'rate' => $model['rate'] ] )
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $jackpot != null ){
                return [ 'is_true' => true , 'rate' => $model['rate'] , 'price' => $model['price'] ];
            }else{
                return [ 'is_true' => false , 'msg' => 'Rate changed! Bet can not placed !!' ];
            }

        }

        $ctime = round(microtime(true) * 1000);

        $marketId = $model['market_id'];

        $cache = \Yii::$app->cache;
        $data = $cache->get($marketId);
        $data = json_decode($data);

        if( $data != null ){

            $dff = $ctime-$data->time;
            if( $dff < $maxDelay ){
                $rate = 0;

                if( $model['session_type'] == 'match_odd' ){

                    foreach ($data->odds as $odds){

                        if( $odds->selectionId == $model['sec_id'] ){

                            if( $model['bet_type'] == 'back' ){
                                $rate = $odds->backPrice1;
                            }
                            if( $model['bet_type'] == 'lay' ){
                                $rate = $odds->layPrice1;
                            }

                        }

                    }
                    return [ 'is_true' => true , 'rate' => $rate ];

                }else if( $model['session_type'] == 'fancy2' ){

                    if( $data->suspended != 'Y' && $data->ballRunning != 'Y' ){

                        if( $model['bet_type'] == 'no' ){
                            $price = $data->data->no;
                            $rate = $data->data->no_rate;
                        }
                        if( $model['bet_type'] == 'yes' ){
                            $price = $data->data->yes;
                            $rate = $data->data->yes_rate;
                        }

                        if( $model['rate'] == $rate && $model['price'] == $price ){
                            return [ 'is_true' => true , 'rate' => $rate , 'price' => $price ];
                        }

                    }

                }


            }else{
                return [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];
            }

        }

        return [ 'is_true' => false , 'msg' => 'Bet can not placed !!' ];


    }


    // Cricket: Get User Name Data
    public function getUserName($id){

        if( $id != null ){

            $user = (new \yii\db\Query())
            ->select(['name','username'])
            ->from('user')
            ->where([ 'id' => $id , 'status' => 1 ])
            ->createCommand(Yii::$app->db2)->queryOne();

            if( $user != null ){
                return $user['name'].' ['.$user['username'].'] ';
            }else{
                return 'undefine';
            }

        }
        return 'undefine';

    }

    // action Check BetDelay
    public function getBetDelay($uid,$sportId,$eventId,$marketId,$type)
    {
        $betDelayVal = 0;
        //$uid = \Yii::$app->user->id;

        if( $type == 'lottery' ){
            return $betDelayVal;
        }

        if( $type == 'match_odd2' ){
            $matchOdd2 = (new \yii\db\Query())
                ->select(['bet_delay'])->from('manual_session_match_odd')
                ->where(['market_id'=>$marketId ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $matchOdd2 != null ){
                return $matchOdd2['bet_delay'];exit;
            }
        }

        if( $type == 'fancy' ){
            $fancy = (new \yii\db\Query())
                ->select(['bet_delay'])->from('manual_session')
                ->where(['market_id'=>$marketId ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $fancy != null ){
                return $fancy['bet_delay'];exit;
            }
        }

        if( $type == 'jackpot' ){
            $jackpot = (new \yii\db\Query())
                ->select(['bet_delay'])->from('cricket_jackpot_setting')
                ->where(['event_id'=>$eventId ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if( $jackpot != null ){
                return $jackpot['bet_delay'];exit;
            }
        }

        if( $type == 'fancy2' ){

            $fancy2 = (new \yii\db\Query())
                ->select(['bet_delay'])->from('market_type')
                ->where(['market_id'=>$marketId ])
                ->createCommand(Yii::$app->db2)->queryOne();
            if( $fancy2 != null ){
                return $fancy2['bet_delay'];exit;
            }

        }


        if( $type == 'match_odd' ){

            $user = (new \yii\db\Query())
                ->select(['bet_delay'])->from('user')
                ->where(['id'=>$uid ])->createCommand(Yii::$app->db2)->queryOne();

            if( $user != null ){
                if( $user['bet_delay'] != 0 ){
                    return $user['bet_delay'];exit;
                }
            }

            $event = (new \yii\db\Query())
                ->select(['bet_delay'])->from('events_play_list')
                ->where(['event_id'=>$eventId ])->createCommand(Yii::$app->db2)->queryOne();

            if( $event != null ){
                if( $event['bet_delay'] != 0 ){
                    return $event['bet_delay'];exit;
                }
            }

            $sport = (new \yii\db\Query())
                ->select(['bet_delay'])->from('events')
                ->where(['event_type_id'=>$sportId ])->createCommand(Yii::$app->db2)->queryOne();

            if( $sport != null ){
                if( $sport['bet_delay'] != 0 ){
                    return $sport['bet_delay'];exit;
                }
            }

        }

        return $betDelayVal;
    }

    // Function to get the client Commission Rate
    public function clientCommissionRate()
    {
        $CCR = 1;//$CCR = Client Commission Rate

        $setting = (new \yii\db\Query())
        ->select(['value'])
        ->from('setting')
        ->where([ 'key' => 'CLIENT_COMMISSION_RATE' , 'status' => 1 ])
        ->createCommand(Yii::$app->db2)->queryOne();

        if( $setting != null ){
            $CCR = $setting['value'];
            return $CCR;
        }else{
            return $CCR;
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
