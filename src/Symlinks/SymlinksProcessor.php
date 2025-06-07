<?php

namespace SomeWork\Symlinks;

use Composer\Util\Filesystem;

class SymlinksProcessor
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var bool
     */
    private $dryRun = false;

    public function __construct(Filesystem $filesystem, bool $dryRun = false)
    {
        $this->filesystem = $filesystem;
        $this->dryRun = $dryRun;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * @param Symlink $symlink
     *
     * @throws \SomeWork\Symlinks\RuntimeException
     * @return bool
     */
    public function processSymlink(Symlink $symlink): bool
    {
        if ($this->dryRun) {
            if ($this->isToUnlink($symlink->getLink()) && !$symlink->isForceCreate()) {
                throw new LinkDirectoryError('Link ' . $symlink->getLink() . ' already exists');
            }
            return true;
        }

        if ($symlink->isForceCreate() && $this->isToUnlink($symlink->getLink())) {
            try {
                if (\is_dir($symlink->getLink())) {
                    $result = $this->filesystem->removeDirectory($symlink->getLink());
                } else {
                    $result = $this->filesystem->remove($symlink->getLink());
                }
                if (!$result) {
                    throw new RuntimeException('Unknown error');
                }
            } catch (\Exception $exception) {
                throw new RuntimeException(sprintf(
                    'Cant unlink %s: %s',
                    $symlink->getLink(),
                    $exception->getMessage()
                ));
            }
        }

        if ($this->isToUnlink($symlink->getLink())) {
            throw new LinkDirectoryError('Link ' . $symlink->getLink() . ' already exists');
        }

        if ($symlink->isAbsolutePath()) {
            return @symlink($symlink->getTarget(), $symlink->getLink());
        }
        return $this->filesystem->relativeSymlink($symlink->getTarget(), $symlink->getLink());
    }

    protected function isToUnlink(string $path): bool
    {
        return
            file_exists($path) ||
            is_dir($path) ||
            is_link($path);
    }
}
