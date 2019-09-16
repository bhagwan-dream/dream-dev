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
 * @property string $bet_option
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class TeenPattiResult extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%teen_patti_result}}';
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
