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
use Piwik\Plugins\TrackerDomain\Variable\MatomoConfigurationVariable;

if (
    defined('ABSPATH')
    && function_exists('add_action')
) {
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
     * A host name, with an optional port and path.
     */
    private const DOMAIN_PATTERN = '~^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:[0-9]{1,5})?(/[a-z0-9._\~/-]*)?$~i';

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
            'TagManager.filterVariables' => 'filterVariables',
            'SitesManager.getImageTrackingCode' => 'setImageTrackerUrl',
            'API.SitesManager.getImageTrackingCode.end' => 'updateImageTrackerUrl',
        ];
    }

    /**
     * The tracker domain as it is written: without protocol and without a slash at the end.
     */
    public static function normalizeDomain(string $domain): string
    {
        return rtrim((string) preg_replace('~^(https?:)?//~i', '', trim($domain)), '/');
    }

    public static function isValidDomain(string $domain): bool
    {
        return preg_match(self::DOMAIN_PATTERN, $domain) === 1;
    }

    /**
     * The tracker domain that is set, or null when none is set. A value that is not a domain is
     * left out, as it ends up in tracking code and in the pages of Matomo.
     */
    public static function getDomain(): ?string
    {
        $config = Config::getInstance()->TrackerDomain;
        $domain = self::normalizeDomain((string) ($config['url'] ?? ''));

        return self::isValidDomain($domain) ? $domain : null;
    }

    /**
     * Set the URL to the tracking target from config,
     */
    public function setPiwikUrl(&$codeImpl, $parameters)
    {
        $domain = self::getDomain();
        if ($domain !== null) {
            $codeImpl["piwikUrl"] = $domain;
        }
    }

    /**
     * Replace Tag Manager's MatomoConfiguration variable with our own so the "Matomo URL"
     * (matomoUrl) parameter defaults to the configured tracker domain instead of the
     * Matomo dashboard URL. This fixes both the auto-created default variable on new sites
     * and the value pre-filled in the Tag Manager UI, without touching the dashboard URL.
     */
    public function filterVariables(&$variables)
    {
        if (self::getDomain() === null) {
            return;
        }
        foreach ($variables as $index => $variable) {
            if ($variable->getId() === 'MatomoConfiguration') {
                $variables[$index] = StaticContainer::get(MatomoConfigurationVariable::class);
            }
        }
    }

    /**
     * Set the URL to the tagmanager target from config,
     */
    public function setTagManagerUrl(&$returnedValue, $extraInfo)
    {
        $domain = self::getDomain();
        if ($domain === null || !Plugin\Manager::getInstance()->isPluginActivated('TagManager')) {
            return;
        }

        $matomoBase = rtrim(str_replace(array('http://', 'https://'), '', SettingsPiwik::getPiwikUrl()), '/');
        $containerJs = $matomoBase . '/' . trim(StaticContainer::get('TagManagerContainerWebDir'), '/') . '/';
        if (is_string($returnedValue)) {
            $returnedValue = str_replace($containerJs, $domain . '/js/', $returnedValue);
        } elseif (is_array($returnedValue)) {
            foreach ($returnedValue as &$val) {
                if (!empty($val['embedCode'])) {
                    $val['embedCode'] = str_replace($containerJs, $domain . '/js/', $val['embedCode']);
                }
            }
        }
    }

    /**
     * Expose JS globals consumed by other plugins:
     *
     *  - piwik.trackerDomain: the configured tracker domain (documented public variable).
     *  - piwik.dashboardUrl:  the real Matomo (dashboard) URL. This is a cross-plugin
     *    contract: because the MatomoConfiguration matomoUrl now points at the tracker
     *    domain, plugins that need to reach the Matomo API (e.g. UserFeedback) read this
     *    to route API requests to the dashboard instead of the tracker domain. Do not
     *    remove without updating those consumers.
     */
    public function addJsGlobalVariables(&$out)
    {
        $domain = self::getDomain();
        if ($domain === null) {
            return;
        }

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $out .= '    piwik.trackerDomain = ' . json_encode($domain, $flags) . ";\n";
        $out .= '    piwik.dashboardUrl = ' . json_encode((string) SettingsPiwik::getPiwikUrl(), $flags) . ";\n";
    }

    /**
     * Builds the image tracker (no-JS <img> fallback) code with the tracker domain.
     */
    public function setImageTrackerUrl(&$piwikUrl)
    {
        $domain = self::getDomain();
        if ($domain !== null) {
            $piwikUrl = $domain;
        }
    }

    /**
     * Rewrites the image tracker code for a Matomo that does not ask for the URL first, and
     * makes it use HTTPS.
     */
    public function updateImageTrackerUrl(&$returnValue)
    {
        $domain = self::getDomain();
        $currentDomain = parse_url((string) SettingsPiwik::getPiwikUrl(), PHP_URL_HOST);
        if ($domain === null || !$currentDomain || !is_string($returnValue)) {
            return;
        }

        $returnValue = str_replace($currentDomain, $domain, $returnValue);
        $returnValue = preg_replace('#https?://+#', 'https://', $returnValue);
        $returnValue = preg_replace('#//+#', '//', $returnValue);
    }
}
