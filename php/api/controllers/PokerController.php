<?php
namespace api\controllers;

use api\modules\v1\modules\users\models\AuthToken;
use common\models\PlaceBet;
use common\models\TeenPattiResult;
use common\models\User;
use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Poker controller
 */
class PokerController extends Controller
{
    public $enableCsrfValidation = false;

    //action Auth
    public function actionAppUserAuth()
    {
        //$data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        //echo '<pre>';print_r($_POST);die;

        $response = [ 'status' => 0 , 'message' => 'UnAuthorized Access !!!' , 'data' => ['suspend' => true] ];

        $setting = (new \yii\db\Query())
            ->select(['value'])->from('setting')
            ->where(['key' => 'TEEN_PATTI_STATUS','status' => 1])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $setting != null && $setting['value'] == 0 ){
            return json_encode($response);exit;
        }

        if (isset($_POST['token'])) {

            $token = $_POST['token'];

            $authCheck = (new \yii\db\Query())
                ->select(['user_id'])->from('auth_token')
                ->where(['token' => $token])
                ->createCommand(Yii::$app->db3)->queryOne();

            if( $authCheck != null ){

                $uid = $authCheck['user_id'];
                $user = (new \yii\db\Query())
                    ->select(['name','username','balance','expose_balance','profit_loss_balance'])->from('user')
                    ->where(['is_login' => 1 , 'status' => 1 , 'id' => $uid , 'role' => 4 ])
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( $user != null ){

                    $event = (new \yii\db\Query())
                        ->select(['min_stack','max_stack','max_profit_all_limit'])->from('events_play_list')
                        ->where(['event_id' => 56767 , 'status' => 1 ])
                        ->createCommand(Yii::$app->db3)->queryOne();
                    $eventData = null;
                    if( $event != null ){
                        $eventData = $event;
                    }

                    $balance = round($user['balance']+$user['profit_loss_balance']-$user['expose_balance']);
                    $data = [
                        "userId" => $uid,
                        "username" => $user['name'].' [ '.$user['name'].' ] ',
                        "balance" => $balance,
                        "expose" => $user['expose_balance'],
                        "eventData" => $eventData
                    ];

                    $response = [ 'status' => 1 , 'message' => 'Success !!' , 'data' => $data ];
                }

            }

        }

        return json_encode($response);
    }

    //action Auth
    public function actionAuth()
    {

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( isset($data) ){

//            $fp = fopen('/var/www/dreamexch9.com/html/php/pokerlog/auth.txt', 'a');
//            fwrite($fp, json_encode($data));
//            fclose($fp);

            //echo '<pre>';print_r($data['token']);die;

			//$token = strrev($data['token']);
            $token = $data['token'];

            //echo $token;die;

			$authCheck = (new \yii\db\Query())
				->select(['user_id'])->from('auth_token')
				->where(['token' => $token])
                ->createCommand(Yii::$app->db3)->queryOne();

            //echo '<pre>';print_r($authCheck);die;

			if( $authCheck != null ){

				$uid = $authCheck['user_id'];
				$user = (new \yii\db\Query())
					->select(['username','balance','expose_balance','profit_loss_balance','role'])->from('user')
					->where(['is_login' => 1 , 'status' => 1 , 'id' => $uid ])
                    ->createCommand(Yii::$app->db3)->queryOne();

				if( $user != null ){

				    if( $user['role'] != 4 ){

                        $data1 = [
                            "operatorId" => 9093,
                            "userId" => $uid,
                            "username" => $user['username'],
                            "playerTokenAtLaunch" => $data['token'],
                            "token" => $data['token'],
                            "balance" => 0,
                            "currency" => "INR",
                            "language" => "en",
                            "timestamp" => time(),
                            "clientIP" => ["1"],
                            "VIP" => "3",
                            "errorCode" => 0,
                            "errorDescription" => "ok"
                        ];

                    }else{

                        $balance = round($user['balance']+$user['profit_loss_balance']-$user['expose_balance']);
                        $data1 = [
                            "operatorId" => 9093,
                            "userId" => $uid,
                            "username" => $user['username'],
                            "playerTokenAtLaunch" => $data['token'],
                            "token" => $data['token'],
                            "balance" => $balance,
                            "currency" => "INR",
                            "language" => "en",
                            "timestamp" => time(),
                            "clientIP" => ["1"],
                            "VIP" => "3",
                            "errorCode" => 0,
                            "errorDescription" => "ok"
                        ];

                    }

                    return json_encode($data1);

				}else{

                    $data1 = [
                        "errorCode" => 1,
                        "errorDescription" => "User Not Found!"
                    ];
                    return json_encode($data1);
                }

			}

//			else{
//
//                $data1 = [
//                    "operatorId" => 9093,
//                    "userId" => 1283,
//                    "username" => 'Demo123',
//                    "playerTokenAtLaunch" => $data['token'],
//                    "token" => $data['token'],
//                    "balance" => 100,
//                    "currency" => "INR",
//                    "language" => "en",
//                    "timestamp" => time(),
//                    "clientIP" => ["1"],
//                    "VIP" => "3",
//                    "errorCode" => 0,
//                    "errorDescription" => "ok"
//                ];
//
//                return json_encode($data1);
//
//            }

		}
    }

    //action Auth
    public function actionExposure()
    {

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( isset($data) ){

            //echo '<pre>';print_r($data['token']);die;

//            $fp = fopen('/var/www/dreamexch9.com/html/php/pokerlog/exposure.txt', 'a');
//            fwrite($fp, json_encode($data));
//            fclose($fp);

            //$token = strrev($data['token']);
            $token = $data['token'];

            $authCheck = (new \yii\db\Query())
                ->select(['user_id'])->from('auth_token')
                ->where(['token' => $token ])
                ->one();

            if( $authCheck != null ){

                //echo '<pre>';print_r($authCheck);die;

                $uid = (int)$authCheck['user_id'];
                $user = (new \yii\db\Query())
                    ->select(['parent_id','name','username','balance','expose_balance','profit_loss_balance'])->from('user')
                    ->where(['is_login' => 1 , 'status' => 1 , 'id' => $uid , 'role' => 4 , 'isbet' => 1 ])
                    ->one();

                if( $user != null ){

                    //echo '<pre>';print_r($user);die;
                    $mainBalance = round($user['balance']);
                    $plBalance = round($user['profit_loss_balance']);
                    $exposeBalance = round( $user['expose_balance'] );
                    $balance = round($mainBalance+$plBalance-$exposeBalance);

                    $betData = $data['betInfo'];
                    $typeGame = [
                        '56767' => 'Teen Patti',
                        '67564' => 'Poker',
                        '87564' => 'Andar Bahar',
                    ];

                    $newExposeBal = (int)$exposeBalance-(int)$data['calculateExposure'];

                    if( ( (int)$mainBalance+(int)$plBalance-(int)$newExposeBal ) < 0 ){

                        $response = [
                            "status"=> 3,
                            "message"=> "Insufficient fund !! Bet Not Allowed!",
                        ];

                        return json_encode($response);

                    }

                    $model = new PlaceBet();

                    $model->user_id = $uid;
                    $model->price = $betData['requestedOdds'];
                    $model->rate = $betData['requestedOdds'];
                    $model->size = $betData['reqStake'];
                    $model->runner = $betData['runnerName'];
                    $model->bet_type = $betData['isBack'] == 1 ? 'back' : 'lay';
                    $model->sec_id = $betData['runnerId'];
                    $model->market_id = $betData['marketId'];
                    $model->event_id = $betData['gameId'];

                    if( isset( $betData['gameId'] ) && $betData['gameId'] == 56767 ){
                        $model->session_type = 'teenpatti';
                    }elseif( isset( $betData['gameId'] ) && $betData['gameId'] == 67564  ){
                        $model->session_type = 'poker';
                    }elseif( isset( $betData['gameId'] ) && $betData['gameId'] == 87564  ){
                        $model->session_type = 'andarbahar';
                    }

                    $model->market_name = $typeGame[$betData['gameId']];
                    //$model->slug = "teen-patti";

                    $model->sport_id = 999999;

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

                    if( $uid == 3135){ $eArr = [56767,67564,87564]; }else{ $eArr = [56767,67564]; }

                    if( !in_array($betData['gameId'] , $eArr ) ){ //[56767,67564,87564]

                        $response = [
                            "status"=> 3,
                            "message"=> "Wrong event id! Bet Not Allowed!",
                        ];

                        return json_encode($response);

                    }

                    // min - max - stack - pl limit
                    $event = (new \yii\db\Query())
                        ->select(['min_stack','max_stack','max_profit','max_profit_all_limit','bet_delay','suspended'])
                        ->from('events_play_list')
                        ->where(['event_id' => (int)$model->event_id , 'status' => 1 ])
                        ->one();

                    if( $event != null && $event['suspended'] != 'N'){
                        $response = [
                            "status"=> 3,
                            "message"=> "Suspended ! Bet Not Allowed!",
                        ];

                        return json_encode($response);

                    }elseif ( $event != null && ( (int)$event['min_stack'] > (int)$model->size ) ){

                        $response = [
                            "status"=> 3,
                            "Message"=> "Min Stack is ".$event['min_stack']."! Bet Not Allowed!",
                        ];

                        return json_encode($response);

                    }elseif ( $event != null && ( (int)$event['max_stack'] < (int)$model->size ) ){

                        $response = [
                            "status"=> 3,
                            "message"=> "Max Stack is ".$event['max_stack']."! Bet Not Allowed!",
                        ];

                        return json_encode($response);

                    }elseif ( (int)$event['max_profit'] < (int)$model->win ){
                        $response = [
                            "status"=> 3,
                            "message"=> "Max Profit is ".$event['max_profit']."! Bet Not Allowed!",
                        ];

                        return json_encode($response);
                    }

                    //check User Balance
                    //if( $balance < $model->loss ){
                        $getUserBalance = $this->checkAvailableBalance($uid,$model);

                        $exposeBalance = $getUserBalance['expose'];

                        if( ( $getUserBalance['expose'] > $getUserBalance['balance'] ) ){
                            $response = [
                                "status"=> 3,
                                "message"=> "Insufficient fund !! Bet Not Allowed!",
                            ];
                            return json_encode($response);
                        }
                    //}else{
                    //    $exposeBalance = $exposeBalance+$model->loss;
                    //}

                    $model->match_unmatch = 1;
                    $model->bet_status = 'Pending';

                    $model->ccr = 0;
                    $model->client_name = $user['name'].' ['.$user['username'].']';
                    $model->master = $this->getUserName( $user['parent_id'] );

                    $model->description = $typeGame[$betData['gameId']].' > '.$betData['runnerName'];

                    $model->status = 1;
                    $model->created_at = $model->updated_at = time();
                    $model->ip_address = $this->get_client_ip();

                    if( $model->save() ){

                        // Update user balance
                        \Yii::$app->db->createCommand()
                            ->update('user', [ 'expose_balance' => $exposeBalance ] , ['id' => $uid] )
                            ->execute();

//                        if( (int)$betData['calculateExposure'] < 0 ){
//                            $this->updateUserExpose($uid,$model->event_id,$model->market_id,$betData['calculateExposure']);
//                            $this->updateUserProfit($uid,$model->event_id,$model->market_id,0);
//                        }else{
//                            $this->updateUserExpose($uid,$model->event_id,$model->market_id,0);
//                            $this->updateUserProfit($uid,$model->event_id,$model->market_id,$betData['calculateExposure']);
//                        }

                        $balance = round($mainBalance+$plBalance-$exposeBalance);

                        $response = [
                            "status"=> 0,
                            "message"=> "Exposure insert Successfully...!",
                            "wallet"=> $balance,
                        ];

                        return json_encode($response);
                    }

                }else{

                    $response = [
                        "status"=> 1,
                        "message"=> "Something wrong! User not found!",
                    ];

                    return json_encode($response);

                }

            }else{

                $response = [
                    "status"=> 1,
                    "message"=> "Something wrong! User token not found!",
                ];

                return json_encode($response);
            }

        }
    }

    //checkMaxProfitLimitNewBet
    public function checkMaxProfitLimitNewBet($cBet)
    {

        if ($cBet != null) {

            $currentProfitVat = $newProfitVat = $profitLimitEvent = $profitMatchOddVal  = 0;
            $maxProfitLimitMatchOdd = $maxProfitLimitEvent = 100000;

            $eventId = $cBet->event_id;
            $marketId = $cBet->market_id;
            $uid = $cBet->user_id;
            $betWinVal = $cBet->win;
            //echo '<pre>';print_r($betWinVal);die;
            //Market Profit Data
            $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketId , 'event_id' => $eventId ];

            $profitLimitData = (new \yii\db\Query())
                ->select(['profit as profitVal'])
                ->from('user_event_expose')
                ->where($where)->createCommand(Yii::$app->db)->queryOne();

            //TeenPatti
            if ( in_array($eventId , [56767,67564,87564])) {

                if ( $profitLimitData != null) {
                    if ( isset($profitLimitData['profitVal']) && $profitLimitData['profitVal'] != null ) {
                        $currentProfitVat = (int)$profitLimitData['profitVal'];
                        $newProfitVat = $profitMatchOddVal = (int)$profitLimitData['profitVal']+$betWinVal;
                    }
                }

                $event = (new \yii\db\Query())
                    ->select(['max_profit_limit'])
                    ->from('events_play_list')->where(['market_id' => $marketId, 'event_id' => $eventId])
                    ->createCommand(Yii::$app->db2)->queryOne();

                if ($event != null) {
                    $maxProfitLimitMatchOdd = $event['max_profit_limit'];
                }

                if ($profitMatchOddVal > $maxProfitLimitMatchOdd) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }

            }

            if ( $isTrue == true ) {

                //Event Profit Data
                $where = [ 'user_id' => $uid , 'status' => 1 , 'event_id' => $eventId ];

                $profitLimitData = (new \yii\db\Query())
                    ->select(['sum(profit) as profitVal'])
                    ->from('user_event_expose')
                    ->where($where)->createCommand(Yii::$app->db)->queryOne();

                if ( $profitLimitData != null) {
                    if (isset($profitLimitData['profitVal']) && $profitLimitData['profitVal'] != null ) {
                        $profitLimitEvent = (int)$profitLimitData['profitVal'];
                        $profitLimitEvent = $profitLimitEvent-$currentProfitVat+$newProfitVat;
                    }
                }

                $event = (new \yii\db\Query())
                    ->select(['max_profit_all_limit'])
                    ->from('events_play_list')->where(['event_id' => $eventId])
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $event != null ){

                    $maxProfitLimitEvent = $event['max_profit_all_limit'];
                    //echo $maxProfitLimitEvent;
                }

                if ( $profitLimitEvent > $maxProfitLimitEvent ) {
                    $isTrue = false;
                } else {
                    $isTrue = true;
                }
                //die;
            }

        }

        return ['is_true' => $isTrue];
    }

    //getBalanceVal
    public function checkAvailableBalance($uid, $cBet)
    {
        $user = (new \yii\db\Query())
            ->select(['balance', 'expose_balance', 'profit_loss_balance'])
            ->from('user')->where(['id' => $uid])
            ->createCommand(Yii::$app->db)->queryOne();

        $exposeBalVal = $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];
        if ($user != null) {
            $mywallet = $user['balance'];
            $user_balance = $user['balance'];
            $user_profit_loss = $user['profit_loss_balance'];
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = $minExpose = 0;
            $sportId = $cBet->sport_id;

            //All
            $marketList = (new \yii\db\Query())
                ->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'bet_status' => 'Pending', 'status' => 1])
                ->andWhere(['!=', 'session_type', 'jackpot' ])
                ->andWhere(['!=', 'market_id', $cBet->market_id])
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

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
                        //echo 'match_odd => '.$balExpose.' , ';
                    }

                }

            }

            // Jackpot
            $marketList6 = (new \yii\db\Query())
                ->select(['event_id'])->from('place_bet')
                ->where(['user_id' => $uid, 'session_type' => 'jackpot', 'bet_status' => 'Pending', 'status' => 1])
                ->groupBy(['event_id'])->createCommand(Yii::$app->db2)->queryAll();

            if ($marketList6 != null) {

                $where = [ 'user_id' => $uid , 'status' => 1 , 'event_id' => $marketList6 ];
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

            // New Teen Patti
            $eventId = $cBet->event_id;
            $marketId = $cBet->market_id;

            $runnersData = (new \yii\db\Query())
                ->select(['selection_id'])->from('events_runners')
                ->where(['event_id' => $eventId])
                ->createCommand(Yii::$app->db2)->queryAll();

            if ($runnersData != null) {
                $balExpose = $balPlus = [];
                foreach ($runnersData as $runners) {
                    $profitLoss = $this->getProfitLossTeenPattiNew($uid,$marketId, $eventId, $runners['selection_id'], $cBet);
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

            if( $balPlus != null ){
                $maxProfit = max($balPlus);
                $maxBal['plus'][] = $maxProfit;
                $this->updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
            }else{
                $this->updateUserProfit( $uid,$eventId,$marketId,0 );
            }


            if (isset($maxBal['expose']) && $maxBal['expose'] != null && array_sum($maxBal['expose']) < 0) {
                $expose_balance = (-1) * (array_sum($maxBal['expose']));
            }

            $mywallet = $mywallet + $user_profit_loss;

            return $data = [ 'balance' => $mywallet, 'expose' => $expose_balance ];


        }
        return $data = ['balance' => 0, 'expose' => 0 ];

    }

    // getProfitLossTeenPattiNew
    public function getProfitLossTeenPattiNew($userId,$marketId, $eventId, $selId, $cBet)
    {
        $total = 0;
        $sessionType = $cBet->session_type;
        // IF RUNNER WIN
        if (null != $userId && $marketId != null && $eventId != null && $selId != null) {

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

    // Function Update User Expose
    public function updateUserExpose( $uid, $eventId, $marketId, $minExpose )
    {
        //$minExpose = (-1)*($minExpose);
        if( $minExpose != 0 ){ $minExpose = (-1)*($minExpose); }
        $where = [ 'user_id' => $uid , 'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];

        $userExpose = (new \yii\db\Query())
            ->select(['id'])->from('user_event_expose')->where($where)
            ->createCommand(Yii::$app->db)->queryOne();

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
                'profit' => 0,
                'updated_at' => time(),
                'status' => 1,
            ];

            \Yii::$app->db->createCommand()->insert('user_event_expose', $addData )->execute();
        }

    }

    // Function Update User Expose
    public function updateUserProfit( $uid, $eventId, $marketId, $maxProfit )
    {
        $where = [ 'user_id' => $uid , 'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];

        $userProfit = (new \yii\db\Query())
            ->select(['id'])->from('user_event_expose')->where($where)
            ->createCommand(Yii::$app->db)->queryOne();

        if( $userProfit != null ){

            $updateData = [
                'profit' => $maxProfit,
                'updated_at' => time(),
            ];

            \Yii::$app->db->createCommand()->update('user_event_expose', $updateData , $where )->execute();

        }else{

            $addData = [
                'user_id' => $uid,
                'event_id' => $eventId,
                'market_id' => $marketId,
                'expose' => 0,
                'profit' => $maxProfit,
                'updated_at' => time(),
                'status' => 1,
            ];

            \Yii::$app->db->createCommand()->insert('user_event_expose', $addData )->execute();
        }

    }

    //action Auth
    public function actionResults()
    {

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        //$fp = fopen('/var/www/dreamexch9.com/html/php/pokerlog/results.txt', 'a');
//            fwrite($fp, json_encode($data));
//            fclose($fp);

        if( isset($data) ){

            //echo '<pre>';print_r($data);die;

            if( isset( $data['result'] ) && isset($data['result'][0]) && isset( $data['runners'] )
                && isset( $data['roundId'] ) ){

                $userArr = $userIds = [];
                $roundId = $data['roundId'];
                $result = $data['result'][0];

                $eventId = $result['gameId'];
                $marketId = $result['marketId'];

                if( isset( $data['betvoid'] ) && $data['betvoid'] == false ){
                    $winner = isset($result['winnerId']) ? $result['winnerId'] : 0;
                }else{
                    $winner = 0;
                }

                foreach ( $data['result'] as $res ){

                    $userArr[] = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'user_id' => $res['userId'],
                        'pl' => $res['downpl']
                    ];
                    //$userIds[] = $res['userId'];
                    $userIds[] = $this->updatedUserResultData($res['userId'],$marketId,$res['downpl']);
                }

                //echo '<pre>';print_r($userArr);die;

                $description = 'Teen Patti ';

                if( isset($data['runners'][0]) && isset($data['runners'][1]) ){

                    $description = $data['runners'][0]['name'].' : '.$data['runners'][0]['status'];
                    $description = $description.' ( '.json_encode($data['runners'][0]['cards']).' )';

                    $description = $description.' '.$data['runners'][1]['name'].' : '.$description = $data['runners'][1]['status'];
                    $description = $description.' ( '.json_encode($data['runners'][1]['cards']).' )';

                }

                $checkData = TeenPattiResult::findOne([ 'game_over' => 0 ,'event_id' => $eventId , 'market_id' => $marketId , 'winner' => $winner ]);

                if( $checkData != null ){

                    $checkData->description = $description;
                    $checkData->winner_result = json_encode($data['runners']);
                    $checkData->user_data = json_encode($userArr);
                    $checkData->round_id = $roundId;
                    $checkData->updated_at = time();
                    $checkData->status = 1;

                    if( $checkData->save() ){
                        $response = [
                            "error" => 0,
                            "result" => $userIds,//json_encode($userIds),
                            "message" => "Result Saved Successfully...!",
                        ];
                    }else{
                        $response = [
                            "error"=> 1,
                            "result" => null,
                            "message"=> "Something Wrong!!!",
                        ];
                    }

                    return json_encode($response);

                }else{

                    $model = new TeenPattiResult();

                    $model->event_id = $eventId;
                    $model->market_id = $marketId;
                    $model->round_id = $roundId;
                    $model->game_over = 0;
                    $model->winner = $winner;
                    $model->description = $description;
                    $model->winner_result = json_encode($data['runners']);
                    $model->user_data = json_encode($userArr);
                    $model->result_data = json_encode($data);
                    $model->created_at = $model->updated_at = time();
                    $model->status = 1;

                    if( $model->save() ){
                        $response = [
                            "error" => 0,
                            "result" => json_encode($userIds),
                            "message" => "Result Saved Successfully...!",
                        ];
                    }else{
                        $response = [
                            "error"=> 1,
                            "result" => null,
                            "message"=> "Something Wrong!!!",
                        ];
                    }

                    return json_encode($response);

                }

            }else{
                $response = [
                    "error"=> 1,
                    "result" => null,
                    "message"=> "Something Wrong!!!",
                ];

                return json_encode($response);
            }

        }

    }


    //action Auth
    public function actionResultsNew()
    {

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( isset($data) ){

            //echo '<pre>';print_r($data);die;

            if( isset( $data['result'] ) && isset($data['result'][0]) && isset( $data['runners'] )
                && isset( $data['roundId'] ) ){

                $userArr = $userIds = [];
                $roundId = $data['roundId'];
                $result = $data['result'][0];

                $eventId = $result['gameId'];
                $marketId = $result['marketId'];

                if( isset( $data['betvoid'] ) && $data['betvoid'] == false ){
                    $winner = isset($result['winnerId']) ? $result['winnerId'] : 0;
                }else{
                    $winner = 0;
                }

                foreach ( $data['result'] as $res ){

//                    $userArr[] = [
//                        'event_id' => $eventId,
//                        'market_id' => $marketId,
//                        'user_id' => $res['userId'],
//                        'pl' => $res['downpl']
//                    ];
//                    $userIds[] = $res['userId'];

                    $this->updatedUserResult($res['userId'],$eventId,$marketId,$winner,$roundId);

                }

//                if( $checkData->save() ){
//                    $response = [
//                        "error" => 0,
//                        "result" => json_encode($userIds),
//                        "message" => "Result Saved Successfully...!",
//                    ];
//                }else{
//                    $response = [
//                        "error"=> 1,
//                        "result" => null,
//                        "message"=> "Something Wrong!!!",
//                    ];
//                }

                //return json_encode($response);

            }else{

                $response = [
                    "error"=> 1,
                    "result" => null,
                    "message"=> "Something Wrong!!!",
                ];

                return json_encode($response);
            }

        }

    }

    //action BetList
    public function updatedUserResult($eventId,$marketId,$winner,$roundId)
    {

        if( $winner != 0 ){

            if( $eventId == 56767 ){
                // Teen Patti
                $sessionType = 'teenpatti'; $marketName = 'Teen Patti';
                //$checkBets = PlaceBet::find()->select('id')
                  //  ->where( [ 'session_type' => 'teenpatti' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                $checkBets = (new \yii\db\Query())
                    ->select(['id'])->from('place_bet')
                    ->where( [ 'session_type' => 'teenpatti' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( $checkBets != null ){
                    $this->gameOverResult( $eventId, $marketId, $winner , 'teenpatti' );
                    $this->transactionResult( $eventId, $marketId , 'teenpatti' );
                }

            }elseif ( $eventId == 67564 ){
                // Poker
                $sessionType = 'poker'; $marketName = 'Poker';
                //$checkBets = PlaceBet::find()->select('id')
                //    ->where( [ 'session_type' => 'poker' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                $checkBets = (new \yii\db\Query())
                    ->select(['id'])->from('place_bet')
                    ->where( [ 'session_type' => 'poker' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( $checkBets != null ){
                    $this->gameOverResult( $eventId, $marketId, $winner , 'poker' );
                    $this->transactionResult( $eventId, $marketId , 'poker' );
                }

            }elseif ( $eventId == 87564 ){
                // Andar Bahar
                $sessionType = 'andarbahar'; $marketName = 'Andar Bahar';

                //$checkBets = PlaceBet::find()->select('id')
                //    ->where( [ 'session_type' => 'andarbahar' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                $checkBets = (new \yii\db\Query())
                    ->select(['id'])->from('place_bet')
                    ->where( [ 'session_type' => 'andarbahar' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( $checkBets != null ){
                    $this->gameOverResult( $eventId, $marketId, $winner , 'andarbahar' );
                    $this->transactionResult( $eventId, $marketId , 'andarbahar' );
                }

            }

        }else{

            // Game Abandoned
            $resultData = 'Abandoned';

            /* Unmatched Bet canceled  */
            $betList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            if( $betList != null ){
                $idsArr = [];
                foreach ( $betList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Canceled'] , ['id'=>$idsArr]);
            }

        }

        if( \Yii::$app->db->createCommand() ->update('teen_patti_result', ['game_over' => 1 ] ,
            ['event_id' => $eventId ,'market_id' => $marketId ] )->execute() ){

            $resultArr = [
                'sport_id' => 999999,
                'event_id' => $eventId,
                'event_name' => 'Teen Patti',
                'market_id' => $marketId,
                'result' => $resultData,
                'market_name' => $marketName .' -> Round #'.$roundId,
                'session_type' => $sessionType,
                'updated_at' => time(),
                'status' => 1,
            ];

            // Update Market Result
            \Yii::$app->db->createCommand()
                ->insert('market_result', $resultArr )->execute();

            // Update User Event Expose
            \Yii::$app->db->createCommand()
                ->update('user_event_expose', ['status' => 2 ] ,
                    ['event_id' => $eventId,'market_id' => $marketId ]  )->execute();

            $response = [
                'status' => 1,
                "success" => [
                    "message" => "Game Over successfully!"
                ]
            ];
        }else{
            $response[ "error" ] = [
                "message" => "Something wrong! event not updated!",
            ];
        }

    }

    //Game Over Result TeenPatti
    public function gameOverResult( $eventId , $marketId , $winResult , $sessionType ){

        if( $eventId != null && $marketId != null && $winResult != null ){

            /*User Win calculation for Back */
            $backWinList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'sec_id' => $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $backWinList != null ){
                foreach ( $backWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Lay*/
            $layWinList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
                ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $layWinList != null ){
                foreach ( $layWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Back */
            $backLossList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
                ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $backLossList != null ){
                foreach ( $backLossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Lay*/
            $layLossList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId , 'event_id' => $eventId , 'sec_id' => $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $layLossList != null ){
                foreach ( $layLossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

        }

    }

    //transaction Result MatchOdds
    public function transactionResult( $eventId, $marketId, $sessionType ){

        $amount = 0;

        $userList = (new \yii\db\Query())
            ->select(['user_id'])->from('place_bet')
            ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->groupBy(['user_id'])->createCommand(Yii::$app->db)->queryAll();


        if( $userList != null ){

            foreach ( $userList as $user ){

                $uId = $user['user_id'];

                $userWinList = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')->from('place_bet')
                    ->where(['user_id' => $user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                    ->andWhere([ 'session_type' => $sessionType,'bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db)->queryOne();

                $userLossList = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')->from('place_bet')
                    ->where( ['user_id' => $user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                    ->andWhere( [ 'session_type' => $sessionType, 'bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db)->queryOne();


                if( !isset($userWinList['winVal']) ){ $profit = 0; }else{ $profit = $userWinList['winVal']; }
                if( !isset($userLossList['lossVal']) ){ $loss = 0; }else{ $loss = $userLossList['lossVal']; }

                $amount = $profit-$loss;

                if( $amount != 0 ){
                    $this->updateTransactionHistory($uId,$eventId,$marketId,$amount,$sessionType);
                }
            }

        }

    }

    // Update Transaction History
    public function updateTransactionHistory($uId,$eventId,$marketId,$amount,$sessionType)
    {
        if( $amount > 0 ){
            $type = 'CREDIT';
        }else{
            $amount = (-1)*$amount;
            $type = 'DEBIT';
        }

        //$cUser = User::find()->select()->where(['id'=>$uId])->asArray()->one();
        $cUser = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where([ 'id' => $uId ] )->createCommand(Yii::$app->db3)->queryOne();


        $pId = $cUser['parent_id'];
        $resultArr = [
            'client_id' => $uId,
            'user_id' => $uId,
            'parent_id' => $pId,
            'child_id' => 0,
            'event_id' => $eventId,
            'market_id' => $marketId,
            'transaction_type' => $type,
            'transaction_amount' => $amount,
            'p_transaction_amount' => 0,
            'c_transaction_amount' => 0,
            'current_balance' => $this->getCurrentBalanceClient($uId,$amount,$type),
            'description' => $this->setDescription($sessionType,$marketId),
            'is_commission' => 0,
            'is_cash' => 0,
            'status' => 1,
            'updated_at' => time(),
            'created_at' => time(),
        ];

        \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
        \Yii::$app->db->close();

        if( $type == 'CREDIT' ){

            $query = new Query();
            $where = [ 'pl.client_id' => $uId ];
            $query->select([ 'pl.user_id as user_id','pl.actual_profit_loss as profit_loss' ,'pl.profit_loss as g_profit_loss', 'u.balance as balance' ] )
                ->from('user_profit_loss as pl')
                ->join('LEFT JOIN','user as u','u.id=pl.user_id')
                ->where( $where )->andWhere(['!=','pl.actual_profit_loss',0])
                ->orderBy(['pl.user_id' => SORT_DESC]);
            $command = $query->createCommand();
            $parentUserData = $command->queryAll();

            //echo '<pre>';print_r($parentUserData);die;

            $childId = $uId;

            foreach ( $parentUserData as $user ){

                $cUser = (new \yii\db\Query())
                    ->select(['parent_id'])->from('user')
                    ->where([ 'id'=>$user['user_id'] ] )->createCommand(Yii::$app->db3)->queryOne();

                $pId = $cUser['parent_id'];

                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'DEBIT');

                $resultArr = [
                    'client_id' => $uId,
                    'user_id' => $user['user_id'],
                    'child_id' => $childId,
                    'parent_id' => $pId,
                    'event_id' => $eventId,
                    'market_id' => $marketId,
                    'transaction_type' => 'DEBIT',
                    'transaction_amount' => $transactionAmount,
                    'p_transaction_amount' => $pTransactionAmount,
                    'c_transaction_amount' => $cTransactionAmount,
                    'current_balance' => $currentBalance,
                    'description' => $this->setDescription($sessionType,$marketId),
                    'is_commission' => 0,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];

                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
                \Yii::$app->db->close();

                $childId = $user['user_id'];

            }

        }else{

            $query = new Query();
            $where = [ 'pl.client_id' => $uId ];
            $query->select([ 'pl.user_id as user_id','pl.actual_profit_loss as profit_loss' ,'pl.profit_loss as g_profit_loss',  'u.balance as balance' ] )
                ->from('user_profit_loss as pl')
                ->join('LEFT JOIN','user as u','u.id=pl.user_id')
                ->where( $where )->andWhere(['!=','pl.actual_profit_loss',0])
                ->orderBy(['pl.user_id' => SORT_DESC]);
            $command = $query->createCommand();
            $parentUserData = $command->queryAll();

            $childId = $uId;

            foreach ( $parentUserData as $user ){

                $cUser = (new \yii\db\Query())
                    ->select(['parent_id'])->from('user')
                    ->where([ 'id'=>$user['user_id'] ] )->createCommand(Yii::$app->db3)->queryOne();

                $pId = $cUser['parent_id'];

                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'CREDIT');

                $resultArr = [
                    'client_id' => $uId,
                    'user_id' => $user['user_id'],
                    'child_id' => $childId,
                    'parent_id' => $pId,
                    'event_id' => $eventId,
                    'market_id' => $marketId,
                    'transaction_type' => 'CREDIT',
                    'transaction_amount' => $transactionAmount,
                    'p_transaction_amount' => $pTransactionAmount,
                    'c_transaction_amount' => $cTransactionAmount,
                    'current_balance' => $currentBalance,
                    'description' => $this->setDescription($sessionType,$marketId),
                    'is_commission' => 0,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];

                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();

                $childId = $user['user_id'];

            }

        }

    }

    // get Current Balance
    public function getCurrentBalanceClient($uid,$amount,$type)
    {
        $user = User::findOne(['id' => $uid ]);
        $balance = 0;
        if( $user != null ){
            $balance = $user->balance;
            $profit_loss_balance = $user->profit_loss_balance;

            if( $type == 'CREDIT' ){
                $profit_loss_balance = $user->profit_loss_balance+$amount;
            }else{
                $profit_loss_balance = $user->profit_loss_balance-$amount;
            }

            $user->profit_loss_balance = $profit_loss_balance;

            if( $user->save(['profit_loss_balance']) ){
                $balance = $user->balance + $user->profit_loss_balance;
            }

        }

        return round($balance,2);
    }

    // get Current Balance
    public function getCurrentBalanceParent($uid,$amount,$type)
    {
        $user = User::findOne(['id' => $uid ]);

        $profit_loss_balance = 0;
        if( $user != null ){
            $profit_loss_balance = $user->profit_loss_balance;
            if( $type == 'CREDIT' ){
                $profit_loss_balance = $user->profit_loss_balance+$amount;
            }else{
                $profit_loss_balance = $user->profit_loss_balance-$amount;
            }

            $user->profit_loss_balance = $profit_loss_balance;

            if( $user->save(['profit_loss_balance']) ){
                //$balance = $user->balance + $user->profit_loss_balance;
                $profit_loss_balance = $user->profit_loss_balance;
            }

        }

        return round($profit_loss_balance,2);
    }

    // Function to get the Description
    public function setDescription($sessionType,$marketId)
    {
        $description = 'Teen Patti';
        $round = $this->getRoundId($marketId);
        if( $sessionType == 'teenpatti' ){
            $description = 'Teen Patti > Round #'.$round;
        }else if( $sessionType == 'poker' ){
            $description = 'Poker > Round #'.$round;
        }else if( $sessionType == 'andarbahar' ){
            $description = 'Andar Bahar > Round #'.$round;
        }

        return $description;
    }

    //actionIndex
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


    //action BetList
    public function updatedUserResultData($uid,$marketId,$pl)
    {
        $wallet = $newExpose = $newPl = 0;
        $user = (new \yii\db\Query())
            ->select(['balance','expose_balance','profit_loss_balance'])
            ->from('user')->where([ 'id' => $uid , 'status' => 1 ])
            ->one();
        if( $user != null ){

            $newPl = round($user['profit_loss_balance'])+round($pl);

            $expose = (new \yii\db\Query())
                ->select(['expose'])
                ->from('user_event_expose')->where([ 'user_id' => $uid , 'market_id' => $marketId ])
                ->one();

            if( $expose != null ){
                $newExpose = round($user['expose_balance'])-round($expose['expose']);
            }

            $wallet = round($user['balance'])+round($newPl)-round($newExpose);

        }

        return ['wallet' => $wallet , 'exposure' => 0 , 'userId' => $uid ];


    }

    //action BetList
    public function actionBetList()
    {
        if( isset( $_GET['id'] ) ){
            $uid = $_GET['id'];

            $betList = [];

            $betList = (new \yii\db\Query())
                ->select('*')
                ->from('place_bet')
                ->where([ 'user_id' => $uid , 'session_type' => 'teenpatti' , 'status' => 1 ])
                ->all();

            return json_encode($betList);
        }


    }

    // Cricket: Get User Name Data
    public function getUserName($id){

        if( $id != null ){

            $user = (new \yii\db\Query())
                ->select(['name','username'])
                ->from('user')
                ->where([ 'id' => $id , 'status' => 1 ])
                ->one();

            if( $user != null ){
                return $user['name'].' ['.$user['username'].'] ';
            }else{
                return 'undefine';
            }

        }
        return 'undefine';

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
