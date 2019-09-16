<?php

namespace api\modules\v1;

/**
 * v1 module definition class
 */
class module extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = 'api\modules\v1\controllers';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
        $this->modules = [
            
            'users' => [
                'class' => 'api\modules\v1\modules\users\module',
            ],
	        'events' => [
                'class' => 'api\modules\v1\modules\events\module',
            ],
            'chips' => [
                'class' => 'api\modules\v1\modules\chips\module',
            ],
            'setting' => [
                'class' => 'api\modules\v1\modules\setting\module',
            ]
            
        ];
        // custom initialization code goes here
    }
}
