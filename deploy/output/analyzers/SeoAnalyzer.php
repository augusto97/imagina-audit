<?php
/**
 * Analiza factores SEO del sitio.
 *
 * Orquestador delgado que delega a 3 sub-checkers:
 *   - SeoOnPageChecker    (title, meta, H1-H6, imágenes, contenido, keywords)
 *   - SeoMarkupChecker    (Open Graph, Twitter Cards, Schema.org, RSS)
 *   - SeoTechnicalChecker (sitemap, robots.txt, canonical, hreflang, URL)
 *
 * Parseamos el HTML una sola vez y pasamos el HtmlParser a cada sub-checker.
 */

class SeoAnalyzer {
    private string $url;
    private string $html;
    private array $headers;
    private HtmlParser $parser;
    private string $host;

    public function __construct(string $url, string $html, array $headers) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->host = parse_url($url, PHP_URL_HOST) ?: '';
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Ejecuta el análisis SEO
     */
    public function analyze(): array {
        $ctx = [$this->url, $this->html, $this->headers, $this->parser, $this->host];

        $onPage = new SeoOnPageChecker(...$ctx);
        $markup = new SeoMarkupChecker(...$ctx);
        $technical = new SeoTechnicalChecker(...$ctx);

        // Cada check loggea su tiempo individualmente — crítico para detectar
        // cuál método específico se cuelga dentro del módulo SEO.
        $metrics = [
            $this->timed('serp_preview',      fn() => $onPage->checkSerpPreview()),
            $this->timed('title',             fn() => $onPage->checkTitle()),
            $this->timed('meta_description',  fn() => $onPage->checkMetaDescription()),
            $this->timed('meta_robots',       fn() => $onPage->checkMetaRobots()),
            $this->timed('h1',                fn() => $onPage->checkH1()),
            $this->timed('heading_hierarchy', fn() => $onPage->checkHeadingHierarchy()),
            $this->timed('open_graph',        fn() => $markup->checkOpenGraph()),
            $this->timed('twitter_cards',     fn() => $markup->checkTwitterCards()),
            $this->timed('images_alt',        fn() => $onPage->checkImagesAlt()),
            $this->timed('structured_data',   fn() => $markup->checkStructuredData()),
            $this->timed('sitemap',           fn() => $technical->checkSitemap()),
            $this->timed('robots',            fn() => $technical->checkRobots()),
            $this->timed('canonical',         fn() => $technical->checkCanonical()),
            $this->timed('favicon',           fn() => $onPage->checkFavicon()),
            $this->timed('language',          fn() => $onPage->checkLanguage()),
            $this->timed('hreflang',          fn() => $technical->checkHreflang()),
            $this->timed('content',           fn() => $onPage->checkContent()),
            $this->timed('url_structure',     fn() => $technical->checkUrlStructure()),
            $this->timed('oversize_headings', fn() => $onPage->checkOversizeHeadings()),
            $this->timed('oversized_alt',     fn() => $onPage->checkOversizedAlt()),
            $this->timed('keyword_density',   fn() => $onPage->checkKeywordDensity()),
            $this->timed('rss_feeds',         fn() => $markup->checkRssFeeds()),
        ];

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'seo',
            'name' => Translator::t('modules.seo.name'),
            'icon' => 'search',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_seo'],
            'metrics' => $metrics,
            'summary' => Translator::t('seo.summary', ['score' => $score]),
            'salesMessage' => $defaults['sales_seo'] !== '' ? $defaults['sales_seo'] : Translator::t('modules.sales.seo'),
        ];
    }

    /**
     * Ejecuta un check midiendo su duración. Los logs revelan al instante
     * qué método está tardando (o colgándose).
     */
    private function timed(string $name, callable $fn): array {
        $t0 = microtime(true);
        try {
            $result = $fn();
            $elapsed = (int) ((microtime(true) - $t0) * 1000);
            if ($elapsed > 500) {
                Logger::warning("SEO.$name lento ({$elapsed}ms)");
            } else {
                Logger::info("SEO.$name OK", ['ms' => $elapsed]);
            }
            return $result;
        } catch (Throwable $e) {
            $elapsed = (int) ((microtime(true) - $t0) * 1000);
            Logger::error("SEO.$name FAIL ({$elapsed}ms): " . $e->getMessage());
            throw $e;
        }
    }
}
