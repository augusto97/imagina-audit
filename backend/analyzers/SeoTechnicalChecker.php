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

    public function checkRobots(): array {
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

    public function checkCanonical(): array {
        $canonical = $this->parser->getLinkByRel('canonical');

        if (!$canonical) {
            return Scoring::createMetric(
                'canonical', 'Canonical URL', null, 'No encontrada', 40,
                'No se encontró la etiqueta <link rel="canonical">. Sin canonical, si tu página es accesible por múltiples URLs (con/sin www, con/sin trailing slash, con parámetros), Google podría indexar versiones duplicadas y dividir la autoridad SEO.',
                'Agregar <link rel="canonical" href="URL-preferida"> en cada página.',
                'Configuramos canonicals correctos en todas las páginas para evitar contenido duplicado.'
            );
        }

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

    public function checkUrlStructure(): array {
        $parsedUrl = parse_url($this->url);

        $score = 100;
        $issues = [];

        $isHttps = ($parsedUrl['scheme'] ?? '') === 'https';
        if (!$isHttps) {
            $issues[] = 'No usa HTTPS. Google da prioridad a sitios seguros.';
            $score -= 20;
        }

        $hasWww = str_starts_with($parsedUrl['host'] ?? '', 'www.');

        $query = $parsedUrl['query'] ?? '';
        if (!empty($query)) {
            $paramCount = count(explode('&', $query));
            if ($paramCount > 3) {
                $issues[] = "URL con $paramCount parámetros. Las URLs limpias son mejores para SEO.";
                $score -= 10;
            }
        }

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
}
