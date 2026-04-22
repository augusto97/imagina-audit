<?php
return [
    'summary' => 'Tu sitio tiene una puntuación de conversión de {{score}}/100.',

    // analytics
    'analytics.name'          => 'Google Analytics',
    'analytics.display.ok'    => 'Instalado ({{type}})',
    'analytics.display.none'  => 'No detectado',
    'analytics.type.ga4'      => 'GA4',
    'analytics.type.ua'       => 'Universal Analytics',
    'analytics.type.generic'  => 'Google Analytics',
    'analytics.desc.ok'       => 'Google Analytics está instalado ({{type}}). Puedes medir el tráfico y comportamiento.',
    'analytics.desc.none'     => 'No se detectó Google Analytics. No puedes medir el rendimiento de tu sitio.',
    'analytics.recommend'     => 'Instalar Google Analytics 4 para medir tráfico y conversiones.',
    'analytics.solution'      => 'Instalamos y configuramos Google Analytics 4 con seguimiento de conversiones.',

    // tag_manager
    'gtm.name'        => 'Google Tag Manager',
    'gtm.display.ok'  => 'Instalado',
    'gtm.display.none' => 'No detectado',
    'gtm.desc.ok'     => 'Google Tag Manager está instalado. Facilita la gestión de scripts de marketing.',
    'gtm.desc.none'   => 'No se detectó Google Tag Manager.',
    'gtm.recommend'   => 'Considerar implementar GTM para gestionar tags de marketing sin modificar código.',
    'gtm.solution'    => 'Configuramos GTM para gestionar todos los scripts de marketing de forma centralizada.',

    // chat
    'chat.name'        => 'Chat en vivo / WhatsApp',
    'chat.display.ok'  => '{{list}}',
    'chat.display.none' => 'No detectado',
    'chat.desc.ok'     => 'Chat o contacto rápido detectado: {{list}}.',
    'chat.desc.none'   => 'No se detectó chat en vivo ni botón de WhatsApp. Pierdes conversiones.',
    'chat.recommend'   => 'Agregar un chat en vivo o botón de WhatsApp para captar leads.',
    'chat.solution'    => 'Instalamos chat de WhatsApp y herramientas de atención instantánea.',

    // forms
    'forms.name'        => 'Formularios de contacto',
    'forms.display.ok_named'  => '{{list}}',
    'forms.display.ok_generic' => '{{count}} formularios',
    'forms.display.none' => 'No detectados',
    'forms.desc.ok_named'    => 'Se detectaron formularios de contacto: {{list}}.',
    'forms.desc.ok_generic'  => 'Se detectaron formularios de contacto.',
    'forms.desc.none'   => 'No se detectaron formularios de contacto. Los visitantes no pueden contactarte fácilmente.',
    'forms.recommend'   => 'Agregar un formulario de contacto visible y accesible.',
    'forms.solution'    => 'Configuramos formularios optimizados para captar leads.',

    // social_media
    'social.name'        => 'Redes Sociales',
    'social.display.ok'  => '{{list}}',
    'social.display.none' => 'No detectadas',
    'social.desc.ok'     => 'Se detectaron enlaces a {{count}} redes sociales: {{list}}.',
    'social.desc.none'   => 'No se detectaron enlaces a redes sociales.',
    'social.recommend'   => 'Agregar enlaces a las redes sociales de la empresa.',
    'social.solution'    => 'Integramos las redes sociales y configuramos sharing buttons.',

    // cookies_legal
    'cookies.name'             => 'Cookies y Cumplimiento Legal',
    'cookies.display.tool'     => '{{name}} detectado',
    'cookies.display.legal'    => 'Páginas legales encontradas',
    'cookies.display.none'     => 'No detectado',
    'cookies.desc.ok_prefix'   => 'Se detectó cumplimiento de cookies/legal.',
    'cookies.desc.ok_tool'     => ' Herramienta: {{name}}.',
    'cookies.desc.none'        => 'No se detectó aviso de cookies ni páginas legales. Posible incumplimiento de GDPR.',
    'cookies.recommend'        => 'Implementar un aviso de cookies y crear páginas de política de privacidad.',
    'cookies.solution'         => 'Implementamos aviso de cookies y creamos las páginas legales necesarias.',

    // facebook_pixel
    'fb.name'        => 'Facebook Pixel',
    'fb.display.ok'  => 'Instalado',
    'fb.display.none' => 'No detectado',
    'fb.desc.ok'     => 'Facebook Pixel está instalado. Puedes crear audiencias y medir campañas.',
    'fb.desc.none'   => 'No se detectó Facebook Pixel. No puedes hacer remarketing en Facebook/Instagram.',
    'fb.recommend'   => 'Instalar Facebook Pixel si haces publicidad en Facebook o Instagram.',
    'fb.solution'    => 'Configuramos Facebook Pixel con eventos de conversión personalizados.',

    // push_notifications
    'push.name'              => 'Notificaciones Push',
    'push.display.ok'        => '{{name}} detectado',
    'push.display.sw_only'   => 'Service Worker sin push',
    'push.display.none'      => 'No detectadas',
    'push.desc.ok'           => 'Notificaciones push configuradas con {{name}}. Permite re-enganchar visitantes que abandonan el sitio.',
    'push.desc.none_sw'      => 'No se detectaron notificaciones push. Se detectó un Service Worker que podría soportar push.',
    'push.desc.none'         => 'No se detectaron notificaciones push. Las push notifications permiten recuperar hasta un 10% de visitantes perdidos.',
    'push.recommend'         => 'Considerar implementar notificaciones push con OneSignal o similar para re-enganchar visitantes.',
    'push.solution'          => 'Implementamos notificaciones push para recuperar visitantes y aumentar conversiones.',

    // email_marketing
    'email.name'           => 'Email Marketing',
    'email.display.named'  => '{{list}}',
    'email.display.generic' => 'Formulario de suscripción',
    'email.display.none'   => 'No detectado',
    'email.desc.ok_prefix' => 'Se detectó integración de email marketing',
    'email.desc.ok_named'  => ': {{list}}',
    'email.desc.ok_suffix' => '. El email marketing tiene el mejor ROI de todos los canales digitales.',
    'email.desc.none'      => 'No se detectó herramienta de email marketing ni formulario de suscripción. El email marketing genera un ROI promedio de $42 por cada $1 invertido.',
    'email.recommend'      => 'Implementar un formulario de suscripción con Mailchimp, Brevo u otra herramienta de email marketing.',
    'email.solution'       => 'Integramos herramientas de email marketing con formularios de captura optimizados.',

    // google_ads
    'ads.name'        => 'Google Ads',
    'ads.display.ok'  => 'Detectado',
    'ads.display.none' => 'No detectado',
    'ads.desc.ok'     => 'Se detectó integración con Google Ads. Permite medir conversiones de campañas de publicidad.',
    'ads.desc.none'   => 'No se detectó Google Ads. Si realizas campañas de publicidad en Google, necesitas el tag de conversión.',
    'ads.recommend'   => 'Si haces publicidad en Google, instalar el tag de Google Ads para medir conversiones.',
    'ads.solution'    => 'Configuramos Google Ads con seguimiento de conversiones y remarketing.',
];
