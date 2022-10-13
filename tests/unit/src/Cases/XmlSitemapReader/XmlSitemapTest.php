<?php

namespace BlueChip\Cache\Tests\Unit\Cases\XmlSitemapReader;

use BlueChip\Cache\XmlSitemapReader;

class XmlSitemapTest extends \BlueChip\Cache\Tests\Unit\TestCase
{
    private const SITEMAP_URLS = [
        'http://www.example.com/',
        'http://www.example.com/catalog?item=12&desc=vacation_hawaii',
        'http://www.example.com/catalog?item=73&desc=vacation_new_zealand',
        'http://www.example.com/catalog?item=74&desc=vacation_newfoundland',
        'http://www.example.com/catalog?item=83&desc=vacation_usa',
    ];


    /**
     * @link https://www.sitemaps.org/protocol.html#sitemapXMLExample
     */
    public function testValidSitemap()
    {
        $this->assertSame(
            self::SITEMAP_URLS,
            $this->runUnaccessibleStaticMethod(
                XmlSitemapReader::class,
                'readUrlsFromSitemap',
                [\simplexml_load_file($this->getDataFilePath('sitemap.xml')),]
            )
        );
    }
}
