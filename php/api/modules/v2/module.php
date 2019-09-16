<?php

namespace api\modules\v2;

/**
 * v2 module definition class
 */
class module extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = 'api\modules\v2\controllers';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
        $this->modules = [
            
            'events' => [
                'class' => 'api\modules\v2\modules\events\module',
            ],
            'users' => [
                'class' => 'api\modules\v2\modules\users\module',
            ]
            
        ];
        // custom initialization code goes here
    }
}
