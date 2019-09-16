<?php
/*$responseData = [];
$url = 'http://13.233.165.68/api/event/4';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);
$responseData = json_decode($responseData);

echo 'CRICKET : ';

echo '<pre>';print_r($responseData);*/
$responseData = [];
$url = 'http://13.233.165.68/api/event/4';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);
$responseData = json_decode($responseData);

echo 'CRICKET : ';

echo '<pre>';print_r($responseData);die;
/*$responseData = [];
$url = 'http://13.233.165.68/api/event/1';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);
$responseData = json_decode($responseData);

echo 'FOOTBALL : ';

echo '<pre>';print_r($responseData);die;*/

/*$responseData = [];
$url = 'http://rohitash.dream24.bet:3000/getmarket?id=1.156352813';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);*/
//$responseData = json_decode($responseData);


$responseData = [];
$url = 'http://score.royalebet.uk/2/29188581';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$responseData = curl_exec($ch);
curl_close($ch);

echo 'TENNIS : Brown v Janvier';

echo '<pre>';print_r($responseData);

?>
