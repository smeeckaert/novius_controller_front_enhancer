<?php

namespace Enhancer;

class Controller_Front_Application_Enhancer extends \Nos\Controller_Front_Application
{
    protected $_routes = array();
    protected $_params = array();
    private $_cacheRoute = null;
    private $_cacheParams = array();

    const ROUTE_SEPARATOR = '/';
    const PARAM_SEMAPHOR  = ':';

    public function action_route()
    {
        $enhancer_url = $this->main_controller->getEnhancerUrl();
        $route        = $this->explodeRoute($enhancer_url);
        $this->initCache();
        $cArgs = count($route);
        if (!isset($this->_cacheRoute[$cArgs])) {
            throw new \Nos\NotFoundException();
        }
        $matchingRoute = $this->findMatchingRoutes($route, $this->_cacheRoute[$cArgs]);
        if (empty($matchingRoute)) {
            throw new \Nos\NotFoundException();
        }
        $action = "action_" . $matchingRoute['action'];
        return $this->$action();
    }

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

    private function extractParameter($str)
    {
        return ($str[0] == Controller_Front_Application_Enhancer::PARAM_SEMAPHOR ? substr($str, 1) : null);
    }

    private function testRoute($route, $parameters)
    {
        $cacheParams = array();
        foreach ($route as $key => $routeElement) {
            $extractParam      = $this->extractParameter($routeElement);
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

    private function testParam($param, $value)
    {
        $paramsInfos = \Arr::merge($this->_params[$param]);
        if (empty($paramsInfos)) {
            return false;
        }
        if (isset($paramsInfos['match'])) {

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
            $model = $this->findModel($paramsInfos, $value);
            if (!empty($model)) {
                return $model;
            }
        }
        return false;
    }

    private function findModel($params, $value)
    {
        $model      = $params['model'];
        $field_name = null;
        $find       = "$model::query";
        if (!is_callable($find)) {
            throw new \Exception("Model must have a query method");
        }

        if (isset($params['field'])) {
            $field_name = $params['field'];
        } else { // We try to find the virtual name by default
            $behaviours = "{$model}::behaviours";
            if (is_callable($behaviours)) {
                $hasVirtualName = call_user_func($behaviours, 'Nos\Orm_Behaviour_Virtualname');
                if ($hasVirtualName) {
                    $field_name = $hasVirtualName['virtual_name_property'];
                }
                $hasVPath = call_user_func($behaviours, 'Nos\Orm_Behaviour_Virtualpath');
                if ($hasVPath) {
                    $field_name = $hasVPath['virtual_name_property'];
                }

            }
        }
        if (empty($field_name)) {
            return null;
        }
        $query = call_user_func($find);
        if (get_class($query) != 'Nos\Orm\Query') {
            throw new \Exception("Query method must return a Nos\Orm\Query");
        }
        return $query->where($field_name, $value)->get_one();
    }

    protected function route_param($param)
    {
        if (!isset($this->_cacheParams[$param])) {
            return null;
        }
        return $this->_cacheParams[$param];
    }

    private function explodeRoute($route)
    {
        return array_values(array_filter(explode(Controller_Front_Application_Enhancer::ROUTE_SEPARATOR, $route)));
    }

    private function initCache()
    {
        if ($this->_cacheRoute !== null) {
            return;
        }
        $this->_cacheRoute = array();
        foreach ($this->_routes as $route => $action) {
            $route = $this->explodeRoute($route);
            $c     = count($route);
            if (!isset($this->_cacheRoute[$c])) {
                $this->_cacheRoute[$c] = array();
            }
            $this->_cacheRoute[$c][] = array('route' => $route, 'action' => $action);
        }
    }
}