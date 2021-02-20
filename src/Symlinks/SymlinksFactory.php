<?php

namespace SomeWork\Symlinks;

use Composer\Script\Event;
use Composer\Util\Filesystem;
use Exception;

class SymlinksFactory
{
    const PACKAGE_NAME = 'somework/composer-symlinks';

    const SYMLINKS = 'symlinks';
    const SKIP_MISSED_TARGET = 'skip-missing-target';
    const ABSOLUTE_PATH = 'absolute-path';
    const THROW_EXCEPTION = 'throw-exception';
    const FORCE_CREATE = 'force-create';
    const LINK = 'link';

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
     * @return Symlink[]
     * @throws Exception|SymlinksException
     */
    public function process(): array
    {
        $symlinksData = $this->getSymlinksData();

        $symlinks = [];
        foreach ($symlinksData as $target => $linkData) {
            try {
                $symlinks[] = $this->processSymlinks($target, $linkData);
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

        return array_filter(array_merge(...$symlinks));
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
     * @param string $target
     * @param array|string $linkData
     *
     * @throws LinkDirectoryError
     * @throws InvalidArgumentException
     * @return array
     */
    protected function processSymlinks(string $target, $linkData): array
    {
        $symlinks = [];
        if (!$target) {
            throw new InvalidArgumentException('No target passed in config');
        }

        $links = $this->getLinks($linkData);

        foreach ($links as $link){
            $symlinks[] = $this->processSymlink($target, $link, $linkData);
        }

        return $symlinks;
    }

    /**
     * @throws InvalidArgumentException
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
     * @return array
     */
    protected function getLinks($linkData): array
    {
        $links = [];
        if (\is_array($linkData)) {
            if (\is_array($linkData[static::LINK])) {
                $links = $linkData[static::LINK];
            } elseif (\is_string($linkData[static::LINK])) {
                $links = [$linkData[static::LINK]];
            } elseif($this->isSimpleArray($linkData)) {
                $links = $linkData;
            }
        } elseif (\is_string($linkData)) {
            $links = [$linkData];
        }
        return $links;
    }

    /**
     * @param string $target
     * @param string $link
     * @param array|string $linkData
     *
     * @throws LinkDirectoryError
     * @throws InvalidArgumentException
     * @return null|Symlink
     */
    private function processSymlink(string $target, string $link, $linkData)
    {
        if (!$link) {
            throw new InvalidArgumentException('No link passed in config');
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
     * @param array $linkData
     * @return bool
     */
    private function isSimpleArray(array $linkData): bool
    {
        foreach ($linkData as $key => $data){
            if(!\is_int($key)){
                return false;
            }
        }
        return true;
    }
}
