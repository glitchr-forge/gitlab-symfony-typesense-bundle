<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Typesense\Bundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    public function testDefaultConnectionDefaultsToDefault(): void
    {
        $config = $this->process([]);

        $this->assertSame('default', $config['default_connection']);
        $this->assertSame([], $config['connections']);
        $this->assertSame([], $config['mappings']);
    }

    /**
     * The "multi server" feature: any number of named connections can be
     * declared, each with its own host/port/secret/options.
     */
    public function testMultipleNamedConnectionsAreAccepted(): void
    {
        $config = $this->process([[
            'default_connection' => 'primary',
            'connections' => [
                'primary' => ['secret' => 'a', 'host' => 'ts-1.internal', 'port' => 8108],
                'secondary' => ['secret' => 'b', 'host' => 'ts-2.internal', 'port' => 8109],
            ],
        ]]);

        $this->assertSame('primary', $config['default_connection']);
        $this->assertSame('ts-1.internal', $config['connections']['primary']['host']);
        $this->assertSame('ts-2.internal', $config['connections']['secondary']['host']);
        $this->assertSame(['connection_timeout_seconds' => 5], $config['connections']['primary']['options']);
    }

    public function testMappingsAreKeyedByTheirName(): void
    {
        $config = $this->process([[
            'mappings' => [
                'article' => [
                    'connection' => 'primary',
                    'class' => 'App\\Entity\\Article',
                    'fields' => [
                        'title' => ['type' => 'string'],
                    ],
                ],
            ],
        ]]);

        $this->assertSame('primary', $config['mappings']['article']['connection']);
        $this->assertSame('App\\Entity\\Article', $config['mappings']['article']['class']);
        $this->assertSame('string', $config['mappings']['article']['fields']['title']['type']);
        // Configuration-level defaults for a mapping.
        $this->assertSame(['+', '-', '@', '.', ' '], $config['mappings']['article']['token_separators']);
        $this->assertSame(['+'], $config['mappings']['article']['symbols_to_index']);
    }

    public function testGetTreeBuilderReturnsTheBuilderCreatedByGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertSame($treeBuilder, $configuration->getTreeBuilder());
    }
}
