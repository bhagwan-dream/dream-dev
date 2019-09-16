<?php
namespace api\modules\v1\modules\users\controllers;

use api\modules\v1\modules\users\models\AuthToken;
use Yii;
use yii\db\Query;
use yii\httpclient\Client;
use yii\helpers\ArrayHelper;

use common\models\User;
use common\models\Setting;
use common\models\EventsPlayList;
use common\models\MarketType;
use common\models\ManualSession;
use api\modules\v1\modules\users\models\PlaceBet;
use common\models\TransactionHistory;
use common\models\UserProfitLoss;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionMatchOddData;
use common\models\EventsRunner;
use yii\helpers\Json;


class DashbordController extends \common\controllers\aController
{
    private $marketIdsArr = [];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin', 'agent' , 'agent1','agent2' , 'sessionuser' ,'sessionuser2' , 'subadmin'],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];

        return $behaviors;
    }

    public function actionClientLogout()
    {

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( !isset($role['admin']) ){
            return $response;
        }

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'status' ] ) && $r_data[ 'status' ] == 'OK' ){
                //TRUNCATE TABLE `auth_token`
                AuthToken::deleteAll([ '!=', 'user_id' , 1 ]);
                User::updateAll([ 'is_login' => 0 ],'id != 1');
                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => "All Client Logout Successfully!"
                    ]
                ];

            }
        }

        return $response;

    }

    //action Market Suspend
    public function actionMarketSuspend(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ] )
                && isset( $r_data[ 'suspended' ] )){

                $type = $r_data[ 'type' ];
                $marketId = $r_data[ 'id' ];

                if( $type == 'match_odd' ){
                    $event = EventsPlayList::findOne( ['market_id' => $marketId] );
                }else if( $type == 'match_odd2' ){
                    $event = ManualSessionMatchOdd::findOne( ['market_id' => $marketId] );
                }else if( $type == 'fancy' ){
                    $event = ManualSession::findOne( ['market_id' => $marketId] );
                }else if( $type == 'fancy2' ){
                    $event = MarketType::findOne( ['market_id' => $marketId] );
                }else{
                    $event = null;
                }

                if( $event != null ){
                    $event->suspended = 'N';
                    if( $r_data[ 'suspended' ] == 'N' ){
                        $event->suspended = 'Y';
                    }
                    $event->updated_at = time();
                    if( $event->save() ){
                        $suspendedStatus = $event->suspended;
                        $this->redisUpdateData($marketId,$suspendedStatus,$type);

                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Save successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Save not changed!"
                        ];
                    }

                }
            }
        }

        return $response;
    }

    //Redis Update
    public function redisUpdateData($mId,$status,$type){

        if( $type == 'match_odd' ){

            $key = $mId;
            $matchOddData = [];
            $cache = Yii::$app->cache;

            if( $cache->exists($key) ) {
                $matchOddData = $cache->get($key);
                $matchOddData = json_decode($matchOddData,true);

                if( $matchOddData != null ){
                    $matchOddData['status'] = $status == 'Y' ? 'SUSPENDED' : 'OPEN';
                    $matchOddData['time'] = round(microtime(true) * 1000);
                    $cache->set( $key , json_encode($matchOddData) );
                }

            }

        }else if( $type == 'match_odd2' ){

            $key = $this->BOOKMAKER_KEY.$mId;
            $bookMakerData = [];
            $cache = Yii::$app->cache;
            if( $cache->exists($key) ) {
                $bookMakerData = $cache->get($key);
                $bookMakerData = json_decode($bookMakerData,true);

                if ($bookMakerData != null) {
                    $bookMakerData['suspended'] = $status;
                    $bookMakerData['time'] = round(microtime(true) * 1000);

                    if (isset($bookMakerData['runners'])) {
                        $i = 0;
                        foreach ($bookMakerData['runners'] as $runners) {
                            $bookMakerData['runners'][$i]['suspended'] = $status;
                            $i++;
                        }

                    }

                    $cache->set($key, json_encode($bookMakerData));
                }
            }

        }else if( $type == 'fancy' ){

            $key = $this->FANCY_KEY.$mId;
            $sessionData = [];
            $cache = Yii::$app->cache;

            if( $cache->exists($key) ) {
                $sessionData = $cache->get($key);
                $sessionData = json_decode($sessionData,true);

                if( $sessionData != null ){
                    $sessionData['suspended'] = $status;
                    $sessionData['time'] = round(microtime(true) * 1000);
                    $cache->set( $key , json_encode($sessionData) );
                }

            }

        }else if( $type == 'fancy2' ){

            $key = $mId;
            $fancyData = [];
            $cache = Yii::$app->cache;

            if( $cache->exists($key) ) {
                $fancyData = $cache->get($key);
                $fancyData = json_decode($fancyData,true);

                if( $fancyData != null ){
                    $fancyData['suspended'] = $status;
                    $fancyData['time'] = round(microtime(true) * 1000);
                    $cache->set( $key , json_encode($fancyData) );
                }

            }

        }

        return;

    }

    //actionEventList New
    public function actionEventList()
    {

        $today = date('Ymd');
        $tomorrow = date('Ymd' , strtotime($today . ' +7 day') );
		$lastday = date('Ymd' , strtotime($today . ' -7 day') );

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        $eventData = $dataList = $teenPattiData = $data = [];

        if( isset($role['sessionuser2']) ){

            $eventList = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('cricket_jackpot')
                ->where([ 'status' => 1 , 'game_over'=>'NO' ])
                ->groupBy(['event_id'])->createCommand(Yii::$app->db3)->queryAll();

            if( $eventList != null ){

                $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['game_over'=>'NO','status'=>1 , 'sport_id' => 4 , 'event_id' => $eventList])
                    ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                    ->orderBy(['event_time' => SORT_ASC])
                    ->all();
            }

        }else{

            $eventData = (new \yii\db\Query())
                ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
                ->from('events_play_list')
                ->where(['game_over'=>'NO','status'=>1 ])
                ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                ->orderBy(['event_time' => SORT_ASC])
                ->all();

            $teenPattiData = (new \yii\db\Query())
                ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running' ,'status'])
                ->from('events_play_list')
                ->where(['sport_id' => 999999,'game_over'=>'NO','status' => 1 ])
                ->orderBy(['id' => SORT_ASC])
                ->all();

        }

        if( $teenPattiData != null ){

            foreach ( $teenPattiData as $event ){

                $dataList['teenpatti'][] = [
                    'market_id' => $event['market_id'],
                    'event_id' => $event['event_id'],
                    'event_name' => $event['event_name'],
                    'event_time' => time(),
                    'play_type' => $event['play_type'],
                    'status' => 'unblock',
                    'suspended' => $event['suspended'],
                    'ball_running' => $event['ball_running'],
                    'runners' => null,
                ];
            }

        }

        if( $eventData != null ){
            $blockList = $this->checkUnBlockStatus($uid);

            $today = date('Ymd');
            $tomorrow = date('Ymd' , strtotime($today . ' +1 day') );

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( !isset($role['admin']) && !isset($role['agent']) && !isset($role['subadmin']) && !isset($role['sessionuser']) && !isset($role['sessionuser2'])){
                $dataList['isFootball'] = ['sportId'=> 1 , 'status' => $this->checkUnBlockSport($uid,1)];
                $dataList['isTennis'] = ['sportId'=> 2 , 'status' => $this->checkUnBlockSport($uid,2)];
                $dataList['isCricket'] = ['sportId'=> 4 , 'status' => $this->checkUnBlockSport($uid,4)];
            }else{
                $dataList['isFootball'] = ['sportId'=> 1 , 'status' => $this->checkUnBlockSport($uid,1)];
                $dataList['isTennis'] = ['sportId'=> 2 , 'status' => $this->checkUnBlockSport($uid,2)];
                $dataList['isCricket'] = ['sportId'=> 4 , 'status' => $this->checkUnBlockSport($uid,4)];
            }

            foreach ( $eventData as $event ){

                $eventDate = date('Ymd',( $event['event_time']/1000 ));
                //if( $today == $eventDate || $tomorrow == $eventDate ){

                    $status = 'unblock';

                    //print_r($blockList);die;

                    if( in_array( $event['event_id'], $blockList )){
                        $status = 'block';
                    }

                    if( ( !isset($role['agent']) && !isset($role['subadmin']) && !isset($role['admin']) ) && !in_array( $event['event_id'], $this->checkUnBlockList($uid) ) ){

                        $betCount = 0;$clients = [];

//                        if( isset($role['agent1']) ){
//                            $clients = $this->getAllClientForSuperMaster($uid);
//                        }elseif( isset($role['agent2']) ){
//                            $clients = $this->getAllClientForMaster($uid);
//                        }

                        if( isset($role['client']) && $role['client'] != null ){
                            array_push( $clients , $uid );
                        }else{
                            $clients = $this->getClientListByUserId($uid);
                        }

                        $betCount = (new \yii\db\Query())
                            ->select(['id'])
                            ->from('place_bet')
                            ->where(['event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
                            ->andWhere(['IN','user_id',$clients])
                            ->count();

                        $eventName = $eventNameJackpot = $event['event_name'];

                        if( $betCount > 0 ){
                            $eventName = $event['event_name'] . ' ( Total Bets: '.$betCount.' )';
                        }


                        $cache = \Yii::$app->cache;
                        $data = $cache->get($event['market_id']);
                        $data = json_decode($data);

                        if( $event['sport_id'] == '1' ){
                            $dataList['football'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '2' ){
                            $dataList['tennis'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '4' ){

                            if( $this->isJackpot($event['event_id']) ){

//                                $betCountJackpot = (new \yii\db\Query())
//                                    ->select(['id'])
//                                    ->from('place_bet')
//                                    ->where(['session_type' => 'jackpot','event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
//                                    ->count();
//
//                                if( $betCountJackpot > 0 ){
//                                    $eventNameJackpot = $eventNameJackpot . ' ( Bets: '.$betCountJackpot.' )';
//                                }

                                $dataList['jackpot'][] = [
                                    'market_id' => $event['market_id'],
                                    'event_id' => $event['event_id'],
                                    'event_name' => $eventNameJackpot,
                                    'event_time' => ( $event['event_time'] / 1000 ),
                                    'play_type' => $event['play_type'],
                                    'status' => $status,
                                    'suspended' => $event['suspended'],
                                    'ball_running' => $event['ball_running'],
                                    'runners' => $data,
                                ];
                            }

                            $dataList['cricket'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                    }

                    if( isset($role['admin']) || isset($role['subadmin']) || isset($role['agent']) ){

                        $betCount = 0;

                        $betCount = (new \yii\db\Query())
                            ->select(['id'])
                            ->from('place_bet')
                            ->where(['event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
                            ->count();

                        $eventName = $eventNameJackpot = $event['event_name'];

                        if( $betCount > 0 ){
                            $eventName = $event['event_name'] . ' ( Total Bets: '.$betCount.' )';
                        }

                        $cache = \Yii::$app->cache;
                        $data = $cache->get($event['market_id']);
                        $data = json_decode($data);

                        if( $event['sport_id'] == '1' ){
                            $dataList['football'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '2' ){
                            $dataList['tennis'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                        if( $event['sport_id'] == '4' ){

                            if( $this->isJackpot($event['event_id']) ){

//                                $betCountJackpot = (new \yii\db\Query())
//                                    ->select(['id'])
//                                    ->from('place_bet')
//                                    ->where(['session_type' => 'jackpot','event_id' => $event['event_id'],'bet_status' => 'Pending','status'=>1])
//                                    ->count();
//
//                                if( $betCountJackpot > 0 ){
//                                    $eventNameJackpot = $eventNameJackpot . ' ( Bets: '.$betCountJackpot.' )';
//                                }

                                $dataList['jackpot'][] = [
                                    'market_id' => $event['market_id'],
                                    'event_id' => $event['event_id'],
                                    'event_name' => $eventNameJackpot,
                                    'event_time' => ( $event['event_time'] / 1000 ),
                                    'play_type' => $event['play_type'],
                                    'status' => $status,
                                    'suspended' => $event['suspended'],
                                    'ball_running' => $event['ball_running'],
                                    'runners' => $data,
                                ];
                            }

                            $dataList['cricket'][] = [
                                'market_id' => $event['market_id'],
                                'event_id' => $event['event_id'],
                                'event_name' => $eventName,
                                'event_time' => ( $event['event_time'] / 1000 ),
                                'play_type' => $event['play_type'],
                                'status' => $status,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                                'runners' => $data,
                            ];
                        }
                    }

                    if( !isset($role['admin']) && in_array( 1, $this->checkUnBlockSportList($uid) ) ){
                        $dataList['football'] = [];
                        $dataList['isFootballBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                    if( !isset($role['admin']) && in_array( 2, $this->checkUnBlockSportList($uid) ) ){
                        $dataList['tennis'] = [];
                        $dataList['isTennisBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                    if( !isset($role['admin']) && in_array( 4, $this->checkUnBlockSportList($uid) ) ){
                        $dataList['cricket'] = [];
                        $dataList['isCricketBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                    }
                //}

            }

        }

        return [ "status" => 1 , "data" => [ "items" => $dataList ] ];

    }



    //isJackpot
    public function isJackpot($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('cricket_jackpot')
            ->where(['event_id' => $eventId, 'status' => 1 ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    //actionAddEvent
    public function actionNewEventList()
    {

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['admin']) ){

            $url = 'http://52.208.223.36/betfair/get_latest_event_list/';

            $client = new Client();

            $nodes['football'] = $client->get($url . 1);
            $nodes['tennis'] = $client->get($url . 2);
            $nodes['cricket'] = $client->get($url . 4);


            $responseData = $client->batchSend($nodes);


            $dataList['cricket'] = $dataList['tennis'] = $dataList['football'] = [];

            if ($responseData != null) {

                foreach ($responseData as $index=>$resData) {

                    if ($resData->isOk) {

                        $resData = json_decode($resData->content);

                        if( $index == 'cricket' ){

                            //echo '<pre>';print_r($resData);die;

                            if( !isset($resData->Error) ) {

                                foreach ($resData as $event) {

                                    if ($event->marketName == 'Match Odds') {

                                        $sportId = 4;
                                        $marketId = $event->marketId;
                                        $eventId = $event->event->id;
                                        $eventName = $event->event->name;
                                        if (isset($event->competition)) {
                                            $eventLeague = $event->competition->name;
                                        } else {
                                            $eventLeague = $event->event->name;
                                        }
                                        $eventTime = strtotime($event->marketStartTime) * 1000;
                                        $playType = 'UPCOMING';

                                        $runnerArr = [];
                                        foreach ($event->runners as $runners) {

                                            $runnerArr[] = [
                                                'selection_id' => $runners->selectionId,
                                                'runnerName' => $runners->runnerName,
                                            ];

                                        }

                                        $dataList['cricket'][] = [
                                            'sport_id' => $sportId,
                                            'market_id' => $marketId,
                                            'event_id' => $eventId,
                                            'event_name' => $eventName,
                                            'event_league' => $eventLeague,
                                            'play_type' => $playType,
                                            'event_time' => ($eventTime / 1000),
                                            'runners' => $runnerArr

                                        ];

                                    }

                                }
                            }

                        }
                        if( $index == 'tennis' ){

                            //echo '<pre>';print_r($resData);die;

                            if( !isset($resData->Error) ) {

                                foreach ($resData as $event) {

                                    if ($event->marketName == 'Match Odds') {
                                        $sportId = 2;
                                        $marketId = $event->marketId;
                                        $eventId = $event->event->id;
                                        $eventName = $event->event->name;
                                        if (isset($event->competition)) {
                                            $eventLeague = $event->competition->name;
                                        } else {
                                            $eventLeague = $event->event->name;
                                        }
                                        $eventTime = strtotime($event->marketStartTime) * 1000;
                                        $playType = 'UPCOMING';

                                        $runnerArr = [];
                                        foreach ($event->runners as $runners) {

                                            $runnerArr[] = [
                                                'selection_id' => $runners->selectionId,
                                                'runnerName' => $runners->runnerName,
                                            ];

                                        }

                                        $dataList['tennis'][] = [
                                            'sport_id' => $sportId,
                                            'market_id' => $marketId,
                                            'event_id' => $eventId,
                                            'event_name' => $eventName,
                                            'event_league' => $eventLeague,
                                            'play_type' => $playType,
                                            'event_time' => ($eventTime / 1000),
                                            'runners' => $runnerArr

                                        ];
                                    }

                                }
                            }

                        }
                        if( $index == 'football' ){

                            //echo '<pre>';print_r($resData);die;

                            if( !isset($resData->Error) ){

                                foreach ( $resData as $event ){

                                    if( $event->marketName == 'Match Odds' ){
                                        $sportId = 1;
                                        $marketId = $event->marketId;
                                        $eventId = $event->event->id;
                                        $eventName = $event->event->name;
                                        if( isset($event->competition) ){ $eventLeague = $event->competition->name;
                                        }else{ $eventLeague = $event->event->name; }

                                        $eventTime = strtotime($event->marketStartTime) * 1000;
                                        $playType = 'UPCOMING';

                                        $runnerArr = [];
                                        foreach ( $event->runners as $runners ){

                                            $runnerArr[] = [
                                                'selection_id' => $runners->selectionId,
                                                'runnerName' => $runners->runnerName,
                                            ];

                                        }

                                        $dataList['football'][] = [
                                            'sport_id' => $sportId,
                                            'market_id' => $marketId,
                                            'event_id' => $eventId,
                                            'event_name' => $eventName,
                                            'event_league' => $eventLeague,
                                            'play_type' => $playType,
                                            'event_time' => ( $eventTime / 1000 ),
                                            'runners' => $runnerArr

                                        ];
                                    }

                                }

                            }



                        }


                    }
                }
            }


        }

        return [ "status" => 1 , "data" => [ "items" => $dataList ] ];

    }

    //actionAddNewEvent
    public function actionAddNewEvent()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['admin']) ){

            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );

                $sportId = $r_data['sport_id'];
                $eventId = $r_data['event_id'];
                $marketId = $r_data['market_id'];
                $eventLeague = $r_data['event_league'];
                $eventName = $r_data['event_name'];
                $eventTime = $r_data['event_time'];

                $check = (new \yii\db\Query())
                    ->select(['id'])->from('events_play_list')
                    ->where(['sport_id' => $sportId, 'event_id' => $eventId, 'market_id' => $marketId])
                    ->one();

                if ($check != null) {
                    // nothing Do
                    //echo '<pre>';print_r($check);die;

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "This event is already added!!"
                        ]
                    ];

                    return $response;

                } else {

                    //echo '<pre>';print_r($r_data);die;

                    $model = new EventsPlayList();

                    $upcoming = Setting::findOne([ 'key' => 'UPCOMING_EVENT_SETTING' , 'status' => 1 ]);

                    if( $upcoming != null ){

                        $dataUpcoming = json_decode( $upcoming->value );

                        if( $dataUpcoming != null ){

                            //Upcoming Setting
                            $model->upcoming_min_stake = $dataUpcoming->upcoming_min_stake;
                            $model->upcoming_max_stake = $dataUpcoming->upcoming_max_stake;
                            $model->upcoming_max_profit = $dataUpcoming->upcoming_max_profit;
                            $model->max_odd_limit = $dataUpcoming->max_odd_limit;
                            $model->accept_unmatch_bet = $dataUpcoming->accept_unmatch_bet;

                        }

                    }else{
                        //Upcoming Setting
                        $model->upcoming_min_stake = 1;
                        $model->upcoming_max_stake = 1000;
                        $model->upcoming_max_profit = 1;
                        $model->max_odd_limit = 30;
                        $model->accept_unmatch_bet = 0;
                    }

                    if( $sportId == 4 ){
                        //In play Setting Cricket
                        $cricketSetting = Setting::findOne([ 'key' => 'CRICKET_EVENT_SETTING' , 'status' => 1 ]);
                        if( $cricketSetting != null ){
                            $dataCricket = json_decode( $cricketSetting->value );
                            $model->max_stack = $dataCricket->max_stack;
                            $model->min_stack = $dataCricket->min_stack;
                            $model->max_profit = $dataCricket->max_profit;
                            $model->max_profit_limit = $dataCricket->max_profit_limit;
                            $model->max_profit_all_limit = $dataCricket->max_profit_all_limit;
                            $model->bet_delay = $dataCricket->bet_delay;
                        }else{
                            $model->max_stack = 50000;
                            $model->min_stack = 1000;
                            $model->max_profit = 100000;
                            $model->max_profit_limit = 100000;
                            $model->max_profit_all_limit = 5000000;
                            $model->bet_delay = 5;
                        }

                    }else if( $sportId == 1 ){
                        //In play Setting Football
                        $footballSetting = Setting::findOne([ 'key' => 'FOOTBALL_EVENT_SETTING' , 'status' => 1 ]);
                        if( $footballSetting != null ){
                            $dataFootball = json_decode( $footballSetting->value );
                            $model->max_stack = $dataFootball->max_stack;
                            $model->min_stack = $dataFootball->min_stack;
                            $model->max_profit = $dataFootball->max_profit;
                            $model->max_profit_limit = $dataFootball->max_profit_limit;
                            $model->max_profit_all_limit = $dataFootball->max_profit_all_limit;
                            $model->bet_delay = $dataFootball->bet_delay;
                        }else{
                            $model->max_stack = 500000;
                            $model->min_stack = 1000;
                            $model->max_profit = 500000;
                            $model->max_profit_limit = 10000000;
                            $model->max_profit_all_limit = 50000000;
                            $model->bet_delay = 5;
                        }
                    }else if( $sportId == 2 ){
                        //In play Setting Tennis
                        $tennisSetting = Setting::findOne([ 'key' => 'TENNIS_EVENT_SETTING' , 'status' => 1 ]);
                        if( $tennisSetting != null ){
                            $dataTennis = json_decode( $tennisSetting->value );
                            $model->max_stack = $dataTennis->max_stack;
                            $model->min_stack = $dataTennis->min_stack;
                            $model->max_profit = $dataTennis->max_profit;
                            $model->max_profit_limit = $dataTennis->max_profit_limit;
                            $model->max_profit_all_limit = $dataTennis->max_profit_all_limit;
                            $model->bet_delay = $dataTennis->bet_delay;
                        }else {
                            $model->max_stack = 500000;
                            $model->min_stack = 1000;
                            $model->max_profit = 500000;
                            $model->max_profit_limit = 10000000;
                            $model->max_profit_all_limit = 50000000;
                            $model->bet_delay = 5;
                        }
                    }else{
                        //In play Setting Tennis,Football
                        $model->max_stack = 500000;
                        $model->min_stack = 1000;
                        $model->max_profit = 500000;
                        $model->max_profit_limit = 10000000;
                        $model->max_profit_all_limit = 50000000;
                        $model->bet_delay = 5;
                    }

                    $model->sport_id = $sportId;
                    $model->event_id = $eventId;
                    $model->market_id = $marketId;
                    $model->event_league = $eventLeague;
                    $model->event_name = $eventName;
                    $model->event_time = $eventTime * 1000;
                    $model->play_type = 'UPCOMING';

                    if ($model->save()) {

                        $runnerModelCheck = (new \yii\db\Query())
                            ->select(['id'])->from('events_runners')
                            ->where(['market_id' => $marketId])
                            ->one();

                        if ($runnerModelCheck == null) {

                            if (isset($r_data['runners']) && $r_data['runners'] != null ) {

                                $runnersArr = $r_data['runners'];

                                $dataRnr = [];
                                foreach ($runnersArr as $runners) {

                                    $dataRnr[] = [
                                        'event_id' => $eventId,
                                        'market_id' => $marketId,
                                        'selection_id' => $runners['selection_id'],
                                        'runner' => $runners['runnerName'],
                                        'created_at' => time(),
                                        'updated_at' => time(),
                                    ];

                                }

                                if ($dataRnr != null) {
                                    \Yii::$app->db->createCommand()->batchInsert('events_runners',
                                        ['event_id', 'market_id', 'selection_id', 'runner', 'created_at', 'updated_at'], $dataRnr)->execute();

                                    $AllUser = $dataUsr = [];
                                    $role = \Yii::$app->authManager->getRolesByUser($uid);
                                    if (isset($role['admin']) && $role['admin'] != null) {
                                        $AllUser = $this->getAllUserForAdmin($uid);
                                        array_push($AllUser, $uid);
                                        if ($AllUser != null) {
                                            foreach ($AllUser as $user) {
                                                $dataUsr[] = [
                                                    'user_id' => $user,
                                                    'event_id' => $eventId,
                                                    'market_id' => $marketId,
                                                    'market_type' => 'all',
                                                    'byuser' => $uid
                                                ];
                                            }

                                        }
                                        if ($dataUsr != null) {
                                            \Yii::$app->db->createCommand()->batchInsert('event_market_status',
                                                ['user_id', 'event_id', 'market_id', 'market_type', 'byuser'], $dataUsr)->execute();
                                        }

                                    }

                                    $response = [
                                        'status' => 1,
                                        "success" => [
                                            "message" => "This event is successfully added!!"
                                        ]
                                    ];

                                    return $response;

                                }
                            }
                        }

                    }else{

                        $response = [
                            'status' => 0,
                            "error" => [
                                "message" => "Something Wrong!!"
                            ]
                        ];

                        return $response;

                    }


                }

            }

        }

        return $response;

    }

    //actionEventDetails First
    public function actionEventDetailsFirst()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $isBookButton = true;
            $eventId = \Yii::$app->request->get( 'id' );

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( isset($role['subadmin']) || isset($role['agent']) ){
                $uid = 1;
            }

            $detailType = 'main';

            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );

                if( isset($r_data['id']) ){
                    $uid = $r_data['id'];

                    $role = \Yii::$app->authManager->getRolesByUser($uid);

                    if( isset($role['client']) ){
                        $isBookButton = false;
                    }
                }

                if( isset($r_data['type']) ){
                    $detailType = $r_data['type'];
                }


            }

            if( in_array($eventId, $this->checkUnBlockList($uid) ) ){
                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];
                return $response;exit;
            }

            $cUser = User::find()->select(['name','username'])->where(['id'=>$uid])->asArray()->one();
            $cUserName = 'No User Found!';
            if( $cUser != null ){
                //$cUserName = $cUser['name'].' [ '.$cUser['username'].' ] ';
                $cUserName = $cUser['username'];
            }

            $eventData = (new \yii\db\Query())
                ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->from('events_play_list')
                ->where(['event_id'=> $eventId,'game_over'=>'NO','status'=>1])
                ->createCommand(Yii::$app->db1)->queryOne();

            $eventArr = $betList = [];
            if( $eventData != null ){
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];

                if( $sportId == '1' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId,false);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Football',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];

                }else if( $sportId == '2' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId,false);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Tennis',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];
                }else if( $sportId == '4' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId,false);
                    $matchodd2Data = $this->getDataManualMatchOdd($uid,$eventId,false);
                    $fancy2Data = $this->getDataFancy($uid,$eventId,false);
                    $fancyData = $this->getDataManualSessionFancy($uid,$eventId,false);
                    $lotteryData = $this->getDataLottery($uid,$eventId,false);
                    //$jackpotData = $this->getDataJackpot($uid,$eventId , false);
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
                        //'jackpot' => $jackpotData
                    ];
                }else if( $sportId == '4' && $detailType == 'jackpot' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId,false);
                    $jackpotData = $this->getDataJackpot($uid,$eventId , false);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'jackpot' => $jackpotData
                    ];
                }else if( $sportId == '999999' && $detailType == 'teenpatti' ){
                    $teenPattiData = $this->getDataTeenPatti($uid,$marketId,$eventId,false);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Teen Patti',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'teenpatti' => $teenPattiData,
                    ];
                }

                if( $detailType == 'jackpot' ){
                    $betList = $this->getBetListJackpot($uid,$eventId);
                }else if( $detailType == 'teenpatti' ){
                    $betList = $this->getBetListTeenPatti($uid,$eventId);
                }else{
                    $betList = $this->getBetList($uid,$eventId);
                }

                $this->marketIdsArr[] = [
                    'type' => 'match_odd',
                    'market_id' => $marketId,
                    'event_id' => $eventId
                ];

                $response = [ "status" => 1 , "data" => [ "items" => $eventArr , 'betList' => $betList,  'marketIdsArr' => $this->marketIdsArr , 'cUserName' => $cUserName , 'isBookButton' => $isBookButton ] ];

            }else{

                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];

            }


        }

        return $response;
    }

    //actionEventDetails New
    public function actionEventDetails()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $isBookButton = true;
            $eventId = \Yii::$app->request->get( 'id' );

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( isset($role['subadmin']) ){
                $uid = 1;
            }
            $detailType = 'main';
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );

                if( isset($r_data['id']) ){
                    $uid = $r_data['id'];

                    $role = \Yii::$app->authManager->getRolesByUser($uid);

                    if( isset($role['client']) ){
                        $isBookButton = false;
                    }
                }

                if( isset($r_data['type']) ){
                    $detailType = $r_data['type'];
                }


            }

            // For Other Games
            if( in_array($eventId, $this->checkUnBlockList($uid) ) ){
                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];
                return $response;exit;
            }

            $cUser = User::find()->select(['name','username'])->where(['id'=>$uid])->asArray()->one();
            $cUserName = 'No User Found!';
            if( $cUser != null ){
                //$cUserName = $cUser['name'].' [ '.$cUser['username'].' ] ';
                $cUserName = $cUser['username'];
            }

            $eventData = (new \yii\db\Query())
                ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->from('events_play_list')
                ->where(['event_id'=> $eventId,'game_over'=>'NO','status'=>1])
                ->createCommand(Yii::$app->db1)->queryOne();

            $eventArr = $betList = [];
            if( $eventData != null ){
                $marketId = $eventData['market_id'];
                $title = $eventData['event_name'];
                $sportId = $eventData['sport_id'];
                if( $sportId == '1' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Football',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];

                }else if( $sportId == '2' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Tennis',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData
                    ];
                }else if( $sportId == '4' && $detailType == 'main' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $matchodd2Data = $this->getDataManualMatchOdd($uid,$eventId);
                    $fancy2Data = $this->getDataFancyArr($eventId);
                    $fancyData = $this->getDataManualSessionFancyArr($eventId);
                    $lotteryData = $this->getDataLottery($uid,$eventId);
                    //$jackpotData = $this->getDataJackpot($uid,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'match_odd2' => $matchodd2Data,
                        //'fancy2' => [],
                        //'fancy' => [],
                        'lottery' => $lotteryData,
                        //'jackpot' => $jackpotData
                    ];
                }else if( $sportId == '4' && $detailType == 'jackpot' ){
                    $matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $jackpotData = $this->getDataJackpot($uid,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'match_odd' => $matchoddData,
                        'jackpot' => $jackpotData
                    ];
                }else if( $sportId == '4' && $detailType == 'fancy' ){
                    //$matchoddData = $this->getDataMatchOdd($uid,$marketId,$eventId);
                    $fancy2Data = $this->getDataFancy($uid,$eventId);
                    $fancyData = $this->getDataManualSessionFancy($uid,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Cricket',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        //'match_odd' => $matchoddData,
                        'fancy2' => $fancy2Data,
                        'fancy' => $fancyData,
                    ];
                }else if( $sportId == '999999' && $detailType == 'teenpatti' ){
                    $teenPattiData = $this->getDataTeenPatti($uid,$marketId,$eventId);
                    $eventArr = [
                        'title' => $title,
                        'sport' => 'Teen Patti',
                        'event_id' => $eventId,
                        'sport_id' => $sportId,
                        'teenpatti' => $teenPattiData,
                    ];
                }

                //$betList = $this->getBetList($uid,$eventId);

                $this->marketIdsArr[] = [
                    'type' => 'match_odd',
                    'market_id' => $marketId,
                    'event_id' => $eventId
                ];

                $response = [ "status" => 1 , "data" => [ "items" => $eventArr , 'marketIdsArr' => $this->marketIdsArr , 'cUserName' => $cUserName , 'isBookButton' => $isBookButton ] ];

            }else{

                $response = [ "status" => 1 , "data" => null , "msg" => "This event is closed !!" ];

            }


        }

        return $response;
    }

    //actionEventDetailBetList
    public function actionEventDetailBetList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( isset($role['subadmin']) || isset($role['agent']) ){
                $uid = 1;
            }

            if( in_array($eventId , [ 56767, 67564, 87564 ]) ){
                $betList = $this->getBetListTeenPatti($uid,$eventId);
            }else{
                $betList = $this->getBetList($uid,$eventId);
            }

            if( $betList != null ){
                $response = [ "status" => 1 , "data" => [ 'items' => $betList ] ];
            }else{
                $response = [ "status" => 1 , "data" => [ 'items' => [] ] ];
            }

        }

        return $response;
    }

    //actionEventBetList
    public function actionEventBetList()
    {
        $pagination = []; $filters = [];

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $models = [];$type = null;$title = 'No Title';
        if( null != \Yii::$app->request->get( 'id' ) ){

            $eventId = \Yii::$app->request->get( 'id' );
            $marketTypeArr = ['match_odd'=>'Match Odd' , 'match_odd2'=>'Book Maker' , 'fancy'=>'Fancy' , 'fancy2'=>'Fancy 2' , 'lottery' => 'Lottery' , 'jackpot' => 'Jackpot'];
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                $type = trim($r_data['type']);

                $filter_args = ArrayHelper::toArray( $request_data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;

            }

            if( in_array($eventId , [56767,67564,87564]) ){
                if( $eventId == 56767 ){
                    $title = 'Teen Patti';
                }elseif( $eventId == 67564 ){
                    $title = 'Teen Patti - Poker';
                }else{
                    $title = 'Teen Patti - Andar Bahar';
                }

            }else{
                $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eventId , 'status' => 1])->asArray()->one();

                if( $event != null ){
                    $title = $event['event_name'].' - '.$marketTypeArr[$type];
                    if( $type == null ){
                        $title = $event['event_name'];
                    }
                }
            }



            $where = ['session_type' => $type,'event_id' => $eventId , 'bet_status' => 'Pending'];

            $query = PlaceBet::find()
                ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                ->where($where);

            //$user = User::findOne(\Yii::$app->user->id);
            //$role = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->id);

            //if( !isset( $role['admin'] ) ){

            $uid = \Yii::$app->user->id;

            if( isset($r_data['uid']) && $r_data['uid'] != null ){
                $uid = trim($r_data['uid']);
            }

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if( isset($role['subadmin']) ){
                $uid = 1;
            }

            //}

            $countQuery = clone $query; $count =  $countQuery->count();

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                    $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
                }
            }

            if( isset($role['agent1']) ){
                //$allUser = $this->getAllClientForSuperMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
                $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

            }else if( isset($role['agent2']) ){
                //$allUser = $this->getAllClientForSuperMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
                $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

            }else if( isset($role['client']) ){
                $query->andWhere( [ 'user_id' => $uid ] );
            }

            $query->andWhere([ "status" => 1 , 'session_type' => $type,'event_id' => $eventId ]);

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

            $uid = \Yii::$app->user->id;

            if( isset($r_data['uid']) && $r_data['uid'] != null ){
                $uid = trim($r_data['uid']);
            }

            $user = User::find()->select(['name','username'])->where(['id' => $uid])->asArray()->one();

            if( $user != null ){
                $title = $user['name'].' [ '.$user['name'].' ] - '.$title;
            }

            $response = [ "status" => 1 , "data" => [ "title" => $title,"items" => $models , "count" => $count ] ];
        }

        return $response;

    }

    //actionTrashBetList
    public function actionTrashBetList()
    {
        $pagination = []; $filters = [];

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $models = [];$type = null;$title = 'No Title';
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->user->id;
            $eventId = \Yii::$app->request->get( 'id' );
            $marketTypeArr = ['match_odd'=>'Match Odd' , 'match_odd2'=>'Book Maker' , 'fancy'=>'Fancy' , 'fancy2'=>'Fancy 2' , 'lottery' => 'Lottery'];
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                $type = trim($r_data['type']);

                $filter_args = ArrayHelper::toArray( $request_data );
                if( isset( $filter_args[ 'filter' ] ) ){
                    $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
                }

                $pagination = $filter_args;

            }

            $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eventId , 'status' => 1])->asArray()->one();

            if( $event != null ){
                $title = $event['event_name'].' - '.$marketTypeArr[$type];
                if( $type == null ){
                    $title = $event['event_name'];
                }
            }


            $query = PlaceBet::find()
                ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                ->where(['event_id' => $eventId]);

            //$user = User::findOne(\Yii::$app->user->id);
            $role = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->id);

            if( !isset( $role['admin'] ) ){

                $uid = \Yii::$app->user->id;
                if(isset($role['agent1']) && $role['agent1'] != null){
                    //$allUser = $this->getAllClientForSuperMaster($uid);
                    $allUser = $this->getClientListByUserId($uid);
                }else{
                    //$allUser = $this->getAllClientForMaster($uid);
                    $allUser = $this->getClientListByUserId($uid);
                }

                if( $allUser != null ){
                    $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );
                }

            }

            $countQuery = clone $query; $count =  $countQuery->count();

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                    $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
                }
            }

            $query->andWhere([ "status" => 0 , 'bet_status' => 'Pending' ]);

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

            $response = [ "status" => 1 , "data" => [ "title" => $title,"items" => $models , "count" => $count ] ];
        }

        return $response;

    }

    // Teen Patti bet list
    public function getBetListTeenPatti($uid,$eventId)
    {
        $items = [];

        if( $eventId == '56767' ){ //teen patti
            $marketName = 'Teen Patti';
            $sessionType = 'teenpatti';
        }elseif ($eventId == '67564'){ //poker
            $marketName = 'Poker';
            $sessionType = 'poker';
        }elseif ($eventId == '87564'){ //andar bahar
            $marketName = 'Andar Bahar';
            $sessionType = 'andarbahar';
        }

        $where = [ 'event_id' => $eventId , 'session_type' => $sessionType ,'status' => 1 ,'bet_status' => 'Pending'];

        $query = PlaceBet::find()
            ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
            ->where($where);

        //$user = User::findOne(\Yii::$app->user->id);
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( !isset( $role['admin'] ) && !isset( $role['sessionuser2'] ) ){

            //$uid = \Yii::$app->user->id;
            $allUser = [];
            if(isset($role['agent1']) && $role['agent1'] != null) {
                //$allUser = $this->getAllClientForSuperMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else if(isset($role['agent2']) && $role['agent2'] != null){
                //$allUser = $this->getAllClientForMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else{
                array_push( $allUser  , $uid );
            }

            $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

        }

        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

        $teenPattiData = $teenPattiData2 = [];

        if( $models != null ){
            $i = 0;
            foreach ( $models as $data ){

                if( count($teenPattiData) < 10 ){
                    $teenPattiData[] = $data;
                }

                $teenPattiData2[] = $data;

                $i++;
            }

        }

        $teenPattiDataArr = []; $count = 0;

        if( $teenPattiData != null ){
            $count = count( $teenPattiData2 );
            $teenPattiDataArr = [ 'data'=>$teenPattiData , 'count' => $count  ];
        }

        $items = [
            'teenPattiData' => $teenPattiDataArr,
        ];

        return $items;

    }

    // Teen Patti bet list
    public function getBetListJackpot($uid,$eventId)
    {
        $items = [];

        $where = [ 'event_id' => $eventId , 'session_type' => 'jackpot' ,'status' => 1 ,'bet_status' => 'Pending'];

        $query = PlaceBet::find()
            ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
            ->where($where);

        //$user = User::findOne(\Yii::$app->user->id);
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( !isset( $role['admin'] ) && !isset( $role['sessionuser2'] ) ){

            //$uid = \Yii::$app->user->id;
            $allUser = [];
            if(isset($role['agent1']) && $role['agent1'] != null) {
                //$allUser = $this->getAllClientForSuperMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else if(isset($role['agent2']) && $role['agent2'] != null){
                //$allUser = $this->getAllClientForMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else{
                array_push( $allUser  , $uid );
            }

            $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

        }

        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

        $jackpotData = $jackpotData2 = [];

        if( $models != null ){
            $i = 0;
            foreach ( $models as $data ){

                if( count($jackpotData) < 10 ){
                    $jackpotData[] = $data;
                }

                $jackpotData2[] = $data;

                $i++;
            }

        }

        $jackpotDataArr = []; $count = 0;

        if( $jackpotData != null ){
            $count = count( $jackpotData2 );
            $jackpotDataArr = [ 'data'=>$jackpotData , 'count' => $count  ];
        }

        $items = [
            'jackpotData' => $jackpotDataArr,
        ];

        return $items;

    }

    // BookMarket for Event Id
    public function getBetList($uid,$eventId)
    {
        $items = [];

        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset( $role['sessionuser2'] ) ){
            $where = ['session_type' => 'jackpot','event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
        }else{
            $where = ['event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
        }

        $query = PlaceBet::find()
            ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
            ->where($where);

        //$user = User::findOne(\Yii::$app->user->id);
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( !isset( $role['admin'] ) && !isset( $role['sessionuser2'] ) ){

            //$uid = \Yii::$app->user->id;
            $allUser = [];
            if(isset($role['agent1']) && $role['agent1'] != null) {
                //$allUser = $this->getAllClientForSuperMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else if(isset($role['agent2']) && $role['agent2'] != null){
                //$allUser = $this->getAllClientForMaster($uid);
                $allUser = $this->getClientListByUserId($uid);
            }else{
                array_push( $allUser  , $uid );
            }

            $query->andWhere( [ 'IN' , 'user_id' , $allUser ] );

        }

        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

        $matchOddMatchData = $matchOddUnMatchData = $matchOdd2MatchData = $matchOdd2UnMatchData = $fancyMatchData = $fancyUnMatchData = $fancy2MatchData = $fancy2UnMatchData = $lotteryData = $jackpotData = $teenPattiData = [];
        $matchOddMatchData2 = $matchOddUnMatchData2 = $matchOdd2MatchData2 = $matchOdd2UnMatchData2 = $fancyMatchData2 = $fancyUnMatchData2 = $fancy2MatchData2 = $fancy2UnMatchData2 = $lotteryData2 = $jackpotData2 = $teenPattiData = [];

        if( $models != null ){
            $i = 0;
            foreach ( $models as $data ){

                if( count($matchOddMatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 1 ){
                        $matchOddMatchData[] = $data;
                    }
                }
                if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 1 ){
                    $matchOddMatchData2[] = $data;
                }


                if( count($matchOddUnMatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 0 ){
                        $matchOddUnMatchData[] = $data;
                    }
                }

                if( $data['session_type'] == 'match_odd' && $data['match_unmatch'] == 0 ){
                    $matchOddUnMatchData2[] = $data;
                }


                if( count($matchOdd2MatchData) < 10 ){
                    if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 1 ){
                        $matchOdd2MatchData[] = $data;
                    }
                }

                if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 1 ){
                    $matchOdd2MatchData2[] = $data;
                }


//                 if( count($matchOdd2UnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'match_odd2' && $data['match_unmatch'] == 0 ){
//                         $matchOdd2UnMatchData[] = $data;
//                     }
//                 }
//                 $matchOdd2UnMatchData2[] = $data;

                if( count($fancyMatchData) < 10 ){
                    if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 1 ){
                        $fancyMatchData[] = $data;
                    }
                }
                if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 1 ){
                    $fancyMatchData2[] = $data;
                }


//                 if( count($fancyUnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'fancy' && $data['match_unmatch'] == 0 ){
//                         $fancyUnMatchData[] = $data;
//                     }
//                 }
//                 $fancyUnMatchData2[] = $data;

                if( count($fancy2MatchData) < 10 ){
                    if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 1 ){
                        $fancy2MatchData[] = $data;
                    }
                }

                if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 1 ){
                    $fancy2MatchData2[] = $data;
                }

//                 if( count($fancy2UnMatchData) <= 10 ){
//                     if( $data['session_type'] == 'fancy2' && $data['match_unmatch'] == 0 ){
//                         $fancy2UnMatchData[] = $data;
//                     }
//                 }
//                 $fancy2UnMatchData2[] = $data;

                if( count($lotteryData) < 10 ){
                    if( $data['session_type'] == 'lottery' ){
                        $lotteryData[] = $data;
                    }
                }

                if( $data['session_type'] == 'lottery' ){
                    $lotteryData2[] = $data;
                }

                if( count($jackpotData) < 10 ){
                    if( $data['session_type'] == 'jackpot' ){
                        $jackpotData[] = $data;
                    }
                }

                if( $data['session_type'] == 'jackpot' ){
                    $jackpotData2[] = $data;
                }

                if( count($teenPattiData) < 10 ){
                    if( $data['session_type'] == 'teenpatti' ){
                        $teenPattiData[] = $data;
                    }
                }

                if( $data['session_type'] == 'teenpatti' ){
                    $teenPattiData2[] = $data;
                }

                $i++;
            }

        }

        $matchOddMatchDataArr = $matchOddUnMatchDataArr = $matchOdd2MatchDataArr = $matchOdd2UnMatchDataArr = $fancyMatchDataArr = $fancyUnMatchDataArr = $fancy2MatchDataArr = $fancy2UnMatchDataArr = $lotteryDataArr = $jackpotDataArr = $teenpattiDataArr = [];
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count7 = $count8 = 0;
        if( $matchOddMatchData != null ){
            $count1 = count( $matchOddMatchData2 );
            $matchOddMatchDataArr = [ 'data'=>$matchOddMatchData , 'count' => $count1  ];
        }
        if( $matchOddUnMatchData != null ){
            $count2 = count( $matchOddUnMatchData2 );
            $matchOddUnMatchDataArr = [ 'data'=>$matchOddUnMatchData , 'count' => $count2  ];
        }
        if( $matchOdd2MatchData != null ){
            $count3 = count( $matchOdd2MatchData2 );
            $matchOdd2MatchDataArr = [ 'data'=>$matchOdd2MatchData , 'count' => $count3  ];
        }
//         if( $matchOdd2UnMatchData != null ){
//             $matchOdd2UnMatchDataArr = [ 'data'=>$matchOdd2UnMatchData , 'count' => count( $matchOdd2UnMatchData2 )  ];
//         }
        if( $fancyMatchData != null ){
            $count4 = count( $fancyMatchData2 );
            $fancyMatchDataArr = [ 'data'=>$fancyMatchData , 'count' => $count4  ];
        }
//         if( $fancyUnMatchData != null ){
//             $fancyUnMatchDataArr = [ 'data'=>$fancyUnMatchData , 'count' => count( $fancyUnMatchData2 )  ];
//         }
        if( $fancy2MatchData != null ){
            $count5 = count( $fancy2MatchData2 );
            $fancy2MatchDataArr = [ 'data'=>$fancy2MatchData , 'count' => $count5  ];
        }
//         if( $fancy2UnMatchData != null ){
//             $fancy2UnMatchDataArr = [ 'data'=>$fancy2UnMatchData , 'count' => count( $fancy2UnMatchData2 )  ];
//         }
        if( $lotteryData != null ){
            $count6 = count( $lotteryData2 );
            $lotteryDataArr = [ 'data'=>$lotteryData , 'count' => $count6  ];
        }

        if( $jackpotData != null ){
            $count7 = count( $jackpotData2 );
            $jackpotDataArr = [ 'data'=>$jackpotData , 'count' => $count7  ];
        }

        if( $teenPattiData != null ){
            $count8 = count( $teenPattiData2 );
            $teenpattiDataArr = [ 'data'=>$teenPattiData , 'count' => $count8  ];
        }

        $items = [
            'matchOddMatchData' => $matchOddMatchDataArr,
            'matchOddUnMatchData' => $matchOddUnMatchDataArr,
            'matchOdd2MatchData' => $matchOdd2MatchDataArr,
            //'matchOdd2UnMatchData' => $matchOdd2UnMatchDataArr,
            'fancyMatchData' => $fancyMatchDataArr,
            //'fancyUnMatchData' => $fancyUnMatchDataArr,
            'fancy2MatchData' => $fancy2MatchDataArr,
            //'fancy2UnMatchData' => $fancy2UnMatchDataArr,
            'lotteryData' => $lotteryDataArr,
            'jackpotData' => $jackpotDataArr,
            'teenpattiData' => $teenpattiDataArr
        ];

        return $items;

    }

    //Event: getDataTeenPatti
    public function getDataTeenPatti($uid,$marketId,$eventId,$withBook=true)
    {
        $marketListArr = null;

        $event = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit','bet_delay'])
            ->from('events_play_list')
            ->where(['game_over'=>'NO','status'=>1])
            ->andWhere(['market_id' => $marketId , 'event_id' => $eventId])->createCommand(Yii::$app->db1)->queryOne();
        //echo '<pre>';print_r($event);die;

        if( $event != null ){

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
            $betDelay = $event['bet_delay'];

            //$isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');

            if( $eventId == '56767' ){ //teen patti
                $marketName = 'Teen Patti';
                $sessionType = 'teenpatti';
            }elseif ($eventId == '67564'){ //poker
                $marketName = 'Poker';
                $sessionType = 'poker';
            }elseif ($eventId == '87564'){ //andar bahar
                $marketName = 'Andar Bahar';
                $sessionType = 'andarbahar';
            }

            $runnerData = (new \yii\db\Query())
                ->select(['selection_id','runner'])
                ->from('events_runners')
                ->where(['event_id' => $eventId ])
                ->createCommand(Yii::$app->db1)->queryAll();

            if( $runnerData != null ){

                $i=0;
                foreach( $runnerData as $runner ){
                    //$back = $lay = ['price' => ' - ', 'size' => ' - '];
                    $runnerName = $runner['runner'];
                    $selectionId = $runner['selection_id'];

                    $runnersArr[] = [
                        'slug' => 'teenpatti',
                        'sportId' => $sportId,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'selectionId' => $selectionId,
                        'runnerName' => $runnerName,
                        'profit_loss' => ($withBook ? $this->getProfitLossOnBetTeenPatti($uid,$eventId,$selectionId,$sessionType):0),
                        'is_profitloss' => 0,
                        'sessionType' => $sessionType,
                        'exchange' => [
                            'back' => '-',
                            'lay' => '-',
                        ]
                    ];
                    $i++;
                }

            }

            $marketListArr = [
                'sportId' => $sportId,
                'slug' => 'teenpatti',
                'sessionType' => $sessionType,
                'marketId' => $marketId,
                'eventId' => $eventId,
                'suspended' => $suspended,
                'ballRunning' => $ballRunning,
                //'is_favorite' => $isFavorite,
                'time' => $time,
                'marketName' => $marketName,
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

    //Event: getDataMatchOdd
    public function getDataMatchOdd($uid,$marketId,$eventId,$withBook=true)
    {
        $marketListArr = null;

        // if user is jackpot user
//        $role = \Yii::$app->authManager->getRolesByUser($uid);
//        if( isset($role['sessionuser2']) ){
//            return $marketListArr;
//        }

        $event = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','max_profit_all_limit','bet_delay'])
            ->from('events_play_list')
            ->where(['game_over'=>'NO','status'=>1])
            ->andWhere(['market_id' => $marketId , 'event_id' => $eventId])->createCommand(Yii::$app->db1)->queryOne();
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
            $betDelay = $event['bet_delay'];

            //$isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');

            $runnerData = (new \yii\db\Query())
                ->select(['selection_id','runner'])
                ->from('events_runners')
                ->where(['event_id' => $eventId ])
                ->createCommand(Yii::$app->db1)->queryAll();

            if( $runnerData != null ){

                $cache = \Yii::$app->cache;
                $oddsData = $cache->get($marketId);
                $oddsData = json_decode($oddsData);
                if( $oddsData != null && isset($oddsData->odds) ){
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
                        //'is_favorite' => $isFavorite,
                        'runnerName' => $runnerName,
                        'profit_loss' => ($withBook ? $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$selectionId,'match_odd'):0),
                        'is_profitloss' => 0,
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
                //'is_favorite' => $isFavorite,
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
    public function getDataManualMatchOdd($uid,$eventId,$withBook=true)
    {
        $items = null;

        // if user is jackpot user
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return $items;
        }

        $market = (new \yii\db\Query())
            ->select(['id','event_id','market_id','suspended','ball_running','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
            ->from('manual_session_match_odd')
            ->where(['status' => 1 , 'game_over' => 'NO' , 'event_id' => $eventId])
            ->createCommand(Yii::$app->db1)->queryOne();
        //echo '<pre>';print_r($market);die;
        $runners = [];
        if($market != null){

            $marketId = $market['market_id'];
            $minStack = $market['min_stack'];
            $maxStack = $market['max_stack'];
            $maxProfit = $market['max_profit'];
            $maxProfitLimit = $market['max_profit_limit'];
            $betDelay = $market['bet_delay'];

            $suspended = $market['suspended'];
            $ballRunning = $market['ball_running'];


            $matchOddData = (new \yii\db\Query())
                ->select(['id','sec_id','runner','lay','back','suspended','ball_running'])
                ->from('manual_session_match_odd_data')
                ->andWhere( [ 'market_id' => $marketId ] )
                ->createCommand(Yii::$app->db1)->queryAll();

            if( $matchOddData != null ){
                foreach( $matchOddData as $data ){

                    if( $suspended != 'Y' ){
                        $suspended = $data['suspended'];
                    }else{
                        $suspended = 'N';
                    }
                    if( $ballRunning != 'Y' ){
                        $ballRunning = $data['suspended'];
                    }else{
                        $ballRunning = 'N';
                    }

                    $profitLoss = ($withBook ? $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$data['sec_id'],'match_odd2') : 0);
                    $runners[] = [
                        'id' => $data['id'],
                        'sportId' => 4,
                        'market_id' => $marketId,
                        'event_id' => $eventId,
                        'sec_id' => $data['sec_id'],
                        'runner' => $data['runner'],
                        'profitloss' => $profitLoss,
                        'is_profitloss' => 0,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'lay' => [
                            'price' => $data['lay'],
                            'size' => $data['lay'] == 0 ? '-' : rand(1234,9999),
                        ],
                        'back' => [
                            'price' => $data['back'],
                            'size' => $data['back'] == 0 ? '-' : rand(1234,9999),
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
                //'is_favorite' => $this->isFavorite($eventId,$marketId,'match_odd2'),
                'minStack' => $minStack,
                'maxStack' => $maxStack,
                'maxProfit' => $maxProfit,
                'maxProfitLimit' => $maxProfitLimit,
                'betDelay' => $betDelay,
                'runners' => $runners,
            ];

            $this->marketIdsArr[] = [
                'type' => 'match_odd2',
                'market_id' => $marketId,
                'event_id' => $eventId
            ];
        }

        return $items;
    }

    //Event: getDataFancyArr
    public function getDataFancyArr($eventId)
    {
        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('market_type')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->orderBy(['id' => SORT_DESC])
            ->createCommand(Yii::$app->db1)->queryAll();

        if( $marketList != null ) {

            foreach ($marketList as $market) {
                $this->marketIdsArr[] = [
                    'type' => 'fancy2',
                    'market_id' => $market['market_id'],
                    'event_id' => $eventId
                ];
            }
        }

    }

    //Event: getDataFancy
    public function getDataFancy($uid,$eventId,$withBook=true)
    {
        $items = [];

        // if user is jackpot user
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return $items;
        }

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('market_type')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->orderBy(['id' => SORT_DESC])
            ->createCommand(Yii::$app->db1)->queryAll();
        //echo '<pre>';print_r($marketList);die;


        if( $marketList != null ){

            foreach ( $marketList as $market ){

                $marketId = $market['market_id'];
                $suspended = $market['suspended'];
                $ballRunning = $market['ball_running'];
                $minStack = $market['min_stack'];
                $maxStack = $market['max_stack'];
                $maxProfit = $market['max_profit'];
                $maxProfitLimit = $market['max_profit_limit'];
                $betDelay = $market['bet_delay'];
                $isBook = $this->isBookOn($uid,$marketId,'fancy2');
                $maxLoss = ($withBook ? $this->getMaxLossOnFancy($uid,$isBook,$eventId,$marketId,'fancy2') : 0);

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
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    //'status' => $status,
                    'sportId' => 4,
                    'slug' => 'cricket',
                    'is_book' => $isBook,
                    //'is_favorite' => $this->isFavorite($market['event_id'],$market['market_id'],'fancy2'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'maxLoss' => $maxLoss,
                    'data' => $dataVal,
                ];

                $this->marketIdsArr[] = [
                    'type' => 'fancy2',
                    'market_id' => $market['market_id'],
                    'event_id' => $eventId
                ];
            }

        }

        return $items;
    }

    //Event: getDataManualSessionFancyArr
    public function getDataManualSessionFancyArr($eventId)
    {
        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('manual_session')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->orderBy(['id' => SORT_DESC])
            ->createCommand(Yii::$app->db1)->queryAll();

        if($marketList != null){
            foreach($marketList as $data){
                $this->marketIdsArr[] = [
                    'type' => 'fancy',
                    'market_id' => $data['market_id'],
                    'event_id' => $eventId
                ];
            }
        }
    }

    //Event: getDataManualSessionFancy
    public function getDataManualSessionFancy($uid,$eventId,$withBook=true)
    {
        $items = [];

        // if user is jackpot user
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return $items;
        }

        $marketList = (new \yii\db\Query())
            ->select('*')
            ->from('manual_session')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->orderBy(['id' => SORT_DESC])
            ->createCommand(Yii::$app->db1)->queryAll();

        if($marketList != null){
            $dataVal = [];
            foreach($marketList as $data){

                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];
                $isBook = $this->isBookOn($uid,$data['market_id'],'fancy');
                $maxLoss = ($withBook ? $this->getMaxLossOnFancy($uid,$isBook,$eventId,$data['market_id'],'fancy') : 0);

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
                    'is_book' => $this->isBookOn($uid,$data['market_id'],'fancy'),
                    //'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'fancy'),
                    'minStack' => $minStack,
                    'maxStack' => $maxStack,
                    'maxProfit' => $maxProfit,
                    'maxProfitLimit' => $maxProfitLimit,
                    'betDelay' => $betDelay,
                    'maxLoss' => $maxLoss,
                    'data' => $dataVal,

                ];

                $this->marketIdsArr[] = [
                    'type' => 'fancy',
                    'market_id' => $data['market_id'],
                    'event_id' => $eventId
                ];
            }
        }

        return $items;
    }

    //Event: getDataLottery
    public function getDataLottery($uid,$eventId,$withBook=true)
    {
        $items = [];

        // if user is jackpot user
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return $items;
        }

        $marketList = (new \yii\db\Query())
            ->select(['id','market_id','event_id','title','rate','min_stack','max_stack','max_profit','max_profit_limit','bet_delay'])
            ->from('manual_session_lottery')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
        ->orderBy(['id' => SORT_DESC])
            ->all();
        //echo '<pre>';print_r($marketList);die;
        if($marketList != null){
            foreach($marketList as $data){

                $minStack = $data['min_stack'];
                $maxStack = $data['max_stack'];
                $maxProfit = $data['max_profit'];
                $maxProfitLimit = $data['max_profit_limit'];
                $betDelay = $data['bet_delay'];

                $numbers = [];
                for($n=0;$n<10;$n++){

                    $profitLoss = ($withBook ? $this->getProfitLossOnBetLottery($uid,$data['event_id'], $data['market_id'] , $n ) : 0);

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
                    //'is_book' => $this->isBookOn($data['market_id'],'lottery'),
                    //'is_favorite' => $this->isFavorite($data['event_id'],$data['market_id'],'lottery'),
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
    public function getDataJackpot($uid,$eventId,$withBook=true)
    {
        $items = [];

        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['sessionuser2']) ){
            $uid = 1;
        }

        $marketList = (new \yii\db\Query())
            ->select(['id','market_id','event_id','team_a','team_b','team_a_player','team_b_player','rate','suspended'])
            ->from('cricket_jackpot')
            ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
            ->orderBy(['id' => SORT_ASC])
            ->createCommand(Yii::$app->db2)->queryAll();
        //echo '<pre>';print_r($marketList);die;

        if($marketList != null){
            foreach($marketList as $data){

                $profitLoss = ( $withBook ? $this->getProfitLossOnBetJackpot($uid,$data['event_id'], $data['market_id'] ) : 0);

                $items[] = [
                    'id' => $data['id'],
                    'sportId' => 4,
                    'event_id' => $data['event_id'],
                    'market_id' => $data['market_id'],
                    'team_a' => $data['team_a'],
                    'team_b' => $data['team_b'],
                    'team_a_player' => $data['team_a_player'],
                    'team_b_player' => $data['team_b_player'],
                    'rate' => $data['rate'],
                    'suspended' => $data['suspended'],
                    'profitLoss' => $profitLoss,
                    'is_book' => 0,
                    'is_favorite' => 0,

                ];

            }
        }

        return $items;
    }

    //action BlockUnblock Event
    public function actionBlockUnblockEvent()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $AllUser = [];
            $uId = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uId);


            if( isset($role['admin']) && $role['admin'] != null ){
                $AllUser = $this->getAllUserForAdmin($uId);
                foreach ( $AllUser as $user ){
                    $AllUserS = $this->getAllUserForSuperMaster($user);
                    foreach ( $AllUserS as $usersupid ){
                        array_push($AllUser,$usersupid);
                    }
                }

            }elseif(isset($role['agent1']) && $role['agent1'] != null){

                $AllUser = $this->getAllUserForSuperMaster($uId);
                foreach ( $AllUser as $user ){
                    $AllUserS = $this->getAllUserForMaster($user);
                     foreach ( $AllUserS as $usermsid ){
                        array_push($AllUser,$usermsid);
                    }
                 }

                    // echo "<pre>"; print_r($AllUser); exit;
            }elseif(isset($role['agent2']) && $role['agent2'] != null){
                $AllUser = $this->getAllUserForMaster($uId);
            }

            array_push($AllUser,$uId);
            if( $AllUser != null ){
                //echo '<pre>';print_r($AllUser);die;

                $marketId = $r_data['market_id'];
                $eventId = $r_data['event_id'];
                $type = $r_data['type'];


                foreach ( $AllUser as $user ){

                    $where = [
                        'user_id' => $user,
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'market_type' => $type,
                        //'byuser' => $uId
                    ];

                    $check = (new \yii\db\Query())
                        ->select('*')->from('event_market_status')
                        ->where($where)->one();

                    if( $check != null ){

                        \Yii::$app->db->createCommand()
                            ->delete('event_market_status', $where)
                            ->execute();
                        $message = "Event Block successfully!";

                    }else{

                        if( $r_data['status']=='block') {

                            $where = [
                                'user_id' => $user,
                                'event_id' => $eventId,
                                'market_id' => $marketId,
                                'market_type' => $type,
                                'byuser' => $uId
                            ];

                            \Yii::$app->db->createCommand()
                                ->insert('event_market_status', $where)->execute();

                        }

                        $message = "Event Unblock successfully!";

                    }

                }

                $url = 'http://52.208.223.36/api/betfair/activate_b/'.$marketId;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $responseData = curl_exec($ch);
                curl_close($ch);

                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => $message
                    ]
                ];

            }

        }

        return $response;
    }

    //action BlockUnblock Sport
    public function actionBlockUnblockSport()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $AllUser = [];
            $uId = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uId);

            if( isset($role['admin']) && $role['admin'] != null ){
                $AllUser = $this->getAllUserForAdmin($uId);
            }elseif(isset($role['agent1']) && $role['agent1'] != null){
                $AllUser = $this->getAllUserForSuperMaster($uId);
            }elseif(isset($role['agent2']) && $role['agent2'] != null){
                $AllUser = $this->getAllUserForMaster($uId);
            }
            array_push($AllUser,$uId);
            if( $AllUser != null ){
                //echo '<pre>';print_r($AllUser);die;
                foreach ( $AllUser as $user ){

                    $sportId = $r_data['sport_id'];

                    $where = [
                        'user_id' => $user,
                        'sport_id' => $sportId,
                        'byuser' => $uId
                    ];

                    $check = (new \yii\db\Query())
                        ->select('*')->from('event_status')
                        ->where($where)->one();

                    if( $check != null ){

                        \Yii::$app->db->createCommand()
                            ->delete('event_status', $where)
                            ->execute();
                        $message = "Sport Block successfully!";

                    }else{

                        \Yii::$app->db->createCommand()
                            ->insert('event_status', $where )->execute();

                        $message = "Sport Unblock successfully!";

                    }

                }

                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => $message
                    ]
                ];

            }

        }

        return $response;
    }

    //check database function
    public function checkUnBlockList($uid)
    {
        //$uId = \Yii::$app->user->id;

        $newList = [];

        $listArr = (new \yii\db\Query())
            ->select(['event_id'])->from('event_market_status')
            ->where(['user_id'=>$uid,'market_type' => 'all' ])
            ->andWhere(['!=','byuser',$uid])->createCommand(\Yii::$app->db1)->queryAll();

        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
        }
        return $newList;

    }

    //check database function
    public function checkUnBlockStatus($uid)
    {
        //$uid = \Yii::$app->user->id;

        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset( $role['subadmin'] ) || isset( $role['sessionuser'] ) ){
            $uid = 1;
        }

        $newList = [];

        $listArr = (new \yii\db\Query())
            ->select(['event_id'])->from('event_market_status')
            ->where(['user_id'=>$uid,'market_type' => 'all' , 'byuser' => $uid ])
            ->createCommand(\Yii::$app->db1)->queryAll();

        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
        }
        return $newList;

    }

    //check database function
    public function checkUnBlocMarketkList($uid,$eventId,$marketId,$type)
    {
        //$uid = \Yii::$app->user->id;
        $market = (new \yii\db\Query())
            ->select(['market_id'])->from('event_market_status')
            ->where(['user_id'=>$uid,'event_id'=>$eventId,'market_id'=>$marketId,'market_type' => $type,'byuser'=>$uid ])
            ->createCommand(\Yii::$app->db1)->queryOne();

        if( $market != null ){
            return true;
        }else{
            return false;
        }

    }

    //check database function
    public function checkUnBlockSport($uid,$sportId)
    {
        //$uid = \Yii::$app->user->id;
        $market = (new \yii\db\Query())
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id'=>$uid,'sport_id'=>$sportId,'byuser'=>$uid ])
            ->createCommand(\Yii::$app->db1)->queryOne();

        if( $market != null ){
            return 'block';
        }else{
            return 'unblock';
        }

    }

    //check database function
    public function checkUnBlockSportList($uid)
    {
        //$uId = \Yii::$app->user->id;

        $newList = [];

        $listArr = (new \yii\db\Query())
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id'=>$uid ])
            ->andWhere(['!=','byuser',$uid])->createCommand(\Yii::$app->db1)->queryAll();

        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }
        }
        return $newList;

    }

    //check database function
    public function checkUnBlockListUser($uid)
    {
        //$uId = \Yii::$app->user->id;

        $newList = [];
        $listArr = (new \yii\db\Query())
            ->select(['event_id'])->from('event_market_status')
            ->where(['user_id'=>$uid,'market_type' => 'all','byuser'=>$uid ])
            ->createCommand(\Yii::$app->db1)->queryAll();

        if( $listArr != null ){

            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }

        }

        return $newList;

    }

    // Cricket: isBookOn
    public function isBookOn($uid,$marketId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $AllClients = [];
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            array_push( $AllClients , $uid );
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){

            $where = [ 'bet_status' => 'Pending','status' => 1,'session_type' => $sessionType,'market_id' => $marketId ];
            $andWhere = ['IN','user_id', $AllClients];

            $findBet = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where($where)->andWhere($andWhere)
                ->createCommand(\Yii::$app->db1)->queryOne();

            if( $findBet != null ){
                return '1';
            }
            return '0';

        }

    }

    // Cricket: User Market BookList
    public function actionUserMarketBookList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $titleData = $userData = $totalData = [];
            if( isset( $r_data['event_id'] ) && isset( $r_data['user_id'] ) ){

                if( $r_data['user_id'] != '' || $r_data['user_id'] != null ){
                    $uid = $r_data['user_id'];
                }else{
                    $uid = \Yii::$app->user->id;
                    $role = \Yii::$app->authManager->getRolesByUser($uid);
                    if( isset( $role['subadmin'] ) ){
                        $uid = 1;
                    }
                }

                $eventId = $r_data['event_id'];
                $userList = User::findAll([ 'parent_id' => $uid , 'status' => 1 ]);
                //echo '<pre>';print_r($userList);die;

                if( isset( $r_data['typ'] ) && $r_data['typ'] == 'match_odd' ){

                    $sessionTyp = $r_data['typ'];

                    $eventRunner = EventsRunner::findAll(['event_id' => $eventId]);
                    $runners = [];$marketId = '';
                    if( $eventRunner != null ){

                        foreach ( $eventRunner as $runner ){

                            $marketId = $runner->market_id;

                            $runners[] = [
                                'secid' => $runner->selection_id,
                                'name' => $runner->runner
                            ];

                        }

                    }

                }

                if( isset( $r_data['typ'] ) && $r_data['typ'] == 'match_odd2' ){

                    $sessionTyp = $r_data['typ'];

                    $bookMaker = ManualSessionMatchOdd::find()->select(['market_id'])->where(['event_id' => $eventId])->one();

                    if( $bookMaker != null ){

                        $eventRunner = ManualSessionMatchOddData::findAll(['market_id' => $bookMaker->market_id ]);
                        $runners = [];$marketId = '';
                        if( $eventRunner != null ){

                            foreach ( $eventRunner as $runner ){

                                $marketId = $runner->market_id;

                                $runners[] = [
                                    'secid' => $runner->sec_id,
                                    'name' => $runner->runner
                                ];

                            }

                        }

                    }

                }


                if( $userList != null && $runners != null ){
                    $runner1 = $runner2 = $runner3 = 0;
                    $runnerName1 = $runnerName2 = $runnerName3 = '';

                    if( isset( $runners[0]['name'] ) ){
                        $runnerName1 = $runners[0]['name'];
                    }
                    if( isset( $runners[1]['name'] ) ){
                        $runnerName2 = $runners[1]['name'];
                    }
                    if( isset( $runners[2]['name'] ) ){
                        $runnerName3 = $runners[2]['name'];
                    }

                    $titleData = [
                        '0' => 'User Name',
                        '1' => $runnerName1,
                        '2' => $runnerName2,
                        '3' => $runnerName3,
                    ];
                    $t1 = $t2 = $t3 = null;
                    foreach ( $userList as $user ){

                        if( isset( $runners[0]['secid'] ) ){
                            $runner1 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                            $t1[] = round($runner1,2);
                        }
                        if( isset( $runners[1]['secid'] ) ){
                            $runner2 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                            $t2[] = round($runner2,2);
                        }
                        if( isset( $runners[2]['secid'] ) ){
                            $runner3 = $this->getProfitLossOnBetMatchOddsUserParent($user->parent_id,$user->id,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                            $t3[] = round($runner3,2);
                        }

                        if( !($runner1 == 0 && $runner2 == 0 && $runner3 == 0) ){
                            $userData[] = [
                                //'username' => $user->name.'  ( '.$user->username.' )',
                                'username' => $user->username,
                                'id' => $user->id,
                                'runner1' => round($runner1,2),
                                'runner2' => round($runner2,2),
                                'runner3' => round($runner3,2),
                            ];
                        }

                    }

                    if( $t1 != null ){ $t1 = array_sum($t1); $t1 = round( $t1 , 2 );}
                    if( $t2 != null ){ $t2 = array_sum($t2); $t2 = round( $t2 , 2 );}
                    if( $t3 != null ){ $t3 = array_sum($t3); $t3 = round( $t3 , 2 );}

                    //My P&L
                    $m1 = $m2 = $m3 = null;

                    if( isset( $runners[0]['secid'] ) ){
                        $runner1 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                        $m1[] = round($runner1,2);
                    }
                    if( isset( $runners[1]['secid'] ) ){
                        $runner2 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                        $m2[] = round($runner2,2);
                    }
                    if( isset( $runners[2]['secid'] ) ){
                        $runner3 = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                        $m3[] = round($runner3,2);
                    }

                    if( $m1 != null ){ $m1 = array_sum($m1); $m1 = round( $m1 , 2 ); }
                    if( $m2 != null ){ $m2 = array_sum($m2); $m2 = round( $m2 , 2 ); }
                    if( $m3 != null ){ $m3 = array_sum($m3); $m3 = round( $m3 , 2 ); }

                    //Parent P&L
                    $parent = User::findOne(['id' => $uid , 'status' => 1]);

                    $p1 = $p2 = $p3 = null;
                    if($parent != null){

                        if( isset( $runners[0]['secid'] ) ){
                            $runner1 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[0]['secid'],$sessionTyp);
                            $p1[] = round($runner1,2);
                        }
                        if( isset( $runners[1]['secid'] ) ){
                            $runner2 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[1]['secid'],$sessionTyp);
                            $p2[] = round($runner2,2);
                        }
                        if( isset( $runners[2]['secid'] ) ){
                            $runner3 = $this->getProfitLossOnBetMatchOddsUserParent($parent->parent_id,$uid,$marketId,$eventId,$runners[2]['secid'],$sessionTyp);
                            $p3[] = round($runner3,2);
                        }
                    }

                    if( $p1 != null ){ $p1 = array_sum($p1); $p1 = round( $p1 , 2 ); }
                    if( $p2 != null ){ $p2 = array_sum($p2); $p2 = round( $p2 , 2 ); }
                    if( $p3 != null ){ $p3 = array_sum($p3); $p3 = round( $p3 , 2 ); }


//                    $totalData = [
//                        '0' => ['My P&L' , $m1 , $m2 , $m3],
//                        '1' => ['Parent P&L' , $p1 , $p2 , $p3],
//                        '2' => ['User P&L' , ( $t1-$m1 ) , ( $t2-$m2 ) , ( $t3-$m3 ) ],
//                        '3' => [ 'Total' , $t1 , $t2 , $t3 ],
//                    ];

//                    $role = \Yii::$app->authManager->getRolesByUser($uid);
//
//                    if( isset( $role['admin'] )){
//                        $totalData = [
//                            '0' => ['My P&L' , $m1 , $m2 , $m3],
//                            //'1' => ['Parent P&L' , $p1 , $p2 , $p3],
//                            '1' => ['User P&L' , ( $t1-$m1 ) , ( $t2-$m2 ) , ( $t3-$m3 ) ],
//                            '2' => [ 'Total' , $t1 , $t2 , $t3 ],
//                        ];
//                    }

                }

                $response = [ "status" => 1 , "data" => [ 'titalData' => $titleData , 'userData' => $userData , 'totalData' => $totalData ] ];

            }

        }
        return $response;
    }


    // Cricket: Get ProfitLossFancy API
    public function actionGetProfitLossFancyBook()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];

            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['subadmin']) || isset($role['sessionuser'])){
                $uid = 1;
            }

            if( isset( $r_data['user_id'] ) ){
                $uid = $r_data['user_id'];
            }


            $profitLossData = $this->getProfitLossFancy($uid,$eventId,$marketId,$sessionType);

            $response = [ 'status' => 1 , 'data' => $profitLossData ];

        }

        return $response;
    }

    // Cricket: Get ProfitLossManualFancy API
    public function actionGetProfitLossManualFancyBook()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];

            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['subadmin']) || isset($role['sessionuser'])){
                $uid = 1;
            }

            if( isset( $r_data['user_id'] ) ){
                $uid = $r_data['user_id'];
            }

            $profitLossData = $this->getProfitLossFancy($uid,$eventId,$marketId,$sessionType);

            $response = [ 'status' => 1 , 'data' => $profitLossData ];

        }

        return $response;
    }

    public function getMaxLossOnFancy($uid,$isBook,$eventId, $marketId, $sessionType)
    {
        $maxLoss = 0;
        if( $isBook == 1 ){
            $dataArr = $this->getProfitLossFancy($uid,$eventId, $marketId, $sessionType);
            if( $dataArr != null ){
                $maxLossArr = [];
                foreach ( $dataArr as $data ){
                    if( $data['profitLoss'] < 0 ){
                        $maxLossArr[] = $data['profitLoss'];
                    }
                }

                if( $maxLossArr != null ){
                    $maxLoss = min($maxLossArr);
                }
            }
        }

        return round($maxLoss,2);

    }

    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancy($uid,$eventId,$marketId,$sessionType)
    {
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $dataReturn = null;
        $AllClients = [];

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId])->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }


        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;

            //echo $marketId;die;
            $dataRnr=[];  $dataReturn=[]; $place_bet_array=[];
            $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
            $andWhere = ['IN','user_id', $AllClients];
            $betList = PlaceBet::find()->select(['id'])->where($where)->andWhere($andWhere)->asArray()->all();
            if(!empty( $betList)){
                foreach ($betList as $key => $value) {
                    $place_bet_id[]=  $value['id'];

                }
            }

            if(!empty( $place_bet_id)){
                $placebetids = implode(', ', $place_bet_id);
                $dataReturnJson='';
                $where = [ 'session_type' => $sessionType,'market_id' => $marketId,'user_id'=>$uid ];
                $betListClient = (new \yii\db\Query())
                    ->select([ 'market_id','dataReturn','place_bet_array'])
                    ->from('user_place_betdetails')->where($where)->createCommand(Yii::$app->db)->queryOne();
                if(!empty($betListClient)){
                    $place_bet_array= $betListClient['place_bet_array'];
                    $place_bet_array=  Json::decode($place_bet_array);

                    $arraymatchcount=0;
                    foreach ($place_bet_id as $key => $value) {
                        if(!in_array( $value,$place_bet_array ) )
                        {
                            $arraymatchcount++;
                        }
                    }


                    foreach ($place_bet_array as $key => $value) {
                        if(!in_array( $value,$place_bet_id) )
                        {
                            $arraymatchcount++;
                        }
                    }

                    if($arraymatchcount>0){
                        $dataReturn=$this->getUserFancyArray($uid, $marketId,$sessionType,$AllClients,$place_bet_id,$placebetids);
                    }else{
                        $dataReturn= $betListClient['dataReturn'];
                        $dataReturn=  Json::decode($dataReturn);

                    }

                    // echo "arraymatchcount-->". $arraymatchcount;

                }else{

                    $dataReturn=$this->getUserFancyArray($uid, $marketId,$sessionType,$AllClients,$place_bet_id,$placebetids);

                } //betListClient else close
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

    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancy_UNUSED($uid,$eventId,$marketId,$sessionType)
    {
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        $dataReturn = null;
        $AllClients = [];

//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            array_push( $AllClients , $uid );
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId])->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }


        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;

            //echo $marketId;die;
            $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
            $andWhere = ['IN','user_id', $AllClients];

            $betList = PlaceBet::find()
                ->select(['bet_type', 'price', 'win', 'loss'])
                ->where($where)->andWhere($andWhere)->asArray()->all();

//            $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            $minRun = $maxRun = 0;
//            if( isset( $betMinRun['price'] ) ){
//                $minRun = $betMinRun['price']-1;
//            }
//
//            if( isset( $betMaxRun['price'] ) ){
//                $maxRun = $betMaxRun['price']+1;
//            }

            //echo $minRun.' - '.$maxRun;die;

            if( $betList != null ){
                //if( $minRun != 0 && $maxRun != 0 ){

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

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList1 = $command->queryAll();


                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList2 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList3 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList4 = $command->queryAll();

                    if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                    if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                    if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                    if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }

                    $profit = ( $winVal1 + $winVal2 );
                    $loss = ( $lossVal1 + $lossVal2 );

                    $total = $loss-$profit;

                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => round($total,2),
                    ];
                }

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

    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return '0';
        }

        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);
//
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            array_push( $AllClients , $uid );
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        //echo '<pre>';print_r($AllClients);die;

        //$AllClients = array_unique($AllClients);

        //echo "marketId===>".$marketId."==selId==".$selId."==eventId===".$eventId."==sessionType====".$sessionType; echo "<br>";
        // client list who has placed bet on this market

         $dataRnr=[];  $dataReturn=[]; $place_bet_array=[];
          $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
          $andWhere = ['IN','user_id', $AllClients];
          $betList = PlaceBet::find()->select(['id'])->where($where)->andWhere($andWhere)->asArray()->all();
            if(!empty( $betList)){
                 foreach ($betList as $key => $value) {
                        $place_bet_id[]=  $value['id'];

                 }
           }

          $totalArr = [];
            $total = 0;

    if(!empty( $place_bet_id)){
       $placebetids = implode(', ', $place_bet_id);
       $dataReturnJson='';
          $where = [ 'session_type' => $sessionType,'market_id' => $marketId,'selId'=>$selId,'user_id'=> $uid ];
         $betListClient = (new \yii\db\Query())
                    ->select([ 'market_id','dataReturn','place_bet_array'])
                    ->from('user_place_betdetails')->where($where)->createCommand(Yii::$app->db)->queryOne();
          if(!empty($betListClient)){
            $place_bet_array= $betListClient['place_bet_array'];
                $place_bet_array=  Json::decode($place_bet_array);

             $arraymatchcount=0;
              foreach ($place_bet_id as $key => $value) {
                  if(!in_array( $value,$place_bet_array ) )
                  {
                      $arraymatchcount++;
                  }
              }


              foreach ($place_bet_array as $key => $value) {
                  if(!in_array( $value,$place_bet_id) )
                  {
                      $arraymatchcount++;
                  }
              }

              if($arraymatchcount>0){
                    $totalArr= $this->getUserMatchOddArray($uid,$marketId,$eventId,$selId,$sessionType,$AllClients,$place_bet_id,$placebetids);
              }else{
                $totalArr= $betListClient['dataReturn'];
                $totalArr=  Json::decode($totalArr);

              }

              //echo "arraymatchcount-->". $arraymatchcount;

          }else{
               $totalArr= $this->getUserMatchOddArray($uid,$marketId,$eventId,$selId,$sessionType,$AllClients,$place_bet_id,$placebetids);
          } //betListClient else close
   }


            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return round((-1)*array_sum($totalArr),2);
            }else{
                return '0';
            }

        //}

    }


    public function getUserMatchOddArray($uid,$marketId,$eventId,$selId,$sessionType,$AllClients,$place_bet_id,$placebetids){


         $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId,'session_type' => $sessionType, 'bet_status' => 'Pending'])->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

          $totalArr =[]; $total =0;

            foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
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

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>(int)$client,'user_id'=>(int)$uid,'status'=>1])->one();

                    if( $profitLoss != null && (float)$profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*(float)$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }

                }

            }




                $dataReturnJson=  Json::encode($totalArr);
               if(!empty($dataReturnJson)){
                 $place_bet_id=  Json::encode($place_bet_id);
                    $dataRnr[] = [
                        'market_id' => $marketId,
                        'session_type' =>$sessionType,
                        'place_bet_id' =>  $placebetids,
                        'place_bet_array' =>  $place_bet_id,
                        'dataReturn' =>  $dataReturnJson,
                        'user_id'=>$uid,
                        'selId'=>$selId,
                    ];

                     $where = [ 'session_type' => $sessionType,'market_id' => $marketId,'user_id'=>$uid,'selId'=>$selId ];
                     $betListClient = (new \yii\db\Query())
                                ->select([ 'market_id','dataReturn','place_bet_array'])
                                ->from('user_place_betdetails')->where($where)->createCommand(Yii::$app->db)->queryOne();
                      if(!empty($betListClient)){
                             $updateData = [ 'place_bet_id' => $placebetids , 'place_bet_array' => $place_bet_id, 'dataReturn' => $dataReturnJson];
                             \Yii::$app->db->createCommand()->update('user_place_betdetails', $updateData , $where )->execute();
                      }else{
                        \Yii::$app->db->createCommand()->batchInsert('user_place_betdetails',
                         ['market_id', 'session_type', 'place_bet_id', 'place_bet_array', 'dataReturn','user_id','selId'], $dataRnr)->execute();
                     }
                }

             return $totalArr;

    }

   public function getUserMatchOddBookArray($pid,$uid,$marketId,$eventId,$selId,$sessionType,$AllClients,$place_bet_id,$placebetids){


         $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId,'session_type' => $sessionType, 'bet_status' => 'Pending'])->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

          $totalArr =[]; $total =0;
        $AllClients = array_unique($AllClients);
                foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    $backWin = (new \yii\db\Query())
                        ->select(['SUM(win) as val'])
                        ->from('place_bet')->where($where)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin['val']; }

                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    //    ->where($where)->andWhere($andWhere)->asArray()->all();

                    $layWin = (new \yii\db\Query())
                        ->select(['SUM(win) as val'])
                        ->from('place_bet')->where($where)->andWhere($andWhere)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    $layLoss = (new \yii\db\Query())
                        ->select(['SUM(loss) as val'])
                        ->from('place_bet')->where($where)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss['val']; }

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    //    ->where($where)->andWhere($andWhere)->asArray()->all();

                    $backLoss = (new \yii\db\Query())
                        ->select(['SUM(loss) as val'])
                        ->from('place_bet')->where($where)->andWhere($andWhere)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($backLoss[0]['val']);die;

                    if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss['val']; }

                    $totalLoss = $backLoss + $layLoss;

                    $total = $totalWin-$totalLoss;

                }

                if( $total != null && $total != 0 ){


                    $profitLoss = (new \yii\db\Query())
                        ->select(['actual_profit_loss'])
                        ->from('user_profit_loss')->where(['client_id'=>$client,'user_id'=>$pid,'status'=>1])
                        ->createCommand(Yii::$app->db1)->queryOne();

                    if( $profitLoss != null && $profitLoss['actual_profit_loss'] > 0 ){
                        // TODO changed by guest
                        //$total = $total;
                        $total = ($total*$profitLoss['actual_profit_loss'])/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }

                }

            }






                $dataReturnJson=  Json::encode($totalArr);
               if(!empty($dataReturnJson)){
                 $place_bet_id=  Json::encode($place_bet_id);
                    $dataRnr[] = [
                        'market_id' => $marketId,
                        'session_type' =>$sessionType,
                        'place_bet_id' =>  $placebetids,
                        'place_bet_array' =>  $place_bet_id,
                        'dataReturn' =>  $dataReturnJson,
                        'user_id'=>$uid,
                        'selId'=>$selId,
                    ];

                     $where = [ 'session_type' => $sessionType,'market_id' => $marketId,'user_id'=>$uid,'selId'=>$selId ];
                     $betListClient = (new \yii\db\Query())
                                ->select([ 'market_id','dataReturn','place_bet_array'])
                                ->from('user_place_betdetails')->where($where)->createCommand(Yii::$app->db)->queryOne();
                      if(!empty($betListClient)){
                             $updateData = [ 'place_bet_id' => $placebetids , 'place_bet_array' => $place_bet_id, 'dataReturn' => $dataReturnJson];
                             \Yii::$app->db->createCommand()->update('user_place_betdetails', $updateData , $where )->execute();
                      }else{
                        \Yii::$app->db->createCommand()->batchInsert('user_place_betdetails',
                         ['market_id', 'session_type', 'place_bet_id', 'place_bet_array', 'dataReturn','user_id','selId'], $dataRnr)->execute();
                     }
                }

             return $totalArr;

    }


    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetMatchOdds02092019($uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if( isset($role['sessionuser2']) ){
            return '0';
        }

        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);
//
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            array_push( $AllClients , $uid );
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        //echo '<pre>';print_r($AllClients);die;

        //$AllClients = array_unique($AllClients);

//echo "marketId===>".$marketId."==selId==".$selId."==eventId===".$eventId."==sessionType====".$sessionType; echo "<br>";
        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId])->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }


        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;

            foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
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

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>(int)$client,'user_id'=>(int)$uid,'status'=>1])->one();

                    if( $profitLoss != null && (float)$profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*(float)$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }



                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return round((-1)*array_sum($totalArr),2);
            }else{
                return '0';
            }

        }

    }

    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetTeenPatti($uid,$eventId,$selId,$sessionType)
    {
        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['event_id' => $eventId , 'status' => 1 , 'bet_status' => 'Pending' , 'session_type' => $sessionType])
            ->distinct()->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;

            foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    $where = [ 'match_unmatch'=>1, 'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }

                    $where = [ 'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
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

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>(int)$client,'user_id'=>(int)$uid,'status'=>1])->one();

                    if( $profitLoss != null && (float)$profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*(float)$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return round((-1)*array_sum($totalArr),2);
            }else{
                return '0';
            }

        }

    }

    // Cricket: get Profit Loss On Bet User Parent
    public function getProfitLossOnBetMatchOddsUserParent($pid,$uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        if( $pid == 0 ){
            return '0';
        }

        $AllClients = [];
        $role = \Yii::$app->authManager->getRolesByUser($uid);

//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            array_push( $AllClients , $uid );
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId])->distinct()
            ->createCommand(Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

        //echo '<pre>';print_r($AllClients);die;
        $AllClients = array_unique($AllClients);
        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){

                // IF RUNNER WIN

                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    $backWin = (new \yii\db\Query())
                        ->select(['SUM(win) as val'])
                        ->from('place_bet')->where($where)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin['val']; }

                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    //    ->where($where)->andWhere($andWhere)->asArray()->all();

                    $layWin = (new \yii\db\Query())
                        ->select(['SUM(win) as val'])
                        ->from('place_bet')->where($where)->andWhere($andWhere)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($layWin[0]['val']);die;

                    if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin['val']; }

                    $totalWin = $backWin + $layWin;

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

                    //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

                    $layLoss = (new \yii\db\Query())
                        ->select(['SUM(loss) as val'])
                        ->from('place_bet')->where($where)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($layLoss[0]['val']);die;

                    if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss['val']; }

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    //    ->where($where)->andWhere($andWhere)->asArray()->all();

                    $backLoss = (new \yii\db\Query())
                        ->select(['SUM(loss) as val'])
                        ->from('place_bet')->where($where)->andWhere($andWhere)
                        ->createCommand(Yii::$app->db1)->queryOne();

                    //echo '<pre>';print_r($backLoss[0]['val']);die;

                    if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss['val']; }

                    $totalLoss = $backLoss + $layLoss;

                    $total = $totalWin-$totalLoss;

                }

                if( $total != null && $total != 0 ){

                    //$profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                    //    ->where(['client_id'=>$client,'user_id'=>$pid,'status'=>1])->one();

                    $profitLoss = (new \yii\db\Query())
                        ->select(['actual_profit_loss'])
                        ->from('user_profit_loss')->where(['client_id'=>$client,'user_id'=>$pid,'status'=>1])
                        ->createCommand(Yii::$app->db1)->queryOne();

                    if( $profitLoss != null && $profitLoss['actual_profit_loss'] > 0 ){
                        // TODO changed by guest
                        //$total = $total;
                        $total = ($total*$profitLoss['actual_profit_loss'])/100;
                        $totalArr[] = $total;
                    }else{
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }

        }


    }

    // Cricket: get Profit Loss On Bet Lottery
    public function getProfitLossOnBetLottery($uid,$eventId,$marketId,$selId)
    {
        //$uid = \Yii::$app->user->id;

        $role = \Yii::$app->authManager->getRolesByUser($uid);

//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            $AllClients = [];
//        }

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['market_id' => $marketId])->distinct()
            ->createCommand(\Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

        //echo '<pre>';print_r($userId);die;

        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){

                // IF RUNNER WIN
                if( $marketId != null && $eventId != null && $selId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => 'lottery' ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    // IF RUNNER LOSS

                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => 'lottery','user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];

                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($backLoss[0]['val']);die;

                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }

                    $total = $backWin-$backLoss;

                }

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();

                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }

        }


    }

    // Cricket: get Profit Loss On Bet Jackpot
    public function getProfitLossOnBetJackpot($uid,$eventId,$marketId)
    {
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['event_id' => $eventId , 'session_type' => 'jackpot','status' => 1, 'bet_status' => 'Pending'])->distinct()
            ->createCommand(\Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

        //echo '<pre>';print_r($userId);die;

        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){

                // IF RUNNER WIN
                if( $marketId != null && $eventId != null){

                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => 'jackpot' ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

                    //echo '<pre>';print_r($backWin[0]['val']);die;

                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }

                    // IF RUNNER LOSS

                    $where = [ 'match_unmatch'=> 1 ,'session_type' => 'jackpot','user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'market_id' , $marketId ];

                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                        ->where($where)->andWhere($andWhere)->asArray()->all();

                    //echo '<pre>';print_r($backLoss[0]['val']);die;

                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }

                    $total = $backWin-$backLoss;

                }

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();

                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }

        }


    }

    // Cricket: get Profit Loss On Bet Jackpot
    public function getProfitLossOnBetJackpot2($uid,$eventId,$marketId)
    {
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['client']) && $role['client'] != null ){
            array_push( $AllClients , $uid );
        }else{
            $AllClients = $this->getClientListByUserId($uid);
        }

        // client list who has placed bet on this market
        $newClient = (new \yii\db\Query())
            ->select(['user_id'])
            ->from('place_bet')
            ->where(['IN' , 'user_id',$AllClients])
            ->andWhere(['event_id' => $eventId , 'session_type' => 'jackpot'])->distinct()
            ->createCommand(\Yii::$app->db1)->queryAll();


        //var_dump($newClient);die;
        $AllClients = [];
        foreach ( $newClient as $client ){
            $AllClients[] = $client['user_id'];
        }

        //echo '<pre>';print_r($userId);die;

        if( $AllClients != null && count($AllClients) > 0 ){

            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){

                // IF RUNNER WIN
                if( $marketId != null && $eventId != null){

                    $user = (new \yii\db\Query())
                        ->select(['balance','profit_loss_balance'])
                        ->from('user')
                        ->where(['id'=> $uid])
                        ->createCommand(\Yii::$app->db1)->queryOne();

                }

                if( $total != null && $total != 0 ){

                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                        ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();

                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }

                }

            }

            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }

        }


    }


    //actionGetBalance
    public function actionGetBalance()
    {
        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset( $role['sessionuser'] ) ){
            return [ "status" => 1 , "data" => [ "balance" => 0 , "profit_loss" =>  0 ] ];
        }

        if( isset( $role['subadmin'] ) || isset( $role['agent'] ) ){

            $uid = 1;
            $user = (new \yii\db\Query())
                ->select(['balance','profit_loss_balance'])
                ->from('user')
                ->where(['id'=> $uid])
                ->createCommand(\Yii::$app->db1)->queryOne();

            if( $user != null ){
                return [ "status" => 1 , "data" => [ "balance" => $user['balance'] , "profit_loss" => round($user['profit_loss_balance']) ,  "cCount" => 0 ] ];
            }else{
                return [ "status" => 1 , "data" => [ "balance" => 0 , "profit_loss" =>  0 ,  "cCount" => 0 ] ];
            }

        }

        $profitLoss = 0;

        //Own Comm
        $OwnComm = 0;

        $pComm = TransactionHistory::find()->select(['SUM(transaction_amount) as pComm'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        if (isset($pComm['pComm'])) {
            $OwnComm = $pComm['pComm'];
        }


        //Own PL
        $OwnPl = 0;

        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }

        $OwnPl = $profitVal - $lossVal;

        if( $OwnPl > 0 ){
            $profitLoss = $OwnPl - $OwnComm;
        }else{
            $profitLoss = $OwnPl + $OwnComm;
        }

        //$profitLoss = $OwnPl-$OwnComm+$OwnCash;
        //$profitLoss = $OwnPl;

        $user = User::findOne($uid);

        if( $user != null ){
            $user->profit_loss_balance = $profitLoss;
            $user->save();

            $client = (new \yii\db\Query())
                ->select(['count( id ) as cCount'])
                ->from('user')
                ->where(['role'=> 4 , 'is_login' => 1 , 'status' => 1])
                ->createCommand(Yii::$app->db1)->queryOne();
            $cCount = 0;
            if( $client != null ){
                $cCount = $client['cCount'];
            }

            return [ "status" => 1 , "data" => [ "balance" => $user->balance , "profit_loss" => round($profitLoss) , "cCount" => $cCount ] ];
        }
        return [ "status" => 1 , "data" => [ "balance" => 0 , "profit_loss" =>  0 , "cCount" => 0 ] ];

    }

    public function getClientListByUserId($uid)
    {
        $client = [];

        $cdata = (new \yii\db\Query())
            ->select(['client_id'])
            ->from('user_profit_loss')
            ->where(['parent_id'=> $uid])->distinct()
            ->createCommand(Yii::$app->db1)->queryAll();

        if ($cdata != null) {
            foreach ($cdata as $c) {
                $client[] = (int)$c['client_id'];
            }
        }

        return $client;

    }



    public function getUserFancyArray($uid, $marketId,$sessionType,$AllClients,$place_bet_id,$placebetids){
     $dataReturn=[];
        $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
            $andWhere = ['IN','user_id', $AllClients];
            $betList = PlaceBet::find()
                ->select(['bet_type', 'price', 'win', 'loss','market_id','user_id'])
                ->where($where)->andWhere($andWhere)->asArray()->all();
           if( $betList != null ){

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

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList1 = $command->queryAll();


                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList2 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList3 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',(int)$i]);
                    $command = $query->createCommand(Yii::$app->db1);
                    $betList4 = $command->queryAll();



                    if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                    if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                    if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                    if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }

                    $profit = ( $winVal1 + $winVal2 );
                    $loss = ( $lossVal1 + $lossVal2 );

                    $total = $loss-$profit;

                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => round($total,2),'loss'=>$loss,'profit'=>$profit
                    ];
                }

                $dataReturnJson=  Json::encode($dataReturn);
               if(!empty($dataReturnJson)){
                 $place_bet_id=  Json::encode($place_bet_id);
                    $dataRnr[] = [
                        'market_id' => $marketId,
                        'session_type' =>$sessionType,
                        'place_bet_id' =>  $placebetids,
                        'place_bet_array' =>  $place_bet_id,
                        'dataReturn' =>  $dataReturnJson,
                        'user_id'=>$uid,
                    ];

                     $where = [ 'session_type' => $sessionType,'market_id' => $marketId,'user_id'=>$uid ];
                     $betListClient = (new \yii\db\Query())
                                ->select([ 'market_id','dataReturn','place_bet_array'])
                                ->from('user_place_betdetails')->where($where)->createCommand(Yii::$app->db)->queryOne();
                      if(!empty($betListClient)){
                             $updateData = [ 'place_bet_id' => $placebetids , 'place_bet_array' => $place_bet_id, 'dataReturn' => $dataReturnJson];
                             \Yii::$app->db->createCommand()->update('user_place_betdetails', $updateData , $where )->execute();
                      }else{
                        \Yii::$app->db->createCommand()->batchInsert('user_place_betdetails',
                         ['market_id', 'session_type', 'place_bet_id', 'place_bet_array', 'dataReturn','user_id'], $dataRnr)->execute();
                     }
                }

                return $dataReturn;
              }
    }
}
