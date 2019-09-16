<?php
namespace api\controllers;

use common\models\PlaceBet;
use Yii;
use yii\web\Controller;

/**
 * Dream controller
 */
class DreamController extends Controller
{
    public $enableCsrfValidation = false;

    //action Fancy
    public function actionFancy()
    {
        if( isset($_GET['id']) ){

            $eventOddsArr = [];
            $event = (new \yii\db\Query())
                ->select(['event_id'])->from('events_play_list')
                ->where(['event_id' => $_GET['id'] , 'game_over' => 'NO' , 'status' => 1 ])
                ->one();

            if( $event != null ){

                $eventId = $event['event_id'];

                $marketArr = (new \yii\db\Query())
                    ->select(['market_id'])->from('manual_session')
                    ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                    ->all();

                if( $marketArr != null ){

                    $dataVal = [];

                    foreach ( $marketArr as $market ){

                        $marketId = $market['market_id'];

                        $matchOddData = (new \yii\db\Query())
                            ->from('manual_session')
                            ->select(['id','event_id','market_id','title','no','no_rate','yes','yes_rate','suspended','ball_running' ])
                            ->andWhere( [ 'market_id' => $marketId ] )
                            ->one();

                        if( $matchOddData != null ){
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

                        $eventOddsArr['fancy'][$marketId] = $data;

                    }

                }

            }

            return json_encode($eventOddsArr);

        }
    }

    //action AddFancy
    public function actionAddFancy()
    {
        if( isset($_GET['id']) ){

            $eventOddsArr = [ 'status' => 0 , 'data' => [] ];
            $event = (new \yii\db\Query())
                ->select(['event_id'])->from('events_play_list')
                ->where(['event_id' => $_GET['id'] , 'game_over' => 'NO' , 'status' => 1 ])
                ->one();

            if( $event != null ){

                $eventId = $event['event_id'];

                $marketArr = (new \yii\db\Query())
                    ->select(['event_id','market_id','title'])->from('manual_session')
                    ->where(['event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ])
                    ->all();

                if( $marketArr != null ){
                    $eventOddsArr['status'] = 200;
                    $eventOddsArr['data'] = $marketArr;
                }

            }

            return json_encode($eventOddsArr);

        }
    }


    //action Auth
    public function actionBookMaker()
    {
        if( isset($_GET['id']) ){


            $eventOddsArr = [];
            $event = (new \yii\db\Query())
                ->select(['event_id'])->from('events_play_list')
                ->where(['event_id' => $_GET['id'] , 'game_over' => 'NO' , 'status' => 1 ])
                ->one();

            if( $event != null ){

                $eventId = $event['event_id'];

                // Book Maker data

                $bookMaker = (new \yii\db\Query())
                    ->from('manual_session_match_odd')
                    ->select(['market_id'])
                    ->where( [ 'event_id' => $eventId , 'game_over' => 'NO' , 'status' => 1 ] )
                    ->one();

                if( $bookMaker != null ){

                    $runners = [];

                    $marketId = $bookMaker['market_id'];

                    $matchOddData = (new \yii\db\Query())
                        ->from('manual_session_match_odd_data')
                        ->select(['id','sec_id','runner','lay','back','suspended','ball_running'])
                        ->andWhere( [ 'market_id' => $marketId ] )
                        ->all();

                    if( $matchOddData != null ){

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

                    $data1 = ['marketId'=>$marketId , 'data' => $runners ];
                    $eventOddsArr['book_maker'] = $data1;
                    $eventOddsArr['status'] = 200;

                }

            }

            return json_encode($eventOddsArr);

        }
    }


}
