<?php

namespace api\modules\v1\modules\chips\controllers;

use yii\db\Query;
use yii\helpers\ArrayHelper;
use common\models\User;
use common\models\TransactionHistory;
use common\models\TempTransactionHistory;

class SummaryController extends \common\controllers\aController
{
    private $parentArr = [];
    private $parent_total = 0;
    private $parent_comm = 0;
    private $child_total = 0;

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors ['access'] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => ['admin', 'agent1', 'agent2'],
                ],
            ],
            "denyCallback" => [\common\controllers\cController::className(), 'accessControlCallBack']
        ];

        return $behaviors;
    }

    //get Profit Loss
    public function getProfitLossBalance($cid, $uid)
    {

        //$pId = \Yii::$app->user->id;

        //$profit_loss = (100-$profit_loss);

        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if (isset($role['client']) && $role['client'] != null) {

            $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['user_id' => $uid, 'client_id' => $cid, 'transaction_type' => 'CREDIT'])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $profitVal = 0;

            if ($profit[0]['profit'] > 0 && $profit[0]['profit'] != null) {
                $profitVal = $profit[0]['profit'];
            }

            $comm = TempTransactionHistory::find()->select(['SUM(commission) as comm'])
                ->where(['user_id' => $uid, 'client_id' => $cid, 'transaction_type' => 'CREDIT'])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $commVal = 0;

            if ($comm[0]['comm'] > 0 && $comm[0]['comm'] != null) {
                $commVal = $comm[0]['comm'];
            }

            $profitVal = $profitVal + $commVal;

            $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                ->where(['user_id' => $uid, 'client_id' => $cid, 'transaction_type' => 'DEBIT'])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $lossVal = 0;

            if ($loss[0]['loss'] > 0 && $loss[0]['loss'] != null) {
                $lossVal = $loss[0]['loss'];
            }

        } else {

            $pUser = User::find()->select(['parent_id'])->where(['id' => $uid])->one();

            $temp = TempTransactionHistory::find()->select(['client_id'])
                ->where(['user_id' => $uid, 'parent_id' => $pUser->parent_id])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['user_id' => $pUser->parent_id, 'client_id' => $temp['client_id'], 'transaction_type' => 'DEBIT'])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $profitVal = 0;

            if ($profit[0]['profit'] > 0 && $profit[0]['profit'] != null) {
                $profitVal = $profit[0]['profit'];
            }

            $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                ->where(['user_id' => $pUser->parent_id, 'client_id' => $temp['client_id'], 'transaction_type' => 'CREDIT'])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $lossVal = 0;

            if ($loss[0]['loss'] > 0 && $loss[0]['loss'] != null) {
                $lossVal = $loss[0]['loss'];
            }

        }

        $cash = $this->getCashBalance($uid, 'user');
        $total = $profitVal - $lossVal - $cash;//(-1)*( ($profitVal-$lossVal)*$profit_loss )/100;

        return $total;

    }

    //get Profit Loss
    public function getProfitLoss($cid, $uid)
    {

        //$pId = \Yii::$app->user->id;

        //$profit_loss = (100-$profit_loss);

        $role = \Yii::$app->authManager->getRolesByUser($cid);

        $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'client_id' => $cid, 'transaction_type' => 'CREDIT'])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->all();

        $profitVal = 0;

        if ($profit[0]['profit'] > 0 && $profit[0]['profit'] != null) {
            $profitVal = $profit[0]['profit'];
        }

        $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['user_id' => $uid, 'client_id' => $cid, 'transaction_type' => 'DEBIT'])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->all();

        $lossVal = 0;

        if ($loss[0]['loss'] > 0 && $loss[0]['loss'] != null) {
            $lossVal = $loss[0]['loss'];
        }

        $total = $profitVal - $lossVal;//(-1)*( ($profitVal-$lossVal)*$profit_loss )/100;

        return $total;

    }

    //get Cash Balance
    public function getCashBalance($uid, $userType)
    {

        //$pId = \Yii::$app->user->id;

        //$profit_loss = (100-$profit_loss);

        if ($userType == 'user') {
            $where1 = ['user_id' => $uid, 'transaction_type' => 'W/CASH'];
            $where2 = ['user_id' => $uid, 'transaction_type' => 'D/CASH'];
        } else {
            $where1 = ['parent_id' => $uid, 'transaction_type' => 'W/CASH'];
            $where2 = ['parent_id' => $uid, 'transaction_type' => 'D/CASH'];
        }

        $profit = TempTransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where($where1)
            ->asArray()->all();

        $profitVal = 0;

        if ($profit[0]['profit'] > 0 && $profit[0]['profit'] != null) {
            $profitVal = $profit[0]['profit'];
        }

        $loss = TempTransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where($where2)
            ->asArray()->all();

        $lossVal = 0;

        if ($loss[0]['loss'] > 0 && $loss[0]['loss'] != null) {
            $lossVal = $loss[0]['loss'];
        }

        $total = $profitVal - $lossVal;//(-1)*( ($profitVal-$lossVal)*$profit_loss )/100;

        return $total;

    }

    // Get Commission
    public function getCommission($uid, $pid)
    {

        $loss = TempTransactionHistory::find()->select(['SUM(commission) as loss'])
            ->where(['user_id' => $pid, 'client_id' => $uid, 'transaction_type' => 'DEBIT'])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->all();

        $lossVal = 0;

        if ($loss[0]['loss'] > 0 && $loss[0]['loss'] != null) {
            $lossVal = $loss[0]['loss'];
        }
        $total = $lossVal;
        return $total;

    }

    public function getUserParents($uid)
    {

        $parent = TempTransactionHistory::find()->select(['parent_id'])
            ->where(['id' => $uid])->andWhere(['!=', 'parent_id', '0'])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->all();

//         $parent = TempTransactionHistory::find()->select(['parent_id'])
//         ->where(['id'=>$uid])->andWhere(['!=','parent_id','0'])
//         ->andWhere(['!=','event_id',0])
//         ->asArray()->all();

//         if( $parent != null ){
//             array_push($this->parentArr, $parent->parent_id);
//             $this->getUserParents($parent->parent_id);
//         }else{
//             array_push($this->parentArr, $uid);
//         }

    }

    public function getUserChild($uid)
    {

        $parent = User::find()->select(['parent_id'])
            ->where(['id' => $uid])->andWhere(['!=', 'parent_id', '0'])->one();
        if ($parent != null) {
            array_push($this->parentArr, $parent->parent_id);
            $this->getUserParents($parent->parent_id);
        } else {
            array_push($this->parentArr, $uid);
        }

    }

    public function getChildPL($child)
    {

//        $clientArr = [];
//        $clientList = TransactionHistory::find()->select(['DISTINCT(client_id)'])
//            ->where(['user_id' => $child['child_id'], 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
//            ->andWhere(['!=', 'event_id', 0])
//            //->groupBy(['client_id'])
//            ->asArray()->all();
//
//
//        if( $clientList != null ){
//            foreach ( $clientList as $clientData ){
//                $clientArr[] = $clientData['client_id'];
//            }
//        }

        //echo '<pre>';print_r($clientArr);die;

        $profit = TransactionHistory::find()->select(['SUM(p_transaction_amount) as profit'])
            ->where(['user_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            //->andWhere(['IN' , 'client_id' , $clientArr])
            ->asArray()->one();

        $loss = TransactionHistory::find()->select(['SUM(p_transaction_amount) as loss'])
            ->where(['user_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            //->andWhere(['IN' , 'client_id' , $clientArr])
            ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }
        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }
        $this->child_total += $profitVal - $lossVal;
//        if ($child['child_id'] != 0) {
//
//            $cUser = User::findOne(['id' => $child['child_id']]);
//            //echo $cUser->parent_id;
//            if ($cUser->parent_id != 0) {
//                $this->getChildPL(['child_id' => $cUser->parent_id]);
//            }
//
//        }
        return $this->child_total;

    }

    public function getCashPL($uid)
    {

        $pCash = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
            ->where(['event_id' => 0, 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->asArray()->one();

        $lCash = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
            ->where(['event_id' => 0, 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
            ->asArray()->one();

        $pCashVal = $lCashVal = 0;
        if (isset($pCash['profit'])) {
            $pCashVal = $pCash['profit'];
        }

        if (isset($lCash['loss'])) {
            $lCashVal = $lCash['loss'];
        }

        $tCash = $pCashVal - $lCashVal;

        return $tCash;

    }

    public function getParentComm($pid, $uid)
    {

        $profit = TransactionHistory::find()->select(['SUM(p_transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

//         $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
//         ->where(['user_id'=>$pid ,'child_id'=>$uid,'transaction_type' => 'DEBIT', 'is_commission' => 0 , 'is_cash' => 0 , 'status' => 1])
//         ->andWhere(['!=','event_id',0])
//         ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

//         if( isset( $loss['loss'] ) ){
//             $lossVal = $loss['loss'];
//         }

        $this->parent_comm += $profitVal - $lossVal;

//        if ($pid != 0) {
//
//            $pUser = User::findOne(['id' => $pid]);
//            $this->getParentComm($pUser->parent_id, $pid);
//
//        }


        return $this->parent_comm;

    }

    public function getParentPL($pid, $uid)
    {

//        $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
//            ->where(['user_id' => $pid, 'child_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
//            ->andWhere(['!=', 'event_id', 0])
//            ->asArray()->one();
//
//        $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
//            ->where(['user_id' => $pid, 'child_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
//            ->andWhere(['!=', 'event_id', 0])
//            ->asArray()->one();
//
//        $profitVal = $lossVal = 0;
//        if (isset($profit['profit'])) {
//            $profitVal = $profit['profit'];
//        }
//
//        if (isset($loss['loss'])) {
//            $lossVal = $loss['loss'];
//        }

        

//         if( $uid == 1){
//             echo $this->parent_total . ' - 1';
//         }
//         if( $uid == 576){
//             echo $this->parent_total . ' - 576';
//         }
//         if( $uid == 578){
//             echo $this->parent_total . ' - 578';die;
//         }


        $profit = TransactionHistory::find()->select(['SUM(p_transaction_amount) as profit'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $loss = TransactionHistory::find()->select(['SUM(p_transaction_amount) as loss'])
            ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $profitVal = $lossVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }

         $this->parent_total += $profitVal-$lossVal;
//         if ($uid != 1 && $pid != 0) {
//
//             $pUser = User::findOne(['id' => $pid]);
//             $this->getParentPL($pUser->parent_id, $pid);
//
//
//         }
        
        return $this->parent_total;

    }

    // User In Profit
    public function actionUserSummary()
    {
        $currentUser = 'No data';

        if (null !== \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        } else {
            $uid = \Yii::$app->user->id;
        }

        $currentUserData = User::find()->select(['name', 'username','role'])
            ->where(['id' => $uid])->asArray()->one();

        if( $currentUserData != null ){
            $currentUser = $currentUserData['name'].'[ '.$currentUserData['username'].' ]';
        }

        $userInProfitArr = $userInLossArr = [];
        $totalProfit = $totalLoss = 0;
        //$childs = User::findAll(['parent_id'=>$uid]);
        //$cUser = User::findOne(['id' => $uid]);

        $role = \Yii::$app->authManager->getRolesByUser($uid);

        if (isset($role['admin'])) {
            $gtCash = 0;


            //Own Comm
            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $profitVal = $lossVal = 0;
            if (isset($profit['profit'])) {
                $profitVal = $profit['profit'];
            }

            $total = $profitVal - $lossVal;

            $OwnComm = 0;

            $OwnComm = $total;
            if ($OwnComm > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Own Comm ',
                    'balance' => abs($OwnComm),
                    'role' => 0,
                ]);
                $totalProfit += $OwnComm;
            }


            //Own PL
            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $profitVal = $lossVal = 0;
            if (isset($profit['profit'])) {
                $profitVal = $profit['profit'];
            }

            if (isset($loss['loss'])) {
                $lossVal = $loss['loss'];
            }

            $total = $profitVal - $lossVal;

            $OwnPl = 0;

            $OwnPl = $total-$OwnComm;
            if ($OwnPl > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs($OwnPl),
                    'role' => 0,
                ]);

                $totalProfit += $OwnPl;

            } else {
                array_push($userInLossArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs($OwnPl),
                    'role' => 0,
                ]);

                $totalLoss += $OwnPl;
            }


            // child
            $childs = TransactionHistory::find()->select(['DISTINCT ( child_id )'])
                ->where(['user_id' => $uid, 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            foreach ($childs as $child) {

                $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                    ->where(['child_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    ->asArray()->one();

                $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                    ->where(['child_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    ->asArray()->one();

                $profitComm = TransactionHistory::find()->select(['SUM(transaction_amount) as pComm'])
                    ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    ->asArray()->one();
                //var_dump($profitComm);die;
                $profitVal = $lossVal = $commVal = 0;
                if (isset($profit['profit'])) {
                    $profitVal = $profit['profit'];
                }

                if (isset($loss['loss'])) {
                    $lossVal = $loss['loss'];
                }

                if (isset($profitComm['pComm'])) {
                    $commVal = $profitComm['pComm'];
                }

                $total = $profitVal - $lossVal;

                $pCash = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                    ->where(['event_id' => 0, 'parent_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
                    ->asArray()->one();

                $lCash = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                    ->where(['event_id' => 0, 'parent_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
                    ->asArray()->one();


                $pCashVal = $lCashVal = 0;
                if (isset($pCash['profit'])) {
                    $pCashVal = $pCash['profit'];
                }

                if (isset($lCash['loss'])) {
                    $lCashVal = $lCash['loss'];
                }

                $tCash = $pCashVal - $lCashVal;

                $gtCash += $tCash;

                $total += $tCash;

                $user = User::find()->select(['name', 'username', 'role'])
                    ->where(['id' => $child['child_id']])->one();
                if ($user != null) {
                    $role = $user->role;
                    $username = $user->name . ' [' . $user->username . ']';
                } else {
                    $role = 0;
                    $username = 'No Username';
                }

                if ($total >= 0) {
                    if ($total != 0) {

                        $userInProfitArr[] = [
                            'id' => $child['child_id'],
                            'name' => $username,
                            'balance' => abs($total),
                            'role' => $role,
                        ];

                        $totalProfit += $total;

                    }

                } else {
                    $userInLossArr[] = [
                        'id' => $child['child_id'],
                        'name' => $username,
                        'balance' => abs($total),
                        'role' => $role,
                    ];

                    $totalLoss += $total;
                }

            }

            if ($gtCash >= 0) {

                $userInLossArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs($gtCash),
                    'role' => 'cash',
                ];

                $totalLoss += (-1) * $gtCash;

            } else {
                $userInProfitArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs($gtCash),
                    'role' => 'cash',
                ];

                $totalProfit += (-1) * $gtCash;
            }


        } else {

            //Own Comm
            $OwnComm = 0;
            $profitComm = TransactionHistory::find()->select(['SUM(transaction_amount) as commVal'])
                ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $commVal = 0;
            if (isset($profitComm['commVal'])) {
                $commVal = $profitComm['commVal'];
            }


            $OwnComm = $commVal;
            if ($OwnComm > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Own Comm ',
                    'balance' => abs($OwnComm),
                    'role' => 0,
                ]);
                $totalProfit += $OwnComm;
            }

            //OwnPl
            $OwnPl = 0;
            $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            $profitVal = $lossVal = 0;
            if (isset($profit['profit'])) {
                $profitVal = $profit['profit'];
            }

            if (isset($loss['loss'])) {
                $lossVal = $loss['loss'];
            }

            $total = $profitVal - $lossVal;


            $OwnPl = $total-$OwnComm;


            if ($OwnPl > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs($OwnPl),
                    'role' => 0,
                ]);
                $totalProfit += $OwnPl;
            } else {
                array_push($userInLossArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs($OwnPl),
                    'role' => 0,
                ]);
                $totalLoss += $OwnPl;
            }

            // childs data

            $childs = TransactionHistory::find()->select(['DISTINCT ( child_id )'])
                ->where(['user_id' => $uid, 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $gtCash = 0;
            //echo '<pre>';print_r($childs);die;
            foreach ($childs as $child) {

                $this->child_total = 0;
                $tCash = 0;
                $role = \Yii::$app->authManager->getRolesByUser($child['child_id']);
                if (isset($role['client'])) {

                    $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                        ->where(['user_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                        ->where(['user_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $profitComm = TransactionHistory::find()->select(['SUM(transaction_amount) as profitComm'])
                        ->where(['user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                        ->andWhere(['!=', 'event_id', 0])
                        ->asArray()->one();

                    $profitVal = $lossVal = $commVal = 0;
                    if (isset($profit['profit'])) {
                        $profitVal = $profit['profit'];
                    }

                    if (isset($loss['loss'])) {
                        $lossVal = $loss['loss'];
                    }

                    if (isset($profitComm['profitComm'])) {
                        $commVal = $profitComm['profitComm'];
                    }

                    // cash total
                    $tCash = $this->getCashPL($child['child_id']);
                    $gtCash += $tCash;

                    $total = $profitVal - $lossVal + $tCash - $commVal;

                } else {

                    $user = User::findOne($uid);
                    $pid = $user->parent_id;

                    $total = $this->getChildPL($child);

                    // cash total
                    $tCash = $this->getCashPL($child['child_id']);

                    $gtCash += $tCash;

                    $total += $tCash;

                }

                $user = User::find()->select(['name', 'username', 'role'])
                    ->where(['id' => $child['child_id']])->one();
                if ($user != null) {
                    $role = $user->role;
                    $username = $user->name . ' [' . $user->username . ']';
                } else {
                    $role = 0;
                    $username = 'No Username';

                }

                if ($total >= 0) {
                    if ($total != 0) {
                        $userInProfitArr[] = [
                            'id' => $child['child_id'],
                            'name' => $username,
                            'balance' => abs($total),
                            'role' => $role,
                        ];

                        $totalProfit += $total;
                    }
                } else {
                    $userInLossArr[] = [
                        'id' => $child['child_id'],
                        'name' => $username,
                        'balance' => abs($total),
                        'role' => $role,
                    ];

                    $totalLoss += $total;
                }

            }
            // parent cash deposit total
            $ppCash = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                ->where(['event_id' => 0, 'user_id' => $uid, 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
                ->asArray()->one();

            $plCash = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                ->where(['event_id' => 0, 'user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
                ->asArray()->one();

            $ppCashVal = $plCashVal = 0;
            if (isset($ppCash['profit'])) {
                $ppCashVal = $ppCash['profit'];
            }

            if (isset($plCash['loss'])) {
                $plCashVal = $plCash['loss'];
            }
            $parent_cash_deposit = $ppCashVal - $plCashVal;

            $gtCash += $ppCashVal - $plCashVal;

            if ($gtCash >= 0) {

                $userInLossArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs($gtCash),
                    'role' => 'cash',
                ];

                $totalLoss += (-1) * $gtCash;


            } else {
                $userInProfitArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs($gtCash),
                    'role' => 'cash',
                ];

                $totalProfit += (-1) * $gtCash;

            }


            $pUser = TransactionHistory::find()->select(['parent_id'])
                ->where(['user_id' => $uid, 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            //echo '<pre>';print_r($pUser);die;

            // ParentComm

            $ParentComm = 0;

            $ParentComm = $this->getParentComm($pUser['parent_id'], $uid);
            //$ParentComm += $parent_cash_deposit;
            if ($ParentComm > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Parent Comm ',
                    'balance' => abs($ParentComm),
                    'role' => 0,
                ]);
                $totalProfit += $ParentComm;
            }

//            $parentPl1 = 0;
//
//            if( $totalProfit >= $totalLoss ){
//
//                $parentPl1 = abs($totalProfit)-abs($totalLoss);
//
//            }else{
//                $parentPl1 = abs($totalLoss)-abs($totalProfit);
//            }


            //$parentPl1 = $parentPl1-$ParentComm;

            //$parentPl1 += $parent_cash_deposit;

//            if ($parentPl1 > 0) {
//                array_push($userInProfitArr, [
//                    'id' => '',
//                    'name' => ' Parent Pl ',
//                    'balance' => abs($parentPl1),
//                    'role' => 0,
//                ]);
//
//                //$totalProfit += $ParentPl;
//
//            } else {
//                array_push($userInLossArr, [
//                    'id' => '',
//                    'name' => ' Parent Pl ',
//                    'balance' => abs($parentPl1),
//                    'role' => 0,
//                ]);
//
//                //$totalLoss += $ParentPl;
//
//            }




            // ParentPl

            $ParentPl = 0;

            $ParentPl = $total;

            $ParentPl = $this->getParentPL($pUser['parent_id'], $uid);

            $ParentPl = $ParentPl-$ParentComm;

            $ParentPl += $parent_cash_deposit;
            
//             if($uid == 566){
//                 echo $ParentPl; die;
//             }
            
            if ($ParentPl > 0) {
                array_push($userInProfitArr, [
                    'id' => '',
                    'name' => ' Parent Pl ',
                    'balance' => abs($ParentPl),
                    'role' => 0,
                ]);

                $totalProfit += $ParentPl;

            } else {
                array_push($userInLossArr, [
                    'id' => '',
                    'name' => ' Parent Pl ',
                    'balance' => abs($ParentPl),
                    'role' => 0,
                ]);

                $totalLoss += $ParentPl;

            }


        }

        //total

        $items['inPlus'] = [
            'user' => $userInProfitArr,
            'comm' => 0,
            'total' => abs($totalProfit),
        ];
        $items['inMinus'] = [
            'user' => $userInLossArr,
            'total' => abs($totalLoss),
        ];


        return ["status" => 1, "data" => ["items" => $items , "currentUser" => $currentUser ]];

    }
    
    // Clear Chips
    public function actionClearChips(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];
        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;
            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){
                    //echo '<pre>';print_r($user);die;
                    $amount = $r_data[ 'amount' ];
                    if( $r_data[ 'amount' ] < 0 ){
                        $amount = (-1)*$r_data[ 'amount' ];
                    }
                    
                    if( $r_data[ 'typ' ] == 'loss' ){
                        $user->profit_loss_balance = ($user->profit_loss_balance+$amount);
                    }else{
                        $user->profit_loss_balance = ($user->profit_loss_balance-$amount);
                    }
                    
                    if( $user->save( [ 'profit_loss_balance' ] ) ){
                        if( $this->clearChipsTransaction( $user , $amount , $r_data[ 'typ' ] ) == true ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Cash Deposit Successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! user not updeted!" ,
                                "data" => $user->errors
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! user not updeted!" ,
                            "data" => $user->errors
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }
    
    
    // Clear Chips Transaction
    public function clearChipsTransaction($user , $amount , $typ){
        
        $pUser = User::findOne( $user->parent_id );
        $parentUserName = 'Not Set';
        if( $pUser != null ){
            $parentUserName = $pUser->name;
        }
        
        $userName = 'Not Set';
        if( $user != null ){
            $userName = $user->name;
        }
        
        $trans = new TransactionHistory();
        
        $trans->user_id = $user->id;
        $trans->child_id = 0;
        $trans->parent_id = $user->parent_id;
        $trans->event_id = $trans->market_id = 0;
        $trans->transaction_amount = $amount;
        $trans->is_cash = 1;
        
        if( $typ == 'loss' ){
            $trans->current_balance = $user->balance+$user->profit_loss_balance-$user->expose_balance;
            $trans->transaction_type = 'CREDIT';
            $trans->description = 'Cash Deposit to '.$parentUserName.' by '.$userName;
        }else{
            $trans->current_balance = $user->balance-$user->profit_loss_balance-$user->expose_balance;
            $trans->transaction_type = 'DEBIT';
            $trans->description = 'Cash Withdraw from '.$parentUserName.' by '.$userName;
        }
        
        $trans->status = 1;
        $trans->created_at = $trans->updated_at = time();
        
        if( $trans->save() ){
            return true;
        }else{
            return false;
        }
        
    }
    
    // Clear Chips Parent Transaction
    public function clearChipsParentTransaction($user , $amount , $typ){
        
        $parentUser = User::findOne( $user->parent_id );
        
        $trans = new TempTransactionHistory();
        
        $trans->user_id = $parentUser->id;
        $trans->client_id = 0;
        $trans->parent_id = $parentUser->parent_id;
        $trans->bet_id = $trans->event_id = 0;
        $trans->parent_type = 'F';
        $trans->username = $parentUser->name;
        $trans->transaction_amount = $amount;
        $trans->commission = 0;
        
        if( $typ == 'loss' ){
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance+$amount;
            
            $trans->current_balance = $parentUser->profit_loss_balance;
            $trans->transaction_type = 'W/CASH';
            $trans->description = 'Cash Withdraw from '.$user->name.' Rs.'.$amount;
        }else{
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance-$amount;
            
            $trans->current_balance = $parentUser->profit_loss_balance;
            $trans->transaction_type = 'D/CASH';
            $trans->description = 'Cash Deposit to '.$user->name.' Rs.'.$amount;
        }
        
        $trans->status = 1;
        $trans->created_at = $trans->updated_at = time();
        
        if( $trans->save() && $parentUser->save(['profit_loss_balance'])  ){
            return true;
        }else{
            return false;
        }
        
    }

}
