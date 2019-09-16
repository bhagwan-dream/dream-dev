<?php
namespace api\modules\v1\modules\users\controllers;

use yii\helpers\ArrayHelper;
use common\models\TransactionHistory;
use common\models\EventsPlayList;

class MarketProfitLossController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent1','agent2' ],
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
        
        $query = TransactionHistory::find()
        ->select( [ 'id' , 'user_id' , 'bet_id' , 'client_name' , 'transaction_type' ,'transaction_amount' , 'current_balance' , 'status' , 'created_at' ] )
            ->andWhere( [ 'status' => [1,2] ] );
        
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
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
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
        
        $query = EventsPlayList::find()
        ->select( [ 'id' , 'sport_id' , 'event_id' , 'event_name' , 'event_time' , 'win_result'] )
        ->where( [ 'status' => 1 , 'game_over' => 'YES' , 'play_type' => 'IN_PLAY' ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "event_name" , $filters[ "title" ] ] );
            }
            /*if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
            }*/
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();

        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $modelsArr = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        //echo '<pre>';print_r($models);die;
        $models = [];
        if( $modelsArr != null ){
            $event = ['2'=>'Tennis','4'=>'Cricket','7'=>'Horse Racing','6423'=>'Football'];
            foreach( $modelsArr as $data ){
                $models[] = [
                    'id' => $data['id'],
                    'market' => $event[$data['sport_id']].' / '.$data['event_name'].'/ Match Odds / Winner - '.$data['win_result'],
                    'admin' => $this->getProfitLoss('A',$data['event_id']),
                    'sm' => $this->getProfitLoss('B',$data['event_id']),
                    'sm2' => $this->getProfitLoss('C',$data['event_id']),
                    'm' => $this->getProfitLoss('D',$data['event_id']),
                    'm2' => $this->getProfitLoss('E',$data['event_id']),
                ];
            }
        }
        
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    public function getProfitLoss($parentType,$eventId){
        
        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
        ->where(['event_id'=>$eventId,'parent_type'=>$parentType , 'transaction_type' => 'CREDIT'])->asArray()->all();
        $profitVal = 0;
        if( $profit[0]['profit'] > 0 ){
            $profitVal = $profit[0]['profit'];
        }
        
        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
        ->where(['event_id'=>$eventId,'parent_type'=>$parentType , 'transaction_type' => 'DEBIT'])->asArray()->all();
        $lossVal = 0;
        
        if( $loss[0]['loss'] > 0 ){
            $lossVal = $loss[0]['loss'];
        }
        
        $total = $profitVal-$lossVal;
        
        return $total;
        
    }
    
    
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = TransactionHistory::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $event->status = 0;
                    
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "transaction deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "history not deleted!" ,
                            "data" => $event->errors
                        ];
                    }
                    
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
                $model = TransactionHistory::findOne( $r_data[ 'id' ] );
                if( $model != null ){
                    
                    if( $model->status == 1 ){
                        $model->status = 2;
                    }else{
                        $model->status = 1;
                    }
                    
                    if( $model->save( [ 'status' ] ) ){
                        
                        $sts = $model->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "transaction history $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "transaction history status not changed!" ,
                            "data" => $model->errors
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
            $model = TransactionHistory::find()->select( [ 'id' , 'user_id' , 'bet_id' , 'transaction_amount' , 'current_balance' , 'status' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
}
