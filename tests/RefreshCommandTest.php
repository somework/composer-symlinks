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
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'command_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'source');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'file.txt', 'data');

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

        $link = $tmp . DIRECTORY_SEPARATOR . 'link.txt';

        $this->assertLinkOrMirror($link, $tmp . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'file.txt');

        chdir($cwd);
    }

    public function testDryRunOptionDoesNotCreateLinks(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'command_' . uniqid();
        mkdir($tmp);
        mkdir($tmp . DIRECTORY_SEPARATOR . 'source');
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'file.txt', 'data');

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

        $this->assertFalse(file_exists($tmp . DIRECTORY_SEPARATOR . 'link.txt'));

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

    private function assertLinkOrMirror(string $link, string $target): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            if (!is_link($link)) {
                $this->assertFileMirrors($target, $link);

                return;
            }

            $linkTarget = @readlink($link);
            if ($linkTarget === false) {
                $this->assertFileMirrors($target, $link);

                return;
            }
        } else {
            $this->assertTrue(is_link($link));
            $linkTarget = readlink($link);
            $this->assertNotFalse($linkTarget);
        }

        $this->assertTrue(is_link($link));

        $normalizedLinkTarget = $this->normalizePath($linkTarget);

        $resolvedLinkTarget = $this->isAbsolutePath($normalizedLinkTarget)
            ? realpath($normalizedLinkTarget)
            : realpath(dirname($link) . DIRECTORY_SEPARATOR . $normalizedLinkTarget);

        $this->assertNotFalse($resolvedLinkTarget);

        $this->assertSame(realpath($target), $resolvedLinkTarget);
    }

    private function normalizePath(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }

    private function assertFileMirrors(string $target, string $path): void
    {
        $this->assertFileExists($path);
        $targetContents = file_get_contents($target);
        $linkContents = file_get_contents($path);

        $this->assertNotFalse($targetContents);
        $this->assertNotFalse($linkContents);
        $this->assertSame($targetContents, $linkContents);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return (bool) preg_match('{^(?:[A-Za-z]:\\\\|\\\\\\\\)}', $path);
        }

        return $path[0] === DIRECTORY_SEPARATOR;
    }
}

