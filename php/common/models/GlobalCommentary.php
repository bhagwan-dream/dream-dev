<?php
namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * GlobalCommentary model
 *
 * @property integer $id
 * @property integer $event_id
 * @property string $title
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class GlobalCommentary extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%global_commentary}}';
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
