<?php
use yii\helpers\Url;
/* @var $this yii\web\View */

$this->title = 'BetFair Api';
$baseUrl = Url::base(true);
?>
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
</style>
<div class="site-index">

    <div class="jumbotron">
        <h1>Cricket Exchange</h1>
         <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
    </div>
	<?php
	echo '<pre>';print_r($data);exit;
    
              $marketName = $data->results[0]->markets[0]->market->marketName;
              $marketId = $data->results[0]->markets[0]->market->marketId;
              $totalAvailable = $data->results[0]->markets[0]->market->totalAvailable;
              $totalMatched = $data->results[0]->markets[0]->market->totalMatched;
              $marketStatus = $data->results[0]->markets[0]->market->marketStatus;
              ?>
    <div class="body-content">

        <div class="row">
        <div class="col-lg-4">
            <table>
              <tr>
              	<th>Market Name</th>
                <th>Market Id</th>
                <th>Total Available</th>
                <th>Total Matched</th>
                <th>Market Status</th>
              </tr>
              
              <tr>
              	<td><?php echo $marketName;?></td>
              	<td><?php echo $marketId;?></td>
                <td><?php echo $totalAvailable;?></td>
                <td><?php echo $totalMatched;?></td>
                <td><?php echo $marketStatus;?></td>
                
              </tr>
            </table>
            
            <h2>Runners List</h2>
            <table>
              <tr>
              	<th>No#</th>
                
                <th>Status</th>
                
                <th>Last Price Traded</th>
                <th>Total Matched</th>
                <th>availableToBack</th>
                <th>availableToLay</th>
                <th>tradedVolume</th>
                
              </tr>
              <?php
              //echo '<pre>';print_r($data->results[0]);exit;
              $i = 1;
              foreach ( $data->results[0]->markets[0]->runners as $runners ){
                	echo '<tr>';
                	   echo '<td>'.$i.'</td>';
                	   //echo '<td><a href="place-bet?MARKETID='.$data->marketId.'&SELECTIONID='.$runners->selectionId.'">'.$runners->selectionId.'</td>';
                	   echo '<td>'.$runners->state->status.'</td>';
                	   //echo '<td>'.$runners->adjustmentFactor.'</td>';
                	   if( isset( $runners->state->lastPriceTraded ) ){
                	       echo '<td>'.$runners->state->lastPriceTraded.'</td>';
                	   }else{
                	       echo '<td>no data</td>';
                	   }
                	   if( isset( $runners->totalMatched ) ){
                	       echo '<td>'.$runners->totalMatched.'</td>';
                	   }else{
                	       echo '<td>no data</td>';
                	   }
                	   echo '<td>';
                	   if( isset( $runners->exchange->availableToBack ) && is_array( $runners->exchange->availableToBack ) ){
                	       foreach ( $runners->exchange->availableToBack as $toback ){
                    	       echo 'Price: '.$toback->price;
                	           echo ' | Size: '.$toback->size;
                	           echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	   echo '<td>';
                	   if( isset( $runners->exchange->availableToLay ) && is_array( $runners->exchange->availableToLay ) ){
                	       foreach ( $runners->exchange->availableToLay as $tolay ){
                    	       echo 'Price: '.$tolay->price;
                    	       echo ' | Size: '.$tolay->size;
                    	       echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	   echo '<td>No data</td>';
                    echo '</tr>';
                    
              $i++;
              }
                ?>
              
            </table>
            
        </div>
        </div>

    </div>
</div>
