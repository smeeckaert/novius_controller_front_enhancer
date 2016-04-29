<?php

namespace Enhancer;


use Nos\Config_Data;

class Helper_Controller
{

    /**
     * Get the list of all urlEnhancer from the class $parentClass
     * @param string $parentClass
     * @return array
     */
    public function getEnhancers($parentClass = '\Enhancer\Controller_Front_Application_Enhancer')
    {
        $urlsEnhancers = $this->getAllUrlEnhancers();
        return $this->filterEnhancers($urlsEnhancers, $parentClass);
    }

    /**
     * Filter a list of urlEnhancer configuration to exclude everything that is not a Controller_Front_Application_Enhancer
     * @param $list
     * @return array
     */
    protected function filterEnhancers($list, $parentClass)
    {
        return array_filter($list, function ($infos) use ($parentClass) {
            $parsedInfos = $this->parseUrlEnhancer($infos['urlEnhancer']);
            $classname   = $parsedInfos['namespace'].'\\'.$parsedInfos['controller'];
            return is_subclass_of($classname, $parentClass) || $classname == $parentClass;
        });
    }

    /**
     * Code copied from Tools_Enhancer::_urls
     * Return the namespace and the class of the controller of an enhancer
     * @param $urlEnhancer
     * @return array
     */
    public function parseUrlEnhancer($urlEnhancer)
    {
        // Calculate the classname of the enhancer's Controller
        // eg. noviusos_blog would match to Nos\Blog\Controller_Front
        $parts            = explode('/', $urlEnhancer);
        $application_name = $parts[0];
        // Replace the application name with 'controller'
        $parts[0] = 'controller';
        // Remove the action
        array_pop($parts);
        // We're left with the fuel Controller classname!
        $controller_name = implode('_', $parts);
        $controller_name = \Inflector::words_to_upper($controller_name);

        // Check if the application exists
        $namespace = Config_Data::get('app_namespaces.'.$application_name, '');
        if (empty($namespace)) {
            return array();
        }
        return array('namespace' => $namespace, 'controller' => $controller_name);
    }

    /**
     * Get the list of all url enhancers available
     * @return array|mixed
     */
    protected function getAllUrlEnhancers()
    {
        $enhancerList = Config_Data::get('enhancers');
        $enhancerList = array_filter($enhancerList, function ($a) {
            return !empty($a['urlEnhancer']);
        });
        return $enhancerList;
    }

}