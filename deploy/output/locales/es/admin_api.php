<?php
return [
    // ——— Compartidos en endpoints admin ——————————————————————————
    'common.audit_id_required' => 'audit_id requerido',
    'common.id_required'       => 'El parámetro id es obligatorio.',
    'common.audit_not_found'   => 'Auditoría no encontrada',

    // ——— /admin/ai-translate.php ————————————————————————————————
    'ai_translate.lang_same'          => 'El idioma origen y destino no pueden ser iguales',
    'ai_translate.lang_not_supported' => 'Idioma destino no soportado',
    'ai_translate.provider_invalid'   => 'Provider inválido',
    'ai_translate.items_required'     => 'items es obligatorio',
    'ai_translate.namespace_required' => 'namespace es obligatorio',

    // ——— /admin/checklist.php ———————————————————————————————————
    'checklist.audit_and_metric_required' => 'auditId y metricId requeridos',

    // ——— /admin/dashboard.php ———————————————————————————————————
    'dashboard.stats_error' => 'Error al obtener estadísticas.',

    // ——— /admin/export-leads.php ————————————————————————————————
    'export_leads.error' => 'Error al exportar.',

    // ——— /admin/lead-detail.php —————————————————————————————————
    'lead_detail.fetch_error' => 'Error al obtener el detalle.',
    'lead_detail.not_found'   => 'Auditoría no encontrada.',

    // ——— /admin/leads.php ———————————————————————————————————————
    'leads.fetch_error'      => 'Error al obtener leads.',
    'leads.protected_report' => 'Este informe está protegido. Desprotégelo antes de eliminarlo.',

    // ——— /admin/leads-bulk.php ——————————————————————————————————
    'leads_bulk.exec_error'    => 'Error ejecutando la acción en lote.',
    'leads_bulk.no_valid_id'   => 'Ningún id válido en el batch',
    'leads_bulk.action_invalid' => 'action inválida (delete|pin|unpin)',
    'leads_bulk.ids_required'  => 'ids requerido (array no vacío)',

    // ——— /admin/pin-audit.php ———————————————————————————————————
    'pin_audit.update_error' => 'Error al actualizar.',
    'pin_audit.id_required'  => 'auditId requerido',

    // ——— /admin/plugin-vault.php ————————————————————————————————
    'plugin_vault.github_error'   => 'No se pudo descargar la última versión desde GitHub. Revisa los logs.',
    'plugin_vault.unknown_plugin' => 'Plugin desconocido',

    // ——— /admin/queue-status.php ————————————————————————————————
    'queue_status.error' => 'Error obteniendo estado de la cola.',

    // ——— /admin/retention-preview.php ———————————————————————————
    'retention.preview_error'  => 'Error al calcular preview.',
    'retention.months_invalid' => 'months debe estar entre 1 y 120',

    // ——— /admin/settings.php ————————————————————————————————————
    'settings.save_error'  => 'Error al guardar configuración.',
    'settings.fetch_error' => 'Error al obtener configuración.',

    // ——— /admin/snapshot.php ————————————————————————————————————
    'snapshot.audit_id_required'    => 'auditId requerido',
    'snapshot.missing_sections'     => 'El JSON no tiene la estructura esperada de wp-snapshot (falta "sections").',
    'snapshot.too_many_sections'    => 'El snapshot tiene demasiadas secciones (posible payload malicioso).',
    'snapshot.analyze_error'        => 'Error al analizar el snapshot: {{details}}',
    'snapshot.json_invalid_reason'  => 'JSON inválido: {{reason}}',
    'snapshot.json_invalid'         => 'JSON inválido',
    'snapshot.json_data_too_big'    => 'jsonData excede el tope de 10MB',
    'snapshot.json_data_required'   => 'jsonData requerido',

    // ——— /admin/snapshot-report.php —————————————————————————————
    'snapshot_report.build_error' => 'Error construyendo el reporte: {{details}}',
    'snapshot_report.corrupt'     => 'Snapshot corrupto en DB.',

    // ——— /admin/translations.php ————————————————————————————————
    'translations.lang_unsupported'          => 'Idioma no soportado',
    'translations.namespace_invalid'         => 'Namespace inválido',
    'translations.namespace_and_key_optional' => 'namespace (y opcionalmente key) son obligatorios',
    'translations.namespace_and_key_required' => 'namespace y key son obligatorios',
    'translations.source_invalid'            => 'source inválido',

    // ——— /admin/languages.php ———————————————————————————————————
    'languages.fetch_error'         => 'Error al cargar los idiomas.',
    'languages.code_invalid'        => 'Código de idioma inválido (usa 2 letras ISO 639-1, ej. pt, fr, de).',
    'languages.already_exists'      => 'Este idioma ya existe.',
    'languages.not_found'           => 'Idioma no encontrado.',
    'languages.save_error'          => 'Error al guardar el idioma.',
    'languages.delete_error'        => 'Error al eliminar el idioma.',
    'languages.cannot_delete_default' => 'No se puede eliminar el idioma por defecto.',

    // ——— /admin/translations-import.php —————————————————————————
    'translations_import.invalid_file' => 'Archivo inválido. Se esperaba un pack de idioma exportado desde Imagina Audit.',
    'translations_import.invalid_mode' => 'Modo de import inválido.',
    'translations_import.apply_error'  => 'No se pudo aplicar el import.',

    // ——— /admin/update-vulnerabilities.php ——————————————————————
    'update_vulns.update_error' => 'Error al actualizar: {{details}}',

    // ——— /admin/upload.php ——————————————————————————————————————
    'upload.file_too_big'       => 'Archivo demasiado grande. Máximo 2 MB.',
    'upload.register_error'     => 'Archivo subido pero no se pudo registrar en la configuración.',
    'upload.bad_format'         => 'Formato no permitido. Solo JPG y PNG.',
    'upload.dir_error'          => 'No se pudo acceder al directorio de uploads en el servidor.',
    'upload.move_error'         => 'No se pudo mover el archivo subido.',
    'upload.no_file'            => 'No se recibió archivo o hubo un error de subida.',
    'upload.asset_type_invalid' => 'Tipo de asset inválido. Usa: logo, logo_collapsed o favicon.',

    // ——— /admin/vulnerabilities.php —————————————————————————————
    'vulns.fetch_error'  => 'Error al obtener vulnerabilidades.',
    'vulns.create_error' => 'Error al crear vulnerabilidad.',
    'vulns.update_error' => 'Error al actualizar vulnerabilidad.',
    'vulns.delete_error' => 'Error al eliminar vulnerabilidad.',

    // ——— /admin/waterfall.php ———————————————————————————————————
    'waterfall.id_required' => 'id requerido',

    // ——— /admin/plans.php (P4.2) ————————————————————————————————
    'plans.fetch_error'  => 'Error al cargar los planes.',
    'plans.create_error' => 'Error al crear el plan.',
    'plans.update_error' => 'Error al actualizar el plan.',
    'plans.delete_error' => 'Error al eliminar el plan.',
    'plans.name_required' => 'El nombre del plan es obligatorio.',
    'plans.limit_invalid' => 'El límite mensual debe ser 0 o mayor (0 = ilimitado).',
    'plans.not_found'    => 'Plan no encontrado.',
    'plans.in_use'       => 'No se puede eliminar: el plan está asignado a {{count}} usuario(s). Reasígnelos primero.',

    // ——— /admin/users.php (P4.2) ————————————————————————————————
    'users.fetch_error'   => 'Error al cargar los usuarios.',
    'users.create_error'  => 'Error al crear el usuario.',
    'users.update_error'  => 'Error al actualizar el usuario.',
    'users.delete_error'  => 'Error al eliminar el usuario.',
    'users.not_found'     => 'Usuario no encontrado.',
    'users.email_required' => 'El email es obligatorio.',
    'users.email_invalid' => 'Email inválido.',
    'users.email_exists'  => 'Ya existe un usuario con ese email.',
    'users.password_too_short' => 'La contraseña debe tener al menos 10 caracteres.',

    // ——— /admin/projects.php (P5.3) ——————————————————————————————
    'projects.fetch_error'  => 'Error al cargar los proyectos.',
    'projects.delete_error' => 'Error al eliminar el proyecto.',
    'projects.not_found'    => 'Proyecto no encontrado.',
];
