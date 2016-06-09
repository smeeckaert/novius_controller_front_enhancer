<?php

namespace Enhancer;

class Controller_Front_Application_Enhancer extends \Nos\Controller_Front_Application
{
    protected static $_routes = array();
    protected static $_params = array();
    /**
     * A cache of each routes sorted this way:
     * [classname =>
     *      [number of segments in the route] => [
     *          [route => [list of segments], routeconfiguration...]
     *      ]
     * ]
     * @var null
     */
    protected static $_cacheRoute = null;
    /**
     * The same thing as $_cacheRoute but with the configured route
     * The route may be the same but the number of segments can change
     * @var null
     */
    protected static $_cacheRouteConfigured = null;
    /**
     * This array is a junction array between $_cacheRoute et $_cacheRouteConfigured.
     * It used the same indexes as $_cacheRoute but instead of route configuration it contains an array ['nb','key']
     * refering respectively to the 2nd and 3rd dimension of the $_cacheRouteConfigured
     * @var null
     */
    protected static $_cacheRouteToConfigured = null;
    protected $_cacheParams = array();
    /**
     * The cache of route parameters
     * @var null
     */
    protected static $_cacheProperty = null;
    /**
     * The context in which the cache has been forcibly created for the last time
     * @var null
     */
    protected static $_cachedContext = null;
    /**
     * The matched route
     * @var null|array
     */
    protected $_matchedRoute = null;


    const ROUTE_SEPARATOR = '/';
    const PARAM_SEMAPHOR = ':';

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


    /**
     * Return the configured routes
     * @return array
     */
    public static function getRoutes()
    {
        return static::$_routes;
    }

    /**
     * Create the url
     * @param array $params
     * @return bool|string
     */
    public static function getUrlEnhanced($params = array())
    {
        $hasContextProperty  = !empty($params['context']);
        $bestRouteParameters = 0;
        $bestRoute           = null;
        $cleanCache          = false;
        // If we are building an url for another context, simply clean the cache if it has changed
        // That way the configuration will take the context into account
        if ($hasContextProperty && static::$_cachedContext != $params['context']) {
            $cleanCache             = true;
            static::$_cachedContext = $params['context'];
            static::cleanCacheRoute();
        }
        if (isset($params['route'])) {
            $bestRoute = array('route' => static::getRouteConfigured($params['route']));
        } else {
            static::initCache(\Arr::get($params, 'context'));
            $class = get_called_class();
            // Find route with the more matching parameters
            if (!empty(static::$_cacheRoute[$class])) {
                foreach (static::$_cacheRoute[$class] as $count => $cachedRoutes) {
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
                            // We are matching a non configured route, so we need to access the configured one before building it
                            $routeToConfigure = static::$_cacheRouteToConfigured[$class][$count][$key];
                            $bestRoute        = static::$_cacheRouteConfigured[$class][$routeToConfigure['nb']][$routeToConfigure['key']];
                        }
                    }
                }
            }
        }
        if ($cleanCache) {
            static::cleanCacheRoute();
        }
        if (!empty($bestRoute)) {
            return static::buildRoute($params, $bestRoute['route']);
        }
        return false;
    }

    /**
     * The default action for the url enhancer, will match the route, retrieve the parameters, ...
     * @param array $args
     * @return mixed
     * @throws \Nos\NotFoundException
     */
    public function action_route($args = array())
    {
        if (method_exists($this->main_controller, 'getEnhancerUrl')) {
            $enhancer_url = $this->main_controller->getEnhancerUrl();
        }
        $route = $this->explodeRoute($enhancer_url);
        static::initCache();
        $cArgs = count($route);
        $class = get_called_class();

        if (!isset(static::$_cacheRouteConfigured[$class][$cArgs])) {
            throw new \Nos\NotFoundException();
        }
        $this->_matchedRoute = $this->findMatchingRoutes($route, static::$_cacheRouteConfigured[$class][$cArgs]);
        if (empty($this->_matchedRoute)) {
            throw new \Nos\NotFoundException();
        }
        $this->routeConfig($this->_matchedRoute);
        $action = "action_".$this->_matchedRoute['action'];
        return $this->format($this->$action($args), \Arr::get($this->_matchedRoute, 'format'), \Arr::get($this->_matchedRoute, 'raw'));
    }

    /**
     * @return string
     */
    public static function input()
    {
        return \Input::is_ajax() ? 'ajax' : 'page';
    }

    /**
     * Add configuration options for the route
     * @param $route
     */
    protected function routeConfig($route)
    {
        if (!isset($route['cache'])) {
            $route['cache'] = array(
                array(
                    'type'     => 'callable',
                    'callable' => "\Enhancer\Controller_Front_Application_Enhancer::input",
                    'args'     => array()
                ),
            );
        }
        if (!empty($route['cache'])) {
            $this->setCacheRoute($route['cache']);
        }
    }

    /**
     * Add cache suffixes if configured
     * @param $infos
     */
    protected function setCacheRoute($infos)
    {
        if (\Arr::get($infos, 'disable')) {
            \Nos\Nos::main_controller()->disableCaching();
        } else {
            \Nos\Nos::main_controller()->addCacheSuffixHandler($infos);
        }
    }

    /**
     * Format the output depending on the input method and the format property of a route
     * @param $data
     * @param null $format
     * @param bool $raw
     * @return mixed
     */
    protected function format($data, $format = null, $raw = false)
    {
        $content = $data;
        if ($format === 'json') {
            if (is_array($content)) {
                $content = json_encode($content);
            }
            $this->main_controller->setHeader('Content-Type', 'application/json');
        }
        if ((\Input::is_ajax()) || $raw) {
            return $this->main_controller->sendContent($content);
        }
        return $data;
    }

    /**
     * Returns the name of a parameter or null if it's not a parameter
     *
     * @param $str
     *
     * @return null|string
     */
    protected static function extractParameter($str)
    {
        return (!empty($str) && $str[0] == Controller_Front_Application_Enhancer::PARAM_SEMAPHOR ? substr($str, 1) : null);
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
        $class       = get_called_class();
        // Replace route parameters with values
        foreach ($route as $key => $i) {
            $extract = self::extractParameter($i);
            $v       = $i;
            if (!empty($extract)) {
                if (isset(static::$_cacheProperty[$class][$extract])) {
                    $prop = static::$_cacheProperty[$class][$extract];
                    try {
                        $v = $params[$extract]->$prop;
                    } catch (\Exception $e) {
                        $v = null;
                    }
                } else {
                    $v = $params[$extract];
                }
                if (!empty(static::$_params[$extract]['format'])) {
                    $v = static::callback(static::$_params[$extract]['format'], array($params[$extract], true));
                }
            }
            $route[$key]     = $v;
        }

        return implode('/', $route).'.html';
    }

    /**
     * Find the first matching route
     *
     * @param $route
     * @param $cachedRoutes
     *
     * @return null
     */
    protected function findMatchingRoutes($route, $cachedRoutes)
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
     * Gets the matched route
     *
     * @return null|array
     */
    public function getMatchedRoute()
    {
        return $this->_matchedRoute;
    }



    /**
     * Test if all arguments of the route are filled with good values
     *
     * @param $route
     * @param $parameters
     *
     * @return array|bool
     */
    protected function testRoute($route, $parameters)
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
                if (!empty(static::$_params[$extractParam]['format'])) {
                    try {
                        $matchingElement = static::callback(static::$_params[$extractParam]['format'], array($matchingElement, false));
                    } catch (\UnexpectedValueException $e) {
                        return false;
                    }
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
    protected function testParam($param, $value)
    {
        $paramsInfos = static::$_params[$param];
        if (empty($paramsInfos['match']) && empty($paramsInfos['model'])) { // No params, we always match this parameter
            return $value;
        }
        if (isset($paramsInfos['match'])) {
            // Callable property to match the variable
            $match = static::callback($paramsInfos['match'], array($value));
            if ($match) {
                return $value;
            }
            return false;
        }
        if (isset($paramsInfos['model'])) {
            $model = $this->findModel($param, $paramsInfos, $value);
            if (!empty($model)) {
                return $model;
            }
        }
        return null;
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
    protected function findModel($paramKey, $params, $value)
    {
        $model         = $params['model'];
        $field_name    = null;
        $find          = "$model::query";
        $isContextable = $model::behaviours('Nos\Orm_Behaviour_Twinnable');
        if (!is_callable($find)) {
            throw new \Exception("Model must have a query method");
        }

        static::initCacheProperty();
        $class      = get_called_class();
        $field_name = \Arr::get(static::$_cacheProperty[$class], $paramKey);
        if (empty($field_name)) {
            return null;
        }
        $query = call_user_func($find);
        if (get_class($query) != 'Nos\Orm\Query') {
            throw new \Exception("Query method must return a Nos\Orm\Query");
        }

        $where = array(
            array($field_name, $value),
        );
        if ($isContextable) {
            $where[] = array($isContextable['context_property'], $this->main_controller->getPage()->page_context);
        }

        return $query->where($where)->get_one();
    }

    protected static function callback($cb, $params)
    {
        if (!is_callable($cb)) {
            $className = get_called_class();
            if (method_exists($className, $cb)) {
                $cb = $className.'::'.$cb;
            }
        }
        if (is_callable($cb)) {
            return call_user_func_array($cb, $params);
        }
        throw new \Exception("$cb must be callable");
    }


    /**
     * Put in cache database property of models fields
     * Will try VirtualName and VirtualPath enhancers if no fields are given
     *
     * Then the database field will be stored in $_cacheProperty[name]
     */
    protected static function initCacheProperty()
    {
        $class = get_called_class();
        if (isset(static::$_cacheProperty[$class])) {
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
                static::$_cacheProperty[$class][$key] = $field_name;
            }
        }
        if (isset(static::$_cacheProperty[$class]['route'])) {
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
    protected static function explodeRoute($route)
    {
        return array_values(array_filter(explode(Controller_Front_Application_Enhancer::ROUTE_SEPARATOR, $route)));
    }

    /**
     *  Clean the route cache, but not the parameter cache
     */
    protected static function cleanCacheRoute()
    {
        static::$_cacheRoute             = null;
        static::$_cacheRouteConfigured   = null;
        static::$_cacheRouteToConfigured = null;
    }

    /**
     * Init the cache route
     * Will store routes by size in $_cacheRoute
     */
    protected static function initCache($context = null)
    {
        $class = get_called_class();
        if (isset(static::$_cacheRoute[$class])) {
            return;
        }
        static::$_cacheRoute[$class] = array();
        foreach ($class::$_routes as $route => $action) {
            $route = $class::explodeRoute($route);
            $c     = count($route);
            if (!isset(static::$_cacheRoute[$class][$c])) {
                static::$_cacheRoute[$class][$c] = array();
            }
            $data = array('route' => $route);
            if (is_array($action)) {
                $data = \Arr::merge($data, $action);
            } else {
                $data['action'] = $action;
            }

            static::$_cacheRoute[$class][$c][] = $data;
        }
        static::initConfigCache($context);
    }

    /**
     * Get the configured route segments
     * @param $route
     * @return array
     */
    protected static function getRouteConfigured($route)
    {
        $routeHelper      = new Helper_Route();
        $controllerHelper = new Helper_Controller();
        $currentClass     = $controllerHelper->getEnhancers(get_called_class());
        $enhancerName     = current(array_keys($currentClass));
        $segments         = $routeHelper->getConfigurationSegments($route, $enhancerName);
        if (!empty($segments)) {
            return array_filter($segments);
        }
        return static::explodeRoute($route);
    }

    /**
     * Store configured routes into cache
     * @param null $context
     */
    protected static function initConfigCache($context = null)
    {
        $class = get_called_class();
        if (isset(static::$_cacheRouteConfigured[$class])) {
            return;
        }
        $routeHelper                           = new Helper_Route();
        $controllerHelper                      = new Helper_Controller();
        $currentClass                          = $controllerHelper->getEnhancers(get_called_class());
        $enhancerName                          = current(array_keys($currentClass));
        static::$_cacheRouteConfigured[$class] = array();
        foreach (static::$_cacheRoute[$class] as $nbParams => $routeList) {
            if (!isset(static::$_cacheRouteToConfigured[$class][$nbParams])) {
                static::$_cacheRouteToConfigured[$class][$nbParams] = array();
            }
            foreach ($routeList as $key => $route) {
                $routePath = '/'.implode('/', $route['route']);
                $segments  = $routeHelper->getConfigurationSegments($routePath, $enhancerName, $context);
                if (!empty($segments)) {
                    $route['route'] = $routeHelper->getRouteFromSegments($segments);
                }
                $countRoute = count($route['route']);
                if (!isset(static::$_cacheRouteConfigured[$class][$countRoute])) {
                    static::$_cacheRouteConfigured[$class][$countRoute] = array();
                }
                $keyConfigure = count(static::$_cacheRouteConfigured[$class][$countRoute]);

                // We put the relationship to the new route
                static::$_cacheRouteToConfigured[$class][$nbParams][$key]          = array('nb' => $countRoute, 'key' => $keyConfigure);
                static::$_cacheRouteConfigured[$class][$countRoute][$keyConfigure] = $route;
            }
        }
    }
}
