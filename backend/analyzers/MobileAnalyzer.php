<?php
/**
 * Analiza la compatibilidad y usabilidad móvil del sitio
 */

class MobileAnalyzer {
    private string $html;
    private string $baseUrl;
    private HtmlParser $parser;
    private ?int $mobileScore;

    /**
     * @param string $html HTML de la página a analizar
     * @param int|null $pageSpeedMobileScore Score de mobile de PageSpeed (reutilizado)
     * @param string $baseUrl URL base para resolver CSS relativos (se usa para
     *   fetchear los primeros ~2 stylesheets y buscar @media queries reales,
     *   que es donde está el 95% de la responsividad real de un sitio moderno).
     */
    public function __construct(string $html, ?int $pageSpeedMobileScore = null, string $baseUrl = '') {
        $this->html = $html;
        $this->baseUrl = $baseUrl;
        $this->mobileScore = $pageSpeedMobileScore;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Ejecuta el análisis de compatibilidad móvil
     */
    public function analyze(): array {
        $metrics = [];

        // Viewport
        $metrics[] = $this->checkViewport();

        // PageSpeed mobile score (reutilizado)
        $metrics[] = $this->checkMobileSpeed();

        // Responsive indicators
        $metrics[] = $this->checkResponsive();

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'mobile',
            'name' => Translator::t('modules.mobile.name'),
            'icon' => 'smartphone',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_mobile'],
            'metrics' => $metrics,
            'summary' => Translator::t('mobile.summary', ['score' => $score]),
            'salesMessage' => $defaults['sales_mobile'] !== '' ? $defaults['sales_mobile'] : Translator::t('modules.sales.mobile'),
        ];
    }

    /**
     * Verifica la meta tag viewport
     */
    private function checkViewport(): array {
        $viewport = $this->parser->getViewport();
        $hasDeviceWidth = $viewport && str_contains($viewport, 'width=device-width');

        $score = 0;
        if ($viewport && $hasDeviceWidth) {
            $score = 100;
        } elseif ($viewport) {
            $score = 50;
        }

        return Scoring::createMetric(
            'viewport',
            Translator::t('mobile.viewport.name'),
            $viewport !== null,
            $viewport ?: Translator::t('mobile.viewport.display.missing'),
            $score,
            $viewport
                ? ($hasDeviceWidth
                    ? Translator::t('mobile.viewport.desc.ok')
                    : Translator::t('mobile.viewport.desc.partial'))
                : Translator::t('mobile.viewport.desc.missing'),
            !$hasDeviceWidth ? Translator::t('mobile.viewport.recommendation') : '',
            Translator::t('mobile.viewport.solution')
        );
    }

    /**
     * Score de velocidad móvil (de PageSpeed)
     */
    private function checkMobileSpeed(): array {
        $score = $this->mobileScore ?? 50;

        return Scoring::createMetric(
            'mobile_speed',
            Translator::t('mobile.mobile_speed.name'),
            $this->mobileScore,
            $this->mobileScore !== null ? "{$this->mobileScore}/100" : Translator::t('mobile.mobile_speed.display.none'),
            $score,
            $this->mobileScore !== null
                ? Translator::t('mobile.mobile_speed.desc.ok', ['score' => $this->mobileScore])
                : Translator::t('mobile.mobile_speed.desc.missing'),
            ($this->mobileScore !== null && $this->mobileScore < 70)
                ? Translator::t('mobile.mobile_speed.recommendation')
                : '',
            Translator::t('mobile.mobile_speed.solution')
        );
    }

    /**
     * Verifica indicadores de diseño responsivo.
     *
     * El 90% de los sitios modernos son responsive vía CSS externo, así que
     * basar la detección solo en el HTML inline da falsos negativos constantes
     * (tema con "Bootstrap" o "Tailwind" mencionado en el HTML ≠ que el sitio
     * sea responsive). Estrategia:
     *
     *   1. Viewport meta (device-width): es prácticamente obligatorio para
     *      que Chrome/Safari no ignoren media queries en móviles. Si está,
     *      el sitio ya implementó lo básico.
     *   2. <picture> / <img srcset>: fuerte indicador de imágenes responsivas.
     *   3. Clases de framework en el HTML (container, row, col-*, sm:/md:/lg:
     *      de Tailwind): mucho más fiables que `str_contains('bootstrap')`.
     *   4. Fetch de los primeros 2 stylesheets y scan de @media queries.
     *      Con CSS puro, esto es donde está realmente la responsividad.
     */
    private function checkResponsive(): array {
        $indicators = [];
        $score = 0;

        // 1. Viewport con width=device-width
        $viewport = $this->parser->getViewport();
        $hasDeviceViewport = $viewport && str_contains(strtolower($viewport), 'width=device-width');
        if ($hasDeviceViewport) {
            $indicators[] = Translator::t('mobile.responsive.indicator.viewport');
            $score += 25;
        }

        // 2. Imágenes responsivas (srcset / <picture>)
        $images = $this->parser->getImages();
        $hasSrcset = false;
        foreach ($images as $img) {
            if (!empty($img['srcset'] ?? '')) { $hasSrcset = true; break; }
        }
        if ($hasSrcset || preg_match('/<picture[\s>]/i', $this->html)) {
            $indicators[] = Translator::t('mobile.responsive.indicator.srcset');
            $score += 20;
        }

        // 3. Clases de framework responsivo en el DOM (más fiable que "contains bootstrap")
        $classFingerprints = [
            'Bootstrap'   => '/\bclass=["\'][^"\']*\b(container|container-fluid|row|col-(xs|sm|md|lg|xl)-\d+|col-(xs|sm|md|lg|xl))\b/',
            'Tailwind'    => '/\bclass=["\'][^"\']*\b(sm|md|lg|xl|2xl):[a-z-]+/',
            'Foundation'  => '/\bclass=["\'][^"\']*\b(small-\d+|medium-\d+|large-\d+)\b/',
            'Bulma'       => '/\bclass=["\'][^"\']*\bis-(mobile|tablet|desktop)\b/',
        ];
        foreach ($classFingerprints as $name => $pattern) {
            if (preg_match($pattern, $this->html)) {
                $indicators[] = $name;
                $score += 25;
                break; // basta con detectar uno
            }
        }

        // 4. @media queries: inline en <style> + en los primeros 2 CSS externos
        if ($this->hasMediaQueries()) {
            $indicators[] = Translator::t('mobile.responsive.indicator.media');
            $score += 40;
        }

        $score = min(100, $score);

        // Si hay viewport móvil pero no pudimos confirmar CSS responsive,
        // es probable que aun así lo sea (muchos temas WP modernos lo son).
        // No penalizamos tan fuerte en ese caso.
        if (empty($indicators)) {
            $score = 30;
        } elseif ($score < 50 && $hasDeviceViewport) {
            $score = 60;
        }

        $displayList = !empty($indicators) ? implode(', ', $indicators) : Translator::t('mobile.responsive.display.none');

        return Scoring::createMetric(
            'responsive',
            Translator::t('mobile.responsive.name'),
            count($indicators),
            $displayList,
            $score,
            !empty($indicators)
                ? Translator::t('mobile.responsive.desc.found', ['list' => $displayList])
                : Translator::t('mobile.responsive.desc.missing'),
            $score < 70
                ? ($hasDeviceViewport
                    ? Translator::t('mobile.responsive.recommendation.partial')
                    : Translator::t('mobile.responsive.recommendation.missing'))
                : '',
            Translator::t('mobile.responsive.solution'),
            ['indicators' => $indicators, 'viewport' => $viewport]
        );
    }

    /**
     * Detecta @media queries en <style> inline + en los primeros 2 CSS externos.
     * Solo fetchea 2 archivos (paralelo, 4s de timeout) para no alargar el scan.
     */
    private function hasMediaQueries(): bool {
        // 1. @media en <style> inline
        if (preg_match('/@media\s*[\s(only|screen]/i', $this->html)) {
            // Verifica que realmente haya un @media query (no solo la palabra "media" suelta)
            if (preg_match('/@media\s+[^{]+\{/i', $this->html)) {
                return true;
            }
        }

        // 2. Fetch de los primeros 2 stylesheets (limitado para no matar el scan)
        $stylesheets = $this->parser->getStylesheets();
        if (empty($stylesheets) || $this->baseUrl === '') return false;

        $urls = [];
        foreach ($stylesheets as $s) {
            $href = $s['href'] ?? '';
            if ($href === '') continue;
            // Excluir obviamente admin / análisis visual (wp-includes, librerías UI comunes)
            if (preg_match('#/wp-admin/|/wp-includes/css/dashicons|googleapis\.com/css#i', $href)) continue;
            try {
                $resolved = UrlValidator::resolveUrl($this->baseUrl, $href);
                $urls[$resolved] = $resolved;
                if (count($urls) >= 2) break;
            } catch (Throwable $e) { /* skip */ }
        }

        if (empty($urls)) return false;

        try {
            $responses = Fetcher::multiGet($urls, 4);
            foreach ($responses as $resp) {
                $body = (string) ($resp['body'] ?? '');
                if ($body === '') continue;
                // Cap del body a 1MB para no explotar RAM con theme frameworks enormes
                $body = substr($body, 0, 1048576);
                if (preg_match('/@media\s+[^{]+\{/i', $body)) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            Logger::warning('MobileAnalyzer CSS fetch falló: ' . $e->getMessage());
        }
        return false;
    }
}
