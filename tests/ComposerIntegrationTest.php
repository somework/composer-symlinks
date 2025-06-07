<?php

namespace SomeWork\Symlinks\Tests;

use PHPUnit\Framework\TestCase;

class ComposerIntegrationTest extends TestCase
{
    public function testPackageCreatesSymlinksViaComposer(): void
    {
        $tmp = sys_get_temp_dir() . '/project_' . uniqid();
        mkdir($tmp);

        // prepare sources
        mkdir($tmp . '/sourceA', 0777, true);
        file_put_contents($tmp . '/sourceA/fileA.txt', 'A');
        mkdir($tmp . '/sourceB', 0777, true);
        file_put_contents($tmp . '/sourceB/fileB.txt', 'B');
        mkdir($tmp . '/sourceC', 0777, true);
        file_put_contents($tmp . '/sourceC/fileC.txt', 'C');
        mkdir($tmp . '/missing', 0777, true);

        // existing file to be replaced
        file_put_contents($tmp . '/replaceLink.txt', 'old');

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
        file_put_contents($tmp . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $cwd = getcwd();
        chdir($tmp);
        exec('composer install --no-interaction --no-ansi 2>&1', $output, $code);
        chdir($cwd);

        $this->assertSame(0, $code, implode("\n", $output));

        $this->assertTrue(is_link($tmp . '/linkA.txt'));
        $this->assertSame(
            realpath($tmp . '/sourceA/fileA.txt'),
            realpath($tmp . '/' . readlink($tmp . '/linkA.txt'))
        );
        $this->assertFalse(str_starts_with(readlink($tmp . '/linkA.txt'), '/'));

        $this->assertTrue(is_link($tmp . '/linkB.txt'));
        $this->assertSame(
            realpath($tmp . '/sourceB/fileB.txt'),
            readlink($tmp . '/linkB.txt')
        );

        $this->assertFalse(file_exists($tmp . '/missingLink.txt'));

        $this->assertTrue(is_link($tmp . '/replaceLink.txt'));
        $this->assertSame(
            realpath($tmp . '/sourceC/fileC.txt'),
            realpath($tmp . '/' . readlink($tmp . '/replaceLink.txt'))
        );
    }
}
