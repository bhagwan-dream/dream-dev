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
use common\models\ManualSession;
use common\models\EventsPlayList;
use common\models\BallToBallSession;
use common\models\GlobalCommentary;
use common\models\FavoriteMarket;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionMatchOddData;

class AppEventController extends \common\controllers\aController  // \yii\rest\Controller
{
    protected $apiUserToken = '15727-8puafDrdScO1Rn';//'13044-CgPWGpYSAOn7aV';
    protected $apiUserId = '5bf52bb732f91';//'5bcb17c84f03a';
    
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
        
        $query = Event::find()
            ->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'event_slug' , 'img' , 'icon' ,  'created_at' , 'updated_at' , 'status' ] )
            //->from( Events::tableName() . ' e' )
            ->andWhere( [ 'status' => 1 ] );
        
        $countQuery = clone $query; $count =  $countQuery->count();

        $models = $query->orderBy( [ "event_slug" => SORT_ASC ] )->asArray()->all();
        
        $i = 0;
        foreach ( $models as $img ){
            $imgpath = Url::base(true).'/uploads/events/default.jpg';
            $iconpath = Url::base(true).'/uploads/events/icon/default.jpg';
            if( $img['img'] != '' ){
                $imgpath = Url::base(true).'/uploads/events/'.$img['img'];
            }
            if( $img['icon'] != '' ){
                $iconpath = Url::base(true).'/uploads/events/icon/'.$img['icon'];
            }
            $models[$i]['img'] = $imgpath;
            $models[$i]['icon'] = $iconpath;
            $i++;
        }
        
        return [ "status" => 1 , "data" => [ "items" => $models ] ];
        
    }
    
    /*
     * Event Setting
     */
    
    public function actionEventSetting()
    {
        $response = [ "status" => 1 , "data" => [] ];
        
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $event = Event::find()
            ->select(['event_type_id','event_type_name','event_slug','min_stack','max_stack','max_profit','bet_delay'])
            ->where(['status'=>1 , 'event_slug' => $r_data['slug'] ])->asArray()->all();
            if( $event != null ){
                $response = [ "status" => 1 , "data" => $event ];
            }
            
        }
        return $response;
    }
    
    /*
     * My Profit Loss
     */
    
    public function actionMyProfitLoss()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $pagination = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( null !== \Yii::$app->user->id ){
            $uid = \Yii::$app->user->id;
        }else{
            return $response;
        }
        
        $betList = PlaceBet::find()->select(['event_id'])
        //->select(['id','sport_id','event_id','runner','bet_type','price','size','win','loss','ccr','bet_status'])
        ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>['Win','Loss'] ])
        ->groupBy(['event_id'])->asArray()->all();
        
        //echo '<pre>';print_r($betList);die;
        
        if( $betList != null ){
            
            $eventList = EventsPlayList::find()
             ->select( [ 'id' , 'sport_id' , 'event_id' , 'event_name','created_at','updated_at'] )
             ->where( [ 'status' => 1 ] )->andWhere( ['IN','event_id',$betList] )
             ->orderBy( [ 'id' => SORT_DESC ] )->asArray()->all();
             
             $eventMarketList = [];
             $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','6423'=>'Football'];
             
             if( $eventList != null ){
                 foreach ( $eventList as $event ){
                     
                     $betListArr = PlaceBet::find()
                     ->select(['id','sport_id','event_id','market_name','runner','bet_type','price','size','win','loss','ccr','bet_status','created_at'])
                     ->where(['user_id'=>$uid , 'session_type'=>'match_odd','bet_status'=>['Win','Loss'] ])
                     ->asArray()->all();
                     
                     if( $betListArr != null ){
                         $betArr = $totalArr = $commission = [];
                         $marketName = 'Match Odd';
                         foreach ( $betListArr as $bet ){
                             
                             if( $bet['bet_status'] == 'Loss' ){
                                 $pl = (-1)*$bet['loss'];   
                             }else{
                                 $pl = $bet['win'];
                             }
                             
                             if( $bet['market_name'] != null || $bet['market_name'] != '' ){
                                 $marketName = $bet['market_name'];
                             }
                             
                             $betArr[] = [
                                 'bet_id' => $bet['id'],
                                 //'sport' => $sportsArr[$event['sport_id']],
                                 //'event_name' => $event['event_name'],
                                 'market_name' => $marketName,
                                 'runner_name' => $bet['runner'],
                                 'odds' => $bet['price'],
                                 'stake' => $bet['size'],
                                 'side' => $bet['bet_type'],
                                 'win_loss' => $bet['bet_status'],
                                 'profit_loss' => $pl,
                                 'placed_date' => $bet['created_at']
                             ];
                             
                             if( $bet['bet_status'] == 'Loss' ){
                                 $totalArr[] = (-1)*$bet['loss'];
                             }else{
                                 $totalArr[] = $bet['win'];
                             }
                             
                             if( $bet['bet_status'] != 'Loss' ){
                                 $commission[] = $bet['ccr'];
                             }
                             
                         }
                         $totalVal = $commissionVal = '0.0';
                         if( count($totalArr) > 0 ){
                             $totalVal = array_sum($totalArr);
                         }
                         
                         if( count($commission) > 0 ){
                             $commissionVal = array_sum($commission);
                         }
                         
                     }
                     
                     $total = $totalVal+$commissionVal;
                     
                     // Market Match Odd
                     $eventMarketList[] = [
                         //'sport' => $sportsArr[$event['sport_id']],
                         'event_name' => $sportsArr[$event['sport_id']].' - '.$event['event_name'],
                         //'market_name' => 'Match Odd',
                         //'winner' => $event['win_result'],
                         //'start_time' => $event['created_at'],
                         //'settled_time' => $event['updated_at'],
                         'bet_list' => $betArr,
                         //'back_total' => $backTotalVal,
                         //'lay_total' => $layTotalVal,
                         'commission' => $commissionVal,
                         'total' => $total,
                     ];
                 }
             }
             //echo '<pre>';print_r($eventMarketList);die;
             $response = [ "status" => 1 , "data" => [ "items" => $eventMarketList , "count" => count($eventMarketList) ] ];
            
        }else{
            $response = [ "status" => 1 , "data" => [] ];
        }
        
        return $response;
        
    }
    
    //Event - My Market
    public function actionMyMarketOLD2()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $dataArr = $marketArr = $runnersArr = [];
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO', 'status'=>1])
                ->andWhere(['IN','event_id',$betList])->asArray()->all();
                
                //$playTypeArr = ['IN_PLAY'=>'In Play','UPCOMING'=>'Upcoming'];
                
                $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','1'=>'Football'];
                $slug = ['4'=>'cricket','2'=>'tennis','7'=>'horse-racing','1'=>'football'];
                
                foreach ( $eventList as $event ){
                    $sportId = $event['sport_id'];
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
                        $i = 0;$runnersArr = [];
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
                                }
                            }
                            
                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                foreach ( $runners->ex->availableToLay as $layArr ){
                                    $lay[] = [
                                        'price' => number_format($layArr->price,2),
                                        'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                    ];
                                }
                            }
                            
                            $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd');
                            
                            $runnersArr[] = [
                                'selectionId' => $selectionId,
                                'slug' => $slug[$sportId],
                                'runnerName' => $runnerName,
                                'profit_loss' => $profitLoss,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'exchange' => [
                                    'back' => $back,
                                    'lay' => $lay
                                ]
                            ];
                            $i++;
                        }
                    }
                    
                    $marketArr = [
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
                    
                    $eventName = $event['event_name'];
                    
                    if( isset( $sportsArr[$sportId] ) ){
                        $eventName = $sportsArr[$sportId].' - '.$eventName;
                    }
                    
                    /*if( isset( $playTypeArr[$event['play_type']] ) ){
                        $eventName = $playTypeArr[$event['play_type']].' - '.$eventName;
                    }*/
                    
                    $dataArr[] = [
                        'event_id' => $event['event_id'],
                        'event_name' => $eventName,
                        'event_time' => $event['event_time'],
                        'market' => $marketArr
                    ];
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
            }
            
        }
        
        return $response;
        
    }
    
    //Event - My Market
    public function actionMyFavoriteList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            //echo $_GET['id'];die;
            if( isset( $_GET['id'] ) && $_GET['id'] != null ){
                $where = ['user_id'=>$uid,'event_id' => $_GET['id'] ];
            }else{
                $where = ['user_id'=>$uid , 'market_type'=>'match_odd' ];
            }
            
            $favoriteList = FavoriteMarket::find()->select(['market_id'])
            ->where($where)->asArray()->all();
            
            if( $favoriteList != null ){
                $items = [];
                foreach ( $favoriteList as $list ){
                    $items[] = $list['market_id'];
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $items ] ];
            }else{
                $response = [ "status" => 1 , "data" => [ "items" => [] ] ];
            }
            
        }
        
        return $response;
        
    }
    //Event - My Favorite
    public function actionMyMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betList = PlaceBet::find()->select(['event_id'])
            ->where(['user_id'=>$uid , 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
            ->groupBy(['event_id'])->asArray()->all();
            
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $dataArr = $marketArr = $runnersArr = [];
                $eventList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                ->where(['game_over'=>'NO', 'status'=>1])
                ->andWhere(['IN','event_id',$betList])->asArray()->all();
                
                //$playTypeArr = ['IN_PLAY'=>'In Play','UPCOMING'=>'Upcoming'];
                
                $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','1'=>'Football'];
                $slug = ['4'=>'cricket','2'=>'tennis','7'=>'horse-racing','1'=>'football'];
                
                foreach ( $eventList as $event ){
                    $sportId = $event['sport_id'];
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
                        
                        foreach( $runnerData as $runner ){
                            $back = $lay = ['price' => ' - ', 'size' => ' - '];
                            $runnerName = $runner['runner'];
                            $selectionId = $runner['selection_id'];
                            
                            if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                $i = 0;$runnersArr = [];
                                foreach ( $responseData->runners as $runners ){
                                    
                                    $back = $lay = [];
                                    $selectionId = $runners->selectionId;
                                    
                                    if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                        foreach ( $runners->ex->availableToBack as $backArr ){
                                            $back[] = [
                                                'price' => number_format($backArr->price , 2),
                                                'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                            ];
                                        }
                                    }
                                    
                                    if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                        foreach ( $runners->ex->availableToLay as $layArr ){
                                            $lay[] = [
                                                'price' => number_format($layArr->price,2),
                                                'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                            ];
                                        }
                                    }
                                    
                                    $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd');
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'slug' => $slug[$sportId],
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $profitLoss,
                                        'marketId' => $marketId,
                                        'eventId' => $eventId,
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    $i++;
                                }
                            }
                            
                        }
                        
                    }
                    
                    $marketArr = [
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
                    
                    $eventName = $event['event_name'];
                    
                    if( isset( $sportsArr[$sportId] ) ){
                        $eventName = $sportsArr[$sportId].' - '.$eventName;
                    }
                    
                    $dataArr[] = [
                        'event_id' => $event['event_id'],
                        'event_name' => $eventName,
                        'event_time' => $event['event_time'],
                        'market' => $marketArr
                    ];
                    
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
            }
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite
    public function actionMyFavorite()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $favoriteList = FavoriteMarket::find()->select(['market_id','market_type'])
            ->where(['user_id'=>$uid ])->asArray()->all();
            
            //echo '<pre>';print_r($betList);die;
            if( $favoriteList != null ){
                
                $sportsArr = ['4'=>'Cricket','2'=>'Tennis','7'=>'Horse Racing','1'=>'Football'];
                $slug = ['4'=>'cricket','2'=>'tennis','7'=>'horse-racing','1'=>'football'];
                
                $favoriteMatchOddData = $dataArr = $marketArr = $runnersArr = [];
                foreach ( $favoriteList as $favorite ){
                    
                    if( $favorite['market_type'] == 'match_odd' ){
                        $favoriteMatchOddData = $favorite['market_id'];
                    }
                    
                }
                
                if( $favoriteMatchOddData != null ){
                    
                    $matchOddList = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['game_over'=>'NO', 'status'=>1])
                    ->andWhere(['IN','market_id',$favoriteMatchOddData])->asArray()->all();
                    
                }
                
                if( $matchOddList != null ){
                    
                    foreach ( $matchOddList as $event ){
                        $sportId = $event['sport_id'];
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
                            
                            foreach( $runnerData as $runner ){
                                $back = $lay = [];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    $i = 0;$runnersArr = [];
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        $back = $lay = [];
                                        $selectionId = $runners->selectionId;
                                        if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                            foreach ( $runners->ex->availableToBack as $backArr ){
                                                $back[] = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                            }
                                        }
                                        
                                        if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                            foreach ( $runners->ex->availableToLay as $layArr ){
                                                $lay[] = [
                                                    'price' => number_format($layArr->price,2),
                                                    'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                                                ];
                                            }
                                        }
                                        
                                        $profitLoss = $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd');
                                        
                                        $runnersArr[] = [
                                            'selectionId' => $selectionId,
                                            'slug' => $slug[$sportId],
                                            'runnerName' => $runnerName,
                                            'profit_loss' => $profitLoss,
                                            'marketId' => $marketId,
                                            'eventId' => $eventId,
                                            'exchange' => [
                                                'back' => $back,
                                                'lay' => $lay
                                            ]
                                        ];
                                        $i++;
                                    }
                                }
                                
                                
                            }
                            
                        }
                        
                        $marketArr = [
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
                        
                        $eventName = $event['event_name'];
                        
                        if( isset( $sportsArr[$sportId] ) ){
                            $eventName = $sportsArr[$sportId].' - '.$eventName;
                        }
                        
                        $dataArr[] = [
                            'event_id' => $event['event_id'],
                            'event_name' => $eventName,
                            'event_time' => $event['event_time'],
                            'market' => $marketArr
                        ];
                        
                    }
                    
                }
                
                $response = [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
            }
            
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
        
        if( $total != 0 ){
            return $total;
        }else{
            return '';
        }
        
    }
    
    //Event - My Market Cricket
    public function actionMyMarketCricket()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 4,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = $marketArr['fancy2'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    $backArr = $runners->ex->availableToBack[0];
                                                    $back = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    $layArr = $runners->ex->availableToLay[0];
                                                    $lay = [
                                                        'price' => number_format($layArr->price , 2),
                                                        'size' => number_format($layArr->size , 2),
                                                    ];
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'slug' => 'cricket',
                                        'marketId' => $marketId,
                                        'eventId' => $eventId,
                                        'suspended' => $suspended,
                                        'ballRunning' => $ballRunning,
                                        'selectionId' => $selectionId,
                                        'isFavorite' => $isFavorite,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 4,
                                    'slug' => 'cricket',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'isFavorite' => $isFavorite,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        // Fancy 2
                        $fancy2Data = (new \yii\db\Query())
                        ->select(['market_id'])->from('place_bet')
                        ->where(['event_id' => $eventId,'sport_id' => 4,'user_id'=>$uid ,'session_type' => 'fancy2', 'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending' ])
                        ->groupBy(['market_id'])->all();
                        
                        if( $fancy2Data != null ){
                            
                            foreach( $fancy2Data as $fancy2 ){
                                
                                /*$DisplayMsg = $dataVal = [];
                                $dataVal[$fancy2['market_id']] = [
                                    'no' => '-',
                                    'no_rate' => '',
                                    'yes' => '-',
                                    'yes_rate' => '',
                                ];*/
                                
                                //$DisplayMsg[$fancy2['market_id']] == 'Y';
                                
                                //CODE for live call api
                                $url = $this->apiUrlFancy.'?eventId='.$eventId;
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
                                                
                                                $marketId = $data->market_id;
                                                $titleFancy = $data->headname;
                                                $isFavorite = $this->isFavorite($eventId,$marketId,'fancy2');
                                                
                                                if( $fancy2['market_id'] == $marketId ){
                                                    
                                                    $isBook = $this->isBookOn($marketId,'fancy2');
                                                    /*$dataVal[$fancy2['market_id']] = [
                                                        'no' => $data->SessInptNo,
                                                        'no_rate' => $data->NoValume,
                                                        'yes' => $data->SessInptYes,
                                                        'yes_rate' => $data->YesValume,
                                                    ];*/
                                                    $dataVal[0] = [
                                                        'no' => $data->SessInptNo,
                                                        'no_rate' => $data->NoValume,
                                                        'yes' => $data->SessInptYes,
                                                        'yes_rate' => $data->YesValume,
                                                    ];
                                                    
                                                    //$DisplayMsg[$fancy2['market_id']] = $data->DisplayMsg;
                                                    
                                                    $marketArr['fancy2'][] = [
                                                        'market_id' => $marketId,
                                                        'event_id' => $eventId,
                                                        'title' => $titleFancy,
                                                        'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                                        'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                                        'data' => $dataVal,
                                                        'sportId' => 4,
                                                        'slug' => 'cricket',
                                                        'sessionType' => 'fancy2',
                                                        'is_book' => $isBook,
                                                        'isFavorite' => $isFavorite,
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                /*$marketData = MarketType::findOne(['market_id' => $fancy2['market_id'],'event_id'=>$eventId]);
                                if( $marketData != null ){
                                    
                                    $marketId = $marketData->market_id;
                                    $titleFancy = $marketData->market_name;
                                    $isFavorite = $this->isFavorite($eventId,$marketId,'fancy2');
                                    $isBook = $this->isBookOn($marketId,'fancy2');
                                    
                                    $marketArr['fancy2'][] = [
                                        'market_id' => $marketId,
                                        'event_id' => $eventId,
                                        'title' => $titleFancy,
                                        'suspended' => 'Y',//$DisplayMsg[$marketId] == 'Suspended' ? 'Y' : 'N',
                                        'ballRunning' => 'Y',//$DisplayMsg[$marketId] == 'Ball Running' ? 'Y' : 'N',
                                        'data' => $dataVal[$marketId],
                                        'sportId' => 4,
                                        'slug' => 'cricket',
                                        'sessionType' => 'fancy2',
                                        'is_book' => $isBook,
                                        'isFavorite' => $isFavorite,
                                    ];
                                }*/
                                
                            }
                            
                        }
                        
                        $eventArr[] = [
                            'title' => 'Cricket - '.$title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
    }
    
    //Event - My Favorite Cricket
    public function actionMyFavoriteCricket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = $marketArr['fancy2'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isInPlay = $eventData['play_type'] == 'IN_PLAY' ? 1 : 0 ;
                        /*$matchOddData = (new \yii\db\Query())
                         ->select(['id'])->from('place_bet')
                         ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                         ->one();*/
                        
                        //if( $matchOddData != null ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                       if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                $backArr = $runners->ex->availableToBack[0];
                                                $back = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),
                                                ];
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                $layArr = $runners->ex->availableToLay[0];
                                                $lay = [
                                                    'price' => number_format($layArr->price , 2),
                                                    'size' => number_format($layArr->size , 2),
                                                ];
                                            }
                                        }
                                        
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'slug' => 'cricket',
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'isInPlay' => $isInPlay,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 4,
                                'slug' => 'cricket',
                                'sessionType' => 'match_odd',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'isInPlay' => $isInPlay,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        //}
                        
                    }
                    
                }
            }
            
            // Fancy 2
            
            $fancy2Data = (new \yii\db\Query())
            ->select(['market_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'fancy2' ])
            ->groupBy(['market_id'])->all();
            
            
            if( $fancy2Data != null ){
                
                foreach( $fancy2Data as $fancy2 ){
                    
                    //CODE for live call api
                    $url = $this->apiUrlFancy.'?eventId='.$eventId;
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
                                    
                                    $marketId = $data->market_id;
                                    $titleFancy = $data->headname;
                                    
                                    if( $fancy2['market_id'] == $marketId ){
                                        
                                        $isBook = $this->isBookOn($marketId,'fancy2');
                                        
                                        $dataVal[0] = [
                                            'no' => $data->SessInptNo,
                                            'no_rate' => $data->NoValume,
                                            'yes' => $data->SessInptYes,
                                            'yes_rate' => $data->YesValume,
                                        ];
                                        
                                        $marketArr['fancy2'][] = [
                                            'market_id' => $marketId,
                                            'event_id' => $eventId,
                                            'title' => $titleFancy,
                                            'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                            'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                            'data' => $dataVal,
                                            'sportId' => 4,
                                            'slug' => 'cricket',
                                            'sessionType' => 'fancy2',
                                            'is_book' => $isBook
                                        ];
                                    }
                                }
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            $eventArr[] = [
                'title' => 'Cricket - '.$title,
                'market' => $marketArr,
            ];
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Cricket
    public function actionMyFavoriteCricketNew()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = $marketArr['fancy2'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isInPlay = $eventData['play_type'] == 'IN_PLAY' ? 1 : 0 ;
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                $backArr = $runners->ex->availableToBack[0];
                                                $back = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),
                                                ];
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                $layArr = $runners->ex->availableToLay[0];
                                                $lay = [
                                                    'price' => number_format($layArr->price , 2),
                                                    'size' => number_format($layArr->size , 2),
                                                ];
                                            }
                                        }
                                        
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'slug' => 'cricket',
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'isInPlay' => $isInPlay,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 4,
                                'slug' => 'cricket',
                                'sessionType' => 'match_odd',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'isInPlay' => $isInPlay,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        //}
                        
                    }
                    
                }
            }
            
            // Fancy 2
            
            $fancy2Data = (new \yii\db\Query())
            ->select(['market_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'fancy2' ])
            ->groupBy(['market_id'])->all();
            
            if( $fancy2Data != null ){
                
                foreach( $fancy2Data as $fancy2 ){
                    
                    $marketType = (new \yii\db\Query())
                    ->select(['market_id','event_id','market_name'])
                    ->from('market_type')
                    ->where(['market_id'=>$fancy2['market_id'],'game_over'=>'NO','status'=>1])
                    ->one();
                    
                    if( $marketType != null ){
                        
                        $eventData = (new \yii\db\Query())
                        ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                        ->from('events_play_list')
                        ->where(['event_id'=>$marketType['event_id'],'game_over'=>'NO','sport_id' => 4,'status'=>1])
                        ->one();
                        
                        if( $eventData != null ){
                            
                            $marketId = $eventData['market_id'];
                            $eventId = $eventData['event_id'];
                            $title = $eventData['event_name'];
                            
                            //CODE for live call api
                            $url = $this->apiUrlFancy.'?eventId='.$eventId;
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
                                            
                                            $marketId = $data->market_id;
                                            $titleFancy = $data->headname;
                                            
                                            if( $fancy2['market_id'] == $marketId ){
                                                
                                                $isBook = $this->isBookOn($marketId,'fancy2');
                                                
                                                $dataVal[0] = [
                                                    'no' => $data->SessInptNo,
                                                    'no_rate' => $data->NoValume,
                                                    'yes' => $data->SessInptYes,
                                                    'yes_rate' => $data->YesValume,
                                                ];
                                                
                                                $marketArr['fancy2'][] = [
                                                    'market_id' => $marketId,
                                                    'event_id' => $eventId,
                                                    'title' => $titleFancy,
                                                    'suspended' => $data->DisplayMsg == 'Suspended' ? 'Y' : 'N',
                                                    'ballRunning' => $data->DisplayMsg == 'Ball Running' ? 'Y' : 'N',
                                                    'data' => $dataVal,
                                                    'sportId' => 4,
                                                    'slug' => 'cricket',
                                                    'sessionType' => 'fancy2',
                                                    'is_book' => $isBook
                                                ];
                                            }
                                        }
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            $eventArr[] = [
                'title' => 'Cricket - '.$title,
                'market' => $marketArr,
            ];
            
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Tennis
    public function actionMyMarketTennis()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 2,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 2,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    $backArr = $runners->ex->availableToBack[0];
                                                    $back = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    $layArr = $runners->ex->availableToLay[0];
                                                    $lay = [
                                                        'price' => number_format($layArr->price , 2),
                                                        'size' => number_format($layArr->size , 2),
                                                    ];
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'slug' => 'tennis',
                                        'marketId' => $marketId,
                                        'eventId' => $eventId,
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'suspended' => $suspended,
                                        'ballRunning' => $ballRunning,
                                        'isFavorite' => $isFavorite,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 2,
                                    'slug' => 'tennis',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'isFavorite' => $isFavorite,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        $eventArr[] = [
                            'title' => 'Tennis - '.$title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
    }
    
    //Event - My Favorite Tennis
    public function actionMyFavoriteTennis()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 2,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isInPlay = $eventData['play_type'] == 'IN_PLAY' ? 1 : 0 ;
                        /*$matchOddData = (new \yii\db\Query())
                         ->select(['id'])->from('place_bet')
                         ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                         ->one();*/
                        
                        //if( $matchOddData != null ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                $backArr = $runners->ex->availableToBack[0];
                                                $back = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),
                                                ];
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                $layArr = $runners->ex->availableToLay[0];
                                                $lay = [
                                                    'price' => number_format($layArr->price , 2),
                                                    'size' => number_format($layArr->size , 2),
                                                ];
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'slug' => 'tennis',
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'isInPlay' => $isInPlay,
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 2,
                                'slug' => 'tennis',
                                'sessionType' => 'match_odd',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'isInPlay' => $isInPlay,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        //}
                        
                        $eventArr[] = [
                            'title' => 'Tennis - '.$title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market Football
    public function actionMyMarketFootball()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 1,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 1,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    $backArr = $runners->ex->availableToBack[0];
                                                    $back = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    $layArr = $runners->ex->availableToLay[0];
                                                    $lay = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),
                                                    ];
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'slug' => 'football',
                                        'marketId' => $marketId,
                                        'eventId' => $eventId,
                                        'suspended' => $suspended,
                                        'ballRunning' => $ballRunning,
                                        'selectionId' => $selectionId,
                                        'isFavorite' => $isFavorite,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 1,
                                    'slug' => 'football',
                                    'sessionType' => 'match_odd',
                                    'title' => $title,
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'isFavorite' => $isFavorite,
                                    'time' => $time,
                                    'marketName'=>'Match Odds',
                                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                    'runners' => $runnersArr,
                                ];
                                
                            }
                        }
                        
                        $eventArr[] = [
                            'title' => 'Football - '.$title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Football
    public function actionMyFavoriteFootball()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = null;
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 1,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        $isInPlay = $eventData['play_type'] == 'IN_PLAY' ? 1 : 0 ;
                        /*$matchOddData = (new \yii\db\Query())
                         ->select(['id'])->from('place_bet')
                         ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                         ->one();*/
                        
                        //if( $matchOddData != null ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
                            foreach( $runnerData as $runner ){
                                $back = $lay = ['price' => ' - ', 'size' => ' - '];
                                $runnerName = $runner['runner'];
                                $selectionId = $runner['selection_id'];
                                
                                if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                    
                                    foreach ( $responseData->runners as $runners ){
                                        
                                        if( $runners->selectionId == $selectionId ){
                                            if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                $backArr = $runners->ex->availableToBack[0];
                                                $back = [
                                                    'price' => number_format($backArr->price , 2),
                                                    'size' => number_format($backArr->size , 2),
                                                ];
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                $layArr = $runners->ex->availableToLay[0];
                                                $lay = [
                                                    'price' => number_format($layArr->price , 2),
                                                    'size' => number_format($layArr->size , 2),
                                                ];
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'slug' => 'football',
                                    'marketId' => $marketId,
                                    'eventId' => $eventId,
                                    'suspended' => $suspended,
                                    'ballRunning' => $ballRunning,
                                    'isInPlay' => $isInPlay,
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 1,
                                'slug' => 'football',
                                'title' => $title,
                                'marketId' => $marketId,
                                'eventId' => $eventId,
                                'suspended' => $suspended,
                                'ballRunning' => $ballRunning,
                                'isInPlay' => $isInPlay,
                                'time' => $time,
                                'marketName'=>'Match Odds',
                                'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                                'runners' => $runnersArr,
                            ];
                            
                        }
                        //}
                        
                        $eventArr[] = [
                            'title' => 'Football - '.$title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Market HorseRacing
    public function actionMyMarketHorseRacing()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('place_bet')
            ->where(['sport_id' => 7,'user_id'=>$uid,'match_unmatch'=>1,'status'=>1,'bet_status'=>'Pending'])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = (new \yii\db\Query())
                    ->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 7,'status'=>1])
                    ->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        $matchOddData = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                        ->one();
                        
                        if( $matchOddData != null ){
                            
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId ])
                            ->all();
                            
                            //echo '<pre>';print_r($runnerData);die;
                            if( $runnerData != null ){
                                $runnersArr = [];
                                //CODE for live call api
                                $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                $responseData = curl_exec($ch);
                                curl_close($ch);
                                $responseData = json_decode($responseData);
                                
                                //echo '<pre>';print_r($responseData);die;
                                foreach( $runnerData as $runner ){
                                    $back = $lay = [];
                                    $runnerName = $runner['runner'];
                                    $selectionId = $runner['selection_id'];
                                    
                                    if( !empty( $responseData->runners ) && !empty( $responseData->runners ) ){
                                        
                                        foreach ( $responseData->runners as $runners ){
                                            
                                            if( $runners->selectionId == $selectionId ){
                                                if( isset( $runners->ex->availableToBack ) && !empty( $runners->ex->availableToBack ) ){
                                                    $backArr = $runners->ex->availableToBack[0];
                                                    $back = [
                                                        'price' => number_format($backArr->price , 2),
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                                
                                                if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                    $layArr = $runners->ex->availableToLay[0];
                                                    $lay = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),
                                                    ];
                                                }
                                            }
                                            
                                        }
                                        
                                    }
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                        'exchange' => [
                                            'back' => $back,
                                            'lay' => $lay
                                        ]
                                    ];
                                    
                                }
                                
                                $marketArr['match_odd'] = [
                                    'sportId' => 7,
                                    'slug' => 'horse-racing',
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
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    //Event - My Favorite Football
    public function actionMyFavoriteHorseRacing()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null !== \Yii::$app->user->id ){
            
            $uid = \Yii::$app->user->id;
            
            $betListEvent = (new \yii\db\Query())
            ->select(['event_id'])->from('favorite_market')
            ->where(['user_id'=>$uid,'market_type' => 'match_odd' ])
            ->groupBy(['event_id'])->all();
            
            $eventArr = $marketArr['match_odd'] = [];
            if( $betListEvent != null ){
                
                foreach ( $betListEvent as $event ){
                    
                    $eventData = EventsPlayList::find()->select(['sport_id','market_id','event_id','event_name','event_time','play_type','suspended','ball_running'])
                    ->where(['event_id'=>$event['event_id'],'game_over'=>'NO','sport_id' => 7,'status'=>1])
                    ->asArray()->one();
                    
                    if( $eventData != null ){
                        //echo '<pre>';print_r($eventData);die;
                        $marketId = $eventData['market_id'];
                        $eventId = $eventData['event_id'];
                        $title = $eventData['event_name'];
                        $time = $eventData['event_time'];
                        $suspended = $eventData['suspended'];
                        $ballRunning = $eventData['ball_running'];
                        
                        /*$matchOddData = (new \yii\db\Query())
                         ->select(['id'])->from('place_bet')
                         ->where(['market_id' => $marketId,'session_type' => 'match_odd','status'=>1 ])
                         ->one();*/
                        
                        //if( $matchOddData != null ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId ])
                        ->all();
                        
                        //echo '<pre>';print_r($runnerData);die;
                        if( $runnerData != null ){
                            $runnersArr = [];
                            //CODE for live call api
                            $url = $this->apiUrlMatchOdd.'?id='.$marketId;
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $responseData = curl_exec($ch);
                            curl_close($ch);
                            $responseData = json_decode($responseData);
                            
                            //echo '<pre>';print_r($responseData);die;
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
                                                        'size' => number_format($backArr->size , 2),
                                                    ];
                                                }
                                            }
                                            
                                            if( isset( $runners->ex->availableToLay ) && !empty( $runners->ex->availableToLay ) ){
                                                foreach ( $runners->ex->availableToLay as $layArr ){
                                                    $lay[] = [
                                                        'price' => number_format($layArr->price,2),
                                                        'size' => number_format($layArr->size,2),
                                                    ];
                                                }
                                            }
                                        }
                                        
                                    }
                                    
                                }
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'profit_loss' => $this->getProfitLossMatchOdds($marketId,$eventId,$selectionId,'match_odd'),
                                    'exchange' => [
                                        'back' => $back,
                                        'lay' => $lay
                                    ]
                                ];
                                
                            }
                            
                            $marketArr['match_odd'] = [
                                'sportId' => 7,
                                'slug' => 'horse-racing',
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
                        //}
                        
                        $eventArr[] = [
                            'title' => $title,
                            'market' => $marketArr,
                        ];
                    }
                    
                }
            }
            
            $response = [ "status" => 1 , "data" => [ "items" => $eventArr ] ];
            
        }
        
        return $response;
        
    }
    
    // Cricket: isBookOn
    public function isBookOn($marketId,$sessionType)
    {
        $uid = \Yii::$app->user->id;
        
        $findBet = (new \yii\db\Query())
        ->select(['id'])->from('place_bet')
        ->where(['user_id'=>$uid,'market_id' => $marketId,'session_type' => $sessionType,'status'=>1 ])
        ->one();
        
        if( $findBet != null ){
            return '1';
        }
        return '0';
        
    }

    public function actionInplayTodayTomorrow()
    {
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];

        $eventData = (new \yii\db\Query())
            ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
            ->from('events_play_list')
            ->where(['game_over'=>'NO','status'=>1])
            ->andWhere(['>','event_time',strtotime( date('Y-m-d').' 23:59:59 -7 day') * 1000])
            //->andWhere(['<','event_time',strtotime( date('Y-m-d').' 23:59:59 +1 day') * 1000])
            ->orderBy(['event_time' => SORT_ASC])
            ->all();

        if( $eventData != null ){

            $unblockEvents = $this->checkUnBlockList($eventData);
            $unblockSport = $this->checkUnBlockSportList();

            foreach ( $eventData as $event ){

                $eventId = $event['event_id'];
                $marketId = $event['market_id'];
                $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');

                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                $eventDate = date('Y-m-d',( $event['event_time'] / 1000 ));

                //In play List
                if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 4
                    ){

                    if( !in_array($eventId, $unblockEvents ) ){

                        $cricketInplay[] = [
                            'slug' => 'cricket',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 2
                    ){

                    if( !in_array($eventId, $unblockEvents ) ){

                        $tennisInplay[] = [
                            'slug' => 'tennis',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 1
                    ){

                    if( !in_array($eventId, $unblockEvents ) ){

                        $footballInplay[] = [
                            'slug' => 'football',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }
                }else{
                    // Do nothing
                }

                //Upcoming List Today
                if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 4
                    && $today == $eventDate ){

                    if( !in_array($eventId, $unblockEvents ) ){

                        $cricketToday[] = [
                            'slug' => 'cricket',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 2
                    && $today == $eventDate ){

                    if( !in_array($eventId, $unblockEvents ) ){

                        $tennisToday[] = [
                            'slug' => 'tennis',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 1
                    && $today == $eventDate ){
                    if( !in_array($eventId, $unblockEvents ) ){
                        $footballToday[] = [
                            'slug' => 'football',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }
                }else{
                    // Do nothing
                }

                //Upcoming List Tomorrow
                if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 4
                    && $tomorrow == $eventDate ){
                    if( !in_array($eventId, $unblockEvents ) ){
                        $cricketTomorrow[] = [
                            'slug' => 'cricket',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id']== 2
                    && $tomorrow == $eventDate ){
                    if( !in_array($eventId, $unblockEvents ) ){
                        $tennisTomorrow[] = [
                            'slug' => 'tennis',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }

                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 1
                    && $tomorrow == $eventDate ){
                    if( !in_array($eventId, $unblockEvents ) ){
                        $footballTomorrow[] = [
                            'slug' => 'football',
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ball_running' => $event['ball_running'],
                        ];
                    }
                }else{
                    // Do nothing
                }


            }

        }

        if( in_array(1, $unblockSport ) ){
            $footballInplay = $footballToday = $footballTomorrow = [];
        }
        if( in_array(2, $unblockSport ) ){
            $tennisInplay = $tennisToday = $tennisTomorrow = [];
        }
        if( in_array(4, $unblockSport ) ){
            $cricketInplay = $cricketToday = $cricketTomorrow = [];
        }

        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }


    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrowOLDD()
    {
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];
        
        $eventData = EventsPlayList::findAll(['play_type'=>['IN_PLAY','UPCOMING'],'game_over'=>'NO','status'=>1]);
        
        if( $eventData != null ){
            
            foreach ( $eventData as $event ){
                
                $eventId = $event->event_id;
                $marketId = $event->market_id;
                $isFavorite = $this->isFavorite($eventId,$marketId,'match_odd');
                
                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                $eventDate = date('Y-m-d',( $event->event_time / 1000 ));
                
                //In play List
                if( $event->play_type == 'IN_PLAY' 
                    && $event->sport_id == 4 
                    && $today == $eventDate ){
                    
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $cricketInplay[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                    
                }else if( $event->play_type == 'IN_PLAY' 
                    && $event->sport_id == 2 
                    && $today == $eventDate ){
                    
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $tennisInplay[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                    
                }else if( $event->play_type == 'IN_PLAY' 
                    && $event->sport_id == 1 
                    && $today == $eventDate ){
                    
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $footballInplay[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                //Upcoming List Today
                if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 4
                    && $today == $eventDate ){
                        
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $cricketToday[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                        
                }else if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 2 
                    && $today == $eventDate ){
                        
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                        
                            $tennisToday[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                        
                }else if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 1
                    && $today == $eventDate ){
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                            $footballToday[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                //Upcoming List Tomorrow
                if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 4
                    && $tomorrow == $eventDate ){
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                            $cricketTomorrow[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                        
                }else if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 2 
                    && $tomorrow == $eventDate ){
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                            $tennisTomorrow[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                        
                }else if( $event->play_type == 'UPCOMING'
                    && $event->sport_id == 1
                    && $today == $eventDate ){
                        if( !in_array($eventId, $this->checkUnBlockList() ) ){
                            $footballTomorrow[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event->event_name,
                                'league' => $event->event_league,
                                'time' => $event->event_time,
                                'is_favorite' => $isFavorite,
                                'suspended' => $event->suspended,
                                'ball_running' => $event->ball_running,
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                
            }
            
        }
        
        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }

    //check database function
    public function checkUnBlockList($eventData)
    {
        $eventArr = [];
        if( $eventData != null ){

            foreach ( $eventData as $event ){

                $eventArr[] = $event['event_id'];

            }
            //echo '<pre>';print_r($eventArr);die;
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
                ->where(['user_id'=>$pId,'market_type' => 'all' ])
                ->andWhere(['IN','event_id',$eventArr])
                ->all();

            if( $listArr != null ){

                foreach ( $listArr as $list ){
                    $newList[] = $list['event_id'];
                }

                return $newList;
            }else{
                return [];
            }

        }else{
            return [];
        }
    }

    //check sport database function
    public function checkUnBlockSportList()
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
            ->select(['sport_id'])->from('event_status')
            ->where(['user_id'=>$pId ])->all();

        if( $listArr != null ){

            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }

            return $newList;
        }else{
            return [];
        }

    }

    // Cricket: isFavorite
    public function isFavorite($eventId,$marketId,$sessionType)
    {
        $uid = \Yii::$app->user->id;
        
        $find = (new \yii\db\Query())
        ->select(['id'])->from('favorite_market')
        ->where(['user_id'=>$uid,'event_id' => $eventId,'market_id' => $marketId,'market_type' => $sessionType ])
        ->one();
        
        if( $find != null ){
            return '1';
        }
        return '0';
        
    }
    
    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrowUNUSED()
    {
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];
        
        //CODE for live call api Cricket
        //$url = $this->apiUrl.'?event_id=4';
        $url = $this->apiUrlCricket;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseDataCricket = curl_exec($ch);
        curl_close($ch);
        $responseDataCricket = json_decode($responseDataCricket);
        
        if( isset($responseDataCricket->result) && !empty($responseDataCricket->result) ){
            foreach ( $responseDataCricket->result as $result ){
                if( $result->inPlay == true ){
                    $cricketInplay[] = [
                        'slug' => 'cricket',
                        'event_id' => $result->groupById,
                        'market_id' => $result->id,
                        'event_name'=>$result->event->name,
                        'event_time'=>$result->start,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                    ];
                    
                }else{
                    
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start / 1000 ));
                    if( $today == $eventDate ){
                        $cricketToday[] = [
                            'slug' => 'cricket',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                    if( $tomorrow == $eventDate ){
                        $cricketTomorrow[] = [
                            'slug' => 'cricket',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                }
            }
        }
        
        //CODE for live call api Tennis
        //$url = $this->apiUrl.'?event_id=2';
        $url = $this->apiUrlTennis;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseDataTennis = curl_exec($ch);
        curl_close($ch);
        $responseDataTennis = json_decode($responseDataTennis);
        
        if( isset($responseDataTennis->result) && !empty($responseDataTennis->result) ){
            foreach ( $responseDataTennis->result as $result ){
                if( $result->inPlay == true ){
                    $tennisInplay[] = [
                        'slug' => 'tennis',
                        'market_id' => $result->id,
                        'event_id' => $result->groupById,
                        'event_name'=>$result->event->name,
                        'event_time'=>$result->start,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                    ];
                }else{
                    
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start / 1000 ));
                    if( $today == $eventDate ){
                        $tennisToday[] = [
                            'slug' => 'tennis',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                    if( $tomorrow == $eventDate ){
                        $tennisTomorrow[] = [
                            'slug' => 'tennis',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                }
            }
        }
        
        //CODE for live call api Football
        //$url = $this->apiUrl.'?event_id=1';
        $url = $this->apiUrlFootball;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseDataFootball = curl_exec($ch);
        curl_close($ch);
        $responseDataFootball = json_decode($responseDataFootball);
        
        if( isset($responseDataFootball->result) && !empty($responseDataFootball->result) ){
            foreach ( $responseDataFootball->result as $result ){
                if( $result->inPlay == true ){
                    $footballInplay[] = [
                        'slug' => 'football',
                        'market_id' => $result->id,
                        'event_id' => $result->groupById,
                        'event_name'=>$result->event->name,
                        'event_time'=>$result->start,
                        'suspended' => 'N',
                        'ballRunning' => 'N',
                    ];
                }else{
                    
                    $today = date('Y-m-d');
                    $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                    $eventDate = date('Y-m-d',( $result->start / 1000 ));
                    if( $today == $eventDate ){
                        $footballToday[] = [
                            'slug' => 'football',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                    if( $tomorrow == $eventDate ){
                        $footballTomorrow[] = [
                            'slug' => 'football',
                            'market_id' => $result->id,
                            'event_id' => $result->groupById,
                            'event_name'=>$result->event->name,
                            'event_time'=>$result->start,
                            'suspended' => 'N',
                            'ballRunning' => 'N',
                        ];
                    }
                }
            }
        }
        
        //CODE for live call api Horse Racing
        /*$url = $this->apiUrl.'?event_id=7';
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         $responseDataHorseRacing = curl_exec($ch);
         curl_close($ch);
         $responseDataHorseRacing = json_decode($responseDataHorseRacing);
         
         if( isset($responseDataHorseRacing->result) && !empty($responseDataHorseRacing->result) ){
         foreach ( $responseDataHorseRacing->result as $result ){
         if( $result->inPlay == true ){
         $horseracingInplay[] = [
         'slug' => 'horse-racing',
         'market_id' => $result->id,
         'event_id' => $result->groupById,
         'event_name'=>$result->event->name,
         'event_time'=>$result->start,
         'suspended' => 'N',
         'ballRunning' => 'N',
         ];
         }else{
         
         $today = date('Y-m-d');
         $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
         $eventDate = date('Y-m-d',( $result->start / 1000 ));
         if( $today == $eventDate ){
         $horseracingToday[] = [
         'slug' => 'horse-racing',
         'market_id' => $result->id,
         'event_id' => $result->groupById,
         'event_name'=>$result->event->name,
         'event_time'=>$result->start,
         'suspended' => 'N',
         'ballRunning' => 'N',
         ];
         }
         if( $tomorrow == $eventDate ){
         $horseracingTomorrow[] = [
         'slug' => 'horse-racing',
         'market_id' => $result->id,
         'event_id' => $result->groupById,
         'event_name'=>$result->event->name,
         'event_time'=>$result->start,
         'suspended' => 'N',
         'ballRunning' => 'N',
         ];
         }
         }
         }
         }*/
        
        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }
    
    public function actionCricketOLD()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
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
    
    public function actionCricket()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
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
    
    // Football: Get data inplay and upcoming list from API
    
    public function actionFootball()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=6423&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
                
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
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
    
    
    // Tennis: Get data inplay and upcoming list from API
    
    public function actionTennis()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=2&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
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
    
    // HorseRacing: Get data inplay and upcoming list from API
    
    public function actionHorseRacing()
    {
        // Get data inplay list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=7&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        $responseArr['upcoming']['market'] = [];
        $responseArr['inplay']['market'] = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1->results[0]->markets[0]);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
                    
                    if( $check != null ){
                        $responseArr['inplay']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
                            'marketName' => $marketName,
                            'matched' => $totalMatched,
                            'runners' => $runnersArr
                        ];
                    }
                }
            }
            
        }
        
        //echo '<pre>';print_r($responseArr['inplay']);die;
        
        // Get data upcoming list from API
        
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        //$responseArr = [];
        
        if( !empty($response->results) ){
            
            foreach ( $response->results as $items ){
                
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                
                $response1 = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response1);die;
                //$event = $response1->results[0]->event;
                
                if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
                    
                    $marketsArr = $response1->results[0]->markets[0];
                    
                    $marketName = $marketsArr->description->marketName;
                    $totalMatched = number_format($marketsArr->state->totalMatched , 5);
                    $marketId = $marketsArr->marketId;
                    $runnersArr = [];
                    
                    $time = strtotime(date('Y-m-d H:i:s'));
                    
                    if( isset($items->time) ){
                        $time = $items->time;
                    }
                    
                    $eventId = 'undefine';
                    
                    if( isset($items->id) ){
                        $eventId = $items->id;
                    }
                    
                    foreach ( $marketsArr->runners as $runners ){
                        
                        $back = $lay = [
                            'price' => '-',
                            'size' => '-'
                        ];
                        
                        if( isset( $runners->exchange->availableToBack ) ){
                            $back = [
                                'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                                'size' => '$'.number_format($runners->exchange->availableToBack[0]->size , 2),
                            ];
                        }
                        
                        if( isset( $runners->exchange->availableToLay ) ){
                            $lay = [
                                'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                                'size' => '$'.number_format($runners->exchange->availableToLay[0]->size,2),
                            ];
                        }
                        
                        $runnersArr[] = [
                            'selectionId' => $runners->selectionId,
                            'runnerName' => $runners->description->runnerName,
                            'runnerId' => $runners->description->metadata->runnerId,
                            'exchange' => [
                                'back' => $back,
                                'lay' => $lay
                            ]
                        ];
                        
                    }
                    
                    $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'UPCOMING' ]);
                    
                    if( $check != null ){
                        $responseArr['upcoming']['market'][] = [
                            'marketId' => $marketId,
                            'eventId' => $eventId,
                            'time' => $time,
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
    
    // Event: Commentary
    public function actionCommentary(){
        
        $eventCommentary = $globalCommentary = 'No data!';
        
        if( null !== \Yii::$app->request->get( 'id' ) ){
            
            $id = \Yii::$app->request->get( 'id' );
            
            $commentaryEvent = GlobalCommentary::findOne(['event_id'=>$id]);
            
            if( $commentaryEvent != null ){
                $eventCommentary = $commentaryEvent->title;
            }
            
        }
        
        $commentary = Setting::findOne(['key'=>'GLOBAL_COMMENTARY' , 'status'=>1 ]);
        
        if( $commentary != null ){
            $globalCommentary = $commentary->value;
        }
        
        $data = [ 'event_commentary'=>$eventCommentary, 'global_commentary' => $globalCommentary ];
        $response = [
            "status" => 1 ,
            "data" => $data,
        ];
        
        return $response;
    }
    
    public function actionManualSession()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $eID = \Yii::$app->request->get( 'id' );
            $query = ManualSession::find()->select(['id' , 'event_id', 'title' , 'yes' , 'no' ])
            ->andWhere( [ 'status' => 1 , 'game_over' => 'NO' , 'event_id' => $eID ] );
            
            $countQuery = clone $query; $count =  $countQuery->count();
            
            $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
            
            $response =  [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
            
        }else{
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
        return $response;
    }
    
    public function actionManualSessionBalltoball()
    {
        if( null !== \Yii::$app->request->get( 'id' ) ){
            $msId = \Yii::$app->request->get( 'id' );
            
            $manualSession = ManualSession::find()->select(['id' , 'event_id', 'title' ])
            ->where( [ 'status_ball_to_ball' => 1 ,'status' => 1 , 'game_over' => 'NO' , 'id' => $msId ] )->one();
            
            if( $manualSession != null ){
                
                $query = BallToBallSession::find()->select(['id' , 'event_id', 'manual_session_id' , 'over' , 'ball' , 'yes' , 'no' ])
                ->where( [ 'status' => 1 , 'manual_session_id' => $msId ] );
                
                $countQuery = clone $query; $count =  $countQuery->count();
                
                $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
                
                if( $models != null ){
                    $response =  [ "status" => 1 , "data" => [ "items" => $models ,"title" => $manualSession->title , "count" => $count ] ];
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
    
    /*public function actionInplay()
    {
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->apiUserToken;//13750-7oAGo6wafQlP47
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        
        foreach ( $response->results as $items ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $response1 = json_decode(curl_exec($ch));
            curl_close($ch);
            
            //$event = $response1->results[0]->event;
            $marketsArr = $response1->results[0]->markets[0];
            
            $responseArr[] = [
                'item' => $items,
                'market' => $marketsArr->runners
            ];
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
    public function actionUpcoming()
    {
        $url = 'https://api.betsapi.com/v1/betfair/ex/upcoming?sport_id=4&token='.$this->apiUserToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        
        $responseArr = [];
        
        foreach ( $response->results as $items ){
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$items->id;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $response1 = json_decode(curl_exec($ch));
            curl_close($ch);
            
            //$event = $response1->results[0]->event;
            $marketsArr = $response1->results[0]->markets[0];
            
            $responseArr[] = [
                'item' => $items,
                'market' => $marketsArr->runners
            ];
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }*/
    
    public function actionExchange()
    {
        $eID = $_GET['id'];
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
            
            $marketsArr = $response1->results[0]->markets[0];
            $marketName = $marketsArr->description->marketName;
            $totalMatched = number_format($marketsArr->state->totalMatched , 5);
            $marketId = $marketsArr->marketId;
            $eventId = $marketsArr->market->eventId;
            $rules = $marketsArr->licence->rules;
            
            $runnersArr = [];
            
            $time = strtotime(date('Y-m-d H:i:s'));
            
            foreach ( $marketsArr->runners as $runners ){
                
                $back = $lay = [
                    'price' => '-',
                    'size' => '-'
                ];
                
                if( isset( $runners->exchange->availableToBack ) ){
                    $back = [
                        'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                        'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                    ];
                }
                
                if( isset( $runners->exchange->availableToLay ) ){
                    $lay = [
                        'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                        'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                    ];
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
            
            
            
            $check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
            
            if( $check != null ){
                $responseArr[] = [
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'time' => $time,
                    'marketName' => $marketName,
                    'matched' => $totalMatched,
                    'runners' => $runnersArr,
                    'rules' => $rules
                ];
            }
        }
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
    }
    
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
    
    
    public function actionIndex()
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
        
        $query = Event::find()
        ->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'event_slug' , 'img' , 'icon' , 'market_count', 'created_at' , 'updated_at' , 'status' ] )
            //->from( Events::tableName() . ' e' )
            ->andWhere( [ 'status' => [1,2] ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "event_type_name" , $filters[ "title" ] ] );
            }
            
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
    
    public function actionCreateplacebet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $data = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( $r_data['session_type'] != 'fancy' ){
                
                $data['PlaceBet'] = $r_data;
                $model = new PlaceBet();
                
                if ($model->load($data)) {
                    
                    if( $model->event_id != null ){
                        
                        $play = EventsPlayList::findOne(['event_id' => $model->event_id , 'game_over' => 'NO' ,'status' => 1 ]);
                        if( $play == null ){
                            $response[ "error" ] = [
                                "message" => "This event is already closed!" ,
                                "data" => $model->errors
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
                    
                    /*if( isset(\Yii::$app->user->id) ){
                     $uid = \Yii::$app->user->id;
                     }else{
                     $user = User::find()->select(['id'])->where(['username' => $data['PlaceBet']['username']])->one();
                     $uid = $user->id;
                     }*/
                    
                    $uid = \Yii::$app->user->id;
                    
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
                    
                    if( $this->currentBalance(false,$uid) < ( $model->loss+1)  ){
                        $response[ "error" ] = [
                            "message" => "Insufficient funds!" ,
                            "data" => $model->errors
                        ];
                        return $response;
                    }
                    
                    $model->bet_status = 'Pending';
                    $model->user_id = $uid;
                    $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                    $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                    
                    if( $model->session_type == 'match_odd' ){
                        
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
                        
                        $bet = $model;
                        
                        $model = new TransactionHistory();
                        
                        $model->user_id = $uid;
                        $model->bet_id = $bet->id;
                        $model->client_name = $bet->client_name;
                        $model->transaction_type = 'DEBIT';
                        $model->transaction_amount = $bet->loss;
                        $model->current_balance = $this->currentBalance($bet->loss , $uid);
                        $model->status = 1;
                        $model->created_at = $model->updated_at = time();
                        
                        if( $model->save() ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Place bet successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $bet->delete();
                            
                            $response[ "error" ] = [
                                "message" => "somthing wrong!" ,
                                "data" => $model->errors
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
                
            }else{
                
                //echo '<pre>';print_r($r_data);die;
                $rnr = explode(':', $r_data['runner']);
                
                if( !is_array( $rnr ) ){
                    
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                    
                }
                
                $rnr_val = explode('&', $rnr[1]);
                
                if( !is_array( $rnr_val ) ){
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                }
                
                
                if( $r_data['bet_type'] == 'yes' && $r_data['price'] != $rnr_val[0] ){
                    
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                    
                }
                if( $r_data['bet_type'] == 'no' && $r_data['price'] != $rnr_val[1] ){
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Wrong place bet!"
                    ];
                    return $response;
                }
                
                $data['PlaceBet'] = $r_data;
                $model = new PlaceBet();
                
                if ($model->load($data)) {
                    
                    if( $model->event_id != null ){
                        
                        $manualSession = ManualSession::findOne([ 'id' => $model->market_id , 'event_id' => $model->event_id , 'game_over' => 'NO' , 'status' => 1 ]);
                        if( $manualSession == null ){
                            $response[ "error" ] = [
                                "message" => "This session is already closed!"
                            ];
                            return $response;
                        }
                        
                        $play = EventsPlayList::findOne(['event_id' => $model->event_id , 'game_over' => 'NO' , 'status' => 1 ]);
                        if( $play == null ){
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
                    
                    /*if( isset(\Yii::$app->user->id) ){
                     $uid = \Yii::$app->user->id;
                     }else{
                     $user = User::find()->select(['id'])->where(['username' => $data['PlaceBet']['username']])->one();
                     $uid = $user->id;
                     }*/
                    
                    $uid = \Yii::$app->user->id;
                    
                    $model->win = $model->size;
                    $model->loss = $model->size;
                    
                    if( $this->currentBalance(false,$uid) < ( $model->loss+1)  ){
                        $response[ "error" ] = [
                            "message" => "Insufficient funds!"
                        ];
                        return $response;
                    }
                    
                    $model->bet_status = 'Pending';
                    $model->user_id = $uid;
                    $model->client_name = $this->getMasterName( $uid );//\Yii::$app->user->username;
                    $model->master = $this->getMasterName( $this->getMasterId( $uid ) );
                    
                    $model->ccr = 0;
                    
                    $model->status = 1;
                    $model->created_at = $model->updated_at = time();
                    $model->ip_address = $this->get_client_ip();
                    
                    if( $model->save() ){
                        
                        $bet = $model;
                        
                        $model = new TransactionHistory();
                        
                        $model->user_id = $uid;
                        $model->bet_id = $bet->id;
                        $model->client_name = $bet->client_name;
                        $model->transaction_type = 'DEBIT';
                        $model->transaction_amount = $bet->loss;
                        $model->current_balance = $this->currentBalance($bet->loss , $uid);
                        $model->status = 1;
                        $model->created_at = $model->updated_at = time();
                        
                        if( $model->save() ){
                            $response = [
                                'status' => 1 ,
                                "success" => [
                                    "message" => "Place bet successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $bet->delete();
                            
                            $response[ "error" ] = [
                                "message" => "somthing wrong!" ,
                                "data" => $model->errors
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
                
            }
            
        }
        
        return $response;
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
    }
    
    // Function to check max stack val
    public function defaultMaxStack()
    {
        $max_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_stack;
        }
    }
    
    // Function to check min stack val
    public function defaultMinStack()
    {
        $min_stack = 0;
        $setting = Setting::findOne([ 'key' => 'MIN_STACK_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $min_stack;
        }
    }
    
    // Function to check max profit limit val
    public function defaultMaxProfit()
    {
        $max_profit = 0;
        $setting = Setting::findOne([ 'key' => 'MAX_PROFIT_CRICKET' , 'status' => 1 ]);
        if( $setting != null ){
            return $setting->value;
        }else{
            return $max_profit;
        }
    }
    
    // Function to get the client current Balance
    public function currentBalance($price = false , $uid)
    {
        $user = User::findOne( \Yii::$app->user->id );
        
        if( $price != false ){
            $user->balance = ( $user->balance - $price );
            if( $user->save(['balance']) ){
                return $user->balance;
            }else{
                return $user->balance;
            }
        }else{
            return $user->balance;
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
