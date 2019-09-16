<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use common\models\PlaceBet;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;


/**
 * BetfairApiCricket controller
 */
class BetfairApiCricketController extends Controller
{
    public $enableCsrfValidation = false;
    
    private $apiUrl = 'https://api.betsapi.com/v1/betfair/sb/';
    private $appKey = '6ELoKExfMWm9RWDE';//5bb742ad1e75f - ID
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
        $url = $this->apiUrl.'inplay?sport_id=4&token='.$this->sessionToken;
        
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
        $url = $this->apiUrl.'upcoming?sport_id=4&token='.$this->sessionToken;
        
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
        //"https://api.betsapi.com/v1/betfair/ex/event?token=YOUR_TOKEN&event_id=28563496
        $eID = $_GET['EVENT_ID'];
        $url = 'https://api.betsapi.com/v1/betfair/ex/event?token='.$this->sessionToken.'&event_id='.$eID;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //$response = curl_exec($ch);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        //echo $response;die;
        //echo '<pre>';print_r($response);die;
        /*
        echo '<h3>Event</h3>';
        $event = $response->results[0]->event;
        echo '<pre>';print_r($event);echo '</pre>';
        echo '==========================================================';
        
        echo '<h3>Timeline</h3>';
        $timeline = $response->results[0]->timeline;
        echo '<pre>';print_r($timeline);echo '</pre>';
        echo '==========================================================';
        
        echo '<h3>Markets</h3>';
        $marketsArr = $response->results[0]->markets;
        
        foreach ( $marketsArr as $markets ){
            
            echo '<h4>Licence</h4>';
            $licence = $markets->licence;
            echo '<pre>';print_r($licence);echo '</pre>';
            echo '=============================================';
            
            echo '<h4>Rates</h4>';
            $rates = $markets->rates;
            echo '<pre>';print_r($rates);echo '</pre>';
            echo '=============================================';
            
            echo '<h4>State</h4>';
            $state = $markets->state;
            echo '<pre>';print_r($state);echo '</pre>';
            echo '=============================================';
            
            echo '<h4>Description</h4>';
            $description = $markets->description;
            echo '<pre>';print_r($description);echo '</pre>';
            echo '==============================================';
            
            echo '<h4>Runners ( Market Id : '.$markets->marketId.' )</h4>';
            $runnersArr = $markets->runners;
            
            foreach ( $runnersArr as $runners ){
                
                echo '<h4>Exchange</h4>';
                $exchange = $runners->exchange;
                if( isset($exchange->availableToBack) ){
                    echo '<h5>availableToBack</h5>';
                    $availableToBack = $exchange->availableToBack;
                    echo '<pre>';print_r($availableToBack);echo '</pre>';
                    echo '=============================';
                }
                if( isset($exchange->availableToLay) ){
                    echo '<h5>availableToLay</h5>';
                    $availableToLay = $exchange->availableToLay;
                    echo '<pre>';print_r($availableToLay);echo '</pre>';
                    echo '=============================';
                }
                echo '==============================================';
                
                echo '<h4>Description</h4>';
                $description = $runners->description;
                echo '<pre>';print_r($description);echo '</pre>';
                echo '===============================================';
                
                echo '<h4>State</h4>';
                $state = $runners->state;
                echo '<pre>';print_r($state);echo '</pre>';
                echo '================================================';
                
            }
            
            echo '=====================================================';
            
            echo '<h4>Market</h4>';
            $market = $markets->market;
            echo '<pre>';print_r($market);echo '</pre>';
            echo '=====================================================';
            
        }
        //die;
         * 
         */
        return $this->render('exchange' , [ 'response' => $response ]);
        
    }
    
    public function actionPlaceBet()
    {
        //die('asdasdasd');
        $data['PlaceBet'] = Yii::$app->request->post();
        $model = new PlaceBet();
        
        if ($model->load($data)) {
            
            $model->user_id = 1;
            $model->status = 1;
            $model->created_at = $model->updated_at = time();
            
            if( $model->save() ){
                echo '1';
            }else{
                echo '<pre>';print_r($model->getErrors());die;
                echo '2';
            }
            
        }else{
            echo '2';
        }
        
        
        
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
