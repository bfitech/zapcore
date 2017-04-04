<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;
use BFITech\ZapCore\Router;


class RouterPatched extends Router {

	public static $test;

	public static $abort_code = null;
	public static $url_redirect = null;
	public static $static_path = null;

	public function __construct(
		$home=null, $host=null, $shutdown=false,
		Logger $logger=null, TestCase $test=null
	) {
		self::$test = $test;
		parent::__construct($home, $host, $shutdown, $logger);
	}

	protected function halt() {
		// don't die
		return;
	}

	public function abort_custom($code) {
		// custom abort; no header ever sent
		self::$test->assertEquals(self::$abort_code, $code);
	}

}

class RouterPatchedCustom extends RouterPatched {

	public function redirect_custom($url) {
		// custom redirect; no header ever sent
		self::$test->assertEquals(self::$url_redirect, $url);
	}

	public function static_file_custom(
		$path, $cache=0, $disposition=false
	) {
		// custom static file serving; no header ever sent
		self::$test->assertEquals(self::$static_path, $path);
	}
}

class RouterTest extends TestCase {

	public static $logger;

	public static function setUpBeforeClass() {
		self::$logger = new Logger(Logger::DEBUG, '/dev/null');
	}

	public static function tearDownAfterClass() {
	}

	public function test_logger() {
		$logfile = __DIR__ . '/zapcore-logger.log';
		if (file_exists($logfile))
			unlink($logfile);
		$logger = new Logger(Logger::INFO, $logfile);
		$this->assertTrue(file_exists($logfile));

		# write to logfile
		$logger->info("Some info.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'INF'), false);
		$logger->warning("Some warning.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'WRN'), false);
		$logger->error("Some error.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'ERR'), false);
		$logger->debug("Some debug.");
		$this->assertEquals(
			strpos(file_get_contents($logfile), 'DEB'), false);

		# write to /dev/null instead of logfile
		$logger = new Logger(Logger::DEBUG, $logfile,
			fopen('/dev/null', 'ab'));
		$logger->debug("Some debug.");
		$this->assertEquals(
			strpos(file_get_contents($logfile), 'DEB'), false);

		# write to STDERR since logfile becomes read-only
		chmod($logfile, 0400);
		$logger = new Logger(Logger::DEBUG, $logfile);
		// $logger->info("Some info.");

		# if chmod-ing happens after opening handle, handle is
		# still writable
		$logfilefile = $logfile . '.log';
		if (file_exists($logfilefile))
			unlink($logfilefile);
		file_put_contents($logfilefile, "START\n");
		$logger = new Logger(Logger::DEBUG, $logfilefile);
		chmod($logfilefile, 0400);
		$logger->info("Some info.");
		$content = file_get_contents($logfilefile);
		$this->assertEquals(substr($content, 0, 5), "START");
		$this->assertNotEquals(strpos($content, "INF"), false);

		foreach ([$logfile, $logfilefile] as $fl)
			unlink($fl);
	}

	public function test_constructor() {
		global $argv;
		$core = new Router(null, null, false, self::$logger);

		# @note On CLI, Router::get_home() will resolve to
		#     the calling script, which in this case,
		#     phpunit script.
		$home = $core->get_home();
		$this->assertEquals(
			rtrim($home, '/'), dirname($argv[0]));

		# @note On CLI, Router::get_host() is meaningless.
		$this->assertEquals(
			$core->get_host(), "http://localhost${home}");
	}

	public function test_path_parser() {
		$core = new Router(null, null, false, self::$logger);

		# regular
		$rv = $core->path_parser('/x/y/');
		$this->assertEquals($rv[0], '/x/y/');
		$this->assertEquals($rv[1], []);

		# short var
		$rv = $core->path_parser('/x/<v1>/y/<v2>/z');
		$this->assertSame($rv[1], ['v1', 'v2']);

		# long var
		$rv = $core->path_parser('/x/<v1>/y/{v2}/z');
		$this->assertSame($rv[1], ['v1', 'v2']);

		# @fixme This shouldn't happen. __(y{v2}) != __(y/{v2})
		$rv = $core->path_parser('/x/<v1>/y{v2}/z');
		$this->assertSame($rv[1], ['v1', 'v2']);

		# illegal character
		$rv = $core->path_parser('/x/<v1>/!{v2}/z');
		$this->assertSame($rv[0], []);
		$this->assertSame($rv[1], []);

		# key reuse
		$rv = $core->path_parser('/x/<v1>/y/{v1}/z');
		$this->assertSame($rv[0], []);
		$this->assertSame($rv[1], []);
	}

	public function test_route_post() {
		# mock input
		$_POST['x'] = 'y';
		$_SERVER['REQUEST_URI'] = '/test/z';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		$cb = function($callback, $args=[]) {
			$callback($args);
		};

		$core = new RouterPatched(null, null, false, self::$logger);
		$this->assertEquals($core->get_request_path(), '/test/z');

		# invalid callback
		$core->route('/', 'cb');
		$core->route('/', null);

		$core->route('/miss', function($args) use($core){
			# path doesn't match
		}, ['GET', 'POST']);

		$core->route('/test/<v1>', function($args) use($core){
			$this->assertEquals($args['get'], []);
			$this->assertEquals($core->get_request_path(), '/test/z');
			$this->assertEquals($core->get_request_comp(),
				['test', 'z']);
			$this->assertEquals($core->get_request_comp(0), 'test');
			$this->assertEquals($core->get_request_comp(2), null);
			$this->assertEquals($core->get_request_method(), 'POST');
			$this->assertEquals($args['post']['x'], 'y');
			$this->assertEquals($args['header']['referer'],
				'http://localhost');
		}, 'POST');
	}

	public function test_route_get() {
		# mock input; this fake REQUEST_URI can't populate QUERY_STRING
		$_SERVER['REQUEST_URI'] = '/getme/';
		$_GET['x'] = 'y';
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		$core = new RouterPatched(null, null, false, self::$logger);
		$this->assertEquals($core->get_request_path(), '/getme');

		$core->route('/getme', function($args) use($core){
			$this->assertEquals($args['get'], ['x' => 'y']);
			$this->assertEquals($core->get_request_path(), '/getme');
		}, ['GET']);
	}

	public function test_route_patch() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/patchme/';
		$_SERVER['REQUEST_METHOD'] = 'PATCH';

		$core = new RouterPatched(
			null, null, false, self::$logger);
		$this->assertEquals($core->get_request_path(), '/patchme');

		$core->route('/patchme', function($args) use($core){
			# we can't fake PATCH with globals here
			$this->assertEquals($core->get_request_path(), '/patchme');
		}, ['PATCH']);
	}

	public function test_route_trace() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/traceme/';
		$_SERVER['REQUEST_METHOD'] = 'TRACE';

		$core = new RouterPatched(
			null, null, false, self::$logger, $this);
		$this->assertEquals($core->get_request_path(), '/traceme');

		# test via $core::$abort_code
		$core::$abort_code = 405;
		$core->route('/traceme', function($args) use($core){
		}, ['TRACE']);
	}

	public function test_route_notfound() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/findme/';

		$core = new RouterPatched(null, null, false, self::$logger, $this);

		# test via $core::$abort_code
		$core::$abort_code = 404;
		# even without this top-level route, $core->shutdown() will still
		# end up with 404
		$core->route('/', function($args){});
	}

	public function test_route_redirect() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/redirect/';

		$core = new RouterPatchedCustom(
			null, null, false, self::$logger, $this);

		$core::$url_redirect = 'http://localhost/x';
		$core->route('/redirect', function($args) use($core){
			$core->redirect('http://localhost/x');
		});
	}

	public function test_route_static() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/static/zapcore-static.log';

		$core = new RouterPatchedCustom(
			null, null, false, self::$logger, $this);

		$core::$static_path = __DIR__ . '/zapcore-static.log';
		$core->route('/static/<path>', function($args) use($core){
			$core->static_file(__DIR__ . '/' . $args['params']['path']);
		});
	}

	public function test_route_shutdown() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/whatever';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$core = new RouterPatched(
			null, null, false, self::$logger, $this);
		$core->route('/', function($args){
		}, 'GET');
		# because GET has been once registered, abort is 404
		$core::$abort_code = 404;
		$core->shutdown();

		$core = new RouterPatched(
			null, null, true, self::$logger, $this);
		# @note
		# - If router is set to execute shutdown function, we'll have
		#   deeply-layered test here. $core will execute shutdown
		#   function because there's no matching path, and in turn,
		#   the shutdown function is a patched abort. Changing
		#   $core::$abort_code below will fail the test.
		$core::$abort_code = 501;
	}
}

