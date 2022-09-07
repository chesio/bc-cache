<?php

namespace BlueChip\Cache\Tests\Unit\Cases\Core;

use BlueChip\Cache\Core;
use BlueChip\Cache\Info;
use BlueChip\Cache\Lock;

/**
 * Class to test Core::getPath() and Core::getUrl() methods.
 */
class GetPathUrlTest extends \BlueChip\Cache\Tests\Unit\TestCase
{
    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;


    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Core('/bc-cache', \Mockery::mock(Info::class), \Mockery::mock(Lock::class));
    }


    public function provideUrlPathsData(): array
    {
        // Format: URL, path
        return [
            'hostname-no-slash'     => ['https://www.example.com', '/bc-cache/https_www.example.com/@file'],
            'hostname-with-slash'   => ['https://www.example.com/', '/bc-cache/https_www.example.com/@dir'],
            'dirname-no-slash'      => ['https://www.example.com/test', '/bc-cache/https_www.example.com/test/@file'],
            'dirname-with-slash'    => ['https://www.example.com/test/', '/bc-cache/https_www.example.com/test/@dir'],
            'filename-no-slash'     => ['https://www.example.com/sitemap.xml', '/bc-cache/https_www.example.com/sitemap.xml/@file'],
            'filename-with-slash'   => ['https://www.example.com/sitemap.xml/', '/bc-cache/https_www.example.com/sitemap.xml/@dir'],
        ];
    }


    /**
     * @dataProvider provideUrlPathsData
     */
    public function testGetPath(string $url, string $path): void
    {
        $this->assertSame(
            $path,
            $this->runUnaccessibleMethod($this->cache, 'getPath', [$url])
        );
    }


    /**
     * @dataProvider provideUrlPathsData
     */
    public function testGetUrl(string $url, string $path): void
    {
        $this->assertSame(
            $url,
            $this->runUnaccessibleMethod($this->cache, 'getUrl', [$path])
        );
    }
}
