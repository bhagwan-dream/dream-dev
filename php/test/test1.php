<?php
$data = '{"status":1,"data":[{"price":44,"profitLoss":-5000},{"price":45,"profitLoss":15000},{"price":46,"profitLoss":15000},{"price":47,"profitLoss":15000},{"price":48,"profitLoss":15000},{"price":49,"profitLoss":15000},{"price":50,"profitLoss":5000},{"price":51,"profitLoss":5000},{"price":52,"profitLoss":5000},{"price":53,"profitLoss":5000},{"price":54,"profitLoss":5000},{"price":55,"profitLoss":-15000},{"price":56,"profitLoss":-15000},{"price":57,"profitLoss":-15000},{"price":58,"profitLoss":-15000},{"price":59,"profitLoss":-15000},{"price":60,"profitLoss":-15000},{"price":61,"profitLoss":5000},{"price":62,"profitLoss":5000}],"betList":[{"runner":"10 Over RUN BAN","bet_type":"no","price":"55","win":"10000","size":"10000","loss":"10000"},{"runner":"10 Over RUN BAN","bet_type":"yes","price":"45","win":"10000","size":"10000","loss":"10000"},{"runner":"10 Over RUN BAN","bet_type":"yes","price":"61","win":"10000","size":"10000","loss":"10000"},{"runner":"10 Over RUN BAN","bet_type":"no","price":"50","win":"5000","size":"5000","loss":"5000"}]}';

$data = json_decode($data);

echo '<pre>';
print_r($data->data);

$dataNew = [];

$i = 0;
$start = 0;
$startPl = 0;
$end = 0;
$endPl = 0;

foreach ($data->data as $index => $d) {

    if ($index == 0) {
        $dataNew[$i]['price'] = $d->price . ' or less';
        $dataNew[$i]['profitLoss'] = $d->profitLoss;

    } else {
        if ($startPl != $d->profitLoss) {
            if ($end != 0) {
                $dataNew[$i]['price'] = $start . ' - ' . $end;
                $dataNew[$i]['profitLoss'] = $startPl;
            }

            $start = $d->price;
            $end = $d->price;

        } else {
            $end = $d->price;
        }
        if ($index == (count($data->data) - 1)) {
            $dataNew[$i]['price'] = $start . ' or more';
            $dataNew[$i]['profitLoss'] = $startPl;
        }

    }

    $startPl = $d->profitLoss;
    $i++;

}


echo '<pre>';
print_r($dataNew);
die;

//$r2 = $d->price;

//if( $p != $d->profitLoss ){
//    $r1 = $r2+1;
//    $p1 = $d->profitLoss;
//}
//if( $p1 != $d->profitLoss ){
//
//    $dataNew[$i]['price'] = $r1.' - '.$r2;
//    $dataNew[$i]['profitLoss'] = $d->profitLoss;
//
//}