<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;
use BFITech\ZapCore\Router;


// Router with simplified constructor and disabled die()
// wrapper is all we need.
class RouterAlive extends Router {
	public function __construct() {
		$logfile = getcwd() . '/zapcore-test-mock-router.log';
		$logger = new Logger(Logger::ERROR, $logfile);
		parent::__construct(null, null, false, $logger);
	}
	public static function header($header_string, $replace=false) {
		echo "$header_string\n";
	}
	public static function header_halt($str=null) {
		// don't die
		return;
	}
}

class RouterCustom extends RouterAlive {
	public function abort_custom($code) {
		echo "ERROR: $code";
	}
	public function redirect_custom($url) {
		echo "Location: $url";
	}
	public function static_file_custom(
		$path, $cache=0, $disposition=false
	) {
		echo file_exists($path) ? "OK" : "NO";
	}
}

class RouterTest extends TestCase {

	public static $logger;

	public static function setUpBeforeClass() {
		$logfile = getcwd() . '/zapcore-test.log';
		self::$logger = new Logger(Logger::DEBUG, $logfile);
	}

	public static function tearDownAfterClass() {
		foreach([
			'/zapcore-test.log',
			'/zapcore-test-mock-router.log'
		] as $logbase) {
			$logfile = getcwd() . $logbase;
			if (file_exists($logfile))
				unlink($logfile);
		}
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
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test/z';
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		$core = new RouterAlive();
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
		$_GET['x'] = 'y';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/getme/';
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		$core = new RouterAlive();
		$core->route('/getme', function($args) use($core){
			$this->assertEquals($args['get'], ['x' => 'y']);
			$this->assertEquals($core->get_request_path(), '/getme');
		}, ['GET']);
	}

	public function test_route_patch() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$_SERVER['REQUEST_URI'] = '/patchme/';

		$core = new RouterAlive();
		$this->assertEquals($core->get_request_path(), '/patchme');
		$core->route('/patchme', function($args) use($core){
			# we can't fake PATCH with globals here
			$this->assertEquals($core->get_request_path(), '/patchme');
		}, ['PATCH']);
	}

	public function test_route_trace() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'TRACE';
		$_SERVER['REQUEST_URI'] = '/traceme/';

		$core = new RouterAlive();
		ob_start();
		$core->route('/traceme', function($args){}, 'TRACE');
		$rv = ob_get_clean();
		# regardless the request, TRACE will always gives 405
		$this->assertNotEquals(strpos($rv, '405'), false);
	}

	public function test_route_notfound() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/findme/';

		# patched router
		$core = new RouterAlive();
		ob_start();
		# without this top-level route, $core->shutdown()
		# will end up with 501
		$core->route('/', function($args){});
		# must invoke shutdown manually since no path matches
		$core->shutdown();
		$rv = ob_get_clean();
		$this->assertNotEquals(strpos($rv, '404'), false);
	}

	public function test_route_static() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/static/' . basename(__FILE__);

		$core = new RouterAlive();
		$core->route('/static/<path>', function($args) use($core){
			ob_start();
			$core->static_file(__DIR__ . '/' . $args['params']['path']);
			$rv = ob_get_clean();
			$this->assertNotEquals(
				strpos($rv, file_get_contents(__FILE__)), false);
		});

		$core = new RouterCustom();
		$core->route('/static/<path>', function($args) use($core){
			ob_start();
			$core->static_file(__DIR__ . '/' . $args['params']['path']);
			$rv = ob_get_clean();
			$this->assertEquals($rv, 'OK');
		});
	}

	public function test_route_abort() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/notfound';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$core = new RouterAlive();
		$core->route('/notfound', function($args) use($core) {
			ob_start();
			$core->abort(404);
			$rv = ob_get_clean();
			# default 404 page contains string '404'
			$this->assertNotEquals(
				strpos($rv, '404'), false);
		}, 'GET');

		$core = new RouterCustom();
		$core->route('/notfound', function($args) use($core) {
			ob_start();
			$core->abort(404);
			$rv = ob_get_clean();
			# default 404 page contains string '404'
			$this->assertNotEquals(
				strpos($rv, '404'), false);
		}, 'GET');
	}

	public function test_route_redirect() {
		# mock input
		$_SERVER['REQUEST_URI'] = '/redirect';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$core = new RouterAlive();
		$core->route('/redirect', function($args) use($core) {
			ob_start();
			$core->redirect('/destination');
			$rv = ob_get_clean();
			# default redirect page contains string '301'
			$this->assertNotEquals(
				strpos($rv, '301'), false);
		}, 'GET');

		$core = new RouterCustom();
		$core->route('/redirect', function($args) use($core) {
			ob_start();
			$core->redirect('/destination');
			$rv = ob_get_clean();
			$this->assertNotEquals(
				strpos($rv, '/destination'), false);
		}, 'GET');
	}

}

