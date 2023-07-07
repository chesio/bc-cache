<?php

namespace BlueChip\Cache;

/**
 * Simple XML sitemaps reader - can read XML sitemap as well as XML sitemap index.
 *
 * @internal Depends on WordPress HTTP API methods: wp_remote_get, wp_remote_retrieve_response_code, wp_remote_retrieve_body.
 */
class XmlSitemapReader
{
    private string $robots_txt_url;

    private string $default_sitemap_url;


    /**
     * @internal $robots_txt_url is parsed in order to determine URLs of available XML sitemaps.
     * If none are found, $default_sitemap_url is used as fallback.
     *
     * @param string $robots_txt_url
     * @param string $default_sitemap_url
     */
    public function __construct(string $robots_txt_url, string $default_sitemap_url)
    {
        $this->robots_txt_url = $robots_txt_url;
        $this->default_sitemap_url = $default_sitemap_url;
    }


    /**
     * Get URLs from XML sitemap(s).
     *
     * @return string[] List of URLs parsed from all available XML sitemaps.
     *
     * @throws Exception
     */
    public function getUrls(): array
    {
        $response = wp_remote_get($this->robots_txt_url);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception("The robots.txt file could not be fetched from {$this->robots_txt_url}!");
        }

        $body = wp_remote_retrieve_body($response);

        $matches = [];
        if (\preg_match_all('/^[Ss]itemap: ?(.+)$/m', $body, $matches)) {
            return \array_reduce(
                $matches[1],
                fn (array $urls, string $sitemap_url): array => \array_merge($urls, self::fetch($sitemap_url)),
                []
            );
        } else {
            // When no sitemap URL is present in robots.txt, assume sitemap.xml:
            return self::fetch($this->default_sitemap_url);
        }
    }


    /**
     * Fetch all URLs found in $url.
     *
     * @param string $url URL of XML sitemap or XML sitemap index.
     * @param bool $expect_sitemap [optional] Set to true to ignore contents of $url if it does not represent XML sitemap.
     *
     * @return string[]
     *
     * @throws Exception
     */
    private static function fetch(string $url, bool $expect_sitemap = false): array
    {
        $response = wp_remote_get($url);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception("Could not get remote URL {$url}!");
        }

        $body = wp_remote_retrieve_body($response);

        try {
            $xml = new \SimpleXMLElement($body);
        } catch (\Exception $e) {
            throw new Exception("Could not parse {$url} as XML file - XML parser reports: " . $e->getMessage());
        }

        switch ($xml->getName()) {
            case 'sitemapindex':
                // Ignore sitemap index if only sitemap is expected.
                return $expect_sitemap ? [] : self::readUrlsFromSitemapIndex($xml);
            case 'urlset':
                return self::readUrlsFromSitemap($xml);
            default:
                return [];
        }
    }


    /**
     * @param \SimpleXMLElement $xml Root element of XML sitemap.
     *
     * @return string[] List of URLs parsed from sitemap.
     */
    private static function readUrlsFromSitemap(\SimpleXMLElement $xml): array
    {
        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        return $urls;
    }


    /**
     * @param \SimpleXMLElement $xml Root element of XML sitemap index.
     *
     * @return string[] List of URLs parsed from all sitemaps found in sitemap index.
     */
    private static function readUrlsFromSitemapIndex(\SimpleXMLElement $xml): array
    {
        $urls = [];

        foreach ($xml->sitemap as $sitemap) {
            // We are parsing sitemap index here, therefore all found URLs have to point to *true* sitemap.
            $urls = \array_merge($urls, self::fetch((string) $sitemap->loc, true));
        }

        return $urls;
    }
}
