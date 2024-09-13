<?php
/**
 * The TrackerDomain plugin for Matomo.
 *
 * Copyright (C) 2024 Digitalist Open Cloud <cloud@digitalist.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TrackerDomain;

use Piwik\Config;
use Piwik\Plugin;
use Piwik\SettingsPiwik;
use Piwik\Container\StaticContainer;

if (defined('ABSPATH')
&& function_exists('add_action')) {
    $path = '/matomo/app/core/Plugin.php';
    if (defined('WP_PLUGIN_DIR') && WP_PLUGIN_DIR && file_exists(WP_PLUGIN_DIR . $path)) {
        require_once WP_PLUGIN_DIR . $path;
    } elseif (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR && file_exists(WPMU_PLUGIN_DIR . $path)) {
        require_once WPMU_PLUGIN_DIR . $path;
    } else {
        return;
    }
    add_action('plugins_loaded', function () {
        if (function_exists('matomo_add_plugin')) {
            matomo_add_plugin(__DIR__, __FILE__, true);
        }
    });
}

class TrackerDomain extends Plugin
{


     /**
     * These are the events that we want to use.
     */
    public function registerEvents()
    {
        return [
            'Tracker.getJavascriptCode' => 'setPiwikUrl',
            'API.TagManager.getContainerEmbedCode.end' => 'setTagManagerUrl',
            'API.TagManager.getContainerInstallInstructions.end' => 'setTagManagerUrl',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
        ];
    }

    /**
     * Set the URL to the tracking target from config,
     */

    public function setPiwikUrl(&$codeImpl, $parameters)
    {
        $config = Config::getInstance()->TrackerDomain;
        if (isset($config['url'])) {
            $url = $config['url'];
            $codeImpl["piwikUrl"] = $url;
        }
    }

    /**
     * Set the URL to the tagmanager target from config,
     */

    public function setTagManagerUrl(&$returnedValue, $extraInfo)
    {
        $pluginManager = Plugin\Manager::getInstance();
        if ($pluginManager->isPluginActivated('TagManager')) {
            $config = Config::getInstance()->TrackerDomain;
            if (isset($config['url'])) {
                $url = $config['url'];
            }
            if (isset($url)) {
                $matomoBase = rtrim(str_replace(array('http://', 'https://'), '', SettingsPiwik::getPiwikUrl()), '/');
                $containerJs = $matomoBase . '/' . trim(StaticContainer::get('TagManagerContainerWebDir'), '/') .'/';
                if (is_string($returnedValue)) {
                    $returnedValue = str_replace($containerJs, $url . '/js/', $returnedValue);
                } elseif (is_array($returnedValue)) {
                    foreach ($returnedValue as &$val) {
                        if (!empty($val['embedCode'])) {
                            $val['embedCode'] = str_replace($containerJs, $url . '/js/', $val['embedCode']);
                        }
                    }
                }
            }
        }
    }

    /**
     * Add TrackerDomain as a global variable (piwik.trackerDomain)
     */
    public function addJsGlobalVariables(&$out)
    {
        $config = Config::getInstance()->TrackerDomain;
        if (isset($config)) {
            if (isset($config['url'])) {
                $url = $config['url'];
            }
            if (isset($url)) {
                $out .= '    piwik.trackerDomain = "'.($url).'"'."\n";
            }
        }
    }
}
