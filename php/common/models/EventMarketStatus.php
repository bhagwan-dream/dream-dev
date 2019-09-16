<?php
namespace common\models;

use yii\db\ActiveRecord;

class EventMarketStatus extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%event_market_status}}';
    }
}
