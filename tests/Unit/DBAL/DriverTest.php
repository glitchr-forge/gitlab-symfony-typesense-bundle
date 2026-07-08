<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\DBAL;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\DBAL\Driver;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Client;

class DriverTest extends TestCase
{
    public function testPrepareBuildsAConfigurationFromParams(): void
    {
        $driver = new Driver('default');

        $configuration = $driver->prepare([
            'secret' => 'xyz',
            'url' => 'https://ts.example.com:443/tenant-a',
        ]);

        $this->assertSame('xyz', $configuration->getSecret());
        $this->assertSame(['host' => 'ts.example.com', 'port' => 443, 'protocol' => 'https'], $configuration->getNode());
    }

    public function testPrepareIsMemoizedAcrossCalls(): void
    {
        $driver = new Driver('default');

        $first = $driver->prepare(['secret' => 'xyz']);
        $second = $driver->prepare(['secret' => 'a-different-secret-ignored-because-memoized']);

        $this->assertSame($first, $second);
    }

    public function testPrepareThrowsWhenNoSecretIsConfiguredUnderCli(): void
    {
        $driver = new Driver('default');

        $this->expectException(TypesenseException::class);
        $this->expectExceptionMessageMatches('/API Key missing for "default" connection/');

        $driver->prepare([]);
    }

    public function testConnectReturnsARealClient(): void
    {
        $driver = new Driver('default');

        $client = $driver->connect(['secret' => 'xyz', 'host' => 'localhost', 'port' => 8108]);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConnectIsMemoizedAndReturnsTheSameClient(): void
    {
        $driver = new Driver('default');

        $first = $driver->connect(['secret' => 'xyz']);
        $second = $driver->connect(['secret' => 'xyz']);

        $this->assertSame($first, $second);
    }

    public function testGetConfigErrorIsNullByDefault(): void
    {
        $this->assertNull((new Driver('default'))->getConfigError());
    }
}
