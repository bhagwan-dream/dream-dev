<?php
namespace api\modules\v2\modules\events\controllers;

use yii\helpers\ArrayHelper;
use common\models\Event;
use common\models\PlaceBet;
use common\models\TransactionHistory;
use yii\helpers\Url;
use common\models\MarketType;
use common\models\User;
use common\models\Setting;
use common\models\EventsPlayList;
use common\models\ManualSession;
use common\models\BallToBallSession;
use common\models\GlobalCommentary;
use common\models\EventsRunner;

class FootballController extends \common\controllers\aController  // \yii\rest\Controller
{
    //protected $apiUserToken = '15727-8puafDrdScO1Rn';//'13044-CgPWGpYSAOn7aV';
    //protected $apiUserId = '5bf52bb732f91';//'5bcb17c84f03a';
    
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
    
    // Cricket: Get data inplay and upcoming list from API
    public function actionIndexOLDBETSAPI()
    {
        //CODE for live call api
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=1&token=15815-peDeUY8w5a9rPq';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $eventList = curl_exec($ch);
        curl_close($ch);
        $eventListInplay = json_decode($eventList);
        //echo '<pre>';print_r($eventListInplay);die;
        //CODE for live call api
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=1&token=15815-peDeUY8w5a9rPq';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $eventList = curl_exec($ch);
        curl_close($ch);
        $eventListUpcoming = json_decode($eventList);
        
        $responseArr = [];
        $responseArr['inplay']['market'] = [];
        $responseArr['upcoming']['market'] = [];
        
        if( !empty($eventListInplay->results)){
            
            foreach ( $eventListInplay->results as $result ){
                
                $marketId = $result->id;
                $eventId = $result->id;
                $time = $result->time;
                $status = 'OPEN';
                $marketName = trim($result->home->name).' v '.trim($result->away->name);
                $totalMatched = '';//$this->getMatchTotalVal($marketId,$eventId);
                $runnersArr = [];
                
                if( isset($result->home) && !empty($result->home)
                    && isset($result->away) && !empty($result->away) ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => ''
                        ];
                        
                        $runnersArr[0] = [
                            'selectionId' => $result->home->id,
                            'runnerName' => $result->home->name,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                        $runnersArr[1] = [
                            'selectionId' => $result->away->id,
                            'runnerName' => $result->away->name,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                }
                
                $responseArr['inplay']['market'][] = [
                    'sportId' => 1,
                    'slug' => 'football',
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'time' => $time,
                    'status' => $status,
                    'marketName' => $marketName,
                    'matched' => $totalMatched,
                    'suspended' => 'N',
                    'ballRunning' => 'N',
                    'runners' => $runnersArr
                ];
                
            }
            
        }
        
        if( isset( $eventListUpcoming->results ) ){
            
            foreach ( $eventListUpcoming->results as $result ){
                
                $marketId = $result->id;
                $eventId = $result->id;
                $time = $result->time;
                $status = 'OPEN';
                $marketName = trim($result->home->name).' v '.trim($result->away->name);
                $totalMatched = '';//$this->getMatchTotalVal($marketId,$eventId);
                $runnersArr = [];
                
                if( isset($result->home) && !empty($result->home)
                    && isset($result->away) && !empty($result->away) ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => ''
                        ];
                        
                        $runnersArr[0] = [
                            'selectionId' => $result->home->id,
                            'runnerName' => $result->home->name,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                        $runnersArr[1] = [
                            'selectionId' => $result->away->id,
                            'runnerName' => $result->away->name,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                }
                
                $responseArr['upcoming']['market'][] = [
                    'sportId' => 1,
                    'slug' => 'football',
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'time' => $time,
                    'status' => $status,
                    'marketName' => $marketName,
                    'matched' => $totalMatched,
                    'runners' => $runnersArr
                ];
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    // Football: Get data inplay and upcoming list from API
    public function actionIndex()
    {
        //CODE for live call api
        $url = $this->apiUrl.'?event_id=1';
        if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
            $url = $this->apiUrlFootball;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        $responseArr = [];
        $responseArr['inplay']['market'] = [];
        $responseArr['upcoming']['market'] = [];
        
        if( !empty($responseData->result)){
            
            foreach ( $responseData->result as $result ){
                
                if( isset( $result->inPlay ) && $result->inPlay == true ){
                    
                    $marketId = $result->id;
                    $eventId = $result->event->id;
                    $time = $result->start;
                    $status = $result->status;
                    $marketName = $result->event->name;
                    $totalMatched = $result->matched;//$this->getMatchTotalVal($marketId,$eventId);
                    $runnersArr = [];
                    
                    if( isset($result->runners) && !empty($result->runners) ){
                        
                        foreach ( $result->runners as $runners ){
                            
                            $back = $lay = [
                                'price' => '-',
                                'size' => ''
                            ];
                            
                            $selectionId = $runners->id;
                            $runnerName = $runners->name;
                            if( isset( $runners->back ) && !empty( $runners->back ) ){
                                $back = [
                                    'price' => number_format($runners->back[0]->price , 2),
                                    'size' => number_format($runners->back[0]->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                            
                            if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                                $lay = [
                                    'price' => number_format($runners->lay[0]->price,2),
                                    'size' => number_format($runners->lay[0]->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'runnerName' => $runnerName,
                                'exchange' => [
                                    'back' => $back,
                                    'lay' => $lay
                                ]
                            ];
                            
                        }
                        
                    } 
                    
                    if( !in_array($eventId, $this->checkUnBlockList() ) ){
                    
                        $responseArr['inplay']['market'][] = [
                            'sportId' => 1,
                            'slug' => 'football',
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'status' => $status,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                            'runners' => $runnersArr
                        ];
                    }
                    
                }else{
                    
                    $marketId = $result->id;
                    $eventId = $result->event->id;
                    $time = $result->start;
                    $status = $result->status;
                    $marketName = $result->event->name;
                    $totalMatched = $result->matched;//$this->getMatchTotalVal($marketId,$eventId);
                    $runnersArr = [];
                    if( isset($result->runners) && !empty($result->runners) ){
                        
                        foreach ( $result->runners as $runners ){
                            
                            $back = $lay = [
                                'price' => '-',
                                'size' => ''
                            ];
                            
                            $selectionId = $runners->id;
                            $runnerName = $runners->name;
                            if( isset( $runners->back ) && !empty( $runners->back ) ){
                                $back = [
                                    'price' => number_format($runners->back[0]->price , 2),
                                    'size' => number_format($runners->back[0]->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                            
                            if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                                $lay = [
                                    'price' => number_format($runners->lay[0]->price,2),
                                    'size' => number_format($runners->lay[0]->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'runnerName' => $runnerName,
                                'exchange' => [
                                    'back' => $back,
                                    'lay' => $lay
                                ]
                            ];
                            
                        }
                        
                    }
                    
                    if( !in_array($eventId, $this->checkUnBlockList() ) ){
                    
                        $responseArr['upcoming']['market'][] = [
                            'sportId' => 1,
                            'slug' => 'football',
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'status' => $status,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }

    
    // Cricket: Get data inplay and upcoming list from API
    public function actionEventList()
    {
        $eventList = (new \yii\db\Query())
        ->select('*')
        ->from('events_play_list')
        ->where(['sport_id' => 1 , 'game_over' => 'NO' ])
        ->all();
        //echo '<pre>';print_r($eventList);die;
        $responseArr = [];
        $responseArr['inplay']['market'] = [];
        $responseArr['upcoming']['market'] = [];
        
        if( $eventList != null ){
            
            foreach ( $eventList as $event ){
                
                if( $event['play_type'] == 'IN_PLAY' ){
                    
                    $marketId = $event['market_id'];
                    $eventId = $event['event_id'];
                    $time = $event['event_time'];
                    $status = $event['status'];
                    $marketName = $event['event_name'];
                    $totalMatched = '';
                    $suspended = $event['suspended'];
                    $ballRunning = $event['ball_running'];
                    $runnersArr = [];
                    
                    $runnerData = (new \yii\db\Query())
                    ->select(['selection_id','runner'])
                    ->from('events_runners')
                    ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                    ->all();
                    
                    if( $runnerData != null ){
                        $cache = \Yii::$app->cache;
                        $oddsData = $cache->get($marketId);
                        $oddsData = json_decode($oddsData);
                        //echo '<pre>';print_r($oddsData);die;
                        if( $oddsData != null && $oddsData->odds != null ){
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
                        foreach ( $runnerData as $runners ){
                            
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
                            
                            $selectionId = $runners['selection_id'];
                            $runnerName = $runners['runner'];
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'runnerName' => $runnerName,
                                'exchange' => [
                                    'back' => $back[$i],
                                    'lay' => $lay[$i]
                                ]
                            ];
                            $i++;
                        }
                        
                    }
                    
                    if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                        $responseArr['inplay']['market'][] = [
                            'sportId' => 1,
                            'slug' => 'football',
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'status' => $status,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'suspended' => $suspended,
                            'ballRunning' => $ballRunning,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }else{
                    
                    $marketId = $event['market_id'];
                    $eventId = $event['event_id'];
                    $time = $event['event_time'];
                    $status = $event['status'];
                    $marketName = $event['event_name'];
                    $totalMatched = '';
                    $suspended = $event['suspended'];
                    $ballRunning = $event['ball_running'];
                    $runnersArr = [];
                    
                    $runnerData = (new \yii\db\Query())
                    ->select(['selection_id','runner'])
                    ->from('events_runners')
                    ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                    ->all();
                    
                    if( $runnerData != null ){
                        
                        $cache = \Yii::$app->cache;
                        $oddsData = $cache->get($marketId);
                        $oddsData = json_decode($oddsData);
                        //echo '<pre>';print_r($oddsData);die;
                        if( $oddsData != null && $oddsData->odds != null ){
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
                        foreach ( $runnerData as $runners ){
                            
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
                            
                            $selectionId = $runners['selection_id'];
                            $runnerName = $runners['runner'];
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'runnerName' => $runnerName,
                                'exchange' => [
                                    'back' => $back[$i],
                                    'lay' => $lay[$i]
                                ]
                            ];
                            $i++;
                        }
                        
                    }
                    
                    if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                        $responseArr['upcoming']['market'][] = [
                            'sportId' => 1,
                            'slug' => 'football',
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'status' => $status,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'suspended' => $suspended,
                            'ballRunning' => $ballRunning,
                            'runners' => $runnersArr
                        ];
                    }
                    
                }
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    // Cricket: Event Match Odds from API
    public function actionEventMatchOdds()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            $event = (new \yii\db\Query())
            ->select(['event_id', 'market_id','event_name','play_type','event_time','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id' => $eId , 'sport_id' => 1])
            ->one();
            
            //echo '<pre>';print_r($event);die;
            if( $event != null ){
                
                $playType = $event['play_type'];
                $marketId = $event['market_id'];
                $eventId = $event['event_id'];
                $title = $event['event_name'];
                $time = $event['event_time'];
                $suspended = $event['suspended'];
                $ballRunning = $event['ball_running'];
                
                $runnerData = (new \yii\db\Query())
                ->select(['selection_id','runner'])
                ->from('events_runners')
                ->where(['event_id' => $eventId ])
                ->all();
                
                //echo '<pre>';print_r($runnerData);die;
                if( $runnerData != null ){
                    
                    foreach( $runnerData as $runner ){
                        $back = $lay = [
                            'price' => '-',
                            'size' => ''
                        ];
                        $runnerName = $runner['runner'];
                        $selectionId = $runner['selection_id'];
                        
                        $runnersArr[] = [
                            'selectionId' => $selectionId,
                            'runnerName' => $runnerName,
                            'profit_loss' => $this->getProfitLossMatchOdds($marketId, $eventId, $selectionId, 'match_odd'),
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $marketArr[] = [
                        'sportId' => 1,
                        'slug' => 'football',
                        'title' => $title,
                        'playType'=>'IN_PLAY',//$playType,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'time' => $time,
                        'marketName'=>'Match Odds',
                        'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                    ];
                }
                
            }
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Cricket: Get data inplay and upcoming list from API
    public function actionGetOdds()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $responseArr = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $marketId = $r_data['market_id'];
            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
            if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
                $url = $this->apiUrlMatchOddLive.'?id='.$marketId;
            }
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
            
            $response = [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
            
        }
        
        return $response;
    }
    
    
    //check database function
    public function checkUnBlockList()
    {
        $uId = \Yii::$app->user->id;
        $user = User::find()->select( ['parent_id'] )
        ->where(['id'=>$uId])->one();
        $pId = 1;
        if( $user != null ){
            $pId = $user->parent_id;
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$pId,'market_type' => 'all'])->all();
        
        if( $listArr != null ){
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
        }
        return $newList;
        
    }
    
    //Football - Inplay Today Tomorrow
    public function actionInplayTodayTomorrow()
    {
        $dataArr = $footballInplay = $footballToday = $footballTomorrow = [];
        //$eventList = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])->asArray()->all();
        $marketArr = [];
        
        //CODE for live call api
        $url = $this->apiUrl.'?event_id=1';
        if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
            $url = $this->apiUrlFootball;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        if( isset($responseData->result) && !empty($responseData->result) ){
            //echo '<pre>';print_r($responseData);die;
            foreach ( $responseData->result as $result ){
                
                if( $result->inPlay == true ){
                    $runnersArr = $marketArr = [];
                    $marketId = $result->id;
                    $eventId = $result->groupById;
                    
                    foreach ( $result->runners as $runners ){
                        
                        $back = $lay = [];
                        $selectionId = $runners->id;
                        $runnerName = $runners->name;
                        if( isset( $runners->back ) && !empty( $runners->back ) ){
                            foreach ( $runners->back as $backArr ){
                                $back[] = [
                                    'price' => number_format($backArr->price , 2),
                                    'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                        }
                        
                        if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                            foreach ( $runners->lay as $layArr ){
                                $lay[] = [
                                    'price' => number_format($layArr->price,2),
                                    'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                            }
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $selectionId,
                            'runnerName' => $runnerName,
                            'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $marketArr[0] = [
                        'slug' => 'football',
                        'sportId' => 1,
                        'title' => $result->event->name,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                        'time' => $result->start,
                        'marketName'=>'Match Odds',
                        'matched' => $result->matched,//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                    ];
                    
                    if( !in_array($eventId, $this->checkUnBlockList() ) ){
                    
                        $footballInplay[] = [
                            'slug' => 'football',
                            'sportId' => 1,
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                            'market' => $marketArr
                        ];
                    }
                    
                }else{
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start/1000 ));
                    
                    if( $today == $eventDate ){
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $footballToday[] = [
                                'sportId' => 1,
                                'slug' => 'football',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                                'suspended' => 'N',
                                'ballRunning' => 'N',
                            ];
                        }
                    }
                    
                    if( $tomorrow == $eventDate ){
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $footballTomorrow[] = [
                                'sportId' => 1,
                                'slug' => 'football',
                                'event_id' => $eventId,
                                'event_name' => $result->event->name,
                                'event_time' => $result->start,
                                'suspended' => 'N',
                                'ballRunning' => 'N',
                            ];
                        }
                    }
                    
                }
                
            }
        }
        
        $dataArr['inplay'] = [
            [
                'title' => 'Football',
                'list' => $footballInplay
            ]
        ];
        
        $dataArr['today'] = [
            [
                'title' => 'Football',
                'list' => $footballToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ]
        ];
        
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
        
    }
    
    // Tennis: Get Match Odds from API
    public function actionMatchOdds()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            $event = (new \yii\db\Query())
            ->select(['event_id', 'market_id','event_name','play_type','event_time','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id' => $eId , 'sport_id' => 1])
            ->one();
            
            //echo '<pre>';print_r($event);die;
            if( $event != null ){
                
                $playType = $event['play_type'];
                $marketId = $event['market_id'];
                $eventId = $event['event_id'];
                $title = $event['event_name'];
                $time = $event['event_time'];
                $suspended = $event['suspended'];
                $ballRunning = $event['ball_running'];
                
                $runnerData = (new \yii\db\Query())
                ->select(['selection_id','runner'])
                ->from('events_runners')
                ->where(['event_id' => $eventId ])
                ->all();
                
                //echo '<pre>';print_r($runnerData);die;
                if( $runnerData != null ){
                    //CODE for live call api
                    $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                    if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
                        $url = $this->apiUrlMatchOddLive.'?id='.$marketId;
                    }
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    
                    foreach( $runnerData as $runner ){
                        $back = $lay = [];
                        $runnerName = $runner['runner'];
                        $selectionId = $runner['selection_id'];
                        
                        if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                            
                            foreach ( $responseData->runners as $runners ){
                                
                                if( $runners->selectionId == $selectionId ){
                                    if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                        foreach ( $runners->ex->availableToBack as $backArr ){
                                            $back[] = [
                                                'price' => number_format($backArr->price , 2),
                                                'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                            ];
                                            //$this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                                        }
                                    }
                                    
                                    if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                        foreach ( $runners->ex->availableToLay as $layArr ){
                                            $lay[] = [
                                                'price' => number_format($layArr->price,2),
                                                'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                            ];
                                            //$this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                                        }
                                    }
                                }
                                
                            }
                            
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $selectionId,
                            'runnerName' => $runnerName,
                            'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $marketArr[] = [
                        'sportId' => 1,
                        'slug' => 'football',
                        'title' => $title,
                        'playType'=>'IN_PLAY',//$playType,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'time' => $time,
                        'marketName'=>'Match Odds',
                        'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                    ];
                    
                }
                
            }
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Football: Get Match Odds from API
    public function actionMatchOddsOLD()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            $event = (new \yii\db\Query())
            ->select(['event_id', 'market_id','event_name','play_type','event_time','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id' => $eId , 'sport_id' => 1])
            ->one();
            
            //echo '<pre>';print_r($event);die;
            if( $event != null ){
                $playType = $event['play_type'];
                $marketId = $event['market_id'];
                $eventId = $event['event_id'];
                $title = $event['event_name'];
                $time = $event['event_time'];
                $suspended = $event['suspended'];
                $ballRunning = $event['ball_running'];
                //CODE for live call api
                //$url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $responseData = curl_exec($ch);
                curl_close($ch);
                $responseData = json_decode($responseData);
                
                //echo '<pre>';print_r($responseData);die;
                $runnerNameArr = explode(' v ', $title);
                $runner[0] = 'Runner 1';
                $runner[1] = 'Runner 2';
                $runner[2] = 'The Draw';
                if( is_array($runnerNameArr) && count( $runnerNameArr ) > 0 ){
                    $runner[0] = trim($runnerNameArr[0]);
                    //$runner[1] = $runnerNameArr[1];
                    if( isset( $runnerNameArr[1] ) ){
                        $runnerNameArr1 = explode('(', $runnerNameArr[1]);
                        if( is_array($runnerNameArr1) && count( $runnerNameArr1 ) > 0 ){
                            $runner[1] = trim($runnerNameArr1[0]);
                        }
                    }
                }
                
                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                    $i = 0;
                    foreach ( $responseData->runners as $runners ){
                        $back = $lay = [];
                        $selectionId = $runners->selectionId;
                        $runnerName = $runner[$i];
                        if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                            foreach ( $runners->ex->availableToBack as $backArr ){
                                $back[] = [
                                    'price' => number_format($backArr->price , 2),
                                    'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                                $this->updateUnmatchedData($eventId, $marketId, 'back', $backArr->price, $selectionId);
                            }
                        }
                        
                        if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                            foreach ( $runners->ex->availableToLay as $layArr ){
                                $lay[] = [
                                    'price' => number_format($layArr->price,2),
                                    'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                ];
                                $this->updateUnmatchedData($eventId, $marketId, 'lay', $layArr->price, $selectionId);
                            }
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $selectionId,
                            'runnerName' => $runnerName,
                            'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        $i++;
                    }
                    
                    $marketArr[] = [
                        'title' => $title,
                        'playType'=>$playType,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => $suspended,
                        'ballRunning' => $ballRunning,
                        'time' => $time,
                        'marketName'=>'Match Odds',
                        'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                    ];
                }
            }
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Cricket: updateUnmatchedData
    public function updateUnmatchedData($eventId,$marketId,$type,$odd,$secId)
    {
        $betIds = [];
        if( $type == 'lay' ){
            $where = [
                'event_id' => $eventId, 'market_id' => $marketId, 'bet_type' => $type,
                'sec_id' => $secId,'match_unmatch' => 0
            ];
            $andWhere = [ '>=' , 'price' , $odd ];
        }else{
            $where = [
                'event_id' => $eventId, 'market_id' => $marketId, 'bet_type' => $type,
                'price' => $odd,'sec_id' => $secId,'match_unmatch' => 0
            ];
            $andWhere = [ '<=' , 'price' , $odd ];
        }
        
        $betList = PlaceBet::find()->select(['id'])
        ->where($where)->andWhere($andWhere)->asArray()->all();
        //echo '<pre>';print_r($betList);die;
        if( $betList != null ){
            
            foreach ( $betList as $bet ){
                $betIds[] = $bet['id'];
            }
            if( $betIds != null ){
                PlaceBet::updateAll(['match_unmatch'=>1],['id'=>$betIds]);
            }
            
        }
        
        return;
    }
    
    // Football: Commentary
    public function actionCommentary(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            
            $id = \Yii::$app->request->get( 'id' );
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>6423 , 'event_id'=>$id]);
            
            if( $commentary != null ){
                $response = [
                    "status" => 1 ,
                    "data" => [ "title" => $commentary->title ],
                ];
            }else{
                $response = [
                    "status" => 1 ,
                    "data" => [ "title" => 'No Data!' ],
                ];
            }
        }else{
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>6423 , 'event_id'=>0]);
            
            if( $commentary != null ){
                $response = [
                    "status" => 1 ,
                    "data" => [ "title" => $commentary->title ],
                ];
            }else{
                $response = [
                    "status" => 1 ,
                    "data" => [ "title" => 'No Data!' ],
                ];
            }
            
        }
        
        return $response;
    }
    
    // Football: Get GetProfitLoss API
    public function actionGetProfitLoss()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $sessionType = $r_data['session_type'];
            $data = [];
            //$selArr = \Yii::$app->db->createCommand('SELECT sec_id FROM place_bet GROUP BY market_id HAVING market_id ='.$marketId)->queryAll();
            
            //CODE for live call api
            //$url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
            /*$url = $this->apiUrlMatchOdd.'?id='.$marketId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);*/
            //echo '<pre>';print_r($responseData);die;
            
            /*if( isset($responseData->runners) && !empty($responseData->runners) ){
                
                foreach ( $responseData->runners as $runners ){
                    
                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->selectionId,$sessionType);
                    
                    $data[] = [
                        'secId' => $runners->selectionId,
                        'profitLoss' => $profitLoss
                    ];
                }
            }*/
            
            $eventRunner = EventsRunner::findAll(['event_id'=>$eventId , 'market_id'=>$marketId]);
            if( $eventRunner != null ){
                foreach ( $eventRunner as $runners ){
                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->selection_id,$sessionType);
                    $data[] = [
                        'secId' => $runners->selection_id,
                        'profitLoss' => $profitLoss
                    ];
                }
            }
            
            $response = [ 'status' => 1 , 'data' => $data ];
            
        }
        
        return $response;
    }
    
    // Football: get Profit Loss Match Odds
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
    
    // Football: get Profit Loss On Bet
    public function getProfitLossOnBet($eventId,$runner)
    {
        $userId = \Yii::$app->user->id;
        
        if( $runner != 'The Draw' ){
            
            // IF RUNNER WIN
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layDrwWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layDrwWin[0]['val']);die;
            
            if( $layDrwWin == null || !isset($layDrwWin[0]['val']) || $layDrwWin[0]['val'] == '' ){
                $layDrwWin = 0;
            }
            
            $totalWin = $backWin + $layWin + $layDrwWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backDrwLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backDrwLoss[0]['val']);die;
            
            if( $backDrwLoss == null || !isset($backDrwLoss[0]['val']) || $backDrwLoss[0]['val'] == '' ){
                $backDrwLoss = 0;
            }
            
            $totalLoss = $backLoss + $layLoss + $backDrwLoss;
            
            $total = $totalWin-$totalLoss;
            
        }else{
            
            // IF RUNNER WIN
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }
            
            $totalWin = $backWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)
            ->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $otherLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($otherLoss[0]['val']);die;
            
            if( $otherLoss == null || !isset($otherLoss[0]['val']) || $otherLoss[0]['val'] == '' ){
                $otherLoss = 0;
            }
            
            $totalLoss = $layLoss + $otherLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
        
    }
    
    // Football: Get Master Name Data
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
    
    // Football: Get Master Id Data
    public function getMasterId($id){
        
        if( $id != null ){
            
            $user = User::find()->select(['parent_id'])->where([ 'id' => $id ])->one();
            if( $user != null ){
                return $user->parent_id;
            }else{
                return '1';
            }
            
        }
        return '1';
        
    }
    
    // Football: Place Bet
    public function actionPlacebet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_dataArr = ArrayHelper::toArray( $request_data );
            
            foreach( $r_dataArr as $r_data ){
                
                if( $r_data['session_type'] == 'match_odd'){
                        
                        $data['PlaceBet'] = $r_data;
                        $model = new PlaceBet();
                        if ($model->load($data)) {
                            $model->match_unmatch = 0;
                            $price = $model->price;
                            if( $model->runner != null ){
                                if( $r_data['market_name'] == null ){
                                    $model->market_name = $model->runner;
                                }
                            }
                            if( $model->bet_type == 'back' && trim($model->rate) >= trim($model->price) ){
                                $model->match_unmatch = 1;
                                $model->price = $model->rate;
                            }
                            if( $model->bet_type == 'lay' && trim($model->rate) <= trim($model->price) ){
                                $model->match_unmatch = 1;
                                $model->price = $model->rate;
                            }
                            
                            if( $model->bet_type == 'back' ){
                                $model->win = ( $model->size*$model->price ) - $model->size;
                                $model->loss = $model->size;
                            }else{
                                $model->win = $model->size;
                                if( $model->price > 1 ){
                                    $model->loss = ($model->price-1)*$model->size;
                                }else{
                                    $model->loss = $model->price*$model->size;
                                }
                            }
                            
                            $uid = \Yii::$app->user->id;
                            $type = $model->bet_type;
                            
                            $getUserBalance = $this->checkAvailableBalance($uid,$model);
                            //echo '<pre>';print_r($getUserBalance);die;
                            if( ( $getUserBalance['expose'] > $getUserBalance['balance'] ) ){
                                $response[ "error" ] = [
                                    "message" => "Insufficient funds!"
                                ];
                                return $response;
                            }
                            
                            //echo '<pre>';print_r($model);die;
                            if( $model->event_id != null ){
                                
                                $event = EventsPlayList::findOne(['sport_id' => 1,'event_id' => $model->event_id , 'market_id' => $model->market_id ]);
                                
                                if( $event != null && $event->game_over == 'YES'){
                                    $response[ "error" ] = [
                                        "message" => "This event is already closed!"
                                    ];
                                    return $response;
                                }
                                if( $event != null && $event->suspended == 'Y' && $event->ball_running == 'Y'){
                                    $response[ "error" ] = [
                                        "message" => "Bet cancelled can not place!" ,
                                    ];
                                    return $response;
                                }
                                
                                if( $event != null && $event->min_stack > $model->size ){
                                    $minStack = $event->min_stack;
                                    $response[ "error" ] = [
                                        "message" => "Minimum stack value is ".$minStack ,
                                    ];
                                    return $response;
                                }
                                
                                if( $event != null && $event->max_stack < $model->size ){
                                    $maxStack = $event->max_stack;
                                    $response[ "error" ] = [
                                        "message" => "Maximum stack value is ".$maxStack,
                                    ];
                                    return $response;
                                }
                                
                                if( $event != null && $event->max_profit != 0 ){
                                    
                                    $maxProfit = $event->max_profit;
                                    
                                    if( $model->bet_type == 'back' ){
                                        $profit = ( $model->size*$model->price ) - $model->size;
                                    }else{
                                        $profit = $model->size;
                                    }
                                    
                                    if( $maxProfit < $profit){
                                        $response[ "error" ] = [
                                            "message" => "Maximum profit value is ".$maxProfit ,
                                            "data" => $model->errors
                                        ];
                                        return $response;
                                    }
                                }
                                
                            }
                            
                            $model->sport_id = 1;
                            $model->bet_status = 'Pending';
                            $model->user_id = $uid;
                            $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                            $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                            
                            $play = EventsPlayList::find()->select(['event_name'])
                            ->where(['sport_id' => 1,'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])->asArray()->one();
                            $eventName = 'Undefined Event';
                            if( $play != null ){
                                $eventName = $play['event_name'];
                            }
                            
                            $model->description = 'Football > '.$eventName.' > '.$model->runner;
                            
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
                            
                            if( $model->save() ){
                                
                                $type = $model->bet_type;
                                $runner = $model->runner;
                                $size = $model->size;
                                $rate = $model->rate;
                                
                                if( $model->match_unmatch != 0 ){
                                    $msg = "Bet ".$type." ".$runner.",<br>Placed ".$size." @ ".$price." Odds <br> Matched ".$size." @ ".$rate." Odds";
                                    $response = [
                                        'status' => 1 ,
                                        "success" => [
                                            "message" => $msg
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
                                    "message" => "Somthing wrong!" ,
                                    "data" => $model->errors
                                ];
                            }
                            
                        }
                        
                }
            }
            
        }
        
        return $response;
    }
    
    public function getBalanceValUNUSED($uid,$cBet)
    {
        $user = User::find()->select(['balance','expose_balance'])->where(['id' => $uid ])->one();
        $exposeBalVal = $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];
        if( $user != null ){
            $mywallet = $user->balance;
            $user_balance = $user->balance;
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;
            
            $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ] )
            ->andWhere(['!=','market_id',$cBet->market_id])
            ->groupBy(['market_id'])->asArray()->all();
            //echo '<pre>';print_r($marketList);
            if( $marketList != null ){
                $maxBal['expose'] = [];
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
            
            $eventNew = EventsPlayList::findOne(['market_id'=>$cBet->market_id]);
            
            if( $eventNew != null ){
                $eventId = $eventNew->event_id;
                $marketId = $cBet->market_id;
                $runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);
                if( $runnersData != null ){
                    $balExpose = $balPlus = [];
                    foreach ( $runnersData as $runners ){
                        $profitLoss = $this->getProfitLossMatchOddsNew($marketId,$eventId,$runners->selection_id,'match_odd',$cBet);
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
            
            
            if( isset( $maxBal['expose'] ) && $maxBal['expose'] != null && array_sum( $maxBal['expose'] ) < 0 ){
                $expose_balance = (-1)*( array_sum( $maxBal['expose'] ) );
            }
            
            return  $data = [ 'balance' => $mywallet,'expose' => $expose_balance,'plus' => 0];
            
        }
        return  $data = [ 'balance' => 0,'available' => 0,'expose' => 0,'plus' => 0];
        
    }
    
    
    // Cricket: get Profit Loss Match Odds
    public function getProfitLossMatchOddsNew($marketId,$eventId,$selId,$sessionType,$cBet)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
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
            
            $where = [ 'market_id' => $marketId ,'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
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
            
            $where = [ 'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $totalLoss = $backLoss + $layLoss;
            
            if( $cBet->market_id == $marketId ){
                
                if( $selId == $cBet->sec_id ){
                    
                    if( $cBet->bet_type == 'back' ){
                        
                        if( $cBet->match_unmatch == 1 )
                            $totalWin = $totalWin+$cBet->win;
                            
                    }else{
                        $totalLoss = $totalLoss+$cBet->loss;
                    }
                    
                }else{
                    
                    if( $cBet->bet_type == 'back' ){
                        $totalLoss = $totalLoss+$cBet->loss;
                    }else{
                        if( $cBet->match_unmatch == 1 )
                            $totalWin = $totalWin+$cBet->win;
                    }
                    
                }
            }
            
            $total = $totalWin-$totalLoss;
            
        }
        
        return $total;
        
    }
    
    public function getProfitLossMatchOddsNewAll($marketId,$eventId,$selId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
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
    
    // Football: getUserBalanceUpdate
    public function getUserBalanceUpdate($data,$uid)
    {
        //Match Odd PL
        $marketId = $data['market_id'];
        $eventId  = $data['event_id'];
        $user = User::findOne(['id'=>$uid]);
        if( $user != null ){
            $expose_balance = $user->expose_balance;
            $balance = $user->balance;
        }
        
        $available = $balance-$expose_balance;
        
        $balExpose = $balPlus = [];$expose = $plus = 0;
        
        //getProfitLossMatchOdds
        $event = EventsPlayList::findOne(['market_id'=>$marketId]);
        if( $event != null ){
            $eventId = $event->event_id;
            $runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);
            if( $runnersData != null ){
                
                foreach ( $runnersData as $runners ){
                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->selection_id,'match_odd');
                    //echo $profitLoss;die;
                    if( $profitLoss < 0 ){
                        $balExpose[] = $profitLoss;
                    }else{
                        $balPlus[] = $profitLoss;
                    }
                    
                    $profitLossNew[$runners->selection_id] = $profitLoss;
                    
                }
            }
            //echo '<pre>';print_r($profitLossNew);die;
            
            if( $balExpose != null ){
                $expose = min($balExpose);
            }
            if( $balPlus != null ){
                $plus = max($balPlus);
            }
            
            if( $data['bet_type'] == 'lay' && ( $profitLossNew[$data['sec_id']] > 0 ) ){
                $available = $plus+((-1)*$expose)+$available;
                
                /*$lossUnMatchOdd = $this->getLossUnMatchOdds($marketId,$data['sec_id']);
                
                if( $lossUnMatchOdd > 0 ){
                    $available = $available-$lossUnMatchOdd;
                }*/
                
            }
            
            if( $data['bet_type'] == 'back' && ( $profitLossNew[$data['sec_id']] < 0 ) ){
                $available = ((-1)*$expose)+$available;
                
                $lossUnMatchOdd = $this->getLossUnMatchOdds($marketId,$data['sec_id']);
                
                if( $lossUnMatchOdd > 0 ){
                    $available = $available-$lossUnMatchOdd;
                }
            }
            
            
            return  $data = ['available' => $available,'expose' => $expose,'plus' => $plus];
            /*\Yii::$app->db->createCommand()
             ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
             ->execute();*/
            
        }
    }
    
    // Football: getLossUnMatchOdds
    public function getLossUnMatchOdds($marketId,$secId)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        $where = [ 'sec_id'=>$secId,'market_id'=>$marketId,'match_unmatch' => 0,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => 'match_odd' ];
        $lossUnMatch = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
        
        if( $lossUnMatch == null || !isset($lossUnMatch[0]['val']) || $lossUnMatch[0]['val'] == '' ){
            $total = 0;
        }else{ $total = $lossUnMatch[0]['val']; }
        
        return $total;
        
    }
    
    // Function to get the Tras Description
    public function getDescription($betId,$eventId)
    {
        $runner = $type = $session = $event_name = $size = '';
        
        $betData = PlaceBet::find()->select(['event_id','market_id','sec_id','runner','bet_type','session_type' , 'size'])
        ->where([ 'id' => $betId,'event_id' => $eventId,'status'=>1, 'bet_status' => 'Pending' ])->one();
        if( $betData != null ){
            $runner = $betData->runner;
            $type = $betData->bet_type;
            $session = $betData->session_type;
            $size = $betData->size;
        }
        
        if( $session == 'fancy' ){
            if( $betData->sec_id == 0 ){
                $manualSession = ManualSession::find()->select(['title','yes','no','rate'])
                ->where([ 'id' => $betData->market_id , 'event_id' => $betData->event_id , 'game_over' => 'NO' , 'status' => 1 ])
                ->one();
                
                if( $manualSession != null ){
                    $session = 'Fancy | '.$manualSession->title.' | Yes('.$manualSession->yes.') | No('.$manualSession->no.') | Rate('.$manualSession->rate.')';
                }
            }else{
                $manualSession = ManualSession::find()->select(['title','yes','no','rate'])
                ->where([ 'id' => $betData->market_id , 'event_id' => $betData->event_id , 'game_over' => 'NO' , 'status' => 1 ])
                ->one();
                
                if( $manualSession != null ){
                    
                    $manualSessionBall = BallToBallSession::find()->select(['over','ball','yes','no','rate'])
                    ->where([ 'id' => $betData->sec_id,'manual_session_id' => $betData->market_id , 'event_id' => $betData->event_id , 'status' => 1 ])
                    ->one();
                    
                    if($manualSessionBall != null ){
                        $session = 'Fancy | '.$manualSession->title.' | Over('.$manualSessionBall->over.') Ball('.$manualSessionBall->ball.') | Yes('.$manualSessionBall->yes.') | No('.$manualSessionBall->no.') | Rate('.$manualSessionBall->rate.')';
                    }
                    
                }
            }
        }else{
            $session = $session .' | '.$type;
        }
        
        $eventData = EventsPlayList::find()->select(['event_name'])
        ->where([ 'event_id' => $eventId, 'status'=>1 ])->one();
        
        if( $eventData != null ){
            $event_name = $eventData->event_name;
        }
        
        return 'Cricket | '.$event_name.' | '.$runner.' | '.$session.' | '.$size;
    }
    
    // Function to get the client Commission Rate
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
    }
    
    // Function to check max stack val
    public function defaultMaxStack()
    {
        $max_stack = 0;
        $setting = Event::find()->select(['max_stack'])
        ->where([ 'event_type_id' => '1' , 'status' => 1 ])->one();
        if( $setting != null ){
            return $setting->max_stack;
        }else{
            return $max_stack;
        }
        
    }
    
    // Function to check min stack val
    public function defaultMinStack()
    {
        $min_stack = 0;
        $setting = Event::find()->select(['min_stack'])
        ->where([ 'event_type_id' => '1' , 'status' => 1 ])->one();
        if( $setting != null ){
            return $setting->min_stack;
        }else{
            return $min_stack;
        }
    }
    
    // Function to check max profit limit val
    public function defaultMaxProfit()
    {
        $max_profit = 0;
        $setting = Event::find()->select(['max_profit'])
        ->where([ 'event_type_id' => '1' , 'status' => 1 ])->one();
        if( $setting != null ){
            return $setting->max_profit;
        }else{
            return $max_profit;
        }
        
    }
    
    // Function to get the client current Balance
    public function currentBalance($uid,$eventId,$marketId,$secId,$sessionType,$type)
    {
        $user_balance = 0;
        $user = User::find()->select(['balance','expose_balance'])->where(['id' => $uid ])->one();
        
        if( $user != null ){
            $user_balance = $user->balance;
            $expose_balance = $user->expose_balance;
            
            if( $user_balance >= $expose_balance ){
                $user_balance = $user_balance-$expose_balance;
            }else{
                $user_balance = 0;
            }
            
            if( $sessionType == 'match_odd' ){
                $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$secId,$sessionType);
                if( $profitLoss != '' ){
                    if( $profitLoss > 0 && $type == 'lay' ){
                        $user_balance = $user_balance+$profitLoss;
                    }
                    if( $profitLoss < 0 && $type == 'back' ){
                        $profitLoss = (-1)*$profitLoss;
                        $user_balance = $user_balance+$profitLoss;
                    }
                }
            }
            
            /*if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
             $profitLossData = $this->getProfitLossFancyForExpose($eventId,$marketId,$sessionType);
             $balExpose = $balPlus = [];
             if( $profitLossData != null ){
             foreach ( $profitLossData as $profitLoss ){
             if( $profitLoss < 0 ){
             $balExpose[] = $profitLoss;
             }else{
             $balPlus[] = $profitLoss;
             }
             }
             if( $type == 'yes' && $balExpose != null ){
             $profitLoss = min($balExpose);
             $profitLoss = (-1)*$profitLoss;
             $user_balance = $user_balance+$profitLoss;
             }
             if( $type == 'no' && $balPlus != null ){
             $profitLoss = max($balPlus);
             $user_balance = $user_balance+$profitLoss;
             }
             }
             
             }*/
            
        }
        return $user_balance;
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
