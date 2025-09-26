<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Script\Event;
use Composer\Semver\Semver;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

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
    const CONDITIONS = 'conditions';

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
            foreach ($this->normalizeSymlinkDefinitions($linkData) as $definition) {
                if ($this->shouldSkipSymlink($definition)) {
                    continue;
                }
                try {
                    $symlinks[] = $this->processSymlink($target, $definition);
                } catch (SymlinksException $exception) {
                    if ($this->getConfig(static::THROW_EXCEPTION, $definition, true)) {
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

        $resolvedLinkTarget = null;
        if (is_link($linkPath)) {
            $linkTarget = @readlink($linkPath);
            if ($linkTarget !== false) {
                $resolvedLinkTarget = realpath(dirname($linkPath) . DIRECTORY_SEPARATOR . $linkTarget);
            }
        }

        if (is_link($linkPath) && $resolvedLinkTarget === $targetPath) {
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

    private function normalizeSymlinkDefinitions($linkData): array
    {
        if (\is_array($linkData) && !$this->isAssociativeArray($linkData)) {
            $normalized = [];
            foreach ($linkData as $definition) {
                if (!\is_array($definition) && !\is_string($definition)) {
                    throw new InvalidArgumentException('Each symlink definition must be either a string link or a configuration array.');
                }
                $normalized[] = $definition;
            }

            return $normalized;
        }

        if (!\is_array($linkData) && !\is_string($linkData)) {
            throw new InvalidArgumentException('Symlink definitions must be provided as strings or arrays.');
        }

        return [$linkData];
    }

    private function shouldSkipSymlink($linkData): bool
    {
        if (!\is_array($linkData) || !\array_key_exists(static::CONDITIONS, $linkData)) {
            return false;
        }

        $conditions = $linkData[static::CONDITIONS];
        if (!\is_array($conditions)) {
            throw new InvalidArgumentException('The conditions option must be an array.');
        }

        if (isset($conditions['os']) && !$this->matchesOsCondition($conditions['os'])) {
            return true;
        }

        if (isset($conditions['env']) && !$this->matchesEnvCondition($conditions['env'])) {
            return true;
        }

        if (isset($conditions['php-version']) && !$this->matchesPhpVersionCondition($conditions['php-version'])) {
            return true;
        }

        return false;
    }

    /**
     * @param array|string $condition
     */
    private function matchesOsCondition($condition): bool
    {
        if (\is_string($condition)) {
            $condition = [$condition];
        }

        if (!\is_array($condition)) {
            throw new InvalidArgumentException('The os condition must be a string or an array of strings.');
        }

        $currentOs = strtolower(PHP_OS_FAMILY ?? '');
        foreach ($condition as $value) {
            if (!\is_string($value)) {
                throw new InvalidArgumentException('The os condition must contain only strings.');
            }

            if ($currentOs === strtolower($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array|string $condition
     */
    private function matchesEnvCondition($condition): bool
    {
        if (\is_string($condition)) {
            $condition = [$condition];
        }

        if (!\is_array($condition)) {
            throw new InvalidArgumentException('The env condition must be a string or an array.');
        }

        $isAssoc = $this->isAssociativeArray($condition);
        if (!$isAssoc) {
            $condition = array_fill_keys($condition, true);
        }

        foreach ($condition as $name => $expected) {
            if (!\is_string($name)) {
                throw new InvalidArgumentException('Environment variable names must be strings.');
            }

            $value = $this->getEnvironmentValue($name);

            if ($expected === true) {
                if (!$this->isTruthy($value)) {
                    return false;
                }
                continue;
            }

            if ($expected === false) {
                if ($this->isTruthy($value)) {
                    return false;
                }
                continue;
            }

            if ($expected === null) {
                if ($value !== null && $value !== '') {
                    return false;
                }
                continue;
            }

            $expectedValues = \is_array($expected) ? $expected : [$expected];
            $normalizedExpected = [];
            foreach ($expectedValues as $singleExpected) {
                if (!\is_scalar($singleExpected) && $singleExpected !== null) {
                    throw new InvalidArgumentException('Environment variable expectations must be scalar values or arrays of scalar values.');
                }
                if ($singleExpected !== null) {
                    $normalizedExpected[] = (string) $singleExpected;
                }
            }

            if ($value === null) {
                return false;
            }

            if ($normalizedExpected === []) {
                if ($value !== '') {
                    return false;
                }
                continue;
            }

            if (!\in_array($value, $normalizedExpected, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array|string $condition
     */
    private function matchesPhpVersionCondition($condition): bool
    {
        if (\is_string($condition)) {
            $condition = [$condition];
        }

        if (!\is_array($condition)) {
            throw new InvalidArgumentException('The php-version condition must be a string or an array of strings.');
        }

        $version = method_exists(Platform::class, 'getPhpVersion') ? Platform::getPhpVersion() : PHP_VERSION;

        foreach ($condition as $constraint) {
            if (!\is_string($constraint)) {
                throw new InvalidArgumentException('The php-version condition must contain only strings.');
            }

            if (Semver::satisfies($version, $constraint)) {
                return true;
            }
        }

        return false;
    }

    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function getEnvironmentValue(string $name): ?string
    {
        $value = null;

        if (class_exists(Platform::class)) {
            $value = Platform::getEnv($name);
        }

        if ($value === false || $value === null) {
            $value = getenv($name);
        }

        if ($value === false) {
            return null;
        }

        return $value !== null ? (string) $value : null;
    }

    private function isTruthy(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower($value);

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
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
