<?php
/**
 * Plugin Name: Tracker Domain (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/TrackerDomain
 * Description: Set Domain for js trackers, useful when your UI is on another domain.
 * Author: Mikke Schirén
 * Author URI: https://github.com/digitalist-se/TrackerDomain
 * Version: 0.2.2
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
                $piwikBase = rtrim(str_replace(array('http://', 'https://'), '', SettingsPiwik::getPiwikUrl()), '/');
                $containerJs = $piwikBase . '/' . trim(StaticContainer::get('TagManagerContainerWebDir'), '/') .'/';
                if (is_string($returnedValue)) {
                    $returnedValue = str_replace($containerJs, $url . '/js/', $returnedValue);
                } elseif (is_array($returnedValue)) {
                    foreach ($returnedValue as &$val) {
                        if (!empty($val['embedCode'])) {
                            $val['embedCode'] = str_replace($containerJs, $url . '/js/', $val['embedCode']);
                        }
                    }
                }
                $returnedValue = str_replace($containerJs, $url . '/', $returnedValue);
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
            $url = $config['url'];
            if (isset($url)) {
                $out .= '    piwik.trackerDomain = "'.($url).'"'."\n";
            }
        }
    }
}
