<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Processor;

use SomeWork\Symlinks\Symlink;

interface LinkProcessorInterface
{
    public function create(Symlink $symlink): bool;
}
