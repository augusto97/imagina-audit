# P7 — Migración a Base de Datos de Producción

> Plan de trabajo y reglas para llevar la app de SQLite a una base de datos
> de producción real (MySQL/MariaDB) sin perder el modo "drop & go" para
> instalaciones pequeñas o de desarrollo.

---

## 1. Por qué migrar

La app hoy corre sobre **SQLite single-file**. Funciona pero tiene techo claro
para una app de producción real:

| Limitación de SQLite           | Impacto en producción                                    |
|--------------------------------|----------------------------------------------------------|
| Lock global en escrituras      | 2+ audits simultáneos serializan; queue se ralentiza     |
| Sin replicación / read replicas| No se puede escalar lecturas en pestañas admin pesadas   |
| Backup = copiar archivo .db    | No hay PITR ni snapshots consistentes con WAL en curso   |
| Tipos laxos (TEXT para todo)   | Datos sucios silenciosos; no hay constraints reales      |
| Sin roles/grants               | Cualquiera con el archivo lo lee/escribe                 |
| JSON como TEXT, sin índice     | Queries sobre `result_json` hacen full scan              |
| Migraciones a mano en código   | Sin historial, sin rollback, hard de auditar             |

Objetivo: mantener la sencillez actual para el comprador casual de CodeCanyon
**y** desbloquear deploys serios con tráfico real.

---

## 2. Decisiones arquitectónicas

### 2.1 Drivers soportados

- **MySQL 5.7+ / MariaDB 10.3+** → driver primario de producción.
  Razón: 100% de hostings cPanel lo traen, plug-and-play para el comprador
  de CodeCanyon.
- **SQLite** → modo dev / instalación mínima. Se mantiene como **opt-in**
  para entornos sin MySQL (laptops, demos, contenedores efímeros). NO es el
  default recomendado en producción.
- **PostgreSQL** → **fuera de scope ahora.** Si surge demanda lo añadimos
  en P8 (el abstraction layer lo permite, solo hay que portar dialect).

### 2.2 ORM vs SQL plano

**Seguimos sin ORM.** Razón: ya hay 30+ archivos PHP que usan PDO directo.
Cambiar a Eloquent/Doctrine sería rewriting el backend completo y el target
sigue siendo "PHP vanilla en hosting compartido".

En su lugar: un wrapper `Database` mejorado que:

- Selecciona el driver según `.env`.
- Expone helpers cross-driver (`upsert`, `now()`, `boolColumn()`, etc.).
- Detecta dialecto y emite SQL adecuado.

### 2.3 Configuración

`.env` nuevo:

```
# Driver de base de datos: 'mysql' (producción) o 'sqlite' (dev)
DB_DRIVER=mysql

# MySQL connection (solo si DB_DRIVER=mysql)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=imagina_audit
DB_USER=imagina_audit
DB_PASSWORD=changeme
DB_CHARSET=utf8mb4

# SQLite path (solo si DB_DRIVER=sqlite — default fallback)
DB_SQLITE_PATH=/imagina_audit_data/audit.db
```

---

## 3. Reglas de oro durante la implementación

Estas reglas son **vinculantes** para cualquier PR/commit que toque la DB
después de P7. Si una regla se rompe, el PR no entra.

1. **Nada de SQL específico de un driver en el código de negocio.** Toda
   query mutativa debe pasar por el wrapper si necesita upsert, timestamps,
   o booleanos.
2. **Sin `INSERT OR IGNORE`, `datetime('now')`, `AUTOINCREMENT`,
   `PRAGMA …` en archivos `*.sql` de migración.** Reemplazar por:
   - `INSERT … ON DUPLICATE KEY UPDATE` (MySQL) o `ON CONFLICT DO NOTHING`
     según el dialecto — el migrator transformará.
   - `CURRENT_TIMESTAMP` (ANSI).
   - `AUTO_INCREMENT` en MySQL, `AUTOINCREMENT` en SQLite — el migrator
     escoge según driver.
3. **Foreign keys siempre habilitadas.** Tanto en SQLite (`PRAGMA
   foreign_keys=ON`) como en MySQL (`SET FOREIGN_KEY_CHECKS=1`).
4. **Las migraciones son inmutables una vez "released"** (mergeadas a la
   rama de release). Para corregir, crear migración nueva. Nunca editar
   una migración ya enviada a clientes.
5. **Cada migración tiene número correlativo + descripción + dirección
   up/down.** Ej: `0007_add_audit_lang_index.up.sql` + `0007_….down.sql`.
6. **Todo método de `Database` que reciba input externo usa prepared
   statements.** Sin excepciones. Si alguna vez se ve `"…$id…"` concat,
   el PR se rechaza.
7. **Transacciones explícitas en cualquier mutación que toque ≥2 tablas.**
   Empezar siempre con `$db->transaction(fn() => …)` (helper nuevo).
8. **JSON: columna nativa cuando exista** (MySQL 5.7+). Para SQLite seguir
   guardando como TEXT pero con check.
9. **Sin queries dentro de loops.** Si el código necesita N+1, refactorizar
   con IN clauses o JOINs. Caso especial: bulk inserts usan
   `INSERT … VALUES (…),(…),(…)` armado en batches de 500.
10. **Indices revisados antes de cada release.** Cada nueva query que
    aparezca en hot path se acompaña de su índice o de un comentario
    `-- intentional full scan, low cardinality`.
11. **Slow query log activo en producción.** Cualquier query >200ms se
    loguea con stack trace para revisión.
12. **Backup antes de cualquier migración destructiva.** El runner toma un
    dump automáticamente si la migración contiene `DROP`, `ALTER … DROP`,
    o `DELETE`. Se rehúsa a aplicar si no puede hacer el dump.
13. **Datos sensibles (passwords, 2FA secrets, tokens) siempre hasheados
    o cifrados.** Nunca columnas TEXT planas con valores en claro.

---

## 4. Fases de trabajo

Cada fase termina con código en main + tests verdes + sección actualizada
en `CLAUDE.md`. **No avanzamos a la siguiente sin merge de la anterior.**

### Fase 1 — Capa de abstracción (sin cambio funcional)

Objetivo: que la app actual siga corriendo con SQLite, pero detrás de un
wrapper que en P7.2 pueda hablar MySQL.

Entregables:
- `backend/lib/Database.php` refactorizado:
  - `__construct()` lee `DB_DRIVER` del .env.
  - Helpers nuevos: `now()`, `upsert($table, $row, $uniqueKeys)`,
    `transaction(callable)`, `bool($v)`, `json($v)`.
  - DSN factory por driver.
- `backend/lib/db/SqliteDialect.php` + `MysqlDialect.php` (abstracción
  fina, sin lógica de negocio).
- Tests `tests/Database.test.php` que corren contra los dos drivers vía
  variable de entorno `TEST_DB_DRIVER`.

Rule check: la suite de tests existente sigue verde con SQLite.

### Fase 2 — Sistema de migraciones versionadas

Objetivo: matar el `runMigrations()` actual (try/catch silencioso) y
reemplazarlo por un runner que sepa qué se aplicó y qué no.

Entregables:
- Carpeta `backend/database/migrations/` con archivos `NNNN_descripcion.sql`.
- Tabla `schema_migrations(version INT PRIMARY KEY, applied_at)`.
- `backend/lib/Migrator.php` que:
  - Lista pendientes.
  - Aplica en orden numérico dentro de transacción (cuando el driver
    lo permite — MySQL DDL hace autocommit, lo sabemos).
  - Loguea cada aplicación.
  - Tiene método `rollback(N)` opcional (downs).
- CLI `backend/database/migrate.php` (para correr local / por cron):
  - `php migrate.php status`
  - `php migrate.php up`
  - `php migrate.php rollback`
- Hook en `bootstrap.php`: si hay migraciones pendientes y `APP_ENV=dev`,
  corre auto. En producción, solo si `MIGRATE_ON_BOOT=true` o por CLI.

### Fase 3 — Portar el schema existente a migraciones cross-driver

Objetivo: cero queries específicas de SQLite en el schema.

Entregables:
- `backend/database/migrations/0001_initial.sql` — todo el schema actual
  reescrito en ANSI SQL portable. Tipos cross-driver:
  | Concepto         | MySQL              | SQLite               |
  |------------------|--------------------|----------------------|
  | PK autoincr      | `BIGINT AUTO_INCREMENT` | `INTEGER`(rowid)|
  | UUID texto       | `CHAR(36)`         | `TEXT`               |
  | Timestamp now    | `DATETIME DEFAULT CURRENT_TIMESTAMP` | `TEXT DEFAULT (datetime('now'))` |
  | JSON             | `JSON`             | `TEXT` + check         |
  | Boolean          | `TINYINT(1)`       | `INTEGER`              |
  | Texto largo      | `TEXT` / `MEDIUMTEXT` | `TEXT`              |
  El migrator preprocesa el SQL para emitir la sintaxis correcta.
- Migraciones incrementales: una por cada `ALTER TABLE` que hoy vive en
  `Database::runMigrations()`. Ej:
  - `0002_audits_add_is_pinned.sql`
  - `0003_audits_add_lang.sql`
  - `0004_audits_add_user_id.sql`
  - `0005_audits_add_project_id.sql`
  - `0006_plans_add_max_projects.sql`
  - `0007_audits_add_is_deleted.sql`
  - `0008_languages_table.sql`
- Borrar `runMigrations()` y `schema.sql` legacy.

### Fase 4 — Setup wizard

Objetivo: el comprador descomprime el ZIP, va a `https://midominio/setup`,
mete sus credenciales MySQL y la app se autoconfigura.

Entregables:
- `backend/api/setup.php` mejorado:
  - Bloqueado tras primer uso (escribe `data/.installed`).
  - Test de conexión al driver elegido.
  - Crea schema vía migraciones.
  - Crea usuario admin inicial (email + password).
  - Genera `.env` (si writable) o muestra el contenido para que el user
    lo pegue manualmente.
- `frontend/src/pages/SetupPage.tsx` — wizard de 3 pasos: DB → admin
  → review. Solo visible si la app NO está instalada.
- Detección automática en `App.tsx`: si `/api/setup/status` retorna
  `installed:false`, redirige a `/setup`.

### Fase 5 — Migración de datos SQLite → MySQL

Objetivo: los compradores actuales que ya tienen data en SQLite pueden
moverla a MySQL en un paso.

Entregables:
- Script CLI `backend/database/migrate-from-sqlite.php`:
  - Lee config actual.
  - Crea conexión paralela al MySQL nuevo.
  - Dump tabla por tabla con respeto de FKs (orden topológico).
  - Bulk insert en MySQL en batches.
  - Verificación post-migración (counts por tabla).
- Endpoint admin `/admin/migrate-database.php`:
  - Solo accesible si DB_DRIVER actual == sqlite y .env tiene credenciales
    MySQL configuradas.
  - Corre el script con progress reporting.
- Documentación paso a paso en `docs/MIGRATING_FROM_SQLITE.md`.

### Fase 6 — Hardening de producción

Objetivo: ya con MySQL funcionando, blindar para tráfico real.

Entregables:
- **Connection pooling**: reuso de conexión PDO durante request (ya
  existe via singleton); retry con backoff exponencial en errores
  transientes (deadlock, connection reset).
- **Slow query log**: middleware en `Database::query()` que mide tiempo,
  loguea queries >200ms con stack trace y bind params.
- **Backup endpoint admin**:
  - `POST /admin/backup` produce dump SQL (`mysqldump --single-transaction`
    si está disponible, fallback a INSERT statements desde PHP).
  - Dump cifrado con clave del .env opcional.
  - Almacenado en `storage/backups/{timestamp}.sql.gz`.
  - Política de retención: último N backups (configurable).
- **Restore endpoint admin**:
  - Sube un dump, lo valida, lo aplica en una DB temporal, swap atómico.
  - Bloquea escrituras durante la ventana (maintenance mode).
- **Health endpoint extendido**: `/api/health` reporta conexión DB,
  latencia ping, pending migrations.
- **Sección de tuning en `CLAUDE.md`**: índices recomendados, valores
  `my.cnf`, ajuste `innodb_buffer_pool_size`.

---

## 5. Estructura de archivos resultante

```
backend/
├── api/
│   ├── setup.php                       # mejorado (P7.4)
│   └── admin/
│       ├── backup.php                  # nuevo (P7.6)
│       ├── restore.php                 # nuevo (P7.6)
│       └── migrate-database.php        # nuevo (P7.5)
├── database/
│   ├── migrations/                     # NUEVO (P7.2)
│   │   ├── 0001_initial.sql
│   │   ├── 0002_audits_add_is_pinned.sql
│   │   └── … (numeradas cronológicamente)
│   ├── migrate.php                     # CLI runner (P7.2)
│   ├── migrate-from-sqlite.php         # CLI migración (P7.5)
│   └── schema.sql                      # ELIMINADO al final de P7.3
├── lib/
│   ├── Database.php                    # refactor (P7.1)
│   ├── Migrator.php                    # nuevo (P7.2)
│   └── db/
│       ├── Dialect.php                 # interface (P7.1)
│       ├── MysqlDialect.php            # nuevo (P7.1)
│       └── SqliteDialect.php           # nuevo (P7.1)
└── storage/
    └── backups/                        # nuevo (P7.6)
        └── .htaccess
```

---

## 6. Testing

- **Matriz de tests obligatoria**: cada commit corre la suite contra los
  2 drivers (`TEST_DB_DRIVER=sqlite` y `TEST_DB_DRIVER=mysql`).
- **Setup de CI**: contenedor MySQL efímero (Docker en CI; en local el
  dev puede correr `docker run -d -p 3306:3306 -e MYSQL_ROOT_PASSWORD=…
  mysql:8`).
- **Tests nuevos a añadir**:
  - `Migrator.test.php` — up, rollback, idempotencia, detección de drift.
  - `Database.upsert.test.php` — ambos drivers, conflictos, partial
    updates.
  - `Backup.test.php` — round-trip dump → restore en SQLite efímera.
- **Smoke test post-migración**: `tests/postMigrate.sh` corre las queries
  más comunes y verifica counts.

---

## 7. Compatibilidad y migración para usuarios actuales

- **Instalaciones existentes con SQLite siguen funcionando** sin tocar
  nada. P7 NO rompe nada — solo añade MySQL como opción.
- **Upgrade path:**
  1. User edita `.env`: añade credenciales MySQL.
  2. Va a `/admin/migrate-database`.
  3. Click "Migrate now" → la app dumpea SQLite, importa a MySQL.
  4. Si todo OK, cambia `DB_DRIVER=mysql` en `.env` y reinicia.
  5. SQLite queda intacta como backup hasta que el user la borre a mano.
- **Downgrade hatch:** documentado en `docs/MIGRATING_FROM_SQLITE.md` — si
  algo falla, volver a `DB_DRIVER=sqlite` reactiva la base anterior.

---

## 8. Documentación a actualizar

- `CLAUDE.md` → sección "Base de datos": menciona drivers, defaults,
  reglas de oro resumidas.
- `README.md` → instrucciones de instalación con cualquiera de los 2
  drivers.
- `deploy/DEPLOY.md` → checklist con MySQL.
- `.env.example` → reflejar bloques DB_* con comentarios.
- `API.md` → endpoints nuevos de setup / backup / restore.
- Nuevo: `docs/MIGRATING_FROM_SQLITE.md`.

---

## 9. Preguntas abiertas — necesito que confirmes antes de empezar

1. **¿MySQL/MariaDB solo, o también PostgreSQL?**
   Recomiendo solo MySQL/MariaDB para esta fase. PostgreSQL queda como
   P8 si surge demanda real.

2. **¿Mantenemos SQLite como opción dev/fallback, o lo eliminamos?**
   Recomiendo mantenerlo como opt-in. Útil para demos, tests, hosting
   muy básico sin MySQL.

3. **¿Setup wizard obligatorio en primera carga o el admin edita
   `.env` a mano?**
   Recomiendo wizard — el target de CodeCanyon no quiere editar archivos
   por SSH.

4. **¿Backups automáticos por cron o solo manuales desde el panel?**
   Recomiendo ambos: manual siempre disponible, cron opcional con
   retención configurable.

5. **Versión mínima objetivo de MySQL/MariaDB.**
   Propongo: MySQL 5.7+ / MariaDB 10.3+. Cubre 99% de hostings cPanel
   actuales. Si quieres soportar versiones más viejas, perdemos `JSON`
   nativo y volvemos a TEXT.

6. **¿Plazo o prioridad de cada fase?**
   Las 6 fases pueden hacerse en orden estricto (1 sprint cada una). Si
   quieres acelerar, F1+F2+F3 son críticas; F4+F5+F6 son "nice for
   shipping pero no bloqueantes para que MySQL funcione".

---

## 10. Definition of Done

Damos P7 por cerrada cuando:

- [ ] App corre identica funcionalmente en SQLite y MySQL.
- [ ] `php migrate.php up` desde cero crea la base completa en ambos
      drivers.
- [ ] Suite de tests pasa contra ambos drivers en CI.
- [ ] `/setup` permite instalación limpia sin tocar archivos.
- [ ] `/admin/migrate-database` migra una install de SQLite a MySQL en
      una sola operación, sin pérdida de datos.
- [ ] Slow query log activo en producción, sin queries >500ms en
      operación normal.
- [ ] Backup + restore probado en un audit completo (con audits,
      snapshots, traducciones, todo).
- [ ] CLAUDE.md, README, .env.example y deploy docs actualizados.
- [ ] Nada de `INSERT OR IGNORE`, `datetime('now')`, `AUTOINCREMENT`
      hardcoded en el código de negocio (verificado con grep).

---

*Última actualización: 2026-05-18*
*Stack final: PHP 8+ vanilla, PDO, MySQL 5.7+ / SQLite 3 (opcional),
sin frameworks ni ORMs.*
