<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Event model
 *
 * @property integer $id
 * @property string $event_type_id
 * @property string $event_type_name
 * @property string $market_count
 * @property string $min_stack
 * @property string $max_stack
 * @property string $max_profit
 * @property string $bet_delay
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class Event extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%events}}';
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
}
