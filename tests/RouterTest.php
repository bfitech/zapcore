<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;
use BFITech\ZapCore\Router;
use BFITech\ZapCoreDev\CoreDev;
use BFITech\ZapCoreDev\RouterDev;


/**
 * Class for testing defaults.
 *
 * This is to test default abort, static file serving and redirects.
 */
class RouterDefault extends Router {

	public static function header($header_string, $replace=false) {
		RouterDev::header($header_string, $replace);
	}

	public static function halt($arg=null) {
		RouterDev::halt($arg);
	}

}

class RouterTest extends TestCase {

	public static $logger;

	public static function setUpBeforeClass() {
		$logfile = CoreDev::testdir(__FILE__) . '/zapcore-test.log';
		if (file_exists($logfile))
			unlink($logfile);
		self::$logger = new Logger(Logger::DEBUG, $logfile);
	}

	public static function tearDownAfterClass() {
	}

	private function make_router() {
		return (new RouterDev())
			->config('home', '/')
			->config('host', 'http://localhost/')
			->config('logger', self::$logger)
			->init();
	}

	public function test_default() {
		$_SERVER['REQUEST_URI'] = '/';

		# abort 404
		ob_start();
		$core = new RouterDefault();
		$core->route('/s', function(){
		});
		$core->shutdown();
		$rv = ob_get_clean();
		$this->assertNotEquals(strpos($rv, '404'), false);

		# redirect
		ob_start();
		$core->deinit();
		$core->route('/', function($args) use($core){
			$core->redirect('/somewhere_else');
		});
		$core->shutdown();
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, '301 Moved'), false);

		# send file
		ob_start();
		$core->deinit();
		$core->route('/', function($args) use($core){
			$core->static_file(__FILE__);
		});
		$rv = ob_get_clean();
		$this->assertEquals(
			strpos($rv, file_get_contents(__FILE__)), false);
	}

	public function test_route_dev() {
		$core = new RouterDev();

		# test fake cookie
		unset($_COOKIE);
		$core::send_cookie('foo', 'bar', 30, '/');
		$this->assertSame($_COOKIE['foo'], 'bar');
		$core::send_cookie('foo', 'bar', -30, '/');
		$this->assertFalse(isset($_COOKIE['foo']));
		$this->assertTrue(is_array($_COOKIE));
	}

	public function test_route() {

		# Override autodetect since we're on the CLI.
		$core = (new RouterDev())
			->config('home', '/')
			->config('host', 'http://localhost')
			->config('shutdown', false)
			->config('logger', self::$logger)
			->config('wut', null)
			->init();
		$this->assertEquals($core->get_home(), '/');
		$this->assertEquals($core->get_host(), 'http://localhost/');
		$core->deinit()->reset();

		$_POST = ['a' => 1];
		$_SERVER['REQUEST_URI'] = '/x/X';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$core
			->config('home', '/')
			->route('a', function($args){
				# invalid path is ignored
			}, 'POST')
			->route('/x/<x>', function($args) use($core){
				$this->assertEquals($core->get_request_path(), '/x/X');
				echo $args['params']['x'];
			}, 'POST')
			->config('eh', 'lol') # config or init here has no effect
			->route('/x/<x>', function($args){
				# matching the same route twice only affects the first
				echo $args['params']['x'];
			}, 'POST');
		$this->assertEquals($core::$body_raw, 'X');
		$core->deinit()->reset();

		$_SERVER['REQUEST_URI'] = '/hello/john';
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		# compound path doesn't match
		$core->route('/hey/<person>', function($args){
		});
		# must call shutdown manually
		$core->shutdown();
		# PATCH is never registered in routes, hence 501
		$this->assertEquals(501, $core::$code);
		$core->deinit()->reset();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$core->route('/hey/<person>', function($args){
		});
		$core->shutdown();
		# GET is registered but no route matches, hence 404
		$this->assertEquals(404, $core::$code);
	}

	public function test_path_parser() {
		$core = (new RouterDev)
			->config('logger', self::$logger);

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

		// @fixme This shouldn't happen. __(y{v2}) != __(y/{v2})
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

	public function test_config() {
		# autodect will always be broken since we're on the CLI
		$core = (new RouterDev)
			->config('home', '/demo/')
			->config('host', 'https://localhost/demo');
		$this->assertEquals($core->get_host(),
			'https://localhost/demo/');

		# invalid, home is array
		$core->config('home', []);
		$this->assertNotEquals($core->get_home(), []);
		$this->assertEquals($core->get_home(), '/demo/');

		# invalid, empty home
		$core->config('home', '');
		$this->assertNotEquals($core->get_home(), '');
		$this->assertEquals($core->get_home(), '/demo/');

		# invalid, null host
		$core->config('host', null);
		$this->assertEquals($core->get_host(),
			'https://localhost/demo/');

		# invalid, non-trailing host
		$host = 'http://example.org/y/';
		$core->config('host', $host);
		$this->assertNotEquals($core->get_host(), $host);
		$this->assertEquals($core->get_host(),
			'https://localhost/demo/');

		# valid host
		$host = 'http://example.org/y/demo';
		$core->config('host', $host);
		# config will always enforce trailing slash
		$this->assertEquals($core->get_host(), $host . '/');

		# prefixed routing
		$core = (new RouterDev)
			->config('logger', self::$logger)
			->config('home', '/begin');
		$this->assertEquals('/begin/', $core->get_home());
		$_SERVER['REQUEST_URI'] = '/begin/sleep?x=y&p=q#asdf';
		$core->route('/sleep', function($args) use($core){
			$this->assertEquals($core->get_request_path(), '/sleep');
			echo "SLEEPING";
		});
		$this->assertEquals($core::$body_raw, "SLEEPING");
		$core->deinit()->reset();
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

		# mock file upload
		$core->deinit()->reset();
		$_POST['x'] = 'y';
		$_FILES = [
			'myfile' => [
				'name' => 'whatever.dat',
				'error' => 0,
			],
		];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test/upload';

		$core->route('/test/upload', function($args) use($core){
			$this->assertEquals('whatever.dat',
				$args['files']['myfile']['name']);
		}, 'POST');
	}

	public function test_route_get() {
		# mock input; this fake REQUEST_URI can't populate QUERY_STRING
		$_GET['x'] = 'y';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/getme/';
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		$core = $this->make_router();
		$core->route('/getme', function($args) use($core){
			$this->assertEquals($args['get'], ['x' => 'y']);
			$core::halt("OK");
		}, ['GET']);
		$this->assertEquals($core->get_request_path(), '/getme');
		$this->assertEquals($core::$body_raw, 'OK');

		$_SERVER['REQUEST_URI'] = '/getjson/';
		$core = $this->make_router();
		$core->route('/getjson', function($args) use($core){
			$this->assertEquals($args['get'], ['x' => 'y']);
			$core::print_json(0, $args['get']);
		}, ['GET']);
		$this->assertEquals($core::$errno, 0);
		$this->assertEquals($core::$data['x'], 'y');
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
		$core->route('/traceme', function($args){
		}, 'TRACE');
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
		$core->route('/', function($args){
		});
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
		$this->assertTrue(in_array('Location: /destination',
			$core::$head));
	}

}
