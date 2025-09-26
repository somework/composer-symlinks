<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new RefreshCommand(),
            new StatusCommand(),
        ];
    }
}
