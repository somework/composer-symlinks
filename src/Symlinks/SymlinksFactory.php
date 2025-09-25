<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class SymlinksFactory
{
    const PACKAGE_NAME = 'somework/composer-symlinks';

    const SYMLINKS = 'symlinks';

    /**
     * Config key for skipping symlink creation when the target is missing.
     */
    const SKIP_MISSING_TARGET = 'skip-missing-target';
    const ABSOLUTE_PATH = 'absolute-path';
    const THROW_EXCEPTION = 'throw-exception';
    const FORCE_CREATE = 'force-create';
    const WINDOWS_MODE = 'windows-mode';

    protected Filesystem $fileSystem;
    protected Event $event;
    /**
     * @var array<string, string>
     */
    private array $configuredSymlinks = [];
    private ?string $vendorDir = null;

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
        $this->configuredSymlinks = [];
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
     * @throws LinkDirectoryError
     * @throws InvalidArgumentException
     *
     * @return null|Symlink
     */
    protected function processSymlink(string $target, $linkData): ?Symlink
    {
        $link = $this->getLink($linkData);

        if (!$link) {
            throw new InvalidArgumentException('No link passed in config');
        }

        if (!$target) {
            throw new InvalidArgumentException('No target passed in config');
        }

        [$target, $targetExpanded] = $this->expandPathPlaceholders($target);
        [$link, $linkExpanded] = $this->expandPathPlaceholders($link);

        $targetIsAbsolute = $this->fileSystem->isAbsolutePath($target);
        if ($targetIsAbsolute && !$targetExpanded) {
            throw new InvalidArgumentException(
                sprintf('Invalid symlink target path %s. It must be relative', $target)
            );
        }

        $linkIsAbsolute = $this->fileSystem->isAbsolutePath($link);
        if ($linkIsAbsolute && !$linkExpanded) {
            throw new InvalidArgumentException(
                sprintf('Invalid symlink link path %s. It must be relative', $link)
            );
        }

        $currentDirectory = realpath(getcwd());
        if ($targetIsAbsolute) {
            $targetPath = realpath($target);
        } else {
            $targetPath = realpath($currentDirectory . DIRECTORY_SEPARATOR . $target);
        }

        if ($linkIsAbsolute) {
            $linkPath = $link;
        } else {
            $linkPath = $currentDirectory . DIRECTORY_SEPARATOR . $link;
        }

        if ($targetPath === false || (!is_dir($targetPath) && !is_file($targetPath))) {
            if ($this->getConfig(static::SKIP_MISSING_TARGET, $linkData)) {
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

        if (
            is_link($linkPath) &&
            realpath(dirname($linkPath) . DIRECTORY_SEPARATOR . readlink($linkPath)) === $targetPath
        ) {
            $this->registerConfiguredSymlink($linkPath, $targetPath);
            $this->event->getIO()->write(
                sprintf(
                    '  Symlink <comment>%s</comment> to <comment>%s</comment> already created',
                    $target,
                    $link
                )
            );
            return null;
        }

        $symlink = (new Symlink())
            ->setTarget($targetPath)
            ->setLink($linkPath)
            ->setAbsolutePath($this->getConfig(static::ABSOLUTE_PATH, $linkData, false))
            ->setForceCreate($this->getConfig(static::FORCE_CREATE, $linkData, false))
            ->setWindowsMode($this->getWindowsMode($linkData));

        $this->registerConfiguredSymlink($linkPath, $targetPath);

        return $symlink;
    }

    /**
     * @throws InvalidArgumentException
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

    /**
     * @return array{0: string, 1: bool}
     */
    private function expandPathPlaceholders(string $path): array
    {
        $wasExpanded = false;

        [$projectDir, $vendorDir] = [$this->getProjectDir(), $this->getVendorDir()];

        if ($projectDir !== null) {
            $path = str_replace('%project-dir%', $projectDir, $path, $count);
            if ($count > 0) {
                $wasExpanded = true;
            }
        }

        if ($vendorDir !== null) {
            $path = str_replace('%vendor-dir%', $vendorDir, $path, $count);
            if ($count > 0) {
                $wasExpanded = true;
            }
        }

        $path = preg_replace_callback('/%env\(([^)]+)\)%/', function (array $matches) use (&$wasExpanded): string {
            $wasExpanded = true;
            $value = getenv($matches[1]);
            if ($value === false) {
                return '';
            }
            return $value;
        }, $path);

        if (!\is_string($path)) {
            $path = '';
        }

        return [$path, $wasExpanded];
    }

    private function getProjectDir(): ?string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return null;
        }

        $path = realpath($cwd);

        return $path === false ? $cwd : $path;
    }

    public function getVendorDirPath(): ?string
    {
        if ($this->vendorDir === null) {
            $this->vendorDir = $this->resolveVendorDir();
        }

        return $this->vendorDir;
    }

    public function getConfiguredSymlinks(): array
    {
        return $this->configuredSymlinks;
    }

    public function isCleanupEnabled(): bool
    {
        return $this->getConfig('cleanup', null, false);
    }

    private function registerConfiguredSymlink(string $link, string $target): void
    {
        $this->configuredSymlinks[$link] = $target;
    }

    private function getVendorDir(): ?string
    {
        return $this->getVendorDirPath();
    }

    private function resolveVendorDir(): ?string
    {
        $composer = $this->event->getComposer();
        $vendorDir = null;

        if ($composer !== null) {
            try {
                $config = $composer->getConfig();
            } catch (\TypeError $exception) {
                $config = null;
            }

            if ($config !== null) {
                $vendorDir = $config->get('vendor-dir');
            }
        }

        if (!$vendorDir) {
            $projectDir = $this->getProjectDir();
            if ($projectDir === null) {
                return null;
            }
            $vendorDir = $projectDir . DIRECTORY_SEPARATOR . 'vendor';
        }

        if (!$this->fileSystem->isAbsolutePath($vendorDir)) {
            $projectDir = $this->getProjectDir();
            if ($projectDir !== null) {
                $combined = $projectDir . DIRECTORY_SEPARATOR . $vendorDir;
                $vendorDir = realpath($combined) ?: $combined;
            }
        }

        return $vendorDir;
    }

    protected function getScalarConfig(string $name, $link = null, ?string $default = null): ?string
    {
        $value = null;
        if (\is_array($link) && \array_key_exists($name, $link)) {
            $value = $link[$name];
        } else {
            $extras = $this->event->getComposer()->getPackage()->getExtra();
            if (!isset($extras[static::PACKAGE_NAME][$name])) {
                return $default;
            }
            $value = $extras[static::PACKAGE_NAME][$name];
        }

        if ($value === null) {
            return $default;
        }

        if (!\is_scalar($value)) {
            throw new InvalidArgumentException(sprintf(
                'The config option %s must be a string or scalar value.',
                $name
            ));
        }

        return (string) $value;
    }

    private function getWindowsMode($linkData): string
    {
        $mode = $this->getScalarConfig(static::WINDOWS_MODE, $linkData, Symlink::WINDOWS_MODE_JUNCTION);
        $mode = strtolower($mode);

        $allowed = [
            Symlink::WINDOWS_MODE_SYMLINK,
            Symlink::WINDOWS_MODE_JUNCTION,
            Symlink::WINDOWS_MODE_COPY,
        ];

        if (!\in_array($mode, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown windows-mode "%s". Allowed values are: %s.',
                $mode,
                implode(', ', $allowed)
            ));
        }

        return $mode;
    }
}
