import { memo } from 'react'
import { Badge } from '@/components/ui/badge'
import { getLevelColor, getLevelLabel } from '@/lib/utils'
import type { ModuleResult, MetricResult } from '@/types/audit'
import { levelBg, levelDot } from './helpers'
import { renderTechnicalDetails } from './MetricDetailRenderer'

/**
 * Detalle de un módulo: score, problemas a corregir, métricas aprobadas e
 * informativas. Memoizado porque los módulos no cambian entre toggles del
 * checklist del padre.
 */
export const ModuleDetail = memo(function ModuleDetail({ module }: { module: ModuleResult }) {
  const issues = module.metrics.filter(m => m.level === 'critical' || m.level === 'warning')
  const passed = module.metrics.filter(m => m.level === 'good' || m.level === 'excellent')
  const info = module.metrics.filter(m => m.level !== 'critical' && m.level !== 'warning' && m.level !== 'good' && m.level !== 'excellent')

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className="h-3 w-3 rounded-full" style={{ backgroundColor: getLevelColor(module.level) }} />
          <h2 className="text-lg font-bold text-[var(--text-primary)]">{module.name}</h2>
          <Badge variant={module.level === 'critical' ? 'destructive' : module.level === 'warning' ? 'warning' : 'success'}>
            {module.score}/100 — {getLevelLabel(module.level)}
          </Badge>
        </div>
      </div>

      {issues.length > 0 && (
        <div className="mb-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-red-500 mb-2">Problemas a corregir</h3>
          <div className="space-y-3">
            {issues.map(m => <MetricDetail key={m.id} metric={m} />)}
          </div>
        </div>
      )}

      {passed.length > 0 && (
        <div className="mb-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-500 mb-2">Aprobados</h3>
          <div className="space-y-1">
            {passed.map(m => (
              <div key={m.id} className="flex items-center gap-2 text-sm py-1">
                <div className={`h-2 w-2 rounded-full ${levelDot(m.level)}`} />
                <span className="text-[var(--text-secondary)]">{m.name}</span>
                <span className="ml-auto text-xs text-[var(--text-tertiary)]">{m.displayValue}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {info.length > 0 && (
        <div>
          <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Informativos</h3>
          <div className="space-y-1">
            {info.map(m => (
              <div key={m.id} className="flex items-center gap-2 text-sm py-1">
                <div className="h-2 w-2 rounded-full bg-gray-400" />
                <span className="text-[var(--text-secondary)]">{m.name}</span>
                <span className="ml-auto text-xs text-[var(--text-tertiary)]">{m.displayValue}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
})

function MetricDetail({ metric }: { metric: MetricResult }) {
  const details = metric.details || {}

  return (
    <div className={`rounded-xl border p-4 ${levelBg(metric.level)}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 mb-1">
            <div className={`h-2.5 w-2.5 rounded-full shrink-0 ${levelDot(metric.level)}`} />
            <span className="font-semibold text-sm text-[var(--text-primary)]">{metric.name}</span>
          </div>
          <p className="text-sm text-[var(--text-secondary)] mb-2">{metric.description}</p>

          {/* Detalles técnicos expandidos */}
          {renderTechnicalDetails(metric.id, details, metric)}

          {metric.recommendation && (
            <div className="mt-3 rounded-lg bg-white/80 border border-[var(--border-default)] p-3">
              <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">CÓMO CORREGIR</p>
              <p className="text-sm text-[var(--text-primary)]">{metric.recommendation}</p>
            </div>
          )}
        </div>
        <span className="text-xs font-bold shrink-0 px-2 py-1 rounded-lg bg-white/60">{metric.score}/100</span>
      </div>
    </div>
  )
}
