<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;
use BFITech\ZapCoreDev\RouterDev;


class RouterTest extends TestCase {

	public static $logger;

	public static function setUpBeforeClass() {
		$logfile = getcwd() . '/zapcore-test.log';
		if (file_exists($logfile))
			unlink($logfile);
		self::$logger = new Logger(Logger::DEBUG, $logfile);
	}

	public static function tearDownAfterClass() {
	}

	private function make_router() {
		return new RouterDev(null, null, false, self::$logger);
	}

	public function test_constructor() {
		global $argv;
		$core = new RouterDev(null, null, false, self::$logger);

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
		$core = new RouterDev(null, null, false, self::$logger);

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

		$core = $this->make_router();
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

		$core = $this->make_router();
		$core->route('/getme', function($args){
			$this->assertEquals($args['get'], ['x' => 'y']);
		}, ['GET']);
		$this->assertEquals($core->get_request_path(), '/getme');
	}

	public function test_route_patch() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$_SERVER['REQUEST_URI'] = '/patchme/';

		$core = $this->make_router();
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

		$core = $this->make_router();
		$core->route('/traceme', function($args){}, 'TRACE');
		# regardless the request, TRACE will always give 405
		$this->assertEquals($core::$code, 405);
	}

	public function test_route_notfound() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/findme/';

		$core = $this->make_router();
		# without this top-level route, $core->shutdown()
		# will end up with 501
		$core->route('/', function($args){});
		# must invoke shutdown manually since no path matches
		$core->shutdown();
		$this->assertEquals($core::$code, 404);
	}

	public function test_route_static() {
		# mock input
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/static/' . basename(__FILE__);

		$core = $this->make_router();
		$core->route('/static/<path>', function($args) use($core){
			$core->static_file(__DIR__ . '/' . $args['params']['path']);
			$this->assertEquals($core::$code, 200);
			$this->assertEquals(
				$core::$body_raw, file_get_contents(__FILE__));
		});

		$_SERVER['REQUEST_URI'] = '/static/notfound.txt';
		$core = $this->make_router();
		$core->route('/static/<path>', function($args) use($core){
			$core->static_file(__DIR__ . '/' . $args['params']['path']);
		});
		$this->assertEquals($core::$code, 404);
	}

	public function test_route_abort() {
		$_SERVER['REQUEST_URI'] = '/notfound';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$core = $this->make_router();
		$core->route('/', function($args) use($core) {
			echo "This will never be reached.";
		}, 'GET');
		# invoke shutdown manually
		$core->shutdown();
		$this->assertEquals($core::$code, 404);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$core = $this->make_router();
		$core->route('/', function($args) use($core) {
			echo "This will never be reached.";
		}, 'GET');
		$core->shutdown();
		# POST is never registered, hence, POST request will give 501
		$this->assertEquals($core::$code, 501);
	}

	public function test_route_redirect() {
		$_SERVER['REQUEST_URI'] = '/redirect';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$core = $this->make_router();
		$core->route('/redirect', function($args) use($core) {
			$core->redirect('/destination');
		}, 'GET');
		$this->assertEquals($core::$code, 301);

		$core = $this->make_router();
		$core->route('/redirect', function($args) use($core) {
			$core->redirect('/destination');
		}, 'GET');
		$this->assertTrue(in_array('Location: /destination', $core::$head));
	}

}

