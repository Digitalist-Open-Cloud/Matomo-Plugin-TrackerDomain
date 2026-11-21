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

namespace Piwik\Plugins\TrackerDomain;

use Exception;
use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\CoreAdminHome\Controller as CoreAdminController;

/**
 * Defines Settings for TrackerDomain.
 *
 * Usage like this:
 * $settings->trackerdomain->getValue();
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $trackerdomain;

    protected function init()
    {
        $isWritable = Piwik::hasUserSuperUserAccess() && CoreAdminController::isGeneralSettingsAdminEnabled();
        $this->trackerdomain = $this->createTrackerDomainSetting();
        $this->trackerdomain->setIsWritableByCurrentUser($isWritable);
    }

    private function createTrackerDomainSetting()
    {
        return $this->makeSettingManagedInConfigOnly('TrackerDomain', 'url', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('TrackerDomain_SettingDomain');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('TrackerDomain_SettingDomainHelp');
            $field->prepare = function ($value) {
                return TrackerDomain::normalizeDomain((string) $value);
            };
            // The domain ends up in tracking code and in the pages of Matomo.
            $field->validate = function ($value) {
                if ($value !== '' && !TrackerDomain::isValidDomain($value)) {
                    throw new Exception(Piwik::translate('TrackerDomain_ExceptionInvalidDomain'));
                }
            };
        });
    }
}
