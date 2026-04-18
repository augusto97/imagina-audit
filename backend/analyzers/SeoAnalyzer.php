<?php
/**
 * Analiza factores SEO del sitio: meta tags, headings, sitemap, robots, schema, etc.
 */

class SeoAnalyzer {
    private string $url;
    private string $html;
    private array $headers;
    private HtmlParser $parser;

    public function __construct(string $url, string $html, array $headers) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Ejecuta el análisis SEO
     */
    public function analyze(): array {
        $metrics = [];

        $metrics[] = $this->checkTitle();
        $metrics[] = $this->checkMetaDescription();
        $metrics[] = $this->checkMetaRobots();
        $metrics[] = $this->checkH1();
        $metrics[] = $this->checkHeadingHierarchy();
        $metrics[] = $this->checkOpenGraph();
        $metrics[] = $this->checkTwitterCards();
        $metrics[] = $this->checkImagesAlt();
        $metrics[] = $this->checkStructuredData();
        $metrics[] = $this->checkSitemap();
        $metrics[] = $this->checkRobots();
        $metrics[] = $this->checkCanonical();
        $metrics[] = $this->checkFavicon();
        $metrics[] = $this->checkLanguage();
        $metrics[] = $this->checkHreflang();
        $metrics[] = $this->checkContent();
        $metrics[] = $this->checkInternalLinks();
        $metrics[] = $this->checkUrlStructure();
        $metrics[] = $this->checkKeywordDensity();
        $metrics[] = $this->checkRssFeeds();

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

    private function checkTitle(): array {
        $title = $this->parser->getTitle();
        $length = $title ? mb_strlen($title) : 0;

        if (!$title) {
            return Scoring::createMetric(
                'title', 'Etiqueta Title', null, 'No encontrada', 0,
                'No se encontró la etiqueta <title>. Es la señal SEO on-page más importante: aparece como título en los resultados de Google y en la pestaña del navegador.',
                'Agregar una etiqueta <title> única y descriptiva de 30-70 caracteres con la keyword principal.',
                'Optimizamos los títulos de todas tus páginas con keywords estratégicas para mejorar el posicionamiento.'
            );
        }

        $issues = [];
        $score = 100;

        if ($length < 30) {
            $issues[] = "Demasiado corto ($length caracteres). Google podría reescribirlo.";
            $score = 40;
        } elseif ($length > 70) {
            $issues[] = "Demasiado largo ($length caracteres). Se truncará en los resultados de Google mostrando '...'.";
            $score = 65;
        }

        // Verificar si el título es genérico
        $genericTitles = ['home', 'inicio', 'página principal', 'untitled', 'mi sitio', 'welcome', 'just another wordpress site'];
        foreach ($genericTitles as $generic) {
            if (stripos($title, $generic) !== false && $length < 40) {
                $issues[] = "El título parece genérico. Debería describir el contenido de la página.";
                $score = min($score, 50);
                break;
            }
        }

        // Verificar separadores comunes (indica buena optimización)
        $hasSeparator = preg_match('/\s[-|–—·»|]\s/', $title);

        $desc = empty($issues)
            ? "Título: \"$title\" ($length caracteres). " . ($hasSeparator ? 'Formato optimizado con separador de marca.' : 'Longitud adecuada.')
            : "Título: \"$title\" ($length caracteres). " . implode(' ', $issues);

        $recommendation = '';
        if ($score < 100) {
            $recommendation = $length < 30
                ? 'Extender el título a 30-70 caracteres incluyendo la keyword principal y el nombre de la marca.'
                : ($length > 70 ? 'Acortar el título a máximo 70 caracteres para evitar truncamiento en Google.' : 'Usar un título más descriptivo con la keyword principal de la página.');
        }

        return Scoring::createMetric(
            'title', 'Etiqueta Title', $title, mb_substr($title, 0, 60) . ($length > 60 ? '...' : '') . " ($length car.)",
            $score, $desc, $recommendation,
            'Optimizamos los títulos de todas tus páginas con keywords estratégicas para mejorar el posicionamiento.',
            ['fullTitle' => $title, 'length' => $length, 'hasSeparator' => $hasSeparator]
        );
    }

    private function checkMetaDescription(): array {
        $desc = $this->parser->getMeta('description');
        $length = $desc ? mb_strlen($desc) : 0;

        if (!$desc) {
            return Scoring::createMetric(
                'meta_description', 'Meta Description', null, 'No encontrada', 0,
                'No se encontró meta description. Esta etiqueta aparece como el texto debajo del título en los resultados de Google. Sin ella, Google elige un fragmento aleatorio de tu página.',
                'Agregar una meta description atractiva de 120-160 caracteres que invite al clic e incluya la keyword principal.',
                'Redactamos meta descriptions optimizadas para cada página importante de tu sitio.'
            );
        }

        $issues = [];
        $score = 100;

        if ($length < 70) {
            $issues[] = "Muy corta ($length caracteres). Google podría ignorarla y mostrar otro texto.";
            $score = 35;
        } elseif ($length < 120) {
            $issues[] = "Algo corta ($length caracteres). Se recomienda entre 120-160 para aprovechar el espacio en Google.";
            $score = 65;
        } elseif ($length > 160) {
            $issues[] = "Excede los 160 caracteres ($length). Google la truncará mostrando '...'.";
            $score = 70;
        }

        // Verificar si tiene call-to-action implícito
        $ctaWords = ['descubre', 'aprende', 'conoce', 'encuentra', 'compra', 'obtén', 'visita', 'solicita', 'discover', 'learn', 'get', 'find', 'buy', 'shop', 'try'];
        $hasCta = false;
        foreach ($ctaWords as $word) {
            if (stripos($desc, $word) !== false) { $hasCta = true; break; }
        }

        $description = empty($issues)
            ? "Meta description ($length caracteres): \"" . mb_substr($desc, 0, 100) . '...". Longitud ideal.' . ($hasCta ? ' Incluye llamada a la acción.' : '')
            : "Meta description ($length caracteres): \"" . mb_substr($desc, 0, 100) . '...". ' . implode(' ', $issues);

        $recommendation = '';
        if ($score < 100) {
            $recommendation = $length < 120
                ? 'Extender la meta description a 120-160 caracteres con una descripción atractiva que invite al clic.'
                : 'Acortar la meta description a máximo 160 caracteres para evitar truncamiento.';
        }

        return Scoring::createMetric(
            'meta_description', 'Meta Description', $desc,
            mb_substr($desc, 0, 55) . '... (' . $length . ' car.)',
            $score, $description, $recommendation,
            'Redactamos meta descriptions optimizadas para cada página importante de tu sitio.',
            ['fullDescription' => $desc, 'length' => $length, 'hasCta' => $hasCta]
        );
    }

    private function checkMetaRobots(): array {
        $robots = $this->parser->getMeta('robots');
        $googlebot = $this->parser->getMeta('googlebot');

        if ($robots === null && $googlebot === null) {
            return Scoring::createMetric(
                'meta_robots', 'Meta Robots', null, 'No definida (por defecto: index, follow)', 100,
                'No se encontró la etiqueta meta robots. Por defecto Google indexa y sigue los enlaces, lo cual es correcto para la mayoría de páginas.',
                '', 'Verificamos que las directivas de indexación sean correctas en todas las páginas.'
            );
        }

        $value = $robots ?? $googlebot;
        $valueLower = strtolower($value);
        $hasNoindex = str_contains($valueLower, 'noindex');
        $hasNofollow = str_contains($valueLower, 'nofollow');

        $score = 100;
        $issues = [];
        if ($hasNoindex) {
            $issues[] = 'Contiene "noindex": Google NO indexará esta página.';
            $score = 0;
        }
        if ($hasNofollow) {
            $issues[] = 'Contiene "nofollow": Google no seguirá los enlaces de esta página.';
            $score = min($score, 40);
        }

        $desc = empty($issues)
            ? "Meta robots: \"$value\". Configuración correcta para indexación."
            : "Meta robots: \"$value\". ATENCIÓN: " . implode(' ', $issues) . ' Si esto no es intencional, tu página no aparecerá en Google.';

        return Scoring::createMetric(
            'meta_robots', 'Meta Robots', $value, $value,
            $score, $desc,
            ($hasNoindex || $hasNofollow) ? 'Verificar que las directivas noindex/nofollow sean intencionales. Si no lo son, cambiar a "index, follow".' : '',
            'Verificamos y corregimos las directivas de indexación en todas las páginas.',
            ['hasNoindex' => $hasNoindex, 'hasNofollow' => $hasNofollow]
        );
    }

    private function checkH1(): array {
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

    private function checkHeadingHierarchy(): array {
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

        // Verificar que no salte niveles (ej: H1 → H3 sin H2)
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

        // Verificar que haya H2s (secciones)
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

    private function checkOpenGraph(): array {
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
                'open_graph', 'Open Graph (redes sociales)', 0, 'No configurado', 0,
                'No se encontró ninguna etiqueta Open Graph. Cuando alguien comparte tu sitio en Facebook, LinkedIn o WhatsApp, aparecerá sin imagen, sin título personalizado y sin descripción atractiva.',
                'Agregar las 5 etiquetas Open Graph: og:title, og:description, og:image, og:url, og:type.',
                'Configuramos Open Graph y Twitter Cards para una presentación profesional en redes sociales.',
                ['tags' => $tags]
            );
        }

        $details = [];
        foreach ($present as $key => $val) {
            $details[] = "$key: \"" . mb_substr($val, 0, 50) . (mb_strlen($val) > 50 ? '...' : '') . '"';
        }

        $desc = "$count/$total etiquetas OG configuradas. ";
        if (!empty($missing)) {
            $desc .= 'Faltan: ' . implode(', ', $missing) . '. ';
            if (in_array('og:image', $missing)) {
                $desc .= 'Sin og:image el enlace compartido no mostrará imagen preview.';
            }
        } else {
            $desc .= 'Configuración completa. Tu sitio se verá profesional al compartirlo en redes sociales.';
        }

        return Scoring::createMetric(
            'open_graph', 'Open Graph (redes sociales)', $count, "$count/$total tags presentes",
            $score, $desc,
            !empty($missing) ? 'Agregar las etiquetas faltantes: ' . implode(', ', $missing) . '.' : '',
            'Configuramos Open Graph y Twitter Cards para una presentación profesional en redes sociales.',
            ['tags' => $tags, 'missing' => $missing, 'details' => $details]
        );
    }

    private function checkTwitterCards(): array {
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

        // Twitter puede usar OG como fallback, así que puntuar menos severo
        $ogTitle = $this->parser->getMeta('og:title');
        $ogDesc = $this->parser->getMeta('og:description');
        $ogImage = $this->parser->getMeta('og:image');
        $hasOgFallback = $ogTitle && $ogDesc && $ogImage;

        if ($count === 0 && $hasOgFallback) {
            return Scoring::createMetric(
                'twitter_cards', 'Twitter Cards', 0, 'Usa Open Graph como fallback', 70,
                'No se encontraron etiquetas Twitter Cards, pero Open Graph está configurado y Twitter/X lo usa como respaldo. Para un control más preciso, se recomienda agregar las etiquetas Twitter específicas.',
                'Agregar twitter:card, twitter:title, twitter:description y twitter:image para control total.',
                'Configuramos Twitter Cards para una presentación optimizada al compartir en X/Twitter.',
                ['tags' => $tags, 'usesOgFallback' => true]
            );
        }

        if ($count === 0) {
            return Scoring::createMetric(
                'twitter_cards', 'Twitter Cards', 0, 'No configuradas', 0,
                'No se encontraron etiquetas Twitter Cards ni Open Graph como respaldo. Los enlaces compartidos en X/Twitter aparecerán sin formato especial.',
                'Agregar twitter:card, twitter:title, twitter:description y twitter:image.',
                'Configuramos Twitter Cards para una presentación optimizada al compartir en X/Twitter.',
                ['tags' => $tags]
            );
        }

        $score = (int) round(($count / $total) * 100);

        return Scoring::createMetric(
            'twitter_cards', 'Twitter Cards', $count, "$count/$total tags presentes",
            $score,
            $count === $total
                ? 'Todas las etiquetas Twitter Cards están configuradas. Presentación optimizada en X/Twitter.'
                : "$count/$total etiquetas Twitter Cards. Faltan: " . implode(', ', $missing) . '.',
            !empty($missing) ? 'Agregar las etiquetas faltantes: ' . implode(', ', $missing) . '.' : '',
            'Configuramos Twitter Cards para una presentación optimizada al compartir en X/Twitter.',
            ['tags' => $tags, 'missing' => $missing]
        );
    }

    private function checkImagesAlt(): array {
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
                // Mostrar el nombre del archivo para referencia
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

    private function checkStructuredData(): array {
        $schemas = $this->parser->getJsonLd();
        $hasSchema = !empty($schemas);

        if (!$hasSchema) {
            // Verificar si hay microdata como fallback
            $hasMicrodata = $this->parser->containsPattern('/itemscope|itemtype/i');
            if ($hasMicrodata) {
                return Scoring::createMetric(
                    'structured_data', 'Datos estructurados (Schema.org)', true, 'Microdata detectada (no JSON-LD)', 60,
                    'Se detectó Schema.org en formato Microdata (atributos HTML). Funciona pero JSON-LD es el formato recomendado por Google por ser más fácil de mantener y depurar.',
                    'Migrar los datos estructurados de Microdata a formato JSON-LD para mejor mantenimiento.',
                    'Implementamos Schema markup en formato JSON-LD recomendado por Google.'
                );
            }

            return Scoring::createMetric(
                'structured_data', 'Datos estructurados (Schema.org)', false, 'No encontrados', 0,
                'No se encontraron datos estructurados (JSON-LD ni Microdata). Sin Schema markup, Google no puede mostrar resultados enriquecidos (estrellas, precios, FAQ, breadcrumbs, etc.) para tu sitio.',
                'Implementar Schema.org en formato JSON-LD. Mínimo recomendado: Organization, WebSite, BreadcrumbList.',
                'Implementamos Schema markup completo para aparecer con fragmentos enriquecidos en Google.'
            );
        }

        // Analizar los tipos de Schema encontrados
        $types = [];
        foreach ($schemas as $schema) {
            if (isset($schema['@type'])) {
                $types[] = is_array($schema['@type']) ? implode(', ', $schema['@type']) : $schema['@type'];
            }
            // Verificar @graph (común en Yoast, Rank Math)
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

        // Evaluar riqueza de los datos estructurados
        $valuableTypes = ['Organization', 'LocalBusiness', 'Product', 'Article', 'BlogPosting', 'FAQPage', 'BreadcrumbList', 'WebSite', 'Person', 'Review', 'HowTo', 'Event', 'Recipe'];
        $hasValuable = !empty(array_intersect($types, $valuableTypes));

        $score = $hasValuable ? 100 : 70;

        $desc = "$typeCount tipos de Schema encontrados: " . implode(', ', array_slice($types, 0, 8));
        if ($typeCount > 8) $desc .= " y " . ($typeCount - 8) . " más";
        $desc .= '.';
        if ($hasValuable) {
            $desc .= ' Incluye tipos valiosos para resultados enriquecidos en Google.';
        } else {
            $desc .= ' Se recomienda agregar tipos como Organization, BreadcrumbList o FAQ para resultados enriquecidos.';
        }

        return Scoring::createMetric(
            'structured_data', 'Datos estructurados (Schema.org)', true,
            "$typeCount tipos: " . implode(', ', array_slice($types, 0, 4)) . ($typeCount > 4 ? '...' : ''),
            $score, $desc,
            !$hasValuable ? 'Agregar tipos de Schema valiosos: Organization, BreadcrumbList, FAQ, Product según el contenido.' : '',
            'Implementamos Schema markup completo para aparecer con fragmentos enriquecidos en Google.',
            ['types' => $types, 'schemaCount' => count($schemas)]
        );
    }

    private function checkSitemap(): array {
        $sitemapUrl = null;
        $urlCount = 0;
        $isIndex = false;

        // 1. Verificar /sitemap.xml
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

        // 2. Si no, verificar /sitemap_index.xml
        if (!$sitemapUrl) {
            $response = Fetcher::get($this->url . '/sitemap_index.xml', 5, true, 0);
            if ($response['statusCode'] === 200 && (str_contains($response['body'] ?? '', '<sitemap') || str_contains($response['body'] ?? '', '<urlset'))) {
                $sitemapUrl = '/sitemap_index.xml';
                $isIndex = true;
            }
        }

        // 3. Si no, buscar en robots.txt
        if (!$sitemapUrl) {
            $robotsResp = Fetcher::get($this->url . '/robots.txt', 5, true, 0);
            if ($robotsResp['statusCode'] === 200 && preg_match('/^Sitemap:\s*(.+)$/mi', $robotsResp['body'] ?? '', $m)) {
                $sitemapUrl = trim($m[1]);
                // Verificar que sea accesible
                $checkResp = Fetcher::head($sitemapUrl, 5);
                if ($checkResp['statusCode'] !== 200) {
                    $sitemapUrl = null;
                }
            }
        }

        if (!$sitemapUrl) {
            return Scoring::createMetric(
                'sitemap', 'Sitemap XML', false, 'No encontrado', 0,
                'No se encontró sitemap XML en /sitemap.xml, /sitemap_index.xml ni referenciado en robots.txt. Sin sitemap, Google descubre páginas solo mediante enlaces internos, lo que puede dejar páginas sin indexar.',
                'Generar un sitemap XML con un plugin SEO (Yoast, Rank Math) y registrarlo en Google Search Console.',
                'Generamos sitemaps automáticos optimizados y los registramos en Google Search Console.'
            );
        }

        $desc = "Sitemap encontrado en $sitemapUrl. ";
        if ($isIndex) {
            $desc .= "Es un sitemap index" . ($urlCount > 0 ? " con $urlCount sub-sitemaps" : '') . '. Estructura profesional.';
        } else {
            $desc .= ($urlCount > 0 ? "Contiene $urlCount URLs indexadas." : 'Accesible correctamente.');
        }

        return Scoring::createMetric(
            'sitemap', 'Sitemap XML', true,
            $sitemapUrl . ($urlCount > 0 ? " ($urlCount " . ($isIndex ? 'sitemaps' : 'URLs') . ')' : ''),
            100, $desc, '',
            'Configuramos sitemaps optimizados y los registramos en Google Search Console.',
            ['url' => $sitemapUrl, 'isIndex' => $isIndex, 'count' => $urlCount]
        );
    }

    private function checkRobots(): array {
        $response = Fetcher::get($this->url . '/robots.txt', 5, true, 0);

        if ($response['statusCode'] !== 200) {
            return Scoring::createMetric(
                'robots', 'Robots.txt', false, 'No encontrado', 30,
                'No se encontró archivo robots.txt. Aunque no es obligatorio, es una buena práctica tenerlo para indicar a los buscadores qué secciones no deben rastrear y dónde está el sitemap.',
                'Crear un archivo robots.txt con directivas adecuadas y referencia al sitemap.',
                'Configuramos robots.txt optimizado para el SEO de tu sitio.'
            );
        }

        $body = $response['body'] ?? '';
        $lines = explode("\n", $body);
        $lineCount = count(array_filter($lines, fn($l) => trim($l) !== '' && !str_starts_with(trim($l), '#')));

        $blocksAll = (bool) preg_match('/Disallow:\s*\/\s*$/m', $body);
        $hasSitemap = stripos($body, 'sitemap:') !== false;
        $hasCrawlDelay = stripos($body, 'crawl-delay') !== false;

        // Contar reglas Disallow
        preg_match_all('/Disallow:\s*(.+)/i', $body, $disallowMatches);
        $disallowCount = count($disallowMatches[1] ?? []);

        if ($blocksAll) {
            return Scoring::createMetric(
                'robots', 'Robots.txt', true, 'BLOQUEA TODO EL SITIO', 5,
                'El robots.txt contiene "Disallow: /" que bloquea TODO el sitio para los buscadores. Google NO puede indexar ninguna página. Esto es un problema crítico a menos que sea intencional (sitio en desarrollo).',
                'Cambiar "Disallow: /" por directivas específicas que solo bloqueen las secciones privadas.',
                'Configuramos robots.txt optimizado que protege áreas privadas sin bloquear el contenido público.',
                ['blocksAll' => true, 'content' => mb_substr($body, 0, 500)]
            );
        }

        $score = 100;
        $notes = [];
        if (!$hasSitemap) {
            $notes[] = 'No referencia al sitemap (agregar "Sitemap: URL").';
            $score -= 10;
        }
        if ($hasCrawlDelay) {
            $notes[] = 'Usa Crawl-delay (Google lo ignora pero otros buscadores podrían rastrear más lento).';
        }

        $desc = "Robots.txt presente con $lineCount directivas activas y $disallowCount reglas Disallow. ";
        if ($hasSitemap) $desc .= 'Incluye referencia al sitemap. ';
        if (empty($notes)) {
            $desc .= 'Configuración correcta.';
        } else {
            $desc .= implode(' ', $notes);
        }

        return Scoring::createMetric(
            'robots', 'Robots.txt', true,
            "$lineCount directivas · $disallowCount Disallow" . ($hasSitemap ? ' · Sitemap' : ''),
            Scoring::clamp($score), $desc,
            !$hasSitemap ? 'Agregar la directiva "Sitemap: https://tusitio.com/sitemap.xml" al robots.txt.' : '',
            'Configuramos robots.txt optimizado para el SEO de tu sitio.',
            ['lineCount' => $lineCount, 'disallowCount' => $disallowCount, 'hasSitemap' => $hasSitemap]
        );
    }

    private function checkCanonical(): array {
        $canonical = $this->parser->getLinkByRel('canonical');

        if (!$canonical) {
            return Scoring::createMetric(
                'canonical', 'Canonical URL', null, 'No encontrada', 40,
                'No se encontró la etiqueta <link rel="canonical">. Sin canonical, si tu página es accesible por múltiples URLs (con/sin www, con/sin trailing slash, con parámetros), Google podría indexar versiones duplicadas y dividir la autoridad SEO.',
                'Agregar <link rel="canonical" href="URL-preferida"> en cada página.',
                'Configuramos canonicals correctos en todas las páginas para evitar contenido duplicado.'
            );
        }

        // Verificar que el canonical apunte a sí mismo o sea coherente
        $canonicalNorm = rtrim(strtolower($canonical), '/');
        $urlNorm = rtrim(strtolower($this->url), '/');
        $isSelfReferencing = $canonicalNorm === $urlNorm;

        $desc = "Canonical configurada: $canonical. ";
        $score = 100;
        if ($isSelfReferencing) {
            $desc .= 'Apunta a sí misma (autoreferencial). Correcto.';
        } else {
            $desc .= 'Apunta a una URL diferente a la actual. Verificar que esto sea intencional.';
            $score = 80;
        }

        return Scoring::createMetric(
            'canonical', 'Canonical URL', $canonical, $isSelfReferencing ? 'Autoreferencial' : mb_substr($canonical, 0, 50),
            $score, $desc,
            !$isSelfReferencing ? 'Verificar que el canonical apunte a la URL correcta. Un canonical diferente indica que esta página es una variante.' : '',
            'Configuramos canonicals correctos en todas las páginas para evitar contenido duplicado.',
            ['canonical' => $canonical, 'isSelfReferencing' => $isSelfReferencing]
        );
    }

    private function checkFavicon(): array {
        $favicon = $this->parser->getLinkByRel('icon') ?? $this->parser->getLinkByRel('shortcut icon');

        return Scoring::createMetric(
            'favicon', 'Favicon', $favicon !== null,
            $favicon ? 'Configurado' : 'No encontrado',
            $favicon ? 100 : 50,
            $favicon
                ? 'Favicon encontrado. Tu sitio muestra un icono en la pestaña del navegador.'
                : 'No se detectó favicon. Afecta la identidad visual en navegadores y bookmarks.',
            !$favicon ? 'Agregar un favicon para mejorar el branding.' : '',
            'Configuramos favicons optimizados para todos los dispositivos.'
        );
    }

    private function checkLanguage(): array {
        $lang = $this->parser->getHtmlLang();

        return Scoring::createMetric(
            'language', 'Idioma declarado', $lang !== null,
            $lang ? "lang=\"$lang\"" : 'No declarado',
            $lang ? 100 : 30,
            $lang
                ? "El idioma del sitio está declarado como \"$lang\"."
                : 'No se declaró el idioma con el atributo lang. Google puede no entender el idioma correcto.',
            !$lang ? 'Agregar lang="es" (o el idioma correspondiente) al tag <html>.' : '',
            'Configuramos correctamente el idioma y hreflang para SEO internacional.'
        );
    }

    private function checkContent(): array {
        $wordCount = $this->parser->getWordCount();

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

    private function checkHreflang(): array {
        // Buscar etiquetas hreflang
        $hreflangFound = [];
        if ($this->parser->containsPattern('/hreflang/i')) {
            preg_match_all('/hreflang=["\']([^"\']+)["\']/i', $this->html, $matches);
            if (!empty($matches[1])) {
                $hreflangFound = array_unique($matches[1]);
            }
        }

        if (empty($hreflangFound)) {
            return Scoring::createMetric(
                'hreflang', 'Hreflang (multi-idioma)', false, 'No configurado', 70,
                'No se encontraron etiquetas hreflang. Si tu sitio solo tiene un idioma, esto es normal. Si tienes versiones en otros idiomas, necesitas hreflang para evitar que Google las trate como contenido duplicado.',
                '', 'Configuramos hreflang para sitios multi-idioma y multi-región.'
            );
        }

        $count = count($hreflangFound);
        $langs = implode(', ', array_slice($hreflangFound, 0, 6));
        $hasXDefault = in_array('x-default', $hreflangFound);

        $desc = "$count idiomas/regiones configurados: $langs.";
        if ($hasXDefault) {
            $desc .= ' Incluye x-default (página por defecto). Configuración correcta.';
        } else {
            $desc .= ' Falta x-default (recomendado para indicar la versión por defecto).';
        }

        return Scoring::createMetric(
            'hreflang', 'Hreflang (multi-idioma)', true, "$count idiomas: $langs",
            $hasXDefault ? 100 : 80, $desc,
            !$hasXDefault ? 'Agregar hreflang="x-default" apuntando a la versión principal del sitio.' : '',
            'Configuramos hreflang para sitios multi-idioma y multi-región.',
            ['languages' => $hreflangFound, 'hasXDefault' => $hasXDefault]
        );
    }

    private function checkInternalLinks(): array {
        $links = $this->parser->getLinks();
        $domain = parse_url($this->url, PHP_URL_HOST);

        $internal = 0;
        $external = 0;
        $nofollow = 0;
        $emptyAnchors = 0;

        foreach ($links as $link) {
            $href = $link['href'] ?? '';
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $linkHost = parse_url($href, PHP_URL_HOST);
            $isInternal = $linkHost === null || $linkHost === $domain || str_ends_with($linkHost, ".$domain");

            if ($isInternal) {
                $internal++;
            } else {
                $external++;
            }

            if (str_contains($link['rel'] ?? '', 'nofollow')) {
                $nofollow++;
            }
            if (empty(trim($link['text'] ?? ''))) {
                $emptyAnchors++;
            }
        }

        $totalNav = $internal + $external;

        if ($totalNav === 0) {
            return Scoring::createMetric(
                'internal_links', 'Enlaces internos', 0, 'Sin enlaces', 20,
                'No se detectaron enlaces en la página. Los enlaces internos son fundamentales para que Google descubra y entienda la estructura de tu sitio.',
                'Agregar enlaces internos a otras páginas relevantes del sitio.',
                'Optimizamos la estructura de enlaces internos para mejorar el rastreo y la autoridad de páginas.'
            );
        }

        $score = 100;
        $issues = [];
        if ($internal < 3) {
            $issues[] = "Pocos enlaces internos ($internal). Se recomiendan al menos 3-5 para buena navegación y distribución de autoridad.";
            $score -= 25;
        }
        if ($emptyAnchors > 3) {
            $issues[] = "$emptyAnchors enlaces sin texto anchor (imágenes o links vacíos). Google usa el anchor text para entender el destino.";
            $score -= 10;
        }

        $desc = "$internal enlaces internos, $external externos. ";
        if ($nofollow > 0) $desc .= "$nofollow con nofollow. ";
        if (empty($issues)) {
            $desc .= 'Buena estructura de enlaces internos.';
        } else {
            $desc .= implode(' ', $issues);
        }

        return Scoring::createMetric(
            'internal_links', 'Enlaces internos', $internal,
            "$internal internos · $external externos",
            Scoring::clamp($score), $desc,
            $internal < 3 ? 'Agregar más enlaces internos a páginas relevantes del sitio para mejorar la navegación y el SEO.' : '',
            'Optimizamos la estructura de enlaces internos para mejorar el rastreo y la autoridad de páginas.',
            ['internal' => $internal, 'external' => $external, 'nofollow' => $nofollow, 'emptyAnchors' => $emptyAnchors]
        );
    }

    private function checkUrlStructure(): array {
        $parsedUrl = parse_url($this->url);
        $path = $parsedUrl['path'] ?? '/';

        $score = 100;
        $issues = [];

        // Verificar HTTPS
        $isHttps = ($parsedUrl['scheme'] ?? '') === 'https';
        if (!$isHttps) {
            $issues[] = 'No usa HTTPS. Google da prioridad a sitios seguros.';
            $score -= 20;
        }

        // Verificar www vs non-www (solo informativo)
        $hasWww = str_starts_with($parsedUrl['host'] ?? '', 'www.');

        // Verificar que la URL sea limpia (no tenga parámetros excesivos)
        $query = $parsedUrl['query'] ?? '';
        if (!empty($query)) {
            $paramCount = count(explode('&', $query));
            if ($paramCount > 3) {
                $issues[] = "URL con $paramCount parámetros. Las URLs limpias son mejores para SEO.";
                $score -= 10;
            }
        }

        // Verificar longitud de URL
        $urlLength = strlen($this->url);
        if ($urlLength > 100) {
            $issues[] = "URL larga ($urlLength caracteres). Se recomiendan URLs cortas y descriptivas.";
            $score -= 5;
        }

        $desc = "URL: {$this->url}. ";
        $desc .= $isHttps ? 'HTTPS activo. ' : 'HTTP sin cifrar. ';
        $desc .= $hasWww ? 'Usa www. ' : 'Sin www. ';
        if (empty($issues)) {
            $desc .= 'Estructura de URL limpia y amigable para SEO.';
        } else {
            $desc .= implode(' ', $issues);
        }

        return Scoring::createMetric(
            'url_structure', 'Estructura de URL', true,
            ($isHttps ? 'HTTPS' : 'HTTP') . ' · ' . ($hasWww ? 'www' : 'sin www') . " · $urlLength car.",
            Scoring::clamp($score), $desc,
            !empty($issues) ? implode(' ', array_map(fn($i) => str_replace('.', ',', $i), $issues)) : '',
            'Optimizamos las URLs para que sean cortas, descriptivas y amigables para SEO.',
            ['isHttps' => $isHttps, 'hasWww' => $hasWww, 'urlLength' => $urlLength]
        );
    }

    private function checkKeywordDensity(): array {
        $text = $this->parser->getTextContent();
        $words = preg_split('/\s+/', mb_strtolower($text));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 3);
        $totalWords = count($words);

        if ($totalWords < 20) {
            return Scoring::createMetric(
                'keyword_density', 'Análisis de palabras clave', 0, 'Contenido insuficiente', 30,
                'No hay suficiente contenido para analizar palabras clave.',
                'Agregar más contenido relevante a la página.',
                'Desarrollamos estrategia de contenido con keywords objetivo.'
            );
        }

        // Count single words
        $freq = array_count_values($words);
        arsort($freq);

        // Count 2-word phrases
        $bigrams = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrase = $words[$i] . ' ' . $words[$i + 1];
            $bigrams[$phrase] = ($bigrams[$phrase] ?? 0) + 1;
        }
        arsort($bigrams);

        // Filter stopwords
        $stopwords = ['para', 'como', 'este', 'esta', 'esta', 'pero', 'más', 'todo', 'todos', 'tiene', 'puede', 'hace', 'cada', 'entre', 'desde', 'hasta', 'sobre', 'también', 'cuando', 'donde', 'the', 'and', 'for', 'that', 'with', 'are', 'from', 'your', 'this', 'have', 'will', 'been', 'more', 'which', 'their', 'they', 'what', 'than', 'other', 'into', 'could', 'would', 'make', 'like', 'just', 'some'];
        $topWords = [];
        foreach ($freq as $word => $count) {
            if (count($topWords) >= 10) break;
            if (in_array($word, $stopwords) || mb_strlen($word) < 4) continue;
            $topWords[$word] = $count;
        }

        $topPhrases = [];
        foreach ($bigrams as $phrase => $count) {
            if (count($topPhrases) >= 5) break;
            if ($count < 2) continue;
            $topPhrases[$phrase] = $count;
        }

        $title = $this->parser->getTitle() ?? '';
        $h1s = array_filter($this->parser->getHeadings(), fn($h) => $h['level'] === 1);
        $h1Text = !empty($h1s) ? mb_strtolower($h1s[0]['text'] ?? '') : '';

        // Check if top keywords appear in title and H1
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
            $desc .= $inH1 ? " Aparece en H1." : " NO aparece en H1.";
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

    private function checkRssFeeds(): array {
        $feeds = [];
        // Check <link> tags for RSS/Atom feeds
        preg_match_all('/<link[^>]+type=["\']application\/(rss|atom)\+xml["\'][^>]*>/i', $this->html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if (preg_match('/href=["\']([^"\']+)/i', $m[0], $href)) {
                $type = stripos($m[0], 'atom') !== false ? 'Atom' : 'RSS';
                $feeds[] = ['url' => $href[1], 'type' => $type];
            }
        }

        $count = count($feeds);
        return Scoring::createMetric(
            'rss_feeds', 'Web Feeds (RSS/Atom)', $count,
            $count === 0 ? 'No detectados' : "$count feed(s) detectados",
            $count > 0 ? 100 : 60,
            $count > 0
                ? "Se detectaron $count feeds: " . implode(', ', array_map(fn($f) => $f['type'] . ': ' . basename($f['url']), $feeds)) . '. Los feeds permiten a los usuarios suscribirse a las actualizaciones del sitio.'
                : 'No se detectaron feeds RSS o Atom. Los feeds permiten que los usuarios se suscriban a tu contenido.',
            $count === 0 ? 'Agregar un feed RSS para que los usuarios y agregadores puedan seguir tu contenido.' : '',
            'Configuramos feeds RSS optimizados para distribución de contenido.',
            ['feeds' => $feeds]
        );
    }
}
