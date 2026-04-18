<?php
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $defaults = require dirname(__DIR__, 2) . '/config/defaults.php';

        // Leer todas las settings de la DB
        $rows = $db->query("SELECT key, value FROM settings");
        $dbSettings = [];
        foreach ($rows as $row) {
            $dbSettings[$row['key']] = $row['value'];
        }

        // Construir respuesta con valores de DB o defaults
        $moduleIds = ['wordpress', 'security', 'performance', 'seo', 'mobile', 'infrastructure', 'conversion'];

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
            'googlePagespeedApiKey' => $dbSettings['google_pagespeed_api_key'] ?? '',
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
        Response::error('Error al obtener configuración.', 500);
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

            $db->execute(
                "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                [$dbKey, (string) $value]
            );
        }
        Response::success();
    } catch (Throwable $e) {
        Logger::error('Error en settings PUT: ' . $e->getMessage());
        Response::error('Error al guardar configuración.', 500);
    }
}

Response::error('Método no permitido', 405);
