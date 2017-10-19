<?php

/**
 * Simplifies PDO usage.
 */
class PDO_Simple extends PDO
{
	public function __construct($dsn)
	{
		$user = $pass = null;
		if (preg_match('/\buser=([^ ;]+)/s', $dsn, $m)) {
			$user = $m[1];
			$dsn = str_replace($m[0], '', $dsn);
		}
		if (preg_match('/\bpassword=([^ ;]*)/s', $dsn, $m)) {
			$pass = $m[1];
			$dsn = str_replace($m[0], '', $dsn);
		}
		$dsn = rtrim(preg_replace('/\s*;[;\s]*/', ';', $dsn), ';');
		parent::__construct($dsn, $user, $pass, array(
			PDO::ATTR_TIMEOUT => 600,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		));
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function selectCell()
	{
		$args = func_get_args();
		$all = call_user_func_array(array($this, 'select'), $args);
		$row = @current($all);
		return $row? current($row) : $row;
	}

	public function selectRow()
	{
		$args = func_get_args();
		$all = call_user_func_array(array($this, 'select'), $args);
		return current($all);
	}

	public function select()
	{
		$args = func_get_args();
		$sql = array_shift($args);
		$t0 = microtime(true);
		$rs = $this->prepare($sql);
		$rs->execute($args);
		$all = $rs->fetchAll(PDO::FETCH_ASSOC);
		$dt = microtime(true) - $t0;
		$this->_logSql($sql, $args, $dt, count($all));
		return $all;
	}
	
	public function update()
	{
		$args = func_get_args();
		$sql = array_shift($args);
		$rs = $this->prepare($sql);
		$result = $rs->execute($args);
		return $result;
	}
	
	public function getSeq()
	{
		$seq = $this->selectCell("SELECT id FROM seq");
		$this->update("UPDATE seq SET id = id + 1");
		return $seq;
	}
	
	private function _logSql($sql, $args, $dt, $numRows)
	{
	    return;
	    
	    foreach ($args as $a) {
	        $sql = preg_replace("/\?/s", "'" . addslashes($a) . "'", $sql, 1);
	    }
	    $style = $dt > 0.1? ' style="color:red"' : '';
	    echo sprintf("<b%s>%d ms; %d row(s)</b><br>", $style, $dt * 1000, $numRows);
	    echo "<pre style='margin:0; padding:0'>" . htmlspecialchars(rtrim($sql)) . "</pre><br>";
	}
}
