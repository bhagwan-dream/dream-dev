<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * TransactionHistory model
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $bet_id
 * @property string $client_name
 * @property string $transaction_type
 * @property string $transaction_amount
 * @property string $current_balance
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class TransactionHistory extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%transaction_history}}';
    }
}
