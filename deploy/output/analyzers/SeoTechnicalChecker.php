<?php
/**
 * Verificaciones SEO técnicas: sitemap, robots.txt, canonical, hreflang, URL.
 *
 * Sub-checker de SeoAnalyzer. Hace requests HTTP externos vía Fetcher.
 */

class SeoTechnicalChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private HtmlParser $parser,
        private string $host
    ) {}

    public function checkSitemap(): array {
        $sitemapUrl = null;
        $urlCount = 0;
        $isIndex = false;

        // 1. /sitemap.xml
        $response = Fetcher::get($this->url . '/sitemap.xml', 5, true, 0);
        if ($response['statusCode'] === 200) {
            $body = $response['body'] ?? '';
            if (str_contains($body, '<sitemapindex')) {
                $sitemapUrl = '/sitemap.xml';
                $isIndex = true;
                preg_match_all('/<sitemap>/i', $body, $m);
                $urlCount = count($m[0]);
            } elseif (str_contains($body, '<urlset')) {
                $sitemapUrl = '/sitemap.xml';
                preg_match_all('/<url>/i', $body, $m);
                $urlCount = count($m[0]);
            }
        }

        // 2. /sitemap_index.xml
        if (!$sitemapUrl) {
            $response = Fetcher::get($this->url . '/sitemap_index.xml', 5, true, 0);
            if ($response['statusCode'] === 200 && (str_contains($response['body'] ?? '', '<sitemap') || str_contains($response['body'] ?? '', '<urlset'))) {
                $sitemapUrl = '/sitemap_index.xml';
                $isIndex = true;
            }
        }

        // 3. robots.txt → Sitemap:
        if (!$sitemapUrl) {
            $robotsResp = Fetcher::get($this->url . '/robots.txt', 5, true, 0);
            if ($robotsResp['statusCode'] === 200 && preg_match('/^Sitemap:\s*(.+)$/mi', $robotsResp['body'] ?? '', $m)) {
                $sitemapUrl = trim($m[1]);
                $checkResp = Fetcher::head($sitemapUrl, 5);
                if ($checkResp['statusCode'] !== 200) {
                    $sitemapUrl = null;
                }
            }
        }

        if (!$sitemapUrl) {
            return Scoring::createMetric(
                'sitemap',
                Translator::t('seo.sitemap.name'),
                false,
                Translator::t('seo.sitemap.display.none'),
                0,
                Translator::t('seo.sitemap.desc.none'),
                Translator::t('seo.sitemap.recommend'),
                Translator::t('seo.sitemap.solution')
            );
        }

        // Build description
        if ($isIndex) {
            $sub = $urlCount > 0
                ? Translator::t('seo.sitemap.desc.found_index_sub', ['count' => $urlCount])
                : '';
            $desc = Translator::t('seo.sitemap.desc.found_index', ['path' => $sitemapUrl, 'suffix' => $sub]);
        } else {
            $desc = $urlCount > 0
                ? Translator::t('seo.sitemap.desc.found_urls', ['path' => $sitemapUrl, 'count' => $urlCount])
                : Translator::t('seo.sitemap.desc.found_ok', ['path' => $sitemapUrl]);
        }

        // Build display
        if ($urlCount > 0) {
            $suffix = $isIndex
                ? Translator::t('seo.sitemap.display.count_sitemaps', ['count' => $urlCount])
                : Translator::t('seo.sitemap.display.count_urls', ['count' => $urlCount]);
        } else {
            $suffix = '';
        }
        $display = Translator::t('seo.sitemap.display.found', ['path' => $sitemapUrl, 'suffix' => $suffix]);

        return Scoring::createMetric(
            'sitemap',
            Translator::t('seo.sitemap.name'),
            true,
            $display,
            100, $desc, '',
            Translator::t('seo.sitemap.solution'),
            ['url' => $sitemapUrl, 'isIndex' => $isIndex, 'count' => $urlCount]
        );
    }

    public function checkRobots(): array {
        $response = Fetcher::get($this->url . '/robots.txt', 5, true, 0);

        if ($response['statusCode'] !== 200) {
            return Scoring::createMetric(
                'robots',
                Translator::t('seo.robots.name'),
                false,
                Translator::t('seo.robots.display.none'),
                30,
                Translator::t('seo.robots.desc.none'),
                Translator::t('seo.robots.recommend.none'),
                Translator::t('seo.robots.solution.none')
            );
        }

        $body = $response['body'] ?? '';
        $lines = explode("\n", $body);
        $lineCount = count(array_filter($lines, fn($l) => trim($l) !== '' && !str_starts_with(trim($l), '#')));

        $blocksAll = (bool) preg_match('/Disallow:\s*\/\s*$/m', $body);
        $hasSitemap = stripos($body, 'sitemap:') !== false;
        $hasCrawlDelay = stripos($body, 'crawl-delay') !== false;

        preg_match_all('/Disallow:\s*(.+)/i', $body, $disallowMatches);
        $disallowCount = count($disallowMatches[1] ?? []);

        if ($blocksAll) {
            return Scoring::createMetric(
                'robots',
                Translator::t('seo.robots.name'),
                true,
                Translator::t('seo.robots.display.blocks'),
                5,
                Translator::t('seo.robots.desc.blocks'),
                Translator::t('seo.robots.recommend.blocks'),
                Translator::t('seo.robots.solution.blocks'),
                ['blocksAll' => true, 'content' => mb_substr($body, 0, 500)]
            );
        }

        $score = 100;
        $notes = [];
        if (!$hasSitemap) {
            $notes[] = Translator::t('seo.robots.note.no_sitemap');
            $score -= 10;
        }
        if ($hasCrawlDelay) {
            $notes[] = Translator::t('seo.robots.note.crawl_delay');
        }

        $desc = Translator::t('seo.robots.desc.prefix', ['lines' => $lineCount, 'disallow' => $disallowCount]);
        if ($hasSitemap) $desc .= Translator::t('seo.robots.desc.with_sitemap');
        $desc .= empty($notes) ? Translator::t('seo.robots.desc.ok') : implode(' ', $notes);

        $sitemapSuffix = $hasSitemap ? Translator::t('seo.robots.display.sitemap_suffix') : '';
        $display = Translator::t('seo.robots.display.found', [
            'lines' => $lineCount,
            'disallow' => $disallowCount,
            'sitemapSuffix' => $sitemapSuffix,
        ]);

        return Scoring::createMetric(
            'robots',
            Translator::t('seo.robots.name'),
            true,
            $display,
            Scoring::clamp($score),
            $desc,
            !$hasSitemap ? Translator::t('seo.robots.recommend.sitemap') : '',
            Translator::t('seo.robots.solution.none'),
            ['lineCount' => $lineCount, 'disallowCount' => $disallowCount, 'hasSitemap' => $hasSitemap]
        );
    }

    public function checkCanonical(): array {
        $canonical = $this->parser->getLinkByRel('canonical');

        if (!$canonical) {
            return Scoring::createMetric(
                'canonical',
                Translator::t('seo.canonical.name'),
                null,
                Translator::t('seo.canonical.display.none'),
                40,
                Translator::t('seo.canonical.desc.none'),
                Translator::t('seo.canonical.recommend.none'),
                Translator::t('seo.canonical.solution')
            );
        }

        $canonicalNorm = rtrim(strtolower($canonical), '/');
        $urlNorm = rtrim(strtolower($this->url), '/');
        $isSelfReferencing = $canonicalNorm === $urlNorm;

        if ($isSelfReferencing) {
            $desc = Translator::t('seo.canonical.desc.self', ['canonical' => $canonical]);
            $score = 100;
        } else {
            $desc = Translator::t('seo.canonical.desc.diff', ['canonical' => $canonical]);
            $score = 80;
        }

        return Scoring::createMetric(
            'canonical',
            Translator::t('seo.canonical.name'),
            $canonical,
            $isSelfReferencing
                ? Translator::t('seo.canonical.display.self')
                : Translator::t('seo.canonical.display.diff', ['canonical' => mb_substr($canonical, 0, 50)]),
            $score, $desc,
            !$isSelfReferencing ? Translator::t('seo.canonical.recommend.diff') : '',
            Translator::t('seo.canonical.solution'),
            ['canonical' => $canonical, 'isSelfReferencing' => $isSelfReferencing]
        );
    }

    public function checkHreflang(): array {
        $hreflangFound = [];
        if ($this->parser->containsPattern('/hreflang/i')) {
            preg_match_all('/hreflang=["\']([^"\']+)["\']/i', $this->html, $matches);
            if (!empty($matches[1])) {
                $hreflangFound = array_unique($matches[1]);
            }
        }

        if (empty($hreflangFound)) {
            return Scoring::createMetric(
                'hreflang',
                Translator::t('seo.hreflang.name'),
                false,
                Translator::t('seo.hreflang.display.none'),
                70,
                Translator::t('seo.hreflang.desc.none'),
                '',
                Translator::t('seo.hreflang.solution')
            );
        }

        $count = count($hreflangFound);
        $langs = implode(', ', array_slice($hreflangFound, 0, 6));
        $hasXDefault = in_array('x-default', $hreflangFound);

        $xDefaultNote = $hasXDefault
            ? Translator::t('seo.hreflang.desc.with_xdefault')
            : Translator::t('seo.hreflang.desc.without_xdefault');
        $desc = Translator::t('seo.hreflang.desc.found', [
            'count' => $count,
            'list' => $langs,
            'xDefaultNote' => $xDefaultNote,
        ]);

        return Scoring::createMetric(
            'hreflang',
            Translator::t('seo.hreflang.name'),
            true,
            Translator::t('seo.hreflang.display.found', ['count' => $count, 'list' => $langs]),
            $hasXDefault ? 100 : 80,
            $desc,
            !$hasXDefault ? Translator::t('seo.hreflang.recommend') : '',
            Translator::t('seo.hreflang.solution'),
            ['languages' => $hreflangFound, 'hasXDefault' => $hasXDefault]
        );
    }

    public function checkUrlStructure(): array {
        $parsedUrl = parse_url($this->url);

        $score = 100;
        $issues = [];

        $isHttps = ($parsedUrl['scheme'] ?? '') === 'https';
        if (!$isHttps) {
            $issues[] = Translator::t('seo.url.issue.no_https');
            $score -= 20;
        }

        $hasWww = str_starts_with($parsedUrl['host'] ?? '', 'www.');

        $query = $parsedUrl['query'] ?? '';
        if (!empty($query)) {
            $paramCount = count(explode('&', $query));
            if ($paramCount > 3) {
                $issues[] = Translator::t('seo.url.issue.params', ['count' => $paramCount]);
                $score -= 10;
            }
        }

        $urlLength = strlen($this->url);
        if ($urlLength > 100) {
            $issues[] = Translator::t('seo.url.issue.long', ['length' => $urlLength]);
            $score -= 5;
        }

        $desc = Translator::t('seo.url.desc.prefix', ['url' => $this->url]);
        $desc .= $isHttps ? Translator::t('seo.url.desc.https') : Translator::t('seo.url.desc.http');
        $desc .= $hasWww ? Translator::t('seo.url.desc.with_www') : Translator::t('seo.url.desc.no_www');
        $desc .= empty($issues) ? Translator::t('seo.url.desc.clean') : implode(' ', $issues);

        $display = Translator::t('seo.url.display', [
            'scheme' => $isHttps ? Translator::t('seo.url.display.https') : Translator::t('seo.url.display.http'),
            'www' => $hasWww ? Translator::t('seo.url.display.www') : Translator::t('seo.url.display.no_www'),
            'length' => $urlLength,
        ]);

        return Scoring::createMetric(
            'url_structure',
            Translator::t('seo.url.name'),
            true,
            $display,
            Scoring::clamp($score), $desc,
            !empty($issues) ? implode(' ', $issues) : '',
            Translator::t('seo.url.solution'),
            ['isHttps' => $isHttps, 'hasWww' => $hasWww, 'urlLength' => $urlLength]
        );
    }
}
