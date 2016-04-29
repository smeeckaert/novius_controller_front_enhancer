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
    'form_name' => __('Route configuration'),
    'fields'    => array('test'=>[]),
), $params
/*'layout'    => array(
    'lines' => array(
        array(
            'cols' => array(
                array(
                    'col_number' => 12,
                    'view'       => 'nos::form/expander',
                    'params'     => array(
                        'title'   => __('Configuration des tarifs par défaut'),
                        'options' => array(
                            'allowExpand' => false,
                        ),
                        'content' => array(
                            'view'   => 'nos::form/fields',
                            'params' => array(
                                'fields' => array(// 'tarifs',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
),
'fields'    => array(),*/
);