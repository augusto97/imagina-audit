<?php
/**
 * Verificaciones SEO de markup estructurado: Open Graph, Twitter Cards,
 * Schema.org (JSON-LD y Microdata) y feeds RSS/Atom.
 *
 * Sub-checker de SeoAnalyzer.
 */

class SeoMarkupChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private HtmlParser $parser,
        private string $host
    ) {}

    public function checkOpenGraph(): array {
        $tags = [
            'og:title' => $this->parser->getMeta('og:title'),
            'og:description' => $this->parser->getMeta('og:description'),
            'og:image' => $this->parser->getMeta('og:image'),
            'og:url' => $this->parser->getMeta('og:url'),
            'og:type' => $this->parser->getMeta('og:type'),
        ];

        $present = array_filter($tags, fn($v) => $v !== null && $v !== '');
        $missing = array_keys(array_filter($tags, fn($v) => $v === null || $v === ''));
        $count = count($present);
        $total = count($tags);
        $score = (int) round(($count / $total) * 100);

        if ($count === 0) {
            return Scoring::createMetric(
                'open_graph',
                Translator::t('seo.og.name'),
                0,
                Translator::t('seo.og.display.none'),
                0,
                Translator::t('seo.og.desc.none'),
                Translator::t('seo.og.recommend.none'),
                Translator::t('seo.og.solution'),
                ['tags' => $tags]
            );
        }

        $details = [];
        foreach ($present as $key => $val) {
            $details[] = "$key: \"" . mb_substr($val, 0, 50) . (mb_strlen($val) > 50 ? '...' : '') . '"';
        }

        $desc = Translator::t('seo.og.desc.prefix', ['count' => $count, 'total' => $total]);
        if (!empty($missing)) {
            $desc .= Translator::t('seo.og.desc.missing_suffix', ['missing' => implode(', ', $missing)]);
            if (in_array('og:image', $missing)) {
                $desc .= Translator::t('seo.og.desc.no_image_warning');
            }
        } else {
            $desc .= Translator::t('seo.og.desc.complete');
        }

        return Scoring::createMetric(
            'open_graph',
            Translator::t('seo.og.name'),
            $count,
            Translator::t('seo.og.display.count', ['count' => $count, 'total' => $total]),
            $score, $desc,
            !empty($missing) ? Translator::t('seo.og.recommend.missing', ['missing' => implode(', ', $missing)]) : '',
            Translator::t('seo.og.solution'),
            ['tags' => $tags, 'missing' => $missing, 'details' => $details]
        );
    }

    public function checkTwitterCards(): array {
        $tags = [
            'twitter:card' => $this->parser->getMeta('twitter:card'),
            'twitter:title' => $this->parser->getMeta('twitter:title'),
            'twitter:description' => $this->parser->getMeta('twitter:description'),
            'twitter:image' => $this->parser->getMeta('twitter:image'),
        ];

        $present = array_filter($tags, fn($v) => $v !== null && $v !== '');
        $missing = array_keys(array_filter($tags, fn($v) => $v === null || $v === ''));
        $count = count($present);
        $total = count($tags);

        $ogTitle = $this->parser->getMeta('og:title');
        $ogDesc = $this->parser->getMeta('og:description');
        $ogImage = $this->parser->getMeta('og:image');
        $hasOgFallback = $ogTitle && $ogDesc && $ogImage;

        if ($count === 0 && $hasOgFallback) {
            return Scoring::createMetric(
                'twitter_cards',
                Translator::t('seo.twitter.name'),
                0,
                Translator::t('seo.twitter.display.fallback'),
                70,
                Translator::t('seo.twitter.desc.fallback'),
                Translator::t('seo.twitter.recommend.fallback'),
                Translator::t('seo.twitter.solution'),
                ['tags' => $tags, 'usesOgFallback' => true]
            );
        }

        if ($count === 0) {
            return Scoring::createMetric(
                'twitter_cards',
                Translator::t('seo.twitter.name'),
                0,
                Translator::t('seo.twitter.display.none'),
                0,
                Translator::t('seo.twitter.desc.none'),
                Translator::t('seo.twitter.recommend.none'),
                Translator::t('seo.twitter.solution'),
                ['tags' => $tags]
            );
        }

        $score = (int) round(($count / $total) * 100);

        return Scoring::createMetric(
            'twitter_cards',
            Translator::t('seo.twitter.name'),
            $count,
            Translator::t('seo.twitter.display.count', ['count' => $count, 'total' => $total]),
            $score,
            $count === $total
                ? Translator::t('seo.twitter.desc.complete')
                : Translator::t('seo.twitter.desc.partial', ['count' => $count, 'total' => $total, 'missing' => implode(', ', $missing)]),
            !empty($missing) ? Translator::t('seo.twitter.recommend.missing', ['missing' => implode(', ', $missing)]) : '',
            Translator::t('seo.twitter.solution'),
            ['tags' => $tags, 'missing' => $missing]
        );
    }

    public function checkStructuredData(): array {
        $schemas = $this->parser->getJsonLd();
        $hasSchema = !empty($schemas);

        if (!$hasSchema) {
            $hasMicrodata = $this->parser->containsPattern('/itemscope|itemtype/i');
            if ($hasMicrodata) {
                return Scoring::createMetric(
                    'structured_data',
                    Translator::t('seo.schema.name'),
                    true,
                    Translator::t('seo.schema.display.microdata'),
                    60,
                    Translator::t('seo.schema.desc.microdata'),
                    Translator::t('seo.schema.recommend.microdata'),
                    Translator::t('seo.schema.solution')
                );
            }

            return Scoring::createMetric(
                'structured_data',
                Translator::t('seo.schema.name'),
                false,
                Translator::t('seo.schema.display.none'),
                0,
                Translator::t('seo.schema.desc.none'),
                Translator::t('seo.schema.recommend.none'),
                Translator::t('seo.schema.solution')
            );
        }

        $types = [];
        foreach ($schemas as $schema) {
            if (isset($schema['@type'])) {
                $types[] = is_array($schema['@type']) ? implode(', ', $schema['@type']) : $schema['@type'];
            }
            if (isset($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as $item) {
                    if (isset($item['@type'])) {
                        $types[] = is_array($item['@type']) ? implode(', ', $item['@type']) : $item['@type'];
                    }
                }
            }
        }
        $types = array_unique($types);
        $typeCount = count($types);

        $valuableTypes = ['Organization', 'LocalBusiness', 'Product', 'Article', 'BlogPosting', 'FAQPage', 'BreadcrumbList', 'WebSite', 'Person', 'Review', 'HowTo', 'Event', 'Recipe'];
        $hasValuable = !empty(array_intersect($types, $valuableTypes));

        $score = $hasValuable ? 100 : 70;

        $listForDesc = implode(', ', array_slice($types, 0, 8));
        $descMore = $typeCount > 8 ? Translator::t('seo.schema.desc.suffix_more', ['count' => $typeCount - 8]) : '';
        $desc = Translator::t('seo.schema.desc.prefix', ['count' => $typeCount, 'list' => $listForDesc, 'ellipsis' => $descMore]);
        $desc .= $hasValuable
            ? Translator::t('seo.schema.desc.valuable')
            : Translator::t('seo.schema.desc.not_valuable');

        $listForDisplay = implode(', ', array_slice($types, 0, 4));
        $displayEllipsis = $typeCount > 4 ? '...' : '';

        return Scoring::createMetric(
            'structured_data',
            Translator::t('seo.schema.name'),
            true,
            Translator::t('seo.schema.display.found', ['count' => $typeCount, 'list' => $listForDisplay, 'ellipsis' => $displayEllipsis]),
            $score, $desc,
            !$hasValuable ? Translator::t('seo.schema.recommend.partial') : '',
            Translator::t('seo.schema.solution'),
            ['types' => $types, 'schemaCount' => count($schemas)]
        );
    }

    public function checkRssFeeds(): array {
        $feeds = [];
        preg_match_all('/<link[^>]+type=["\']application\/(rss|atom)\+xml["\'][^>]*>/i', $this->html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if (preg_match('/href=["\']([^"\']+)/i', $m[0], $href)) {
                $type = stripos($m[0], 'atom') !== false ? 'Atom' : 'RSS';
                $feeds[] = ['url' => $href[1], 'type' => $type];
            }
        }

        $count = count($feeds);
        $feedList = implode(', ', array_map(fn($f) => $f['type'] . ': ' . basename($f['url']), $feeds));
        return Scoring::createMetric(
            'rss_feeds',
            Translator::t('seo.rss.name'),
            $count,
            $count === 0
                ? Translator::t('seo.rss.display.none')
                : Translator::t('seo.rss.display.found', ['count' => $count]),
            null, // Informativo — no afecta score
            $count > 0
                ? Translator::t('seo.rss.desc.found', ['count' => $count, 'list' => $feedList])
                : Translator::t('seo.rss.desc.none'),
            $count === 0 ? Translator::t('seo.rss.recommend') : '',
            Translator::t('seo.rss.solution'),
            ['feeds' => $feeds]
        );
    }
}
