<?php
/**
 * Analiza la compatibilidad y usabilidad móvil del sitio
 */

class MobileAnalyzer {
    private string $html;
    private HtmlParser $parser;
    private ?int $mobileScore;

    public function __construct(string $html, ?int $pageSpeedMobileScore = null) {
        $this->html = $html;
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
            'name' => 'Compatibilidad Móvil',
            'icon' => 'smartphone',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_mobile'],
            'metrics' => $metrics,
            'summary' => "Tu sitio tiene una puntuación móvil de $score/100.",
            'salesMessage' => $defaults['sales_mobile'],
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
            'Meta Viewport',
            $viewport !== null,
            $viewport ?: 'No configurada',
            $score,
            $viewport
                ? ($hasDeviceWidth
                    ? 'Meta viewport configurada correctamente con width=device-width.'
                    : 'Meta viewport presente pero sin width=device-width.')
                : 'No se encontró meta viewport. El sitio no se adaptará a pantallas móviles.',
            !$hasDeviceWidth ? 'Agregar <meta name="viewport" content="width=device-width, initial-scale=1">.' : '',
            'Configuramos el viewport y la experiencia móvil completa.'
        );
    }

    /**
     * Score de velocidad móvil (de PageSpeed)
     */
    private function checkMobileSpeed(): array {
        $score = $this->mobileScore ?? 50;

        return Scoring::createMetric(
            'mobile_speed',
            'Velocidad en Móvil (PageSpeed)',
            $this->mobileScore,
            $this->mobileScore !== null ? "{$this->mobileScore}/100" : 'No disponible',
            $score,
            $this->mobileScore !== null
                ? "Google PageSpeed califica la velocidad móvil con {$this->mobileScore}/100."
                : 'No fue posible obtener la puntuación de velocidad móvil.',
            ($this->mobileScore !== null && $this->mobileScore < 70)
                ? 'Optimizar la velocidad móvil: reducir CSS/JS, optimizar imágenes, usar lazy loading.'
                : '',
            'Optimizamos específicamente para velocidad en dispositivos móviles.'
        );
    }

    /**
     * Verifica indicadores de diseño responsivo
     */
    private function checkResponsive(): array {
        $indicators = [];
        $score = 0;

        // Buscar frameworks responsivos
        if ($this->parser->contains('bootstrap') || $this->parser->contains('Bootstrap')) {
            $indicators[] = 'Bootstrap';
            $score += 40;
        }
        if ($this->parser->contains('tailwind') || $this->parser->contains('Tailwind')) {
            $indicators[] = 'Tailwind CSS';
            $score += 40;
        }
        if ($this->parser->contains('foundation') || $this->parser->contains('Foundation')) {
            $indicators[] = 'Foundation';
            $score += 40;
        }

        // Buscar media queries
        if ($this->parser->containsPattern('/@media\s*\(/')) {
            $indicators[] = 'Media queries';
            $score += 30;
        }

        // Buscar srcset en imágenes
        $images = $this->parser->getImages();
        $hasSrcset = false;
        foreach ($images as $img) {
            if (!empty($img['srcset'] ?? '')) {
                $hasSrcset = true;
                break;
            }
        }
        if ($hasSrcset || $this->parser->contains('srcset')) {
            $indicators[] = 'Imágenes responsivas (srcset)';
            $score += 30;
        }

        $score = min(100, $score);
        if (empty($indicators)) {
            $score = 40; // Puede ser responsive sin indicadores detectables
        }

        return Scoring::createMetric(
            'responsive',
            'Diseño Responsivo',
            count($indicators),
            !empty($indicators) ? implode(', ', $indicators) : 'No se detectaron indicadores claros',
            $score,
            !empty($indicators)
                ? 'Se detectaron indicadores de diseño responsivo: ' . implode(', ', $indicators) . '.'
                : 'No se detectaron indicadores claros de diseño responsivo.',
            $score < 70 ? 'Implementar diseño responsive con media queries y frameworks modernos.' : '',
            'Aseguramos que tu sitio sea 100% responsive en todos los dispositivos.'
        );
    }
}
