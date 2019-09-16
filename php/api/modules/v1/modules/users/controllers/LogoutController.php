<?php
namespace api\modules\v1\modules\users\controllers;

use common\models\User;
use api\modules\v1\modules\users\models\AuthToken;
use Yii;

class LogoutController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin','agent1','agent2','sessionuser','sessionuser2','subadmin','agent' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    public function actionIndex(){
        
        $response = [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $uid = \Yii::$app->user->id;
        $user = User::findOne( [ 'id' =>  $uid, 'status' => 1 ] );
        if( $user != null ){
            $user->is_login = 0;
            //$user->updated_at = time();
            if( $user->save( [ 'is_login'] ) ){
                //AuthToken::deleteAll(['user_id' => \Yii::$app->user->id ]);

//                Yii::$app->cache->flush();
//                $cookies = Yii::$app->response->cookies;
//                $cookies->remove('user_cookies');

                $response =  [ "status" => 1 , "success" => [ "message" => "Logout successfully !" ] ];
//                if( \Yii::$app->db->createCommand()
//                    ->delete('auth_token', [ 'user_id' => $uid ])
//                    ->execute() ){
//                    Yii::$app->user->logout();
//                    $response =  [ "status" => 1 , "success" => [ "message" => "Logout successfully !" ] ];
//                }else{
//                    $response =  [ "status" => 1 , "success" => [ "message" => "Logout successfully !!" ] ];
//                }

            }else{
                $response =  [ "status" => 0 , "error" => [ "message" => "Something wrong!" ,  "httpStatus" => '0' ] ];
            }
        }
        
        return $response;
        
    }
}
