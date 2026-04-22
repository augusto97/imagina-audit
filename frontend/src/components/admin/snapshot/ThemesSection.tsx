import { Palette, CheckCircle, ExternalLink } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Temas instalados con resaltado del activo y flags relevantes
 * (child theme, block theme, update disponible).
 */
export default function ThemesSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.themes.summary

  return (
    <SectionCard
      title={t('report.snap_themes_title')}
      subtitle={t('report.snap_themes_subtitle', {
        total: s.total,
        name: s.activeName || '—',
        suffix: s.isChild ? t('report.snap_themes_subtitle_child') : '',
      })}
      icon={<Palette className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 overflow-hidden rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="px-3 py-2" />
              <th className="px-3 py-2">{t('report.snap_col_theme')}</th>
              <th className="px-3 py-2">{t('report.snap_col_version')}</th>
              <th className="px-3 py-2">{t('report.snap_col_type')}</th>
              <th className="px-3 py-2">{t('report.snap_col_update')}</th>
              <th className="px-3 py-2">{t('report.snap_col_author')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {report.themes.items.map((th) => {
              const isActive = Boolean(th.isActive)
              const isChild = Boolean(th.isChildTheme)
              const isBlock = Boolean(th.isBlockTheme)
              const hasUpdate = Boolean(th.hasUpdate)
              return (
                <tr key={String(th.slug) || String(th.name)} className={isActive ? 'bg-emerald-50/30' : 'hover:bg-[var(--bg-secondary)]'}>
                  <td className="w-8 px-3 py-2">
                    {isActive ? <CheckCircle className="h-4 w-4 text-emerald-600" strokeWidth={2} /> : null}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-[var(--text-primary)]">{String(th.name)}</span>
                      {th.uri ? (
                        <a href={String(th.uri)} target="_blank" rel="noreferrer" className="text-[var(--text-tertiary)] hover:text-[var(--accent-primary)]">
                          <ExternalLink className="h-3 w-3" />
                        </a>
                      ) : null}
                    </div>
                    <div className="mt-0.5 font-mono text-[10px] text-[var(--text-tertiary)]">{String(th.slug || '')}</div>
                  </td>
                  <td className="px-3 py-2 font-mono tabular-nums">{String(th.version || '—')}</td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-1">
                      {isChild && <Tag label={t('report.snap_themes_tag_child')} tone="blue" />}
                      {!isChild && isActive && <Tag label={t('report.snap_themes_tag_parent')} tone="gray" />}
                      {isBlock && <Tag label={t('report.snap_themes_tag_block')} tone="purple" />}
                      {!isActive && <Tag label={t('report.snap_themes_tag_inactive')} tone="gray" />}
                    </div>
                    {th.parent ? (
                      <div className="mt-0.5 text-[10px] text-[var(--text-tertiary)]">{t('report.snap_themes_parent_label')} {String(th.parent)}</div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2">
                    {hasUpdate
                      ? <span className="font-mono text-[10px] text-amber-700">{t('report.snap_themes_update_available')}</span>
                      : <span className="font-mono text-[10px] text-emerald-600">{t('report.snap_themes_update_ok')}</span>}
                  </td>
                  <td className="px-3 py-2 truncate max-w-[180px] text-[var(--text-secondary)]" title={String(th.author || '')}>
                    {String(th.author || '—')}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        {t('report.snap_themes_actionable')}
      </h4>
      <IssueList issues={report.themes.issues} />
    </SectionCard>
  )
}

function Tag({ label, tone }: { label: string; tone: 'blue' | 'gray' | 'purple' }) {
  const tones = {
    blue:   'bg-blue-100 text-blue-800',
    gray:   'bg-gray-100 text-gray-700',
    purple: 'bg-purple-100 text-purple-800',
  }
  return <span className={`rounded px-1 py-0.5 text-[10px] font-medium ${tones[tone]}`}>{label}</span>
}
