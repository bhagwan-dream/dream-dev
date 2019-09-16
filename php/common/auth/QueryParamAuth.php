<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace common\auth;

use yii\filters\auth\AuthMethod;

/**
 * QueryParamAuth is an action filter that supports the authentication based on the access token passed through a query parameter.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class QueryParamAuth extends AuthMethod
{
    /**
     * @var string the parameter name for passing the access token
     */
    public $tokenParam 	= 'token';
    
    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $accessToken = \Yii::$app->request->get( 'token' );
        
        if ($accessToken == null) {
        	$this->unAuthorizedAccess();
        }
        
        if (is_string($accessToken)) {
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
            	return $identity;
            }else{
            	$this->unAuthorizedAccess();
            }
        }
        
        return null;
    }
    
    /**
     * @inheritdoc
     */
    public function unAuthorizedAccess(){
        
        $arr = [
            'code' => 0 ,
            'errors' => [
                'type' => 'UnAuthorized Access !' ,
                'message' => 'You are not authorized to access the requested page !' ,
                'httpStatus' => 503
            ]
        ];
        
        header( "Content-Type: application/json" ); 
        echo json_encode( $arr ); exit;
    }
    
}
