import { ShieldCheck } from 'lucide-react'
import { SectionCard, IssueList, SeverityIcon } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Los 11 checks precalculados por wp-snapshot + los issues accionables.
 * Cada check es una config que el operador solo puede ver estando dentro
 * del sitio — aquí los consolidamos para el reporte técnico.
 */
export default function SecuritySection({ report }: { report: SnapshotReport }) {
  const s = report.security.summary

  return (
    <SectionCard
      title="Seguridad (checks internos)"
      subtitle={`${s.critical} críticos · ${s.warning} warnings · ${s.good} OK`}
      icon={<ShieldCheck className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* Tabla de checks */}
      <div className="mb-4 overflow-hidden rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="w-8 px-2 py-2" />
              <th className="px-3 py-2">Verificación</th>
              <th className="px-3 py-2">Estado</th>
              <th className="px-3 py-2">Detalle</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {report.security.items.map((c) => (
              <tr key={c.id} className="hover:bg-[var(--bg-secondary)]">
                <td className="px-2 py-2"><SeverityIcon severity={c.status} /></td>
                <td className="px-3 py-2 font-medium text-[var(--text-primary)]">{c.label}</td>
                <td className="px-3 py-2">
                  <span className={`rounded px-1.5 py-0.5 font-mono text-[10px] ${
                    c.status === 'critical' ? 'bg-red-100 text-red-800'
                    : c.status === 'warning' ? 'bg-amber-100 text-amber-800'
                    : c.status === 'good' ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-blue-100 text-blue-800'
                  }`}>
                    {renderValue(c.value)}
                  </span>
                </td>
                <td className="px-3 py-2 text-[var(--text-secondary)]">{c.note}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Issues accionables */}
      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        Hallazgos accionables
      </h4>
      <IssueList issues={report.security.issues} />
    </SectionCard>
  )
}

function renderValue(v: unknown): string {
  if (typeof v === 'boolean') return v ? 'Sí' : 'No'
  if (v === null || v === undefined) return '—'
  return String(v)
}
