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
	 * Lightweight debug helper that logs to PHP error log/STDOUT in Docker.
	 */
	private function debugLog($message, $context = [])
	{
		// Avoid exceptions in logging path
		try {
			$prefix = '[TrackerDomain] ';
			if (!empty($context)) {
				$message .= ' ' . json_encode($context);
			}
			error_log($prefix . $message);
		} catch (\Throwable $e) {
			// noop
		}
	}

	/**
	 * Build the target tracker URL string used in Tag Manager MatomoConfiguration variable,
	 * matching Matomo core behavior (force https when configured, otherwise protocol-relative).
	 */
	private function buildTrackerUrlFromConfig(): ?string
	{
		$config = Config::getInstance()->TrackerDomain;
		if (empty($config['url'])) {
			return null;
		}
		$domain = rtrim((string)$config['url'], "/");
		if (SettingsPiwik::isHttpsForced()) {
			return 'https://' . $domain . '/';
		}
		return '//' . $domain . '/';
	}

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
			'TagManager.getDefaultContainerVariable' => 'setDefaultContainerVariable',
            'API.TagManager.addContainerVariable' => 'beforeAddContainerVariable',
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
	 * Ensure the default MatomoConfiguration variable created by Tag Manager uses the tracker domain.
	 */
	public function setDefaultContainerVariable(&$defaultValue, $variableType)
	{
		if ($variableType !== 'MatomoConfiguration') {
			return;
		}
		$this->debugLog('setDefaultContainerVariable called', ['variableType' => $variableType, 'shape' => is_array($defaultValue) ? array_keys($defaultValue) : gettype($defaultValue)]);
		$url = $this->buildTrackerUrlFromConfig();
		if (empty($url)) {
			return;
		}
		$updated = false;
		// Case A: defaultValue has parameters array of name/value
		if (is_array($defaultValue) && isset($defaultValue['parameters']) && is_array($defaultValue['parameters'])) {
			foreach ($defaultValue['parameters'] as &$parameter) {
				if (isset($parameter['name']) && $parameter['name'] === 'matomoUrl') {
					$parameter['value'] = $url;
					$updated = true;
					break;
				}
			}
		}
		// Case B: defaultValue itself is flat map
		if (!$updated && is_array($defaultValue) && array_key_exists('matomoUrl', $defaultValue) && !is_array($defaultValue['matomoUrl'])) {
			$defaultValue['matomoUrl'] = $url;
			$updated = true;
		}
		// Case C: parameters is JSON string
		if (
			!$updated
			&& is_array($defaultValue)
			&& isset($defaultValue['parameters'])
			&& is_string($defaultValue['parameters'])
		) {
			$inner = json_decode($defaultValue['parameters'], true);
			if (is_array($inner)) {
				$inner['matomoUrl'] = $url;
				$defaultValue['parameters'] = json_encode($inner);
				$updated = true;
			}
		}
		$this->debugLog('setDefaultContainerVariable finished', ['updated' => $updated, 'url' => $url]);
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
     * Also add dashboard URL (original Matomo URL) for API requests
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
                // Add dashboard URL (original Matomo URL) for API requests
                $dashboardUrl = SettingsPiwik::getPiwikUrl();
                $out .= '    piwik.dashboardUrl = "'.($dashboardUrl).'"'."\n";
                $this->debugLog('addJsGlobalVariables executed', ['trackerDomain' => $url, 'dashboardUrl' => $dashboardUrl]);
            }
        }
    }

	/**
	 * Ensure the value is set correctly before persisting a new variable.
	 */
	public function beforeAddContainerVariable(&$parameters, ...$rest)
	{
		// Try to detect type from the variable arguments (API may pass different signatures)
		$type = null;
		foreach ($rest as $arg) {
			if (is_string($arg) && $arg === 'MatomoConfiguration') {
				$type = $arg;
				break;
			}
		}
		$this->debugLog('beforeAddContainerVariable called', ['restCount' => count($rest), 'detectedType' => $type, 'paramShapeKeys' => is_array($parameters) ? array_keys($parameters) : gettype($parameters)]);
		$url = $this->buildTrackerUrlFromConfig();
		if (empty($url)) {
			$this->debugLog('beforeAddContainerVariable skipped, no TrackerDomain url configured');
			return;
		}

		// Normalize parameters to array if JSON string is passed
		$wasJsonString = false;
		if (is_string($parameters)) {
			$decoded = json_decode($parameters, true);
			if (is_array($decoded)) {
				$parameters = $decoded;
				$wasJsonString = true;
			}
		}

		if (is_array($parameters)) {
			$updated = false;
			// Case A: parameters is already a flat map like ['matomoUrl' => '...', ...]
			if (array_key_exists('matomoUrl', $parameters) && !is_array($parameters['matomoUrl'])) {
				$parameters['matomoUrl'] = $url;
				$updated = true;
			}
			// Case B: parameters is an array of ['name' => ..., 'value' => ...]
			if (!$updated && isset($parameters[0]) && is_array($parameters[0])) {
				foreach ($parameters as &$param) {
					if (isset($param['name']) && $param['name'] === 'matomoUrl') {
						$param['value'] = $url;
						$updated = true;
					}
				}
			}
			// Case C: parameters array nested under 'parameters' key
			if (!$updated && isset($parameters['parameters']) && is_array($parameters['parameters'])) {
				// Sometimes it's already decoded
				foreach ($parameters['parameters'] as &$param) {
					if (isset($param['name']) && $param['name'] === 'matomoUrl') {
						$param['value'] = $url;
						$updated = true;
					}
				}
				// Or it's a flat array map already
				if (!$updated && array_key_exists('matomoUrl', $parameters['parameters']) && !is_array($parameters['parameters']['matomoUrl'])) {
					$parameters['parameters']['matomoUrl'] = $url;
					$updated = true;
				}
			}
			// Case D: full variable payload with nested JSON string under 'parameters'
			if (
				!$updated
				&& isset($parameters['parameters'])
				&& is_string($parameters['parameters'])
			) {
				$sample = substr($parameters['parameters'], 0, 300);
				$this->debugLog('beforeAddContainerVariable nested JSON present', ['sample' => $sample]);
				$inner = json_decode($parameters['parameters'], true);
				if (is_array($inner)) {
					$prev = isset($inner['matomoUrl']) ? $inner['matomoUrl'] : null;
					// Only set if this is clearly the MatomoConfiguration payload (has matomoUrl) or type matches
					if ((isset($parameters['type']) && $parameters['type'] === 'MatomoConfiguration') || array_key_exists('matomoUrl', $inner)) {
						$inner['matomoUrl'] = $url;
						$parameters['parameters'] = json_encode($inner);
						$updated = true;
						$this->debugLog('beforeAddContainerVariable nested JSON updated', ['prev' => $prev, 'new' => $url]);
					}
				}
			}
			// If the input was JSON, encode it back
			if ($wasJsonString && $updated) {
				$parameters = json_encode($parameters);
			}
			$this->debugLog('beforeAddContainerVariable finished', ['updated' => $updated, 'url' => $url]);
		}
	}
}
