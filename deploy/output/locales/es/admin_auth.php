<?php
return [
    // ——— /api/admin/login.php ——————————————————————————————————
    'login.password_required'  => 'La contraseña es obligatoria.',
    'login.password_incorrect' => 'Contraseña incorrecta.',
    'login.backoff'            => 'Demasiados intentos fallidos. Espera {{seconds}}s antes de volver a intentar.',
    'login.ip_blocked'         => 'Demasiados intentos. IP bloqueada por {{minutes}} minutos.',

    // ——— /api/admin/login-2fa.php ——————————————————————————————
    'login2fa.no_pending'      => 'No hay login pendiente de 2FA. Ingresa tu contraseña primero.',
    'login2fa.session_expired' => 'La sesión 2FA expiró. Vuelve a ingresar tu contraseña.',
    'login2fa.too_many'        => 'Demasiados códigos incorrectos. Vuelve a ingresar tu contraseña.',
    'login2fa.code_required'   => 'Código requerido',
    'login2fa.invalid_code'    => 'Código inválido. Revisa la hora de tu dispositivo o usa un recovery code.',

    // ——— /api/admin/2fa.php ————————————————————————————————————
    '2fa.already_enabled_reconfigure' => 'El 2FA ya está habilitado. Desactívalo primero si quieres re-configurar.',
    '2fa.already_enabled'             => 'El 2FA ya está habilitado.',
    '2fa.secret_and_code_required'    => 'secret y code son obligatorios',
    '2fa.invalid_totp'                => 'Código inválido. Revisa la hora de tu dispositivo y vuelve a intentarlo.',
    '2fa.password_and_code_required'  => 'password y code son obligatorios',
    '2fa.password_incorrect'          => 'Contraseña incorrecta',
    '2fa.invalid_totp_or_recovery'    => 'Código 2FA o recovery code inválido',
    '2fa.not_enabled'                 => '2FA no está habilitado',
    '2fa.invalid_totp_only'           => 'Código TOTP inválido',
    '2fa.unknown_action'              => 'Acción desconocida',

    // ——— /api/setup.php ————————————————————————————————————————
    'setup.already_done'       => 'El setup ya se completó. Para cambiar la password, entra al admin y ve a Configuración → General.',
    'setup.password_too_short' => 'La password debe tener al menos 10 caracteres.',
    'setup.passwords_mismatch' => 'Las passwords no coinciden.',
    'setup.save_error'         => 'Error al guardar la configuración.',

    // ——— /api/admin/test-email.php ————————————————————————————
    'test_email.invalid_address' => 'Email de destino inválido.',
    'test_email.send_failed'     => 'No se pudo enviar el email. Verifica la configuración SMTP.',
    'test_email.sent_ok'         => 'Email de prueba enviado correctamente.',
];
