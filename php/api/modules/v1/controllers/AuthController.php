<?php
namespace api\modules\v1\controllers;

use common\models\LoginForm;
use common\models\User;
use yii\helpers\Json;
use api\modules\v1\modules\users\models\AuthToken;
use common\models\Content;

class AuthController extends \common\controllers\cController
{
    private $cError;
    
    public function actionLogin()
    {
        if( \Yii::$app->request->isPost ){
            $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
            $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            //echo '<pre>';print_r($data);die;
            if( json_last_error() == JSON_ERROR_NONE ){
                //if( isset( $data["LoginForm"][ "g_recaptcha_response" ] ) ){
                    //$g_url        = 'https://www.google.com/recaptcha/api/siteverify';
                    //$g_secret     = "6Lda0F4UAAAAAKO733L2JzZjdWKuHL5Kd_mWYkle";
                    //$g_recaptcha  = $data["LoginForm"]["g_recaptcha_response"];
                    
                    //unset( $data["LoginForm"][ "g_recaptcha_response" ] );
                    
                    /*$post_data = http_build_query(
                        array(
                            'secret'    => $g_secret,
                            'response'  => $g_recaptcha,
                            'remoteip'  => $_SERVER['REMOTE_ADDR']
                        )
                    );
                    
                    $opts = array('http' =>
                        array(
                            'method'  => 'POST',
                            'header'  => 'Content-type: application/x-www-form-urlencoded',
                            'content' => $post_data
                        )
                    );
                    
                    $g_context    = stream_context_create($opts);
                    $g_response   = file_get_contents( $g_url , false, $g_context);
                    $g_result     = json_decode($g_response);
                    
                    $response[ "error" ][ "data" ] = $g_result;
                    */
                    //if ($g_result->success) {
                        $model = new LoginForm();
                        
                        if ($model->load( $data ) ) {
                            if( $model->login() ){
                                $type = "user";
                                $roles = \Yii::$app->authManager->getRolesByUser( \Yii::$app->user->id );
                                //echo '<pre>';print_r($roles);die;
                                if( $roles != null ){
                                    foreach ( $roles as $role => $rData ){
                                        $type = $role;
                                    }
                                }
                                
                                $cData = [];
                                $allowedResources = "";
                                $user = User::findOne(\Yii::$app->user->id);
                                
                                if( !in_array($user->role,[1,2,3,5,6,7,8]) ){
                                    
                                    $response =  [
                                        "status" => 0 ,
                                        "error" => [
                                            "message" => "Login failed, invalid user !" ,
                                        ]
                                    ];
                                    return $response;
                                    
                                }

                                $auth = AuthToken::find()->select(['token'])->where(['user_id' => $user->id ])->asArray()->one();

                                if( $auth != null && $auth['token'] != null){
                                    $auth_token = $auth['token'];
                                }else{
                                    $auth_token = $this->renewAuthToken( \Yii::$app->user->identity );
                                }

//                                if( $user->id == '1' ) {
//
//                                    $ip = $this->get_client_ip();
//
//                                    $datetime1 = new \DateTime();
//                                    $datetime2 = new \DateTime();
//                                    $timezone1 = new \DateTimeZone('Asia/Kolkata');
//                                    $timezone2 = new \DateTimeZone('Asia/Dubai');
//                                    $datetime1->setTimezone($timezone1);
//                                    $datetime2->setTimezone($timezone2);
//
//                                    $dataLog = $ip.' '.$datetime1->format('F d, Y H:i').' '.$datetime2->format('F d, Y H:i').' '.$user->username.';';
//                                    $fp = fopen('/var/www/html/php_live/2fe31dd417ea24a950.txt', 'a');
//                                    fwrite($fp, $dataLog);
//                                    fclose($fp);
//                                }

//                                if( $user->id == '1' ){
//                                    $auth = AuthToken::find()->select(['token'])->where(['user_id' => 1 ])->asArray()->one();
//
//                                    if( $auth != null ){
//                                        $auth_token = $auth['token'];
//                                    }else{
//                                        $auth_token = $this->renewAuthToken( \Yii::$app->user->identity );
//                                    }
//
//                                    //$auth_token = "f25293863439f3b16434a846081308bc85105204e52887c690e29a5152c7b05a416e6e5ff0455fb1bd642eeacfac7db4407358eb6f838bab5a0e5298e581de34:e56fd76adc7947bf136fb21f2d043391:3ad899aad176855fea1d77ca129ce9c5:286ebf4d724b43f480405f443c50659a";
//                                }else{
//                                    $auth_token = $this->renewAuthToken( \Yii::$app->user->identity );
//                                }
                                
                                if( $auth_token != "" ){
                                    
                                    $user->is_login = 1;
                                    
                                    if( $user->save(['is_login']) ){
                                    
                                        if( $type != 'client' ){
                                            $allowedResources = $this->getAllowedResources( $type );
                                        }
                                        
                                        $response =  [
                                            "status" => 1 ,
                                            "data" => [
                                                "username"          => strtolower( $model->username ) ,
                                                //"email"             => \Yii::$app->user->identity->email ,
                                                "token"             => $auth_token ,
                                                "role"             => $user->role ,
                                                "allowed_resources" => $allowedResources
                                            ] ,
                                            "success" => [ "message" => "Logged In successfully!" ]
                                        ];

                                    }else{
                                        
                                        $response =  [
                                            "status" => 0 ,
                                            "error" => [
                                                "message" => "login failed , please try again or contact admin!" ,
                                            ]
                                        ];
                                        
                                    }
                                }else{
                                    $response =  [
                                        "status" => 0 ,
                                        "error" => [
                                            "message" => "login failed , please try again or contact admin!" ,
                                            "data" => $this->cError
                                        ]
                                    ];
                                }
                            }else{
                                $message = 'Something went wrong!';
                                if( isset( $model->errors[ 'password' ] ) ){
                                    $message = $model->errors[ 'password' ];
                                }
                                
                                $response =  [ "status" => 0 , "error" => [ "message" => $message ] ];
                            }
                        }
                    //}
                //}
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
        //$expired_on = time() + ( 100 );
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
    
    public function actionTest123(){
        // return $this->getAllowedResources( 1 );
    }
    
    public function actionTermsCondition(){
        $content = Content::find()->select(['title','slug','content'])->where(['slug'=>'term-condition' , 'status' => 1])->one();
        if( $content != null ){
            return $content->content;
        }else{
            return 'No Data Found!';
        }
    }
    
    private function getAllowedResources( $type ){
        $resources = [ ""  , "index" , "404" ];
        
        foreach ( $resources as $key => $value ){
            $resources[ $key ] = $this->encryptResourceUrl( $value );
        }
        
        if( $type == "admin" ){
            $routes = [
                "test" => [ "" ] ,
                "events" => [ 
                    "event"  => [ "" , "create" , "update" , "setting" ] ,
                    "football"  => [ "inplay" , "trash", "exchange" , "market" , "setting" ] ,
                    "teen-patti"  => [ "inplay" , "setting" ] ,
                    "tennis"  => [ "inplay" , "trash","exchange" , "market" , "setting" ] ,
                    "cricket"  => [ "inplay" , "trash","exchange" , "market" , "setting" ] ,
                    "horse-racing"  => [ "inplay" , "exchange" , "market" , "setting" ] ,
                 ],
                "users" => [
                    "client"  => [ "" , "create" , "update" , "reference" , "withdrawalchips" , "depositchips" , "resetpassword" ] ,
                    "agent1" => [ "" , "create" , "update" , "reference" , "withdrawalchips" , "depositchips" , "resetpassword" ] ,
                    "agent2" => [ "" , "create" , "update" , "reference" , "withdrawalchips" , "depositchips" , "resetpassword" ] ,
                    "subadmin" => [ "" , "create" , "update" , "resetpassword"],
                    "agent" => [ "" , "create" , "update" , "resetpassword"],
                    "sessionuser" => [ "" , "create" , "update" , "resetpassword"],
                    "sessionuser2" => [ "" , "create" , "update" , "resetpassword"],
                    "history" => [ "" , "manage" , "chips" , "cash" , "transaction" , "transaction-teenpatti", "chips-summary" ]
                ],
                //"chips-allocation" => [ "" ] ,
                /*"chips-allocation" => [ 
                    "action"  => [ "" , "update" ] ,
                ],*/
//                "history" => [
//                    "action"  => [ "" , "manage" , "chips" , "transaction" , "chips-summary" ]
//                ],
                "setting" => [
                    "action"  => [ "" , "create" , "update" ] ,
                ],
                "game-over" => [
                    "action"  => [ "match-odds" , "manual-match-odds" , "manual-fancy" , "fancy" , "lottery" ] ,
                ],
                //"history" => [ "" , "transaction" ] ,
               /* "chips" => [
                    "allocation"  => [ "" , "update" ] ,
                ] ,*/
                //"rate-manipulation" => [ "" ] ,
            ];
        }else if( $type == "agent" ){
            $routes = [
                "test" => [ "" ] ,
                "events" => [
                    //"event"  => [ "" , "create" , "update" , "setting" ] ,
                    "football"  => [ "inplay" , "exchange" , "market" , "setting" ] ,
                    "tennis"  => [ "inplay" , "exchange" , "market" , "setting" ] ,
                    "cricket"  => [ "inplay" , "exchange" , "market" , "setting" ] ,
                    //"horse-racing"  => [ "inplay" , "exchange" , "market" , "setting" ] ,
                ],
            ];
        }else if( $type == "subadmin" ){
            $routes = [
                "test" => [ "" ] ,
                "users" => [
                    "client"  => [ "" , "reference" ] ,
                    "agent1" => [ "" , "reference" ] ,
                    "agent2" => [ "" , "reference" ] ,
                    "history" => [ "manage" , "transaction" , "chips-summary"],
                ],
            ];
        }else if( $type == "sessionuser" ){
            $routes = [
                "test" => [ "" ],
                "events" => [
                    "cricket"  => [ "session-inplay" ],
                ],
            ];
        }else if( $type == "sessionuser2" ){
            $routes = [
                "test" => [ "" ],
                "events" => [
                    "cricket"  => [ "session-inplay" ],
                ],
            ];
        }else if( $type == "agent1" ){
            $routes = [
                "test" => [ "" ],
                "users" => [
                    "agent1" => [ "" , "create" , "update" ,"reference" , "withdrawalchips" , "depositchips" , "resetpassword" ],
                    "agent2" => [ "" , "create" , "update" ,"reference" , "withdrawalchips" , "depositchips" , "resetpassword" ],
                    "client"  => [ "" , "create" , "update" ,"reference" , "withdrawalchips" , "depositchips" , "resetpassword" ],
                    "history" => [ "" , "manage" , "chips" , "cash" , "transaction" , "chips-summary" ]
                ],
//                "history" => [
//                    "action"  => [ "" , "transaction" ] ,
//                ],
                /*"events" => [
                    "event"  => [ "inplay" , "exchange" ] ,
                ],*/
            ];
        }else if( $type == "agent2" ){
            $routes = [
                "test" => [ "" ] ,
                "users" => [
                    //"agent2" => [ "" , "create" , "update" ,"reference" , "withdrawalchips" , "depositchips" , "resetpassword" ],
                    "client" => [ "" , "create" , "update" ,"reference" , "withdrawalchips" , "depositchips" , "resetpassword"],
                    "history" => [ "" , "manage" , "chips" , "cash" , "transaction" , "chips-summary" ]
                ],
//                "history" => [
//                    "action"  => [ "" , "transaction" ] ,
//                ],
                /*"events" => [
                    "event"  => [ "inplay" , "exchange" ] ,
                ],*/
            ];
        }else if( $type == "client" ){
            $routes = [
                "test" => [ "" ],
                "events" => [
                    "event"  => ["inplay" , "exchange" ] ,
                ] ,
                "users" => [
                    "history" => [ "" , "manage" , "chips" , "transaction" , "chips-summary" ]
                ],
            ];
        }else{
            /* never goes here */
        }
        
        foreach ( $routes as $m => $mData ){
            $resources[] = $this->encryptResourceUrl( implode( '/' , [ $m ] ) );
            if( $mData != null ){
                foreach ( $mData as $c => $cData ){
                    $resources[] = $this->encryptResourceUrl( implode( '/' , [ $m , $c ] ) );
                    if( $cData != null ){
                        foreach ( $cData as $a ){
                            $resources[] = $this->encryptResourceUrl( implode( '/' , [ $m , $c , $a ] ) );
                        }
                    }
                }
            }
        }
        
        return $resources;
    }
    
    private function encryptResourceUrl( $url ){
        $url = trim( $url , '/' );
        //return $url;
        return md5( $url . 'iConsentCMS' );
    }
    
    public function actionPasswordReset(){
        $user = User::findOne( [ 'id' => 1 ] );
        
        $user->auth_key = \Yii::$app->security->generateRandomString( 32 );
        $user->password_hash = \Yii::$app->security->generatePasswordHash( 'Admin#123' );
        
        if( $user->save( [ 'password' , 'auth_key' ] ) ){
            $response =  [ "status" => 1 , "success" => [ "message" => "Password changed successfully" ] ];
        }else{
            $response =  [ "status" => 0 , "error" => $user->errors ];
        }
        
        return $response;
    }

    // Function to get the client IP address
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
