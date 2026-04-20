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

    public function checkStructuredData(): array {
        $schemas = $this->parser->getJsonLd();
        $hasSchema = !empty($schemas);

        if (!$hasSchema) {
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
        return Scoring::createMetric(
            'rss_feeds', 'Web Feeds (RSS/Atom)', $count,
            $count === 0 ? 'No detectados' : "$count feed(s) detectados",
            null, // Informativo — no afecta score
            $count > 0
                ? "Se detectaron $count feeds: " . implode(', ', array_map(fn($f) => $f['type'] . ': ' . basename($f['url']), $feeds)) . '. Los feeds permiten a los usuarios suscribirse a las actualizaciones del sitio.'
                : 'No se detectaron feeds RSS o Atom. Los feeds permiten que los usuarios se suscriban a tu contenido.',
            $count === 0 ? 'Agregar un feed RSS para que los usuarios y agregadores puedan seguir tu contenido.' : '',
            'Configuramos feeds RSS optimizados para distribución de contenido.',
            ['feeds' => $feeds]
        );
    }
}
