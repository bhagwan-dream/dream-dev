<?php

namespace api\modules\v1\modules\chips\controllers;

use common\models\TransactionUserBalance;
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


        $pComm = TransactionHistory::find()->select(['SUM(p_transaction_amount) as pComm'])
            ->where(['user_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $profitVal = $lossVal = $commVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }
        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }
        if (isset($pComm['pComm'])) {
            $commVal = $pComm['pComm'];
        }

        if( ($profitVal-$lossVal) > 0 ){
//            $this->child_total += $profitVal-$lossVal-$commVal;
            $this->child_total += $profitVal-$lossVal;
        }else{
//            $this->child_total += $profitVal-$lossVal+$commVal;
            $this->child_total += $profitVal-$lossVal;
        }

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

        $this->parent_comm += $profitVal;

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

        $pComm = TransactionHistory::find()->select(['SUM(p_transaction_amount) as pComm'])
            ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
            ->andWhere(['!=', 'event_id', 0])
            ->asArray()->one();

        $profitVal = $lossVal = $commVal = 0;
        if (isset($profit['profit'])) {
            $profitVal = $profit['profit'];
        }

        if (isset($loss['loss'])) {
            $lossVal = $loss['loss'];
        }

        if (isset($pComm['pComm'])) {
            $commVal = $pComm['pComm'];
        }

         $this->parent_total += $profitVal-$lossVal;

//        if( ( $profitVal-$lossVal ) > 0 ){
//            $this->parent_total += $profitVal-$lossVal - $commVal;
//        }else{
//            $this->parent_total += $profitVal-$lossVal + $commVal;
//        }

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
        $currentUserParentId = 0;

        if (null !== \Yii::$app->request->get('id')) {
            $uid = \Yii::$app->request->get('id');
        } else {
            $uid = \Yii::$app->user->id;
        }

        $currentUserData = User::find()->select(['name', 'username','role' , 'parent_id'])
            ->where(['id' => $uid])->asArray()->one();

        if( $currentUserData != null ){
            $currentUser = $currentUserData['name'].' [ '.$currentUserData['username'].' ]';
            $currentUserParentId = $currentUserData['parent_id'];
        }

        if ( null == \Yii::$app->request->get('id') || \Yii::$app->request->get('id') == \Yii::$app->user->id ) {
            $currentUserParentId = 0;
        }

        $userInProfitArr = $userInLossArr = [];
        $ownInProfitArr = $ownInLossArr = [];
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
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Own Comm ',
                    'balance' => abs(round($OwnComm,2)),
                    'role' => 0,
                ]);
                $totalProfit += $OwnComm;
            }


            //Own PL

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

            if( $total > 0 ){
                $OwnPl = $total-$OwnComm;
            }else{
                $OwnPl = $total+$OwnComm;
            }

            //$OwnPl = $total+$OwnComm;
            //$OwnPl = $total;


            if ($OwnPl > 0) {
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs(round($OwnPl,2)),
                    'role' => 0,
                ]);

                $totalProfit += $OwnPl;

            } else {
                array_push($ownInLossArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs(round($OwnPl,2)),
                    'role' => 0,
                ]);

                $totalLoss += $OwnPl;
            }


            // child
            $childs = TransactionHistory::find()->select(['DISTINCT ( child_id )'])
                ->where(['user_id' => $uid, 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->all();

            $childArr = [];

            foreach ($childs as $child) {

                $childArr[] = $child['child_id'];

                $profit = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                    ->where(['child_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    ->asArray()->one();

                $loss = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                    ->where(['child_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
                    ->andWhere(['!=', 'event_id', 0])
                    ->asArray()->one();

//                $profit = TransactionHistory::find()->select(['SUM(p_transaction_amount) as profit'])
//                    ->where(['user_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
//                    ->andWhere(['!=', 'event_id', 0])
//                    //->andWhere(['IN' , 'client_id' , $clientArr])
//                    ->asArray()->one();
//
//                $loss = TransactionHistory::find()->select(['SUM(p_transaction_amount) as loss'])
//                    ->where(['user_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 0, 'status' => 1])
//                    ->andWhere(['!=', 'event_id', 0])
//                    //->andWhere(['IN' , 'client_id' , $clientArr])
//                    ->asArray()->one();


                $profitComm = TransactionHistory::find()->select(['SUM(transaction_amount) as pComm'])
                    ->where(['child_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
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
//                if( $total > 0 ){
//                    $total = $profitVal - $lossVal - $commVal;
//                }else{
//                    $total = $profitVal - $lossVal + $commVal;
//                }

                $pCash = TransactionHistory::find()->select(['SUM(transaction_amount) as profit'])
                    ->where(['event_id' => 0, 'user_id' => $child['child_id'], 'transaction_type' => 'CREDIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
                    ->asArray()->one();

                $lCash = TransactionHistory::find()->select(['SUM(transaction_amount) as loss'])
                    ->where(['event_id' => 0, 'user_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 0, 'is_cash' => 1, 'status' => 1])
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
                    //if ($total != 0) {
                        $userInProfitArr[] = [
                            'id' => $child['child_id'],
                            'name' => $username,
                            'balance' => abs(round($total,2)),
                            'role' => $role,
                        ];
                        $totalProfit += $total;
                    //}

                } else {
                    $userInLossArr[] = [
                        'id' => $child['child_id'],
                        'name' => $username,
                        'balance' => abs(round($total,2)),
                        'role' => $role,
                    ];
                    $totalLoss += $total;
                }

            }

            $otherChild = User::find()->select(['id','name', 'username', 'role'])
                ->where(['parent_id' => $uid])
                ->andWhere(['NOT IN', 'role' , [5,6]])
                ->andWhere([ 'NOT IN', 'id' , $childArr])
                ->all();

            if( $otherChild != null ){

                foreach ( $otherChild as $child ){

                    $userInProfitArr[] = [
                        'id' => $child['id'],
                        'name' => $child['name'] . ' [ '.$child['username'].' ]',
                        'balance' => 0,
                        'role' => $child['role'],
                    ];

                }

            }


            if ($gtCash >= 0) {

                $ownInLossArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs(round($gtCash,2)),
                    'role' => 'cash',
                ];

                $totalLoss += (-1) * $gtCash;

            } else {
                $ownInProfitArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs(round($gtCash,2)),
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
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Own Comm ',
                    'balance' => abs(round($OwnComm,2)),
                    'role' => 0,
                ]);
                //$totalProfit += $OwnComm;
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

            if( $total > 0 ){
                $OwnPl = $total;
//                $OwnPl = $total-$OwnComm;
            }else{
                $OwnPl = $total;
//                $OwnPl = $total+$OwnComm;
            }

            //$OwnPl = $total+$OwnComm;
            //$OwnPl = $total;

            if ($OwnPl > 0) {
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs(round($OwnPl,2)),
                    'role' => 0,
                ]);
                $totalProfit += $OwnPl;
            } else {
                array_push($ownInLossArr, [
                    'id' => '',
                    'name' => ' Own Pl ',
                    'balance' => abs(round($OwnPl,2)),
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
            $childArr = [];
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
                        ->where(['user_id' => $child['child_id'], 'transaction_type' => 'DEBIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
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

                    $total = $profitVal - $lossVal + $tCash ;

                    if( $total > 0 ){
//                        $OwnPl = $total;
                        $total = $total-$commVal;
                    }else{
//                        $OwnPl = $total;
                        $total = $total+$commVal;
                    }

                    $totalProfit += $commVal;

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
                    //if ($total != 0) {
                        $userInProfitArr[] = [
                            'id' => $child['child_id'],
                            'name' => $username,
                            'balance' => abs(round($total,2)),
                            'role' => $role,
                        ];

                        $totalProfit += $total;
                    //}
                } else {
                    $userInLossArr[] = [
                        'id' => $child['child_id'],
                        'name' => $username,
                        'balance' => abs(round($total,2)),
                        'role' => $role,
                    ];

                    $totalLoss += $total;
                }

                $childArr[] = $child['child_id'];

            }

            $otherChild = User::find()->select(['id','name', 'username', 'role'])
                ->where(['parent_id' => $uid])
                ->andWhere(['NOT IN', 'role' , [5,6]])
                ->andWhere([ 'NOT IN', 'id' , $childArr])
                ->all();

            if( $otherChild != null ){

                foreach ( $otherChild as $child ){

                    $userInProfitArr[] = [
                        'id' => $child['id'],
                        'name' => $child['name'] . ' [ '.$child['username'].' ]',
                        'balance' => 0,
                        'role' => $child['role'],
                    ];

                }

            }

            //parent cash deposit total
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

                $ownInLossArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs(round($gtCash,2)),
                    'role' => 'cash',
                ];

                $totalLoss += (-1) * $gtCash;


            } else {
                $ownInProfitArr[] = [
                    'id' => '',
                    'name' => 'Cash',
                    'balance' => abs(round($gtCash,2)),
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

            /*
            $ParentComm = 0;
            $ParentComm = $this->getParentComm($pUser['parent_id'], $uid);
            //$ParentComm += $parent_cash_deposit;
            if ($ParentComm > 0) {
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Parent Comm ',
                    'balance' => abs(round($ParentComm,2)),
                    'role' => 0,
                ]);
                $totalProfit += $ParentComm;
            }*/

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

            //$ParentPl = $total;

            $ParentPl = $this->getParentPL($pUser['parent_id'], $uid);

            $pComm = TransactionHistory::find()->select(['SUM(p_transaction_amount) as pComm'])
                ->where(['user_id' => $uid, 'transaction_type' => 'CREDIT', 'is_commission' => 1, 'is_cash' => 0, 'status' => 1])
                ->andWhere(['!=', 'event_id', 0])
                ->asArray()->one();

            if (isset($pComm['pComm'])) {
                $pComm = $pComm['pComm'];
            }


            if ($pComm > 0) {
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Parent Comm. ',
                    'balance' => abs(round($pComm,2)),
                    'role' => 0,
                ]);

                //$totalProfit += $pComm;

            }

            //$ParentPl = $ParentPl-$ParentComm;

            $ParentPl += $parent_cash_deposit;
            
//             if($uid == 566){
//                 echo $ParentPl; die;
//             }
            
            if ($ParentPl > 0) {
                array_push($ownInProfitArr, [
                    'id' => '',
                    'name' => ' Parent Pl ',
                    'balance' => abs(round($ParentPl,2)),
                    'role' => 0,
                ]);

                $totalProfit += $ParentPl;

            } else {
                array_push($ownInLossArr, [
                    'id' => '',
                    'name' => ' Parent Pl ',
                    'balance' => abs(round($ParentPl,2)),
                    'role' => 0,
                ]);

                $totalLoss += $ParentPl;

            }

        }

        //total

        $items['inPlus'] = [
            'user' => $userInProfitArr,
            'own' => $ownInProfitArr,
            'comm' => 0,
            'total' => abs(round($totalProfit,2)),
        ];
        $items['inMinus'] = [
            'user' => $userInLossArr,
            'own' => $ownInLossArr,
            'total' => abs(round($totalLoss,2)),
        ];

        return ["status" => 1, "data" => ["items" => $items , "currentUser" => $currentUser , 'currentUserParentId' => $currentUserParentId ]];

    }
    
    // Clear Chips
    public function actionClearChips(){
        $response =  [ "status" => 0 , "error" => [ "code" => 400 , "message" => "Bad request!" ] ];

        $request_data = json_decode( file_get_contents('php://input') , JSON_FORCE_OBJECT );
        //die('asd');
        if( json_last_error() == JSON_ERROR_NONE ){
            $r_data = ArrayHelper::toArray( $request_data );
            //echo '<pre>';print_r($r_data);die;

            if( $r_data[ 'typ' ] == 'profit' &&  $r_data[ 'amount' ] > $r_data[ 'tempamount' ] ){

                $response = [
                    "error" => [
                        "message" => "Invalid amount entered !!"
                    ]
                ];
                return $response;
            }

            if( $r_data[ 'typ' ] == 'loss' &&  $r_data[ 'amount' ] > $r_data[ 'tempamount' ] ){

                $response = [
                    "error" => [
                        "message" => "Invalid amount entered !!"
                    ]
                ];
                return $response;
            }

            if( isset( $r_data[ 'id' ] ) ){
                $user = User::findOne( $r_data[ 'id' ] );
                if( $user != null ){

                    $amount = $r_data[ 'amount' ];
                    if( $r_data[ 'amount' ] < 0 ){
                        $amount = (-1)*$r_data[ 'amount' ];
                    }

                    $uBlnc = $user->balance-$user->expose_balance+$user->profit_loss_balance;

                    if( $r_data[ 'typ' ] == 'profit' &&  $r_data[ 'amount' ] > $uBlnc ){

                        $response = [
                            "error" => [
                                "message" => "Balance not available !!"
                            ]
                        ];
                        return $response;

                    }

                    if( $r_data[ 'typ' ] == 'loss' ){
                        $user->profit_loss_balance = ($user->profit_loss_balance+$amount);
                        $user->balance = ($user->balance-$amount);
                    }else{
                        $user->profit_loss_balance = ($user->profit_loss_balance-$amount);
                    }
                    
                    if( $user->save( [ 'profit_loss_balance' ] ) ){
                        if( $this->clearChipsTransaction( $user , $amount , $r_data[ 'typ' ] , $r_data[ 'remark' ] ) == true
                            && $this->updateChipsTransaction( $user , $r_data ) == true ){
                            //&& $this->clearChipsParentTransaction( $user , $amount , $r_data[ 'typ' ] ) == true ){
                            $response = [
                                'status' => 1,
                                "success" => [
                                    "message" => "Cash Deposit Successfully!"
                                ]
                            ];
                        }else{
                            $response[ "error" ] = [
                                "message" => "Something wrong! user not updated!"
                            ];
                        }
                        
                    }else{
                        $response[ "error" ] = [
                            "message" => "Something wrong! user not updated!"
                        ];
                    }
                    
                }
            }
        }
        
        return $response;
    }

    // Update Chips Transaction
    public function updateChipsTransaction($user , $data){

        if( isset( $data['typ'] ) && ( $data['typ'] == 'loss' ) ){

            $pUser = User::findOne( $user->parent_id );

            $columns = ['user_id','parent_id','child_id','event_id','market_id','transaction_type','transaction_amount','current_balance','description','remark','status','updated_at','created_at'];

            if( isset($data['chips']) && $data['chips'] != null || $data['chips'] != "" ){
                $chips = $data['chips']+0;
            }else{
                $chips = 0;
            }

            if( isset($data['chips']) && $data['amount'] != null || $data['amount'] != "" ){
                $amount = (float)$data['amount'];
            }else{
                $amount = 0;
            }

            $resultArr[] = [ $user->id,$user->parent_id,0,0,0,'DEBIT',$amount,($user->balance-$amount),'Cash Settled By '.$pUser->username,$data['remark'],1,time(),time()];
            $resultArr[] = [ $user->id,$user->parent_id,0,0,0,'CREDIT',$chips,($user->balance+$chips),'Deposit By '.$pUser->username,$data['remark'],1,time(),time()];
            $resultArr[] = [ $pUser->id,$pUser->parent_id,0,0,0,'DEBIT',$chips,($user->balance-$chips),'Deposit To '.$user->username,$data['remark'],1,time(),time()];

            if( \Yii::$app->db->createCommand()->batchInsert('transaction_history', $columns,$resultArr )->execute() ){

                if( $pUser->balance > $chips ){
                    $pUser->balance = $pUser->balance-$chips;
                    $pUser->save(['balance']);
                }
                return true;
            }else{
                return false;
            }

        }else{
            return true;
        }

    }
    // Clear Chips Transaction
    public function clearChipsTransaction($user , $amount , $typ , $remark){
        
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
        $trans->client_id = $user->id;
        $trans->child_id = 0;
        $trans->parent_id = $user->parent_id;
        $trans->event_id = $trans->market_id = 0;
        $trans->transaction_amount = $amount;
        $trans->is_cash = 1;

        if( $user->role == 4 ){
            $current_balance = $user->balance+$user->profit_loss_balance-$user->expose_balance;
        }else{
            $current_balance = $user->profit_loss_balance;
        }

        if( $typ == 'loss' ){
            $trans->current_balance = $current_balance;
            $trans->transaction_type = 'CREDIT';
            //$trans->description = 'Cash Received From '.$userName.' By '.$parentUserName;
            $trans->description = 'Cash Deposit By '.$userName.' To '.$parentUserName;
        }else{
            $trans->current_balance = $current_balance;
            $trans->transaction_type = 'DEBIT';
            //$trans->description = 'Cash Received By '.$userName.' From '.$parentUserName;
            $trans->description = 'Cash Received By '.$userName.' From '.$parentUserName;
        }

        $trans->remark = $remark;
        $trans->status = 1;
        $trans->created_at = $trans->updated_at = time();

        if( $trans->save() ){

            $transBal = new TransactionUserBalance();
            $transBal->trans_id = $trans->id;
            $transBal->user_id = $trans->parent_id;
            $transBal->updated_at = time();

            if( $typ == 'loss' ){
                $transBal->balance = $pUser->profit_loss_balance-$amount;
                //$trans->transaction_type = 'DEBIT';
            }else{
                $transBal->balance = $pUser->profit_loss_balance+$amount;
                //$trans->transaction_type = 'CREDIT';
            }

            if( $transBal->save() ){
                return true;
            }else{
                $trans->delete();
                return false;
            }

        }else{
            return false;
        }
        
    }
    
    // Clear Chips Parent Transaction
    public function clearChipsParentTransaction($user , $amount , $typ){
        
        $parentUser = User::findOne( $user->parent_id );

        $parentUserName = 'Not Set';
        if( $parentUser != null ){
            $parentUserName = $parentUser->name;
        }

        $userName = 'Not Set';
        if( $user != null ){
            $userName = $user->name;
        }

        $trans = new TransactionHistory();
        
        $trans->user_id = $parentUser->id;
        $trans->client_id = $user->id;
        $trans->child_id = $user->id;
        $trans->parent_id = $parentUser->parent_id;
        $trans->event_id = $trans->market_id = 0;
        $trans->transaction_amount = $amount;
        $trans->is_cash = 1;

        //$current_balance = $parentUser->balance+$parentUser->profit_loss_balance-$parentUser->expose_balance;
        $current_balance = $parentUser->profit_loss_balance;
        
        if( $typ == 'loss' ){
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance-$amount;
            
            $trans->current_balance = $current_balance+$amount;
            $trans->transaction_type = 'DEBIT';
            $trans->description = 'Cash Received from '.$userName.' by '.$parentUserName;

        }else{
            
            $parentUser->profit_loss_balance = $parentUser->profit_loss_balance+$amount;
            
            $trans->current_balance = $current_balance-$amount;
            $trans->transaction_type = 'CREDIT';
            $trans->description = 'Cash Paid to '.$userName.' by '.$parentUserName;
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
