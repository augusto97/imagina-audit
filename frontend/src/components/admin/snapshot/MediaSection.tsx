import { ImageIcon } from 'lucide-react'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Biblioteca de medios: cantidad, peso, breakdown por grupo y por MIME.
 * Los issues destacan ausencia de WebP y uploads muy pesados.
 */
export default function MediaSection({ report }: { report: SnapshotReport }) {
  const s = report.media.summary
  const total = Number(s.totalAttachments ?? 0)

  return (
    <SectionCard
      title="Biblioteca de medios"
      subtitle={`${total} archivos · ${s.sizeHuman || '—'}`}
      icon={<ImageIcon className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <KpiTile label="Archivos" value={total} />
        <KpiTile label="Peso total" value={String(s.sizeHuman || '—')} />
        <KpiTile
          label="Formatos"
          value={report.media.mimeDetail.length}
          hint={report.media.mimeDetail.some(m => m.mime.includes('webp')) ? 'incluye WebP' : 'sin WebP'}
          tone={report.media.mimeDetail.some(m => m.mime.includes('webp')) ? 'good' : 'warning'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Por grupo */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            Por tipo
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
            MIME types
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">MIME</th>
                  <th className="px-2 py-1.5 text-right">Archivos</th>
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
