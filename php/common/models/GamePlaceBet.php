<?php
namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;


class GamePlaceBet extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()  
    {
        return '{{%game_place_bet}}';
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
