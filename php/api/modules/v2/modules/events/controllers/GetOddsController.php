<?php
namespace api\modules\v2\modules\events\controllers;

use Yii;
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

class GetOddsController extends \common\controllers\aController  // \yii\rest\Controller
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
    
    //Event: Event Odds Data
    public function actionData()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            //$r_dataArr = ArrayHelper::toArray( $request_data );
            $r_data = ArrayHelper::toArray( $request_data );
            $marketIdsArr = $r_data;
            //echo '<pre>';print_r($marketIdsArr);die;
            if( $marketIdsArr != null ){
                $eventOddsArr = [];

                $cache = Yii::$app->cache;

                foreach ( $marketIdsArr as $market ){
                    $data = [];
                    if( $market['type'] == 'match_odd' ){
                        
                        $marketId = $market['market_id'];
                        $cache = \Yii::$app->cache;
                        $data = $cache->get($marketId);
                        $data = json_decode($data);
                        //echo '<pre>';print_r($data);die;
                        $eventOddsArr['match_odd'][$marketId] = $data;
                        
                    }else if( $market['type'] == 'fancy2' ){
                        
                        $marketId = $market['market_id'];
                        $key = $marketId;
                        $cache = \Yii::$app->cache;
                        $data = $cache->get($key);
                        $data = json_decode($data);
                        //echo '<pre>';print_r($data);die;

                        if( $data == null && !isset( $data->data ) ){

                            $dataVal = [
                                'no' => 0,
                                'no_rate' => 0,
                                'yes' => 0,
                                'yes_rate' => 0,
                            ];

                            $data = [
                                'market_id' => $marketId,
                                'suspended' => 'Y',
                                'ballRunning' => 'N',
                                'time' => round(microtime(true) * 1000),
                                'data' => $dataVal
                            ];

                        }


                        $eventOddsArr['fancy2'][$marketId] = $data;
                        
                    }else if( $market['type'] == 'match_odd2' ){
                        $runners = [];
                        $marketId = $market['market_id'];
                        $key = $this->BOOKMAKER_KEY.$marketId;
                        $redisDataAvailable = false;
                        if( $cache->exists($key) ) {
                            $redisDataAvailable = true;
                            $bookMakerData = $cache->get($key);
                            $bookMakerData = json_decode($bookMakerData, true);
                            //echo '<pre>';print_r($bookMakerData);die;
                            if( $bookMakerData != null ){
                                foreach( $bookMakerData['runners'] as $runner ){
                                    $runners[] = [
                                        'sec_id' => $runner['sec_id'],
                                        'runner' => $runner['runner'],
                                        'suspended' => isset($runner['suspended']) ? $runner['suspended'] : 'Y',
                                        'ballRunning' => isset($runner['ball_running']) ? $runner['ball_running'] : 'Y',
                                        'lay' => [
                                            'price' => $runner['lay'],
                                            'size' => $runner['lay'] == '0' ? '-' : rand(1234,9999),
                                        ],
                                        'back' => [
                                            'price' => $runner['back'],
                                            'size' => $runner['back'] == '0' ? '-' : rand(1234,9999),
                                        ]
                                    ];
                                }

                            }else{ $redisDataAvailable = false; }

                        }
                        if( $redisDataAvailable == false ){

                            $matchOddData = (new \yii\db\Query())
                                ->from('manual_session_match_odd_data')
                                ->select(['id','sec_id','runner','lay','back','suspended','ball_running'])
                                ->andWhere( [ 'market_id' => $marketId ] )
                                ->all();

                            foreach( $matchOddData as $runner ){

                                $runners[] = [
                                    'sec_id' => $runner['sec_id'],
                                    'runner' => $runner['runner'],
                                    'suspended' => $runner['suspended'],
                                    'ballRunning' => $runner['ball_running'],
                                    'lay' => [
                                        'price' => $runner['lay'],
                                        'size' => $runner['lay'] == '0' ? '-' : rand(1234,9999),
                                    ],
                                    'back' => [
                                        'price' => $runner['back'],
                                        'size' => $runner['back'] == '0' ? '-' : rand(1234,9999),
                                    ]
                                ];
                            }
                        }

                        $data = ['marketId'=>$marketId , 'data' => $runners ];
                        $eventOddsArr['match_odd2'][$marketId] = $data;
                        
                    }else if( $market['type'] == 'fancy' ) {
                        
                        $marketId = $market['market_id'];
                        $key = $this->FANCY_KEY.$marketId;
                        $redisDataAvailable = false;
                        if( $cache->exists($key) ) {
                            $redisDataAvailable = true;
                            $sessionData = $cache->get($key);
                            $sessionData = json_decode($sessionData, true);

                            if( $sessionData != null ){
                                $odds = $sessionData;
                                $dataVal = [
                                    'no' => $odds['no'],
                                    'no_rate' => $odds['no_rate'],
                                    'yes' => $odds['yes'],
                                    'yes_rate' => $odds['yes_rate'],
                                ];

                                $data = [
                                    'market_id' => $odds['market_id'],
                                    'suspended' => $odds['suspended'],
                                    'ballRunning' => $odds['ball_running'],
                                    'data' => $dataVal,
                                ];

                            }else{ $redisDataAvailable = false; }

                        }
                        if( $redisDataAvailable == false ){

                            $matchOddData = (new \yii\db\Query())
                                ->from('manual_session')
                                ->select(['id', 'event_id', 'market_id', 'title', 'no', 'no_rate', 'yes', 'yes_rate', 'suspended', 'ball_running'])
                                ->andWhere(['market_id' => $marketId])
                                ->one();

                            if ($matchOddData != null) {
                                $odds = $matchOddData;
                                $dataVal = [
                                    'no' => $odds['no'],
                                    'no_rate' => $odds['no_rate'],
                                    'yes' => $odds['yes'],
                                    'yes_rate' => $odds['yes_rate'],
                                ];

                                $data = [
                                    'market_id' => $odds['market_id'],
                                    'suspended' => $odds['suspended'],
                                    'ballRunning' => $odds['ball_running'],
                                    'data' => $dataVal,
                                ];

                            }
                        }
                        
                        $eventOddsArr['fancy'][$marketId] = $data;
                        
                    }
                    
                }
                
                //echo '<pre>';print_r($eventOddsArr);die;
                $response = [ "status" => 1 , "data" => [ "items" => $eventOddsArr ] ];
                
                
            }
            
        }
        return $response;
        
    }
    
    
}
