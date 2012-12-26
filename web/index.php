<?php
function isCacheable() {
	return $_SERVER['REQUEST_METHOD'] == 'GET' && !array_key_exists('mlt', $_COOKIE);
}
class Cache {
	private $file;
	private $request;
	private $ttl = 10;
	private $debug = false;

	public function __construct($requestUri, $cacheDir) {
		$hash = md5($requestUri);
		$this->file = "$cacheDir/$hash[0]/$hash[1]/$hash[2]/$hash";
		$this->request = $requestUri;
	}

	public function get() {
		if (file_exists($this->file)) {
			if ($this->isFresh()) {
				$this->log("=== CACHE HIT");
				return file_get_contents($this->file);
			}
			$this->purge();
		}
		return null;
	}
	public function getTtl() {
		return $this->ttl;
	}
	private function isFresh() {
		$origTtl = file_get_contents("$this->file.ttl") + rand(0, 30);
		$age = time() - filemtime($this->file);
		$this->ttl = $origTtl - $age;
		return $this->ttl > 0;
	}
	public function set($content, $ttl) {
		if ( ! $ttl) {
			return;
		}
		$cacheDir = dirname($this->file);
		if (!file_exists($cacheDir)) {
			mkdir($cacheDir, 0777, true);
		}
		file_put_contents($this->file, $content);
		file_put_contents("$this->file.ttl", $ttl);
		$this->log("+++ CACHE MISS ($ttl)");
	}
	private function purge() {
		unlink($this->file);
		unlink("$this->file.ttl");
		$this->log('--- CACHE PURGE');
	}
	private function log($msg) {
		if ($this->debug) {
			error_log("$msg - $this->request");
		}
	}
}
$cache = new Cache($_SERVER['REQUEST_URI'], __DIR__.'/../app/cache/prod/simple_http_cache');

if (isCacheable() && null !== ($cachedContent = $cache->get())) {
	header("Cache-Control: public, max-age=".$cache->getTtl());
	echo $cachedContent;
	return;
}

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;

$rootDir = __DIR__.'/..';
require_once $rootDir.'/app/bootstrap.php.cache';

try {
	// Use APC for autoloading to improve performance
	$loader = new ApcClassLoader('chitanka', $loader);
	$loader->register(true);
} catch (\RuntimeException $e) {
	// APC not enabled
}

require_once $rootDir.'/app/AppKernel.php';
//require_once $rootDir.'/app/AppCache.php';

register_shutdown_function(function(){
	$error = error_get_last();
	if ($error['type'] == E_ERROR) {
		if (preg_match('/parameters\.yml.+does not exist/', $error['message'])) {
			header('Location: /install.php');
			exit;
		}
		if (strpos($error['message'], 'Out of memory') === false) {
			file_put_contents(__DIR__.'/../app/logs/fatal-error.log',
				"\nFATAL ERROR\nrequest = $_SERVER[REQUEST_URI]\n"
				. print_r($error, true)."\n", FILE_APPEND);
		}
		ob_clean();
		header('HTTP/1.1 503 Service Unavailable');
		readfile(__DIR__ . '/503.html');
	}
});

$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
if (isCacheable() && $response->isOk()) {
	$cache->set($response->getContent(), $response->getTtl());
}
$response->send();
$kernel->terminate($request, $response);
