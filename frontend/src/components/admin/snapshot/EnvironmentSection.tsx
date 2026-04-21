import { Server, CheckCircle, XCircle } from 'lucide-react'
import { SectionCard, KeyValueList, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Stack WordPress + PHP + DB + servidor. Cuatro paneles lado a lado con
 * pares key:value, más el bloque de extensiones PHP como checklist.
 */
export default function EnvironmentSection({ report }: { report: SnapshotReport }) {
  const wp = report.environment.wordpress
  const php = report.environment.php
  const db = report.environment.database
  const server = report.environment.server

  return (
    <SectionCard
      title="Entorno y servidor"
      subtitle="Stack real del sitio"
      icon={<Server className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="grid gap-3 lg:grid-cols-2">
        <Panel title="WordPress">
          <KeyValueList rows={[
            ['Versión', <span className="font-mono">{String(wp.version)}</span>],
            ['Última conocida', <span className="font-mono text-[var(--text-tertiary)]">{String(wp.latest || '—')}</span>],
            ['Idioma / TZ', `${wp.locale || '—'} · ${wp.timezone || '—'}`],
            ['Multisite', <span className="font-mono">{wp.multisite ? 'Sí' : 'No'}</span>],
            ['Permalinks', <code className="text-[11px]">{String(wp.permalinks || '—')}</code>],
            ['WP_DEBUG', <DebugIndicator on={Boolean(wp.debug)} display={Boolean(wp.debugDisplay)} />],
            ['Memory limit (WP)', <span className="font-mono">{String(wp.memoryLimit || '—')}</span>],
          ]} />
        </Panel>

        <Panel title="PHP">
          <KeyValueList rows={[
            ['Versión', <span className="font-mono">{String(php.version)}</span>],
            ['memory_limit', <span className="font-mono">{String(php.memoryLimit)}</span>],
            ['max_execution', <span className="font-mono">{String(php.maxExecution)}s</span>],
            ['upload_max', <span className="font-mono">{String(php.maxUpload)}</span>],
            ['post_max_size', <span className="font-mono">{String(php.postMaxSize)}</span>],
            ['OPcache', <span className="font-mono">{php.opcacheEnabled ? 'Activo' : 'Inactivo'}</span>],
          ]} />
          <ExtensionsGrid extensions={php.extensions as Record<string, boolean>} missing={php.missingExtensions as string[]} />
        </Panel>

        <Panel title="Base de datos">
          <KeyValueList rows={[
            ['Motor', <span className="font-mono">{String(db.type || '—')}</span>],
            ['Versión', <span className="font-mono">{String(db.version || '—')}</span>],
            ['Info del server', <span className="font-mono text-[10px]">{String(db.serverInfo || '—')}</span>],
          ]} />
        </Panel>

        <Panel title="Servidor web">
          <KeyValueList rows={[
            ['Software', <span className="font-mono">{String(server.software || '—')}</span>],
            ['Sistema', <span className="font-mono">{String(server.os || '—')}</span>],
            ['HTTPS', <span className="font-mono">{server.isHttps ? 'Activo' : 'HTTP'}</span>],
            ['.htaccess', <span className="font-mono">{server.htaccessWritable ? 'Escribible' : 'Read-only'}</span>],
          ]} />
        </Panel>
      </div>

      <div className="mt-4">
        <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
          Hallazgos accionables
        </h4>
        <IssueList issues={report.environment.issues} />
      </div>
    </SectionCard>
  )
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-[var(--border-default)] p-3">
      <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{title}</p>
      {children}
    </div>
  )
}

function DebugIndicator({ on, display }: { on: boolean; display: boolean }) {
  if (!on) return <span className="font-mono text-emerald-600">OFF</span>
  if (display) return <span className="font-mono text-red-600">ON + DISPLAY</span>
  return <span className="font-mono text-amber-600">ON (log)</span>
}

function ExtensionsGrid({ extensions, missing }: { extensions: Record<string, boolean>; missing: string[] }) {
  const entries = Object.entries(extensions)
  if (entries.length === 0) return null
  return (
    <div className="mt-3 border-t border-[var(--border-default)] pt-2">
      <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        Extensiones PHP {missing.length > 0 && <span className="text-red-600">· {missing.length} faltantes</span>}
      </p>
      <div className="flex flex-wrap gap-1">
        {entries.map(([name, enabled]) => (
          <span key={name} className={`inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-mono ${
            enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'
          }`}>
            {enabled ? <CheckCircle className="h-2.5 w-2.5" /> : <XCircle className="h-2.5 w-2.5" />}
            {name}
          </span>
        ))}
      </div>
    </div>
  )
}
