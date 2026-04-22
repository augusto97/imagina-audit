import { useState, useMemo } from 'react'
import { Puzzle, ExternalLink, Filter, ChevronDown, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile, StatusBadge, VulnIcon, YesNo } from './ui'
import type { SnapshotReport, PluginItem } from '@/types/snapshotReport'

type FilterKey = 'all' | 'active' | 'inactive' | 'outdated' | 'vulnerable' | 'safe'

/**
 * Tabla de plugins instalados con cruce de vulnerabilidades.
 * La columna principal es el status (safe/outdated/vulnerable/outdated_vulnerable)
 * — el operador puede filtrar por dimensión en un click.
 */
export default function PluginsSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.plugins.summary
  const items = report.plugins.items
  const [filter, setFilter] = useState<FilterKey>('all')
  const [expanded, setExpanded] = useState<string | null>(null)

  const filtered = useMemo(() => {
    switch (filter) {
      case 'active':      return items.filter(p => p.isActive)
      case 'inactive':    return items.filter(p => !p.isActive)
      case 'outdated':    return items.filter(p => p.hasUpdate)
      case 'vulnerable':  return items.filter(p => p.vulnerabilities.length > 0)
      case 'safe':        return items.filter(p => p.vulnerabilityStatus === 'safe' && p.isActive)
      default:            return items
    }
  }, [items, filter])

  return (
    <SectionCard
      title={t('report.snap_plugins_title')}
      subtitle={t('report.snap_plugins_subtitle', { total: s.total, active: s.active, outdated: s.outdated, vulnerable: s.vulnerable })}
      icon={<Puzzle className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* KPIs */}
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label={t('report.snap_plugins_kpi_total')} value={Number(s.total ?? 0)} />
        <KpiTile label={t('report.snap_plugins_kpi_active')} value={Number(s.active ?? 0)} tone="neutral" />
        <KpiTile
          label={t('report.snap_plugins_kpi_outdated')}
          value={Number(s.outdated ?? 0)}
          tone={Number(s.outdated ?? 0) > 0 ? 'warning' : 'good'}
        />
        <KpiTile
          label={t('report.snap_plugins_kpi_cve')}
          value={Number(s.vulnerable ?? 0)}
          tone={Number(s.vulnerable ?? 0) > 0 ? 'critical' : 'good'}
        />
      </div>

      {/* Filtros */}
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <Filter className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />
        <FilterChip active={filter === 'all'} onClick={() => setFilter('all')} label={t('report.snap_plugins_filter_all', { count: items.length })} />
        <FilterChip active={filter === 'active'} onClick={() => setFilter('active')} label={t('report.snap_plugins_filter_active', { count: items.filter(p => p.isActive).length })} />
        <FilterChip active={filter === 'inactive'} onClick={() => setFilter('inactive')} label={t('report.snap_plugins_filter_inactive', { count: items.filter(p => !p.isActive).length })} />
        <FilterChip active={filter === 'outdated'} onClick={() => setFilter('outdated')} label={t('report.snap_plugins_filter_outdated', { count: items.filter(p => p.hasUpdate).length })} />
        <FilterChip
          active={filter === 'vulnerable'}
          onClick={() => setFilter('vulnerable')}
          label={t('report.snap_plugins_filter_vulnerable', { count: items.filter(p => p.vulnerabilities.length > 0).length })}
          tone="danger"
        />
      </div>

      {/* Tabla */}
      <div className="overflow-x-auto rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="w-8 px-2 py-2" />
              <th className="px-3 py-2">{t('report.snap_col_plugin')}</th>
              <th className="px-3 py-2">{t('report.snap_col_version')}</th>
              <th className="px-3 py-2">{t('report.snap_col_status')}</th>
              <th className="px-3 py-2">{t('report.snap_plugins_col_active')}</th>
              <th className="px-3 py-2">{t('report.snap_plugins_col_auto_update')}</th>
              <th className="px-3 py-2">{t('report.snap_col_author')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="py-6 text-center text-[var(--text-tertiary)]">{t('report.snap_plugins_no_match')}</td></tr>
            ) : filtered.map((p) => (
              <PluginRow
                key={p.slug}
                p={p}
                isExpanded={expanded === p.slug}
                onToggle={() => setExpanded(expanded === p.slug ? null : p.slug)}
              />
            ))}
          </tbody>
        </table>
      </div>

      {/* MU-plugins y drop-ins */}
      {(report.plugins.muPlugins.length > 0 || report.plugins.dropins.length > 0) && (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
          <p className="mb-2 text-xs font-semibold text-amber-900">
            {t('report.snap_plugins_mudrop_title', { count: report.plugins.muPlugins.length + report.plugins.dropins.length })}
          </p>
          <p className="mb-2 text-[11px] text-amber-900/80">
            {t('report.snap_plugins_mudrop_intro')}
          </p>
          <ul className="space-y-1 text-[11px]">
            {report.plugins.muPlugins.map((m, i) => (
              <li key={`mu-${i}`} className="flex items-center gap-2">
                <span className="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-[10px]">{t('report.snap_plugins_mudrop_mu')}</span>
                <span className="font-medium">{m.name}</span>
                {m.version && <span className="text-[var(--text-tertiary)]">v{m.version}</span>}
                {m.author && <span className="text-[var(--text-tertiary)]">· {m.author}</span>}
                <code className="ml-auto text-[10px] text-[var(--text-tertiary)]">{m.file}</code>
              </li>
            ))}
            {report.plugins.dropins.map((d, i) => (
              <li key={`dr-${i}`} className="flex items-center gap-2">
                <span className="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-[10px]">{t('report.snap_plugins_mudrop_dropin')}</span>
                <span className="font-medium">{d.name}</span>
                {d.version && <span className="text-[var(--text-tertiary)]">v{d.version}</span>}
                {d.author && <span className="text-[var(--text-tertiary)]">· {d.author}</span>}
                <code className="ml-auto text-[10px] text-[var(--text-tertiary)]">{d.file}</code>
              </li>
            ))}
          </ul>
        </div>
      )}
    </SectionCard>
  )
}

function PluginRow({ p, isExpanded, onToggle }: { p: PluginItem; isExpanded: boolean; onToggle: () => void }) {
  const { t } = useTranslation()
  const hasDetails = p.vulnerabilities.length > 0 || p.description
  const rowTone =
    p.vulnerabilityStatus === 'vulnerable' || p.vulnerabilityStatus === 'outdated_vulnerable'
      ? 'bg-red-50/50'
      : p.hasUpdate ? 'bg-amber-50/30'
      : ''

  return (
    <>
      <tr className={`${rowTone} hover:bg-[var(--bg-secondary)]`}>
        <td className="px-2 py-2">
          {hasDetails ? (
            <button type="button" onClick={onToggle} className="text-[var(--text-tertiary)] hover:text-[var(--text-primary)]">
              {isExpanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
            </button>
          ) : null}
        </td>
        <td className="px-3 py-2">
          <div className="flex items-center gap-2">
            <VulnIcon status={p.vulnerabilityStatus} />
            <span className="font-medium text-[var(--text-primary)]">{p.name}</span>
            {p.uri && (
              <a href={p.uri} target="_blank" rel="noreferrer" className="text-[var(--text-tertiary)] hover:text-[var(--accent-primary)]">
                <ExternalLink className="h-3 w-3" />
              </a>
            )}
          </div>
          <div className="mt-0.5 font-mono text-[10px] text-[var(--text-tertiary)]">{p.slug}</div>
        </td>
        <td className="px-3 py-2 font-mono tabular-nums">
          {p.version || '—'}
          {p.hasUpdate && p.updateVersion && (
            <div className="text-[10px] text-amber-600">→ {p.updateVersion}</div>
          )}
        </td>
        <td className="px-3 py-2"><StatusBadge status={p.vulnerabilityStatus} /></td>
        <td className="px-3 py-2"><YesNo value={p.isActive} positive={true} /></td>
        <td className="px-3 py-2"><YesNo value={p.autoUpdate} positive={true} /></td>
        <td className="px-3 py-2 text-[var(--text-secondary)] truncate max-w-[200px]" title={p.author}>{p.author || '—'}</td>
      </tr>
      {isExpanded && hasDetails && (
        <tr className="bg-[var(--bg-secondary)]">
          <td colSpan={7} className="px-4 py-3">
            {p.description && (
              <p className="mb-3 text-[11px] leading-relaxed text-[var(--text-secondary)]">{p.description}</p>
            )}
            {p.vulnerabilities.length > 0 && (
              <div>
                <p className="mb-2 text-[11px] font-semibold text-red-700">
                  {t('report.snap_plugins_vuln_title', { count: p.vulnerabilities.length })}
                </p>
                <ul className="space-y-1 text-[11px]">
                  {p.vulnerabilities.map((v, i) => (
                    <li key={i} className="rounded border border-red-200 bg-red-50 p-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold text-red-900">{v.name}</span>
                        {v.cve && <code className="rounded bg-red-100 px-1 font-mono text-[10px]">{v.cve}</code>}
                        {v.cvssScore !== null && (
                          <span className="rounded bg-red-100 px-1.5 text-[10px] font-mono">CVSS {v.cvssScore}</span>
                        )}
                        <span className="ml-auto text-[10px] text-red-700">
                          {v.unfixed ? t('report.snap_plugins_vuln_unfixed') : v.fixedIn ? t('report.snap_plugins_vuln_fixed_in', { version: v.fixedIn }) : ''}
                        </span>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            )}
            <div className="mt-3 flex flex-wrap gap-3 text-[10px] text-[var(--text-tertiary)]">
              {p.requiresWp && <span>{t('report.snap_plugins_requires_wp', { version: p.requiresWp })}</span>}
              {p.requiresPhp && <span>{t('report.snap_plugins_requires_php', { version: p.requiresPhp })}</span>}
              {p.networkActive && <span>{t('report.snap_plugins_network_active')}</span>}
            </div>
          </td>
        </tr>
      )}
    </>
  )
}

function FilterChip({
  label, active, onClick, tone = 'default',
}: { label: string; active: boolean; onClick: () => void; tone?: 'default' | 'danger' }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors ${
        active
          ? (tone === 'danger' ? 'bg-red-600 text-white' : 'bg-[var(--accent-primary)] text-white')
          : 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--border-default)]'
      }`}
    >
      {label}
    </button>
  )
}
