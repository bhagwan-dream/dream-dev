<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;


/**
 * MarketType model
 *
 * @property integer $id
 * @property integer $event_type_id
 * @property string $market_type
 * @property string $market_name
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class MarketType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%market_type}}';
    }
  
}
