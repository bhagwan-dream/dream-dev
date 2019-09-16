<?php
namespace api\modules\v1\modules\setting\controllers;

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\Setting;
use common\models\User;

class CheckNewUserController extends \common\controllers\aController
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
    
    public function actionIndex(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $uid = \Yii::$app->user->id;
        if( null != \Yii::$app->request->get( 'id' ) ){
            $uid = \Yii::$app->request->get( 'id' );
        }
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data['username'] ) ){
                
                $user = User::findOne([ 'username' => $r_data['username'] ]);
                
                if( $user != null ){
                    
                    $response = [
                        'status' => 0 ,
                        "success" => [
                            "message" => "Username already exist!"
                        ]
                    ];
                    
                }else{
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Username allowed!"
                        ]
                    ];
                }
                
            }else{
                
                $user = User::find()->select(['role','profit_loss','balance','name','username'])
                ->where([ 'id' => $uid ])->asArray()->one();
                
                if( $user != null ){

                    $username = $user['name'] .' [ '.$user['username'].' ]';

                    $profitloss = $user['profit_loss'];
                    $balance = $user['balance'];
                    if( $uid == 1 && $user['role'] == 1 ){
                        $profitloss = 100;
                    }
                    
                    $response = [
                        'status' => 1 ,
                        "data" => [
                            "profit_loss" => $profitloss,
                            "balance" => $balance,
                            "username" => $username
                        ]
                    ];
                    
                }
                
            }
            
            
        }
        
        return $response;
    }
    
}
