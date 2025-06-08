<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use PHPUnit\Framework\TestCase;
use Composer\Util\Filesystem;
use SomeWork\Symlinks\SymlinksFactory;

class SymlinksFactoryTest extends TestCase
{
    public function testProcessCreatesSymlinkDefinition(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/target');
        file_put_contents($tmp . '/target/file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => 'link.txt'
                ],
                'absolute-path' => true
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $symlink = $symlinks[0];
        $this->assertSame(realpath($tmp . '/target/file.txt'), $symlink->getTarget());
        $this->assertSame($tmp . '/link.txt', $symlink->getLink());
        $this->assertTrue($symlink->isAbsolutePath());

        chdir($cwd);
    }

    public function testProcessSkipsMissingTargetPerLink(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'missing/file.txt' => [
                        'link' => 'link.txt',
                        'skip-missing-target' => true
                    ]
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(0, $symlinks);

        chdir($cwd);
    }

    public function testExistingRelativeSymlinkIsNotProcessed(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/target', 0777, true);
        mkdir($tmp . '/dir', 0777, true);
        file_put_contents($tmp . '/target/file.txt', 'content');
        symlink('../target/file.txt', $tmp . '/dir/link.txt');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => 'dir/link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(0, $symlinks);

        chdir($cwd);
    }

    public function testProcessThrowsExceptionForEmptyLink(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'foo' => ''
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('No link passed in config');
        $factory->process();
    }

    public function testProcessThrowsExceptionForEmptyTarget(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    '' => 'link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('No target passed in config');
        $factory->process();
    }

    public function testProcessThrowsExceptionForAbsoluteTarget(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    '/abs/target.txt' => 'link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid symlink target path');
        $factory->process();
    }

    public function testProcessThrowsExceptionForAbsoluteLink(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => '/abs/link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid symlink link path');
        $factory->process();
    }

    public function testProcessThrowsExceptionWhenSymlinksIsNotArray(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => 'invalid'
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('The extra.somework/composer-symlinks.symlinks setting must be an array.');
        $factory->process();
    }

    public function testProcessReturnsEmptyWhenNoSymlinksConfigured(): void
    {
        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertSame([], $symlinks);
    }

    public function testProcessSkipsMissingTargetGlobally(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'missing/file.txt' => 'link.txt'
                ],
                'skip-missing-target' => true
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(0, $symlinks);

        chdir($cwd);
    }

    public function testProcessSetsForceCreateFromConfig(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/target');
        file_put_contents($tmp . '/target/file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => 'link.txt'
                ],
                'force-create' => true
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $this->assertTrue($symlinks[0]->isForceCreate());

        chdir($cwd);
    }

    public function testProcessThrowsExceptionForMissingTarget(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'missing/file.txt' => 'link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('The target path  does not exists');

        try {
            $factory->process();
        } finally {
            chdir($cwd);
        }
    }

    public function testProcessThrowsLinkDirectoryErrorOnEnsureDirectoryFailure(): void
    {
        $tmp = sys_get_temp_dir() . '/factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/target');
        file_put_contents($tmp . '/target/file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => 'dir/link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('isAbsolutePath')->willReturn(false);
        $filesystem->expects($this->once())
            ->method('ensureDirectoryExists')
            ->willThrowException(new \RuntimeException('fail'));
        $filesystem->expects($this->never())->method('relativeSymlink');

        $factory = new SymlinksFactory($event, $filesystem);

        $this->expectException(\SomeWork\Symlinks\LinkDirectoryError::class);

        try {
            $factory->process();
        } finally {
            chdir($cwd);
        }
    }
}
