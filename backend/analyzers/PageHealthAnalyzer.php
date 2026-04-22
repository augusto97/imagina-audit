<?php
/**
 * Analiza la salud técnica de la página: status code, recursos rotos,
 * contenido mixto, canonical, codificación, frames, enlaces, etc.
 */

class PageHealthAnalyzer {
    private string $url;
    private string $html;
    private array $headers;
    private HtmlParser $parser;
    private bool $isHttps;
    private string $domain;

    public function __construct(string $url, string $html, array $headers) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
        $this->isHttps = str_starts_with($url, 'https');
        $this->domain = parse_url($url, PHP_URL_HOST) ?: '';
    }

    public function analyze(): array {
        $metrics = [];

        $metrics[] = $this->checkStatusCode();
        $metrics[] = $this->checkMixedContent();
        $metrics[] = $this->checkMetaRefresh();
        $metrics[] = $this->checkCharset();
        $metrics[] = $this->checkFrames();
        $metrics[] = $this->checkDuplicateCanonical();
        $metrics[] = $this->checkHtmlErrors();
        $metrics[] = $this->checkTextCodeRatio();
        $metrics[] = $this->checkLinkStats();
        $metrics[] = $this->checkBrokenResources();
        $metrics[] = $this->checkDoctype();
        $metrics[] = $this->checkCustom404();
        $metrics[] = $this->checkUrlResolution();

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'page_health',
            'name' => Translator::t('modules.page_health.name'),
            'icon' => 'heart-pulse',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_page_health'] ?? 0.10,
            'metrics' => $metrics,
            'summary' => Translator::t('page_health.summary', ['score' => $score]),
            'salesMessage' => !empty($defaults['sales_page_health']) ? $defaults['sales_page_health'] : Translator::t('modules.sales.page_health'),
        ];
    }

    private function checkStatusCode(): array {
        $status = (int)($this->headers['_status_code'] ?? 200);
        $score = $status === 200 ? 100 : ($status < 400 ? 70 : 0);

        return Scoring::createMetric(
            'status_code',
            Translator::t('page_health.status.name'),
            $status,
            Translator::t('page_health.status.display', ['code' => $status]),
            $status === 200 ? null : $score, // 200 es informativo, otros sí afectan
            $status === 200
                ? Translator::t('page_health.status.desc.ok')
                : Translator::t('page_health.status.desc.bad', ['code' => $status]),
            $status !== 200 ? Translator::t('page_health.status.recommend') : '',
            Translator::t('page_health.status.solution')
        );
    }

    private function checkMixedContent(): array {
        if (!$this->isHttps) {
            return Scoring::createMetric(
                'mixed_content',
                Translator::t('page_health.mixed.name'),
                null,
                Translator::t('page_health.mixed.display.na'),
                70,
                Translator::t('page_health.mixed.desc.na'),
                Translator::t('page_health.mixed.recommend.na'),
                Translator::t('page_health.mixed.solution.na')
            );
        }

        $mixedPatterns = [
            'src="http://', "src='http://",
            'href="http://', "href='http://",
            'url(http://', "url('http://",
        ];
        $found = 0;
        foreach ($mixedPatterns as $p) {
            $found += substr_count($this->html, $p);
        }

        $score = $found === 0 ? 100 : ($found <= 3 ? 60 : 20);
        return Scoring::createMetric(
            'mixed_content',
            Translator::t('page_health.mixed.name'),
            $found,
            $found === 0
                ? Translator::t('page_health.mixed.display.ok')
                : Translator::t('page_health.mixed.display.bad', ['count' => $found]),
            $score,
            $found === 0
                ? Translator::t('page_health.mixed.desc.ok')
                : Translator::t('page_health.mixed.desc.bad', ['count' => $found]),
            $found > 0 ? Translator::t('page_health.mixed.recommend.bad') : '',
            Translator::t('page_health.mixed.solution'),
            ['count' => $found]
        );
    }

    private function checkMetaRefresh(): array {
        $metaRefresh = $this->parser->getMeta('refresh');
        $hasRefresh = $metaRefresh !== null;

        return Scoring::createMetric(
            'meta_refresh',
            Translator::t('page_health.mrefresh.name'),
            $hasRefresh,
            $hasRefresh ? Translator::t('page_health.mrefresh.display.bad') : Translator::t('page_health.mrefresh.display.ok'),
            $hasRefresh ? 30 : 100,
            $hasRefresh ? Translator::t('page_health.mrefresh.desc.bad') : Translator::t('page_health.mrefresh.desc.ok'),
            $hasRefresh ? Translator::t('page_health.mrefresh.recommend') : '',
            Translator::t('page_health.mrefresh.solution')
        );
    }

    private function checkCharset(): array {
        $charset = $this->parser->getMeta('charset');
        // Also check <meta charset="...">
        $hasCharset = $charset !== null || preg_match('/<meta\s+charset=["\']?([^"\'>\s]+)/i', $this->html, $m);
        $detectedCharset = $charset ?? ($m[1] ?? null);
        $isUtf8 = $detectedCharset && stripos($detectedCharset, 'utf-8') !== false;

        $score = $isUtf8 ? 100 : ($hasCharset ? 70 : 30);
        return Scoring::createMetric(
            'charset',
            Translator::t('page_health.charset.name'),
            $detectedCharset,
            $detectedCharset ?: Translator::t('page_health.charset.display.none'),
            $score,
            $isUtf8
                ? Translator::t('page_health.charset.desc.utf8')
                : ($hasCharset
                    ? Translator::t('page_health.charset.desc.other', ['charset' => $detectedCharset])
                    : Translator::t('page_health.charset.desc.none')),
            !$isUtf8 ? Translator::t('page_health.charset.recommend') : '',
            Translator::t('page_health.charset.solution')
        );
    }

    private function checkFrames(): array {
        $hasFrame = preg_match('/<frame[\s>]/i', $this->html);
        $iframeCount = substr_count(strtolower($this->html), '<iframe');

        $score = $hasFrame ? 20 : ($iframeCount > 5 ? 60 : 100);
        if ($hasFrame) {
            $display = Translator::t('page_health.frames.display.frame');
            $desc = Translator::t('page_health.frames.desc.frame');
        } elseif ($iframeCount > 5) {
            $display = Translator::t('page_health.frames.display.many', ['count' => $iframeCount]);
            $desc = Translator::t('page_health.frames.desc.many', ['count' => $iframeCount]);
        } elseif ($iframeCount > 0) {
            $display = Translator::t('page_health.frames.display.many', ['count' => $iframeCount]);
            $desc = Translator::t('page_health.frames.desc.some', ['count' => $iframeCount]);
        } else {
            $display = Translator::t('page_health.frames.display.none');
            $desc = Translator::t('page_health.frames.desc.none');
        }

        return Scoring::createMetric(
            'frames',
            Translator::t('page_health.frames.name'),
            $iframeCount,
            $display,
            $score,
            $desc,
            $hasFrame ? Translator::t('page_health.frames.recommend') : '',
            Translator::t('page_health.frames.solution')
        );
    }

    private function checkDuplicateCanonical(): array {
        $canonicals = $this->parser->findInHtml('/<link[^>]+rel=["\']canonical["\'][^>]*>/i');
        $count = count($canonicals);

        $score = $count === 1 ? 100 : ($count === 0 ? 50 : 20);
        if ($count === 1) {
            $display = Translator::t('page_health.dupcan.display.ok');
            $desc = Translator::t('page_health.dupcan.desc.ok');
        } elseif ($count === 0) {
            $display = Translator::t('page_health.dupcan.display.none');
            $desc = Translator::t('page_health.dupcan.desc.none');
        } else {
            $display = Translator::t('page_health.dupcan.display.dup', ['count' => $count]);
            $desc = Translator::t('page_health.dupcan.desc.dup', ['count' => $count]);
        }

        return Scoring::createMetric(
            'duplicate_canonical',
            Translator::t('page_health.dupcan.name'),
            $count,
            $display,
            $score,
            $desc,
            $count > 1 ? Translator::t('page_health.dupcan.recommend') : '',
            Translator::t('page_health.dupcan.solution')
        );
    }

    private function checkIndexability(): array {
        // Check X-Robots-Tag header
        $xRobots = $this->headers['x-robots-tag'] ?? '';
        $headerNoindex = stripos($xRobots, 'noindex') !== false;

        // Check meta robots
        $metaRobots = $this->parser->getMeta('robots') ?? '';
        $metaNoindex = stripos($metaRobots, 'noindex') !== false;

        // Check noindex in response
        $blocked = $headerNoindex || $metaNoindex;
        $reasons = [];
        if ($headerNoindex) $reasons[] = "Header X-Robots-Tag: $xRobots";
        if ($metaNoindex) $reasons[] = "Meta robots: $metaRobots";

        $score = $blocked ? 0 : 100;
        return Scoring::createMetric(
            'indexability', 'Restricción de indexación', $blocked, $blocked ? 'Bloqueada' : 'Indexable',
            $score,
            $blocked
                ? 'La página está bloqueada para indexación: ' . implode('. ', $reasons) . '. Google NO la mostrará en los resultados.'
                : 'La página es indexable. No tiene restricciones de indexación.',
            $blocked ? 'Verificar si el bloqueo es intencional. Si no, eliminar las directivas noindex.' : '',
            'Verificamos la configuración de indexación en todas las páginas.',
            ['headerNoindex' => $headerNoindex, 'metaNoindex' => $metaNoindex, 'reasons' => $reasons]
        );
    }

    private function checkHtmlErrors(): array {
        // Basic HTML validation: unclosed tags, common errors
        $errors = [];

        // Check for unclosed common tags
        $importantTags = ['html', 'head', 'body', 'title'];
        foreach ($importantTags as $tag) {
            $opens = substr_count(strtolower($this->html), "<$tag");
            $closes = substr_count(strtolower($this->html), "</$tag>");
            if ($opens > 0 && $closes === 0) {
                $errors[] = "Tag <$tag> sin cerrar";
            }
        }

        // Check for deprecated tags
        $deprecated = ['<center', '<font ', '<marquee', '<blink', '<strike'];
        foreach ($deprecated as $tag) {
            if (stripos($this->html, $tag) !== false) {
                $errors[] = "Tag obsoleto: $tag";
            }
        }

        // Check for inline styles (excessive)
        $inlineStyles = substr_count($this->html, 'style="');
        if ($inlineStyles > 30) {
            $errors[] = "$inlineStyles estilos inline (excesivo)";
        }

        $count = count($errors);
        $score = $count === 0 ? 100 : ($count <= 2 ? 70 : 40);

        return Scoring::createMetric(
            'html_errors', 'Errores y alertas HTML', $count, $count === 0 ? 'Sin errores detectados' : "$count problemas",
            $score,
            $count === 0
                ? 'No se detectaron errores HTML importantes.'
                : 'Se detectaron problemas en el HTML: ' . implode(', ', array_slice($errors, 0, 5)) . '.',
            $count > 0 ? 'Corregir los errores HTML detectados para mejorar la compatibilidad con los navegadores.' : '',
            'Corregimos los errores HTML y optimizamos la estructura del código.',
            ['errors' => $errors, 'inlineStyles' => $inlineStyles]
        );
    }

    private function checkLinkStats(): array {
        $links = $this->parser->getLinks();
        $internal = 0;
        $external = 0;
        $nofollow = 0;
        $dofollow = 0;
        $broken = [];

        $linkDetails = [];
        foreach ($links as $link) {
            $href = $link['href'] ?? '';
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) continue;

            $linkHost = parse_url($href, PHP_URL_HOST);
            $isInternal = $linkHost === null || $linkHost === $this->domain || str_ends_with($linkHost, '.' . $this->domain);

            if ($isInternal) $internal++;
            else $external++;

            $rel = strtolower($link['rel'] ?? '');
            $isNofollow = str_contains($rel, 'nofollow');
            if ($isNofollow) $nofollow++;
            else $dofollow++;

            $linkDetails[] = [
                'href' => mb_substr($href, 0, 120),
                'anchor' => mb_substr(trim($link['text'] ?? ''), 0, 60) ?: '(sin texto)',
                'type' => $isInternal ? 'internal' : 'external',
                'follow' => $isNofollow ? 'nofollow' : 'dofollow',
            ];
        }

        $total = $internal + $external;
        $extDofollow = count(array_filter($linkDetails, fn($l) => $l['type'] === 'external' && $l['follow'] === 'dofollow'));

        return Scoring::createMetric(
            'link_stats', 'Estadísticas de enlaces', $total,
            "$total enlaces ($internal int. · $external ext. · $extDofollow ext. dofollow)",
            null, // Informativo — no afecta score
            "La página tiene $total enlaces: $internal internos y $external externos. $dofollow dofollow y $nofollow nofollow. $extDofollow enlaces externos dofollow.",
            $total > 200 ? 'Reducir el número de enlaces a menos de 200 para no diluir el PageRank.' : '',
            'Optimizamos la estructura de enlaces internos para mejorar el SEO.',
            ['total' => $total, 'internal' => $internal, 'external' => $external, 'dofollow' => $dofollow, 'nofollow' => $nofollow, 'extDofollow' => $extDofollow, 'links' => array_slice($linkDetails, 0, 50)]
        );
    }

    private function checkBrokenResources(): array {
        // Check images and scripts for broken URLs (limited to 10 checks)
        $images = $this->parser->getImages();
        $scripts = $this->parser->getScripts();
        $broken = [];
        $checked = 0;
        $maxChecks = 10;

        $resources = [];
        foreach ($images as $img) {
            if (!empty($img['src']) && !str_starts_with($img['src'], 'data:')) {
                $resources[] = ['url' => $img['src'], 'type' => 'image'];
            }
        }
        foreach ($scripts as $s) {
            if (!empty($s['src'])) {
                $resources[] = ['url' => $s['src'], 'type' => 'script'];
            }
        }

        // Shuffle and check a random sample
        shuffle($resources);
        foreach ($resources as $res) {
            if ($checked >= $maxChecks) break;
            $resUrl = $res['url'];
            if (!str_starts_with($resUrl, 'http')) {
                $resUrl = rtrim($this->url, '/') . '/' . ltrim($resUrl, '/');
            }
            try {
                $response = Fetcher::head($resUrl, 3);
                if ($response['statusCode'] >= 400) {
                    $broken[] = ['url' => $res['url'], 'type' => $res['type'], 'status' => $response['statusCode']];
                }
            } catch (Throwable $e) {
                // skip
            }
            $checked++;
        }

        $count = count($broken);
        $score = $count === 0 ? 100 : ($count <= 2 ? 60 : 20);
        $brokenDisplay = array_map(fn($b) => basename(parse_url($b['url'], PHP_URL_PATH) ?: $b['url']) . " ({$b['status']})", $broken);

        return Scoring::createMetric(
            'broken_resources', 'Recursos rotos', $count,
            $count === 0 ? 'Ninguno detectado' : "$count recursos rotos",
            $score,
            $count === 0
                ? "Se verificaron $checked recursos y no se encontraron rotos."
                : "Se encontraron $count recursos rotos de $checked verificados: " . implode(', ', $brokenDisplay) . '.',
            $count > 0 ? 'Corregir o eliminar los recursos rotos (imágenes o scripts que devuelven error 404).' : '',
            'Identificamos y corregimos todos los recursos rotos del sitio.',
            ['broken' => $broken, 'checked' => $checked, 'totalResources' => count($resources)]
        );
    }

    private function checkUrlHealth(): array {
        $url = $this->url;
        $parsedUrl = parse_url($url);
        $issues = [];

        // Dynamic URL (has query parameters)
        $isDynamic = !empty($parsedUrl['query']);
        if ($isDynamic) $issues[] = 'URL dinámica con parámetros (?key=value)';

        // URL length
        $length = strlen($url);
        if ($length > 100) $issues[] = "URL larga ($length caracteres)";

        // Uppercase in URL
        $path = $parsedUrl['path'] ?? '/';
        if ($path !== strtolower($path)) $issues[] = 'URL contiene mayúsculas';

        // Underscores (Google prefers hyphens)
        if (str_contains($path, '_')) $issues[] = 'URL usa guiones bajos en vez de guiones';

        // Double slashes
        if (str_contains($path, '//')) $issues[] = 'URL tiene doble barra (//)';

        $count = count($issues);
        $score = $count === 0 ? 100 : Scoring::clamp(100 - ($count * 15));

        return Scoring::createMetric(
            'url_health', 'Salud de la URL', $count,
            $count === 0 ? 'URL limpia' : "$count problemas",
            $score,
            $count === 0
                ? "URL limpia y amigable para SEO ($length caracteres)."
                : 'Problemas en la URL: ' . implode('. ', $issues) . '.',
            $count > 0 ? 'Usar URLs cortas, en minúsculas, con guiones, sin parámetros innecesarios.' : '',
            'Optimizamos la estructura de URLs para mejorar el SEO.',
            ['length' => $length, 'isDynamic' => $isDynamic, 'issues' => $issues]
        );
    }

    private function checkDoctype(): array {
        $hasDoctype = (bool)preg_match('/<!DOCTYPE\s+html/i', $this->html);

        return Scoring::createMetric(
            'doctype',
            Translator::t('page_health.doctype.name'),
            $hasDoctype,
            $hasDoctype ? Translator::t('page_health.doctype.display.ok') : Translator::t('page_health.doctype.display.none'),
            $hasDoctype ? 100 : 40,
            $hasDoctype ? Translator::t('page_health.doctype.desc.ok') : Translator::t('page_health.doctype.desc.none'),
            $hasDoctype ? '' : Translator::t('page_health.doctype.recommend'),
            Translator::t('page_health.doctype.solution')
        );
    }

    private function checkOpenGraphComplete(): array {
        $requiredOg = ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'];
        $requiredTw = ['twitter:card', 'twitter:title', 'twitter:description'];

        $ogPresent = 0;
        $twPresent = 0;
        foreach ($requiredOg as $tag) {
            if ($this->parser->getMeta($tag)) $ogPresent++;
        }
        foreach ($requiredTw as $tag) {
            if ($this->parser->getMeta($tag)) $twPresent++;
        }

        $total = count($requiredOg) + count($requiredTw);
        $found = $ogPresent + $twPresent;
        $score = (int)round(($found / $total) * 100);

        return Scoring::createMetric(
            'social_tags_complete', 'Tags sociales completos', $found,
            "$found/$total tags sociales",
            $score,
            "$ogPresent/" . count($requiredOg) . " Open Graph y $twPresent/" . count($requiredTw) . " Twitter Cards configurados.",
            $found < $total ? 'Completar las etiquetas Open Graph y Twitter Cards faltantes para mejor presentación al compartir.' : '',
            'Configuramos todas las etiquetas sociales para una presentación profesional.',
            ['ogPresent' => $ogPresent, 'ogTotal' => count($requiredOg), 'twPresent' => $twPresent, 'twTotal' => count($requiredTw)]
        );
    }

    private function checkTextCodeRatio(): array {
        $htmlSize = strlen($this->html);
        $textContent = $this->parser->getTextContent();
        $textSize = strlen($textContent);

        if ($htmlSize === 0) {
            return Scoring::createMetric(
                'text_code_ratio', 'Ratio Texto/Código', 0, 'Sin datos', 50,
                'No se pudo calcular el ratio texto/código.', '',
                'Optimizamos el código para un mejor ratio texto/código.'
            );
        }

        $ratio = round(($textSize / $htmlSize) * 100, 1);
        $score = $ratio >= 25 ? 100 : ($ratio >= 15 ? 80 : ($ratio >= 10 ? 60 : ($ratio >= 5 ? 40 : 15)));

        return Scoring::createMetric(
            'text_code_ratio', 'Ratio Texto/Código', $ratio, "$ratio%",
            $score,
            $ratio >= 15
                ? "El ratio texto/código es $ratio%. Buen equilibrio entre contenido visible y código HTML."
                : "El ratio texto/código es $ratio%. " . ($ratio < 10 ? 'Muy bajo — los buscadores podrían considerar que esta página tiene poco contenido relevante.' : 'Se recomienda al menos 15% para que los buscadores valoren el contenido.'),
            $ratio < 15 ? 'Reducir el código innecesario (CSS/JS inline, HTML redundante) y agregar más contenido de texto visible.' : '',
            'Optimizamos el código eliminando bloat y mejorando la proporción de contenido útil.',
            ['textSize' => $textSize, 'htmlSize' => $htmlSize, 'ratio' => $ratio]
        );
    }

    private function checkCustom404(): array {
        // Fetch a URL that shouldn't exist
        $testUrl = $this->url . '/imagina-audit-test-404-' . time();
        $response = Fetcher::get($testUrl, 5, false, 0);
        $status = $response['statusCode'];
        $hasCustom = $status === 404;
        $returns200 = $status === 200;

        if ($hasCustom) {
            return Scoring::createMetric(
                'custom_404', 'Página 404 personalizada', true, 'Configurada (HTTP 404)',
                100, 'El servidor devuelve código 404 para páginas inexistentes. Correcto.',
                '', 'Configuramos páginas 404 personalizadas con enlaces útiles.'
            );
        }

        return Scoring::createMetric(
            'custom_404', 'Página 404 personalizada', false,
            $returns200 ? 'Devuelve 200 en vez de 404' : "Devuelve $status",
            $returns200 ? 30 : 50,
            $returns200
                ? 'El servidor devuelve código 200 para URLs inexistentes en vez de 404. Esto causa "soft 404" que confunde a Google y desperdicia crawl budget.'
                : "El servidor devuelve código $status para páginas inexistentes.",
            'Configurar el servidor para devolver código HTTP 404 en páginas inexistentes y mostrar una página útil con enlaces.',
            'Configuramos páginas 404 personalizadas que ayudan a los usuarios a navegar.'
        );
    }

    private function checkUrlResolution(): array {
        $parsed = parse_url($this->url);
        $host = $parsed['host'] ?? '';
        $scheme = $parsed['scheme'] ?? 'https';

        // Build 4 URL variants
        $variants = [
            "http://$host/",
            "https://$host/",
        ];
        // Add www/non-www variant
        if (str_starts_with($host, 'www.')) {
            $bare = substr($host, 4);
            $variants[] = "http://$bare/";
            $variants[] = "https://$bare/";
        } else {
            $variants[] = "http://www.$host/";
            $variants[] = "https://www.$host/";
        }

        $results = [];
        $allResolve = true;
        $targetUrl = rtrim($this->url, '/') . '/';

        foreach ($variants as $v) {
            try {
                $resp = Fetcher::get($v, 5, false, 0);
                $finalUrl = $resp['finalUrl'] ?? $v;
                // Follow redirects manually (check Location header)
                if (in_array($resp['statusCode'], [301, 302, 307, 308])) {
                    $finalUrl = $resp['headers']['location'] ?? $finalUrl;
                }
                $matches = rtrim(strtolower($finalUrl), '/') === rtrim(strtolower($targetUrl), '/');
                $results[] = ['variant' => $v, 'redirectsTo' => $finalUrl, 'matches' => $matches, 'status' => $resp['statusCode']];
                if (!$matches) $allResolve = false;
            } catch (Throwable $e) {
                $results[] = ['variant' => $v, 'redirectsTo' => 'Error', 'matches' => false, 'status' => 0];
                $allResolve = false;
            }
        }

        $score = $allResolve ? 100 : 60;
        $desc = $allResolve
            ? 'Todas las variantes del dominio (http/https, www/sin-www) redirigen correctamente a la URL principal.'
            : 'No todas las variantes del dominio redirigen al mismo destino. Esto puede causar contenido duplicado.';

        return Scoring::createMetric(
            'url_resolution', 'Resolución de URL (www/https)', $allResolve,
            $allResolve ? 'Todas redirigen correctamente' : 'Inconsistencias detectadas',
            $score, $desc,
            $allResolve ? '' : 'Configurar redirecciones 301 para que http, https, www y sin-www apunten a la misma URL.',
            'Configuramos las redirecciones correctas para evitar contenido duplicado.',
            ['results' => $results]
        );
    }
}
