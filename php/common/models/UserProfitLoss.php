<?php
namespace common\models;

use api\modules\v2\modules\users\models\PlaceBet;
use Yii;
use yii\db\ActiveRecord;

/**
 * Setting model
 *
 * @property integer $id
 * @property integer $client_id
 * @property integer $user_id
 * @property integer $parent_id
 * @property integer $level
 * @property string $profit_loss
 * @property string $actual_profit_loss
 * @property integer $status
 * @property integer $updated_at
 */
class UserProfitLoss extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user_profit_loss}}';
    }

    //newUpdateUserExpose
    public function newUpdateUserExpose($uid,$marketId,$sessionType)
    {
        $exposeBalance = 0;
        $marketList = PlaceBet::find()->select(['market_id'])
            ->where(['user_id' => $uid, 'bet_status' => 'Pending', 'status' => 1])
            ->andWhere(['!=', 'market_id', $marketId ])
            ->groupBy(['market_id'])->asArray()->all();

        if ($marketList != null) {
            $where = [ 'user_id' => $uid , 'status' => 1 , 'market_id' => $marketList ];
            $userExpose = (new \yii\db\Query())
                ->select(['sum(expose) as exposeVal'])->from('user_event_expose')->where($where)->one();
            if( $userExpose != null ){
                $balExpose = (-1)*($userExpose['exposeVal']);

                if( $balExpose != null ){
                    $exposeBalance += $balExpose;
                }

            }

        }

        // Match Odds
        if( $sessionType == 'match_odd' ){
            $event = EventsPlayList::findOne(['market_id' => $marketId]);
            if ($event != null) {
                $eventId = $event->event_id;
                $runnersData = EventsRunner::findAll(['market_id' => $marketId, 'event_id' => $eventId]);

                if ($runnersData != null) {
                    $balExpose = $balPlus = [];
                    foreach ($runnersData as $runners) {
                        $profitLoss = self::getProfitLossMatchOddsNewAll($uid,$marketId, $eventId, $runners->selection_id, 'match_odd');

                        if ($profitLoss < 0) {
                            $balExpose[] = $profitLoss;
                        }

                    }
                }

                if ($balExpose != null) {
                    $minExpose = min($balExpose);
                    $exposeBalance += $minExpose;
                    self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                }
            }
        }

        if( isset( $exposeBalance ) && $exposeBalance < 0 ){
            $exposeBalance = (-1)*( $exposeBalance );
        }

        //echo $exposeBalance;die;

        \Yii::$app->db->createCommand()
            ->update('user', ['expose_balance' => $exposeBalance], ['id' => $uid])
            ->execute();

        return true;

    }

    // Function Update User Expose
    public function updateUserExpose( $uid, $eventId, $marketId, $minExpose )
    {
        //$minExpose = (-1)*($minExpose);
        if( $minExpose != 0 ){ $minExpose = (-1)*($minExpose); }
        $where = [ 'user_id' => $uid , 'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];

        $userExpose = (new \yii\db\Query())
            ->select(['id'])->from('user_event_expose')->where($where)
            ->createCommand(Yii::$app->db)->queryOne();

        if( $userExpose != null ){

            $updateData = [
                'expose' => $minExpose,
                'updated_at' => time(),
            ];

            \Yii::$app->db->createCommand()->update('user_event_expose', $updateData , $where )->execute();

        }else{

            $addData = [
                'user_id' => $uid,
                'event_id' => $eventId,
                'market_id' => $marketId,
                'expose' => $minExpose,
                'profit' => 0,
                'updated_at' => time(),
                'status' => 1,
            ];

            \Yii::$app->db->createCommand()->insert('user_event_expose', $addData )->execute();
        }

    }

    // Function Update User Expose
    public function updateUserProfit( $uid, $eventId, $marketId, $maxProfit )
    {
        $where = [ 'user_id' => $uid , 'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];

        $userProfit = (new \yii\db\Query())
            ->select(['id'])->from('user_event_expose')->where($where)
            ->createCommand(Yii::$app->db)->queryOne();

        if( $userProfit != null ){

            $updateData = [
                'profit' => $maxProfit,
                'updated_at' => time(),
            ];

            \Yii::$app->db->createCommand()->update('user_event_expose', $updateData , $where )->execute();

        }else{

            $addData = [
                'user_id' => $uid,
                'event_id' => $eventId,
                'market_id' => $marketId,
                'expose' => 0,
                'profit' => $maxProfit,
                'updated_at' => time(),
                'status' => 1,
            ];

            \Yii::$app->db->createCommand()->insert('user_event_expose', $addData )->execute();
        }

    }

    public function getBalanceRefresh($uid)
    {
        //$user = User::find()->select(['balance','expose_balance','profit_loss_balance'])->where(['id' => $uid ])->one();

        $user = (new \yii\db\Query())->select(['balance','expose_balance','profit_loss_balance'])->from('user')
            ->where(['id' => $uid , 'status' => 1 ] )->createCommand(Yii::$app->db3)->queryOne();

        $dataExpose = $maxBal['expose'] = $maxBal['plus'] = $balPlus = $balPlus1 = $balExpose = $balExpose2 = $profitLossNew = [];

        if( $user != null ){
            $mywallet = $user['balance'];
            $profit_loss_balance = $user['profit_loss_balance'];
            $user_balance = $user['balance'];
            $expose_balance = $exposeLossVal = $exposeWinVal = $balExposeUnmatch = $tempExposeUnmatch = $tempExpose = $tempPlus = 0;

            //Match Odd Expose

            $marketList = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList as $market ){

                    $marketId = $market['market_id'];

                    //$event = EventsPlayList::findOne(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO']);
                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        //$runnersData = EventsRunner::findAll(['market_id'=>$marketId,'event_id'=>$eventId]);

                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->createCommand(Yii::$app->db3)->queryAll();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = self::getProfitLossMatchOddsNewAll($uid,$marketId,$eventId,$runners['selection_id'],'match_odd');

                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }

                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            //Match Odd 2 Expose

            $marketList2 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'match_odd2' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList2 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList2 as $market ){

                    $marketId = $market['market_id'];

                    $manualMatchOdd = $query->select(['event_id'])
                        ->from('manual_session_match_odd')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->one();

                    if( $manualMatchOdd != null ){
                        $eventId = $manualMatchOdd['event_id'];

                        $runnersData = $query->select(['sec_id'])
                            ->from('manual_session_match_odd_data')
                            ->where(['market_id'=>$marketId])
                            ->all();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = self::getProfitLossMatchOddsNewAll($uid,$marketId,$eventId,$runners['sec_id'],'match_odd2');
                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }
                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }
                    }

                }
            }

            // Fancy Expose
            $marketList3 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList3 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList3 as $market ){

                    $marketId = $market['market_id'];

                    $manualFancy = $query->select(['market_id','event_id'])
                        ->from('manual_session')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $manualFancy != null ) {
                        $eventId = $manualFancy['event_id'];
                        $profitLossData = self::getProfitLossFancyOnZero($uid,$marketId, 'fancy');
                        if ($profitLossData != null) {
                            $minExpose = min($profitLossData);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        if( $profitLossData != null ){
                            $maxProfit = max($profitLossData);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }
                }
            }

            // Fancy 2 Expose

            $marketList4 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'fancy2' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList4 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList4 as $market ){

                    $marketId = $market['market_id'];
                    $fancy2 = $query->select(['market_id','event_id'])
                        ->from('market_type')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $fancy2 != null ) {
                        $eventId = $fancy2['event_id'];
                        $profitLossData = self::getProfitLossFancy2OnZero($uid,$marketId, 'fancy2');

                        if ($profitLossData != null) {
                            $minExpose = min($profitLossData);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $profitLossData != null ){
                            $maxProfit = max($profitLossData);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }
                    }
                }
            }

            // Lottery Expose
            $marketList5 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'lottery' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList5 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList5 as $market ){

                    $marketId = $market['market_id'];

                    $lottery = $query->select(['event_id'])
                        ->from('manual_session_lottery')
                        ->where(['market_id'=>$marketId , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $lottery != null ){

                        $eventId = $lottery['event_id'];
                        $balExpose = $balPlus = [];
                        for($n=0;$n<10;$n++){
                            $profitLoss = $this->getLotteryProfitLoss($uid,$eventId,$marketId,$n);
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            // Jackpot Expose
            $marketList6 = (new \yii\db\Query())->select(['event_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'jackpot' , 'status' => 1 ] )
                ->groupBy(['event_id'])->createCommand(Yii::$app->db)->queryAll();

            //echo '<pre>';print_r($marketList);die;

            if( $marketList6 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList5 as $market ){

                    $eventId = $market['event_id'];

                    $jackpotMarket = $query->select(['market_id'])
                        ->from('cricket_jackpot')
                        ->where(['event_id'=>$eventId , 'game_over' => 'NO' , 'status' => 1 ])
                        ->createCommand(Yii::$app->db3)->queryAll();

                    if( $jackpotMarket != null ){

                        $balExpose = $balPlus = [];
                        foreach ( $jackpotMarket as $jackpot ){
                            $marketId = $jackpot['market_id'];
                            $profitLoss = $this->getJackpotProfitLoss($uid,$eventId,$marketId);
                            if( $profitLoss < 0 ){
                                $balExpose[] = $profitLoss;
                            }else{
                                $balPlus[] = $profitLoss;
                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$eventId.'-JKPT',$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$eventId.'-JKPT',0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = max($maxProfit);
                            self::updateUserExpose( $uid,$eventId,$eventId.'-JKPT',$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$eventId.'-JKPT',0 );
                        }

                    }

                }
            }

            //Teen Patti Expose
            $marketList7 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'teenpatti' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList7 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList7 as $market ){

                    $marketId = $market['market_id'];

                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->createCommand(Yii::$app->db3)->queryAll();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = self::getProfitLossTeenPattiNewAll($uid,$marketId,$eventId,$runners['selection_id'],'teenpatti');

                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }

                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            //Poker Expose
            $marketList8 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'poker' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList8 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList8 as $market ){

                    $marketId = $market['market_id'];

                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->createCommand(Yii::$app->db3)->queryAll();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = self::getProfitLossTeenPattiNewAll($uid,$marketId,$eventId,$runners['selection_id'],'poker');

                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }

                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            //Andar Bahar Expose
            $marketList9 = (new \yii\db\Query())->select(['market_id'])->from('place_bet')
                ->where(['user_id' => $uid , 'bet_status' => 'Pending' , 'session_type' => 'andarbahar' , 'status' => 1 ] )
                ->groupBy(['market_id'])->createCommand(Yii::$app->db)->queryAll();

            if( $marketList9 != null ){
                $query = new \yii\db\Query();
                foreach ( $marketList9 as $market ){

                    $marketId = $market['market_id'];

                    $event = $query->select(['event_id'])
                        ->from('events_play_list')
                        ->where(['market_id'=>$marketId , 'status' => 1 , 'game_over' => 'NO'])
                        ->createCommand(Yii::$app->db3)->queryOne();

                    if( $event != null ){
                        $eventId = $event['event_id'];
                        $runnersData = $query->select(['selection_id'])
                            ->from('events_runners')
                            ->where(['market_id'=>$marketId,'event_id'=>$eventId])
                            ->createCommand(Yii::$app->db3)->queryAll();

                        if( $runnersData != null ){
                            $balExpose = $balPlus = [];
                            foreach ( $runnersData as $runners ){
                                $profitLoss = self::getProfitLossTeenPattiNewAll($uid,$marketId,$eventId,$runners['selection_id'],'andarbahar');

                                if( $profitLoss < 0 ){
                                    $balExpose[] = $profitLoss;
                                }else{
                                    $balPlus[] = $profitLoss;
                                }

                            }
                        }

                        if( $balExpose != null ){
                            $minExpose = min($balExpose);
                            $maxBal['expose'][] = $minExpose;
                            self::updateUserExpose( $uid,$eventId,$marketId,$minExpose );
                        }else{
                            self::updateUserExpose( $uid,$eventId,$marketId,0 );
                        }

                        //echo $marketId.' => '.$minExpose.'</br>';

                        if( $balPlus != null ){
                            $maxProfit = max($balPlus);
                            $maxBal['plus'][] = $maxProfit;
                            self::updateUserProfit( $uid,$eventId,$marketId,$maxProfit );
                        }else{
                            self::updateUserProfit( $uid,$eventId,$marketId,0 );
                        }

                    }

                }
            }

            // All Expose
            if( isset( $maxBal['expose'] ) && $maxBal['expose'] != null && array_sum( $maxBal['expose'] ) < 0 ){

                $expose_balance = (-1)*( array_sum( $maxBal['expose'] ) );

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
                    ->execute();

                return $uid;
                //return true;
            }else{
                $expose_balance = 0;

                \Yii::$app->db->createCommand()
                    ->update('user', ['expose_balance' => $expose_balance], ['id' => $uid])
                    ->execute();
                return $uid;
                //return true;
            }

            //$user_balance = $user_balance-$expose_balance+$profit_loss_balance;

            //return [ "balance" => round($user_balance) , "expose" => round($expose_balance) , "mywallet" => round($mywallet) ];
        }

        //return [ "balance" => 0 , "expose" => 0 , "mywallet" => 0 ];
        //return false;
        return 0;

    }

    public function getProfitLossMatchOddsNewAll($userId,$marketId,$eventId,$selId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;
        $total = 0;

        //$sessionType = ['match_odd','match_odd2'];

        // IF RUNNER WIN
        if( null != $userId && $marketId != null && $eventId != null && $selId != null){

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            //$backWin = PlaceBet::find()->select(['SUM(win) as val'])->where($where)->asArray()->all();

            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            //$layWin = PlaceBet::find()->select(['SUM(win) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin['val']; }

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType ,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

            //$layLoss = PlaceBet::find()->select(['SUM(loss) as val'])->where($where)->asArray()->all();

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            //$backLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->andWhere($andWhere)->asArray()->all();

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss['val']; }

            // IF UNMATCH BET LOSS
            $where = [ 'match_unmatch' => 0,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => ['back','lay'] ];

            //$unmatchLoss = PlaceBet::find()->select(['SUM(loss) as val'])
            //    ->where($where)->asArray()->all();

            $unmatchLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $unmatchLoss == null || !isset($unmatchLoss['val']) || $unmatchLoss['val'] == '' ){
                $unmatchLoss = 0;
            }else{ $unmatchLoss = $unmatchLoss['val']; }

            $totalLoss = $backLoss + $layLoss + $unmatchLoss;

            $total = $totalWin-$totalLoss;

        }

        return $total;

    }


    public function getProfitLossTeenPattiNewAll($userId,$marketId,$eventId,$selId,$sessionType)
    {
        $total = 0;

        //$sessionType = ['teenpatti','poker','andarbahar'];

        if( null != $userId && $marketId != null && $eventId != null && $selId != null){

            // IF RUNNER WIN
            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'back' ,'session_type' => $sessionType ];
            $backWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $backWin == null || !isset($backWin['val']) || $backWin['val'] == '' ){
                $backWin = 0;
            }else{ $backWin = $backWin['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType, 'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'lay' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            $layWin = (new \yii\db\Query())
                ->select(['SUM(win) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            if( $layWin == null || !isset($layWin['val']) || $layWin['val'] == '' ){
                $layWin = 0;
            }else{ $layWin = $layWin['val']; }

            $totalWin = $backWin + $layWin;

            // IF RUNNER LOSS
            $where = [ 'match_unmatch' => 1,'market_id' => $marketId ,'session_type' => $sessionType ,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'sec_id' => $selId , 'event_id' => $eventId , 'bet_type' => 'lay' ];

            $layLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryOne();

            if( $layLoss == null || !isset($layLoss['val']) || $layLoss['val'] == '' ){
                $layLoss = 0;
            }else{ $layLoss = $layLoss['val']; }

            $where = [ 'match_unmatch' => 1,'market_id' => $marketId , 'session_type' => $sessionType,'user_id' => $userId, 'status' => 1 , 'bet_status' => 'Pending', 'event_id' => $eventId , 'bet_type' => 'back' ];
            $andWhere = [ '!=' , 'sec_id' , $selId ];

            $backLoss = (new \yii\db\Query())
                ->select(['SUM(loss) as val'])
                ->from('place_bet')->where($where)->andWhere($andWhere)->createCommand(Yii::$app->db3)->queryOne();

            if( $backLoss == null || !isset($backLoss['val']) || $backLoss['val'] == '' ){
                $backLoss = 0;
            }else{ $backLoss = $backLoss['val']; }

            $totalLoss = $backLoss + $layLoss;

            $total = $totalWin-$totalLoss;

        }

        return $total;

    }


    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancyOnZero($userId,$marketId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = [ 'bet_status' => 'Pending','session_type' => 'fancy','user_id' => $userId,'market_id' => $marketId ,'status' => 1];

        //$betList = PlaceBet::find()
        //    ->select(['bet_type','price','win','loss'])
        //    ->where( $where )->asArray()->all();

        $betList = (new \yii\db\Query())
            ->select(['bet_type','price','win','loss'])
            ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryAll();

        if( $betList != null ){
            $result = [];

//        $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->one();
//
//        $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->one();
//
//        if( isset( $betMinRun->price ) ){
//            $minRun = $betMinRun->price-1;
//        }
//
//        if( isset( $betMaxRun->price ) ){
//            $maxRun = $betMaxRun->price+1;
//        }

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

                $where = [ 'bet_status' => 'Pending','bet_type' => 'no','session_type' => 'fancy','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                //$betList1 = PlaceBet::find()
                //    ->select('SUM( win ) as winVal')
                //    ->where( $where )->andWhere(['>','price',(int)$i])
                //    ->asArray()->all();

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'yes','session_type' => 'fancy','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList2 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'yes','session_type' => 'fancy','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList3 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['>','price',(int)$i])
//                    ->asArray()->all();

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['>','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'no','session_type' => 'fancy','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList4 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( !isset($betList1['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1['winVal']; }
                if( !isset($betList2['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2['winVal']; }
                if( !isset($betList3['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3['lossVal']; }
                if( !isset($betList4['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4['lossVal']; }

                $profit = ( $winVal1 + $winVal2 );
                $loss = ( $lossVal1 + $lossVal2 );

                $result[$i] = $profit-$loss;
            }

        }

        return $result;
    }

    // getProfitLossFancyOnZeroNewAll
    public function getProfitLossFancy2OnZero($userId,$marketId,$sessionType)
    {
        //$userId = \Yii::$app->user->id;

        $where = [ 'bet_status' => 'Pending','session_type' => 'fancy2','user_id' => $userId,'market_id' => $marketId ,'status' => 1];

        //$betList = PlaceBet::find()
        //    ->select(['bet_type','price','win','loss'])
        //    ->where( $where )->asArray()->all();

        $betList = (new \yii\db\Query())
            ->select(['bet_type','price','win','loss'])
            ->from('place_bet')->where($where)->createCommand(Yii::$app->db3)->queryAll();

        if( $betList != null ){
            $result = [];

//        $betMinRun = PlaceBet::find()
//            ->select(['MIN( price ) as price'])
//            ->where( $where )->one();
//
//        $betMaxRun = PlaceBet::find()
//            ->select(['MAX( price ) as price'])
//            ->where( $where )->one();
//
//        if( isset( $betMinRun->price ) ){
//            $minRun = $betMinRun->price-1;
//        }
//
//        if( isset( $betMaxRun->price ) ){
//            $maxRun = $betMaxRun->price+1;
//        }

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

                $where = [ 'bet_status' => 'Pending','bet_type' => 'no','session_type' => 'fancy2','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
                //$betList1 = PlaceBet::find()
                //    ->select('SUM( win ) as winVal')
                //    ->where( $where )->andWhere(['>','price',(int)$i])
                //    ->asArray()->all();

                $betList1 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['>', 'price', (int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'yes','session_type' => 'fancy2','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList2 = PlaceBet::find()
//                    ->select('SUM( win ) as winVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList2 = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'yes','session_type' => 'fancy2','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList3 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['>','price',(int)$i])
//                    ->asArray()->all();

                $betList3 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['>','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                $where = [ 'bet_status' => 'Pending','bet_type' => 'no','session_type' => 'fancy2','user_id' => $userId,'market_id' => $marketId , 'status' => 1 ];
//                $betList4 = PlaceBet::find()
//                    ->select('SUM( loss ) as lossVal')
//                    ->where( $where )->andWhere(['<=','price',(int)$i])
//                    ->asArray()->all();

                $betList4 = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')
                    ->from('place_bet')->where($where)->andWhere(['<=','price',(int)$i])
                    ->createCommand(Yii::$app->db3)->queryOne();

                if( !isset($betList1['winVal']) ){ $winVal1 = 0; }else{ $winVal1 = $betList1['winVal']; }
                if( !isset($betList2['winVal']) ){ $winVal2 = 0; }else{ $winVal2 = $betList2['winVal']; }
                if( !isset($betList3['lossVal']) ){ $lossVal1 = 0; }else{ $lossVal1 = $betList3['lossVal']; }
                if( !isset($betList4['lossVal']) ){ $lossVal2 = 0; }else{ $lossVal2 = $betList4['lossVal']; }

                $profit = ( $winVal1 + $winVal2 );
                $loss = ( $lossVal1 + $lossVal2 );

                $result[$i] = $profit-$loss;
            }

        }

        return $result;
    }

    // Cricket: get Lottery Profit Loss On Bet
    public function getLotteryProfitLoss($userId,$eventId,$marketId ,$selectionId)
    {
        $total = 0;
        $where = [ 'bet_status' => 'Pending', 'session_type' => 'lottery', 'user_id'=>$userId,'event_id' => $eventId ,'market_id' => $marketId , 'status' => 1 ];
        // IF RUNNER WIN
        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['sec_id' => $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        // IF RUNNER LOSS
        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=','sec_id' , $selectionId])
            ->createCommand(Yii::$app->db3)->queryOne();

        if( $betWinList == null ){
            $totalWin = 0;
        }else{ $totalWin = $betWinList['totalWin']; }

        if( $betLossList == null ){
            $totalLoss = 0;
        }else{ $totalLoss = (-1)*$betLossList['totalLoss']; }

        $total = $totalWin+$totalLoss;

        return $total;

    }

    // getJackpotProfitLoss
    public function getJackpotProfitLoss($userId,$eventId, $marketId)
    {
        $total = 0;
        //$userId = \Yii::$app->user->id;
        $where = ['bet_status' => 'Pending', 'session_type' => 'jackpot', 'user_id' => $userId, 'event_id' => $eventId , 'status' => 1];
        // IF RUNNER WIN

        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['market_id' => $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betWinList == null) {
            $totalWin = 0;
        } else {
            $totalWin = $betWinList['totalWin'];
        }

        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=', 'market_id', $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betLossList == null) {
            $totalLoss = 0;
        } else {
            $totalLoss = (-1) * $betLossList['totalLoss'];
        }

        $total = $totalWin + $totalLoss;

        return $total;
    }

    // getJackpotProfitLoss
    public function getJackpotProfitLossOLD($userId,$eventId, $marketId)
    {
        $total = 0;
        $where = ['bet_status' => 'Pending', 'session_type' => 'jackpot', 'user_id' => $userId, 'event_id' => $eventId , 'status' => 1];
        // IF RUNNER WIN
        $betWinList = (new \yii\db\Query())
            ->select('SUM(win) as totalWin')
            ->from('place_bet')->where($where)->andWhere(['market_id' => $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betWinList == null) {
            $totalWin = 0;
        } else {
            $totalWin = $betWinList['totalWin'];
        }

        // IF RUNNER LOSS
        $betLossList = (new \yii\db\Query())
            ->select('SUM(loss) as totalLoss')
            ->from('place_bet')->where($where)->andWhere(['!=', 'market_id', $marketId])
            ->createCommand(Yii::$app->db2)->queryOne();

        if ($betLossList == null) {
            $totalLoss = 0;
        } else {
            $totalLoss = (-1) * $betLossList['totalLoss'];
        }

        $total = $totalWin + $totalLoss;

        return $total;
    }


}


