<?php
$url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);
$responseData = json_decode($responseData);

//echo '<pre>';print_r($responseData);die;

$responseArr = [];
$responseArr['inplay']['market'] = [];
$responseArr['upcoming']['market'] = [];

if( !empty($responseData->result)){
    
    foreach ( $responseData->result as $result ){
        
        if( isset( $result->inPlay ) && $result->inPlay == true ){
            
            $marketId = $result->id;
            $eventId = $result->event->id;
            $time = $result->start;
            $status = $result->status;
            $marketName = $result->event->name;
            $totalMatched = $result->matched;//$this->getMatchTotalVal($marketId,$eventId);
            $runnersArr = [];
            
            if( isset($result->runners) && !empty($result->runners) ){
                
                foreach ( $result->runners as $runners ){
                    
                    $back = $lay = [
                        'price' => '-',
                        'size' => ''
                    ];
                    
                    $selectionId = $runners->id;
                    $runnerName = $runners->name;
                    if( isset( $runners->back ) && !empty( $runners->back ) ){
                        $back = [
                            'price' => number_format($runners->back[0]->price , 2),
                            'size' => number_format($runners->back[0]->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                        ];
                    }
                    
                    if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                        $lay = [
                            'price' => number_format($runners->lay[0]->price,2),
                            'size' => number_format($runners->lay[0]->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                        ];
                    }
                    
                    $runnersArr[] = [
                        'selectionId' => $selectionId,
                        'runnerName' => $runnerName,
                        'exchange' => [
                            'back' => $back,
                            'lay' => $lay
                        ]
                    ];
                    
                }
                
            }
            
            $responseArr['inplay']['market'][] = [
                'marketId' => $marketId,
                'eventId' => $eventId,
                'time' => $time,
                'status' => $status,
                'marketName' => $marketName,
                'matched' => $totalMatched,
                'suspended' => 'N',
                'ballRunning' => 'N',
                'runners' => $runnersArr
            ];
            
        }else{
            
            $marketId = $result->id;
            $eventId = $result->event->id;
            $time = $result->start;
            $status = $result->status;
            $marketName = $result->event->name;
            $totalMatched = $result->matched;//$this->getMatchTotalVal($marketId,$eventId);
            $runnersArr = [];
            if( isset($result->runners) && !empty($result->runners) ){
                
                foreach ( $result->runners as $runners ){
                    
                    $back = $lay = [
                        'price' => '-',
                        'size' => ''
                    ];
                    
                    $selectionId = $runners->id;
                    $runnerName = $runners->name;
                    if( isset( $runners->back ) && !empty( $runners->back ) ){
                        $back = [
                            'price' => number_format($runners->back[0]->price , 2),
                            'size' => number_format($runners->back[0]->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                        ];
                    }
                    
                    if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                        $lay = [
                            'price' => number_format($runners->lay[0]->price,2),
                            'size' => number_format($runners->lay[0]->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                        ];
                    }
                    
                    $runnersArr[] = [
                        'selectionId' => $selectionId,
                        'runnerName' => $runnerName,
                        'exchange' => [
                            'back' => $back,
                            'lay' => $lay
                        ]
                    ];
                    
                }
                
            }
            
            $responseArr['upcoming']['market'][] = [
                'marketId' => $marketId,
                'eventId' => $eventId,
                'time' => $time,
                'status' => $status,
                'marketName' => $marketName,
                'matched' => $totalMatched,
                'runners' => $runnersArr
            ];
            
        }
        
    }
    
}

$response = [ "status" => 1 , "data" => [ "items" => $responseArr ] ];

echo json_encode($response);
exit;

?>
