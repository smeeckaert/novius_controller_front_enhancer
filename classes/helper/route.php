<?php

namespace Enhancer;

use Nos\Nos;

class Helper_Route
{
    public function applyParamsToSegments($params, $segments)
    {
        foreach ($segments as $key => $segment) {
            if (\Str::starts_with($segment, ':')) {
                $segments[$key] = $params[$segment];
            }
        }
        return $segments;
    }

    public function getRouteFromSegments($segments)
    {
        $segments = array_filter($segments);
        $listSegments = array();
        foreach ($segments as $segment) {
            if (mb_strpos($segment, '/') !== false) {
                $listSegments = \Arr::merge($listSegments, explode('/', $segment));
            }
            else {
                $listSegments[] = $segment;
            }
        }
        return $listSegments;
    }

    public function getConfigurationSegments($route, $enhancer)
    {
        $options = Controller_Admin_Route::getOptions();
        $helper  = new Helper_Config();
        if (empty($options)) {
            return null;
        }
        $ctrl = Nos::main_controller();
        if (method_exists($ctrl, 'getContext')) {
            $context = $ctrl->getContext();
        } else {
            $context = current(array_keys($options));
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

    public function extractSegments($route)
    {
        if ($route === '/') {
            return array();
        }
        $segments     = array();
        $listSegments = explode('/', $route);

        $countSegment = 0;
        foreach ($listSegments as $segment) {
            $segments[$countSegment++] = $segment;
            // Add a virtual segment after each parameter
            if (\Str::starts_with($segment, ':')) {
                $segments[$countSegment++] = '';
            }
        }
        return $segments;
    }
}