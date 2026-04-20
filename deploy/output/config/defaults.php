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
    'weight_wp_internal' => 0.10, // Solo aplica si el admin subió un wp-snapshot

    // Mensajes de venta por módulo
    'sales_wordpress' => 'Con nuestros planes de soporte, mantenemos tu WordPress actualizado y seguro. Actualizamos core, plugins y temas cada semana con testing previo para evitar problemas de compatibilidad.',
    'sales_security' => 'Implementamos un sistema de seguridad completo: firewall, protección anti-malware, headers de seguridad, protección de login con 2FA, y monitoreo 24/7 de vulnerabilidades.',
    'sales_performance' => 'Optimizamos tu sitio para cargar en menos de 3 segundos: configuramos cache avanzado, CDN, compresión de imágenes, lazy loading y optimización de base de datos.',
    'sales_seo' => 'Configuramos las bases técnicas del SEO: meta tags, schema markup, sitemap, robots.txt, Open Graph, y optimización de contenido para mejorar tu posicionamiento.',
    'sales_mobile' => 'Aseguramos que tu sitio se vea perfecto en móviles: responsive design, optimización de velocidad móvil y experiencia de usuario adaptada a pantallas táctiles.',
    'sales_infrastructure' => 'Recomendamos y migramos tu sitio al hosting más adecuado, configuramos CDN, HTTP/2, compresión y todas las optimizaciones de servidor necesarias.',
    'sales_conversion' => 'Instalamos y configuramos las herramientas esenciales: Google Analytics, chat en vivo, formularios optimizados, píxeles de tracking y cumplimiento legal (cookies, GDPR).',
    'sales_page_health' => 'Corregimos todos los errores técnicos de tu sitio: recursos rotos, contenido mixto, errores HTML, problemas de codificación y todo lo que afecta la salud técnica de tus páginas.',
    'sales_wp_internal' => 'Analizamos el estado interno de tu WordPress y lo optimizamos a nivel de plugins, base de datos, configuración y rendimiento.',
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

    // Cola de auditorías
    'audit_max_concurrent' => 3,           // Audits que pueden correr en paralelo
    'audit_stale_seconds' => 300,          // Tras esto, un job 'running' se considera huérfano (5 min de margen)
    'audit_failure_cache_minutes' => 10,   // Si una URL falló en los últimos N min, devolvemos el mismo error sin reprocesar
    'audit_max_attempts' => 3,             // Tras N intentos fallidos, se marca como permanently_failed
    'audit_jobs_retention_days' => 7,      // Cuánto retener jobs completed/failed antes de borrar

    // Retención de informes de auditoría (resultados guardados en `audits`)
    'audits_retention_enabled' => false,   // Master switch del borrado automático
    'audits_retention_months' => 6,        // Informes > N meses se borran (excepto los pinned)

    // Branding — color principal y assets subibles
    'brand_primary_color' => '#3B82F6',
    'logo_url' => '',                      // Imagen del logo (subida por el admin)
    'logo_collapsed_url' => '',            // Logo reducido / marca para sidebar colapsado
    'favicon_url' => '',                   // Favicon público

    // SEO del home público
    'home_seo_title' => 'Auditoría WordPress gratuita · Imagina Audit',
    'home_seo_description' => 'Analiza tu sitio WordPress en 30 segundos. Seguridad, rendimiento, SEO y más. Recibe un informe gratuito con recomendaciones.',
    'home_seo_og_image' => '',

    // Textos editables del home público
    'home_hero_headline' => 'Auditoría Gratuita de tu WordPress',
    'home_hero_subheadline' => 'Descubre en 30 segundos qué tan seguro, rápido y optimizado está tu sitio web',
    'home_form_button_text' => 'Auditar Mi Sitio Gratis',
    'home_form_microcopy' => 'Sin instalar nada · 100% externo · Resultados en 30 seg',
    'home_features_title' => 'Analizamos 8 áreas clave de tu sitio',
    'home_trust_text' => 'Con la experiencia de 15 años de maestría exclusiva en WordPress',
];
