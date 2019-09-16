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
        <h1>Time Line</h1>
        <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
    </div>

    <div class="body-content">

        <div class="row">
        
        <div class="col-lg-4">
                
			<table>
              <tr>
              	<th>No#</th>
              	<th>Timeline Updated At</th>
                <th>Event Id</th>
                <th>Status</th>
                <th>Score</th>
                <th>Update Details</th>
              </tr>
              <?php 
              //echo '<pre>';print_r($data->results);exit;
              $i = 1;
              foreach ( $data->results as $event ){?>
              <tr>
              	<td><?php echo $i;?></td>
              	<td><?php echo $event->timeline_updated_at;?></td>
              	<td><?php echo $event->eventId;?></td>
              	<td><?php if( isset($event->status) ){ echo $event->status; }else{ echo 'No Data'; };?></td>
              	<td><?php 
              	if( !empty($event->score) ){
              	    $name = $event->score->home->name;
              	    $runs = $event->score->home->inning1->runs;
              	    $wickets = $event->score->home->inning1->wickets;
              	    $overs = $event->score->home->inning1->overs;
              	    
              	    echo '<table>
                            <tr>
                                <td>'.$name.' : '.$runs.'/'.$wickets.' ( '.$overs.' )</td>
                            </tr>
                          </table>';
              	}
              	?></td>
              	<td><?php if( !empty($event->updateDetails) ){ } ;?></td>
                
              </tr>
              <?php $i++;}?>
        	</table>

            </div>         
        </div>

    </div>
</div>
