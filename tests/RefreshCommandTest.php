<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Command\RefreshCommand;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshCommandTest extends TestCase
{
    public function testCommandCreatesSymlinks(): void
    {
        $tmp = sys_get_temp_dir() . '/command_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/source');
        file_put_contents($tmp . '/source/file.txt', 'data');

        $cwd = getcwd();
        chdir($tmp);

        $composer = $this->createComposer([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'source/file.txt' => 'link.txt',
                ],
            ],
        ]);

        $this->runCommand($composer);

        $this->assertTrue(is_link($tmp . '/link.txt'));
        $this->assertSame(
            realpath($tmp . '/source/file.txt'),
            realpath(dirname($tmp . '/link.txt') . '/' . readlink($tmp . '/link.txt'))
        );

        chdir($cwd);
    }

    public function testDryRunOptionDoesNotCreateLinks(): void
    {
        $tmp = sys_get_temp_dir() . '/command_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . '/source');
        file_put_contents($tmp . '/source/file.txt', 'data');

        $cwd = getcwd();
        chdir($tmp);

        $composer = $this->createComposer([
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'source/file.txt' => 'link.txt',
                ],
            ],
        ]);

        $this->runCommand($composer, ['--dry-run' => true]);

        $this->assertFalse(file_exists($tmp . '/link.txt'));

        chdir($cwd);
    }

    private function createComposer(array $extra): Composer
    {
        $composer = new Composer();
        $io = new NullIO();
        $dispatcher = new EventDispatcher($composer, $io);
        $composer->setEventDispatcher($dispatcher);

        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra($extra);
        $composer->setPackage($package);

        $composer->setConfig(new \Composer\Config());

        return $composer;
    }

    private function runCommand(Composer $composer, array $input = []): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $io = new NullIO();

        $command = new RefreshCommand();
        $command->setComposer($composer);
        $command->setIO($io);
        $application->add($command);

        $tester = new CommandTester($application->find('symlinks:refresh'));
        $tester->execute(array_merge(['command' => 'symlinks:refresh'], $input));
    }
}

