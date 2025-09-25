<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

use Composer\IO\IOInterface;

trait SymlinksExecutionTrait
{
    private function runSymlinks(
        SymlinksFactory $factory,
        SymlinksProcessor $processor,
        IOInterface $io,
        bool $dryRun
    ): void {
        $symlinks = $factory->process();
        foreach ($symlinks as $symlink) {
            try {
                if (!$processor->processSymlink($symlink)) {
                    throw new RuntimeException('Unknown error');
                }

                $io->write(sprintf(
                    '  %sSymlinking <comment>%s</comment> to <comment>%s</comment>',
                    $dryRun ? '[DRY RUN] ' : '',
                    $symlink->getLink(),
                    $symlink->getTarget()
                ));
            } catch (LinkDirectoryError $exception) {
                $io->write(sprintf(
                    '  Symlinking <comment>%s</comment> to <comment>%s</comment> - %s',
                    $symlink->getLink(),
                    $symlink->getTarget(),
                    'Skipped'
                ));
            } catch (\Exception $exception) {
                $io->writeError(sprintf(
                    '  Symlinking <comment>%s</comment> to <comment>%s</comment> - %s',
                    $symlink->getLink(),
                    $symlink->getTarget(),
                    $exception->getMessage()
                ));
            }
        }
    }
}
