import { ImageIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Biblioteca de medios: cantidad, peso, breakdown por grupo y por MIME.
 * Los issues destacan ausencia de WebP y uploads muy pesados.
 */
export default function MediaSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.media.summary
  const total = Number(s.totalAttachments ?? 0)
  const hasWebp = report.media.mimeDetail.some(m => m.mime.includes('webp'))

  return (
    <SectionCard
      title={t('report.snap_media_title')}
      subtitle={t('report.snap_media_subtitle', { files: total, size: s.sizeHuman || '—' })}
      icon={<ImageIcon className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <KpiTile label={t('report.snap_media_kpi_files')} value={total} />
        <KpiTile label={t('report.snap_media_kpi_weight')} value={String(s.sizeHuman || '—')} />
        <KpiTile
          label={t('report.snap_media_kpi_formats')}
          value={report.media.mimeDetail.length}
          hint={hasWebp ? t('report.snap_media_hint_with_webp') : t('report.snap_media_hint_no_webp')}
          tone={hasWebp ? 'good' : 'warning'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Por grupo */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_media_by_type_title')}
          </h4>
          <div className="space-y-1.5">
            {report.media.byType.map((b) => {
              const pct = total > 0 ? (b.count / total) * 100 : 0
              return (
                <div key={b.group}>
                  <div className="flex justify-between text-[11px]">
                    <span className="capitalize font-medium">{b.group}</span>
                    <span className="text-[var(--text-tertiary)] tabular-nums">{b.count}</span>
                  </div>
                  <div className="mt-0.5 h-1.5 w-full rounded-full bg-[var(--bg-secondary)]">
                    <div className="h-full rounded-full bg-[var(--accent-primary)]" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              )
            })}
          </div>
        </div>

        {/* MIME detalle */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_media_kpi_mime_types')}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_mime')}</th>
                  <th className="px-2 py-1.5 text-right">{t('report.snap_col_files')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {report.media.mimeDetail.map((m) => (
                  <tr key={m.mime}>
                    <td className="px-2 py-1 font-mono">{m.mime}</td>
                    <td className="px-2 py-1 text-right tabular-nums">{m.count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="mt-4">
        <IssueList issues={report.media.issues} />
      </div>
    </SectionCard>
  )
}
