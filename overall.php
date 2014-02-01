<?php
chdir(dirname(__FILE__));
require_once "config.php";
require_once "lib/config.php";
require_once "HTML/FormPersister.php";
require_once "Mail/Simple.php";
require_once "PDO/Simple.php";
require_once "Tools/TimeSeriesAxis.php";

define("TAGS_SEP", "|");
define("PREVIEW_TABLES_COLS", 63);

// Initialize environment.
if (isCgi() && defined("USE_GZIP")) {
	ob_start("ob_gzhandler", 9);
}
if (isCgi()) {
	ob_start(array('HTML_FormPersister', 'ob_formpersisterhandler'));
	header("Content-Type: text/html; charset=utf-8");
}

if (!@session_id()) {
    session_start();
}
$DB_BY_DSN = array();
$DB = createDbConnection();

// Check credentials.
if (isCgi() && !defined("NO_AUTH")) {
	if (isset($_POST['auth'])) {
		$cred = $_POST['auth']['login'] . ":" . $_POST['auth']['pass'];
		if (getSetting("loginpass") === $cred) {
			$_SESSION['credentials'] = $cred;
			$tag = getSetting('tagafterlogin', "");
			if (preg_match('{(/|index.php)$}s', $_SERVER['REQUEST_URI']) && $tag) {
			    redirect("index.php?tag=" . urlencode($tag));
			} else {
				selfRedirect();
			}
		} else {
			addMessage("Authentication failed.");
		}
	}
	if (strval(@$_SESSION['credentials']) !== getSetting("loginpass", "")) {
		template("login", array("title" => "Authenticate yourself", "isGuest" => 1));
		exit();
	}
}

// Undo damned magic_quotes_gpc().
if (get_magic_quotes_gpc()) {
	foreach (array("_GET", "_POST") as $k) {
		if (isset($GLOBALS[$k])) {
			array_walk_recursive($GLOBALS[$k], create_function('&$a', '$a = stripslashes($a);'));
		}
	}
}
define("LOGGED_IN", true);

/**
 * Initially creates a database connection and applies all migrations
 * if needed.
 *
 * @return PDO
 */
function createDbConnection()
{
	$DB = new PDO_Simple(DB_DSN);
	if ($DB->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
		$DB->exec("SET sql_mode='ANSI_QUOTES'");
	}
	try {
		$version = $DB->selectCell('SELECT "version" FROM "version"');
	} catch (PDOException $e) {
		$version = -1;
	}
	foreach (glob("sql/*.sql") as $f) {
		if (preg_match('/^(\d+)/s', basename($f), $m) && intval($m[1]) > intval($version)) {
			$sql = file_get_contents($f);
			try {
				$DB->beginTransaction();
				foreach (explode(";", $sql) as $cmd) {
					$DB->exec(trim($cmd));
				}
				$DB->update('UPDATE "version" SET "version"=?', intval($m[1]));
				$DB->commit();
			} catch (Exception $e) {
				die("Exception: " . $e->getMessage() . "\n" . $sql);
			}
		}
	}
	return $DB;
}


/**
 * Forces all DSN connections to be re-established on the next data query,
 * This is mostly for long-running mass recalculations to clean up the
 * connection session (e.g. if we use PostgreSQL replica, it is good to
 * reconnect time to time).
 */
function reconnectDbs()
{
    global $DB_BY_DSN;
    $DB_BY_DSN = array();
}


/**
 * Renders a template.
 *
 * @param string $__name
 * @param array $__args
 */
function template($__name, $__args = array(), $noLayout = false, $noQuote = false)
{
	// Assign variables available everywhere.
	$__args['menu'] = getPageMenu();
	$__args['tagsSubmenu'] = getTagsSubmenu();
	$__args['base'] = preg_replace("{/[^/]*$}s", "/", getSetting("index_url"));

	if (!$noQuote) {
		$t0 = microtime(true);
		$__args = htmlspecialchars_recursive($__args);
//		echo sprintf("Quoting took %d ms<br>", (microtime(true) - $t0) * 1000);
	}
	extract($__args);

	// Process the template.
	$__cwd = getcwd();
	chdir(dirname(__FILE__) . "/tpl");
	if (!$noLayout) require "_header.php";
	$t0 = microtime(true);
	require "$__name.php";
//	echo sprintf("Templating of %s took %d ms<br>", $__name, (microtime(true) - $t0) * 1000);
	if (!$noLayout) require "_footer.php";
	chdir($__cwd);
}


/**
 * Quotes array recursively.
 *
 * @param string $s
 * @return string
 */
function htmlspecialchars_recursive($a)
{
	if (is_array($a)) {
		$r = array();
		foreach ($a as $k => $v) {
			$r[htmlspecialchars($k)] = htmlspecialchars_recursive($v);
		}
	} else if (is_object($a)) {
		$r = new stdClass();
		foreach ($a as $k => $v) {
			$k = htmlspecialchars($k);
			$r->$k = htmlspecialchars_recursive($v);
		}
	} else {
		$r = htmlspecialchars($a);
	}
	return $r;
}


/**
 * Much faster version of htmlspecialchars_decode().
 *
 * @param string $s
 * @return string
 */
function unhtmlspecialchars($s)
{
	return str_replace(array("&lt;", "&gt;", "&quot;", "&amp;"), array("<", ">", '"', '&'), $s);
}


/**
 * Truncate time to lower bound of minimum accounting interval (e.g. 1 day).
 *
 * @param int $time
 * @return int
 */
function trunkTime($time)
{
	return Tools_TimeSeriesAxis::trunkTime($time);
}


/**
 * Returns array of periods names which could be used to create <SELECT>.
 *
 * @return array
 */
function getPeriods()
{
	return Tools_TimeSeriesAxis::getPeriods();
}


/**
 * Alias for Tools_TimeSeriesAxis::getAxis() with $minDate from config.
 *
 * @return array
 */
function getAxis($to, $back, $period)
{
	return Tools_TimeSeriesAxis::getAxis($to, $back, $period, @strtotime(getSetting("mindate", "1971-01-01")));
}


/**
 * Generates a table with stats data.
 *
 * @param int $period
 * @param int $from
 * @param int $to
 * @param mixed $onlyItemIds
 * @param mixed $onlyDataNames
 * @return array  Array of groups of rows.
 */
function generateTableData($to, $back, $period, $onlyItemIds = null, $onlyDataNames = null, $onlyReName = null)
{
	global $DB;
	$meta = Tools_TimeSeriesAxis::getPeriodMetadata($period);
	$series = array_values(getAxis($to, $back, $period));
	$to = $series[0]["to"];
	$from = $series[count($series) - 1]["from"];

	$filterItem = "1=1";
	if ($onlyItemIds) {
		if (!is_array($onlyItemIds)) $onlyItemIds = explode(TAGS_SEP, $onlyItemIds);
		$filterItem = "item.id IN(" . join(",", array_map("intval", $onlyItemIds)) . ")";
		foreach ($onlyItemIds as $tag) {
			$filterItem .= " OR item.tags LIKE " . $DB->quote('%' . TAGS_SEP . $tag . TAGS_SEP . '%');
		}
	}
	$filterData = "1=1";
	if ($onlyDataNames) {
		if (!is_array($onlyDataNames)) $onlyDataNames = explode(TAGS_SEP, $onlyDataNames);
		$filterData = "1=0";
		foreach ($onlyDataNames as $dn) {
			$filterData .= " OR c.name LIKE " . $DB->quote($dn);
		}
	}
	$createdBetweens = "1=0";
	foreach ($series as $interval) {
	    $t = $interval['to'];
	    $f = $t - 3600 * 24;
	    // We fetch only data within 1d from a specified column boundary. It reduces
	    // the number of fetched data for weekly and monthly items greatly: typically
	    // only one data cell is fetched for each item's column.
	    $createdBetweens .= ' OR (c.created BETWEEN ' . $DB->quote($f) . ' AND ' . $DB->quote($t) . ')';
	}

	$t0 = microtime(true);
	$cells = $DB->select('
			SELECT
				item.name, item.id AS item_id, item.archived AS archived,
				c.id AS data_id, c.value, c.created, c.name AS data_name,
				t.value AS total,
				r.value AS relative_value,
				ri.name AS relative_name,
				item.relative_to AS relative_to
			FROM
				item
				LEFT JOIN data c ON (
					c.item_id = item.id
					AND (' . $createdBetweens . ')
					AND c.period = ' . $DB->quote($period) . '
					AND (' . $filterData . ')
				)
				LEFT JOIN data t ON (
					t.item_id = item.id
					AND t.created = c.created
					AND t.period = \'total\'
					AND t.name = c.name
				)
				LEFT JOIN item ri ON (
					ri.id = item.relative_to
				)
				LEFT JOIN data r ON (
					r.item_id = item.relative_to
					AND r.created = c.created
					AND r.period = c.period
					AND (ri.dim = 1 OR r.name = c.name)
				)
			WHERE
				1=1
				AND (' . $filterItem . ')
	');
	// PHP sorting is a bit faster and do not force the planner to think
	// about plan changes to deal with sorts.
	usort($cells, '_sortFetchedData');
//	echo sprintf("Fetching took %d ms<br>", (microtime(true) - $t0) * 1000);

	// For each data cell compute its unique date point.
	$t0 = microtime(true);
	$names = array();
	foreach ($cells as $i => $cell) {
		$name = $cell['name'];
		if ($cell['data_name']) {
            // Insert data name at the end of string and berore each ";" (for multi-named items).
			$name = preg_replace('/(?=;)|$/s', "/" . $cell['data_name'], trim($name));
		}
		if (!isset($names[$name])) {
			$names[$name] = array();
		}
		if ($cell['data_id']) {
			$uniq = Tools_TimeSeriesAxis::getUniqForTime($cell['created'], $meta);
			if (!isset($names[$name][$uniq])) {
				$value = extractNumeric($cell['value']);
				$relativeValue = extractNumeric($cell['relative_value']);
				$cell['percent'] = (is_numeric($relativeValue) && $relativeValue? _roundPercent($value / $relativeValue * 100) : null);
				$names[$name][$uniq] = $cell;
			}
		} else {
			// Save item_id information.
			$names[$name][""] = $cell;
		}
	}

	// Calculate relative percentage.
	$periodIndex = 0; // -1 - year, -2 - quarter, -3 - month, -4 - week, -5 - day etc.
	foreach (array_reverse(Tools_TimeSeriesAxis::getPeriodsMetadata()) as $k => $v) {
	    $periodIndex--;
	    if ($k == $period) break;
	}
	foreach ($names as $name => $cells) {
	    $cell = null;
	    foreach ($cells as $k => $nextCell) {
		    if ($cell && $cell['relative_to'] < 0 && $cell['relative_to'] <= $periodIndex) {
		        $curVal = extractNumeric($cell['value']);
		        $relVal = extractNumeric($nextCell['value']);
		        $delta = abs($relVal) > 0.0000001? ($curVal - $relVal) / $relVal * 100 : '';
		        $delta = _roundPercent($delta);
		        $cell['percent'] = $delta < 0? $delta : '+' . $delta;
		        $cell['relative_name'] = 'previous period value';
		    }
		    $cell =& $names[$name][$k];
	    }
	    unset($cell); // very important, because is ref!
	}
//	printr($names,1);

//	echo sprintf("Split by uniq intervals took %d ms<br>", (microtime(true) - $t0) * 1000);

	// Expand multi-place names.
	foreach ($names as $name => $row) {
		$list = preg_split('/\s*;\s*/s', $name);
		if (count($list) > 1) {
			unset($names[$name]);
			foreach ($list as $subName) {
				foreach ($row as $k => $v) {
					$names[$subName][$k] = $v;
				}
			}
		}
	}
	ksort($names);

    if ($onlyReName) {
        foreach ($names as $name => $row) {
            if (!preg_match('/^\d*(?:' . $onlyReName . ')$/s', $name)) {
                unset($names[$name]);
            }
        }
    }

	// Now build resulting table columns.
	$t0 = microtime(true);
	$table = array();
	$captions = array();
	$hasFirstColumn = false;
	foreach ($names as $name => $cells) {
		// Create a new row in the table.
		$group = "";
		if (preg_match('{^(.*?)/(.*)}s', $name, $m)) {
			$group = $m[1];
			$name = $m[2];
		}
		$group = preg_replace('{(^|/)\d+}s', '$1', $group);
		$name  = preg_replace('{(^|/)\d+}s', '$1', $name);
		$cell = current($cells);
		$table[$group][$name] = array(
			"total"         => false,
			"average"       => 0,
			"average_filled"=> 0,
			"relative_name" => null,
			"item_id"       => @$cell['item_id'],
			"archived"      => @$cell['archived'],
			"cells"         => array(),
		);
		$rr =& $table[$group][$name];

		// Calculate columns.
		$total = null;
		foreach ($series as $i => $interval) {
			$uniq = $interval['uniq'];
			if (isset($cells[$uniq])) {
				$cell = $cells[$uniq];
				$cell['is_complete'] = ($interval['is_complete'] && $cell['created'] == $interval['to']);
				if ($rr['total'] === false) {
					$rr['total'] = $cell['total'];
				}
				if ($cell['is_complete'] && strlen($cell['value'])) {
					$rr['average'] += extractNumeric($cell['value']);
					$rr['average_filled']++;
				}
				if ($cell['relative_name']) {
					$rr['relative_name'] = $cell['relative_name'];
				}
				$rr['item_id'] = $cell['item_id'];
				$rr['cells'][$uniq] = $cell;
				if ($i == 0) {
					$hasFirstColumn = true;
				}
			} else {
				$rr['cells'][$uniq] = null;
			}
		}
	}
//	echo sprintf("Table columns building took %d ms<br>", (microtime(true) - $t0) * 1000);

	// Calculate average.
	foreach ($table as $groupName => $group) {
		foreach ($group as $rowName => $row) {
			if ($row['average_filled']) {
				$av = $row['average'] / $row['average_filled'];
				$v = sprintf(($av < 10? "%.2f" : "%.1f"), $av);
				$table[$groupName][$rowName]['average'] = $v > 500? round($v) : $v;
			}
		}
	}

	// Build captions.
	$captions = array();
	foreach ($series as $interval) {
		$captions[$interval['uniq']] = $interval;
	}

	// Remove first column if it is empty.
	if (!$hasFirstColumn) {
		foreach ($table as $groupName => $group) {
			foreach ($group as $rowName => &$rr) {
				if ($rr['cells']) {
					reset($rr['cells']);
					unset($rr['cells'][key($rr['cells'])]);
				}
			}
		}
		reset($captions);
		unset($captions[key($captions)]);
	}

	return array(
		"captions" => $captions,
		"groups"   => $table
	);
}


function _roundPercent($p)
{
    return sprintf(($p < 10? '%.1f' : '%d'), $p);
}


function _sortFetchedData($a, $b)
{
    $c = strcmp($a['name'], $b['name']);
    if (!$c) {
        $c = strcmp($b['created'], $a['created']);
    }
    return $c;
}


/**
 * Generates a HTML representation of the stats data.
 *
 * @param array $table
 * @return string
 */
function generateHtmlTableFromData($table, $showArchived = false)
{
    if ($showArchived) {
		foreach ($table['groups'] as $gKey => $gContent) {
			foreach ($gContent as $iKey => $iContent) {
				$table['groups'][$gKey][$iKey]['archived'] = false;
			}
		}
	}
	$period = null;
	if ($table['captions']) {
		$firstInterval = current($table['captions']);
		$period = $firstInterval['period'];
	}
	ob_start();
	template(
		"table",
		array(
			"table" => $table,
			"period" => $period,
		),
		true
	);
	$html = ob_get_clean();
	$html = preg_replace('/\s+/s', ' ', $html);
	$html = preg_replace('/\s*>\s*/s', '>', $html);
	$html = preg_replace('/\s*<\s*/s', '<', $html);
	return $html;
}


function csvQuote($s)
{
    return '"' . str_replace('"', '""', $s) . '"';
}


function getSettingCsvSep()
{
    $sep = getSetting("csv_sep", ";");
    if ($sep === "tab") $sep = "\t";
    return $sep;
}


function generateCsvTableFromData($data)
{
    //printr($data,1);
    $lines = array();
    $lastColWithData = -1;

    // Build caption line (order is reversed).
    $header = array();
    foreach ($data['captions'] as $cap) {
        if (!$cap['is_complete']) continue;
        $header[] = trim(preg_replace('/\s+/s', ' ', $cap['caption']));
    }
    $lines['Title'] = $header;

    // Build rows (order of cols is reversed).
    foreach ($data['groups'] as $gname => $rows) {
        foreach ($rows as $iname => $row) {
            $cells = array();
            $i = 0;
            foreach ($data['captions'] as $uniq => $cap) {
                if (!$cap['is_complete']) continue;
                $cell = $row["cells"][$uniq];
                $value = $cell? $cell['value'] : '';
                $cells[] = $value;
                if (strlen($value)) {
                    $lastColWithData = max($lastColWithData, $i);
                }
                $i++;
            }
            $lines[$iname] = $cells;
        }
    }

    // Build CSV lines.
    $sep = getSettingCsvSep();
    foreach ($lines as $title => $row) {
        $row = array_slice($row, 0, $lastColWithData + 1); // remain only non-empty cols
        $row = array_reverse($row); // set direct time order
        $row = array_merge(array($title), $row); // add left caption column
        $row = array_map('csvQuote', $row);
        $lines[$title] = join($sep, $row);
    }

    // Return data with BOM to support UTF-8 in Excel.
    return "\xEF\xBB\xBF" . join("\r\n", $lines);
}


function selfRedirect($msg = null)
{
	if ($msg) addMessage($msg);
	header("Location: {$_SERVER['REQUEST_URI']}");
	exit();
}


function redirect($url, $msg = null)
{
	if ($msg) addMessage($msg);
	header("Location: {$url}");
	exit();
}


function addMessage($text)
{
	$_SESSION['messages'][] = $text;
}


function getAndRemoveMessages()
{
	$msgs = isset($_SESSION['messages'])? $_SESSION['messages'] : array();
	unset($_SESSION['messages']);
	return $msgs;
}


function validateItem($item)
{
	if (!strlen(trim($item['dsn_id']))) {
		throw new Exception('No database selected');
	}
	if (!strlen(trim($item['name']))) {
		throw new Exception('Name must be specified');
	}
	if (!strlen(trim($item['sql']))) {
		throw new Exception('SQL must be specified');
	}
	if (!$item['relative_to']) {
		$item['relative_to'] = null;
	}
	$item['recalculatable'] = intval(@$item['recalculatable']);
	$item['tags'] = TAGS_SEP . join(TAGS_SEP, preg_split('/\s+/', trim($item['tags']))) . TAGS_SEP;
	return $item;
}

function extractTags($string)
{
	$tags = explode(TAGS_SEP, $string);
	$tags = array_filter($tags, 'trim');
	$tags = array_map("trim", $tags);
	return $tags;
}

function fetchItem($id)
{
	global $DB;
	$item = $DB->selectRow("SELECT * FROM item WHERE id=?", $id);
	$item['tags'] = trim(join(" ", extractTags($item['tags'])));
	return $item;
}

function getIndexUrl($tag)
{
	$periods = getPeriods();
	$params = array();
	if (strlen($tag)) $params['tag'] = $tag;
	if (strlen(@$_GET['period'])) $params['period'] = $_GET['period'];
	if (strlen(@$_GET['to'])) $params['to'] = $_GET['to'];
	$params = http_build_query($params);
	$url = "index.php" . (strlen($params)? "?" . $params : "");
	return $url;
}

function getTagsSubmenu()
{
	global $DB;
	$rows = $DB->select("SELECT tags FROM item");
	$tags = array();
	foreach ($rows as $row) {
		foreach (array_unique(extractTags($row['tags'])) as $t) {
			$tags[$t] = @$tags[$t] + 1;
		}
	}
	ksort($tags);
	$tagsMenu = array();
	foreach ($tags as $tag => $count) {
		$url = getIndexUrl($tag);
		$tagsMenu[$url] = array(
			'title' => $tag,
			'count' => $count,
		);
	}
	return $tagsMenu;
}

function recalcItemRow($itemId, $to, $back, $period)
{
	global $DB;
	$series = getAxis($to, $back, $period);
	$item = $DB->selectRow("SELECT * FROM item WHERE id=?", $itemId);
	foreach ($series as $interval) {
		recalcItemCell($item, $interval);
	}
}

function replaceMacrosInSql($sql, $interval)
{
	$macros = array(
		'TO'    => date("Y-m-d H:i:s", $interval['to']), // we do not trunk $to here
		'FROM'  => date("Y-m-d H:i:s", $interval['from']),
		'DAYS'  => intval(($interval['to'] - $interval['from']) / 3600 / 24),
		'HOURS' => intval(($interval['to'] - $interval['from']) / 3600),
	);
	foreach ($macros as $k => $v) {
		$sql = str_replace('$' . $k, "'$v'", $sql);
	}
	return $sql;
}

function recalcItemCell($item, $interval)
{
	global $DB, $DB_BY_DSN;
	try {
		$t0 = microtime(true); // for catch {} block
		writeLogLine("[" . preg_replace('/\s+/s', ' ', $interval['caption']) . "] \"{$item['name']}\" " . sprintf("%-13s", strtolower($interval['periodCaption']) . "..."));

		// Test if we could calculate this item.
		if (!$item['recalculatable']) {
			if (trunkTime(time()) != trunkTime($interval['to']) && trunkTime(trunkTime(time()) - 1) != trunkTime($interval['to'])) {
				writeLogLine("skipped (cannot be recalculated to the past)\n");
				return;
			}
		}

		// Connect to the database with connection pooling.
		$dsn = $DB->selectCell("SELECT value FROM dsn WHERE id=?", $item['dsn_id']);
		if (!isset($DB_BY_DSN[$dsn])) {
			$DB_BY_DSN[$dsn] = new PDO_Simple($dsn);
		}
		$db = $DB_BY_DSN[$dsn];

		// Run the calculation.
		$t0 = microtime(true); // refresh $t0 excluding connect time
		$sql = replaceMacrosInSql($item['sql'], $interval);
		$rowset = $db->select($sql);

		// Parse single or column-returning result.
		$values = array();
		if ($item['dim'] == 1) {
			$values = array("" => @current(current($rowset)));
		} else {
			foreach ($rowset as $i => $row) {
				reset($row);
				if (count($row) > 1) {
					$key = current($row);
					next($row);
					$value = current($row);
				} else {
					$key = $i;
					$value = current($row);
				}
				if (!strlen($key)) $key = '<empty>';
				$values[$key] = $value;
			}
		}

		// Insert the data.
		$DB->update('DELETE FROM data WHERE item_id=? AND period=? AND (created BETWEEN ? AND ?)', $item['id'], $interval['period'], $interval['from'] + 1, $interval['to']);
		foreach ($values as $key => $value) {
			$DB->update(
				'INSERT INTO data(id, item_id, period, created, value, name) VALUES(?, ?, ?, ?, ?, ?)',
				$DB->getSeq(), $item['id'], $interval['period'], $interval['to'], $value, $key
			);
		}

		$t1 = microtime(true);
		writeLogLine("OK (" . join(", ", $values) . "); took " . sprintf("%d ms", ($t1 - $t0) * 1000) . "\n");
	} catch (Exception $e) {
		$t1 = microtime(true);
		writeLogLine(
			htmlspecialchars("ERROR! " . preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . "; took " . sprintf("%d ms", ($t1 - $t0) * 1000) . "\n"),
			true
		);
		throw $e;
	}
}

function testSqlAndReturnError($dsnId, $sql)
{
	global $DB;
	$dsn = $DB->selectCell("SELECT value FROM dsn WHERE id=?", $dsnId);
	if (!$dsn) return "No database selected";
	$sql = replaceMacrosInSql($sql, array("from" => time() - 3600 * 24, "to" => time()));
	try {
		$db = new PDO_Simple($dsn);
		$result = $db->select("EXPLAIN $sql");
	} catch (Exception $e) {
		return $e->getMessage();
	}
	return null;
}

function canAjaxTestSql()
{
	global $DB;
	$dsnId = $DB->selectCell("SELECT id FROM dsn LIMIT 1");
	return !testSqlAndReturnError($dsnId, "SELECT 1");
}

function writeLogLine($line, $noEscape = false)
{
	if (@$_SERVER['GATEWAY_INTERFACE']) {
		if (!$noEscape) {
			$line = htmlspecialchars($line);
			$line = str_replace(" ", "&nbsp;", $line);
			$line = nl2br($line);
		}
		$line .= '
			<script type="text/javascript">
			if (document.body && !window.sct) {
				window.sct = setTimeout(function() { document.body.scrollTop=100000000; window.sct=null; }, 50);
			}
			</script>
		';
	}
	echo $line;
	if (ob_get_level()) ob_flush();
	flush();
}


function isCgi()
{
	return !empty($_SERVER['GATEWAY_INTERFACE']);
}


function parseToBackPeriod($arr, $wholeIntervalByDefault = false)
{
	if (@$arr['to']) {
		$to = @strtotime($arr['to']);
		if (!$to) throw new Exception("Invalid date format: {$arr['to']}");
		// ATTENTION!
		// If somebody enters "2010-05-02", he means "2010-05-02 23:59:59", not "2010-05-02 00:00:00".
		if ($to == trunkTime($to)) $to = trunkTime($to + 3600 * 24) - 1;
	} else {
		if ($wholeIntervalByDefault) {
			$to = trunkTime(time()) - 1;
		} else {
			$to = time();
		}
	}
	$period = strlen(@$arr['period'])? $arr['period'] : 'day';
	$back = @$arr['back']? $arr['back'] : getSetting(isCgi()? "cols" : "cols_email", 30) + 1;
	return array($to, $back, $period);
}


function getSetting($name, $default = null)
{
	global $DB;
	$v = $DB->selectCell("SELECT value FROM setting WHERE name=?", $name);
	if (!strlen($v)) $v = $default;
	return $v;
}


function setSetting($name, $value)
{
	global $DB;
	if ($DB->selectCell("SELECT 1 FROM setting WHERE name=?", $name)) {
		$DB->update("UPDATE setting SET value=? WHERE name=?", $value, $name);
	} else {
		$DB->update("INSERT INTO setting(name, value) VALUES(?, ?)", $name, $value);
	}
}

function getPageMenu()
{
	$tagsSubmenu = array();
	if (!isCgi() || defined('LOGGED_IN')) {
		$tagsSubmenu = getTagsSubmenu();
	}
	
	foreach ($tagsSubmenu as $url => $info) {
		$tagsSubmenu[$url]['title'] = 'Tag: ' . $info['title'];
	}
	
	$firstUrl = getIndexUrl("");
	$firstTitle = "All items";
	foreach ($tagsSubmenu as $url => $info) {
		if (isCurUrl($url, false)) {
			$tagsSubmenu = array_merge(array($firstUrl => array('title' => $firstTitle, 'count' => '')), $tagsSubmenu);
			$firstUrl = "index.php";
			$firstTitle = $info['title'];
		}
	}
	
	$menu = array(
		$firstUrl => array(
			"title" => $firstTitle,
			"submenu" => $tagsSubmenu,
		),
		"item.php" => array(
			"title" => "Add an item",
		),
		"dsns.php" => array(
			"title" => "Databases",
		),
		"settings.php" => array(
			"title" => "Settings",
		),
	);
	if (defined('LOGGED_IN')) {
		$menu["logout.php"] = array("title" => "Log out");
	}
	foreach ($menu as $url => $info) {
		$menu[$url]['current'] = isCurUrl($url, true);
		if (@$info['submenu']) {
			foreach ($info['submenu'] as $subUrl => $subInfo) {
				$menu[$url]['submenu'][$subUrl]['current'] = isCurUrl($subUrl, false);
			}
		}
	}
	return $menu;
}

function isCurUrl($url, $isTopLevel)
{
    if (!isCgi()) return false;
	$curUri = preg_replace('{/(?=\?|$)}s', '/index.php', $_SERVER['REQUEST_URI']);
	if (preg_match('/item.php\?id=/s', $curUri)) return false;
	if ($isTopLevel) {
		return basename(preg_replace('/\?.*/s', '', $curUri)) == basename(preg_replace('/\?.*/s', '', $url));
	} else {
		return basename($curUri) == basename($url);
	}
}

function glueUrl($a, $b)
{
    if (!strlen($a)) return $b;
    return $a . (false === strpos($a, '?')? '?' : '&') . $b;
}

function glueQs($a, $b)
{
    if (!strlen($a)) return $b;
    if (!strlen($b)) return $a;
    return $a . "&" . $b;
}

function error_get_last_msg()
{
    $e = error_get_last();
    return $e? strip_tags($e['message']) : null;
}

function generateTableDataFromGetArgs($to, $back, $period)
{
    return generateTableData($to, $back, $period, @$_GET['tag'], null, @$_GET['re']);
}

function makeCommonPrefixTransparent($prev, $cur, $delim, $style)
{
    $pCommon = 0;
    for ($p = -1;;) {
        $p = strpos($prev, $delim, $p + 1);
        if ($p === false) break;
        if (substr($prev, 0, $p + 1) !== substr($cur, 0, $p + 1)) break;
        $pCommon = $p;
    }
    if ($pCommon) {
        return "<span style=\"$style\">" . substr($cur, 0, $pCommon) . "</span>" . substr($cur, $pCommon);
    } else {
        return $cur;
    }
}

function extractNumeric($s)
{
    return preg_replace('/[^-\d.]+/', '', trim($s));
}
