<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TrackerDomain;

use Piwik\Config;

class TrackerDomain extends \Piwik\Plugin
{

    public function registerEvents()
    {
        return [
            'Tracker.getJavascriptCode' => 'setPiwikUrl',
        ];
    }

    /**
     * Set the URL from config,
     */

    public function setPiwikUrl(&$codeImpl, $parameters)
    {
        $config = Config::getInstance()->TrackerDomain;
        $url = $config['url'];
        if (isset($url)) {
            $codeImpl["piwikUrl"] = $url;
        }
    }
}
