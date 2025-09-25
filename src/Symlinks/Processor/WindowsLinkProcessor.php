<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Processor;

use SomeWork\Symlinks\RuntimeException;
use SomeWork\Symlinks\Symlink;

class WindowsLinkProcessor extends AbstractLinkProcessor
{
    public function create(Symlink $symlink): bool
    {
        if ($symlink->getWindowsMode() === Symlink::WINDOWS_MODE_COPY) {
            return $this->createCopy($symlink, Symlink::WINDOWS_MODE_COPY, null);
        }

        [$result, $errorMessage] = $this->callWithErrorCapture(function () use ($symlink): bool {
            return $this->createSymlink($symlink);
        });

        if ($result) {
            return true;
        }

        return $this->handleWindowsSymlinkFailure($symlink, $errorMessage);
    }

    private function handleWindowsSymlinkFailure(Symlink $symlink, ?string $errorMessage): bool
    {
        $mode = $symlink->getWindowsMode();

        if ($mode === Symlink::WINDOWS_MODE_SYMLINK) {
            throw new RuntimeException($this->formatWindowsSymlinkError($symlink, $errorMessage));
        }

        if ($mode === Symlink::WINDOWS_MODE_COPY) {
            return $this->createCopy($symlink, $mode, $errorMessage);
        }

        if (is_dir($symlink->getTarget())) {
            try {
                $this->filesystem->junction($symlink->getTarget(), $symlink->getLink());

                return true;
            } catch (\Throwable $exception) {
                throw new RuntimeException(
                    $this->formatWindowsJunctionError($symlink, $exception->getMessage(), $errorMessage),
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }

        [$hardLinked, $hardLinkError] = $this->tryHardLink($symlink->getTarget(), $symlink->getLink());
        if ($hardLinked) {
            return true;
        }

        return $this->createCopy($symlink, $mode, $errorMessage, $hardLinkError);
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function tryHardLink(string $target, string $link): array
    {
        if (!function_exists('link')) {
            return [false, 'link() function is not available'];
        }

        return $this->callWithErrorCapture(static function () use ($target, $link): bool {
            return link($target, $link);
        });
    }

    private function createCopy(
        Symlink $symlink,
        string $mode,
        ?string $symlinkError,
        ?string $hardLinkError = null
    ): bool {
        try {
            if ($this->filesystem->copy($symlink->getTarget(), $symlink->getLink())) {
                return true;
            }

            $copyError = 'Filesystem::copy returned false';
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                $this->formatWindowsCopyError(
                    $symlink,
                    $mode,
                    $symlinkError,
                    $hardLinkError,
                    $exception->getMessage()
                ),
                (int) $exception->getCode(),
                $exception
            );
        }

        throw new RuntimeException(
            $this->formatWindowsCopyError($symlink, $mode, $symlinkError, $hardLinkError, $copyError)
        );
    }

    private function formatWindowsSymlinkError(Symlink $symlink, ?string $errorMessage): string
    {
        return sprintf(
            'Failed to create symlink %s -> %s. Enable Windows Developer Mode or configure extra.somework/composer-symlinks.windows-mode to "junction" or "copy".%s',
            $symlink->getLink(),
            $symlink->getTarget(),
            $this->formatDetails($errorMessage ? ['symlink: ' . $errorMessage] : [])
        );
    }

    private function formatWindowsJunctionError(Symlink $symlink, string $junctionError, ?string $symlinkError): string
    {
        $details = [
            'junction: ' . $junctionError,
        ];

        if ($symlinkError) {
            $details[] = 'symlink: ' . $symlinkError;
        }

        return sprintf(
            'Failed to create link %s -> %s using windows-mode "junction". Enable Windows Developer Mode or set windows-mode to "copy".%s',
            $symlink->getLink(),
            $symlink->getTarget(),
            $this->formatDetails($details)
        );
    }

    private function formatWindowsCopyError(
        Symlink $symlink,
        string $mode,
        ?string $symlinkError,
        ?string $hardLinkError,
        ?string $copyError
    ): string {
        $details = [];

        if ($symlinkError) {
            $details[] = 'symlink: ' . $symlinkError;
        }

        if ($hardLinkError) {
            $details[] = 'hardlink: ' . $hardLinkError;
        }

        if ($copyError) {
            $details[] = 'copy: ' . $copyError;
        }

        $advice = $mode === Symlink::WINDOWS_MODE_COPY
            ? 'Enable Windows Developer Mode to allow native symlinks.'
            : 'Enable Windows Developer Mode or set windows-mode to "copy".';

        return sprintf(
            'Failed to create link %s -> %s using windows-mode "%s". %s%s',
            $symlink->getLink(),
            $symlink->getTarget(),
            $mode,
            $advice,
            $this->formatDetails($details)
        );
    }
}
