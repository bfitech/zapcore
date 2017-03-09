<?php


require(__DIR__ . './../vendor/autoload.php');

use BFITech\ZapCore as zc;

$logger = new zc\Logger(zc\Logger::DEBUG, '/tmp/zc.log');
$core = new zc\Router(null, null, true, $logger);

$core->route('/', function($args) use($core) {
	echo "Hello Friend";
});

# route errors
$core->route('/#', function($args){});
$core->route('/xnocb', null);
$core->route('/xtrace', function($args){}, 'TRACE');
$core->route('/x1/<path>/x2/{path}', function($args){});

$core->route('/', function($args) use($core) {
	$core->print_json(0, [
		'home' => $core->get_home(),
		'host' => $core->get_host(),
	]);
}, 'POST');

$core->route('/raw', function($args) use($core) {
	$core->print_json(0, $args['post']);
}, 'POST', true);

$core->route('/json', function($args) use($core) {
	if ($args['method'] == 'GET')
		$core->print_json(0, 1);
	$core->print_json(1, null, 403);
}, ['GET', 'POST']);

$core->route('/1/2/thing', function($args) use($core) {
	$core->print_json(0, $core->get_request_comp());
});

$core->route(
	'/some/<var1>/other/<var2>/thing',
	function($args) use($core) {
		$core->print_json(0, $args);
});

$core->route(
	'/some/{dir}/that/ends/with/<file>',
	function($args) use($core) {
		$core->print_json(0, $args);
});

$core->route('/some/thing', function($args) use($core){
	extract($args['get'], EXTR_SKIP);
	if (!isset($var1) || !isset($var2))
		$core->abort(404);
	$core->redirect("/some/$var1/other/$var2/thing");
});

$core->route('/put/it/down', function($args) use($core){
	$method = $core->get_request_method();
	$data = $args[strtolower($method)];
	$core->print_json(0, [$method, $data]);
}, ['PUT', 'POST']);

