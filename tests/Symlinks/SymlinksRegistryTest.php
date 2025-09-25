<?php

declare(strict_types=1);

namespace SomeWork\Symlinks\Tests\Symlinks;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Symlink;
use SomeWork\Symlinks\SymlinksRegistry;

class SymlinksRegistryTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
    }

    public function testSyncRecordsProcessedAndConfiguredSymlinks(): void
    {
        $workspace = $this->createWorkspace();
        $vendorDir = $workspace . '/vendor';
        mkdir($vendorDir, 0777, true);

        $registry = new SymlinksRegistry($this->filesystem, $vendorDir);

        // Seed the registry with an entry referencing a missing link to ensure cleanup.
        file_put_contents(
            $registry->getRegistryFile(),
            json_encode([
                $workspace . '/stale-link' => $workspace . '/stale-target',
                ['invalid'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $targetFile = $workspace . '/target.txt';
        file_put_contents($targetFile, 'content');

        $processedLink = $workspace . '/processed-link.txt';
        symlink($targetFile, $processedLink);

        $createdSymlink = (new Symlink())
            ->setLink($processedLink)
            ->setTarget($targetFile);

        $configuredLink = $workspace . '/configured-link.txt';
        symlink($targetFile, $configuredLink);

        $registry->sync([
            $configuredLink => $targetFile,
        ], [$createdSymlink], false);

        $state = $registry->load();

        $this->assertArrayHasKey($processedLink, $state);
        $this->assertSame(realpath($targetFile), $state[$processedLink]);
        $this->assertArrayHasKey($configuredLink, $state);
        $this->assertSame(realpath($targetFile), $state[$configuredLink]);
        $this->assertArrayNotHasKey($workspace . '/stale-link', $state);

        $this->filesystem->removeDirectory($workspace);
    }

    public function testSyncCleanupRemovesStaleEntriesAndPaths(): void
    {
        $workspace = $this->createWorkspace();
        $vendorDir = $workspace . '/vendor';
        mkdir($vendorDir, 0777, true);

        $registry = new SymlinksRegistry($this->filesystem, $vendorDir);

        $targetA = $workspace . '/target-a.txt';
        $targetB = $workspace . '/target-b.txt';
        file_put_contents($targetA, 'A');
        file_put_contents($targetB, 'B');

        $linkA = $workspace . '/link-a.txt';
        $linkB = $workspace . '/link-b.txt';
        symlink($targetA, $linkA);
        symlink($targetB, $linkB);

        $registry->sync([
            $linkA => $targetA,
            $linkB => $targetB,
        ], [
            (new Symlink())->setLink($linkA)->setTarget($targetA),
            (new Symlink())->setLink($linkB)->setTarget($targetB),
        ], false);

        $this->assertFileExists($registry->getRegistryFile());

        $registry->sync([
            $linkA => $targetA,
        ], [], true);

        $state = $registry->load();

        $this->assertArrayHasKey($linkA, $state);
        $this->assertArrayNotHasKey($linkB, $state);
        $this->assertFileDoesNotExist($linkB);

        $this->filesystem->removeDirectory($workspace);
    }

    public function testRemoveAllDeletesRegisteredLinksAndStateFile(): void
    {
        $workspace = $this->createWorkspace();
        $vendorDir = $workspace . '/vendor';
        mkdir($vendorDir, 0777, true);

        $registry = new SymlinksRegistry($this->filesystem, $vendorDir);

        $target = $workspace . '/target.txt';
        file_put_contents($target, 'content');

        $link = $workspace . '/link.txt';
        symlink($target, $link);

        $registry->sync([
            $link => $target,
        ], [
            (new Symlink())->setLink($link)->setTarget($target),
        ], false);

        $this->assertFileExists($link);
        $this->assertFileExists($registry->getRegistryFile());

        $registry->removeAll();

        $this->assertFileDoesNotExist($link);
        $this->assertFileDoesNotExist($registry->getRegistryFile());

        $this->filesystem->removeDirectory($workspace);
    }

    private function createWorkspace(): string
    {
        $workspace = sys_get_temp_dir() . '/symlinks_registry_' . uniqid('', true);
        mkdir($workspace, 0777, true);

        return $workspace;
    }
}
