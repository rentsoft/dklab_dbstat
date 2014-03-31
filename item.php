<?php
// No HTTP GZIP must be here!
require_once "overall.php";

$tables = null;
$id = @$_GET['id']? @$_GET['id'] : @$_POST['item']['id'];

$retpath = isset($_GET['retpath'])? $_GET['retpath'] : 'index.php';

if (!empty($_POST['doDelete'])) {
	$DB->update("DELETE FROM item WHERE id=?", $id);
	$DB->update("DELETE FROM data WHERE item_id=?", $id);
	redirect($retpath, "Item deleted.");
}

if (!empty($_POST['doClear'])) {
	$DB->update("DELETE FROM data WHERE item_id=?", $id);
	addMessage("Item data cleared.");
} else if (!empty($_POST['doSave']) || !empty($_POST['doTest']) || !empty($_POST['doRecalc'])) {
	try {
		$DB->beginTransaction();
		$item = validateItem($_POST['item']);
		if (!$id) {
			$DB->update(
				'INSERT INTO item(id, name, "sql", dsn_id, recalculatable, archived, dim, tags, created, modified, relative_to, comment) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$id = $DB->getSeq(), $item['name'], $item['sql'], $item['dsn_id'], $item['recalculatable'], $item['archived'], $item['dim'], $item['tags'], time(), time(), $item['relative_to'], $item['comment']
			);
 		} else {
			$DB->update(
				'UPDATE item SET name=?, "sql"=?, dsn_id=?, recalculatable=?, archived=?, dim=?, tags=?, modified=?, relative_to=?, comment=? WHERE id=?',
				$item['name'], $item['sql'], $item['dsn_id'], $item['recalculatable'], $item['archived'], $item['dim'], $item['tags'], time(), $item['relative_to'], $item['comment'], $id
			);
 		}
 		if (!empty($_POST['doSave'])) {
			$DB->commit();
			redirect("{$retpath}#$id", "Data is saved.");
		} else if (!empty($_POST['doTest']) || !empty($_POST['doRecalc'])) {
			list ($to, $back, $period) = parseToBackPeriod($_POST);
			$periods = $period? array($period) : array_keys(getPeriods()); 
			$tables = array();
			$hideLogJs = '</div><script type="text/javascript">document.getElementById("log").style.display="none"</script>';
			try {
				echo '<div id="log">';
				foreach ($periods as $period) {
					recalcItemRow($id, $to, $back, $period);
					$data = generateTableData($to + 1, $back, $period, $id);
					$periods = getPeriods();
					$tables[$periods[$period]] = generateHtmlTableFromData($data, true);
				}
				echo $hideLogJs;
			} catch (Exception $e) {
				echo $hideLogJs;
				throw $e;
			}
			if (!empty($_POST['doTest'])) {
				$DB->rollBack();
			} else {
				$DB->commit();
				$_POST['item']['id'] = $id;
			}
		}
	} catch (Exception $e) {
		$DB->rollBack();
		addMessage($e->getMessage());
	}
} else {
	$_POST['item'] = array();
	if ($id) {
		$_POST['item'] = fetchItem($id);
	} else if (@$_GET['clone']) {
		$_POST['item'] = fetchItem($_GET['clone']);
		unset($_POST['item']['id']); // very important!
	} else {
		$_POST['item']['sql'] = "SELECT COUNT(*)\nFROM some_table\nWHERE created BETWEEN \$FROM AND \$TO\n";
	}
}

$SELECT_DSNS = array();
foreach ($DB->select("SELECT id, name FROM dsn ORDER BY name") as $row) {
	$SELECT_DSNS[$row['id']] = $row['name'];
}

$SELECT_ITEMS = array();
$seenArchived = 0;
foreach ($DB->select("SELECT id, name, archived FROM item ORDER BY archived, name") as $row) {
	if (!$seenArchived && $row['archived']) {
		$SELECT_ITEMS[0] = "";
	}
	$SELECT_ITEMS[$row['id']] = $row['name'];
}

$SELECT_PERIODS = getPeriods();

if (!$tables && $id) {
	$to = $DB->selectCell("SELECT MAX(created) FROM data WHERE item_id=?", $id);
	if (!$to) $to = time();
	foreach ($SELECT_PERIODS as $period => $periodName) {
		$data = generateTableData($to, PREVIEW_TABLES_COLS, $period, $id);
		$tables[$periodName] = generateHtmlTableFromData($data, true);
	}
}

$title = $id
	? 'Edit item <a href="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '">' . htmlspecialchars($_POST['item']['name']) . '</a>' .
	  '&nbsp;<a href="item.php?clone=' . htmlspecialchars($id) . '" title="Clone this item"><img src="static/clone.gif" width="10" height="10" border="0" /></a>'
	: "Add a new item";
template(
	"item", 
	array(
		"titleHtml"  => $title,
		"title" => strip_tags($title),
		"tables" => $tables,
		"canAjaxTestSql" => canAjaxTestSql(),
	)              
);
