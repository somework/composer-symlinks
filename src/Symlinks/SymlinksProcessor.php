<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Util\Filesystem;
use Composer\Util\Platform;
use SomeWork\Symlinks\Processor\LinkProcessorInterface;
use SomeWork\Symlinks\Processor\UnixLinkProcessor;
use SomeWork\Symlinks\Processor\WindowsLinkProcessor;

class SymlinksProcessor
{
    private Filesystem $filesystem;
    private bool $dryRun = false;
    private LinkProcessorInterface $linkProcessor;

    public function __construct(Filesystem $filesystem, bool $dryRun = false, ?LinkProcessorInterface $linkProcessor = null)
    {
        $this->filesystem = $filesystem;
        $this->dryRun = $dryRun;
        $this->linkProcessor = $linkProcessor ?? $this->createDefaultLinkProcessor();
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * @throws RuntimeException
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

        return $this->linkProcessor->create($symlink);
    }

    protected function isToUnlink(string $path): bool
    {
        return
            file_exists($path) ||
            is_dir($path) ||
            is_link($path);
    }

    private function createDefaultLinkProcessor(): LinkProcessorInterface
    {
        if (Platform::isWindows()) {
            return new WindowsLinkProcessor($this->filesystem);
        }

        return new UnixLinkProcessor($this->filesystem);
    }
}
