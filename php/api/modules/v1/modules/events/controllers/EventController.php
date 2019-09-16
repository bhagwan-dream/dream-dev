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
use yii\helpers\Inflector;
use common\models\EventsRunner;
use common\models\EventMarketStatus;

class EventController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    //action List
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
            ->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'market_count', 'created_at' , 'updated_at' , 'status' ] )
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
        
        $models = $query->orderBy( [ "status" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
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
    
    // Cricket: get Profit Loss On Bet
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
            }else{ $backWin = $backWin[0]['val']; }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $layWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            //echo '<pre>';print_r($layWin[0]['val']);die;
            
            if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin[0]['val']; }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layDrwWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layDrwWin[0]['val']);die;
            
            if( $layDrwWin == null || !isset($layDrwWin[0]['val']) || $layDrwWin[0]['val'] == '' ){
                $layDrwWin = 0;
            }else{ $layDrwWin = $layDrwWin[0]['val']; }
            
            $totalWin = $backWin + $layWin + $layDrwWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'runner' , $runner ];
            
            $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            ->where($where)->andWhere($andWhere)->asArray()->all();
            
            //echo '<pre>';print_r($backLoss[0]['val']);die;
            
            if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss[0]['val']; }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
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
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
            
            $backWin = PlaceBet::find()->select(['SUM(win) as val'])
            ->where($where)->asArray()->all();
            
            //echo '<pre>';print_r($backWin[0]['val']);die;
            
            if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin[0]['val']; }
            
            $totalWin = $backWin;
            
            // IF RUNNER LOSS
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
            
            $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)
            ->asArray()->all();
            
            //echo '<pre>';print_r($layLoss[0]['val']);die;
            
            if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss[0]['val']; }
            
            $where = [ 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];
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
    
    //actionIndex
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
            ->select( [ 'id' , 'event_type_id' ,'img','max_profit_all_limit','bet_delay', 'event_type_name' , 'market_count', 'created_at' , 'updated_at' , 'status' ] )
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
        
        $models = $query->orderBy( [ "status" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    public function actionCreate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $model = new Event();
            
            $model->event_type_name = $r_data[ 'event_type_name' ];
            $model->event_type_id   = $r_data[ 'event_type_id' ];
	        $model->market_count    = $r_data[ 'market_count' ];
	        $model->event_slug = Inflector::slug($r_data[ 'event_type_name' ]);
            $model->status          = 1;
            
            if( $model->validate() ){
                if( $model->save() ){
                    $response = [ 
                        'status' => 1 , 
                        "success" => [ 
                            "message" => "new event created successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [ 
                        "message" => "event not saved!" ,
                        "data" => $model->errors
                    ];
                }
            }else{
                $response[ "error" ] = [
                    "message" => "event not saved!" ,
                    "data" => $model->errors
                ];
            }
        }
        
        return $response;
    }
    
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = Event::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $event->status = 0;
                    
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "event deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "event not deleted!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    public function actionUpdate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $event = Event::findOne( $id );
            
            if( $event != null ){
                $event->event_type_id = $r_data[ 'event_type_id' ];
                $event->event_type_name = $r_data[ 'event_type_name' ];
                $event->event_slug = Inflector::slug($r_data[ 'event_type_name' ]);
                $event->market_count = $r_data[ 'market_count' ];
                $event->updated_at = time();
                
                $attr = [ 'event_type_id' , 'event_type_name' , 'market_count' , 'updated_at' ];
                
                if( $event->validate( $attr ) ){
                    if( $event->save( $attr ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "event saved successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "event not updated!" ,
                            "data" => $event->errors
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "event not updated!" ,
                        "data" => $event->errors
                    ];
                }
            }
        }
        
        return $response;
    }
    
    public function actionCommentaryList(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        $commentry = [
            '2' => 'TENNIS_COMMENTARY',
            '4' => 'CRICKET_COMMENTARY',
            '7' => 'HORSE_RACING_COMMENTARY',
            '6423' => 'FOOTBALL_COMMENTARY',
        ];
        if( $id != null ){
           
            $model = Setting::find()->select( [ 'value'] )->where( [ 'key' => $commentry[$id] ] )->asArray()->one();
            if( $model != null ){
                
                $commentryList = explode(',', $model['value']);
                
                if( $commentryList != null ){
                    $response = [ "status" => 1 , "data" => $commentryList ];
                }else{
                    $response = [ "status" => 1 , "data" => [] ];
                }
                
                
            }
        }
        
        return $response;
    }
    
    public function actionCommentary(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>$id , 'event_id'=>0]);
            
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
                $commentary->sport_id = $id;
                $commentary->event_id = 0;
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
    
    public function actionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = Event::findOne( $r_data[ 'id' ] );
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
                                "message" => "event $sts successfully!"
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
    
    public function actionBlockUnblock(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $where = [ 'user_id' => $r_data[ 'user_id' ],'sport_id' => $r_data[ 'sport_id' ],'event_id' => $r_data[ 'event_id' ],'market_id' => $r_data[ 'market_id' ] ];
                $event = EventMarketStatus::findOne( $where );
                
                if( $event != null ){
                    
                    if( $event->delete() ){
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Market Block successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Market not changed!"
                        ];
                    }
                    
                }else{
                    
                    $event = new EventMarketStatus();
                    
                    $event->user_id = $r_data['user_id'];
                    $event->sport_id = $r_data['sport_id'];
                    $event->event_id = $r_data['event_id'];
                    $event->market_id = $r_data['market_id'];
                    $event->status = 1;
                    
                    if( $event->save() ){
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Market Un Block successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Market not changed!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    public function actionSettingView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        if( $id != null ){
            $model = Event::find()->select( [ 'event_type_name','max_profit_all_limit','bet_delay' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    public function actionSetting(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $id ) ){
                
                $event = Event::findOne( ['id' => $id ] );
                
                if( $event != null ){
                    
                    $event->max_profit_all_limit          = $r_data[ 'max_profit_all_limit' ];
                    $event->bet_delay          = $r_data[ 'bet_delay' ];
                    
                    if( $event->save( [ 'max_profit_all_limit' , 'bet_delay' ] ) ){
                        
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
    
    public function actionGameover(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $id ) && isset( $r_data[ 'win_result' ] ) ){
                
                $event = EventsPlayList::findOne( ['event_id' => $id ] );
                
                if( $event != null ){
                    
                    $manualSession = ManualSession::findOne(['event_id' => $id , 'game_over' => 'NO' , 'status' => '1' ]);
                    
                    if( $manualSession != null ){
                        $response[ "error" ] = [
                            "message" => "Still Manual Session Game Over is Pending!" ,
                            "data" => $event->errors
                        ];
                        
                        return $response;
                    }
                    
                    $event->game_over = 'YES';
                    $event->win_result = $r_data[ 'win_result' ];
                    
                    if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                        
                        $this->gameoverResult( $event->event_id , $event->win_result );
                        
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
                    
                }else{
                    
                    $manualSession = ManualSession::findOne(['id' => $id , 'game_over' => 'NO' , 'status' => '1' ]);
                    
                    if( $manualSession != null ){
                        
                        $manualSession->game_over = 'YES';
                        $manualSession->win_result = $r_data[ 'win_result' ];
                        
                        $this->gameoverResultManualSession( $manualSession->id , $manualSession->event_id , $manualSession->win_result );
                        
                        if( $manualSession->save( [ 'game_over' , 'win_result' ] ) ){
                            
                            $this->gameoverResultManualSession( $manualSession->id , $manualSession->event_id , $manualSession->win_result );
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Manual Session game over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! Manual Session not updated!"
                            ];
                        }
                        
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not found!"
                        ];
                    }
                    
                }
            }
        }
        return $response;
    }
    
    
    public function gameoverResultManualSession( $msId , $eventId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $yesWinList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( [ 'market_id' => $msId, 'event_id' => $eventId ] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'yes' , 'status' => 1] )
                ->andWhere( [ '<=' , 'price' , $winResult ] )
                ->asArray()->all();
            
            //echo '<pre>';print_r($yesWinList);die;
            
            $noWinList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( [ 'market_id' => $msId, 'event_id' => $eventId ] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'no' , 'status' => 1] )
                ->andWhere( [ '<' , 'price' , $winResult ] )
                ->asArray()->all();
            
            //echo '<pre>';print_r($noWinList);die;
            
            if( $yesWinList != null ){
                foreach( $yesWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'market_id' => $msId, 'event_id' => $list['event_id'] ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id);
                        }
                    }
                }
            }
            
            if( $noWinList != null ){
                
                foreach( $noWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'market_id' => $msId, 'event_id' => $list['event_id'] ]);
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id);
                        }
                    }
                }
                
            }
            
            /*User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( [ 'market_id' => $msId, 'event_id' => $eventId ] )
                ->andWhere( [ '!=', 'bet_status' , 'Win'] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['yes' , 'no'] , 'status' => 1] )
                ->asArray()->all();
            
            //echo '<pre>';print_r($lossList);die;
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'market_id' => $msId, 'event_id' => $list['event_id'] ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    public function gameoverResult( $eventId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( ['event_id' => $eventId , 'runner' => $winResult ] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1] )
                ->asArray()->all();
            //echo '<pre>';print_r($backWinList);
            $layWinList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( ['event_id' => $eventId ] )
                ->andwhere( [ '!=' , 'runner' , $winResult ] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1] )
                ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if( $backWinList != null ){
                foreach( $backWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id);
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
                            $this->transectionWin($win->id);
                        }
                    }
                }
                
            }
            
            /*User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
                ->where( ['event_id' => $eventId ] )
                ->where( [ '!=', 'bet_status' , 'Win'] )
                ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1] )
                ->asArray()->all();
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    
    public function transectionWin($betID)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = $model->win - $model->ccr;
        
        $client = User::findOne( $model->user_id );
        
        $client->balance = ( ( $client->balance + $model->loss ) + $amount );
        
        if( $client != null && $client->save(['balance']) 
            && $this->updateTransactionHistory( 'CREDIT',$client->id, $betID,$client->username, ($amount+$model->loss), $client->balance) ){
            
            /*$bet = $model;
            
            $trans = new TransactionHistory();
            
            $trans->user_id = $bet->user_id;
            $trans->bet_id = $bet->id;
            $trans->client_name = $bet->client_name;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $model->win - $model->ccr;
            $trans->current_balance = $client->balance;
            $trans->status = 1;
            $trans->updated_at = time();*/
            
            if( $client->parent_id === 1 ){
                
                //if client parent admin
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance - $commission );
                
                if( $admin->save( ['balance'] ) ){
                    
                    if( $this->updateTransactionHistory( 'DEBIT',$admin->id, $betID, $admin->username , $commission, $admin->balance) ){
                        return true;
                    }else{
                        return false;
                    }
                    
                }else{
                    return false;
                }
                
            }else{
                
                //client to master
                
                $agent21 = User::findOne( $client->parent_id );
                
                if( $agent21 != null && $agent21->profit_loss != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent21->profit_loss )/100 );
                    
                    $agent21->balance = ( $agent21->balance-$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent21->save(['balance']) 
                        && $this->updateTransactionHistory( 'DEBIT', $agent21->id, $betID, $agent21->username, $commission1, $agent21->balance) ){
                        
                        if( $agent21->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent21->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance-$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                if( $this->updateTransactionHistory('DEBIT', $admin->id,$betID, $admin->username, $commission, $admin->balance) ){
                                    return true;
                                }else{
                                    return false;
                                }
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            //master to master
                            
                            $agent22 = User::findOne( $agent21->parent_id );
                            
                            if( $agent22 != null && $agent22->profit_loss != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent22->profit_loss )/100 );
                                
                                $agent22->balance = ( $agent22->balance-$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent22->save(['balance']) 
                                    && $this->updateTransactionHistory( 'DEBIT',$agent22->id, $betID,$agent22->username, $commission2, $agent22->balance) ){
                                    
                                   if( $agent22->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent22->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance-$commission );
                                        
                                        if( $admin->save( ['balance'] ) 
                                            && $this->updateTransactionHistory( 'DEBIT', $admin->id, $betID, $admin->username, $commission, $admin->balance) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        //master to super master
                                        
                                        $agent11 = User::findOne( $agent22->parent_id );
                                        
                                        if( $agent11 != null && $agent11->profit_loss != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent11->profit_loss )/100 );
                                            
                                            $agent11->balance = ( $agent11->balance-$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent11->save(['balance']) 
                                                && $this->updateTransactionHistory( 'DEBIT', $agent11->id, $betID,$agent11->username, $commission3, $agent11->balance) ){
                                                
                                                    if( $agent11->parent_id === 1 ){
                                                    
                                                        $admin = User::findOne( $agent->parent_id );
                                                        
                                                        $commission = $amount;
                                                        
                                                        $admin->balance = ( $admin->balance-$commission );
                                                        
                                                        if( $admin->save( ['balance'] ) ){
                                                            if( $this->updateTransactionHistory( 'DEBIT', $admin->id, $betID,$admin->username, $commission, $admin->balance) ){
                                                                return true;
                                                            }else{
                                                                return false;
                                                            }
                                                        }else{
                                                            return false;
                                                        }
                                                    }else{
                                                        
                                                        //super master to super master
                                                        
                                                        $agent12 = User::findOne( $agent11->parent_id );
                                                        
                                                        if( $agent12 != null && $agent12->profit_loss != 0 ){
                                                            
                                                            $commission4 = round ( ( $amount*$agent12->profit_loss )/100 );
                                                            
                                                            $agent12->balance = ( $agent12->balance-$commission4 );
                                                            
                                                            $amount = ( $amount-$commission4 );
                                                            
                                                            if( $agent12->save(['balance'])
                                                                && $this->updateTransactionHistory( 'DEBIT', $agent12->id, $betID,$agent12->username, $commission4, $agent12->balance) ){
                                                                
                                                                    $admin = User::findOne( $agent12->parent_id );
                                                                    
                                                                    $commission = $amount;
                                                                    
                                                                    $admin->balance = ( $admin->balance-$commission );
                                                                    
                                                                    if( $admin->save( ['balance'] ) ){
                                                                        if( $this->updateTransactionHistory( 'DEBIT', $admin->id, $betID,$admin->username, $commission, $admin->balance) ){
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
    
    public function transactionLoss($betID)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = $model->loss;
        
        $client = User::findOne( $model->user_id );
        
        if( $client != null ){
            
            if( $client->parent_id === 1 ){
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance+$commission );
                
                if( $admin->save( ['balance'] ) 
                    && $this->updateTransactionHistory( 'CREDIT', $admin->id, $betID, $admin->username, $commission, $admin->balance) ){
                    return true;
                }else{
                    return false;
                }
                
            }else{
                
                //client to master
                
                $agent21 = User::findOne( $client->parent_id );
                
                if( $agent21 != null && $agent21->profit_loss != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent21->profit_loss )/100 );
                    
                    $agent21->balance = ( $agent21->balance+$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent21->save(['balance']) 
                        && $this->updateTransactionHistory( 'CREDIT', $agent21->id, $betID, $agent21->username, $commission1, $agent21->balance) ){
                        
                        if( $agent21->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent21->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance+$commission );
                            
                            if( $admin->save( ['balance'] ) 
                                && $this->updateTransactionHistory( 'CREDIT', $admin->id, $betID,$admin->username, $commission, $admin->balance)  ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            //master to master
                            
                            $agent22 = User::findOne( $agent21->parent_id );
                            
                            if( $agent22 != null && $agent22->profit_loss != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent22->profit_loss )/100 );
                                
                                $agent22->balance = ( $agent22->balance+$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent22->save(['balance']) 
                                    && $this->updateTransactionHistory( 'CREDIT', $agent22->id, $betID, $agent22->username, $commission2, $agent22->balance) ){
                                    
                                    if( $agent22->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent22->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance+$commission );
                                        
                                        if( $admin->save( ['balance'] ) 
                                            && $this->updateTransactionHistory( 'CREDIT',$admin->id, $betID,$admin->username, $commission, $admin->balance) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        //master to super master
                                        
                                        $agent11 = User::findOne( $agent22->parent_id );
                                        
                                        if( $agent11 != null && $agent11->profit_loss != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent11->profit_loss )/100 );
                                            
                                            $agent11->balance = ( $agent11->balance+$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent11->save(['balance']) 
                                                && $this->updateTransactionHistory( 'CREDIT', $agent11->id, $betID, $agent11->username, $commission3, $agent11->balance) ){
                                                
                                                if( $agent11->parent_id === 1 ){
                                                    
                                                    $admin = User::findOne( $agent11->parent_id );
                                                    
                                                    $commission = $amount;
                                                    
                                                    $admin->balance = ( $admin->balance+$commission );
                                                    
                                                    if( $admin->save( ['balance'] ) 
                                                        && $this->updateTransactionHistory( 'CREDIT',$admin->id, $betID,$admin->username, $commission, $admin->balance) ){
                                                        return true;
                                                    }else{
                                                        return false;
                                                    }
                                                }else{
                                                    
                                                    //super master to super master
                                                    
                                                    $agent12 = User::findOne( $agent11->parent_id );
                                                    
                                                    if( $agent12 != null && $agent12->profit_loss != 0 ){
                                                        
                                                        $commission4 = round ( ( $amount*$agent12->profit_loss )/100 );
                                                        
                                                        $agent12->balance = ( $agent12->balance+$commission4 );
                                                        
                                                        $amount = ( $amount-$commission4 );
                                                        
                                                        if( $agent12->save(['balance'])
                                                            && $this->updateTransactionHistory( 'CREDIT', $agent12->id, $betID, $agent12->username, $commission4, $agent12->balance) ){
                                                            
                                                                $admin = User::findOne( $agent12->parent_id );
                                                                
                                                                $commission = $amount;
                                                                
                                                                $admin->balance = ( $admin->balance+$commission );
                                                                
                                                                if( $admin->save( ['balance'] )
                                                                    && $this->updateTransactionHistory( 'CREDIT',$admin->id, $betID,$admin->username, $commission, $admin->balance) ){
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
    
    public function actionView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        if( $id != null ){
            $model = Event::find()->select( [ 'id' , 'event_type_id' , 'event_type_name' , 'market_count' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    public function actionMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $r_data = ArrayHelper::toArray( $request_data );
        //echo '<pre>';print_r($r_data);die;
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            
            $pagination = $filter_args;
        }
        
        if( json_last_error() == JSON_ERROR_NONE ){
            
            if( isset( $r_data[ 'id' ] ) ){
                $eId = $r_data[ 'id' ];
                
                $query = MarketType::find()
                ->select( [ 'id' , 'event_type_id' , 'market_type' , 'market_name', 'created_at' , 'updated_at' , 'status' ] )
                ->andWhere( [ 'status' => [1,2] , 'event_id' => $eId ] );
            
                if( $filters != null ){
                    if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                        $query->andFilterWhere( [ "like" , "market_name" , $filters[ "title" ] ] );
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
            
                //echo '<pre>';print_r($models);die;
                $response = [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
            }
        }
        return $response;
           
    }
    
    public function actionRefreshEventListOLDBETSAPI()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            if( $r_data['id'] == 4 ){
                //CODE for live call api
                $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token=15815-peDeUY8w5a9rPq';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $eventList = curl_exec($ch);
                curl_close($ch);
                $responseData = json_decode($eventList);
                //echo '<pre>';print_r($responseData);die;
                if( isset( $responseData->results ) && !empty($responseData->results) ){
                    
                    foreach( $responseData->results as $result ){
                        
                        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token=15815-peDeUY8w5a9rPq&event_id='.$result->id;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $responseData2 = curl_exec($ch);
                        curl_close($ch);
                        
                        $responseData2 = json_decode($responseData2);
                        //echo '<pre>';print_r($responseData2);die;
                        
                        if( isset( $responseData2->results ) && !empty($responseData2->results) ){
                            
                            $results = $responseData2->results[0];
                            
                            $marketId = $results->markets[0]->marketId;
                            $eventId = $result->id;
                            if( isset( $result->league->name ) ){
                                $eventLeague = $result->league->name;
                            }else{
                                $eventLeague = $results->event->name;
                            }
                            $eventName = $results->event->name;
                            $eventTime = strtotime($results->event->openDate);
                            
                            $check = EventsPlayList::findOne(['sport_id' => $r_data['id'],'event_id' => $eventId , 'market_id' => $marketId ]);
                            if( $check == null ){
                                $model = new EventsPlayList();
                                $model->sport_id = $r_data['id'];
                                $model->event_id = $eventId;
                                $model->market_id = $marketId;
                                $model->event_league = $eventLeague;
                                $model->event_name = $eventName;
                                $model->event_time = $eventTime;
                                $model->play_type = 'IN_PLAY';
                                $model->save();
                            }else{
                                $check->play_type = 'IN_PLAY';
                                $check->save();
                            }  
                        }
                    }
                }
            }
            
            if( $r_data['id'] == 2 ){
                //CODE for live call api
                $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=2&token=15815-peDeUY8w5a9rPq';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $eventList = curl_exec($ch);
                curl_close($ch);
                $responseData = json_decode($eventList);
                //echo '<pre>';print_r($responseData);die;
                if( isset( $responseData->results ) && !empty($responseData->results) ){
                    
                    foreach( $responseData->results as $result ){
                        
                        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token=15815-peDeUY8w5a9rPq&event_id='.$result->id;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $responseData2 = curl_exec($ch);
                        curl_close($ch);
                        
                        $responseData2 = json_decode($responseData2);
                        //echo '<pre>';print_r($responseData2);die;
                        
                        if( isset( $responseData2->results ) && !empty($responseData2->results) ){
                            
                            $results = $responseData2->results[0];
                            
                            $marketId = $results->markets[0]->marketId;
                            $eventId = $result->id;
                            if( isset( $result->league->name ) ){
                                $eventLeague = $result->league->name;
                            }else{
                                $eventLeague = $results->event->name;
                            }
                            $eventName = $results->event->name;
                            $eventTime = strtotime($results->event->openDate);
                            
                            $check = EventsPlayList::findOne(['sport_id' => $r_data['id'],'event_id' => $eventId , 'market_id' => $marketId ]);
                            if( $check == null ){
                                $model = new EventsPlayList();
                                $model->sport_id = $r_data['id'];
                                $model->event_id = $eventId;
                                $model->market_id = $marketId;
                                $model->event_league = $eventLeague;
                                $model->event_name = $eventName;
                                $model->event_time = $eventTime;
                                $model->play_type = 'IN_PLAY';
                                $model->save();
                            }else{
                                $check->play_type = 'IN_PLAY';
                                $check->save();
                            }
                        }
                    }
                }
            }
            
            $response = [
                'status' => 1 ,
                "success" => [
                    "message" => "Data refresh successfully!"
                ]
            ];
            
        }
        return $response;
    }
    
    public function actionRefreshEventList()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //CODE for live call api
            $url = $this->apiUrl.'?event_id='.$r_data['id'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseData = curl_exec($ch);
            curl_close($ch);
            $responseData = json_decode($responseData);
            //echo '<pre>';print_r($responseData);die;
            if( isset( $responseData->result ) && !empty($responseData->result) ){
                
                foreach( $responseData->result as $result ){
                    
                    $today = date('Ymd');
                    $tomorrow = date('Ymd' , strtotime($today . ' +1 day') );
                    $eventDate = date('Ymd',( $result->start/1000 ));
                    if( $today == $eventDate || $tomorrow == $eventDate ){
                        
                        $marketId = $result->id;
                        $eventId = $result->event->id;
                        $eventLeague = isset( $result->competition->name ) ? $result->competition->name : 'No Data' ;
                        $eventName = $result->event->name;
                        $eventTime = $result->start;
                        
                        $check = EventsPlayList::findOne(['sport_id' => $r_data['id'],'event_id' => $eventId , 'market_id' => $marketId ]);
                        
                        if( $check != null ){
                            
                            if( $result->inPlay == 1 || $result->inPlay == true || $result->inPlay == 'true' ){
                                $check->play_type = 'IN_PLAY';
                            }else{
                                $check->play_type = 'UPCOMING';
                            }
                            
                            if( $check->event_time != $result->start ){
                                $check->event_time = $result->start;
                            }
                            
                            if( $check->save() ){
                                $runnerModelCheck = EventsRunner::findOne(['market_id'=>$marketId]);
                                if( $runnerModelCheck == null ){
                                    if( isset( $result->runners ) ){
                                        foreach( $result->runners as $runners ){
                                            $selId = $runners->id;
                                            $runnerName = $runners->name;
                                            $runnerModel = new EventsRunner();
                                            $runnerModel->event_id = $eventId;
                                            $runnerModel->market_id = $marketId;
                                            $runnerModel->selection_id = $selId;
                                            $runnerModel->runner = $runnerName;
                                            $runnerModel->save();
                                        }
                                    }
                                }
                            }
                            
                            
                        }else{
                            $model = new EventsPlayList();
                            $model->sport_id = $r_data['id'];
                            $model->event_id = $eventId;
                            $model->market_id = $marketId;
                            $model->event_league = $eventLeague;
                            $model->event_name = $eventName;
                            $model->event_time = $eventTime;
                            
                            if( $result->inPlay == 1 || $result->inPlay == true || $result->inPlay == 'true' ){
                                $model->play_type = 'IN_PLAY';
                            }else{
                                $model->play_type = 'UPCOMING';
                            }
                            if( $model->save() ){
                                
                                $runnerModelCheck = EventsRunner::findOne(['market_id'=>$marketId]);
                                if( $runnerModelCheck == null ){
                                    if( isset( $result->runners ) ){
                                        foreach( $result->runners as $runners ){
                                            $selId = $runners->id;
                                            $runnerName = $runners->name;
                                            $runnerModel = new EventsRunner();
                                            $runnerModel->event_id = $eventId;
                                            $runnerModel->market_id = $marketId;
                                            $runnerModel->selection_id = $selId;
                                            $runnerModel->runner = $runnerName;
                                            $runnerModel->save();
                                        }
                                    }
                                }
                                
                            }
                            
                            $AllUser = [];
                            $uId = \Yii::$app->user->id;
                            $role = \Yii::$app->authManager->getRolesByUser($uId);
                            if( isset($role['admin']) && $role['admin'] != null ){
                                $AllUser = $this->getAllUserForAdmin($uId);
                                array_push($AllUser,$uId);
                                if( $AllUser != null ){
                                    foreach ( $AllUser as $user ){
                                        $data[] = [
                                            'user_id' => $user,
                                            'event_id' => $eventId,
                                            'market_id' => $marketId,
                                            'market_type' => 'all',
                                            'byuser' => $uId
                                        ];
                                    }
                                }
                                
                                \Yii::$app->db->createCommand()->batchInsert('event_market_status',
                                    ['user_id', 'event_id','market_id','market_type','byuser'], $data )->execute();
                                
                            }
                           
                        }
                        
                    }
                    
                }
                
            }
            $response = [
                'status' => 1 ,
                "success" => [
                    "message" => "Data refresh successfully!"
                ]
            ];
            
        }
        return $response;
    }
    
    public function actionRefreshMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
        
            if( isset( $r_data[ 'id' ] ) ){
                $eID = $r_data[ 'id' ];//'28968172';//$_GET['id'];
                $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                
                //$response = curl_exec($ch);
                $response = json_decode(curl_exec($ch));
                curl_close($ch);
                //echo '<pre>';print_r($response);die;
                
                $event = $response->results[0]->event;
                //$markets = $response->results[0]->markets[0];
                $marketsArr = $response->results[0]->markets;
        
                //Test Data
                
                //$response = json_decode(file_get_contents( Url::base(true).'/data.json'));
                //$marketsArr = $response->data->items->results[0]->markets;
                //$event = $response->data->items->results[0]->event;
                
                //echo '<pre>';print_r($response);die;
            
                $marketsNew = [];
                
                $eventTypeId = $event->eventTypeId;
                
                foreach ( $marketsArr as $markets ){
                    
                    $marketsNew[] = [ 
                                    $eventTypeId,
                                    $eID,
                                    $markets->description->marketType,
                                    $markets->description->marketName,
                                    1,
                                    time(),
                                    time()
                                ];
                
                }
        
                //echo '<pre>';print_r($marketsNew);die;
                if( $marketsNew != null ){
                    $command = \Yii::$app->db->createCommand();
                    
                    //$truncate = $command->truncateTable( MarketType::tableName() );
                    //$truncate->execute();
                    
                    $truncate = $command->delete(MarketType::tableName(), ['event_id' => $eID]);
                    $truncate->execute();
                    
                    $insert = $command->batchInsert(MarketType::tableName(),
                        ['event_type_id','event_id','market_type','market_name','status','created_at','updated_at' ],
                        $marketsNew);
                    $insert->execute();
                }
        
                $response = [
                    'status' => 1 ,
                    "success" => [
                        "message" => "data refresh successfully!"
                    ]
                ];
            }
        }
        return $response;
    }
    
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
    
    public function actionManualSession()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        $query = ManualSession::find()
        ->select( [ 'id' , 'event_id','title' , 'start_over' , 'end_over', 'yes' ,'no' ,'created_at', 'updated_at' , 'status' , 'status_ball_to_ball' ] )
        //->from( Events::tableName() . ' e' )
        ->andWhere( [ 'status' => [1,2] , 'event_id' => $id ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "title" , $filters[ "title" ] ] );
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
        
        $models = $query->orderBy( [ "status" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    public function actionManualSessionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = ManualSession::findOne( $r_data[ 'id' ] );
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
                                "message" => "ManualSession $sts successfully!"
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
    
    public function actionManualSessionStatusBalltoball(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = ManualSession::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status_ball_to_ball == 1 ){
                        $event->status_ball_to_ball = 2;
                    }else{
                        $event->status_ball_to_ball = 1;
                    }
                    
                    if( $event->save( [ 'status_ball_to_ball' ] ) ){
                        
                        $sts = $event->status_ball_to_ball == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Manual Session Ball to Ball Status $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Manual Session Ball to Ball Status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    
    public function actionManualSessionBalltoball()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        $query = BallToBallSession::find()
        ->select( [ 'id' ,'manual_session_id','event_id','over' , 'ball' , 'yes' ,'no' ,'created_at', 'updated_at' , 'status' ] )
        //->from( Events::tableName() . ' e' )
        ->andWhere( [ 'status' => [1,2] , 'manual_session_id' => $id ] );
        
        if( $filters != null ){
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
        
        $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    
    public function actionManualSessionBalltoballStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = BallToBallSession::findOne( $r_data[ 'id' ] );
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
                                "message" => "ManualSession $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Event status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    public function actionCreateManualSession(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['ManualSession'] = $r_data;
            $model = new ManualSession();
            
            if ($model->load($data)) {
                
                $model->event_id = $id;
                $model->title = $r_data['title'];
                $model->start_over =  $r_data['start_over'];
                $model->end_over =  $r_data['end_over'];
                $model->yes =  $r_data['yes'];
                $model->no = $r_data['no'];
                //$model->user_id = \Yii::$app->user->id;
                $model->status = 2;
                $model->status_ball_to_ball = 2;
                $model->created_at = $model->updated_at = time();
                
                if( $model->save() ){
                    
                    if( $this->createBallToBallSession($model) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "ManualSession added successfully!"
                            ]
                        ];
                    }else{
                        
                        $model->delete();
                        
                        $response[ "error" ] = [
                            "message" => "Somthing wrong! Please try again !!"
                            //"data" => $model->errors
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
        
        return $response;
    }
    
    public function createBallToBallSession($data){
        
        if( $data != null ){
            $ball2ballArr = [];
            $yes = $data->yes;
            $no = $data->no;
            $eventId = $data->event_id;
            $msId = $data->id;
            $time = time();
            $startOver = $data->start_over;
            $endOver = $data->end_over;
            for ( $startOver; $startOver <= $endOver; $startOver++ ){
                
                for($i = 1; $i <= 6;$i++ ){
                    $ball2ballArr[] = [
                        'event_id' => $eventId,
                        'manual_session_id' => $msId,
                        'over' => $startOver,
                        'ball' => ($startOver-1).'.'.$i,
                        'yes' => $yes,
                        'no' => $no,
                        'created_at' => $time,
                        'updated_at' => $time,
                        'status' => 1
                    ];
                }
            }
            
            $command = \Yii::$app->db->createCommand();
            $attrArr = ['event_id' , 'manual_session_id' , 'over' , 'ball' , 'yes' , 'no' , 'created_at' , 'updated_at' , 'status'   ];
            $qry = $command->batchInsert('manual_session_ball_to_ball', $attrArr, $ball2ballArr);
            if( $qry->execute() ){
                return true;
            }else{
                return false;
            }
            
        }else{
            return false;
        }
        
    }
    
    public function actionUpdateManualSession(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['ManualSession'] = $r_data;
            $model = ManualSession::findOne($r_data['id']);
            
            if ($model->load($data)) {
                
                //$model->event_id = $r_data['event_id'];
                //$model->title = $r_data['title'];
                //$model->start_over =  $r_data['start_over'];
                //$model->end_over =  $r_data['end_over'];
                $model->yes =  $r_data['yes'];
                $model->no = $r_data['no'];
                //$model->user_id = \Yii::$app->user->id;
                //$model->status = 1;
                $model->updated_at = time();
                
                if( $model->save( ['yes','no' , 'updated_at'] ) ){
                    
                    $d = [ 'manual_session_id' => $model->id,
                            'yes' => $model->yes,
                            'no' => $model->no,
                            'updated_at' => time()
                         ];
                    
                    \Yii::$app->db->createCommand()->insert('manual_session_data', $d )->execute();
                    
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "ManualSession added successfully!"
                        ]
                    ];
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Somthing wrong!",
                        "data" => $model->errors
                    ];
                }
                
            }
            
            
        }
        
        return $response;
    }
    
    public function actionUpdateManualSessionBalltoball(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['BallToBallSession'] = $r_data;
            $model = BallToBallSession::findOne($r_data['id']);
            
            if ($model->load($data)) {
                
                $model->yes =  $r_data['yes'];
                $model->no = $r_data['no'];
                $model->updated_at = time();
                
                if( $model->save( ['yes','no','updated_at'] ) ){
                    
                    $d = [ 'manual_session_ball_to_ball_id' => $model->id,
                        'yes' => $model->yes,
                        'no' => $model->no,
                        'updated_at' => time()
                    ];
                    
                    \Yii::$app->db->createCommand()->insert('manual_session_ball_to_ball_data', $d )->execute();
                    
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Session updated successfully!"
                        ]
                    ];
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Somthing wrong!",
                        "data" => $model->errors
                    ];
                }
                
            }
            
            
        }
        
        return $response;
    }
}
