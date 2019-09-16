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
            <h2>Place Bet</h2>
            <?php echo '<pre>';print_r($data);exit;?>
        </div>
        </div>

    </div>
</div>
