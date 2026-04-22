<?php
/**
 * Verificaciones SEO on-page: meta tags, encabezados, imágenes, contenido, keywords.
 *
 * Sub-checker de SeoAnalyzer. Recibe un HtmlParser ya inicializado
 * para no re-parsear el HTML por cada check.
 */

class SeoOnPageChecker {
    /** Cache del texto visible extraído (se calcula una vez, se reusa en varios checks). */
    private ?string $cachedText = null;

    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private HtmlParser $parser,
        private string $host
    ) {}

    public function checkSerpPreview(): array {
        $title = $this->parser->getTitle() ?? '';
        $desc = $this->parser->getMeta('description') ?? '';
        $favicon = $this->parser->getLinkByRel('icon') ?? $this->parser->getLinkByRel('shortcut icon');
        $domain = parse_url($this->url, PHP_URL_HOST) ?: $this->url;

        $titleLen = mb_strlen($title);
        $descLen = mb_strlen($desc);
        $issues = [];

        if ($titleLen === 0) $issues[] = Translator::t('seo.serp.issue.no_title');
        elseif ($titleLen > 70) $issues[] = Translator::t('seo.serp.issue.title_long', ['length' => $titleLen]);
        if ($descLen === 0) $issues[] = Translator::t('seo.serp.issue.no_description');
        elseif ($descLen > 160) $issues[] = Translator::t('seo.serp.issue.desc_long', ['length' => $descLen]);
        if (!$favicon) $issues[] = Translator::t('seo.serp.issue.no_favicon');

        $score = 100 - (count($issues) * 20);

        return Scoring::createMetric(
            'serp_preview',
            Translator::t('seo.serp.name'),
            empty($issues),
            empty($issues)
                ? Translator::t('seo.serp.display.ok')
                : Translator::t('seo.serp.display.issues', ['count' => count($issues)]),
            Scoring::clamp($score),
            empty($issues)
                ? Translator::t('seo.serp.desc.ok')
                : Translator::t('seo.serp.desc.issues', ['list' => implode('. ', $issues)]),
            !empty($issues) ? Translator::t('seo.serp.recommend') : '',
            Translator::t('seo.serp.solution'),
            [
                'title' => $title,
                'description' => $desc,
                'url' => $this->url,
                'domain' => $domain,
                'favicon' => $favicon,
                'titleLength' => $titleLen,
                'descriptionLength' => $descLen,
            ]
        );
    }

    public function checkTitle(): array {
        $title = $this->parser->getTitle();
        $length = $title ? mb_strlen($title) : 0;

        if (!$title) {
            return Scoring::createMetric(
                'title',
                Translator::t('seo.title.name'),
                null,
                Translator::t('seo.title.display.none'),
                0,
                Translator::t('seo.title.desc.none'),
                Translator::t('seo.title.recommend.none'),
                Translator::t('seo.title.solution')
            );
        }

        $issues = [];
        $score = 100;

        if ($length < 30) {
            $issues[] = Translator::t('seo.title.issue.short', ['length' => $length]);
            $score = 40;
        } elseif ($length > 70) {
            $issues[] = Translator::t('seo.title.issue.long', ['length' => $length]);
            $score = 65;
        }

        $genericTitles = ['home', 'inicio', 'página principal', 'untitled', 'mi sitio', 'welcome', 'just another wordpress site'];
        foreach ($genericTitles as $generic) {
            if (stripos($title, $generic) !== false && $length < 40) {
                $issues[] = Translator::t('seo.title.issue.generic');
                $score = min($score, 50);
                break;
            }
        }

        $hasSeparator = preg_match('/\s[-|–—·»|]\s/', $title);

        if (empty($issues)) {
            $formatNote = $hasSeparator
                ? Translator::t('seo.title.format.separator')
                : Translator::t('seo.title.format.length_ok');
            $desc = Translator::t('seo.title.desc.ok', ['title' => $title, 'length' => $length, 'formatNote' => $formatNote]);
        } else {
            $desc = Translator::t('seo.title.desc.issues', ['title' => $title, 'length' => $length, 'issues' => implode(' ', $issues)]);
        }

        $recommendation = '';
        if ($score < 100) {
            $recommendation = $length < 30
                ? Translator::t('seo.title.recommend.short')
                : ($length > 70 ? Translator::t('seo.title.recommend.long') : Translator::t('seo.title.recommend.generic'));
        }

        $displayTitle = mb_substr($title, 0, 60) . ($length > 60 ? '...' : '');
        return Scoring::createMetric(
            'title',
            Translator::t('seo.title.name'),
            $title,
            Translator::t('seo.title.display.truncated', ['title' => $displayTitle, 'length' => $length]),
            $score, $desc, $recommendation,
            Translator::t('seo.title.solution'),
            ['fullTitle' => $title, 'length' => $length, 'hasSeparator' => $hasSeparator]
        );
    }

    public function checkMetaDescription(): array {
        $desc = $this->parser->getMeta('description');
        $length = $desc ? mb_strlen($desc) : 0;

        if (!$desc) {
            return Scoring::createMetric(
                'meta_description',
                Translator::t('seo.metadesc.name'),
                null,
                Translator::t('seo.metadesc.display.none'),
                0,
                Translator::t('seo.metadesc.desc.none'),
                Translator::t('seo.metadesc.recommend.none'),
                Translator::t('seo.metadesc.solution')
            );
        }

        $issues = [];
        $score = 100;

        if ($length < 70) {
            $issues[] = Translator::t('seo.metadesc.issue.too_short', ['length' => $length]);
            $score = 35;
        } elseif ($length < 120) {
            $issues[] = Translator::t('seo.metadesc.issue.short', ['length' => $length]);
            $score = 65;
        } elseif ($length > 160) {
            $issues[] = Translator::t('seo.metadesc.issue.long', ['length' => $length]);
            $score = 70;
        }

        $ctaWords = ['descubre', 'aprende', 'conoce', 'encuentra', 'compra', 'obtén', 'visita', 'solicita', 'discover', 'learn', 'get', 'find', 'buy', 'shop', 'try'];
        $hasCta = false;
        foreach ($ctaWords as $word) {
            if (stripos($desc, $word) !== false) { $hasCta = true; break; }
        }

        $preview = mb_substr($desc, 0, 100);
        if (empty($issues)) {
            $ctaNote = $hasCta ? Translator::t('seo.metadesc.desc.cta_note') : '';
            $description = Translator::t('seo.metadesc.desc.ok', ['length' => $length, 'preview' => $preview, 'ctaNote' => $ctaNote]);
        } else {
            $description = Translator::t('seo.metadesc.desc.issues', ['length' => $length, 'preview' => $preview, 'issues' => implode(' ', $issues)]);
        }

        $recommendation = '';
        if ($score < 100) {
            $recommendation = $length < 120
                ? Translator::t('seo.metadesc.recommend.short')
                : Translator::t('seo.metadesc.recommend.long');
        }

        return Scoring::createMetric(
            'meta_description',
            Translator::t('seo.metadesc.name'),
            $desc,
            Translator::t('seo.metadesc.display.truncated', ['preview' => mb_substr($desc, 0, 55), 'length' => $length]),
            $score, $description, $recommendation,
            Translator::t('seo.metadesc.solution'),
            ['fullDescription' => $desc, 'length' => $length, 'hasCta' => $hasCta]
        );
    }

    public function checkMetaRobots(): array {
        $robots = $this->parser->getMeta('robots');
        $googlebot = $this->parser->getMeta('googlebot');

        if ($robots === null && $googlebot === null) {
            return Scoring::createMetric(
                'meta_robots',
                Translator::t('seo.metarobots.name'),
                null,
                Translator::t('seo.metarobots.display.default'),
                100,
                Translator::t('seo.metarobots.desc.default'),
                '',
                Translator::t('seo.metarobots.solution.default')
            );
        }

        $value = $robots ?? $googlebot;
        $valueLower = strtolower($value);
        $hasNoindex = str_contains($valueLower, 'noindex');
        $hasNofollow = str_contains($valueLower, 'nofollow');

        $score = 100;
        $issues = [];
        if ($hasNoindex) {
            $issues[] = Translator::t('seo.metarobots.issue.noindex');
            $score = 0;
        }
        if ($hasNofollow) {
            $issues[] = Translator::t('seo.metarobots.issue.nofollow');
            $score = min($score, 40);
        }

        $desc = empty($issues)
            ? Translator::t('seo.metarobots.desc.ok', ['value' => $value])
            : Translator::t('seo.metarobots.desc.issues', ['value' => $value, 'issues' => implode(' ', $issues)]);

        return Scoring::createMetric(
            'meta_robots',
            Translator::t('seo.metarobots.name'),
            $value, $value,
            $score, $desc,
            ($hasNoindex || $hasNofollow) ? Translator::t('seo.metarobots.recommend') : '',
            Translator::t('seo.metarobots.solution'),
            ['hasNoindex' => $hasNoindex, 'hasNofollow' => $hasNofollow]
        );
    }

    public function checkH1(): array {
        $headings = $this->parser->getHeadings();
        $h1s = array_filter($headings, fn($h) => $h['level'] === 1);
        $h1s = array_values($h1s);
        $h1Count = count($h1s);

        if ($h1Count === 0) {
            return Scoring::createMetric(
                'h1',
                Translator::t('seo.h1.name'),
                0,
                Translator::t('seo.h1.display.none'),
                0,
                Translator::t('seo.h1.desc.none'),
                Translator::t('seo.h1.recommend.none'),
                Translator::t('seo.h1.solution')
            );
        }

        if ($h1Count > 1) {
            $h1Texts = array_map(fn($h) => '"' . mb_substr($h['text'], 0, 50) . '"', $h1s);
            return Scoring::createMetric(
                'h1',
                Translator::t('seo.h1.name'),
                $h1Count,
                Translator::t('seo.h1.display.multiple', ['count' => $h1Count]),
                30,
                Translator::t('seo.h1.desc.multiple', ['count' => $h1Count, 'list' => implode(', ', $h1Texts)]),
                Translator::t('seo.h1.recommend.multiple'),
                Translator::t('seo.h1.solution'),
                ['h1Texts' => array_map(fn($h) => $h['text'], $h1s)]
            );
        }

        $h1Text = $h1s[0]['text'];
        $h1Len = mb_strlen($h1Text);
        $score = 100;
        $notes = Translator::t('seo.h1.desc.ok_prefix', ['text' => $h1Text]);

        if ($h1Len < 10) {
            $notes .= Translator::t('seo.h1.desc.length_short', ['length' => $h1Len]);
            $score = 70;
        } elseif ($h1Len > 80) {
            $notes .= Translator::t('seo.h1.desc.length_long', ['length' => $h1Len]);
            $score = 75;
        } else {
            $notes .= Translator::t('seo.h1.desc.length_ok', ['length' => $h1Len]);
        }

        $displayText = mb_substr($h1Text, 0, 45) . ($h1Len > 45 ? '...' : '');
        return Scoring::createMetric(
            'h1',
            Translator::t('seo.h1.name'),
            1,
            Translator::t('seo.h1.display.ok', ['text' => $displayText]),
            $score, $notes,
            $score < 100 ? Translator::t('seo.h1.recommend.length') : '',
            Translator::t('seo.h1.solution'),
            ['h1Text' => $h1Text, 'h1Length' => $h1Len]
        );
    }

    public function checkHeadingHierarchy(): array {
        $headings = $this->parser->getHeadings();
        $counts = [];
        for ($i = 1; $i <= 6; $i++) {
            $counts["h$i"] = count(array_filter($headings, fn($h) => $h['level'] === $i));
        }
        $totalHeadings = count($headings);

        if ($totalHeadings === 0) {
            return Scoring::createMetric(
                'heading_hierarchy',
                Translator::t('seo.hhier.name'),
                0,
                Translator::t('seo.hhier.display.none'),
                0,
                Translator::t('seo.hhier.desc.none'),
                Translator::t('seo.hhier.recommend.none'),
                Translator::t('seo.hhier.solution')
            );
        }

        $score = 100;
        $issues = [];

        $usedLevels = [];
        foreach ($headings as $h) { $usedLevels[] = $h['level']; }
        $usedLevels = array_unique($usedLevels);
        sort($usedLevels);

        $skipsLevel = false;
        for ($i = 1; $i < count($usedLevels); $i++) {
            if ($usedLevels[$i] - $usedLevels[$i - 1] > 1) {
                $skipsLevel = true;
                break;
            }
        }
        if ($skipsLevel) {
            $issues[] = Translator::t('seo.hhier.issue.skips');
            $score -= 15;
        }

        if ($counts['h2'] === 0) {
            $issues[] = Translator::t('seo.hhier.issue.no_h2');
            $score -= 20;
        }

        $summary = "H1:{$counts['h1']} · H2:{$counts['h2']} · H3:{$counts['h3']}";
        if ($counts['h4'] > 0) $summary .= " · H4:{$counts['h4']}";

        $desc = Translator::t('seo.hhier.desc.prefix', ['summary' => $summary, 'total' => $totalHeadings]);
        $desc .= empty($issues)
            ? Translator::t('seo.hhier.desc.logical')
            : Translator::t('seo.hhier.desc.issues', ['issues' => implode(' ', $issues)]);

        return Scoring::createMetric(
            'heading_hierarchy',
            Translator::t('seo.hhier.name'),
            $totalHeadings,
            Translator::t('seo.hhier.display.summary', ['summary' => $summary]),
            Scoring::clamp($score), $desc,
            !empty($issues) ? Translator::t('seo.hhier.recommend.fix') : '',
            Translator::t('seo.hhier.solution'),
            [
                'counts' => $counts,
                'skipsLevel' => $skipsLevel,
                'headings' => array_map(fn($h) => [
                    'level' => $h['level'],
                    'tag' => 'H' . $h['level'],
                    'text' => mb_substr($h['text'], 0, 100),
                ], array_slice($headings, 0, 30)),
            ]
        );
    }

    public function checkOversizeHeadings(): array {
        $headings = $this->parser->getHeadings();
        $oversized = [];

        foreach ($headings as $h) {
            $len = mb_strlen($h['text']);
            $maxLen = $h['level'] <= 1 ? 70 : 100;
            if ($len > $maxLen) {
                $oversized[] = [
                    'tag' => 'H' . $h['level'],
                    'text' => mb_substr($h['text'], 0, 80) . '...',
                    'length' => $len,
                    'maxLength' => $maxLen,
                ];
            }
        }

        $count = count($oversized);
        $score = $count === 0 ? 100 : Scoring::clamp(100 - ($count * 15));
        $sampleList = implode('; ', array_map(fn($o) => "{$o['tag']} ({$o['length']} car.)", array_slice($oversized, 0, 3)));

        return Scoring::createMetric(
            'oversize_headings',
            Translator::t('seo.over_head.name'),
            $count,
            $count === 0
                ? Translator::t('seo.over_head.display.ok')
                : Translator::t('seo.over_head.display.exceeded', ['count' => $count]),
            $score,
            $count === 0
                ? Translator::t('seo.over_head.desc.ok')
                : Translator::t('seo.over_head.desc.exceeded', ['count' => $count, 'list' => $sampleList]),
            $count > 0 ? Translator::t('seo.over_head.recommend') : '',
            Translator::t('seo.over_head.solution'),
            ['oversized' => $oversized]
        );
    }

    public function checkImagesAlt(): array {
        $images = $this->parser->getImages();
        $total = count($images);

        if ($total === 0) {
            return Scoring::createMetric(
                'images_alt',
                Translator::t('seo.imgalt.name'),
                0,
                Translator::t('seo.imgalt.display.none'),
                70,
                Translator::t('seo.imgalt.desc.none'),
                Translator::t('seo.imgalt.recommend.none'),
                Translator::t('seo.imgalt.solution')
            );
        }

        $withAlt = 0;
        $withoutAlt = [];
        $withLazyLoad = 0;

        foreach ($images as $img) {
            $alt = trim($img['alt'] ?? '');
            if (!empty($alt)) {
                $withAlt++;
            } else {
                $src = $img['src'] ?? '';
                $filename = basename(parse_url($src, PHP_URL_PATH) ?: $src);
                if ($filename && $filename !== '/') {
                    $withoutAlt[] = $filename;
                }
            }
            if (!empty($img['loading']) && $img['loading'] === 'lazy') {
                $withLazyLoad++;
            }
        }
        $withoutAltCount = $total - $withAlt;
        $percent = round(($withAlt / $total) * 100);
        $score = $percent >= 90 ? 100 : (int) round($percent * 0.9);

        $desc = Translator::t('seo.imgalt.desc.prefix', ['withAlt' => $withAlt, 'total' => $total, 'percent' => $percent]);
        if ($withoutAltCount > 0) {
            $sampleMissing = implode(', ', array_slice($withoutAlt, 0, 5));
            $more = $withoutAltCount > 5
                ? Translator::t('seo.imgalt.desc.more_count', ['count' => $withoutAltCount - 5])
                : '';
            $desc .= Translator::t('seo.imgalt.desc.missing', ['sample' => $sampleMissing, 'more' => $more]);
        } else {
            $desc .= Translator::t('seo.imgalt.desc.all_ok');
        }
        $desc .= Translator::t('seo.imgalt.desc.lazy_suffix', ['count' => $withLazyLoad, 'total' => $total]);

        $lazyStr = $withLazyLoad > 0 ? Translator::t('seo.imgalt.display.lazy', ['count' => $withLazyLoad]) : '';
        return Scoring::createMetric(
            'images_alt',
            Translator::t('seo.imgalt.name'),
            $withAlt,
            Translator::t('seo.imgalt.display.stats', ['withAlt' => $withAlt, 'total' => $total, 'percent' => $percent, 'lazy' => $lazyStr]),
            $score, $desc,
            $withoutAltCount > 0 ? Translator::t('seo.imgalt.recommend.missing', ['count' => $withoutAltCount]) : '',
            Translator::t('seo.imgalt.solution'),
            ['total' => $total, 'withAlt' => $withAlt, 'withoutAlt' => $withoutAltCount, 'lazyLoaded' => $withLazyLoad, 'missingExamples' => array_slice($withoutAlt, 0, 10)]
        );
    }

    public function checkOversizedAlt(): array {
        $images = $this->parser->getImages();
        $oversized = [];

        foreach ($images as $img) {
            $alt = $img['alt'] ?? '';
            if (mb_strlen($alt) > 125) {
                $src = $img['src'] ?? '';
                $filename = basename(parse_url($src, PHP_URL_PATH) ?: $src);
                $oversized[] = [
                    'file' => $filename,
                    'altLength' => mb_strlen($alt),
                    'altPreview' => mb_substr($alt, 0, 60) . '...',
                ];
            }
        }

        $totalImages = count($images);
        $count = count($oversized);
        $score = $count === 0 ? 100 : Scoring::clamp(100 - ($count * 10));

        return Scoring::createMetric(
            'oversized_alt',
            Translator::t('seo.alt_over.name'),
            $count,
            $count === 0
                ? Translator::t('seo.alt_over.display.ok')
                : Translator::t('seo.alt_over.display.exceeded', ['count' => $count]),
            $score,
            $count === 0
                ? Translator::t('seo.alt_over.desc.ok', ['total' => $totalImages])
                : Translator::t('seo.alt_over.desc.exceeded', ['count' => $count]),
            $count > 0 ? Translator::t('seo.alt_over.recommend') : '',
            Translator::t('seo.alt_over.solution'),
            ['oversized' => $oversized, 'totalImages' => $totalImages]
        );
    }

    public function checkFavicon(): array {
        $favicon = $this->parser->getLinkByRel('icon') ?? $this->parser->getLinkByRel('shortcut icon');

        return Scoring::createMetric(
            'favicon',
            Translator::t('seo.favicon.name'),
            $favicon !== null,
            $favicon ? Translator::t('seo.favicon.display.ok') : Translator::t('seo.favicon.display.none'),
            $favicon ? 100 : 50,
            $favicon ? Translator::t('seo.favicon.desc.ok') : Translator::t('seo.favicon.desc.none'),
            !$favicon ? Translator::t('seo.favicon.recommend') : '',
            Translator::t('seo.favicon.solution')
        );
    }

    public function checkLanguage(): array {
        $lang = $this->parser->getHtmlLang();

        return Scoring::createMetric(
            'language',
            Translator::t('seo.lang.name'),
            $lang !== null,
            $lang ? Translator::t('seo.lang.display.ok', ['lang' => $lang]) : Translator::t('seo.lang.display.none'),
            $lang ? 100 : 30,
            $lang
                ? Translator::t('seo.lang.desc.ok', ['lang' => $lang])
                : Translator::t('seo.lang.desc.none'),
            !$lang ? Translator::t('seo.lang.recommend') : '',
            Translator::t('seo.lang.solution')
        );
    }

    /**
     * Extrae texto visible del HTML usando DOM walking. Indepediente del
     * HtmlParser::getTextContent para que el SEO no se cuelgue si esa
     * versión está desactualizada en producción (regex con backtracking
     * catastrófico). Implementación O(n) iterativa.
     */
    private function extractVisibleText(): string {
        if ($this->cachedText !== null) return $this->cachedText;

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $this->html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $bodyNodes = $dom->getElementsByTagName('body');
        $root = $bodyNodes->length > 0 ? $bodyNodes->item(0) : $dom->documentElement;
        if ($root === null) return '';

        $excluded = ['script' => true, 'style' => true, 'noscript' => true, 'template' => true];
        $out = '';
        $stack = [$root];
        while (!empty($stack)) {
            $node = array_pop($stack);
            if ($node === null) continue;
            if ($node->nodeType === XML_TEXT_NODE) {
                $out .= ' ' . $node->textContent;
                continue;
            }
            if ($node->nodeType === XML_ELEMENT_NODE && isset($excluded[strtolower($node->nodeName)])) {
                continue;
            }
            if ($node->hasChildNodes()) {
                $kids = [];
                foreach ($node->childNodes as $c) $kids[] = $c;
                for ($i = count($kids) - 1; $i >= 0; $i--) $stack[] = $kids[$i];
            }
        }

        $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->cachedText = trim(preg_replace('/\s+/u', ' ', $out) ?? $out);
        return $this->cachedText;
    }

    public function checkContent(): array {
        $text = $this->extractVisibleText();
        $wordCount = $text === '' ? 0 : str_word_count($text);

        if ($wordCount < 100) {
            return Scoring::createMetric(
                'content_length',
                Translator::t('seo.content.name'),
                $wordCount,
                Translator::t('seo.content.display', ['count' => $wordCount]),
                15,
                Translator::t('seo.content.desc.very_low', ['count' => $wordCount]),
                Translator::t('seo.content.recommend.very_low'),
                Translator::t('seo.content.solution')
            );
        }

        $score = $wordCount >= 800 ? 100 : ($wordCount >= 500 ? 85 : ($wordCount >= 300 ? 65 : 40));

        $desc = Translator::t('seo.content.desc.prefix', ['count' => $wordCount]);
        if ($wordCount >= 800)      $desc .= Translator::t('seo.content.desc.solid');
        elseif ($wordCount >= 500)  $desc .= Translator::t('seo.content.desc.good');
        elseif ($wordCount >= 300)  $desc .= Translator::t('seo.content.desc.min');
        else                        $desc .= Translator::t('seo.content.desc.scarce');

        return Scoring::createMetric(
            'content_length',
            Translator::t('seo.content.name'),
            $wordCount,
            Translator::t('seo.content.display', ['count' => $wordCount]),
            $score, $desc,
            $wordCount < 500 ? Translator::t('seo.content.recommend.low') : '',
            Translator::t('seo.content.solution'),
            ['wordCount' => $wordCount]
        );
    }

    public function checkKeywordDensity(): array {
        // LÍMITES DE MEMORIA: en sitios grandes (blogs, e-commerce, páginas
        // muy extensas) el texto visible puede pasar 500KB. Procesar todo
        // con array_count_values + bigrams + arsort se come 150-250 MB y
        // mata el proceso PHP por OOM silenciosamente (sin log de error).
        // Por eso capamos agresivamente — análisis de keywords con 8000
        // palabras es igual de representativo que con 50000.
        $MAX_TEXT_CHARS = 80_000;   // ~12k palabras típicas
        $MAX_WORDS = 8_000;
        $MAX_BIGRAM_KEYS = 15_000;

        $stopwords = [
            'para', 'como', 'este', 'esta', 'pero', 'más', 'todo', 'todos',
            'tiene', 'puede', 'hace', 'cada', 'entre', 'desde', 'hasta',
            'sobre', 'también', 'cuando', 'donde', 'the', 'and', 'for',
            'that', 'with', 'are', 'from', 'your', 'this', 'have', 'will',
            'been', 'more', 'which', 'their', 'they', 'what', 'than',
            'other', 'into', 'could', 'would', 'make', 'like', 'just', 'some',
        ];
        $stopwordSet = array_flip($stopwords);

        $text = $this->extractVisibleText();
        if (mb_strlen($text) > $MAX_TEXT_CHARS) {
            $text = mb_substr($text, 0, $MAX_TEXT_CHARS);
        }

        // Tokenizar en lower-case y filtrar (cortos + stopwords) de una pasada
        $allWords = preg_split('/\s+/', mb_strtolower($text)) ?: [];
        $words = [];
        $count = 0;
        foreach ($allWords as $w) {
            if ($count >= $MAX_WORDS) break;
            if (mb_strlen($w) < 4) continue;
            $words[] = $w;
            $count++;
        }
        unset($allWords); // liberar memoria

        $totalWords = count($words);
        if ($totalWords < 20) {
            return Scoring::createMetric(
                'keyword_density',
                Translator::t('seo.kw.name'),
                0,
                Translator::t('seo.kw.display.none'),
                30,
                Translator::t('seo.kw.desc.none'),
                Translator::t('seo.kw.recommend.none'),
                Translator::t('seo.kw.solution')
            );
        }

        // Frecuencias — filtrar stopwords aquí para no acumular claves
        $freq = [];
        foreach ($words as $w) {
            if (isset($stopwordSet[$w])) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $topWords = array_slice($freq, 0, 10, true);
        unset($freq);

        // Bigrams — límite duro al tamaño del array para no explotar
        $bigrams = [];
        $len = $totalWords - 1;
        for ($i = 0; $i < $len; $i++) {
            if (count($bigrams) >= $MAX_BIGRAM_KEYS) break;
            $phrase = $words[$i] . ' ' . $words[$i + 1];
            $bigrams[$phrase] = ($bigrams[$phrase] ?? 0) + 1;
        }
        // Solo conservar bigrams con count >= 2 y top 5
        $bigrams = array_filter($bigrams, fn($c) => $c >= 2);
        arsort($bigrams);
        $topPhrases = array_slice($bigrams, 0, 5, true);
        unset($bigrams, $words);

        $title = $this->parser->getTitle() ?? '';
        $h1s = array_filter($this->parser->getHeadings(), fn($h) => $h['level'] === 1);
        $h1Text = !empty($h1s) ? mb_strtolower(reset($h1s)['text'] ?? '') : '';

        $topKeyword = array_key_first($topWords);
        $inTitle = $topKeyword && str_contains(mb_strtolower($title), $topKeyword);
        $inH1 = $topKeyword && str_contains($h1Text, $topKeyword);

        $score = 70;
        if ($inTitle && $inH1) $score = 100;
        elseif ($inTitle || $inH1) $score = 85;

        $keywordList = array_map(fn($w, $c) => "$w ($c)", array_keys($topWords), array_values($topWords));
        $phraseList = array_map(fn($p, $c) => "$p ($c)", array_keys($topPhrases), array_values($topPhrases));

        $desc = Translator::t('seo.kw.desc.prefix', ['words' => implode(', ', array_slice($keywordList, 0, 5))]);
        if (!empty($phraseList)) {
            $desc .= Translator::t('seo.kw.desc.phrases', ['phrases' => implode(', ', array_slice($phraseList, 0, 3))]);
        }
        if ($topKeyword) {
            $desc .= $inTitle
                ? Translator::t('seo.kw.desc.in_title_yes', ['keyword' => $topKeyword])
                : Translator::t('seo.kw.desc.in_title_no', ['keyword' => $topKeyword]);
            $desc .= $inH1
                ? Translator::t('seo.kw.desc.in_h1_yes')
                : Translator::t('seo.kw.desc.in_h1_no');
        }

        return Scoring::createMetric(
            'keyword_density',
            Translator::t('seo.kw.name'),
            count($topWords),
            $topKeyword
                ? Translator::t('seo.kw.display.ok', ['keyword' => $topKeyword, 'count' => $topWords[$topKeyword]])
                : Translator::t('seo.kw.display.none'),
            $score, $desc,
            (!$inTitle || !$inH1) ? Translator::t('seo.kw.recommend.missing') : '',
            Translator::t('seo.kw.solution'),
            ['topWords' => $topWords, 'topPhrases' => $topPhrases, 'inTitle' => $inTitle, 'inH1' => $inH1]
        );
    }
}
