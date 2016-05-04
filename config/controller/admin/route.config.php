<?php

$helper    = new \Enhancer\Helper_Controller();
$enhancers = $helper->getEnhancers();

$config = new \Enhancer\Helper_Config();
$params = $config->getConfig($enhancers);

return \Arr::merge(array(
    'tab'       => array(
        'label'        => __('Route configuration'),
        'iconUrl'      => '/static/apps/noviusos_controller_front_enhancer/img/icons/16.png',
        'iconSize'     => 16,
        'labelDisplay' => true
    ),
    'css'       => array('/static/apps/noviusos_controller_front_enhancer/css/admin/config.css'),
    'form_name' => __('Route configuration'),
    'fields'    => array('test' => []),
), $params
);