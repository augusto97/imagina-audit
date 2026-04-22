<?php
return [
    // ——— /api/user/projects endpoints ——————————————————————————————
    'not_found'         => 'Proyecto no encontrado.',
    'url_required'      => 'Se requiere una URL válida.',
    'url_invalid'       => 'La URL es inválida.',
    'name_required'     => 'El nombre del proyecto es obligatorio.',
    'fetch_error'       => 'Error al cargar los proyectos.',
    'create_error'      => 'Error al crear el proyecto.',
    'update_error'      => 'Error al actualizar el proyecto.',
    'delete_error'      => 'Error al eliminar el proyecto.',
    'url_duplicate'     => 'Ya tenés un proyecto para esta URL.',
    'quota_projects'    => 'Alcanzaste el límite de proyectos de tu plan ({{used}}/{{limit}}). Actualizá tu plan o eliminá un proyecto.',
    'no_plan'           => 'Tu cuenta no tiene plan asignado. Los proyectos no están disponibles.',
    'not_owner'         => 'No tenés acceso a este proyecto.',

    // ——— Checklist ————————————————————————————————————————————————
    'checklist.fetch_error'      => 'Error al cargar el checklist.',
    'checklist.item_not_found'   => 'Item del checklist no encontrado.',
    'checklist.status_invalid'   => 'Estado inválido. Debe ser open, done o ignored.',

    // ——— Share links ——————————————————————————————————————————————
    'share.invalid_token'  => 'Este enlace compartido es inválido o fue revocado.',
    'share.toggle_error'   => 'Error al cambiar el estado del enlace compartido.',
    'share.not_enabled'    => 'El compartido no está habilitado para este proyecto.',
];
