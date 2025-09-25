<?php

namespace SomeWork\Symlinks\Tests;

use PHPUnit\Framework\TestCase;

class ComposerIntegrationTest extends TestCase
{
    public function testPackageCreatesSymlinksViaComposer(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'project_' . uniqid();
        mkdir($tmp);

        // prepare sources
        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceA', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceA' . DIRECTORY_SEPARATOR . 'fileA.txt', 'A');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceB', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceB' . DIRECTORY_SEPARATOR . 'fileB.txt', 'B');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceC', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceC' . DIRECTORY_SEPARATOR . 'fileC.txt', 'C');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'missing', 0777, true);

        // existing file to be replaced
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'replaceLink.txt', 'old');

        $pluginPath = realpath(__DIR__ . '/..');

        $composerData = [
            'name' => 'test/project',
            'minimum-stability' => 'dev',
            'require' => [
                'somework/composer-symlinks' => '*'
            ],
            'repositories' => [
                ['type' => 'path', 'url' => $pluginPath, 'options' => ['symlink' => false]]
            ],
            'config' => [
                'allow-plugins' => [
                    'somework/composer-symlinks' => true
                ]
            ],
            'extra' => [
                'somework/composer-symlinks' => [
                    'symlinks' => [
                        'sourceA/fileA.txt' => 'linkA.txt',
                        'sourceB/fileB.txt' => [
                            'link' => 'linkB.txt',
                            'absolute-path' => true
                        ],
                        'missing/file.txt' => [
                            'link' => 'missingLink.txt',
                            'skip-missing-target' => true
                        ],
                        'sourceC/fileC.txt' => [
                            'link' => 'replaceLink.txt',
                            'force-create' => true
                        ]
                    ],
                    'skip-missing-target' => true
                ]
            ]
        ];
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $cwd = getcwd();
        chdir($tmp);
        exec('composer install --no-interaction --no-ansi 2>&1', $output, $code);
        chdir($cwd);

        $this->assertSame(0, $code, implode("\n", $output));

        $linkA = $tmp . DIRECTORY_SEPARATOR . 'linkA.txt';
        $this->assertLinkOrMirror(
            $linkA,
            $tmp . DIRECTORY_SEPARATOR . 'sourceA' . DIRECTORY_SEPARATOR . 'fileA.txt',
            $tmp,
            true
        );

        $linkB = $tmp . DIRECTORY_SEPARATOR . 'linkB.txt';
        $this->assertLinkOrMirror(
            $linkB,
            $tmp . DIRECTORY_SEPARATOR . 'sourceB' . DIRECTORY_SEPARATOR . 'fileB.txt',
            $tmp
        );

        $this->assertFalse(file_exists($tmp . DIRECTORY_SEPARATOR . 'missingLink.txt'));

        $replaceLink = $tmp . DIRECTORY_SEPARATOR . 'replaceLink.txt';
        $this->assertLinkOrMirror(
            $replaceLink,
            $tmp . DIRECTORY_SEPARATOR . 'sourceC' . DIRECTORY_SEPARATOR . 'fileC.txt',
            $tmp
        );
    }

    public function testDryRunViaComposerDoesNotCreateLinks(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'project_' . uniqid();
        mkdir($tmp);

        // prepare sources
        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceA', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceA' . DIRECTORY_SEPARATOR . 'fileA.txt', 'A');

        $pluginPath = realpath(__DIR__ . '/..');

        $composerData = [
            'name' => 'test/project',
            'minimum-stability' => 'dev',
            'require' => [
                'somework/composer-symlinks' => '*'
            ],
            'repositories' => [
                ['type' => 'path', 'url' => $pluginPath, 'options' => ['symlink' => false]]
            ],
            'config' => [
                'allow-plugins' => [
                    'somework/composer-symlinks' => true
                ]
            ],
            'extra' => [
                'somework/composer-symlinks' => [
                    'symlinks' => [
                        'sourceA/fileA.txt' => 'linkA.txt'
                    ]
                ]
            ]
        ];
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $cwd = getcwd();
        chdir($tmp);
        putenv('SYMLINKS_DRY_RUN=1');
        exec('composer install --no-interaction --no-ansi 2>&1', $output, $code);
        putenv('SYMLINKS_DRY_RUN');
        chdir($cwd);

        $this->assertSame(0, $code, implode("\n", $output));

        $this->assertFalse(file_exists($tmp . DIRECTORY_SEPARATOR . 'linkA.txt'));
    }

    public function testCleanupRemovesUnusedSymlinks(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'project_' . uniqid();
        mkdir($tmp);

        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceA', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceA' . DIRECTORY_SEPARATOR . 'fileA.txt', 'A');
        mkdir($tmp . DIRECTORY_SEPARATOR . 'sourceB', 0777, true);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sourceB' . DIRECTORY_SEPARATOR . 'fileB.txt', 'B');

        $pluginPath = realpath(__DIR__ . '/..');

        $composerData = [
            'name' => 'test/project',
            'minimum-stability' => 'dev',
            'require' => [
                'somework/composer-symlinks' => '*'
            ],
            'repositories' => [
                ['type' => 'path', 'url' => $pluginPath, 'options' => ['symlink' => false]]
            ],
            'config' => [
                'allow-plugins' => [
                    'somework/composer-symlinks' => true
                ]
            ],
            'extra' => [
                'somework/composer-symlinks' => [
                    'cleanup' => true,
                    'symlinks' => [
                        'sourceA/fileA.txt' => 'linkA.txt',
                        'sourceB/fileB.txt' => 'linkB.txt'
                    ]
                ]
            ]
        ];
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $cwd = getcwd();
        chdir($tmp);
        exec('composer install --no-interaction --no-ansi 2>&1', $output, $code);
        chdir($cwd);

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertLinkExists($tmp . DIRECTORY_SEPARATOR . 'linkA.txt');
        $this->assertLinkExists($tmp . DIRECTORY_SEPARATOR . 'linkB.txt');

        $registryPath = $tmp . DIRECTORY_SEPARATOR . 'vendor/composer-symlinks-state.json';
        $this->assertFileExists($registryPath);
        $registry = json_decode((string)file_get_contents($registryPath), true);
        $this->assertIsArray($registry);
        $this->assertArrayHasKey($this->normalizePath($tmp . '/linkA.txt'), $registry);
        $this->assertArrayHasKey($this->normalizePath($tmp . '/linkB.txt'), $registry);

        unset($composerData['extra']['somework/composer-symlinks']['symlinks']['sourceB/fileB.txt']);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        chdir($tmp);
        $output = [];
        exec('composer update --no-interaction --no-ansi 2>&1', $output, $code);
        chdir($cwd);

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertLinkExists($tmp . DIRECTORY_SEPARATOR . 'linkA.txt');
        $this->assertFalse(file_exists($tmp . DIRECTORY_SEPARATOR . 'linkB.txt'));

        $registry = json_decode((string)file_get_contents($registryPath), true);
        $this->assertIsArray($registry);
        $this->assertArrayHasKey($this->normalizePath($tmp . '/linkA.txt'), $registry);
        $this->assertArrayNotHasKey($this->normalizePath($tmp . '/linkB.txt'), $registry);
    }

    private function resolveLinkTarget(string $link, string $baseDir, ?string $linkTarget = null): string
    {
        $target = $linkTarget ?? readlink($link);
        $this->assertNotFalse($target);

        $normalized = $this->normalizePath($target);

        if ($this->isAbsolutePath($normalized)) {
            $resolved = realpath($normalized);
            $this->assertNotFalse($resolved);

            return $resolved;
        }

        $resolved = realpath(dirname($link) . DIRECTORY_SEPARATOR . $normalized);
        if ($resolved !== false) {
            return $resolved;
        }

        $resolved = realpath($baseDir . DIRECTORY_SEPARATOR . $normalized);
        $this->assertNotFalse($resolved);

        return $resolved;
    }

    private function assertLinkOrMirror(string $link, string $target, string $baseDir, bool $expectRelative = false): void
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

        if ($expectRelative) {
            $this->assertNotSame('/', substr($linkTarget, 0, 1));
        }

        $this->assertSame(
            realpath($target),
            $this->resolveLinkTarget($link, $baseDir, $linkTarget)
        );
    }

    private function assertLinkExists(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && !is_link($path)) {
            $this->assertFileExists($path);

            return;
        }

        $this->assertTrue(is_link($path));
    }

    private function assertFileMirrors(string $target, string $path): void
    {
        $this->assertFileExists($path);
        $targetContents = file_get_contents($target);
        $pathContents = file_get_contents($path);

        $this->assertNotFalse($targetContents);
        $this->assertNotFalse($pathContents);
        $this->assertSame($targetContents, $pathContents);
    }

    private function normalizePath(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
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
