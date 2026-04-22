<?php
return [
    // ——— Compartidos entre endpoints ——————————————————————————————
    'common.method_not_allowed'  => 'Método no permitido',
    'common.endpoint_not_found'  => 'Endpoint no encontrado',
    'common.internal_error'      => 'Error interno',
    'common.param_required'      => 'El parámetro {{param}} es obligatorio.',

    // ——— /api/audit.php ————————————————————————————————————————
    'audit.url_required'   => 'La URL es obligatoria.',
    'audit.rate_limit'     => 'Has alcanzado el límite de auditorías por hora. Intenta más tarde.',
    'audit.not_found'      => 'Auditoría no encontrada.',
    'audit.fetch_error'    => 'Error al obtener la auditoría.',
    'audit.id_required'    => 'El parámetro id es obligatorio.',
    'audit.runtime_error'  => 'Ocurrió un error al analizar el sitio. Intenta nuevamente.',
    'audit.save_error'     => 'Error guardando el resultado. Intenta nuevamente.',

    // ——— /api/compare.php ——————————————————————————————————————
    'compare.urls_required' => 'Ambas URLs son obligatorias.',
    'compare.rate_limit'    => 'Has alcanzado el límite de comparaciones por hora.',
    'compare.runtime_error' => 'Error al analizar los sitios: {{details}}',

    // ——— /api/history.php ——————————————————————————————————————
    'history.domain_required' => 'El parámetro domain es obligatorio.',
    'history.fetch_error'     => 'Error al obtener historial.',

    // ——— /api/scan-progress.php ————————————————————————————————
    'progress.id_required' => 'id requerido',
    'progress.not_found'   => 'Progreso no encontrado o expirado',
];
