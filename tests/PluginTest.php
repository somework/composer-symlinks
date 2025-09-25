<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\NullIO;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Plugin;
use SomeWork\Symlinks\Command\RefreshCommand;

class PluginTest extends TestCase
{
    public function testActivateRegistersListeners(): void
    {
        $composer = new Composer();
        $dispatcher = new class($composer) extends EventDispatcher {
            public $recorded = [];
            public function __construct(Composer $composer)
            {
                parent::__construct($composer, new NullIO());
            }
            public function addListener(string $eventName, $listener, int $priority = 0): void
            {
                $this->recorded[$eventName][] = $listener;
                parent::addListener($eventName, $listener, $priority);
            }
        };
        $composer->setEventDispatcher($dispatcher);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $this->assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $dispatcher->recorded);
        $this->assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $dispatcher->recorded);
        $this->assertIsCallable($dispatcher->recorded[ScriptEvents::POST_INSTALL_CMD][0]);
    }

    public function testGetCapabilitiesRegistersCommand(): void
    {
        $plugin = new Plugin();

        $capabilities = $plugin->getCapabilities();

        $this->assertArrayHasKey(CommandProviderCapability::class, $capabilities);
        $this->assertSame(
            \SomeWork\Symlinks\Command\CommandProvider::class,
            $capabilities[CommandProviderCapability::class]
        );

        $providerClass = $capabilities[CommandProviderCapability::class];
        $provider = new $providerClass();
        $commands = $provider->getCommands();

        $this->assertNotEmpty($commands);
        $this->assertInstanceOf(RefreshCommand::class, $commands[0]);
    }
}
