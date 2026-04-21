import { useMemo, useState } from 'react'
import { Blocks, Shield, Gauge, Search, Smartphone, Server, BarChart3, Activity, Database, HelpCircle, AlertCircle, AlertTriangle, CheckCircle2, Info, type LucideIcon } from 'lucide-react'
import { Accordion, AccordionRadixItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion'
import { Badge } from '@/components/ui/badge'
import { getLevelColor } from '@/lib/utils'
import type { ModuleResult, MetricResult } from '@/types/audit'
import { levelBg, levelDot } from './helpers'
import { renderTechnicalDetails } from './MetricDetailRenderer'

/**
 * Acordeón con todos los módulos del reporte técnico.
 *
 * Diseño:
 *   - Un AccordionItem por módulo. Expandir/colapsar independiente
 *     (type="multiple") para que el usuario pueda abrir varios si quiere
 *     comparar.
 *   - Por defecto abierto: los módulos con problemas críticos.
 *   - Trigger compacto: icono + nombre + score + mini-counts
 *     (ej. "3 críticos · 2 importantes · 8 OK").
 *   - Content con secciones Problemas / Aprobados / Informativos. Los
 *     dos últimos ahora son sub-acordeones colapsados por defecto para
 *     reducir densidad visual.
 */

const MODULE_ICONS: Record<string, LucideIcon> = {
  wordpress: Blocks,
  security: Shield,
  performance: Gauge,
  seo: Search,
  mobile: Smartphone,
  infrastructure: Server,
  conversion: BarChart3,
  page_health: Activity,
  wp_internal: Database,
}

export function ModulesAccordion({ modules }: { modules: ModuleResult[] }) {
  // Default open: los módulos con problemas críticos. Evita que al abrir
  // el tab todo esté cerrado (no ves nada) pero tampoco todo expandido.
  const defaultValue = useMemo(
    () => modules.filter(m => m.metrics.some(mt => mt.level === 'critical')).map(m => m.id),
    [modules]
  )

  const [value, setValue] = useState<string[]>(defaultValue)

  return (
    <Accordion type="multiple" value={value} onValueChange={setValue} className="space-y-3">
      {modules.map(m => (
        <ModuleAccordionItem key={m.id} module={m} />
      ))}
    </Accordion>
  )
}

function ModuleAccordionItem({ module: m }: { module: ModuleResult }) {
  const issues    = m.metrics.filter(mt => mt.level === 'critical' || mt.level === 'warning')
  const criticals = issues.filter(mt => mt.level === 'critical')
  const warnings  = issues.filter(mt => mt.level === 'warning')
  const passed    = m.metrics.filter(mt => mt.level === 'good' || mt.level === 'excellent')
  const info      = m.metrics.filter(mt => !['critical', 'warning', 'good', 'excellent'].includes(mt.level))

  const Icon = MODULE_ICONS[m.id] ?? HelpCircle
  const color = getLevelColor(m.level)

  return (
    <AccordionRadixItem
      value={m.id}
      className="rounded-2xl border border-[var(--border-default)] bg-white data-[state=open]:shadow-sm"
    >
      <AccordionTrigger className="px-5 py-4 hover:no-underline">
        <div className="flex flex-1 items-center gap-3 text-left">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style={{ backgroundColor: color + '20', color }}>
            <Icon className="h-4 w-4" strokeWidth={1.75} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h2 className="truncate text-base font-bold text-[var(--text-primary)]">{m.name}</h2>
              <Badge
                variant={m.level === 'critical' ? 'destructive' : m.level === 'warning' ? 'warning' : 'success'}
                className="shrink-0 text-[10px]"
              >
                {m.score ?? '—'}/100
              </Badge>
            </div>
            <div className="mt-0.5 flex flex-wrap gap-2.5 text-[11px]">
              {criticals.length > 0 && (
                <span className="inline-flex items-center gap-1 text-red-600"><AlertCircle className="h-3 w-3" />{criticals.length} crítico{criticals.length === 1 ? '' : 's'}</span>
              )}
              {warnings.length > 0 && (
                <span className="inline-flex items-center gap-1 text-amber-600"><AlertTriangle className="h-3 w-3" />{warnings.length} importante{warnings.length === 1 ? '' : 's'}</span>
              )}
              {passed.length > 0 && (
                <span className="inline-flex items-center gap-1 text-emerald-600"><CheckCircle2 className="h-3 w-3" />{passed.length} OK</span>
              )}
              {info.length > 0 && (
                <span className="inline-flex items-center gap-1 text-[var(--text-tertiary)]"><Info className="h-3 w-3" />{info.length} info</span>
              )}
            </div>
          </div>
        </div>
      </AccordionTrigger>

      <AccordionContent className="px-5 pb-5">
        {issues.length > 0 && (
          <div className="mb-4">
            <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-red-500">Problemas a corregir</h3>
            <div className="space-y-3">
              {issues.map(mt => <MetricDetail key={mt.id} metric={mt} />)}
            </div>
          </div>
        )}

        {/* Aprobados e informativos como sub-acordeones — colapsados por default */}
        {(passed.length > 0 || info.length > 0) && (
          <Accordion type="multiple" className="space-y-2">
            {passed.length > 0 && (
              <AccordionRadixItem value="passed" className="rounded-lg border border-[var(--border-default)] bg-[var(--bg-secondary)]">
                <AccordionTrigger className="px-3 py-2 text-xs uppercase tracking-wider text-emerald-600 hover:no-underline">
                  Aprobados · {passed.length}
                </AccordionTrigger>
                <AccordionContent className="px-3 pb-3">
                  <div className="space-y-1">
                    {passed.map(mt => (
                      <div key={mt.id} className="flex items-center gap-2 py-1 text-sm">
                        <div className={`h-2 w-2 rounded-full ${levelDot(mt.level)}`} />
                        <span className="text-[var(--text-secondary)]">{mt.name}</span>
                        <span className="ml-auto text-xs text-[var(--text-tertiary)]">{mt.displayValue}</span>
                      </div>
                    ))}
                  </div>
                </AccordionContent>
              </AccordionRadixItem>
            )}

            {info.length > 0 && (
              <AccordionRadixItem value="info" className="rounded-lg border border-[var(--border-default)] bg-[var(--bg-secondary)]">
                <AccordionTrigger className="px-3 py-2 text-xs uppercase tracking-wider text-[var(--text-tertiary)] hover:no-underline">
                  Informativos · {info.length}
                </AccordionTrigger>
                <AccordionContent className="px-3 pb-3">
                  <div className="space-y-1">
                    {info.map(mt => (
                      <div key={mt.id} className="flex items-center gap-2 py-1 text-sm">
                        <div className="h-2 w-2 rounded-full bg-gray-400" />
                        <span className="text-[var(--text-secondary)]">{mt.name}</span>
                        <span className="ml-auto text-xs text-[var(--text-tertiary)]">{mt.displayValue}</span>
                      </div>
                    ))}
                  </div>
                </AccordionContent>
              </AccordionRadixItem>
            )}
          </Accordion>
        )}
      </AccordionContent>
    </AccordionRadixItem>
  )
}

function MetricDetail({ metric }: { metric: MetricResult }) {
  const details = metric.details || {}
  return (
    <div className={`rounded-xl border p-4 ${levelBg(metric.level)}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="mb-1 flex items-center gap-2">
            <div className={`h-2.5 w-2.5 shrink-0 rounded-full ${levelDot(metric.level)}`} />
            <span className="text-sm font-semibold text-[var(--text-primary)]">{metric.name}</span>
          </div>
          <p className="mb-2 text-sm text-[var(--text-secondary)]">{metric.description}</p>

          {renderTechnicalDetails(metric.id, details, metric)}

          {metric.recommendation && (
            <div className="mt-3 rounded-lg border border-[var(--border-default)] bg-white/80 p-3">
              <p className="mb-1 text-xs font-bold text-[var(--text-tertiary)]">CÓMO CORREGIR</p>
              <p className="text-sm text-[var(--text-primary)]">{metric.recommendation}</p>
            </div>
          )}
        </div>
        <span className="shrink-0 rounded-lg bg-white/60 px-2 py-1 text-xs font-bold">{metric.score}/100</span>
      </div>
    </div>
  )
}
