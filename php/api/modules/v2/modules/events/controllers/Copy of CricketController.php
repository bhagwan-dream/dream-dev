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
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionLottery;
use common\models\ManualSessionLotteryNumbers;
use common\models\ManualSessionMatchOddData;
use common\models\EventsRunner;

class CricketController extends \common\controllers\aController  // \yii\rest\Controller
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
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token=15815-peDeUY8w5a9rPq';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $eventList = curl_exec($ch);
        curl_close($ch);
        $eventListInplay = json_decode($eventList);
        //echo '<pre>';print_r($eventListInplay);die;
        //CODE for live call api
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token=15815-peDeUY8w5a9rPq';
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
                    'sportId' => 4,
                    'slug' => 'cricket',
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
                    'sportId' => 4,
                    'slug' => 'cricket',
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
    
    // Cricket: Get data inplay and upcoming list from API
    public function actionIndex()
    {
        //CODE for live call api
        $url = $this->apiUrl.'?event_id=4';
        if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
            $url = $this->apiUrlCricket;
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
                    
                    $responseArr['inplay']['market'][] = [
                        'sportId' => 4,
                        'slug' => 'cricket',
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
                    
                    $responseArr['upcoming']['market'][] = [
                        'sportId' => 4,
                        'slug' => 'cricket',
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
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrow()
    {
        $dataArr = $cricketInplay = $cricketToday = $cricketTomorrow = [];
        //$eventList = EventsPlayList::find()->where(['game_over'=>'NO','status'=>1])->asArray()->all();
        $marketArr = [];
        
        //CODE for live call api
        $url = $this->apiUrl.'?event_id=4';
        if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] != 'localhost') && ($_SERVER['HTTP_HOST'] != '192.168.0.4') ){
            $url = $this->apiUrlCricket;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseData = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($responseData);
        
        if( isset($responseData->result) && !empty($responseData->result) ){
            
            foreach ( $responseData->result as $result ){
                
                if( $result->inPlay == true ){
                    $runnersArr = $marketArr = [];
                    //echo '<pre>';print_r($responseDataCricket);die;
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
                        'title' => $result->event->name,
                        'marketId' => $marketId,
                        'eventId' => $eventId,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                        'time' => $result->start,
                        'marketName'=>'Match Odds',
                        'matched' => $result->matched,//$this->getMatchTotalVal($marketId,$eventId),
                        'runners' => $runnersArr,
                        'slug' => 'cricket',
                        'sportId' => 4,
                    ];
                        
                    $cricketInplay[] = [
                        'slug' => 'cricket',
                        'sportId' => 4,
                        'event_id' => $eventId,
                        'event_name' => $result->event->name,
                        'event_time' => $result->start,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                        'market' => $marketArr
                    ];
                    
                }else{
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start/1000 ));
                    
                    if( $today == $eventDate ){
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        $cricketToday[] = [
                            'slug' => 'cricket',
                            'sportId' => 4,
                            'event_id' => $eventId,
                            'event_name' => $result->event->name,
                            'event_time' => $result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                    
                    if( $tomorrow == $eventDate ){
                        $marketId = $result->id;
                        $eventId = $result->groupById;
                        
                        $cricketTomorrow[] = [
                            'slug' => 'cricket',
                            'sportId' => 4,
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
        
        $dataArr['inplay'] = [ 
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ]
        ];
        
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ]
        ];
        
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
        
    }
    
    // Cricket: Get GetProfitLoss API
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
            if( $sessionType == 'match_odd' ){
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
                
                if( isset($responseData->runners) && !empty($responseData->runners) ){
                    
                    foreach ( $responseData->runners as $runners ){
                        
                        $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->selectionId,$sessionType);
                        
                        $data[] = [
                            'secId' => $runners->selectionId,
                            'profitLoss' => $profitLoss
                        ];
                    }
                }
            }
            if( $sessionType == 'match_odd2' ){
                $matchOddData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);
                if( $matchOddData != null ){
                    foreach ( $matchOddData as $matchOdd ){
                        
                        $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$matchOdd->sec_id,$sessionType);
                        
                        $data[] = [
                            'secId' => $matchOdd->sec_id,
                            'profitLoss' => $profitLoss
                        ];
                    }
                }
                
                
            }
            
            
            $response = [ 'status' => 1 , 'data' => $data ];
            
        }
        
        return $response;
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
        
        return $total;
        /*if( $total != 0 ){
            return $total;
        }else{
            return '';
        }*/
        
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
            $price = $r_data['price'];
            $sessionType = $r_data['session_type'];
            
            if( $r_data['price'] != 0 ){
                $profitLossData = $this->getProfitLossFancy($eventId,$marketId,$sessionType,$price);
            }else{
                $profitLossData = $this->getProfitLossFancyOnZero($eventId,$marketId,$sessionType);
            }
            
            
            $response = [ 'status' => 1 , 'data' => $profitLossData ];
            
        }
        
        return $response;
    }
    
    // Cricket: get ProfitLoss Fancy 
    public function getProfitLossFancy($eventId,$marketId,$sessionType,$priceVal)
    {
        $userId = \Yii::$app->user->id;
        $dataReturn = [];
        //$total = $totalLoss = $totalWin = 0;
        if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
            
            // IF RUNNER LOSS
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId,'market_id' => $marketId ];
            
            $betList = PlaceBet::find()
            ->select(['bet_type','price','win','loss'])
            ->where( $where )->asArray()->all();
            
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
                    
                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => array_sum($data[$i]),
                    ];
                    
                }
                
            }
            
        }
        
        return $dataReturn;
    }
    
    // Cricket: get ProfitLoss Fancy
    public function getProfitLossFancyForExpose($eventId,$marketId,$sessionType,$n)
    {
        $userId = \Yii::$app->user->id;
        $dataReturn = [];
        //$total = $totalLoss = $totalWin = 0;
        //if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
        $priceVal = 10;
        $where = [ 'session_type' => $sessionType,'user_id' => $userId,'market_id' => $marketId , 'price' => $n ];
        
        $betList = PlaceBet::find()
        ->select(['bet_type','price','win','loss'])
        ->where( $where )->asArray()->all();
        
        /*$betMax = PlaceBet::find()
        ->select(['price'])
        ->where( $where )->orderBy(['price'=>SORT_DESC])->asArray()->one();
        if( $betMax != null ){
            $priceVal = $betMax['price'];
        }*/
        
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
    
    // Cricket: get ProfitLoss Fancy
    public function getProfitLossFancyOnZero($eventId,$marketId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $dataReturn[] = [
            'price' => 'no data',
            'profitLoss' => 'no data',
        ];
        //$total = $totalLoss = $totalWin = 0;
        if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
            
            // IF RUNNER LOSS
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId,'market_id' => $marketId ];
            
            $betList = PlaceBet::find()
            ->select(['bet_type','price','win','loss'])
            ->where( $where )->asArray()->all();
            
            $betMax = PlaceBet::find()
            ->select(['price'])
            ->where( $where )->orderBy(['price'=>SORT_DESC])->asArray()->one();
            if( $betMax != null ){
                $priceVal = $betMax['price'];
            }else{
                $priceVal = 10;
            }
            
            if( $betList != null ){
                
                $priceStart = $priceVal-7;
                
                if( $priceStart < 0 ){
                    $priceStart = 0;
                }
                
                $priceEnd = $priceVal+7;
                
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
                    
                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => array_sum($data[$i]),
                    ];
                    
                }
                
            }
            
        }
        
        return $dataReturn;
    }
    
    // Cricket: get ProfitLoss Fancy Zero Price
    public function getProfitLossFancyOnZeroOLD($eventId,$marketId,$sessionType,$priceVal)
    {
        $userId = \Yii::$app->user->id;
        $dataReturn = [];
        //$total = $totalLoss = $totalWin = 0;
        if( $sessionType == 'fancy' || $sessionType == 'fancy2' ){
            
            // IF RUNNER LOSS
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId,'market_id' => $marketId ];
            
            $betMax = PlaceBet::find()
            ->select(['price'])
            ->where( $where )->orderBy(['price'=>SORT_DESC])->asArray()->one();
            
            $betList = PlaceBet::find()
            ->select(['bet_type','price','win','loss'])
            ->where( $where )->asArray()->all();
            
            if( $betMax != null ){
                $rangeArr = $this->bookRange($betMax['price']);
            }
            
            //echo '<pre>';print_r($rangeArr);die;
            
            if( $rangeArr != null && $betList != null ){
                
                foreach ( $rangeArr as $range ){
                
                    $rangeNew = explode('-', $range);
                    
                    if( $rangeNew != null && count($rangeNew) > 1 ){
                        
                        $priceStart = $rangeNew[0];
                        
                        $priceEnd = $rangeNew[1];
                        
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
                            
                            $dataReturn[] = [
                                'price' => $i,
                                'profitLoss' => array_sum($data[$i]),
                            ];
                            
                        }
                        
                        //echo '<pre>';print_r($dataNew);die;
                        
                    }
                }
                
            }
            
        }
        
        return $dataReturn;
    }
    
    public function bookRange($max)
    {
        if( $max > 0 && $max < 50 ){
            $data = ['0-9','10-24','25-49','50+'];
        }else if( $max > 50 && $max < 100 ){
            $data = ['0-24','25-49','50-74','75-99','100+'];
        }else if($max > 100 && $max < 200){
            $data = ['0-44','45-74','75-124','125-199','200+'];
        }else{
            $data = ['0-49','50-99','100-174','175-249','250+'];
        }
        
        return $data;
        
    }
    
    // Cricket: Get Current Event Score from API
    public function actionCurrentEventScore()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $eID = \Yii::$app->request->get( 'id' );
        
        if( $eID != null ){
            
            $items = $this->getCurrentEventScore($eID);
            
            $response =  [ "status" => 1 , "data" => [ "items" => $items ] ];
        }
        
        return $response;
    }
    
    // Cricket: getCurrentEventScore
    public function getCurrentEventScore($eventId)
    {
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eventId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        //echo '<pre>';print_r($response);die;
        
        if( $response->success == 1 && !empty( $response->results ) ){
            
            $eventName = 'Undefine Event';
            
            if( !empty( $response->results[0]->event ) ){
                $event = $response->results[0]->event;
                $eventName = $event->name;
            }
            
            if( !empty( $response->results[0]->timeline ) ){
                $score = $response->results[0]->timeline->score;
                
                if( !empty($score->home) && !empty($score->away) ){
                    
                    $items[$score->home->name] = [];
                    
                    if( !empty($score->home->inning1) ){
                        $items[$score->home->name]['inning1'] = [
                            'overs' => $score->home->inning1->overs,
                            'runs' => $score->home->inning1->runs,
                            'wickets' => $score->home->inning1->wickets,
                        ];
                        
                        $items['current'] = [
                            'name' => $score->home->name,
                            'score' => $items[$score->home->name]['inning1']
                        ];
                        
                    }
                    
                    if( !empty($score->home->inning2) ){
                        $items[$score->home->name]['inning2'] = [
                            'overs' => $score->home->inning2->overs,
                            'runs' => $score->home->inning2->runs,
                            'wickets' => $score->home->inning2->wickets,
                        ];
                        
                        $items['current'] = [
                            'name' => $score->home->name,
                            'score' => $items[$score->home->name]['inning2']
                        ];
                    }
                    
                    if( !empty($score->away->inning1) ){
                        $items[$score->away->name]['inning1'] = [
                            'overs' => $score->away->inning1->overs,
                            'runs' => $score->away->inning1->runs,
                            'wickets' => $score->away->inning1->wickets,
                        ];
                        
                        $items['current'] = [
                            'name' => $score->away->name,
                            'score' => $items[$score->away->name]['inning1']
                        ];
                    }
                    
                    if( !empty($score->away->inning2) ){
                        $items[$score->away->name]['inning2'] = [
                            'overs' => $score->away->inning2->overs,
                            'runs' => $score->away->inning2->runs,
                            'wickets' => $score->away->inning2->wickets,
                        ];
                        
                        $items['current'] = [
                            'name' => $score->away->name,
                            'score' => $items[$score->away->name]['inning2']
                        ];
                        
                        
                    }
                    
                    
                }
                
            }
            
        }
        
        return $items;
        
    }
    
    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBet($eventId,$runner,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        
        if( $runner != 'The Draw' ){
            
            // IF RUNNER WIN
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layDrwWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layDrwWin[0]['val']);die;
            
            if( $layDrwWin == null || !isset($layDrwWin[0]['val']) || $layDrwWin[0]['val'] == '' ){
                $layDrwWin = 0;
            }else{ $layDrwWin = $layDrwWin[0]['val']; }
            
            $totalWin = $backWin + $layWin + $layDrwWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backDrwLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backDrwLoss[0]['val']);die;
            
            if( $backDrwLoss == null || !isset($backDrwLoss[0]['val']) || $backDrwLoss[0]['val'] == '' ){
                $backDrwLoss = 0;
            }else{ $backDrwLoss = $backDrwLoss[0]['val']; }
            
            $totalLoss = $backLoss + $layLoss + $backDrwLoss;
            
            $total = $totalWin-$totalLoss;
            
        }else{
            
            // IF RUNNER WIN
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $totalWin = $backWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)
            ->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $otherLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($otherLoss[0]['val']);die;
            
            if( $otherLoss == null || !isset($otherLoss[0]['val']) || $otherLoss[0]['val'] == '' ){
                $otherLoss = 0;
            }else{ $otherLoss = $otherLoss[0]['val']; }
            
            $totalLoss = $layLoss + $otherLoss;
            
            $total = $totalWin-$totalLoss;
            
        }
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
        
    }
    
    // Cricket: Get Match Odds from Bets API
    public function actionMatchOddsOLDBETSAPI()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            //CODE for live call api
            //$url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token=15815-peDeUY8w5a9rPq&event_id='.$eId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            //echo '<pre>';print_r($responseData);die;
            $responseData = json_decode($responseData);
            
            if( isset( $responseData->success ) && $responseData->success == 1 && isset( $responseData->results ) && $responseData->results != null ){
                
                foreach ( $responseData->results as $event ){
                    //echo '<pre>';print_r($event);die;
                    $eventId = $eId;
                    $title = $event->event->name;
                    $time = strtotime($event->event->openDate);
                    $suspended = 'N';
                    $ballRunning = 'N';
                    
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
                    
                    if( !empty( $event->markets ) && !empty( $event->markets ) ){
                        
                        $markets = $event->markets[0];
                        
                        if( isset( $markets ) && !empty( $markets ) ){
                            
                            $marketId = $markets->marketId;
                            
                            $i = 0;
                            foreach ( $markets->runners as $runners ){
                                $back = $lay = [];
                                $selectionId = $runners->description->metadata->runnerId;
                                $runnerName = $runner[$i];
                                if( isset( $runners->exchange->availableToBack ) && !empty( $runners->exchange->availableToBack ) ){
                                    foreach ( $runners->exchange->availableToBack as $backArr ){
                                        $back[] = [
                                            'price' => number_format($backArr->price , 2),
                                            'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                        ];
                                    }
                                }
                                
                                if( isset( $runners->exchange->availableToLay ) && !empty( $runners->exchange->availableToLay ) ){
                                    foreach ( $runners->exchange->availableToLay as $layArr ){
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
                                $i++;
                            }
                            
                            $marketArr[] = [
                                'sportId' => 4,
                                'slug' => 'cricket',
                                'title' => $title,
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
                
            }
            
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Cricket: Get Match Odds from API
    public function actionMatchOdds()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            $event = (new \yii\db\Query())
            ->select(['event_id', 'market_id','event_name','play_type','event_time','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id' => $eId , 'sport_id' => 4])
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
                    //$url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
                    $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);
                    
                    //echo '<pre>';print_r($responseData);die;
                    /*$runnerNameArr = explode(' v ', $title);
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
                    }*/
                
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
                        'sportId' => 4,
                        'slug' => 'cricket',
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
                    
                    /*if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                        $i = 0;
                        foreach ( $responseData->runners as $runners ){
                            $back = $lay = [];
                            $selectionId = $runners->selectionId;
                            
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( $runners->selectionId ){
                                    
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
                                }
                                
                            $i++;
                        }
                        
                    }*/
                }
                
            }
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Cricket: Get UnMatch To Match For MatchOdds from API
    public function actionUnMatchToMatchForMatchOdds()
    {
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            $event = (new \yii\db\Query())
            ->select(['event_id', 'market_id','event_name','event_time','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['event_id' => $eId , 'sport_id' => 4])
            ->one();
            
            if( $event != null ){
                
                $marketId = $event['market_id'];
                $eventId = $event['event_id'];
                
                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
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
                            foreach ( $runners->ex->availableToBack as $backArr ){
                                $this->updateUnmatchedData($eventId, $marketId, 'back', number_format($backArr->price , 2), $selectionId);
                            }
                        }
                        
                        if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                            foreach ( $runners->ex->availableToLay as $layArr ){
                                $this->updateUnmatchedData($eventId, $marketId, 'lay', number_format($backArr->price , 2), $selectionId );
                            }
                        }
                    }
                }
            }
            
        }
        return;
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
    
    // Cricket: Get Match Odds from API
    public function actionMatchOddsOld()
    {
        $runnersArr = $marketArr = [];
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eId = \Yii::$app->request->get( 'id' );
            
            //CODE for live call api
            $url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);
            
            //echo '<pre>';print_r($responseData);die;
            if( !empty( $responseData->result ) && !empty( $responseData->result ) ){
                
                foreach ( $responseData->result as $result ){
                    
                    $marketId = $result->id;
                    $eventId = $result->event->id;
                    $title = $result->event->name;
                    $time = $result->start;
                    if( $eId == $result->event->id ){
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
                        
                        $marketArr[] = [
                            'title' => $title,
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                            'time' => $time,
                            'marketName'=>'Match Odds',
                            'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                            'runners' => $runnersArr,
                        ];
                    }
                }
            }
            
        }
        return [ "status" => 1 , "data" => [ "items" => $marketArr ] ];
    }
    
    // Cricket: Get ManualSession Data
    public function actionManualSession()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eID = \Yii::$app->request->get( 'id' );
            $query = ManualSession::find()->select(['id' , 'event_id','market_id', 'title' , 'no_yes_val_2' , 'rate_2','suspended','ball_running' ])
                ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $eID ] );
            
            $countQuery = clone $query; $count =  $countQuery->count();
            
            $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
            
            //echo '<pre>';print_r($models);die;
            $items = [];
            if($models != null){
                foreach($models as $data){
                    
                    $no1 = $yes1 = $no2 = $yes2 = '-';
                    $dataVal = [];
                    /*if( $data['no_yes_val_1'] != null ){
                        
                        $no_yes1 = explode('/', $data['no_yes_val_1']);
                        
                        if( count($no_yes1) > 1 ){
                            $no1 = $no_yes1[0];
                            $yes1 = $no_yes1[1];
                        }
                        
                        $rate1 = explode('/', $data['rate_1']);
                        
                        if( count($rate1) > 1 ){
                            $yes_rate1 = $rate1[0];
                            $no_rate1 = $rate1[1];
                        }
                        
                        $dataVal[0] = [
                            'no' => $no1,
                            'no_rate' => $no_rate1,
                            'yes' => $yes1,
                            'yes_rate' => $yes_rate1
                        ];
                        
                    }*/
                    
                    if( $data['no_yes_val_2'] != null && $data['no_yes_val_2'] != 0 ){
                    
                        $no_yes2 = explode('/', $data['no_yes_val_2']);
                        
                        if( count($no_yes2) > 1 ){
                            $no2 = $no_yes2[0];
                            $yes2 = $no_yes2[1];
                        }
                        
                        $rate2 = explode('/', $data['rate_2']);
                        
                        if( count($rate2) > 1 ){
                            $yes_rate2 = $rate2[0];
                            $no_rate2 = $rate2[1];
                        }
                        
                        $dataVal[0] = [
                            'no' => $no2,
                            'no_rate' => $no_rate2,
                            'yes' => $yes2,
                            'yes_rate' => $yes_rate2
                        ];
                        
                    }
                    
                    //$profitLoss = $this->getManualSessionProfitLossOnBet($data['event_id'], $data['market_id'],'fancy');
                    
                    $items[] = [
                        'id' => $data['id'],
                        'event_id' => $data['event_id'],
                        'market_id' => $data['market_id'],
                        'title' => $data['title'],
                        'suspended' => $data['suspended'],
                        'ballRunning' => $data['ball_running'],
                        'profitloss' => '',
                        'data' => $dataVal,
                        'sportId' => 4,
                        'slug' => 'cricket',
                    ];
                }
            }
            
            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => $count ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    // Cricket: Get ManualSession Fancy Data
    public function actionManualSessionFancy()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );
            
            //CODE for live call api
            $url = $this->apiUrlFancy.'?eventId='.$eventId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);
            
            //echo '<pre>';print_r($responseData);die;
            $items = [];
            if( $responseData->status == 200 ){
                
                if( isset( $responseData->data ) && !empty( $responseData->data ) ){
                    
                    foreach ( $responseData->data as $key=>$data ){
                        
                        if( $key != 'active' ){
                        
                            $marketId = $data->market_id;
                            $title = $data->headname;
                            
                            $dataVal[0] = [
                                'no' => $data->SessInptNo,
                                'no_rate' => $data->NoValume,
                                'yes' => $data->SessInptYes,
                                'yes_rate' => $data->YesValume,
                            ];
                            
                            $items[] = [
                                'market_id' => $marketId,
                                'event_id' => $eventId,
                                'title' => $title,
                                'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                'profitloss' => '',
                                'data' => $dataVal,
                                'sportId' => 4,
                                'slug' => 'cricket',
                            ];
                        }
                    }   
                    
                }
                
            }
            
            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => count($items) ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    // Cricket: get ManualSession Profit Loss On Bet
    public function getManualSessionProfitLossOnBet($eventId,$marketId ,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $where = [ 'session_type' => $sessionType, 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId ];
        // IF RUNNER WIN
        $betWinList = PlaceBet::find()->select(['SUM(win) as totalWin'])->where( $where )->asArray()->all();
        // IF RUNNER LOSS
        $betLossList = PlaceBet::find()->select(['SUM(loss) as totalLoss'])->where( $where )->asArray()->all();
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
    
    
    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLossOnBet($eventId,$marketId ,$selectionId)
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
    
    // Cricket: Get ManualSessionBalltoball Data
    public function actionManualSessionBalltoball()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $msId = \Yii::$app->request->get( 'id' );
            
            $manualSession = ManualSession::find()->select(['id' , 'event_id', 'title' ])
            ->where( [ 'status_ball_to_ball' => 1 , 'status' => 1 , 'game_over' => 'NO' , 'id' => $msId ] )->one();
            
            if( $manualSession != null ){
                
                $query = BallToBallSession::find()->select(['id' , 'event_id','manual_session_id', 'over' , 'ball' , 'no_yes_val_1' , 'no_yes_val_2' , 'rate_1' , 'rate_2' ])
                ->where( [ 'status' => 1 , 'manual_session_id' => $msId ] );
                
                $countQuery = clone $query; $count =  $countQuery->count();
                
                $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
                
                if( $models != null ){
                    
                    foreach($models as $data){
                        
                        $no1 = $yes1 = $no2 = $yes2 = '-';
                        $dataVal = [];
                        if( $data['no_yes_val_1'] != null ){
                            
                            $no_yes1 = explode('/', $data['no_yes_val_1']);
                            
                            if( count($no_yes1) > 1 ){
                                $no1 = $no_yes1[0];
                                $yes1 = $no_yes1[1];
                            }
                            
                            $rate1 = explode('/', $data['rate_1']);
                            
                            if( count($rate1) > 1 ){
                                $yes_rate1 = $rate1[0];
                                $no_rate1 = $rate1[1];
                            }
                            
                            $dataVal[0] = [
                                'no' => $no1,
                                'no_rate' => $no_rate1,
                                'yes' => $yes1,
                                'yes_rate' => $yes_rate1
                            ];
                            
                        }
                        
                        if( $data['no_yes_val_2'] != null && $data['no_yes_val_2'] != 0 ){
                            
                            $no_yes2 = explode('/', $data['no_yes_val_2']);
                            
                            if( count($no_yes2) > 1 ){
                                $no2 = $no_yes2[0];
                                $yes2 = $no_yes2[1];
                            }
                            
                            $rate2 = explode('/', $data['rate_2']);
                            
                            if( count($rate2) > 1 ){
                                $yes_rate2 = $rate2[0];
                                $no_rate2 = $rate2[1];
                            }
                            
                            $dataVal[1] = [
                                'no' => $no2,
                                'no_rate' => $no_rate2,
                                'yes' => $yes2,
                                'yes_rate' => $yes_rate2
                            ];
                            
                        }
                        
                        $items[] = [
                            'id' => $data['id'],
                            'event_id' => $data['event_id'],
                            'manual_session_id' => $data['manual_session_id'],
                            'over' => $data['over'],
                            'ball' => $data['ball'],
                            'data' => $dataVal,
                        ];
                    }
                    
                    $response =  [ "status" => 1 , "data" => [ "items" => $items ,"title" => $manualSession->title , "count" => $count ] ];
                }else{
                    $response =  [ "status" => 1 , "data" => [ "items" => [] ,"title" => $manualSession->title , "count" => 0 ] ];
                }
                
            }else{
                $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            }
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    // Cricket: Get Manual Session MatchOdd Data
    public function actionManualSessionMatchOdd()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eID = \Yii::$app->request->get( 'id' );
            
            $event = EventsPlayList::find()->select(['event_name'])
            ->where(['event_id'=>$eID])->asArray()->one();
            $eventName = '';
            if( $event != null ){
                $eventName = $event['event_name'];
            }
            
            $manualSessionMatchOdd = ManualSessionMatchOdd::find()
            ->select(['id','event_id','market_id','suspended','ball_running'])
            ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $eID ] )
            ->asArray()->one();
            
            //echo '<pre>';print_r($models);die;
            $items = $runners = [];
            if($manualSessionMatchOdd != null){
                $suspended = $ballRunning = 'N';
                if( isset( $manualSessionMatchOdd['suspended'] ) && $manualSessionMatchOdd['suspended'] != null ){
                    $suspended = $manualSessionMatchOdd['suspended'];
                }
                if( isset( $manualSessionMatchOdd['ball_running'] ) && $manualSessionMatchOdd['ball_running'] != null ){
                    $ballRunning = $manualSessionMatchOdd['ball_running'];
                }
                
                $marketId = $manualSessionMatchOdd['market_id'];
                $eventId = $manualSessionMatchOdd['event_id'];
                
                $matchOddData = ManualSessionMatchOddData::find()
                ->select(['id','sec_id','runner','lay','back'])
                ->andWhere( [ 'market_id' => $marketId ] )
                ->asArray()->all();
                
                foreach( $matchOddData as $data ){
                    
                    //$profitLoss = $this->getProfitLossMatchOdds($data['event_id'], $data['market_id'] , 'match_odd2');
                    $runners[] = [
                        'id' => $data['id'],
                        'market_id' => $marketId,
                        'event_id' => $eventId,
                        'sec_id' => $data['sec_id'],
                        'runner' => $data['runner'],
                        'profitloss' => '',
                        'lay' => [
                            'price' => $data['lay'],
                            'size' => '',
                        ],
                        'back' => [
                            'price' => $data['back'],
                            'size' => '',
                        ]
                    ];
                }
                
                $items[] = [
                    'title' => 'Bookmarket Market 0% Commission ',
                    'suspended' => $suspended,
                    'ballRunning' => $ballRunning,
                    'runners' => $runners,
                ];
            }
            
            $response =  [ "status" => 1 , "data" => [ "items" => $items ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    // Cricket: Get Manual Session Lottery
    public function actionManualSessionLottery()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eID = \Yii::$app->request->get( 'id' );
            $query = ManualSessionLottery::find()->select(['id','market_id','event_id','title','rate'])
            ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $eID ] );
            
            $countQuery = clone $query; $count =  $countQuery->count();
            
            $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
            
            //echo '<pre>';print_r($models);die;
            $items = $numbers = [];
            if($models != null){
                foreach($models as $data){
                    
                    /*$lotteryNumbers = ManualSessionLotteryNumbers::find()->select(['id','number','rate'])
                    ->where( [ 'manual_session_lottery_id' => $data['id'] ] )->asArray()->all();
                    
                    $numbers = [];
                    if( $lotteryNumbers != null ){
                        
                        foreach($lotteryNumbers as $lottery){
                            
                            $profitLoss = $this->getLotteryProfitLossOnBet($data['event_id'], $data['id'] , $lottery['id'] );
                            
                            $numbers[] = [
                                'id' => $lottery['id'],
                                'number' => $lottery['number'],
                                'rate' => $lottery['rate'],
                                'profitloss' => $profitLoss
                                
                            ];
                        }
                    }*/
                    
                    //$numbers = [];
                    $profitLoss = $this->getLotteryProfitLossOnBet($data['event_id'], $data['id'] , $n );
                    
                    for($n=0;$n<10;$n++){
                        $numbers[] = [
                            'id' => $n,
                            'number' => $n,
                            'rate' => $data['rate'],
                            'profitloss' => $profitLoss
                            
                        ];
                    }
                    
                    $items[] = [
                        'id' => $data['id'],
                        'event_id' => $data['event_id'],
                        'market_id' => $data['market_id'],
                        'title' => $data['title'],
                        'numbers' => $numbers,
                        'sportId' => 4,
                        'slug' => 'cricket',
                    ];
                    
                }
            }
            
            $response =  [ "status" => 1 , "data" => [ "items" => $items , "count" => $count ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    // Cricket: Commentary
    public function actionCommentary(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            
            $id = \Yii::$app->request->get( 'id' );
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>4 , 'event_id'=>$id]);
            
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
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>4 , 'event_id'=>0]);
            
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
    
    // Cricket: Get Master Name Data
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
    
    // Cricket: Get Master Id Data
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
    
    // Cricket: Place Bet
    public function actionPlacebet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_dataArr = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_dataArr);die;
            foreach( $r_dataArr as $r_data ){
            
                if( $r_data['session_type'] == 'match_odd' 
                    || $r_data['session_type'] == 'match_odd2' ){
                    
                    $data['PlaceBet'] = $r_data;
                    $model = new PlaceBet();
                    if ($model->load($data)) {
                        //echo '<pre>';print_r($data);die;
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
                        
                        $getUserBalance = $this->getUserBalanceUpdate($r_data,$uid);
                        //echo '<pre>';print_r($getUserBalance);die;
                        
                        if( $getUserBalance['available'] < $model->loss || $getUserBalance['available'] == 0  ){
                            $response[ "error" ] = [
                                "message" => "Insufficient funds!"
                            ];
                            return $response;
                        }
                        
                        
                        //die('un match');
                        //echo '<pre>';print_r($model);die;
                        if( $model->event_id != null ){
                            $play = EventsPlayList::findOne(['sport_id' => 4,'event_id' => $model->event_id ]);
                            if( $play != null && $play->game_over == 'YES'){
                                $response[ "error" ] = [
                                    "message" => "This event is already closed!" ,
                                    "data" => $model->errors
                                ];
                                return $response;
                            }
                            if( $play != null &&  ( $play->suspended == 'Y' || $play->ball_running == 'Y' ) ){
                                $response[ "error" ] = [
                                    "message" => "Bet cancelled can not place!" ,
                                ];
                                return $response;
                            }
                            
                        }
                        
                        if( $this->defaultMinStack() != 0 && $this->defaultMinStack() > $model->size ){
                            $minStack = $this->defaultMinStack();
                            $response[ "error" ] = [
                                "message" => "Minimum stack value is ".$minStack ,
                                "data" => $model->errors
                            ];
                            return $response;
                        }
                        
                        if( $this->defaultMaxStack() != 0 && $this->defaultMaxStack() < $model->size ){
                            $maxStack = $this->defaultMaxStack();
                            $response[ "error" ] = [
                                "message" => "Maximum stack value is ".$maxStack ,
                                "data" => $model->errors
                            ];
                            return $response;
                        }
                        
                        if( $this->defaultMaxProfit() != 0 ){
                            
                            $maxProfit = $this->defaultMaxProfit();
                            
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
                        
                        /*$currentBalance = $this->currentBalance($uid,$eventId,$marketId,$secId,$sessionType,$type);
                        
                        if( $currentBalance < $model->loss || $currentBalance == 0  ){
                            $response[ "error" ] = [
                                "message" => "Insufficient funds!"
                            ];
                            return $response;
                        }*/
                        
                        $model->sport_id = 4;
                        $model->bet_status = 'Pending';
                        $model->user_id = $uid;
                        $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                        $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                        
                        /*$play = EventsPlayList::find()->select(['event_name'])
                        ->where(['sport_id' => 4,'event_id' => $model->event_id , 'market_id' => $model->market_id , 'status' => 1 ])->asArray()->one();
                        $eventName = 'Undefined Event';
                        if( $play != null ){
                            $eventName = $play['event_name'];
                        }*/
                        
                        $model->description = 'Cricket / '.$model->market_name.' / '.$r_data['session_type'].' / '.$model->runner;
                        
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
                
                if( $r_data['session_type'] == 'fancy2' || $r_data['session_type'] == 'fancy' || $r_data['session_type'] == 'lottery' ){
                    
                    $model = new PlaceBet();
                    
                    $data['PlaceBet'] = $r_data;
                    //$model = new PlaceBet();
                    
                    $model->load($data);
                    
                    $model->match_unmatch = 1;
                    //$model->description = $model->runner;
                    $model->description = 'Cricket / '.$model->market_name.' / '.$r_data['session_type'].' / '.$model->runner;
                    
                    if( $r_data['market_name'] == null ){
                        $model->market_name = $model->runner;
                    }
                    
                    if( $model->event_id != null ){
                        
                        if($r_data['session_type'] == 'fancy'){
                            $manualSession = ManualSession::findOne([ 'market_id' => $model->market_id , 'event_id' => $model->event_id , 'game_over' => 'YES' ]);
                            if( $manualSession != null ){
                                $response[ "error" ] = [
                                    "message" => "This session is already closed!"
                                ];
                                return $response;
                            }
                        }
                        
                        if($r_data['session_type'] == 'fancy2'){
                            $marketType = MarketType::findOne([ 'market_id' => $model->market_id , 'event_id' => $model->event_id , 'game_over' => 'YES' ]);
                            if( $marketType != null ){
                                $response[ "error" ] = [
                                    "message" => "This session is already closed!"
                                ];
                                return $response;
                            }
                        }
                        
                        if($r_data['session_type'] == 'lottery'){
                            $marketType = ManualSessionLottery::findOne([ 'market_id' => $model->market_id , 'event_id' => $model->event_id , 'game_over' => 'YES' ]);
                            if( $marketType != null ){
                                $response[ "error" ] = [
                                    "message" => "This session is already closed!"
                                ];
                                return $response;
                            }
                        }
                        
                        $play = EventsPlayList::findOne(['sport_id' => 4,'event_id' => $model->event_id , 'game_over' => 'YES' ]);
                        if( $play != null ){
                            $response[ "error" ] = [
                                "message" => "This event is already closed!"
                            ];
                            return $response;
                        }
                        
                    }
                    
                    if( $this->defaultMinStack() != 0 && $this->defaultMinStack() > $model->size ){
                        $minStack = $this->defaultMinStack();
                        $response[ "error" ] = [
                            "message" => "Minimum stack value is ".$minStack
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxStack() != 0 && $this->defaultMaxStack() < $model->size ){
                        $maxStack = $this->defaultMaxStack();
                        $response[ "error" ] = [
                            "message" => "Maximum stack value is ".$maxStack
                        ];
                        return $response;
                    }
                    
                    if( $this->defaultMaxProfit() != 0 ){
                        
                        $maxProfit = $this->defaultMaxProfit();
                        
                        $profit = $model->size;
                        
                        if( $maxProfit < $profit){
                            $response[ "error" ] = [
                                "message" => "Maximum profit value is ".$maxProfit
                            ];
                            return $response;
                        }
                    }
                    
                    $uid = \Yii::$app->user->id;
                    
                    if( $r_data['bet_type'] == 'yes' && $r_data['rate'] != null ){
                        $model->win = round(( $model->size*$r_data['rate'] )/100);
                        $model->loss = $model->size;
                    }elseif( $r_data['bet_type'] == 'no' && $r_data['rate'] != null ){
                        $model->win = $model->size;
                        $model->loss = round(( $model->size*$r_data['rate'] )/100);
                    }else{
                        
                        if($r_data['session_type'] == 'lottery'){
                            $model->win = round(( $model->size*( $r_data['rate']-1 ) )/100);
                            $model->loss = $model->size;
                        }else{
                            $model->win = $model->size;
                            $model->loss = $model->size;
                        }
                    }
                    
                    $marketId = $model->market_id;
                    $secId = $model->sec_id;
                    $eventId = $model->event_id;
                    $sessionType = $model->session_type;
                    $type = $model->bet_type;
                    
                    if( $this->currentBalance($uid,$eventId,$marketId,$secId,$sessionType,$type) < $model->loss  ){
                        $response[ "error" ] = [
                            "message" => "Insufficient funds!"
                        ];
                        return $response;
                    }
                    
                    $model->sport_id = 4;
                    $model->bet_status = 'Pending';
                    $model->user_id = $uid;
                    $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                    $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                    
                    if( $r_data['session_type'] == 'fancy' ){
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
                        $price = $model->price;
                        $rate = $model->rate;
                        $msg = "Bet ".$type." RUN,<br>Placed ".$size." @ ".$price." Odds <br> Matched ".$size." @ ".$rate." Odds";
                        
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => $msg
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
            }
            
        }
        
        return $response;
    }
    
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
        
        //Get Profit Loss MatchOdds
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
            
            if( ( $data['bet_type'] == 'lay' && $profitLossNew[$data['sec_id']] > 0 ) 
                || ( $data['bet_type'] == 'back' && $profitLossNew[$data['sec_id']] < 0 ) ){
                
                $available = $plus+((-1)*$expose)+$available;
                
                $lossUnMatchOdd = $this->getLossUnMatchOdds($marketId,'match_odd');
                
                if( $lossUnMatchOdd > 0 ){
                    $available = $available-$lossUnMatchOdd;
                }
                
            }
            
            /*if( ( $data['bet_type'] == 'lay' && ( $profitLossNew[$data['sec_id']] > 0 && $profitLossNew[$data['sec_id']] == $plus ) ) || ( $data['bet_type'] == 'back' && ( $profitLossNew[$data['sec_id']] < 0 && $profitLossNew[$data['sec_id']] == $expose ) ) ){
                $available = $plus+((-1)*$expose)+$available;
                
                $lossUnMatchOdd = $this->getLossUnMatchOdds($marketId,'match_odd');
                
                if( $lossUnMatchOdd > 0 ){
                    $available = $available-$lossUnMatchOdd;
                }
                
            }*/
            
        }
        
        //Get Profit Loss MatchOdds 2
        $market = ManualSessionMatchOdd::findOne(['market_id'=>$marketId]);
        if( $market != null ){
            $eventId = $market->event_id;
            $runnersData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);
            if( $runnersData != null ){
                
                foreach ( $runnersData as $runners ){
                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$runners->sec_id,'match_odd2');
                    //echo $profitLoss;die;
                    if( $profitLoss < 0 ){
                        $balExpose[] = $profitLoss;
                    }else{
                        $balPlus[] = $profitLoss;
                    }
                    
                    $profitLossNew[$runners->sec_id] = $profitLoss;
                    
                }
            }
            //echo '<pre>';print_r($profitLossNew);die;
            
            if( $balExpose != null ){
                $expose = min($balExpose);
            }
            if( $balPlus != null ){
                $plus = max($balPlus);
            }
            
            if( ( $data['bet_type'] == 'lay' && $profitLossNew[$data['sec_id']] > 0 )
                || ( $data['bet_type'] == 'back' && $profitLossNew[$data['sec_id']] < 0 ) ){
                    
                    $available = $plus+((-1)*$expose)+$available;
                    
                    $lossUnMatchOdd = $this->getLossUnMatchOdds($marketId,'match_odd2');
                    
                    if( $lossUnMatchOdd > 0 ){
                        $available = $available-$lossUnMatchOdd;
                    }
                    
            }
            
        }
        
        return  $data = ['available' => $available,'expose' => $expose,'plus' => $plus];
    }
    
    // Tennis: getLossUnMatchOdds
    public function getLossUnMatchOdds($marketId,$sessionType)
    {
        $userId = \Yii::$app->user->id;
        $total = 0;
        
        $where = [ 'market_id'=>$marketId,'match_unmatch' => 0,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending',  'session_type' => $sessionType ];
        $lossUnMatch = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
        
        if( $lossUnMatch == null || !isset($lossUnMatch[0]['val']) || $lossUnMatch[0]['val'] == '' ){
            $total = 0;
        }else{ $total = $lossUnMatch[0]['val']; }
        
        return $total;
        
    }
    
    // Function to get Profit Loss ParentUser
    public function addProfitLossParentUser($data)
    {
        
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
        /*$max_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_stack;
        }*/
        
        $max_stack = 0;
        $setting = Event::find()->select(['max_stack'])
        ->where([ 'event_type_id' => '4' , 'status' => 1 ])->one();
        if( $setting != null ){
            return $setting->max_stack;
        }else{
            return $max_stack;
        }
        
    }
    
    // Function to check min stack val
    public function defaultMinStack()
    {
        /*$min_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MIN_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $min_stack;
        }*/
        
        $min_stack = 0;
        $setting = Event::find()->select(['min_stack'])
        ->where([ 'event_type_id' => '4' , 'status' => 1 ])->one();
        if( $setting != null ){
            return $setting->min_stack;
        }else{
            return $min_stack;
        }
    }
    
    // Function to check max profit limit val
    public function defaultMaxProfit()
    {
        /*$max_profit = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_PROFIT_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_profit;
        }*/
        
        $max_profit = 0;
        $setting = Event::find()->select(['max_profit'])
        ->where([ 'event_type_id' => '4' , 'status' => 1 ])->one();
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
            
            if( $sessionType == 'match_odd' || $sessionType == 'match_odd2' ){
                
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
