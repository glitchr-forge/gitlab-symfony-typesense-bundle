<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Typesense\Bundle\DBAL\Collections;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\DBAL\TypesenseManager;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'typesense:import', aliases:[], description:'Import collections from Database')]
class ImportCommand extends Command
{
    private $em;
    private $typesenseManager;
    private const ACTIONS = [
        'create',
        'upsert',
        'update',
    ];
    private $isError = false;

    public function __construct(TypesenseManager $typesenseManager) {

        parent::__construct();
        $this->typesenseManager = $typesenseManager;
    }

    protected function configure()
    {
        $this->addOption('action', null, InputOption::VALUE_OPTIONAL, 'Action modes for typesense import ("create", "upsert" or "update")', 'upsert');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!in_array($input->getOption('action'), self::ACTIONS, true)) {
            $io->error('Action option only takes the values : "create", "upsert" or "update"');

            return 1;
        }

        $action = $input->getOption('action');

        $this->typesenseManager->getObjectManager()->getConnection()->getConfiguration()->setSQLLogger(null);

        $execStart = microtime(true);
        $populated = 0;

        $io->newLine();

        foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

            $output->writeln(sprintf('<info>Connection Typesense: </info> <comment>%s</comment>', $connectionName));

            $collectionDefinitions = $this->typesenseManager->getCollections($connectionName)->getCollectionDefinitions();
            foreach ($collectionDefinitions as $collectionDefinition) {

                $collectionName = $collectionDefinition['name'];
                $class = $collectionDefinition['entity'];

                $q = $this->typesenseManager->getObjectManager()->createQuery('select e from ' . $class . ' e');
                $entities = $q->toIterable();

                $nbEntities = (int) $this->typesenseManager->getObjectManager()->createQuery('select COUNT(u.id) from ' . $class . ' u')->getSingleScalarResult();
                $populated += $nbEntities;

                $data = [];
                foreach ($entities as $entity) {
                    $data[] = $this->typesenseManager->getDoctrineTransformer($connectionName)->convert($entity);
                }

                $output->writeln("\t" . 'Importing <info>[' . $collectionName . '] ' . $class . '</info> ');
                try {

                    $response = $this->typesenseManager->getDocuments($connectionName)->import($collectionName, $data, $action);

                } catch (\Typesense\Exceptions\ObjectNotFound $exception) {

                    $this->isError = true;
                    $output->writeln("\t" . sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $collectionName));
                    continue;
                }

                if ($this->printErrors($io, $response)) {
                    $this->isError = true;
                    $io->error('Error happened during the import of the collection : ' . $collectionName . ' (you can see them with the option -v)');

                    return 2;
                }

                $io->text("\t". 'DONE.');
                $io->newLine();
            }

            $io->newLine();
            if (!$this->isError) {
                $io->success(sprintf(
                    '%s element%s populated in %s seconds',
                    $populated,
                    $populated > 1 ? 's' : '',
                    round(microtime(true) - $execStart, PHP_ROUND_HALF_DOWN)
                ));
            }
        }
        
        return 0;
    }

    private function printErrors(SymfonyStyle $io, array $response): bool
    {
        $isError = false;
        foreach ($response as $item) {
            if (!$item['success']) {
                $isError = true;
                $io->error($item['error']);
            }
        }

        return $isError;
    }
}
