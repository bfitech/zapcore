<?php declare(strict_types=1);


namespace BFITech\ZapCoreDev;


/**
 * Mock routing.
 *
 * Use this class to instantiate a router and perform requests on it
 * without manually manipulating HTTP variables. Should be used in
 * conjunction with RouterDev.
 *
 * Pardon the class name. It's one of hard problems in CS. :\
 *
 * ### example
 * @code
 * <?php
 *
 * $rdev = new RoutingDev;
 * $core = $rdev::$core;
 *
 * $rdev
 *     ->request('/hello/world', 'PUT', ['put' => ['say' => 'what']])
 *     ->route('/hello/<who>', function ($args) {
 *         assert($args['put']['say'] == 'what');
 *         assert($args['params']['who'] = 'world';
 *     }, 'PUT');
 *
 * $rdev->request('/hello/there', 'POST');
 * $_POST = ['say' => 'what'];
 * $core->route('/hello/<where>', function($args) {
 *     assert($args['post']['say'] == 'what');
 *     assert($args['params']['where'] = 'there';
 * });
 * @endcode
 */
class RoutingDev {

	/** RouterDev instance. */
	public static $core;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$core = new RouterDev;
	}

	/**
	 * Simulate HTTP request.
	 *
	 * HTTP vars that are consumed by router callbacks can be simulated
	 * by setting `$_GET`, `$_POST`, `$_FILES` and `$_REQUEST` directly,
	 * but this is not possible for PUT, PATCH, DELETE, and raw POST.
	 *
	 * To simulate proper args to the callbacks, use $args for more
	 * flexibility and also to prevent accidental HTTP var leaks to
	 * another test after subsequent runs. When $args is not null, all
	 * HTTP vars collected from globals are completely ignored.
	 *
	 * Simulating request headers by setting `$_SERVER`[`'HTTP_*'`]
	 * still works.
	 *
	 * @param string $request_uri Simulated request URI.
	 * @param string $request_method Simulated request method.
	 * @param array $args Simulated callback args, dict with keys:
	 *     'get', 'post', 'files', 'put', 'patch', 'delete'.
	 * @param array $cookie Simulated cookies.
	 * @return Modified RouterDev instance, useful for chaining from
	 *     this method to $core->route().
	 */
	public function request(
		string $request_uri=null, string $request_method='GET',
		array $args=null, array $cookie=[]
	) {
		self::$core->reset();
		self::$core->config('home', '/');

		# just set global cookie and nothing else
		$_COOKIE = $cookie;

		# will set matching route and populate callback args from
		# globals
		$_SERVER['REQUEST_URI'] = $request_uri
			? $request_uri : '/';
		$_SERVER['REQUEST_METHOD'] = $request_method;

		# will override collected callback args and reset the rest to
		# empty array
		if ($args !== null) {
			foreach ([
				'get', 'post', 'files', 'put', 'patch', 'delete'
			] as $key) {
				$args[$key] = array_key_exists($key, $args)
					? $args[$key] : [];
			}
			self::$core->override_callback_args($args);
		}

		return self::$core;
	}

}
