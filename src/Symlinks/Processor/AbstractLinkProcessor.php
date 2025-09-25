<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Processor;

use Composer\Util\Filesystem;
use SomeWork\Symlinks\Symlink;

abstract class AbstractLinkProcessor implements LinkProcessorInterface
{
    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected function createSymlink(Symlink $symlink): bool
    {
        if ($symlink->isAbsolutePath()) {
            return symlink($symlink->getTarget(), $symlink->getLink());
        }

        return $this->filesystem->relativeSymlink($symlink->getTarget(), $symlink->getLink());
    }

    /**
     * @param callable():bool $operation
     * @return array{0: bool, 1: ?string}
     */
    protected function callWithErrorCapture(callable $operation): array
    {
        $errorMessage = null;
        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): void {
            $errorMessage = $message;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        return [$result, $errorMessage];
    }

    protected function formatGenericSymlinkError(Symlink $symlink, ?string $errorMessage): string
    {
        return sprintf(
            'Failed to create symlink %s -> %s.%s',
            $symlink->getLink(),
            $symlink->getTarget(),
            $this->formatDetails($errorMessage ? ['symlink: ' . $errorMessage] : [])
        );
    }

    /**
     * @param string[] $details
     */
    protected function formatDetails(array $details): string
    {
        $filtered = array_filter($details);
        if ($filtered === []) {
            return '';
        }

        return ' Details: ' . implode('; ', $filtered) . '.';
    }
}
