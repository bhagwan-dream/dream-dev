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
        <h1>All Events List</h1>
        <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
    </div>

    <div class="body-content">

        <div class="row">
        
        <div class="col-lg-4">
                
			<table>
              <tr>
              	<th>No#</th>
                <th>Event Name</th>
                <th>Market Count</th>
              </tr>
              <?php 
                //echo '<pre>';print_r($data->runners);exit;
              $i = 1;
              foreach ( $data as $event ){?>
              <tr>
              	<td><?php echo $i;?></td>
                <td><a href="<?php if( $event['event_type_id'] == 7 ){ echo 'event-market';}else{ echo '#';}?>"><?php echo $event['event_type_name'];?></a></td>
                <td><?php echo $event['market_count'];?></td>
              </tr>
              <?php $i++;}?>
        	</table>

            </div>         
        </div>

    </div>
</div>
