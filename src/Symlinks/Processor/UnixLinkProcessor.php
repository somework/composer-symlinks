<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Processor;

use SomeWork\Symlinks\RuntimeException;
use SomeWork\Symlinks\Symlink;

class UnixLinkProcessor extends AbstractLinkProcessor
{
    public function create(Symlink $symlink): bool
    {
        [$result, $errorMessage] = $this->callWithErrorCapture(function () use ($symlink): bool {
            return $this->createSymlink($symlink);
        });

        if (!$result) {
            throw new RuntimeException($this->formatGenericSymlinkError($symlink, $errorMessage));
        }

        return true;
    }
}
