<?php
namespace common\models;

use Yii;
use yii\base\Model;

/**
 * Manual session form
 */
class ManualSessionTestForm extends Model
{
    public $no_yes_val_2;
    public $rate_2;

    private $_manualsession;


    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    protected function getManualsession()
    {
        if ($this->_manualsession === null) {
            $this->_manualsession = User::findByUsername($this->username);
        }

        return $this->_manualsession;
    }
}
