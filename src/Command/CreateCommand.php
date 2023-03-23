<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\UX\Typesense\Manager\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\UX\Typesense\Manager\TypesenseManager;

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
            foreach($this->typesenseManager->getFinders($connectionName) as $name => $finder) {

                $def = $finder->getDefinition();

                $name = $def['name'];
                $typesenseName = $def['typesense_name'];
                try {
                    $output->writeln("\t" . sprintf('<info>Deleting</info> <comment>%s</comment> (<comment>%s</comment> in Typesense)', $name, $typesenseName));
                    $collectionManager->deleteCollection($name);
                } catch (\Typesense\Exceptions\ObjectNotFound $exception) {
                    $output->writeln("\t" . sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $typesenseName));
                }

                $output->writeln("\t" . sprintf('<info>Creating</info> <comment>%s</comment>', $name));
                $collectionManager->createCollection($name);
                $output->writeln("");
            }
        }

        return 0;
    }
}
