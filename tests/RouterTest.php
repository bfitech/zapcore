<?php


use BFITech\ZapCore\Logger;
use BFITech\ZapCore\Router;
use BFITech\ZapCore\Parser;
use BFITech\ZapCore\ParserError;
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

	private function make_routing() {
		$rdev = new RoutingDev;
		$rdev::$core
			->config('home', '/')
			->config('host', 'http://localhost/')
			->config('logger', self::$logger);
		return [$rdev, $rdev::$core];
	}

	public function test_default() {
		extract(self::vars());

		$_SERVER['REQUEST_URI'] = '/';

		# abort 404
		ob_start();
		$core = new RouterDefault;
		$core
			->route('/s', function(){})
			->shutdown();
		$rv = ob_get_clean();
		$ne(strpos($rv, '404'), false);

		# redirect
		ob_start();
		$core = new RouterDefault;
		$core
			->route('/', function($args) use($core){
				$core->redirect('/somewhere_else');
			})
			->shutdown();
		$rv = ob_get_clean();
		$ne(strpos($rv, '301 Moved Permanently'), false);

		# send file ok
		ob_start();
		$core = new RouterDefault;
		$core->route('/', function($args) use($core){
			$core->static_file(__FILE__);
		});
		$eq(ob_get_clean(), file_get_contents(__FILE__));

		# send file not found
		ob_start();
		$core = new RouterDefault;
		$core->route('/', function($args) use($core){
			$core->static_file(__FILE__ . '/notfound');
		});
		$tr(strpos(ob_get_clean(), '404 Not Found') !== false);
	}

	public function test_route_dev() {
		extract(self::vars());

		$core = new RouterDev;

		# test fake cookie
		unset($_COOKIE);
		$core::send_cookie('foo', 'bar', 30, '/');
		$sm($_COOKIE['foo'], 'bar');
		$core::send_cookie('foo', 'bar', -30, '/');
		$fl(isset($_COOKIE['foo']));
		$tr(is_array($_COOKIE));

		# test fake cookie with opts
		unset($_COOKIE);
		$core::send_cookie_with_opts('quux', 'baz');
		$sm($_COOKIE['quux'], 'baz');
		$core::send_cookie_with_opts('quux', 'baz', ['expires' => 0]);
		$fl(isset($_COOKIE['quux']));
		$tr(is_array($_COOKIE));

		# send file with custom not-found callback
		$core->route('/', function($args) use($core){
			$core->static_file(__FILE__ . '/notfound', [
				'callback_notfound' => function() {
					echo "wow much not found";
				},
			]);
		});
		$eq($core::$body_raw, "wow much not found");
	}

	public function test_route() {
		extract(self::vars());

		### override autodetect since we're on the CLI
		$core = (new RouterDev)
			->config('home', '/')
			->config('host', 'http://localhost')
			->config('shutdown', false)
			->config('logger', self::$logger)
			->config('wut', null)
			->init();
		$eq($core->get_home(), '/');
		$eq($core->get_host(), 'http://localhost/');

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/x/X', 'POST', ['post' => ['a' => 1]])
			->config('home', '/')
			->route('/x/<x>', function($args) use($core, $eq){
				$eq($core->get_request_path(), '/x/X');
				echo $args['params']['x'];
			}, 'POST')
			### config or init here has no effect
			->config('eh', 'lol')
			### matching the same route twice only affects the first
			->route('/x/<x>', function($args){
				echo $args['params']['x'];
			}, 'POST');
		$eq($core::$body_raw, 'X');

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/hello/john', 'PATCH')
			# compound path doesn't match
			->route('/hey/<person>', function(){})
			# must call shutdown manually
			->shutdown();
		# PATCH is never registered in routes, hence 501
		$eq(501, $rdev::$core::$code);

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/x/X')
			->route('/hey/<person>', function(){})
			->shutdown();
		# GET is registered but no route matches, hence 404
		$eq(404, $core::$code);
	}

	public function test_parser() {
		extract(self::vars());

		# regular
		$rv = Parser::match_route('/x/y/');
		### trailing slash is removed on generated regex
		$eq($rv[0], '/x/y');
		$eq($rv[1], []);

		# short var
		$rv = Parser::match_route('/@x/<v1>/y/<v2>/z');
		$sm($rv[1], ['v1', 'v2']);

		# long var
		$rv = Parser::match_route('/x/<v1>/y/{v2}/1:z');
		$sm($rv[1], ['v1', 'v2']);

		$ce = function($path) {
			try {
				Parser::match_route($path);
			} catch(ParserError $err) {
				return $err->getCode();
			}
			return 0;
		};

		# no leading slash
		$eq(ParserError::PATH_INVALID, $ce('a'));
		# y{v2}
		$eq(ParserError::DYNAMIC_PATH_INVALID, $ce('/x/<v1>/y{v2}/z'));
		# <12>
		$eq(ParserError::PARAM_KEY_INVALID, $ce('/X/<12>/{w2}/z'));
		# !
		$eq(ParserError::CHAR_INVALID, $ce('/x/<v1>/!/{v2}/z'));
		# <v1> {v1}
		$eq(ParserError::PARAM_KEY_REUSED, $ce('/x/<v1>/y/{v1}/z'));
	}

	public function test_config() {
		extract(self::vars());

		# autodect will always be broken since we're on the CLI
		$core = (new RouterDev)
			->config('home', '/demo/')
			->config('host', 'https://localhost/demo');
		$eq($core->get_host(), 'https://localhost/demo/');

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
		$eq($core->get_host(), 'https://localhost/demo/');

		# invalid, non-trailing host
		$host = 'http://example.org/y/';
		$core->config('host', $host);
		$ne($core->get_host(), $host);
		$eq($core->get_host(), 'https://localhost/demo/');

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
	}

	public function test_route_post() {
		$eq = self::eq();

		list($rdev, $core) = $this->make_routing();
		$rdev->request('/test/z', 'POST', ['post' => ['x' => 'y']]);
		# setting args['header'] via global still works; see below
		$_SERVER['HTTP_REFERER'] = 'http://example.tld';
		# no request path set until $core->route is called at least once
		$eq($core->get_request_path(), null);
		# path doesn't match
		$core->route('/miss', function() {}, ['GET', 'POST']);
		# request path is properly set
		$eq($core->get_request_path(), '/test/z');
		# path matches
		$core->route('/test/<v1>', function($args) use($core, $eq) {
			$eq($args['get'], []);
			$eq($core->get_request_path(), '/test/z');
			$eq($core->get_request_method(), 'POST');
			$eq($args['post']['x'], 'y');
			$eq($args['header']['referer'], 'http://example.tld');
		}, 'POST');
		$eq($core::$code, 200);

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

	public function test_route_post_json() {
		$eq = self::eq();
		$sm = self::sm();
		list($_, $core) = $this->make_routing();

		/* test static method */

		$args = [];
		$eq($core::get_json($args), []);

		$args['header'] = [];
		$eq($core::get_json($args), []);

		$args['header'] = [
			'content_type' => 'application/json',
		];
		$args['method'] = null;
		$eq($core::get_json($args), []);

		$args['method'] = 'post';
		$eq($core::get_json($args), []);

		$args['post'] = '';
		$eq($core::get_json($args), []);

		$args['post'] = '{"errno":0,"data":null}';
		$sm($core::get_json($args), [
			'errno' => 0,
			'data' => null,
		]);

		/* test in router */

		$post_json = function($header, $post, $callback) {
			list($rdev, $core) = $this->make_routing();
			$rdev
				->request('/test', 'POST', [
					'header' => $header,
					'post' => $post,
				])
				->route('/test', function($args) use($callback, $core) {
					$callback($core, $args);
					return $core::pj([0, $args]);
				}, 'POST', true);
			return $core;
		};

		# mime doesn't match
		$post_json([], '', function($core, $args) use($eq) {
			$eq($core::get_json($args), []);
		});

		# mime matches with broken body
		$hdr = ['content_type' => 'application/json'];
		# header can also be set with:
		#   $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
		# but it will pollute global $_SERVER.
		$post_json($hdr, '', function($core, $args) use($eq) {
			$eq($core::get_json($args), []);
		});

		# ok
		$post = '{"hey":0,"there":null}';
		$c = $post_json($hdr, $post, function($core, $args) use($sm) {
			$sm($core::get_json($args), [
				'hey' => 0,
				'there' => null,
			]);
		});
	}

	public function test_route_get() {
		$eq = self::eq();

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/getme/', 'GET', ['get' => ['x' => 'y']])
			->route('/getme', function($args) use($core, $eq){
				$eq($args['get'], ['x' => 'y']);
				$core::halt("OK");
			}, ['GET']);
		$eq($core->get_request_path(), '/getme');
		$eq($core::$body_raw, 'OK');

		list($rdev, $core) = $this->make_routing();
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

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/patchme/', 'PATCH',
				['patch' => 'hello'])
			->route('/patchme', function($args) use($core){
				$core::halt($args['patch']);
			}, 'PATCH', false);
		$eq($core->get_request_path(), '/patchme');
		$eq($core::$body_raw, "hello");
	}

	public function test_route_trace() {
		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/traceme/', 'TRACE')
			->route('/traceme', function($args){}, 'TRACE');
		# regardless the request, TRACE will always give 405
		self::eq()($core::$code, 405);
	}

	public function test_route_notfound() {
		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/findme/')
			# without this top-level route, $core->shutdown()
			# will set 501
			->route('/', function($args){})
			# must invoke shutdown manually since no path matches
			->shutdown();
		self::eq()($core::$code, 404);
	}

	public function test_route_static() {
		$eq = self::eq();

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/static/' . basename(__FILE__))
			->route('/static/<path>', function($args) use($core, $eq){
				$core->static_file(
					__DIR__ . '/' . $args['params']['path']);
				$eq($core::$code, 200);
				$eq($core::$body_raw, file_get_contents(__FILE__));
			});

		list($rdev, $core) = $this->make_routing();
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

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/notfound')
			->route('/', function($args) use($core) {
				echo "This will never be reached.";
			})
			# invoke shutdown manually
			->shutdown();
		$eq($core::$code, 404);

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/notfound', 'POST')
			->shutdown();
		# POST is never registered, hence, POST request will give 501
		$eq($core::$code, 501);
	}

	public function test_route_redirect() {
		$eq = self::eq();

		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/redirect')
			->route('/redirect', function($args) use($core) {
				$core->redirect('/destination');
			});
		$eq($core::$code, 301);
	}

	public function test_middleware() {
		$eq = self::eq();

		### original callback
		list($_, $core) = $this->make_routing();
		$callback = function($args) use($core) {
			if (!$args['params'])
				return $core::pj([0, 'original']);
			return $core::pj([0, $args['params']['q']]);
		};

		### middlewares
		$mdw1 = function(&$args) {
			$args['params']['q'] = 1;
		};
		$mdw2 = function(&$args, $_core) {
			if ($_core->get_request_method() == 'POST')
				$args['params']['q'] = 2;
		};

		# no middleware
		list($rdev, $core) = $this->make_routing();
		$rdev
			->request('/')
			->route('/', $callback);
		$eq($core::$data, 'original');

		# with one middleware, default priority
		list($rdev, $core) = $this->make_routing();
		$core->add_middleware($mdw1);
		$rdev
			->request('/')
			->route('/', $callback);
		$eq($core::$data, '1');

		# with another middleware, sensitive to request method
		list($rdev, $core) = $this->make_routing();
		$core->add_middleware($mdw1);
		$core->add_middleware($mdw2, 100);
		$rdev
			->request('/')
			->route('/', $callback);
		$eq($core::$data, '1');

		# same middleware, added multiple times
		list($rdev, $core) = $this->make_routing();
		$core->add_middleware($mdw2, 100);
		$core->add_middleware($mdw1);
		$core->add_middleware($mdw2, 100);
		$rdev
			->request('/')
			->route('/', $callback);
		$eq($core::$data, '1');

		# same middleware, different request method
		list($rdev, $core) = $this->make_routing();
		$core->add_middleware($mdw1);
		$core->add_middleware($mdw2, 100);
		$rdev
			->request('/', 'POST')
			->route('/', $callback, 'POST');
		$eq($core::$data, '2');
	}

}
