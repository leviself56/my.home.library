<?php

/*
 * https://codeshack.io/super-fast-php-mysql-database-class/
 
include 'db.php';
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'example';

$db = new db($dbhost, $dbuser, $dbpass, $dbname);

 *
 *
 */

class db {

	protected $connection;
	protected $query;
	protected $show_errors = TRUE;
	protected $query_closed = TRUE;
	

	public $ErrorNumber;
	public $query_count = 0;

	public function __construct($dbhost = 'localhost', $dbuser = 'root', $dbpass = '', $dbname = '', $charset = 'utf8') {
		$this->connection = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
		if ($this->connection->connect_error) {
			$this->error('Failed to connect to MySQL - ' . $this->connection->connect_error);
		}
		$this->connection->set_charset($charset);
	}

	public function query($query) {
		if (!$this->query_closed) {
			$this->query->close();
		}
		if ($this->query = $this->connection->prepare($query)) {
			if (func_num_args() > 1) {
				$args = func_get_args();
				$params = func_num_args() === 2 && is_array($args[1]) ? $args[1] : array_slice($args, 1);
				$types = '';
				$args_ref = array();
				foreach ($params as &$arg) {
					$types .= $this->_gettype($arg);
					$args_ref[] = &$arg;
				}
				array_unshift($args_ref, $types);
				call_user_func_array(array($this->query, 'bind_param'), $args_ref);
			}
			$this->query->execute();
			if ($this->query->errno) {
				$this->error('Unable to process MySQL query (check your params) - ' . $this->query->error);
			}
			$this->query_closed = FALSE;
			$this->query_count++;
		} else {
			$this->error('Unable to prepare MySQL statement (check your syntax) - ' . $this->connection->error."\n".$query);
		}
		return $this;
	}


	// MULTI ROW RESULTS 
	//array (0: [id:1, tech_id:7], 1: [id:2, tech_id:8])
	public function fetchAll($callback = null) {
		$params = array();
		$row = array();
		$meta = $this->query->result_metadata();
		while ($field = $meta->fetch_field()) {
		        $params[] = &$row[$field->name];
		}
		call_user_func_array(array($this->query, 'bind_result'), $params);
		$result = array();
		while ($this->query->fetch()) {
			$r = array();
			foreach ($row as $key => $val) {
				$r[$key] = $val;
			}
			if ($callback != null && is_callable($callback)) {
				$value = call_user_func($callback, $r);
				if ($value == 'break') break;
			} else {
				$result[] = $r;
			}
		}
		$this->query->close();
		$this->query_closed = TRUE;
		return $result;
	}

	// SINGLE ROW RESULT array (id:1, tech_id:7)
	public function fetchArray() {
		$params = array();
		$row = array();
		$meta = $this->query->result_metadata();
		while ($field = $meta->fetch_field()) {
		        $params[] = &$row[$field->name];
		}
		call_user_func_array(array($this->query, 'bind_result'), $params);
		$result = array();
		while ($this->query->fetch()) {
			foreach ($row as $key => $val) {
				$result[$key] = $val;
			}
		}
		$this->query->close();
		$this->query_closed = TRUE;
		return $result;
	}

	public function close() {
		return $this->connection->close();
	}

	public function numRows() {
		$this->query->store_result();
		return $this->query->num_rows;
	}

	public function affectedRows() {
		return $this->query->affected_rows;
	}

	public function lastInsertID() {
		return $this->connection->insert_id;
	}

	public function error($error) {
		$this->ErrorNumber = 1;
		if ($this->show_errors) {
			print $error;
			exit($error);
		}
	}

	private function _gettype($var) {
		if (is_null($var)) return 's'; // Map NULL to string for MySQL to handle as NULL
		if (is_string($var)) return 's';
		if (is_float($var)) return 'd';
		if (is_int($var)) return 'i';
		return 'b';
	}
	
}

?>