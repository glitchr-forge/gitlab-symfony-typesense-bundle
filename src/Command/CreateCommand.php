<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Doctrine\ORM\ObjectManager;
use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Typesense\Bundle\Manager\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Bundle\Manager\TypesenseManager;

#[AsCommand(name:'typesense:create', aliases:[], description:'Create Typesenses indexes')]
class CreateCommand extends Command
{
    private $typesenseManager;

    public function __construct(TypesenseManager $typesenseManager)
    {
        parent::__construct();
        $this->typesenseManager = $typesenseManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

            $output->writeln(sprintf('<info>Connection Typesense: </info> <comment>%s</comment>', $connectionName));

            $collectionManager = $this->typesenseManager->getCollectionManager($connectionName);
            $collectionDefinitions = $collectionManager->getCollectionDefinitions();
            foreach ($collectionDefinitions as $collectionDefinition) {

                $name = $collectionDefinition['name'];
                $typesenseName = $collectionDefinition['name'];
                try {

                    $output->writeln("\t" . sprintf('<info>Deleting</info> <comment>%s</comment> (<comment>%s</comment> in Typesense)', $name, $typesenseName));
                    $collectionManager->deleteCollection($name);

                } catch (\Typesense\Exceptions\ObjectNotFound $exception) {
                    $output->writeln("\t" . sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $typesenseName));
                }
            }

            foreach ($collectionDefinitions as $collectionDefinition) {

                $name = $collectionDefinition['name'];

                $output->writeln("\t" . sprintf('<info>Creating</info> <comment>%s</comment>', $name));
                $collectionManager->createCollection($name);
                $output->writeln("");
            }
        }

        return 0;
    }
}
