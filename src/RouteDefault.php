<?php declare(strict_types=1);


namespace BFITech\ZapCore;



/**
 * Route default class.
 *
 * This contains essential methods for Router to properly work out of
 * the box. Each methods can be indirectly patched by declaring their
 * custom counterparts from within Route class, e.g. Route::abort_custom
 * to override RouteDefault::abort_default.
 */
abstract class RouteDefault extends Header {

	/**
	 * Default static file.
	 *
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	final protected function static_file_default(
		string $path, array $kwargs
	) {
		extract(Common::extract_kwargs($kwargs, [
			'cache' => 0,
			'disposition' => null,
			'headers' => [],
			'reqheaders' => [],
			'noread' => false,
			'callback_notfound' => function() {
				return $this->abort_default(404);
			},
		]));
		static::send_file($path, $cache, $disposition, $headers,
			$reqheaders, $noread, $callback_notfound);
		static::halt();
	}

	/**
	 * Default abort method.
	 */
	final protected function abort_default($code) {
		extract(self::get_header_string($code));
		static::start_header($code);
		$uri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);
		echo "<!doctype html>
<html>
	<head>
		<meta charset=utf-8>
		<meta name=viewport
			content='width=device-width, initial-scale=1.0,
				user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans-serif;}
			div  {background-color: #fff; border: 1px solid #ddd;
			      padding: 25px; max-width:800px;
			      margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>$code $msg</h1>
			<p>The URL <tt>&#039;<a href='$uri'>$uri</a>&#039;</tt>
			   caused an error.</p>
		</div>
	</body>
</html>";
		static::halt();
	}

	/**
	 * Default redirect.
	 */
	final protected function redirect_default(string $destination) {
		extract(self::get_header_string(301));
		static::start_header($code, 0, [
			"Location: $destination",
		]);
		$dst = htmlspecialchars($destination, ENT_QUOTES);
		echo "<!doctype html>
<html>
	<head>
		<meta charset='utf-8'/>
		<meta name=viewport
			content='width=device-width, initial-scale=1.0,
				user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans-serif;}
			div  {background-color: #fff; border: 1px solid #ddd;
			      padding: 25px; max-width:800px;
			      margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>$code $msg</h1>
			<p>See <tt>&#039;<a href='$dst'>$dst</a>&#039;</tt>.</p>
		</div>
	</body>
</html>";
		static::halt();
	}

}
