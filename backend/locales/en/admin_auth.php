<?php
return [
    // ——— /api/admin/login.php ——————————————————————————————————
    'login.password_required'  => 'Password is required.',
    'login.password_incorrect' => 'Incorrect password.',
    'login.backoff'            => 'Too many failed attempts. Wait {{seconds}}s before trying again.',
    'login.ip_blocked'         => 'Too many attempts. IP blocked for {{minutes}} minutes.',

    // ——— /api/admin/login-2fa.php ——————————————————————————————
    'login2fa.no_pending'   => 'No pending 2FA login. Enter your password first.',
    'login2fa.session_expired' => 'The 2FA session expired. Please enter your password again.',
    'login2fa.too_many'     => 'Too many invalid codes. Please enter your password again.',
    'login2fa.code_required' => 'Code is required',
    'login2fa.invalid_code'  => 'Invalid code. Check the clock on your device or use a recovery code.',

    // ——— /api/admin/2fa.php ————————————————————————————————————
    '2fa.already_enabled_reconfigure' => '2FA is already enabled. Disable it first if you want to reconfigure.',
    '2fa.already_enabled'             => '2FA is already enabled.',
    '2fa.secret_and_code_required'    => 'secret and code are required',
    '2fa.invalid_totp'                => 'Invalid code. Check the clock on your device and try again.',
    '2fa.password_and_code_required'  => 'password and code are required',
    '2fa.password_incorrect'          => 'Incorrect password',
    '2fa.invalid_totp_or_recovery'    => 'Invalid 2FA code or recovery code',
    '2fa.not_enabled'                 => '2FA is not enabled',
    '2fa.invalid_totp_only'           => 'Invalid TOTP code',
    '2fa.unknown_action'              => 'Unknown action',

    // ——— /api/setup.php ————————————————————————————————————————
    'setup.already_done'       => 'Setup is already complete. To change the password, log in as admin and go to Settings → General.',
    'setup.password_too_short' => 'The password must be at least 10 characters long.',
    'setup.passwords_mismatch' => 'Passwords do not match.',
    'setup.save_error'         => 'Error saving the configuration.',

    // ——— /api/admin/test-email.php ————————————————————————————
    'test_email.invalid_address' => 'Invalid destination email.',
    'test_email.send_failed'     => 'Failed to send email. Check your SMTP configuration.',
    'test_email.sent_ok'         => 'Test email sent successfully.',
];
