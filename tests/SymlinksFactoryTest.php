<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Composer;
use Composer\Config;
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
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
        $this->assertSame(realpath($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt'), $symlink->getTarget());
        $this->assertSame($this->normalizePath($tmp . '/link.txt'), $symlink->getLink());
        $this->assertTrue($symlink->isAbsolutePath());
        $this->assertSame(\SomeWork\Symlinks\Symlink::WINDOWS_MODE_JUNCTION, $symlink->getWindowsMode());

        chdir($cwd);
    }

    public function testProcessSkipsMissingTargetPerLink(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target', 0777, true);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'dir', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $this->createSymlinkOrSkip('../target/file.txt', $tmp . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'link.txt');
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
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

    public function testProcessExpandsProjectDirAndEnvPlaceholders(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'env-dir');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'env-dir' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'links');
        $cwd = getcwd();
        chdir($tmp);

        putenv('SYMLINKS_CUSTOM_DIR=' . $this->normalizePath($tmp . '/env-dir'));

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    '%env(SYMLINKS_CUSTOM_DIR)%/file.txt' => '%project-dir%/links/env-link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $this->assertSame(realpath($tmp . DIRECTORY_SEPARATOR . 'env-dir' . DIRECTORY_SEPARATOR . 'file.txt'), $symlinks[0]->getTarget());
        $this->assertSame($this->normalizePath($tmp . '/links/env-link.txt'), $symlinks[0]->getLink());

        putenv('SYMLINKS_CUSTOM_DIR');
        chdir($cwd);
    }

    public function testProcessHandlesEmptyEnvPlaceholderExpansion(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'links', 0777, true);
        $cwd = getcwd();
        chdir($tmp);

        $previousValue = getenv('SYMLINKS_OPTIONAL_SEGMENT');
        putenv('SYMLINKS_OPTIONAL_SEGMENT');

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/%env(SYMLINKS_OPTIONAL_SEGMENT)%file.txt' => 'links/%env(SYMLINKS_OPTIONAL_SEGMENT)%file-link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $this->assertSame(realpath($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt'), $symlinks[0]->getTarget());
        $this->assertSame($this->normalizePath($tmp . '/links/file-link.txt'), $symlinks[0]->getLink());

        if ($previousValue === false) {
            putenv('SYMLINKS_OPTIONAL_SEGMENT');
        } else {
            putenv('SYMLINKS_OPTIONAL_SEGMENT=' . $previousValue);
        }

        chdir($cwd);
    }

    public function testProcessExpandsVendorDirPlaceholder(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $vendorDir = $tmp . DIRECTORY_SEPARATOR . 'custom-vendor';
        mkdir($vendorDir);
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $config = new Config(false, $tmp);
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $composer->setConfig($config);
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => '%vendor-dir%/package/link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $this->assertSame(realpath($tmp . '/target/file.txt'), $symlinks[0]->getTarget());
        $this->assertSame($this->normalizePath($vendorDir . '/package/link.txt'), $symlinks[0]->getLink());

        chdir($cwd);
    }

    public function testProcessExpandsVendorDirPlaceholderWithRelativeConfig(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'vendor', 0777, true);
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $config = new Config(false, $tmp);
        $config->merge(['config' => ['vendor-dir' => 'build/vendor']]);
        $composer->setConfig($config);
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'target/file.txt' => '%vendor-dir%/package/link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $this->assertSame(realpath($tmp . '/target/file.txt'), $symlinks[0]->getTarget());
        $this->assertSame($this->normalizePath($tmp . '/build/vendor/package/link.txt'), $symlinks[0]->getLink());

        chdir($cwd);
    }

    public function testProcessAllowsConfiguringWindowsMode(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'windows-mode' => 'copy',
                'symlinks' => [
                    'target/file.txt' => [
                        'link' => 'link.txt',
                        'windows-mode' => 'symlink'
                    ]
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());
        $symlinks = $factory->process();

        $this->assertCount(1, $symlinks);
        $symlink = $symlinks[0];
        $this->assertSame(\SomeWork\Symlinks\Symlink::WINDOWS_MODE_SYMLINK, $symlink->getWindowsMode());

        chdir($cwd);
    }

    public function testProcessRejectsInvalidWindowsMode(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'windows-mode' => 'invalid',
                'symlinks' => [
                    'target/file.txt' => 'link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown windows-mode');

        try {
            $factory->process();
        } finally {
            chdir($cwd);
        }
    }

    public function testProcessRejectsNonScalarWindowsMode(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factory_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'target');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $cwd = getcwd();
        chdir($tmp);

        $composer = new Composer();
        $dispatcher = new EventDispatcher($composer, new NullIO());
        $composer->setEventDispatcher($dispatcher);
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'somework/composer-symlinks' => [
                'windows-mode' => ['array'],
                'symlinks' => [
                    'target/file.txt' => 'link.txt'
                ]
            ]
        ]);
        $composer->setPackage($package);

        $event = new Event('post-install-cmd', $composer, new NullIO());
        $factory = new SymlinksFactory($event, new Filesystem());

        $this->expectException(\SomeWork\Symlinks\InvalidArgumentException::class);
        $this->expectExceptionMessage('The config option windows-mode must be a string or scalar value.');

        try {
            $factory->process();
        } finally {
            chdir($cwd);
        }
    }

    private function normalizePath(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }

    private function createSymlinkOrSkip(string $target, string $link): void
    {
        error_clear_last();
        if (@symlink($target, $link)) {
            return;
        }

        $error = error_get_last();
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlink creation is not available: ' . ($error['message'] ?? 'unknown error'));
        }

        $this->fail('Failed to create symlink: ' . ($error['message'] ?? 'unknown error'));
    }
}
