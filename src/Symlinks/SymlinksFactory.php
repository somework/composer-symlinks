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
    const THROW_EXCEPTION = 'exception';

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
     * @throws \SomeWork\Symlinks\RuntimeException
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return Symlink[]
     */
    public function process(): array
    {
        $symlinksData = $this->getSymlinksData();

        $symlinksData = array_filter($symlinksData, function ($linkData, $target) {
            try {
                return $this->filterSymlink($target, $linkData);
            } catch (SymlinksException $exception) {
                if ($this->getConfig(static::THROW_EXCEPTION, $linkData, true)) {
                    throw $exception;
                }
            }
            return false;
        }, ARRAY_FILTER_USE_BOTH);

        $symlinks = [];
        foreach ($symlinksData as $target => $linkData) {
            $symlinks[] = $this->processSymlink($target, $linkData);
        }

        return $symlinks;
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
     * @throws \SomeWork\Symlinks\RuntimeException
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return Symlink
     */
    protected function processSymlink(string $target, $linkData): Symlink
    {
        $link = $this->getLink($linkData);

        $targetPath = realpath(getcwd() . DIRECTORY_SEPARATOR . $target);
        $linkPath = realpath(getcwd() . DIRECTORY_SEPARATOR . $link);

        return (new Symlink())
            ->setTarget($targetPath)
            ->setLink($linkPath)
            ->setAbsolutePath($this->getConfig(static::ABSOLUTE_PATH, $linkData, false));
    }

    /**
     * @param string       $target
     * @param array|string $linkData
     *
     * @throws \SomeWork\Symlinks\LinkDirectoryError
     * @throws \SomeWork\Symlinks\InvalidArgumentException
     * @return bool
     */
    protected function filterSymlink(string $target, $linkData): bool
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

        $targetPath = realpath(getcwd() . DIRECTORY_SEPARATOR . $target);
        $linkPath = realpath(getcwd() . DIRECTORY_SEPARATOR . $link);

        if (!is_dir($targetPath) && !is_file($targetPath)) {
            if ($this->getConfig(static::SKIP_MISSED_TARGET, $link)) {
                return false;
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
        return true;
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

        return array_unique($configs);
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
