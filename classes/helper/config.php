<?php

namespace Enhancer;

class Helper_Config
{
    public function getConfig($enhancers)
    {
        $config = array();
        foreach ($enhancers as $name => $enhancer) {
            $config = \Arr::merge($config, $this->getEnhancerConfig($name, $enhancer));
        }
        return $config;
    }

    protected function getRoutes($enhancer)
    {
        $helper      = new Helper_Controller();
        $parsedInfos = $helper->parseUrlEnhancer($enhancer['urlEnhancer']);
        $classname   = $parsedInfos['namespace'].'\\'.$parsedInfos['controller'];
        return $classname::getRoutes();
    }

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

    protected function serializeRoute($route)
    {
        return 'route-'.\Inflector::friendly_title($route);
    }

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
                // We don't create field for params
                if (\Str::starts_with($param, ':')) {
                    $fieldConfig['form']['readonly'] = true;
                }
                $groupFields[$route]['fields'][$fieldName] = $fieldConfig;
            }
        }
        return $groupFields;
    }

    public function getRouteFieldName($enhancerName, $route, $fieldId)
    {
        return $enhancerName."-".$this->serializeRoute($route)."-$fieldId";
    }

    protected function getEnhancerConfig($name, $enhancer)
    {
        $config               = array();
        $routeSegments        = $this->getRoutesSegments($enhancer);
        $routeFields          = $this->getRouteFields($routeSegments, $name);
        $completeListOfFields = array_reduce(\Arr::pluck($routeFields, 'fields'), 'array_merge', array());

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
            )
        );


        $config['fields'] = $completeListOfFields;

        return $config;
    }
}