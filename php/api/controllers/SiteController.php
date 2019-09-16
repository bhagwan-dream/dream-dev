<?php
namespace api\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;
use common\models\User;
use common\models\ManualSessionTestForm; //added by jayesh
use PhpOffice\PhpSpreadsheet\IOFactory;


/**
 * Site controller
 */
class SiteController extends Controller
{

    public $enableCsrfValidation = false;
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                    'poker-auth' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

	/**
     * Displays homepage.
     *
     * @return string
     */
    public function actionUserDetailIdentity()
    {
        echo '<pre>';print_r($_POST);die('asdasd');

        if( isset($_POST['token']) ){
			$token = strrev($_POST['token']);
			$authCheck = (new \yii\db\Query())
				->select(['user_id'])->from('auth_token')
				->where(['token' => $token ])
				->one();

			if( $authCheck != null ){

				echo '<pre>';print_r($authCheck);die;

				$uid = $authCheck['user_id'];
				$user = (new \yii\db\Query())
					->select('*')->from('user')
					->where(['is_login' => 1 , 'status' => 1 , 'id' => $uid ])
					->one();
				if( $user != null ){

					echo '<pre>';print_r($user);die;

				}

			}

		}
    }

    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionPokerAuth()
    {

        if( Yii::$app->request->post() ){

            $data = Yii::$app->request->post();

            echo '<pre>';print_r($data);die;

            $token = strrev($_POST['token']);
            $authCheck = (new \yii\db\Query())
                ->select(['user_id'])->from('auth_token')
                ->where(['token' => $token ])
                ->one();

            if( $authCheck != null ){

                echo '<pre>';print_r($authCheck);die;

                $uid = $authCheck['user_id'];
                $user = (new \yii\db\Query())
                    ->select('*')->from('user')
                    ->where(['is_login' => 1 , 'status' => 1 , 'id' => $uid ])
                    ->one();
                if( $user != null ){

                    echo '<pre>';print_r($user);die;

                }

            }

        }
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
    
    public function actionCreateUser(){
        $response = [ "status" => 0 , "error" => [ "message" => "Bad Request" ]  ];
        
        $user = new User();
        
        $user->username = 'admin';
        $user->auth_key = 'IV4ZST3Wc0ym4DnkzeZlEv4PaAAjnXao';
        $user->email    = 'a@a.com';
        $user->password = \Yii::$app->security->generatePasswordHash( 'Systra#2016#' );
        $user->status   = 10;
        
        if( $user->validate() ){
            if( $user->save() ){
                $response = [ "status" => 1 , "success" => [ "message" => "User created successfully" ]  ];
            }else{
                $response = [ "status" => 0 , "error" => [ "message" => "User Not Saved!" ]  ];
            }
        }else{
            $response = [ "status" => 0 , "error" => $user->errors ];
        }
    }
    
    public function actionTest(){
        $spreadsheet = IOFactory::load( __DIR__ . '/test.xlsx' );
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        echo '<pre>'; print_r( $sheetData ); exit();  
    }
    
    /**
     * Manual session action.
     *
     * @return string
     */
    public function actionManualsession()
    {
        //Yii::$app->controller->enableCsrfValidation = false;
        $model = new ManualSessionTestForm();
        if ($model->load(Yii::$app->request->post())) {
            echo '<pre>';print_r($model);die;
            //return $this->goBack();
        }else{
            return $this->render('manual_session', [
                'model' => $model,
                'baseurl'=> yii::$app->request->baseUrl
            ]);
        }
    }
}
