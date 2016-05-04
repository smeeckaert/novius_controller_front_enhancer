<?php

namespace Enhancer;

use Nos\Nos;

class Helper_Route
{
    /**
     * Apply a list of segments to the route
     * @param $params
     * @param $segments
     * @return mixed
     */
    public function applyParamsToSegments($params, $segments)
    {
        foreach ($segments as $key => $segment) {
            if (\Str::starts_with($segment, Controller_Front_Application_Enhancer::PARAM_SEMAPHOR)) {
                $segments[$key] = $params[$segment];
            }
        }
        return $segments;
    }

    /**
     * Create a route usable by the controller from a list of segments
     * @param $segments
     * @return array
     */
    public function getRouteFromSegments($segments)
    {
        $segments     = array_filter($segments);
        $listSegments = array();
        foreach ($segments as $segment) {
            if (mb_strpos($segment, Controller_Front_Application_Enhancer::ROUTE_SEPARATOR) !== false) {
                $listSegments = \Arr::merge($listSegments, explode(Controller_Front_Application_Enhancer::ROUTE_SEPARATOR, $segment));
            } else {
                $listSegments[] = $segment;
            }
        }
        return $listSegments;
    }

    /**
     * Get the configured segment for a route of an enhancer
     * By default takes the current context but it can be overriden
     * @param $route
     * @param $enhancer
     * @param null $context
     * @return array|null
     */
    public function getConfigurationSegments($route, $enhancer, $context = null)
    {
        $options = Controller_Admin_Route::getOptions();
        $helper  = new Helper_Config();
        if (empty($options)) {
            return null;
        }
        $ctrl = Nos::main_controller();
        if (empty($context)) {
            if (method_exists($ctrl, 'getContext')) {
                $context = $ctrl->getContext();
            } else {
                $context = current(array_keys($options));
            }
        }

        $options = \Arr::get($options, $context);
        if (empty($options)) {
            return $this->extractSegments($route);
        }

        $segments = $this->extractSegments($route);

        foreach ($segments as $id => $segment) {
            $nameField     = $helper->getRouteFieldName($enhancer, $route, $id);
            $value         = isset($options[$nameField]) ? $options[$nameField] : $segment;
            $segments[$id] = $value;
        }
        return $segments;
    }

    /**
     * Extract all segments for a route, add customisable segments behind each parameters
     * @param $route
     * @return array
     */
    public function extractSegments($route)
    {
        if ($route === Controller_Front_Application_Enhancer::ROUTE_SEPARATOR) {
            return array();
        }
        $segments     = array();
        $listSegments = explode(Controller_Front_Application_Enhancer::ROUTE_SEPARATOR, $route);

        $countSegment = 0;
        foreach ($listSegments as $segment) {
            $segments[$countSegment++] = $segment;
            // Add a virtual segment after each parameter
            if (\Str::starts_with($segment, Controller_Front_Application_Enhancer::PARAM_SEMAPHOR)) {
                $segments[$countSegment++] = '';
            }
        }
        return $segments;
    }
}