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
    background-color:none;
}
.markets_rules{font-size: 10px !important;}
.availableToBack{background: #b9d9f3;}
.availableToLay{background:#f8c9d4; }
.availableToBack .place-a-bet{padding:5px 10px;display: inline-block;}
.availableToBack .place-a-bet:hover{background:#75c2fd;}
.availableToLay .place-a-bet{padding:5px 10px;display: inline-block;}
.availableToLay .place-a-bet:hover{background:#f694aa;}
input.bet-price {width: 100px; padding: 3px 10px;}
input.bet-size {width: 100px; padding: 3px 10px;}
.modal-body p{display: inline-block;margin-right: 20px;}

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
        <h3><?php echo $eventName = $event->name;?></h3>
        <h4><?php echo $openDate = date('F j, Y, g:i a' , strtotime($event->openDate));?></h4>
        
        <h3>Markets</h3>
        <?php 
        $marketsArr = $response->results[0]->markets;
        $a = 1;
        foreach ( $marketsArr as $k=>$markets ){
            if( $k == 0 ){
            $licence = $markets->licence;
            $rules = $licence->rules;
            ?>
            <a style="float: right;" href="#" data-toggle="modal" data-target="#Rules-<?php echo $a;?>">Rules</a>
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
            <div>
            Total Matched: <?php echo $totalMatched;?><br>
            Total Available: <?php echo $totalAvailable;?>
            </div>
        	
            <?php 
            $runnersArr = $markets->runners;
            $market_id = $markets->marketId;
            ?>
    		<h4>Market Id : <?php echo $markets->marketId;?></h4>
            <table>
              <tr>
              	<th>No#</th>
              	<th>Selection</th>
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
                	   
                	   //echo '<td><a href="place-bet?MARKETID='.$data->marketId.'&SELECTIONID='.$runners->selectionId.'">'.$runners->selectionId.'</td>';
                	   $description = $runners->description;
                	   echo '<td>'.$description->runnerName.'</td>';
                	   $state = $runners->state;
                	   if( isset( $state->lastPriceTraded ) ){
                	       echo '<td>'.$state->lastPriceTraded.'</td>';
                	   }else{
                	       echo '<td>No Data</td>';
                	   }
                	   
                	   echo '<td class="availableToBack">';
                	   if( isset( $runners->exchange->availableToBack ) && is_array( $runners->exchange->availableToBack ) ){
                	       foreach ( $runners->exchange->availableToBack as $toback ){
                	           echo '<a href="#" data-rname="'.$description->runnerName.'" data-class="availableToBack" class="place-a-bet" data-toggle="modal" data-target="#PlaceBet" data-sid="'.$selection_id.'" data-price="'.$toback->price.'" data-mid="'.$market_id.'">Price: '.$toback->price;
                	           echo ' | Size: '.$toback->size.'</a>';
                	           echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	   echo '<td class="availableToLay">';
                	   if( isset( $runners->exchange->availableToLay ) && is_array( $runners->exchange->availableToLay ) ){
                	       foreach ( $runners->exchange->availableToLay as $tolay ){
                	           echo '<a href="#" data-rname="'.$description->runnerName.'" data-class="availableToLay" class="place-a-bet" data-toggle="modal" data-target="#PlaceBet" data-sid="'.$selection_id.'" data-price="'.$tolay->price.'" data-mid="'.$market_id.'">Price: '.$tolay->price;
                	           echo ' | Size: '.$tolay->size.'</a>';
                    	       echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	                   	   
                    echo '</tr>';
                    
              $i++;
              }
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
          <h4 class="modal-title">Place Bet <span></span></h4>
        </div>
        
        <div class="modal-body">
          <p>Price: <input type="number" min="1" class="bet-price" name="price" value=""></p>
          <p>Stake: <input type="number" min="1" class="bet-size" name="size" value=""></p>
          <input type="hidden" class="bet-mid" name="mid" value="">
          <input type="hidden" class="bet-sid" name="sid" value="">
          <p>Profit: <span class="bet-profit">0.00</span></p>
          <p>Liability: <span class="bet-liability">0.00</span></p>
        </div>
        
        <div class="modal-footer">
          <button type="button" id="confrim" class="btn btn-success">Confrim</button>
          <button type="button" id="dismiss-modal" class="btn btn-warning" data-dismiss="modal">Cancel</button>
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
			$('#PlaceBet .modal-content').removeClass('availableToLay');
			$('#PlaceBet .modal-content').removeClass('availableToBack');
			$('#PlaceBet .modal-content').addClass($(this).data('class'));
			$('#PlaceBet .modal-content .modal-title span').text(': '+$(this).data('rname'));
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