<?php
/**
 * GET /api/config — Configuración pública (branding, textos, planes)
 * Este endpoint NO requiere autenticación
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$defaults = require dirname(__DIR__) . '/config/defaults.php';

// Intentar cargar configuraciones desde la BD
$settings = [];
try {
    $db = Database::getInstance();
    $rows = $db->query("SELECT key, value FROM settings");
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Throwable $e) {
    // Usar valores por defecto si no hay BD
}

// Construir respuesta con valores de BD o defaults
$config = [
    'companyName' => $settings['company_name'] ?? $defaults['company_name'],
    'companyUrl' => $settings['company_url'] ?? $defaults['company_url'],
    'companyWhatsapp' => $settings['company_whatsapp'] ?? $defaults['company_whatsapp'],
    'companyEmail' => $settings['company_email'] ?? $defaults['company_email'],
    'companyPlansUrl' => $settings['company_plans_url'] ?? $defaults['company_plans_url'],
    'logoUrl' => $settings['logo_url'] ?? $defaults['logo_url'],
    'logoCollapsedUrl' => $settings['logo_collapsed_url'] ?? $defaults['logo_collapsed_url'],
    'faviconUrl' => $settings['favicon_url'] ?? $defaults['favicon_url'],
    'brandPrimaryColor' => $settings['brand_primary_color'] ?? $defaults['brand_primary_color'],
    'ctaTitle' => $settings['cta_title'] ?? $defaults['cta_title'],
    'ctaDescription' => $settings['cta_description'] ?? $defaults['cta_description'],
    'ctaButtonWhatsappText' => $settings['cta_button_whatsapp_text'] ?? $defaults['cta_button_whatsapp_text'],
    'ctaButtonPlansText' => $settings['cta_button_plans_text'] ?? $defaults['cta_button_plans_text'],
    'plans' => isset($settings['plans']) ? json_decode($settings['plans'], true) : $defaults['plans'],
    'salesMessages' => [
        'wordpress' => $settings['sales_wordpress'] ?? $defaults['sales_wordpress'],
        'security' => $settings['sales_security'] ?? $defaults['sales_security'],
        'performance' => $settings['sales_performance'] ?? $defaults['sales_performance'],
        'seo' => $settings['sales_seo'] ?? $defaults['sales_seo'],
        'mobile' => $settings['sales_mobile'] ?? $defaults['sales_mobile'],
        'infrastructure' => $settings['sales_infrastructure'] ?? $defaults['sales_infrastructure'],
        'conversion' => $settings['sales_conversion'] ?? $defaults['sales_conversion'],
        'backups' => $settings['sales_backups'] ?? $defaults['sales_backups'],
    ],
    'home' => [
        'seoTitle'        => $settings['home_seo_title']        ?? $defaults['home_seo_title'],
        'seoDescription'  => $settings['home_seo_description']  ?? $defaults['home_seo_description'],
        'seoOgImage'      => $settings['home_seo_og_image']     ?? $defaults['home_seo_og_image'],
        'heroHeadline'    => $settings['home_hero_headline']    ?? $defaults['home_hero_headline'],
        'heroSubheadline' => $settings['home_hero_subheadline'] ?? $defaults['home_hero_subheadline'],
        'formButtonText'  => $settings['home_form_button_text'] ?? $defaults['home_form_button_text'],
        'formMicrocopy'   => $settings['home_form_microcopy']   ?? $defaults['home_form_microcopy'],
        'featuresTitle'   => $settings['home_features_title']   ?? $defaults['home_features_title'],
        'trustText'       => $settings['home_trust_text']       ?? $defaults['home_trust_text'],
    ],
];

Response::success($config);
