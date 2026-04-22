<?php
return [
    'summary' => 'La salud técnica de la página tiene una puntuación de {{score}}/100.',

    // status_code
    'status.name'    => 'Código de estado HTTP',
    'status.display' => '{{code}}',
    'status.desc.ok' => 'La página responde con código 200 (OK).',
    'status.desc.bad' => 'La página responde con código {{code}}. Se espera código 200 para una página saludable.',
    'status.recommend' => 'Verificar que la página principal devuelva código 200.',
    'status.solution'  => 'Verificamos que todas las páginas respondan correctamente.',

    // mixed_content
    'mixed.name'          => 'Contenido mixto HTTP/HTTPS',
    'mixed.display.na'    => 'N/A (sitio HTTP)',
    'mixed.display.ok'    => 'No detectado',
    'mixed.display.bad'   => '{{count}} recursos mixtos',
    'mixed.desc.na'       => 'El sitio no usa HTTPS, por lo que no aplica la verificación de contenido mixto.',
    'mixed.desc.ok'       => 'No se detectaron recursos cargados por HTTP en una página HTTPS. Correcto.',
    'mixed.desc.bad'      => 'Se detectaron {{count}} recursos cargados por HTTP inseguro dentro de una página HTTPS. Esto genera advertencias de seguridad en el navegador.',
    'mixed.recommend.na'  => 'Migrar el sitio a HTTPS.',
    'mixed.recommend.bad' => 'Cambiar todas las URLs de recursos de http:// a https:// o usar URLs relativas al protocolo.',
    'mixed.solution.na'   => 'Migramos tu sitio a HTTPS y corregimos contenido mixto.',
    'mixed.solution'      => 'Corregimos todos los problemas de contenido mixto.',

    // meta_refresh
    'mrefresh.name'        => 'Meta Refresh',
    'mrefresh.display.ok'  => 'No',
    'mrefresh.display.bad' => 'Detectado',
    'mrefresh.desc.ok'     => 'No se detectó meta refresh. Correcto.',
    'mrefresh.desc.bad'    => 'Se detectó <meta http-equiv="refresh">. Esto redirige la página automáticamente y es malo para SEO porque los buscadores no lo manejan bien.',
    'mrefresh.recommend'   => 'Reemplazar meta refresh con redirección 301 del servidor.',
    'mrefresh.solution'    => 'Configuramos redirecciones correctas desde el servidor.',

    // charset
    'charset.name'         => 'Codificación de caracteres',
    'charset.display.none' => 'No declarada',
    'charset.desc.utf8'    => 'Codificación UTF-8 declarada correctamente.',
    'charset.desc.other'   => 'Codificación declarada como "{{charset}}". Se recomienda UTF-8.',
    'charset.desc.none'    => 'No se declaró la codificación de caracteres. Puede causar problemas con acentos y caracteres especiales.',
    'charset.recommend'    => 'Agregar <meta charset="UTF-8"> al inicio del <head>.',
    'charset.solution'     => 'Verificamos la codificación de caracteres en todas las páginas.',

    // frames
    'frames.name'          => 'Frames e Iframes',
    'frames.display.frame' => 'Usa <frame> (obsoleto)',
    'frames.display.many'  => '{{count}} iframes',
    'frames.display.none'  => 'No usa frames',
    'frames.desc.frame'    => 'El sitio usa <frame>, que es una tecnología obsoleta no soportada por los buscadores.',
    'frames.desc.many'     => 'Se encontraron {{count}} iframes. Un exceso de iframes puede afectar el rendimiento.',
    'frames.desc.some'     => '{{count}} iframes detectados. Cantidad aceptable.',
    'frames.desc.none'     => 'No se detectaron frames. Correcto.',
    'frames.recommend'     => 'Eliminar el uso de <frame> y migrar a diseño moderno.',
    'frames.solution'      => 'Optimizamos la estructura de la página eliminando elementos obsoletos.',

    // duplicate_canonical
    'dupcan.name'           => 'Canonical duplicado',
    'dupcan.display.ok'     => 'Única',
    'dupcan.display.none'   => 'No encontrada',
    'dupcan.display.dup'    => '{{count}} canonicals',
    'dupcan.desc.ok'        => 'Se encontró exactamente una etiqueta canonical. Correcto.',
    'dupcan.desc.none'      => 'No se encontró etiqueta canonical.',
    'dupcan.desc.dup'       => 'Se encontraron {{count}} etiquetas canonical. Solo debe haber una. Los buscadores pueden confundirse con canonicals duplicados.',
    'dupcan.recommend'      => 'Eliminar los canonicals duplicados y dejar solo uno.',
    'dupcan.solution'       => 'Verificamos y corregimos las etiquetas canonical de todas las páginas.',

    // doctype
    'doctype.name'         => 'Declaración DOCTYPE',
    'doctype.display.ok'   => 'HTML5',
    'doctype.display.none' => 'No encontrada',
    'doctype.desc.ok'      => 'DOCTYPE HTML5 declarado correctamente.',
    'doctype.desc.none'    => 'No se encontró <!DOCTYPE html>. Sin DOCTYPE, los navegadores entran en "quirks mode" y renderizan de forma inconsistente.',
    'doctype.recommend'    => 'Agregar <!DOCTYPE html> como primera línea del documento.',
    'doctype.solution'     => 'Verificamos que todas las páginas tengan la declaración DOCTYPE correcta.',
];
