<?php
return [
    // ——— Common across admin endpoints ——————————————————————————
    'common.audit_id_required' => 'audit_id is required',
    'common.id_required'       => 'The id parameter is required.',
    'common.audit_not_found'   => 'Audit not found',

    // ——— /admin/ai-translate.php ————————————————————————————————
    'ai_translate.lang_same'          => 'Source and target languages cannot be the same',
    'ai_translate.lang_not_supported' => 'Target language not supported',
    'ai_translate.provider_invalid'   => 'Invalid provider',
    'ai_translate.items_required'     => 'items is required',
    'ai_translate.namespace_required' => 'namespace is required',

    // ——— /admin/checklist.php ———————————————————————————————————
    'checklist.audit_and_metric_required' => 'auditId and metricId are required',

    // ——— /admin/dashboard.php ———————————————————————————————————
    'dashboard.stats_error' => 'Failed to retrieve statistics.',

    // ——— /admin/export-leads.php ————————————————————————————————
    'export_leads.error' => 'Export failed.',

    // ——— /admin/lead-detail.php —————————————————————————————————
    'lead_detail.fetch_error' => 'Failed to retrieve the detail.',
    'lead_detail.not_found'   => 'Audit not found.',

    // ——— /admin/leads.php ———————————————————————————————————————
    'leads.fetch_error'      => 'Failed to retrieve leads.',
    'leads.protected_report' => 'This report is protected. Unprotect it before deleting.',

    // ——— /admin/leads-bulk.php ——————————————————————————————————
    'leads_bulk.exec_error'    => 'Error executing the bulk action.',
    'leads_bulk.no_valid_id'   => 'No valid id in the batch',
    'leads_bulk.action_invalid' => 'Invalid action (delete|pin|unpin)',
    'leads_bulk.ids_required'  => 'ids is required (non-empty array)',

    // ——— /admin/pin-audit.php ———————————————————————————————————
    'pin_audit.update_error' => 'Update failed.',
    'pin_audit.id_required'  => 'auditId is required',

    // ——— /admin/plugin-vault.php ————————————————————————————————
    'plugin_vault.github_error'   => 'Could not download the latest release from GitHub. Check the logs.',
    'plugin_vault.unknown_plugin' => 'Unknown plugin',

    // ——— /admin/queue-status.php ————————————————————————————————
    'queue_status.error' => 'Failed to retrieve queue status.',

    // ——— /admin/retention-preview.php ———————————————————————————
    'retention.preview_error'  => 'Failed to compute preview.',
    'retention.months_invalid' => 'months must be between 1 and 120',

    // ——— /admin/settings.php ————————————————————————————————————
    'settings.save_error'  => 'Failed to save the configuration.',
    'settings.fetch_error' => 'Failed to retrieve the configuration.',

    // ——— /admin/snapshot.php ————————————————————————————————————
    'snapshot.audit_id_required'    => 'auditId is required',
    'snapshot.missing_sections'     => 'The JSON does not have the expected wp-snapshot structure (missing "sections").',
    'snapshot.too_many_sections'    => 'The snapshot has too many sections (possibly a malicious payload).',
    'snapshot.analyze_error'        => 'Failed to analyze the snapshot: {{details}}',
    'snapshot.json_invalid_reason'  => 'Invalid JSON: {{reason}}',
    'snapshot.json_invalid'         => 'Invalid JSON',
    'snapshot.json_data_too_big'    => 'jsonData exceeds the 10 MB limit',
    'snapshot.json_data_required'   => 'jsonData is required',

    // ——— /admin/snapshot-report.php —————————————————————————————
    'snapshot_report.build_error' => 'Failed to build the report: {{details}}',
    'snapshot_report.corrupt'     => 'Corrupt snapshot in DB.',

    // ——— /admin/translations.php ————————————————————————————————
    'translations.lang_unsupported'          => 'Unsupported language',
    'translations.namespace_invalid'         => 'Invalid namespace',
    'translations.namespace_and_key_optional' => 'namespace (and optionally key) are required',
    'translations.namespace_and_key_required' => 'namespace and key are required',
    'translations.source_invalid'            => 'Invalid source',

    // ——— /admin/update-vulnerabilities.php ——————————————————————
    'update_vulns.update_error' => 'Update failed: {{details}}',

    // ——— /admin/upload.php ——————————————————————————————————————
    'upload.file_too_big'       => 'File too large. Maximum 2 MB.',
    'upload.register_error'     => 'File uploaded but could not be saved in the configuration.',
    'upload.bad_format'         => 'Format not allowed. Only JPG and PNG.',
    'upload.dir_error'          => 'Could not access the uploads directory on the server.',
    'upload.move_error'         => 'Could not move the uploaded file.',
    'upload.no_file'            => 'No file received, or upload error.',
    'upload.asset_type_invalid' => 'Invalid asset type. Use: logo, logo_collapsed or favicon.',

    // ——— /admin/vulnerabilities.php —————————————————————————————
    'vulns.fetch_error'  => 'Failed to retrieve vulnerabilities.',
    'vulns.create_error' => 'Failed to create the vulnerability.',
    'vulns.update_error' => 'Failed to update the vulnerability.',
    'vulns.delete_error' => 'Failed to delete the vulnerability.',

    // ——— /admin/waterfall.php ———————————————————————————————————
    'waterfall.id_required' => 'id is required',

    // ——— /admin/plans.php (P4.2) ————————————————————————————————
    'plans.fetch_error'  => 'Failed to load plans.',
    'plans.create_error' => 'Failed to create the plan.',
    'plans.update_error' => 'Failed to update the plan.',
    'plans.delete_error' => 'Failed to delete the plan.',
    'plans.name_required' => 'Plan name is required.',
    'plans.limit_invalid' => 'Monthly limit must be 0 or greater (0 = unlimited).',
    'plans.not_found'    => 'Plan not found.',
    'plans.in_use'       => 'Cannot delete: the plan is assigned to {{count}} user(s). Reassign them first.',

    // ——— /admin/users.php (P4.2) ————————————————————————————————
    'users.fetch_error'   => 'Failed to load users.',
    'users.create_error'  => 'Failed to create the user.',
    'users.update_error'  => 'Failed to update the user.',
    'users.delete_error'  => 'Failed to delete the user.',
    'users.not_found'     => 'User not found.',
    'users.email_required' => 'Email is required.',
    'users.email_invalid' => 'Invalid email address.',
    'users.email_exists'  => 'A user with that email already exists.',
    'users.password_too_short' => 'Password must be at least 10 characters.',
];
