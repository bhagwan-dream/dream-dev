<?php
namespace api\modules\v1\modules\events\controllers;

use common\models\CricketJackpot;
use Yii;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use common\models\MarketType;
use common\models\User;
use common\models\EventsPlayList;
use common\models\ManualSession;
use common\models\UserProfitLoss;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionLottery;
use yii\db\Query;

class GameOverController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent','sessionuser','sessionuser2' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    // Game Over Match Odds
    public function actionMatchOdds(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'result' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) && ( $r_data[ 'result' ] != null ) ){
                
                $eventId = $r_data[ 'event_id' ];
                $marketId = $r_data[ 'market_id' ];
                $result = $r_data[ 'result' ];
                
                $event = EventsPlayList::findOne( ['event_id' => $eventId , 'market_id' => $marketId , 'game_over' => 'NO' , 'status' => [1,2] ] );
                
                if( $event != null ){
                    
                    $winResult = $this->commonRunnerName( $eventId, $marketId, $result );
                    
                    $event->game_over = 'YES';
                    $event->win_result = $winResult;
                    $event->status = 1;

                    $sportId = $event->sport_id;

                    // check if bet exist;
                    $checkBets = PlaceBet::find()->select('id')
                        ->where( ['event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' ] )->asArray()->one();

                    if( $checkBets != null ){
                        $this->gameoverResultMatchOdds( $eventId, $marketId, $result , 'match_odd' );
                        $this->transactionResultMatchOdds( $sportId , $eventId, $marketId , 'match_odd' );
                    }

                    if( $event->save( [ 'game_over','win_result','status' ] ) ){

                        $resultArr = [
                            'sport_id' => $event->sport_id,
                            'event_id' => $event->event_id,
                            'event_name' => $event->event_name,
                            'market_id' => $event->market_id,
                            'result' => $event->win_result,
                            'market_name' => 'Match Odd',
                            'session_type' => 'match_odd',
                            'updated_at' => time(),
                            'status' => 1,
                        ];

                        // Update Market Result
                        \Yii::$app->db->createCommand()
                        ->insert('market_result', $resultArr )->execute();

                        // Update User Event Expose
                        \Yii::$app->db->createCommand()
                            ->update('user_event_expose', ['status' => 2 ] ,
                                ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                        // Update Favorite Market
                        \Yii::$app->db->createCommand()
                        ->delete('favorite_market', ['market_id' => $event->market_id])
                        ->execute();

                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Event play game over successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        return $response;
    }
    
    // Game Over Manual Match Odds
    public function actionManualMatchOdds(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'result' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) && ( $r_data[ 'result' ] != null ) ){
                    
                    $eventId = $r_data[ 'event_id' ];
                    $marketId = $r_data[ 'market_id' ];
                    $result = $r_data[ 'result' ];
                    
                    $event = ManualSessionMatchOdd::findOne( ['event_id' => $eventId , 'market_id' => $marketId , 'game_over' => 'NO' , 'status' => [1,2] ] );
                    
                    if( $event != null ){
                        
                        $winResult = $this->commonRunnerName( $eventId, $marketId, $result );
                        
                        $event->game_over = 'YES';
                        $event->win_result = $winResult;
                        //$event->status = 1;

                        // check if bet exist;
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( ['event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameoverResultMatchOdds( $eventId, $marketId, $result , 'match_odd2' );
                            $this->transactionResultMatchOdds( $eventId, $marketId , 'match_odd2' );
                        }

                        if( $event->save( [ 'game_over','win_result' ] ) ){
                            
                            $resultArr = [
                                'sport_id' => 4,
                                'event_id' => $event->event_id,
                                'event_name' => $this->getEventName($event->event_id),
                                'market_id' => $event->market_id,
                                'result' => $event->win_result,
                                'market_name' => 'Book Maker',
                                'session_type' => 'match_odd2',
                                'updated_at' => time(),
                                'status' => 1,
                            ];

                            // Update Market Result
                            \Yii::$app->db->createCommand()
                                ->insert('market_result', $resultArr )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $event->market_id])
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Event play game over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!" ,
                                "data" => $event->errors
                            ];
                        }
                        
                    }
            }
        }
        return $response;
    }
    
    // Game Over Manual Fancy
    public function actionManualFancy(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'result' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) && ( $r_data[ 'result' ] != null ) ){
                    
                    $eventId = $r_data[ 'event_id' ];
                    $marketId = $r_data[ 'market_id' ];
                    $result = $r_data[ 'result' ];
                    
                    $event = ManualSession::findOne( ['event_id' => $eventId , 'market_id' => $marketId , 'game_over' => 'NO' , 'status' => [1,2] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = $result;
                        //$event->status = 1;

                        // check if bet exist;
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( ['event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameoverResultFancy( $eventId, $marketId, $result,'fancy' );
                            $this->transactionResultFancy( $eventId, $marketId ,'fancy' );
                        }

                        if( $event->save( [ 'game_over','win_result' ] ) ){
                            
                            $resultArr = [
                                'sport_id' => 4,
                                'event_id' => $event->event_id,
                                'event_name' => $this->getEventName($event->event_id),
                                'market_id' => $event->market_id,
                                'result' => $event->win_result,
                                'market_name' => $event->title,
                                'session_type' => 'fancy',
                                'updated_at' => time(),
                                'status' => 1,
                            ];

                            // Update Market Result
                            \Yii::$app->db->createCommand()
                                ->insert('market_result', $resultArr )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $event->market_id])
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Event play game over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!" ,
                            ];
                        }
                        
                    }else{

                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!" ,
                        ];

                    }
            }
        }
        return $response;
    }
    
    // Game Over Fancy
    public function actionFancy(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'result' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) && ( $r_data[ 'result' ] != null ) ){
                    
                    $eventId = $r_data[ 'event_id' ];
                    $marketId = $r_data[ 'market_id' ];
                    $result = $r_data[ 'result' ];
                    
                    $event = MarketType::findOne( ['event_id' => $eventId , 'market_id' => $marketId ,'game_over' => 'NO' , 'status' => [1,2] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = $result;
                        //$event->status = 1;

                        // check if bet exist;
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( ['event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameoverResultFancy( $eventId, $marketId, $result , 'fancy2' );
                            $this->transactionResultFancy( $eventId, $marketId , 'fancy2' );
                        }

                        if( $event->save( [ 'game_over','win_result' ] ) ){
                            
                            $resultArr = [
                                'sport_id' => 4,
                                'event_id' => $event->event_id,
                                'event_name' => $this->getEventName($event->event_id),
                                'market_id' => $event->market_id,
                                'result' => $event->win_result,
                                'market_name' => $event->market_name,
                                'session_type' => 'fancy2',
                                'updated_at' => time(),
                                'status' => 1,
                            ];

                            // Update Market Result
                            \Yii::$app->db->createCommand()
                                ->insert('market_result', $resultArr )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $event->market_id])
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Event play game over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }else{

                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!"
                        ];
                    }
            }
        }
        return $response;
    }
    
    // Game Over Lottery
    public function actionLottery(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'result' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) && ( $r_data[ 'result' ] != null ) ){
                    
                    $eventId = $r_data[ 'event_id' ];
                    $marketId = $r_data[ 'market_id' ];
                    $result = $r_data[ 'result' ];
                    
                    $event = ManualSessionLottery::findOne( ['event_id' => $eventId , 'market_id' => $marketId , 'game_over' => 'NO' , 'status' => [1,2] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = $result;
                        //$event->status = 1;

                        // check if bet exist;
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( ['event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameoverResultLottery( $eventId, $marketId, $result , 'lottery' );
                            $this->transactionResultLottery( $eventId, $marketId , 'lottery' );
                        }

                        if( $event->save( [ 'game_over','win_result' ] ) ){
                            
                            $resultArr = [
                                'sport_id' => 4,
                                'event_id' => $event->event_id,
                                'event_name' => $this->getEventName($event->event_id),
                                'market_id' => $event->market_id,
                                'result' => $event->win_result,
                                'market_name' => $event->title,
                                'session_type' => 'lottery',
                                'updated_at' => time(),
                                'status' => 1,
                            ];

                            // Update Market Result
                            \Yii::$app->db->createCommand()
                                ->insert('market_result', $resultArr )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $event->market_id])
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Event play game over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
            }
        }
        return $response;
    }


    // Game Over Jackpot
    public function actionJackpot(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data[ 'market_id' ] )
                && ( $r_data[ 'event_id' ] != null ) && ( $r_data[ 'market_id' ] != null ) ){

                $eventId = $r_data[ 'event_id' ];
                $marketId = $r_data[ 'market_id' ];

                $event = CricketJackpot::findOne( ['event_id' => $eventId , 'market_id' => $marketId , 'game_over' => 'NO' , 'status' => [1,2] ] );

                if( $event != null ){

                    $event->game_over = 'YES';
                    $event->win_result = 1;
                    $event->updated_at = time();
                    //$event->status = 1;

                    // check if bet exist;
                    $checkBets = PlaceBet::find()->select('id')
                        ->where( ['event_id' => $eventId , 'bet_status' => 'Pending' , 'status' =>1 ] )->asArray()->one();

                    if( $checkBets != null ){
                        $this->gameoverResultJackpot( $eventId, $marketId );
                        $this->transactionResultJackpot( $eventId, $marketId );
                    }

                    if( $event->save( [ 'game_over','win_result' , 'updated_at'] ) ){

                        $attr = [ 'game_over'=> 'YES' , 'win_result' => 0 , 'updated_at' => time() ];
                        $where = ['game_over'=> 'NO' , 'event_id' => $eventId ];
                        //CricketJackpot::updateAll( $attr , 'game_over = "NO" AND event_id = '.$eventId.' AND market_id != '.$marketId );
                        CricketJackpot::updateAll( $attr , $where );

                        $result = $marketName = $event->team_a.' ( '.$event->team_a_player.' ) AND '.$event->team_b.' ( '.$event->team_b_player.' )';
                        //$marketName = $event->team_a.' ('.$event->team_a_player.') AND '.$event->team_b.' ( '.$event->team_b_player.' )';

                        $resultArr = [
                            'sport_id' => 4,
                            'event_id' => $event->event_id,
                            'event_name' => $this->getEventName($event->event_id),
                            'market_id' => $event->market_id,
                            'result' => $result,//$event->win_result,
                            'market_name' => $marketName,//$event->title,
                            'session_type' => 'jackpot',
                            'updated_at' => time(),
                            'status' => 1,
                        ];

                        // Update Market Result
                        \Yii::$app->db->createCommand()
                            ->insert('market_result', $resultArr )->execute();

                        // Update User Event Expose
                        \Yii::$app->db->createCommand()
                            ->update('user_event_expose', ['status' => 2 ] ,
                                ['event_id' => $event->event_id,'market_id' => $event->event_id.'-JKPT' ]  )->execute();

                        // Update User Event Expose
                        \Yii::$app->db->createCommand()
                            ->update('user_event_expose', ['status' => 2 ] ,
                                ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                        // Update Favorite Market
//                        \Yii::$app->db->createCommand()
//                            ->delete('favorite_market', ['market_id' => $event->market_id])
//                            ->execute();

                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Game over successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!"
                        ];
                    }

                }
            }
        }
        return $response;
    }
    
    // Game Abundant
    public function actionAbundant(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'type' ] )  ){

                // check if bet exist;
                $checkBets = PlaceBet::find()->select('id')
                    ->where( [ 'session_type' => $r_data[ 'type' ] ,'event_id' => $r_data[ 'id' ] , 'market_id' => $r_data[ 'market_id' ] , 'bet_status' => 'Pending' ] )->asArray()->one();
                $checkBetsStatus = false;
                if( $checkBets != null ){
                    $checkBetsStatus = true;
                }

                if( $r_data[ 'type' ] == 'match_odd' ){
                    
                    $event = EventsPlayList::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';

                        // check if bet exist;
                        if( $checkBetsStatus != false ){
                            $where = [ 'session_type' => 'match_odd', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        }
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                }else if( $r_data[ 'type' ] == 'fancy2' ){
                    
                    $event = MarketType::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';

                        // check if bet exist;
                        if( $checkBetsStatus != false ) {
                            $where = ['session_type' => 'fancy2', 'market_id' => $r_data['market_id'], 'event_id' => $r_data['id'], 'bet_status' => 'Pending', 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'], $where);
                        }

                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if( $r_data[ 'type' ] == 'fancy' ){
                    
                    $event = ManualSession::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';

                        // check if bet exist;
                        if( $checkBetsStatus != false ) {
                            $where = ['session_type' => 'fancy', 'market_id' => $r_data['market_id'], 'event_id' => $r_data['id'], 'bet_status' => 'Pending', 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'], $where);
                        }

                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if($r_data[ 'type' ] == 'lottery' ){
                    
                    $event = ManualSessionLottery::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';

                        // check if bet exist;
                        if( $checkBetsStatus != false ) {
                            $where = ['session_type' => 'lottery', 'market_id' => $r_data['market_id'], 'event_id' => $r_data['id'], 'bet_status' => 'Pending', 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'], $where);
                        }

                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if( $r_data[ 'type' ] == 'match_odd2' ){
                    
                    $event = ManualSessionMatchOdd::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';

                        // check if bet exist;
                        if( $checkBetsStatus != false ) {
                            $where = [ 'session_type' => 'match_odd2', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        }

                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
                            \Yii::$app->db->createCommand()
                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if($r_data[ 'type' ] == 'jackpot' ){

                    $event = CricketJackpot::findOne( ['event_id' => $r_data[ 'id' ] ] );

                    if( $event != null ){

                        $checkBets = PlaceBet::find()->select('id')
                            ->where( [ 'session_type' => $r_data[ 'type' ] ,'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' ] )->asArray()->one();
                        $checkBetsStatus = false;
                        if( $checkBets != null ){
                            $checkBetsStatus = true;
                        }

                        // check if bet exist;
                        if( $checkBetsStatus != false ) {
                            //Update placebet Canceled
                            $where = ['session_type' => 'jackpot', 'event_id' => $r_data['id'], 'bet_status' => 'Pending', 'status' => 1];
                            PlaceBet::updateAll(['bet_status' => 'Canceled'], $where);

                            //Update jackpot gameover
                            $attr = [ 'game_over' => 'YES' , 'win_result' => 2 , 'updated_at' => time() ];
                            CricketJackpot::updateAll( $attr, ['event_id' => $r_data['id'] , 'game_over' => 'NO' ] );

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->event_id.'-JKPT' ]  )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 2 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Favorite Market
//                            \Yii::$app->db->createCommand()
//                                ->delete('favorite_market', ['market_id' => $r_data[ 'market_id' ] ] )
//                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];

                        }else{

                            //Update jackpot gameover
                            $attr = [ 'game_over' => 'YES' , 'win_result' => 2 , 'updated_at' => time() ];
                            CricketJackpot::updateAll( $attr, ['event_id' => $r_data['id'] , 'game_over' => 'NO' ] );

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];

                        }

                    }

                }

            }
        }
        return $response;
    }
    
    //transaction Result MatchOdds
    public function transactionResultMatchOdds( $sportId , $eventId, $marketId, $sessionType ){
        
        $amount = 0;
        
        $userList = PlaceBet::find()->select(['user_id'])
        ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
        ->andWhere( [ 'session_type' => $sessionType,'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
        ->groupBy(['user_id'])->asArray()->all();
        
        if( $userList != null ){
            
            foreach ( $userList as $user ){
                
                $uId = $user['user_id'];
                
                $userWinList = PlaceBet::find()
                ->select('SUM( win ) as winVal')
                ->where(['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere([ 'session_type' => $sessionType,'bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                $userLossList = PlaceBet::find()
                ->select('SUM( loss ) as lossVal')
                ->where( ['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => $sessionType, 'bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                if( !isset($userWinList[0]['winVal']) ){ $profit = 0; }else{ $profit = $userWinList[0]['winVal']; }
                if( !isset($userLossList[0]['lossVal']) ){ $loss = 0; }else{ $loss = $userLossList[0]['lossVal']; }
                
                $amount = $profit-$loss;
                
                if( $amount != 0 ){
                    $this->updateTransactionHistory($sportId,$uId,$eventId,$marketId,$amount,$sessionType);
                }
            }
            
        }
        
    }

    public function gameOverUpdateBalanceUser( $eventId, $marketId, $sessionType ){

        //User List
        $userList = PlaceBet::find()->select(['user_id'])
            ->where(['market_id' => $marketId ,'event_id' => $eventId , 'session_type' => $sessionType , 'status' => 1 ] )
            ->groupBy(['user_id'])->asArray()->all();

        if( $userList != null ) {
            foreach ($userList as $userData) {
                $uid = $userData['user_id'];
                UserProfitLoss::balanceValUpdate($uid);
            }
        }

    }

    //gameover Result MatchOdds
    public function gameoverResultMatchOdds( $eventId , $marketId , $winResult , $sessionType ){
        
        if( $eventId != null && $marketId != null && $winResult != null ){
            
            /*User Win calculation for Back */
            $backWinList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'sec_id' => $winResult ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $backWinList != null ){
                $idsArr = [];
                foreach ( $backWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }
            
            /*User Win calculation for Lay*/
            $layWinList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $layWinList != null ){
                $idsArr = [];
                foreach ( $layWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }
            
            /* User Loss calculation */
            $lossList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            //->andWhere( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                $idsArr = [];
                foreach ( $lossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

            /* Unmatched Bet canceled  */
            $unMatchedList = PlaceBet::find()->select(['id'])
                ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 0] )
                ->asArray()->all();

            if( $unMatchedList != null ){
                $idsArr = [];
                foreach ( $unMatchedList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Canceled'] , ['id'=>$idsArr]);
            }
            
        }
        
    }
    
    //gameover Result Fancy
    public function gameoverResultFancy( $eventId , $marketId , $winResult , $sessionType ){
        
        if( $eventId != null && $marketId != null && $winResult != null ){
            
            /*User Win calculation for No */
            $noWinList = PlaceBet::find()->select(['id'])
            ->where( [ 'bet_type' => 'no','market_id' => $marketId, 'event_id' => $eventId ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1] )
            ->andWhere( [ '>' , 'price' , ($winResult+0) ] )
            ->asArray()->all();

            if( $noWinList != null ){

                $idsArr = [];
                foreach ( $noWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }
            
            /*User Win calculation for Yes */
            $yesWinList = PlaceBet::find()->select(['id'])
            ->where( [ 'bet_type' => 'yes','market_id' => $marketId, 'event_id' => $eventId ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1] )
            ->andWhere( [ '<=' , 'price' , ($winResult+0) ] )
            ->asArray()->all();
            
            //var_dump($yesWinList);die;

            if( $yesWinList != null ){


                $idsArr = [];
                foreach ( $yesWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }
            
            /* User Loss calculation */
            $lossList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            //->where( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => ['no' , 'yes'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();

            if( $lossList != null ){

                $idsArr = [];
                foreach ( $lossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }
            
        }
        
    }
    
    //transaction Result Fancy
    public function transactionResultFancy( $eventId, $marketId, $sessionType){
        
        $amount = 0;
        
        $userList = PlaceBet::find()->select(['user_id'])
        ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
        ->andWhere( [ 'session_type' => $sessionType,'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
        ->groupBy(['user_id'])->asArray()->all();

        if( $userList != null ){

            foreach ( $userList as $user ){
                
                $uId = $user['user_id'];
                
                $userWinList = PlaceBet::find()
                ->select('SUM( win ) as winVal')
                ->where(['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere([ 'session_type' => $sessionType,'bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                $userLossList = PlaceBet::find()
                ->select('SUM( loss ) as lossVal')
                ->where( ['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                if( !isset($userWinList[0]['winVal']) ){ $profit = 0; }else{ $profit = $userWinList[0]['winVal']; }
                if( !isset($userLossList[0]['lossVal']) ){ $loss = 0; }else{ $loss = $userLossList[0]['lossVal']; }
                
                $amount = $profit-$loss;
                
                if( $amount != 0 ){
                    $this->updateTransactionHistory(4,$uId,$eventId,$marketId,$amount,$sessionType);
                }
            }
            
        }
        
    }
    
    //gameover Result Lottery
    public function gameoverResultLottery( $eventId , $marketId , $winResult , $sessionType ){
        
        if( $eventId != null && $marketId != null && $winResult != null ){
            
            /*User Win calculation for Back */
            $backWinList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'price' => ($winResult+0) ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $backWinList != null ){
                $idsArr = [];
                foreach ( $backWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }
            
            /* User Loss calculation */
            $lossList = PlaceBet::find()->select(['id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            //->andWhere( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                $idsArr = [];
                foreach ( $lossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }
            
        }
        
    }

    //gameover Result Jackpot
    public function gameoverResultJackpot( $eventId , $marketId ){

        if( $eventId != null && $marketId != null ){

            //echo 'Do something for jackpot';die;
            /*User Win calculation for Back */
            $winList = PlaceBet::find()->select(['id'])
                ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => 'jackpot','bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();

            if( $winList != null ){
                $idsArr = [];
                foreach ( $winList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /* User Loss calculation */
            $lossList = PlaceBet::find()->select(['id'])
                ->where( [ '!=' , 'market_id', $marketId ] )
                ->andWhere( [ 'event_id' => $eventId, 'session_type' => 'jackpot','bet_status' => 'Pending' ,'bet_type' => 'back', 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();

            if( $lossList != null ){
                $idsArr = [];
                foreach ( $lossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

        }

    }
    
    //transaction Result Lottery
    public function transactionResultLottery( $eventId, $marketId, $sessionType ){
        
        $amount = 0;
        
        $userList = PlaceBet::find()->select(['user_id'])
        ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
        ->andWhere( [ 'session_type' => $sessionType,'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
        ->groupBy(['user_id'])->asArray()->all();
        
        if( $userList != null ){
            
            foreach ( $userList as $user ){
                
                $uId = $user['user_id'];
                
                $userWinList = PlaceBet::find()
                ->select('SUM( win ) as winVal')
                ->where(['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere([ 'session_type' => $sessionType,'bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                $userLossList = PlaceBet::find()
                ->select('SUM( loss ) as lossVal')
                ->where( ['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();
                
                if( !isset($userWinList[0]['winVal']) ){ $profit = 0; }else{ $profit = $userWinList[0]['winVal']; }
                if( !isset($userLossList[0]['lossVal']) ){ $loss = 0; }else{ $loss = $userLossList[0]['lossVal']; }
                
                $amount = $profit-$loss;
                
                if( $amount != 0 ){
                    $this->updateTransactionHistory(4,$uId,$eventId,$marketId,$amount,$sessionType);
                }
            }
            
        }
        
    }

    //transaction Result Jackpot
    public function transactionResultJackpot( $eventId, $marketId ){

        if( $eventId != null && $marketId != null ){

            $winList = PlaceBet::find()->select(['id'])
                ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
                ->andWhere( [ 'session_type' => 'jackpot','bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();

            if( $winList != null ){
                $idsArr = [];
                foreach ( $winList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /* User Loss calculation */
            $lossList = PlaceBet::find()->select(['id'])
                ->where( [ '!=' , 'market_id', $marketId ] )
                ->andWhere( [ 'event_id' => $eventId, 'session_type' => 'jackpot','bet_status' => 'Pending' ,'bet_type' => 'back', 'status' => 1 , 'match_unmatch' => 1] )
                ->asArray()->all();

            if( $lossList != null ){
                $idsArr = [];
                foreach ( $lossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

        }

        $amount = 0;

        $userList = (new \yii\db\Query())
            ->select(['user_id'])->from('place_bet')
            ->where( ['event_id' => $eventId ] )
            ->andWhere( [ 'session_type' => 'jackpot','bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->groupBy(['user_id'])->createCommand(Yii::$app->db2)->queryAll();

        if( $userList != null ){
            //echo '<pre>';print_r($userList);die;
            foreach ( $userList as $user ){
                $uId = $user['user_id'];
                $userWinList = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')->from('place_bet')
                    ->where(['user_id'=>$user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                    ->andWhere([ 'session_type' => 'jackpot','bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db2)->queryOne();

                $userLossList = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')->from('place_bet')
                    ->where( [ '!=' , 'market_id', $marketId ] )
                    ->andWhere( ['user_id'=>$user['user_id'],'event_id' => $eventId ] )
                    ->andWhere( [ 'session_type' => 'jackpot','bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db2)->queryOne();

//                if( $uId == '1121' ){
//                    echo '<pre>';print_r($userWinList);print_r($userLossList);die;
//                }

                if( !isset($userWinList['winVal']) ){ $profit = 0; }else{ $profit = (float)$userWinList['winVal']; }
                if( !isset($userLossList['lossVal']) ){ $loss = 0; }else{ $loss = (float)$userLossList['lossVal']; }

                $amount = $profit-$loss;

                if( $amount != 0 ){
                    $this->updateTransactionHistory(4,$uId,$eventId,$marketId,$amount,'jackpot');
                }
            }

        }

    }

    // Update Transaction History
    public function updateTransactionHistory($sportId,$uId,$eventId,$marketId,$amount,$sessionType)
    {
        if( $amount > 0 ){
            $type = 'CREDIT';
        }else{
            $amount = (-1)*$amount;
            $type = 'DEBIT';
        }
        
        $cUser = User::find()->select(['parent_id'])->where(['id'=>$uId])->asArray()->one();
        
        $pId = $cUser['parent_id'];
        $resultArr = [
            'sport_id' => $sportId,
            'session_type' => $sessionType,
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
            'description' => $this->setDescription($eventId,$marketId,$sessionType),
            'is_commission' => 0,
            'is_cash' => 0,
            'status' => 1,
            'updated_at' => time(),
            'created_at' => time(),
        ];
        
        \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
        \Yii::$app->db->close();
        
        if( $type == 'CREDIT' ){

            if( $sessionType == 'match_odd' ){
                $commission = ($amount*1)/100;

                $resultArr = [
                    'sport_id' => $sportId,
                    'session_type' => $sessionType,
                    'client_id' => $uId,
                    'user_id' => $uId,
                    'parent_id' => $pId,
                    'child_id' => 0,
                    'event_id' => $eventId,
                    'market_id' => $marketId,
                    'transaction_type' => 'DEBIT',
                    'transaction_amount' => $commission,
                    'p_transaction_amount' => 0,
                    'c_transaction_amount' => 0,
                    'current_balance' => $this->getCurrentBalanceClient($uId,$commission,'DEBIT'),
                    'description' => $this->setDescription($eventId,$marketId,$sessionType),
                    'is_commission' => 1,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];

                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
                \Yii::$app->db->close();
            }

            
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
                
                $cUser = User::find()->select(['parent_id'])->where(['id'=>$user['user_id']])->asArray()->one();
                
                $pId = $cUser['parent_id'];
                
                //$balance = $user['balance']+$amount;
                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'DEBIT');

                $resultArr = [
                    'sport_id' => $sportId,
                    'session_type' => $sessionType,
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
                    'description' => $this->setDescription($eventId,$marketId,$sessionType),
                    'is_commission' => 0,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];
                
                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
                \Yii::$app->db->close();

                if( $sessionType == 'match_odd' ){
                    // Commistion Data
                    $transAmountComm = ( $commission*$user['profit_loss'] )/100;
                    $pTransactionAmountComm = ( $commission*(100-$user['g_profit_loss']) )/100;
                    $cTransactionAmountComm = ( $commission*($user['g_profit_loss']-$user['profit_loss']) )/100;
                    $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transAmountComm,'CREDIT');
                    $resultArr = [
                        'sport_id' => $sportId,
                        'session_type' => $sessionType,
                        'client_id' => $uId,
                        'user_id' => $user['user_id'],
                        'child_id' => $childId,
                        'parent_id' => $pId,
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'transaction_type' => 'CREDIT',
                        'transaction_amount' => $transAmountComm,
                        'p_transaction_amount' => $pTransactionAmountComm,
                        'c_transaction_amount' => $cTransactionAmountComm,
                        'current_balance' => $currentBalance,
                        'description' => $this->setDescription($eventId,$marketId,$sessionType),
                        'is_commission' => 1,
                        'is_cash' => 0,
                        'status' => 1,
                        'updated_at' => time(),
                        'created_at' => time(),
                    ];

                    \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
                    \Yii::$app->db->close();
                }
                
                $childId = $user['user_id'];
                
            }
            
        }else{
            //$parentUserData = $this->getParentUserData($uId);
            $query = new Query();
            $where = [ 'pl.client_id' => $uId ];
            $query->select([ 'pl.user_id as user_id','pl.actual_profit_loss as profit_loss' ,'pl.profit_loss as g_profit_loss',  'u.balance as balance' ] )
            ->from('user_profit_loss as pl')
            ->join('LEFT JOIN','user as u','u.id=pl.user_id')
            ->where( $where )->andWhere(['!=','pl.actual_profit_loss',0])
            ->orderBy(['pl.user_id' => SORT_DESC]);
            $command = $query->createCommand();
            $parentUserData = $command->queryAll();
            
            //echo '<pre>';print_r($parentUserData);die;
            
            $childId = $uId;
            
            foreach ( $parentUserData as $user ){
                
                $cUser = User::find()->select(['parent_id'])->where(['id'=>$user['user_id']])->asArray()->one();
                
                $pId = $cUser['parent_id'];
                
                //$balance = $user['balance']+$amount;
                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'CREDIT');
                
                $resultArr = [
                    'sport_id' => $sportId,
                    'session_type' => $sessionType,
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
                    'description' => $this->setDescription($eventId,$marketId,$sessionType),
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
    public function getParentUserData($uid)
    {
        $userArr = [];
        $user = User::find()->select(['parent_id','balance'])->where(['user_id' => $uid ])->asArray()->one();
        if( $user != null ){
            if( $user['parent_id'] != 0 ){
                
                array_push($userArr, ['user_id' => $user['parent_id'] , 'balance' => $user['balance'] ]);
                
                $user = User::find()->select(['parent_id','balance'])->where(['user_id' => $user['parent_id'] ])->asArray()->one();
                if( $user != null ){
                    if( $user['parent_id'] != 0 ){
                        
                        array_push($userArr, ['user_id' => $user['parent_id'] , 'balance' => $user['balance'] ]);
                        
                        $user = User::find()->select(['parent_id','balance'])->where(['user_id' => $user['parent_id'] ])->asArray()->one();
                        if( $user != null ){
                            if( $user['parent_id'] != 0 ){
                                
                                array_push($userArr, ['user_id' => $user['parent_id'] , 'balance' => $user['balance'] ]);
                                
                                $user = User::find()->select(['parent_id','balance'])->where(['user_id' => $user['parent_id'] ])->asArray()->one();
                                if( $user != null ){
                                    if( $user['parent_id'] != 0 ){
                                        
                                        array_push($userArr, ['user_id' => $user['parent_id'] , 'balance' => $user['balance'] ]);
                                        
                                        $user = User::find()->select(['parent_id','balance'])->where(['user_id' => $user['parent_id'] ])->asArray()->one();
                                        if( $user != null ){
                                            if( $user['parent_id'] != 0 ){
                                                
                                                array_push($userArr, ['user_id' => $user['parent_id'] , 'balance' => $user['balance'] ]);
                                                
                                                //$user = User::find()->select(['parent_id','balance'])->where(['user_id' => $user['parent_id'] ])->asArray()->one();
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $userArr;
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
                //$profit_loss_balance = $user->profit_loss_balance;
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
            //$balance = $user->balance;
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
    
    // Function to get the Tras Description
    public function setDescription($eventId,$marketId,$sessionType)
    {
        $sport = ['1'=>'Football','2'=>'Tennis','4'=>'Cricket'];
        $query = new Query();
        $query->select([ 'sport_id','event_name' ] )
        ->from('events_play_list')
        ->where(['event_id' => $eventId ]);
        $command = $query->createCommand();
        $event = $command->queryOne();
        //echo '<pre>';print_r($event);die;
        if( $event != null ){
            
            $description = $sport[$event['sport_id']].' > '.$event['event_name'];
            
            if( $sessionType == 'match_odd' ){
                
                $description = $description.' > Match Odd';
                
            }else if( $sessionType == 'match_odd2' ){
                
                $description = $description.' > Match Odd 2';
                
            }else if( $sessionType == 'fancy' ){
                
                $query = new Query();
                $query->select([ 'title' ] )
                ->from('manual_session')
                ->where(['market_id' => $marketId ]);
                $command = $query->createCommand();
                $market = $command->queryOne();
                //echo '<pre>';print_r($market);die;
                if( $market != null ){
                    
                    $description = $description.' > Fancy > '.$market['title'];
                    
                }
                
            }else if( $sessionType == 'fancy2' ){
                
                $query = new Query();
                $query->select([ 'market_name' ] )
                ->from('market_type')
                ->where(['market_id' => $marketId ]);
                $command = $query->createCommand();
                $market = $command->queryOne();
                
                if( $market != null ){
                    
                    $description = $description.' > Fancy 2 > '.$market['market_name'];
                    
                }
                
            }else if( $sessionType == 'lottery' ){
                
                $query = new Query();
                $query->select([ 'title' ] )
                ->from('manual_session_lottery')
                ->where(['market_id' => $marketId ]);
                $command = $query->createCommand();
                $market = $command->queryOne();
                
                if( $market != null ){
                    
                    $description = $description.' > Lottery > '.$market['title'];
                    
                }
                
            }else if( $sessionType == 'jackpot' ){

                $description = $description.' > Jackpot';

            }else{
                $description = $description;
            }
            
        }else{ $description = 'Event Not Found!'; }
        
        return $description;
    }
    
    // get Current Balance
    public function getEventName($eventId)
    {
        $event = EventsPlayList::find()->select(['event_name'])
        ->where(['event_id' => $eventId ])->asArray()->one();
        
        $eventName = $eventId;
        if( $event != null ){
            $eventName = $event['event_name'];
        }
        
        return $eventName;
    }
    
}
