<?php

use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = 'BetFair Api';
$baseUrl = Url::base(true);

if( $response->success != 1 ){
    echo '<h1>Error: Somthing wrong in API</h1>';exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</head>
<body>

<div class="container">
  <h2>Exchange</h2>
  <div class="panel-group" id="accordion">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h4 class="panel-title">
          <a data-toggle="collapse" data-parent="#accordion" href="#Event">Event</a>
        </h4>
      </div>
      <div id="Event" class="panel-collapse collapse in">
        <div class="panel-body">
        <?php 
            $event = $response->results[0]->event;
            echo '<pre>';print_r($event);echo '</pre>';
        ?>
        </div>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h4 class="panel-title">
          <a data-toggle="collapse" data-parent="#accordion" href="#Timeline">Timeline</a>
        </h4>
      </div>
      <div id="Timeline" class="panel-collapse collapse">
        <div class="panel-body">
        <?php 
        $timeline = $response->results[0]->timeline;
        echo '<pre>';print_r($timeline);echo '</pre>';
        ?>
        </div>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h4 class="panel-title">
          <a data-toggle="collapse" data-parent="#accordion" href="#Markets">Markets</a>
        </h4>
      </div>
      <div id="Markets" class="panel-collapse collapse">
        <div class="panel-body">
        <?php 
        $i = 1;
        $marketsArr = $response->results[0]->markets;
        foreach ( $marketsArr as $markets ){?>
        
        <div class="panel panel-default">
          <div class="panel-heading">
            <h4 class="panel-title">
              <a data-toggle="collapse" data-parent="#accordion" href="#Markets<?php echo $i;?>">Markets <?php echo $i;?></a>
            </h4>
          </div>
          <div id="Markets<?php echo $i;?>" class="panel-collapse collapse">
            <div class="panel-body">
            	<div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#Licence<?php echo $i;?>">Licence</a>
                    </h4>
                  </div>
                  <div id="Licence<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    	<?php 
                    	$licence = $markets->licence;
                    	echo '<pre>';print_r($licence);echo '</pre>';
                    	?>
                    </div>
                  </div>
                </div>
            
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#Rates<?php echo $i;?>">Rates</a>
                    </h4>
                  </div>
                  <div id="Rates<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    	<?php 
                    	$rates = $markets->rates;
                    	echo '<pre>';print_r($rates);echo '</pre>';
                    	?>
                    </div>
                  </div>
                </div>
            
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#State<?php echo $i;?>">State</a>
                    </h4>
                  </div>
                  <div id="State<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    	<?php 
                    	$state = $markets->state;
                    	echo '<pre>';print_r($state);echo '</pre>';
                    	?>
                    </div>
                  </div>
                </div>
            
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#Description<?php echo $i;?>">Description</a>
                    </h4>
                  </div>
                  <div id="Description<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    	<?php 
                    	$description = $markets->description;
                    	echo '<pre>';print_r($description);echo '</pre>';
                    	?>
                    </div>
                  </div>
                </div>
            
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#Runners<?php echo $i;?>">Runners ( Market Id : <?php echo $markets->marketId;?> )</a>
                    </h4>
                  </div>
                  <div id="Runners<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    <?php 
                    $j = 1;
                    $runnersArr = $markets->runners;
                    foreach ( $runnersArr as $runners ){?>
                    
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h4 class="panel-title">
                          <a data-toggle="collapse" data-parent="#accordion" href="#Runners_<?php echo $i;?>_<?php echo $j;?>">Runners <?php echo $j;?></a>
                        </h4>
                      </div>
                      <div id="Runners_<?php echo $i;?>_<?php echo $j;?>" class="panel-collapse collapse">
                        <div class="panel-body">
                    
                        	<div class="panel panel-default">
                              <div class="panel-heading">
                                <h4 class="panel-title">
                                  <a data-toggle="collapse" data-parent="#accordion" href="#Exchange_<?php echo $i;?>_<?php echo $j;?>">Exchange</a>
                                </h4>
                              </div>
                              <div id="Exchange_<?php echo $i;?>_<?php echo $j;?>" class="panel-collapse collapse">
                                <div class="panel-body">
                                	<?php 
                                	$exchange = $runners->exchange;
                                	if( isset($exchange->availableToBack) ){
                                	    echo '<h5>availableToBack</h5>';
                                	    $availableToBack = $exchange->availableToBack;
                                	    echo '<pre>';print_r($availableToBack);echo '</pre>';
                                	}
                                	if( isset($exchange->availableToLay) ){
                                	    echo '<h5>availableToLay</h5>';
                                	    $availableToLay = $exchange->availableToLay;
                                	    echo '<pre>';print_r($availableToLay);echo '</pre>';
                                	}
                                	?>
                                </div>
                              </div>
                            </div>
                    
                            <div class="panel panel-default">
                              <div class="panel-heading">
                                <h4 class="panel-title">
                                  <a data-toggle="collapse" data-parent="#accordion" href="#Description_<?php echo $i;?>_<?php echo $j;?>">Description</a>
                                </h4>
                              </div>
                              <div id="Description_<?php echo $i;?>_<?php echo $j;?>" class="panel-collapse collapse">
                                <div class="panel-body">
                                	<?php 
                                	$description = $runners->description;
                                	echo '<pre>';print_r($description);echo '</pre>';
                                	?>
                                </div>
                              </div>
                            </div>
                    
                            <div class="panel panel-default">
                              <div class="panel-heading">
                                <h4 class="panel-title">
                                  <a data-toggle="collapse" data-parent="#accordion" href="#State_<?php echo $i;?>_<?php echo $j;?>">State</a>
                                </h4>
                              </div>
                              <div id="State_<?php echo $i;?>_<?php echo $j;?>" class="panel-collapse collapse">
                                <div class="panel-body">
                                	<?php 
                                	$state = $runners->state;
                                	echo '<pre>';print_r($state);echo '</pre>';
                                	?>
                                </div>
                              </div>
                            </div>
                           </div>
                          </div>
                        </div>
                	<?php $j++;}?>
                
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h4 class="panel-title">
                      <a data-toggle="collapse" data-parent="#accordion" href="#Market<?php echo $i;?>">Market</a>
                    </h4>
                  </div>
                  <div id="Market<?php echo $i;?>" class="panel-collapse collapse">
                    <div class="panel-body">
                    	<?php 
                    	$market = $markets->market;
                    	echo '<pre>';print_r($market);echo '</pre>';
                    	?>
                    </div>
                  </div>
                </div>                
                </div>
              </div>
            </div>
            </div>
          </div>
        </div>
        <?php $i++;}?>
        </div>
      </div>
    </div>
  </div> 
</div>
    
</body>
</html>
