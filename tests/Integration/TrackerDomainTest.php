<?php

namespace Piwik\Plugins\TrackerDomain\tests\Integration;

use Piwik\API\Request;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\TagManager\Template\Variable\VariablesProvider;
use Piwik\Plugins\TrackerDomain\SystemSettings;
use Piwik\SettingsPiwik;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group TrackerDomain
 * @group Plugins
 */
class TrackerDomainTest extends IntegrationTestCase
{
    private const DOMAIN = 'tracker.example.org';

    /**
     * @var string the host of the Matomo of the test
     */
    private $matomoHost;

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::clearAccess(true);
        Fixture::createWebsite('2024-01-01 00:00:00');
        $this->matomoHost = (string) parse_url(SettingsPiwik::getPiwikUrl(), PHP_URL_HOST);
        $this->assertNotSame('', $this->matomoHost);
    }

    public function testTheTrackingCodeSendsToTheTrackerDomain()
    {
        $this->setDomain(self::DOMAIN);

        $code = $this->getTrackingCode();

        $this->assertStringContainsString('var u="//' . self::DOMAIN . '/";', $code);
        $this->assertStringNotContainsString($this->matomoHost, $code);
    }

    /**
     * @dataProvider getValuesThatAreNotADomain
     */
    public function testTheTrackingCodeSendsToMatomoWithoutATrackerDomain(?string $domain)
    {
        $this->setDomain($domain);

        $code = $this->getTrackingCode();

        $this->assertStringContainsString('var u="//' . $this->matomoHost, $code);
        $this->assertStringNotContainsString('alert', $code);
    }

    public function getValuesThatAreNotADomain(): array
    {
        return [
            'not set' => [null],
            'emptied' => [''],
            'not a domain' => ['example.org";alert(1);//'],
        ];
    }

    public function testADomainInTheConfigFileIsUsedWithoutItsProtocol()
    {
        $this->setDomain('https://' . self::DOMAIN . '/');

        $this->assertStringContainsString('var u="//' . self::DOMAIN . '/";', $this->getTrackingCode());
    }

    public function testTheImageTrackingCodeSendsToTheTrackerDomain()
    {
        $this->setDomain(self::DOMAIN);

        $code = Request::processRequest('SitesManager.getImageTrackingCode', ['idSite' => 1]);

        $this->assertStringContainsString('src="https://' . self::DOMAIN . '/matomo.php?idsite=1', html_entity_decode($code));
    }

    public function testTheImageTrackingCodeIsNotChangedWhenTheTrackerDomainIsEmptied()
    {
        $this->setDomain(null);
        $unchanged = Request::processRequest('SitesManager.getImageTrackingCode', ['idSite' => 1]);

        $this->setDomain('');

        $this->assertSame($unchanged, Request::processRequest('SitesManager.getImageTrackingCode', ['idSite' => 1]));
    }

    public function testTheTagManagerCodeLoadsTheContainerFromTheTrackerDomain()
    {
        $this->setDomain(self::DOMAIN);

        $code = $this->getContainerCode();

        $this->assertStringContainsString('://' . self::DOMAIN . '/js/container_', $code);
        $this->assertStringNotContainsString($this->matomoHost, $code);
    }

    public function testTheTagManagerCodeIsNotChangedWhenTheTrackerDomainIsEmptied()
    {
        $this->setDomain(null);
        $unchanged = $this->getContainerCode();
        $this->assertStringContainsString($this->matomoHost, $unchanged);

        $this->setDomain('');

        $this->assertSame($unchanged, $this->getContainerCode());
    }

    public function testTheMatomoUrlOfATagManagerConfigurationIsTheTrackerDomain()
    {
        $this->assertStringContainsString($this->matomoHost, $this->getDefaultMatomoUrlOfTagManager());

        $this->setDomain(self::DOMAIN);

        $this->assertSame('//' . self::DOMAIN . '/', $this->getDefaultMatomoUrlOfTagManager());
    }

    public function testTheTrackerDomainAndTheUrlOfMatomoAreGivenToJavaScript()
    {
        $this->setDomain(self::DOMAIN);

        $script = '';
        Piwik::postEvent('Template.jsGlobalVariables', [&$script]);

        $this->assertStringContainsString('piwik.trackerDomain = "' . self::DOMAIN . '";', $script);
        $this->assertStringContainsString('piwik.dashboardUrl = "' . SettingsPiwik::getPiwikUrl() . '";', $script);
    }

    /**
     * @dataProvider getValuesThatAreNotADomain
     */
    public function testNothingIsGivenToJavaScriptWithoutATrackerDomain(?string $domain)
    {
        $this->setDomain($domain);

        $script = '';
        Piwik::postEvent('Template.jsGlobalVariables', [&$script]);

        $this->assertStringNotContainsString('trackerDomain', $script);
        $this->assertStringNotContainsString('alert', $script);
    }

    public function testTheSettingKeepsADomainWithoutItsProtocol()
    {
        $settings = new SystemSettings();

        $settings->trackerdomain->setValue('https://' . self::DOMAIN . '/');
        $this->assertSame(self::DOMAIN, $settings->trackerdomain->getValue());

        $settings->trackerdomain->setValue('');
        $this->assertSame('', $settings->trackerdomain->getValue());
    }

    /**
     * @dataProvider getInvalidDomains
     */
    public function testTheSettingRefusesWhatIsNotADomain(string $domain)
    {
        $settings = new SystemSettings();

        $this->expectExceptionMessage('ExceptionInvalidDomain');
        $settings->trackerdomain->setValue($domain);
    }

    public function getInvalidDomains(): array
    {
        return [
            'script' => ['example.org";alert(1);//'],
            'a tag' => ['<script>'],
            'two lines' => ["example.org\nfoo = bar"],
        ];
    }

    public function testOnlyASuperUserCanChangeTheSetting()
    {
        $this->assertTrue((new SystemSettings())->trackerdomain->isWritableByCurrentUser());

        FakeAccess::clearAccess(false, [1]);

        $this->assertFalse((new SystemSettings())->trackerdomain->isWritableByCurrentUser());
    }

    private function setDomain(?string $domain): void
    {
        Config::getInstance()->TrackerDomain = $domain === null ? [] : ['url' => $domain];
    }

    private function getTrackingCode(): string
    {
        return html_entity_decode(Request::processRequest('SitesManager.getJavascriptTag', ['idSite' => 1]));
    }

    private function getContainerCode(): string
    {
        $containers = Request::processRequest('TagManager.getContainers', ['idSite' => 1]);
        $this->assertNotEmpty($containers, 'Tag Manager created no container for the website.');

        return Request::processRequest('TagManager.getContainerEmbedCode', [
            'idSite' => 1,
            'idContainer' => $containers[0]['idcontainer'],
            'environment' => 'live',
        ]);
    }

    private function getDefaultMatomoUrlOfTagManager(): string
    {
        // The variables are kept for the request.
        $provider = StaticContainer::getContainer()->make(VariablesProvider::class);
        foreach ($provider->getVariable('MatomoConfiguration')->getParameters() as $parameter) {
            if ($parameter->getName() === 'matomoUrl') {
                return (string) $parameter->getDefaultValue();
            }
        }

        $this->fail('The Matomo configuration of Tag Manager has no Matomo URL.');
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);

        $fixture->createSuperUser = true;
        $fixture->extraPluginsToLoad = ['TagManager', 'TrackerDomain'];
    }
}
