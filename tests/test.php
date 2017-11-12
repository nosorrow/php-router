<?php
//error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
include_once '../src/Router.php';
include_once 'routes.php';

$match ='';
$route_str = '';
$start = microtime(true);
$router = new Router;
$x = 1;

echo '<pre><p>' . str_pad(' Route match ', 100, '*', STR_PAD_BOTH) . '</p>';

// router match uri
for ($i=0; $i<$x; $i++) {
    $match = $router->dispatch('get', '45');
    //$match = $router->dispatch('get', 'route-test/slug.pdf');
   // $match = $router->dispatch('get', 'route100/slug/0');
    //$match = $router->dispatch('get', 'route149/test/2/3/14/10/6/7/8/90');
}
var_dump($match);

printf('<p>Time: %f sec. | memory: %f KB</p>', microtime(true)-$start, memory_get_peak_usage()/1024);
echo '<p>' . str_pad(' Route name ', 100, '*', STR_PAD_BOTH) . '</p>';

// generate URI from route
for ($i=0; $i<$x; $i++) {

  $route_str = $router->route('index', ['45'])->route;

 //   $route_str = $router->route('lang-fr',['lang'=>'fr', 'slug'=>'your-post-slug'], 'get')->route;

    //$route_str = $router->route('route_booking', ['id'=>'55', 'name'=>'plamen'], 'get')->route;
    $url = $router->site_url($route_str);


}
echo '<br><br> URI string: ';
print_r($route_str);

echo "<br> Site URL: ";
print_r($url);
printf('<p>Time:  %f sec. | memory: %f KB</p>', microtime(true)-$start, memory_get_peak_usage()/1024);
echo '<p>' . str_pad(" Bench for $x ", 100, '*', STR_PAD_BOTH) . '</p>';
echo '<pre>';
//var_dump($_SERVER);