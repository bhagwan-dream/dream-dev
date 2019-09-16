<?php

namespace api\modules\v1\modules\users\models;

use Yii;
use common\models\User;
//use api\modules\v1\modules\instances\model\AppUser;
//use api\modules\v1\modules\consent\models\ConsentEmail;

/**
 * This is the model class for table "{{%auth_token}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $token
 * @property integer $expired_on
 */
class AuthToken extends \yii\db\ActiveRecord
{ 
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth_token}}';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'user_type' , 'token', 'expired_on'], 'required'],
            [['user_id', 'user_type' , 'expired_on'], 'integer'],
            [['token'], 'string', 'max' => 255],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => Yii::t('app', 'ID'),
            'user_type'  => Yii::t('app', 'User Type'),
            'user_id'    => Yii::t('app', 'User ID'),
            'token'      => Yii::t('app', 'Token'),
            'expired_on' => Yii::t('app', 'Expired On')
        ];
    }
    
    public function getRelationCmsUser(){
        return $this->hasOne( User::className() , [ "id" => "user_id" ] )->from( User::tableName() . ' u' );
    }
    
//     public function getRelationAppUser(){
//         return $this->hasOne( AppUser::className() , [ "id" => "user_id" ] )->from( AppUser::tableName() . ' u' );
//     }
    
//     public function getRelationWebUser(){
//         return $this->hasOne( ConsentEmail::className() , [ "id" => "user_id" ] );
//     }
    
    public function generateAuthTokenForWeb( $user ){
        $hash_1 = md5( $user->username );
        $hash_2 = md5( $user->auth_key );
        $hash_3 = md5( $user->created_at );
        $hash_4 = md5( microtime() );
        $hash_5 = md5( "iConsent2" );
        
        $hash_f = [];
        $hash_f[] = hash( 'sha512' , $hash_1 . $hash_2 . $hash_3 . $hash_4 . $hash_5 );
        $hash_f[] = md5( $user->username . microtime() . "iConsent2"  );
        $hash_f[] = md5( $user->auth_key . microtime() . $user->created_at . "iConsent2"  );
        $hash_f[] = md5( "iConsent2-web"  );
        
        return implode( ":" , $hash_f );
    }
    
    public function generateAuthTokenForApp( $user ){
        $hash_1 = md5( $user->name );
        $hash_2 = md5( $user->name . $user->created_at );
        $hash_3 = md5( $user->created_at );
        $hash_4 = md5( microtime() );
        $hash_5 = md5( "iConsent2" );
        
        $hash_f = [];
        $hash_f[] = hash( 'sha512' , $hash_1 . $hash_2 . $hash_3 . $hash_4 . $hash_5 );
        $hash_f[] = md5( $user->name . microtime() . "iConsent2"  );
        $hash_f[] = md5( $user->name . microtime() . $user->created_at . "iConsent2"  );
        $hash_f[] = md5( "iConsent2-app"  );
        
        return implode( ":" , $hash_f );
    }
}
