<?php
//include_once "php/api/index.php";
ini_set("display_errors", "1");
error_reporting(E_ALL);
$url = 'http://13.233.165.68/api/odds/cricket';
     
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);
curl_close($ch);
//$response = json_decode($response);
echo '<pre>';print_r($response);die;
?>
