<?php
//
// Command-line usage:
//   php sendmail_with_recalc.php ['reName'] ['custom@email'] ['skipRecalc']
//

chdir(dirname(__FILE__));


function getArgv($i) {
	return @$_SERVER['argv'][$i]? $_SERVER['argv'][$i] : null;
}
$re = getArgv(1);
$email = getArgv(2);
// allow user to skip recalc if he just want to use "aggregated sendmail" feature of this script
// todo: refactor a bit to separate aggregated sendmail feature
$skipRecalc = getArgv(3);
$time = time();

function execute($what) {
	$phpBin = (isset($_SERVER['_']) && strpos($_SERVER['_'], 'sendmail') === false)? $_SERVER['_'] : "php";
	$args = array();
	foreach (array_slice(func_get_args(), 1) as $arg) {
		if ($arg) $args[] = escapeshellarg($arg);
	}
	$cmd = "$phpBin $what.php " . join(" ", $args);
	return system($cmd);
}

if (!$skipRecalc) {
	echo "Recalculating previous day values...\n";
	execute('recalc');
}

echo "Sending daily report...\n";

execute('sendmail', 'day', $re, $email);

$sentMonthly = false;
if (date('w', $time) == 1) { 
	// Monday morning: send weekly & monthly report
	echo "Sending weekly report...\n";
	execute('sendmail', 'week', $re, $email);
	echo "Sending monthly report...\n";
	execute('sendmail', 'month', $re, $email);
	$sentMonthly = true;
}

if (date('d', $time) == 1 && !$sentMonthly) {
	echo "Sending monthly report...\n";
	execute('sendmail', 'month', $re, $email);
}

if (date('d', $time) == 1) {
	echo "Sending quarterly report...\n";
	execute('sendmail', 'quarter', $re, $email);
}
