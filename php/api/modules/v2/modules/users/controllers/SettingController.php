<?php
namespace api\modules\v2\modules\users\controllers;

use common\models\User;
use common\models\PlaceBetOption;
use common\models\Event;
use common\models\FavoriteMarket;

class SettingController extends \common\controllers\aController
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
    
    // ChangePassword
    
    public function actionChangePassword(){
        
        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $user = User::findOne( [ 'id' => \Yii::$app->user->id , 'role' => 4 , 'status' => 1 ] );
                
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
            return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
    }
    
    // SetBetOptions
    public function actionSetBetOptions(){
        
        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $user = User::findOne( [ 'id' => \Yii::$app->user->id , 'role' => 4 , 'status' => 1 ] );
                
                if( $user != null ){
                    
                    $model = PlaceBetOption::findOne(['user_id'=>$user->id]);
                    
                    if( $model != null ){
                        
                        $model->bet_option = trim($data['bet_option']);
                        $model->updated_at = time();
                        
                        if( $model->update(['bet_option','updated_at']) ){
                            $response =  [ "status" => 1 , "success" => [ "message" => "Bet Options updated successfully" ] ];
                        }else{
                            $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong! Bet Options not update!" ,  "httpStatus" => '0' ] ];
                        }
                        
                    }else{
                        
                        $model = new PlaceBetOption();
                        
                        $model->user_id = $user->id;
                        $model->bet_option = trim($data['bet_option']);
                        $model->updated_at = $model->created_at = time();
                        $model->status = 1;
                        
                        if( $model->save() ){
                            $response =  [ "status" => 1 , "success" => [ "message" => "Bet Options saved successfully" ] ];
                        }else{
                            $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong! Bet Options not saved!" ,  "httpStatus" => '0' ] ];
                        }
                        
                    }
                }
                
            }
            
            return $response;
        }else{
            return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
    }
    
    // AddRemoveFavorite
    public function actionAddRemoveFavorite(){
        
        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $uId = \Yii::$app->user->id;
                
                $user = User::findOne( [ 'id' => \Yii::$app->user->id , 'role' => 4 , 'status' => 1 ] );
                
                if( $user != null ){
                    
                    $model = FavoriteMarket::findOne([ 'market_id'=> trim($data['market_id']) , 'market_type'=> trim($data['market_type']) , 'user_id' => $uId ]);
                    
                    if( $data['favorite'] == 'remove' && $model != null ){
                        $model->delete();
                        $response =  [ "status" => 1 , "success" => [ "message" => "Removed in Favorite Successfully !" ] ];
                    }else if( $data['favorite'] == 'add' && $model == null  ){
                        
                        $model = new FavoriteMarket();
                        $model->event_id = trim($data['event_id']);
                        $model->market_id = trim($data['market_id']);
                        $model->market_type = trim($data['market_type']);
                        $model->user_id = $uId;
                        
                        if( $model->save() ){
                            $response =  [ "status" => 1 , "success" => [ "message" => "Added in Favorite Successfully !" ] ];
                        }else{
                            $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong! Not add in Favorite !" ] ];
                        }
                        
                    }else{
                        $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong! Add Or Remove in Favorite !" ] ];
                    }
                    
                }
                
            }
            
            return $response;
        }else{
            return [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        }
        
    }
    
    // Bet Config
    
    public function actionBetConfig()
    {
        $query = Event::find()
        ->select( [ 'id' , 'event_type_name','max_profit_all_limit'  ] )
        ->where( [ 'status' => 1 ] );
        
        $models = $query->orderBy( [ "event_slug" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models ] ];
        
    }
    
}
