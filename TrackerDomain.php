<?php

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
        ];
    }

    /**
     * Set the URL to the tracking target from config,
     */

    public function setPiwikUrl(&$codeImpl, $parameters)
    {
        $config = Config::getInstance()->TrackerDomain;
        $url = $config['url'];
        if (isset($url)) {
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
            $url = $config['url'];

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
}
