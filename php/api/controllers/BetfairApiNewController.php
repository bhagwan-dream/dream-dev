<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;


/**
 * BetfairApi controller
 */
class BetfairApiNewController extends Controller
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
     * Get All Event List
     * 
     */

    public function actionAllEvent()
    {
        $url = $this->apiUrl.'event?token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /*curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:',
            'X-Application: ' . $appKey,
            'X-Authentication: ' . $sessionToken,
            'Accept: application/json',
            'Content-Type: application/json'
        ));
        
        $postData =
        '[{ "jsonrpc": "2.0", "method": "SportsAPING/v1.0/' . $operation . '", "params" :' . $params . ', "id": 1}]';
        
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);*/
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        echo '<pre>';print_r($response);die;
        
        if (isset($response[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode($response);
            exit;
        } else {
            echo '<pre>';print_r($response);die;
        }
        
    }

    /**
     * Get All Event List InPlay
     *
     */
    
    public function actionAllEventInplay()
    {
        //inplay?sport_id=1&token=YOUR-TOKEN
        $url = $this->apiUrl.'inplay?sport_id=4&token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /*curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:',
         'X-Application: ' . $appKey,
         'X-Authentication: ' . $sessionToken,
         'Accept: application/json',
         'Content-Type: application/json'
         ));
        
         $postData =
         '[{ "jsonrpc": "2.0", "method": "SportsAPING/v1.0/' . $operation . '", "params" :' . $params . ', "id": 1}]';
         
         
         curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);*/
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        echo '<pre>';print_r($response);die;
        
        if (isset($response[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode($response);
            exit;
        } else {
            echo '<pre>';print_r($response);die;
        }
        
    }
    
    /**
     * Get All Event List Exchange
     *
     */
    
    public function actionAllEventExchange()
    {
        //https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token=YOUR-TOKEN
        $url = 'https://api.betsapi.com/v1/betfair/ex/inplay?sport_id=4&token='.$this->sessionToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        echo '<pre>';print_r($response);die;
        
        if (isset($response[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode($response);
            exit;
        } else {
            echo '<pre>';print_r($response);die;
        }
        
    }
    
    /**
     * Get All Event List Exchange
     *
     */
    
    public function actionAllEventTimeline()
    {
        //"https://api.betsapi.com/v1/betfair/timeline?token=YOUR_TOKEN&event_id=28667170"
        $url = 'https://api.betsapi.com/v1/betfair/timeline?token='.$this->sessionToken.'&event_id=28934416';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        echo '<pre>';print_r($response);die;
        
        if (isset($response[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode($response);
            exit;
        } else {
            echo '<pre>';print_r($response);die;
        }
        
    }
    
    /**
     * Get Event Market
     * 
     */

    public function actionEventMarket()
    {
        $eventId = '7';
        $data = $this->getEventMarket($this->appKey, $this->sessionToken , $eventId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('event_market' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * Get Market Book
     * 
     */

    public function actionMarketBook()
    {
        //$marketId = '1.147986863';
        if( isset( $_GET['MARKETID'] ) && !empty($_GET['MARKETID']) ){
            $marketId = $_GET['MARKETID'];
        }else{
            return $this->redirect('event-market');
        }
        
        $data = $this->getMarketBook($this->appKey, $this->sessionToken , $marketId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('market_book' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * Place Bet
     * 
     */

    public function actionPlaceBet()
    {
        if( isset( $_GET['MARKETID'] ) && !empty($_GET['MARKETID']) 
            && isset( $_GET['SELECTIONID'] ) && !empty($_GET['SELECTIONID']) ){
            $marketId = $_GET['MARKETID'];
            $selectionId = $_GET['SELECTIONID'];
        }else{
            return $this->redirect('event-market');
        }
        
        $data = $this->placeBet($this->appKey, $this->sessionToken , $marketId , $selectionId);
        //echo '<pre>';print_r($data);exit;
        return $this->render('place_bet' , [ 'data' => $data ]);
        //return $jsonResponse[0]->result;
    }

    /**
     * getAllEventTypes
     */

    private function getAllEventTypes($appKey, $sessionToken)
    {
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listEventTypes', '{"filter":{}}');
        //return $jsonResponse[0]->result;

        if (isset($jsonResponse[0]->error)) {
            echo 'Call to api-ng failed: ' . "\n";
            echo  'Response: ' . json_encode( $jsonResponse[0]->error );
            exit(-1);
        } else {
            return $jsonResponse[0]->result;
        }
    }

    /**
     * getEventMarket
     */

    private function getEventMarket($appKey, $sessionToken, $eventId)
    {
        $params = '{"filter":{"eventTypeIds":["' . $eventId . '"],
                "marketCountries":["GB"],
                "marketTypeCodes":["WIN"],
                "marketStartTime":{"from":"' . date('c') . '"}},
                "sort":"FIRST_TO_START",
                "maxResults":"1",
                "marketProjection":["RUNNER_DESCRIPTION"]}';
        //echo $params;exit;
    
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listMarketCatalogue', $params);
    
        return $jsonResponse[0]->result[0];
    }

    /**
     * getMarketBook
     */

    private function getMarketBook($appKey, $sessionToken, $marketId)
    {
        $params = '{"marketIds":["' . $marketId . '"], "priceProjection":{"priceData":["EX_BEST_OFFERS"]}}';
        //echo $params;exit;
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listMarketBook', $params);
    
        return $jsonResponse[0]->result[0];
    }

    /**
     * placeBet
     */

    private function placeBet($appKey, $sessionToken, $marketId, $selectionId)
    {
        $params = '{"marketId":"' . $marketId . '",
                    "instructions":
                         [{"selectionId":"' . $selectionId . '",
                           "handicap":"0",
                           "side":"BACK",
                           "orderType":
                           "LIMIT",
                           "limitOrder":{"size":"1",
                                        "price":"25",
                                        "persistenceType":"LAPSE"}
                           }], "customerRef":"fsdf"}';
        //echo $params;exit;

        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'placeOrders', $params);
     
        return $jsonResponse[0]->result;
     
    }

    /**
     * sportsApingRequest
     */

    private function sportsApingRequest($appKey, $sessionToken, $operation, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:',
            'X-Application: ' . $appKey,
            'X-Authentication: ' . $sessionToken,
            'Accept: application/json',
            'Content-Type: application/json'
        ));
    
        $postData =
            '[{ "jsonrpc": "2.0", "method": "SportsAPING/v1.0/' . $operation . '", "params" :' . $params . ', "id": 1}]';
    
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        return $response;
        //if (isset($response[0]->error)) {
            //echo 'Call to api-ng failed: ' . "\n";
            //echo  'Response: ' . json_encode($response);
            //exit(-1);
        //} else {
            //return $response;
        //}
 
    }
}
