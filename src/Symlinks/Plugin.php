<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        $eventDispatcher = $composer->getEventDispatcher();
        $eventDispatcher->addListener(ScriptEvents::POST_INSTALL_CMD, $this->createLinks());
        $eventDispatcher->addListener(ScriptEvents::POST_UPDATE_CMD, $this->createLinks());
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

            $symlinks = $factory->process();
            foreach ($symlinks as $symlink) {
                try {
                    if (!$processor->processSymlink($symlink)) {
                        throw new RuntimeException('Unknown error');
                    }
                    $event
                        ->getIO()
                        ->write(sprintf(
                            '  %sSymlinking <comment>%s</comment> to <comment>%s</comment>',
                            $dryRun ? '[DRY RUN] ' : '',
                            $symlink->getLink(),
                            $symlink->getTarget()
                        ));
                } catch (LinkDirectoryError $exception) {
                    $event
                        ->getIO()
                        ->write(sprintf(
                            '  Symlinking <comment>%s</comment> to <comment>%s</comment> - %s',
                            $symlink->getLink(),
                            $symlink->getTarget(),
                            'Skipped'
                        ));
                } catch (\Exception $exception) {
                    $event
                        ->getIO()
                        ->writeError(sprintf(
                            '  Symlinking <comment>%s</comment> to <comment>%s</comment> - %s',
                            $symlink->getLink(),
                            $symlink->getTarget(),
                            $exception->getMessage()
                        ));
                }
            }
        };
    }
}
