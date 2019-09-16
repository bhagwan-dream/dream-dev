<?php
namespace api\modules\v1\modules\events\controllers;

use common\models\CricketJackpot;
use Yii;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use common\models\TransactionHistory;
use common\models\MarketType;
use common\models\User;
use common\models\EventsPlayList;
use common\models\ManualSession;
use common\models\UserProfitLoss;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionLottery;

class GameRecallController extends \common\controllers\aController
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
    
    // Game Recall Fancy
    public function actionFancy(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'fancy2' ){
                    
                    $event = MarketType::findOne( ['market_id' => $r_data[ 'id' ] , 'event_type_id' => 4 , 'game_over' => 'YES' ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $this->gameRecallFancy( $event->market_id , 'fancy2') == true ){
                            
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                                // Update User Event Expose
                                \Yii::$app->db->createCommand()
                                    ->update('user_event_expose', ['status' => 1 ] ,
                                        ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                                // Update Market Result
                                \Yii::$app->db->createCommand()
                                ->delete('market_result', ['market_id' => $event->market_id])
                                ->execute();

                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
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
                    
                }else{
                    
                    $event = ManualSession::find()
                    ->where(['market_id' => $r_data[ 'id' ] , 'game_over' => 'YES' ])
                    ->one();
                    //echo '<pre>';print_r($event);die;
                    if( $event != null ){
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $this->gameRecallFancy( $event->market_id , 'fancy' ) == true ){
                            
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                                // Update User Event Expose
                                \Yii::$app->db->createCommand()
                                    ->update('user_event_expose', ['status' => 1 ] ,
                                        ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                                // Update Market Result
                                \Yii::$app->db->createCommand()
                                    ->delete('market_result', ['market_id' => $event->market_id])
                                    ->execute();

                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                            
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!2"
                            ];
                        }
                    }
                    
                }
            }
        }
        return $response;
    }
    
    //game Recall Fancy
    public function gameRecallFancy( $marketId , $sessionType){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss','Canceled'] , 'session_type' => $sessionType , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($betList);
            if( $betList != null ){
                $betIds = [];
                foreach ( $betList as $betId ){
                    $betIds[] = $betId['id'];
                }
                $transArr = TransactionHistory::findAll(['market_id' => $marketId , 'status'=>1]);
                if( $transArr != null ){
                    
                    if( TransactionHistory::updateAll(['status'=>2],['market_id'=>$marketId]) ){
                        
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                            foreach ( $transArr as $trans ){
                                
                                $user = User::findOne(['id'=>$trans->user_id]);
                                if( $trans->transaction_type == 'CREDIT' ){
                                    $user->profit_loss_balance = ($user->profit_loss_balance-$trans->transaction_amount);
                                }else{
                                    $user->profit_loss_balance = ($user->profit_loss_balance+$trans->transaction_amount);
                                }
                                $user->save();
                                
                            }
                            return true;
                            
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                }else{
                    if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                        return true;
                    }else{
                        return false;
                    }
                }
            }else{
                return true;
            }
        }
        
        return false;
        
    }
    
    // Game Recall MatchOdds
    public function actionMatchOdds(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data['market_id'] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'match_odd' ){

                    $event = EventsPlayList::findOne( [ 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'event_id' ] ,'game_over' => 'YES' ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';

                        if( $this->gameRecallMatchOdds( $event->market_id , 'match_odd' ) == true ){
                            
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                                // Update User Event Expose
                                \Yii::$app->db->createCommand()
                                    ->update('user_event_expose', ['status' => 1 ] ,
                                        ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                                // Update Market Result
                                \Yii::$app->db->createCommand()
                                    ->delete('market_result', ['market_id' => $event->market_id])
                                    ->execute();

                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
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
                    
                }else{
                    
                    $event = ManualSessionMatchOdd::find()
                    ->where([ 'event_id' => $r_data[ 'event_id' ] , 'market_id' => $r_data[ 'market_id' ] , 'game_over' => 'YES' ])
                    ->one();
                    
                    if( $event != null ){
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';

                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            
                            if( $this->gameRecallMatchOdds( $event->market_id , 'match_odd2' ) == true ){

                                // Update User Event Expose
                                \Yii::$app->db->createCommand()
                                    ->update('user_event_expose', ['status' => 1 ] ,
                                        ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                                // Update Market Result
                                \Yii::$app->db->createCommand()
                                    ->delete('market_result', ['market_id' => $event->market_id])
                                    ->execute();

                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
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
            }
        }
        return $response;
    }
    
    //game Recall Match Odds
    public function gameRecallMatchOdds( $marketId , $sessionType ){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss','Canceled'] , 'session_type' => $sessionType, 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($betList);die;
            if( $betList != null ){
                
                $transArr = TransactionHistory::findAll(['market_id'=>$marketId , 'status'=>1]);
                
                if( $transArr != null ){
                    //echo '<pre>';print_r($transArr);die;
                    if( TransactionHistory::updateAll(['status'=>2],['market_id'=>$marketId]) ){
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betList]) ){
                            
                            foreach ( $transArr as $trans ){
                                
                                $user = User::findOne([ 'id'=> $trans->user_id]);
                                if( $trans->transaction_type == 'CREDIT' ){
                                    $user->profit_loss_balance = ($user->profit_loss_balance-$trans->transaction_amount);
                                }else{
                                    $user->profit_loss_balance = ($user->profit_loss_balance+$trans->transaction_amount);
                                }
                                $user->save();
                                
                            }
                            return true;
                            
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }else{

                    if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betList]) ){
                        return true;
                    }else{
                        return false;
                    }


                }
            }else{
                return true;
            }
        }
        
        return false;
        
    }
    
    
    // Game Recall Lottery
    public function actionLottery(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'lottery' ){
                    
                    $event = ManualSessionLottery::findOne( ['market_id' => $r_data[ 'id' ] , 'game_over'=>'YES' ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';

                        if( $this->gameRecallLottery( $event->market_id ) == true ){
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){

                                // Update User Event Expose
                                \Yii::$app->db->createCommand()
                                    ->update('user_event_expose', ['status' => 1 ] ,
                                        ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                                // Update Market Result
                                \Yii::$app->db->createCommand()
                                ->delete('market_result', ['market_id' => $event->market_id])
                                ->execute();

                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
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
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong!"
                    ];
                }
            }
        }
        return $response;
    }
    
    //game Recall Lottery
    public function gameRecallLottery( $marketId ){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss','Canceled'] , 'session_type' => 'lottery' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($allBetListArr);die;
            if( $betList != null ){
                
                $betIds = [];
                foreach ( $betList as $betId ){
                    $betIds[] = $betId['id'];
                }
                
                $transArr = TransactionHistory::findAll(['market_id'=>$marketId, 'status'=>1]);
                
                if( $transArr != null ){
                    
                    if( TransactionHistory::updateAll(['status'=>2],['market_id'=>$marketId]) ){
                        
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                            foreach ( $transArr as $trans ){
                                
                                $user = User::findOne(['id'=>$trans->user_id]);

                                if( $trans->transaction_type == 'CREDIT' ){
                                    $user->profit_loss_balance = ($user->profit_loss_balance-$trans->transaction_amount);
                                }else{
                                    $user->profit_loss_balance = ($user->profit_loss_balance+$trans->transaction_amount);
                                }

                                $user->save();
                                
                            }

                            return true;
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }else{
                    if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                        return true;
                    }else{
                        return false;
                    }
                }
            }else{
                return true;
            }
        }
        
        return false;
        
    }

    // Game Recall Jackpot
    public function actionJackpot(){

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        //$id = \Yii::$app->request->get( 'id' );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data['type'] ) ){

                if( $r_data['type'] == 'jackpot' ){

                    $event = CricketJackpot::findOne( [ 'market_id' => $r_data[ 'id' ] , 'game_over'=>'YES' ] );

                    if( $event != null ){

                        //$event->game_over = 'NO';
                        //$event->win_result = 0;
                        //$event->updated_at = time();

                        if( $this->gameRecallJackpot( $event->event_id , $event->market_id ) == true ){

                            //Update jackpot gameover
                            $attr = [ 'game_over' => 'NO' , 'win_result' => 0 , 'updated_at' => time() ];
                            CricketJackpot::updateAll( $attr, ['event_id' => $event->event_id , 'game_over' => 'YES' ] );

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 1 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->event_id.'-JKPT' ]  )->execute();

                            // Update User Event Expose
                            \Yii::$app->db->createCommand()
                                ->update('user_event_expose', ['status' => 1 ] ,
                                    ['event_id' => $event->event_id,'market_id' => $event->market_id ]  )->execute();

                            // Update Market Result
                            \Yii::$app->db->createCommand()
                                ->delete('market_result', ['market_id' => $event->market_id])
                                ->execute();

                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Recall Successfully!"
                                ]
                            ];

                        }else{

                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }

                }else{

                    $response[ "error" ] = [
                        "message" => "Something wrong!"
                    ];
                }
            }
        }
        return $response;
    }

    //game Recall Jackpot
    public function gameRecallJackpot( $eventId , $marketId ){

        if( isset($marketId) && $marketId != null && isset($eventId) && $eventId != null ){

//            $betList = (new \yii\db\Query())
//                ->select(['id'])->where( [ 'event_id' => $eventId,'market_id' => $marketId ] )->from('place_bet')
//                ->andWhere( [ 'bet_status' => ['Win','Loss','Canceled'] , 'session_type' => 'jackpot' , 'status' => 1 , 'match_unmatch' => 1] )
//                ->createCommand(Yii::$app->db2)->queryAll();
//            //echo '<pre>';print_r($betList);die;

            //if( $betList != null ){

//                $betIds = [];
//                foreach ( $betList as $betId ){
//                    $betIds[] = $betId['id'];
//                }

                $transArr = TransactionHistory::findAll([ 'event_id' => $eventId, 'market_id'=>$marketId, 'status'=>1]);

                if( $transArr != null ){

                    if( TransactionHistory::updateAll(['status'=>2],['market_id'=>$marketId , 'event_id' => $eventId]) ){

                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],[ 'event_id' => $eventId , 'session_type' => 'jackpot' , 'status' => 1]) ){
                            foreach ( $transArr as $trans ){

                                $user = User::findOne(['id' => $trans->user_id]);

                                if( $trans->transaction_type == 'CREDIT' ){
                                    $user->profit_loss_balance = ($user->profit_loss_balance-$trans->transaction_amount);
                                }else{
                                    $user->profit_loss_balance = ($user->profit_loss_balance+$trans->transaction_amount);
                                }

                                $user->save();

                            }

                            return true;
                        }else{
                            return false;
                        }

                    }else{
                        return false;
                    }

                }else{

                    $checkBet = PlaceBet::findOne([ 'bet_status' => ['Win','Loss','Canceled'],'event_id' => $eventId , 'session_type' => 'jackpot' , 'status' => 1]);

                    if( $checkBet != null ){
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],[ 'event_id' => $eventId , 'session_type' => 'jackpot' , 'status' => 1]) ){
                            return true;
                        }else{
                            return false;
                        }
                    }else{
                        return true;
                    }

                }
            //}else{
                //return true;
            //}
        }

        return false;

    }

    //gameOverUpdateBalanceUser
    public function gameRecallUpdateBalanceUser( $marketId, $sessionType ){

        //User List
        $userList = PlaceBet::find()->select(['user_id'])
            ->where(['market_id' => $marketId , 'session_type' => $sessionType , 'status' => 1 ] )
            ->groupBy(['user_id'])->asArray()->all();

        if( $userList != null ) {
            foreach ($userList as $userData) {
                $uid = $userData['user_id'];
                UserProfitLoss::balanceValUpdate($uid);
            }
        }

    }
    
}
