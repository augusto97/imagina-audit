<?php
/**
 * GET /api/config — Configuración pública (branding, textos, planes)
 * Este endpoint NO requiere autenticación.
 *
 * Soporta ?lang=xx — si el admin NO editó un texto, se devuelve la versión
 * localizada del bundle PHP (backend/locales/{lang}/modules.php). Si el
 * admin editó el texto desde el panel, su override siempre gana (la DB es
 * la fuente de verdad para cualquier string manualmente personalizado).
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

Translator::setLang($_GET['lang'] ?? null);

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

/**
 * Devuelve el valor de la DB si existe y no está vacío; si no, el fallback
 * localizado por `$localeKey`; si tampoco, el default crudo.
 */
$i18n = function (string $dbKey, string $localeKey, string $fallback) use ($settings) {
    if (isset($settings[$dbKey]) && $settings[$dbKey] !== '') {
        return $settings[$dbKey];
    }
    return Translator::has($localeKey) ? Translator::t($localeKey) : $fallback;
};

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
    'ctaTitle' => $i18n('cta_title', 'modules.cta.title', $defaults['cta_title']),
    'ctaDescription' => $i18n('cta_description', 'modules.cta.description', $defaults['cta_description']),
    'ctaButtonWhatsappText' => $i18n('cta_button_whatsapp_text', 'modules.cta.button_whatsapp', $defaults['cta_button_whatsapp_text']),
    'ctaButtonPlansText' => $i18n('cta_button_plans_text', 'modules.cta.button_plans', $defaults['cta_button_plans_text']),
    'plans' => isset($settings['plans']) ? json_decode($settings['plans'], true) : $defaults['plans'],
    'salesMessages' => [
        'wordpress'      => $i18n('sales_wordpress',      'modules.sales.wordpress',      $defaults['sales_wordpress']),
        'security'       => $i18n('sales_security',       'modules.sales.security',       $defaults['sales_security']),
        'performance'    => $i18n('sales_performance',    'modules.sales.performance',    $defaults['sales_performance']),
        'seo'            => $i18n('sales_seo',            'modules.sales.seo',            $defaults['sales_seo']),
        'mobile'         => $i18n('sales_mobile',         'modules.sales.mobile',         $defaults['sales_mobile']),
        'infrastructure' => $i18n('sales_infrastructure', 'modules.sales.infrastructure', $defaults['sales_infrastructure']),
        'conversion'     => $i18n('sales_conversion',     'modules.sales.conversion',     $defaults['sales_conversion']),
        'backups'        => $i18n('sales_backups',        'modules.sales.backups',        $defaults['sales_backups']),
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
    'form' => [
        'placeholderUrl'      => $settings['form_placeholder_url']      ?? $defaults['form_placeholder_url'],
        'placeholderName'     => $settings['form_placeholder_name']     ?? $defaults['form_placeholder_name'],
        'placeholderEmail'    => $settings['form_placeholder_email']    ?? $defaults['form_placeholder_email'],
        'placeholderWhatsapp' => $settings['form_placeholder_whatsapp'] ?? $defaults['form_placeholder_whatsapp'],
    ],
    'header' => [
        'compareText'  => $settings['header_compare_text']  ?? $defaults['header_compare_text'],
        'externalText' => $settings['header_external_text'] ?? $defaults['header_external_text'],
        'externalUrl'  => $settings['header_external_url']  ?? $defaults['header_external_url'],
    ],
    'footer' => [
        'tagline'         => $settings['footer_tagline']          ?? $defaults['footer_tagline'],
        'experienceText'  => $settings['footer_experience_text']  ?? $defaults['footer_experience_text'],
        'privacyUrl'      => $settings['footer_privacy_url']      ?? $defaults['footer_privacy_url'],
        'privacyText'     => $settings['footer_privacy_text']     ?? $defaults['footer_privacy_text'],
    ],
];

Response::success($config);
