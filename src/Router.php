<?php

/*
 * Class Router
 * https://github.com/nosorrow/php-router
 *
 * Маршрутизация с използване на регулярни изрази по идея на "nikic"
 * https://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
 *
 */

class Router
{
    /**
     * @var array
     */
    protected static $allowed_methods = ['GET', 'POST', 'HEAD', 'PUT', 'PATCH', 'DELETE'];
    /**
     * @var
     */
    public static $site_down;
    /**
     * @var array
     */
    protected static $rawRoutes = [];
    /**
     * @var array
     */
    protected $regexRoute = [];
    /**
     * @var array
     */
    protected $routeRegexCollect = [];
    /**
     * @var
     */
    public $route;
    /**
     * @var
     */
    public $url;
    /**
     * @var array
     */
    protected $matches = [];

    protected $countOptional = 0;

    /**
     * Router constructor.
     */
    public function __construct()
    {
        $this->routeRegexCollect = $this->routeCollector();
    }

    /**
     * @param $method
     * @param $uri
     * @param $action
     * @throws \Exception
     */
    public static function addroute($method, $uri, $action)
    {
        $method = strtoupper($method);

        if (!isset(self::$rawRoutes[$method][$uri])) {

            if (isset($action['name'])) {

                self::dublicateRouteName($method, $action['name']);

            }

            self::$rawRoutes[$method][$uri] = (array)$action;

        } else {
            throw new \Exception(sprintf('Duplicate route: %s ', $uri));
        }
    }

    /**
     * @param $httpMethod
     * @param $route_name
     * @throws \Exception
     */
    protected static function dublicateRouteName($httpMethod, $route_name)
    {
        //skip: Notice: Undefined index”
        $r = isset(self::$rawRoutes[$httpMethod]) ? self::$rawRoutes[$httpMethod] : '';
        //skip Warning: Invalid argument supplied for foreach()
        if (is_array($r)) {
            // check for Duplicate
            foreach ($r as $method => $action) {
                if (isset($action['name']) && $route_name === $action['name']) {
                    throw new \Exception(sprintf('Duplicate [httpMethod: %s  route name: %s]', $httpMethod, $route_name));
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function getRawRoutes()
    {
        return self::$rawRoutes;
    }

    /**
     * @param $wildcard
     * @return mixed
     */
    protected function replaceWildcardWithRegex($wildcard)
    {
        $regex = ['#\{([\w]+?)\}#',
            '#\/\{([\w]+?)\?\}#',
            '#\{([\w]+?):([^{}]*(\{(?-2)\}[^{}]*)*)\}#', //'#\{([\w]+?):([^{}]*(\{(?-1)\}[^{}]*)*)\}#'
            '#\/\{([\w]+[?]):([^{}]*(\{(?-2)\}[^{}]*)*)\}#'];

        $replace = ['([^/]+)', '(?:\/?)([^/]*)', '([^/]+)', '(?:\/?)([^/]*)'];
        $wildcard = $this->bindWordRoute($wildcard);

        return preg_replace($regex, $replace, $wildcard);
    }

    protected function bindWordRoute($route)
    {
        $new = [];
        $arr = explode('/', $route);
        foreach ($arr as $key => $value) {
            if (isset($value[0]) && $value[0] !== '{') {
                $value = '\b' . $value . '\b';
            }

            $new[] = $value;
        }
        return implode('/', $new);
    }

    /**
     * @param $uri
     * @return array
     * @throws \Exception
     */
    protected function parseParameters($uri)
    {
        preg_match_all('#\{([^/]+)*?\}#', $uri, $matches);

        $_parameters = array_map(function ($a) {
            if (!false == strpos($a, ':')) {
                $a = substr($a, 0, strpos($a, ':'));
            }
            return trim($a, '?');
        }, $matches[1]);

        $_regex = array_map(function ($a) {
            if (!false == strpos($a, ':')) {
                $a = substr($a, strpos($a, ':') + 1);
            } else {
                $a = null;
            }
            return trim($a);
        }, $matches[1]);

        $parameters = [];
        foreach ($_parameters as $key => $value) {
            if (isset($parameters[$value])) {
                throw new \Exception(sprintf('Duplicate parameters [ %s ]', $value));
            } else {
                $parameters[$value] = $_regex[$key];
            }
        }
        return $parameters;
    }

    /**
     * @param $arg_pattern
     * @param $arg_values
     * @return bool
     */
    protected function parametersPatternMatch($arg_pattern, $arg_values)
    {
        $r = true;
        $format = '';

        foreach (array_values($arg_pattern) as $key => $value) {
            if ($value == '') {
                continue;
            }

            if (strpos($value, 'format') !== false) {
                parse_str($value, $output);
                $value = str_replace($value, '^([a-z0-9_-]+)(?:\.' . $output['format'] . ')*$', $value);
            }

            if (isset($arg_values[$key])) {
                if (!preg_match_all('~^' . $value . '$~', $arg_values[$key])) {
                    $r = false;
                    break;
                } else {
                    $r = true;
                }
            }
        }
        return $r;
    }

    /**
     * @param array $param_name
     * @param array $param_val
     * @return mixed
     */
    protected function compileParameters(array $param_name, array $param_val)
    {
        $parameters = [];
        $format_value = (array_values($param_name));

        foreach (array_keys($param_name) as $key => $value) {
            $parameters[$key]['paramname'] = $value;

            if ($format_value[$key] != '') {
                $paramvalue = substr($param_val[$key], 0, strrpos($param_val[$key], '.'));
                $parameters[$key]['paramvalue'] = ((bool)$paramvalue == false) ? $param_val[$key] : $paramvalue;

            } else {
                $parameters[$key]['paramvalue'] = $param_val[$key];
            }
        }
        $parameters = array_column($parameters, 'paramvalue', 'paramname');

        return $this->param_filter($parameters);
    }

    /**
     * @param array $parameters
     * @return array
     */
    protected function param_filter(array $parameters)
    {
        foreach ($parameters as $k => $v) {
            if ($v === null) {
                unset($parameters[$k]);
            }
        }

        return array_filter($parameters, function ($x) {
            if ($x === null) {
                $x = '1';
            }
            return ($x !== '');
        });
    }

    /**
     * @return array
     */
    protected function makeRouteRegex()
    {
        foreach ($this->getRawRoutes() as $key => $value) {
            foreach ($value as $k => $v) {
                $v['parameters'] = $this->parseParameters($k);
                $k = $this->replaceWildcardWithRegex($k);
                $this->regexRoute[$key][$k] = $v;
            }
        }

        return $this->regexRoute;
    }

    /**
     * @param array $array
     * @return array
     */
    protected function getRegexWithGroups(array $array)
    {
        $regex = [];
        $routeMap = [];
        $numGroups = 0;
        foreach ($array as $key => $value) {
            if (strpos($key, '(') !== false) {
                $numArguments = count($value['parameters']);
                $numGroups = max($numGroups, $numArguments);

                $regex_key = $key . str_repeat('()', $numGroups - $numArguments);
                $regex[] = $regex_key;
                $routeMap[$numGroups + 1] = $value;

                ++$numGroups;
            }
        }
        $regex = '#^(?|' . implode('|', $regex) . ')$#';

        return ['regex' => $regex, 'routeMap' => $routeMap];

    }

    /**
     * @param $needle
     * @param $haystack
     * @return bool|int|string
     */
    protected function recursiveRouteNameSearch($needle, $haystack)
    {
        foreach ($haystack as $key => $value) {
            if (isset($value['name'])) {
                if ($needle !== $value['name']) {
                    continue;
                }

                return $key;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function routeCollector()
    {
        $collect = [];
        foreach ($this->makeRouteRegex() as $method => $value) {
            $split = array_chunk($value, 10, true);
            $collect[$method] = array_map([$this, 'getRegexWithGroups'], $split);
        }

        return $collect;

    }

    /**
     * @param $httpMethod
     * @param $uri
     * @return array|mixed
     * @throws \Exception
     */
    public function dispatch($httpMethod, $uri)
    {
        $httpMethod = strtoupper($httpMethod);

        if (!in_array($httpMethod, self::$allowed_methods)) {
            throw new \Exception(sprintf('Wrong http method %s', $httpMethod));
        }
        $routes = self::$rawRoutes;

        if (isset(self::$site_down)) {
            if (!is_string(self::$site_down) && is_callable(self::$site_down)) {
                $this->matches = call_user_func(self::$site_down);

            } else {
                $this->matches['action'] = self::$site_down;
            }
            return $this->matches;
        }

        // ако има съвпадение ОК!
        if (isset($routes[$httpMethod][$uri])) {
            if (!is_string($routes[$httpMethod][$uri][0]) && is_callable($routes[$httpMethod][$uri][0])) {
                $this->matches = call_user_func($routes[$httpMethod][$uri][0]);
                exit;
            } else {
                $this->matches['action'] = ($routes[$httpMethod][$uri][0]);

            }

            return $this->matches;
        }
        /*
         * Ако няма директно подадение (имаме аргументи в Uri)
         * get route Collection Array
         *
         * */
        $routeCollect = $this->routeRegexCollect;

        foreach ($routeCollect[$httpMethod] as $key => $value) {

            if (!preg_match($value['regex'], $uri, $matches)) {
                continue;
            }

            $count = count($matches);

            array_shift($matches);
            // параметрите от url
            $parameters = $this->param_filter($matches);

            if (true == $this->parametersPatternMatch($value['routeMap'][$count]['parameters'], $parameters)) {
                if (!is_string($value['routeMap'][$count][0]) && is_callable($value['routeMap'][$count][0])) {
                    $this->matches = call_user_func_array($value['routeMap'][$count][0], $parameters);
                    exit;

                } else {
                    $this->matches['action'] = $value['routeMap'][$count][0];
                    if (count($parameters) !== 0){
                        $params = $this->compileParameters($value['routeMap'][$count]['parameters'], $parameters);

                    } else {
                        $params = [];
                    }
                    $this->matches['params'] = $params;
                }

                return $this->matches;
            }
        }// end foreach

        if ($httpMethod == 'HEAD') {
            if (isset($routes['GET'][$uri])) {
                $this->matches['action'] = ($routes['GET'][$uri][0]);
                return $this->matches;
            } else {
                return $this->dispatch('GET', $uri);
            }
        }

        throw new \Exception(sprintf('Route not found: { %s } | { %s }', $httpMethod, $uri));
    }

    /**
     * $this->route( 'routename', [param, param-1, param2] );
     * @param $routename
     * @param array $params
     * @return $this
     * @throws \Exception
     */
    public function route($routename, array $arguments = [], $request_method = null)
    {
        $routes = self::$rawRoutes;

        if ($request_method == null) {
            $httpmethod = ($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        } else {
            $httpmethod = strtoupper($request_method);
        }

        // има ли route name ?
        $route = $this->recursiveRouteNameSearch($routename, $routes[$httpmethod]);

        if (!$route) {
            throw new \Exception(sprintf('Route name: %s is not found in router.php', $routename));
        }

        // Има ли параметри в route
        // \{.*?\} -> pattern for papameters
        if (preg_match_all('#\{([^/]+)*?\}#', $route, $matches)) {
            $parameters = array_map(function ($a) {
                if (!false == strpos($a, ':')) {
                    $a = substr($a, 0, strpos($a, ':'));
                }
                // ще преброим опционалните параметри
                if(strpos($a, '?') !== false){
                    $this->countOptional ++;
                }

                return rtrim($a, '?');
            }, $matches[1]);

            /*
            * ако има параметри , но не са подадени аргументите ->
            *  Router::get('route/{slug}/{id?}', ['controller@action', 'name'=>'name']);
            *  $router->route('name') ще хвърли Exeption
            */
            $count_argument = count($parameters);
            $count_params = count($arguments);

            // Ако има опционални параметри то може да не са подадени.
            // Router::get('route/{slug}/{id?}', ['controller@action', 'name'=>'name']);
            // route('name', ['slug'=>'blabla']
            /* if ($count_argument !== $count_params) { */
            if ($count_argument > ($count_params + $this->countOptional)) {
                throw new \Exception(sprintf('An array of %d values must be passed to the route. An array of %d values has been received',
                    $count_argument, $count_params), 500);
            }

            // ще замества wildcard с подаден аргумент
            // в примерно  post/{id} ще замени id с подадено число
            $pattern = '#\{%s\}|\{%s\?\}|\{%s:[\s\S]+?\}|\{%s\?:[\s\S]+?\}#';

            // Aко имаме опционални параметри, но не подаваме аргуенти
            if (empty($arguments)) {
                $pattern_array = array_map(function ($a) use ($pattern){
                    return sprintf($pattern, $a, $a, $a, $a);
                    //return '#\{' . $a . '\}|\{' . $a . '\?\}|\{' . $a . ':[\s\S]+?\}|\{' . $a . '\?:[\s\S]+?\}#';
                }, $parameters);

                $_route = rtrim(preg_replace($pattern_array, $arguments, $route), '/');
                $this->route = str_replace('//','/', $_route);
                return $this;
            }

            /*
            * Ако $params е индексиран масив, параметрите ще бъдат
            * поставени в URI последователно
            * Router::get('route/{slug}/{id}', ['controller@action', 'name'=>'name']);
            * $router->route('name', ['p1', 'p2']) ще върне route/p1/p2
            */
            $isIndexed = count(array_filter(array_keys($arguments), 'is_string')) < count(array_keys($arguments));

            if ($isIndexed === true) {
                $diff = array_diff_key($arguments, $parameters);

                if (!empty($diff)) {
                    // Ako е подаден грешен ключ в масива $params
                    throw new \Exception('Router say : Wrong set parameter in route ' . implode(' | ', $diff), 500);
                }

                $pattern_array = array_map(function ($a) use ($pattern) {
                    return sprintf($pattern, $a, $a, $a, $a);
                    // return '#\{' . $a . '\}|\{' . $a . '\?\}|\{' . $a . ':[\s\S]+?\}|\{' . $a . '\?:[\s\S]+?\}#';

                }, $parameters);

                $this->route = preg_replace($pattern_array, $arguments, $route);

            } else {
                /*
                * Ако  $params е асоциативен масив - ще постави параметрите
                * в URI на техните правилни позиции
                */
                $diff = array_diff(array_keys($arguments), $parameters);

                if (!empty($diff)) {
                    throw new \Exception('Router say : Wrong pass route parameter ' . implode(' | ', $diff), 500);
                }

                $_array = array_map(function ($a) use ($pattern){
                    return sprintf($pattern, $a, $a, $a, $a);
                    // return '#\{' . $a . '\}|\{' . $a . '\?\}|\{' . $a . ':[\s\S]+?\}|\{' . $a . '\?:[\s\S]+?\}#';

                }, array_keys($arguments));

                $pattern_array = array_combine($_array, array_values($arguments));

                foreach ($pattern_array as $pattern => $replacement) {
                    $route = preg_replace($pattern, $replacement, $route);
                }
                // ако са подадени по-малко аргументи изчистваме wildcard от route
                $this->route = preg_replace("#\{[^/]*?\}#", '', $route);
            }

        } else {
            $routekey = trim($route);

            $_params = (!empty($arguments)) ? '/' . implode('/', $arguments) : '';

            $this->route = $routekey . $_params;
        }

        if (!$this->route) {
            throw new \Exception(sprintf('Router say : Route name: %s is not found in router.php', $routename), 500);

        } else {
            $this->route = rtrim($this->route, '/');

        }

        return $this;
    }


    public function site_url($uri = null)
    {
        $request_scheme = (!isset($_SERVER['REQUEST_SCHEME'])) ? 'http' : $_SERVER['REQUEST_SCHEME'];
        $site_url = $request_scheme . '://' . $_SERVER['HTTP_HOST'] .
            substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], basename($_SERVER['SCRIPT_FILENAME'])))
            . $uri;

        return $site_url;
    }

    /**
     * $this->route( 'routename', [param, param-1 ] )->redirect();
     *
     * @param null $uri
     */
    public function redirect($uri = null)
    {
        if ($uri === null) {
            $uri = $this->route;
        }

        header("location:" . $this->site_url($uri));
    }

    /**
     * @param $uri
     * @param $action
     * @return array
     * @throws \Exception
     */
    public static function get($uri, $action)
    {
        self::addroute('GET', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @return array
     * @throws \Exception
     */
    public static function post($uri, $action)
    {
        self::addroute('POST', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @throws \Exception
     */
    public static function head($uri, $action)
    {
        self::addroute('head', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @throws \Exception
     */
    public static function put($uri, $action)
    {
        self::addroute('put', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     */
    public static function patch($uri, $action)
    {
        self::addroute('patch', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @throws \Exception
     */
    public static function delete($uri, $action)
    {
        self::addroute('delete', $uri, $action);
    }

    /**
     * @param array $httpMethod
     * @param $uri
     * @param $action
     * @throws \Exception
     */
    public static function methods(array $httpMethod, $uri, $action)
    {
        foreach ($httpMethod as $method) {
            self::addroute($method, $uri, $action);
        }
    }

    /**
     * @param $uri
     * @param $action
     */
    public static function any($uri, $action)
    {
        foreach (self::$allowed_methods as $method) {
            self::addroute($method, $uri, $action);
        }
    }

    /**
     * @param $action
     */
    public static function site_down($action)
    {
        self::$site_down = $action;
    }

    /**
     * @param $name
     * @param $arguments
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        throw new \Exception(printf('Invoke inaccessible method ( %s )', $name));
    }

    /**
     * @param $name
     * @param $arguments
     * @throws Exception
     */
    public static function __callStatic($name, $arguments)
    {
        throw new \Exception(sprintf('Invoke inaccessible static method ( %s )', $name));
    }
}
