<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\DBAL;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\DBAL\Configuration;

class ConfigurationTest extends TestCase
{
    public function testDefaultsAreAppliedWhenParamsAreEmpty(): void
    {
        $config = new Configuration('secret', []);

        $this->assertSame('secret', $config->getSecret());
        $this->assertSame(['host' => 'localhost', 'port' => 8108, 'protocol' => 'http'], $config->getNode());
        $this->assertSame('', $config->getPath());
        $this->assertSame('http://localhost:8108', $config->getEndpoint());
    }

    public function testExplicitParamsOverrideDefaults(): void
    {
        $config = new Configuration('secret', [
            'scheme' => 'https',
            'host' => 'ts.example.com',
            'port' => 443,
            'path' => '/tenant-a/',
        ]);

        $this->assertSame(['host' => 'ts.example.com', 'port' => 443, 'protocol' => 'https'], $config->getNode());
        $this->assertSame('/tenant-a', $config->getPath());
        $this->assertSame('https://ts.example.com:443/tenant-a', $config->getEndpoint());
    }

    public function testGetEndpointAppendsAnExtraPath(): void
    {
        $config = new Configuration('secret', ['path' => 'tenant-a']);

        $this->assertSame('http://localhost:8108/tenant-a/collections', $config->getEndpoint('/collections'));
    }

    public function testToStringReturnsTheEndpoint(): void
    {
        $config = new Configuration('secret', []);

        $this->assertSame($config->getEndpoint(), (string) $config);
    }

    public function testOptionsArePassedThrough(): void
    {
        $config = new Configuration('secret', [], ['connection_timeout_seconds' => 5]);

        $this->assertSame(['connection_timeout_seconds' => 5], $config->getOptions());
    }

    public function testSecretCanBeNull(): void
    {
        $config = new Configuration(null, []);

        $this->assertNull($config->getSecret());
    }
}
