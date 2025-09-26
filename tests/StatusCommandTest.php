<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Command\StatusCommand;
use Symfony\Component\Console\Tester\CommandTester;

class StatusCommandTest extends TestCase
{
    public function testStatusCommandOutputsReport(): void
    {
        $environment = $this->createEnvironment();
        $composer = $this->createComposer($environment['extra'], $environment['vendorDir']);

        $io = $this->createIoSpy();
        $tester = $this->createCommandTester($composer, $io['mock']);

        $cwd = getcwd();
        chdir($environment['projectDir']);

        try {
            $exitCode = $tester->execute(['command' => 'symlinks:status']);
        } finally {
            chdir($cwd);
            $this->removeDirectory($environment['projectDir']);
        }

        $this->assertSame(0, $exitCode);

        $output = implode("\n", $io['output']);
        $errors = implode("\n", $io['errors']);

        $this->assertStringContainsString('Configured symlinks', $output);
        $this->assertStringContainsString('[OK]', $output);
        $this->assertStringContainsString('[MISSING]', $output);
        $this->assertStringContainsString('[MISMATCH]', $output);
        $this->assertStringContainsString('Registry entries', $output);
        $this->assertStringContainsString('[STALE]', $output);
        $this->assertStringContainsString('[ORPHAN]', $output);
        $this->assertStringContainsString('Problems were found while checking symlinks.', $errors);
    }

    public function testStatusCommandOutputsJson(): void
    {
        $environment = $this->createEnvironment();
        $composer = $this->createComposer($environment['extra'], $environment['vendorDir']);

        $io = $this->createIoSpy();
        $tester = $this->createCommandTester($composer, $io['mock']);

        $cwd = getcwd();
        chdir($environment['projectDir']);

        try {
            $exitCode = $tester->execute([
                'command' => 'symlinks:status',
                '--json' => true,
            ]);
        } finally {
            chdir($cwd);
            $this->removeDirectory($environment['projectDir']);
        }

        $this->assertSame(0, $exitCode);

        $this->assertCount(1, $io['output']);
        $decoded = json_decode($io['output'][0], true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('configured', $decoded);
        $this->assertArrayHasKey('registry', $decoded);

        $statuses = array_column($decoded['configured'], 'status');
        $this->assertContains('ok', $statuses);
        $this->assertContains('missing', $statuses);
        $this->assertContains('mismatch', $statuses);

        $registryStatuses = array_column($decoded['registry'], 'status');
        $this->assertContains('stale', $registryStatuses);
        $this->assertContains('orphan', $registryStatuses);

        $this->assertSame([], $io['errors']);
    }

    public function testStrictOptionReturnsFailure(): void
    {
        $environment = $this->createEnvironment();
        $composer = $this->createComposer($environment['extra'], $environment['vendorDir']);

        $io = $this->createIoSpy();
        $tester = $this->createCommandTester($composer, $io['mock']);

        $cwd = getcwd();
        chdir($environment['projectDir']);

        try {
            $exitCode = $tester->execute([
                'command' => 'symlinks:status',
                '--strict' => true,
            ]);
        } finally {
            chdir($cwd);
            $this->removeDirectory($environment['projectDir']);
        }

        $this->assertSame(1, $exitCode);
    }

    /**
     * @return array{projectDir: string, vendorDir: string, extra: array}
     */
    private function createEnvironment(): array
    {
        $projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'status_' . uniqid('', true);
        $sourceDir = $projectDir . DIRECTORY_SEPARATOR . 'source';
        $vendorDir = $projectDir . DIRECTORY_SEPARATOR . 'vendor';

        mkdir($sourceDir, 0777, true);
        mkdir($vendorDir, 0777, true);

        file_put_contents($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt', 'ok');
        file_put_contents($sourceDir . DIRECTORY_SEPARATOR . 'missing.txt', 'missing');
        file_put_contents($sourceDir . DIRECTORY_SEPARATOR . 'other.txt', 'other');

        symlink($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt', $projectDir . DIRECTORY_SEPARATOR . 'link-ok.txt');
        symlink($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt', $projectDir . DIRECTORY_SEPARATOR . 'link-mismatch.txt');
        symlink($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt', $projectDir . DIRECTORY_SEPARATOR . 'registry-orphan.txt');

        $registryData = [
            $projectDir . DIRECTORY_SEPARATOR . 'registry-stale.txt' => realpath($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt'),
            $projectDir . DIRECTORY_SEPARATOR . 'registry-orphan.txt' => realpath($sourceDir . DIRECTORY_SEPARATOR . 'ok.txt'),
        ];

        file_put_contents(
            $vendorDir . DIRECTORY_SEPARATOR . 'composer-symlinks-state.json',
            json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $extra = [
            'somework/composer-symlinks' => [
                'symlinks' => [
                    'source/ok.txt' => 'link-ok.txt',
                    'source/missing.txt' => 'link-missing.txt',
                    'source/other.txt' => 'link-mismatch.txt',
                ],
            ],
        ];

        return [
            'projectDir' => $projectDir,
            'vendorDir' => $vendorDir,
            'extra' => $extra,
        ];
    }

    private function createComposer(array $extra, string $vendorDir): Composer
    {
        $composer = new Composer();
        $io = new NullIO();
        $dispatcher = new EventDispatcher($composer, $io);
        $composer->setEventDispatcher($dispatcher);

        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra($extra);
        $composer->setPackage($package);

        $config = new \Composer\Config();
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $composer->setConfig($config);

        return $composer;
    }

    /**
     * @return array{mock: IOInterface, output: array<int, string>, errors: array<int, string>}
     */
    private function createIoSpy(): array
    {
        $output = [];
        $errors = [];

        $io = $this->createMock(IOInterface::class);
        $io->method('write')->willReturnCallback(function ($messages, $newline = true, $verbosity = IOInterface::NORMAL) use (&$output): void {
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $message) {
                $output[] = $message;
            }
        });
        $io->method('writeError')->willReturnCallback(function ($messages, $newline = true, $verbosity = IOInterface::NORMAL) use (&$errors): void {
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $message) {
                $errors[] = $message;
            }
        });
        $io->method('isDecorated')->willReturn(false);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isVeryVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);

        return [
            'mock' => $io,
            'output' => &$output,
            'errors' => &$errors,
        ];
    }

    private function createCommandTester(Composer $composer, IOInterface $io): CommandTester
    {
        $command = new StatusCommand();
        $command->setComposer($composer);
        $command->setIO($io);

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);

        return new CommandTester($application->find('symlinks:status'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                @unlink($item->getPathname());
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
