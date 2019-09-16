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
            <h2>Market Id : <?php echo $data->marketId;?></h2>
            <h2>Market Name : <?php echo $data->marketName;?></h2>
            <h2>Total Matched : <?php echo $data->totalMatched;?></h2>
            <h2>Runners List</h2>
            <table>
              <tr>
              	<th>No#</th>
                <th>Selection Id</th>
                <th>Runner Name</th>
                <th>Sort Priority</th>
              </tr>
              
              <?php 
                //echo '<pre>';print_r($data->runners);exit;
              $i = 1;
              $marketId = $data->marketId;
              foreach ( $data->runners as $runners ){
                	echo '<tr>';
                	   echo '<td>'.$i.'</td>';
                	   echo '<td><a href="market-book?MARKETID='.$marketId.'">'.$runners->selectionId.'</td>';
                	   echo '<td>'.$runners->runnerName.'</td>';
                	   echo '<td>'.$runners->sortPriority.'</td>';
                    echo '</tr>';
              $i++;
              }
                ?>
              
            </table>
            
        </div>
        </div>

    </div>
</div>
