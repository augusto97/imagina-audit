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

        $metrics = [
            // On-page (meta, encabezados, contenido)
            $onPage->checkSerpPreview(),
            $onPage->checkTitle(),
            $onPage->checkMetaDescription(),
            $onPage->checkMetaRobots(),
            $onPage->checkH1(),
            $onPage->checkHeadingHierarchy(),

            // Markup estructurado
            $markup->checkOpenGraph(),
            $markup->checkTwitterCards(),
            $onPage->checkImagesAlt(),
            $markup->checkStructuredData(),

            // Técnico
            $technical->checkSitemap(),
            $technical->checkRobots(),
            $technical->checkCanonical(),
            $onPage->checkFavicon(),
            $onPage->checkLanguage(),
            $technical->checkHreflang(),
            $onPage->checkContent(),
            $technical->checkUrlStructure(),
            $onPage->checkOversizeHeadings(),
            $onPage->checkOversizedAlt(),
            $onPage->checkKeywordDensity(),
            $markup->checkRssFeeds(),
        ];

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'seo',
            'name' => 'SEO',
            'icon' => 'search',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_seo'],
            'metrics' => $metrics,
            'summary' => "Tu sitio tiene una puntuación SEO de $score/100.",
            'salesMessage' => $defaults['sales_seo'],
        ];
    }
}
