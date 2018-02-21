<?php

namespace SomeWork\Symlinks;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class SymlinksFactory
{
    const PACKAGE_NAME = 'somework/composer-symlinks';

    const SYMLINKS = 'symlinks';
    const SKIP_MISSED_TARGET = 'skip-missing-target';
    const ABSOLUTE_PATH = 'absolute-path';
    const THROW_EXCEPTION = 'throw-exception';
    const FORCE_CREATE = 'force-create';

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var Event
     */
    protected $event;

    public function __construct(Event $event, Filesystem $filesystem)
    {
        $this->event = $event;
        $this->fileSystem = $filesystem;
    }


    /**
     * @throws \Exception
     * @return Symlink[]
     */
    public function process(): array
    {
        $symlinksData = $this->getSymlinksData();

        $symlinks = [];
        foreach ($symlinksData as $target => $linkData) {
            try {
                $symlinks[] = $this->processSymlink($target, $linkData);
            } catch (SymlinksException $exception) {
                if ($this->getConfig(static::THROW_EXCEPTION, $linkData, true)) {
                    throw $exception;
                }
                $this->event->getIO()->writeError(
                    sprintf(
                        '  Error while process <comment>%s</comment>: <comment>%s</comment>',
                        $target,
                        $exception->getMessage()
                    )
                );
            }
        }

        return array_filter($symlinks);
    }

    protected function getConfig(string $name, $link = null, $default = false): bool
    {
        if (\is_array($link) && isset($link[$name])) {
            return (bool)$link[$name];
        }

        $extras = $this->event->getComposer()->getPackage()->getExtra();
        if (!isset($extras[static::PACKAGE_NAME][$name])) {
            return $default;
        }
        return (bool)$extras[static::PACKAGE_NAME][$name];
    }

    /**
     * @param string       $target
     * @param array|string $linkData
     *
     * @throws \SomeWork\Symlinks\LinkDirectoryError
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return null|Symlink
     */
    protected function processSymlink(string $target, $linkData)
    {
        $link = $this->getLink($linkData);

        if (!$link) {
            throw new InvalidArgumentException('No link passed in config');
        }

        if (!$target) {
            throw new InvalidArgumentException('No target passed in config');
        }

        if ($this->fileSystem->isAbsolutePath($target)) {
            throw new InvalidArgumentException(
                sprintf('Invalid symlink target path %s. It must be relative', $target)
            );
        }

        if ($this->fileSystem->isAbsolutePath($link)) {
            throw new InvalidArgumentException(
                sprintf('Invalid symlink link path %s. It must be relative', $link)
            );
        }

        $currentDirectory = realpath(getcwd());
        $targetPath = realpath($currentDirectory . DIRECTORY_SEPARATOR . $target);
        $linkPath = $currentDirectory . DIRECTORY_SEPARATOR . $link;

        if (!is_dir($targetPath) && !is_file($targetPath)) {
            if ($this->getConfig(static::SKIP_MISSED_TARGET, $link)) {
                return null;
            }
            throw new InvalidArgumentException(
                sprintf('The target path %s does not exists', $targetPath)
            );
        }

        try {
            $this->fileSystem->ensureDirectoryExists(\dirname($linkPath));
        } catch (\RuntimeException $exception) {
            throw new LinkDirectoryError($exception->getMessage(), $exception->getCode(), $exception);
        }

        if (is_link($linkPath) && realpath(readlink($linkPath)) === $targetPath) {
            $this->event->getIO()->write(
                sprintf(
                    '  Symlink <comment>%s</comment> to <comment>%s</comment> already created',
                    $target,
                    $link
                )
            );
            return null;
        }

        return (new Symlink())
            ->setTarget($targetPath)
            ->setLink($linkPath)
            ->setAbsolutePath($this->getConfig(static::ABSOLUTE_PATH, $linkData, false))
            ->setForceCreate($this->getConfig(static::FORCE_CREATE, $linkData, false));
    }

    /**
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return array
     */
    protected function getSymlinksData(): array
    {
        $extras = $this->event->getComposer()->getPackage()->getExtra();

        if (!isset($extras[static::PACKAGE_NAME][static::SYMLINKS])) {
            return [];
        }

        $configs = $extras[static::PACKAGE_NAME][static::SYMLINKS];

        if (!\is_array($configs)) {
            throw new InvalidArgumentException(sprintf(
                'The extra.%s.%s setting must be an array.',
                static::PACKAGE_NAME,
                static::SYMLINKS
            ));
        }

        return array_unique($configs, SORT_REGULAR);
    }

    /**
     * @param $linkData
     *
     * @return string
     */
    protected function getLink($linkData): string
    {
        $link = '';
        if (\is_array($linkData)) {
            $link = $linkData['link'] ?? '';
        } elseif (\is_string($linkData)) {
            $link = $linkData;
        }
        return $link;
    }
}
