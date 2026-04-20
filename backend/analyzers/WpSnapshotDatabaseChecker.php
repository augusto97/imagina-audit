<?php
/**
 * Checks del snapshot sobre base de datos y contenido acumulado:
 * tamaño, autoload, revisiones, transients, metadata huérfana, spam,
 * papelera, cron y biblioteca de medios.
 *
 * Sub-checker de WpSnapshotAnalyzer.
 */

class WpSnapshotDatabaseChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeDatabase(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $totalSize = $db['total_db_size'] ?? 0;
        $totalRows = $db['total_rows'] ?? 0;
        $totalTables = $db['total_tables'] ?? 0;
        $humanSize = $db['total_db_size_human'] ?? '?';

        $tables = $db['tables'] ?? [];
        $topTables = array_slice($tables, 0, 5);
        $topTablesFmt = array_map(fn($t) => [
            'name' => $t['name'],
            'rows' => $t['rows'],
            'size' => $this->formatBytes($t['total_size']),
        ], $topTables);

        $gbSize = $totalSize / (1024 * 1024 * 1024);
        $score = $gbSize < 0.5 ? 100 : ($gbSize < 1 ? 80 : ($gbSize < 3 ? 60 : 30));

        return Scoring::createMetric(
            'db_size', 'Tamaño de la base de datos',
            $totalSize, $humanSize,
            $score,
            "La base de datos pesa $humanSize con $totalRows filas en $totalTables tablas. " . ($gbSize > 1 ? 'DB grande — considerar optimización.' : 'Tamaño razonable.'),
            $gbSize > 1 ? 'Limpiar revisiones, transients, meta huérfana. Considerar migrar datos históricos a archivos externos.' : '',
            'Optimizamos la base de datos eliminando datos innecesarios y creando índices.',
            ['totalSize' => $totalSize, 'humanSize' => $humanSize, 'totalRows' => $totalRows, 'totalTables' => $totalTables, 'topTables' => $topTablesFmt]
        );
    }

    public function analyzeDbEngine(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $tables = $db['tables'] ?? [];
        $myisamTables = [];
        foreach ($tables as $t) {
            $engine = $t['engine'] ?? ($t['Engine'] ?? '');
            if (stripos($engine, 'myisam') !== false) {
                $myisamTables[] = $t['name'] ?? '?';
            }
        }

        if (empty($myisamTables)) return null;

        $count = count($myisamTables);

        return Scoring::createMetric(
            'db_engine', 'Motor de base de datos',
            'MyISAM', "$count tablas con MyISAM",
            $count <= 2 ? 70 : 40,
            "$count tablas usan el motor MyISAM, que no soporta transacciones, bloqueo a nivel de fila, ni foreign keys. InnoDB es superior en rendimiento y confiabilidad.",
            'Convertir las tablas MyISAM a InnoDB con: ALTER TABLE nombre ENGINE=InnoDB;',
            'Migramos tablas a InnoDB para mejor rendimiento y confiabilidad.',
            ['count' => $count, 'tables' => array_slice($myisamTables, 0, 10)]
        );
    }

    public function analyzeAutoload(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $autoloadSize = $db['autoload_size'] ?? 0;
        $autoloadHuman = $db['autoload_size_human'] ?? '?';
        $autoloadedCount = $db['autoloaded_options'] ?? 0;
        $mb = $autoloadSize / (1024 * 1024);
        $score = $mb < 0.5 ? 100 : ($mb < 1 ? 80 : ($mb < 2 ? 50 : 20));

        return Scoring::createMetric(
            'autoload_size', 'Opciones autoload',
            $autoloadSize, "$autoloadHuman ($autoloadedCount opciones)",
            $score,
            $mb < 0.5
                ? "Autoload de $autoloadHuman con $autoloadedCount opciones. Dentro del rango saludable."
                : "Autoload de $autoloadHuman con $autoloadedCount opciones. Cada petición a WP carga estas opciones — un autoload grande ralentiza TODO el sitio.",
            $mb > 1 ? 'Identificar opciones autoload pesadas que no se necesiten en cada request y cambiar autoload=no. Usar plugins como "Autoload Options Monitor".' : '',
            'Optimizamos las opciones autoload reduciendo el peso de cada petición a WordPress.',
            ['size' => $autoloadSize, 'human' => $autoloadHuman, 'count' => $autoloadedCount]
        );
    }

    public function analyzeRevisions(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $revisions = $db['revisions_count'] ?? 0;
        $score = $revisions < 100 ? 100 : ($revisions < 500 ? 80 : ($revisions < 2000 ? 50 : 20));

        return Scoring::createMetric(
            'db_revisions', 'Revisiones en base de datos',
            $revisions, "$revisions revisiones",
            $score,
            $revisions < 100
                ? "$revisions revisiones. Cantidad normal."
                : "$revisions revisiones en la DB. Cada revisión ocupa espacio innecesario.",
            $revisions > 500 ? 'Limpiar revisiones antiguas y limitar con define("WP_POST_REVISIONS", 5) en wp-config.php.' : '',
            'Limpiamos revisiones antiguas y configuramos límites saludables.',
            ['count' => $revisions]
        );
    }

    public function analyzeTransients(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $transients = $db['transients_count'] ?? 0;
        $score = $transients < 500 ? 100 : ($transients < 2000 ? 80 : ($transients < 5000 ? 50 : 20));

        return Scoring::createMetric(
            'db_transients', 'Transients en base de datos',
            $transients, "$transients transients",
            $score,
            $transients < 500
                ? "$transients transients. Normal."
                : "$transients transients. Muchos transients (caches temporales) pueden quedarse huérfanos y acumularse.",
            $transients > 2000 ? 'Limpiar transients expirados. Considerar cache de objetos (Redis/Memcached) para reducirlos.' : '',
            'Configuramos cache de objetos y limpiamos transients huérfanos regularmente.',
            ['count' => $transients]
        );
    }

    public function analyzeOrphanedMeta(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $orphaned = $db['orphaned_postmeta'] ?? 0;
        if ($orphaned === 0) return null;
        $score = $orphaned < 100 ? 80 : ($orphaned < 1000 ? 50 : 20);

        return Scoring::createMetric(
            'orphaned_meta', 'Metadata huérfana',
            $orphaned, "$orphaned registros huérfanos",
            $score,
            "$orphaned registros en wp_postmeta referencian posts que ya no existen. Son datos basura acumulados.",
            'Limpiar metadata huérfana con un plugin de optimización DB o query manual.',
            'Limpiamos datos huérfanos acumulados en la base de datos.',
            ['count' => $orphaned]
        );
    }

    public function analyzeSpamComments(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $spam = $db['spam_comments'] ?? ($db['spam_comment_count'] ?? null);
        if ($spam === null || $spam === 0) return null;

        $score = $spam < 100 ? 80 : ($spam < 1000 ? 50 : 20);

        return Scoring::createMetric(
            'spam_comments', 'Comentarios spam',
            $spam, "$spam comentarios spam",
            $score,
            "$spam comentarios marcados como spam en la base de datos. Estos ocupan espacio y afectan el rendimiento de consultas.",
            'Vaciar la carpeta de spam desde Comentarios → Spam → Vaciar spam. Instalar Akismet o similar para prevención.',
            'Limpiamos spam acumulado y configuramos protección anti-spam efectiva.',
            ['count' => $spam]
        );
    }

    public function analyzeTrashedPosts(): ?array {
        $db = $this->getSection('database');
        $pt = $this->getSection('post_types');
        $trashed = $db['trashed_posts'] ?? ($pt['trashed_count'] ?? null);
        if ($trashed === null || $trashed === 0) return null;

        $score = $trashed < 50 ? 90 : ($trashed < 200 ? 70 : 40);

        return Scoring::createMetric(
            'trashed_posts', 'Posts en papelera',
            $trashed, "$trashed posts",
            $score,
            "$trashed posts en la papelera. Estos se mantienen en la DB ocupando espacio innecesario.",
            'Vaciar la papelera desde Posts → Papelera → Vaciar papelera. Configurar limpieza automática con EMPTY_TRASH_DAYS.',
            'Configuramos limpieza automática de la papelera para mantener la DB limpia.',
            ['count' => $trashed]
        );
    }

    public function analyzeCron(): ?array {
        $cron = $this->getSection('cron');
        if (empty($cron)) return null;

        $total = $cron['total_events'] ?? 0;
        $overdue = $cron['overdue_count'] ?? 0;
        $wpCronDisabled = ($this->getSection('environment')['wp_cron_disabled'] ?? false);

        $score = $overdue === 0 ? 100 : ($overdue < 5 ? 70 : 30);

        return Scoring::createMetric(
            'cron_status', 'Tareas programadas (cron)',
            $overdue, $overdue === 0 ? "$total tareas OK" : "$overdue atrasadas de $total",
            $score,
            $overdue === 0
                ? "$total cron jobs registrados, todos ejecutándose a tiempo." . ($wpCronDisabled ? ' WP_CRON está deshabilitado (debe tener cron real del servidor).' : '')
                : "Hay $overdue cron jobs atrasados de $total totales. Tareas automáticas no se están ejecutando correctamente.",
            $overdue > 0 ? 'Verificar que WP_CRON funcione. En sitios grandes, configurar un cron real del servidor y desactivar WP_CRON.' : '',
            'Configuramos cron real del servidor y monitoreamos tareas programadas.',
            ['total' => $total, 'overdue' => $overdue, 'wpCronDisabled' => $wpCronDisabled]
        );
    }

    public function analyzeMediaSize(): ?array {
        $media = $this->getSection('media');
        if (empty($media)) return null;

        $totalCount = $media['total_count'] ?? ($media['total_items'] ?? 0);
        $totalSize = $media['total_size'] ?? ($media['uploads_size'] ?? 0);
        $orphaned = $media['orphaned_count'] ?? ($media['unattached'] ?? null);

        if ($totalCount === 0 && $totalSize === 0) return null;

        $humanSize = $this->formatBytes($totalSize);
        $gbSize = $totalSize / (1024 * 1024 * 1024);

        $score = 100;
        if ($gbSize > 5) $score -= 30;
        elseif ($gbSize > 2) $score -= 15;
        if ($orphaned !== null && $orphaned > 50) $score -= 20;

        $desc = "$totalCount archivos de medios ($humanSize).";
        if ($orphaned !== null && $orphaned > 0) {
            $desc .= " $orphaned archivos no están asociados a ningún contenido (huérfanos).";
        }

        return Scoring::createMetric(
            'media_size', 'Biblioteca de medios',
            $totalCount, "$totalCount archivos · $humanSize",
            Scoring::clamp($score),
            $desc . ($gbSize > 2 ? ' La biblioteca es muy pesada — considerar limpieza y optimización de imágenes.' : ''),
            ($gbSize > 2 || ($orphaned !== null && $orphaned > 50))
                ? 'Eliminar medios no usados, comprimir imágenes con ShortPixel/Imagify, y servir en formato WebP.'
                : '',
            'Optimizamos la biblioteca de medios: compresión, formato WebP y limpieza de archivos no utilizados.',
            ['totalCount' => $totalCount, 'totalSize' => $totalSize, 'humanSize' => $humanSize, 'orphaned' => $orphaned]
        );
    }

    /**
     * Formatea bytes como KB/MB/GB legible.
     */
    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . 'B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . 'KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 1) . 'MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . 'GB';
    }
}
