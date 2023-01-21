<?php
define('MYLIB', 1);

$thisDir = dirname(__FILE__);
ini_set('error_log', $thisDir . '/log/error');
ini_set('log_errors', 1);
error_reporting(E_ALL);


define('BASEDIR', dirname(__FILE__));


/**
* Load a class file.
* @param $class Class name
*/
function __autoload($class) {
	require_once $class .'.php';
}

function addIncludePath($path) {
	if ( is_array($path) ) {
		$path = implode(PATH_SEPARATOR, $path);
	}
	set_include_path(get_include_path() . PATH_SEPARATOR . $path);
}

$cyrlats = array(
	'щ' => 'sht', 'ш' => 'sh', 'ю' => 'ju', 'я' => 'ja', 'ч' => 'ch',
	'ц' => 'ts',
	'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
	'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j',
	'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
	'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
	'ф' => 'f', 'х' => 'h', 'ъ' => 'y', 'ь' => 'x',

	'Щ' => 'Sht', 'Ш' => 'Sh', 'Ю' => 'Ju', 'Я' => 'Ja', 'Ч' => 'Ch',
	'Ц' => 'Ts',
	'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
	'Е' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'J',
	'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
	'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
	'Ф' => 'F', 'Х' => 'H', 'Ъ' => 'Y', 'Ь' => 'X',

	'„' => ',,', '“' => '"', '«' => '<', '»' => '>',
	' — ' => ' - ', '–' => '-',
	'№' => 'No.', '…' => '...', '’' => '\''
);
$latcyrs = array_flip($cyrlats);
function lat2cyr($s) {
	return strtr($s, $GLOBALS['latcyrs']);
}







$cfg = array();
$parameters = file_get_contents(__DIR__.'/../../app/config/parameters.yml');

$cfg['url'] = 'https://chitanka.info';

if (preg_match_all('/database_(.+): (.+)/', $parameters, $matches)) {
	$dbParams = array_combine($matches[1], $matches[2]);
	$cfg['db']['server'] = trim($dbParams['host'], '" ');
	$cfg['db']['user']   = trim($dbParams['user'], '" ');
	$cfg['db']['pass']   = trim($dbParams['password'], '" ');
	$cfg['db']['name']   = trim($dbParams['name'], '" ');
}

if (preg_match('/wiki_url: (.+)/', $parameters, $match)) {
	$cfg['wiki_url'] = trim($match[1], '" ');
}




class Setup {

	const
		// the encoding used for internal representation
		IN_ENCODING = 'utf-8';

	private static
		$configFile = 'config.php',
		$cfg = array(),
		$setupDone = false;

	private static
		/** @var Request */      $request,
		/** @var mlDatabase */   $db;


	public static function doSetup()
	{
		if ( self::$setupDone ) {
			return;
		}

		self::loadConfiguration();

		self::defineConstants();

		self::$setupDone = true;
	}


	public static function loadConfiguration()
	{
		self::$cfg = $GLOBALS['cfg'];
	}


	public static function defineConstants()
	{
		self::defineDbTableConsts();
	}

	public static function setting($settingName)
	{
		return isset(self::$cfg[$settingName])
			? self::$cfg[$settingName] : '';
	}

	public static function request()
	{
		return self::setupRequest();
	}

	public static function db()
	{
		return self::setupDb();
	}


	private static function setupDb()
	{
		if ( ! isset(self::$db) ) {
			extract(self::$cfg['db']);
			self::$db = new mlDatabase($server, $user, $pass, $name);
		}
		return self::$db;
	}


	private static function setupRequest()
	{
		if ( ! isset(self::$request) ) {
			self::$request = new Request();
		}
		return self::$request;
	}


	private static function defineDbTableConsts()
	{
		$tables = array(
			'LABEL' => 'label',
			'PERSON' => 'person',
			'SERIES' => 'series',
		);
		foreach ($tables as $constant => $table) {
			define('DBT_' . $constant, $table);
		}
	}
}











class Request {

	const
		STANDARD_PORT = 80,
		PARAM_SEPARATOR = '=';

	public function __construct() {
		$this->path = preg_replace('#^/.+index\.php/#', '', $_SERVER['REQUEST_URI']);

		// this was a separator for a while
		$this->path = strtr($this->path, array(':' => self::PARAM_SEPARATOR));

		$this->params = explode('/', ltrim(urldecode($this->path), '/'));
		foreach ($this->params as $key => $param) {
			if ( $param === '' ) {
				unset($this->params[$key]);
				continue;
			}
			if ( strpos($param, self::PARAM_SEPARATOR) === false ) {
				$param = $this->normalizeParamValue($param);
				$this->params[$key] = $param;
			} else {
				list($var, $value) = explode(self::PARAM_SEPARATOR, $param);
				$value = $this->normalizeParamValue($value);
				if ( preg_match('/(\w+)\[(.*)\]/', $var, $match) ) {
					// the parameter is an array element
					list(, $arr, $key) = $match;
					if ( empty($key) ) {
						$_REQUEST[$arr][] = $_GET[$arr][] = $value;
					} else {
						$_REQUEST[$arr][$key] = $_GET[$arr][$key] = $value;
					}
				} else {
					$_REQUEST[$var] = $_GET[$var] = $value;
				}
			}
		}

		// normalize keys to start from 0
		$this->params = array_values($this->params);
		if ( empty($this->params) ) {
			$this->params[] = '';
		}
		if ( empty($_REQUEST[Page::FF_ACTION]) ) {
			$this->action = PageManager::validatePage( $this->params[0] );
			$_REQUEST[Page::FF_ACTION] = $_GET[Page::FF_ACTION] = $this->action;
		} else {
			$this->action = $_REQUEST[Page::FF_ACTION];
		}
		if ( $this->params[0] != $this->action ) {
			array_unshift($this->params, $this->action);
		}

		$this->unescapeGlobals();
	}


	public function action() {
		return $this->action;
	}

	public function value($name, $default = null, $paramno = null) {
		if ( isset($_REQUEST[$name]) ) {
			$val = $_REQUEST[$name];
		} else if ( is_null($paramno) ) {
			return $default;
		} else if ( isset($this->params[$paramno]) ) {
			$val = $_REQUEST[$name] = $_GET[$name] = $this->params[$paramno];
		} else {
			return $default;
		}
		return $val;
	}

	public function serverPlain() {
		return $_SERVER['SERVER_NAME'];
	}

	public function server() {
		$s = @$_SERVER['HTTPS'] != 'off' ? 'http' : 'https';
		$s .= '://' . $_SERVER['SERVER_NAME'];
		if ( $_SERVER['SERVER_PORT'] != self::STANDARD_PORT ) {
			$s .= ':' . $_SERVER['SERVER_PORT'];
		}
		return $s;
	}


	public function requestUri( $absolute = false ) {
		$uri = $absolute ? $this->server() : '';
		$uri .= $_SERVER['REQUEST_URI'];
		return $uri;
	}

	protected function normalizeParamValue($val) {
		if ( !empty($val) && $val[0] == '!' ) {
			// replace latin chars if it starts with "!"
			$val = lat2cyr( ltrim($val, '!') );
		}
		return $val;
	}

	/**
	* Remove slashes from some global arrays if magic_quotes_gpc option is on.
	*/
	protected function unescapeGlobals() {
		if ( get_magic_quotes_gpc() ) {
			$_GET = $this->unescapeArray($_GET);
			$_POST = $this->unescapeArray($_POST);
			$_COOKIE = $this->unescapeArray($_COOKIE);
			$_REQUEST = $this->unescapeArray($_REQUEST);
		}
	}

	protected function unescapeArray($arr) {
		$narr = array();
		// normalize line delimiter
		$repl = array("\r\n" => "\n", "\r" => "\n");
		foreach ($arr as $key => $val) {
			$narr[ stripslashes($key) ] = is_array($val)
				? $this->unescapeArray($val)
				: strtr(stripslashes($val), $repl);
		}
		return $narr;
	}

}






class mlDatabase {

	protected $server;
	protected $user;
	protected $pass;
	protected $dbName;
	protected $charset = 'utf8';
	protected $collationConn = 'utf8_general_ci';
	protected $conn = NULL;
	protected $doLog = true;
	protected $logFile = 'log/db-DAY.sql';
	protected $errLogFile = 'log/db-error-DAY';

	public function __construct($server, $user, $pass, $dbName) {
		$this->server = $server;
		$this->user = $user;
		$this->pass = $pass;
		$this->dbName = $dbName;
		$date = date('Y-m-d');
		$this->logFile = str_replace('DAY', $date, $this->logFile);
		$this->errLogFile = str_replace('DAY', $date, $this->errLogFile);
	}

	public function select($table, $keys = array(), $fields = array(),
			$orderby = '', $offset = 0, $limit = 0, $groupby = '') {

		$q = $this->selectQ($table, $keys, $fields, $orderby, $offset, $limit);
		return $this->query($q);
	}

	public function selectQ($table, $keys = array(), $fields = array(),
			$orderby = '', $offset = 0, $limit = 0, $groupby = '') {

		settype($fields, 'array');
		$sel = empty($fields) ? '*' : implode(', ', $fields);
		$sorder = empty($orderby) ? '' : ' ORDER BY '.$orderby;
		$sgroup = empty($groupby) ? '' : ' GROUP BY '.$groupby;
		$slimit = $limit > 0 ? " LIMIT $offset, $limit" : '';
		return "SELECT $sel FROM $table".$this->makeWhereClause($keys).
			$sgroup . $sorder . $slimit;
	}

	/**
	@param $keys Array with mixed keys (associative and numeric).
		By numeric key take the value as is if the value is a string, or send it
		recursive to makeWhereClause() with OR-joining if the value is an array.
		By string key use “=” for compare relation if the value is string;
		if the value is an array, use the first element as a relation and the
		second as comparison value.
		An example follows:
		$keys = array(
			'k1 <> 1', // numeric key, string value
			array('k2' => 2, 'k3' => 3), // numeric key, array value
			'k4' => 4, // string key, scalar value
			'k5' => array('>=', 5), // string key, array value (rel, val)
		)
	@param $join How to join the elements from $keys
	@param $putKeyword Should the keyword “WHERE” precede the clause
	*/
	public function makeWhereClause($keys, $join = 'AND', $putKeyword = true) {
		if ( empty($keys) ) {
			return $putKeyword ? ' WHERE 1' : '';
		}
		$cl = $putKeyword ? ' WHERE ' : '';
		$whs = array();
		foreach ($keys as $field => $rawval) {
			if ( is_numeric($field) ) { // take the value as is
				$field = $rel = '';
				if ( is_array($rawval) ) {
					$njoin = $join == 'AND' ? 'OR' : 'AND';
					$val = '('.$this->makeWhereClause($rawval, $njoin, false).')';
				} else {
					$val = $rawval;
				}
			} else {
				if ( is_array($rawval) ) {
					list($rel, $val) = $rawval;
					if (($rel == 'IN' || $rel == 'NOT IN') && is_array($val)) {
						// set relation — build an SQL set
						$cb = array($this, 'normalizeValue');
						$val = '('. implode(', ', array_map($cb, $val)) .')';
					} else {
						$val = $this->normalizeValue($val);
					}
				} else {
					$rel = '='; // default relation
					$val = $this->normalizeValue($rawval);
				}
			}
			$whs[] = "$field $rel $val";
		}
		$cl .= '('. implode(") $join (", $whs) . ')';
		return $cl;
	}


	public function normalizeValue($value) {
		if ( is_bool($value) ) {
			$value = $value ? 'true' : 'false';
		} else {
			$value = $this->escape($value);
		}
		return '\''. $value .'\'';
	}


	public function escape($string) {
		if ( !isset($this->conn) ) { $this->connect(); }
		return mysqli_real_escape_string( $this->conn, $string);
	}


	/**
		Send a query to the database.
		@param string $query
		@param bool $useBuffer Use buffered or unbuffered query
		@return resource, or false by failure
	*/
	public function query($query, $useBuffer = true) {
		if ( empty($query) ) {
			return true;
		}

		if ( !isset($this->conn) ) { $this->connect(); }
		$res = mysqli_query( $this->conn, $query);
		if ( !$res ) {
			$errno = ((is_object($GLOBALS["___mysqli_ston"])) ? mysqli_errno($GLOBALS["___mysqli_ston"]) : (($___mysqli_res = mysqli_connect_errno()) ? $___mysqli_res : false));
			$error = ((is_object($GLOBALS["___mysqli_ston"])) ? mysqli_error($GLOBALS["___mysqli_ston"]) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false));
			$this->log("Error $errno: $error\nQuery: $query\n".
				"Backtrace\n". print_r(debug_backtrace(), true), true);
			return false;
		}

		return $res;
	}

	public function fetchAssoc($result) {
		return mysqli_fetch_assoc($result);
	}

	public function fetchRow($result) {
		return mysqli_fetch_row($result);
	}

	public function numRows($result) {
		return mysqli_num_rows($result);
	}

	protected function connect() {
		$this->conn = ($GLOBALS["___mysqli_ston"] = mysqli_connect($this->server,  $this->user,  $this->pass))
			or $this->mydie("Проблем: Няма връзка с базата. Изчакайте пет минути и опитайте отново да заредите страницата.");
		((bool)mysqli_query( $this->conn, "USE " . $this->dbName))
			or $this->mydie("Could not select database $this->dbName.");
		mysqli_query( $this->conn, "SET NAMES '$this->charset' COLLATE '$this->collationConn'")
			or $this->mydie("Could not set names to '$this->charset':");
	}

	protected function mydie($msg) {
		header('Content-Type: text/plain; charset=UTF-8');
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		die($msg .' '. ((is_object($GLOBALS["___mysqli_ston"])) ? mysqli_error($GLOBALS["___mysqli_ston"]) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));
	}

	protected function log($msg, $isError = true)
	{
		if ($this->doLog) {
			file_put_contents($isError ? $this->errLogFile : $this->logFile,
				'/*'.date('Y-m-d H:i:s').'*/ '. $msg."\n", FILE_APPEND);
		}
	}

}






class Page {

	const FF_ACTION = 'action';

	protected
		$request,
		$db,
		$query_escapes = array(
			'"' => '', '\'' => '’'
		);


	public function __construct() {
		$this->request = Setup::request();
		$this->db = Setup::db();

		$this->startwith = $this->request->value('q', '', 1);
		if ( ! empty($this->startwith) ) {
			$this->startwith = strtr( $this->startwith, $this->query_escapes );
			$this->startwith = htmlspecialchars( $this->startwith );
		}
	}


	public function execute() {
		return $this->buildContent();
	}

	protected function buildContent() {
		$this->redirectLegacy('');
	}


	protected function redirectLegacy($url) {
		header('HTTP/1.1 301 Moved Permanently');
		if (strpos($url, 'http') === false) {
			$url = Setup::setting('url') . "/$url";
		}
		//echo '<h1>Redirecting to ', $url, '</h1>';
		header('Location: '. $url);
		exit;
	}

}





class PageManager {

	private static
		$pageDir = 'page/', $defaultPage = 'main', $errorPage = 'noPage';

	public static function pageExists($page) {
		return file_exists(self::pageDir() . "$page.php");
	}

	public static function getPageClass($action) {
		return ucfirst($action) .'Page';
	}

	public static function executePage($action) {
		$page = self::buildPage($action);
		$page->execute();
		return $page;
	}

	public static function buildPage($action) {
		$pageClass = self::loadPage($action);
		return new $pageClass();
	}

	public static function loadPage($action) {
		$page = self::getPageClass($action);
		return $page;
	}

	public static function validatePage($action) {
		if ( empty($action) ) {
			return self::$defaultPage;
		}
		$page = self::getPageClass($action);
		return self::pageExists($page) ? $action : self::$defaultPage;
	}

	public static function pageDir() {
		return BASEDIR .'/'. self::$pageDir;
	}
}




addIncludePath($thisDir . '/page');

Setup::doSetup();
PageManager::executePage(Setup::request()->action());
