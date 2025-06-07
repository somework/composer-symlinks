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
}
