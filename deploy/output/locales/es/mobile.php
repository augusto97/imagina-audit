<?php
/**
 * Strings del MobileAnalyzer (español).
 */
return [
    // Módulo
    'summary' => 'Tu sitio tiene una puntuación móvil de {{score}}/100.',

    // viewport
    'viewport.name'             => 'Meta Viewport',
    'viewport.display.missing'  => 'No configurada',
    'viewport.desc.ok'          => 'Meta viewport configurada correctamente con width=device-width.',
    'viewport.desc.partial'     => 'Meta viewport presente pero sin width=device-width.',
    'viewport.desc.missing'     => 'No se encontró meta viewport. El sitio no se adaptará a pantallas móviles.',
    'viewport.recommendation'   => 'Agregar <meta name="viewport" content="width=device-width, initial-scale=1">.',
    'viewport.solution'         => 'Configuramos el viewport y la experiencia móvil completa.',

    // mobile_speed
    'mobile_speed.name'           => 'Velocidad en Móvil (PageSpeed)',
    'mobile_speed.display.none'   => 'No disponible',
    'mobile_speed.desc.ok'        => 'Google PageSpeed califica la velocidad móvil con {{score}}/100.',
    'mobile_speed.desc.missing'   => 'No fue posible obtener la puntuación de velocidad móvil.',
    'mobile_speed.recommendation' => 'Optimizar la velocidad móvil: reducir CSS/JS, optimizar imágenes, usar lazy loading.',
    'mobile_speed.solution'       => 'Optimizamos específicamente para velocidad en dispositivos móviles.',

    // responsive
    'responsive.name'              => 'Diseño Responsivo',
    'responsive.display.none'      => 'No se detectaron indicadores claros',
    'responsive.desc.found'        => 'Se detectaron indicadores de diseño responsivo: {{list}}.',
    'responsive.desc.missing'      => 'No se detectaron indicadores claros de diseño responsivo (sin viewport móvil, sin media queries accesibles y sin clases de framework responsivo).',
    'responsive.recommendation.partial' => 'No pudimos verificar media queries en los CSS externos. Asegúrate de que el CSS principal tenga breakpoints (@media (max-width: 768px)) para tablets y móviles.',
    'responsive.recommendation.missing' => 'Implementar diseño responsive: agregar <meta name="viewport" content="width=device-width, initial-scale=1"> y usar media queries en CSS.',
    'responsive.solution'          => 'Aseguramos que tu sitio sea 100% responsive en todos los dispositivos.',
    'responsive.indicator.viewport'  => 'Viewport móvil',
    'responsive.indicator.srcset'    => 'Imágenes responsivas',
    'responsive.indicator.media'     => 'Media queries',
];
