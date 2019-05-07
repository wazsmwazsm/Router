<?php

namespace Http;
/**
 * Http Router
 *
 * @author MirQin https://github.com/wazsmwazsm
 */

use Closure;

class Router 
{
    /**
     * route map tree.
     *
     * @var array
     */
    protected static $_map_tree = array();

    /**
     * allow http request method.
     *
     * @var array
     */
    protected static $_allow_method = array(
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE'
    );

    /**
     * route config filter.
     *
     * @var array
     */
    protected static $_filter = [
        'prefix'     => '',
        'namespace'  => '',
        'middleware' => [],
    ];

    /**
     * for MRouter::get() MRouter::post().
     *
     * @param  string  $method
     * @param  array   $params 
     * @return void
     * @throws RouteException
     */
    public static function __callstatic($method, $params)
    {
        if (count($params) !== 2) {
            throw new RouteException("method $method accept 2 params!", 404);
        }

        if ( ! in_array(strtoupper($method), self::$_allow_method)) {
            throw new RouteException("method $method not allow!", 405);
        }

        self::_setMapTree($method, $params[0], $params[1]);
    }

    /**
     * set group route.
     *
     * @param  array    $filter
     * @param  \Closure  $routes
     * @return void
     */
    public static function group(array $filter, Closure $routes)
    {
        // save sttribute
        $tmp_prefix    = self::$_filter['prefix'];
        $tmp_namespace = self::$_filter['namespace'];
        // set filter path prefix
        if (isset($filter['prefix'])) {
            self::$_filter['prefix'] .= '/'.$filter['prefix'].'/';
        }
        // set filter namespace prefix
        if (isset($filter['namespace'])) {
            self::$_filter['namespace'] .= '\\'.$filter['namespace'].'\\';
        }
        // call route setting
        call_user_func($routes);
        // recover sttribute
        self::$_filter['prefix']     = $tmp_prefix;
        self::$_filter['namespace']  = $tmp_namespace;
    }
    /**
     * dispatch request.
     *
     * @param array $params
     * @return mixed
     * @throws RouteException
     */
    public static function dispatch($path, $method)
    {
        // route exist?
        if (array_key_exists($path, self::$_map_tree) &&
           array_key_exists($method, self::$_map_tree[$path])
        ) {
            // get route infomation
            $callback = self::$_map_tree[$path][$method];  
            return self::_runDispatch($callback, []);
        }

        // route not exist
        $e = new RouteException("route rule path: $path <==> method : $method is not set!", 404);
        throw $e; 
    }

    /**
     * get all routes.
     *
     * @return array
     */
    public static function aGetRoutes()
    {
        return self::$_map_tree;
    }

    /**
     * run dispatch.
     *
     * @param string  $callback 
     * @param array   $params
     * @return mixed
     * @throws RouteException
     */
    protected static function _runDispatch($callback, $params = [])
    {
        // is class
        if (is_string($callback)) {
            // class@method mode
            if ( ! preg_match('/^[a-zA-Z0-9_\\\\]+@[a-zA-Z0-9_]+$/', $callback)) {
                throw new RouteException("Please use controller@method define callback", 404);
            }
            $controller = explode('@', $callback);
            list($class, $method) = [$controller[0], $controller[1]];

            if ( ! class_exists($class) || ! method_exists($class, $method)) {
                $e = new RouteException("Class@method: $callback is not found!", 404);
                throw $e;
            }
            
            return IOCContainer::run($class, $method, $params);
        }
        // is callback
        if (is_callable($callback)) {
            return call_user_func_array($callback, $params);
        }
        
    }

    /**
     * set route map tree.
     *
     * @param  string  $method
     * @param  string  $path
     * @param  string  $content 
     * @return void
     */
    protected static function _setMapTree($method, $path, $content)
    {
        $path     = self::_pathParse(self::$_filter['prefix'].$path);
        $callback = is_string($content) ?
                    self::_namespaceParse('\\'.self::$_filter['namespace'].$content) : $content;
        
        self::$_map_tree[$path][strtoupper($method)] = $callback;
    }

    /**
     * parse path.
     *
     * @param  string  $path
     * @return string
     */
    protected static function _pathParse($path)
    {
        // make path as /a/b/c mode
        $path = ($path == '/') ? $path : '/'.rtrim($path, '/');
        $path = preg_replace('/\/+/', '/', $path);
        return $path;
    }
    /**
     * parse namespace.
     *
     * @param  string  $namespace
     * @return string
     */
    protected static function _namespaceParse($namespace)
    {
        // make namespace as \a\b\c mode
        // why 4 '\' ? see php document preg_replace
        return preg_replace('/\\\\+/', '\\\\', $namespace);
    }

}
