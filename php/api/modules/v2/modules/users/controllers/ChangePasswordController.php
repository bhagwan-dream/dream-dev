<?php
namespace api\modules\v2\modules\users\controllers;

//use yii\helpers\ArrayHelper;
//use common\models\PlaceBet;
//use yii\data\ActiveDataProvider;
use common\models\User;
//use common\models\TransactionHistory;
//use common\models\Setting;

class ChangePasswordController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'client','agent1','agent2' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    public function actionIndex(){
        
        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            
            if( json_last_error() == JSON_ERROR_NONE ){
                
                $user = User::findOne( [ 'id' => \Yii::$app->user->id , 'status' => 1 ] );
                
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
}