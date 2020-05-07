<?php
$limit = $_GET['target'];
if (is_string($limit) == false || $limit < 1 || preg_match('/^([1-9]\d*|0)\.(\d+)?$/', $limit) ) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$dsn = 'mysql:dbname=test;host=mysql';
$dbuser = 'test';
$dbpassword = 'test';

try {
    $db = new PDO ($dsn, $dbuser, $dbpassword);
} catch (PDOException $e) {
    echo 'DB接続エラー： ' . $e->getMessage();
}

$sth = $db->query('SELECT value FROM prechallenge3');
$aryDBNums = $sth->fetchAll(PDO::FETCH_COLUMN, 0);

function makeCombinations($aryNums) {
    $aryAllCombined = array(array());
    foreach ($aryNums as $number) {
        foreach ($aryAllCombined as $eachCombination) {
            array_push($aryAllCombined, array_merge(array($number), $eachCombination));
        }
    }
    return $aryAllCombined;
}

$aryAllCombinations = makeCombinations($aryDBNums);

foreach ($aryAllCombinations as $aryCombination) {
    if (array_sum($aryCombination) === (int)$limit) {
        $aryResultCombinations[] = $aryCombination;
    }
}
if (is_null($aryResultCombinations)) {
    $aryResultCombinations[] = json_encode(array());
}

json_encode($aryResultCombinations, JSON_NUMERIC_CHECK);
?>
