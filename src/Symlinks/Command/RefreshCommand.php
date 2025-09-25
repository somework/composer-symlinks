<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Command;

use Composer\Command\BaseCommand;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use SomeWork\Symlinks\SymlinksExecutionTrait;
use SomeWork\Symlinks\SymlinksFactory;
use SomeWork\Symlinks\SymlinksProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshCommand extends BaseCommand
{
    use SymlinksExecutionTrait;

    protected function configure(): void
    {
        $this
            ->setName('symlinks:refresh')
            ->setDescription('Create symlinks defined in extra.somework/composer-symlinks.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the operations without creating links.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();

        $dryRun = (bool) $input->getOption('dry-run');

        $event = new Event('symlinks:refresh', $composer, $io);
        $filesystem = new Filesystem();
        $factory = new SymlinksFactory($event, $filesystem);
        $processor = new SymlinksProcessor($filesystem, $dryRun);

        $this->runSymlinks($factory, $processor, $io, $dryRun);

        return Command::SUCCESS;
    }
}
