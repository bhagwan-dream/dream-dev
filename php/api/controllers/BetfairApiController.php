<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\EventsRunner;
use common\models\EventsPlayList;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use common\models\MarketType;
use common\models\User;


/**
 * BetfairApi controller
 */
class BetfairApiController extends Controller
{
    private $apiUrl = 'https://api.betfair.com/exchange/betting/json-rpc/v1';
    private $appKey = 'O4lUcGrZJumxWMKB';//'3sTY3nHndebeguXe';//'6ELoKExfMWm9RWDE';
    private $sessionToken = 'lh/Mf/TdJNDZjOxBpn+JbNHSzTDEg3nBBXcdsn7reQw=';//'hyFbOvu4WZEsHwaXXB2dlYYxqrkjj+Zz4cDyWbrjl/Y=';//'ICvTU9sPpMP0XuuyFm9crDAHB9nUxhZd7g8yxpdgsuY=';
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionReGenerateBlock(){
        $uId = 1;
        $AllUser = $this->getAllUserForAdmin($uId);
        
        $eventList = EventsPlayList::findAll(['status' => 1 ]);
        
        array_push($AllUser,$uId);
        if( $AllUser != null ){
            
            foreach ( $eventList as $event ){
                
                foreach ( $AllUser as $user ){
                    $dataUsr[] = [
                        'user_id' => $user,
                        'event_id' => $event->event_id,
                        'market_id' => $event->market_id,
                        'market_type' => 'all',
                        'byuser' => $uId
                    ];
                }
                
            }
            
            
            
        }
        if( $dataUsr != null ){
            
            \Yii::$app->db->createCommand()->truncateTable('event_market_status')->execute();
            
            \Yii::$app->db->createCommand()->batchInsert('event_market_status',
                ['user_id', 'event_id','market_id','market_type','byuser'], $dataUsr )->execute();
        }
        
       return 'Done!';
        
    }
    
    /**
     * Get All Event List
     * 
     */

    public function actionAllEvent()
    {
        $command = Yii::$app->db->createCommand();

        $data = $this->getAllEventTypes($this->appKey, $this->sessionToken);
        //return $jsonResponse[0]->result;
        //echo '<pre>';print_r($data);exit;

        if( !empty( $data ) ){
            
            //Truncate table of event
            
            //$truncate = $command->truncateTable( Event::tableName() );
            //$truncate->execute();

            //Insert all new data from event api 

            $allEventArr = [];
            foreach( $data as $event ){
                $allEventArr[] = [
                    'event_type_id' => $event->eventType->id,
                    'event_type_name' => $event->eventType->name,
                    'event_slug' => strtolower( str_replace(' ', '-', trim($event->eventType->name)) ),
                    'market_count' => $event->marketCount,
                    'img' => 'default.jpg',
                    'icon' => 'default.png',
                    'status' => 1,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
            }

            //$query = $command->batchInsert( Event::tableName(), ['event_type_id','event_type_name','event_slug','market_count','img','icon','status','created_at','updated_at'], $allEventArr );
            //$query->execute();

        }
        return $this->render('index' , [ 'data' => $allEventArr ]);
        //echo '<pre>';print_r($allEventArr);exit;
    }

    /**
     * Get Event Market
     * 
     */

    public function actionEventMarket()
    {
        $eventId = '4';
        $data = $this->getEventMarket($this->appKey, $this->sessionToken , $eventId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('event_market' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * Get Market Book
     * 
     */

    public function actionMarketBook()
    {
        //$marketId = '1.147986863';
        if( isset( $_GET['MARKETID'] ) && !empty($_GET['MARKETID']) ){
            $marketId = $_GET['MARKETID'];
        }else{
            return $this->redirect('event-market');
        }
        
        $data = $this->getMarketBook($this->appKey, $this->sessionToken , $marketId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('market_book' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * Place Bet
     * 
     */

    public function actionPlaceBet()
    {
        if( isset( $_GET['MARKETID'] ) && !empty($_GET['MARKETID']) 
            && isset( $_GET['SELECTIONID'] ) && !empty($_GET['SELECTIONID']) ){
            $marketId = $_GET['MARKETID'];
            $selectionId = $_GET['SELECTIONID'];
        }else{
            return $this->redirect('event-market');
        }
        
        $data = $this->placeBet($this->appKey, $this->sessionToken , $marketId , $selectionId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('place_bet' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * getAllEventTypes
     */

    private function getAllEventTypes($appKey, $sessionToken)
    {
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listEventTypes', '{"filter":{}}');
        //return $jsonResponse[0]->result;

        if (isset($jsonResponse[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode( $jsonResponse[0]->error );
            exit(-1);
        } else {
            return $jsonResponse[0]->result;
        }
    }

    /**
     * getEventMarket
     */

    private function getEventMarket($appKey, $sessionToken, $eventId)
    {
        $params = '{"filter":{"eventTypeIds":["' . $eventId . '"],
                "marketCountries":["GB"],
                "marketTypeCodes":["WIN"],
                "marketStartTime":{"from":"' . date('c') . '"}},
                "sort":"FIRST_TO_START",
                "maxResults":"1",
                "marketProjection":["RUNNER_DESCRIPTION"]}';
        //echo $params;exit;
    
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listEvents', $params);
        echo '<pre>';print_r($jsonResponse);exit;
        return $jsonResponse[0]->result[0];
    }

    /**
     * getMarketBook
     */

    private function getMarketBook($appKey, $sessionToken, $marketId)
    {
        $params = '{"marketIds":["' . $marketId . '"], "priceProjection":{"priceData":["EX_BEST_OFFERS"]}}';
        //echo $params;exit;
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listMarketBook', $params);
    
        return $jsonResponse[0]->result[0];
    }

    /**
     * placeBet
     */

    private function placeBet($appKey, $sessionToken, $marketId, $selectionId)
    {
        $params = '{"marketId":"' . $marketId . '",
                    "instructions":
                         [{"selectionId":"' . $selectionId . '",
                           "handicap":"0",
                           "side":"BACK",
                           "orderType":
                           "LIMIT",
                           "limitOrder":{"size":"1",
                                        "price":"25",
                                        "persistenceType":"LAPSE"}
                           }], "customerRef":"fsdf"}';
        //echo $params;exit;

        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'placeOrders', $params);
     
        return $jsonResponse[0]->result;
     
    }

    /**
     * sportsApingRequest
     */

    private function sportsApingRequest($appKey, $sessionToken, $operation, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:',
            'X-Application: ' . $appKey,
            'X-Authentication: ' . $sessionToken,
            'Accept: application/json',
            'Content-Type: application/json'
        ));
    
        $postData =
            '[{ "jsonrpc": "2.0", "method": "SportsAPING/v1.0/' . $operation . '", "params" :' . $params . ', "id": 1}]';
    
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        
        //echo '<pre>';print_r($response);exit;
        return $response;
        //if (isset($response[0]->error)) {
            //echo 'Call to api-ng failed: ' . "\n";
            //echo  'Response: ' . json_encode($response);
            //exit(-1);
        //} else {
            //return $response;
        //}
 
    }
    
    public function actionTestRedisSet(){
        
        $key = 'test';
        
        $cache = Yii::$app->cache;
        
        $data = 'sdfsdfsdfsdfsf';
        // store $data in cache so that it can be retrieved next time
        $cache->set($key, $data);
        
        
    }
    
    public function actionTestRedisGet(){
        
        $cache = Yii::$app->cache;
        $key = 'test';
        // try retrieving $data from cache
        $data = $cache->get($key);
        
        echo $data;
        
    }
    
    // Cricket: Get data inplay and upcoming list from API
    public function actionSetOdds()
    {
        if( isset( $_GET['mid'] ) ){
            //echo $_GET['mid'];die;
            
            $startTime = time();
            $endTime = time()+60;
            
            while( $endTime > time() ){
                
                usleep(300000);
            
                $marketId = $_GET['mid'];
                $url = 'http://odds.appleexch.uk:3000/getmarket?id='.$marketId;
                //$url = 'http://rohitash.dream24.bet:3000/getmarket?id='.$marketId;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $responseData = curl_exec($ch);
                curl_close($ch);
                $responseData = json_decode($responseData);
                //echo '<pre>';print_r($responseData);die;
                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                    
                    foreach ( $responseData->runners as $runners ){
                        $backPrice1 = $backPrice2 = $backPrice3 = '-';
                        $layPrice1 = $layPrice2 = $layPrice3 = '-';
                        $backSize1 = $backSize2 = $backSize3 = '';
                        $laySize1 = $laySize2 = $laySize3 = '';
                        if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                            if( isset($runners->ex->availableToBack[0]) ){
                                $backArr1 = $runners->ex->availableToBack[0];
                                $backPrice1 = number_format($backArr1->price , 2);
                                $backSize1 = number_format($backArr1->size , 2);
                            }
                            if( isset($runners->ex->availableToBack[1]) ){
                                $backArr2 = $runners->ex->availableToBack[1];
                                $backPrice2 = number_format($backArr2->price , 2);
                                $backSize2 = number_format($backArr2->size , 2);
                            }
                            if( isset($runners->ex->availableToBack[2]) ){
                                $backArr3 = $runners->ex->availableToBack[2];
                                $backPrice3 = number_format($backArr3->price , 2);
                                $backSize3= number_format($backArr3->size , 2);
                            }
                        }
                        
                        if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                            if( isset($runners->ex->availableToLay[0]) ){
                                $layArr1 = $runners->ex->availableToLay[0];
                                $layPrice1 = number_format($layArr1->price , 2);
                                $laySize1 = number_format($layArr1->size , 2);
                            }
                            if( isset($runners->ex->availableToLay[1]) ){
                                $layArr2 = $runners->ex->availableToLay[1];
                                $layPrice2 = number_format($layArr2->price , 2);
                                $laySize2 = number_format($layArr2->size , 2);
                            }
                            if( isset($runners->ex->availableToLay[2]) ){
                                $layArr3 = $runners->ex->availableToLay[2];
                                $layPrice3 = number_format($layArr3->price , 2);
                                $laySize3= number_format($layArr3->size , 2);
                            }
                        }
                        
                        $responseArr[] = [
                            'backPrice1' => $backPrice1,
                            'backSize1' => $backSize1,
                            'backPrice2' => $backPrice2,
                            'backSize2' => $backSize2,
                            'backPrice3' => $backPrice3,
                            'backSize3' => $backSize3,
                            'layPrice1' => $layPrice1,
                            'laySize1' => $laySize1,
                            'layPrice2' => $layPrice2,
                            'laySize2' => $laySize2,
                            'layPrice3' => $layPrice3,
                            'laySize3' => $laySize3
                        ];
                        
                    }
                    
                }
                
                $data[] = [
                    'inplay' => $responseData->inplay,
                    'time' => round(microtime(true) * 1000),
                    'odds' => $responseArr
                ];
                
                //$cache = Yii::$app->cache;
                //$cache->set($marketId,json_encode( $data ) );
            
            }
          }
        
          echo json_encode($data);
            
    }
    
    // Cricket: Get data inplay and upcoming list from API
    public function actionGetOdds()
    {
        if( isset( $_GET['mid'] ) ){
            //echo $_GET['mid'];die;
            $marketId = $_GET['mid'];
            $cache = Yii::$app->cache;
            $data = $cache->get($marketId);
            echo $data;die;
        }
        
    }
    
    public function actionRefreshEventList()
    {
        $url = 'http://master.heavyexch.com/api/markets';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        //echo '<pre>';print_r($responseData);die;
        if( $responseData != null ){
            
            foreach( $responseData as $data ){
                
                /*$eventTime = strtotime( $data->MstDate );
                echo $eventTime.' - '.date( 'Y-m-d H:i:s', $eventTime).' == ';
                echo strtotime(date('Y-m-d H:i:s',strtotime('+330 minutes', time())) ).' - '.date('Y-m-d H:i:s',strtotime('+330 minutes', time()));
                echo '<pre>';print_r($data);die;*/
                
                if( $data->name == 'Match Odds' ){
                    $marketId = $data->Id;
                    $eventId = $data->matchid;
                    $eventLeague = $data->seriesname;
                    $eventName = $data->matchName;
                    $eventTime = strtotime( $data->MstDate )*1000;
                    $sportId = $data->SportID;
                    
                    // Add Fancy Market Data
                    if( $sportId == 4 ){
                        $this->addFancyMarketData($eventId);
                    }
                    
                    $check = EventsPlayList::findOne(['sport_id' => $sportId,'event_id' => $eventId , 'market_id' => $marketId ]);
                    
                    if( $check != null ){
                        $curTime = strtotime(date('Y-m-d H:i:s',strtotime('+330 minutes', time())) );
                        if( $eventTime < $curTime ){
                            $check->play_type = 'IN_PLAY';
                        }else{
                            $check->play_type = 'UPCOMING';
                        }
                        
                        if( $check->event_time != $eventTime ){
                            $check->event_time = $eventTime;
                        }
                        
                        $check->save();
                        
                    }else{
                        $model = new EventsPlayList();
                        $model->sport_id = $sportId;
                        $model->event_id = $eventId;
                        $model->market_id = $marketId;
                        $model->event_league = $eventLeague;
                        $model->event_name = $eventName;
                        $model->event_time = $eventTime;
                        
                        $curTime = strtotime(date('Y-m-d H:i:s',strtotime('+330 minutes', time())) )*1000;
                        if( $eventTime < $curTime ){
                            $model->play_type = 'IN_PLAY';
                        }else{
                            $model->play_type = 'UPCOMING';
                        }
                        
                        if( $model->save() ){
                            $runnerModelCheck = EventsRunner::findOne(['market_id'=>$marketId]);
                            if( $runnerModelCheck == null ){
                                if( isset( $data->runners ) ){
                                    
                                    $runnersArr = json_decode( $data->runners );
                                    
                                    foreach( $runnersArr->runners as $runners ){
                                        $selId = $runners->selectionId;
                                        $runnerName = $runners->runnerName;
                                        $runnerModel = new EventsRunner();
                                        $runnerModel->event_id = $eventId;
                                        $runnerModel->market_id = $marketId;
                                        $runnerModel->selection_id = $selId;
                                        $runnerModel->runner = $runnerName;
                                        $runnerModel->save();
                                    }
                                }
                            }
                            
                            $AllUser = $dataUsr = [];
                            $uId = 1;
                            $role = \Yii::$app->authManager->getRolesByUser($uId);
                            if( isset($role['admin']) && $role['admin'] != null ){
                                $AllUser = $this->getAllUserForAdmin($uId);
                                array_push($AllUser,$uId);
                                if( $AllUser != null ){
                                    foreach ( $AllUser as $user ){
                                        $dataUsr[] = [
                                            'user_id' => $user,
                                            'event_id' => $eventId,
                                            'market_id' => $marketId,
                                            'market_type' => 'all',
                                            'byuser' => $uId
                                        ];
                                    }
                                }
                                
                                \Yii::$app->db->createCommand()->batchInsert('event_market_status',
                                    ['user_id', 'event_id','market_id','market_type','byuser'], $dataUsr )->execute();
                                    
                            }
                            
                            
                        }
                    }
                    
                }
                
            }
            
            
            
            // Ckeck UnMatch Bets
            $unmatchBetList = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['session_type'=>'match_odd','match_unmatch'=>0,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['market_id'])->all();
            
            if( $unmatchBetList != null ){
                
                foreach ( $unmatchBetList as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $url = 'http://odds.appleexch.uk:3000/getmarket?id='.$marketId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    
                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                        
                        foreach ( $responseData->runners as $runners ){
                            
                            $selectionId = $runners->selectionId;
                            
                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                $backArr = $runners->ex->availableToBack[0];
                                $price = $backArr->price;
                                if( $price != '' || $price != ' - ' || $price != null || $price != '0' )
                                    $this->updateUnmatchedData($marketId,'back', $price, $selectionId);
                            }
                            
                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                $layArr = $runners->ex->availableToLay[0];
                                $price = $layArr->price;
                                if( $price != '' || $price != ' - ' || $price != null || $price != '0' )
                                    $this->updateUnmatchedData($marketId,'lay', $price, $selectionId);
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        
        return true;
    }
    
    //AllUserForAdmin
    public function getAllUserForAdmin($uid)
    {
        $userList = [];
        $smdata = (new \yii\db\Query())
        ->select(['id','role'])->from('user')
        ->where(['parent_id'=>$uid , 'role'=> 2])->all();
        
        if($smdata != null){
            
            foreach ( $smdata as $sm ){
                
                $userList[] = $sm['id'];
                // get all master
                $sm2data = (new \yii\db\Query())
                ->select(['id','role'])->from('user')
                ->where(['parent_id'=>$sm['id'] , 'role'=> 2])->all();
                
                if($sm2data != null){
                    
                    foreach ( $sm2data as $sm2 ){
                        $userList[] = $sm2['id'];
                        // get all master
                        $m1data = (new \yii\db\Query())
                        ->select(['id','role'])->from('user')
                        ->where(['parent_id'=>$sm2['id'] , 'role'=> 3])->all();
                        
                        if($m1data != null){
                            foreach ( $m1data as $m1 ){
                                $userList[] = $m1['id'];
                                // get all master
                                $m2data = (new \yii\db\Query())
                                ->select(['id','role'])->from('user')
                                ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->all();
                                
                                if($m2data != null){
                                    foreach ( $m2data as $m2 ){
                                        $userList[] = $m2['id'];
                                        
                                    }
                                }
                                
                            }
                        }
                    }
                    
                }
                
                
                // get all master
                $m1data = User::find()->select(['id','role'])->where(['parent_id'=>$sm['id'] , 'role'=> 3])->all();
                if($m1data != null){
                    foreach ( $m1data as $m1 ){
                        $userList[] = $m1['id'];
                        // get all master
                        $m2data = (new \yii\db\Query())
                        ->select(['id','role'])->from('user')
                        ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->all();
                        if($m2data != null){
                            foreach ( $m2data as $m2 ){
                                $userList[] = $m2['id'];
                                
                            }
                        }
                        
                    }
                }
                
            }
        }
        
        // get all master
        $mdata = (new \yii\db\Query())
        ->select(['id','role'])->from('user')
        ->where(['parent_id'=>$uid , 'role'=> 3])->all();
        
        
        if($mdata != null){
            
            foreach ( $mdata as $m ){
                $userList[] = $m['id'];
                // get all master
                $m2data = (new \yii\db\Query())
                ->select(['id','role'])->from('user')
                ->where(['parent_id'=>$m['id'] , 'role'=> 3])->all();
                if($m2data != null){
                    foreach ( $m2data as $m2 ){
                        $userList[] = $m2['id'];
                        
                    }
                }
                
            }
            
        }
        
        return $userList;
        
    }
    
    public function actionRefreshEventListOLD()
    {
        
        $startTime = time();
        $endTime = time()+300;
        
        while( $endTime > time() ){
            
            sleep(5);
            //CODE for live call api
            $url = 'http://irfan.royalebet.uk/getodds.php?event_id='.$_GET['id'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);
            //echo '<pre>';print_r($responseData);die;
            if( isset( $responseData->result ) && !empty($responseData->result) ){
                
                foreach( $responseData->result as $result ){
                    
                    $today = date('Ymd');
                    $tomorrow = date('Ymd' , strtotime($today . ' +1 day') );
                    $eventDate = date('Ymd',( $result->start/1000 ));
                    if( $today == $eventDate || $tomorrow == $eventDate ){
                        
                        $marketId = $result->id;
                        $eventId = $result->event->id;
                        $eventLeague = isset( $result->competition->name ) ? $result->competition->name : 'No Data' ;
                        $eventName = $result->event->name;
                        $eventTime = $result->start;
                        
                        // Add Fancy Market Data
                        if( $_GET['id'] == 4 ){
                            $this->addFancyMarketData($eventId);
                        }
                        
                        $check = EventsPlayList::findOne(['sport_id' => $_GET['id'],'event_id' => $eventId , 'market_id' => $marketId ]);
                        
                        if( $check != null ){
                            
                            if( $result->inPlay == 1 || $result->inPlay == true || $result->inPlay == 'true' ){
                                $check->play_type = 'IN_PLAY';
                            }else{
                                $check->play_type = 'UPCOMING';
                            }
                            
                            if( $check->event_time != $result->start ){
                                $check->event_time = $result->start;
                            }
                            
                            if( $check->save() ){
                                $runnerModelCheck = EventsRunner::findOne(['market_id'=>$marketId]);
                                if( $runnerModelCheck == null ){
                                    if( isset( $result->runners ) ){
                                        foreach( $result->runners as $runners ){
                                            $selId = $runners->id;
                                            $runnerName = $runners->name;
                                            $runnerModel = new EventsRunner();
                                            $runnerModel->event_id = $eventId;
                                            $runnerModel->market_id = $marketId;
                                            $runnerModel->selection_id = $selId;
                                            $runnerModel->runner = $runnerName;
                                            $runnerModel->save();
                                        }
                                    }
                                }
                            }
                            
                            
                        }else{
                            $model = new EventsPlayList();
                            $model->sport_id = $_GET['id'];
                            $model->event_id = $eventId;
                            $model->market_id = $marketId;
                            $model->event_league = $eventLeague;
                            $model->event_name = $eventName;
                            $model->event_time = $eventTime;
                            
                            if( $result->inPlay == 1 || $result->inPlay == true || $result->inPlay == 'true' ){
                                $model->play_type = 'IN_PLAY';
                            }else{
                                $model->play_type = 'UPCOMING';
                            }
                            if( $model->save() ){
                                $runnerModelCheck = EventsRunner::findOne(['market_id'=>$marketId]);
                                if( $runnerModelCheck == null ){
                                    if( isset( $result->runners ) ){
                                        foreach( $result->runners as $runners ){
                                            $selId = $runners->id;
                                            $runnerName = $runners->name;
                                            $runnerModel = new EventsRunner();
                                            $runnerModel->event_id = $eventId;
                                            $runnerModel->market_id = $marketId;
                                            $runnerModel->selection_id = $selId;
                                            $runnerModel->runner = $runnerName;
                                            $runnerModel->save();
                                        }
                                    }
                                }
                            }
                        }
                        
                    }
                    
                }
                
            }
            
            // Ckeck UnMatch Bets
            $unmatchBetList = (new \yii\db\Query())
            ->select(['market_id'])->from('place_bet')
            ->where(['session_type'=>'match_odd','match_unmatch'=>0,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['market_id'])->all();
            
            if( $unmatchBetList != null ){
                
                foreach ( $unmatchBetList as $market ){
                    
                    $marketId = $market['market_id'];
                    
                    $url = 'http://odds.appleexch.uk:3000/getmarket?id='.$marketId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    
                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                        
                        foreach ( $responseData->runners as $runners ){
                            
                            $selectionId = $runners->selectionId;
                            
                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                $backArr = $runners->ex->availableToBack[0];
                                $price = $backArr->price;
                                if( $price != '' || $price != ' - ' || $price != null || $price != '0' )
                                    $this->updateUnmatchedData($marketId,'back', $price, $selectionId);
                            }
                            
                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                $layArr = $runners->ex->availableToLay[0];
                                $price = $layArr->price;
                                if( $price != '' || $price != ' - ' || $price != null || $price != '0' )
                                    $this->updateUnmatchedData($marketId,'lay', $price, $selectionId);
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        return true;
    }
    
    // add Fancy Market Data
    public function addFancyMarketData($eventId)
    {
        if( isset( $eventId ) ){
            //CODE for live call api
            $url = 'http://irfan.royalebet.uk/getfancy.php?eventId='.$eventId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);
            
            //echo '<pre>';print_r($responseData);die;
            if( $responseData->status == 200 ){
                if( isset( $responseData->data ) && !empty( $responseData->data ) ){
                    foreach ( $responseData->data as $key=>$data ){
                        
                        if( $key != 'active' ){
                            
                            $check = MarketType::findOne(['market_id'=> $data->market_id , 'event_id' => $eventId ]);
                            
                            if( $check == null ){
                                
                                $model = New MarketType();
                                
                                $model->event_type_id = 4;
                                $model->market_id = $data->market_id;
                                $model->event_id = $eventId;
                                $model->market_name = $data->headname;
                                $model->market_type = 'INNINGS_RUNS';
                                $model->status = 1;
                                $model->created_at = time();
                                $model->updated_at = time();
                                
                                $model->save();
                            }
                            
                        }
                        
                    }
                }
            }
        }
        
    }
    
    // Cricket: updateUnmatchedData
    public function updateUnmatchedData($marketId,$type,$odd,$secId)
    {
        //$betIds = [];
        if( $type == 'lay' ){
            $where = [
                'market_id' => $marketId, 'bet_type' => $type,
                'sec_id' => $secId,'match_unmatch' => 0
            ];
            $andWhere = [ '>=' , 'price' , $odd ];
        }else{
            $where = [
                'market_id' => $marketId, 'bet_type' => $type,
                'sec_id' => $secId,'match_unmatch' => 0
            ];
            $andWhere = [ '<=' , 'price' , $odd ];
        }
        
        $betList = (new \yii\db\Query())
        ->select(['id'])->from('place_bet')
        ->where($where)->andWhere($andWhere)
        ->all();
        
        if( $betList != null ){
            /*foreach ( $betList as $bet ){
                $betIds[] = $bet['id'];
            }*/
            if( $betList != null ){
                PlaceBet::updateAll(['match_unmatch'=>1,'updated_at' => time()],['id'=>$betList]);
            }
            
        }
        
        return;
    }
    
}
