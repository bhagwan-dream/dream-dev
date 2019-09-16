<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;


/**
 * BetfairApiCricket controller
 */
class BetfairApiHorseRacingController extends Controller
{
    private $apiUrl = 'https://api.betsapi.com/v1/betfair/sb/';
    private $appKey = '6ELoKExfMWm9RWDE';//5bb47187dbecb - ID
    private $sessionToken = '13044-CgPWGpYSAOn7aV';
    
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
     * Get InPlay
     *
     */
    
    public function actionInplay()
    {
        //inplay?sport_id=1&token=YOUR-TOKEN
        $url = $this->apiUrl.'inplay?sport_id=7&token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        return $this->render('inplay' , [ 'data' => $response ]);
        
    }
    
    /**
     * Get Upcoming
     *
     */
    
    public function actionUpcoming()
    {
        //
        $url = $this->apiUrl.'upcoming?sport_id=7&token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        //echo '<pre>';print_r($response);die;
        return $this->render('upcoming' , [ 'data' => $response ]);
        
    }
    
    
    /**
     * Get Exchange
     *
     */
    
    public function actionExchange()
    {
        //https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token=YOUR-TOKEN
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=7&token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        //echo '<pre>';print_r($response);die;
        return $this->render('exchange' , [ 'data' => $response ]);
        
    }
    
    /**
     * Get Timeline
     *
     */
    
    public function actionTimeline()
    {
        //"https://api.betsapi.com/v1/betfair/timeline?token=YOUR_TOKEN&event_id=28667170"
        $eID = $_GET['EVENT_ID'];
        $url = 'https://api.betsapi.com/v1/betfair/timeline?token='.$this->sessionToken.'&event_id='.$eID;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        //echo '<pre>';print_r($response);die;
        return $this->render('timeline' , [ 'data' => $response ]);
        
    }
}
