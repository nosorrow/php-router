<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
include_once '../src/Router.php';
include_once 'routes.php';


class RouterTest extends PHPUnit_Framework_TestCase
{
    public $router;

    /**
     * RouterTest constructor.
     */
    public function __construct()
    {
        $this->router = new Router();
    }

    public function testRoteParse()
    {
        $result = $this->router->dispatch('get', 'route3/slug/55');
        $this->assertEquals('Admin3/controller@route_GET', $result['action']);
        $this->assertEquals(['slug' => 'slug', 'id' => 55], $result['params']);
    }

}
