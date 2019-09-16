<?php
namespace api\modules\v1\modules\users\controllers;

use api\modules\v1\modules\users\models\AuthToken;
use common\models\User;

class ChangePasswordController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','subadmin','agent1','agent2' ],
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
                        $user->is_login = 0;
                        $user->auth_key = \Yii::$app->security->generateRandomString( 32 );
                        $user->password_hash = \Yii::$app->security->generatePasswordHash( $data['password'] );

                        if( $data['username'] != '' && $data['username'] != null
                            && strlen($data['username']) > 1 && trim($user->username) != trim($data['username']) ){
                            $user->username = $data['username'];
                        }else{
                            $user->username = $user->username;
                        }

                        if( $user->save( [ 'username' , 'password' , 'auth_key' , 'is_password_updated' , 'is_login' ] ) ){

                            //$role = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );

                            //if( isset($role['admin']) ){
                                $this->renewAuthToken( \Yii::$app->user->identity );
                            //}

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

    private function renewAuthToken( $user ){

        $auth_model = AuthToken::find()->where( [ "user_id" => $user->id , "user_type" => 1 ] )->one();

        $auth_token = $this->generateAuthTokenForCms( $user );

        //$expired_on = time() + ( 24 * 60 * 60 );
        $expired_on = time() + ( 24 * 60 * 60 * 365);

//        if( $user->id == '4' ){
//            $expired_on = time() + ( 24 * 60 * 60 * 365);
//        }

        if( $auth_model != null ){
            $auth_model->token = $auth_token;
            $auth_model->expired_on = $expired_on;
            if( $auth_model->validate( [ "token" , "expired_on" ] ) ){
                if( $auth_model->save( [ "token" , "expired_on" ] ) ){
                    return $auth_token;
                }else{
                    $this->cError = $auth_model->errors;
                }
            }else{
                $this->cError = $auth_model->errors;
            }
        }else{
            $auth_model = new AuthToken();

            $auth_model->user_id = $user->id;
            $auth_model->user_type = 1;
            $auth_model->token = $auth_token;
            $auth_model->expired_on = $expired_on;

            if( $auth_model->validate() ){
                if( $auth_model->save() ){
                    return $auth_token;
                }else{
                    $this->cError = $auth_model->errors;
                }
            }else{
                $this->cError = $auth_model->errors;
            }
        }

        return "";
    }

    private function generateAuthTokenForCms( $user ){
        $hash_1 = md5( $user->username );
        $hash_2 = md5( $user->password_hash );
        $hash_3 = md5( $user->created_at );
        $hash_4 = md5( microtime() );
        $hash_5 = md5( "iConsent2" );

        $hash_f = [];
        $hash_f[] = hash( 'sha512' , $hash_1 . $hash_2 . $hash_3 . $hash_4 . $hash_5 );
        $hash_f[] = md5( $user->username . microtime() . "iConsent2"  );
        $hash_f[] = md5( $user->password_hash . microtime() . $user->created_at . "iConsent2"  );
        $hash_f[] = md5( "iConsent2-cms"  );

        return implode( ":" , $hash_f );
    }
}
