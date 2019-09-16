<?php
namespace api\modules\v1\modules\events\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\PlaceBet;
use common\models\TransactionHistory;
use common\models\MarketType;
use common\models\User;
use common\models\Setting;
use common\models\EventsPlayList;
use common\models\ManualSession;
use common\models\BallToBallSession;
use common\models\UserProfitLoss;
use common\models\TempTransactionHistory;
use common\models\GlobalCommentary;
use common\models\ManualSessionMatchOdd;
use common\models\ManualSessionLottery;
use common\models\ManualSessionLotteryNumbers;
use common\models\ManualSessionMatchOddData;
use common\models\EventsRunner;
use yii\db\Query;

class CricketController extends \common\controllers\aController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'admin' , 'agent' ,  'sessionuser' , 'sessionuser2' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    public function actionInplay()
    {
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $inplay = [];

        $today = date('Ymd');
        $tomorrow = date('Ymd' , strtotime($today . ' +7 day') );
        $lastday = date('Ymd' , strtotime($today . ' -7 day') );

        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if( isset($role['agent']) || isset($role['sessionuser']) || isset($role['sessionuser2']) ){
            $gameOver = 'NO';
        }else{
            $gameOver = ['YES','NO'];
        }

        if( json_last_error() == JSON_ERROR_NONE ) {
            $r_data = ArrayHelper::toArray($request_data);

            if( isset( $r_data['filter'] ) ){
                $filter = $r_data['filter'];

                if( $filter['status'] != null || $filter['title'] != null){
                    $inplay = EventsPlayList::find()
                        ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 4 , 'play_type' => $filter['status'] ])
                        ->andWhere(['LIKE','event_name',$filter['title']])
                        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
                }else{
                    $inplay = EventsPlayList::find()
                        ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 4 ])
                        ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
                }

            }else{

                $inplay = EventsPlayList::find()
                    ->where(['game_over' => $gameOver , 'status'=>[1,2] , 'sport_id' => 4 ])
                    ->andWhere(['between', 'event_time', ( strtotime($lastday)*1000 ), ( strtotime($tomorrow)*1000 ) ])
                    ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();

            }

        }

        //'event_time' => SORT_DESC
        if( $inplay != null ){
            $i = 0;
            foreach ( $inplay as $data ){
                
                $runners = EventsRunner::find()->where(['event_id' => $data['event_id']])
                ->asArray()->all();
                
                if( $runners != null ){
                    foreach ( $runners as $rnr ){
                        $inplay[$i]['runners'][] = [
                            'secId' => $rnr['selection_id'],
                            'nunnerName' => $rnr['runner'],
                        ];
                    }
                }

                $inplay[$i]['isJackpot'] = $this->isJackpot($data['event_id']);
                $inplay[$i]['isBookMaker'] = $this->isBookMaker($data['event_id']);
                $inplay[$i]['isManualSession'] = $this->isManualSession($data['event_id']);
                $inplay[$i]['isFancy'] = $this->isFancy($data['event_id']);
                $inplay[$i]['isLottery'] = $this->isLottery($data['event_id']);

                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $inplay ] ];
    }

    //isJackpot
    public function isJackpot($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('cricket_jackpot')
            ->where(['event_id' => $eventId, 'status' => [1,0] ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    //isMatchOdds
    public function isBookMaker($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('manual_session_match_odd')
            ->where(['event_id' => $eventId, 'status' => 1 ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    //isManualSession
    public function isManualSession($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('manual_session')
            ->where(['event_id' => $eventId, 'status' => [1,2] ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    //isFancy
    public function isFancy($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('market_type')
            ->where(['event_id' => $eventId, 'status' => [1,2] ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    //isLottery
    public function isLottery($eventId)
    {
        $count = (new \yii\db\Query())
            ->select(['id'])
            ->from('manual_session_lottery')
            ->where(['event_id' => $eventId, 'status' => 1 ])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $count != null ){
            return true;
        }else{
            return false;
        }

    }

    public function actionTrash()
    {
        $inplay = [];
        $inplay = EventsPlayList::find()
        ->where(['game_over' => ['YES','NO'] , 'status' => 0 , 'sport_id' => 4 ])
        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
        //'event_time' => SORT_DESC
        if( $inplay != null ){
            $i = 0;
            foreach ( $inplay as $data ){
                
                $runners = EventsRunner::find()->where(['event_id' => $data['event_id']])
                ->asArray()->all();
                
                if( $runners != null ){
                    foreach ( $runners as $rnr ){
                        $inplay[$i]['runners'][] = [
                            'secId' => $rnr['selection_id'],
                            'nunnerName' => $rnr['runner'],
                        ];
                    }
                }
                $i++;
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $inplay ] ];
    }
    
    public function actionUpcoming()
    {
        $upcoming = EventsPlayList::find()
        ->where(['play_type'=>'UPCOMING' , 'game_over' => 'NO' , 'sport_id' => 4 ])
        ->orderBy( [ 'event_time' => SORT_DESC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $upcoming ] ];
    }

    public function actionDelete(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $event = EventsPlayList::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $event->status = 0;
                    if( $event->save( [ 'status' ] ) ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "Event deleted successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Event not deleted!"
                        ];
                    }

                }
            }
        }

        return $response;
    }

    
    public function checkMarketStatus($mtype)
    {
        $marketType = MarketType::findOne([ 'market_type'=> $mtype,'status' => 1 ]);
        if( $marketType != null ){
            return true;
        }else{
            return false;
        }
        
    }
    
    // Cricket: Get Exchange Web from API
    public function actionMyProfitLossByEvent()
    {
        $eID = \Yii::$app->request->get( 'id' );//$_GET['id'];
        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response1 = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response1);die;
        //$event = $response1->results[0]->event;
        $responseArr = [];
        
        if( !empty( $response1->results ) && !empty( $response1->results[0]->markets ) ){
            
            $marketsArr = $response1->results[0]->markets[0];
            $marketName = $marketsArr->description->marketName;
            $totalMatched = number_format($marketsArr->state->totalMatched , 5);
            $marketId = $marketsArr->marketId;
            $eventId = $marketsArr->market->eventId;
            //$rules = $marketsArr->licence->rules;
            
            $runnersArr = [];
            
            $time = strtotime(date('Y-m-d H:i:s'));
            
            $title = $marketsArr->runners[0]->description->runnerName .' Vs '.$marketsArr->runners[1]->description->runnerName;
            
            foreach ( $marketsArr->runners as $runners ){
                
                $back = $lay = [];
                /*$back = $lay = [
                 'price' => '-',
                 'size' => '-'
                 ];*/
                
                if( isset( $runners->exchange->availableToBack ) ){
                    
                    foreach ( $runners->exchange->availableToBack as $backData ){
                        $back[] = [
                            'price' => number_format($backData->price , 2),
                            'size' => number_format($backData->size , 2),
                        ];
                    }
                    
                    /*$back = [
                     'price' => number_format($runners->exchange->availableToBack[0]->price , 2),
                     'size' => number_format($runners->exchange->availableToBack[0]->size , 2),
                     ];*/
                }
                
                if( isset( $runners->exchange->availableToLay ) ){
                    
                    foreach ( $runners->exchange->availableToLay as $layData ){
                        $lay[] = [
                            'price' => number_format($layData->price , 2),
                            'size' => number_format($layData->size , 2),
                        ];
                    }
                    
                    /*$lay = [
                     'price' => number_format($runners->exchange->availableToLay[0]->price,2),
                     'size' => number_format($runners->exchange->availableToLay[0]->size,2),
                     ];*/
                }
                
                $profitLoss = $this->getProfitLossOnBet($eventId , $runners->description->runnerName );
                
                $runnersArr[] = [
                    'selectionId' => $runners->selectionId,
                    'runnerName' => $runners->description->runnerName,
                    'runnerId' => $runners->description->metadata->runnerId,
                    'profit_loss' => $profitLoss,
                    'exchange' => [
                        'back' => $back,
                        'lay' => $lay
                    ]
                ];
                
            }
            
            //$check = EventsPlayList::findOne(['event_id' => $eventId , 'status' => 1 , 'play_type' => 'IN_PLAY' ]);
            
            //if( $check != null ){
            $responseArr[] = [
                'title' => $title,
                'marketId' => $marketId,
                'eventId' => $eventId,
                'time' => $time,
                'marketName' => $marketName,
                'matched' => $totalMatched,
                'runners' => $runnersArr,
                //'rules' => $rules
            ];
            //}
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseArr ] ];
        
    }
    
    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBet($eventId,$runner)
    {
        $uid = \Yii::$app->user->id;
        
        $AllClients = $this->getAllClientForAdmin($uid);
        
        //echo '<pre>';print_r($userId);die;
        
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            
            foreach ( $AllClients as $client ){
                
                if( $runner != 'The Draw' ){
                    
                    // IF RUNNER WIN
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
                    
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
                    //echo '<pre>';print_r($backWin[0]['val']);die;
                    
                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'runner' , $runner ];
                    
                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    //echo '<pre>';print_r($layWin[0]['val']);die;
                    
                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layDrwWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($layDrwWin[0]['val']);die;
                    
                    if( $layDrwWin == null || !isset($layDrwWin[0]['val']) || $layDrwWin[0]['val'] == '' ){
                        $layDrwWin = 0;
                    }else{ $layDrwWin = $layDrwWin[0]['val']; }
                    
                    //$totalWin = $backWin + $layWin + $layDrwWin;
                    $totalLoss = $backWin + $layWin + $layDrwWin;
                    
                    // IF RUNNER LOSS
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($layLoss[0]['val']);die;
                    
                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'runner' , $runner ];
                    
                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    //echo '<pre>';print_r($backLoss[0]['val']);die;
                    
                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => 'The Draw' , 'event_id' => $eventId , 'bet_type' => 'back' ];
                    
                    $backDrwLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($backDrwLoss[0]['val']);die;
                    
                    if( $backDrwLoss == null || !isset($backDrwLoss[0]['val']) || $backDrwLoss[0]['val'] == '' ){
                        $backDrwLoss = 0;
                    }else{ $backDrwLoss = $backDrwLoss[0]['val']; }
                    
                    //$totalLoss = $backLoss + $layLoss + $backDrwLoss;
                    $totalWin = $backLoss + $layLoss + $backDrwLoss;
                    
                    $total = $totalWin-$totalLoss;
                    
                }else{
                    
                    // IF RUNNER WIN
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'back' ];
                    
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($backWin[0]['val']);die;
                    
                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    $totalWin = $backWin;
                    
                    // IF RUNNER LOSS
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'runner' => $runner , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)
                    ->asArray()->all();
                    
                    //echo '<pre>';print_r($layLoss[0]['val']);die;
                    
                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }
                    
                    $where = [ 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];
                    $andWhere = [ '!=' , 'runner' , $runner ];
                    
                    $otherLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    if( $otherLoss == null || !isset($otherLoss[0]['val']) || $otherLoss[0]['val'] == '' ){
                        $otherLoss = 0;
                    }else{ $otherLoss = $otherLoss[0]['val']; }
                    
                    $total = $layLoss + $otherLoss;
                    
                }
                
                if( $total != null && $total != 0 ){
                    
                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                    ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();
                    
                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }
                    
                }
                
            }
            
            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return array_sum($totalArr);
            }else{
                return '';
            }
            
        }
        
    }

    public function getClientListByUserId($uid)
    {
        $client = [];

        $cdata = (new \yii\db\Query())
            ->select(['client_id'])
            ->from('user_profit_loss')
            ->where(['parent_id'=> $uid])->distinct()
            ->createCommand(Yii::$app->db1)->queryAll();

        if ($cdata != null) {
            foreach ($cdata as $c) {
                $client[] = (int)$c['client_id'];
            }
        }

        return $client;

    }

    //AllClientForAdmin
    public function getAllClientForAdmin($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id','role'])
            ->where(['parent_id'=>$uid , 'role'=> 2])->asArray()->all();
        
        if($smdata != null){
            
            foreach ( $smdata as $sm ){
                
                // get all master
                $sm2data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 2])->asArray()->all();
                if($sm2data != null){
                    
                    foreach ( $sm2data as $sm2 ){
                        // get all master
                        $m1data = User::find()->select(['id','role'])
                            ->where(['parent_id'=>$sm2['id'] , 'role'=> 3])->asArray()->all();
                        if($m1data != null){
                            foreach ( $m1data as $m1 ){
                                // get all master
                                $m2data = User::find()->select(['id','role'])
                                    ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->asArray()->all();
                                if($m2data != null){
                                    foreach ( $m2data as $m2 ){
                                        
                                        // get all client
                                        $cdata = User::find()->select(['id'])
                                            ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                                        if($cdata != null){
                                            foreach ( $cdata as $c ){
                                                $client[] = $c['id'];
                                            }
                                        }
                                        
                                    }
                                }
                                
                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id'=>$m1['id'] , 'role'=> 4])->asArray()->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c['id'];
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id'=>$sm['id'] , 'role'=> 4])->asArray()->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c['id'];
                            }
                        }
                    }
                    
                }
                
                
                // get all master
                $m1data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 3])->asArray()->all();
                if($m1data != null){
                    foreach ( $m1data as $m1 ){
                        // get all master
                        $m2data = User::find()->select(['id','role'])
                            ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->asArray()->all();
                        if($m2data != null){
                            foreach ( $m2data as $m2 ){
                                
                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c['id'];
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id'=>$m1['id'] , 'role'=> 4])->asArray()->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c['id'];
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 4])->asArray()->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c['id'];
                    }
                }
                
            }
        }
        
        // get all master
        $mdata = User::find()->select(['id','role'])
            ->where(['parent_id'=>$uid , 'role'=> 3])->asArray()->all();
        if($mdata != null){
            
            foreach ( $mdata as $m ){
                
                $m2data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$m['id'] , 'role'=> 3])->asArray()->all();
                if($m2data != null){
                    foreach ( $m2data as $m2 ){
                        
                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c['id'];
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id'=>$m['id'] , 'role'=> 4])->asArray()->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c['id'];
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])
            ->where(['parent_id'=>$uid , 'role'=> 4])->asArray()->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c['id'];
            }
        }
        
        return $client;
        
    }
    
    //AllClientForSuperMaster
    public function getAllClientForSuperMaster($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id','role'])
            ->where(['parent_id'=>$uid , 'role'=> 2])->asArray()->all();
        
        if($smdata != null){
            foreach ( $smdata as $sm ){
                // get all master
                $m1data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 3])->asArray()->all();
                if($m1data != null){
                    foreach ( $m1data as $m1 ){
                        // get all master
                        $m2data = User::find()->select(['id','role'])
                            ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->asArray()->all();
                        if($m2data != null){
                            foreach ( $m2data as $m2 ){
                                
                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c['id'];
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id'=>$m1['id'] , 'role'=> 4])->asArray()->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c['id'];
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 4])->asArray()->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c['id'];
                    }
                }
                
            }
        }
        
        // get all master
        $mdata = User::find()->select(['id','role'])
            ->where(['parent_id'=>$uid , 'role'=> 3])->asArray()->all();
        if($mdata != null){
            
            foreach ( $mdata as $m ){
                
                $m2data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$m['id'] , 'role'=> 3])->asArray()->all();
                if($m2data != null){
                    foreach ( $m2data as $m2 ){
                        
                        // get all client
                        $cdata = User::find()->select(['id'])
                            ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c['id'];
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])
                    ->where(['parent_id'=>$m['id'] , 'role'=> 4])->asArray()->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c['id'];
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->asArray()->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c['id'];
            }
        }
        
        return $client;
        
    }
    
    //AllClientForMaster
    public function getAllClientForMaster($uid)
    {
        $client = [];
        $smdata = User::find()->select(['id','role'])
            ->where(['parent_id'=>$uid , 'role'=> 2])->asArray()->all();
        
        if($smdata != null){
            foreach ( $smdata as $sm ){
                // get all master
                $m1data = User::find()->select(['id','role'])
                    ->where(['parent_id'=>$sm['id'] , 'role'=> 3])->asArray()->all();
                if($m1data != null){
                    foreach ( $m1data as $m1 ){
                        // get all master
                        $m2data = User::find()->select(['id','role'])
                            ->where(['parent_id'=>$m1['id'] , 'role'=> 3])->asArray()->all();
                        if($m2data != null){
                            foreach ( $m2data as $m2 ){
                                
                                // get all client
                                $cdata = User::find()->select(['id'])
                                    ->where(['parent_id'=>$m2['id'] , 'role'=> 4])->asArray()->all();
                                if($cdata != null){
                                    foreach ( $cdata as $c ){
                                        $client[] = $c['id'];
                                    }
                                }
                                
                            }
                        }
                        
                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m1->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$sm->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id;
                    }
                }
                
            }
        }
        
        // get all master
        $mdata = User::find()->select(['id','role'])->where(['parent_id'=>$uid , 'role'=> 3])->all();
        if($mdata != null){
            
            foreach ( $mdata as $m ){
                
                $m2data = User::find()->select(['id','role'])->where(['parent_id'=>$m->id , 'role'=> 3])->all();
                if($m2data != null){
                    foreach ( $m2data as $m2 ){
                        
                        // get all client
                        $cdata = User::find()->select(['id'])->where(['parent_id'=>$m2->id , 'role'=> 4])->all();
                        if($cdata != null){
                            foreach ( $cdata as $c ){
                                $client[] = $c->id;
                            }
                        }
                        
                    }
                }
                
                // get all client
                $cdata = User::find()->select(['id'])->where(['parent_id'=>$m->id , 'role'=> 4])->all();
                if($cdata != null){
                    foreach ( $cdata as $c ){
                        $client[] = $c->id;
                    }
                }
                
            }
            
        }
        
        // get all client
        $cdata = User::find()->select(['id'])->where(['parent_id'=>$uid , 'role'=> 4])->all();
        if($cdata != null){
            foreach ( $cdata as $c ){
                $client[] = $c->id;
            }
        }
        
        return $client;
        
    }
    
    public function actionCurrentEventScore()
    {
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $eID = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->apiUserToken.'&event_id='.$eID;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            //$response = curl_exec($ch);
            $response = json_decode(curl_exec($ch));
            curl_close($ch);
            
            //echo '<pre>';print_r($response);die;
            return [ "status" => 1 , "data" => [ "items" => $items ] ];
        }
    }
    
    public function actionCreateplacebet(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( $this->currentBalance() > 0 ){
            
            $data['PlaceBet'] = $r_data;
            $model = new PlaceBet();
            
            if ($model->load($data)) {
                
                if( $this->currentBalance() < $model->size ){
                    $response[ "error" ] = [
                        "message" => "insufficient funds!" ,
                        "data" => $model->errors
                    ];
                }
                
                $model->win = $model->size*$model->price;
                $model->loss = $model->size;
                
                $model->ccr = round ( ( ($model->win-$model->size)*$this->clientCommissionRate() )/100 );
                
                $model->bet_status = 'Pending';
                $model->user_id = \Yii::$app->user->id;
                $model->status = 1;
                $model->created_at = $model->updated_at = time();
                
                if( $model->save() ){
                    
                    $bet = $model;
                    
                    $model = new TransactionHistory();
                    
                    $model->user_id = \Yii::$app->user->id;
                    $model->bet_id = $bet->id;
                    $model->client_name = $bet->client_name;
                    $model->transaction_type = 'DEBIT';
                    $model->transaction_amount = $bet->size;
                    $model->current_balance = $this->currentBalance($bet->size);
                    $model->status = 1;
                    $model->created_at = $model->updated_at = time();
                    
                    if( $model->save() ){
                        $response = [
                            'status' => 1 ,
                            "success" => [
                                "message" => "place bet successfully!"
                            ]
                        ];
                        
                    }else{
                        
                        $bet->delete();
                        
                        $response[ "error" ] = [
                            "message" => "somthing wrong!" ,
                            "data" => $model->errors
                        ];
                    }
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "somthing wrong!" ,
                        "data" => $model->errors
                    ];
                }
                
            }
            }else{
                $response[ "error" ] = [
                    "message" => "insufficient funds!" ,
                    "data" => $model->errors
                ];
            }
            
        }
        
        return $response;
    }
    
    public function clientCommissionRate()
    {
        $CCR = 1;//$CCR = Client Commission Rate
        $setting = Setting::findOne([ 'key' => 'CLIENT_COMMISSION_RATE' , 'status' => 1 ]);
        if( $setting != null ){
            $CCR = $setting->value;
            return $CCR;
        }else{
            return $CCR;
        }
    }
    
    public function currentBalance($price = false)
    {
        $user = User::findOne( \Yii::$app->user->id );
        
        if( $price != false ){
            $user->balance = ( $user->balance - $price );
            if( $user->save(['balance']) ){
                return $user->balance;
            }else{
                return $user->balance;
            }
        }else{
            return $user->balance;
        }
    }
    
    //action Setting View
    public function actionSettingView(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){

            $id = \Yii::$app->request->get( 'id' );
            $model = EventsPlayList::find()->select( [ 'event_name','upcoming_min_stake','upcoming_max_stake','upcoming_max_profit','max_odd_limit','accept_unmatch_bet','max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit' , 'max_profit_all_limit' , 'bet_delay' ] )->where( [ 'event_id' => $id ] )->asArray()->one();
            if( $model != null ){
                $response = [ "status" => 1 , "data" => $model ];
            }
            
        }else{
            
            $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                
                if( $r_data['type'] == 'fancy2'  ){
                    
                    $id = $r_data['id'];
                    $model = MarketType::find()->select( [ 'market_id' , 'market_name','max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit', 'bet_delay' ] )->where( [ 'market_id' => $id ] )->asArray()->one();
                    if( $model != null ){
                        $response = [ "status" => 1 , "data" => $model ];
                    }
                    
                }else if( $r_data['type'] == 'fancy'  ){
                    
                    $id = $r_data['id'];
                    $model = ManualSession::find()->select( [ 'title', 'market_id' ,'max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit', 'bet_delay' ] )->where( [ 'market_id' => $id ] )->asArray()->one();
                    if( $model != null ){
                        $response = [ "status" => 1 , "data" => $model ];
                    }
                    
                }else if( $r_data['type'] == 'match_odd2'  ){
                    
                    $id = $r_data['id'];
                    $model = ManualSessionMatchOdd::find()->select( [ 'event_name','market_id' ,'max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit', 'bet_delay' ] )->where( [ 'market_id' => $id ] )->asArray()->one();
                    if( $model != null ){
                        $response = [ "status" => 1 , "data" => $model ];
                    }
                    
                }else if( $r_data['type'] == 'lottery'  ){
                    
                    $id = $r_data['id'];
                    $model = ManualSessionLottery::find()->select( [ 'title','market_id' ,'max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit', 'bet_delay' ] )->where( [ 'market_id' => $id ] )->asArray()->one();
                    if( $model != null ){
                        $response = [ "status" => 1 , "data" => $model ];
                    }
                    
                }else{
                    $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
                }
                
            }
            
            
        }
        
        return $response;
    }
    
    //action Setting
    public function actionSetting(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( null != \Yii::$app->request->get('id') ){
            
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );
                
                $id = \Yii::$app->request->get('id');
                if( isset( $id ) && isset( $r_data[ 'max_stack' ] )
                    && isset( $r_data[ 'min_stack' ] )
                    && isset( $r_data[ 'max_profit' ] )
                    && isset( $r_data[ 'max_profit_limit' ] )
                    && isset( $r_data[ 'max_profit_all_limit' ] ) ){
                        
                        $event = EventsPlayList::findOne( ['event_id' => $id ] );
                        
                        if( $event != null ){

                            $event->upcoming_min_stake   = $r_data[ 'upcoming_min_stake' ];
                            $event->upcoming_max_stake   = $r_data[ 'upcoming_max_stake' ];
                            $event->upcoming_max_profit   = $r_data[ 'upcoming_max_profit' ];
                            $event->max_odd_limit   = $r_data[ 'max_odd_limit' ];
                            $event->accept_unmatch_bet   = $r_data[ 'accept_unmatch_bet' ];

                            $event->max_stack   = $r_data[ 'max_stack' ];
                            $event->min_stack   = $r_data[ 'min_stack' ];
                            $event->max_profit  = $r_data[ 'max_profit' ];
                            $event->max_profit_limit  = $r_data[ 'max_profit_limit' ];
                            $event->max_profit_all_limit  = $r_data[ 'max_profit_all_limit' ];
                            $event->bet_delay  = $r_data[ 'bet_delay' ];
                            
                            if( $event->save( [ 'upcoming_min_stake','upcoming_max_stake','upcoming_max_profit','max_odd_limit','accept_unmatch_bet', 'max_stack' , 'min_stack' , 'max_profit' , 'max_profit_limit' , 'max_profit_all_limit','bet_delay' ] ) ){
                                
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Event Setting updated successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!" ,
                                    "data" => $event->errors
                                ];
                            }
                            
                        }
                }
            }
            
        }else{
            
            if( json_last_error() == JSON_ERROR_NONE ){
                $r_data = ArrayHelper::toArray( $request_data );

                if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ] ) && isset( $r_data[ 'title' ] ) ){

                    if( $r_data[ 'type' ] == 'fancy2' ){

                        $fancy = MarketType::findOne( ['id' => $r_data[ 'id' ] ] );
                        if( $fancy != null ){

                            $fancy->market_name = $r_data[ 'title' ];

                            if( $fancy->save() ){
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Market title updated successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }

                        }
                    }

                }

                if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ] ) && isset( $r_data[ 'max_stack' ] )
                    && isset( $r_data[ 'min_stack' ] ) && isset( $r_data[ 'max_profit' ] )
                    && isset( $r_data[ 'max_profit_limit' ] ) && isset( $r_data[ 'bet_delay' ] ) ){
                        $id = $r_data[ 'id' ];
                        if( $r_data[ 'type' ] == 'fancy2' ){
                            $event = MarketType::findOne( ['market_id' => $id ] );
                        }else if( $r_data[ 'type' ] == 'fancy' ){
                            $event = ManualSession::findOne( ['market_id' => $id ] );
                        }else if( $r_data[ 'type' ] == 'match_odd2' ){
                            $event = ManualSessionMatchOdd::findOne( ['market_id' => $id ] );
                        }else if( $r_data[ 'type' ] == 'lottery' ){
                            $event = ManualSessionLottery::findOne( ['market_id' => $id ] );
                        }else{
                            $event = null;
                        }
                        
                        if( $event != null ){
                            
                            $event->max_stack   = $r_data[ 'max_stack' ];
                            $event->min_stack   = $r_data[ 'min_stack' ];
                            $event->max_profit  = $r_data[ 'max_profit' ];
                            $event->max_profit_limit  = $r_data[ 'max_profit_limit' ];
                            $event->bet_delay  = $r_data[ 'bet_delay' ];
                            
                            //echo '<pre>';print_r($event);die;
                            if( $event->save() ){
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Market Setting updated successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                            
                        }
                }
            }
            
        }
        
        return $response;
    }
    
    
    /*
     *Match Odds Actions 
     */
    
    //Generate Match Odd
    public function actionGenerateMatchOdd(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $eventId = \Yii::$app->request->get( 'id' );
        
        $condition = ['event_id' => $eventId , 'play_type'=>['IN_PLAY','UPCOMING'],'game_over'=>'NO'];
        $event = EventsPlayList::find()->select(['event_name','event_id','market_id'])->where($condition)->one();
        $matchoddArr = [];
        if( $event != null ){
            
            $marketId = $event->market_id;
            
            $runnerData = EventsRunner::findAll(['event_id'=>$eventId,'market_id'=>$marketId]);
            if( $runnerData != null ){
                foreach ( $runnerData as $runner ){
                    $runners[] = $runner->runner;
                }
            }
            
            $model = new ManualSessionMatchOdd();

            $model->event_name = $event->event_name;
            $model->event_id = $eventId;
            $model->market_id = '1.'.time().'-M';
            $model->game_over = 'NO';
            $model->win_result = 'undefined';
            $model->status = 1;
            $model->created_at = $model->updated_at = time();
            
            if( $model->save() ){

                foreach ($runners as $rnr){

                    $matchoddArr[] = [
                        'market_id'=>$model->market_id,
                        'sec_id' => mt_rand(123456, 987654),
                        'runner' => $rnr,
                        'lay'=>'0',
                        'back'=>'0',
                        'updated_at'=>time()
                    ];

                }

                $command = \Yii::$app->db->createCommand();
                $attrArr = ['market_id' , 'sec_id','runner' , 'lay' , 'back','updated_at' ];
                $qry = $command->batchInsert('manual_session_match_odd_data', $attrArr, $matchoddArr);
                if( $qry->execute() ){

                    //$key1 = 'BOOK-MAKER-'.$model->market_id;
                    $key1 = $this->BOOKMAKER_KEY.$model->market_id;

                    $data1 = json_encode ( [
                        'event_id' => $model->event_id,
                        'market_id' => $model->market_id,
                        'title' => $model->event_name,
                        'game_over' => $model->game_over,
                        'win_result' => $model->win_result,
                        'suspended' => $model->suspended,
                        'ball_running' => $model->ball_running,
                        'status' => $model->status,
                        'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                        'time' => round(microtime(true) * 1000),
                        'runners' => $matchoddArr
                    ]);

                    if( $data1 != null ){
                        $cache = Yii::$app->cache;
                        $cache->set( $key1 , $data1 );
                    }

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Manual Session Match Odd Generate successfully!"
                        ]
                    ];
                }else{
                    $model->delete();
                    $response[ "error" ] = [
                        "message" => "Somthing wrong! Please try again !!"
                        //"data" => $model->errors
                    ];
                }
                
            }else{
                $response[ "error" ] = [
                    "message" => "Somthing wrong! Please try again !!"
                    //"data" => $model->errors
                ];
            }
            
        }
        
        //}
        
        return $response;
    }
    
    // Game Abundant MatchOdds
    public function actionGameAbundant(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'market_id' ] ) && isset( $r_data[ 'type' ] )  ){
                
                if( $r_data[ 'type' ] == 'match_odd' ){
                    
                    $event = EventsPlayList::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ], 'sport_id' => 4 ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';
                        
                        $where = [ 'session_type' => 'match_odd', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                        
                        PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                }else if( $r_data[ 'type' ] == 'fancy2' ){
                    
                    $event = MarketType::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';
                        
                        $where = [ 'session_type' => 'fancy2', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                        
                        PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if( $r_data[ 'type' ] == 'fancy' ){
                    
                    $event = ManualSession::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';
                        
                        $where = [ 'session_type' => 'fancy', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                        
                        PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if($r_data[ 'type' ] == 'lottery' ){
                    
                    $event = ManualSessionLottery::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';
                        
                        $where = [ 'session_type' => 'lottery', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                        
                        PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }else if( $r_data[ 'type' ] == 'match_odd2' ){
                    
                    $event = ManualSessionMatchOdd::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id' => $r_data[ 'market_id' ] ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'YES';
                        $event->win_result = 'Abundant';
                        
                        $where = [ 'session_type' => 'match_odd2', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                        
                        PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Abundant successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                        
                    }
                    
                }
                
                
            }
        }
        return $response;
    }
    
    // Game Over Match Odds
    public function actionGameoverMatchOdds(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            $id = $r_data[ 'id' ];
            if( isset( $id ) && isset( $r_data[ 'result' ] ) ){
                
                $event = EventsPlayList::findOne( ['event_id' => $id ] );
                
                if( $event != null ){
                    
                    /*$manualSession = ManualSession::findOne(['event_id' => $id , 'game_over' => 'NO' , 'status' => '1' ]);
                    
                    if( $manualSession != null ){
                        $response[ "error" ] = [
                            "message" => "Still Manual Session Game Over is Pending!" ,
                            "data" => $event->errors
                        ];
                        
                        return $response;
                    }
                    
                    $fancySession = MarketType::findOne(['event_id' => $id , 'game_over' => 'NO' , 'status' => '1' ]);
                    
                    if( $fancySession != null ){
                        $response[ "error" ] = [
                            "message" => "Still Fancy Session Game Over is Pending!" ,
                            "data" => $event->errors
                        ];
                        
                        return $response;
                    }*/
                    
                    $winResult = $this->commonRunnerName( $event->event_id ,$event->market_id, $r_data[ 'result' ] );
                    
                    //echo $winResult;die;
                    
                    $event->game_over = 'YES';
                    $event->win_result = $winResult;
                    $event->status = 1;
                    
                    $this->commonGameoverResultMatchOdds( $event->event_id , $event->market_id , $r_data[ 'result' ] );
                    
                    if( $event->save( [ 'game_over','win_result','status' ] ) ){
                        
                        $resultArr = [
                            'sport_id' => $event->sport_id,
                            'event_id' => $event->event_id,
                            'event_name' => $event->event_name,
                            'market_id' => $event->market_id,
                            'result' => $event->win_result,
                            'market_name' => 'Match Odd',
                            'session_type' => 'match_odd',
                            'updated_at' => time(),
                            'status' => 1,
                        ];
                        
                        \Yii::$app->db->createCommand()
                        ->insert('market_result', $resultArr )->execute();
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Event play game over successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        return $response;
    }
    
    public function getRunnerNameUNUSED($eventId ,$marketId, $selectionId){
        
        $runnerName = 'undefined';
        
        $runners = EventsRunner::find()->select(['runner'])
        ->where(['event_id' => $eventId , 'market_id' => $marketId , 'selection_id' => $selectionId ])
        ->asArray()->one();
        if( $runners != null ){
            $runnerName = $runners['runner'];
        }
        return $runnerName;
        
    }
    
    // Game Recall MatchOdds
    public function actionGameRecallMatchOdds(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'event_id' ] ) && isset( $r_data['market_id'] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'match_odd' ){
                    
                    $event = EventsPlayList::findOne( [ 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'event_id' ] , 'sport_id' => 4 , 'status' => 1 ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            
                            $this->gameRecallMatchOdds( $event->market_id );
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Recall Successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                    
                }else{
                    
                    $event = ManualSessionMatchOdd::find()
                    ->where([ 'event_id' => $r_data[ 'event_id' ] , 'market_id' => $r_data[ 'market_id' ] , 'game_over' => 'NO' , 'status' => '1' ])
                    ->one();
                    
                    if( $event != null ){
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            
                            if( $this->gameRecallMatchOdds( $event->market_id ) == true ){
                                
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
                                    ]
                                ];
                                
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                    
                }
            }
        }
        return $response;
    }
    
    //game Recall Match Odds
    public function gameRecallMatchOdds( $marketId ){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($allBetListArr);die;
            if( $betList != null ){
                
                $transArr = TempTransactionHistory::findAll(['bet_id'=>$betList]);
                
                if( $transArr != null ){
                    
                    foreach ( $transArr as $trans ){
                        
                        $user = User::findOne(['id'=>$trans->user_id]);
                        if( $trans->transaction_type == 'CREDIT' ){
                            $user->balance = ($user->balance-$trans->transaction_amount);
                        }else{
                            $user->balance = ($user->balance+$trans->transaction_amount);
                        }
                        $user->save(['balance']);
                    }
                    
                    if( TempTransactionHistory::updateAll(['status'=>2],['bet_id'=>$betList]) ){
                        
                        if( PlaceBet::updateAll(['status'=>2],['id'=>$betList]) ){
                            return true;
                        }
                        
                    }
                    
                }
            }
        }
        
        return false;
        
    }
    
    //gameover Result MatchOdds
    public function gameoverResultMatchOddsUNUSED( $eventId , $marketId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'sec_id' => $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            //echo '<pre>';print_r($backWinList);die;
            
            $layWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if( $backWinList != null ){
                foreach( $backWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
            }
            
            if( $layWinList != null ){
                
                foreach( $layWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
                
            }
            
            /* User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->where( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id,$loss->event_id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    //gameover Result MatchOdds
    public function gameoverResultMatchOddsUNUSED_2( $eventId , $marketId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'runner' => $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            //echo '<pre>';print_r($backWinList);die;
            
            $layWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->andwhere( [ '!=' , 'runner' , $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if( $backWinList != null ){
                foreach( $backWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
            }
            
            if( $layWinList != null ){
                
                foreach( $layWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
                
            }
            
            /* User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->where( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'event_id' => $list['event_id'] ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id,$loss->event_id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    //Remove Match Odd
    public function actionRemoveMatchOdd(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        
        $model = ManualSessionMatchOdd::findOne(['event_id'=>$id]);
        
        if( $model != null ){
            if( ManualSessionMatchOdd::deleteAll(['event_id'=>$id]) && ManualSessionMatchOddData::deleteAll(['market_id'=>$model->market_id]) ){
                $response = [
                    'status' => 1,
                    "success" => [
                        "message" => "Manual Session Match Odd Removed successfully!"
                    ]
                ];
            }else{
                $response[ "error" ] = [
                    "message" => "Somthing wrong! Please try again !!"
                    //"data" => $model->errors
                ];
            }
            
        }else{
            $response[ "error" ] = [
                "message" => "Somthing wrong! Data not found !!"
            ];
        }
        
        
        
        return $response;
    }
    
    //UpdateManualSession MatchOdd
    public function actionUpdateManualSessionMatchOdd(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        $bookMakerData = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if ( $r_data != null ) {

                $cache = Yii::$app->cache;

                $key = $this->BOOKMAKER_KEY.$r_data['market_id'];

                if( $cache->exists($key) ) {
                    $bookMakerData = $cache->get($key);
                    $bookMakerData = json_decode($bookMakerData,true);
                }else{
                    $eventId = \Yii::$app->request->get( 'id' );
                    $model = ManualSessionMatchOdd::findOne(['event_id' => $eventId]);

                    if( $model != null ) {
                        $marketId = $model->market_id;

                        $runners = ManualSessionMatchOddData::findAll(['market_id' => $model->market_id]);

                        if ($runners != null) {

                            foreach ($runners as $runner) {
                                $profitLoss = '--'; //$this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runner->sec_id,'match_odd2');

                                $runnersData[] = [
                                    'sec_id' => $runner->sec_id,
                                    'runner' => $runner->runner,
                                    'suspended' => $runner->suspended,
                                    'ball_running' => $runner->ball_running,
                                    'lay' => $runner->lay,
                                    'back' => $runner->back,
                                    'profitloss' => $profitLoss,
                                ];

                            }

                        }

                        $bookMakerData = [
                            'event_id' => $model->event_id,
                            'market_id' => $model->market_id,
                            'title' => $model->event_name,
                            'game_over' => $model->game_over,
                            'win_result' => $model->win_result,
                            'suspended' => $model->suspended,
                            'ball_running' => $model->ball_running,
                            'status' => $model->status,
                            'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                            'runners' => $runnersData
                        ];

                        $key1 = $this->BOOKMAKER_KEY . $marketId;
                        $cache = Yii::$app->cache;

                        if (!$cache->exists($key1)) {
                            $cache->set($key1, json_encode($bookMakerData));
                        }
                    }
                }

                //echo '<pre>';print_r($bookMakerData);die;

                $connection = \Yii::$app->db->createCommand();


                //Zero One Rate
                if( isset( $r_data['zero-one-rate'] ) && ( $r_data['zero-one-rate'] == true ) ){

                    $dataSession = [
                        'lay' =>  '1',
                        'back' => '0',
                        'suspended' => 'N',
                        'ball_running' => 'N',
                        'updated_at' => time(),
                    ];

                    $mid = $r_data['market_id'];
                    $sid = $r_data['sec_id'];

                    $condition = 'market_id = "'.$mid.'" AND sec_id != '.$sid;

                    $connection->update('manual_session_match_odd_data', [ 'suspended' => 'Y' , 'lay' => 0 , 'back' => 0, 'updated_at' => time() ] ,
                        $condition )->execute();

                    if( $bookMakerData != null ){
                        if( isset($bookMakerData['runners']) ){
                            $i = 0;
                            foreach ( $bookMakerData['runners'] as $runners ){
                                $bookMakerData['runners'][$i]['suspended'] = 'Y';
                                $bookMakerData['runners'][$i]['lay'] = 0;
                                $bookMakerData['runners'][$i]['back'] = 0;
                                $i++;
                            }
                        }
                        $bookMakerData['time'] = round(microtime(true) * 1000);
                    }

                    if( $connection->update('manual_session_match_odd_data', $dataSession,
                        ['sec_id'=>$r_data['sec_id'] , 'market_id'=>$r_data['market_id'] ] )->execute()){


                        if( $bookMakerData != null ){
                            if( isset($bookMakerData['runners']) ){
                                $i = 0;

                                foreach ( $bookMakerData['runners'] as $runners ){

                                    if( $runners['sec_id'] == $r_data['sec_id'] ){
                                        $bookMakerData['runners'][$i]['suspended'] = 'N';
                                        $bookMakerData['runners'][$i]['ball_running'] = 'N';
                                        $bookMakerData['runners'][$i]['lay'] = '1';
                                        $bookMakerData['runners'][$i]['back'] = '0';
                                    }
                                    $i++;
                                }
                            }
                            $bookMakerData['suspended'] = 'N';
                            $bookMakerData['ball_running'] = 'N';
                            $bookMakerData['time'] = round(microtime(true) * 1000);
                        }

                        $dataSession2 = [
                            'suspended' => 'N',
                            'ball_running' => 'N',
                            'updated_at' => time()
                        ];

                        if( $connection->update('manual_session_match_odd', $dataSession2,
                            [ 'market_id' => $r_data['market_id'] ] )->execute()){

                            $cache->set($key,json_encode($bookMakerData));

                            $status = 1;
                            $message = "Manual Session MatchOdd Updated Successfully!";

                        }else{

                            $status = 0;
                            $message = "Something wrong!";
                        }

                    }else{
                        $status = 0;
                        $message = "Something wrong!";
                    }

                }

                if( isset( $r_data['suspended'] ) && ( $r_data['suspended'] == true ) ){
                    
                    $model = ManualSessionMatchOdd::findOne(['market_id'=>$r_data['market_id'] ]);
                    if( $model != null ){
                        $model->suspended = 'Y';
                        $model->updated_at = time();
                        
                        if( $model->save( ['suspended','updated_at'] )
                            && ManualSessionMatchOddData::updateAll(
                                [ 'suspended' => 'Y' ] , ['market_id'=>$r_data['market_id'] ] )){

                            if( $bookMakerData != null ){
                                if( isset( $bookMakerData['suspended'] ) ){
                                    $bookMakerData['suspended'] = 'Y';
                                    if( isset($bookMakerData['runners']) ){
                                        $i = 0;
                                        foreach ( $bookMakerData['runners'] as $runners ){
                                            $bookMakerData['runners'][$i]['suspended'] = 'Y';
                                            $bookMakerData['runners'][$i]['ball_running'] = 'N';
                                            $i++;
                                        }
                                    }
                                }
                            }

                            // Save Radis Cache
                            $cache->set($key,json_encode($bookMakerData));
                            $status = 1;$message = "Book Maker Suspended successfully!";

                        }
                    }
                }
                
                
                if( isset( $r_data['ball_running'] ) && ( $r_data['ball_running'] == true ) ){
                    
                    $model = ManualSessionMatchOdd::findOne(['market_id'=>$r_data['market_id'] ]);
                    if( $model != null ){
                        $model->ball_running = 'Y';
                        $model->updated_at = time();
                        
                        if( $model->save( ['ball_running','updated_at'] ) 
                            && ManualSessionMatchOddData::updateAll(
                                [ 'ball_running' => 'Y' ] , ['market_id'=>$r_data['market_id'] ] )){
                            
                            if( $bookMakerData != null ){
                                if( isset( $bookMakerData['suspended'] ) ){
                                    $bookMakerData['ball_running'] = 'Y';
                                    if( isset($bookMakerData['runners']) ){
                                        $i = 0;
                                        foreach ( $bookMakerData['runners'] as $runners ){
                                            $bookMakerData['runners'][$i]['ball_running'] = 'Y';
                                            $bookMakerData['runners'][$i]['suspended'] = 'N';
                                            $i++;
                                        }
                                    }
                                }
                            }

                            // Save Radis Cache
                            $cache->set($key,json_encode($bookMakerData));
                            $status = 1;$message = "Book Maker Ball Running successfully!";
                        }
                    }
                }

                
                if( isset($r_data['sec_id']) && isset($r_data['both'])
                    && $r_data['sec_id'] != 0 && $r_data['both'] != 1
                    && $r_data['ball_running'] != true && $r_data['suspended'] != true ){

                        $dataSession = [
                            'lay' =>  round($r_data['lay'],2),
                            'back' => round($r_data['back'],2),
                            'suspended' => 'N',
                            'ball_running' => 'N',
                            'updated_at' => time(),
                        ];
                        
                        $mid = $r_data['market_id'];
                        $sid = $r_data['sec_id'];
                        
                        $condition = 'market_id = "'.$mid.'" AND sec_id != '.$sid;

                        $connection->update('manual_session_match_odd_data', [ 'suspended' => 'Y' , 'lay' => 0 , 'back' => 0, 'updated_at' => time() ] ,
                            $condition )->execute();
                        
                        if( $bookMakerData != null ){
                            if( isset($bookMakerData['runners']) ){
                                $i = 0;
                                foreach ( $bookMakerData['runners'] as $runners ){
                                    $bookMakerData['runners'][$i]['suspended'] = 'Y';
                                    $bookMakerData['runners'][$i]['lay'] = 0;
                                    $bookMakerData['runners'][$i]['back'] = 0;
                                    $i++;
                                }
                            }
                            $bookMakerData['time'] = round(microtime(true) * 1000);
                        }

                        if( $connection->update('manual_session_match_odd_data', $dataSession,
                            ['sec_id'=>$r_data['sec_id'] , 'market_id'=>$r_data['market_id'] ] )->execute()){
                        

                                if( $bookMakerData != null ){
                                    if( isset($bookMakerData['runners']) ){
                                        $i = 0;

                                        foreach ( $bookMakerData['runners'] as $runners ){

                                            if( $runners['sec_id'] == $r_data['sec_id'] ){
                                                $bookMakerData['runners'][$i]['suspended'] = 'N';
                                                $bookMakerData['runners'][$i]['ball_running'] = 'N';
                                                $bookMakerData['runners'][$i]['lay'] = round($r_data['lay'],2);
                                                $bookMakerData['runners'][$i]['back'] = round($r_data['back'],2);
                                            }
                                            $i++;
                                        }
                                    }
                                    $bookMakerData['suspended'] = 'N';
                                    $bookMakerData['ball_running'] = 'N';
                                    $bookMakerData['time'] = round(microtime(true) * 1000);
                                }

                                $dataSession2 = [
                                    'suspended' => 'N',
                                    'ball_running' => 'N',
                                    'updated_at' => time()
                                ];

                                if( $connection->update('manual_session_match_odd', $dataSession2,
                                    [ 'market_id' => $r_data['market_id'] ] )->execute()){
                                    
//                                    if( $bookMakerData != null ){
//                                        $bookMakerData['suspended'] = 'N';
//                                        $bookMakerData['ball_running'] = 'N';
//                                        $bookMakerData['time'] = round(microtime(true) * 1000);
//                                    }

                                    // Save Radis Cache
                                    $cache->set($key,json_encode($bookMakerData));

                                    $status = 1;
                                    $message = "Manual Session MatchOdd Updated Successfully!";
                                    
                                }else{

                                    $status = 0;
                                    $message = "Something wrong!";
                                }

                        }else{
                            $status = 0;
                            $message = "Something wrong!";
                        }
                        
                }
                
                if( isset($r_data['both']) && $r_data['both'] == 1
                    && $r_data['ball_running'] != true && $r_data['suspended'] != true ){
                    
                    if( ManualSessionMatchOdd::updateAll( [ 'suspended' => 'N' , 'ball_running' => 'N' ] ,
                        ['market_id'=>$r_data['market_id'] ] ) && ManualSessionMatchOddData::updateAll( [ 'back' => $r_data['back'] , 'suspended' => 'N' , 'ball_running' => 'N' , 'lay' => 0 ] ,
                        ['market_id'=>$r_data['market_id'] ] ) ){
                        
                        if( $bookMakerData != null ){

                            if( isset($bookMakerData['runners']) ){
                                $i = 0;
                                foreach ( $bookMakerData['runners'] as $runners ){
                                    $bookMakerData['runners'][$i]['suspended'] = 'N';
                                    $bookMakerData['runners'][$i]['ball_running'] = 'N';
                                    $bookMakerData['runners'][$i]['lay'] = 0;
                                    $bookMakerData['runners'][$i]['back'] = round($r_data['back'],2);
                                    $i++;
                                }
                            }

                            $bookMakerData['suspended'] = 'N';
                            $bookMakerData['ball_running'] = 'N';
                            $bookMakerData['time'] = round(microtime(true) * 1000);
                        }

                        // Save Radis Cache
                        $cache->set($key,json_encode($bookMakerData));

                        $status = 1;
                        $message = "Manual Session MatchOdd Updated Successfully!";
                        
                    }else{

                        $status = 0;
                        $message = "Something wrong!";
                        
                    }
                    
                }

                $uid = \Yii::$app->user->id;
                //$modelData = ManualSessionMatchOdd::findOne(['market_id' => $r_data['market_id']]);
                $data = null;
                if( $bookMakerData != null ){

                    $marketId = $bookMakerData['market_id'];
                    $eventId = $bookMakerData['event_id'];

                    if( isset($bookMakerData['runners']) ){
                        $i = 0;
                        foreach ( $bookMakerData['runners'] as $runners ){
                            $profitLoss = '';//$this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runners['sec_id'],'match_odd2');
                            $bookMakerData['runners'][$i]['profitloss'] = $profitLoss;
                            $i++;
                        }
                    }

//                    $runners = ManualSessionMatchOddData::findAll([ 'market_id' => $modelData->market_id]);
//
//                    if( $runners != null ){
//
//                        foreach ( $runners as $runner ){
//                            $profitLoss = $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runner->sec_id,'match_odd2');
//                            $runnersData[] = [
//                                'sec_id' => $runner->sec_id,
//                                'runner' => $runner->runner,
//                                'suspended' => $runner->suspended,
//                                'ball_running' => $runner->ball_running,
//                                'lay' => $runner->lay,
//                                'back' => $runner->back,
//                                'profitloss' => $profitLoss,
//                            ];
//
//                        }
//
//                    }
//
//                    $items = [
//                        'event_id' => $modelData->event_id,
//                        'market_id' => $modelData->market_id,
//                        'title' => $modelData->event_name,
//                        'game_over' => $modelData->game_over,
//                        'win_result' => $modelData->win_result,
//                        'suspended' => $modelData->suspended,
//                        'ball_running' => $modelData->ball_running,
//                        'status' => $modelData->status,
//                        'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
//                        'time' => round(microtime(true) * 1000),
//                        'runners' => $runnersData
//                    ];

//                    if( $items != null ){
//                        $cache = Yii::$app->cache;
//                        $cache->set( $modelData->market_id , json_encode($items) );
//                    }

//                    $where = ['market_id' => $marketId ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
//
//                    $query = PlaceBet::find()
//                        ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
//                        ->where($where);
//
//                    $betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
//                    $betList = [];
//                    if( $betListData != null ){
//                        $betList = $betListData;
//                    }

                    $status = 1;$message = "Manual Session MatchOdd Updated Successfully!";
                    $data = [ "items" => $bookMakerData ];

                }

            }

        }

        $response = [ "status" => $status , 'data' => $data , "message" => $message ];

        return $response;
    }
    
    //action Manual Session MatchOdd
    public function actionManualSessionMatchOdd(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            
            $items = []; $rule = '';
            $marketId = "";
            $gameOver = 'NO';
            $winResult = 'undefined';
            $id = \Yii::$app->request->get( 'id' );

            $eventName = 'No data!';

            $event = EventsPlayList::find()
                ->select( [ 'event_name' ] )->andWhere( [ 'status' => 1 , 'event_id' => $id ] )
                ->one();

            if( $event != null ){
                $eventName = $event->event_name;
                $manualSessionMatchOdd = ManualSessionMatchOdd::find()
                    ->select( [ 'market_id','event_id','game_over','win_result','info' ] )->andWhere( [ 'status' => [1,2] , 'event_id' => $id ] )
                    ->one();

                if( $manualSessionMatchOdd != null ){

                    $marketId = $manualSessionMatchOdd->market_id;
                    $gameOver = $manualSessionMatchOdd->game_over;
                    $winResult = $manualSessionMatchOdd->win_result;
                    $rule = $manualSessionMatchOdd->info;

                    $matchOddData = ManualSessionMatchOddData::findAll(['market_id'=>$marketId]);

                    if( $matchOddData != null ){

                        foreach ( $matchOddData as $data ){
                            $items[] = [
                                'id' => $data->id,
                                'secId' => $data->sec_id,
                                'runner' => $data->runner,
                                'back' => $data->back,
                                'lay' => $data->lay,
                            ];
                        }

                    }

                }

            }

            $response = [ "status" => 1 , "data" => [ "items" => $items , 'marketId' => $marketId , 'game_over' => $gameOver, 'win_result' => $winResult , 'eventName' => $eventName ,'rule' => $rule] ];
            
        }
        
        return $response;
        
    }
    
    //action Data Manual Session Data
    public function actionManualSessionMatchOddData(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){

            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['sessionuser']) ){
                $uid = 1;
            }

            $eventId = \Yii::$app->request->get( 'id' );
            $model = ManualSessionMatchOdd::findOne(['event_id' => $eventId]);
            
            if( $model != null ){
                $marketId = $model->market_id;

                $runners = ManualSessionMatchOddData::findAll([ 'market_id' => $model->market_id]);
                
                if( $runners != null ){

                    foreach ( $runners as $runner ){
                        $profitLoss = '--'; //$this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$runner->sec_id,'match_odd2');

                        $runnersData[] = [
                            'sec_id' => $runner->sec_id,
                            'runner' => $runner->runner,
                            'suspended' => $runner->suspended,
                            'ball_running' => $runner->ball_running,
                            'lay' => $runner->lay,
                            'back' => $runner->back,
                            'profitloss' => $profitLoss,
                        ];

                    }

                }
                
                $items = [
                    'event_id' => $model->event_id,
                    'market_id' => $model->market_id,
                    'title' => $model->event_name,
                    'game_over' => $model->game_over,
                    'win_result' => $model->win_result,
                    'suspended' => $model->suspended,
                    'ball_running' => $model->ball_running,
                    'status' => $model->status,
                    'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                    'runners' => $runnersData
                ];

                $key1 = $this->BOOKMAKER_KEY.$marketId;
                $cache = Yii::$app->cache;
                if( !$cache->exists($key1) ) {
                    $cache->set($key1, json_encode($items));
                }

                $where = ['market_id' => $model->market_id ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
                
                $query = PlaceBet::find()
                ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                ->where($where);
                
                $betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
                $betList = [];
                if( $betListData != null ){
                    $betList = $betListData;
                }
                
                $response = [
                    "status" => 1 ,
                    "data" => [ "items" => $items , 'betList' => $betList ]
                ];
                
            }
            
        }
        
        return $response;
    }

    //action Data Manual Session Data
    public function actionManualSessionMatchOddBetList(){

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null != \Yii::$app->request->get( 'id' ) ){

            $uid = \Yii::$app->user->id;
            $role = \Yii::$app->authManager->getRolesByUser($uid);
            if(isset($role['sessionuser']) ){
                $uid = 1;
            }

            $eventId = \Yii::$app->request->get( 'id' );
            //$model = ManualSessionMatchOdd::findOne(['event_id' => $eventId]);

            $model = (new \yii\db\Query())
                ->select(['market_id'])
                ->from('manual_session_match_odd')
                ->where( [ 'event_id' => $eventId ] )
                ->createCommand(Yii::$app->db1)->queryOne();

            if( $model != null ){
                $marketId = $model['market_id'];

                $where = ['market_id' => $marketId ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];

                //$query = PlaceBet::find()
                 //   ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                 //   ->where($where);

                //$betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();

                $betListData = (new \yii\db\Query())
                    ->select([ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ])
                    ->from('place_bet')
                    ->where($where)
                    ->orderBy([ "id" => SORT_DESC ])
                    ->createCommand(Yii::$app->db1)->queryAll();

                $betList = $bookData = [];
                if( $betListData != null ){

                    $matchOddData = (new \yii\db\Query())
                        ->select(['id','sec_id','runner','lay','back','suspended','ball_running'])
                        ->from('manual_session_match_odd_data')
                        ->andWhere( [ 'market_id' => $marketId ] )
                        ->createCommand(Yii::$app->db1)->queryAll();

                    if( $matchOddData != null ){
                        foreach( $matchOddData as $data ){
                            $bookData[$data['sec_id']] = round( $this->getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$data['sec_id'],'match_odd2') , 2 );
                        }
                    }

                    $betList = $betListData;
                }

                $response = [
                    "status" => 1 ,
                    "data" => [ 'betList' => $betList , 'bookData' => $bookData ]
                ];

            }

        }

        return $response;
    }

    // Cricket: get Profit Loss On Bet
    public function getProfitLossOnBetMatchOdds($uid,$marketId,$eventId,$selId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        
        //$role = \Yii::$app->authManager->getRolesByUser($uid);
        
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }else{
//            $AllClients = [];
//        }

        $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
        $betUsers = PlaceBet::find()->select(['user_id'])->where($where)->asArray()->all();
        $AllClients = [];
        if( $betUsers != null ){
            foreach ( $betUsers as $usr ){
                $AllClients[] = $usr['user_id'];
            }
        }
        //$AllClients = $this->getClientListByUserId($uid);
        //echo '<pre>';print_r($userId);die;
        
        if( $AllClients != null && count($AllClients) > 0 ){
            
            $totalArr = [];
            $total = 0;
            foreach ( $AllClients as $client ){
                
                if( $marketId != null && $eventId != null && $selId != null){
                    
                    // IF RUNNER WIN
                    
                    $where = [ 'match_unmatch'=>1,'market_id' => $marketId , 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
                    $backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();
                    
                    //echo '<pre>';print_r($backWin[0]['val']);die;
                    
                    if( $backWin == null || !isset($backWin[0]['val']) || $backWin[0]['val'] == '' ){
                        $backWin = 0;
                    }else{ $backWin = $backWin[0]['val']; }
                    
                    $where = [ 'match_unmatch'=>1, 'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $layWin = PlaceBet::find()->select(['SUM(win) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    
                    //echo '<pre>';print_r($layWin[0]['val']);die;
                    
                    if( $layWin == null || !isset($layWin[0]['val']) || $layWin[0]['val'] == '' ){
                        $layWin = 0;
                    }else{ $layWin = $layWin[0]['val']; }
                    
                    $totalWin = $backWin + $layWin;
                    
                    // IF RUNNER LOSS
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 ,'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];
                    
                    $layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();
                    //echo '<pre>';print_r($layLoss[0]['val']);die;
                    
                    if( $layLoss == null || !isset($layLoss[0]['val']) || $layLoss[0]['val'] == '' ){
                        $layLoss = 0;
                    }else{ $layLoss = $layLoss[0]['val']; }
                    
                    $where = [ 'market_id' => $marketId ,'match_unmatch'=> 1 , 'session_type' => $sessionType,'user_id' => $client, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
                    $andWhere = [ '!=' , 'sec_id' , $selId ];
                    
                    $backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
                    ->where($where)->andWhere($andWhere)->asArray()->all();
                    //echo '<pre>';print_r($backLoss[0]['val']);die;
                    
                    if( $backLoss == null || !isset($backLoss[0]['val']) || $backLoss[0]['val'] == '' ){
                        $backLoss = 0;
                    }else{ $backLoss = $backLoss[0]['val']; }
                    
                    $totalLoss = $backLoss + $layLoss;
                    $total = $totalWin-$totalLoss;
                    
                }
                
                if( $total != null && $total != 0 ){
                    
                    $profitLoss = UserProfitLoss::find()->select(['actual_profit_loss'])
                    ->where(['client_id'=>$client,'user_id'=>$uid,'status'=>1])->one();
                    
                    if( $profitLoss != null && $profitLoss->actual_profit_loss > 0 ){
                        $total = ($total*$profitLoss->actual_profit_loss)/100;
                        $totalArr[] = $total;
                    }
                    
                }
                
            }
            
            if( array_sum($totalArr) != null && array_sum($totalArr) ){
                return (-1)*array_sum($totalArr);
            }else{
                return '0';
            }
            
        }
        
        
    }
    
    /*
     *Fancy Actions
     */
    
    // Game Over Fancy
    public function actionGameoverFancy(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( !is_numeric($r_data[ 'result' ]) ){
                $response[ "error" ] = [
                    "message" => "Something wrong! win result must be a number!"
                ];
                return $response;exit;
            }
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'result' ] ) ){
                
                if( $r_data['type'] == 'fancy2' ){
                
                    $market = MarketType::findOne( ['market_id' => $r_data[ 'id' ] , 'event_type_id' => 4 , 'status' => 1 ] );
                
                    if( $market != null ){
                        $eventId = $market->event_id;
                        $event = EventsPlayList::findOne(['event_id'=>$eventId]);
                        
                        $market->game_over = 'YES';
                        $market->win_result = $r_data[ 'result' ];
                        $market->status = 2;
                        
                        $this->gameoverResultFancy( $market->event_id ,$market->market_id, $market->win_result );
                        
                        if( $market->save( [ 'game_over' , 'win_result' , 'status' ] ) ){
                            
                            $resultArr = [
                                'sport_id' => $event->sport_id,
                                'event_id' => $market->event_id,
                                'event_name' => $event->event_name,
                                'market_id' => $market->market_id,
                                'market_name' => $market->market_name,
                                'result' => $market->win_result,
                                'session_type' => 'fancy2',
                                'updated_at' => time(),
                                'status' => 1,
                            ];
                            
                            \Yii::$app->db->createCommand()
                            ->insert('market_result', $resultArr )->execute();
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                        
                 }else{
                    
                     $market = ManualSession::find()
                        ->where(['market_id' => $r_data[ 'id' ] , 'game_over' => 'NO' , 'status' => '1' ])
                        ->one();
                        
                        if( $market != null ){
                            
                            $eventId = $market->event_id;
                            $event = EventsPlayList::findOne(['event_id'=>$eventId]);
                            
                            $market->game_over = 'YES';
                            $market->win_result = $r_data[ 'result' ];
                            $market->status = 2;
                        
                            $this->gameoverResultFancy( $market->event_id ,$market->market_id, $market->win_result );
                        
                            if( $market->save( [ 'game_over' , 'win_result','status' ] ) ){
                            
                            $resultArr = [
                                'event_id' => $market->event_id,
                                'event_name' => $event->event_name,
                                'market_id' => $market->market_id,
                                'market_name' => $market->market_name,
                                'session_type' => 'fancy',
                                'updated_at' => time(),
                                'status' => 1,
                            ];
                            
                            \Yii::$app->db->createCommand()
                            ->insert('market_result', $resultArr )->execute();
                            
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Game Over successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!" ,
                                "data" => $event->errors
                            ];
                        }
                    }
                    
                }
            }
        }
        return $response;
    }
    
    // Game Abundant MatchOdds
    public function actionGameAbundantFancy(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'market_id' ] )  ){
                
                $event = MarketType::findOne( ['event_id' => $r_data[ 'id' ] ,'market_id'=>$r_data[ 'market_id' ] ] );
                
                if( $event != null ){
                    
                    $event->game_over = 'YES';
                    $event->win_result = 'Abundant';
                    
                    $where = [ 'session_type' => 'match_odd', 'market_id' => $r_data[ 'market_id' ] , 'event_id' => $r_data[ 'id' ] , 'bet_status' => 'Pending' , 'status' => 1];
                    
                    PlaceBet::updateAll(['bet_status' => 'Canceled'],$where);
                    
                    if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Game Abundant successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! event not updated!"
                        ];
                    }
                    
                }
            }
        }
        return $response;
    }
    
    // Game Recall Fancy
    public function actionGameRecallFancy(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'fancy2' ){
                    
                    $event = MarketType::findOne( ['market_id' => $r_data[ 'id' ] , 'event_type_id' => 4 , 'status' => 1 ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $this->gameRecallFancy( $event->market_id ) == true ){
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                            
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                    
                }else{
                    
                    $event = ManualSession::find()
                    ->where(['market_id' => $r_data[ 'id' ] , 'game_over' => 'YES' , 'status' => '1' ])
                    ->one();
                    //echo '<pre>';print_r($event);die;
                    if( $event != null ){
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $this->gameRecallFancy( $event->market_id ) == true ){
                         
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                            
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                            
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                    
                }
            }
        }
        return $response;
    }
    
    //game Recall Fancy
    public function gameRecallFancy( $marketId ){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($betList);
            if( $betList != null ){
                $betIds = [];
                foreach ( $betList as $betId ){
                    $betIds[] = $betId['id'];
                }
                //echo '<pre>';print_r($betIds);
                $transArr = TempTransactionHistory::findAll(['bet_id'=>$betIds]);
                //echo '<pre>';print_r($transArr);die;
                if( $transArr != null ){
                    
                    foreach ( $transArr as $trans ){
                        $user = User::findOne(['id'=>$trans->user_id]);
                        if( $trans->transaction_type == 'CREDIT' ){
                            $user->balance = ($user->balance-$trans->transaction_amount);
                        }else{
                            $user->balance = ($user->balance+$trans->transaction_amount);
                        }
                        $user->save(['balance']);
                    }
                    
                    if( TempTransactionHistory::updateAll(['status'=>2],['bet_id'=>$betIds]) ){
                        
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                            return true;
                        }
                        
                    }
                    
                    
                }
            }
        }
        
        return false;
        
    }
    
    //gameover Result Fancy
    public function gameoverResultFancy( $eventId , $marketId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $betList = PlaceBet::find()->select(['id','price','bet_type'])
            ->where( [ 'market_id' => $marketId, 'event_id' => $eventId ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'status' => 1 , 'match_unmatch' => 1] )
            //->andWhere( [ '<=' , 'price' , $winResult ] )
            ->asArray()->all();
            
            if( $betList != null ){
                
                foreach ( $betList as $bet ){
                    // Win
                    if( $bet['bet_type'] == 'no' && $bet['price'] > $winResult ){
                        $win = PlaceBet::findOne([ 'id' => $bet['id'] ]);
                        if( $win != null ){
                            $win->bet_status = 'Win';
                            if( $win->save( [ 'bet_status' ]) ){
                                $this->transectionWin($win->id , $eventId);
                            }
                        }
                        
                    }
                    if( $bet['bet_type'] == 'yes' && $bet['price'] <= $winResult ){
                        $win = PlaceBet::findOne([ 'id' => $bet['id'] ]);
                        if( $win != null ){
                            $win->bet_status = 'Win';
                            if( $win->save( [ 'bet_status' ]) ){
                                $this->transectionWin($win->id , $eventId);
                            }
                        }
                        
                    }
                    //Loss
                    if( $bet['bet_type'] == 'no' && $bet['price'] <= $winResult  ){
                        $loss = PlaceBet::findOne([ 'id' => $bet['id'] ]);
                        if( $loss != null ){
                            $loss->bet_status = 'Loss';
                            if( $loss->save( [ 'bet_status' ]) ){
                                $this->transactionLoss($loss->id , $eventId);
                            }
                        }
                    }
                    if( $bet['bet_type'] == 'yes' && $bet['price'] > $winResult  ){
                        $loss = PlaceBet::findOne([ 'id' => $bet['id'] ]);
                        if( $loss != null ){
                            $loss->bet_status = 'Loss';
                            if( $loss->save( [ 'bet_status' ]) ){
                                $this->transactionLoss($loss->id , $eventId);
                            }
                        }
                    }
                    
                }
            }
        }
        
    }

    //action Manual Session Fancy
    public function actionGetEventName()
    {

        $eventName = 'Not Found!';
        if( null != \Yii::$app->request->get( 'id' ) ){
            $eventId = \Yii::$app->request->get( 'id' );

            $event = EventsPlayList::find()->select(['event_name'])
                ->where(['event_id' => $eventId , 'status' => 1 ])->asArray()->one();

            if( $event != null ){
                $eventName = $event['event_name'];

                $runners = EventsRunner::find()->select(['runner'])
                    ->where(['event_id' => $eventId ])->asArray()->all();

                if( $runners != null ){
                    $runnerData = [
                        'runner1' => $runners[0]['runner'],
                        'runner2' => $runners[1]['runner'],
                    ];
                }else{
                    $runnerData = null;
                }


            }

        }

        return [ "status" => 1 , "data" => [ "title" => $eventName , 'runner' => $runnerData ] ];


    }

    //action Manual Session Fancy
    public function actionManualSession()
    {
        $pagination = []; $filters = [];

        $response = [ "status" => 1 , "data" => [ "items" => [] , "count" => 0 ] ];

        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }

        $event = EventsPlayList::find()->select(['event_name'])
            ->where(['event_id' => $id , 'status' => 1 ])->asArray()->one();

        if( $event != null ){

            $eventName = $event['event_name'];

            $query = ManualSession::find()
                ->select( [ 'id' , 'event_id','market_id','title' ,'info', 'game_over','win_result','no', 'no_rate','yes', 'yes_rate','created_at', 'updated_at' , 'status' ] )
                ->andWhere( [ 'status' => [1,2] , 'event_id' => $id ] );

            if( $filters != null ){
                if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                    $query->andFilterWhere( [ "like" , "title" , $filters[ "title" ] ] );
                }

                if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                    $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
                }
            }

            $countQuery = clone $query; $count =  $countQuery->count();

            if( $pagination != null ){
                $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                $limit  = $pagination[ 'pageSize' ];

                $query->offset( $offset )->limit( $limit );
            }

            $models = $query->orderBy( [ "status" => SORT_ASC ] )->asArray()->all();

            $response = [ "status" => 1 , "data" => [ "eventName" => $eventName,"items" => $models , "count" => $count ] ];

        }

        return $response;
        
    }
    
    //action Manual Session Fancy Status
    public function actionManualSessionStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = ManualSession::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status == 1 ){
                        $event->status = 2;
                    }else{
                        $event->status = 1;
                    }
                    
                    if( $event->save( [ 'status' ] ) ){
                        
                        $sts = $event->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "ManualSession $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "event status not changed!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }

    //action Create Manual Fancy Session
    public function actionManualMatchOddsAddRule(){

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset($r_data['rule']) && $r_data['rule'] != null ){
                $model = ManualSessionMatchOdd::findOne(['event_id' => $r_data['event_id'],'market_id'=>$r_data['market_id'] ]);

                if( $model != null ){

                    $model->info = $r_data['rule'];

                    if( $model->save() ){

                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Rule added successfully!"
                            ]
                        ];

                    }else{

                        $response[ "error" ] = [
                            "message" => "Rule not saved!"
                        ];

                    }

                }else{

                    $response[ "error" ] = [
                        "message" => "Something wrong! "
                    ];

                }

            }

        }

        return $response;

    }

    //action Create New Event
    public function actionCreateNewEvent(){

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            $eventId = rand(71234567,99999999);
            $marketId = '9.'.time();
            $check = EventsPlayList::findOne([ 'event_id' => $eventId , 'market_id' => $marketId ]);

            if( $check != null ){
                $eventId = rand(712345678,999999999);
                $marketId = '9.'.time();
            }

            $model = new EventsPlayList();

            $model->sport_id = 4;
            $model->event_id = $eventId;
            $model->market_id = $marketId;
            $model->event_name = $r_data['event_name'];
            $model->event_league = $r_data['event_league'];
            $model->event_time = ($r_data['event_time']+0)*1000;
            $model->play_type = $r_data['play_type'];
            $model->status = 2;
            $model->created_at = $model->updated_at = time();

            if( $model->save() ){

                $eventId = $model->event_id;
                $marketId = $model->market_id;
                $rData = [];
                if( isset( $r_data['runner1'] ) ){
                    $rData[0] = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'selection_id' => rand(44444,99999),
                        'runner' => $r_data['runner1'] == '' ? 'Runner 1' : $r_data['runner1'],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if( isset( $r_data['runner2'] ) ){
                    $rData[1] = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'selection_id' => rand(44444,99999),
                        'runner' => $r_data['runner2'] == '' ? 'Runner 2' : $r_data['runner2'],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if( isset( $r_data['runner3'] ) && $r_data['runner3'] != null && $r_data['runner3'] != '' ){
                    $rData[2] = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'selection_id' => rand(44444,99999),
                        'runner' => $r_data['runner3'],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if( $rData != null ){
                    $col = [ 'event_id', 'market_id' , 'selection_id' , 'runner' , 'created_at' , 'updated_at'];
                    Yii::$app->db->createCommand()->batchInsert('events_runners', $col , $rData )->execute();

                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Create event successfully!"
                        ]
                    ];
                }else{
                    $model->delete();
                    $response[ "error" ] = [
                        "message" => "Something wrong! Create event not Created !"
                    ];
                }



            }else{
                $response[ "error" ] = [
                    "message" => "Something wrong! Create event not Created !"
                ];
            }

        }

        return $response;
    }

    //action Create Manual Fancy Session
    public function actionCreateManualSession(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        $sessionData = [];
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            $model = new ManualSession();

            $settingData = Setting::find()->select(['key','value'])->where(['key' => ['MFY_MAX_STAKE_DEFAULT','MFY_MIN_STAKE_DEFAULT','MFY_MAX_PROFIT_DEFAULT','MFY_MAX_PROFIT_LIMIT_DEFAULT','MFY_BET_DELAY_DEFAULT']])->asArray()->all();

            if( $settingData != null ){
                foreach ( $settingData as $setting ){

                    if( $setting['key'] == 'MFY_MIN_STAKE_DEFAULT' ){
                        $model->min_stack = $setting['value'];
                    }
                    if( $setting['key'] == 'MFY_MAX_STAKE_DEFAULT' ){
                        $model->max_stack = $setting['value'];
                    }
                    if( $setting['key'] == 'MFY_MAX_PROFIT_DEFAULT' ){
                        $model->max_profit = $setting['value'];
                    }
                    if( $setting['key'] == 'MFY_MAX_PROFIT_LIMIT_DEFAULT' ){
                        $model->max_profit_limit = $setting['value'];
                    }
                    if( $setting['key'] == 'MFY_BET_DELAY_DEFAULT' ){
                        $model->bet_delay = $setting['value'];
                    }

                }

            }

            $model->event_id = $id;
            $model->market_id = '1.'.time().'-MFY';
            $model->title = $r_data['title'];

            //$model->no = $r_data['no_val'];
            //$model->yes = $r_data['yes_val'];
            //$model->no_rate = $r_data['no_rate'];
            //$model->yes_rate = $r_data['yes_rate'];
            
            $model->status = 2;
            $model->created_at = $model->updated_at = time();

            $check = ManualSession::findOne([ 'market_id' => $model->market_id ]);

            if( $check == null && $model->save() ){

                //$key = 'ManualFancy-'.$model->market_id;
                $key = $this->FANCY_KEY.$model->market_id;
                $sessionData = [
                    'event_id' => $model->event_id,
                    'market_id' => $model->market_id,
                    'title' => $model->title,
                    'game_over' => 'NO',
                    'win_result' => 'undefined',
                    'suspended' => 'Y',
                    'ball_running' => 'N',
                    'no' => '0',
                    'no_rate' => '0',
                    'yes' => '0',
                    'yes_rate' => '0',
                    'status' => '1',
                    'isBook' => 0,
                    'maxLoss' => 0,
                    'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                    'time' => round(microtime(true) * 1000),
                ];

                if( $sessionData != null ){
                    $cache = Yii::$app->cache;
                    $cache->set( $key , json_encode($sessionData) );
                }

                $response = [
                    'status' => 1 ,
                    "success" => [
                        "message" => "ManualSession added successfully!"
                    ]
                ];
            }else{
                $response[ "error" ] = [
                    "message" => "Somthing wrong! ManualSession not Created !!"
                ];
            }
            
        }
        
        return $response;
    }
    
    //action Data Manual Session
    public function actionManualSessionData(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        if( null != \Yii::$app->request->get( 'id' ) ){
            
            $id = \Yii::$app->request->get( 'id' );

            $model = (new \yii\db\Query())
            ->select(['event_id','market_id','title','game_over','win_result','suspended','suspended','ball_running','no','no_rate','yes','yes_rate','status'])
            ->from('manual_session')
            ->where(['id'=>$id])->one();
            
            if( $model != null ){

                $marketId = $model['market_id'];
                $eventId = $model['event_id'];

                $key = $this->FANCY_KEY.$marketId;

                $cache = Yii::$app->cache;
                if( !$cache->exists($key) ){

                    $sessionData = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'title' => $model['title'],
                        'game_over' => $model['game_over'],
                        'win_result' => $model['win_result'],
                        'suspended' => $model['suspended'],
                        'ball_running' => $model['ball_running'],
                        'no' => $model['no'],
                        'no_rate' => $model['no_rate'],
                        'yes' => $model['yes'],
                        'yes_rate' => $model['yes_rate'],
                        'status' => '1',
                        'isBook' => 0,
                        'maxLoss' => 0,
                        'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                        'time' => round(microtime(true) * 1000),
                    ];

                    if( $sessionData != null ){
                        $cache->set( $key , json_encode($sessionData) );
                    }
                }


                $isBook = 0;
                $maxLoss = '--';

                $model['isBook'] = $isBook;
                $model['maxLoss'] = $maxLoss;
                $model['sessionSuspendedTime'] = $this->getSessionSuspendedTime();
                
                $event = (new \yii\db\Query())
                ->select(['event_name'])
                ->from('events_play_list')
                ->where(['event_id'=>$model['event_id'] , 'sport_id' => 4 , 'status' => 1 , 'game_over' => 'NO' ])->one();
                
                if( $event != null ){
                    
                    $where = ['market_id' => $marketId ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];
                    
                    $query = PlaceBet::find()
                    ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                    ->where($where);
                    
                    $betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
                    $betList = [];
                    if( $betListData != null ){
                        $betList = $betListData;
                    }

                    $rateOption = ["100/100", "95/105", "90/110", "85/115", "80/120", "75/125", "70/90", "60/90", "75/95"];

                    $setting = (new \yii\db\Query())
                        ->select(['value'])
                        ->from('setting')
                        ->where(['key'=>['RATE_OPTIONS_1','RATE_OPTIONS_2','RATE_OPTIONS_3'] , 'status' => 1 ])
                        ->orderBy(['key'=>SORT_ASC])->all();

                    if( $setting != null ){
                        $rateOption = [];
                        foreach ( $setting as $set ){

                            $rateOpt = explode( ',' , $set['value'] );

                            if( count( $rateOpt ) > 0 ){

                                foreach ( $rateOpt as $opt ){
                                    $rateOption[] = $opt;
                                }

                            }

                        }

                    }

                    $response = [
                        "status" => 1 ,
                        "data" => [ "title" => $event['event_name'],"items" => $model , "betList" => $betList , "rateOption" => $rateOption ]
                    ];
                }
                
            }
            
        }
        
        return $response;
    }

    //action Data Manual Session
    public function actionManualSessionBetList(){

        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        if( null != \Yii::$app->request->get( 'id' ) ){

            $id = \Yii::$app->request->get( 'id' );

            $model = (new \yii\db\Query())
                ->select(['event_id','market_id'])
                ->from('manual_session')
                ->where(['id'=>$id])->one();

            if( $model != null ){
                $marketId = $model['market_id'];
                $eventId = $model['event_id'];

                $where = ['market_id' => $marketId ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];

                $query = PlaceBet::find()
                    ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                    ->where($where);

                $betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
                $betList = [];
                if( $betListData != null ){
                    $betList = $betListData;
                }

                $commentary = (new \yii\db\Query())
                    ->select(['title'])
                    ->from('global_commentary')
                    ->where(['event_id'=>$eventId])->one();

                $suspended = $ballRunning = false;

                if( $commentary != null ){
                    if( trim($commentary['title']) == 'Suspended' ){
                        $suspended = true;
                    }
                    if( trim($commentary['title']) == 'Ball Running' ){
                        $ballRunning = true;
                    }
                }

                //$isBook = $this->isBookOn($marketId , 'fancy');

                $response = [
                    "status" => 1 ,
                    "data" => [
                        "betList" => $betList,
                        "suspended" => $suspended,
                        "ballRunning" => $ballRunning,
                        "maxLoss" => '',//$this->getMaxLossOnFancy($isBook,$eventId,$marketId,'fancy')
                    ]
                ];

            }

        }

        return $response;
    }

    // Event: Commentary
    public function getSessionSuspendedTime(){
        
        $sessionSuspendedTime = '30';
        
        $data = Setting::findOne(['key'=>'SESSION_SUSPENDED_TIME' , 'status'=>1 ]);
        
        if( $data != null ){
            $sessionSuspendedTime = $data->value;
        }
        
        return $sessionSuspendedTime;
    }
    
    // Cricket: isBookOn
    public function isBookOn($marketId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        //$role = \Yii::$app->authManager->getRolesByUser($uid);
        
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }elseif( isset($role['sessionuser']) && $role['sessionuser'] != null ){
//            $AllClients = $this->getAllClientForAdmin(1);
//        }else{
//            $AllClients = [];
//        }

        //$AllClients = [];
        //if( isset($role['sessionuser']) && $role['sessionuser'] != null ){
            //$uid = 1;
        //}

        //$AllClients = $this->getClientListByUserId($uid);

        //echo '<pre>';print_r($AllClients);die;
        //if( $AllClients != null && count($AllClients) > 0 ){
            
            $where = [ 'bet_status' => 'Pending','status' => 1,'session_type' => $sessionType,'market_id' => $marketId ];
            //$andWhere = ['IN','user_id', $AllClients];
            
            $findBet = (new \yii\db\Query())
            ->select(['id'])->from('place_bet')
            ->where($where)
            //->andWhere($andWhere)
            ->one();
            
            if( $findBet != null ){
                return '1';
            }
            return '0';
            
        //}
        
    }
    
    public function getMaxLossOnFancy($isBook,$eventId, $marketId, $sessionType)
    {
        $maxLoss = 0;
        if( $isBook == 1 ){
            $dataArr = $this->getProfitLossFancy($eventId, $marketId, $sessionType);
            if( $dataArr != null ){
                $maxLossArr = [];
                foreach ( $dataArr as $data ){
                    if( $data['profitLoss'] < 0 ){
                        $maxLossArr[] = $data['profitLoss'];
                    }
                }
                
                if( $maxLossArr != null ){
                    $maxLoss = min($maxLossArr);
                }
            }
        }
        
        return $maxLoss;
        
    }
    
    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancy($eventId,$marketId,$sessionType)
    {
        $uid = \Yii::$app->user->id;
        $role = \Yii::$app->authManager->getRolesByUser($uid);
        
//        if( isset($role['admin']) && $role['admin'] != null ){
//            $AllClients = $this->getAllClientForAdmin($uid);
//        }elseif(isset($role['agent1']) && $role['agent1'] != null){
//            $AllClients = $this->getAllClientForSuperMaster($uid);
//        }elseif(isset($role['agent2']) && $role['agent2'] != null){
//            $AllClients = $this->getAllClientForMaster($uid);
//        }elseif(isset($role['sessionuser']) && $role['sessionuser'] != null){
//            $AllClients = $this->getAllClientForAdmin(1);
//            $uid = 1;
//        }else{
//            $AllClients = [];
//        }
        $AllClients = [];
        if( isset($role['sessionuser']) && $role['sessionuser'] != null ){
            $uid = 1;
        }

        $getUser = PlaceBet::find()
            ->select(['user_id'])
            ->where( [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ] )->asArray()->all();

        if( $getUser != null ){
            foreach ( $getUser as $usr ){
                $AllClients[] = $usr['user_id'];
            }
        }

        //$AllClients = $this->getClientListByUserId($uid);

        //echo '<pre>';print_r($AllClients);die;
        if( $AllClients != null && count($AllClients) > 0 ){
            $totalArr = [];
            $total = 0;
            $dataReturn = null;
            
            $where = [ 'bet_status' => 'Pending','session_type' => $sessionType,'market_id' => $marketId , 'status' => 1 ];
            $andWhere = ['IN','user_id', $AllClients];

            $betList = PlaceBet::find()
                ->select(['bet_type','price','win','loss'])
                ->where( $where )->andWhere( $andWhere )->asArray()->all();

//            $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->andWhere($andWhere)->asArray()->one();
//
//            if( isset( $betMinRun['price'] ) ){
//                $minRun = $betMinRun['price']-1;
//            }
//
//            if( isset( $betMaxRun['price'] ) ){
//                $maxRun = $betMaxRun['price']+1;
//            }
            
            //echo $minRun.' - '.$maxRun;die;

            if( $betList != null ){

                $min = 0;
                $max = 0;

                foreach ($betList as $index => $bet) {
                    if ($index == 0) {
                        $min = $bet['price'];
                        $max = $bet['price'];
                    }
                    if ($min > $bet['price'])
                        $min = $bet['price'];
                    if ($max < $bet['price'])
                        $max = $bet['price'];
                }

                $min = $min-1;
                $max = $max+1;

                for($i=$min;$i<=$max;$i++){

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',$i]);
                    $command = $query->createCommand();
                    $betList1 = $command->queryAll();


                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.win*upl.actual_profit_loss )/100 as winVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',$i]);
                    $command = $query->createCommand();
                    $betList2 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'yes','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['>','pb.price',$i]);
                    $command = $query->createCommand();
                    $betList3 = $command->queryAll();

                    $query = new Query();
                    $where = [ 'pb.status' => 1,'pb.bet_status' => 'Pending','pb.bet_type' => 'no','pb.session_type' => $sessionType,'pb.user_id' => $AllClients,'pb.market_id' => $marketId ];
                    $query->select([ 'SUM( pb.loss*upl.actual_profit_loss )/100 as lossVal' ] )
                        ->from('place_bet as pb')
                        ->join('LEFT JOIN', 'user_profit_loss as upl',
                            'upl.client_id=pb.user_id AND upl.user_id='.$uid)
                        ->where( $where )->andWhere(['<=','pb.price',$i]);
                    $command = $query->createCommand();
                    $betList4 = $command->queryAll();

                    if( !isset($betList1[0]['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1[0]['winVal']; }
                    if( !isset($betList2[0]['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2[0]['winVal']; }
                    if( !isset($betList3[0]['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3[0]['lossVal']; }
                    if( !isset($betList4[0]['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4[0]['lossVal']; }

                    $profit = ( $winVal1 + $winVal2 );
                    $loss = ( $lossVal1 + $lossVal2 );

                    $total = $loss-$profit;

                    $dataReturn[] = [
                        'price' => $i,
                        'profitLoss' => $total,
                    ];
                }

            }

        }
        
        return $dataReturn;
    }
    
    //action Update Manual Session
    public function actionUpdateManualSession(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $sessionData = [];
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            //$model = ManualSession::find()
            //    ->select(['id','market_id','suspended','ball_running'])->where(['id' => $r_data['id']])->asArray()->one();

            //$key = 'MANUAL-FANCY-'.$model['market_id'];
//            $key = 'ManualFancy-'.$model['market_id'];
//
//            $cache = Yii::$app->cache;
//            if( $cache->exists($key) ) {
//                $sessionData = $cache->get($key);
//                $sessionData = json_decode($sessionData,true);
//            }

            $connection = \Yii::$app->db->createCommand();

            $model = ManualSession::find()
                ->select(['id','market_id','suspended','ball_running'])->where(['id' => $r_data['id']])->asArray()->one();

            if( isset( $r_data['title'] ) ){
                $info = null;
                if(isset($r_data['info'])){
                    $info = $r_data['info'];
                }

                $connection->update('manual_session', ['title' => $r_data['title'] , 'info' => $info , 'updated_at' => time() ],
                    ['id' => $r_data['id']])->execute();

                $this->redisUpdateManualFancy($model['market_id'],'title',$r_data['title'] );

                $response = [ "status" => 1 , 'data' => [] , "message" => "ManualSession Updated successfully!" ];
                return $response;
            }

            if( isset( $r_data['suspended'] ) && ( $r_data['suspended'] == true ) ){
                
//                if( $model['suspended'] == 'N' ){
//                    $suspended = 'Y';
//                }else{ $suspended = 'N'; }

                $suspended = 'Y';

                //if( $model->save( ['suspended','updated_at'] ) ){
                if( $connection->update('manual_session', ['suspended' => $suspended , 'updated_at' => time() ],
                    ['id' => $r_data['id']])->execute() ){

                    $sessionData = $this->redisUpdateManualFancy($model['market_id'],'suspended',null);

//                    if( $sessionData != null ){
//                        $sessionData['suspended'] = $suspended;
//                        $sessionData['time'] = round(microtime(true) * 1000);
//                        $cache->set( $key , json_encode($sessionData) );
//                    }

                    $status = 1;
                    $message = "ManualSession Suspended successfully!";

                }
            }
            
            if( isset( $r_data['ball_running'] ) && ( $r_data['ball_running'] == true ) ){
                

//                if( $model['ball_running'] == 'N' ){
//                    $ballRunning = 'Y';
//                }else{ $ballRunning = 'N'; }

                $ballRunning = 'Y';

                //if( $model->save( ['ball_running','updated_at'] ) ){
                if( $connection->update('manual_session', ['ball_running' => $ballRunning , 'updated_at' => time() ],
                    ['id' => $r_data['id']])->execute() ){

                    $sessionData = $this->redisUpdateManualFancy($model['market_id'],'ball_running',null);

//                    if( $sessionData != null ){
//                        $sessionData['ball_running'] = $ballRunning;
//                        $sessionData['time'] = round(microtime(true) * 1000);
//                        $cache->set( $key , json_encode($sessionData) );
//                    }

                    $status = 1;
                    $message = "ManualSession Ball Running successfully!";
                }
            }
            
            
            if ( $model != null && $r_data['ball_running'] != true
                && $r_data['suspended'] != true ) {
                
                if( !is_numeric($r_data['no_val']) || !is_numeric($r_data['yes_val']) || !is_numeric($r_data['no_rate']) || !is_numeric($r_data['yes_rate']) ){
                    
                    return $response = [
                        'status' => 0 ,
                        "error" => [
                            "message" => "Somthing wrong! Invalid Value Enter !!"
                        ]
                    ];
                }


                $changeData = [
                    'no' => $r_data['no_val'],
                    'yes' => $r_data['yes_val'],
                    'no_rate' => $r_data['no_rate'],
                    'yes_rate' => $r_data['yes_rate'],
                    'suspended' => 'N',
                    'ball_running' => 'N',
                    'updated_at' => time(),
                ];
                
                //if( $model->save( ['no','yes','no_rate','yes_rate','suspended','ball_running','updated_at'] ) ){
                if( $connection->update('manual_session',$changeData, ['id' => $r_data['id']])->execute() ){

//                    if( $sessionData != null ){
//
//                        $sessionData['no'] = $r_data['no_val'];
//                        $sessionData['yes'] = $r_data['yes_val'];
//                        $sessionData['no_rate'] = $r_data['no_rate'];
//                        $sessionData['yes_rate'] = $r_data['yes_rate'];
//                        $sessionData['suspended'] = 'N';
//                        $sessionData['ball_running'] = 'N';
//                        $sessionData['time'] = round(microtime(true) * 1000);
//
//                        $cache->set( $key , json_encode($sessionData) );
//                    }

                    $sessionData = $this->redisUpdateManualFancy( $r_data['market_id'],'open',$r_data );

                    $d = [ 'manual_session_id' => $model['id'],
                        'no' => $r_data['no_val'],
                        'yes' => $r_data['yes_val'],
                        'no_rate' => $r_data['no_rate'],
                        'yes_rate' => $r_data['yes_rate'],
                        'updated_at' => time()
                    ];

                    \Yii::$app->db->createCommand()->insert('manual_session_data', $d )->execute();

                    $status = 1;
                    $message = "ManualSession updated successfully!";
                }else{
                    $status = 0;
                    $message = "Something wrong!";
                }
                
            }

//            $key = 'ManualFancy-'.$model['market_id'];
//            $cache = Yii::$app->cache;
//            if( $cache->exists($key) ) {
//                $sessionData = $cache->get($key);
//                $sessionData = json_decode($sessionData,true);
//            }
//
            if( $sessionData != null ){

                $marketId = $sessionData['market_id'];
                $eventId = $sessionData['event_id'];

                $key = $this->FANCY_KEY.$marketId;

                $isBook = 1;//$this->isBookOn($marketId,'fancy');
                $maxLoss = '--';//$this->getMaxLossOnFancy($isBook,$eventId,$marketId,'fancy');

                $sessionData['isBook'] = $isBook;
                $sessionData['maxLoss'] = $maxLoss;
                $sessionData['sessionSuspendedTime'] = $this->getSessionSuspendedTime();
                $sessionData['time'] = round(microtime(true) * 1000);

                $cache = Yii::$app->cache;
                $cache->set( $key , json_encode($sessionData) );

                $event = (new \yii\db\Query())
                    ->select(['event_name'])
                    ->from('events_play_list')
                    ->where(['event_id'=>$eventId , 'sport_id' => 4 , 'status' => 1 , 'game_over' => 'NO' ])->one();

                if( $event != null ){

                    $where = ['market_id' => $marketId ,'event_id' => $eventId , 'status' => 1 ,'bet_status' => 'Pending'];

                    $query = PlaceBet::find()
                        ->select( [ 'id','sport_id','event_id','market_id','sec_id','user_id','client_name' , 'master' , 'runner' , 'bet_type' , 'ip_address' , 'price' , 'rate','size' , 'win' , 'loss' , 'bet_status' ,'session_type', 'status' ,'match_unmatch', 'created_at' ] )
                        ->where($where);

                    $betListData = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
                    $betList = [];
                    if( $betListData != null ){
                        $betList = $betListData;
                    }

                }

            }

            $rateOption = ["100/100", "95/105", "90/110", "85/115", "80/120", "75/125", "70/90", "60/90", "75/95"];

            $setting = (new \yii\db\Query())
                ->select(['value'])
                ->from('setting')
                ->where(['key'=>['RATE_OPTIONS_1','RATE_OPTIONS_2','RATE_OPTIONS_3'] , 'status' => 1 ])
                ->orderBy(['key'=>SORT_ASC])->all();

            if( $setting != null ){
                $rateOption = [];
                foreach ( $setting as $set ){

                    $rateOpt = explode( ',' , $set['value'] );

                    if( count( $rateOpt ) > 0 ){

                        foreach ( $rateOpt as $opt ){
                            $rateOption[] = $opt;
                        }

                    }

                }

            }
            
        }

        $data = [ "title" => $event['event_name'],"items" => $sessionData , "betList" => $betList , "rateOption" => $rateOption ];

        $response = [ "status" => $status , 'data' => $data , "message" => $message ];
        return $response;
    }
    
    /*
     *Lottery Actions
     */
    
    // Game Over Lottery
    public function actionGameoverLottery(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        //$id = \Yii::$app->request->get( 'id' );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'result' ] ) ){
                
                $manualSession = ManualSessionLottery::findOne(['market_id' => $r_data[ 'id' ] , 'game_over' => 'NO' , 'status' => '1' ]);
                
                if( $manualSession != null ){
                    
                    $eventId = $manualSession->event_id;
                    $event = EventsPlayList::findOne(['event_id'=>$eventId]);
                    
                    $manualSession->game_over = 'YES';
                    $manualSession->win_result = $r_data[ 'result' ];
                    
                    $this->gameoverResultLottery( $manualSession->event_id  , $manualSession->market_id  , $manualSession->win_result );
                    
                    if( $manualSession->save( [ 'game_over' , 'win_result' ] ) ){
                        
                        $resultArr = [
                            'sport_id' => $event->sport_id,
                            'event_id' => $manualSession->event_id,
                            'event_name' => $event->event_name,
                            'market_id' => $manualSession->market_id,
                            'market_name' => $manualSession->market_name,
                            'result' => $manualSession->win_result,
                            'session_type' => 'lottery',
                            'updated_at' => time(),
                            'status' => 1,
                        ];
                        
                        \Yii::$app->db->createCommand()
                        ->insert('market_result', $resultArr )->execute();
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Manual Session game over successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! Manual Session not updated!"
                        ];
                    }
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! event not found!"
                    ];
                }
            }
        }
        return $response;
    }
    
    //gameover Result Lottery
    public function gameoverResultLottery( $eventId , $marketId , $winResult ){
        
        if( isset( $eventId ) && isset( $winResult ) && ( $winResult != null ) && ( $eventId != null ) ){
            
            /*User Win calculation */
            $backWinList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'price' => $winResult ] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($layWinList);die;
            if( $backWinList != null ){
                foreach( $backWinList as $list ){
                    $win = PlaceBet::findOne([ 'id' => $list['id'] , 'market_id' => $marketId ]);
                    //echo '<pre>';print_r($win);die;
                    if( $win != null ){
                        $win->bet_status = 'Win';
                        if( $win->save( [ 'bet_status' ]) ){
                            $this->transectionWin($win->id,$win->event_id);
                        }
                    }
                }
            }
            
            /* User Loss calculation */
            
            $lossList = PlaceBet::find()->select(['id' , 'event_id'])
            ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
            ->where( [ '!=', 'bet_status' , 'Win'] )
            ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            
            if( $lossList != null ){
                
                foreach( $lossList as $list ){
                    $loss = PlaceBet::findOne([ 'id' => $list['id'] , 'market_id' => $marketId ]);
                    if( $loss != null ){
                        $loss->bet_status = 'Loss';
                        if( $loss->save( [ 'bet_status' ]) ){
                            $this->transactionLoss($loss->id,$loss->event_id);
                        }
                    }
                }
                
            }
        }
        
    }
    
    // Game Recall Lottery
    public function actionGameRecallLottery(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data['type'] ) ){
                
                if( $r_data['type'] == 'lottery' ){
                    
                    $event = ManualSessionLottery::findOne( ['market_id' => $r_data[ 'id' ] , 'game_over'=>'YES','status' => 1 ] );
                    
                    if( $event != null ){
                        
                        $event->game_over = 'NO';
                        $event->win_result = 'undefined';
                        
                        if( $this->gameRecallLootery( $event->market_id ) == true ){
                            if( $event->save( [ 'game_over' , 'win_result' ] ) ){
                                $response = [
                                    'status' => 1,
                                    "success" => [
                                        "message" => "Game Recall Successfully!"
                                    ]
                                ];
                            }else{
                                $response[ "error" ] = [
                                    "message" => "Something wrong! event not updated!"
                                ];
                            }
                            
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! event not updated!"
                            ];
                        }
                    }
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong!"
                    ];
                }
            }
        }
        return $response;
    }
    
    //game Recall Lottery
    public function gameRecallLootery( $marketId ){
        
        if( isset($marketId) && $marketId != null ){
            
            $betList = PlaceBet::find()->select(['id'])->where( [ 'market_id' => $marketId ] )
            ->andWhere( [ 'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->asArray()->all();
            //echo '<pre>';print_r($allBetListArr);die;
            if( $betList != null ){
                
                $betIds = [];
                foreach ( $betList as $betId ){
                    $betIds[] = $betId['id'];
                }
                
                $transArr = TempTransactionHistory::findAll(['bet_id'=>$betIds]);
                
                if( $transArr != null ){
                    
                    foreach ( $transArr as $trans ){
                        
                        $user = User::findOne(['id'=>$trans->user_id]);
                        if( $trans->transaction_type == 'CREDIT' ){
                            $user->balance = ($user->balance-$trans->transaction_amount);
                        }else{
                            $user->balance = ($user->balance+$trans->transaction_amount);
                        }
                        $user->save(['balance']);
                    }
                    
                    if( TempTransactionHistory::updateAll(['status'=>2],['bet_id'=>$betIds]) ){
                        
                        if( PlaceBet::updateAll(['bet_status'=>'Pending'],['id'=>$betIds]) ){
                            return true;
                        }
                        
                    }
                    
                }
            }
        }
        
        return false;
        
    }
    
    //action Manual Session Lottery
    public function actionManualSessionLottery()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $eId = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        $query = ManualSessionLottery::find()
        ->select( [ 'id' , 'event_id','market_id','title' ,'game_over','win_result','rate', 'created_at', 'updated_at' , 'status' ] )
        //->from( Events::tableName() . ' e' )
        ->andWhere( [ 'status' => [1,2] , 'event_id' => $eId ] );
        
        if( $filters != null ){
            if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                $query->andFilterWhere( [ "like" , "title" , $filters[ "title" ] ] );
            }
            
            if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "status" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    //action Manual Session Lottery Status
    public function actionManualSessionLotteryStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = ManualSessionLottery::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status == 1 ){
                        $event->status = 2;
                    }else{
                        $event->status = 1;
                    }
                    
                    if( $event->save( [ 'status' ] ) ){
                        
                        $sts = $event->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "ManualSessionLottery $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "event status not changed!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    // action Manual Session LotteryNumber
    public function actionManualSessionLotteryNumber()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        $query = ManualSessionLotteryNumbers::find()
        ->select( [ 'id' , 'manual_session_lottery_id','number' , 'rate', 'updated_at' ] )
        //->from( Events::tableName() . ' e' )
        ->andWhere( [ 'manual_session_lottery_id' => $id ] );
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    //action Create Manual Session Lottery
    public function actionCreateManualSessionLottery(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $eId = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['ManualSessionLottery'] = $r_data;
            $model = new ManualSessionLottery();
            
            if ($model->load($data)) {
                
                $model->event_id = $eId;
                $model->market_id = '1.'.time().'-L';
                $model->title = $r_data['title'];
                $model->rate = $r_data['rate'];
                $model->game_over = 'NO';
                $model->win_result = 'undefined';
                $model->status = 1;
                $model->created_at = $model->updated_at = time();
                
                if( $model->save() ){
                    
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Manual Session Lottery added successfully!"
                        ]
                    ];
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Somthing wrong!" ,
                        "data" => $model->errors
                    ];
                }
                
            }
            
        }
        
        return $response;
    }
    
    
    /*
     * Common Functions
     */

    // Update Transaction History
    public function updateTransactionHistory($type,$clientId,$uId,$parentId,$betId,$eventId,$parentType,$uName,$amount,$ccr,$balance)
    {
        $trans = new TransactionHistory();
        
        if( $type == 'CREDIT' ){
            $trans->client_id = $clientId;
            $trans->user_id = $uId;
            $trans->parent_id = $parentId;
            $trans->bet_id = $betId;
            $trans->event_id = $eventId;
            $trans->parent_type = $parentType;
            $trans->username = $uName;
            $trans->transaction_type = 'CREDIT';
            $trans->transaction_amount = $amount;
            $trans->commission = $ccr;
            $trans->current_balance = $balance;
            $trans->description = $this->getDescription($betId,$eventId);
            $trans->status = 1;
            $trans->updated_at = $trans->created_at = time();
        }else{
            $trans->client_id = $clientId;
            $trans->user_id = $uId;
            $trans->parent_id = $parentId;
            $trans->bet_id = $betId;
            $trans->event_id = $eventId;
            $trans->parent_type = $parentType;
            $trans->username = $uName;
            $trans->transaction_type = 'DEBIT';
            $trans->transaction_amount = $amount;
            $trans->commission = $ccr;
            $trans->current_balance = $balance;
            $trans->description = $this->getDescription($betId,$eventId);
            $trans->status = 1;
            $trans->updated_at = $trans->created_at = time();
        }
        
        if( $trans->save() ){
            return true;
        }else{
            return false;
        }
        
    }
    
    // Function to get the Tras Description
    public function getDescription($betId,$eventId)
    {
        $type = $size = 'NoData';
        
        $betData = PlaceBet::find()->select(['bet_type','size','rate','description'])
        ->where([ 'id' => $betId,'event_id' => $eventId,'status'=>1, 'bet_status' => ['Win','Loss'] ])->one();
        
        if( $betData != null ){
            $type = $betData->bet_type;
            $size = $betData->size;
            $rate = $betData->rate;
            $description = $betData->description;
        }
        
        $description = $description.' | '.$rate.' | '.$type.' | '.$size;
        
        return $description;
    }
    
    //action Eventlist Status
    public function actionEventlistStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ] ) ){
                
                $event = EventsPlayList::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    $type = $r_data[ 'type' ];
                    if( $type == 'status' ){
                        if( $event->status == 1 ){
                            $event->status = 2;
                        }else{
                            $event->status = 1;
                        }
                        $arr = [ 'status' ];
                        $sts = $event->status == 1 ? 'Active' : 'Inactive';
                    }else if( $type == 'delete' ) {
                        $event->status = 0;
                        $arr = [ 'status' ];
                        $sts = 'Delete';
                    }else if( $type == 'restore' ) {
                        $event->status = 2;
                        $arr = [ 'status' ];
                        $sts = 'Restore';
                    }else{
                        
                        $arr = [ $type ];
                        if( $type == 'suspended' ){
                            if( $event->suspended == 'N' ){ $event->suspended = 'Y';}else{ $event->suspended = 'N';}
                            $sts = $event->suspended == 'N' ? 'Suspended: No' : 'Suspended: Yes';
                        }elseif( $type == 'ball_running' ){
                            if( $event->ball_running == 'N' ){ $event->ball_running = 'Y';}else{ $event->ball_running = 'N';}
                            $sts = $event->ball_running == 'N' ? 'Ball Running: No' : 'Ball Running: Yes';
                        }else{
                            if( $event->match_odd_status == 1 ){ $event->match_odd_status = 2;}else{ $event->match_odd_status = 1;}
                            $sts = $event->match_odd_status == 1 ? 'Match Odd: Active' : 'Match Odd: InActive';
                        }
                    }
                    
                    if( $event->save( $arr ) ){
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Event $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Event status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    /*
     * Market Actions
     */
    
    //action Market
    public function actionMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $pagination = []; $filters = [];
        //$data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $r_data = ArrayHelper::toArray( $request_data );
        //echo '<pre>';print_r($r_data);die;
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $request_data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            
            $pagination = $filter_args;
        }
        
        if( json_last_error() == JSON_ERROR_NONE ){
            
            if( isset( $r_data[ 'id' ] ) ){
                $eId = $r_data[ 'id' ];
                $title = '';
                $event = EventsPlayList::find()->select(['event_name'])->where(['event_id' => $eId])->asArray()->one();
                
                if( $event != null ){
                    $title = $event['event_name'];
                }
                
                $query = MarketType::find()
                ->select( [ 'id' , 'event_type_id' , 'event_id','market_id' , 'market_type' , 'market_name', 'game_over', 'win_result', 'created_at' , 'updated_at' , 'suspended','ball_running','status' ] )
                ->andWhere( [ 'status' => [1,2,3] , 'event_id' => $eId ] );
            
                if( $filters != null ){
                    if( isset( $filters[ "title" ] ) && $filters[ "title" ] != '' ){
                        $query->andFilterWhere( [ "like" , "market_name" , $filters[ "title" ] ] );
                    }
                    
                    if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                        $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
                    }
                }
        
                $countQuery = clone $query; $count =  $countQuery->count();
            
                if( $pagination != null ){
                    $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
                    $limit  = $pagination[ 'pageSize' ];
                    
                    $query->offset( $offset )->limit( $limit );
                }
        
                $models = $query->orderBy( [ "id" => SORT_DESC ] )->asArray()->all();
            
                //echo '<pre>';print_r($models);die;
                $response = [ "status" => 1 , "data" => [ "items" => $models ,'title'=>$title, "count" => $count ] ];
            }
        }
        return $response;
           
    }

    // CopyMarket
    public function actionCopyMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        $r_data = ArrayHelper::toArray( $request_data );

        if( json_last_error() == JSON_ERROR_NONE ){

            $marketId = $r_data['market_id'];

            $market = MarketType::find()
                ->select( [ 'event_id','market_name'] )
                ->where( [ 'market_id' => $marketId ] )->asArray()->one();

            if( $market != null ){

                $model = new ManualSession();

                $model->event_id = $market['event_id'];
                $model->market_id = '1.'.time().'-MFY';
                $model->title = $market['market_name'];

                $model->status = 2;
                $model->created_at = $model->updated_at = time();

                if( $model->save() ){
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "ManualSession added successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! ManualSession not Created !!"
                    ];
                }

            }

        }

        return $response;

    }

    // Refresh Market
    public function actionRefreshMarketOLD08092019()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $eventId = $r_data[ 'id' ];
                $event = EventsPlayList::findOne(['event_id'=>$eventId]);

                if( $event != null ){
                    //$marketId = $event->market_id;
                    //CODE for live call api
                    //$url = 'http://52.208.223.36/api/dream/get_session/'.$marketId;
                    $url = 'http://52.50.107.50/get_fancy.php?matchId='.$eventId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);

                    $marketArr = $allData = [];

                    //echo '<pre>';print_r($responseData);die;
                    if( isset( $responseData->data ) && $responseData->status == 200 ){
                        foreach ( $responseData->data as $data ){

                            $marketId = $data->match_market_id;

                            $marketIdNew = $marketId.'-'.$data->_id.'.FY';

                            $check = MarketType::findOne(['market_id'=> $marketIdNew , 'event_id' => $eventId ]);

                            if( $check == null ){

                                $model = New MarketType();

                                $model->event_type_id = 4;
                                $model->market_id = $marketIdNew;
                                $model->event_id = $eventId;
                                $model->market_name = $data->headname;
                                $model->market_type = 'INNINGS_RUNS';
                                $model->suspended = 'Y';
                                $model->ball_running = 'N';
                                $model->status = 2;
                                $model->created_at = time();
                                $model->updated_at = time();

                                $model->save();

                            }else{

                                if( $check->status == 1 ){
                                    $check->status = 1;
                                }else{ $check->status = 2; }

                                $check->market_name = $data->headname;
                                $check->save();

                            }

                            //array_push($marketArr , $marketIdNew );

                        }
                    }

//                    $allData =  MarketType::find()->select(['id'])->where([ 'NOT IN' , 'market_id' , $marketArr ])->andWhere(['event_id' => $eventId])->asArray()->all();
//
//                    if( $allData != null ){
//                        MarketType::updateAll(['status'=>3], [ 'IN' , 'id' , $allData ]);
//                    }

                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Updated successfully!"
                        ]
                    ];

                }

            }
        }
        return $response;
    }

// Refresh Market
    public function actionRefreshMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $eventId = $r_data[ 'id' ];
                $event = EventsPlayList::findOne(['event_id'=>$eventId]);

                if( $event != null ){
                    //$marketId = $event->market_id;
                    //CODE for live call api
                    //$url = 'http://52.208.223.36/api/dream/get_session/'.$marketId;
                    //$url = 'http://52.50.107.50/get_fancy.php?matchId='.$eventId;
					$url = 'https://www.9wickets.com/apiFancyBet/fancybet/queryMarketDatas?cert=fZzuFTbgoKU5GC5l&eventId='.$eventId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);

                    $marketArr = $allData = [];

                    //echo '<pre>';print_r($responseData);die;
                    if( isset( $responseData->status ) && isset( $responseData->marketList ) && $responseData->status == 1 ){
                        foreach ( $responseData->marketList as $data ){

                            $marketId = $data->marketId;

                            $marketIdNew = '1-'.$marketId.'.FY';

                            $check = MarketType::findOne(['market_id'=> $marketIdNew , 'event_id' => $eventId ]);

							/*
							if(isset($data->statusName) && ( $data->statusName == 'OFFLINE' || $data->statusName == 'CLOSE' ) ){
								
								$check->status = 2;
                                $check->market_name = $data->marketName;
                                $check->save();
								
							}
							*/

                            if( $check == null ){

                                $model = New MarketType();

                                $model->event_type_id = 4;
                                $model->market_id = $marketIdNew;
                                $model->event_id = $eventId;
                                $model->market_name = $data->marketName;
                                $model->market_type = 'INNINGS_RUNS';
                                $model->suspended = 'Y';
                                $model->ball_running = 'N';
                                $model->status = 2;
                                $model->created_at = time();
                                $model->updated_at = time();

                                $model->save();

                            }else{

                                if( $check->status == 1 ){
                                    $check->status = 1;
                                }else{ $check->status = 2; }

                                $check->market_name = $data->marketName;
                                $check->save();

                            }

                        }
                    }

                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Updated successfully!"
                        ]
                    ];

                }

            }
        }
        return $response;
    }

    // Refresh Market
    public function actionRefreshDreamMarket()
    {
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );

        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            if( isset( $r_data[ 'id' ] ) ){
                $eventId = $r_data[ 'id' ];
                $event = EventsPlayList::findOne(['event_id'=>$eventId]);

                if( $event != null ){
                    $url = 'http://54.171.86.120/php/api/dream/add-fancy?id='.$eventId;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseData = curl_exec($ch);
                    curl_close($ch);
                    $responseData = json_decode($responseData);

                    $marketArr = $allData = [];

                    //echo '<pre>';print_r($responseData);die;
                    if( isset( $responseData->data ) && $responseData->status == 200 ){
                        foreach ( $responseData->data as $data ){

                            $marketId = $data->market_id;
                            $check = ManualSession::findOne(['market_id'=> $marketId , 'event_id' => $eventId ]);

                            if( $check == null ){

                                $model = New ManualSession();

                                $model->market_id = $marketId;
                                $model->event_id = $eventId;
                                $model->title = $data->title;
                                $model->status = 2;
                                $model->created_at = time();
                                $model->updated_at = time();

                                $model->save();

                            }else{

                                if( $check->status == 1 ){
                                    $check->status = 1;
                                }else{ $check->status = 2; }

                                $check->title = $data->title;
                                $check->save();

                            }

                        }
                    }

                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Updated successfully!"
                        ]
                    ];

                }

            }
        }
        return $response;
    }

    //action Statusmarket
    public function actionStatusmarket(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) && isset( $r_data[ 'type' ]) ){
                $event = MarketType::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    $type = $r_data[ 'type' ];
                    if( $type == 'status' ){
                        if( $event->status == 1 ){
                            $event->status = 2;
                        }else{
                            $event->status = 1;
                        }
                        $arr = [ 'status' ];
                        $sts = $event->status == 1 ? 'Active' : 'Inactive';
                    }else{
                        
                        $arr = [ $type ];
                        if( $type == 'suspended' ){
                            if( $event->suspended == 'N' ){ $event->suspended = 'Y';}else{ $event->suspended = 'N';}
                            $sts = $event->suspended == 'N' ? 'Suspended: No' : 'Suspended: Yes';
                        }elseif( $type == 'ball_running' ){
                            if( $event->ball_running == 'N' ){ $event->ball_running = 'Y';}else{ $event->ball_running = 'N';}
                            $sts = $event->ball_running == 'N' ? 'Ball Running: No' : 'Ball Running: Yes';
                        }else{
                            if( $event->match_odd_status == 1 ){ $event->match_odd_status = 2;}else{ $event->match_odd_status = 1;}
                            $sts = $event->match_odd_status == 1 ? 'Match Odd: Active' : 'Match Odd: InActive';
                        }
                    }
                    
                    if( $event->save( $arr ) ){
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "market $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "market status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    /*
     * Commentary Actions
     */
    
    //action Commentary List
    public function actionCommentaryList(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $id = \Yii::$app->request->get( 'id' );
        $commentry = [
            '2' => 'TENNIS_COMMENTARY',
            '4' => 'CRICKET_COMMENTARY',
            '7' => 'HORSE_RACING_COMMENTARY',
            '1' => 'FOOTBALL_COMMENTARY',
        ];
        
        if( $id != null ){
            
            $model = Setting::find()->select( [ 'value'] )->where( [ 'key' => $commentry['4'] ] )->asArray()->one();
            if( $model != null ){
                
                $commentryList = explode(',', $model['value']);
                
                if( $commentryList != null ){
                    $response = [ "status" => 1 , "data" => $commentryList ];
                }else{
                    $response = [ "status" => 1 , "data" => [] ];
                }
                
                
            }
        }
        
        return $response;
    }
    
    //action Commentary
    public function actionCommentary(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        
        $id = \Yii::$app->request->get( 'id' );
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( $id != null && json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );

            //echo '<pre>';print_r($r_data);die;

            $commentary = GlobalCommentary::findOne(['sport_id'=>4 , 'event_id'=>$id]);

            if( $commentary != null ){

                if( $r_data[ 'title' ] == "Ball Running" || $r_data[ 'title' ] == "Suspended" ){

                    $bookMaker = ManualSessionMatchOdd::find()->select(['market_id'])->where(['event_id'=>$id])->asArray()->one();
                    if( $bookMaker != null ){
                        $marketId = $bookMaker['market_id'];
                        if( $r_data[ 'title' ] == "Ball Running" ){
                            $this->redisUpdateBookMaker($marketId,'ball_running',null);
                            ManualSessionMatchOddData::updateAll(['ball_running'=>'Y','suspended'=>'N'],['market_id'=>$marketId]);
                        }
                        if( $r_data[ 'title' ] == "Suspended" ){
                            $this->redisUpdateBookMaker($marketId,'suspended',null);
                            ManualSessionMatchOddData::updateAll(['ball_running'=>'N','suspended'=>'Y'],['market_id'=>$marketId]);
                        }
                    }

                    $manualSession = ManualSession::find()->select(['market_id'])
                        ->where(['event_id'=>$id , 'status' => 1 , 'game_over' => 'NO'])->asArray()->all();
                    if( $manualSession != null ){
                        if( $r_data[ 'title' ] == "Ball Running" ){
                            foreach ( $manualSession as $marketData ){
                                $this->redisUpdateManualFancy($marketData['market_id'],'ball_running',null);
                            }

                            ManualSession::updateAll(['ball_running'=>'Y','suspended'=>'N'],['event_id'=>$id]);

                        }
                        if( $r_data[ 'title' ] == "Suspended" ){
                            foreach ( $manualSession as $marketData ){
                                $this->redisUpdateManualFancy($marketData['market_id'],'suspended',null);
                            }
                            ManualSession::updateAll(['ball_running'=>'N','suspended'=>'Y'],['event_id'=>$id]);

                        }

                    }

                    $fancy = MarketType::find()->select(['market_id'])
                        ->where(['event_id'=>$id , 'status' => 1 , 'game_over' => 'NO'])->asArray()->all();
                    if( $fancy != null ){
                        if( $r_data[ 'title' ] == "Ball Running" ){
                            MarketType::updateAll(['ball_running'=>'Y','suspended'=>'N'],['event_id'=>$id]);
                        }
                        if( $r_data[ 'title' ] == "Suspended" ){
                            MarketType::updateAll(['ball_running'=>'N','suspended'=>'Y'],['event_id'=>$id]);
                        }
                    }


                }else{

                    $fancy = MarketType::find()->select(['market_id'])->where(['event_id'=>$id])->asArray()->all();
                    if( $fancy != null ){
                        MarketType::updateAll(['ball_running'=>'N','suspended'=>'N'],['event_id'=>$id]);
                    }

//                    $bookMaker = ManualSessionMatchOdd::find()->select(['market_id'])->where(['event_id'=>$id])->asArray()->one();
//                    if( $bookMaker != null ){
//                        $marketId = $bookMaker['market_id'];
//                        ManualSessionMatchOddData::updateAll(['ball_running'=>'N','suspended'=>'N'],['market_id'=>$marketId]);
//                    }
//
//                    $manualSession = ManualSession::find()->select(['market_id'])->where(['event_id'=>$id])->asArray()->all();
//                    if( $manualSession != null ){
//                        ManualSession::updateAll(['ball_running'=>'N','suspended'=>'N'],['event_id'=>$id]);
//                    }


                }



                $commentary->title = $r_data[ 'title' ];
                $commentary->updated_at = time();
                
                $attr = [ 'title','updated_at'];
                
                if( $commentary->save( $attr ) ){
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Commentary updated successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Commentary not updated!"
                    ];
                }
            }else{
                
                $commentary = new GlobalCommentary();
                
                $commentary->title = $r_data[ 'title' ];
                $commentary->sport_id = 4;
                $commentary->event_id = $id;
                $commentary->status = 1;
                $commentary->created_at = $commentary->updated_at = time();
                
                if( $commentary->save() ){
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Commentary updated successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Commentary not updated!"
                    ];
                }
                
            }
        }
        
        return $response;
    }
    
    
    //action Manual Session Status Balltoball
    public function actionManualSessionStatusBalltoball(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = ManualSession::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status_ball_to_ball == 1 ){
                        $event->status_ball_to_ball = 2;
                    }else{
                        $event->status_ball_to_ball = 1;
                    }
                    
                    if( $event->save( [ 'status_ball_to_ball' ] ) ){
                        
                        $sts = $event->status_ball_to_ball == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "Manual Session Ball to Ball Status $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Manual Session Ball to Ball Status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //action Manual Session Balltoball
    public function actionManualSessionBalltoball()
    {
        $pagination = []; $filters = [];
        $data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        $id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $filter_args = ArrayHelper::toArray( $data );
            if( isset( $filter_args[ 'filter' ] ) ){
                $filters = $filter_args[ 'filter' ]; unset( $filter_args[ 'filter' ] );
            }
            $pagination = $filter_args;
        }
        
        $query = BallToBallSession::find()
        ->select( [ 'id' ,'manual_session_id','event_id','over' , 'ball' , 'no_yes_val_1' , 'no_yes_val_2', 'rate_1','rate_2','created_at', 'updated_at' , 'status' ] )
        //->from( Events::tableName() . ' e' )
        ->andWhere( [ 'status' => [1,2] , 'manual_session_id' => $id ] );
        
        if( $filters != null ){
            if( isset( $filters[ "status" ] ) && $filters[ "status" ] != '' ){
                $query->andFilterWhere( [ "status" => $filters[ "status" ] ] );
            }
        }
        
        $countQuery = clone $query; $count =  $countQuery->count();
        
        if( $pagination != null ){
            $offset = ( $pagination[ 'page' ] - 1) * $pagination[ 'pageSize' ];
            $limit  = $pagination[ 'pageSize' ];
            
            $query->offset( $offset )->limit( $limit );
        }
        
        $models = $query->orderBy( [ "id" => SORT_ASC ] )->asArray()->all();
        
        return [ "status" => 1 , "data" => [ "items" => $models , "count" => $count ] ];
        
    }
    
    //action Manual Session Balltoball Status
    public function actionManualSessionBalltoballStatus(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            if( isset( $r_data[ 'id' ] ) ){
                $event = BallToBallSession::findOne( $r_data[ 'id' ] );
                if( $event != null ){
                    
                    if( $event->status == 1 ){
                        $event->status = 2;
                    }else{
                        $event->status = 1;
                    }
                    
                    if( $event->save( [ 'status' ] ) ){
                        
                        $sts = $event->status == 1 ? 'active' : 'inactive';
                        
                        $response = [
                            'status' => 1,
                            "success" => [
                                "message" => "ManualSession $sts successfully!"
                            ]
                        ];
                    }else{
                        $response[ "error" ] = [
                            "message" => "Event status not changed!" ,
                            "data" => $event->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    //create BallToBall Session
    public function createBallToBallSession($data){
        
        if( $data != null ){
            $ball2ballArr = [];
            $no_yes_val_1 = $data->no_yes_val_1;
            $no_yes_val_2 = $data->no_yes_val_2;
            $rate_1 = $data->rate_1;
            $rate_2 = $data->rate_2;
            $eventId = $data->event_id;
            $msId = $data->id;
            $time = time();
            $startOver = $data->start_over;
            $endOver = $data->end_over;
            for ( $startOver; $startOver <= $endOver; $startOver++ ){
                
                for($i = 1; $i <= 6;$i++ ){
                    $ball2ballArr[] = [
                        'event_id' => $eventId,
                        'manual_session_id' => $msId,
                        'over' => $startOver,
                        'ball' => ($startOver-1).'.'.$i,
                        'no_yes_val_1' => $no_yes_val_1,
                        'no_yes_val_2' => $no_yes_val_2,
                        'rate_1' => $rate_1,
                        'rate_2' => $rate_2,
                        'created_at' => $time,
                        'updated_at' => $time,
                        'status' => 1
                    ];
                }
            }
            
            $command = \Yii::$app->db->createCommand();
            $attrArr = ['event_id' , 'manual_session_id' , 'over' , 'ball' , 'no_yes_val_1' , 'no_yes_val_2' , 'rate_1' ,'rate_2', 'created_at' , 'updated_at' , 'status'   ];
            $qry = $command->batchInsert('manual_session_ball_to_ball', $attrArr, $ball2ballArr);
            if( $qry->execute() ){
                return true;
            }else{
                return false;
            }
            
        }else{
            return false;
        }
        
    }
    
    //create Manual Session Lottery Numbers
    public function createManualSessionLotteryNumbers($data){
        
        if( $data != null ){
            for($i = 0; $i <= 9;$i++ ){
                $lotteryNumbersArr[] = [
                    'manual_session_lottery_id' => $data->id,
                    'number' => $i,
                    'rate' => '10',
                    'updated_at' => time(),
                ];
            }
            
            $command = \Yii::$app->db->createCommand();
            $attrArr = ['manual_session_lottery_id' , 'number' , 'rate' , 'updated_at'];
            $qry = $command->batchInsert('manual_session_lottery_numbers', $attrArr, $lotteryNumbersArr);
            if( $qry->execute() ){
                return true;
            }else{
                return false;
            }
            
        }else{
            return false;
        }
        
    }
    
    //action Update Manual Session Balltoball
    public function actionUpdateManualSessionBalltoball(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['BallToBallSession'] = $r_data;
            $model = BallToBallSession::findOne($r_data['id']);
            
            if ($model->load($data)) {
                
                $v1 = explode('/', $r_data['no_yes_val_1']);
                $v3 = explode('/', $r_data['rate_1']);
                $v2 = $v4 = [0,0];
                if( $r_data['no_yes_val_2'] != null && $r_data['rate_2'] ){
                    $v2 = explode('/', $r_data['no_yes_val_2']);
                    $v4 = explode('/', $r_data['rate_2']);
                }
                
                //echo '<pre>';print_r($r_data);die;
                if( !is_numeric($v1[0]) || !is_numeric($v1[1]) || !is_numeric($v2[0])
                    || !is_numeric($v2[1]) || !is_numeric($v3[0]) || !is_numeric($v3[1])
                    || !is_numeric($v4[0]) || !is_numeric($v4[1]) ){
                        
                        return $response = [
                            'status' => 0 ,
                            "error" => [
                                "message" => "Somthing wrong! Invalid Value Enter !!"
                            ]
                        ];
                }
                
                $model->no_yes_val_1 =  $r_data['no_yes_val_1'];
                //$model->no_yes_val_2 = $r_data['no_yes_val_2'];
                $model->rate_1 = $r_data['rate_1'];
                //$model->rate_2 = $r_data['rate_2'];
                $model->updated_at = time();
                
                if( $r_data['no_yes_val_2'] != null && $r_data['rate_2'] ){
                    $model->no_yes_val_2 = $r_data['no_yes_val_2'];
                    $model->rate_2 = $r_data['rate_2'];
                }else{
                    $model->no_yes_val_2 = 0;
                    $model->rate_2 = 0;
                }
                
                //echo '<pre>';print_r($r_data);die;
                if( $model->save( ['no_yes_val_1','no_yes_val_2','rate','updated_at'] ) ){
                    
                    $d = [ 'manual_session_ball_to_ball_id' => $model->id,
                        'no_yes_val_1' => $model->no_yes_val_1,
                        'no_yes_val_2' => $model->no_yes_val_2,
                        'rate_1' => $model->rate_1,
                        'rate_2' => $model->rate_2,
                        'updated_at' => time()
                    ];
                    
                    \Yii::$app->db->createCommand()->insert('manual_session_ball_to_ball_data', $d )->execute();
                    
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Session updated successfully!"
                        ]
                    ];
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Somthing wrong!",
                        "data" => $model->errors
                    ];
                }
                
            }
            
            
        }
        
        return $response;
    }
    
    //action Update Manual Session Lottery Number
    public function actionUpdateManualSessionLotteryNumber(){
        
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        //$id = \Yii::$app->request->get( 'id' );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            
            //echo '<pre>';print_r($r_data);exit;
            
            $data['ManualSessionLotteryNumbers'] = $r_data;
            $model = ManualSessionLotteryNumbers::findOne($r_data['id']);
            
            if ($model->load($data)) {
                
                $model->rate = $r_data['rate'];
                $model->updated_at = time();
                
                if( $model->save( ['rate','updated_at'] ) ){
                    
                    $response = [
                        'status' => 1 ,
                        "success" => [
                            "message" => "Lottery numbers updated successfully!"
                        ]
                    ];
                    
                }else{
                    $response[ "error" ] = [
                        "message" => "Somthing wrong!",
                        "data" => $model->errors
                    ];
                }
                
            }
            
            
        }
        
        return $response;
    }

    //Redis Update Manual Fancy
    public function redisUpdateManualFancy($mId,$status,$data){

        $key = $this->FANCY_KEY.$mId;
        $sessionData = [];
        $cache = Yii::$app->cache;

        if( $cache->exists($key) ) {
            $sessionData = $cache->get($key);
            $sessionData = json_decode($sessionData,true);

            if( $status == 'suspended' && $data == null ){
                if( $sessionData != null ){
                    $sessionData['suspended'] = 'Y';
                    $sessionData['ball_running'] = 'N';
                    $sessionData['no'] = 0;
                    $sessionData['yes'] = 0;
                    $sessionData['no_rate'] = 0;
                    $sessionData['yes_rate'] = 0;
                    $sessionData['time'] = round(microtime(true) * 1000);
                    $cache->set( $key , json_encode($sessionData) );
                }
            }else if( $status == 'ball_running' && $data == null ){
                if( $sessionData != null ){
                    $sessionData['ball_running'] = 'Y';
                    $sessionData['no'] = 0;
                    $sessionData['yes'] = 0;
                    $sessionData['no_rate'] = 0;
                    $sessionData['yes_rate'] = 0;
                    $sessionData['time'] = round(microtime(true) * 1000);
                    $cache->set( $key , json_encode($sessionData) );
                }
            }else if( $status == 'open' && $data != null ){

                if( $sessionData != null ){

                    $sessionData['no'] = $data['no_val'];
                    $sessionData['yes'] = $data['yes_val'];
                    $sessionData['no_rate'] = $data['no_rate'];
                    $sessionData['yes_rate'] = $data['yes_rate'];
                    $sessionData['suspended'] = 'N';
                    $sessionData['ball_running'] = 'N';
                    $sessionData['time'] = round(microtime(true) * 1000);

                    $cache->set( $key , json_encode($sessionData) );
                }

            }else if( $status == 'title' && $data != null ){

                if( $sessionData != null ){
                    $sessionData['title'] = $data;
                    $cache->set( $key , json_encode($sessionData) );
                }

            }else{
                $cache->set( $key , json_encode($sessionData) );
            }

            //echo '<pre>';print_r($sessionData);die;

        }else{

            $model = (new \yii\db\Query())
                ->select(['event_id','market_id','title','game_over','win_result','suspended','suspended','ball_running','no','no_rate','yes','yes_rate','status'])
                ->from('manual_session')
                ->where(['market_id' => $mId])->one();

            if( $model != null ) {

                $marketId = $model['market_id'];
                $eventId = $model['event_id'];

                $key = $this->FANCY_KEY . $marketId;

                $cache = Yii::$app->cache;
                if (!$cache->exists($key)) {

                    $sessionData = [
                        'event_id' => $eventId,
                        'market_id' => $marketId,
                        'title' => $model['title'],
                        'game_over' => $model['game_over'],
                        'win_result' => $model['win_result'],
                        'suspended' => $model['suspended'],
                        'ball_running' => $model['ball_running'],
                        'no' => $model['no'],
                        'no_rate' => $model['no_rate'],
                        'yes' => $model['yes'],
                        'yes_rate' => $model['yes_rate'],
                        'status' => '1',
                        'isBook' => 0,
                        'maxLoss' => 0,
                        'sessionSuspendedTime' => $this->getSessionSuspendedTime(),
                        'time' => round(microtime(true) * 1000),
                    ];

                    if ($sessionData != null) {
                        $cache->set($key, json_encode($sessionData));
                    }
                }
            }

        }
        return $sessionData;

    }

    //Redis Update Manual Fancy
    public function redisUpdateBookMaker($mId,$status,$data){

        //$key = 'BookMaker-'.$mId;
        $key = $this->BOOKMAKER_KEY.$mId;
        $cache = Yii::$app->cache;

        if( $cache->exists($key) ) {
            $bookMakerData = $cache->get($key);
            $bookMakerData = json_decode($bookMakerData,true);

            //echo '<pre>';print_r($bookMakerData);die;

            if( $status == 'suspended' && $data == null ){
                if( $bookMakerData != null ){
                    $bookMakerData['suspended'] = 'Y';
                    $bookMakerData['ball_running'] = 'N';
                    $bookMakerData['time'] = round(microtime(true) * 1000);

                    if( isset($bookMakerData['runners']) ){
                        $i = 0;
                        foreach ( $bookMakerData['runners'] as $runners ){
                            $bookMakerData['runners'][$i]['suspended'] = 'Y';
                            $bookMakerData['runners'][$i]['ball_running'] = 'N';
                        $i++;
                        }

                    }

                    $cache->set( $key , json_encode($bookMakerData) );
                }
            }else if( $status == 'ball_running' && $data == null ){
                if( $bookMakerData != null ){
                    $bookMakerData['suspended'] = 'N';
                    $bookMakerData['ball_running'] = 'Y';
                    $bookMakerData['time'] = round(microtime(true) * 1000);

                    if( isset($bookMakerData['runners']) ){
                        $i = 0;
                        foreach ( $bookMakerData['runners'] as $runners ){
                            $bookMakerData['runners'][$i]['suspended'] = 'N';
                            $bookMakerData['runners'][$i]['ball_running'] = 'Y';
                            $i++;
                        }

                    }
                }
            }else{

            }

        }else{



        }
        return;

    }
}
