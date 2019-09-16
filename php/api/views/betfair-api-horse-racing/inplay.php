<?php

use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = 'BetFair Api';
$baseUrl = Url::base(true);

if( $data->success != 1 ){
    echo '<h1>Error: Somthing wrong in API</h1>';exit;
}
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
        <h1>Cricket In Play</h1>
        <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
    </div>

    <div class="body-content">

        <div class="row">
        
        <div class="col-lg-4">
                
			<table>
              <tr>
              	<th>No#</th>
              	<th>ID</th>
              	<th>TIME</th>
              	<th>IN PLAY</th>
              </tr>
              <?php 
              //echo '<pre>';print_r($data);exit;
              $i = 1;
              foreach ( $data->results as $event ){
              ?>
              <tr>
              	<td><?php echo $i;?></td>
                <td><a href="timeline?EVENT_ID=<?php echo $event->id;?>"><?php echo $event->id;?></a></td>
                <td><?php echo date('F j, Y', $event->time);?></td>
                <td>
                <?php 
                    if( isset( $event->league ) ){
                        echo $event->league->name.' : ';
                    };
                    echo $event->home->name.' VS '.$event->away->name;
                ?></td>
              </tr>
              <?php $i++;}?>
        	</table>

            </div>         
        </div>

    </div>
</div>
