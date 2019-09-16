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
class TempTransactionHistory extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%temp_transaction_history}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [ 
            [[ 'client_id' ,'user_id' ,'parent_id', 'bet_id' , 'username' , 'transaction_type' , 'transaction_amount' ,'commission', 'current_balance' , 'description' , 'status' , 'created_at' , 'updated_at' ], 'safe'],
        ];
    }
    
    public function getUser(){
        return $this->hasOne( User::className() , [ 'user_id' => 'id' ] );
    }
}
