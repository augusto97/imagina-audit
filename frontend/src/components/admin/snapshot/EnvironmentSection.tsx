import { Server, CheckCircle, XCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KeyValueList, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Stack WordPress + PHP + DB + servidor. Cuatro paneles lado a lado con
 * pares key:value, más el bloque de extensiones PHP como checklist.
 */
export default function EnvironmentSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const wp = report.environment.wordpress
  const php = report.environment.php
  const db = report.environment.database
  const server = report.environment.server

  return (
    <SectionCard
      title={t('report.snap_env_title')}
      subtitle={t('report.snap_env_subtitle')}
      icon={<Server className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="grid gap-3 lg:grid-cols-2">
        <Panel title={t('report.snap_env_panel_wp')}>
          <KeyValueList rows={[
            [t('report.snap_env_row_version'), <span className="font-mono">{String(wp.version)}</span>],
            [t('report.snap_env_row_latest'), <span className="font-mono text-[var(--text-tertiary)]">{String(wp.latest || '—')}</span>],
            [t('report.snap_env_row_locale_tz'), `${wp.locale || '—'} · ${wp.timezone || '—'}`],
            [t('report.snap_env_row_multisite'), <span className="font-mono">{wp.multisite ? t('report.snap_yes') : t('report.snap_no')}</span>],
            [t('report.snap_env_row_permalinks'), <code className="text-[11px]">{String(wp.permalinks || '—')}</code>],
            ['WP_DEBUG', <DebugIndicator on={Boolean(wp.debug)} display={Boolean(wp.debugDisplay)} />],
            [t('report.snap_env_row_memory_limit_wp'), <span className="font-mono">{String(wp.memoryLimit || '—')}</span>],
          ]} />
        </Panel>

        <Panel title={t('report.snap_env_panel_php')}>
          <KeyValueList rows={[
            [t('report.snap_env_row_version'), <span className="font-mono">{String(php.version)}</span>],
            ['memory_limit', <span className="font-mono">{String(php.memoryLimit)}</span>],
            ['max_execution', <span className="font-mono">{String(php.maxExecution)}s</span>],
            ['upload_max', <span className="font-mono">{String(php.maxUpload)}</span>],
            ['post_max_size', <span className="font-mono">{String(php.postMaxSize)}</span>],
            [t('report.snap_env_row_opcache'), <span className="font-mono">{php.opcacheEnabled ? t('report.snap_env_opcache_active') : t('report.snap_env_opcache_inactive')}</span>],
          ]} />
          <ExtensionsGrid extensions={php.extensions as Record<string, boolean>} missing={php.missingExtensions as string[]} />
        </Panel>

        <Panel title={t('report.snap_env_panel_db')}>
          <KeyValueList rows={[
            [t('report.snap_env_row_engine'), <span className="font-mono">{String(db.type || '—')}</span>],
            [t('report.snap_env_row_version'), <span className="font-mono">{String(db.version || '—')}</span>],
            [t('report.snap_env_row_server_info'), <span className="font-mono text-[10px]">{String(db.serverInfo || '—')}</span>],
          ]} />
        </Panel>

        <Panel title={t('report.snap_env_panel_server')}>
          <KeyValueList rows={[
            [t('report.snap_env_row_software'), <span className="font-mono">{String(server.software || '—')}</span>],
            [t('report.snap_env_row_os'), <span className="font-mono">{String(server.os || '—')}</span>],
            [t('report.snap_env_row_https'), <span className="font-mono">{server.isHttps ? t('report.snap_env_https_active') : t('report.snap_env_https_plain')}</span>],
            [t('report.snap_env_row_htaccess'), <span className="font-mono">{server.htaccessWritable ? t('report.snap_env_htaccess_writable') : t('report.snap_env_htaccess_readonly')}</span>],
          ]} />
        </Panel>
      </div>

      <div className="mt-4">
        <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
          {t('report.snap_env_actionable')}
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
  const { t } = useTranslation()
  if (!on) return <span className="font-mono text-emerald-600">{t('report.snap_off')}</span>
  if (display) return <span className="font-mono text-red-600">{t('report.snap_env_debug_on_display')}</span>
  return <span className="font-mono text-amber-600">{t('report.snap_env_debug_on_log')}</span>
}

function ExtensionsGrid({ extensions, missing }: { extensions: Record<string, boolean>; missing: string[] }) {
  const { t } = useTranslation()
  const entries = Object.entries(extensions)
  if (entries.length === 0) return null
  return (
    <div className="mt-3 border-t border-[var(--border-default)] pt-2">
      <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        {t('report.snap_env_extensions_label')} {missing.length > 0 && <span className="text-red-600">{t('report.snap_env_extensions_missing', { count: missing.length })}</span>}
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
