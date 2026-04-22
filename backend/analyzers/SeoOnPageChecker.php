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
                'h1', 'Encabezado H1', 0, 'No encontrado', 0,
                'No se encontró ningún encabezado H1. El H1 es la etiqueta de encabezado más importante: le dice a Google de qué trata la página. Toda página debe tener exactamente un H1.',
                'Agregar un encabezado H1 único que describa el contenido principal e incluya la keyword objetivo.',
                'Optimizamos la estructura de encabezados para mejorar el SEO on-page.'
            );
        }

        if ($h1Count > 1) {
            $h1Texts = array_map(fn($h) => '"' . mb_substr($h['text'], 0, 50) . '"', $h1s);
            return Scoring::createMetric(
                'h1', 'Encabezado H1', $h1Count, "$h1Count H1 encontrados (debería ser 1)", 30,
                "Se encontraron $h1Count encabezados H1: " . implode(', ', $h1Texts) . '. Solo debe haber uno por página. Múltiples H1 confunden a Google sobre cuál es el tema principal.',
                'Mantener solo un H1 principal. Cambiar los demás a H2 o H3 según corresponda.',
                'Optimizamos la estructura de encabezados para mejorar el SEO on-page.',
                ['h1Texts' => array_map(fn($h) => $h['text'], $h1s)]
            );
        }

        $h1Text = $h1s[0]['text'];
        $h1Len = mb_strlen($h1Text);
        $score = 100;
        $notes = "H1: \"$h1Text\".";

        if ($h1Len < 10) {
            $notes .= " Muy corto ($h1Len caracteres), podría ser más descriptivo.";
            $score = 70;
        } elseif ($h1Len > 80) {
            $notes .= " Bastante largo ($h1Len caracteres). Se recomienda menos de 70 caracteres.";
            $score = 75;
        } else {
            $notes .= " Longitud adecuada ($h1Len caracteres).";
        }

        return Scoring::createMetric(
            'h1', 'Encabezado H1', 1, '1 H1: "' . mb_substr($h1Text, 0, 45) . ($h1Len > 45 ? '...' : '') . '"',
            $score, $notes, $score < 100 ? 'Ajustar el H1 para que sea descriptivo, entre 20-70 caracteres, con la keyword principal.' : '',
            'Optimizamos la estructura de encabezados para mejorar el SEO on-page.',
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
                'heading_hierarchy', 'Jerarquía de Encabezados', 0, 'Sin encabezados', 0,
                'No se encontró ningún encabezado (H1-H6). Los encabezados son esenciales para que Google entienda la estructura y temas de tu contenido.',
                'Agregar una estructura de encabezados lógica: un H1, seguido de H2 para secciones, H3 para subsecciones.',
                'Estructuramos el contenido con una jerarquía de encabezados optimizada para SEO.'
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
            $issues[] = 'La jerarquía salta niveles (ej: de H1 a H3 sin H2).';
            $score -= 15;
        }

        if ($counts['h2'] === 0) {
            $issues[] = 'No hay encabezados H2 para dividir el contenido en secciones.';
            $score -= 20;
        }

        $summary = "H1:{$counts['h1']} · H2:{$counts['h2']} · H3:{$counts['h3']}";
        if ($counts['h4'] > 0) $summary .= " · H4:{$counts['h4']}";

        $desc = "Estructura de encabezados: $summary. Total: $totalHeadings encabezados.";
        if (!empty($issues)) {
            $desc .= ' Problemas: ' . implode(' ', $issues);
        } else {
            $desc .= ' Jerarquía lógica y bien organizada.';
        }

        return Scoring::createMetric(
            'heading_hierarchy', 'Jerarquía de Encabezados', $totalHeadings, $summary,
            Scoring::clamp($score), $desc,
            !empty($issues) ? 'Corregir la jerarquía: H1 → H2 → H3 sin saltar niveles. Usar H2 para secciones principales.' : '',
            'Estructuramos el contenido con una jerarquía de encabezados optimizada para SEO.',
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

        return Scoring::createMetric(
            'oversize_headings', 'Encabezados demasiado largos', $count,
            $count === 0 ? 'Todos dentro del límite' : "$count encabezados exceden el largo recomendado",
            $score,
            $count === 0
                ? 'Todos los encabezados tienen una longitud adecuada. H1 hasta 70 caracteres, H2-H6 hasta 100.'
                : "$count encabezados son demasiado largos: " . implode('; ', array_map(fn($o) => "{$o['tag']} ({$o['length']} car.)", array_slice($oversized, 0, 3))) . '.',
            $count > 0 ? 'Acortar los encabezados que excedan el límite. H1 máx. 70 caracteres, H2-H6 máx. 100.' : '',
            'Optimizamos los encabezados para que sean concisos y descriptivos.',
            ['oversized' => $oversized]
        );
    }

    public function checkImagesAlt(): array {
        $images = $this->parser->getImages();
        $total = count($images);

        if ($total === 0) {
            return Scoring::createMetric(
                'images_alt', 'Imágenes con texto alt', 0, 'Sin imágenes en la página', 70,
                'No se encontraron imágenes en la página. Las imágenes enriquecen el contenido y pueden generar tráfico desde Google Imágenes.',
                'Considerar agregar imágenes relevantes con textos alt descriptivos.',
                'Optimizamos todas las imágenes con textos alt descriptivos y formatos modernos.'
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

        $desc = "$withAlt de $total imágenes tienen texto alt ($percent%). ";
        if ($withoutAltCount > 0) {
            $sampleMissing = array_slice($withoutAlt, 0, 5);
            $desc .= "Imágenes sin alt: " . implode(', ', $sampleMissing);
            if ($withoutAltCount > 5) $desc .= " y " . ($withoutAltCount - 5) . " más";
            $desc .= '. Google no puede interpretar imágenes sin texto alternativo.';
        } else {
            $desc .= 'Todas las imágenes tienen texto alternativo. Excelente para accesibilidad y SEO.';
        }
        $desc .= " Lazy loading: $withLazyLoad/$total imágenes.";

        return Scoring::createMetric(
            'images_alt', 'Imágenes con texto alt', $withAlt,
            "$withAlt/$total con alt ($percent%)" . ($withLazyLoad > 0 ? " · $withLazyLoad lazy" : ''),
            $score, $desc,
            $withoutAltCount > 0 ? "Agregar texto alt descriptivo a las $withoutAltCount imágenes que lo necesitan. El alt debe describir el contenido de la imagen." : '',
            'Optimizamos todas las imágenes con textos alt descriptivos y formatos modernos (WebP).',
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
            'oversized_alt', 'Textos ALT demasiado largos', $count,
            $count === 0 ? 'Todos dentro del límite' : "$count imágenes con alt excesivo",
            $score,
            $count === 0
                ? "Las $totalImages imágenes revisadas tienen textos alt de longitud adecuada (máx. 125 caracteres)."
                : "$count imágenes tienen textos alt de más de 125 caracteres. Textos alt muy largos pueden ser ignorados por los buscadores.",
            $count > 0 ? 'Acortar los textos alt a máximo 125 caracteres. Deben ser descriptivos pero concisos.' : '',
            'Optimizamos los textos alt para que sean descriptivos y de longitud adecuada.',
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
                'content_length', 'Cantidad de contenido', $wordCount, "$wordCount palabras", 15,
                "La página tiene solo $wordCount palabras. Es extremadamente poco contenido. Google necesita texto suficiente para entender de qué trata la página y posicionarla correctamente. Las páginas con poco contenido raramente rankean bien.",
                'Desarrollar contenido original de al menos 300 palabras. Para posicionar keywords competitivas, se recomiendan 800+ palabras.',
                'Desarrollamos estrategias de contenido y redactamos textos optimizados para SEO.'
            );
        }

        $score = $wordCount >= 800 ? 100 : ($wordCount >= 500 ? 85 : ($wordCount >= 300 ? 65 : 40));

        $desc = "La página tiene $wordCount palabras de contenido visible. ";
        if ($wordCount >= 800) {
            $desc .= 'Volumen de contenido sólido para SEO. Las páginas con contenido extenso y relevante tienden a posicionar mejor.';
        } elseif ($wordCount >= 500) {
            $desc .= 'Buen volumen de contenido. Para keywords competitivas, considerar expandir a 800+ palabras.';
        } elseif ($wordCount >= 300) {
            $desc .= 'Contenido mínimo aceptable, pero podría ser insuficiente para competir en resultados de Google.';
        } else {
            $desc .= 'Contenido escaso. Google prefiere páginas con contenido sustancial y útil para el usuario.';
        }

        return Scoring::createMetric(
            'content_length', 'Cantidad de contenido', $wordCount, "$wordCount palabras",
            $score, $desc,
            $wordCount < 500 ? 'Expandir el contenido a mínimo 500 palabras con información útil, preguntas frecuentes o descripciones detalladas.' : '',
            'Desarrollamos estrategias de contenido y redactamos textos optimizados para SEO.',
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
                'keyword_density', 'Análisis de palabras clave', 0, 'Contenido insuficiente', 30,
                'No hay suficiente contenido para analizar palabras clave.',
                'Agregar más contenido relevante a la página.',
                'Desarrollamos estrategia de contenido con keywords objetivo.'
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

        $desc = 'Palabras más frecuentes: ' . implode(', ', array_slice($keywordList, 0, 5)) . '.';
        if (!empty($phraseList)) $desc .= ' Frases: ' . implode(', ', array_slice($phraseList, 0, 3)) . '.';
        if ($topKeyword) {
            $desc .= $inTitle ? " \"$topKeyword\" aparece en el título." : " \"$topKeyword\" NO aparece en el título.";
            $desc .= $inH1 ? ' Aparece en H1.' : ' NO aparece en H1.';
        }

        return Scoring::createMetric(
            'keyword_density', 'Análisis de palabras clave', count($topWords),
            $topKeyword ? "\"$topKeyword\" ({$topWords[$topKeyword]}x)" : 'Sin datos',
            $score, $desc,
            (!$inTitle || !$inH1) ? 'Incluir la keyword principal en el título y H1 de la página.' : '',
            'Optimizamos el contenido con las keywords más relevantes para tu negocio.',
            ['topWords' => $topWords, 'topPhrases' => $topPhrases, 'inTitle' => $inTitle, 'inH1' => $inH1]
        );
    }
}
