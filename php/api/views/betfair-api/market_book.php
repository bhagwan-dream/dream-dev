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
        <h1>Event: Horse Racing</h1>
         <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
    </div>

    <div class="body-content">

        <div class="row">
        <div class="col-lg-4">
            <table>
              <tr>
              	<th>Market Id</th>
                <th>Status</th>
                <th>Number Of Winners</th>
                <th>Number Of Runners</th>
                <th>Number Of Active Runners</th>
                <th>Last Match Time</th>
                <th>Total Matched</th>
                <th>Total Available</th>
              </tr>
              
              <tr>
              	<td><?php echo $data->marketId;?></td>
                <td><?php echo $data->status;?></td>
                <td><?php echo $data->numberOfWinners;?></td>
                <td><?php echo $data->numberOfRunners;?></td>
                <td><?php echo $data->numberOfActiveRunners;?></td>
                <td><?php echo $data->lastMatchTime;?></td>
                <td><?php echo $data->totalMatched;?></td>
                <td><?php echo $data->totalAvailable;?></td>
              </tr>
            </table>
            
            <h2>Runners List</h2>
            <table>
              <tr>
              	<th>No#</th>
                <th>Selection Id</th>
                <th>Status</th>
                <th>Adjustment Factor</th>
                <th>Last Price Traded</th>
                <th>Total Matched</th>
                <th>availableToBack</th>
                <th>availableToLay</th>
                <th>tradedVolume</th>
                
              </tr>
              <?php
              //echo '<pre>';print_r($data->runners);exit;
              $i = 1;
              foreach ( $data->runners as $runners ){
                	echo '<tr>';
                	   echo '<td>'.$i.'</td>';
                	   echo '<td><a href="place-bet?MARKETID='.$data->marketId.'&SELECTIONID='.$runners->selectionId.'">'.$runners->selectionId.'</td>';
                	   echo '<td>'.$runners->status.'</td>';
                	   echo '<td>'.$runners->adjustmentFactor.'</td>';
                	   if( isset( $runners->lastPriceTraded ) ){
                	       echo '<td>'.$runners->lastPriceTraded.'</td>';
                	   }else{
                	       echo '<td>no data</td>';
                	   }
                	   if( isset( $runners->totalMatched ) ){
                	       echo '<td>'.$runners->totalMatched.'</td>';
                	   }else{
                	       echo '<td>no data</td>';
                	   }
                	   echo '<td>';
                	   if( isset( $runners->ex->availableToBack ) && is_array( $runners->ex->availableToBack ) ){
                    	   foreach ( $runners->ex->availableToBack as $toback ){
                    	       echo 'Price: '.$toback->price;
                	           echo ' | Size: '.$toback->size;
                	           echo '<br>';
                    	   }
                	   }
                	   echo '</td>';
                	   echo '<td>';
                	   if( isset( $runners->ex->availableToLay ) && is_array( $runners->ex->availableToLay ) ){
                    	   foreach ( $runners->ex->availableToLay as $tolay ){
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
