import type { AuditResult, MetricResult } from '@/types/audit'

/**
 * Métrica extendida con el módulo al que pertenece — se usa en ActionPlan
 * para mostrar de qué módulo viene cada item.
 */
export interface MetricWithModule extends MetricResult {
  moduleName: string
  moduleId: string
}

/**
 * Estado local del checklist: un bool "completed" + nota opcional por metric id.
 */
export type ChecklistState = Record<
  string,
  { completed: boolean; notes: string | null; completedAt: string | null }
>

/**
 * Agrupa todas las métricas de todos los módulos que coincidan con un nivel
 * de semáforo dado.
 */
export function getAllMetricsByLevel(result: AuditResult, level: string): MetricWithModule[] {
  const items: MetricWithModule[] = []
  for (const mod of result.modules) {
    for (const metric of mod.metrics) {
      if (metric.level === level) {
        items.push({ ...metric, moduleName: mod.name, moduleId: mod.id })
      }
    }
  }
  return items
}

/**
 * Clase Tailwind para el fondo de un card según el nivel.
 */
export function levelBg(level: string): string {
  const map: Record<string, string> = {
    critical: 'bg-red-50 border-red-200',
    warning: 'bg-amber-50 border-amber-200',
    good: 'bg-emerald-50 border-emerald-200',
    excellent: 'bg-emerald-50 border-emerald-200',
  }
  return map[level] || 'bg-gray-50 border-gray-200'
}

/**
 * Clase Tailwind para el color del dot semáforo.
 */
export function levelDot(level: string): string {
  const map: Record<string, string> = {
    critical: 'bg-red-500',
    warning: 'bg-amber-500',
    good: 'bg-emerald-500',
    excellent: 'bg-emerald-600',
  }
  return map[level] || 'bg-gray-400'
}
