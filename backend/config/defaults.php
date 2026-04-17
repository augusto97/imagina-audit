<?php
/**
 * Valores por defecto de configuración
 * Se sobreescriben desde la tabla settings de SQLite
 */

return [
    // Branding
    'company_name' => 'Imagina WP',
    'company_url' => 'https://imaginawp.com',
    'company_whatsapp' => '+573001234567',
    'company_email' => 'hola@imaginawp.com',
    'company_plans_url' => 'https://imaginawp.com/mensualidad',
    'logo_url' => '',

    // Umbrales de scoring
    'threshold_excellent' => 90,
    'threshold_good' => 70,
    'threshold_warning' => 50,
    'threshold_critical' => 30,

    // Pesos de módulos (deben sumar 1.0)
    'weight_wordpress' => 0.12,
    'weight_security' => 0.20,
    'weight_performance' => 0.18,
    'weight_seo' => 0.13,
    'weight_mobile' => 0.07,
    'weight_infrastructure' => 0.08,
    'weight_conversion' => 0.10,
    'weight_page_health' => 0.12,

    // Mensajes de venta por módulo
    'sales_wordpress' => 'Con nuestros planes de soporte, mantenemos tu WordPress actualizado y seguro. Actualizamos core, plugins y temas cada semana con testing previo para evitar problemas de compatibilidad.',
    'sales_security' => 'Implementamos un sistema de seguridad completo: firewall, protección anti-malware, headers de seguridad, protección de login con 2FA, y monitoreo 24/7 de vulnerabilidades.',
    'sales_performance' => 'Optimizamos tu sitio para cargar en menos de 3 segundos: configuramos cache avanzado, CDN, compresión de imágenes, lazy loading y optimización de base de datos.',
    'sales_seo' => 'Configuramos las bases técnicas del SEO: meta tags, schema markup, sitemap, robots.txt, Open Graph, y optimización de contenido para mejorar tu posicionamiento.',
    'sales_mobile' => 'Aseguramos que tu sitio se vea perfecto en móviles: responsive design, optimización de velocidad móvil y experiencia de usuario adaptada a pantallas táctiles.',
    'sales_infrastructure' => 'Recomendamos y migramos tu sitio al hosting más adecuado, configuramos CDN, HTTP/2, compresión y todas las optimizaciones de servidor necesarias.',
    'sales_conversion' => 'Instalamos y configuramos las herramientas esenciales: Google Analytics, chat en vivo, formularios optimizados, píxeles de tracking y cumplimiento legal (cookies, GDPR).',
    'sales_page_health' => 'Corregimos todos los errores técnicos de tu sitio: recursos rotos, contenido mixto, errores HTML, problemas de codificación y todo lo que afecta la salud técnica de tus páginas.',
    'sales_backups' => 'Configuramos backups automáticos diarios con retención de 30 días, almacenados fuera del servidor. Incluimos restauración gratuita en caso de emergencia.',

    // CTA
    'cta_title' => 'Todos estos problemas tienen solución',
    'cta_description' => 'En Imagina WP somos especialistas exclusivos en WordPress con más de 15 años de experiencia. Nuestros planes de soporte mensual incluyen todo lo que tu sitio necesita para estar seguro, rápido y optimizado.',
    'cta_button_whatsapp_text' => 'Hablar con un Experto por WhatsApp',
    'cta_button_plans_text' => 'Ver Planes y Precios',

    // Planes
    'plans' => [
        ['name' => 'Basic', 'price' => '97', 'currency' => 'USD'],
        ['name' => 'Pro', 'price' => '197', 'currency' => 'USD'],
        ['name' => 'Custom', 'price' => 'Cotizar', 'currency' => 'USD'],
    ],

    // Versión más reciente conocida de WordPress
    'latest_wp_version' => '6.7.2',
];
