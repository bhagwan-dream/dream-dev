<?php
namespace common\controllers;

use common\auth\HttpBearerAuth;
use yii\filters\auth\CompositeAuth;
use common\auth\QueryParamAuth;

/**
 * Auth controller
 */
class wController extends cController
{
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		$behaviors = parent::behaviors();
		
		$behaviors['authenticator'] = [
			'class' => CompositeAuth::className(),
	        'authMethods' => [
	            [ 'class' => QueryParamAuth::className() ],
	        ]
		];
		
		return $behaviors;
	}
}
