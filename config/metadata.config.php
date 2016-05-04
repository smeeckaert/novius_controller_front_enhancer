<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2013 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link       http://www.novius-os.org
 */

return array(
    'name'      => __('Controller Front Enhancer'),
    'version'   => '0.1-alpha',
    'provider'  => array(
        'name' => 'Smeeckaert Martin',
    ),
    'require' => array('lib_options'),
    'namespace' => 'Enhancer',
    'icons'     => array(
        64 => 'static/apps/noviusos_controller_front_enhancer/img/icons/64.png',
        32 => 'static/apps/noviusos_controller_front_enhancer/img/icons/32.png',
        16 => 'static/apps/noviusos_controller_front_enhancer/img/icons/16.png',
    ),
    'launchers' => array(
        'Enhancer::Routes'   => array(
            'name'   => 'Routes', // displayed name of the launcher
            'icon'   => 'static/apps/noviusos_controller_front_enhancer/img/icons/64.png',

            'action' => array(
                'action' => 'nosTabs',
                'tab'    => array(
                    'url'     => 'admin/noviusos_controller_front_enhancer/route/form', // url to load
                    'iconUrl' => 'static/apps/noviusos_controller_front_enhancer/img/icons/32.png',
                ),
            ),
        ),

    ),
    'enhancers' => array()
);