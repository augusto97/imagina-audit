<?php
/**
 * Strings del analyzer SEO (español). Se añaden incrementalmente por
 * sub-checker para mantener cada commit manejable.
 */
return [
    'summary' => 'Tu sitio tiene una puntuación SEO de {{score}}/100.',

    // sitemap
    'sitemap.name'             => 'Sitemap XML',
    'sitemap.display.none'     => 'No encontrado',
    'sitemap.display.found'    => '{{path}}{{suffix}}',
    'sitemap.display.count_urls'     => ' ({{count}} URLs)',
    'sitemap.display.count_sitemaps' => ' ({{count}} sitemaps)',
    'sitemap.desc.none'        => 'No se encontró sitemap XML en /sitemap.xml, /sitemap_index.xml ni referenciado en robots.txt. Sin sitemap, Google descubre páginas solo mediante enlaces internos, lo que puede dejar páginas sin indexar.',
    'sitemap.desc.found_index' => 'Sitemap encontrado en {{path}}. Es un sitemap index{{suffix}}. Estructura profesional.',
    'sitemap.desc.found_index_sub'  => ' con {{count}} sub-sitemaps',
    'sitemap.desc.found_urls'  => 'Sitemap encontrado en {{path}}. Contiene {{count}} URLs indexadas.',
    'sitemap.desc.found_ok'    => 'Sitemap encontrado en {{path}}. Accesible correctamente.',
    'sitemap.recommend'        => 'Generar un sitemap XML con un plugin SEO (Yoast, Rank Math) y registrarlo en Google Search Console.',
    'sitemap.solution'         => 'Generamos sitemaps automáticos optimizados y los registramos en Google Search Console.',

    // robots
    'robots.name'             => 'Robots.txt',
    'robots.display.none'     => 'No encontrado',
    'robots.display.blocks'   => 'BLOQUEA TODO EL SITIO',
    'robots.display.found'    => '{{lines}} directivas · {{disallow}} Disallow{{sitemapSuffix}}',
    'robots.display.sitemap_suffix' => ' · Sitemap',
    'robots.desc.none'        => 'No se encontró archivo robots.txt. Aunque no es obligatorio, es una buena práctica tenerlo para indicar a los buscadores qué secciones no deben rastrear y dónde está el sitemap.',
    'robots.desc.blocks'      => 'El robots.txt contiene "Disallow: /" que bloquea TODO el sitio para los buscadores. Google NO puede indexar ninguna página. Esto es un problema crítico a menos que sea intencional (sitio en desarrollo).',
    'robots.desc.prefix'      => 'Robots.txt presente con {{lines}} directivas activas y {{disallow}} reglas Disallow. ',
    'robots.desc.with_sitemap' => 'Incluye referencia al sitemap. ',
    'robots.desc.ok'          => 'Configuración correcta.',
    'robots.desc.notes'       => '{{notes}}',
    'robots.note.no_sitemap'  => 'No referencia al sitemap (agregar "Sitemap: URL").',
    'robots.note.crawl_delay' => 'Usa Crawl-delay (Google lo ignora pero otros buscadores podrían rastrear más lento).',
    'robots.recommend.none'     => 'Crear un archivo robots.txt con directivas adecuadas y referencia al sitemap.',
    'robots.recommend.blocks'   => 'Cambiar "Disallow: /" por directivas específicas que solo bloqueen las secciones privadas.',
    'robots.recommend.sitemap'  => 'Agregar la directiva "Sitemap: https://tusitio.com/sitemap.xml" al robots.txt.',
    'robots.solution.none'    => 'Configuramos robots.txt optimizado para el SEO de tu sitio.',
    'robots.solution.blocks'  => 'Configuramos robots.txt optimizado que protege áreas privadas sin bloquear el contenido público.',

    // canonical
    'canonical.name'            => 'Canonical URL',
    'canonical.display.none'    => 'No encontrada',
    'canonical.display.self'    => 'Autoreferencial',
    'canonical.display.diff'    => '{{canonical}}',
    'canonical.desc.none'       => 'No se encontró la etiqueta <link rel="canonical">. Sin canonical, si tu página es accesible por múltiples URLs (con/sin www, con/sin trailing slash, con parámetros), Google podría indexar versiones duplicadas y dividir la autoridad SEO.',
    'canonical.desc.self'       => 'Canonical configurada: {{canonical}}. Apunta a sí misma (autoreferencial). Correcto.',
    'canonical.desc.diff'       => 'Canonical configurada: {{canonical}}. Apunta a una URL diferente a la actual. Verificar que esto sea intencional.',
    'canonical.recommend.none'  => 'Agregar <link rel="canonical" href="URL-preferida"> en cada página.',
    'canonical.recommend.diff'  => 'Verificar que el canonical apunte a la URL correcta. Un canonical diferente indica que esta página es una variante.',
    'canonical.solution'        => 'Configuramos canonicals correctos en todas las páginas para evitar contenido duplicado.',

    // hreflang
    'hreflang.name'             => 'Hreflang (multi-idioma)',
    'hreflang.display.none'     => 'No configurado',
    'hreflang.display.found'    => '{{count}} idiomas: {{list}}',
    'hreflang.desc.none'        => 'No se encontraron etiquetas hreflang. Si tu sitio solo tiene un idioma, esto es normal. Si tienes versiones en otros idiomas, necesitas hreflang para evitar que Google las trate como contenido duplicado.',
    'hreflang.desc.found'       => '{{count}} idiomas/regiones configurados: {{list}}. {{xDefaultNote}}',
    'hreflang.desc.with_xdefault'    => 'Incluye x-default (página por defecto). Configuración correcta.',
    'hreflang.desc.without_xdefault' => 'Falta x-default (recomendado para indicar la versión por defecto).',
    'hreflang.recommend'        => 'Agregar hreflang="x-default" apuntando a la versión principal del sitio.',
    'hreflang.solution'         => 'Configuramos hreflang para sitios multi-idioma y multi-región.',

    // url_structure
    'url.name'            => 'Estructura de URL',
    'url.display'         => '{{scheme}} · {{www}} · {{length}} car.',
    'url.display.https'   => 'HTTPS',
    'url.display.http'    => 'HTTP',
    'url.display.www'     => 'www',
    'url.display.no_www'  => 'sin www',
    'url.desc.prefix'     => 'URL: {{url}}. ',
    'url.desc.https'      => 'HTTPS activo. ',
    'url.desc.http'       => 'HTTP sin cifrar. ',
    'url.desc.with_www'   => 'Usa www. ',
    'url.desc.no_www'     => 'Sin www. ',
    'url.desc.clean'      => 'Estructura de URL limpia y amigable para SEO.',
    'url.issue.no_https'  => 'No usa HTTPS. Google da prioridad a sitios seguros.',
    'url.issue.params'    => 'URL con {{count}} parámetros. Las URLs limpias son mejores para SEO.',
    'url.issue.long'      => 'URL larga ({{length}} caracteres). Se recomiendan URLs cortas y descriptivas.',
    'url.solution'        => 'Optimizamos las URLs para que sean cortas, descriptivas y amigables para SEO.',
];
