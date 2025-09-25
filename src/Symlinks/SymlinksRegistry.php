<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\Util\Filesystem;

class SymlinksRegistry
{
    private const REGISTRY_FILENAME = 'composer-symlinks-state.json';

    private Filesystem $filesystem;
    private string $registryFile;

    public function __construct(Filesystem $filesystem, string $vendorDir)
    {
        $this->filesystem = $filesystem;
        $this->registryFile = rtrim($vendorDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::REGISTRY_FILENAME;
    }

    /**
     * @return array<string, string>
     */
    public function load(): array
    {
        if (!is_file($this->registryFile)) {
            return [];
        }

        $contents = file_get_contents($this->registryFile);
        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $registry = [];
        foreach ($decoded as $link => $target) {
            if (\is_string($link) && \is_string($target)) {
                $registry[$link] = $target;
            }
        }

        return $registry;
    }

    /**
     * @param array<string, string> $configuredSymlinks
     * @param Symlink[]             $createdSymlinks
     */
    public function sync(array $configuredSymlinks, array $createdSymlinks, bool $cleanup): void
    {
        $registry = $this->filterMissingPaths($this->load());

        $registry = $this->recordProcessedSymlinks($registry, $createdSymlinks);
        $registry = $this->recordConfiguredSymlinks($registry, $configuredSymlinks);

        if ($cleanup) {
            $registry = $this->cleanupRemovedSymlinks($registry, array_keys($configuredSymlinks));
        }

        $this->save($registry);
    }

    public function clear(): void
    {
        if (is_file($this->registryFile)) {
            @unlink($this->registryFile);
        }
    }

    public function removeAll(): void
    {
        foreach ($this->load() as $link => $_target) {
            $this->removePath($link);
        }

        $this->clear();
    }

    public function getRegistryFile(): string
    {
        return $this->registryFile;
    }

    /**
     * @param array<string, string> $registry
     */
    private function save(array $registry): void
    {
        if ($registry === []) {
            $this->clear();
            return;
        }

        $directory = \dirname($this->registryFile);
        if (!is_dir($directory)) {
            $this->filesystem->ensureDirectoryExists($directory);
        }

        file_put_contents(
            $this->registryFile,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function resolveTarget(string $link, string $fallback): ?string
    {
        if (is_link($link)) {
            $target = readlink($link);
            if ($target === false) {
                return $fallback;
            }

            if ($this->filesystem->isAbsolutePath($target)) {
                return realpath($target) ?: $target;
            }

            $base = realpath(\dirname($link));
            if ($base === false) {
                return $fallback;
            }

            $combined = $base . DIRECTORY_SEPARATOR . $target;
            return realpath($combined) ?: $combined;
        }

        if (file_exists($link)) {
            return realpath($link) ?: $fallback;
        }

        return $fallback;
    }

    /**
     * @param array<string, string> $registry
     *
     * @return array<string, string>
     */
    private function filterMissingPaths(array $registry): array
    {
        foreach ($registry as $link => $target) {
            if (!$this->pathExists($link)) {
                unset($registry[$link]);
            }
        }

        return $registry;
    }

    private function pathExists(string $path): bool
    {
        return is_link($path) || file_exists($path);
    }

    private function removePath(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);
            return;
        }

        if (is_dir($path)) {
            $this->filesystem->removeDirectory($path);
            return;
        }

        if (file_exists($path)) {
            $this->filesystem->remove($path);
        }
    }

    /**
     * @param array<string, string> $registry
     * @param Symlink[]             $createdSymlinks
     *
     * @return array<string, string>
     */
    private function recordProcessedSymlinks(array $registry, array $createdSymlinks): array
    {
        foreach ($createdSymlinks as $symlink) {
            if (!$symlink instanceof Symlink) {
                continue;
            }

            $link = $symlink->getLink();
            if (!$this->pathExists($link)) {
                continue;
            }

            $resolvedTarget = $this->resolveTarget($link, $symlink->getTarget());
            if ($resolvedTarget === null) {
                continue;
            }

            $registry[$link] = $resolvedTarget;
        }

        return $registry;
    }

    /**
     * @param array<string, string> $registry
     * @param array<string, string> $configuredSymlinks
     *
     * @return array<string, string>
     */
    private function recordConfiguredSymlinks(array $registry, array $configuredSymlinks): array
    {
        foreach ($configuredSymlinks as $link => $target) {
            if (!\is_string($link) || !\is_string($target)) {
                continue;
            }

            if (isset($registry[$link])) {
                $registry[$link] = $target;
                continue;
            }

            if (!$this->pathExists($link)) {
                continue;
            }

            $resolvedTarget = $this->resolveTarget($link, $target);
            if ($resolvedTarget !== null) {
                $registry[$link] = $resolvedTarget;
            }
        }

        return $registry;
    }

    /**
     * @param array<string, string> $registry
     * @param array<int, string>    $configuredLinks
     *
     * @return array<string, string>
     */
    private function cleanupRemovedSymlinks(array $registry, array $configuredLinks): array
    {
        $allowed = array_fill_keys($configuredLinks, true);

        foreach (array_keys($registry) as $link) {
            if (isset($allowed[$link])) {
                continue;
            }

            $this->removePath($link);
            unset($registry[$link]);
        }

        return $registry;
    }
}
