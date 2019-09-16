<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use api\modules\v1\modules\users\models\UserRegion;
use api\modules\v1\modules\users\models\UserCountry;
use api\modules\v1\modules\users\models\AuthToken;
use api\modules\v1\modules\instances\model\AppUser;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $email
 * @property string $mobile
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $password write-only password
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 2;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
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

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [ ['name' , 'profit_loss'], 'required'],
            [ [ 'commission' , 'balance','role','is_password_updated','is_login' , 'remark'], 'safe'],
            ['username', 'trim'],
            ['username', 'required'],
            ['username', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This username has already been taken.'],
            ['username', 'string', 'min' => 2, 'max' => 255],
            
            /*['email', 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This email address has already been taken.'],
            
            
            ['mobile', 'trim'],
            ['mobile', 'required'],
            ['mobile', 'string', 'max' => 20],
            ['mobile', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This mobile address has already been taken.'],
            */
            
            ['password_hash', 'required'],
            ['password_hash', 'string', 'min' => 6],
            
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE,self::STATUS_DELETED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $type = static::findUserTypeByAccessToken( $token );
        if( $type == "cms" ){
            $model_auth_token = AuthToken::find()
                ->from( AuthToken::tableName() . ' t' )
                ->where( [ "token" => $token , "user_type" => 1 ] )
                ->andWhere( [ ">" , "expired_on" , time() ] )
                ->joinWith( [ "relationCmsUser" ] )
                ->one();

            if( $model_auth_token != null ){
                if( isset( $model_auth_token[ "relationCmsUser" ] ) ){
                    return $model_auth_token->relationCmsUser;
                }
            }
        }else if( $type == "app" ){
            $model_auth_token = AuthToken::find()
                ->where( [ "token" => $token , "user_type" => 2 ] )
                ->orWhere( [ "token" => $token , "user_type" => 12 ] )
                ->with( [ "relationAppUser" ] )
                ->one();
            
            if( $model_auth_token != null ){
                if( isset( $model_auth_token[ "relationAppUser" ] ) ){
                    return AppUser::findIdentityByAccessToken($token);
                }
            }
        }else if( $type == "web" ){
            $model_auth_token = AuthToken::find()
            ->where( [ "token" => $token , "user_type" => 3 ] )
            ->with( [ "relationWebUser" ] )
            ->one();
            
            if( $model_auth_token != null ){
                if( isset( $model_auth_token[ "relationWebUser" ] ) ){
                    if( isset( $model_auth_token[ "relationWebUser" ][ "is_submitted" ] ) && 
                        $model_auth_token[ "relationWebUser" ][ "is_submitted" ] == 0 ){
                        return $model_auth_token->relationWebUser;
                    }
                }
            }
        }else{
            
        }
        
        return null;
    }
    
    private static function findUserTypeByAccessToken( $token ){
        $type = "";
        
        $arr = explode( ":" , $token );
        
        if( count( $arr ) > 1 ){
            $pType = end( $arr );
            switch ( $pType ){
                case md5( "iConsent2-cms"  ) : $type = "cms"; break;
                case md5( "iConsent2-app"  ) : $type = "app"; break;
                case md5( "iConsent2-web"  ) : $type = "web"; break;
                default: break;
            }
        }
        
        return $type;
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }
    
    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }
    
    public function getRelationUserRegion(){
        return $this->hasOne( UserRegion::className() , [ 'user_id' => 'id' ] );
    }
    
    public function getRelationUserCountry(){
        return $this->hasOne( UserCountry::className() , [ 'user_id' => 'id' ] )->from( UserCountry::tableName() . ' tuc' );
    }
}
