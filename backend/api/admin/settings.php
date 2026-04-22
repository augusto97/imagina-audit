<?php
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();

/**
 * Convierte un string de la DB a bool. SQLite guarda todo como string:
 * el toggle de retención viene como "1"/"0"/"true"/"false" según cómo
 * lo serialicen los distintos callers.
 */
function settingsParseBool(mixed $value, bool $default = false): bool {
    if ($value === null) return $default;
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
    if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) return false;
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $defaults = require dirname(__DIR__, 2) . '/config/defaults.php';

        // Leer todas las settings de la DB
        $rows = $db->query("SELECT key, value FROM settings");
        $dbSettings = [];
        foreach ($rows as $row) {
            $dbSettings[$row['key']] = $row['value'];
        }

        // Derivar la lista de módulos dinámicamente desde los defaults + DB.
        // Al agregar un nuevo módulo (weight_* en defaults.php) aparece
        // automáticamente en el admin sin tocar esta lista.
        $moduleIds = [];
        $seen = [];
        foreach (array_merge(array_keys($defaults), array_keys($dbSettings)) as $key) {
            if (str_starts_with($key, 'weight_')) {
                $id = substr($key, 7);
                if (!isset($seen[$id])) {
                    $moduleIds[] = $id;
                    $seen[$id] = true;
                }
            }
        }

        $weights = [];
        $salesMessages = [];
        foreach ($moduleIds as $id) {
            $weights[$id] = (float) ($dbSettings["weight_$id"] ?? $defaults["weight_$id"] ?? 0.1);
            $salesMessages[$id] = $dbSettings["sales_$id"] ?? $defaults["sales_$id"] ?? '';
        }

        $plans = isset($dbSettings['plans'])
            ? json_decode($dbSettings['plans'], true)
            : $defaults['plans'];

        $config = [
            'companyName' => $dbSettings['company_name'] ?? $defaults['company_name'],
            'companyUrl' => $dbSettings['company_url'] ?? $defaults['company_url'],
            'companyWhatsapp' => $dbSettings['company_whatsapp'] ?? $defaults['company_whatsapp'],
            'companyEmail' => $dbSettings['company_email'] ?? $defaults['company_email'],
            'companyPlansUrl' => $dbSettings['company_plans_url'] ?? $defaults['company_plans_url'],
            'logoUrl' => $dbSettings['logo_url'] ?? $defaults['logo_url'] ?? '',
            'logoCollapsedUrl' => $dbSettings['logo_collapsed_url'] ?? $defaults['logo_collapsed_url'] ?? '',
            'faviconUrl' => $dbSettings['favicon_url'] ?? $defaults['favicon_url'] ?? '',
            'brandPrimaryColor' => $dbSettings['brand_primary_color'] ?? $defaults['brand_primary_color'] ?? '#3B82F6',
            'homeSeoTitle' => $dbSettings['home_seo_title'] ?? $defaults['home_seo_title'] ?? '',
            'homeSeoDescription' => $dbSettings['home_seo_description'] ?? $defaults['home_seo_description'] ?? '',
            'homeSeoOgImage' => $dbSettings['home_seo_og_image'] ?? $defaults['home_seo_og_image'] ?? '',
            'homeHeroHeadline' => $dbSettings['home_hero_headline'] ?? $defaults['home_hero_headline'] ?? '',
            'homeHeroSubheadline' => $dbSettings['home_hero_subheadline'] ?? $defaults['home_hero_subheadline'] ?? '',
            'homeFormButtonText' => $dbSettings['home_form_button_text'] ?? $defaults['home_form_button_text'] ?? '',
            'homeFormMicrocopy' => $dbSettings['home_form_microcopy'] ?? $defaults['home_form_microcopy'] ?? '',
            'homeFeaturesTitle' => $dbSettings['home_features_title'] ?? $defaults['home_features_title'] ?? '',
            'homeTrustText' => $dbSettings['home_trust_text'] ?? $defaults['home_trust_text'] ?? '',

            // Placeholders del form público
            'formPlaceholderUrl'      => $dbSettings['form_placeholder_url']      ?? $defaults['form_placeholder_url'] ?? '',
            'formPlaceholderName'     => $dbSettings['form_placeholder_name']     ?? $defaults['form_placeholder_name'] ?? '',
            'formPlaceholderEmail'    => $dbSettings['form_placeholder_email']    ?? $defaults['form_placeholder_email'] ?? '',
            'formPlaceholderWhatsapp' => $dbSettings['form_placeholder_whatsapp'] ?? $defaults['form_placeholder_whatsapp'] ?? '',

            // Header y footer del sitio público
            'headerCompareText'    => $dbSettings['header_compare_text']    ?? $defaults['header_compare_text'] ?? '',
            'headerExternalText'   => $dbSettings['header_external_text']   ?? $defaults['header_external_text'] ?? '',
            'headerExternalUrl'    => $dbSettings['header_external_url']    ?? $defaults['header_external_url'] ?? '',
            'footerTagline'        => $dbSettings['footer_tagline']         ?? $defaults['footer_tagline'] ?? '',
            'footerExperienceText' => $dbSettings['footer_experience_text'] ?? $defaults['footer_experience_text'] ?? '',
            'footerPrivacyUrl'     => $dbSettings['footer_privacy_url']     ?? $defaults['footer_privacy_url'] ?? '',
            'footerPrivacyText'    => $dbSettings['footer_privacy_text']    ?? $defaults['footer_privacy_text'] ?? '',

            // Retención + cola (faltaban y por eso el toggle no persistía en la UI)
            'auditsRetentionEnabled'   => settingsParseBool($dbSettings['audits_retention_enabled'] ?? null, $defaults['audits_retention_enabled'] ?? false),
            'auditsRetentionMonths'    => (int) ($dbSettings['audits_retention_months'] ?? $defaults['audits_retention_months'] ?? 6),
            'auditMaxConcurrent'       => (int) ($dbSettings['audit_max_concurrent'] ?? $defaults['audit_max_concurrent'] ?? 3),
            'auditStaleSeconds'        => (int) ($dbSettings['audit_stale_seconds'] ?? $defaults['audit_stale_seconds'] ?? 300),
            'auditFailureCacheMinutes' => (int) ($dbSettings['audit_failure_cache_minutes'] ?? $defaults['audit_failure_cache_minutes'] ?? 10),
            'auditMaxAttempts'         => (int) ($dbSettings['audit_max_attempts'] ?? $defaults['audit_max_attempts'] ?? 3),

            'systemTotalRamMb' => (int) ($dbSettings['system_total_ram_mb'] ?? 0),
            'googlePagespeedApiKey' => $dbSettings['google_pagespeed_api_key'] ?? '',
            'defaultAiProvider'      => $dbSettings['default_ai_provider']       ?? 'claude',
            'openaiApiKey'           => !empty($dbSettings['openai_api_key']) ? '••••••••' : '',
            'openaiModel'            => $dbSettings['openai_model']              ?? 'gpt-4o-mini',
            'anthropicApiKey'        => !empty($dbSettings['anthropic_api_key']) ? '••••••••' : '',
            'anthropicModel'         => $dbSettings['anthropic_model']           ?? 'claude-sonnet-4-5',
            'googleTranslateApiKey'  => !empty($dbSettings['google_translate_api_key']) ? '••••••••' : '',
            'leadNotificationEmail' => $dbSettings['lead_notification_email'] ?? '',
            'smtpHost' => $dbSettings['smtp_host'] ?? '',
            'smtpPort' => (int) ($dbSettings['smtp_port'] ?? 587),
            'smtpUsername' => $dbSettings['smtp_username'] ?? '',
            'smtpPassword' => !empty($dbSettings['smtp_password']) ? '••••••••' : '',
            'smtpEncryption' => $dbSettings['smtp_encryption'] ?? 'tls',
            'smtpFromEmail' => $dbSettings['smtp_from_email'] ?? '',
            'smtpFromName' => $dbSettings['smtp_from_name'] ?? 'Imagina Audit',
            'rateLimitMaxPerHour' => (int) ($dbSettings['rate_limit_max_per_hour'] ?? env('RATE_LIMIT_MAX_PER_HOUR', '100')),
            'cacheTtlHours' => (int) round(((int) ($dbSettings['cache_ttl_seconds'] ?? env('CACHE_TTL_SECONDS', '86400'))) / 3600),
            'allowedOrigins' => $dbSettings['allowed_origins'] ?? env('ALLOWED_ORIGIN', '*'),
            'moduleWeights' => $weights,
            'thresholds' => [
                'excellent' => (int) ($dbSettings['threshold_excellent'] ?? $defaults['threshold_excellent']),
                'good' => (int) ($dbSettings['threshold_good'] ?? $defaults['threshold_good']),
                'warning' => (int) ($dbSettings['threshold_warning'] ?? $defaults['threshold_warning']),
                'critical' => (int) ($dbSettings['threshold_critical'] ?? $defaults['threshold_critical']),
            ],
            'salesMessages' => $salesMessages,
            'ctaTitle' => $dbSettings['cta_title'] ?? $defaults['cta_title'],
            'ctaDescription' => $dbSettings['cta_description'] ?? $defaults['cta_description'],
            'ctaButtonWhatsappText' => $dbSettings['cta_button_whatsapp_text'] ?? $defaults['cta_button_whatsapp_text'],
            'ctaButtonPlansText' => $dbSettings['cta_button_plans_text'] ?? $defaults['cta_button_plans_text'],
            'plans' => $plans,
        ];

        // NO incluir admin_password_hash
        Response::success($config);
    } catch (Throwable $e) {
        Logger::error('Error en settings GET: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.settings.fetch_error'), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = Response::getJsonBody();

    try {
        foreach ($body as $key => $value) {
            // No sobreescribir contraseña SMTP con el placeholder
            if ($key === 'smtpPassword' && ($value === '••••••••' || $value === '')) {
                continue;
            }
            // Tampoco pisar las API keys con el placeholder de bullets
            if (in_array($key, ['openaiApiKey', 'anthropicApiKey', 'googleTranslateApiKey'], true)
                && ($value === '••••••••' || $value === '')) {
                continue;
            }

            // Cambio de contraseña admin
            if ($key === 'adminPassword' && !empty($value)) {
                $hash = password_hash($value, PASSWORD_BCRYPT);
                $db->execute(
                    "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES ('admin_password_hash', ?, datetime('now'))",
                    [$hash]
                );
                continue;
            }

            // Convertir cacheTtlHours a cache_ttl_seconds
            if ($key === 'cacheTtlHours') {
                $seconds = max(0, (int) $value) * 3600;
                $db->execute(
                    "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES ('cache_ttl_seconds', ?, datetime('now'))",
                    [(string) $seconds]
                );
                continue;
            }

            // Convertir camelCase a snake_case para la DB
            $dbKey = strtolower(preg_replace('/[A-Z]/', '_$0', $key));

            // Si es array/objeto, codificar como JSON
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            // Booleans → "1"/"0" para que el GET los lea correctamente
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $db->execute(
                "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                [$dbKey, (string) $value]
            );
        }
        Response::success();
    } catch (Throwable $e) {
        Logger::error('Error en settings PUT: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.settings.save_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
