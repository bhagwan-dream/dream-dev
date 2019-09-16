<?php

namespace api\modules\v1\modules\users\models;

use Yii;
use api\modules\v1\modules\instances\model\InstanceLocationRegion;
use api\modules\v1\modules\instances\model\InstanceLocationCountry;

/**
 * This is the model class for table "user_country".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $region_id
 * @property integer $country_id
 * @property integer $status
 */
class UserCountry extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_country';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'region_id', 'country_id', 'status'], 'required'],
            [['user_id', 'region_id', 'country_id', 'status'], 'integer'],
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
            'country_id' => Yii::t('app', 'Country ID'),
            'status' => Yii::t('app', 'Status'),
        ];
    }
    
    public function getRelationRegion(){
        return $this->hasOne( InstanceLocationRegion::className() , [ "region_id" => "region_id" ] );
    }
    
    public function getRelationCountry(){
        return $this->hasOne( InstanceLocationCountry::className() , [ "loc_region_id" => "region_id" , "loc_country_id" => "country_id" ] );
    }
}
