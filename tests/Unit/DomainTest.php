<?php

namespace Piwik\Plugins\TrackerDomain\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\TrackerDomain\TrackerDomain;

/**
 * @group TrackerDomain
 * @group Plugins
 */
class DomainTest extends TestCase
{
    /**
     * @dataProvider getDomainsAsTheyAreEntered
     */
    public function testADomainIsKeptWithoutProtocolAndSlashAtTheEnd(string $entered, string $expected)
    {
        $this->assertSame($expected, TrackerDomain::normalizeDomain($entered));
    }

    public function getDomainsAsTheyAreEntered(): array
    {
        return [
            'a domain' => ['tracker.example.org', 'tracker.example.org'],
            'with https' => ['https://tracker.example.org/', 'tracker.example.org'],
            'with http' => ['HTTP://tracker.example.org', 'tracker.example.org'],
            'without a protocol name' => ['//tracker.example.org', 'tracker.example.org'],
            'with spaces around it' => [' tracker.example.org ', 'tracker.example.org'],
            'with a path' => ['example.org/analytics/', 'example.org/analytics'],
            'empty' => ['', ''],
        ];
    }

    /**
     * @dataProvider getDomains
     */
    public function testOnlyADomainIsValid(string $domain, bool $expected)
    {
        $this->assertSame($expected, TrackerDomain::isValidDomain($domain));
    }

    public function getDomains(): array
    {
        return [
            'a domain' => ['tracker.example.org', true],
            'a host' => ['localhost', true],
            'with a port' => ['tracker.example.org:8443', true],
            'with a path' => ['example.org/analytics/eu', true],
            'an address' => ['192.0.2.10', true],
            'empty' => ['', false],
            'with a quote' => ['example.org";alert(1);//', false],
            'with a tag' => ['example.org</script>', false],
            'with a space' => ['example.org evil.example', false],
            'with a line break' => ["example.org\nfoo = bar", false],
            'with a query' => ['example.org/?a=b', false],
            'with login' => ['user@example.org', false],
            'only a path' => ['/analytics', false],
        ];
    }
}
