<?php
namespace api\modules\v1\modules\users\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;


/**
 * PlaceBet model
 *
 * @property integer $id
 * @property string $user_id
 * @property string $sport_id
 * @property string $sec_id
 * @property string $event_id
 * @property string $market_id
 * @property string $client_name
 * @property string $master
 * @property string $runner
 * @property string $bet_type
 * @property string $session_type
 * @property string $ip_address
 * @property string $size
 * @property string $win
 * @property string $loss
 * @property string $ccr
 * @property string $bet_status
 * @property string $description
 * @property integer $status
 * @property integer $match_unmatch
 * @property integer $created_at
 * @property integer $updated_at
 */
class PlaceBet extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%place_bet}}';
    }

    public static function getDb() {
        return Yii::$app->db1;
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
            [[ 'user_id' , 'sport_id' , 'sec_id' ,'event_id', 'market_id' ,'market_name','client_name' , 'master' , 'runner' , 'bet_type' , 'session_type' , 'ip_address' , 'price' , 'size' ,'rate', 'win' , 'loss' , 'ccr' , 'bet_status' , 'status' ,'match_unmatch', 'description' ,'created_at' , 'updated_at' ], 'safe'],
        ];
    }
  
}
