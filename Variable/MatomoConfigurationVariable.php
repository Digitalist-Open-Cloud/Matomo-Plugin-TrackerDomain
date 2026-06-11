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

namespace Piwik\Plugins\TrackerDomain\Variable;

use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\SettingsPiwik;
use Piwik\Plugins\TagManager\Template\Variable\MatomoConfigurationVariable as TagManagerMatomoConfigurationVariable;

/**
 * Drop-in replacement for Tag Manager's MatomoConfiguration variable.
 *
 * The only thing it changes is the default value of the "Matomo URL" (matomoUrl)
 * parameter: instead of the Matomo dashboard URL (SettingsPiwik::getPiwikUrl()) it uses
 * the configured tracker domain. Everything else - id, category, validation, and the
 * runtime web.js template - is inherited unchanged from Tag Manager so the variable keeps
 * behaving exactly like the core one.
 *
 * This class lives outside the "Template/Variable" directory on purpose: Tag Manager's
 * VariablesProvider auto-discovers BaseVariable subclasses found in that directory, and we
 * do not want this registered as a second variable type. Instead it is swapped in for the
 * core instance via the TagManager.filterVariables event (see TrackerDomain::filterVariables).
 */
class MatomoConfigurationVariable extends TagManagerMatomoConfigurationVariable
{
    public function getParameters()
    {
        $parameters = parent::getParameters();

        $url = $this->buildTrackerUrl();
        if ($url !== null) {
            foreach ($parameters as $parameter) {
                if ($parameter->getName() === 'matomoUrl') {
                    // getDefaultValue() drives both the persisted value of the auto-created
                    // default variable (Model\Variable::formatParameters) and the value
                    // pre-filled in the Tag Manager UI (BaseTemplate::toArray).
                    $parameter->setDefaultValue($url);
                    break;
                }
            }
        }

        return $parameters;
    }

    /**
     * The runtime container template is resolved from the class file name via reflection
     * (get_class($this) + ".web.js"). This subclass intentionally ships no web.js of its
     * own, so we delegate to the original Tag Manager variable to load its template
     * unchanged - otherwise the MatomoConfiguration variable would be missing from the
     * generated container.
     */
    public function loadTemplate($context, $entity)
    {
        return StaticContainer::get(TagManagerMatomoConfigurationVariable::class)
            ->loadTemplate($context, $entity);
    }

    /**
     * Build the tracker URL, matching Tag Manager core formatting: forced https when
     * configured, otherwise protocol-relative. Returns null when no tracker domain is set.
     */
    private function buildTrackerUrl(): ?string
    {
        $config = Config::getInstance()->TrackerDomain;
        if (empty($config['url'])) {
            return null;
        }
        $domain = rtrim((string) $config['url'], '/');
        if (SettingsPiwik::isHttpsForced()) {
            return 'https://' . $domain . '/';
        }
        return '//' . $domain . '/';
    }
}
