<?php
namespace api\modules\v1\modules\events\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\CricketJackpot;
use common\models\CricketJackpotSetting;

class CricketJackpotController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin' , 'sessionuser' , 'sessionuser2' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];

        return $behaviors;
    }


    //action create
    public function actionCreate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        // echo '<pre>'; print_r($request_data); die;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            //echo '<pre>';print_r($r_data);die;

            $model = new CricketJackpot();
            $model->event_id = $r_data[ 'event_id' ];
            $model->market_id = '1.'.time().'-JKPT';
            $model->team_a = $r_data[ 'team_a' ];
            $model->team_a_player = $r_data[ 'team_a_player' ];
            $model->team_b = $r_data[ 'team_b' ];
            $model->team_b_player = $r_data[ 'team_b_player' ];
            $model->rate = $r_data[ 'rate' ];
            $model->status = 0;
            $model->created_at = $model->updated_at = time();

            if( $model->save() ){

                $setting = CricketJackpotSetting::findOne([ 'event_id' => $r_data[ 'event_id' ] ]);

                if( $setting == null ){

                    $setting = new CricketJackpotSetting();

                    $setting->event_id = $model->event_id;
                    $setting->rules = "<b>Big jackpot rules</b>
                                        <p>After toss the bet will not allowed.</p> 
                                        <p>Any Cheated bets will be automatically deleted.</p> 
                                        <p>In a 20-20 over matches, both sides or both team at least played 10 overs than result will be valid other wise game will be abandoned</p>
                                        <p>In 50-50 over match(one day) both side or both team at least play 15 over than result will be valid other wise game will be abandoned.</p>
                                        <p>In Test match both side or both team at least play 30 over than result will be valid other wise game will be abandoned.</p>
                                        <p>If result of any jodi will be tie, all games will be abandoned.</p>";

                    $setting->created_at = $setting->updated_at = time();

                    if( $setting->save() ){
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Cricket Jackpot create successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!" ,
                        ];
                    }

                }else{
                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Cricket Jackpot create successfully!"
                        ]
                    ];
                }

            }else{
                $response[ "error" ] = [
                    "message" => "Something wrong! event not updated!!!" ,
                ];
            }
            $response;

        }
        return $response;

    }


    //action Update
    public function actionUpdate(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        // echo '<pre>'; print_r($request_data); die;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            //echo '<pre>'; print_r($r_data); die;

            $id =  $r_data[ 'id'];
            $event_id = $r_data[ 'event_id'];
            $market_id = $r_data[ 'market_id'];
            //$team_a = $r_data[ 'team_a' ];
            $team_a_player = $r_data[ 'team_a_player'];
            //$team_b = $r_data[ 'team_b' ];
            $team_b_player = $r_data[ 'team_b_player'];
            $rate = $r_data[ 'rate' ];
            $where = [ 'id' => $id , 'event_id' => $event_id , 'market_id' => $market_id ];

            $checkData = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')
                ->where($where)->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $updateData = [ 'team_a_player' => $team_a_player , 'team_b_player' => $team_b_player, 'rate' => $rate , 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , $where )->execute() ){

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Cricket Jackpot Update successfully!"
                        ]
                    ];

                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Cricket Jackpot not updated!" ,
                    ];
                }
            }
            else{
                $response[ "error" ] = [
                    "message" => "Something wrong! Cricket Jackpot not updated!" ,
                ];
            }
            $response;

        }
        return $response;

    }


    //action Setting
    public function actionSetting(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        // echo '<pre>'; print_r($request_data); die;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $event_id = $r_data[ 'event_id'];
            $min_stack = $r_data[ 'min_stack' ];
            $max_stack = $r_data[ 'max_stack'];
            $max_profit = $r_data[ 'max_profit'];
            $max_profit_limit = $r_data[ 'max_profit_limit' ];
            $suspend_timer = $r_data[ 'suspend_timer'];
            $rules = $r_data[ 'rules' ];
            $highlight_msg = $r_data[ 'highlight_msg' ];
            $betDelay = $r_data[ 'bet_delay' ];

            $jackpotSetting = (new \yii\db\Query())
                            ->select(['id'])->from('cricket_jackpot_setting')
                            ->where([ 'event_id' => $event_id ])
                            ->createCommand(Yii::$app->db2)->queryOne();

            if( $jackpotSetting != null ){

                $updateData = [ 'bet_delay' => $betDelay ,'min_stack' => $min_stack,'max_stack' => $max_stack,'max_profit' => $max_profit,'max_profit_limit' => $max_profit_limit,'suspend_timer' => $suspend_timer,'rules' => $rules, 'highlight_msg' => $highlight_msg ,'updated_at' => time()];

                \Yii::$app->db->createCommand()
                    ->update('cricket_jackpot_setting', $updateData , [ 'event_id' => $event_id ] )
                    ->execute();

                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => "Update successfully!"
                    ]
                ];

            }else{

                $model = new CricketJackpotSetting();

                $model->event_id = $event_id;
                $model->min_stack = $min_stack;
                $model->max_stack = $max_stack;
                $model->max_profit = $max_profit;
                $model->max_profit_limit = $max_profit_limit;
                $model->suspend_timer = $suspend_timer;

                if( $rules == null ){
                    $model->rules = "<b>Big jackpot rules</b>
                                        <p>In 20-20 match both side will played 10 over minimum</p> 
                                        <p>In one day match both side will played in 15 over</p> 
                                        <p>In test match both side wheel play minimum 30 over</p>
                                        <p>If any Jodi I will tie the game is abandoned</p>";
                }else{
                    $model->rules = $rules;
                }


                $model->highlight_msg = $highlight_msg;
                $model->bet_delay = $betDelay;
                $model->status = 1;
                $model->created_at = $model->updated_at = time();

                if( $model->save() ){
                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Jackpot setting saved successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                    "message" => "Something wrong! setting not updated!" ,
                    ];
                }
            }

        }
        return $response;

    }



    //action Status
    public function actionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $event_id = $r_data[ 'event_id'];
            $market_id = $r_data[ 'market_id'];
            $where = [ 'event_id' => $event_id , 'market_id' => $market_id ];

            $checkData = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $status = $r_data[ 'status'] == 1 ? 0 : 1;

                $updateData = ['status' => $status, 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , $where )->execute() ){
                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Status change successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Status not updated!",
                    ];
                }

            } else {
                $response[ "error" ] = [
                    "message" => "Something wrong! Status not updated!",
                ];
            }

        }
        return $response;

    }

    //action Suspended
    public function actionSuspended(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $event_id = $r_data[ 'event_id'];
            $market_id = $r_data[ 'market_id'];

            $where = [ 'event_id' => $event_id , 'market_id' => $market_id ];

            $checkData = (new \yii\db\Query())
                ->select(['id','suspended'])->from('cricket_jackpot')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $suspended = $checkData['suspended'] == 'Y' ? 'N' : 'Y';

                $updateData = ['suspended' => $suspended, 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , $where )->execute() ){
                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Status change successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Status not updated!",
                    ];
                }

            } else {
                $response[ "error" ] = [
                    "message" => "Something wrong! Status not updated!",
                ];
            }

        }
        return $response;

    }

    //action Delete
    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $event_id = $r_data[ 'event_id'];
            $market_id = $r_data[ 'market_id'];

            $where = [ 'event_id' => $event_id , 'market_id' => $market_id ];

            $checkData = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')->where($where)
                ->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $updateData = ['status' => 2, 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , $where )->execute() ){

                    \Yii::$app->db->createCommand()->update('place_bet', [ 'status' => 0 ] ,
                        [ 'session_type' => 'jackpot' , 'event_id' => $event_id , 'market_id' => $market_id , 'bet_status' => 'Pending' ] )->execute();

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Delete successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Status not updated!",
                    ];
                }

            } else {
                $response[ "error" ] = [
                    "message" => "Something wrong! Status not updated!",
                ];
            }

        }
        return $response;

    }

    //action Delete All
    public function actionDeleteAll(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $event_id = $r_data[ 'event_id'];

            $checkData = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')->where([ 'event_id' => $event_id ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $updateData = ['status' => 2, 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , [ 'event_id' => $event_id ] )->execute() ){

                    \Yii::$app->db->createCommand()->update('place_bet', [ 'status' => 0 ] ,
                        [ 'event_id' => $event_id , 'session_type' => 'jackpot' , 'bet_status' => 'Pending' ] )->execute();

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Deleted all successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Status not updated!",
                    ];
                }

            } else {
                $response[ "error" ] = [
                    "message" => "Something wrong! Status not updated!",
                ];
            }

        }
        return $response;

    }

    //action Suspended All
    public function actionSuspendedAll(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            $event_id = $r_data[ 'event_id'];

            $checkData = (new \yii\db\Query())
                ->select(['id'])->from('cricket_jackpot')->where([ 'event_id' => $event_id ])
                ->createCommand(Yii::$app->db2)->queryOne();

            if(!empty($checkData)){

                $updateData = ['suspended' => 'Y', 'updated_at' => time()];

                if( \Yii::$app->db->createCommand()->update('cricket_jackpot', $updateData , [ 'event_id' => $event_id ] )->execute() ){

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Suspended all successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! Status not updated!",
                    ];
                }

            } else {
                $response[ "error" ] = [
                    "message" => "Something wrong! Status not updated!",
                ];
            }

        }
        return $response;

    }

    //action Manage
    public function actionManage(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        //echo '<pre>';print_r($request_data);die;
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'event_id' ])){

                $eventId = $r_data[ 'event_id'];

                $dataList = $dataSetting = null;

                $jackpotData = (new \yii\db\Query())
                    ->select(['id','event_id','market_id','team_a','team_b','team_a_player','team_b_player','rate','game_over','win_result','suspended','status'])
                    ->from('cricket_jackpot')
                    ->where(['event_id' => $eventId, 'status' => [0,1] ])
                    ->orderBy(['id' => SORT_ASC])
                    ->all();

                if( $jackpotData != null ){
                    $dataList = $jackpotData;
                }

                $jackpotSetting = (new \yii\db\Query())
                    ->select('*')->from('cricket_jackpot_setting')
                    ->where([ 'event_id' => $eventId ])
                    ->createCommand(Yii::$app->db2)->queryOne();

                if( $jackpotSetting != null ){
                    $dataSetting = $jackpotSetting;
                }

                $response = [ "status" => 1 , "data" => [ "dataList" => $dataList , "dataSetting" => $dataSetting ] ];
            }
        }
        return $response;
    }


    public function actionImportXls(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if(!empty($r_data)){

                $event_id = $r_data['event_id'];

                $newData = [];
                $i = 1;
                foreach ( $r_data['data'] as $data ) {

                    $marketId = '1.'.(time()-$i).'-JKPT';

                    $newData[] = [
                        'event_id' => $event_id,
                        'market_id' => $marketId,
                        'team_a' => $data[ 'Team A' ],
                        'team_b' => $data[ 'Team B' ],
                        'team_a_player' => $data[ 'Team A Player' ],
                        'team_b_player' => $data[ 'Team B Player' ],
                        'rate' => $data[ 'Rate' ],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];

                    $i++;
                }

                if( $newData != null ){

                    $attr = ['event_id','market_id','team_a','team_b','team_a_player','team_b_player','rate','created_at','updated_at'];

                    Yii::$app->db->createCommand()
                        ->batchInsert('cricket_jackpot', $attr , $newData)->execute();

                    $setting = CricketJackpotSetting::findOne([ 'event_id' => $event_id ]);

                    if( $setting == null ){

                        $setting = new CricketJackpotSetting();

                        $setting->event_id = $event_id;
                        $setting->rules = "<b>Big jackpot rules</b>
                                        <p>After toss the bet will not allowed.</p> 
                                        <p>Any Cheated bets will be automatically deleted.</p> 
                                        <p>In a 20-20 over matches, both sides or both team at least played 10 overs than result will be valid other wise game will be abandoned</p>
                                        <p>In 50-50 over match(one day) both side or both team at least play 15 over than result will be valid other wise game will be abandoned.</p>
                                        <p>In Test match both side or both team at least play 30 over than result will be valid other wise game will be abandoned.</p>
                                        <p>If result of any jodi will be tie, all games will be abandoned.</p>
                                        <p>Only For Big Jackpot Me Jo Jodi Name Se Hogi Usame Jo Player Nahi Khelega Uska 00 Gina Jayega</p>";
                        $setting->created_at = $setting->updated_at = time();

                        if( $setting->save() ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Cricket Jackpot Added successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!" ,
                            ];
                        }

                    }else{
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Cricket Jackpot Added successfully!"
                            ]
                        ];
                    }

                }
            }
        }
        return $response;

    }

}