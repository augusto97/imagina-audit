import { Database as DbIcon } from 'lucide-react'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Peso de la DB + top 15 tablas + MyISAM + stats de housekeeping
 * (autoload, revisiones, transients, orphaned meta).
 */
export default function DatabaseSection({ report }: { report: SnapshotReport }) {
  const s = report.database.summary

  return (
    <SectionCard
      title="Base de datos"
      subtitle={`${s.sizeHuman} · ${s.tables} tablas · ${s.rows?.toLocaleString?.('es') ?? s.rows} filas`}
      icon={<DbIcon className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* KPIs */}
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label="Tamaño" value={String(s.sizeHuman || '—')} hint={`${s.tables} tablas`} />
        <KpiTile
          label="Autoload"
          value={String(s.autoloadHuman || '—')}
          tone={Number(s.autoloadBytes) > 1048576 ? 'warning' : 'good'}
          hint={`${s.autoloadOptions}/${s.totalOptions} opciones`}
        />
        <KpiTile
          label="Revisiones"
          value={Number(s.revisions ?? 0)}
          tone={Number(s.revisions) > 500 ? 'warning' : 'good'}
        />
        <KpiTile
          label="Transients"
          value={Number(s.transients ?? 0)}
          tone={Number(s.transients) > 1000 ? 'warning' : 'neutral'}
        />
      </div>

      {/* Info DB */}
      <div className="mb-4 grid gap-2 sm:grid-cols-3 text-xs">
        <InfoBox label="Prefijo" value={String(s.prefix || '—')} mono />
        <InfoBox label="Charset" value={String(s.charset || '—')} mono />
        <InfoBox label="Collation" value={String(s.collation || 'default')} mono />
      </div>

      {/* Top tablas */}
      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        Top tablas por tamaño
      </h4>
      <div className="mb-4 overflow-x-auto rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="px-3 py-2">Tabla</th>
              <th className="px-3 py-2 text-right">Filas</th>
              <th className="px-3 py-2 text-right">Tamaño</th>
              <th className="px-3 py-2">Motor</th>
              <th className="px-3 py-2">Collation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {report.database.topTables.map((t) => (
              <tr key={String(t.name)} className="hover:bg-[var(--bg-secondary)]">
                <td className="px-3 py-1.5 font-mono text-[11px]">{String(t.name)}</td>
                <td className="px-3 py-1.5 text-right tabular-nums">{Number(t.rows ?? 0).toLocaleString('es')}</td>
                <td className="px-3 py-1.5 text-right tabular-nums font-medium">{Number(t.sizeMb ?? 0)} MB</td>
                <td className="px-3 py-1.5">
                  <span className={`font-mono text-[10px] ${String(t.engine).toLowerCase() === 'myisam' ? 'text-amber-600' : 'text-[var(--text-secondary)]'}`}>
                    {String(t.engine || '—')}
                  </span>
                </td>
                <td className="px-3 py-1.5 text-[10px] text-[var(--text-tertiary)]">{String(t.collation || '—')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Tablas MyISAM */}
      {report.database.myisamTables.length > 0 && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
          <p className="mb-1 text-xs font-semibold text-amber-900">
            Tablas con motor MyISAM ({report.database.myisamTables.length})
          </p>
          <p className="mb-2 text-[11px] text-amber-900/80">
            Convertir a InnoDB para habilitar transacciones, row-level locking y foreign keys.
          </p>
          <div className="flex flex-wrap gap-1">
            {report.database.myisamTables.map((t) => (
              <code key={t.name} className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px]">{t.name}</code>
            ))}
          </div>
        </div>
      )}

      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        Hallazgos accionables
      </h4>
      <IssueList issues={report.database.issues} />
    </SectionCard>
  )
}

function InfoBox({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="rounded-lg border border-[var(--border-default)] px-3 py-2">
      <div className="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">{label}</div>
      <div className={`text-sm font-medium text-[var(--text-primary)] ${mono ? 'font-mono' : ''}`}>{value}</div>
    </div>
  )
}
