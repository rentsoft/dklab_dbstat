<?php
//
// Command-line usage:
//   php recalc.php {<only_period>|all} {<back>}
//
// No HTTP GZIP must be here!
//
define("NO_AUTH", 1);
require_once "overall.php";
$id = isset($argv[1]) ? $argv[1] : null;
$period = isset($argv[2]) ? $argv[2] : 'day';
$back = isset($argv[3]) ? $argv[3] : 1;

list ($to, $back, $period) = parseToBackPeriod(['to' => 'now', 'back' => $back, 'period' => $period]);
$periods = $period? array($period) : array_keys(getPeriods());
$DB->beginTransaction();
foreach ($periods as $period) {
    recalcItemRow($id, $to, $back, $period);
}
$DB->commit();
