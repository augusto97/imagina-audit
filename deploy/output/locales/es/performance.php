<?php
return [
    'summary' => 'Tu sitio tiene una puntuación de rendimiento de {{score}}/100.',

    // pagespeed_mobile / desktop
    'psi_mobile.name'          => 'PageSpeed Mobile',
    'psi_desktop.name'         => 'PageSpeed Desktop',
    'psi.display.score'        => '{{score}}/100',
    'psi.display.na'           => 'No disponible',
    'psi_mobile.desc.ok'       => 'Google PageSpeed califica tu sitio móvil con {{score}}/100.',
    'psi_mobile.desc.na'       => 'No fue posible obtener la puntuación de PageSpeed móvil.',
    'psi_mobile.recommend'     => 'Optimizar la velocidad de carga en dispositivos móviles.',
    'psi_mobile.solution'      => 'Optimizamos tu sitio para obtener scores de 90+ en PageSpeed.',
    'psi_desktop.desc.ok'      => 'Google PageSpeed califica tu sitio desktop con {{score}}/100.',
    'psi_desktop.desc.na'      => 'No fue posible obtener la puntuación de PageSpeed desktop.',
    'psi_desktop.recommend'    => 'Optimizar la velocidad de carga en escritorio.',
    'psi_desktop.solution'     => 'Configuramos cache, CDN y optimizaciones avanzadas de rendimiento.',

    // lcp
    'lcp.name'               => 'Largest Contentful Paint (LCP)',
    'lcp.display'            => '{{seconds}}s',
    'lcp.desc.prefix'        => 'El contenido principal tarda {{seconds}}s en cargar. ',
    'lcp.desc.good'          => 'Buen tiempo de carga.',
    'lcp.desc.bad'           => 'Se recomienda menos de 2.5 segundos.',
    'lcp.recommend'          => 'Optimizar imágenes, lazy loading y reducir recursos bloqueantes.',
    'lcp.solution'           => 'Reducimos el LCP con cache, CDN, optimización de imágenes y código.',

    // fcp
    'fcp.name'        => 'First Contentful Paint (FCP)',
    'fcp.display'     => '{{seconds}}s',
    'fcp.desc'        => 'El primer contenido visible aparece en {{seconds}}s.',
    'fcp.recommend'   => 'Reducir recursos bloqueantes y optimizar CSS crítico.',
    'fcp.solution'    => 'Implementamos CSS crítico inline y optimización de carga.',

    // cls
    'cls.name'        => 'Cumulative Layout Shift (CLS)',
    'cls.display'     => '{{value}}',
    'cls.desc.prefix' => 'El desplazamiento visual acumulado es {{value}}. ',
    'cls.desc.good'   => 'Buen valor.',
    'cls.desc.bad'    => 'Se recomienda menos de 0.1.',
    'cls.recommend'   => 'Definir dimensiones para imágenes y embeds. Evitar insertar contenido dinámico arriba.',
    'cls.solution'    => 'Eliminamos los shifts de layout para una experiencia visual estable.',

    // tbt
    'tbt.name'        => 'Total Blocking Time (TBT)',
    'tbt.display'     => '{{ms}}ms',
    'tbt.desc.prefix' => 'El tiempo de bloqueo total es {{ms}}ms. ',
    'tbt.desc.good'   => 'Buen valor.',
    'tbt.desc.bad'    => 'Se recomienda menos de 200ms.',
    'tbt.recommend'   => 'Reducir el JavaScript pesado y dividir tareas largas.',
    'tbt.solution'    => 'Optimizamos el JavaScript y eliminamos scripts innecesarios.',

    // opportunities
    'opp.name'             => 'Oportunidades de mejora',
    'opp.display'          => '{{count}} oportunidades{{savings}}',
    'opp.display.savings'  => ' · {{seconds}}s de ahorro potencial',
    'opp.desc.prefix'      => 'Google detectó {{count}} oportunidades de optimización: {{list}}',
    'opp.desc.suffix_more' => '... y {{count}} más.',
    'opp.desc.suffix_end'  => '.',
    'opp.recommend'        => 'Aplicar las optimizaciones sugeridas por PageSpeed para mejorar la velocidad de carga.',
    'opp.solution'         => 'Implementamos todas las optimizaciones recomendadas por Google PageSpeed.',

    // ttfb
    'ttfb.name'        => 'Tiempo de respuesta del servidor (TTFB)',
    'ttfb.display'     => '{{ms}}ms',
    'ttfb.desc.prefix' => 'El servidor responde en {{ms}}ms. ',
    'ttfb.desc.good'   => 'Buen tiempo.',
    'ttfb.desc.bad'    => 'Se recomienda menos de 500ms.',
    'ttfb.recommend'   => 'Mejorar el hosting, habilitar cache de servidor y optimizar consultas a base de datos.',
    'ttfb.solution'    => 'Recomendamos hosting optimizado y configuramos cache de servidor.',

    // compression
    'comp.name'          => 'Compresión de contenido',
    'comp.display.ok'    => '{{encoding}}',
    'comp.display.none'  => 'Sin compresión',
    'comp.desc.ok'       => 'El contenido se sirve con compresión {{encoding}}.',
    'comp.desc.none'     => 'El contenido no está comprimido. Esto aumenta el tiempo de descarga.',
    'comp.recommend'     => 'Habilitar compresión GZIP o Brotli en el servidor.',
    'comp.solution'      => 'Configuramos compresión Brotli/GZIP para reducir el tamaño de transferencia.',

    // cache_headers
    'cache.name'             => 'Cache del navegador',
    'cache.display.ok'       => '{{details}}',
    'cache.display.none'     => 'No configurado',
    'cache.desc.ok'          => 'Cache configurado: {{details}}. Los archivos se almacenan para cargas más rápidas.',
    'cache.desc.none'        => 'No se detectaron headers de cache ni plugin de cache activo. El navegador descarga todo cada vez.',
    'cache.detail.cc'        => 'Cache-Control: {{value}}',
    'cache.detail.etag'      => 'ETag presente',
    'cache.detail.expires'   => 'Expires: {{value}}',
    'cache.detail.plugin_h'  => 'Plugin de cache activo (headers de servidor)',
    'cache.detail.plugin_html' => 'Plugin de cache detectado en HTML',
    'cache.recommend'        => 'Instalar un plugin de cache (WP Rocket, LiteSpeed Cache) y configurar headers Cache-Control.',
    'cache.solution'         => 'Configuramos cache agresivo para archivos estáticos con expiración optimizada.',
];
