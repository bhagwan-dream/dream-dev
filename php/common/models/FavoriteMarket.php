<?php
namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Setting model
 *
 * @property integer $id
 * @property string $user_id
 * @property string $market_id
 * 
 */
class FavoriteMarket extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%favorite_market}}';
    }
}
