<?php

namespace SomeWork\Symlinks;

use Composer\Util\Filesystem;

class SymlinksProcessor
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param Symlink $symlink
     *
     * @return bool
     */
    public function processSymlink(Symlink $symlink): bool
    {
        if ($symlink->isAbsolutePath()) {
            return @symlink($symlink->getTarget(), $symlink->getLink());
        }
        return $this->filesystem->relativeSymlink($symlink->getTarget(), $symlink->getLink());
    }
}
