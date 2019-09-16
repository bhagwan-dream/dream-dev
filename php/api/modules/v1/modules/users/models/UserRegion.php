<?php

namespace api\modules\v1\modules\users\models;

use Yii;
use api\modules\v1\modules\location\models\LocationRegion;
use api\modules\v1\modules\instances\model\InstanceLocationRegion;

/**
 * This is the model class for table "user_region".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $region_id
 * @property integer $status
 */
class UserRegion extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_region';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'region_id', 'status'], 'required'],
            [['user_id', 'region_id', 'status'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'region_id' => Yii::t('app', 'Region ID'),
            'status' => Yii::t('app', 'Status'),
        ];
    }
    
    public function getRelationRegion(){
        return $this->hasOne( LocationRegion::className() , [ 'id' => 'region_id' ] );
    }
    
    public function getRelationInstanceRegion(){
        return $this->hasOne( InstanceLocationRegion::className() , [ 'region_id' => 'region_id' ] );
    }
}
