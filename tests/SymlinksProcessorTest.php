<?php

namespace SomeWork\Symlinks\Tests;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SomeWork\Symlinks\Symlink;
use SomeWork\Symlinks\SymlinksProcessor;

class SymlinksProcessorTest extends TestCase
{
    public function testProcessSymlinkCreatesLink(): void
    {
        $tmp = sys_get_temp_dir() . '/processor_' . uniqid();
        mkdir($tmp);
        $target = $tmp . '/target.txt';
        file_put_contents($target, 'data');

        $link = $tmp . '/link.txt';

        $symlink = (new Symlink())
            ->setTarget($target)
            ->setLink($link)
            ->setAbsolutePath(true);

        $processor = new SymlinksProcessor(new Filesystem());
        $result = $processor->processSymlink($symlink);

        $this->assertTrue($result);
        $this->assertTrue(is_link($link));
        $this->assertSame(realpath($target), realpath(readlink($link)));
    }
}
