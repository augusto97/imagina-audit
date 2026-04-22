<?php
return [
    // ——— Shared auth errors used by UserAuth ————————————————————
    'not_authenticated' => 'Not authenticated',
    'csrf_invalid'      => 'Invalid or missing CSRF token',

    // ——— /api/user/login.php ————————————————————————————————————
    'login.credentials_required' => 'Email and password are required.',
    'login.invalid_credentials'  => 'Invalid credentials.',
    'login.account_disabled'     => 'This account is disabled. Contact the administrator.',
    'login.backoff'              => 'Too many failed attempts. Wait {{seconds}}s before trying again.',
    'login.ip_blocked'           => 'Too many attempts. IP blocked for {{minutes}} minutes.',

    // ——— /api/user/audits.php ——————————————————————————————————
    'audits.fetch_error' => 'Failed to load your audit history.',

    // ——— Quota enforcement in /api/audit.php (P4.4) ——————————————
    'quota.exceeded'        => 'You have reached your monthly quota ({{used}}/{{limit}}). It resets at the start of next month.',
    'quota.no_plan'         => 'Your account has no plan assigned. Please contact the administrator.',
    'quota.plan_inactive'   => 'Your plan is currently inactive. Please contact the administrator.',
];
