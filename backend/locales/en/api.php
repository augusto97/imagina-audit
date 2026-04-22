<?php
return [
    // ——— Shared across endpoints ——————————————————————————————————
    'common.method_not_allowed'  => 'Method not allowed',
    'common.endpoint_not_found'  => 'Endpoint not found',
    'common.internal_error'      => 'Internal error',
    // Genéricos con placeholder — usar cuando se necesite un mensaje
    // parametrizado por nombre de parámetro.
    'common.param_required'      => 'The {{param}} parameter is required.',

    // ——— /api/audit.php ————————————————————————————————————————
    'audit.url_required'   => 'The URL is required.',
    'audit.rate_limit'     => 'You have reached the hourly audit limit. Please try again later.',
    'audit.not_found'      => 'Audit not found.',
    'audit.fetch_error'    => 'Failed to retrieve the audit.',
    'audit.id_required'    => 'The id parameter is required.',
    'audit.runtime_error'  => 'An error occurred while analyzing the site. Please try again.',
    'audit.save_error'     => 'Error saving the result. Please try again.',

    // ——— /api/compare.php ——————————————————————————————————————
    'compare.urls_required' => 'Both URLs are required.',
    'compare.rate_limit'    => 'You have reached the hourly comparison limit.',
    'compare.runtime_error' => 'Error analyzing the sites: {{details}}',

    // ——— /api/history.php ——————————————————————————————————————
    'history.domain_required' => 'The domain parameter is required.',
    'history.fetch_error'     => 'Failed to retrieve history.',

    // ——— /api/scan-progress.php ————————————————————————————————
    'progress.id_required' => 'id is required',
    'progress.not_found'   => 'Progress not found or expired',
];
