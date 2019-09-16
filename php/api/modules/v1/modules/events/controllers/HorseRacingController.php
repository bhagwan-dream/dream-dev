<?php
namespace api\modules\v1\modules\events\controllers;

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
use common\models\TempTransactionHistory;

class HorseRacingController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','client' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    //action Inplay
    public function actionInplay()
    {
        $inplay = EventsPlayList::find()
        ->where(['play_type'=>['IN_PLAY','UPCOMING'] , 'game_over' => ['YES','NO'] , 'sport_id' => 7 ])
        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $inplay ] ];
    }
    
    //action Upcoming
    public function actionUpcoming()
    {
        $upcoming = EventsPlayList::find()
        ->where(['play_type'=>'UPCOMING' , 'game_over' => 'NO' , 'sport_id' => 7 ])
        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $upcoming ] ];
    }
    
    //check Market Status
    public function checkMarketStatus($mtype)
    {
        $marketType = MarketType::findOne([ 'market_type'=> $mtype,'status' => 1 ]);
        if( $marketType != null ){
            return true;
        }else{
            return false;
        }
        
    }
    
    //client Commission Rate
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
    
    //current Balance
    public function currentBalance($price = false)
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
    
    //action Setting View
    public function actionSettingView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        if( $id != null ){
            $model = EventsPlayList::find()->select( [ 'max_stack' , 'min_stack' , 'max_profit' ] )->where( [ 'event_id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    //action Setting
    public function actionSetting(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $id ) && isset( $r_data[ 'win_result' ] ) ){
                
                $event = EventsPlayList::findOne( ['event_id' => $id ] );
                
                if( $event != null ){
                    
                    $event->max_stack           = $r_data[ 'max_stack' ];
                    $event->min_stack           = $r_data[ 'min_stack' ];
                    $event->max_profit    = $r_data[ 'max_profit' ];
                    
                    if( $event->save( [ 'max_stack' , 'min_stack' , 'max_profit' ] ) ){
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Event Setting updated successfully!"
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
    
    // Game Over Match Odds
    public function actionGameoverMatchOdds(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $id = $r_data[ 'id' ];
            if( isset( $id ) && isset( $r_data[ 'result' ] ) ){
                
                $event = EventsPlayList::findOne( ['event_id' => $id , 'sport_id' => 7 ] );
                
                if( $event != null ){
                    
                    $event->game_over = 'YES';
                    $event->win_result = $r_data[ 'result' ];
                    
                    if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                        
                        $this->gameoverResultMatchOdds( $event->event_id , $event->market_id , $event->win_result );
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Game Over successfully!"
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
    
    //Gameover Result Match Odds
    public function gameoverResultMatchOdds( $eventId , $marketId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'runner' => $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($backWinList);
            $layWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->andwhere( [ '!=' , 'runner' , $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if( $backWinList != null ){
                foreach( $backWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
            }
            
            if( $layWinList != null ){
                
                foreach( $layWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
                
            }
            
            /* User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->where( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id,$loss->event_id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    // transection Win
    public function transectionWin($betID,$eventId)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        $clientId = $model->user_id;
        $ccr = $model->ccr;
        $amount = $model->win;
        $client = User::findOne( $model->user_id );
        $client->balance = $client->balance+$amount;
        //$updateTransArr = [];
        if( $client != null && $client->save(['balance'])
            && $this->updateTempTransactionHistory('CREDIT',$clientId,$client->id,$client->parent_id,$betID,$eventId,'F',$client->username,($amount-$ccr),$ccr,$client->balance) ){
                
                $parent = User::findOne( $client->parent_id );
                if( $parent->role === 1 ){
                    //if client parent admin
                    $admin = $parent;//User::findOne( $client->parent_id );
                    $commission = $amount;
                    //$admin->balance = ( $admin->balance - $commission );
                    if( $this->updateTempTransactionHistory('DEBIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->profit_loss_balance) ){
                        return true;
                    }else{
                        return false;
                    }
                    
                }else{
                    //client to master
                    $agent21 = User::findOne( $client->parent_id );
                    if( $agent21 != null && $agent21->profit_loss != 0 ){
                        $profitLoss1 = $agent21->profit_loss;
                        //$commission1 = round ( ( $amount*$profitLoss1 )/100 ,1);
                        $commission1 = $amount;
                        //$agent21->balance = ( $agent21->balance-$commission1 );
                        //$amount = ( $amount-$commission1 );
                        if( $this->updateTempTransactionHistory('DEBIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission1,$ccr,$agent21->profit_loss_balance) ){
                            
                            $parent = User::findOne( $agent21->parent_id );
                            
                            if( $parent->role === 1 ){
                                $ccrRate = 1;
                                $admin = $parent;//User::findOne( $agent21->parent_id );
                                $profitLoss = 100-$profitLoss1;
                                $commission = round ( ( $amount*$profitLoss )/100 , 1 );
                                $ccr = round ( ( $commission*$ccrRate )/100 , 1 );
                                //$admin->balance = ( $admin->balance-$commission );
                                if( $this->updateTempTransactionHistory('DEBIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->profit_loss_balance) ){
                                    //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission,$ccr,$agent21->profit_loss_balance)){
                                    return true;
                                }else{
                                    return false;
                                }
                                
                            }else{
                                
                                //master to master
                                $agent22 = User::findOne( $agent21->parent_id );
                                if( $agent22 != null && $agent22->profit_loss != 0 ){
                                    $ccrRate = 1;
                                    $profitLoss2 = 100-$agent21->profit_loss;//$agent22->profit_loss-$agent21->profit_loss;
                                    $commission2 = round ( ( $amount*$profitLoss2 )/100 , 1);
                                    $ccr = round ( ( $commission2*$ccrRate )/100 , 1 );
                                    //$agent22->balance = ( $agent22->balance-$commission2 );
                                    //$amount = ( $amount-$commission2 );
                                    if( $this->updateTempTransactionHistory('DEBIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission2,$ccr,$agent22->profit_loss_balance) ){
                                        //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission2,$ccr,$agent21->profit_loss_balance)){
                                        $parent = User::findOne( $agent22->parent_id );
                                        if( $parent->role === 1 ){
                                            $ccrRate = 1;
                                            $admin = $parent;//User::findOne( $agent22->parent_id );
                                            $profitLoss = 100-$agent22->profit_loss;
                                            $commission = round ( ( $amount*$profitLoss )/100 , 1 );
                                            $ccr = round ( ( $commission*$ccrRate )/100 , 1 );
                                            
                                            //$admin->balance = ( $admin->balance-$commission );
                                            if( $this->updateTempTransactionHistory('DEBIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->profit_loss_balance) ){
                                                //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission,$ccr,$agent22->profit_loss_balance) ){
                                                return true;
                                            }else{
                                                return false;
                                            }
                                            
                                        }else{
                                            
                                            //master to super master
                                            
                                            $agent11 = User::findOne( $agent22->parent_id );
                                            
                                            if( $agent11 != null && $agent11->profit_loss != 0 ){
                                                $ccrRate = 1;
                                                $profitLoss3 = 100-$agent22->profit_loss;//$agent11->profit_loss-$agent22->profit_loss;
                                                $commission3 = round ( ( $amount*$profitLoss3 )/100 , 1 );
                                                $ccr = round ( ( $commission3*$ccrRate )/100 , 1 );
                                                //$agent11->balance = ( $agent11->balance-$commission3 );
                                                
                                                //$amount = ( $amount-$commission3 );
                                                if( $this->updateTempTransactionHistory('DEBIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission3,$ccr,$agent11->profit_loss_balance) ){
                                                    //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission3,$ccr,$agent22->profit_loss_balance)){
                                                    
                                                    $parent = User::findOne( $agent11->parent_id );
                                                    if( $parent->role === 1 ){
                                                        $ccrRate = 1;
                                                        $admin = $parent;//User::findOne( $agent->parent_id );
                                                        $profitLoss = 100-$agent11->profit_loss;
                                                        $commission = round ( ( $amount*$profitLoss )/100 , 1 );
                                                        $ccr = round ( ( $commission*$ccrRate )/100 , 1 );
                                                        //$admin->balance = ( $admin->balance-$commission );
                                                        if( $this->updateTempTransactionHistory('DEBIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->profit_loss_balance) ){
                                                            //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission,$ccr,$agent11->profit_loss_balance) ){
                                                            return true;
                                                        }else{
                                                            return false;
                                                        }
                                                    }else{
                                                        
                                                        //super master to super master
                                                        $agent12 = User::findOne( $agent11->parent_id );
                                                        if( $agent12 != null && $agent12->profit_loss != 0 ){
                                                            $ccrRate = 1;
                                                            $profitLoss4 = 100-$agent11->profit_loss;//$agent12->profit_loss-$agent11->profit_loss;
                                                            $commission4 = round ( ( $amount*$profitLoss4 )/100 , 1 );
                                                            $ccr = round ( ( $commission4*$ccrRate )/100 , 1 );
                                                            //$agent12->balance = ( $agent12->balance-$commission4 );
                                                            //$amount = ( $amount-$commission4 );
                                                            if( $this->updateTempTransactionHistory('DEBIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission4,$ccr,$agent12->profit_loss_balance) ){
                                                                //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission4,$ccr,$agent11->profit_loss_balance) ){
                                                                $admin = User::findOne( $agent12->parent_id );
                                                                $ccrRate = 1;
                                                                $profitLoss = 100-$agent12->profit_loss;
                                                                $commission = round ( ( $amount*$profitLoss )/100 , 1 );
                                                                $ccr = round ( ( $commission*$ccrRate )/100 , 1 );
                                                                //$admin->balance = ( $admin->balance-$commission );
                                                                if( $this->updateTempTransactionHistory('DEBIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->profit_loss_balance) ){
                                                                    //&& $this->updateTempTransactionHistory('CREDIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission,$ccr,$agent12->profit_loss_balance) ){
                                                                    return true;
                                                                }else{
                                                                    return false;
                                                                }
                                                                
                                                            }else{
                                                                return false;
                                                            }
                                                            
                                                        }else{
                                                            return false;
                                                        }
                                                    }
                                                    
                                                }else{
                                                    return false;
                                                }
                                                
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                    
                                }else{
                                    return false;
                                }
                                
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
        }
    }
    
    //transaction Loss
    public function transactionLoss($betID , $eventId)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        $clientId = $model->user_id;
        $amount = $model->loss;
        $ccr = 0;
        $client = User::findOne( $model->user_id );
        $client->balance = ( $client->balance - $amount );
        
        if( $client != null && $client->save(['balance'])
            && $this->updateTempTransactionHistory('DEBIT',$clientId,$client->id,$client->parent_id,$betID,$eventId,'F',$client->username,$amount,$ccr,$client->balance) ){
                
                $parent = User::findOne( $client->parent_id );
                if( $parent->role === 1 ){
                    $admin = $parent;//User::findOne( $client->parent_id );
                    $commission = $amount;
                    //$admin->balance = ( $admin->balance+$commission );
                    if( $this->updateTempTransactionHistory('CREDIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$amount,$ccr,$admin->balance) ){
                        return true;
                    }else{
                        return false;
                    }
                    
                }else{
                    
                    //client to master
                    $agent21 = User::findOne( $client->parent_id );
                    if( $agent21 != null && $agent21->profit_loss != 0 ){
                        $profitLoss1 = $agent21->profit_loss;
                        $commission1 = $amount;
                        //$agent21->balance = ( $agent21->balance+$commission1 );
                        //$amount = ( $amount-$commission1 );
                        if( $this->updateTempTransactionHistory('CREDIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission1,$ccr,$agent21->balance) ){
                            $parent = User::findOne( $agent21->parent_id );
                            if( $parent->role === 1 ){
                                
                                $admin = $parent;//User::findOne( $agent21->parent_id );
                                $profitLoss = 100-$profitLoss1;
                                $commission = round ( ( $amount*$profitLoss )/100 , 1);
                                //$admin->balance = ( $admin->balance+$commission );
                                if( $this->updateTempTransactionHistory('CREDIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->balance) ){
                                    //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission,$ccr,$agent21->balance)){
                                    return true;
                                }else{
                                    return false;
                                }
                                
                            }else{
                                
                                //master to master
                                $agent22 = User::findOne( $agent21->parent_id );
                                if( $agent22 != null && $agent22->profit_loss != 0 ){
                                    $profitLoss2 = 100-$agent21->profit_loss;//$agent22->profit_loss-$agent21->profit_loss;
                                    $commission2 = round ( ( $amount*$profitLoss2 )/100 , 1);
                                    //$agent22->balance = ( $agent22->balance+$commission2 );
                                    //$amount = ( $amount-$commission2 );
                                    
                                    if( $this->updateTempTransactionHistory('CREDIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission2,$ccr,$agent22->balance) ){
                                        //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent21->id,$agent21->parent_id,$betID,$eventId,'E',$agent21->username,$commission2,$ccr,$agent21->balance)){
                                        $parent = User::findOne( $agent22->parent_id );
                                        if( $parent->role === 1 ){
                                            $admin = $parent;//User::findOne( $agent22->parent_id );
                                            $profitLoss = 100-$agent22->profit_loss;
                                            $commission = round ( ( $amount*$profitLoss )/100 , 1);
                                            //$admin->balance = ( $admin->balance+$commission );
                                            
                                            if( $this->updateTempTransactionHistory('CREDIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->balance) ){
                                                //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission,$ccr,$agent22->balance)){
                                                return true;
                                            }else{
                                                return false;
                                            }
                                            
                                        }else{
                                            
                                            //master to super master
                                            $agent11 = User::findOne( $agent22->parent_id );
                                            
                                            if( $agent11 != null && $agent11->profit_loss != 0 ){
                                                
                                                $profitLoss3 = 100-$agent22->profit_loss;//$agent11->profit_loss-$agent22->profit_loss;
                                                
                                                $commission3 = round ( ( $amount*$profitLoss3 )/100 , 1);
                                                
                                                //$agent11->balance = ( $agent11->balance+$commission3 );
                                                //$amount = ( $amount-$commission3 );
                                                if( $this->updateTempTransactionHistory('CREDIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission3,$ccr,$agent11->balance) ){
                                                    //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent22->id,$agent22->parent_id,$betID,$eventId,'D',$agent22->username,$commission3,$ccr,$agent22->balance) ){
                                                    $parent = User::findOne( $agent11->parent_id );
                                                    if( $parent->role === 1 ){
                                                        
                                                        $admin = $parent;//User::findOne( $agent11->parent_id );
                                                        $profitLoss = 100-$agent11->profit_loss;
                                                        $commission = round ( ( $amount*$profitLoss )/100 , 1);
                                                        //$admin->balance = ( $admin->balance+$commission );
                                                        
                                                        if( $this->updateTempTransactionHistory('CREDIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->balance) ){
                                                            //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission,$ccr,$agent11->balance) ){
                                                            return true;
                                                        }else{
                                                            return false;
                                                        }
                                                    }else{
                                                        
                                                        //super master to super master
                                                        $agent12 = User::findOne( $agent11->parent_id );
                                                        if( $agent12 != null && $agent12->profit_loss != 0 ){
                                                            $profitLoss4 = 100-$agent11->profit_loss;//$agent12->profit_loss-$agent11->profit_loss;
                                                            $commission4 = round ( ( $amount*$profitLoss4 )/100 , 1);
                                                            //$agent12->balance = ( $agent12->balance+$commission4 );
                                                            //$amount = ( $amount-$commission4 );
                                                            if( $this->updateTempTransactionHistory('CREDIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission4,$ccr,$agent12->balance) ){
                                                                //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent11->id,$agent11->parent_id,$betID,$eventId,'C',$agent11->username,$commission4,$ccr,$agent11->balance) ){
                                                                $admin = User::findOne( $agent12->parent_id );
                                                                $profitLoss = 100-$agent12->profit_loss;
                                                                $commission = round ( ( $amount*$profitLoss )/100 , 1);
                                                                //$admin->balance = ( $admin->balance+$commission );
                                                                if( $this->updateTempTransactionHistory('CREDIT',$clientId,$admin->id,$admin->parent_id,$betID,$eventId,'A',$admin->username,$commission,$ccr,$admin->balance) ){
                                                                    //&& $this->updateTempTransactionHistory('DEBIT',$clientId,$agent12->id,$agent12->parent_id,$betID,$eventId,'B',$agent12->username,$commission,$ccr,$agent12->balance)){
                                                                    return true;
                                                                }else{
                                                                    return false;
                                                                }
                                                                
                                                            }else{
                                                                return false;
                                                            }
                                                            
                                                        }else{
                                                            return false;
                                                        }
                                                        
                                                    }
                                                    
                                                }else{
                                                    return false;
                                                }
                                                
                                                
                                            }else{
                                                return false;
                                            }
                                            
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                    
                                }else{
                                    return false;
                                }
                                
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
        }
    }
    
    // Update Temp Transaction History
    public function updateTempTransactionHistory($type,$clientId,$uId,$parentId,$betId,$eventId,$parentType,$uName,$amount,$ccr,$balance)
    {
        $trans = new TempTransactionHistory();
        
        if( $type == 'CREDIT' ){
            $trans->client_id = $clientId;
            $trans->user_id = $uId;
            $trans->parent_id = $parentId;
            $trans->bet_id = $betId;
            $trans->event_id = $eventId;
            $trans->parent_type = $parentType;
            $trans->username = $uName;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $amount;
            $trans->commission = $ccr;
            $trans->current_balance = $balance;
            $trans->description = $this->getDescription($betId,$eventId);
            $trans->status = 1;
            $trans->updated_at = $trans->created_at = time();
        }else{
            $trans->client_id = $clientId;
            $trans->user_id = $uId;
            $trans->parent_id = $parentId;
            $trans->bet_id = $betId;
            $trans->event_id = $eventId;
            $trans->parent_type = $parentType;
            $trans->username = $uName;
            $trans->transaction_type = 'DEBIT';
            $trans->transaction_amount = $amount;
            $trans->commission = $ccr;
            $trans->current_balance = $balance;
            $trans->description = $this->getDescription($betId,$eventId);
            $trans->status = 1;
            $trans->updated_at = $trans->created_at = time();
        }
        
        if( $trans->save() ){
            return true;
        }else{
            return false;
        }
        
    }
    
    // Function to get the Tras Description
    public function getDescription($betId,$eventId)
    {
        $type = $size = 'NoData';
        
        $betData = PlaceBet::find()->select(['bet_type','size','rate','description'])
        ->where([ 'id' => $betId,'event_id' => $eventId,'status'=>1, 'bet_status' => ['Win','Loss'] ])->one();
        
        if( $betData != null ){
            $type = $betData->bet_type;
            $size = $betData->size;
            $rate = $betData->rate;
            $description = $betData->description;
        }
        
        $description = $description.' | '.$rate.' | '.$type.' | '.$size;
        
        return $description;
    }
    
    //update Transaction History
    public function updateTransactionHistory( $type , $uId , $betId ,$uName, $amount , $balance)
    {
        $trans = new TransactionHistory();
        
        if( $type == 'CREDIT' ){
            $trans->user_id = $uId;
            $trans->bet_id = $betId;
            $trans->client_name = $uName;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $amount;
            $trans->current_balance = $balance;
            $trans->status = 1;
            $trans->updated_at = time();
        }else{
            $trans->user_id = $uId;
            $trans->bet_id = $betId;
            $trans->client_name = $uName;
            $trans->transaction_type = 'DEBIT';
            $trans->transaction_amount = $amount;
            $trans->current_balance = $balance;
            $trans->status = 1;
            $trans->updated_at = time();
        }
        
        if( $trans->save() ){
            return true;
        }else{
            return false;
        }
        
        
    }
    
    //action Eventlist Status
    public function actionEventlistStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = EventsPlayList::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status == 1 ){
                        $event->status = 2;
                    }else{
                        $event->status = 1;
                    }
                    
                    if( $event->save( [ 'status' ] ) ){
                        
                        $sts = $event->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Event play $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "event status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //action Commentary
    public function actionCommentary(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>7 , 'event_id'=>$id]);
            
            if( $commentary != null ){
                $commentary->title = $r_data[ 'title' ];
                $commentary->updated_at = time();
                
                $attr = [ 'title','updated_at'];
                
                if( $commentary->save( $attr ) ){
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Commentary updated successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Commentary not updated!"
                    ];
                }
            }else{
                
                $commentary = new GlobalCommentary();
                
                $commentary->title = $r_data[ 'title' ];
                $commentary->sport_id = 7;
                $commentary->event_id = $id;
                $commentary->status = 1;
                $commentary->created_at = $commentary->updated_at = time();
                
                if( $commentary->save() ){
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Commentary updated successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Commentary not updated!"
                    ];
                }
                
            }
        }
        
        return $response;
    }
    
    //action Statusmarket
    public function actionStatusmarket(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = MarketType::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status == 1 ){
                        $event->status = 2;
                    }else{
                        $event->status = 1;
                    }
                    
                    if( $event->save( [ 'status' ] ) ){
                        
                        $sts = $event->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "market $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "market status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
}
