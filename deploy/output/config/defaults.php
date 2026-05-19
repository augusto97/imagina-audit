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

    // Umbrales de scoring — recalibrados v2.1 para reflejar la realidad de los
    // sitios analizados. Antes "good ≥ 70" hacía que sitios con problemas
    // reales cayeran en zona verde. Ahora "good ≥ 80" es un umbral exigente:
    // un sitio típico con 2-3 problemas concretos cae a "warning/deficient" y
    // el cliente percibe que sí hay algo que arreglar.
    'threshold_excellent' => 92,
    'threshold_good' => 80,
    'threshold_warning' => 60,
    'threshold_critical' => 40,

    // Pesos de módulos (deben sumar ~1.0 — el normalizer ajusta).
    // Recalibrado v2.1: seguridad + performance + WordPress pesan más porque
    // son lo que Imagina WP realmente resuelve. Conversion baja porque
    // "tener Analytics instalado" no debería compensar SSL roto.
    'weight_wordpress' => 0.15,
    'weight_security' => 0.25,
    'weight_performance' => 0.20,
    'weight_seo' => 0.12,
    'weight_mobile' => 0.06,
    'weight_infrastructure' => 0.08,
    'weight_conversion' => 0.04,
    'weight_page_health' => 0.10,
    'weight_wp_internal' => 0.10, // Solo aplica si el admin subió un wp-snapshot

    // Cap del score de un módulo cuando tiene métricas críticas. Sin esto, un
    // módulo con 1 crítica y 9 buenas saca ~85, lo que da una falsa sensación
    // de "casi perfecto". Con cap=50 el módulo no puede pintar verde si tiene
    // al menos una métrica crítica. Configurable por módulo desde admin.
    'scoring_critical_cap_enabled' => true,
    'scoring_critical_cap_per_module' => [
        'security'       => 50, // 1 crítico = serio
        'wordpress'      => 55,
        'performance'    => 65,
        'page_health'    => 65,
        'infrastructure' => 70,
        'seo'            => 75,
        'mobile'         => 70,
        'conversion'     => 80, // críticos en conversion son menos impactantes
        'wp_internal'    => 60,
    ],

    // Penalización exponencial al score GLOBAL según número de críticos
    // totales en el audit. Si hay muchos problemas reales el score baja
    // notoriamente — no se promedia hacia el centro.
    'scoring_critical_penalty_enabled' => true,
    'scoring_critical_penalties' => [
        // index = nro de críticos, value = puntos a restar.
        // 0 críticos => 0 (sin penalty), 1 => -3, 2 => -8, 3 => -15, 4+ => -25
        0, 3, 8, 15, 25,
    ],

    // Métricas / módulos excluidos del cálculo del score (siguen apareciendo
    // en el informe como "informativas"). Editable desde /admin/scoring.
    // Formato: array de strings.
    //   scoring_disabled_metrics  → ["security.powered_by_header", ...]
    //   scoring_disabled_modules  → ["backups", ...]
    'scoring_disabled_metrics' => [],
    'scoring_disabled_modules' => [],

    // Pesos por métrica DENTRO de su módulo (defaults). Editable desde
    // /admin/scoring para ajustar finamente. Las que no están aquí pesan 1.0.
    // Empuja a que las métricas críticas para el negocio (SSL, headers,
    // velocidad) pesen más que las cosméticas (X-Powered-By, etc.).
    'scoring_metric_weights' => [
        // Security
        'security.ssl_valid'         => 3.0,
        'security.security_headers'  => 2.5,
        'security.directory_listing' => 2.0,
        'security.login_protection'  => 1.8,
        'security.https_redirect'    => 2.0,
        'security.vulnerabilities'   => 3.0,
        'security.powered_by_header' => 0.5,
        // Performance
        'performance.lcp'            => 3.0,
        'performance.fcp'            => 2.0,
        'performance.cls'            => 2.5,
        'performance.tbt'            => 2.0,
        'performance.ttfb'           => 2.0,
        'performance.compression'    => 1.5,
        // WordPress
        'wordpress.wp_version'             => 2.5,
        'wordpress.plugins_outdated'       => 2.5,
        'wordpress.rest_api_exposed'       => 3.0,
        'wordpress.xmlrpc_active'          => 1.5,
        'wordpress.user_enumeration'       => 2.0,
        'wordpress.debug_mode'             => 3.0,
        'wordpress.sensitive_files'        => 3.0,
        // SEO
        'seo.title'                  => 2.0,
        'seo.meta_description'       => 1.8,
        'seo.h1'                     => 1.5,
        'seo.sitemap'                => 1.5,
        'seo.canonical'              => 1.2,
        'seo.favicon'                => 0.5,
        // Mobile
        'mobile.viewport'            => 2.5,
        // Infrastructure
        'infrastructure.https'       => 2.5,
        'infrastructure.ttfb'        => 2.0,
        'infrastructure.cdn'         => 1.0,
        // Conversion (todo cosmético, casi todo pesa 0.5-1)
        'conversion.analytics'       => 1.5,
        'conversion.contact_form'    => 1.0,
        'conversion.facebook_pixel'  => 0.3,
    ],

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

    // Textos editables del home público.
    // Sintaxis de resaltado (opcional):
    //   **palabra**  → color primario de la marca
    //   ==palabra==  → highlight amarillo
    'home_hero_headline' => 'Auditoría ==Gratuita== de tu **WordPress**',
    'home_hero_subheadline' => 'Descubre en 30 segundos qué tan seguro, rápido y optimizado está tu sitio web',
    'home_form_button_text' => 'Auditar Mi Sitio Gratis',
    'home_form_microcopy' => 'Sin instalar nada · 100% externo · Resultados en 30 seg',
    'home_features_title' => 'Analizamos ==8 áreas clave== de tu sitio',
    'home_trust_text' => 'Con la experiencia de ==15 años== de maestría exclusiva en WordPress',

    // Placeholders del formulario público
    'form_placeholder_url' => 'https://tusitio.com',
    'form_placeholder_name' => 'Tu nombre',
    'form_placeholder_email' => 'tu@email.com',
    'form_placeholder_whatsapp' => '+57...',

    // Header público
    'header_compare_text' => 'Comparar',
    'header_external_text' => 'imaginawp.com',
    'header_external_url' => 'https://imaginawp.com',

    // Footer público
    'footer_tagline' => 'Especialistas exclusivos en WordPress',
    'footer_experience_text' => '15 años de experiencia',
    'footer_privacy_url' => '',
    'footer_privacy_text' => 'Política de privacidad',
];
