<?php
return [
    'summary' => 'Tu infraestructura tiene una puntuación de {{score}}/100.',

    // server
    'server.name'          => 'Servidor Web',
    'server.display.unknown' => 'Desconocido',
    'server.display.none'  => 'No detectado',
    'server.desc'          => 'Servidor web detectado: {{name}}.',
    'server.solution'      => 'Recomendamos LiteSpeed o Nginx para máximo rendimiento con WordPress.',

    // http_protocol
    'proto.name'         => 'Protocolo HTTP',
    'proto.display'      => 'HTTP/{{version}}',
    'proto.desc.modern'  => 'El sitio usa HTTP/{{version}}. Protocolo moderno con multiplexación y mejor rendimiento.',
    'proto.desc.old'     => 'El sitio usa HTTP/{{version}}. HTTP/2 ofrece mejor rendimiento con carga en paralelo.',
    'proto.recommend'    => 'Habilitar HTTP/2 en el servidor para mejorar la velocidad de carga.',
    'proto.solution'     => 'Configuramos HTTP/2 o HTTP/3 para máximo rendimiento.',

    // ttfb
    'ttfb.name'          => 'Tiempo de Respuesta (TTFB)',
    'ttfb.display'       => '{{ms}}ms',
    'ttfb.desc.prefix'   => 'El servidor responde en {{ms}}ms. ',
    'ttfb.desc.good'     => 'Buen tiempo de respuesta.',
    'ttfb.desc.bad'      => 'Se recomienda menos de 500ms.',
    'ttfb.recommend'     => 'Considerar un hosting más rápido o configurar cache de servidor.',
    'ttfb.solution'      => 'Migramos tu sitio a hosting optimizado con cache de servidor avanzado.',

    // cdn
    'cdn.name'           => 'CDN (Red de distribución)',
    'cdn.display.ok'     => '{{name}}',
    'cdn.display.none'   => 'No detectado',
    'cdn.detected.cache' => 'CDN (cache activo)',
    'cdn.detected.generic' => 'CDN detectado',
    'cdn.desc.ok'        => 'Se detectó CDN: {{name}}. El contenido se sirve desde servidores cercanos al usuario.',
    'cdn.desc.none'      => 'No se detectó un CDN. El contenido se sirve desde un solo servidor.',
    'cdn.recommend'      => 'Implementar un CDN como Cloudflare para mejorar velocidad y disponibilidad.',
    'cdn.solution'       => 'Configuramos Cloudflare CDN para que tu sitio cargue rápido en todo el mundo.',

    // compression
    'comp.name'          => 'Compresión del Servidor',
    'comp.display.ok'    => '{{encoding}}',
    'comp.display.none'  => 'Sin compresión',
    'comp.desc.ok'       => 'El servidor usa compresión {{encoding}}. Reduce el tamaño de transferencia.',
    'comp.desc.none'     => 'No se detectó compresión GZIP o Brotli. Los archivos se transfieren sin comprimir.',
    'comp.recommend'     => 'Habilitar compresión GZIP o Brotli en la configuración del servidor.',
    'comp.solution'      => 'Configuramos compresión Brotli/GZIP para máxima eficiencia.',

    // php_exposed
    'php.name'           => 'Versión PHP Expuesta',
    'php.display.ok'     => 'Oculta',
    'php.display.exposed' => '{{value}}',
    'php.desc.ok'        => 'La versión de PHP está oculta. Buena práctica de seguridad.',
    'php.desc.exposed'   => 'La versión de PHP está expuesta: {{value}}. Facilita que atacantes conozcan vulnerabilidades.',
    'php.recommend'      => 'Ocultar el header X-Powered-By en la configuración de PHP.',
    'php.solution'       => 'Ocultamos toda información del servidor que pueda ser usada por atacantes.',

    // hosting
    'host.name'          => 'Hosting / IP',
    'host.display'       => '{{provider}} ({{ip}})',
    'host.provider.unknown' => 'No identificado',
    'host.ip.unresolved' => 'No resuelto',
    'host.desc'          => 'IP del servidor: {{ip}}. Proveedor detectado: {{provider}}.',
    'host.solution'      => 'Evaluamos tu hosting y recomendamos la mejor opción para WordPress.',
];
