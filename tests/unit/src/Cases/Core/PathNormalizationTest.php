<?php

namespace BlueChip\Cache\Tests\Unit\Cases\Core;

use BlueChip\Cache\Core;
use BlueChip\Cache\Exception;

class PathNormalizationTest extends \BlueChip\Cache\Tests\Unit\TestCase
{
    public function testEmptyPath()
    {
        $this->expectException(Exception::class);
        $this->runUnaccessibleStaticMethod(Core::class, 'normalizePath', ['']);
    }


    public function testRelativePath()
    {
        $this->expectException(Exception::class);
        $this->runUnaccessibleStaticMethod(Core::class, 'normalizePath', ['a/relative/path']);
    }


    public function providePathsData(): array
    {
        // Format: raw path, normalized path
        return [
            'separators-only'       => ['/\/', '/'],
            'empty-segments'        => ['//path/with/empty//segments', '/path/with/empty/segments'],
            'segments-with-spaces'  => ['/path with/segments with spaces', '/path with/segments with spaces'],
            'mixed-separators'      => ['/this-path\has/mixed/\path-separators', '/this-path/has/mixed/path-separators'],
            'backslash-separators'  => ['\this\is\windows', '/this/is/windows'],
            'dot-segments'          => ['/path/./with/././dot-segments', '/path/with/dot-segments'],
            'dots-segments'         => ['/ignore-me/../path/with/ignore-me-too/../dots-segments', '/path/with/dots-segments'],
            'trailing-separator'    => ['/trailing/separator/', '/trailing/separator'],
            'all-in'                => ['//empty/ignore-me/../mixed\.//with spaces/', '/empty/mixed/with spaces']
        ];
    }


    /**
     * @dataProvider providePathsData
     */
    public function testPathNormalization(string $raw_path, string $normalized_path): void
    {
        $this->assertSame(
            $normalized_path,
            $this->runUnaccessibleStaticMethod(Core::class, 'normalizePath', [$raw_path])
        );
    }
}
