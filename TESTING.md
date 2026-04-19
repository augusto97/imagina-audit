# Testing — Imagina Audit

Guía para correr y extender la suite de tests. El tooling es solo de desarrollo —
nada se despliega al hosting.

## Backend (PHPUnit)

Requiere Composer local (no en producción).

```bash
cd backend
composer install              # solo la primera vez
./vendor/bin/phpunit --testdox
```

Coverage (requiere Xdebug o pcov):

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

### Qué se testea hoy

| Archivo | Qué cubre |
|---|---|
| `tests/Unit/JsonStoreTest.php` | Roundtrip gzip, compatibilidad con JSON plano legacy, edge cases, ratio de compresión |
| `tests/Unit/ScoringTest.php` | Umbrales de semáforo, promedio ponderado, skip de módulos nulos, conteo de issues, mapa de soluciones |
| `tests/Unit/UrlValidatorTest.php` | Normalización de URL, rechazo de IPs privadas/reservadas/metadata AWS, resolución de URLs relativas |
| `tests/Unit/LoggerTest.php` | Enmascarado de emails, rutas del servidor, tokens largos, claves sensibles (password, token, api_key) |
| `tests/Unit/FetcherTest.php` | Validación anti-SSRF por URL (via reflection), resolución de redirects relativos y bloqueo de esquemas peligrosos |

### Cómo añadir un test nuevo

1. Crear `tests/Unit/XxxTest.php` extendiendo `PHPUnit\Framework\TestCase`.
2. Usar atributos `#[Test]` y opcionalmente `#[DataProvider('casos')]`.
3. Si necesitas una clase con método privado, usa `ReflectionClass::getMethod()->setAccessible(true)` (ver `LoggerTest` y `FetcherTest` como referencia).
4. Clases del dominio se autoloadean desde `lib/` y `analyzers/` vía `tests/bootstrap.php`.

### Qué NO se testea todavía

- **Analyzers** (SecurityAnalyzer, SeoAnalyzer, etc.). Requieren fixtures HTML guardados + mocking de Fetcher. Candidatos claros para la siguiente iteración.
- **Endpoints HTTP** (audit.php, admin/*). Requieren fixture de SQLite en memoria y simulación de `$_SESSION`/`$_SERVER`.
- **Auth CSRF**. Requiere manipular `$_SESSION` — hacer un test de integración o refactorizar Auth para aceptar storage inyectado.

## Frontend (Vitest)

```bash
cd frontend
npm test              # corre una vez
npm run test:watch    # modo watch
npm run test:coverage # con coverage HTML en coverage/
```

### Qué se testea hoy

| Archivo | Qué cubre |
|---|---|
| `src/lib/utils.test.ts` | `cn()`, `formatMs()`, `formatCurrency()`, `getLevelColor/ClassName/Label()` |
| `src/store/authStore.test.ts` | Estado inicial y setters de Zustand (autenticación y CSRF token) |

### Cómo añadir un test nuevo

1. Crear `src/<path>/<nombre>.test.ts` (o `.test.tsx` para componentes).
2. Usar `describe`/`it`/`expect` de Vitest (globals activados en `vitest.config.ts`).
3. Para componentes React: añadir `@testing-library/react` y `@testing-library/jest-dom` como devDeps cuando hagas falta (aún no están — no hay tests de componentes).

### Qué NO se testea todavía

- **Componentes React** (AuditForm, TechnicalReport, WaterfallPage). Requiere setup de Testing Library.
- **Hooks** (useAuth, useAdmin, useAudit). Requieren mock de axios + Router context.
- **Integración con el backend** (end-to-end).

## Regla de cobertura

Objetivo realista: **60-70%** donde importa (lógica pura, validaciones de seguridad,
scoring, utilidades). NO apuntamos a 100% — los analyzers y componentes de UI se
cubrirán cuando se refactoricen (Puntos 1 y 2 del plan de reestructuración).

## CI

Todavía no hay GitHub Actions configurado. Para correr localmente antes de
commitear:

```bash
# Backend
(cd backend && ./vendor/bin/phpunit)

# Frontend
(cd frontend && npm test && npm run build)
```

Si se añade CI en el futuro, estos dos comandos son los que deberían correr en
cada PR.
