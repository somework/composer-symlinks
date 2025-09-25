<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Symlink;
use SomeWork\Symlinks\SymlinksProcessor;

class SymlinksProcessorTest extends TestCase
{
    public function testProcessSymlinkCreatesLink(): void
    {
        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/link.txt';

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true);

        $processor = new SymlinksProcessor(new Filesystem());
        $result = $processor->processSymlink($symlink);

        $this->assertTrue($result);
        $this->assertTrue(is_link($link));
        $this->assertSame(realpath($target), realpath(readlink($link)));
    }

    public function testDryRunDoesNotCreateLink(): void
    {
        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/link.txt';

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true);

        $processor = new SymlinksProcessor(new Filesystem(), true);
        $result = $processor->processSymlink($symlink);

        $this->assertTrue($result);
        $this->assertFalse(file_exists($link));
    }

    public function testForceCreateReplacesExistingLink(): void
    {
        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'new');

        $link = $tmp . '/link.txt';
        file_put_contents($link, 'old');

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true)
            ->setForceCreate(true);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('remove')
            ->with($link)
            ->willReturnCallback(function (string $path) {
                return unlink($path);
            });

        $processor = new SymlinksProcessor($filesystem);
        $result = $processor->processSymlink($symlink);

        $this->assertTrue($result);
        $this->assertTrue(is_link($link));
        $this->assertSame(realpath($target), realpath(readlink($link)));
    }

    public function testThrowsErrorWhenLinkExists(): void
    {
        $this->expectException(\SomeWork\Symlinks\LinkDirectoryError::class);

        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/link.txt';
        file_put_contents($link, 'old');

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true);

        $processor = new SymlinksProcessor(new Filesystem());
        $processor->processSymlink($symlink);
    }

    public function testDryRunThrowsErrorWhenLinkExists(): void
    {
        $this->expectException(\SomeWork\Symlinks\LinkDirectoryError::class);

        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/link.txt';
        file_put_contents($link, 'old');

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true);

        $processor = new SymlinksProcessor(new Filesystem(), true);
        $processor->processSymlink($symlink);
    }

    public function testProcessSymlinkCreatesRelativeLink(): void
    {
        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/dir', 0777, true);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/dir/link.txt';

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link);

        $processor = new SymlinksProcessor(new Filesystem());
        $result = $processor->processSymlink($symlink);

        $this->assertTrue($result);
        $this->assertTrue(is_link($link));
        $this->assertSame(realpath($target), realpath(dirname($link) . '/' . readlink($link)));
    }

    /**
     * @requires OSFAMILY Windows
     */
    public function testWindowsFallbackCreatesJunctionWhenSymlinksUnavailable(): void
    {
        $tmp = sys_get_temp_dir() . '\\processor_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '\\target');
        file_put_contents($tmp . '\\target\\file.txt', 'data');

        $link = $tmp . '\\linkDir';

        if (@symlink($tmp . '\\target', $link)) {
            // Symlinks are available; cleanup and skip so that fallback is not exercised.
            $filesystem = new Filesystem();
            $filesystem->removeDirectory($link);
            $this->markTestSkipped('Native symlinks are available.');
        }

        $symlink = (new Symlink())
            ->setTarget($tmp . '\\target')
            ->setLink($link)
            ->setAbsolutePath(true)
            ->setWindowsMode(Symlink::WINDOWS_MODE_JUNCTION);

        $processor = new SymlinksProcessor(new Filesystem());

        try {
            $result = $processor->processSymlink($symlink);
            $this->assertTrue($result);
            $this->assertDirectoryExists($link);
            $this->assertFileExists($link . DIRECTORY_SEPARATOR . 'file.txt');
            $this->assertSame('data', file_get_contents($link . DIRECTORY_SEPARATOR . 'file.txt'));
        } finally {
            $filesystem = new Filesystem();
            $filesystem->removeDirectory($tmp);
        }
    }

    /**
     * @requires OSFAMILY Windows
     */
    public function testWindowsFallbackCreatesCopyWhenHardlinksUnavailable(): void
    {
        $tmp = sys_get_temp_dir() . '\\processor_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '\\target.txt', 'payload');

        $link = $tmp . '\\link.txt';

        if (@symlink($tmp . '\\target.txt', $link)) {
            unlink($link);
            $this->markTestSkipped('Native symlinks are available.');
        }

        $symlink = (new Symlink())
            ->setTarget($tmp . '\\target.txt')
            ->setLink($link)
            ->setAbsolutePath(true)
            ->setWindowsMode(Symlink::WINDOWS_MODE_JUNCTION);

        $processor = new SymlinksProcessor(new Filesystem());

        try {
            $result = $processor->processSymlink($symlink);
            $this->assertTrue($result);
            $this->assertFileExists($link);
            $this->assertSame('payload', file_get_contents($link));
        } finally {
            $filesystem = new Filesystem();
            $filesystem->removeDirectory($tmp);
        }
    }

    /**
     * @requires OSFAMILY Windows
     */
    public function testWindowsCopyModeSkipsSymlinks(): void
    {
        $tmp = sys_get_temp_dir() . '\\processor_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '\\target.txt', 'payload');

        $link = $tmp . '\\copy.txt';

        $symlink = (new Symlink())
            ->setTarget($tmp . '\\target.txt')
            ->setLink($link)
            ->setAbsolutePath(true)
            ->setWindowsMode(Symlink::WINDOWS_MODE_COPY);

        $processor = new SymlinksProcessor(new Filesystem());

        try {
            $result = $processor->processSymlink($symlink);
            $this->assertTrue($result);
            $this->assertFileExists($link);
            $this->assertFalse(is_link($link));
            $this->assertSame('payload', file_get_contents($link));
        } finally {
            $filesystem = new Filesystem();
            $filesystem->removeDirectory($tmp);
        }
    }
}
