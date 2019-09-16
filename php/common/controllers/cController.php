<?php
namespace common\controllers;

use yii\web\Response;
use yii\filters\ContentNegotiator;
use common\models\User;
use api\modules\v1\modules\users\models\AuthToken;
use yii\helpers\Json;

/**
 * Site controller
 */
class cController extends \yii\rest\Controller
{
	protected $authUser = [];
	protected $apiUserToken = '15815-peDeUY8w5a9rPq';//'13044-CgPWGpYSAOn7aV';//'15727-8puafDrdScO1Rn';//'13044-CgPWGpYSAOn7aV';
	protected $apiUserId = '5bfba9dccb4b8';//'5bf52bb732f91';//'5bcb17c84f03a';
	
	//Karim Sir API
	//protected $apiUrlCricket = 'http://cricket.royalebet.uk/';
	//protected $apiUrlTennis = 'http://tennis.royalebet.uk/';
	//protected $apiUrlFootball = 'http://soccer.royalebet.uk/';
	
	//Lala Bhai API
	protected $apiUrlCricket = 'http://13.233.165.68/api/event/4';
	protected $apiUrlTennis = 'http://13.233.165.68/api/event/2';
	protected $apiUrlFootball = 'http://13.233.165.68/api/event/1';
	
	protected $apiUrl = 'http://irfan.royalebet.uk/getodds.php';//http://odds.kheloindia.bet/getodds.php
	protected $apiUrlFancy = 'http://irfan.royalebet.uk/getfancy.php';//http://shubham.kheloindia.bet/getfancy.php
	protected $apiUrlMatchOdd = 'http://odds.appleexch.uk:3000/getmarket';//'http://appleexch.uk:3000/getMarket';
	
	protected $apiUrlMatchOddLive = 'http://rohitash.dream24.bet:3000/getmarket';
	protected $apiUrlFancyLive = 'http://fancy.royalebet.uk';//http://shubham.kheloindia.bet/getfancy.php
	
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		$behaviors = parent::behaviors();
	
		$behaviors[] = [
			'class' => ContentNegotiator::className(),
			'formats' => [
				'application/json' => Response::FORMAT_JSON,
			],
		];
	       
		// remove authentication filter
		$auth = $behaviors['authenticator'];
		unset($behaviors['authenticator']);
		
		// add CORS filter
		$behaviors['corsFilter'] = [
		    'class' => \yii\filters\Cors::className(),
		];
		
		// re-add authentication filter
		$behaviors['authenticator'] = $auth;
		
		// avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
		$behaviors['authenticator']['except'] = ['options'];
		
		return $behaviors;
	}
	
	public function actions(){
	    if( \Yii::$app->request->isOptions ){
	        
	    }
	}
	
	protected function getUserDetails(){
	    $res = [];	    
        $user = User::find()
           ->where( [ "id" =>  \Yii::$app->user->id ] )
           ->with( [ 'relationUserRegion.relationInstanceRegion' , 'relationUserCountry.relationCountry.relationRegion' ])
           ->asArray()
           ->one();
           
       if( $user != null ){
           $role = "";
           $roles = \Yii::$app->authManager->getRolesByUser( $user[ "id" ] );
           
           if ( $roles != null ){
               foreach ( $roles as $cRole => $cRoleData ){
                   $role = $cRole;
               }
           }
           
           $res = [ "user" => $user , "role" => $role ];
           
           if( $role == 'admin' ){
               
           }else if( $role == 'region_manager' ){
               if( isset( $user[ "relationUserRegion" ] ) ){
                   if( isset( $user[ "relationUserRegion" ][ "relationInstanceRegion" ] ) ){
                       $res[ "info" ] = [ "instance_id" => $user[ "relationUserRegion" ][ "relationInstanceRegion" ][ "instance_id" ] ];
                   }
               }
           }else if( $role == 'country_manager' ){
               if( isset( $user[ "relationUserCountry" ] ) ){
                   $res[ "info" ] = [ 
                       "country_id" => $user[ "relationUserCountry" ][ "country_id" ] 
                   ];
                   
                   if( isset( $user[ "relationUserCountry" ][ "relationCountry" ] ) ){
                       $res[ "info" ][ "country_code" ] = $user[ "relationUserCountry" ][ "relationCountry" ][ "code" ];
                       
                       if( isset( $user[ "relationUserCountry" ][ "relationCountry" ][ "relationRegion" ] ) ){
                           $res[ "info" ][ "instance_id" ] = $user[ "relationUserCountry" ][ "relationCountry" ][ "relationRegion" ][ "instance_id" ];
                       }
                   }
               }
           }else{
               
           }
        }   
	           
	    $this->authUser = $res;
	}
	
	
	protected function getAuthToken( $user ){
	    $authTokenCheck = AuthToken::find()->where( [ "user_id" => $user->id , "user_type" => 2 ] )->one();
	    if( $authTokenCheck == null ){
    	    $auth_model = new AuthToken();
    	    
    	    $auth_token = $auth_model->generateAuthTokenForWeb( $user );
    	    
    	    $expired_on = time() + ( 24 * 60 * 60 * 365 );
    	    
    	    $auth_model->user_id = $user->id;
    	    $auth_model->user_type = 2;
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
	    }else{
	        $auth_token = $authTokenCheck->generateAuthTokenForApp( $user );
	        $authTokenCheck->token = $auth_token;
	        
	        if( $authTokenCheck->validate( [ "token" ] ) ){
	            if( $authTokenCheck->save(  [ "token" ]  ) ){
	                return $auth_token;
	            }else{
	                $this->cError = $authTokenCheck->errors;
	            }
	        }else{
	            $this->cError = $authTokenCheck->errors;
	        }
	    }
	    
	    return "";
	}
	
	
	
	public static function accessControlCallBack(){
	    $msg = [
	        'status' => 0 ,
	        'error' => [
	            'type' => 'UnAuthorized Access !!!' ,
	            'message' => 'You are not authorized to access the requested page !' ,
	            'httpStatus' => 503
	        ]
	    ];
	    
	    header( "Content-Type: application/json" );
	    echo Json::encode( $msg ); \Yii::$app->end( 503 );
	}
}
