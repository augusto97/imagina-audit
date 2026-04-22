<?php
return [
    // ——— Errores de auth compartidos (UserAuth) ——————————————————
    'not_authenticated' => 'No autenticado',
    'csrf_invalid'      => 'Token CSRF inválido o ausente',

    // ——— /api/user/login.php ————————————————————————————————————
    'login.credentials_required' => 'Email y contraseña son obligatorios.',
    'login.invalid_credentials'  => 'Credenciales inválidas.',
    'login.account_disabled'     => 'Esta cuenta está deshabilitada. Contacta al administrador.',
    'login.backoff'              => 'Demasiados intentos fallidos. Espera {{seconds}}s antes de volver a intentar.',
    'login.ip_blocked'           => 'Demasiados intentos. IP bloqueada por {{minutes}} minutos.',

    // ——— /api/user/audits.php ——————————————————————————————————
    'audits.fetch_error' => 'Error al cargar tu historial de auditorías.',

    // ——— Cuota en /api/audit.php (P4.4) ——————————————————————————
    'quota.exceeded'      => 'Has alcanzado tu cuota mensual ({{used}}/{{limit}}). Se reinicia a principios del próximo mes.',
    'quota.no_plan'       => 'Tu cuenta no tiene plan asignado. Por favor contacta al administrador.',
    'quota.plan_inactive' => 'Tu plan está inactivo actualmente. Por favor contacta al administrador.',
];
