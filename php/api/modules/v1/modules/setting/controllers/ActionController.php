<?php
namespace api\modules\v1\modules\setting\controllers;

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\Setting;
use common\models\User;

class ActionController extends \common\controllers\aController
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
        
        $query = Setting::find()
            ->select( [ 'id' , 'key' , 'value' , 'status' ] )
            ->andWhere( [ 'status' => [1,2] ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "key" , $filters[ "title" ] ] );
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
    
    public function actionCreate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $model = new Setting();
            
            $model->key     = strtoupper( str_replace(' ', '_', trim($r_data[ 'key' ])) );
            $model->value   = $r_data[ 'value' ];
	        $model->status  = 1;
            
            if( $model->validate() ){
                if( $model->save() ){
                    $response = [ 
                        'status' => 1 , 
                        "success" => [ 
                            "message" => "new setting created successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [ 
                        "message" => "setting not saved!" ,
                        "data" => $model->errors
                    ];
                }
            }else{
                $response[ "error" ] = [
                    "message" => "setting not saved!" ,
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
                $model = Setting::findOne( $r_data[ 'id' ] );
                if( $model != null ){
                    $model->status = 0;
                    
                    if( $model->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "setting deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "setting not deleted!" ,
                            "data" => $model->errors
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
            
            $model = Setting::findOne( $id );
            
            if( $model != null ){
                $model->value = $r_data[ 'value' ];
                $model->updated_at = time();
                
                $attr = [ 'value' , 'updated_at' ];
                
                if( $model->validate( $attr ) ){
                    if( $model->save( $attr ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "setting saved successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "setting not updated!" ,
                            "data" => $model->errors
                        ];
                    }
                }else{
                    $response[ "error" ] = [
                        "message" => "event not updated!" ,
                        "data" => $model->errors
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
                $model = Setting::findOne( $r_data[ 'id' ] );
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
                                "message" => "setting $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "setting status not changed!" ,
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
            $model = Setting::find()->select( [ 'id' , 'key' , 'value' ] )->where( [ 'id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
        }
        
        return $response;
    }
    
    public function actionChangePassword(){
        
        if( \Yii::$app->request->isPost ){
            
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $user = User::findOne( [ 'id' => \Yii::$app->user->id , 'role' => [2,3] , 'status' => 1 ] );
                
                if( $user != null ){
                    
                    if ( $user->validatePassword( $data['oldpassword'] )) {
                        
                        $user->is_password_updated = 1;
                        
                        $user->auth_key = \Yii::$app->security->generateRandomString( 32 );
                        $user->password_hash = \Yii::$app->security->generatePasswordHash( $data['password'] );
                        
                        if( $user->save( [ 'password' , 'auth_key' , 'is_password_updated' ] ) ){
                            $response =  [ "status" => 1 , "success" => [ "message" => "Password changed successfully" ] ];
                        }else{
                            $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong! Password not update!" ,  "httpStatus" => '0' ] ];
                        }
                    }else{
                        $response =  [ "status" => 0 , "error" => [ "message" => "Incorrect old password." ,  "httpStatus" => '0' ] ];
                    }
                }
                
            }
            
            return $response;
        }else{
            return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!asd" ] ];
        }
        
    }
}
