<?php
$runnersArr = $marketArr = [];
if( null != $_GET['id'] ){
    $eId = $_GET['id'];
    
    //CODE for live call api
    $url = 'http://odds.kheloindia.bet/getodds.php?event_id=4';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $responseData = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($responseData);
    
    //echo '<pre>';print_r($responseData);die;
    if( !empty( $responseData->result ) && !empty( $responseData->result ) ){
        
        foreach ( $responseData->result as $result ){
            
            $marketId = $result->id;
            $eventId = $result->event->id;
            $title = $result->event->name;
            $time = $result->start;
            if( $eId == $result->event->id ){
                foreach ( $result->runners as $runners ){
                    
                    $back = $lay = [];
                    
                    $selectionId = $runners->id;
                    $runnerName = $runners->name;
                    if( isset( $runners->back ) && !empty( $runners->back ) ){
                        foreach ( $runners->back as $backArr ){
                            $back[] = [
                                'price' => number_format($backArr->price , 2),
                                'size' => number_format($backArr->size , 2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                            ];
                        }
                    }
                    
                    if( isset( $runners->lay ) && !empty( $runners->lay ) ){
                        foreach ( $runners->lay as $layArr ){
                            $lay[] = [
                                'price' => number_format($layArr->price,2),
                                'size' => number_format($layArr->size,2),//$this->getSizeTotalVal($marketId,$eventId,$selectionId),
                            ];
                        }
                    }
                    
                    $runnersArr[] = [
                        'selectionId' => $selectionId,
                        'runnerName' => $runnerName,
                        'profit_loss' => '',//$this->getProfitLossOnBet($eventId,$runnerName,'match_odd'),
                        'exchange' => [
                            'back' => $back,
                            'lay' => $lay
                        ]
                    ];
                    
                }
                
                $marketArr[] = [
                    'title' => $title,
                    'marketId' => $marketId,
                    'eventId' => $eventId,
                    'suspended' => 'N',
                    'ballRunning' => 'N',
                    'time' => $time,
                    'marketName'=>'Match Odds',
                    'matched' => '',//$this->getMatchTotalVal($marketId,$eventId),
                    'runners' => $runnersArr,
                ];
            }
        }
    }
    
}

$response = [ "status" => 1 , "data" => [ "items" => $marketArr ] ];

echo json_encode($response);
exit;
?>
