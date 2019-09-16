<?php
use yii\helpers\Url;
/* @var $this yii\web\View */

$this->title = 'BetFair Api';
$baseUrl = Url::base(true);
//28922245
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>BetFair Api</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</head>
<body>
<style>
table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
    width: 100%;
}

td, th {
    border: 1px solid #dddddd;
    text-align: left;
    padding: 8px;
}

tr:nth-child(even) {
    background-color: #dddddd;
}
.markets_rules{font-size: 10px !important;}
</style>
<div class="container">

	<h2>Cricket Exchange</h2>
    <a href="<?php echo dirname(dirname($baseUrl));?>/php/api/betfair-api-cricket/inplay" >Back To InPlay</a>
    
    <div class="row">
        <div class="col-lg-12">
        
        <?php 
        $event = $response->results[0]->event;
        
        if( isset($response->results[0]->timeline) ){
            $timeline = $response->results[0]->timeline;
            
            $currentDay = isset($timeline->currentDay) == '' ? 'Notset' : $timeline->currentDay;
            $wickets = $wickets2 = $overs = $overs2 = $runs = $runs2 = '-';
            if( isset($timeline->score->away->inning1) ){
                $wickets = $timeline->score->home->inning1->wickets;
                $overs = $timeline->score->home->inning1->overs;
                $runs = $timeline->score->home->inning1->runs;
            }
            
            $teamName1 = $timeline->score->home->name;
            $teamName2 = $timeline->score->away->name;
            
            if( isset($timeline->score->away->inning1) ){
                $wickets2 = $timeline->score->away->inning1->wickets;
                $overs2 = $timeline->score->away->inning1->overs;
                $runs2 = $timeline->score->away->inning1->runs;
            }
            
            
            $matchType = isset($timeline->matchType) == '' ? 'Notset' : $timeline->matchType;
        }else{
            $timeline = 'Nodata';
        }
        
        ?>
        <h3><?php echo $matchType.' Match : '.$teamName1.' VS '.$teamName2.'<br>';?></h3>
        <div>
        <?php 
          	if( isset($response->results[0]->timeline) ){
          	 //echo $matchType.' Match : '.$teamName1.' VS '.$teamName2.'<br>';
          	 echo $teamName1.' : '.$runs.'/'.$wickets.' ( '.$overs.' ) '.$currentDay.' day<br>';
          	 echo $teamName2.' : '.$runs2.'/'.$wickets2.' ( '.$overs2.' ) '.$currentDay.' day';
          	}else{
          	    echo $timeline;
          	}
      	?>
        </div>
        <table>
            <tr>
                <th>Event</th>
                <th>Timeline</th>
            </tr>
            <tr>
                <td>
                <?php 
                    echo 'Event Id : '.$eventId = $event->eventId.'<br>';
                    echo 'Event Name : '.$eventName = $event->name.'<br>';
                    echo 'Event Date : '.$openDate = $event->openDate;
                ?></td>
              	<td>
              	<?php 
              	if( isset($response->results[0]->timeline) ){
              	 echo $matchType.' Match : '.$teamName1.' VS '.$teamName2.'<br>';
              	 echo $teamName1.' : '.$runs.'/'.$wickets.' ( '.$overs.' ) '.$currentDay.' day<br>';
              	 echo $teamName2.' : '.$runs2.'/'.$wickets2.' ( '.$overs2.' ) '.$currentDay.' day';
              	}else{
              	    echo $timeline;
              	}
              	 ?></td>  
            </tr>
        </table>
        <h3>Markets</h3>
        <?php 
        $marketsArr = $response->results[0]->markets;
        $a = 1;
        foreach ( $marketsArr as $markets ){
            
            $licence = $markets->licence;
            $rules = $licence->rules;
            ?>
            <a href="#" data-toggle="modal" data-target="#Rules-<?php echo $a;?>">Rules</a>
            <!-- Modal -->
              <div class="modal fade" id="Rules-<?php echo $a;?>" role="dialog">
                <div class="modal-dialog">
                  <!-- Modal content-->
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                      <h4 class="modal-title">Rules</h4>
                    </div>
                    <div class="modal-body">
                      <?php echo $rules;?>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
            <!-- End Modal -->
            
            <h4>Rates</h4>
            <?php 
            $rates = $markets->rates;
            $marketBaseRate = $rates->marketBaseRate;
            $discountAllowed = $rates->discountAllowed;
            ?>
            <table>
                <tr>
                    <th>Market Base Rate</th>
                    <th>Discount Allowed</th>
                </tr>
                <tr>
                    <td><?php echo $marketBaseRate;?></td>
                    <td><?php echo $discountAllowed;?></td>
                </tr>
            </table>
            <h4>State</h4>
            <?php 
            $state = $markets->state;
            $totalMatched = $lastMatchTime = $status = $version = $totalAvailable = '';
            if( isset($state->totalMatched) )
                $totalMatched = $state->totalMatched;
            if( isset($state->lastMatchTime) )  
                $lastMatchTime = $state->lastMatchTime;
            if( isset($state->lastMatchTime) )
                $status = $state->status;
            if( isset($state->version) )
                $version = $state->version;
            if( isset($state->totalAvailable) )
                $totalAvailable = $state->totalAvailable;
            ?>
        	<table>
                <tr>
                    <th>Total Matched</th>
                    <th>Last Match Time</th>
                    <th>Status</th>
                    <th>Version</th>
                    <th>Total Available</th>
                </tr>
                <tr>
                    <td><?php echo $totalMatched;?></td>
                  	<td><?php echo $lastMatchTime;?></td>
                    <td><?php echo $status;?></td>
                    <td><?php echo $version;?></td>
                    <td><?php echo $totalAvailable;?></td>   
                </tr>
            </table>
    		<h4>Market</h4>
            <?php 
            $market = $markets->market;
            ?>
    		<table>
            <tr>
                <th>Product Type</th>
                <th>Market Time</th>
                <th>Market Name</th>
                <th>Top Level Event Id</th>
                <th>Event Type Id</th>
                <th>Event Id</th>
                <th>Associated Markets</th>
                <th>Number Of Active Runners</th>
                <th>Market Id</th>
                <th>Total Matched</th>
                <th>Market Status</th>
                <th>Upper Level EventId</th>
                <th>Number Of Winners</th>
                <th>Total Available</th>
                <th>Number Of Runners</th>
            </tr>
        
            <tr>
                <td><?php echo $market->productType;?></td>
              	<td><?php echo $market->marketTime;?></td>
                <td><?php echo $market->marketName;?></td>
                <td><?php echo $market->topLevelEventId;?></td>
                <td><?php echo $market->eventTypeId;?></td>
                <td><?php echo $market->eventId;?></td>
                <td><?php 
                if( isset( $market->associatedMarkets ) && is_array( $market->associatedMarkets ) ){
                    foreach ( $market->associatedMarkets as $assoMarket ){
                        echo 'SportsbookMarketId: '.$assoMarket->sportsbookMarketId;
                        echo ' | ';//EventId: '.$assoMarket->eventId
                        //echo ' | EventTypeId: '.$assoMarket->EventTypeId;
                        echo '<br>';
                    }
                }
                ?></td>
                <td><?php echo $market->numberOfActiveRunners;?></td>
                <td><?php echo $market->marketId;?></td>
                <td><?php echo $market->totalMatched;?></td>
                <td><?php echo $market->marketStatus;?></td>
                <td><?php echo $market->upperLevelEventId;?></td>
                <td><?php echo $market->numberOfWinners;?></td>
                <td><?php echo $market->totalAvailable;?></td>
                <td><?php echo $market->numberOfRunners;?></td>
            </tr>
            </table>
            <?php 
            $runnersArr = $markets->runners;
            $market_id = $markets->marketId;
            ?>
    		<h4>Runners ( Market Id : <?php echo $markets->marketId;?> )</h4>
            <table>
              <tr>
              	<th>No#</th>
              	<th>Selection Id</th>
                <th>Description</th>
                <th>Last Price Traded</th>
                <th>Available To Back</th>
                <th>Available To Lay</th>
                
              </tr>
              <?php
              $i = 1;
              foreach ( $runnersArr as $runners ){
                  
                  $selection_id = $runners->selectionId;
                	echo '<tr>';
                	   echo '<td>'.$i.'</td>';
                	   echo '<td>'.$runners->selectionId.'</td>';
                	   //echo '<td><a href="place-bet?MARKETID='.$data->marketId.'&SELECTIONID='.$runners->selectionId.'">'.$runners->selectionId.'</td>';
                	   $description = $runners->description;
                	   echo '<td>RunnerName : '.$description->runnerName.' | RunnerID : '.$description->metadata->runnerId.'</td>';
                	   $state = $runners->state;
                	   if( isset( $state->lastPriceTraded ) ){
                	       echo '<td>'.$state->lastPriceTraded.'</td>';
                	   }else{
                	       echo '<td>No Data</td>';
                	   }
                	   
                	   echo '<td>';
                	   if( isset( $runners->exchange->availableToBack ) && is_array( $runners->exchange->availableToBack ) ){
                	       foreach ( $runners->exchange->availableToBack as $toback ){
                	           echo '<a href="#" class="place-a-bet" data-toggle="modal" data-target="#PlaceBet" data-sid="'.$selection_id.'" data-price="'.$toback->price.'" data-mid="'.$market_id.'">Price: '.$toback->price.'</a>';
                	           echo ' | Size: '.$toback->size;
                	           echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	   echo '<td>';
                	   if( isset( $runners->exchange->availableToLay ) && is_array( $runners->exchange->availableToLay ) ){
                	       foreach ( $runners->exchange->availableToLay as $tolay ){
                	           echo '<a href="#" class="place-a-bet" data-toggle="modal" data-target="#PlaceBet" data-sid="'.$selection_id.'" data-price="'.$tolay->price.'" data-mid="'.$market_id.'">Price: '.$tolay->price.'</a>';
                    	       echo ' | Size: '.$tolay->size;
                    	       echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	                   	   
                    echo '</tr>';
                    
              $i++;
              }
                ?>
            </table>
    		
<?php $a++;} ?>
	</div>
</div>
<!-- Modal -->
  <div class="modal fade" id="PlaceBet" role="dialog">
    <div class="modal-dialog">
      <!-- Modal content-->
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Place Bet</h4>
        </div>
        
        <div class="modal-body">
          Price: <input type="number" min="1" class="bet-price" name="price" value="">
          Stake: <input type="number" min="1" class="bet-size" name="size" value="">
          <input type="hidden" class="bet-mid" name="mid" value="">
          <input type="hidden" class="bet-sid" name="sid" value="">
          <br>
          Profit: <span class="bet-profit">0.00</span>
          <br>
          Liability: <span class="bet-liability">0.00</span>
        </div>
        
        <div class="modal-footer">
          <button type="button" id="confrim" class="btn btn-info">Confrim</button>
          <button type="button" id="dismiss-modal" class="btn btn-default" data-dismiss="modal">Cancle</button>
        </div>
        
      </div>
    </div>
  </div>
<!-- End Modal -->
  
  </div>
  </body>
  <script type="text/javascript">
  	$( document ).ready(function() {
	    $( document ).on( 'click' , '.place-a-bet' , function() {
			var mid = $(this).data('mid');
			var sid = $(this).data('sid');
			var price = $(this).data('price');
			$('#PlaceBet .bet-price').val(price);
			$('#PlaceBet .bet-sid').val(sid);
			$('#PlaceBet .bet-mid').val(price);
		});

	    $( document ).on( 'change' , '.bet-size' , function() {
			var price = $('#PlaceBet .bet-price').val();
			$('#PlaceBet .bet-profit').text(price*$(this).val());
			$('#PlaceBet .bet-liability').text(price);
		});

	    $(document).on( 'click' , '#confrim' ,function(){
		    var tmp = $('#PlaceBet-form').serializeArray();
		    
		    var data = {
		    		price : $('#PlaceBet .bet-price').val(),
		    		size : $('#PlaceBet .bet-size').val(),
		    		sec_id : $('#PlaceBet .bet-sid').val(),
		    		market_id : $('#PlaceBet .bet-mid').val(),
				    };
		    //console.log(data);
			$.ajax({
			    type : 'POST',
			    dataType: 'json',
			    url : '<?php echo $baseUrl.'/betfair-api-cricket/place-bet'; ?>',
			    data : data,	
			    success:function(res){
				    if( res == 1 ){
			    		$('#dismiss-modal').click();
				    }else{
				    	$('#dismiss-modal').click();
					}
				},
				error:function(e){
					console.log(e);
				}
			});
		});

	});
  </script>
</html>