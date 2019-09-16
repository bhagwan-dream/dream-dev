<?php

use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = 'BetFair Api';
$baseUrl = Url::base(true);

if( $data->success != 1 ){
    echo '<h1>Error: Somthing wrong in API</h1>';exit;
}
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
</style>
<div class="container">
	<h2>Cricket Exchange</h2>
    <a href="<?php echo dirname(dirname($baseUrl));?>/admin" >Back To Admin</a>
        <div class="row">
        <div class="col-lg-10">   
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
                <td><a href="exchange?EVENT_ID=<?php echo $event->id;?>"><?php echo $event->id;?></a></td>
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
</body>
</html>