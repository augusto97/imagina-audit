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

        // Title
        $metrics[] = $this->checkTitle();

        // Meta description
        $metrics[] = $this->checkMetaDescription();

        // Headings H1
        $metrics[] = $this->checkH1();

        // Open Graph
        $metrics[] = $this->checkOpenGraph();

        // Imágenes con alt
        $metrics[] = $this->checkImagesAlt();

        // Datos estructurados
        $metrics[] = $this->checkStructuredData();

        // Sitemap
        $metrics[] = $this->checkSitemap();

        // Robots.txt
        $metrics[] = $this->checkRobots();

        // Canonical
        $metrics[] = $this->checkCanonical();

        // Favicon
        $metrics[] = $this->checkFavicon();

        // Idioma
        $metrics[] = $this->checkLanguage();

        // Contenido
        $metrics[] = $this->checkContent();

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

        $score = 0;
        if ($title) {
            $score = ($length >= 30 && $length <= 70) ? 100 : 60;
        }

        return Scoring::createMetric(
            'title',
            'Etiqueta Title',
            $title,
            $title ? "$title ($length caracteres)" : 'No encontrada',
            $score,
            $title
                ? (($length >= 30 && $length <= 70)
                    ? "Título correcto con $length caracteres."
                    : "Título presente pero con $length caracteres. Se recomienda entre 30 y 70.")
                : 'No se encontró la etiqueta <title>. Es esencial para el SEO.',
            !$title ? 'Agregar una etiqueta <title> descriptiva y única.' : '',
            'Optimizamos los títulos de todas tus páginas para mejorar el posicionamiento.'
        );
    }

    private function checkMetaDescription(): array {
        $desc = $this->parser->getMeta('description');
        $length = $desc ? mb_strlen($desc) : 0;

        $score = 0;
        if ($desc) {
            $score = ($length >= 120 && $length <= 160) ? 100 : 60;
        }

        return Scoring::createMetric(
            'meta_description',
            'Meta Description',
            $desc,
            $desc ? mb_substr($desc, 0, 80) . '... (' . $length . ' car.)' : 'No encontrada',
            $score,
            $desc
                ? (($length >= 120 && $length <= 160)
                    ? "Meta description correcta con $length caracteres."
                    : "Meta description presente pero con $length caracteres. Se recomienda entre 120 y 160.")
                : 'No se encontró meta description. Afecta cómo aparece tu sitio en Google.',
            !$desc ? 'Agregar una meta description atractiva que describa la página.' : '',
            'Redactamos meta descriptions optimizadas para cada página importante.'
        );
    }

    private function checkH1(): array {
        $headings = $this->parser->getHeadings();
        $h1s = array_filter($headings, fn($h) => $h['level'] === 1);
        $h1Count = count($h1s);

        $score = $h1Count === 1 ? 100 : ($h1Count === 0 ? 0 : 30);

        return Scoring::createMetric(
            'h1',
            'Encabezado H1',
            $h1Count,
            $h1Count === 1 ? '1 H1 encontrado' : "$h1Count H1 encontrados",
            $score,
            $h1Count === 1
                ? 'Exactamente un H1 presente. Estructura correcta.'
                : ($h1Count === 0
                    ? 'No se encontró ningún H1. El H1 es el encabezado más importante para SEO.'
                    : "Se encontraron $h1Count H1. Solo debe haber uno por página."),
            $h1Count !== 1 ? 'Usar exactamente un H1 por página con la keyword principal.' : '',
            'Optimizamos la estructura de encabezados para mejorar el SEO on-page.',
            ['headings' => array_map(fn($h) => $h['text'], $h1s)]
        );
    }

    private function checkOpenGraph(): array {
        $tags = [
            'og:title' => $this->parser->getMeta('og:title'),
            'og:description' => $this->parser->getMeta('og:description'),
            'og:image' => $this->parser->getMeta('og:image'),
            'og:url' => $this->parser->getMeta('og:url'),
        ];

        $present = array_filter($tags, fn($v) => $v !== null && $v !== '');
        $count = count($present);
        $total = count($tags);
        $score = (int) round(($count / $total) * 100);

        return Scoring::createMetric(
            'open_graph',
            'Open Graph (redes sociales)',
            $count,
            "$count/$total tags presentes",
            $score,
            $count === $total
                ? 'Todas las etiquetas Open Graph están configuradas. Buena presentación en redes sociales.'
                : "Faltan etiquetas Open Graph. Tu sitio no se verá bien al compartir en redes sociales.",
            $count < $total ? 'Agregar las etiquetas Open Graph faltantes (og:title, og:description, og:image, og:url).' : '',
            'Configuramos Open Graph y Twitter Cards para una presentación profesional en redes.',
            ['tags' => $tags]
        );
    }

    private function checkImagesAlt(): array {
        $images = $this->parser->getImages();
        $total = count($images);
        $withAlt = count(array_filter($images, fn($img) => !empty(trim($img['alt'] ?? ''))));

        if ($total === 0) {
            return Scoring::createMetric(
                'images_alt', 'Imágenes con alt', 0, 'Sin imágenes', 100,
                'No se encontraron imágenes en la página.', '',
                'Optimizamos todas las imágenes con textos alt descriptivos.'
            );
        }

        $percent = round(($withAlt / $total) * 100);
        $score = $percent >= 90 ? 100 : (int) round($percent * 0.9);

        return Scoring::createMetric(
            'images_alt',
            'Imágenes con texto alt',
            $withAlt,
            "$withAlt/$total imágenes con alt ($percent%)",
            $score,
            $percent >= 90
                ? "El $percent% de las imágenes tienen texto alternativo."
                : "Solo el $percent% de las imágenes tienen texto alternativo. Google necesita el alt para indexarlas.",
            $percent < 90 ? 'Agregar texto alt descriptivo a todas las imágenes.' : '',
            'Agregamos textos alt optimizados a todas las imágenes del sitio.'
        );
    }

    private function checkStructuredData(): array {
        $schemas = $this->parser->getJsonLd();
        $hasSchema = !empty($schemas);
        $types = array_map(fn($s) => $s['@type'] ?? 'Desconocido', $schemas);

        return Scoring::createMetric(
            'structured_data',
            'Datos estructurados (Schema.org)',
            $hasSchema,
            $hasSchema ? implode(', ', $types) : 'No encontrados',
            $hasSchema ? 100 : 0,
            $hasSchema
                ? 'Se encontraron datos estructurados: ' . implode(', ', $types) . '.'
                : 'No se encontraron datos estructurados. Ayudan a Google a entender tu contenido.',
            $hasSchema ? '' : 'Agregar Schema.org (JSON-LD) para mejorar resultados enriquecidos.',
            'Implementamos Schema markup para aparecer con fragmentos enriquecidos en Google.'
        );
    }

    private function checkSitemap(): array {
        // Verificar sitemap.xml
        $response = Fetcher::get($this->url . '/sitemap.xml', 5, true, 0);
        if ($response['statusCode'] === 200 && str_contains($response['body'], '<urlset') || str_contains($response['body'] ?? '', '<sitemapindex')) {
            return Scoring::createMetric(
                'sitemap', 'Sitemap XML', true, 'Encontrado en /sitemap.xml', 100,
                'Sitemap XML encontrado y accesible.', '',
                'Configuramos sitemaps optimizados y los registramos en Search Console.'
            );
        }

        // Verificar sitemap_index.xml
        $response = Fetcher::get($this->url . '/sitemap_index.xml', 5, true, 0);
        if ($response['statusCode'] === 200 && (str_contains($response['body'], '<sitemap') || str_contains($response['body'], '<urlset'))) {
            return Scoring::createMetric(
                'sitemap', 'Sitemap XML', true, 'Encontrado en /sitemap_index.xml', 100,
                'Sitemap XML encontrado en /sitemap_index.xml.', '',
                'Configuramos sitemaps optimizados y los registramos en Search Console.'
            );
        }

        return Scoring::createMetric(
            'sitemap', 'Sitemap XML', false, 'No encontrado', 0,
            'No se encontró sitemap XML. Google necesita el sitemap para indexar tu sitio correctamente.',
            'Generar y configurar un sitemap XML con un plugin SEO.',
            'Generamos sitemaps automáticos y los registramos en Google Search Console.'
        );
    }

    private function checkRobots(): array {
        $response = Fetcher::get($this->url . '/robots.txt', 5, true, 0);

        if ($response['statusCode'] !== 200) {
            return Scoring::createMetric(
                'robots', 'Robots.txt', false, 'No encontrado', 30,
                'No se encontró robots.txt. Se recomienda tenerlo para guiar a los buscadores.',
                'Crear un archivo robots.txt con las directivas adecuadas.',
                'Configuramos robots.txt optimizado para el SEO de tu sitio.'
            );
        }

        $body = $response['body'];
        $blocksAll = preg_match('/Disallow:\s*\/\s*$/m', $body);

        return Scoring::createMetric(
            'robots', 'Robots.txt', true,
            $blocksAll ? 'Bloquea todo el sitio' : 'Configurado correctamente',
            $blocksAll ? 20 : 100,
            $blocksAll
                ? 'El robots.txt está bloqueando todo el sitio (Disallow: /). Google no puede indexar tu contenido.'
                : 'Robots.txt presente y configurado correctamente.',
            $blocksAll ? 'Corregir robots.txt para permitir la indexación.' : '',
            'Configuramos robots.txt optimizado para el SEO.'
        );
    }

    private function checkCanonical(): array {
        $canonical = $this->parser->getLinkByRel('canonical');

        return Scoring::createMetric(
            'canonical', 'Canonical URL', $canonical !== null,
            $canonical ? 'Configurada' : 'No encontrada',
            $canonical ? 100 : 40,
            $canonical
                ? 'Se encontró la etiqueta canonical. Evita problemas de contenido duplicado.'
                : 'No se encontró canonical. Puede causar problemas de contenido duplicado en Google.',
            !$canonical ? 'Agregar <link rel="canonical"> apuntando a la URL preferida.' : '',
            'Configuramos canonicals correctos en todas las páginas.'
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
        $score = $wordCount >= 500 ? 100 : ($wordCount >= 300 ? 70 : 30);

        return Scoring::createMetric(
            'content_length', 'Cantidad de contenido', $wordCount,
            "$wordCount palabras",
            $score,
            $wordCount >= 500
                ? "La página tiene $wordCount palabras. Buen volumen de contenido."
                : "La página tiene solo $wordCount palabras. Google prefiere contenido más extenso.",
            $wordCount < 300 ? 'Agregar más contenido relevante y útil para los usuarios.' : '',
            'Ayudamos a planificar y optimizar el contenido de tu sitio.'
        );
    }
}
