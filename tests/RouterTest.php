<?php


use BFITech\ZapCore\Logger;
use BFITech\ZapCore\Router;
use BFITech\ZapCore\RouterError;
use BFITech\ZapCoreDev\RouterDev;
use BFITech\ZapCoreDev\RoutingDev;
use BFITech\ZapCoreDev\TestCase;


/**
 * Class for testing defaults.
 *
 * This is to test default abort, static file serving and redirects.
 */
class RouterDefault extends Router {

	public static function header(
		string $header_string, bool $replace=false
	) {
		RouterDev::header($header_string, $replace);
	}

	public static function halt(string $arg=null) {
		RouterDev::halt($arg);
	}

}

class RouterTest extends TestCase {

	public static $logger;

	public static function setUpBeforeClass() {
		$logfile = self::tdir(__FILE__) . '/zapcore-test.log';
		if (file_exists($logfile))
			unlink($logfile);
		self::$logger = new Logger(Logger::DEBUG, $logfile);
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
		$core
			->route('/s', function(){})
			->shutdown();
		$rv = ob_get_clean();
		$this->ne()(strpos($rv, '404'), false);
		$core->deinit();

		# redirect
		ob_start();
		$core
			->route('/', function($args) use($core){
				$core->redirect('/somewhere_else');
			})
			->shutdown();
		$rv = ob_get_clean();
		$this->ne()(strpos($rv, '301 Moved'), false);
		$core->deinit();

		# send file
		ob_start();
		$core->route('/', function($args) use($core){
			$core->static_file(__FILE__);
		});
		$rv = ob_get_clean();
		self::eq()(strpos($rv, file_get_contents(__FILE__)), false);
	}

	public function test_route_dev() {
		extract(self::vars());

		$core = new RouterDev();

		# test fake cookie
		unset($_COOKIE);
		$core::send_cookie('foo', 'bar', 30, '/');
		$sm($_COOKIE['foo'], 'bar');
		$core::send_cookie('foo', 'bar', -30, '/');
		$fl(isset($_COOKIE['foo']));
		$tr(is_array($_COOKIE));
	}

	public function test_route() {
		extract(self::vars());

		# Override autodetect since we're on the CLI.
		$core = (new RouterDev())
			->config('home', '/')
			->config('host', 'http://localhost')
			->config('shutdown', false)
			->config('logger', self::$logger)
			->config('wut', null)
			->init();
		$eq($core->get_home(), '/');
		$eq($core->get_host(), 'http://localhost/');

		$rdev = new RoutingDev($core);
		$rdev
			->request('/x/X', 'POST', ['post' => ['a' => 1]])
			->config('home', '/')
			->route('/x/<x>', function($args) use($core, $eq){
				$eq($core->get_request_path(), '/x/X');
				echo $args['params']['x'];
			}, 'POST')
			->config('eh', 'lol') # config or init here has no effect
			->route('/x/<x>', function($args){
				# matching the same route twice only affects the first
				echo $args['params']['x'];
			}, 'POST');
		$eq($core::$body_raw, 'X');

		$rdev
			->request('/hello/john', 'PATCH')
			# compound path doesn't match
			->route('/hey/<person>', function($args){
			})
			# must call shutdown manually
			->shutdown();
		# PATCH is never registered in routes, hence 501
		$eq(501, $core::$code);

		$rdev
			->request('/x/X')
			->route('/hey/<person>', function($args){
			})
			->shutdown();
		# GET is registered but no route matches, hence 404
		$eq(404, $core::$code);
	}

	private function make_parser() {
		return (new RouterDev)->config('logger', self::$logger);
	}

	public function test_path_parser() {
		extract(self::vars());

		$core = $this->make_parser();

		# regular
		$rv = $core->path_parser('/x/y/');
		$eq($rv[0], '/x/y');
		$eq($rv[1], []);

		# short var
		$rv = $core->path_parser('/@x/<v1>/y/<v2>/z');
		$sm($rv[1], ['v1', 'v2']);

		# long var
		$rv = $core->path_parser('/x/<v1>/y/{v2}/1:z');
		$sm($rv[1], ['v1', 'v2']);
	}

	public function test_path_parser_invalid_path() {
		$this->expectException(RouterError::class);
		$this->make_parser()->path_parser('a');
	}

	public function test_path_parser_invalid_dynamic_path() {
		$this->expectException(RouterError::class);
		$this->make_parser()->path_parser('/x/<v1>/y{v2}/z');
	}

	public function test_path_parser_invalid_key() {
		$this->expectException(RouterError::class);
		$this->make_parser()->path_parser('/X/<12>/{w2}/z');
	}

	public function test_path_parser_illegal_char() {
		$this->expectException(RouterError::class);
		$this->make_parser()->path_parser('/x/<v1>/!/{v2}/z');
	}

	public function test_path_parser_key_reuse() {
		$this->expectException(RouterError::class);
		$this->make_parser()->path_parser('/x/<v1>/y/{v1}/z');
	}

	public function test_config() {
		extract(self::vars());

		# autodect will always be broken since we're on the CLI
		$core = (new RouterDev)
			->config('home', '/demo/')
			->config('host', 'https://localhost/demo');
		$eq($core->get_host(),
			'https://localhost/demo/');

		# invalid, home is array
		$core->config('home', []);
		$ne($core->get_home(), []);
		$eq($core->get_home(), '/demo/');

		# invalid, empty home
		$core->config('home', '');
		$ne($core->get_home(), '');
		$eq($core->get_home(), '/demo/');

		# invalid, null host
		$core->config('host', null);
		$eq($core->get_host(),
			'https://localhost/demo/');

		# invalid, non-trailing host
		$host = 'http://example.org/y/';
		$core->config('host', $host);
		$ne($core->get_host(), $host);
		$eq($core->get_host(),
			'https://localhost/demo/');

		# valid host
		$host = 'http://example.org/y/demo';
		$core->config('host', $host);
		# config will always enforce trailing slash
		$eq($core->get_host(), $host . '/');

		# prefixed routing
		$core = (new RouterDev)
			->config('logger', self::$logger)
			->config('home', '/begin');
		$eq('/begin/', $core->get_home());
		$_SERVER['REQUEST_URI'] = '/begin/sleep?x=y&p=q#asdf';
		$core->route('/sleep', function($args) use($core, $eq){
			$eq($core->get_request_path(), '/sleep');
			echo "SLEEPING";
		});
		$eq($core::$body_raw, "SLEEPING");
		$core->deinit()->reset();
	}

	public function test_route_post() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		# mock request
		$rdev->request('/test/z', 'POST', ['post' => ['x' => 'y']]);

		# setting args['header'] via global still works; see below
		$_SERVER['HTTP_REFERER'] = 'http://localhost';

		# no request path set until $core->route is called at least once
		$eq($core->get_request_path(), null);

		# path doesn't match
		$core->route('/miss', function($args) use($core) {},
			['GET', 'POST']);
		# request path is properly set
		$eq($core->get_request_path(), '/test/z');

		# path matches
		$core->route('/test/<v1>', function($args) use($core, $eq) {
			$eq($args['get'], []);
			$eq($core->get_request_path(), '/test/z');
			$eq($core->get_request_comp(), ['test', 'z']);
			$eq($core->get_request_comp(0), 'test');
			$eq($core->get_request_comp(2), null);
			$eq($core->get_request_method(), 'POST');
			$eq($args['post']['x'], 'y');
			$eq($args['header']['referer'], 'http://localhost');
		}, 'POST');

		# mock file upload
		$rdev
			->request('/test/upload', 'POST', [
				'post' => ['x' => 'y'],
				'files' => [
					'myfile' => [
						'name' => 'whatever.dat',
						'error' => 0,
					],
				],
				# intentionally-invalid arg key
				'trace' => 1,
			])
			->route('/test/upload', function($args) use($core, $eq) {
				$eq('whatever.dat', $args['files']['myfile']['name']);
			}, 'POST');

		# mock file upload via globals
		$rdev->request('/test/upload', 'POST');
		## cannot chain $rdev->request->route since $rdev->request
		## always resets all HTTP vars internally
		$_POST = ['mypost' => 'something'];
		$_FILES = [
			'myfile' => [
				'name' => 'what.dat',
				'error' => 0,
			],
		];
		$core->route('/test/upload', function($args) use($core, $eq) {
			$eq($args['post']['mypost'], 'something');
			$eq($args['files']['myfile']['name'], 'what.dat');
		}, 'POST');
	}

	public function test_route_get() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/getme/', 'GET', ['get' => ['x' => 'y']])
			->route('/getme', function($args) use($core, $eq){
				$eq($args['get'], ['x' => 'y']);
				$core::halt("OK");
			}, ['GET']);
		$eq($core->get_request_path(), '/getme');
		$eq($core::$body_raw, 'OK');

		$rdev
			->request('/getjson/', 'GET', ['get' => ['x' => 'y']])
			->route('/getjson', function($args) use($core, $eq){
				$eq($args['get'], ['x' => 'y']);
				$core::print_json(0, $args['get']);
			}, 'GET');
		$eq($core::$errno, 0);
		$eq($core::$data['x'], 'y');
	}

	public function test_route_patch() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/patchme/', 'PATCH',
				['patch' => 'hello'])
			->route('/patchme', function($args) use($core){
				$core::halt($args['patch']);
			}, ['PATCH'], false);
		$eq($core->get_request_path(), '/patchme');
		$eq($core::$body_raw, "hello");
	}

	public function test_route_trace() {
		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/traceme/', 'TRACE')
			->route('/traceme', function($args){
			}, 'TRACE');
		# regardless the request, TRACE will always give 405
		self::eq()($core::$code, 405);
	}

	public function test_route_notfound() {
		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev->request('/findme/');

		# without this top-level route, $core->shutdown()
		# will set 501
		$core->route('/', function($args){
		});

		# must invoke shutdown manually since no path matches
		$core->shutdown();
		self::eq()($core::$code, 404);
	}

	public function test_route_static() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/static/' . basename(__FILE__))
			->route('/static/<path>', function($args) use($core, $eq){
				$core->static_file(
					__DIR__ . '/' . $args['params']['path']);
				$eq($core::$code, 200);
				$eq($core::$body_raw, file_get_contents(__FILE__));
			});

		$rdev
			->request('/static/notfound.txt')
			->route('/static/<path>', function($args) use($core){
				$core->static_file(
					__DIR__ . '/' . $args['params']['path']);
			});
		$eq($core::$code, 404);
	}

	public function test_route_abort() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/notfound')
			->route('/', function($args) use($core) {
				echo "This will never be reached.";
			}, 'GET')
			# invoke shutdown manually
			->shutdown();
		$eq($core::$code, 404);

		$rdev
			->request('/notfound', 'POST')
			->shutdown();

		# POST is never registered, hence, POST request will give 501
		$eq($core::$code, 501);
	}

	public function test_route_redirect() {
		$eq = self::eq();

		$core = $this->make_router();
		$rdev = new RoutingDev($core);

		$rdev
			->request('/redirect')
			->route('/redirect', function($args) use($core) {
				$core->redirect('/destination');
			}, 'GET');
		$eq($core::$code, 301);

		# we can reuse the request here
		$core->route('/redirect', function($args) use($core) {
				$core->redirect('/destination');
			}, 'GET');
		self::tr()(in_array('Location: /destination',
			$core::$head));
	}

}
