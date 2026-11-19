import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ExternalLink, Smartphone, Monitor, AlertTriangle, Pencil, Check, X } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import WaterfallView, { buildMilestones } from '../waterfall/WaterfallView'
import {
  type PerfRunDetail, type Strategy, type MetricKey, type RunSummary,
  METRIC_ORDER, rate, delta, formatMetric, formatMs, formatDateTime,
  RATING_COLOR, RATING_BG,
} from './metrics'

interface Props {
  run: PerfRunDetail
  onSaveNote: (note: string) => Promise<void>
}

/** Score circular estilo PageSpeed (verde/naranja/rojo). */
export function ScoreRing({ score, size = 84 }: { score: number | null; size?: number }) {
  const r = (size - 8) / 2
  const c = 2 * Math.PI * r
  const pct = score === null ? 0 : Math.max(0, Math.min(100, score)) / 100
  const color = RATING_COLOR[rate('score', score)]
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill={RATING_BG[rate('score', score)]} stroke="var(--border-default)" strokeWidth={6} />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color} strokeWidth={6}
          strokeDasharray={c} strokeDashoffset={c * (1 - pct)} strokeLinecap="round" />
      </svg>
      <span className="absolute inset-0 flex items-center justify-center font-bold tabular-nums"
        style={{ color, fontSize: size * 0.32 }}>
        {score ?? '—'}
      </span>
    </div>
  )
}

function DeltaTag({ k, current, previous }: { k: MetricKey; current: number | null | undefined; previous: number | null | undefined }) {
  const d = delta(k, current, previous)
  if (!d) return null
  const color = d.better === null ? 'var(--text-tertiary)' : d.better ? '#0c9d58' : '#e5322d'
  return <span className="text-[11px] font-semibold tabular-nums" style={{ color }}>{d.text}</span>
}

export default function RunResult({ run, onSaveNote }: Props) {
  const { t, i18n } = useTranslation()
  const strategies = useMemo(
    () => (['mobile', 'desktop'] as Strategy[]).filter((s) => run.result?.strategies[s]),
    [run.result],
  )
  const [active, setActive] = useState<Strategy>(strategies[0] ?? 'mobile')
  const [editing, setEditing] = useState(false)
  const [note, setNote] = useState(run.note ?? '')

  useEffect(() => {
    setActive(strategies[0] ?? 'mobile')
    setNote(run.note ?? '')
    setEditing(false)
  }, [run.id, strategies, run.note])

  const sr = run.result?.strategies[active]
  const summary = sr?.summary
  const prev: RunSummary | undefined = run.previous?.summaries?.[active]
  const domain = (() => { try { return new URL(run.url).hostname } catch { return run.url } })()

  const labelFor = (k: MetricKey) => t(`perflab.metric_${k}`)

  const saveNote = async () => {
    await onSaveNote(note)
    setEditing(false)
  }

  return (
    <div className="space-y-4">
      {/* Cabecera */}
      <Card className="border-0 shadow-sm">
        <CardContent className="p-4 space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <a href={run.url} target="_blank" rel="noreferrer"
              className="text-sm font-semibold text-[var(--accent-primary)] hover:underline flex items-center gap-1 break-all">
              {run.url} <ExternalLink className="h-3 w-3 shrink-0" />
            </a>
            <Badge variant="secondary">{formatDateTime(run.completedAt ?? run.createdAt, i18n.language)}</Badge>
            <Badge variant="secondary">
              {run.runsRequested > 1 ? t('perflab.mode_precise_short') : t('perflab.mode_quick_short')}
            </Badge>
          </div>
          {editing ? (
            <div className="flex items-center gap-2">
              <Input value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} autoFocus
                onKeyDown={(e) => { if (e.key === 'Enter') saveNote(); if (e.key === 'Escape') setEditing(false) }}
                placeholder={t('perflab.note_placeholder')} className="h-8 text-sm" />
              <Button size="icon" variant="ghost" className="h-8 w-8" onClick={saveNote} title={t('common.save')}><Check className="h-4 w-4" /></Button>
              <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => { setEditing(false); setNote(run.note ?? '') }}><X className="h-4 w-4" /></Button>
            </div>
          ) : (
            <button type="button" onClick={() => setEditing(true)}
              className="flex items-center gap-1.5 text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] cursor-pointer">
              <Pencil className="h-3.5 w-3.5" />
              {run.note ? <span className="italic">“{run.note}”</span> : <span>{t('perflab.note_add')}</span>}
            </button>
          )}
          {run.previous && (
            <p className="text-xs text-[var(--text-tertiary)]">
              {t('perflab.compared_with_previous', { date: formatDateTime(run.previous.completedAt ?? run.previous.createdAt, i18n.language) })}
              {run.previous.note ? ` — “${run.previous.note}”` : ''}
            </p>
          )}
        </CardContent>
      </Card>

      {/* Selector de dispositivo */}
      {strategies.length > 1 && (
        <div className="inline-flex rounded-lg border border-[var(--border-default)] bg-white p-0.5">
          {strategies.map((s) => (
            <button key={s} type="button" onClick={() => setActive(s)}
              className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium cursor-pointer ${active === s ? 'bg-[var(--accent-primary)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)]'}`}>
              {s === 'mobile' ? <Smartphone className="h-3.5 w-3.5" /> : <Monitor className="h-3.5 w-3.5" />}
              {t(`perflab.device_${s}`)}
              <span className="tabular-nums opacity-80">· {run.result?.strategies[s]?.summary?.score ?? '—'}</span>
            </button>
          ))}
        </div>
      )}

      {sr?.error && (
        <Card className="border-0 shadow-sm bg-red-50">
          <CardContent className="p-4 text-sm text-red-700 flex gap-2">
            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" /> {sr.error}
          </CardContent>
        </Card>
      )}

      {summary && (
        <>
          {/* Score + métricas */}
          <Card className="border-0 shadow-sm">
            <CardContent className="p-4">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div className="flex items-center gap-4">
                  <ScoreRing score={summary.score} />
                  <div>
                    <p className="text-xs uppercase tracking-wide text-[var(--text-tertiary)]">{t('perflab.performance_score')}</p>
                    <p className="text-sm font-medium text-[var(--text-primary)]">{t(`perflab.device_${active}`)}</p>
                    {prev && (
                      <p className="text-xs text-[var(--text-secondary)]">
                        {t('perflab.before')}: <span className="tabular-nums">{prev.score ?? '—'}</span>{' '}
                        <DeltaTag k="score" current={summary.score} previous={prev.score} />
                      </p>
                    )}
                  </div>
                </div>
                {sr.runs && sr.runs.length > 1 && (
                  <div className="sm:ml-auto rounded-lg bg-[var(--bg-tertiary)] px-3 py-2 text-xs text-[var(--text-secondary)]">
                    <p className="font-medium text-[var(--text-primary)]">{t('perflab.runs_label', { count: sr.runs.length })}</p>
                    <p className="tabular-nums">
                      {sr.runs.map((r, i) => (
                        <span key={i} className={r.isMedian ? 'font-bold text-[var(--text-primary)]' : ''}>
                          {i > 0 && ' · '}{r.score}
                        </span>
                      ))}
                      {(() => {
                        const scores = sr.runs!.map((r) => r.score ?? 0)
                        return <> &nbsp;({t('perflab.spread', { n: Math.max(...scores) - Math.min(...scores) })})</>
                      })()}
                    </p>
                    <p className="text-[var(--text-tertiary)]">{t('perflab.median_hint')}</p>
                  </div>
                )}
              </div>

              <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                {METRIC_ORDER.filter((k) => k !== 'score').map((k) => {
                  const v = summary[k as keyof RunSummary] as number | null
                  const r = rate(k, v)
                  return (
                    <div key={k} className="rounded-lg border border-[var(--border-default)] p-2.5">
                      <p className="text-[11px] text-[var(--text-tertiary)]">{labelFor(k)}</p>
                      <div className="flex items-baseline justify-between gap-2">
                        <span className="text-base font-semibold tabular-nums" style={{ color: RATING_COLOR[r] }}>
                          {formatMetric(k, v)}
                        </span>
                        {prev && <DeltaTag k={k} current={v} previous={prev[k as keyof RunSummary] as number | null} />}
                      </div>
                    </div>
                  )
                })}
              </div>
            </CardContent>
          </Card>

          {/* Avisos de Lighthouse (redirecciones, página lenta, etc.) */}
          {!!sr.meta?.warnings?.length && (
            <Card className="border-0 shadow-sm bg-amber-50">
              <CardContent className="p-3 text-xs text-amber-800 space-y-1">
                {sr.meta.warnings.map((w, i) => (
                  <p key={i} className="flex gap-1.5"><AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" /> {w}</p>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Oportunidades */}
          {!!sr.data?.opportunities?.length && (
            <Card className="border-0 shadow-sm">
              <CardContent className="p-4">
                <p className="mb-2 text-sm font-semibold text-[var(--text-primary)]">{t('perflab.opportunities')}</p>
                <div className="divide-y divide-[var(--border-default)]">
                  {[...sr.data.opportunities].sort((a, b) => (b.savings || 0) - (a.savings || 0)).map((o) => (
                    <div key={o.id} className="flex flex-col gap-0.5 py-1.5 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                      <span className="text-[var(--text-primary)]">{o.title}</span>
                      <span className="text-xs tabular-nums text-[var(--text-secondary)] sm:shrink-0 sm:text-right">
                        {o.displayValue}{o.savings > 0 ? ` · ${t('perflab.savings', { v: formatMs(o.savings) })}` : ''}
                      </span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Waterfall de la medición mediana */}
          {sr.data && sr.data.networkRequests.length > 0 && (
            <Card className="border-0 shadow-sm">
              <CardContent className="p-4">
                <WaterfallView
                  key={`${run.id}-${active}`}
                  url={sr.meta?.finalUrl || run.url}
                  domain={domain}
                  requests={sr.data.networkRequests}
                  milestones={buildMilestones({ fcp: summary.fcp, lcp: summary.lcp, tbt: summary.tbt, ttfb: summary.ttfb })}
                  cruxData={sr.data.crux}
                  resourceBreakdown={sr.data.resourceBreakdown}
                  lighthouseAudits={sr.data.lighthouseAudits}
                  lcpElement={sr.data.lcpElement}
                  clsElements={sr.data.clsElements}
                  mainThreadWork={sr.data.mainThreadWork}
                />
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
