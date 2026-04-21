import { Link } from 'react-router-dom'
import { Repeat, TrendingUp, TrendingDown, Minus, ExternalLink } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { getLevelClassName } from '@/lib/utils'
import type { DashboardData } from '@/types/dashboard'

/**
 * Dominios auditados más de una vez, ordenados por nº de auditorías.
 * Cada fila muestra la tendencia (flecha verde/roja/gris) comparando la
 * última auditoría con la penúltima — útil para identificar clientes cuya
 * situación está empeorando y requieren seguimiento.
 */
export function RecurringDomains({ domains }: { domains: DashboardData['recurringDomains'] }) {
  return (
    <Card className="border-0 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-center gap-2">
          <Repeat className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
          <CardTitle className="text-base">Dominios recurrentes</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {domains.length === 0 ? (
          <div className="py-8 text-center text-sm text-[var(--text-tertiary)]">
            Aún ningún dominio se ha auditado más de una vez.
          </div>
        ) : (
          <ul className="space-y-1.5">
            {domains.map((d) => {
              const level = levelFromScore(d.bestScore)
              return (
                <li key={d.domain} className="flex items-center gap-3 rounded-lg border border-[var(--border-default)] bg-white px-3 py-2">
                  <TrendIcon trend={d.trend} />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <Link
                        to={`/admin/leads/${d.lastAuditId}`}
                        className="truncate font-medium text-[var(--text-primary)] hover:text-[var(--accent-primary)]"
                      >
                        {d.domain}
                      </Link>
                      <ExternalLink className="h-3 w-3 text-[var(--text-tertiary)]" />
                    </div>
                    <div className="text-[10px] text-[var(--text-tertiary)]">
                      {d.totalAudits} auditorías · rango {d.worstScore}–{d.bestScore}
                    </div>
                  </div>
                  <span className={`text-sm font-bold tabular-nums ${getLevelClassName(level)}`}>{d.bestScore}</span>
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

function TrendIcon({ trend }: { trend: 'improving' | 'stable' | 'declining' }) {
  if (trend === 'improving')  return <TrendingUp className="h-4 w-4 shrink-0 text-emerald-600" strokeWidth={2} />
  if (trend === 'declining')  return <TrendingDown className="h-4 w-4 shrink-0 text-red-600" strokeWidth={2} />
  return <Minus className="h-4 w-4 shrink-0 text-[var(--text-tertiary)]" strokeWidth={2} />
}

function levelFromScore(score: number): string {
  if (score >= 90) return 'excellent'
  if (score >= 70) return 'good'
  if (score >= 50) return 'regular'
  if (score >= 30) return 'deficient'
  return 'critical'
}
