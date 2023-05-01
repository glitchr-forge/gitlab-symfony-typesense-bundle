<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Exceptions\ObjectNotFound;

#[AsCommand(name: 'typesense:create', aliases: [], description: 'Create Typesenses indexes')]
class CreateCommand extends Command
{
    private TypesenseManager $typesenseManager;

    public function __construct(TypesenseManager $typesenseManager)
    {
        parent::__construct();
        $this->typesenseManager = $typesenseManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->typesenseManager->getConnections() as $connectionName => $connection) {
            $output->writeln(sprintf('<info>Connection Typesense </info> "<comment>%s</comment>": ' . ($connection->getHealth() ? 'OK' : 'BAD STATE'), $connectionName));

            foreach ($connection->getCollections()->retrieve() as $collection) {
                try {
                    $name = $collection['name'];
                    $output->writeln("\t" . sprintf('<info>Deleting</info> <comment>%s</comment> (<comment>%s</comment> in Typesense)', $name, $name));
                    $connection->getCollection($name)->delete();
                } catch (ObjectNotFound $exception) {
                    $output->writeln("\t" . sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $name));
                }
            }
        }

        foreach ($this->typesenseManager->getCollections() as $name => $collection) {
            $output->writeln("\t" . sprintf('<info>Creating</info> <comment>%s</comment>', $name));
            $collection->create();
        }

        return 0;
    }
}
