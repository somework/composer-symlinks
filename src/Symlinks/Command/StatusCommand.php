<?php
declare(strict_types=1);

namespace SomeWork\Symlinks\Command;

use Composer\Command\BaseCommand;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use SomeWork\Symlinks\SymlinksFactory;
use SomeWork\Symlinks\SymlinksRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends BaseCommand
{
    private const STATUS_OK = 'ok';
    private const STATUS_MISSING = 'missing';
    private const STATUS_MISMATCH = 'mismatch';
    private const STATUS_UNEXPECTED = 'unexpected';
    private const STATUS_BROKEN = 'broken';
    private const STATUS_STALE = 'stale';
    private const STATUS_ORPHAN = 'orphan';

    protected function configure(): void
    {
        $this
            ->setName('symlinks:status')
            ->setDescription('Show the current status of configured symlinks.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output the status information as JSON.')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Return a non-zero exit code when problems are found.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();

        $filesystem = new Filesystem();
        $event = new Event('symlinks:status', $composer, $io);
        $factory = new SymlinksFactory($event, $filesystem);

        try {
            $factory->process();
        } catch (\Throwable $exception) {
            $io->writeError(sprintf('<error>Failed to read symlink configuration: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $configuredSymlinks = $factory->getConfiguredSymlinks();
        ksort($configuredSymlinks);

        $registryData = [];
        $vendorDir = $factory->getVendorDirPath();
        if ($vendorDir !== null) {
            $registry = new SymlinksRegistry($filesystem, $vendorDir);
            $registryData = $registry->load();
            ksort($registryData);
        }

        $report = [
            'configured' => $this->buildConfiguredReport($configuredSymlinks, $filesystem),
            'registry' => $this->buildRegistryReport($configuredSymlinks, $registryData, $filesystem),
        ];

        $hasProblems = $this->hasProblems($report);

        if ($input->getOption('json')) {
            $io->write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $hasProblems && $input->getOption('strict') ? Command::FAILURE : Command::SUCCESS;
        }

        $this->renderReport($report, $io);

        if ($hasProblems) {
            $io->writeError('<error>Problems were found while checking symlinks.</error>');

            if ($input->getOption('strict')) {
                return Command::FAILURE;
            }
        } else {
            $io->write('<info>No issues detected.</info>');
        }

        return Command::SUCCESS;
    }

    private function buildConfiguredReport(array $configuredSymlinks, Filesystem $filesystem): array
    {
        $report = [];

        foreach ($configuredSymlinks as $link => $target) {
            [$actual, $type] = $this->inspectPath($link, $filesystem);
            $status = self::STATUS_OK;

            if ($type === 'missing') {
                $status = self::STATUS_MISSING;
            } elseif ($type === 'file') {
                $status = self::STATUS_UNEXPECTED;
            } elseif ($type === 'symlink-broken') {
                $status = self::STATUS_BROKEN;
            } elseif ($type === 'symlink') {
                if ($actual === null || $this->normalizePath($actual) !== $this->normalizePath($target)) {
                    $status = self::STATUS_MISMATCH;
                }
            }

            $report[] = [
                'link' => $link,
                'expected' => $target,
                'actual' => $actual,
                'status' => $status,
                'type' => 'configured',
            ];
        }

        return $report;
    }

    private function buildRegistryReport(array $configuredSymlinks, array $registryData, Filesystem $filesystem): array
    {
        $report = [];

        foreach ($registryData as $link => $target) {
            if (isset($configuredSymlinks[$link])) {
                continue;
            }

            [$actual, $type] = $this->inspectPath($link, $filesystem);
            $status = self::STATUS_ORPHAN;

            if ($type === 'missing') {
                $status = self::STATUS_STALE;
            } elseif ($type === 'symlink-broken') {
                $status = self::STATUS_BROKEN;
            }

            $report[] = [
                'link' => $link,
                'expected' => $target,
                'actual' => $actual,
                'status' => $status,
                'type' => 'registry',
            ];
        }

        return $report;
    }

    private function inspectPath(string $link, Filesystem $filesystem): array
    {
        if (is_link($link)) {
            $target = @readlink($link);
            if ($target === false) {
                return [null, 'symlink-broken'];
            }

            if ($filesystem->isAbsolutePath($target)) {
                $resolved = realpath($target);
                if ($resolved === false) {
                    $resolved = $target;
                }
            } else {
                $base = realpath(dirname($link));
                if ($base === false) {
                    $base = dirname($link);
                }

                $combined = $base . DIRECTORY_SEPARATOR . $target;
                $resolved = realpath($combined);
                if ($resolved === false) {
                    $resolved = $combined;
                }
            }

            return [$resolved, 'symlink'];
        }

        if (file_exists($link)) {
            $resolved = realpath($link);
            if ($resolved === false) {
                $resolved = $link;
            }

            return [$resolved, 'file'];
        }

        return [null, 'missing'];
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return strtolower(str_replace('\\', '/', $path));
        }

        return $path;
    }

    private function hasProblems(array $report): bool
    {
        foreach ($report['configured'] ?? [] as $item) {
            if ($item['status'] !== self::STATUS_OK) {
                return true;
            }
        }

        foreach ($report['registry'] ?? [] as $item) {
            if ($item['status'] !== self::STATUS_OK) {
                return true;
            }
        }

        return false;
    }

    private function renderReport(array $report, \Composer\IO\IOInterface $io): void
    {
        $io->write('<info>Configured symlinks</info>');
        if ($report['configured'] === []) {
            $io->write('  (none)');
        } else {
            foreach ($report['configured'] as $item) {
                $io->write($this->formatLine($item));
            }
        }

        if ($report['registry'] !== []) {
            $io->write('<info>Registry entries</info>');
            foreach ($report['registry'] as $item) {
                $io->write($this->formatLine($item));
            }
        }
    }

    private function formatLine(array $item): string
    {
        $status = strtoupper((string) $item['status']);
        $line = sprintf('  [%s] %s -> %s', $status, $item['link'], $item['expected']);

        if ($item['actual'] !== null && $this->normalizePath($item['actual']) !== $this->normalizePath($item['expected'])) {
            $line .= sprintf(' (actual: %s)', $item['actual']);
        }

        if ($item['status'] === self::STATUS_MISSING) {
            $line .= ' (missing)';
        } elseif ($item['status'] === self::STATUS_BROKEN) {
            $line .= ' (broken link)';
        }

        if ($item['type'] === 'registry') {
            $line .= ' [registry]';
        }

        return $line;
    }
}
