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
use common\models\EventsRunner;

class FootballController extends \common\controllers\aController
{
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }

    //action Inplay
    public function actionInplay()
    {
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $inplay = [];

		$today = date('Ymd');
        $tomorrow = date('Ymd' , strtotime($today . ' +7 day') );
		$lastday = date('Ymd' , strtotime($today . ' -7 day') );

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['agent']) || isset($role['sessionuser']) || isset($role['sessionuser2']) ){
            $gameOver = 'NO';
        }else{
            $gameOver = ['YES','NO'];
        }

        if( json_last_error() == JSON_ERROR_NONE ) {
            $r_data = ArrayHelper::toArray($request_data);

            if( isset( $r_data['filter'] ) ){
                $filter = $r_data['filter'];

                if( $filter['status'] != null || $filter['title'] != null){
                    $inplay = EventsPlayList::find()
                        ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 1 , 'play_type' => $filter['status'] ])
                        ->andWhere(['LIKE','event_name',$filter['title']])
                        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
                }else{
                    $inplay = EventsPlayList::find()
                        ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 1 ])
                        ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
                }

            }else{

                $inplay = EventsPlayList::find()
                    ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 1 ])
                    ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                    ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();

            }

        }

//        $inplay = EventsPlayList::find()
//        ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 1 ])
//		->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
//        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();

        if( $inplay != null ){
            $i = 0;
            foreach ( $inplay as $data ){
                
                $runners = EventsRunner::find()->where(['event_id' => $data['event_id']])
                ->asArray()->all();
                
                if( $runners != null ){
                    foreach ( $runners as $rnr ){
                        $inplay[$i]['runners'][] = [
                            'secId' => $rnr['selection_id'],
                            'nunnerName' => $rnr['runner'],
                        ];
                    }
                }
                $i++;
            }
            
        }

        return [ "status" => 1 , "data" => [ "items" => $inplay ] ];
    }

    //action Trash
    public function actionTrash()
    {
        $inplay = [];
        $inplay = EventsPlayList::find()
        ->where(['game_over' => ['YES','NO'] ,'status'=>0, 'sport_id' => 1 ])
        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
        
        if( $inplay != null ){
            $i = 0;
            foreach ( $inplay as $data ){
                
                $runners = EventsRunner::find()->where(['event_id' => $data['event_id']])
                ->asArray()->all();
                
                if( $runners != null ){
                    foreach ( $runners as $rnr ){
                        $inplay[$i]['runners'][] = [
                            'secId' => $rnr['selection_id'],
                            'nunnerName' => $rnr['runner'],
                        ];
                    }
                }
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $inplay ] ];
    }

    //action Delete
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $event = EventsPlayList::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $event->status = 0;
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Event deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Event not deleted!"
                        ];
                    }

                }
            }
        }

        return $response;
    }

    //action Setting View
    public function actionSettingView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        if( $id != null ){
            $model = EventsPlayList::find()->select( [ 'event_name','upcoming_min_stake','upcoming_max_stake','upcoming_max_profit','max_odd_limit','accept_unmatch_bet','max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit' , 'max_profit_all_limit','bet_delay' ] )->where( [ 'event_id' => $id ] )->asArray()->one();
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
            
            if( isset( $id ) && isset( $r_data[ 'max_stack' ] )
                && isset( $r_data[ 'min_stack' ] )
                && isset( $r_data[ 'max_profit' ] )
                && isset( $r_data[ 'max_profit_limit' ] )
                && isset( $r_data[ 'max_profit_all_limit' ] ) ){
                    
                    $event = EventsPlayList::findOne( ['event_id' => $id ] );
                    
                    if( $event != null ){

                        $event->upcoming_min_stake   = $r_data[ 'upcoming_min_stake' ];
                        $event->upcoming_max_stake   = $r_data[ 'upcoming_max_stake' ];
                        $event->upcoming_max_profit   = $r_data[ 'upcoming_max_profit' ];
                        $event->max_odd_limit   = $r_data[ 'max_odd_limit' ];
                        $event->accept_unmatch_bet   = $r_data[ 'accept_unmatch_bet' ];

                        $event->max_stack   = $r_data[ 'max_stack' ];
                        $event->min_stack   = $r_data[ 'min_stack' ];
                        $event->max_profit  = $r_data[ 'max_profit' ];
                        $event->max_profit_limit  = $r_data[ 'max_profit_limit' ];
                        $event->max_profit_all_limit  = $r_data[ 'max_profit_all_limit' ];
                        $event->bet_delay  = $r_data[ 'bet_delay' ];
                        
                        if( $event->save() ){
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Event Setting updated successfully!"
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
                            "message" => "event status not changed!"
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
            
            $commentary = GlobalCommentary::findOne(['sport_id'=>1 , 'event_id'=>$id]);
            
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
                $commentary->sport_id = 1;
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
    
}
