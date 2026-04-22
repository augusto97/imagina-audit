<?php
return [
    // Job labels and descriptions
    'drain_queue.label'       => 'Audit queue drain',
    'drain_queue.description' => 'Pulls pending jobs from the queue and processes them. Must run every minute.',
    'cleanup.label'           => 'Daily cleanup',
    'cleanup.description'     => 'Purges expired rate-limits, stale cache and (if enabled) old reports.',
    'vacuum.label'            => 'SQLite vacuum',
    'vacuum.description'      => 'Compacts the DB and optimizes indexes. Weekly.',
    'update_vulnerabilities.label'       => 'Vulnerability database update',
    'update_vulnerabilities.description' => 'Syncs the local CVE database. Recommended daily.',
    'refresh_plugin_vault.label'         => 'Plugin Vault refresh',
    'refresh_plugin_vault.description'   => 'Checks for new wp-snapshot releases on GitHub. Monthly.',

    // Statuses / messages
    'msg.never'    => 'Has never run',
    'msg.ok'       => 'Running on time',
    'msg.warning'  => 'Overdue (the system cron may not be configured)',
    'msg.critical' => 'Has not run for a long time — the system cron is not working',

    // Human intervals
    'unit.seconds' => '{{count}}s',
    'unit.minutes' => '{{count}} min',
    'unit.hours'   => '{{count}} h',
    'unit.days'    => '{{count}} days',
    'unit.weeks'   => '{{count}} weeks',
];
