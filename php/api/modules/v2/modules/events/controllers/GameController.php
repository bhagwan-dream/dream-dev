<?php
namespace api\modules\v2\modules\events\controllers;

use yii\helpers\ArrayHelper;
use common\models\TransactionHistory;
use yii\helpers\Url;
use common\models\User;
use common\models\Setting;
use common\models\Game;
use common\models\GamePlaceBet;
use common\models\GameMatkaSchedule;

class GameController extends \common\controllers\aController
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
    
    /*
     * List of all games 
     */
    
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
        
        $query = Game::find()
        ->select( [ 'id' , 'name' , 'slug' , 'img' , 'status' ] )
        ->andWhere( [ 'status' => 1 ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "name" , $filters[ "title" ] ] );
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
    
    //Running Game
    public function actionRunningGame()
    {
        $response = [ "status" => 1 , "counter" => 0 , "message" => "There is no found any game" ];
        
        $model = GameMatkaSchedule::find()->select(['id','end_game'])
        ->where(['<=','start_game',strtotime(date("Y-m-d H:i:s"))])
        ->andWhere(['>=','end_game',strtotime(date("Y-m-d H:i:s"))])
        ->asArray()->one();
        if( $model != null ){
            
            $diff = $model['end_game']-strtotime(date("Y-m-d H:i:s"));
            
            $timer = round($diff / 60*60,2);
            $msg = 'Game Start In: '.$timer.' sec';
            $response = [ "status" => 1 , "data" => $model ,"counter" => $timer , "message" => $msg ];
            
        }else{
            
            $model = GameMatkaSchedule::find()->select(['id','result_end'])
            ->where(['<=','result_start',strtotime(date("Y-m-d H:i:s"))])
            ->andWhere(['>=','result_end',strtotime(date("Y-m-d H:i:s"))])
            ->asArray()->one();
            if( $model != null ){
                
                $diff = $model['result_end']-strtotime(date("Y-m-d H:i:s"));
                $timer = round($diff / 60*60,2);
                
                $msg = 'Game Result In: '.$timer.' sec';
                $response = [ "status" => 1 , "data" => $model ,"counter" => $timer , "message" => $msg ];
                
            }
            
        }
        return $response;
        
    }
    
    public function actionList()
    {
        $response = [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = $_POST;//json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //if( json_last_error() == JSON_ERROR_NONE ){
            //$r_data = ArrayHelper::toArray( $request_data );
        if( $request_data != null && $request_data['name'] != '' ){
            $model = Game::find()
            ->select( [ 'id' , 'name' ] )
            ->where( [ 'slug' => $request_data['name'],'status' => 1 ] )->one();
            
            if( $model != null ){
                $response = [ "status" => 1 , "data" => [ "items" => $model ] ];
            }else{
                $response = [ "status" => 1 , "data" => [ "items" => [] ] ];
            }
            
            
        }
        return $response;
    }
    
    /*
     * Place Bet in Game
     */
    
    public function actionPlaceBet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = $_POST;//json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //if( json_last_error() == JSON_ERROR_NONE ){
            //$r_data = ArrayHelper::toArray( $request_data );
            
        if( $request_data != null && isset($request_data['game_id']) 
            && isset($request_data['price']) && isset($request_data['bet_number']) ){
            
            $r_data = $request_data;
                
            $model = new GamePlaceBet();
                
            $uid = \Yii::$app->user->id;
            
            if( $this->currentBalance(false,$uid) < ( $r_data['price']+1)  ){
                $response[ "error" ] = [
                    "message" => "Insufficient funds!"
                ];
                return $response;
            }
            $model->game_id = $r_data['game_id'];
            $model->price = $r_data['price'];
            $model->bet_number = $r_data['bet_number'];
            $model->win = $r_data['price'];
            $model->win_number = "undefined";
            $model->loss = $r_data['price'];
            
            $model->bet_status = 'Pending';
            $model->user_id = $uid;
            $model->current_balance = $this->currentBalance($r_data['price'],$uid);
            $model->status = 1;
            $model->created_at = $model->updated_at = time();
            $model->ip_address = $this->get_client_ip();
                    
            if( $model->save() ){
                
                $response = [
                    'status' => 1 ,
                    "success" => [
                        "message" => "Place bet successfully!"
                    ]
                ];
                
            }else{
                $response[ "error" ] = [
                    "message" => "Somthing wrong!"
                ];
            }
            
        }
        
        return $response;
    }
    
    
    /*
     * Result in Game
     */
    
    public function actionResult(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = $_POST;//json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //if( json_last_error() == JSON_ERROR_NONE ){
            //$r_data = ArrayHelper::toArray( $request_data );
            
        if( $request_data != null && isset($request_data['game_id']) && isset($request_data['result']) 
            && isset($request_data['bet_price']) && isset($request_data['win_number']) ){
        
            $r_data = $request_data;
                
            $uid = \Yii::$app->user->id;
            
            $model = GamePlaceBet::find()
            ->where(['game_id' => $r_data['game_id'] , 'user_id' => $uid , 'bet_status' => 'Pending' , 'status' => 1 ])
            ->orderBy( [ 'id' => SORT_DESC ] )->one();
            //print_r($model);die;
            if( $model != null ){
                
                $model->bet_status = $r_data['result'];
                $model->win_number = $r_data['win_number'];
                $model->win = $this->getProfitAmount($request_data['bet_price']);
                $model->current_balance = $model->current_balance+$model->win;
                if( $model->save() ){
                    $user = User::findOne( $uid );
                    if( $user != null ){
                        
                        if( $model->bet_status == 'Win' ){
                            
                            $user->balance = ( $user->balance + $model->win );
                            if( $user->save(['balance']) ){
                                $response = [ "status" => 1 , "data" => [ "balance" => $user->balance ] ];
                            }else{
                                $response = [ "status" => 1 , "data" => [ "balance" => $user->balance ] ];
                            }
                        }else{
                            $response = [ "status" => 1 , "data" => [ "balance" => $user->balance ] ];
                        }
                    }else{
                        $response = [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Somthing wrong! User not found!" ] ];
                    }
                    
                }else{
                    $response = [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Somthing wrong! Data not updated!" ] ];
                }
                
            }
            
        }
        
        return $response;
    }
    
    /*
     * Get Current Balance
     */
    
    public function actionGetCurrentBalance(){
        
        $user = User::find()->select(['balance'])->where(['id' => \Yii::$app->user->id ])->one();
        
        if( $user != null ){
            $user_balance = $user->balance;
            return [ "status" => 1 , "data" => [ "balance" => $user_balance ] ];
        }
        return [ "status" => 1 , "data" => [ "balance" => 0 ] ];
        
    }
    
    /*
     * List of all Game Bet History
     */
    
    public function actionHistory()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = $_POST;//json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        //if( json_last_error() == JSON_ERROR_NONE ){
            //$r_data = ArrayHelper::toArray( $request_data );
        if( $request_data != null && isset($request_data['game_id']) ){
            $r_data = $request_data;
            $uid = \Yii::$app->user->id;
            
            $query = GamePlaceBet::find()
            ->where( [ 'status' => [1,2] , 'user_id' => $uid , 'game_id' => $r_data['game_id']  ] );
            
            $countQuery = clone $query; $count =  $countQuery->count();
            
            $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
            
            if( $models != null ){
                $response = [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
            }else{
                $response = [ "status" => 1 , "data" => [ "items" => [] , "count" => 0 ] ];
            }
            
            return $response;
        }
    }
    
    /*
     * Function to get the return profit amount val
     */
    public function getProfitAmount($winAmount)
    {
        $makta_profit_amount = 1;
        $setting = Setting::findOne([ 'key' => 'GAME_MATKA_PROFIT_AMOUNT' , 'status' => 1 ]);
        if( $setting != null ){
            $makta_profit_amount = $setting->value;
        }
        
        return $winAmount*$makta_profit_amount;
    }
    
   /*
    * Function to get the client current Balance
    */
    
    public function currentBalance($price = false , $uid){
        $user = User::findOne( $uid );
        
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
    
    /*
     * Function to get the client IP address
     */
    
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