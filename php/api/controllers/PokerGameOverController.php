<?php
namespace api\controllers;

use api\modules\v1\modules\users\models\AuthToken;
use common\models\EventsPlayList;
use common\models\PlaceBet;
use common\models\TeenPattiResult;
use common\models\User;
use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Poker controller
 */
class PokerGameOverController extends Controller
{

// Cricket: actionSetOdds
    public function actionIndex()
    {

        $gameData = (new \yii\db\Query())
            ->select('*')->from('teen_patti_result')->where(['status' => 1 , 'game_over' => 0 ])
            ->createCommand(Yii::$app->db)->queryAll();

        if( $gameData != null ){

            foreach ( $gameData as $gData ){

                $eventId = $gData['event_id'];
                $marketId = $gData['market_id'];
                $winner = $gData['winner'];
                $roundId = $gData['round_id'];
                $resultData = $gData['description'];
                $sessionType = 'teenpatti'; $marketName = 'Teen Patti';
                $checkBets = [];

                if( $winner != 0 ){

                    if( $eventId == 56767 ){
                        // Teen Patti
                        $sessionType = 'teenpatti'; $marketName = 'Teen Patti';
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( [ 'session_type' => 'teenpatti' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameOverResult( $eventId, $marketId, $winner , 'teenpatti' );
                            $this->transactionResult( $roundId, $eventId, $marketId , 'teenpatti' );
                        }

                    }elseif ( $eventId == 67564 ){
                        // Poker
                        $sessionType = 'poker'; $marketName = 'Poker';
                        $checkBets = PlaceBet::find()->select('id')
                            ->where( [ 'session_type' => 'poker' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameOverResult( $eventId, $marketId, $winner , 'poker' );
                            $this->transactionResult( $roundId,$eventId, $marketId , 'poker' );
                        }

                    }elseif ( $eventId == 87564 ){
                        // Andar Bahar
                        $sessionType = 'andarbahar'; $marketName = 'Andar Bahar';

                        $checkBets = PlaceBet::find()->select('id')
                            ->where( [ 'session_type' => 'andarbahar' , 'event_id' => $eventId , 'market_id' => $marketId , 'bet_status' => 'Pending' , 'status' => 1 ] )->asArray()->one();

                        if( $checkBets != null ){
                            $this->gameOverResult( $eventId, $marketId, $winner , 'andarbahar' );
                            $this->transactionResult( $roundId,$eventId, $marketId , 'andarbahar' );
                        }

                    }

                }else{

                    // Game Abandoned
                    $resultData = 'Abandoned';

                    /* Unmatched Bet canceled  */
                    $betList = (new \yii\db\Query())
                        ->select(['id'])->from('place_bet')
                        ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
                        ->andWhere( [ 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1] )
                        ->createCommand(Yii::$app->db3)->queryAll();

                    if( $betList != null ){
                        $idsArr = [];
                        foreach ( $betList as $ids ){
                            $idsArr[] = $ids['id'];
                        }
                        PlaceBet::updateAll(['bet_status'=>'Canceled'] , ['id'=>$idsArr]);
                    }

                }


                if( \Yii::$app->db->createCommand() ->update('teen_patti_result', ['game_over' => 1 ] ,
                        ['event_id' => $eventId ,'market_id' => $marketId ] )->execute() ){

                    $resultArr = [
                        'sport_id' => 999999,
                        'event_id' => $eventId,
                        'event_name' => 'Teen Patti',
                        'market_id' => $marketId,
                        'result' => $resultData,
                        'market_name' => $marketName .' -> Round #'.$roundId,
                        'session_type' => $sessionType,
                        'updated_at' => time(),
                        'status' => 1,
                    ];

                    // Update Market Result
                    \Yii::$app->db->createCommand()
                        ->insert('market_result', $resultArr )->execute();

                    // Update User Event Expose
                    \Yii::$app->db->createCommand()
                        ->update('user_event_expose', ['status' => 2 ] ,
                            ['event_id' => $eventId,'market_id' => $marketId ]  )->execute();

                    $response = [
                        'status' => 1,
                        "success" => [
                            "message" => "Game Over successfully!"
                        ]
                    ];
                }else{
                    $response[ "error" ] = [
                        "message" => "Something wrong! event not updated!",
                    ];
                }

            }


        }else{
            $response = [
                'status' => 1,
                "success" => [
                    "message" => "Game Over successfully!!!"
                ]
            ];
        }

        echo json_encode($response);

    }

    //Game Over Result TeenPatti
    public function gameOverResult( $eventId , $marketId , $winResult , $sessionType ){

        if( $eventId != null && $marketId != null && $winResult != null ){

            /*User Win calculation for Back */
            $backWinList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId ,'event_id' => $eventId , 'sec_id' => $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $backWinList != null ){
                foreach ( $backWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Lay*/
            $layWinList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
                ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $layWinList != null ){
                foreach ( $layWinList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Win'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Back */
            $backLossList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
                ->andwhere( [ '!=' , 'sec_id' , $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'back' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $backLossList != null ){
                foreach ( $backLossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

            /*User Win calculation for Lay*/
            $layLossList = (new \yii\db\Query())
                ->select(['id'])->from('place_bet')
                ->where( ['market_id' => $marketId , 'event_id' => $eventId , 'sec_id' => $winResult ] )
                ->andWhere( [ 'session_type' => $sessionType,'bet_status' => 'Pending' , 'bet_type' => 'lay' , 'status' => 1 , 'match_unmatch' => 1] )
                ->createCommand(Yii::$app->db3)->queryAll();

            $idsArr = [];
            if( $layLossList != null ){
                foreach ( $layLossList as $ids ){
                    $idsArr[] = $ids['id'];
                }
                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
            }

            /* User Loss calculation */
//            $lossList = (new \yii\db\Query())
//                ->select(['id'])->from('place_bet')
//                ->where( ['market_id' => $marketId , 'event_id' => $eventId ] )
//                ->andWhere( [ 'session_type' => $sessionType, 'bet_status' => 'Pending' , 'bet_type' => ['back' , 'lay'] , 'status' => 1 , 'match_unmatch' => 1] )
//                ->createCommand(Yii::$app->db3)->queryAll();
//
//            $idsArr = [];
//            if( $lossList != null ){
//                foreach ( $lossList as $ids ){
//                    $idsArr[] = $ids['id'];
//                }
//                PlaceBet::updateAll(['bet_status'=>'Loss'] , ['id'=>$idsArr]);
//            }

        }

    }

    //transaction Result MatchOdds
    public function transactionResult( $roundId,$eventId, $marketId, $sessionType ){

        $amount = 0;

        $userList = (new \yii\db\Query())
            ->select(['user_id'])->from('place_bet')
            ->where( ['market_id' => $marketId ,'event_id' => $eventId ] )
            ->andWhere( [ 'session_type' => $sessionType,'bet_status' => ['Win','Loss'] , 'status' => 1 , 'match_unmatch' => 1] )
            ->groupBy(['user_id'])->createCommand(Yii::$app->db)->queryAll();


        if( $userList != null ){

            foreach ( $userList as $user ){

                $uId = $user['user_id'];

                $userWinList = (new \yii\db\Query())
                    ->select('SUM( win ) as winVal')->from('place_bet')
                    ->where(['user_id' => $user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                    ->andWhere([ 'session_type' => $sessionType,'bet_status' => 'Win' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db)->queryOne();

                $userLossList = (new \yii\db\Query())
                    ->select('SUM( loss ) as lossVal')->from('place_bet')
                    ->where( ['user_id' => $user['user_id'],'market_id' => $marketId ,'event_id' => $eventId ] )
                    ->andWhere( [ 'session_type' => $sessionType, 'bet_status' => 'Loss' , 'status' => 1 , 'match_unmatch' => 1] )
                    ->createCommand(Yii::$app->db)->queryOne();


                if( !isset($userWinList['winVal']) ){ $profit = 0; }else{ $profit = $userWinList['winVal']; }
                if( !isset($userLossList['lossVal']) ){ $loss = 0; }else{ $loss = $userLossList['lossVal']; }

                $amount = $profit-$loss;

                if( $amount != 0 ){
                    $this->updateTransactionHistory($uId,$roundId,$eventId,$marketId,$amount,$sessionType);
                }
            }

        }

    }

    // Update Transaction History
    public function updateTransactionHistory($uId,$roundId,$eventId,$marketId,$amount,$sessionType)
    {
        if( $amount > 0 ){
            $type = 'CREDIT';
        }else{
            $amount = (-1)*$amount;
            $type = 'DEBIT';
        }

        //$cUser = User::find()->select()->where(['id'=>$uId])->asArray()->one();
        $cUser = (new \yii\db\Query())
            ->select(['parent_id'])->from('user')
            ->where([ 'id' => $uId ] )->createCommand(Yii::$app->db3)->queryOne();


        $pId = $cUser['parent_id'];
        $resultArr = [
            'sport_id' => 999999,
            'session_type' => $sessionType,
            'round_id' => $roundId,
            'client_id' => $uId,
            'user_id' => $uId,
            'parent_id' => $pId,
            'child_id' => 0,
            'event_id' => $eventId,
            'market_id' => $marketId,
            'transaction_type' => $type,
            'transaction_amount' => $amount,
            'p_transaction_amount' => 0,
            'c_transaction_amount' => 0,
            'current_balance' => $this->getCurrentBalanceClient($uId,$amount,$type),
            'description' => $this->setDescription($sessionType,$marketId),
            'is_commission' => 0,
            'is_cash' => 0,
            'status' => 1,
            'updated_at' => time(),
            'created_at' => time(),
        ];

        \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
        \Yii::$app->db->close();

        if( $type == 'CREDIT' ){

            $query = new Query();
            $where = [ 'pl.client_id' => $uId ];
            $query->select([ 'pl.user_id as user_id','pl.actual_profit_loss as profit_loss' ,'pl.profit_loss as g_profit_loss', 'u.balance as balance' ] )
                ->from('user_profit_loss as pl')
                ->join('LEFT JOIN','user as u','u.id=pl.user_id')
                ->where( $where )->andWhere(['!=','pl.actual_profit_loss',0])
                ->orderBy(['pl.user_id' => SORT_DESC]);
            $command = $query->createCommand();
            $parentUserData = $command->queryAll();

            //echo '<pre>';print_r($parentUserData);die;

            $childId = $uId;

            foreach ( $parentUserData as $user ){

                //$cUser = User::find()->select(['parent_id'])->where(['id'=>$user['user_id']])->asArray()->one();
                $cUser = (new \yii\db\Query())
                    ->select(['parent_id'])->from('user')
                    ->where([ 'id'=>$user['user_id'] ] )->createCommand(Yii::$app->db3)->queryOne();

                $pId = $cUser['parent_id'];

                //$balance = $user['balance']+$amount;
                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'DEBIT');

                $resultArr = [
                    'sport_id' => 999999,
                    'session_type' => $sessionType,
                    'round_id' => $roundId,
                    'client_id' => $uId,
                    'user_id' => $user['user_id'],
                    'child_id' => $childId,
                    'parent_id' => $pId,
                    'event_id' => $eventId,
                    'market_id' => $marketId,
                    'transaction_type' => 'DEBIT',
                    'transaction_amount' => $transactionAmount,
                    'p_transaction_amount' => $pTransactionAmount,
                    'c_transaction_amount' => $cTransactionAmount,
                    'current_balance' => $currentBalance,
                    'description' => $this->setDescription($sessionType,$marketId),
                    'is_commission' => 0,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];

                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();
                \Yii::$app->db->close();

                $childId = $user['user_id'];

            }

        }else{
            //$parentUserData = $this->getParentUserData($uId);
            $query = new Query();
            $where = [ 'pl.client_id' => $uId ];
            $query->select([ 'pl.user_id as user_id','pl.actual_profit_loss as profit_loss' ,'pl.profit_loss as g_profit_loss',  'u.balance as balance' ] )
                ->from('user_profit_loss as pl')
                ->join('LEFT JOIN','user as u','u.id=pl.user_id')
                ->where( $where )->andWhere(['!=','pl.actual_profit_loss',0])
                ->orderBy(['pl.user_id' => SORT_DESC]);
            $command = $query->createCommand();
            $parentUserData = $command->queryAll();

            //echo '<pre>';print_r($parentUserData);die;

            $childId = $uId;

            foreach ( $parentUserData as $user ){

                //$cUser = User::find()->select(['parent_id'])->where(['id'=>$user['user_id']])->asArray()->one();

                $cUser = (new \yii\db\Query())
                    ->select(['parent_id'])->from('user')
                    ->where([ 'id'=>$user['user_id'] ] )->createCommand(Yii::$app->db3)->queryOne();

                $pId = $cUser['parent_id'];

                //$balance = $user['balance']+$amount;
                $transactionAmount = ( $amount*$user['profit_loss'] )/100;
                $pTransactionAmount = ( $amount*(100-$user['g_profit_loss']) )/100;
                $cTransactionAmount = ( $amount*($user['g_profit_loss']-$user['profit_loss']) )/100;
                $currentBalance = $this->getCurrentBalanceParent($user['user_id'],$transactionAmount,'CREDIT');

                $resultArr = [
                    'sport_id' => 999999,
                    'session_type' => $sessionType,
                    'round_id' => $roundId,
                    'client_id' => $uId,
                    'user_id' => $user['user_id'],
                    'child_id' => $childId,
                    'parent_id' => $pId,
                    'event_id' => $eventId,
                    'market_id' => $marketId,
                    'transaction_type' => 'CREDIT',
                    'transaction_amount' => $transactionAmount,
                    'p_transaction_amount' => $pTransactionAmount,
                    'c_transaction_amount' => $cTransactionAmount,
                    'current_balance' => $currentBalance,
                    'description' => $this->setDescription($sessionType,$marketId),
                    'is_commission' => 0,
                    'is_cash' => 0,
                    'status' => 1,
                    'updated_at' => time(),
                    'created_at' => time(),
                ];

                \Yii::$app->db->createCommand()->insert('transaction_history', $resultArr )->execute();

                $childId = $user['user_id'];

            }

        }

    }

    // get Current Balance
    public function getCurrentBalanceClient($uid,$amount,$type)
    {
        $user = User::findOne(['id' => $uid ]);
        $balance = 0;
        if( $user != null ){
            $balance = $user->balance;
            $profit_loss_balance = $user->profit_loss_balance;

            if( $type == 'CREDIT' ){
                $profit_loss_balance = $user->profit_loss_balance+$amount;
            }else{
                $profit_loss_balance = $user->profit_loss_balance-$amount;
            }

            $user->profit_loss_balance = $profit_loss_balance;

            if( $user->save(['profit_loss_balance']) ){
                $balance = $user->balance + $user->profit_loss_balance;
                //$profit_loss_balance = $user->profit_loss_balance;
            }

        }

        return round($balance,2);
    }

    // get Current Balance
    public function getCurrentBalanceParent($uid,$amount,$type)
    {
        $user = User::findOne(['id' => $uid ]);

        $profit_loss_balance = 0;
        if( $user != null ){
            //$balance = $user->balance;
            $profit_loss_balance = $user->profit_loss_balance;
            if( $type == 'CREDIT' ){
                $profit_loss_balance = $user->profit_loss_balance+$amount;
            }else{
                $profit_loss_balance = $user->profit_loss_balance-$amount;
            }

            $user->profit_loss_balance = $profit_loss_balance;

            if( $user->save(['profit_loss_balance']) ){
                //$balance = $user->balance + $user->profit_loss_balance;
                $profit_loss_balance = $user->profit_loss_balance;
            }

        }

        return round($profit_loss_balance,2);
    }

    // Function to get the Description
    public function setDescription($sessionType,$marketId)
    {
        $description = 'Teen Patti';
        $round = $this->getRoundId($marketId);
        if( $sessionType == 'teenpatti' ){
            $description = 'Teen Patti > Round #'.$round;
        }else if( $sessionType == 'poker' ){
            $description = 'Poker > Round #'.$round;
        }else if( $sessionType == 'andarbahar' ){
            $description = 'Andar Bahar > Round #'.$round;
        }

        return $description;
    }

    //actionIndex
    public function getRoundId($marketId)
    {
        $round = 'not found';
        $event = (new \yii\db\Query())
            ->select(['round_id'])
            ->from('teen_patti_result')
            ->where(['market_id' => $marketId ])
            ->one();

        if( $event != null && $event['round_id'] != 0 ){
            $round = $event['round_id'];
        }
        return $round;
    }


}
