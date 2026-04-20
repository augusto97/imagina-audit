import { memo } from 'react'
import { getLevelColor } from '@/lib/utils'
import type { AuditResult, ModuleResult } from '@/types/audit'

/**
 * Resumen ejecutivo con scores globales y grid de módulos.
 */
export const ExecutiveSummary = memo(function ExecutiveSummary({
  result,
  criticalCount,
  warningCount,
  snapshotModule,
}: {
  result: AuditResult
  criticalCount: number
  warningCount: number
  snapshotModule: ModuleResult | null
}) {
  const modules = snapshotModule ? [...result.modules, snapshotModule] : result.modules
  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">Resumen Ejecutivo</h2>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <SummaryCard label="Score Global" value={`${result.globalScore}/100`} color={getLevelColor(result.globalLevel)} />
        <SummaryCard label="Críticos" value={String(criticalCount)} color="var(--color-critical)" />
        <SummaryCard label="Importantes" value={String(warningCount)} color="var(--color-warning)" />
        <SummaryCard label="WordPress" value={result.isWordPress ? 'Sí' : 'No'} color="var(--accent-primary)" />
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {modules.map(m => (
          <div key={m.id} className="flex items-center gap-2 text-sm">
            <div className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: getLevelColor(m.level) }} />
            <span className="text-[var(--text-secondary)] truncate">{m.name}</span>
            <span className="font-semibold text-[var(--text-primary)] ml-auto">{m.score ?? '—'}</span>
          </div>
        ))}
      </div>
    </div>
  )
})

function SummaryCard({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3 text-center">
      <p className="text-xs text-[var(--text-tertiary)] mb-1">{label}</p>
      <p className="text-2xl font-bold" style={{ color }}>{value}</p>
    </div>
  )
}
