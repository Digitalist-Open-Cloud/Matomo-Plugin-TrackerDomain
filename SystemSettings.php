<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TrackerDomain;
use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Validators\NotEmpty;
use Piwik\Plugins\CoreAdminHome\Controller as CoreAdminController;
use Piwik\Tracker\Cache;

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
        $default = "";

        return $this->makeSettingManagedInConfigOnly('TrackerDomain', 'url', $default ='',FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Domain';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = 'Add domain, without https:// or http://';
            //$field->validators[] = new NotEmpty();
        });
    }

    public function save()
    {
        parent::save();
    }

}
