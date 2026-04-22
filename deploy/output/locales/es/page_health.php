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

    // html_errors
    'htmlerr.name'         => 'Errores y alertas HTML',
    'htmlerr.display.ok'   => 'Sin errores detectados',
    'htmlerr.display.bad'  => '{{count}} problemas',
    'htmlerr.desc.ok'      => 'No se detectaron errores HTML importantes.',
    'htmlerr.desc.bad'     => 'Se detectaron problemas en el HTML: {{list}}.',
    'htmlerr.err.unclosed'   => 'Tag <{{tag}}> sin cerrar',
    'htmlerr.err.deprecated' => 'Tag obsoleto: {{tag}}',
    'htmlerr.err.inline_styles' => '{{count}} estilos inline (excesivo)',
    'htmlerr.recommend'    => 'Corregir los errores HTML detectados para mejorar la compatibilidad con los navegadores.',
    'htmlerr.solution'     => 'Corregimos los errores HTML y optimizamos la estructura del código.',

    // link_stats
    'links.name'           => 'Estadísticas de enlaces',
    'links.display'        => '{{total}} enlaces ({{internal}} int. · {{external}} ext. · {{extDofollow}} ext. dofollow)',
    'links.desc'           => 'La página tiene {{total}} enlaces: {{internal}} internos y {{external}} externos. {{dofollow}} dofollow y {{nofollow}} nofollow. {{extDofollow}} enlaces externos dofollow.',
    'links.recommend'      => 'Reducir el número de enlaces a menos de 200 para no diluir el PageRank.',
    'links.solution'       => 'Optimizamos la estructura de enlaces internos para mejorar el SEO.',

    // broken_resources
    'broken.name'          => 'Recursos rotos',
    'broken.display.ok'    => 'Ninguno detectado',
    'broken.display.bad'   => '{{count}} recursos rotos',
    'broken.desc.ok'       => 'Se verificaron {{checked}} recursos y no se encontraron rotos.',
    'broken.desc.bad'      => 'Se encontraron {{count}} recursos rotos de {{checked}} verificados: {{list}}.',
    'broken.recommend'     => 'Corregir o eliminar los recursos rotos (imágenes o scripts que devuelven error 404).',
    'broken.solution'      => 'Identificamos y corregimos todos los recursos rotos del sitio.',

    // text_code_ratio
    'ratio.name'             => 'Ratio Texto/Código',
    'ratio.display.none'     => 'Sin datos',
    'ratio.display'          => '{{ratio}}%',
    'ratio.desc.none'        => 'No se pudo calcular el ratio texto/código.',
    'ratio.desc.good'        => 'El ratio texto/código es {{ratio}}%. Buen equilibrio entre contenido visible y código HTML.',
    'ratio.desc.low_prefix'  => 'El ratio texto/código es {{ratio}}%. ',
    'ratio.desc.very_low'    => 'Muy bajo — los buscadores podrían considerar que esta página tiene poco contenido relevante.',
    'ratio.desc.below_rec'   => 'Se recomienda al menos 15% para que los buscadores valoren el contenido.',
    'ratio.recommend'        => 'Reducir el código innecesario (CSS/JS inline, HTML redundante) y agregar más contenido de texto visible.',
    'ratio.solution'         => 'Optimizamos el código eliminando bloat y mejorando la proporción de contenido útil.',

    // custom_404
    'n404.name'               => 'Página 404 personalizada',
    'n404.display.ok'         => 'Configurada (HTTP 404)',
    'n404.display.soft'       => 'Devuelve 200 en vez de 404',
    'n404.display.other'      => 'Devuelve {{code}}',
    'n404.desc.ok'            => 'El servidor devuelve código 404 para páginas inexistentes. Correcto.',
    'n404.desc.soft'          => 'El servidor devuelve código 200 para URLs inexistentes en vez de 404. Esto causa "soft 404" que confunde a Google y desperdicia crawl budget.',
    'n404.desc.other'         => 'El servidor devuelve código {{code}} para páginas inexistentes.',
    'n404.recommend'          => 'Configurar el servidor para devolver código HTTP 404 en páginas inexistentes y mostrar una página útil con enlaces.',
    'n404.solution'           => 'Configuramos páginas 404 personalizadas que ayudan a los usuarios a navegar.',

    // url_resolution
    'urlres.name'          => 'Resolución de URL (www/https)',
    'urlres.display.ok'    => 'Todas redirigen correctamente',
    'urlres.display.bad'   => 'Inconsistencias detectadas',
    'urlres.desc.ok'       => 'Todas las variantes del dominio (http/https, www/sin-www) redirigen correctamente a la URL principal.',
    'urlres.desc.bad'      => 'No todas las variantes del dominio redirigen al mismo destino. Esto puede causar contenido duplicado.',
    'urlres.recommend'     => 'Configurar redirecciones 301 para que http, https, www y sin-www apunten a la misma URL.',
    'urlres.solution'      => 'Configuramos las redirecciones correctas para evitar contenido duplicado.',
];
