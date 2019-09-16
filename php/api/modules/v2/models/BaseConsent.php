<?php

namespace api\modules\v2\models;

use Yii;

use api\modules\v1\modules\app\models\MiConsentApi;
use api\modules\v1\modules\instances\model\Instance;
use api\modules\v1\modules\instances\model\AppUser;
use api\modules\v1\modules\consent\models\ConsentInfo;
use api\modules\v1\modules\consent\models\ConsentStatus;

/**
 * This is the model class for table "consent".
 *
 * @property integer $id
 * @property integer $instance_id
 * @property integer $user_id
 * @property string $country_title
 * @property string $email
 * @property string $phone_code
 * @property string $phone_number
 * @property string $title
 * @property string $first_name
 * @property string $last_name
 * @property string $work_name
 * @property string $work_addr
 * @property string $postal_code
 * @property string $city
 * @property string $therapy
 * @property string $speciality
 * @property string $one_key_id
 * @property string $one_key_request_id
 * @property string $signature
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $status
 */
class BaseConsent extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consent';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['instance_id', 'user_id', 'country_title', 'email', 'first_name', 'last_name', 'work_name', 'work_addr', 'city', 'created_at', 'updated_at', 'status'], 'required'],
            [['instance_id', 'type' , 'user_id', 'created_at', 'updated_at', 'status'], 'integer'],
            [['work_addr', 'one_key_request_response', 'signature'], 'string'],
            [['country_title', 'phone_number' , 'first_name', 'last_name', 'city', 'one_key_request_id'], 'string', 'max' => 32],
            [['country_code', 'title', 'postal_code', 'therapy_code', 'speciality_code'], 'string', 'max' => 16],
            [['email', 'work_name'], 'string', 'max' => 255],
            [['phone_code'], 'string', 'max' => 8],
            [['therapy_title', 'speciality_title', 'one_key_id' , 'one_key_individual_id' ], 'string', 'max' => 64],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'type' =>  Yii::t('app', 'Type'),
            'instance_id' => Yii::t('app', 'Instance ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'country_title' => Yii::t('app', 'Country Title'),
            'country_code' => Yii::t('app', 'Country Code'),
            'email' => Yii::t('app', 'Email'),
            'phone_code' => Yii::t('app', 'Phone Code'),
            'phone_number' => Yii::t('app', 'Phone Number'),
            'title' => Yii::t('app', 'Title'),
            'first_name' => Yii::t('app', 'First Name'),
            'last_name' => Yii::t('app', 'Last Name'),
            'work_name' => Yii::t('app', 'Work Name'),
            'work_addr' => Yii::t('app', 'Work Addr'),
            'postal_code' => Yii::t('app', 'Postal Code'),
            'city' => Yii::t('app', 'City'),
            'therapy_title' => Yii::t('app', 'Therapy Title'),
            'therapy_code' => Yii::t('app', 'Therapy Code'),
            'speciality_title' => Yii::t('app', 'Speciality Title'),
            'speciality_code' => Yii::t('app', 'Speciality Code'),
            'one_key_id' => Yii::t('app', 'One Key ID'),
            'one_key_request_id' => Yii::t('app', 'One Key Request ID'),
            'one_key_request_response' => Yii::t('app', 'One Key Request Response'),
            'signature' => Yii::t('app', 'Signature'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'status' => Yii::t('app', 'Status'),
        ];
    }
    
    public function beforeSave($insert){
        if( $insert ){
            $this->created_at = time();
            $this->updated_at = 0;
            $this->status = 1;
        }else{
            $this->updated_at = time();
        }
        
        return parent::beforeSave($insert);
    }
    
    public function getRelationInstance(){
        return $this->hasOne( Instance::className() , [ 'id' => 'instance_id' ] )->from( [ Instance::tableName() . ' ti' ] );
    }
    
    public function getRelationAppUser(){
        return $this->hasOne( AppUser::className() , [ 'id' => 'user_id' ] )->from( [ AppUser::tableName() . ' u' ] );;
    }
    
    public function getRelationMIRequest(){
        return $this->hasMany( MiConsentApi::className() , [ 'consent_id' => 'id' ]);
    }
    
    public function getRelationConsentInfo(){
        return $this->hasMany( ConsentInfo::className() , [ 'consent_id' => 'id' ]);
    }
    
    public function getRelationStatus(){
        return $this->hasOne( ConsentStatus::className() , [ 'consent_id' => 'id' ] )->from( [ ConsentStatus::tableName() . ' tcs' ] );
    }
}
