# API Contract — Imagina Audit

Este proyecto mantiene un **contrato OpenAPI 3.1** (`openapi.yaml` en la raíz)
como fuente de verdad para todos los endpoints del backend. Los tipos
TypeScript del frontend se generan desde ese YAML, de modo que cualquier
divergencia entre backend y frontend falla en compile-time y no en producción.

## Regenerar los tipos TypeScript

Cada vez que cambies `openapi.yaml`:

```bash
cd frontend
npm run types:api
```

Esto sobreescribe `frontend/src/types/api.ts` con los tipos derivados del YAML.
No editar ese archivo a mano — se regenera.

## Cómo usar los tipos generados

```ts
import type { components, paths } from '@/types/api'

// Schemas compartidos
type AuditResult = components['schemas']['AuditResult']
type AppSettings = components['schemas']['AppSettings']

// Body/response de un endpoint específico
type AuditBody = paths['/audit.php']['post']['requestBody']['content']['application/json']
type AuditResponse = paths['/audit.php']['post']['responses']['200']['content']['application/json']
```

Para usos cotidianos, los tipos legibles siguen en `types/audit.ts` y
`types/admin.ts`. Conviven:

- **`types/audit.ts` / `types/admin.ts`** — tipos legibles, escritos a mano,
  usados por la app. Docstrings en español, nombres de propiedades estables.
- **`types/api.ts`** — generado. Úsalo como red de seguridad: si al regenerar
  aparecen diferencias con los tipos hechos a mano, significa que el contrato
  y el código están divergiendo.

Cuando quieras alinear los dos, importa un schema desde `api.ts` y compara:

```ts
import type { components } from '@/types/api'
import type { AuditResult } from '@/types/audit'

// Esto falla si los shapes difieren
const _check: AuditResult = {} as components['schemas']['AuditResult']
```

## Workflow recomendado al cambiar un endpoint

1. **Backend**: implementa el cambio.
2. **Contract**: edita `openapi.yaml` reflejando el nuevo shape.
3. **Regenerar**: `npm run types:api` en frontend.
4. **Build**: `npm run build`. Si algo revienta, es que el frontend asumía un
   shape viejo — úsalo como checklist de lo que hay que actualizar.
5. **Tests**: `npm test && (cd ../backend && ./vendor/bin/phpunit)`.

## Visualizar la API

Cualquier herramienta OpenAPI lee el YAML:

```bash
# Instalado globalmente — no es parte de las deps del proyecto
npx @redocly/cli preview-docs openapi.yaml
# o
npx @stoplight/spectral-cli lint openapi.yaml
```

## Cobertura actual

Todos los endpoints expuestos por `backend/api/` están documentados:

**Públicos** (CORS `*` sin credenciales):
- `POST /audit.php`
- `GET /audit-status.php`
- `GET /config.php`
- `GET /health.php`
- `POST /compare.php`
- `GET /history.php`

**Admin** (sesión + CSRF en mutaciones):
- Auth: `login.php`, `logout.php`, `session.php`
- Data: `dashboard.php`, `leads.php`, `lead-detail.php`, `checklist.php`,
  `waterfall.php`, `snapshot.php`, `export-leads.php`
- Config: `settings.php`, `vulnerabilities.php`, `update-vulnerabilities.php`,
  `test-email.php`

Schemas definidos: `AuditRequest`, `AuditResult`, `ModuleResult`,
`MetricResult`, `SolutionItem`, `TechStack`, `HostingInfo`, `DomainInfo`,
`SemaphoreLevel`, `AppSettings`, `Plan`, `LeadSummary`, `DashboardStats`,
`VulnerabilityEntry`, `SuccessEnvelope`, `ErrorEnvelope`.

Algunos endpoints internos (waterfall, snapshot) usan
`additionalProperties: true` porque su estructura interna es dinámica y
cambia según los datos de Google PageSpeed / wp-snapshot. Sus schemas
detallados viven en los tipos TypeScript manuales.
