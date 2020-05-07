<?php
$limit = $_GET['target'];
if (is_string($limit) === false || $limit < 1 || preg_match('/^([1-9]\d*|0)\.(\d+)?$/', $limit) ) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode('invalid limit : ' . $limit);
    exit;
}

$dsn = 'mysql:dbname=test;host=mysql';
$dbuser = 'test';
$dbpassword = 'test';

try {
    $db = new PDO ($dsn, $dbuser, $dbpassword);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode('DB接続エラー： ' . $e->getMessage(), JSON_UNESCAPED_UNICODE);
    exit;
}

$sth = $db->prepare('SELECT value FROM prechallenge3 WHERE value <= ? ORDER BY value');
$sth->execute(array($limit));
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
    $aryResultCombinations[[]];
}

echo json_encode($aryResultCombinations, JSON_NUMERIC_CHECK);
?>
