<?php

namespace SomeWork\Symlinks;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

/**
 * Class Plugin
 *
 * @author  Dmitry Panychev <thor_work@yahoo.com>
 *
 * @package SomeWork\Composer
 */
class Plugin implements PluginInterface
{
    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $eventDispatcher = $composer->getEventDispatcher();
        $eventDispatcher->addListener(ScriptEvents::POST_INSTALL_CMD, $this->createLinks());
        $eventDispatcher->addListener(ScriptEvents::POST_UPDATE_CMD, $this->createLinks());
    }

    /**
     * @return callable
     */
    protected function createLinks(): callable
    {
        return function (Event $event) {
            $fileSystem = new Filesystem();
            $factory = new SymlinksFactory($event, $fileSystem);
            $processor = new SymlinksProcessor($fileSystem);

            $symlinks = $factory->process();
            foreach ($symlinks as $symlink) {
                if ($processor->processSymlink($symlink)) {
                    $event
                        ->getIO()
                        ->write(sprintf(
                            '  Symlinking <comment>%s</comment> to <comment>%s</comment>',
                            $symlink->getLink(),
                            $symlink->getTarget()
                        ));
                } else {
                    $event
                        ->getIO()
                        ->writeError(sprintf(
                            '  Symlinking <comment>%s</comment> to <comment>%s</comment> - error',
                            $symlink->getLink(),
                            $symlink->getTarget()
                        ));
                }
            }
        };
    }
}
