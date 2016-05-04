<?php

namespace Enhancer;

class Helper_Config
{
    /**
     * Get the config for the lib option controller
     * @param $enhancers
     * @return array
     */
    public function getConfig($enhancers)
    {
        $config = array();
        foreach ($enhancers as $name => $enhancer) {
            $config = \Arr::merge($config, $this->getEnhancerConfig($name, $enhancer));
        }
        return $config;
    }

    /**
     * Get the routes of an enhancer
     * @param $enhancer
     * @return mixed
     */
    protected function getRoutes($enhancer)
    {
        $helper      = new Helper_Controller();
        $parsedInfos = $helper->parseUrlEnhancer($enhancer['urlEnhancer']);
        $classname   = $parsedInfos['namespace'].'\\'.$parsedInfos['controller'];
        return $classname::getRoutes();
    }

    /**
     * Return a list of routename => [segments]
     * @param $enhancer
     * @return array
     */
    protected function getRoutesSegments($enhancer)
    {
        $data       = array();
        $routes     = $this->getRoutes($enhancer);
        $routeNames = array_keys($routes);
        $helper     = new Helper_Route();
        foreach ($routeNames as $route) {
            $segments     = $helper->extractSegments($route);
            $data[$route] = $segments;
        }
        return $data;
    }

    /**
     * Make the name of the route more friendly for the crud
     * @param $route
     * @return string
     */
    protected function serializeRoute($route)
    {
        return 'route-'.\Inflector::friendly_title($route);
    }

    /**
     * Get the fields displayed in the configuration for all segments
     * @param $segments
     * @param $enhancerName
     * @return array
     */
    protected function getRouteFields($segments, $enhancerName)
    {
        $groupFields = array();
        foreach ($segments as $route => $parameters) {
            $groupFields[$route] = array('fields' => array());
            foreach ($parameters as $id => $param) {
                $fieldName   = $this->getRouteFieldName($enhancerName, $route, $id);
                $fieldConfig = array(
                    'template' => '{field}',
                    'form'     => array(
                        'type'  => 'text',
                        'value' => $param,
                    ),
                );
                // Params can't be changed
                if (\Str::starts_with($param, Controller_Front_Application_Enhancer::PARAM_SEMAPHOR)) {
                    $fieldConfig['form']['readonly'] = true;
                }
                $groupFields[$route]['fields'][$fieldName] = $fieldConfig;
            }
        }
        return $groupFields;
    }

    /**
     * Serialize a field name for a route to be used as the config key / input name
     * @param $enhancerName
     * @param $route
     * @param $fieldId
     * @return string
     */
    public function getRouteFieldName($enhancerName, $route, $fieldId)
    {
        return $enhancerName."-".$this->serializeRoute($route)."-$fieldId";
    }

    /**
     * Get the lib_option configuration for an enhancer
     * @param $name
     * @param $enhancer
     * @return array
     */
    protected function getEnhancerConfig($name, $enhancer)
    {
        $config               = array();
        $routeSegments        = $this->getRoutesSegments($enhancer);
        $routeFields          = $this->getRouteFields($routeSegments, $name);
        $completeListOfFields = array_reduce(\Arr::pluck($routeFields, 'fields'), 'array_merge', array());
        if (empty($completeListOfFields)) {
            return array();
        }
        $config['layout'] = array(
            'lines' => array(
                array(
                    'cols' => array(
                        array(
                            'col_number' => 12,
                            'view'       => 'nos::form/expander',
                            'params'     => array(
                                'title'   => $enhancer['title'],
                                'options' => array(
                                    'allowExpand' => true,
                                ),
                                'content' => array(
                                    'view'   => 'noviusos_controller_front_enhancer::admin/fields',
                                    'params' => array(
                                        'routeFields' => $routeFields,
                                        'fields'      => array_keys($completeListOfFields),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $config['fields'] = $completeListOfFields;
        return $config;
    }
}