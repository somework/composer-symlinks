<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, Capable
{
    use SymlinksExecutionTrait;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $eventDispatcher = $composer->getEventDispatcher();
        $eventDispatcher->addListener(ScriptEvents::POST_INSTALL_CMD, $this->createLinks());
        $eventDispatcher->addListener(ScriptEvents::POST_UPDATE_CMD, $this->createLinks());
    }

    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => Command\CommandProvider::class,
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    protected function createLinks(): callable
    {
        return function (Event $event) {
            $fileSystem = new Filesystem();
            $factory = new SymlinksFactory($event, $fileSystem);
            $dryRun = getenv('SYMLINKS_DRY_RUN') === '1' || getenv('SYMLINKS_DRY_RUN') === 'true';
            $processor = new SymlinksProcessor($fileSystem, $dryRun);

            $this->runSymlinks($factory, $processor, $event->getIO(), $dryRun);
        };
    }
}
