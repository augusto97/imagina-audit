<?php
return [
    // Labels y descripciones de los jobs
    'drain_queue.label'       => 'Cola de auditorías (drain)',
    'drain_queue.description' => 'Saca jobs pendientes de la cola y los procesa. Debe correr cada minuto.',
    'cleanup.label'           => 'Limpieza diaria',
    'cleanup.description'     => 'Purga rate-limits expirados, cache viejo y (si está activada) informes antiguos.',
    'vacuum.label'            => 'Vacuum de SQLite',
    'vacuum.description'      => 'Compacta la DB y optimiza índices. Semanal.',
    'update_vulnerabilities.label'       => 'Actualización de vulnerabilidades',
    'update_vulnerabilities.description' => 'Sincroniza la base local de CVEs. Diario recomendado.',
    'refresh_plugin_vault.label'         => 'Refresh del Plugin Vault',
    'refresh_plugin_vault.description'   => 'Busca nuevas versiones de wp-snapshot en GitHub. Mensual.',

    // Estados / mensajes
    'msg.never'    => 'Nunca se ha ejecutado',
    'msg.ok'       => 'Corriendo a tiempo',
    'msg.warning'  => 'Atrasado (posiblemente el cron del sistema no está configurado)',
    'msg.critical' => 'No ha corrido en mucho tiempo — el cron del sistema no está funcionando',

    // Intervalos humanos
    'unit.seconds' => '{{count}}s',
    'unit.minutes' => '{{count}} min',
    'unit.hours'   => '{{count}} h',
    'unit.days'    => '{{count}} días',
    'unit.weeks'   => '{{count}} sem',
];
