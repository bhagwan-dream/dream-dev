<?php
namespace api\controllers;

use Yii;
use common\models\Event;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\EventsPlayList;


/**
 * Events controller
 */
class EventsController extends Controller
{
    private $apiUrl = 'https://api.betfair.com/exchange/betting/json-rpc/v1';
    private $appKey = '3sTY3nHndebeguXe';//'HfOFNi39Z4ObuGdZ';//'6ELoKExfMWm9RWDE';
    private $sessionToken = 'HCDQwwVcFUlYcfZOnJxh9ovR0RdHPwxD78AXrZa2p3I=';//'Zia3NmODmQjI8BFMLewpBEbwPVeJNQlq1Ha+hhjkp7s=';//'rX48AbC3hc8xKVCoGIWxwSZF2RPzrqqL6DvA3OxpgA4=';//'7sdQ1p8UMr/r9DMAKOXxVfVCTFIahAJLvL8tjtihnX4=';
    //7sdQ1p8UMr/r9DMAKOXxVfVCTFIahAJLvL8tjtihnX4=
    
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
     * Displays Event List.
     *
     * @return string
     */
    public function actionIndex()
    {
        //die('asd');
        $command = Yii::$app->db->createCommand();

        $data = $this->getAllEventTypes($this->appKey, $this->sessionToken);
        //return $jsonResponse[0]->result;
        //echo '<pre>';print_r($data);exit;
        
        if( !empty( $data ) ){
            
            //Truncate table of event
            
            $truncate = $command->truncateTable( Event::tableName() );
            $truncate->execute();

            //Insert all new data from event api 

            $allEventArr = [];
            foreach( $data as $event ){
                $allEventArr[] = [
                    'event_type_id' => $event->eventType->id,
                    'event_type_name' => $event->eventType->name,
                    'market_count' => $event->marketCount,
                    'status' => 1,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
            }
            
            $query = $command->batchInsert( Event::tableName(), ['event_type_id','event_type_name','market_count','status','created_at','updated_at'], $allEventArr );
            $query->execute();

        }
        $response = [ "status" => 1 , "data" => [ "items" => $allEventArr , "count" => count($allEventArr) ] ];
        return json_encode($response);
        
    }

    
    /**
     * Displays Event Play List Cricket.
     *
     * @return string
     */
    public function actionEventPlayListCricket()
    {
        //echo strtotime('2019-01-22T12:25:04.280Z');die;
        
        $command = Yii::$app->db->createCommand();
        
        //$eventMarketList = $this->getEventMarketList($this->appKey, $this->sessionToken , 4 , 29085747);
        
        $eventList = $this->getEventList($this->appKey, $this->sessionToken , 4);
        //echo '<pre>';print_r($eventList);exit;
        
        $allEventArr = $runnersArr = [];
        
        if( !empty( $eventList ) ){
            
            foreach ( $eventList as $event ){
                $sport_id = 4;
                $event_id = $event->event->id;
                $event_league = $event->event->name;
                $event_time = strtotime($event->event->openDate);
                $play_type = 'UPCOMING';
                if( $event_time < strtotime(date("Y-m-dTH:m:s.sZ")) ){
                    $play_type = 'IN_PLAY';
                }
                
                $eventMarketList = $this->getEventMarketList($this->appKey, $this->sessionToken , $sport_id , $event_id);
                
                if( !empty( $eventMarketList ) ){
                    $market_id = $eventMarketList->marketId;
                    
                    foreach ( $eventMarketList->runners as $runners ){
                        $runnersArr[] = trim($runners->runnerName);
                    }
                    $event_name = $runnersArr[0]. ' -Vs- ' .$runnersArr[1];
                
                    $check = EventsPlayList::findOne(['market_id' => $market_id]);
                    
                    if( $check == null ){
                        
                        $allEventArr[] = [
                            'sport_id' => $sport_id,
                            'event_id' => $event_id,
                            'market_id' => $market_id,
                            'event_name' => $event_name,
                            'event_league' => $event_league,
                            'event_time' => $event_time,
                            'play_type' => $play_type,
                            'status' => 1,
                            'created_at' => time(),
                            'updated_at' => time()
                        ];
                    }
                    
                }
                
            }
            
            //Truncate table of event
            
            //$truncate = $command->truncateTable( Event::tableName() );
            //$truncate->execute();
            
            //Insert all new data from event api
            
            $query = $command->batchInsert( EventsPlayList::tableName(), ['sport_id','event_id','market_id','event_name','event_league','event_time','play_type','status','created_at','updated_at'], $allEventArr );
            $query->execute();
            
        }
        $response = [ "status" => 1 , "message" => "Data saved successfully!" ];
        return json_encode($response);
        
    }
    
    /**
     * getEventList
     */
    private function getEventList($appKey, $sessionToken, $sportId)
    {
        $params = '{"filter":{"eventTypeIds":["' . $sportId . '"]},
                    "marketStartTime":{"from":"' . date('c') . '"},
                    "maxResults":"1",
                    "marketProjection":["RUNNER_DESCRIPTION"]
        }';
        
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listEvents', $params);
        //echo '<pre>';print_r($jsonResponse);exit;
        return $jsonResponse[0]->result;
    }
    
    /**
     * getEventList
     */
    private function getEventMarketList($appKey, $sessionToken, $sportId , $eventId )
    {
        $params = '{"filter":{"eventTypeIds":["' . $sportId . '"],"eventIds":["'. $eventId .'"]},
                    "marketStartTime":{"from":"' . date('c') . '"},
                    "maxResults":"1",
                    "marketProjection":["RUNNER_DESCRIPTION"]
        }';
        
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listMarketCatalogue', $params);
        //echo '<pre>';print_r($jsonResponse);exit;
        return $jsonResponse[0]->result[0];
    }
    
    /**
     * Displays Event Market.
     *
     * @return string
     */
    
    public function actionMarket()
    {
        $eventId = '4';
        $data = $this->getEventMarket($this->appKey, $this->sessionToken , $eventId);
        echo '<pre>';print_r($data);exit;
        $allEventArr = [];
        $allEventArr['market_id'] = $data->marketId;
        $allEventArr['market_name'] = $data->marketName;
        $allEventArr['total_matched'] = $data->totalMatched;
        
        foreach( $data->runners as $runners ){
            $allEventArr['runners'][] = [
                'selection_id' => $runners->selectionId,
                'runner_name' => $runners->runnerName,
                'handicap' => $runners->handicap,
                'sort_priority' => $runners->sortPriority
            ];
        }

        //echo '<pre>';print_r($data);exit;
        $response = [ "status" => 1 , "data" => [ "items" => $allEventArr , "count" => count( $allEventArr['runners'] ) ] ];
        return json_encode($response);
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
        /*$params = '{"filter":{"eventTypeIds":["' . $eventId . '"]},
                    "marketStartTime":{"from":"' . date('c') . '"},
                    "maxResults":"1",
                    "marketProjection":["RUNNER_DESCRIPTION"]
        }';*/
        
        $params = '{"filter":{"eventTypeIds":["' . $eventId . '"],"eventIds":["29079232"]},
                    "marketStartTime":{"from":"' . date('c') . '"},
                    "maxResults":"1",
                    "marketProjection":["RUNNER_DESCRIPTION"]
        }';
        
        /*$params = '{"filter":{"eventTypeIds":["' . $eventId . '"],
                "marketCountries":["GB"],
                "marketTypeCodes":["WIN"],
                "marketStartTime":{"from":"' . date('c') . '"}},
                "sort":"FIRST_TO_START",
                "maxResults":"1",
                "marketProjection":["RUNNER_DESCRIPTION"]}';*/
        //echo $params;exit;
    
        $jsonResponse = $this->sportsApingRequest($appKey, $sessionToken, 'listMarketCatalogue', $params);
        //echo '<pre>';print_r($jsonResponse);exit;
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
