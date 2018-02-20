<?php

namespace SomeWork\Symlinks;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

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
     *
     * @throws \RuntimeException
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $eventDispatcher = $composer->getEventDispatcher();
        $eventDispatcher->addListener(ScriptEvents::POST_INSTALL_CMD, $this->createLinks());
        $eventDispatcher->addListener(ScriptEvents::POST_UPDATE_CMD, $this->createLinks());
    }

    /**
     * @throws \RuntimeException
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return callable
     */
    protected function createLinks(): callable
    {
        return function (Event $event) {
            Symlinks::create($event);
        };
    }
}
