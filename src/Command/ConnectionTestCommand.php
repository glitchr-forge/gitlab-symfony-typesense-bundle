<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Bundle\Client\TypesenseClient;
use Typesense\Bundle\Manager\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'typesense:connection:test', aliases:[], description:'Test connections to Typesense servers')]
class ConnectionTestCommand extends Command
{
    private $collectionManager;

    public function __construct(TypesenseClient $typesenseClient)
    {
        parent::__construct();
        $this->typesenseClient = $typesenseClient;
    }

    public function str_blankspace(int $length)
    {
        return $length < 1 ? "" : str_repeat(" ", $length);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $this->typesenseClient->getClientUrl();
        $ok = $this->typesenseClient->getHealth()->retrieve()["ok"] ?? null;

        $io = new SymfonyStyle($input, $output);
        $output->getFormatter()->setStyle('info,bkg', new OutputFormatterStyle('black', 'green'));
        $output->getFormatter()->setStyle('warning,bkg', new OutputFormatterStyle('black', 'yellow'));
        $output->getFormatter()->setStyle('red,bkg', new OutputFormatterStyle(null, 'red'));

        $io->writeln("");
        if ($ok === null) {
            $msg = ' [WARN] "' . $url . '" is not responding.';
            $io->writeln('<warning,bkg>' . str_blankspace(strlen($msg)));
            $io->info($msg);
            $io->writeln(str_blankspace(strlen($msg)) . '</warning,bkg>');
        } elseif (!$ok) {
            $msg = ' [FAIL] "' . $url . '" is in BAD health condition.';
            $io->writeln('<red,bkg>' . str_blankspace(strlen($msg)));
            $io->writeln($msg);
            $io->writeln(str_blankspace(strlen($msg)) . '</red,bkg>');
        } else {
            $msg = ' [OK] "' . $url . '" is in GOOD health condition.';
            $io->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $io->writeln($msg);
            $io->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
        }

        $io->writeln("");

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
