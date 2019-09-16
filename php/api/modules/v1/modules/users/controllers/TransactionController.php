<?php

namespace api\modules\v1\modules\users\controllers;

use common\models\User;
use yii\helpers\ArrayHelper;
use common\models\TransactionHistory;
use common\models\TempTransactionHistory;
use common\models\EventsPlayList;
use common\models\PlaceBet;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSession;
use common\models\MarketType;
use common\models\ManualSessionLottery;

class TransactionController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors ['access'] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => ['admin', 'subadmin','agent1', 'agent2', 'client'],
                ],
            ],
            "denyCallback" => [\common\controllers\cController::className(), 'accessControlCallBack']
        ];

        return $behaviors;
    }


    public function actionIndexOLD()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }

        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];


        $userName = 'User Not Found!';
        $lastdate = date('Ymd' , strtotime(date('Ymd') . ' -3 day') );
        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $eventArr = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('transaction_history')
                ->where(['user_id' => $uid, 'status' => 1])
                ->andWhere(['is_cash' => 0])
                ->andWhere(['!=', 'event_id', 0])
                ->andWhere(['>' , 'created_at' , strtotime($lastdate) ])
                ->orderBy(['id' => SORT_DESC])
                ->groupBy(['event_id'])
                //->limit(15)
                //->offset(0)
                ->all();


            if ($eventArr != null) {
                $transEventDataArr = [];
                $totalEventPl = $totalEventComm = 0;
                foreach ($eventArr as $event) {

                    $profitEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $lossEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $commEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $profitEventVal = $lossEventVal = $commEventVal = $totalEvent = 0;
                    if (isset($profitEvent['profit']) && $profitEvent['profit'] != null) {
                        $profitEventVal = $profitEvent['profit'];
                    }

                    if (isset($lossEvent['loss']) && $lossEvent['loss'] != null) {
                        $lossEventVal = $lossEvent['loss'];
                    }

                    $totalEvent = $profitEventVal - $lossEventVal;

                    if (isset($commEvent['comm']) && $commEvent['comm'] != null) {
                        $commEventVal = $commEvent['comm'] + 0;
                    }


                    $marketArr = $transMarketArr = [];
                    $marketArr = (new \yii\db\Query())
                        ->select(['market_id'])
                        ->from('transaction_history')
                        ->where(['user_id' => $uid, 'event_id' => $event['event_id'], 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['market_id'])
                        ->all();

                    if ($marketArr != null) {
                        $transMarketArr = [];
                        $totalMarketPl = $totalMarketComm = 0;

                        foreach ($marketArr as $market) {

                            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $comm = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $profitVal = $lossVal = $commVal = $total = 0;
                            if (isset($profit['profit']) && $profit['profit'] != null) {
                                $profitVal = $profit['profit'];
                            }

                            if (isset($loss['loss']) && $loss['loss'] != null) {
                                $lossVal = $loss['loss'];
                            }

                            $total = $profitVal - $lossVal;

                            $totalMarketPl += $total;

                            if (isset($comm['comm']) && $comm['comm'] != null) {
                                $commVal = $comm['comm'] + 0;
                            }

                            $totalMarketComm += $commVal;

                            $transMarketArr[] = [
                                'time' => $this->getMarketTime($market['market_id']),
                                'market_id' => $market['market_id'],
                                'event_id' => $event['event_id'],
                                'description' => $this->getMarketName($market['market_id'],$event['event_id']),
                                'profitLoss' => round($total,2),
                                'comm' => round($commVal,2)
                            ];

                        }

                        $transMarketDataArr = [
                            'list' => $transMarketArr,
                            'totalPl' => round($totalMarketPl,2),
                            'totalComm' => round($totalMarketComm,2)
                        ];

                    }

                    $totalEventPl += $totalEvent;
                    $totalEventComm += $commEventVal;

                    if( $request_data != null && isset( $request_data['filter'] ) ) {

                        $filter = $request_data['filter'];

                        if( ( isset( $filter['title'] ) && $filter['title'] != '' )
                            || ( ( isset( $filter['start'] ) && $filter['start'] != '' )
                                && ( isset( $filter['end'] ) && $filter['end'] != '' ) ) ){

                            if( ( isset( $filter['start'] ) && $filter['start'] != '' )
                                && ( isset( $filter['end'] ) && $filter['end'] != '' ) ){

                                $desc = $this->getEventName($event['event_id']);
                                $time = $this->getEventTime($event['event_id']);

                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($desc,$filter['title']) > 0 ){

                                        $transEventArr[] = [
                                            'time' => $time,
                                            'event_id' => $event['event_id'],
                                            'description' => $desc,
                                            'profitLoss' => round($totalEvent,2),
                                            'comm' => round($commEventVal,2),
                                            'transMarketDataArr' => $transMarketDataArr
                                        ];

                                    }
                                }

                                $startDate = strtotime($filter[ "start" ]);
                                $endDate = strtotime($filter[ "end" ] . ' 23:59:59');

                                if( ($time/1000) >= $startDate && ($time/1000) <= $endDate ){
                                    $transEventArr[] = [
                                        'time' => $time,
                                        'event_id' => $event['event_id'],
                                        'description' => $desc,
                                        'profitLoss' => round($totalEvent,2),
                                        'comm' => round($commEventVal,2),
                                        'transMarketDataArr' => $transMarketDataArr
                                    ];
                                }

                            }else{

                                $desc = $this->getEventName($event['event_id']);
                                $time = $this->getEventTime($event['event_id']);

                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($desc,$filter['title']) > 0 || strpos(strtolower($desc),$filter['title']) > 0){
                                        $transEventArr[] = [
                                            'time' => $time,
                                            'event_id' => $event['event_id'],
                                            'description' => $desc,
                                            'profitLoss' => round($totalEvent,2),
                                            'comm' => round($commEventVal,2),
                                            'transMarketDataArr' => $transMarketDataArr
                                        ];
                                    }
                                }

                            }

                        }else{

                            $transEventArr[] = [
                                'time' => $this->getEventTime($event['event_id']),
                                'event_id' => $event['event_id'],
                                'description' => $this->getEventName($event['event_id']),
                                'profitLoss' => round($totalEvent,2),
                                'comm' => round($commEventVal,2),
                                'transMarketDataArr' => $transMarketDataArr
                            ];

                        }

                    }

                }

                $transEventDataArr = [
                    'list' => $transEventArr,
                    'totalPl' => round($totalEventPl,2),
                    'totalComm' => round($totalEventComm,2)
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transEventDataArr ]];
    }

    public function actionIndex()
    {
        //Other Transection

        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }
        $sport_id='all'; $title=''; $diff='';
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if(isset($role['subadmin'])){
            $uid = 1;
        }

        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];


        $userName = 'User Not Found!';

        $startdate = date('Ymd' );
        $lastdate = date('Ymd' , strtotime(date('Ymd') . ' -4 day') );

        if(!empty($request_data)) {

            // $filter = $request_data['filter'];
            $sport_id=$request_data['filter_status'];
            $title=$request_data['filter_event'];
            if( ( isset( $request_data['filter_event'] ) && $request_data['filter_event'] != '' )
                || ( ( isset( $request_data['filter_start'] ) && $request_data['filter_start'] != '' )
                    && ( isset( $request_data['filter_end'] ) && $request_data['filter_end'] != '' ) ) ){

                $startdate = date('Ymd' , strtotime( $request_data['filter_start'] ) );
                $lastdate = date('Ymd' , strtotime( $request_data['filter_end'] ) );
                $lastdate = date('Ymd' , strtotime( $lastdate . ' +1 day') );
                $diff = abs($lastdate - $startdate);

                if( $diff > 5 ){

                    // return ["status" => 0, "data" => [],'message' => 'Select Max 5 day for search result!'];

                }

            }
        }


        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            //if( $evnts != null ){


            if(empty($sport_id)){
                $sport_id='all';
            }

            if($sport_id=='all'){
                $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1];
            }elseif ($sport_id=='jackpot') {
                $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1 ,'session_type'=> $sport_id];
            }
            else{
                $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1 ,'sport_id'=> $sport_id];
            }

            if ($title!='') {

                if(!empty($startdate) && !empty($lastdate)){
                    $marketArr = (new \yii\db\Query())
                        ->select(['event_id'])
                        ->from('transaction_history')
                        ->where($where)
                        ->andWhere(['!=', 'event_id', 0])
                        ->andFilterWhere( [ "like" , "description" ,$title ])
                        ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['event_id'])
                        ->all();

                }else{
                    $marketArr = (new \yii\db\Query())
                        ->select(['event_id'])
                        ->from('transaction_history')
                        ->where($where)
                        ->andFilterWhere( [ "like" , "description" ,$title ])
                        ->andWhere(['!=', 'event_id', 0])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['event_id'])
                        ->all();
                }
            }else{

                if(!empty($startdate) && !empty($lastdate)){
                    $marketArr = (new \yii\db\Query())
                        ->select(['event_id'])
                        ->from('transaction_history')
                        ->where($where)
                        ->andWhere(['!=', 'event_id', 0])
                        ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['event_id'])
                        ->all();

                }else{
                    $marketArr = (new \yii\db\Query())
                        ->select(['event_id'])
                        ->from('transaction_history')
                        ->where($where)
                        ->andWhere(['!=', 'event_id', 0])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['event_id'])
                        ->all();
                }
            }



            $eventIDList=[];   $transEventArr=[];   $eventdescription="";
            foreach ($marketArr as  $value) {
                $eventIDList[]= $value['event_id'];

            }

            //}

            //echo '<pre>';print_r($eventArr);die;

            // if ($eventArr != null) {
            //  foreach ($eventArr as $event) {

            if (!empty($eventIDList)) {
                $eventIDList= array_unique($eventIDList);
                $commEventVal=0; $totalEvent=0;$transEventDataArr = [];
                $totalEventPl = $totalEventComm = 0;
                foreach ($eventIDList as $event_id) {
                    if ($sport_id!='999999') {
                        $event = (new \yii\db\Query())
                            ->select(['e.sport_id','e.event_id','e.event_time','e.event_name','mr.result'])
                            ->from('events_play_list as e')
                            ->join('LEFT JOIN', 'market_result as mr', 'mr.event_id = e.event_id AND mr.session_type = "match_odd"')
                            ->where(['e.event_id'=>$event_id])
                            ->one();
                    }else{
                        $event = (new \yii\db\Query())
                            ->select(['e.sport_id','e.event_id','e.event_time','e.event_name'])
                            ->from('events_play_list as e')
                            ->where(['e.event_id'=>$event_id])
                            ->one();
                    }

                    $profitEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $lossEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $commEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $profitEventVal = $lossEventVal = $commEventVal = $totalEvent = 0;
                    if (isset($profitEvent['profit']) && $profitEvent['profit'] != null) {
                        $profitEventVal = $profitEvent['profit'];
                    }

                    if (isset($lossEvent['loss']) && $lossEvent['loss'] != null) {
                        $lossEventVal = $lossEvent['loss'];
                    }

                    $totalEvent = $profitEventVal - $lossEventVal;

                    if (isset($commEvent['comm']) && $commEvent['comm'] != null) {
                        $commEventVal = $commEvent['comm'] + 0;
                    }


                    $marketArr = $transMarketArr = [];


                    $totalEventPl += $totalEvent;
                    $totalEventComm += $commEventVal;

                    $time = $event['event_time'];
                    if ($sport_id!='999999') {
                        $desc = $this->getEventNameNew($event);
                    }else{

                        $desc =$event['sport_id'] . ' > ' . $event['event_name'];
                        $rsevent = (new \yii\db\Query())
                            ->select(['round_id'])
                            ->from('transaction_history')
                            ->where(['event_id'=>$event['event_id']])
                            ->one();
                        if( $rsevent['round_id'] != 0 ){

                            $desc = $desc.' ( Result : '.$rsevent['round_id'].' )';
                        }
                        // $desc = $this->getEventNameNew($event);
                    }
                    $transEventArr[] = [
                        'time' => $time,
                        'event_id' => $event['event_id'],
                        'description' => $desc,
                        'profitLoss' => round($totalEvent,2),
                        'comm' => round($commEventVal,2),
                        'transMarketDataArr' => $transMarketDataArr
                    ];


                }

                $transEventDataArr = [
                    'list' => $transEventArr,
                    'totalPl' => round($totalEventPl,2),
                    'totalComm' => round($totalEventComm,2)
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transEventDataArr ]];
    }

    //action Index
    public function actionIndex11092019()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }

        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if(isset($role['subadmin'])){
            $uid = 1;
        }

        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];


        $userName = 'User Not Found!';

        $startdate = date('Ymd' );
        $lastdate = date('Ymd' , strtotime(date('Ymd') . ' -4 day') );

        if( $request_data != null && isset( $request_data['filter'] ) ) {

            $filter = $request_data['filter'];

            if( ( isset( $filter['title'] ) && $filter['title'] != '' )
                || ( ( isset( $filter['start'] ) && $filter['start'] != '' )
                    && ( isset( $filter['end'] ) && $filter['end'] != '' ) ) ){

                $startdate = date('Ymd' , strtotime( $filter['start'] ) );
                $lastdate = date('Ymd' , strtotime( $filter['end'] ) );

                $diff = abs($lastdate - $startdate);

                if( $diff > 5 ){

                    return ["status" => 0, "data" => [],'message' => 'Select Max 5 day for search result!'];

                }

            }
        }


        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

			//if( $evnts != null ){
				$eventArr = (new \yii\db\Query())
					->select(['e.sport_id','e.event_id','e.event_time','e.event_name','mr.result'])
					->from('events_play_list as e')
					->join('LEFT JOIN', 'market_result as mr', 'mr.event_id = e.event_id AND mr.session_type = "match_odd"')
					->where(['e.game_over' => 'YES', 'e.status' => 1 ])
					->orWhere(['e.play_type' => 'IN_PLAY', 'e.status' => 1])
					//->andWhere(['>' , 'e.created_at' , strtotime($lastdate) ])
                    ->andWhere(['between', 'e.created_at', strtotime($startdate), strtotime($lastdate) ])
					->orderBy(['mr.updated_at' => SORT_DESC])
					->all();
			//}

            //echo '<pre>';print_r($eventArr);die;

            if ($eventArr != null) {
                $transEventDataArr = [];
                $totalEventPl = $totalEventComm = 0;
                foreach ($eventArr as $event) {

                    $profitEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $lossEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $commEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $profitEventVal = $lossEventVal = $commEventVal = $totalEvent = 0;
                    if (isset($profitEvent['profit']) && $profitEvent['profit'] != null) {
                        $profitEventVal = $profitEvent['profit'];
                    }

                    if (isset($lossEvent['loss']) && $lossEvent['loss'] != null) {
                        $lossEventVal = $lossEvent['loss'];
                    }

                    $totalEvent = $profitEventVal - $lossEventVal;

                    if (isset($commEvent['comm']) && $commEvent['comm'] != null) {
                        $commEventVal = $commEvent['comm'] + 0;
                    }


                    $marketArr = $transMarketArr = [];
                    $marketArr = (new \yii\db\Query())
                        ->select(['market_id'])
                        ->from('transaction_history')
                        ->where(['user_id' => $uid, 'event_id' => $event['event_id'], 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->orderBy(['id' => SORT_DESC])
                        ->groupBy(['market_id'])
                        ->all();

                    if ($marketArr != null) {
                        $transMarketArr = [];
                        $totalMarketPl = $totalMarketComm = 0;

                        foreach ($marketArr as $market) {

                            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $comm = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $profitVal = $lossVal = $commVal = $total = 0;
                            if (isset($profit['profit']) && $profit['profit'] != null) {
                                $profitVal = $profit['profit'];
                            }

                            if (isset($loss['loss']) && $loss['loss'] != null) {
                                $lossVal = $loss['loss'];
                            }

                            $total = $profitVal - $lossVal;

                            $totalMarketPl += $total;

                            if (isset($comm['comm']) && $comm['comm'] != null) {
                                $commVal = $comm['comm'] + 0;
                            }

                            $totalMarketComm += $commVal;

                            $transMarketArr[] = [
                                'time' => $this->getMarketTime($market['market_id']),
                                'market_id' => $market['market_id'],
                                'event_id' => $event['event_id'],
                                'description' => $this->getMarketName($market['market_id'],$event['event_id']),
                                'profitLoss' => round($total,2),
                                'comm' => round($commVal,2)
                            ];

                        }

                        $transMarketDataArr = [
                            'list' => $transMarketArr,
                            'totalPl' => round($totalMarketPl,2),
                            'totalComm' => round($totalMarketComm,2)
                        ];

                    }

                    $totalEventPl += $totalEvent;
                    $totalEventComm += $commEventVal;

                    if( $request_data != null && isset( $request_data['filter'] ) ) {

                        $filter = $request_data['filter'];

                        if( ( isset( $filter['title'] ) && $filter['title'] != '' )
                            || ( ( isset( $filter['start'] ) && $filter['start'] != '' )
                            && ( isset( $filter['end'] ) && $filter['end'] != '' ) ) ){

                            if( ( isset( $filter['start'] ) && $filter['start'] != '' )
                                && ( isset( $filter['end'] ) && $filter['end'] != '' ) ){

                                $desc = $this->getEventNameNew($event);
                                //$desc = $this->getEventName($event['event_id']);
                                $time = $event['event_time'];//$this->getEventTime($event['event_id']);

                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($desc,$filter['title']) > 0 ){

										if( round($totalEvent,2) != 0 ){
											$transEventArr[] = [
												'time' => $time,
                                                'event_id' => $event['event_id'],
												'description' => $desc,
												'profitLoss' => round($totalEvent,2),
												'comm' => round($commEventVal,2),
												'transMarketDataArr' => $transMarketDataArr
											];
										} 

                                    }
                                }

                                $startDate = strtotime($filter[ "start" ]);
                                $endDate = strtotime($filter[ "end" ] . ' 23:59:59');

                                if( ($time/1000) >= $startDate && ($time/1000) <= $endDate ){
									
									if( round($totalEvent,2) != 0 ){
									
										$transEventArr[] = [
											'time' => $time,
                                            'event_id' => $event['event_id'],
											'description' => $desc,
											'profitLoss' => round($totalEvent,2),
											'comm' => round($commEventVal,2),
											'transMarketDataArr' => $transMarketDataArr
										];
									}
                                }

                            }else{

                                $desc = $this->getEventNameNew($event);
                                //$desc = $this->getEventName($event['event_id']);
                                $time = $event['event_time'];//$this->getEventTime($event['event_id']);
                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($desc,$filter['title']) > 0 || strpos(strtolower($desc),$filter['title']) > 0){
                                        if( round($totalEvent,2) != 0 ){
											$transEventArr[] = [
												'time' => $time,
                                                'event_id' => $event['event_id'],
												'description' => $desc,
												'profitLoss' => round($totalEvent,2),
												'comm' => round($commEventVal,2),
												'transMarketDataArr' => $transMarketDataArr
											];
										}
                                    }
                                }

                            }

                        }else{
							if( round($totalEvent,2) != 0 ){
								$transEventArr[] = [
									'time' => $event['event_time'],//$this->getEventTime($event['event_id']),
                                    'event_id' => $event['event_id'],
									'description' => $this->getEventName($event['event_id']),
									'profitLoss' => round($totalEvent,2),
									'comm' => round($commEventVal,2),
									'transMarketDataArr' => $transMarketDataArr
								];
							}

                        }

                    }

                }

                $transEventDataArr = [
                    'list' => $transEventArr,
                    'totalPl' => round($totalEventPl,2),
                    'totalComm' => round($totalEventComm,2)
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transEventDataArr ]];
    }

    //action TeenPatti
    public function actionTeenPatti()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }

        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if(isset($role['subadmin'])){
            $uid = 1;
        }

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];
        $userName = 'User Not Found!';
        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $eventArr = (new \yii\db\Query())
                ->select(['sport_id','event_id','event_time','event_name'])
                ->from('events_play_list')
                ->where(['sport_id' => 999999, 'status' => 1])
                ->all();

            if ($eventArr != null) {

                $totalEventPl = $totalEventComm = 0;

                foreach ($eventArr as $event) {

                    $profitEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $lossEvent = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['event_id' => $event['event_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->asArray()->one();

                    $profitEventVal = $lossEventVal = $commEventVal = $totalEvent = 0;
                    if (isset($profitEvent['profit']) && $profitEvent['profit'] != null) {
                        $profitEventVal = $profitEvent['profit'];
                    }

                    if (isset($lossEvent['loss']) && $lossEvent['loss'] != null) {
                        $lossEventVal = $lossEvent['loss'];
                    }

                    $totalEvent = $profitEventVal - $lossEventVal;

                    $marketArr = $transMarketArr = [];

                    $totalEventPl += $totalEvent;

                    $transEventArr[] = [
                        'time' => $event['event_time'],//$this->getEventTime($event['event_id']),
                        'event_id' => $event['event_id'],
                        'description' => $this->getEventName($event['event_id']),
                        'profitLoss' => round($totalEvent,2),
                    ];

                }

                $transEventDataArr = [
                    'list' => $transEventArr,
                    'totalPl' => round($totalEventPl,2),
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transEventDataArr ]];
    }

    //action transaction details for event wise
    public function actionTransactionDetailMarkets()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;


        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if(isset($role['subadmin'])){
            $uid = 1;
        }
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);
        $eventId = null;

        //echo '<pre>';print_r($request_data);die;



        if (null != \Yii::$app->request->get('id')) {
            $eventId = \Yii::$app->request->get('id');
        }

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];
        $userName = 'User Not Found!';
        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2] ])->asArray()->one();

        if ( $user != null && $eventId != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $marketArr = $transMarketArr = [];
            $marketArr = (new \yii\db\Query())
                ->select(['market_id'])
                ->from('transaction_history')
                ->where(['user_id' => $uid, 'event_id' => $eventId, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->orderBy(['id' => SORT_DESC])
                ->groupBy(['market_id'])
                ->all();

            if ($marketArr != null) {
                $transMarketArr = [];
                $totalMarketPl = $totalMarketComm = 0;

                foreach ($marketArr as $market) {

                    $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $comm = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                        ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $profitVal = $lossVal = $commVal = $total = 0;
                    if (isset($profit['profit']) && $profit['profit'] != null) {
                        $profitVal = $profit['profit'];
                    }

                    if (isset($loss['loss']) && $loss['loss'] != null) {
                        $lossVal = $loss['loss'];
                    }

                    $total = $profitVal - $lossVal;

                    $totalMarketPl += $total;

                    if (isset($comm['comm']) && $comm['comm'] != null) {
                        $commVal = $comm['comm'] + 0;
                    }

                    $totalMarketComm += $commVal;

                    $transMarketArr[] = [
                        'time' => $this->getMarketTime($market['market_id']),
                        'market_id' => $market['market_id'],
                        'event_id' => $eventId,
                        'description' => $this->getMarketName($market['market_id'],$eventId),
                        'profitLoss' => round($total,2),
                        'comm' => round($commVal,2)
                    ];

                }

                $transMarketDataArr = [
                    'list' => $transMarketArr,
                    'totalPl' => round($totalMarketPl,2),
                    'totalComm' => round($totalMarketComm,2)
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transMarketDataArr ]];
    }

    //action TeenPatti
    public function actionTeenPattiMarkets()
    {
        //Other Transection
        $pagination = []; $filters = [];
        $count = 0;
        $uid = \Yii::$app->user->id;
        $eventId = null;
        if (null != \Yii::$app->request->get('id')) {
            $eventId = \Yii::$app->request->get('id');
        }

        $role = \Yii::$app->authManager->getRolesByUser($uid);
        if(isset($role['subadmin'])){
            $uid = 1;
        }

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
//        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);
//        if( $request_data != null && isset( $request_data['eventId'] ) ) {
//            $eventId = $request_data['eventId'];
//        }

        //echo '<pre>';print_r($request_data);die;

        $eventArr = $transEventArr = $transEventDataArr = $transMarketDataArr = [];
        $userName = 'User Not Found!';
        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2] ])->asArray()->one();

        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }

            $pagination = $filter_args;
        }

        if ( $user != null && $eventId != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $marketArr = $transMarketArr = [];
            $query = (new \yii\db\Query())
                ->select(['market_id'])
                ->from('transaction_history')
                ->where(['user_id' => $uid, 'event_id' => $eventId, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->orderBy(['id' => SORT_DESC])
                ->groupBy(['market_id']);
                //->all();

            if( $filters != null ){
                if( ( isset( $filters[ "start" ] ) && $filters[ "start" ] != '' )
                    && ( isset( $filters[ "end" ] ) && $filters[ "end" ] != '' ) ){
                    $startDate = strtotime($filters[ "start" ]);
                    $endDate = strtotime($filters[ "end" ] . ' 23:59:59');
                    $query->andFilterWhere( ['between','created_at',$startDate,$endDate] );
                }

            }

            $countQuery = clone $query; $count =  $countQuery->count();

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $marketArr = $query->all();

            if ($marketArr != null) {
                $transMarketArr = [];
                $totalMarketPl = $totalMarketComm = 0;

                foreach ($marketArr as $market) {

                    $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $profitVal = $lossVal = $commVal = $total = 0;
                    if (isset($profit['profit']) && $profit['profit'] != null) {
                        $profitVal = $profit['profit'];
                    }

                    if (isset($loss['loss']) && $loss['loss'] != null) {
                        $lossVal = $loss['loss'];
                    }

                    $total = $profitVal - $lossVal;

                    $totalMarketPl += $total;

                    $transMarketArr[] = [
                        'time' => $this->getMarketTime($market['market_id']),
                        'market_id' => $market['market_id'],
                        'event_id' => $eventId,
                        'description' => $this->getMarketName($market['market_id'],$eventId),
                        'profitLoss' => round($total,2),
                    ];

                }

                $transMarketDataArr = [
                    'list' => $transMarketArr,
                    'totalPl' => round($totalMarketPl,2),
                ];

            }

        }

        return ["status" => 1, "data" => [ "userName" => $userName , "items" => $transMarketDataArr , "count" => $count ]];
    }

    public function getMarketName($marketId,$eventId)
    {
        $description = 'No data';
        if( in_array( $eventId , [56767,67564,87564] ) ){

//            if( $eventId == 56767 ){ $description = 'Teen Patti'; }
//            elseif ( $eventId == 67564 ){ $description = 'Poker'; }
//            elseif ( $eventId == 87564 ){ $description = 'Andar Bahar'; }
//            else { $description = 'Teen Patti'; }

            $marketResult = (new \yii\db\Query())
                ->select(['description','round_id'])
                ->from('teen_patti_result')
                ->where([ 'market_id' => $marketId,'status' => 1 ])
                ->one();

            if( $marketResult != null ){
                $description = 'Round #'.$marketResult['round_id'].' ( Result : '.$marketResult['description'].' )';
            }

        }else{

            $market = TransactionHistory::find()->select(['description'])
                ->where(['market_id' => $marketId ])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            if ($market != null) {
                $description = $market['description'];
                $descriptionData = explode('>', $description);
                unset($descriptionData[0]);
                unset($descriptionData[1]);
                $description = implode('>', $descriptionData);

                $marketResult = (new \yii\db\Query())
                    ->select(['result'])
                    ->from('market_result')
                    ->where([ 'market_id' => $marketId,'status' => 1 ])
                    ->one();

                if( $marketResult != null ){
                    $description = $description.' ( Result : '.$marketResult['result'].' )';
                }
            }

        }

        return $description;
    }

    public function getEventNameNew($event)
    {
        $description = 'No data';
        $sportArr = ['1' => 'Football', '2' => 'Tennis', '4' => 'Cricket' , '999999' => 'Teen Patti' , '99999' => 'Teen Patti'];

        if ($event != null) {
            $description = $sportArr[$event['sport_id']] . ' > ' . $event['event_name'];

            if( $event['result'] != null ){
                $description = $description.' ( Result : '.$event['result'].' )';
            }

        }
        return $description;
    }

    public function getEventName($eventId)
    {
        $description = 'No data';
        $sportArr = ['1' => 'Football', '2' => 'Tennis', '4' => 'Cricket' , '999999' => 'Teen Patti' , '99999' => 'Teen Patti'];
        $event = EventsPlayList::find()->select(['sport_id', 'event_name'])
            ->where(['event_id' => $eventId])
            ->asArray()->one();

        if ($event != null) {
            $description = $sportArr[$event['sport_id']] . ' > ' . $event['event_name'];

            $marketResult = (new \yii\db\Query())
                ->select(['result'])
                ->from('market_result')
                ->where([ 'event_id' => $eventId,'session_type' => 'match_odd','status' => 1 ])
                ->one();

            if( $marketResult != null ){
                $description = $description.' ( Result : '.$marketResult['result'].' )';
            }

        }
        return $description;
    }

    public function getMarketEventId($marketId)
    {
        $eventId = 'No data';
        $market = TransactionHistory::find()->select(['event_id'])
            ->where(['market_id' => $marketId, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        if ($market != null) {
            $eventId = $market['event_id'];
        }
        return $eventId;
    }

    public function getMarketTime($marketId)
    {
        $trans = (new \yii\db\Query())
            ->select(['created_at'])
            ->from('transaction_history')
            ->where(['market_id' => $marketId, 'status' => 1])
            ->orderBy(['created_at' => SORT_DESC])->one();

        if ($trans != null) {
            return date('d F Y, h:i:s A', $trans['created_at']);
        }

    }

    public function getEventTime($eventId)
    {
        $trans = (new \yii\db\Query())
            ->select(['created_at'])
            ->from('transaction_history')
            ->where(['event_id' => $eventId, 'status' => 1])
            ->orderBy(['created_at' => SORT_DESC])->one();

        if ($trans != null) {
            return $trans['created_at'] * 1000;
        }

    }

    //actionMarketBetList
    public function actionMarketBetList()
    {

        $pagination = [];
        $filters = [];

        $response = ["status" => 0, "error" => ["code" => 400, "message" => "Bad request!"]];
        $type = null;
        $title = 'No Title';
        //if( null != \Yii::$app->request->get( 'id' ) ){
        //$uid = \Yii::$app->user->id;
        $marketTypeArr = ['match_odd' => 'Match Odd', 'match_odd2' => 'Book Maker', 'fancy' => 'Fancy', 'fancy2' => 'Fancy 2', 'lottery' => 'Lottery' , 'jackpot' => 'Jackpot'];
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);
        if (json_last_error() == JSON_ERROR_NONE) {
            $r_data = ArrayHelper::toArray($request_data);
            $type = $r_data['type'];
            $marketId = $r_data['market_id'];
            $eventId = $r_data['event_id'];
            $filter_args = ArrayHelper::toArray($request_data);
            if (isset($filter_args['filter'])) {
                $filters = $filter_args['filter'];
                unset($filter_args['filter']);
            }

            $pagination = $filter_args;

            //}

            $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eventId, 'status' => 1])->asArray()->one();

            if ($event != null) {
                $title = $event['event_name'];
                if( isset( $marketTypeArr[$type] ) ){
                    $title = $event['event_name'] . ' - ' . $marketTypeArr[$type];
                }
            }

            if ($type != null) {

                if ($type == 'fancy') {

                    $market = ManualSession::find()->select(['title'])
                        ->andWhere(['event_id' => $eventId, 'market_id' => $marketId])->asArray()->one();

                    if ($market != null) {
                        $title = $title . ' - ' . $market['title'];
                    }

                } elseif ($type == 'fancy2') {

                    $market = MarketType::find()->select(['market_name'])
                        ->where(['event_id' => $eventId, 'market_id' => $marketId])->asArray()->one();

                    if ($market != null) {
                        $title = $title . ' - ' . $market['market_name'];
                    }

                } elseif ($type == 'lottery') {

                    $market = ManualSessionLottery::find()->select(['title'])
                        ->where(['event_id' => $eventId, 'market_id' => $marketId])->asArray()->one();

                    if ($market != null) {
                        $title = $title . ' - ' . $market['title'];
                    }

                } else {
                    //$title = $title;
                }

            }

            if( isset( $marketTypeArr[$type] ) ) {
                $where = ['session_type' => $type, 'event_id' => $eventId, 'market_id' => $marketId, 'status' => 1, 'bet_status' => ['Win', 'Loss', 'Canceled']];

                $query = PlaceBet::find()
                    ->select(['id', 'sport_id', 'event_id', 'market_id', 'sec_id', 'user_id', 'client_name', 'master', 'runner', 'bet_type', 'ip_address', 'price', 'rate', 'size', 'win', 'loss', 'bet_status', 'session_type', 'status', 'match_unmatch', 'created_at'])
                    ->andWhere( $where );

            }else{

                $where = ['pb.event_id' => $eventId, 'pb.status' => 1, 'pb.bet_status' => ['Win', 'Loss', 'Canceled']];

                $query = PlaceBet::find()
                    ->select(['res.round_id','pb.id', 'pb.sport_id', 'pb.event_id', 'pb.market_id', 'pb.sec_id', 'pb.user_id', 'pb.client_name', 'pb.master', 'pb.runner', 'pb.bet_type', 'pb.ip_address', 'pb.price', 'pb.rate', 'pb.size', 'pb.win', 'pb.loss', 'pb.bet_status', 'pb.session_type', 'pb.status', 'pb.match_unmatch', 'pb.created_at'])
                    ->from( PlaceBet::tableName() . ' pb' )
                    ->innerJoin( 'teen_patti_result res' , "pb.market_id=res.market_id" )
                    ->andWhere( $where );


            }


            //$user = User::findOne(\Yii::$app->user->id);

            $uid = \Yii::$app->user->id;
            if( isset($r_data['user_id']) && trim($r_data['user_id']) != null ){
                $uid = trim($r_data['user_id']);
            }

            $role = \Yii::$app->authManager->getRolesByUser($uid);

            if (!isset($role['admin'])) {
                if (isset($role['agent1']) && $role['agent1'] != null) {
                    $allUser = $this->getAllClientForSuperMaster($uid);
                    $query->andWhere(['IN', 'user_id', $allUser]);
                } else if (isset($role['agent2']) && $role['agent2'] != null) {
                    $allUser = $this->getAllClientForMaster($uid);
                    $query->andWhere(['IN', 'user_id', $allUser]);
                }else{
                    $query->andWhere(['user_id'=> $uid]);
                }

            }

            $countQuery = clone $query;
            $count = $countQuery->count();

            if ($filters != null) {
                if (isset($filters["title"]) && $filters["title"] != '') {
                    $query->andFilterWhere(["like", "runner", $filters["title"]]);
                    $query->orFilterWhere(["like", "client_name", $filters["title"]]);
                }
            }

            if ($pagination != null) {
                $offset = ($pagination['page'] - 1) * $pagination['pageSize'];
                $limit = $pagination['pageSize'];

                $query->offset($offset)->limit($limit);
            }

            $models = $query->orderBy(["id" => SORT_DESC])->asArray()->all();

            $response = ["status" => 1, "data" => ["title" => $title, "items" => $models, "count" => $count]];
        }

        return $response;

    }

    //actionChips
    public function actionChips()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }
        $filters = [];
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $transArr = [];
        $userName = 'User Not Found!';

        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $query = (new \yii\db\Query())
                ->select(['created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark'])
                ->from('transaction_history')
                ->where(['user_id' => $uid, 'is_cash' => 0])
                //->orWhere([ 'user_id'=>$uid , 'is_cash' => 1 ])
                ->andWhere(['event_id' => 0, 'status' => 1]);


            if (json_last_error() == JSON_ERROR_NONE) {

                $filter_args = ArrayHelper::toArray($request_data);
                if (isset($filter_args['filter'])) {
                    $filters = $filter_args['filter'];
                    unset($filter_args['filter']);
                }

                if ($filters != null) {
                    if (isset($filters["title"]) && $filters["title"] != '') {
                        $query->andFilterWhere(["like", "description", $filters["title"]]);
                    }
                    if( isset( $filters[ "start" ] ) && $filters[ "start" ] != '' ){
                        $startDate = strtotime($filters[ "start" ]);
                        $query->andFilterWhere( ['>=','created_at',$startDate] );
                    }
                    if( isset( $filters[ "end" ] ) && $filters[ "end" ] != '' ){
                        $endDate = strtotime($filters[ "end" ] . ' 23:59:59');
                        $query->andFilterWhere( ['<=','created_at',$endDate] );
                    }
                }

            }

            $transDataArr = $query->orderBy(['id' => SORT_DESC])->all();


            if ($transDataArr != null) {

                foreach ($transDataArr as $trans) {
                    if ($trans['transaction_amount'] != 0) {
                        $transArr[] = [
                            'created_at' => $trans['created_at'],
                            'transaction_amount' => $trans['transaction_amount'],
                            'transaction_type' => $trans['transaction_type'],
                            'current_balance' => $trans['current_balance'],
                            'description' => $trans['description'],
                            'remark' => $trans['remark'],
                        ];
                    }
                }
            }

            return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => COUNT($transArr)]];

        }

        return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => 0]];

    }


    //actionCash
    public function actionCash()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }
        $filters = [];
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $transArr = [];
        $userName = 'User Not Found!';

        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $query = (new \yii\db\Query())
                ->select(['id', 'user_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark'])
                ->from('transaction_history')
                ->where(['parent_id' => $uid, 'is_cash' => 1])
                ->orWhere(['user_id' => $uid, 'is_cash' => 1])
                ->andWhere(['event_id' => 0, 'status' => 1]);


            if (json_last_error() == JSON_ERROR_NONE) {

                $filter_args = ArrayHelper::toArray($request_data);
                if (isset($filter_args['filter'])) {
                    $filters = $filter_args['filter'];
                    unset($filter_args['filter']);
                }

                if ($filters != null) {
                    if (isset($filters["title"]) && $filters["title"] != '') {
                        $query->andFilterWhere(["like", "description", $filters["title"]]);
                    }
                }

            }

            $transDataArr = $query->orderBy(['created_at' => SORT_ASC])->all();

            if ($transDataArr != null) {
                $currentBalance = 0;
                foreach ($transDataArr as $trans) {
                    if ($trans['transaction_amount'] != 0) {

                        if ($uid != $trans['user_id']) {
                            if ($trans['transaction_type'] == 'DEBIT') {
                                $trans['transaction_type'] = 'CREDIT';
                                $currentBalance += $trans['transaction_amount'];
                            } else {
                                $trans['transaction_type'] = 'DEBIT';
                                $currentBalance -= $trans['transaction_amount'];
                            }
                        } else {

                            if ($trans['transaction_type'] == 'DEBIT') {
                                $currentBalance -= $trans['transaction_amount'];
                            } else {
                                $currentBalance += $trans['transaction_amount'];
                            }

                        }

                        $transArr[] = [
                            'created_at' => $trans['created_at'],
                            'transaction_amount' => $trans['transaction_amount'],
                            'transaction_type' => $trans['transaction_type'],
                            'current_balance' => $currentBalance,
                            'description' => $trans['description'],
                            'remark' => $trans['remark'],
                        ];
                    }
                }
            }

            return ["status" => 1, "data" => ["items" => array_reverse($transArr), "user" => $userName, "count" => COUNT($transArr)]];

        }

        return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => 0]];

    }

    /*
     * Transaction History
     */
    public function actionClient()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        //$uId = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }

        $transArr = [];

        $userName = 'User Not Found!';

        $user = User::find()->select(['name', 'username'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['name'] . ' [ ' . $user['username'] . ' ]';

            $transDataArr = (new \yii\db\Query())
                ->select(['created_at','transaction_amount','transaction_type','current_balance','description','remark'])
                ->from('transaction_history')
                ->where(['user_id'=>$uid , 'status' => 1])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            if( $transDataArr != null ){

                $currentBlnc = 0;

                foreach ( $transDataArr as $trans ){
                    if( $trans['transaction_amount'] != 0 ){

                        if( $trans['transaction_type'] == 'CREDIT' ){
                            $currentBlnc += $trans['transaction_amount'];
                        }else{
                            $currentBlnc -= $trans['transaction_amount'];
                        }

                        $transArr[] = [
                            'created_at' => $trans['created_at'],
                            'transaction_amount' => $trans['transaction_amount'],
                            'transaction_type' => $trans['transaction_type'],
                            'current_balance' => $currentBlnc,
                            'description' => $trans['description'],
                            'remark' => $trans['remark'],
                        ];
                    }
                }
            }

            return ["status" => 1, "data" => ["items" => array_reverse($transArr), "user" => $userName, "count" => COUNT($transArr)]];
        }

        return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => 0]];

    }


    //transUserBalance
    public function transUserBalance($id, $uid)
    {
        $data = (new \yii\db\Query())
            ->select(['balance'])->from('transaction_user_balance')
            ->where(['trans_id' => $id, 'user_id' => $uid])->one();
        if ($data != null) {
            return $data['balance'];
        } else {
            return 'not set!';
        }

    }

    //actionSummeryHistory
    public function actionSummeryHistory()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }
        $filters = []; $diff='';
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);
        $sport_id='all'; $title='';
        $startdate = date('Ymd' );
        $lastdate = date('Ymd' , strtotime(date('Ymd') . ' -4 day') );

        //echo "<pre>"; print_r($request_data ); exit;
        if( !empty($request_data) ) {

            // $filter = $request_data['filter'];
            $sport_id = $request_data['filter_status'];
            $title = $request_data['filter_event'];
            if( ( isset( $request_data['filter_event'] ) && $request_data['filter_event'] != '' ) || ( ( isset( $request_data['filter_start'] ) && $request_data['filter_start'] != '' ) && ( isset( $request_data['filter_end'] ) && $request_data['filter_end'] != '' ) ) ){

                $startdate = date('Ymd' , strtotime( $request_data['filter_start'] ) );
                $lastdate = date('Ymd' , strtotime( $request_data['filter_end'] ) );
                $lastdate = date('Ymd' , strtotime( $lastdate . ' +1 day') );
                $diff = abs($lastdate - $startdate);

                if( $diff > 5 ){

                    // return ["status" => 0, "data" => [],'message' => 'Select Max 5 day for search result!'];

                }

            }
        }

        $transArr = [];
        $userName = 'User Not Found!';

        $user = User::find()->select(['name', 'username' , 'role'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['username'];
            if( $user['role'] != 4 ){

                if($sport_id!='cash'){
                    if(empty($sport_id)){
                        $sport_id='all';
                    }

                    if($sport_id=='all'){
                        $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1];
                    }elseif ($sport_id=='jackpot') {
                        $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1 ,'session_type'=> $sport_id];
                    }
                    else{
                        $where=['user_id' =>$uid , 'is_cash' => 0, 'status' => 1 ,'sport_id'=> $sport_id];
                    }

                    if ($title!='') {

                        if(!empty($startdate) && !empty($lastdate)){
                            $marketArr = (new \yii\db\Query())
                                ->select(['market_id'])
                                ->from('transaction_history')
                                ->where($where)
                                ->andWhere(['!=', 'event_id', 0])
                                ->andFilterWhere( [ "like" , "description" ,$title ])
                                ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ])
                                ->orderBy(['id' => SORT_DESC])
                                ->groupBy(['market_id'])
                                ->all();

                        }else{
                            $marketArr = (new \yii\db\Query())
                                ->select(['market_id'])
                                ->from('transaction_history')
                                ->where($where)
                                ->andFilterWhere( [ "like" , "description" ,$title ])
                                ->andWhere(['!=', 'event_id', 0])
                                ->orderBy(['id' => SORT_DESC])
                                ->groupBy(['market_id'])
                                ->all();
                        }
                    }else{

                        if(!empty($startdate) && !empty($lastdate)){

                            $marketArr = (new \yii\db\Query())
                                ->select(['market_id'])
                                ->from('transaction_history')
                                ->where($where)
                                ->andWhere(['!=', 'event_id', 0])
                                ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ])
                                ->orderBy(['id' => SORT_DESC])
                                ->groupBy(['market_id'])
                                ->all();

                        }else{
                            $marketArr = (new \yii\db\Query())
                                ->select(['market_id'])
                                ->from('transaction_history')
                                ->where($where)
                                ->andWhere(['!=', 'event_id', 0])
                                ->orderBy(['id' => SORT_DESC])
                                ->groupBy(['market_id'])
                                ->all();
                        }
                    }

                    //echo '<pre>';print_r($marketArr);die;

                    if ($marketArr != null) {

                        //$currentBalance = 0;

                        foreach ($marketArr as $market) {

                            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $comm = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                                ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                                ->andWhere(['!=', 'event_id', 0])
                                ->asArray()->one();

                            $profitVal = $lossVal = $commVal = $total = 0;
                            if (isset($profit['profit']) && $profit['profit'] != null) {
                                $profitVal = $profit['profit'];
                            }

                            if (isset($loss['loss']) && $loss['loss'] != null) {
                                $lossVal = $loss['loss'];
                            }

                            $total = $profitVal - $lossVal;

                            if (isset($comm['comm']) && $comm['comm'] != null) {
                                $commVal = $comm['comm'] + 0;
                            }

                            if( $total > 0 ){
                                $transactionType = 'CREDIT';
                                //$currentBalance += $total;
                            }else{
                                $transactionType = 'DEBIT';
                                //$currentBalance -= $total;
                            }

                            $created_at= $this->getCreatedTime($market['market_id']);
                            $created_at = date('Y-m-d H:i:s' , $created_at);
                            $transArr[] = [
                                'created_at' => $created_at,
                                'transaction_amount' => round($total,2),
                                'transaction_type' => $transactionType,
                                'current_balance' => 0,//round($currentBalance,2),
                                'description' => $this->getDescriptionTrans($market['market_id']),
                                'remark' => '',
                                'hrefUrl' => '',//$this->getUrlTrans($market['market_id'] , $uid),
                            ];

                            if( $commVal > 0 ){

                                $transactionType = 'CREDIT';

                                $transArr[] = [
                                    'created_at' =>$created_at,
                                    'transaction_amount' => round($commVal,2),
                                    'transaction_type' => $transactionType,
                                    'current_balance' => 0,//round($currentBalance,2),
                                    'description' => $this->getDescriptionTrans($market['market_id']) . ' (Comm.)',
                                    'remark' => '',
                                    'hrefUrl' => false,
                                ];

                            }



                        }

                    }
                }else{
                    $where = '( status = 1  AND ( ( user_id = ' . $uid . ' AND ( is_cash = 1 AND event_id = 0 ) ) OR ( parent_id = ' . $uid . '  AND ( is_cash = 1 AND event_id = 0 ) ) ) )';

                    if ($title!='') {

                        $query = (new \yii\db\Query())
                            ->select(['id', 'user_id', 'event_id','market_id','parent_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark', 'is_cash' , 'is_commission'])
                            ->from('transaction_history')
                            ->where($where)
                            ->andFilterWhere( [ "like" , "description" ,$title ])
                            ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ]);
                        $transDataArr = $query->orderBy(['created_at' => SORT_ASC])
                            ->all();
                    }else{
                        $query = (new \yii\db\Query())
                            ->select(['id', 'user_id', 'event_id','market_id','parent_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark', 'is_cash' , 'is_commission'])
                            ->from('transaction_history')
                            ->where($where)
                            // ->andWhere(['>' , 'created_at' , strtotime($lastdate) ]);
                            ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ]);
                        $transDataArr = $query->orderBy(['created_at' => SORT_ASC])
                            //->limit(50)->offset(0)
                            ->all();
                    }

                    if ($transDataArr != null) {
                        $currentBalance = 0;
                        foreach ($transDataArr as $trans) {

                            if ($trans['transaction_amount'] != 0) {
                                $desc = $trans['description'];
                                if ($uid != $trans['user_id'] && $trans['is_cash'] == 1) {
                                    if ($trans['transaction_type'] == 'DEBIT') {
                                        $trans['transaction_type'] = 'CREDIT';
                                        //$currentBalance += $trans['transaction_amount'];

                                        $desc = $this->changeDescription($desc, 'CREDIT');

                                    } else {
                                        $trans['transaction_type'] = 'DEBIT';
                                        //$currentBalance -= $trans['transaction_amount'];
                                        $desc = $this->changeDescription($desc, 'DEBIT');
                                    }
                                } else {
                                    $desc = $trans['description'];

                                }

                                $hrefUrl = false;
                                $created_at= $this->getCreatedTime($trans['market_id']);
                                $created_at = date('Y-m-d H:i:s' , $created_at);
                                $transArr[] = [
                                    'created_at' =>$created_at,
                                    'transaction_amount' => round($trans['transaction_amount'],2),
                                    'transaction_type' => $trans['transaction_type'],
                                    'current_balance' => 0,//round($currentBalance,2),
                                    'description' => $desc,
                                    'remark' => $trans['remark'],
                                    'hrefUrl' => $hrefUrl,
                                ];
                            }

                        }
                    }
                }

                if( $transArr != null ){

                    $i = 0;$currentBalance = 0;
                    foreach ( $transArr as $trans ){

                        if ($trans['transaction_type'] == 'DEBIT') {
                            $currentBalance += $trans['transaction_amount'];
                            $transArr[$i]['current_balance'] = round($currentBalance,2);
                        } else {
                            $currentBalance += $trans['transaction_amount'];
                            $transArr[$i]['current_balance'] = round($currentBalance,2);
                        }

                        $i++;
                    }

                }



            }else{

                $where = '( status = 1  AND ( ( user_id = ' . $uid . ' AND ( ( is_cash = 0 AND event_id != 0 ) OR ( is_cash = 1 AND event_id = 0 ) ) ) OR ( parent_id = ' . $uid . '  AND ( is_cash = 1 AND event_id = 0 ) ) ) )';

                $query = (new \yii\db\Query())
                    ->select(['id', 'user_id', 'event_id','market_id','parent_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark', 'is_cash' , 'is_commission'])
                    ->from('transaction_history')
                    ->where($where)
                    ->andWhere(['>' , 'created_at' , strtotime($lastdate) ]);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $filter_args = ArrayHelper::toArray($request_data);
                    if (isset($filter_args['filter'])) {
                        $filters = $filter_args['filter'];
                        unset($filter_args['filter']);
                    }

                    if ($filters != null) {
                        if (isset($filters["title"]) && $filters["title"] != '') {
                            $query->andFilterWhere(["like", "description", $filters["title"]]);
                        }
                    }

                }

                $transDataArr = $query->orderBy(['created_at' => SORT_ASC])
                    //->limit(50)->offset(0)
                    ->all();

                if ($transDataArr != null) {
                    $currentBalance = 0;
                    foreach ($transDataArr as $trans) {

                        if ($trans['transaction_amount'] != 0) {
                            $desc = $trans['description'];
                            if ($uid != $trans['user_id'] && $trans['is_cash'] == 1) {
                                if ($trans['transaction_type'] == 'DEBIT') {
                                    $trans['transaction_type'] = 'CREDIT';
                                    $currentBalance += $trans['transaction_amount'];

                                    $desc = $this->changeDescription($desc, 'CREDIT');

                                } else {
                                    $trans['transaction_type'] = 'DEBIT';
                                    $currentBalance -= $trans['transaction_amount'];
                                    $desc = $this->changeDescription($desc, 'DEBIT');
                                }
                                //$currentBalance = $this->transUserBalance($trans['id'],$uid);
                            } else {
                                $desc = $trans['description'];

                                if ($trans['transaction_type'] == 'DEBIT') {
                                    $currentBalance -= $trans['transaction_amount'];
                                } else {
                                    $currentBalance += $trans['transaction_amount'];
                                }
                                //$currentBalance = $trans['current_balance'];
                            }

                            if( $trans['is_commission'] != 0 ){
                                $desc = $desc. '  (Comm.) ';
                            }

                            $hrefUrl = false;

                            if( $trans['event_id'] != 0 ){
                                $hrefUrl = '/history/transaction-detail/'.$trans['event_id'].'/'.$trans['market_id'].'/'.$trans['user_id'];
                            }

                            if( $user['role'] == 4 ){
                                $currentBalance = $trans['current_balance'];
                            }

                            $transArr[] = [
                                'created_at' => $trans['created_at'],
                                'transaction_amount' => round($trans['transaction_amount'],2),
                                'transaction_type' => $trans['transaction_type'],
                                'current_balance' => round($currentBalance,2),
                                'description' => $desc,
                                'remark' => $trans['remark'],
                                'hrefUrl' => $hrefUrl,
                            ];
                        }

                    }
                }

            }

            return ["status" => 1, "data" => ["items" => array_reverse($transArr), "cUser" => $userName, "count" => COUNT($transArr)]];

        }

        return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => 0]];

    }
    //actionSummeryHistory
    public function actionSummeryHistory11092019()
    {
        //Other Transection
        $uid = \Yii::$app->user->id;
        if (null != \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        }
        $filters = [];
        $request_data = json_decode(file_get_contents('php://input'), JSON_FORCE_OBJECT);

        $startdate = date('Ymd' );
        $lastdate = date('Ymd' , strtotime(date('Ymd') . ' -4 day') );

        if( $request_data != null && isset( $request_data['filter'] ) ) {

            $filter = $request_data['filter'];

            if( ( isset( $filter['title'] ) && $filter['title'] != '' )
                || ( ( isset( $filter['start'] ) && $filter['start'] != '' )
                    && ( isset( $filter['end'] ) && $filter['end'] != '' ) ) ){

                $startdate = date('Ymd' , strtotime( $filter['start'] ) );
                $lastdate = date('Ymd' , strtotime( $filter['end'] ) );

                $diff = abs($lastdate - $startdate);

                if( $diff > 5 ){

                    return ["status" => 0, "data" => [],'message' => 'Select Max 5 day for search result!'];

                }

            }
        }

        $transArr = [];
        $userName = 'User Not Found!';

        $user = User::find()->select(['name', 'username' , 'role'])
            ->where(['id' => $uid, 'status' => [1, 2]])->asArray()->one();

        if ($user != null) {

            $userName = $user['username'];
            if( $user['role'] != 4 ){

                $marketArr = (new \yii\db\Query())
                    ->select(['market_id'])
                    ->from('transaction_history')
                    ->where(['user_id' => $uid, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    //->andWhere(['>' , 'created_at' , strtotime($lastdate) ])
                    ->andWhere(['between', 'created_at', strtotime($startdate), strtotime($lastdate) ])
                    ->orderBy(['id' => SORT_DESC])
                    ->groupBy(['market_id'])
                    //->limit(100)
                    //->offset(0)
                    ->all();

                if ($marketArr != null) {

                    //$currentBalance = 0;

                    foreach ($marketArr as $market) {

                        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                            ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                            ->andWhere(['!=', 'event_id', 0])
                            ->asArray()->one();

                        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                            ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                            ->andWhere(['!=', 'event_id', 0])
                            ->asArray()->one();

                        $comm = TransactionHistory::find()->select(['SUM(transaction_amount) as comm'])
                            ->where(['market_id' => $market['market_id'], 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                            ->andWhere(['!=', 'event_id', 0])
                            ->asArray()->one();

                        $profitVal = $lossVal = $commVal = $total = 0;
                        if (isset($profit['profit']) && $profit['profit'] != null) {
                            $profitVal = $profit['profit'];
                        }

                        if (isset($loss['loss']) && $loss['loss'] != null) {
                            $lossVal = $loss['loss'];
                        }

                        $total = $profitVal - $lossVal;

                        if (isset($comm['comm']) && $comm['comm'] != null) {
                            $commVal = $comm['comm'] + 0;
                        }

                        if( $total > 0 ){
                            $transactionType = 'CREDIT';
                            //$currentBalance += $total;
                        }else{
                            $transactionType = 'DEBIT';
                            //$currentBalance -= $total;
                        }

                        $transArr[] = [
                            'created_at' => $this->getCreatedTime($market['market_id']),
                            'transaction_amount' => round($total,2),
                            'transaction_type' => $transactionType,
                            'current_balance' => 0,//round($currentBalance,2),
                            'description' => $this->getDescriptionTrans($market['market_id']),
                            'remark' => '',
                            'hrefUrl' => $this->getUrlTrans($market['market_id'] , $uid),
                        ];

                        if( $commVal > 0 ){

                            $transactionType = 'CREDIT';

                            $transArr[] = [
                                'created_at' => $this->getCreatedTime($market['market_id']),
                                'transaction_amount' => round($commVal,2),
                                'transaction_type' => $transactionType,
                                'current_balance' => 0,//round($currentBalance,2),
                                'description' => $this->getDescriptionTrans($market['market_id']) . ' (Comm.)',
                                'remark' => '',
                                'hrefUrl' => false,
                            ];

                        }

                    }

                }

                $where = '( status = 1  AND ( ( user_id = ' . $uid . ' AND ( is_cash = 1 AND event_id = 0 ) ) OR ( parent_id = ' . $uid . '  AND ( is_cash = 1 AND event_id = 0 ) ) ) )';

                $query = (new \yii\db\Query())
                    ->select(['id', 'user_id', 'event_id','market_id','parent_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark', 'is_cash' , 'is_commission'])
                    ->from('transaction_history')
                    ->where($where)
                    ->andWhere(['>' , 'created_at' , strtotime($lastdate) ]);

                $transDataArr = $query->orderBy(['created_at' => SORT_ASC])
                    //->limit(50)->offset(0)
                    ->all();

                if ($transDataArr != null) {
                    $currentBalance = 0;
                    foreach ($transDataArr as $trans) {

                        if ($trans['transaction_amount'] != 0) {
                            $desc = $trans['description'];
                            if ($uid != $trans['user_id'] && $trans['is_cash'] == 1) {
                                if ($trans['transaction_type'] == 'DEBIT') {
                                    $trans['transaction_type'] = 'CREDIT';
                                    //$currentBalance += $trans['transaction_amount'];

                                    $desc = $this->changeDescription($desc, 'CREDIT');

                                } else {
                                    $trans['transaction_type'] = 'DEBIT';
                                    //$currentBalance -= $trans['transaction_amount'];
                                    $desc = $this->changeDescription($desc, 'DEBIT');
                                }
                            } else {
                                $desc = $trans['description'];

//                                if ($trans['transaction_type'] == 'DEBIT') {
//                                    $currentBalance -= $trans['transaction_amount'];
//                                } else {
//                                    $currentBalance += $trans['transaction_amount'];
//                                }
                            }

                            $hrefUrl = false;

                            $transArr[] = [
                                'created_at' => $trans['created_at'],
                                'transaction_amount' => round($trans['transaction_amount'],2),
                                'transaction_type' => $trans['transaction_type'],
                                'current_balance' => 0,//round($currentBalance,2),
                                'description' => $desc,
                                'remark' => $trans['remark'],
                                'hrefUrl' => $hrefUrl,
                            ];
                        }

                    }
                }

                if( $transArr != null ){

                    foreach ($transArr as $key => $row) {
                        // replace 0 with the field's index/key
                        $dates[$key]  = $row['created_at'];
                    }

                    array_multisort($dates, SORT_ASC, $transArr );

                    $i = 0;$currentBalance = 0;
                    foreach ( $transArr as $trans ){

                        if ($trans['transaction_type'] == 'DEBIT') {
                            $currentBalance += $trans['transaction_amount'];
                            $transArr[$i]['current_balance'] = round($currentBalance,2);
                        } else {
                            $currentBalance += $trans['transaction_amount'];
                            $transArr[$i]['current_balance'] = round($currentBalance,2);
                        }

                        $i++;
                    }

                }



            }else{

                $where = '( status = 1  AND ( ( user_id = ' . $uid . ' AND ( ( is_cash = 0 AND event_id != 0 ) OR ( is_cash = 1 AND event_id = 0 ) ) ) OR ( parent_id = ' . $uid . '  AND ( is_cash = 1 AND event_id = 0 ) ) ) )';

                $query = (new \yii\db\Query())
                    ->select(['id', 'user_id', 'event_id','market_id','parent_id', 'created_at', 'transaction_amount', 'transaction_type', 'current_balance', 'description', 'remark', 'is_cash' , 'is_commission'])
                    ->from('transaction_history')
                    ->where($where)
                    ->andWhere(['>' , 'created_at' , strtotime($lastdate) ]);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $filter_args = ArrayHelper::toArray($request_data);
                    if (isset($filter_args['filter'])) {
                        $filters = $filter_args['filter'];
                        unset($filter_args['filter']);
                    }

                    if ($filters != null) {
                        if (isset($filters["title"]) && $filters["title"] != '') {
                            $query->andFilterWhere(["like", "description", $filters["title"]]);
                        }
                    }

                }

                $transDataArr = $query->orderBy(['created_at' => SORT_ASC])
                    //->limit(50)->offset(0)
                    ->all();

                if ($transDataArr != null) {
                    $currentBalance = 0;
                    foreach ($transDataArr as $trans) {

                        if ($trans['transaction_amount'] != 0) {
                            $desc = $trans['description'];
                            if ($uid != $trans['user_id'] && $trans['is_cash'] == 1) {
                                if ($trans['transaction_type'] == 'DEBIT') {
                                    $trans['transaction_type'] = 'CREDIT';
                                    $currentBalance += $trans['transaction_amount'];

                                    $desc = $this->changeDescription($desc, 'CREDIT');

                                } else {
                                    $trans['transaction_type'] = 'DEBIT';
                                    $currentBalance -= $trans['transaction_amount'];
                                    $desc = $this->changeDescription($desc, 'DEBIT');
                                }
                                //$currentBalance = $this->transUserBalance($trans['id'],$uid);
                            } else {
                                $desc = $trans['description'];

                                if ($trans['transaction_type'] == 'DEBIT') {
                                    $currentBalance -= $trans['transaction_amount'];
                                } else {
                                    $currentBalance += $trans['transaction_amount'];
                                }
                                //$currentBalance = $trans['current_balance'];
                            }

                            if( $trans['is_commission'] != 0 ){
                                $desc = $desc. '  (Comm.) ';
                            }

                            $hrefUrl = false;

                            if( $trans['event_id'] != 0 ){
                                $hrefUrl = '/history/transaction-detail/'.$trans['event_id'].'/'.$trans['market_id'].'/'.$trans['user_id'];
                            }

                            if( $user['role'] == 4 ){
                                $currentBalance = $trans['current_balance'];
                            }

                            $transArr[] = [
                                'created_at' => $trans['created_at'],
                                'transaction_amount' => round($trans['transaction_amount'],2),
                                'transaction_type' => $trans['transaction_type'],
                                'current_balance' => round($currentBalance,2),
                                'description' => $desc,
                                'remark' => $trans['remark'],
                                'hrefUrl' => $hrefUrl,
                            ];
                        }

                    }
                }

            }

            if( $transArr != null  ){
                $transArrNew = [];
                if( $request_data != null && isset( $request_data['filter'] ) ){

                    $filter = $request_data['filter'];

                    if( ( isset( $filter['title'] ) && $filter['title'] != '' )
                        || ( isset( $filter['start'] ) && $filter['start'] != '' )
                        || ( isset( $filter['end'] ) && $filter['end'] != '' ) ){

                        foreach ( $transArr as $trans ){

                            if( ( isset( $filter['start'] ) && $filter['start'] != '' )
                                    && ( isset( $filter['end'] ) && $filter['end'] != '' ) ){

                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($trans['description'],$filter['title']) > 0 ){
                                        $transArrNew[] = $trans;
                                    }
                                }

                                $startDate = strtotime($filter[ "start" ]);
                                $endDate = strtotime($filter[ "end" ] . ' 23:59:59');

                                if( $trans['created_at'] >= $startDate && $trans['created_at'] <= $endDate ){
                                    $transArrNew[] = $trans;
                                }

                            }else{

                                if( isset( $filter['title'] ) && $filter['title'] != '' ){
                                    if( strpos($trans['description'],$filter['title']) > 0 ){
                                        $transArrNew[] = $trans;
                                    }
                                }

                            }

                        }

                        $transArr = $transArrNew;

                    }

                }


            }


            return ["status" => 1, "data" => ["items" => array_reverse($transArr), "cUser" => $userName, "count" => COUNT($transArr)]];

        }

        return ["status" => 1, "data" => ["items" => $transArr, "user" => $userName, "count" => 0]];

    }

    public function actionUpdateTransactionTable(){

        $where=['status' => 1, 'is_cash' => 0, 'status' => 1,'sport_id'=>0];
        $transArrMarket = (new \yii\db\Query())
            ->select(['DISTINCT(market_id)'])
            ->from('transaction_history')
            ->where($where)
            ->andWhere(['!=', 'event_id', 0])
            ->all();

        if(!empty($transArrMarket)){
            $marketIDList=[];
            foreach ($transArrMarket as  $value) {
                $marketIDList[]= $value['market_id'];

            }
        }



        if ($marketIDList != null) {
            $marketIDList = array_unique($marketIDList);
            foreach ($marketIDList as $marketId) {
                $betHistory = (new \yii\db\Query())
                    ->select(['sport_id','session_type'])
                    ->from('place_bet')
                    ->where(['market_id' => $marketId,'status'=>1])
                    ->one();
                if(!empty( $betHistory)){
                    $round_id=0;
                    if( $betHistory['session_type']=='teenpatti' || $betHistory['session_type']=='poker'){
                        $teenpattiHistory = (new \yii\db\Query())->select(['round_id'])->from('teen_patti_result')->where(['market_id' => $marketId])->one();
                        $round_id=$teenpattiHistory['round_id'];
                    }


                    $where = ['market_id' => $marketId];
                    $updateData = [ 'sport_id' => $betHistory['sport_id'] , 'session_type' =>$betHistory['session_type'], 'round_id' => $round_id];
                    \Yii::$app->db->createCommand()->update('transaction_history', $updateData , $where )->execute();

                    // echo "<pre>";  print_r($betHistory); exit;
                }

            }
        }

    }

    public function getUrlTrans($marketId,$uid)
    {
        $hrefUrl = false;

        if( $marketId != 0 ){

            $trans = (new \yii\db\Query())
                ->select(['event_id'])
                ->from('transaction_history')
                ->where(['market_id' => $marketId ])
                ->andWhere(['!=', 'event_id', 0])
                ->one();

            $hrefUrl = '/history/transaction-detail/'.$trans['event_id'].'/'.$marketId.'/'.$uid;
        }

        return $hrefUrl;
    }




    public function getDescriptionTrans($marketId)
    {
        $description = 'No data';
        //$market = TransactionHistory::find()->select(['description'])
        $trans = (new \yii\db\Query())
            ->select(['description'])
            ->from('transaction_history')
            ->where(['market_id' => $marketId ])
            ->andWhere(['!=', 'event_id', 0])
            ->one();

        if ($trans != null) {
            $description = $trans['description'];
        }
        return $description;
    }

    public function getCreatedTime($marketId)
    {
        $trans = (new \yii\db\Query())
            ->select(['created_at'])
            ->from('transaction_history')
            ->where(['market_id' => $marketId, 'status' => 1])
            ->orderBy(['created_at' => SORT_DESC])->one();

        if ($trans != null) {
            return $trans['created_at'];
        }

    }

    //actionSummeryHistory
    public function changeDescription($desc, $type)
    {
        if ($type == 'CREDIT') {
            //$desc = 'Cash Received By '.$userName.' From '.$parentUserName;
            $desc1 = explode('By', $desc);
            if (count($desc1) > 0) {
                $desc2 = explode('From', $desc1[1]);
                if (count($desc2) > 1) {
                    $userName = trim($desc2[0]);
                    $parentUserName= trim($desc2[1]);
                    $desc = 'Cash Deposit By ' . $parentUserName . ' To ' . $userName;
                }
            }
            return $desc;
        } else {
            //$desc = 'Cash Deposit By '.$userName.' To '.$parentUserName;
            $desc1 = explode('By', $desc);
            if (count($desc1) > 1) {
                $desc2 = explode('To', $desc1[1]);
                if (count($desc2) > 1) {
                    $userName = trim($desc2[0]);
                    $parentUserName = trim($desc2[1]);
                    $desc = 'Cash Received By ' . $userName . ' From ' . $parentUserName;
                }
            }
            return $desc;
        }

    }

}
