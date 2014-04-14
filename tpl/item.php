<?if (!$GLOBALS['SELECT_DSNS']) {?>
	There are no databases yet configured.<br/>
	<a href="dsns.php">Configure databases</a>
	<?return?>
<?}?>

<link rel="stylesheet" href="static/codemirror/lib/codemirror.css">
<script src="static/codemirror/lib/codemirror-compressed.js"></script>
<link rel="stylesheet" href="static/codemirror/theme/neat.css">
<link rel="stylesheet" href="static/codemirror/neat-plsql.css">

<?if ($canAjaxTestSql) {?>
	<div id="ajax_test_sql_result">
		<div class="head">SQL validation result</div>
		<div class="error"></div>
	</div>
<?}?>

<form method="post">
<input type="hidden" name="item[id]" />
<table width="100%">
	<tr valign="top">
		<td width="1" class="caption">Options</td>
		<td>
			<select name="item[dsn_id]" id="dsn_id"><option value="">- Database -</option>SELECT_DSNS</select>
			<select name="item[relative_to]" style="width:25em">
			    <option value="">- Data is relative to -</option>
			    <option value="-3">- Previous monthly and above interval -</option>
			    <option value="-4">- Previous weekly and above interval -</option>
			    <option value="-5">- Previous daily and above interval -</option>
			    SELECT_ITEMS
			</select>
			<select name="item[dim]" default="1">
				<option value="1">Single value returned</option>
				<option value="2">Column returned</option>
			</select>
		</td>
		<td width="300"></td>
	</tr>
	<tr valign="top">
		<td class="caption">Name</td>
		<td><input type="text" name="item[name]" size="90" style="width:100%"/></td>
		<td class="comment">Separate aliases with ";".</td>
	</tr>
	<tr valign="top">
		<td class="caption">Tags</td>
		<td><input type="text" name="item[tags]" size="60" style="width:100%"/></td>
		<td class="comment">Separate tags with space.</td>
	</tr>
	<tr valign="top">
		<td class="caption">Comment</td>
		<td><input type="text" name="item[comment]" size="60" style="width:100%"/></td>
		<td class="comment">Optional comment.</td>
	</tr>
	<tr valign="top">
		<td class="caption">SQL</td>
		<td>
			<textarea id="sql" name="item[sql]" cols="80" rows="8" style="width:100%"></textarea>
		</td>
		<td class="comment">
			Available macros are:
			<ul>
			<li><b>$FROM</b>: period start (TIMESTAMP)</li>
			<li><b>$TO</b>: period end (TIMESTAMP)</li>
			<li><b>$DAYS</b>: period length (number of days)</li>
			</ul>
		</td>
	</tr>
	<tr valign="top" id="action_bar">
		<td><br/></td>
		<td style="padding-top: 10px">
			<div>
				<input type="hidden" name="item[recalculatable]" value="0" />
				<input type="checkbox" id="recalculatable" name="item[recalculatable]" value="1" default="1" />
				<label for="recalculatable" style="margin-right:3em">Could be recalculated to the past</label>
			</div>
			<div>
				<input type="hidden" name="item[archived]" value="0" />
				<input type="checkbox" id="archived" name="item[archived]" value="1" default="0" />
				<label for="archived">Archived</label>
			</div>
			<div style="margin-top: 4px">
				<div style="float:right; text-align:right">
					<input type="submit" name="doTest" value="Test" /> or
					<input type="submit" name="doRecalc" value="Recalc" />
					from <input type="text" name="to" size="4" default="now"/> back <input type="text" name="back" size="4" default="14"/>
					<select name="period"><option value="0">- ALL -</option>SELECT_PERIODS</select> periods
				</div>
				<input type="submit" style="width:100px" name="doSave" value="<?=@$_POST['item']['id']? "Save" : "Add"?>"/>
				<?if (@$_POST['item']['id']) {?>
					<input type="submit" name="doDelete" confirm="Are you sure you want to delete this item?" value="Delete" style="margin-left:1em"/>
					<input type="submit" name="doClear" confirm="Are you sure you want to clear all data for this item?" value="Clear" style="margin-left:1em"/>
				<?}?>
			</div>
		</td>
		<td><br/></td>
	</tr>
</table>
</form>

<?if ($tables) {?>
	<br/>
	<?foreach ($tables as $tableName => $info) {?>
		<div>
			<h2 style="display:inline-block">
				<?=$tableName?> period last calculated values
			</h2>
			<span style="padding-left:6em">
				Export as CSV:
				<a href="export.php?tag=<?=$_POST['item']['id']?>&amp;period=<?=$info['period']?>">this period</a>,
				<a href="export.php?tag=<?=$_POST['item']['id']?>">all periods</a>
			</span>
		</div>
		<?=unhtmlspecialchars($info['html'])?>
	<?}?>
<?}?>
