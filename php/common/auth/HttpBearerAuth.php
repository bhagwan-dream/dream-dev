<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace common\auth;

use yii\filters\auth\AuthMethod;
use yii\helpers\Json;

/**
 * HttpBearerAuth is an action filter that supports the authentication method based on HTTP Bearer token.
 *
 * You may use HttpBearerAuth by attaching it as a behavior to a controller or module, like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'bearerAuth' => [
 *             'class' => \yii\filters\auth\HttpBearerAuth::className(),
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HttpBearerAuth extends AuthMethod
{
    /**
     * @var string the HTTP authentication realm
     */
    public $realm = 'api';

    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
       
        $authHeader = $request->getHeaders()->get('Authorization');
       
        if ($authHeader !== null && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            $identity = $user->loginByAccessToken($matches[1], get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }

        $this->unAuthorizedAccess();
    }

    /**
     * @inheritdoc
     */
    public function unAuthorizedAccess(){
        $msg = [
            'status' => 0 ,
            'error' => [
                'type' => 'UnAuthorized Access !!' ,
                'message' => 'You are not authorized to access the requested page !' ,
                'httpStatus' => 503
            ]
        ];
        
        header( "Content-Type: application/json" ); 
        echo Json::encode( $msg ); \Yii::$app->end( 503 );
    }
}
