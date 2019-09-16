<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use common\models\PlaceBet;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\EventsPlayList;
use yii\helpers\Url;
//use common\models\GameMatkaShedule;


/**
 * BetfairApiCricket controller
 */
class BetfairApiCricketController extends Controller
{
    public $enableCsrfValidation = false;
    
    private $apiUserToken = '15815-peDeUY8w5a9rPq';//'13044-CgPWGpYSAOn7aV';
    
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

    public function actionInplayUpcomingFootball()
    {
        //CODE for live call api
        $url = 'http://odds.kheloindia.bet/getodds.php?event_id=1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        //echo '<pre>';print_r($responseData);die;
        
        if( isset( $responseData->result ) && !empty($responseData->result) ){
            
            foreach( $responseData->result as $result ){
                $marketId = $result->id;
                $eventId = $result->event->id;
                $eventLeague = $result->competition->name;
                $eventName = $result->event->name;
                $eventTime = $result->start;
                
                $check = EventsPlayList::findOne(['market_id' => $marketId ,'event_id' => $eventId , 'sport_id' => 1]);
                
                if( $check != null ){
                    $check->event_time = $eventTime;
                    if( $result->inPlay == true ){
                        $check->play_type = 'IN_PLAY';
                    }else{
                        $check->play_type = 'UPCOMING';
                    }
                    $check->save();
                    
                }else{
                    $model = new EventsPlayList();
                    $model->sport_id = 1;
                    $model->event_id = $eventId;
                    $model->market_id = $marketId;
                    $model->event_league = $eventLeague;
                    $model->event_name = $eventName;
                    $model->event_time = $eventTime;
                    
                    if( $result->inPlay == true ){
                        $model->play_type = 'IN_PLAY';
                    }else{
                        $model->play_type = 'UPCOMING';
                    }
                    $model->save();
                }
            }
            
        }
        $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
        return json_encode($response , 16);
        exit;
    }
    
    public function actionInplayUpcomingCricket()
    {
        //CODE for live call api
        $url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        //echo '<pre>';print_r($responseData);die;
        
        if( isset( $responseData->result ) && !empty($responseData->result) ){
            
            foreach( $responseData->result as $result ){
                $marketId = $result->id;
                $eventId = $result->event->id;
                $eventLeague = $result->competition->name;
                $eventName = $result->event->name;
                $eventTime = $result->start;
                
                $check = EventsPlayList::findOne(['market_id' => $marketId ,'event_id' => $eventId , 'sport_id' => 4]);
                
                if( $check != null ){
                    $check->event_time = $eventTime;
                    if( $result->inPlay == true ){
                        $check->play_type = 'IN_PLAY';
                    }else{
                        $check->play_type = 'UPCOMING';
                    }
                    $check->save();
                    
                }else{
                    $model = new EventsPlayList();
                    $model->sport_id = 4;
                    $model->event_id = $eventId;
                    $model->market_id = $marketId;
                    $model->event_league = $eventLeague;
                    $model->event_name = $eventName;
                    $model->event_time = $eventTime;
                    
                    if( $result->inPlay == true ){
                        $model->play_type = 'IN_PLAY';
                    }else{
                        $model->play_type = 'UPCOMING';
                    }
                    $model->save();
                }
            }
            
        }
        $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
        return json_encode($response , 16);
        exit;
    }
    
    public function actionInplayUpcomingTennis()
    {
        //CODE for live call api
        $url = 'http://odds.kheloindia.bet/getodds.php?event_id=2';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        //echo '<pre>';print_r($responseData);die;
        
        if( isset( $responseData->result ) && !empty($responseData->result) ){
            
            foreach( $responseData->result as $result ){
                $marketId = $result->id;
                $eventId = $result->event->id;
                $eventLeague = $result->competition->name;
                $eventName = $result->event->name;
                $eventTime = $result->start;
                
                $check = EventsPlayList::findOne(['market_id' => $marketId ,'event_id' => $eventId , 'sport_id' => 4]);
                
                if( $check != null ){
                    $check->event_time = $eventTime;
                    if( $result->inPlay == true ){
                        $check->play_type = 'IN_PLAY';
                    }else{
                        $check->play_type = 'UPCOMING';
                    }
                    $check->save();
                    
                }else{
                    $model = new EventsPlayList();
                    $model->sport_id = 2;
                    $model->event_id = $eventId;
                    $model->market_id = $marketId;
                    $model->event_league = $eventLeague;
                    $model->event_name = $eventName;
                    $model->event_time = $eventTime;
                    
                    if( $result->inPlay == true ){
                        $model->play_type = 'IN_PLAY';
                    }else{
                        $model->play_type = 'UPCOMING';
                    }
                    $model->save();
                }
            }
            
        }
        $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
        return json_encode($response , 16);
        exit;
    }
    
    public function actionNewApi()
    {
        ini_set("display_errors", "1");
        error_reporting(E_ALL);
        
        $url = 'http://13.233.165.68/api/odds/cricket';
        if( null != \Yii::$app->request->get( 'event' ) ){
            $eID = \Yii::$app->request->get( 'event' );
            $url = 'http://13.233.165.68/api/odds/cricket/detail/'.$eID;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = curl_exec($ch);
        curl_close($ch);
        //$response = json_decode($response);
        echo '<pre>';print_r($response);die;
    }
    /**
     * Displays homepage.
     *
     * @return string
     */
    // Cricket: Get data inplay and upcoming list from API
    public function actionIndex()
    {
        die('asdasd');
        
        $basePath = \Yii::$app->basePath;
        
        $file = $basePath.'/uploads/json/cricket/cricket.json';
        $fileNew = $basePath.'/uploads/json/cricket/cricket_live.json';
        
        if( file_exists($file) ){
            if( file_exists($fileNew) ){
                unlink($fileNew);
            }
            rename($file,$fileNew);
        }
        
        // Get data inplay list from API
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response1 = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response1);die;
        $responseArr = [];
        $responseArr['inplay'] = [];
        $responseArr['upcoming'] = [];
        
        if( null !== $response1 && $response1->success != 1 ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => $response1->error ] ];
            return json_encode($response , 16);
            exit;
        }
        
        if( !empty($response1->results) ){
            
            foreach ( $response1->results as $items ){
                
                $eventId = $items->id;
                $time = $items->time;
                $runner1 = $items->home->name;
                $runner2 = $items->away->name;
                
                //$check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                
                //if( $check != null ){
                    $responseArr['inplay'][] = [
                        'eventId' => $eventId,
                        'time' => $time,
                        'runner1' => $runner1,
                        'runner2' => $runner2
                    ];
                //}
                
            }
            
        }
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response2 = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response2);die;
        
        if( $response2->success == '0' ){
            
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            return json_encode($response , 16);
            exit;
            
        }
        
        if( !empty($response2->results) ){
            
            foreach ( $response2->results as $items ){
                
                $eventId = $items->id;
                $time = $items->time;
                $runner1 = $items->home->name;
                $runner2 = $items->away->name;
                
                //$check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                
                //if( $check != null ){
                    $responseArr['upcoming'][] = [
                        'eventId' => $eventId,
                        'time' => $time,
                        'runner1' => $runner1,
                        'runner2' => $runner2
                    ];
                //}
                
            }
            
        }
        
        if( $response1->success != '0' && $response2->success != '0' ){
            
            $responseText = [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
            
            $responseText = json_encode($responseText , 16);
            
            if( !file_exists($file) ){
                $fileCreate = fopen($file, "w") or die("Unable to open file!");
                fwrite($fileCreate, $responseText);
                fclose($fileCreate);
            }
            
            if( !file_exists($fileNew) ){
                $fileCreate = fopen($fileNew, "w") or die("Unable to open file!");
                fwrite($fileCreate, $responseText);
                fclose($fileCreate);
            }
            
            
            
            $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
            
            return json_encode($response , 16);
            exit;
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
    }
    
    // Cricket: Get Exchange Web from API
    public function actionExchangeWeb()
    {
        $basePath = \Yii::$app->basePath;
        
        $file = $basePath.'/uploads/json/cricket/cricket.json';
        $fileNew = $basePath.'/uploads/json/cricket/cricket_live.json';
        
        if( !file_exists($fileNew) ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "File not exist!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        if( file_exists($file) ){
            if( !file_exists($fileNew) ){
                rename($file,$fileNew);
            }
        }
        
        $responseData = json_decode(file_get_contents( $fileNew ));
        
        if( null !== $responseData && $responseData->status != 1 ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        $eventList = [];
        if( null !== $responseData->data->items->inplay ){
            $inplay = $responseData->data->items->inplay;
            foreach ( $inplay as $data ){
                $eventList[] = $data->eventId;
            }
            
        }
        if( null !== $responseData->data->items->upcoming ){
            $upcoming = $responseData->data->items->upcoming;
            foreach ( $upcoming as $data ){
                $eventList[] = $data->eventId;
            }
        }
        
        //echo '<pre>';print_r($eventList);die;
        
        $errors = [];
        
        foreach ( $eventList as $eID ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $responseData = json_decode(curl_exec($ch));
            curl_close($ch);
            //echo '<pre>';print_r($responseData);die;
            if( $responseData->success != 1 && null !== $responseData->results ){
                $errors[] = $eID;
            }
            
            $responseArr = [];
            
            if( !empty( $responseData->results ) && !empty( $responseData->results[0]->markets ) ){
                
                $marketsArr = $responseData->results[0]->markets[0];
                $marketName = $marketsArr->description->marketName;
                $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                $marketId = $marketsArr->marketId;
                $eventId = $marketsArr->market->eventId;
                //$rules = $marketsArr->licence->rules;
                
                $runnersArr = [];
                $time = strtotime(date('Y-m-d H:i:s'));
                $title = $marketsArr->runners[0]->description->runnerName .' Vs '.$marketsArr->runners[1]->description->runnerName;
                
                foreach ( $marketsArr->runners as $runners ){
                    
                    $back = $lay = [];
                    /*$back = $lay = [
                     'price' => '-',
                     'size' => '-'
                     ];*/
                    $runnerName = $runners->description->runnerName;
                    if( isset( $runners->exchange->availableToBack ) ){
                        
                        foreach ( $runners->exchange->availableToBack as $backData ){
                            $back[] = [
                                'price' => number_format($backData->price , 2),
                                'size' => number_format($backData->size , 2),
                            ];
                            $odd = number_format($backData->price , 2);
                            $this->updateUnmatchedData($eventId,$marketId,'back',$odd,$runnerName);
                        }
                        
                        /*$back = [
                         'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                         'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                         ];*/
                    }
                    
                    if( isset( $runners->exchange->availableToLay ) ){
                        
                        foreach ( $runners->exchange->availableToLay as $layData ){
                            $lay[] = [
                                'price' => number_format($layData->price , 2),
                                'size' => number_format($layData->size , 2),
                            ];
                            $odd = number_format($layData->price , 2);
                            $this->updateUnmatchedData($eventId,$marketId,'lay',$odd,$runnerName);
                        }
                        
                        /*$lay = [
                         'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                         'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                         ];*/
                    }
                    
                    //$profitLoss = $this->getProfitLossOnBet($eventId , $runners->description->runnerName );
                    
                    $runnersArr[] = [
                        'selectionId' => $runners->selectionId,
                        'runnerName' => $runners->description->runnerName,
                        'runnerId' => $runners->description->metadata->runnerId,
                        'profit_loss' => '',//$profitLoss,
                        'exchange' => [
                            'back' => $back,
                            'lay' => $lay
                        ]
                    ];
                    
                }
                
                $getEvent = EventsPlayList::find()->select(['suspended','ball_running'])->where(['event_id' => $eID])->asArray()->one();
                $suspended = $ballRunning = 'N';
                if( $getEvent != null ){
                    $suspended = $getEvent['suspended'];
                    $ballRunning = $getEvent['ball_running'];
                }
                
                $responseArr[] = [
                    'title' => $title,
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    'time' => $time,
                    'marketName' => $marketName,
                    'matched' => $totalMatched,
                    'runners' => $runnersArr,
                    //'rules' => $rules
                ];
            }
            
            $responseText = [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
            $responseText = json_encode($responseText , 16);
            
            $fileEx = $basePath.'/uploads/json/cricket/'.$eID.'.json';
            
            $file = fopen($fileEx, "w") or die("Unable to open file!");
            fwrite($file, $responseText);
            fclose($file);
            
        }
        
        if( $errors != null ){
            $message = 'This event list are not updated:'.json_encode($errors);
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => $message ] ];
            return json_encode($response , 16);
            exit;
        }else{
            $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
            return json_encode($response , 16);
            exit;
            
        }
        
    }
    // Cricket: Get Exchange Web from API
    public function actionExchangeWebNew()
    {
        $basePath = \Yii::$app->basePath;
        
        $file = $basePath.'/uploads/json/cricket/cricket.json';
        $fileNew = $basePath.'/uploads/json/cricket/cricket_live.json';
        
        if( !file_exists($fileNew) ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "File not exist!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        if( file_exists($file) ){
            if( !file_exists($fileNew) ){
                rename($file,$fileNew);
            }
        }
        
        $responseData = json_decode(file_get_contents( $fileNew ));
        
        if( null !== $responseData && $responseData->status != 1 ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        $eventList = [];
        if( null !== $responseData->data->items->inplay ){
            $inplay = $responseData->data->items->inplay;
            foreach ( $inplay as $data ){
                $eventList[] = $data->eventId;
            }
            
        }
        if( null !== $responseData->data->items->upcoming ){
            $upcoming = $responseData->data->items->upcoming;
            foreach ( $upcoming as $data ){
                $eventList[] = $data->eventId;
            }
        }
        
        //echo '<pre>';print_r($eventList);die;
        
        $errors = [];
        
        foreach ( $eventList as $eID ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $responseData = json_decode(curl_exec($ch));
            curl_close($ch);
            //echo '<pre>';print_r($responseData);die;
            if( $responseData->success != 1 && null !== $responseData->results ){
                $errors[] = $eID;
            }
            
            $responseArr = [];
            
            if( !empty( $responseData->results ) && !empty( $responseData->results[0]->markets ) ){
                
                $marketsAll = $responseData->results[0]->markets;
                
                foreach ( $marketsAll as $marketsArr ){
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $eventId = $marketsArr->market->eventId;
                    //$rules = $marketsArr->licence->rules;
                    
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    
                    $title = $marketsArr->runners[0]->description->runnerName .' Vs '.$marketsArr->runners[1]->description->runnerName;
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [];
                        /*$back = $lay = [
                         'price' => '-',
                         'size' => '-'
                         ];*/
                        $runnerName = $runners->description->runnerName;
                        if( isset( $runners->exchange->availableToBack ) ){
                            
                            foreach ( $runners->exchange->availableToBack as $backData ){
                                $back[] = [
                                    'price' => number_format($backData->price , 2),
                                    'size' => number_format($backData->size , 2),
                                ];
                                $odd = number_format($backData->price , 2);
                                $this->updateUnmatchedData($eventId,$marketId,'back',$odd,$runnerName);
                                
                            }
                            /*$back = [
                             'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                             'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                             ];*/
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            
                            foreach ( $runners->exchange->availableToLay as $layData ){
                                $lay[] = [
                                    'price' => number_format($layData->price , 2),
                                    'size' => number_format($layData->size , 2),
                                ];
                                $odd = number_format($layData->price , 2);
                                $this->updateUnmatchedData($eventId,$marketId,'lay',$odd,$runnerName);
                                
                            }
                            /*$lay = [
                             'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                             'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                             ];*/
                        }
                        
                        //$profitLoss = $this->getProfitLossOnBet($eventId , $runners->description->runnerName );
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'profit_loss' => '',
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    //$check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    //if( $check != null ){
                    $responseArr[] = [
                        'title' => $title,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'time' => $time,
                        'marketName' => $marketName,
                        'matched' => $totalMatched,
                        'runners' => $runnersArr,
                        //'rules' => $rules
                    ];
                    //}
                }
            }
            
            $responseText = [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
            $responseText = json_encode($responseText , 16);
            
            $fileEx = $basePath.'/uploads/json/cricket/'.$eID.'_full.json';
            
            $file = fopen($fileEx, "w") or die("Unable to open file!");
            fwrite($file, $responseText);
            fclose($file);
            
        }
        
        if( $errors != null ){
            $message = 'This event list are not updated:'.json_encode($errors);
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => $message ] ];
            return json_encode($response , 16);
            exit;
        }else{
            $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
            return json_encode($response , 16);
            exit;
            
        }
        
    }
    // Cricket: Get Exchange Web from API
    public function actionExchangeWebNewOLD()
    {
        $eID = \Yii::$app->request->get( 'id' );//$_GET['id'];
        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response1 = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response1);die;
        //$event = $response1->results[0]->event;
        $responseArr = [];
        
        if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
            
            $marketsAll = $response1->results[0]->markets;
            
            foreach ( $marketsAll as $marketsArr ){
                
                $marketName = $marketsArr->description->marketName;
                $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                $marketId = $marketsArr->marketId;
                $eventId = $marketsArr->market->eventId;
                $rules = $marketsArr->licence->rules;
                
                $runnersArr = [];
                
                $time = strtotime(date('Y-m-d H:i:s'));
                
                
                $title = $marketsArr->runners[0]->description->runnerName .' Vs '.$marketsArr->runners[1]->description->runnerName;
                
                foreach ( $marketsArr->runners as $runners ){
                    
                    $back = $lay = [];
                    /*$back = $lay = [
                     'price' => '-',
                     'size' => '-'
                     ];*/
                    
                    if( isset( $runners->exchange->availableToBack ) ){
                        
                        foreach ( $runners->exchange->availableToBack as $backData ){
                            $back[] = [
                                'price' => number_format($backData->price , 2),
                                'size' => number_format($backData->size , 2),
                            ];
                        }
                        
                        /*$back = [
                         'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                         'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                         ];*/
                    }
                    
                    if( isset( $runners->exchange->availableToLay ) ){
                        
                        foreach ( $runners->exchange->availableToLay as $layData ){
                            $lay[] = [
                                'price' => number_format($layData->price , 2),
                                'size' => number_format($layData->size , 2),
                            ];
                        }
                        
                        /*$lay = [
                         'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                         'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                         ];*/
                    }
                    
                    $profitLoss = $this->getProfitLossOnBet($eventId , $runners->description->runnerName );
                    
                    $runnersArr[] = [
                        'selectionId' => $runners->selectionId,
                        'runnerName' => $runners->description->runnerName,
                        'runnerId' => $runners->description->metadata->runnerId,
                        'profit_loss' => $profitLoss,
                        'exchange' => [
                            'back' => $back,
                            'lay' => $lay
                        ]
                    ];
                    
                }
                
                //$check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                
                //if( $check != null ){
                $responseArr[] = [
                    'title' => $title,
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'time' => $time,
                    'marketName' => $marketName,
                    'matched' => $totalMatched,
                    'runners' => $runnersArr,
                    'rules' => $rules
                ];
                //}
            }
        }
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    // Cricket: update Unmatched Data
    public function updateUnmatchedData($eventId,$marketId,$type,$odd,$runnerName)
    {
        $where = [ 
            'event_id' => $eventId, 'market_id' => $marketId, 'bet_type' => $type, 
            'price' => $odd,'runner' => $runnerName,'match_unmatch' => 0
        ];
        
        PlaceBet::updateAll(['match_unmatch'=>1],$where);
        return;
        
    }
    
    // Cricket: RenamedFile
    public function actionRenamedFile()
    {
        $basePath = \Yii::$app->basePath;
        //$file = $basePath.'/uploads/json/cricket/cricket.json';
        $fileNew = $basePath.'/uploads/json/cricket/cricket_live.json';
        
        //if( file_exists($fileNew) ){
        //    unlink($fileNew);
        //}
        //rename($file,$fileNew);
        
        $responseData = json_decode(file_get_contents( $fileNew ));
        
        if( $responseData->status != 1 ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        $eventList = [];
        if( null !== $responseData->data->items->inplay ){
            $inplay = $responseData->data->items->inplay;
            foreach ( $inplay as $data ){
                $eventList[] = $data->eventId;
            }
            
        }
        if( null !== $responseData->data->items->upcoming ){
            $upcoming = $responseData->data->items->upcoming;
            foreach ( $upcoming as $data ){
                $eventList[] = $data->eventId;
            }
        }
        $error = false;
        foreach ( $eventList as $event ){
            
            $eventFile = $basePath.'/uploads/json/cricket/'.$event.'.json';
            $eventFileNew = $basePath.'/uploads/json/cricket/'.$event.'_live.json';
            
            if( file_exists($eventFile) ){
                if( file_exists($eventFileNew) ){
                    unlink($eventFileNew);
                }
                rename($eventFile,$eventFileNew);
            }else{
                $error = true;
            }
        }
        
        if( $error != true ){
            $response = [ "status" => 1 , "success" => [ "message" => "Data saved successfuly!" ] ];
            return json_encode($response , 16);
            exit;
        }else{
            $response = [ "status" => 1 , "success" => [ "message" => "Plz Call New Exchange!" ] ];
            return json_encode($response , 16);
            exit;
        }
        
        
        
    }
    
    
}
