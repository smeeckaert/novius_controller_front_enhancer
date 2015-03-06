<?php

namespace Enhancer;

class Controller_Front_Application_Enhancer extends \Nos\Controller_Front_Application
{
    protected static $_routes = array();
    protected static $_params = array();
    protected static $_cacheRoute = null;
    private $_cacheParams = array();
    protected static $_cacheProperty = null;

    const ROUTE_SEPARATOR = '/';
    const PARAM_SEMAPHOR  = ':';

    /**
     * Retrieve a route parameter
     *
     * @param $param name of the parameter
     *
     * @return null|mixed
     */
    protected function routeParam($param)
    {
        if (!isset($this->_cacheParams[$param])) {
            return null;
        }
        return $this->_cacheParams[$param];
    }


    public static function getUrlEnhanced($params = array())
    {
        $bestRouteParameters = 0;
        $bestRoute           = null;
        if (isset($params['route'])) {
            return static::buildRoute($params, static::explodeRoute($params['route']));
        }
        // Find route with the more matching parameters
        foreach (static::$_cacheRoute as $count => $cachedRoutes) {
            foreach ($cachedRoutes as $key => $route) {
                $match      = true;
                $matchCount = 0;
                // Try to match all parameters
                foreach ($route['route'] as $param) {
                    $extract = static::extractParameter($param);
                    if (!empty($extract) && empty($params[$extract])) {
                        $match = false;
                        break;
                    }
                    if (!empty($extract)) {
                        $matchCount++;
                    }
                }
                // if we match the more parameter, keep this route
                if ($match && $matchCount > $bestRouteParameters) {
                    $bestRouteParameters = $matchCount;
                    $bestRoute           = $route;
                }
            }
        }
        if (!empty($bestRoute)) {
            return static::buildRoute($params, $bestRoute['route']);
        }
        return false;
    }


    public function action_route()
    {
        $enhancer_url = $this->main_controller->getEnhancerUrl();
        $route        = $this->explodeRoute($enhancer_url);
        static::initCache();
        $cArgs = count($route);
        if (!isset(static::$_cacheRoute[$cArgs])) {
            throw new \Nos\NotFoundException();
        }
        $matchingRoute = $this->findMatchingRoutes($route, static::$_cacheRoute[$cArgs]);
        if (empty($matchingRoute)) {
            throw new \Nos\NotFoundException();
        }
        $action = "action_" . $matchingRoute['action'];
        return $this->$action();
    }

    /**
     * Returns the name of a parameter or null if it's not a parameter
     *
     * @param $str
     *
     * @return null|string
     */
    private static function extractParameter($str)
    {
        return ($str[0] == Controller_Front_Application_Enhancer::PARAM_SEMAPHOR ? substr($str, 1) : null);
    }

    /**
     * Build a an url according to a route and parameters
     *
     * @param $params
     * @param $route
     *
     * @return string
     */
    protected static function buildRoute($params, $route)
    {
        static::initCacheProperty();
        // Replace route parameters with values
        $route = array_map(function ($i) use ($params) {
            $extract = static::extractParameter($i);
            if (!empty($extract)) {
                if (isset(static::$_cacheProperty[$extract])) {
                    $prop = static::$_cacheProperty[$extract];
                    return $params[$extract]->$prop;
                } else {
                    return $params[$extract];
                }
            }
            return $i;
        }, $route);
        return implode('/', $route) . '.html';
    }


    /**
     * Find the first matching route
     *
     * @param $route
     * @param $cachedRoutes
     *
     * @return null
     */
    private function findMatchingRoutes($route, $cachedRoutes)
    {
        $matchingRoute = null;
        foreach ($cachedRoutes as $testingRoute) {
            if (($params = $this->testRoute($testingRoute['route'], $route)) !== false) { // Test matching with current route
                $matchingRoute      = $testingRoute;
                $this->_cacheParams = $params;
                break;
            }
        }
        return $matchingRoute;
    }


    /**
     * Test if all arguments of the route are filled with good values
     *
     * @param $route
     * @param $parameters
     *
     * @return array|bool
     */
    private function testRoute($route, $parameters)
    {
        $cacheParams = array();
        foreach ($route as $key => $routeElement) {
            $extractParam      = static::extractParameter($routeElement);
            $matchingParameter = $parameters[$key];
            if ($extractParam) {
                $matchingElement = $this->testParam($extractParam, $matchingParameter);
                if (empty($matchingElement)) { // Matching parameters
                    return false;
                }
                $cacheParams[$extractParam] = $matchingElement;
            } elseif ($routeElement != $parameters[$key]) { // Matching string parts of the route
                return false;
            }
        }
        return $cacheParams;
    }

    /**
     * Test if a param match his configuration
     *
     * @param $param
     * @param $value
     *
     * @return bool|null
     * @throws \Exception
     */
    private function testParam($param, $value)
    {
        $paramsInfos = static::$_params[$param];
        if (empty($paramsInfos)) { // No params, we always match this parameter
            return $value;
        }
        if (isset($paramsInfos['match'])) { // Callable property to match the variable
            if (!is_callable($paramsInfos['match'])) {
                throw new \Exception("Match parameter must be callable");
                return null;
            } else {
                if ($paramsInfos['match']($value)) {
                    return $value;
                }
                return false;
            }
        }
        if (isset($paramsInfos['model'])) {
            $model = $this->findModel($param, $paramsInfos, $value);
            if (!empty($model)) {
                return $model;
            }
        }
        return false;
    }

    /**
     * Try to find a model matching the route parameters
     *
     * @param $paramKey
     * @param $params
     * @param $value
     *
     * @return null|Model
     * @throws \Exception
     */
    private function findModel($paramKey, $params, $value)
    {
        $model      = $params['model'];
        $field_name = null;
        $find       = "$model::query";
        if (!is_callable($find)) {
            throw new \Exception("Model must have a query method");
        }

        static::initCacheProperty();
        $field_name = \Arr::get(static::$_cacheProperty, $paramKey);
        if (empty($field_name)) {
            return null;
        }
        $query = call_user_func($find);
        if (get_class($query) != 'Nos\Orm\Query') {
            throw new \Exception("Query method must return a Nos\Orm\Query");
        }
        return $query->where($field_name, $value)->get_one();
    }

    /**
     * Put in cache database property of models fields
     * Will try VirtualName and VirtualPath enhancers if no fields are given
     *
     * Then the database field will be stored in $_cacheProperty[name]
     */
    protected static function initCacheProperty()
    {
        if (static::$_cacheProperty !== null) {
            return;
        }
        foreach (static::$_params as $key => $params) {
            $model      = \Arr::get($params, 'model');
            $field_name = null;

            if (isset($params['field'])) {
                $field_name = $params['field'];
            } elseif ($model) {
                $behaviours     = "{$model}::behaviours";
                $hasVirtualName = call_user_func($behaviours, 'Nos\Orm_Behaviour_Virtualname');
                if ($hasVirtualName) {
                    $field_name = $hasVirtualName['virtual_name_property'];
                }
                $hasVPath = call_user_func($behaviours, 'Nos\Orm_Behaviour_Virtualpath');
                if ($hasVPath) {
                    $field_name = $hasVPath['virtual_name_property'];
                }
            }
            if (!empty($field_name)) {
                static::$_cacheProperty[$key] = $field_name;
            }
        }
        if (isset(static::$_cacheProperty['route'])) {
            throw new \Exception("You can't use 'route' as a parameter name");
        }
    }


    /**
     * Explode a route into segments
     *
     * @param $route
     *
     * @return array
     */
    private static function explodeRoute($route)
    {
        return array_values(array_filter(explode(Controller_Front_Application_Enhancer::ROUTE_SEPARATOR, $route)));
    }

    /**
     * Init the cache route
     * Will store routes by size in $_cacheRoute
     */
    private static function initCache()
    {
        if (static::$_cacheRoute !== null) {
            return;
        }
        static::$_cacheRoute = array();
        foreach (static::$_routes as $route => $action) {
            $route = static::explodeRoute($route);
            $c     = count($route);
            if (!isset(static::$_cacheRoute[$c])) {
                static::$_cacheRoute[$c] = array();
            }
            static::$_cacheRoute[$c][] = array('route' => $route, 'action' => $action);
        }
    }
}