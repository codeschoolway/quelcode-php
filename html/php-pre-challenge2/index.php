<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
    $min = $i;
    for($j=$i+1; $j<count($array); $j++) {
        if ($array[$min] > $array[$j]) {
            $min = $j;
        }
    }
    $tmp = $array[$i];
    $array[$i] = $array[$min];
    $array[$min] = $tmp;

}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
