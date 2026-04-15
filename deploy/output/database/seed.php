<?php
/**
 * Script para inicializar la base de datos y datos iniciales
 *
 * IMPORTANTE: Ejecutar una sola vez después del deploy.
 * Eliminar o proteger este archivo después de ejecutarlo.
 *
 * Uso: php seed.php (CLI) o abrir en navegador (una sola vez)
 */

// Cargar configuración
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Logger.php';

$isWeb = php_sapi_name() !== 'cli';
if ($isWeb) {
    header('Content-Type: text/plain; charset=utf-8');
}

function output(string $msg): void {
    echo $msg . PHP_EOL;
}

try {
    output('=== Imagina Audit — Inicialización de Base de Datos ===');
    output('');

    // 1. Crear base de datos y tablas
    $db = Database::getInstance();
    $db->initSchema();
    output('[OK] Base de datos creada y schema aplicado.');

    // 2. Insertar configuración por defecto
    $defaults = require dirname(__DIR__) . '/config/defaults.php';

    $settingsToInsert = [
        'company_name' => $defaults['company_name'],
        'company_url' => $defaults['company_url'],
        'company_whatsapp' => $defaults['company_whatsapp'],
        'company_email' => $defaults['company_email'],
        'company_plans_url' => $defaults['company_plans_url'],
        'cta_title' => $defaults['cta_title'],
        'cta_description' => $defaults['cta_description'],
        'cta_button_whatsapp_text' => $defaults['cta_button_whatsapp_text'],
        'cta_button_plans_text' => $defaults['cta_button_plans_text'],
        'plans' => json_encode($defaults['plans'], JSON_UNESCAPED_UNICODE),
        'latest_wp_version' => $defaults['latest_wp_version'],
    ];

    // Insertar mensajes de venta
    $moduleIds = ['wordpress', 'security', 'performance', 'seo', 'mobile', 'infrastructure', 'conversion', 'backups'];
    foreach ($moduleIds as $id) {
        $settingsToInsert["sales_$id"] = $defaults["sales_$id"];
    }

    foreach ($settingsToInsert as $key => $value) {
        $db->execute(
            "INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)",
            [$key, $value]
        );
    }
    output('[OK] Configuración por defecto insertada.');

    // 3. Insertar contraseña admin por defecto si no hay una configurada
    $adminHash = env('ADMIN_PASSWORD_HASH', '');
    if (empty($adminHash)) {
        // Contraseña por defecto: "imagina2024" — cambiar inmediatamente
        $defaultPassword = 'imagina2024';
        $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $db->execute(
            "INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password_hash', ?)",
            [$hash]
        );
        output("[OK] Contraseña admin por defecto establecida: '$defaultPassword'");
        output('     ¡IMPORTANTE! Cambiar esta contraseña desde el panel admin.');
    } else {
        output('[OK] Contraseña admin configurada desde .env');
    }

    // 4. Insertar algunas vulnerabilidades conocidas de ejemplo
    $vulnerabilities = [
        ['elementor', 'Elementor', '<3.18.0', 'high', 'CVE-2024-0001', 'Vulnerabilidad XSS en widgets', '3.18.0'],
        ['contact-form-7', 'Contact Form 7', '<5.8.4', 'medium', 'CVE-2023-6449', 'Bypass de validación de archivos', '5.8.4'],
        ['wordfence', 'Wordfence Security', '<7.11.0', 'medium', '', 'Vulnerabilidad de omisión de autenticación', '7.11.0'],
        ['woocommerce', 'WooCommerce', '<8.2.0', 'high', 'CVE-2023-47781', 'Inyección SQL en búsqueda de pedidos', '8.2.0'],
        ['all-in-one-seo-pack', 'All in One SEO', '<4.5.0', 'critical', 'CVE-2023-0585', 'Escalamiento de privilegios', '4.5.0'],
    ];

    foreach ($vulnerabilities as $v) {
        $db->execute(
            "INSERT OR IGNORE INTO vulnerabilities (plugin_slug, plugin_name, affected_versions, severity, cve_id, description, fixed_in_version) VALUES (?, ?, ?, ?, ?, ?, ?)",
            $v
        );
    }
    output('[OK] Vulnerabilidades de ejemplo insertadas (' . count($vulnerabilities) . ').');

    // 5. Crear directorios necesarios
    $dirs = [
        dirname(__DIR__) . '/cache',
        dirname(__DIR__) . '/logs',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            output("[OK] Directorio creado: $dir");
        }
    }

    output('');
    output('=== Inicialización completada exitosamente ===');
    output('');
    output('RECUERDA:');
    output('1. Cambiar la contraseña admin desde el panel (/admin)');
    output('2. Configurar el archivo .env con tus datos');
    output('3. Eliminar o renombrar este archivo (seed.php) por seguridad');

} catch (Throwable $e) {
    output('[ERROR] ' . $e->getMessage());
    if (env('APP_DEBUG', 'false') === 'true') {
        output($e->getTraceAsString());
    }
}
