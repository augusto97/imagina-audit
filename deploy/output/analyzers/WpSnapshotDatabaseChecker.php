<?php
/**
 * Checks del snapshot sobre base de datos, contenido acumulado, cron y medios.
 *
 * Sub-checker de WpSnapshotAnalyzer. Lee la estructura real de
 * sections.database.data, sections.cron.data y sections.media.data.
 */

class WpSnapshotDatabaseChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeDbSize(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $totalSize = (int) ($db['total_db_size'] ?? 0);
        $humanSize = $db['total_db_size_human'] ?? '?';
        $totalRows = (int) ($db['total_rows'] ?? 0);
        $totalTables = (int) ($db['total_tables'] ?? 0);

        if ($totalSize === 0) return null;

        $mb = $totalSize / (1024 * 1024);
        $score = $mb < 200 ? 100 : ($mb < 500 ? 85 : ($mb < 1500 ? 60 : 30));

        // Top 5 tablas por tamaño
        $tables = $db['tables'] ?? [];
        $sorted = $tables;
        usort($sorted, fn($a, $b) => ($b['total_size'] ?? 0) <=> ($a['total_size'] ?? 0));
        $topTables = array_map(fn($t) => [
            'name' => $t['name'] ?? '?',
            'rows' => (int) ($t['rows'] ?? 0),
            'sizeMb' => round(((int) ($t['total_size'] ?? 0)) / (1024 * 1024), 1),
            'engine' => $t['engine'] ?? '',
        ], array_slice($sorted, 0, 10));

        return Scoring::createMetric(
            'db_size', 'Tamaño de la base de datos',
            $humanSize,
            "$humanSize · $totalRows filas · $totalTables tablas",
            $score,
            $mb < 200
                ? "Base de datos de $humanSize ($totalRows filas, $totalTables tablas). Tamaño saludable."
                : "Base de datos de $humanSize — " . ($mb > 1500 ? 'CRÍTICO: DB muy pesada' : 'grande') . ". En las tablas top se ve dónde está el peso (ver detalles).",
            $mb > 500 ? 'Revisar las tablas top: plugins de seguridad (Wordfence = wfHits, wfLogins), orders (WooCommerce), logs. Muchas veces un plugin acumula logs sin rotación.' : '',
            'Optimizamos la DB: purgamos logs de plugins, ajustamos retención, y añadimos índices donde hace falta.',
            ['totalSize' => $totalSize, 'humanSize' => $humanSize, 'totalRows' => $totalRows, 'totalTables' => $totalTables, 'topTables' => $topTables]
        );
    }

    public function analyzeAutoload(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $autoloadSize = (int) ($db['autoload_size'] ?? 0);
        $autoloadHuman = $db['autoload_size_human'] ?? '?';
        $count = (int) ($db['autoloaded_options'] ?? 0);
        if ($autoloadSize === 0) return null;

        $mb = $autoloadSize / (1024 * 1024);
        $score = $mb < 0.5 ? 100 : ($mb < 1 ? 85 : ($mb < 3 ? 55 : 20));

        return Scoring::createMetric(
            'db_autoload', 'Opciones autoload',
            $autoloadHuman,
            "$autoloadHuman · $count opciones",
            $score,
            $mb < 0.5
                ? "Autoload de $autoloadHuman con $count opciones. Saludable (<512 KB es lo deseable)."
                : "Autoload pesado ($autoloadHuman, $count opciones). Cada request a WP carga TODAS estas opciones en memoria — un autoload de varios MB ralentiza absolutamente todo el sitio.",
            $mb > 1
                ? 'Instalar plugin "WP-Optimize" o "Autoload Options Monitor" para identificar qué opciones pesan más. Muchas veces plugins desactivados dejan basura con autoload=yes.'
                : '',
            'Limpiamos opciones autoload pesadas y configuramos buenas prácticas.',
            ['size' => $autoloadSize, 'human' => $autoloadHuman, 'count' => $count]
        );
    }

    public function analyzeDbEngine(): ?array {
        $db = $this->getSection('database');
        $tables = $db['tables'] ?? [];
        if (empty($tables)) return null;

        $myisam = [];
        foreach ($tables as $t) {
            $engine = strtolower((string) ($t['engine'] ?? ''));
            if ($engine === 'myisam') {
                $myisam[] = [
                    'name' => $t['name'] ?? '?',
                    'rows' => (int) ($t['rows'] ?? 0),
                    'sizeMb' => round(((int) ($t['total_size'] ?? 0)) / (1024 * 1024), 1),
                ];
            }
        }
        if (empty($myisam)) return null;

        $count = count($myisam);
        $score = $count <= 2 ? 75 : ($count <= 10 ? 55 : 35);

        return Scoring::createMetric(
            'db_engine', 'Motor de base de datos',
            $count,
            "$count tablas con MyISAM",
            $score,
            "$count tablas usan MyISAM. Sin transacciones, sin row-level locking, sin foreign keys. InnoDB es superior en rendimiento y concurrencia.",
            'Convertir a InnoDB: ALTER TABLE nombre_tabla ENGINE=InnoDB; (una por una, empezando por las más pequeñas). Hacer backup antes.',
            'Migramos tablas MyISAM a InnoDB para concurrencia y rendimiento.',
            ['count' => $count, 'tables' => array_slice($myisam, 0, 15)]
        );
    }

    public function analyzeRevisions(): ?array {
        $db = $this->getSection('database');
        $revisions = (int) ($db['revisions_count'] ?? 0);
        if ($revisions === 0) return null;

        $score = $revisions < 100 ? 100 : ($revisions < 500 ? 90 : ($revisions < 2000 ? 65 : 30));

        return Scoring::createMetric(
            'db_revisions', 'Revisiones de posts',
            $revisions,
            "$revisions revisiones",
            $score,
            $revisions < 100
                ? "$revisions revisiones acumuladas. Cantidad normal."
                : "$revisions revisiones ocupando espacio en wp_posts. Cada edición genera una revisión nueva sin límite por defecto.",
            $revisions > 500 ? 'Limitar revisiones: en wp-config.php, define("WP_POST_REVISIONS", 5). Limpiar las antiguas con WP-Optimize o plugin similar.' : '',
            'Limpiamos revisiones antiguas y limitamos las futuras.',
            ['count' => $revisions]
        );
    }

    public function analyzeTransients(): ?array {
        $db = $this->getSection('database');
        $t = (int) ($db['transients_count'] ?? 0);
        if ($t === 0) return null;

        $score = $t < 300 ? 100 : ($t < 1000 ? 85 : ($t < 5000 ? 50 : 25));

        return Scoring::createMetric(
            'db_transients', 'Transients en options',
            $t,
            "$t transients",
            $score,
            $t < 300
                ? "$t transients. Normal."
                : "$t transients. Muchos plugins dejan transients expirados que se acumulan — WP no los limpia solo si no usan TTL correcto.",
            $t > 1000 ? 'Limpiar con WP-Optimize. Configurar cache de objetos (Redis) para que los transients vayan a memoria en vez de a wp_options.' : '',
            'Configuramos Redis object cache para que transients no toquen la DB.',
            ['count' => $t]
        );
    }

    public function analyzeOrphanedMeta(): ?array {
        $db = $this->getSection('database');
        $orphaned = (int) ($db['orphaned_postmeta'] ?? 0);
        if ($orphaned === 0) return null;

        $score = $orphaned < 100 ? 80 : ($orphaned < 1000 ? 55 : 25);

        return Scoring::createMetric(
            'db_orphaned_meta', 'Metadata huérfana',
            $orphaned,
            "$orphaned registros huérfanos",
            $score,
            "$orphaned registros en wp_postmeta apuntan a posts que ya no existen. Son datos basura acumulados por plugins que no limpian al borrar posts.",
            'Limpiar con WP-Optimize o SQL: DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;',
            'Limpiamos metadata huérfana y otros residuos de la DB.',
            ['count' => $orphaned]
        );
    }

    public function analyzeCron(): ?array {
        $cron = $this->getSection('cron');
        if (empty($cron)) return null;

        $total = (int) ($cron['total_events'] ?? 0);
        $overdue = (int) ($cron['overdue_count'] ?? 0);
        $wpCronDisabled = (bool) ($cron['wp_cron_disabled'] ?? false);
        $alternate = (bool) ($cron['alternate_cron'] ?? false);

        $score = $overdue === 0 ? 100 : ($overdue < 5 ? 70 : ($overdue < 20 ? 50 : 25));

        // Tareas próximas (nombres de hooks)
        $events = $cron['events'] ?? [];
        $upcomingHooks = [];
        $seen = [];
        foreach ($events as $e) {
            $hook = $e['hook'] ?? '';
            if ($hook === '' || isset($seen[$hook])) continue;
            $seen[$hook] = true;
            $upcomingHooks[] = $hook;
            if (count($upcomingHooks) >= 20) break;
        }

        return Scoring::createMetric(
            'cron_status', 'Tareas programadas (WP Cron)',
            $overdue,
            $overdue === 0 ? "$total tareas OK" : "$overdue atrasadas de $total",
            $score,
            $overdue === 0
                ? "$total cron jobs registrados, ejecutando a tiempo." . ($wpCronDisabled ? ' WP_CRON está deshabilitado (debería haber cron real del servidor configurado).' : '')
                : "$overdue de $total cron jobs atrasados. Tareas automáticas (actualizaciones, emails, backups) no se están ejecutando.",
            $overdue > 0
                ? ($wpCronDisabled
                    ? 'Verificar que el cron del servidor esté llamando a wp-cron.php cada minuto.'
                    : 'En sitios con tráfico bajo, WP_CRON no se dispara. Configurar cron real del sistema: */5 * * * * wget -qO- https://tu-sitio.com/wp-cron.php')
                : '',
            'Configuramos cron real del servidor para que las tareas se ejecuten a tiempo.',
            ['total' => $total, 'overdue' => $overdue, 'wpCronDisabled' => $wpCronDisabled, 'alternate' => $alternate, 'hooks' => $upcomingHooks]
        );
    }

    public function analyzeMedia(): ?array {
        $media = $this->getSection('media');
        if (empty($media)) return null;

        $count = (int) ($media['total_attachments'] ?? 0);
        $size = (int) ($media['upload_dir_size'] ?? 0);
        $humanSize = $media['upload_dir_size_human'] ?? '?';
        $mimeSummary = $media['mime_summary'] ?? [];

        if ($count === 0 && $size === 0) return null;

        $gb = $size / (1024 * 1024 * 1024);
        $score = $gb < 1 ? 100 : ($gb < 5 ? 80 : ($gb < 15 ? 55 : 25));

        // Detalles de mime types
        $mimeDetail = [];
        foreach ($mimeSummary as $group => $info) {
            if (is_array($info)) {
                $mimeDetail[] = [
                    'group' => $group,
                    'count' => (int) ($info['count'] ?? 0),
                    'sizeMb' => round(((int) ($info['size'] ?? 0)) / (1024 * 1024), 1),
                ];
            }
        }

        return Scoring::createMetric(
            'media_library', 'Biblioteca de medios',
            $count,
            "$count archivos · $humanSize",
            $score,
            $gb < 1
                ? "$count archivos ($humanSize) en la biblioteca. Tamaño razonable."
                : "$count archivos ocupando $humanSize. " . ($gb > 5 ? 'La biblioteca es pesada — probablemente hay imágenes sin comprimir ni convertir a WebP.' : 'Optimizable con compresión y WebP.'),
            $gb > 1 ? 'Instalar ShortPixel o Imagify para comprimir y convertir a WebP automáticamente. Configurar lazy loading (WP ya lo hace desde 5.5).' : '',
            'Comprimimos imágenes, las convertimos a WebP y servimos via CDN.',
            ['count' => $count, 'size' => $size, 'humanSize' => $humanSize, 'mimeSummary' => $mimeDetail]
        );
    }

    public function analyzePostTypes(): ?array {
        $pt = $this->getSection('post_types');
        if (empty($pt)) return null;

        $total = (int) ($pt['total_post_types'] ?? 0);
        $custom = (int) ($pt['custom_count'] ?? 0);
        if ($total === 0) return null;

        $list = $pt['post_types'] ?? [];
        $customList = array_values(array_filter($list, fn($p) => !($p['is_builtin'] ?? true)));
        $customSummary = array_map(fn($p) => [
            'slug' => $p['slug'] ?? '?',
            'label' => $p['label'] ?? '',
            'public' => (bool) ($p['is_public'] ?? false),
            'hasArchive' => (bool) ($p['has_archive'] ?? false),
            'inRest' => (bool) ($p['show_in_rest'] ?? false),
        ], array_slice($customList, 0, 15));

        return Scoring::createMetric(
            'custom_post_types', 'Tipos de contenido',
            $custom,
            "$custom custom · $total total",
            null,
            $custom === 0
                ? 'Solo se usan los tipos nativos de WP (posts, pages). Estructura simple.'
                : "$custom tipos de contenido personalizados (CPTs) registrados por plugins/tema. Pueden afectar rendimiento si se abusa del REST (show_in_rest=true expone todo el contenido).",
            '',
            'Auditamos los CPTs y optimizamos queries/índices para los que manejan mucho contenido.',
            ['total' => $total, 'custom' => $custom, 'customTypes' => $customSummary]
        );
    }

    public function analyzeRestApi(): ?array {
        $rest = $this->getSection('rest_api');
        if (empty($rest)) return null;

        $total = (int) ($rest['total_routes'] ?? 0);
        $namespaces = $rest['namespaces'] ?? [];
        $byNs = $rest['by_namespace'] ?? [];

        if ($total === 0) return null;

        // Muchas rutas = plugins que exponen mucho vía REST (normal)
        // Pero >1000 puede indicar bloat extremo
        $score = $total < 300 ? 100 : ($total < 800 ? 85 : ($total < 1500 ? 65 : 40));

        $topNamespaces = [];
        foreach ($byNs as $ns => $info) {
            $topNamespaces[] = ['namespace' => $ns, 'routes' => is_array($info) ? (int) ($info['count'] ?? count($info)) : (int) $info];
        }
        usort($topNamespaces, fn($a, $b) => $b['routes'] <=> $a['routes']);
        $topNamespaces = array_slice($topNamespaces, 0, 10);

        return Scoring::createMetric(
            'rest_api_routes', 'Rutas REST API',
            $total,
            "$total rutas en " . count($namespaces) . ' namespaces',
            $score,
            $total < 300
                ? "$total rutas REST. Volumen normal para un sitio WordPress."
                : "$total rutas REST expuestas. Cada plugin añade endpoints; demasiados indican plugin bloat y potencialmente datos expuestos.",
            $total > 800 ? 'Auditar qué plugins exponen tantas rutas. Considerar si alguno puede desactivarse o si el REST debe restringirse a usuarios autenticados.' : '',
            'Restringimos y auditamos endpoints REST para reducir superficie de ataque.',
            ['total' => $total, 'namespaces' => $namespaces, 'topNamespaces' => $topNamespaces]
        );
    }
}
