<?php
namespace api\modules\v1\modules\users\controllers;

use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use yii\data\ActiveDataProvider;
use common\models\User;
use common\models\TransactionHistory;
use common\models\Setting;

class ReportController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent1','agent2','client' ],
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
        
        $query = PlaceBet::find()
            ->select( [ 'id' , 'user_id' , 'sec_id' , 'market_id' , 'price' , 'size', 'status' ] )
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
        
        $query = PlaceBet::find()
            ->select( [ 'client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'size' , 'win' , 'loss' , 'bet_status' , 'status' , 'created_at' ] )
            ->andWhere( [ 'status' => [1,2] ] );
        
        if( \Yii::$app->user->id != 1 ){
            
            $user = User::findOne(\Yii::$app->user->id);
            if( $user != null ){
                $query->andWhere( [ 'master' => $user->username ] );
            }
            
        }
            
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "runner" , $filters[ "title" ] ] );
                $query->orFilterWhere( [ "like" , "client_name" , $filters[ "title" ] ] );
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
        
        $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = PlaceBet::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $event->status = 0;
                    
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Bet deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! history not deleted!" ,
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
                $model = PlaceBet::findOne( $r_data[ 'id' ] );
                if( $model != null ){
                    
                    if( $model->status == 1 ){
                        $model->status = 2;
                    }else{
                        $model->status = 1;
                    }
                    
                    if( $model->save( [ 'status' ] ) ){
                        
                        $sts = $model->status == 1 ? 'allow' : 'deny';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Bet $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet status not changed!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    public function actionChangeBetStatus(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $model = PlaceBet::findOne( ['id' => $r_data[ 'id' ] , 'bet_status' => 'PENNING' ] );
                if( $model != null ){
                    
                    $model->bet_status = $r_data[ 'sts' ];
                    
                    if( $model->save( [ 'bet_status' ] ) ){
                        
                        $sts = $model->bet_status;
                        
                        if( $sts == 'WIN' ){
                        
                            $this->commissionTransWin($model->id);
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Bet $sts successfully!"
                                ]
                            ];
                            
                        }else{
                            
                            $this->commissionTransLoss($model->id);
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Bet $sts successfully!"
                                ]
                            ];
                            
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Bet status not changed!" ,
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
            $model = PlaceBet::find()->select( [  'id' , 'user_id' , 'sec_id' , 'market_id' , 'price' , 'size', 'status' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    public function currentBalance($price = false)
    {
        $id = \Yii::$app->user->id;
        $user = User::findOne($id);
        
        $user->balance = ( $user->balance + $price );
        
        if( $user->save(['balance']) ){
            return $user->balance;
        }else{
            return $user->balance;
        }
    }
    
    /*public function actionCommissionTrans()
    {
        $this->commissionTransWin('1');
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
    }*/
    
    public function commissionTransWin($betID)
    {
        
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = ($model->win - $model->size) - $model->ccr;
        
        $client = User::findOne( $model->user_id );
        
        $client->balance = ( ( $client->balance + $model->win ) - $model->ccr );
        
        if( $client != null && $client->save(['balance']) ){
            
            $bet = $model;
            
            $trans = new TransactionHistory();
            
            $trans->user_id = \Yii::$app->user->id;
            $trans->bet_id = $bet->id;
            $trans->client_name = $bet->client_name;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $model->win-$model->ccr;
            $trans->current_balance = $client->balance;
            $trans->status = 1;
            $trans->updated_at = time();
            
            if( $client->parent_id === 1 ){
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance-$commission );
                
                if( $admin->save( ['balance'] ) && $trans->save() ){
                    return true;
                }else{
                    return false;
                }
                
            }else{
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null && $agent2->commission != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent2->commission )/100 );
                    
                    $agent2->balance = ( $agent2->balance-$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent2->save(['balance']) ){
                        
                        if( $agent2->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent2->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance-$commission );
                            
                            if( $admin->save( ['balance'] ) && $trans->save() ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null && $agent1->commission != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                
                                $agent1->balance = ( $agent1->balance-$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent1->save(['balance']) ){
                                    
                                    if( $agent1->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent1->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance-$commission );
                                        
                                        if( $admin->save( ['balance'] ) && $trans->save() ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        $agent = User::findOne( $agent1->parent_id );
                                        
                                        if( $agent != null && $agent->commission != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent->commission )/100 );
                                            
                                            $agent->balance = ( $agent->balance-$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent->save(['balance']) ){
                                                
                                                $admin = User::findOne( $agent->parent_id );
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance-$commission );
                                                
                                                if( $admin->save( ['balance'] ) && $trans->save() ){
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
            
        }
    }
    
    public function commissionTransLoss($betID)
    {
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        $amount = $model->loss;
        
        $client = User::findOne( $model->user_id );
        
        if( $client != null ){
            
            if( $client->parent_id === 1 ){
                
                $admin = User::findOne( $client->parent_id );
                
                $commission = $amount;
                
                $admin->balance = ( $admin->balance+$commission );
                
                if( $admin->save( ['balance'] ) ){
                    return true;
                }else{
                    return false;
                }
                
            }else{
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null && $agent2->commission != 0 ){
                    
                    $commission1 = round ( ( $amount*$agent2->commission )/100 );
                    
                    $agent2->balance = ( $agent2->balance+$commission1 );
                    
                    $amount = ( $amount-$commission1 );
                    
                    if( $agent2->save(['balance']) ){
                        
                        if( $agent2->parent_id === 1 ){
                            
                            $admin = User::findOne( $agent2->parent_id );
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance+$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null && $agent1->commission != 0 ){
                                
                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                
                                $agent1->balance = ( $agent1->balance+$commission2 );
                                
                                $amount = ( $amount-$commission2 );
                                
                                if( $agent1->save(['balance']) ){
                                    
                                    if( $agent1->parent_id === 1 ){
                                        
                                        $admin = User::findOne( $agent1->parent_id );
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance+$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        
                                        $agent = User::findOne( $agent1->parent_id );
                                        
                                        if( $agent != null && $agent->commission != 0 ){
                                            
                                            $commission3 = round ( ( $amount*$agent->commission )/100 );
                                            
                                            $agent->balance = ( $agent->balance+$commission3 );
                                            
                                            $amount = ( $amount-$commission3 );
                                            
                                            if( $agent->save(['balance']) ){
                                                
                                                $admin = User::findOne( $agent->parent_id );
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance+$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
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
            
        }
    }
    
    /*public function commissionTrans($betID)
    {
        
        $model = PlaceBet::findOne( ['id' => $betID ] );
        
        if( $model->bet_status == 'WIN' ){
            
            $amount = ($model->win - $model->size);
            
            $client = User::findOne( $model->user_id );
            
            $client->balance = ( $client->balance+$model->win );
            
            if( $client != null && $client->save(['balance']) ){
                
                if( $client->parent_id === 1 ){
                    
                    $admin = User::findOne( $client->parent_id );
                    
                    if( $admin != null ){
                        
                        $commission = $amount;
                        
                        $admin->balance = ( $admin->balance-$commission );
                        
                        if( $admin->save( ['balance'] ) ){
                            return true;
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null ){
                    
                    /*if( $agent2->parent_id === 1 && $agent2->commission != 0 ){
                        
                        $admin = User::findOne( $agent2->parent_id );
                        
                        if( $admin != null ){
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance-$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }*/
                    
                    /*if( $agent2->commission != 0 ){
                    
                        $commission1 = round ( ( $amount*$agent2->commission )/100 );
                        
                        $agent2->balance = ( $agent2->balance-$commission1 );
                        
                        $amount = ( $amount-$commission1 );
                        
                        if( $agent2->save( ['balance'] ) ){
                        
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null ){
                                
                                if( $agent1->parent_id == 1 && $agent1->commission != 0 ){
                                    
                                    $admin = User::findOne( $agent1->parent_id );
                                    
                                    if( $admin != null ){
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance-$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                }
                                
                                if( $agent1->commission != 0 ){
                                
                                    $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                    
                                    $agent1->balance = ( $agent1->balance-$commission2 );
                                    
                                    $amount = ( $amount-$commission2 );
                                    
                                    if( $agent1->save( ['balance'] ) ){
                                    
                                        if( $agent1->parent_id === 1 ){
                                        
                                            $admin = User::findOne( $agent1->parent_id );
                                            
                                            if( $admin != null ){
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance-$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                        }else{
                                            
                                            $agent1 = User::findOne( $agent1->parent_id );
                                            
                                            if( $agent1 != null ){
                                                
                                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                                
                                                $agent1->balance = ( $agent1->balance-$commission2 );
                                                
                                                $amount = ( $amount-$commission2 );
                                                
                                                if( $agent1->save( ['balance'] ) ){
                                                    
                                                    $admin = User::findOne( $agent1->parent_id );
                                                    
                                                    if( $admin != null ){
                                                        
                                                        $commission = $amount;
                                                        
                                                        $admin->balance = ( $admin->balance-$commission );
                                                        
                                                        if( $admin->save( ['balance'] ) ){
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
                        
            }else{
                return false;
            }
            
        }else{
            
            $amount = $model->loss;
            
            $client = User::findOne( $model->user_id );
            
            if( $client != null ){
                
                if( $client->parent_id === 1 ){
                    
                    $admin = User::findOne( $client->parent_id );
                    
                    if( $admin != null ){
                        
                        $commission = $amount;
                        
                        $admin->balance = ( $admin->balance+$commission );
                        
                        if( $admin->save( ['balance'] ) ){
                            return true;
                        }else{
                            return false;
                        }
                        
                    }else{
                        return false;
                    }
                    
                }
                
                $agent2 = User::findOne( $client->parent_id );
                
                if( $agent2 != null ){
                    
                    if( $agent2->parent_id === 1 && $agent2->commission != 0 ){
                        
                        $admin = User::findOne( $agent2->parent_id );
                        
                        if( $admin != null ){
                            
                            $commission = $amount;
                            
                            $admin->balance = ( $admin->balance+$commission );
                            
                            if( $admin->save( ['balance'] ) ){
                                return true;
                            }else{
                                return false;
                            }
                            
                        }else{
                            return false;
                        }
                        
                    }
                    
                    if( $agent2->commission != 0 ){
                        
                        $commission1 = round ( ( $amount*$agent2->commission )/100 );
                        
                        $agent2->balance = ( $agent2->balance+$commission1 );
                        
                        $amount = ( $amount-$commission1 );
                        
                        if( $agent2->save( ['balance'] ) ){
                            
                            $agent1 = User::findOne( $agent2->parent_id );
                            
                            if( $agent1 != null ){
                                
                                if( $agent1->parent_id === 1 && $agent1->commission != 0 ){
                                    
                                    $admin = User::findOne( $agent1->parent_id );
                                    
                                    if( $admin != null ){
                                        
                                        $commission = $amount;
                                        
                                        $admin->balance = ( $admin->balance+$commission );
                                        
                                        if( $admin->save( ['balance'] ) ){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                        
                                    }else{
                                        return false;
                                    }
                                    
                                }
                                
                                if( $agent1->commission != 0 ){
                                    
                                    $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                    
                                    $agent1->balance = ( $agent1->balance+$commission2 );
                                    
                                    $amount = ( $amount-$commission2 );
                                    
                                    if( $agent1->save( ['balance'] ) ){
                                        
                                        if( $agent1->parent_id === 1 ){
                                            
                                            $admin = User::findOne( $agent1->parent_id );
                                            
                                            if( $admin != null ){
                                                
                                                $commission = $amount;
                                                
                                                $admin->balance = ( $admin->balance+$commission );
                                                
                                                if( $admin->save( ['balance'] ) ){
                                                    return true;
                                                }else{
                                                    return false;
                                                }
                                                
                                            }else{
                                                return false;
                                            }
                                        }else{
                                            
                                            $agent1 = User::findOne( $agent1->parent_id );
                                            
                                            if( $agent1 != null ){
                                                
                                                $commission2 = round ( ( $amount*$agent1->commission )/100 );
                                                
                                                $agent1->balance = ( $agent1->balance+$commission2 );
                                                
                                                $amount = ( $amount-$commission2 );
                                                
                                                if( $agent1->save( ['balance'] ) ){
                                                    
                                                    $admin = User::findOne( $agent1->parent_id );
                                                    
                                                    if( $admin != null ){
                                                        
                                                        $commission = $amount;
                                                        
                                                        $admin->balance = ( $admin->balance+$commission );
                                                        
                                                        if( $admin->save( ['balance'] ) ){
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
                
            }else{
                return false;
            }
            
        }
    }*/
}
