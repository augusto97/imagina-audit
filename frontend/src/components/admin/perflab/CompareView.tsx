import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  type PerfRunDetail, type Strategy, type RunSummary,
  METRIC_ORDER, rate, delta, formatMetric, formatDateTime, RATING_COLOR,
} from './metrics'

interface Props {
  a: PerfRunDetail // la más antigua (antes)
  b: PerfRunDetail // la más reciente (después)
  onClose: () => void
}

/** Tabla antes/después entre dos pruebas, por dispositivo. */
export default function CompareView({ a, b, onClose }: Props) {
  const { t, i18n } = useTranslation()
  const strategies = (['mobile', 'desktop'] as Strategy[]).filter(
    (s) => a.result?.strategies[s]?.summary && b.result?.strategies[s]?.summary,
  )

  const head = (r: PerfRunDetail, label: string) => (
    <div className="min-w-0">
      <p className="text-[11px] uppercase tracking-wide text-[var(--text-tertiary)]">{label}</p>
      <p className="text-xs font-medium text-[var(--text-primary)]">{formatDateTime(r.completedAt ?? r.createdAt, i18n.language)}</p>
      {r.note && <p className="truncate text-xs italic text-[var(--text-secondary)]" title={r.note}>“{r.note}”</p>}
      {a.url !== b.url && <p className="truncate text-[11px] text-[var(--text-tertiary)]" title={r.url}>{r.url}</p>}
    </div>
  )

  return (
    <Card className="border-0 shadow-sm">
      <CardContent className="p-4 space-y-4">
        <div className="flex items-center justify-between">
          <p className="text-sm font-semibold text-[var(--text-primary)]">{t('perflab.compare_title')}</p>
          <Button variant="ghost" size="icon" className="h-8 w-8" onClick={onClose}><X className="h-4 w-4" /></Button>
        </div>

        {strategies.length === 0 && (
          <p className="text-sm text-[var(--text-secondary)]">{t('perflab.compare_no_common')}</p>
        )}

        {strategies.map((s) => {
          const sa = a.result!.strategies[s]!.summary as RunSummary
          const sb = b.result!.strategies[s]!.summary as RunSummary
          return (
            <div key={s} className="overflow-x-auto">
              <p className="mb-1 text-xs font-semibold text-[var(--text-secondary)]">{t(`perflab.device_${s}`)}</p>
              <table className="w-full min-w-[420px] text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-default)] text-left align-bottom">
                    <th className="py-2 pr-2 font-normal" />
                    <th className="py-2 pr-2">{head(a, t('perflab.before'))}</th>
                    <th className="py-2 pr-2">{head(b, t('perflab.after'))}</th>
                    <th className="py-2 text-right text-[11px] font-normal uppercase tracking-wide text-[var(--text-tertiary)]">{t('perflab.change')}</th>
                  </tr>
                </thead>
                <tbody>
                  {METRIC_ORDER.map((k) => {
                    const va = sa[k as keyof RunSummary] as number | null
                    const vb = sb[k as keyof RunSummary] as number | null
                    const d = delta(k, vb, va)
                    const color = !d || d.better === null ? 'var(--text-tertiary)' : d.better ? '#0c9d58' : '#e5322d'
                    return (
                      <tr key={k} className="border-b border-[var(--border-default)] last:border-0">
                        <td className="py-1.5 pr-2 text-xs text-[var(--text-secondary)]">{t(`perflab.metric_${k}`)}</td>
                        <td className="py-1.5 pr-2 tabular-nums font-medium" style={{ color: RATING_COLOR[rate(k, va)] }}>{formatMetric(k, va)}</td>
                        <td className="py-1.5 pr-2 tabular-nums font-medium" style={{ color: RATING_COLOR[rate(k, vb)] }}>{formatMetric(k, vb)}</td>
                        <td className="py-1.5 text-right text-xs font-semibold tabular-nums" style={{ color }}>{d?.text ?? '—'}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )
        })}
      </CardContent>
    </Card>
  )
}
