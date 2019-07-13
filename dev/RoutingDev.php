<?php declare(strict_types=1);


namespace BFITech\ZapCoreDev;


/**
 * Mock routing.
 *
 * Use this class to instantiate a router and perform requests on it
 * without manually manipulating HTTP variables.
 *
 * Pardon the class name. It's one of hard problems in CS. :\
 */
class RoutingDev {

	/** RouterDev instance. */
	public static $core;

	/**
	 * Constructor.
	 *
	 * @param RouterDev $core RouterDev instance. Optional. Since the
	 *     router this sets is made public and static, you can always
	 *     patch as you go.
	 */
	public function __construct(RouterDev $core=null) {
		self::$core = ($core != null) ? $core : new RouterDev;
	}

	/**
	 * Fake request.
	 *
	 * Use this to simulate HTTP request. To set args to callback
	 * handler without relying on collected HTTP variables, use
	 * $args.
	 *
	 * @param string $request_uri Simulated request URI.
	 * @param string $request_method Simulated request method.
	 * @param array $args Simulated callback args.
	 * @param array $cookie Simulated cookies.
	 * @return RouterDev instance, useful for chaining from this method
	 *     to $core->route().
	 */
	public function request(
		string $request_uri=null, string $request_method='GET',
		array $args=[], array $cookie=[]
	) {
		self::$core->deinit()->reset();
		self::$core->config('home', '/');
		$_SERVER['REQUEST_URI'] = $request_uri
			? $request_uri : '/';
		$_SERVER['REQUEST_METHOD'] = $request_method;
		$_COOKIE = $cookie;
		if ($args)
			self::$core->override_callback_args($args);
		return self::$core;
	}

}
