import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  FlaskConical, Play, Loader2, Smartphone, Monitor, MonitorSmartphone, Zap, Target,
  Trash2, RotateCw, GitCompareArrows, AlertTriangle, Square, RefreshCw,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import api from '@/lib/api'
import RunResult from './perflab/RunResult'
import CompareView from './perflab/CompareView'
import {
  type PerfRun, type PerfRunDetail, type StrategyChoice,
  formatDateTime, parseServerDate, rate, RATING_COLOR,
} from './perflab/metrics'

const ENDPOINT = '/admin/perf-lab.php'
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))

interface ListResponse {
  runs: PerfRun[]
  urls: Array<{ url: string; total: number; lastAt: string }>
  hasApiKey: boolean
}

type ApiError = { response?: { status?: number; data?: { error?: string } }; code?: string }

/**
 * Laboratorio de rendimiento: mediciones repetidas SOLO de PageSpeed para
 * iterar optimizaciones. No crea auditorías ni leads y no usa la caché de
 * 24h. Cada prueba avanza por rondas (una petición por ronda) para que el
 * progreso sea real y no dependa de procesos en segundo plano.
 */
export default function PerfLab() {
  const { t, i18n } = useTranslation()

  // Formulario
  const [url, setUrl] = useState('')
  const [strategy, setStrategy] = useState<StrategyChoice>('mobile')
  const [runs, setRuns] = useState<1 | 3>(1)
  const [note, setNote] = useState('')

  // Datos
  const [list, setList] = useState<ListResponse | null>(null)
  const [filterUrl, setFilterUrl] = useState('')
  const [active, setActive] = useState<PerfRun | null>(null)
  const [elapsed, setElapsed] = useState(0)
  const [selected, setSelected] = useState<PerfRunDetail | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [compareIds, setCompareIds] = useState<number[]>([])
  const [compare, setCompare] = useState<{ a: PerfRunDetail; b: PerfRunDetail } | null>(null)

  // Token del bucle de rondas: cambiarlo detiene el bucle en curso.
  const loopToken = useRef(0)
  const mounted = useRef(true)
  const resumedOnce = useRef(false)
  const topRef = useRef<HTMLDivElement>(null)
  const resultRef = useRef<HTMLDivElement>(null)
  const scrollToResult = () => {
    // El detalle queda debajo del formulario: llevarlo a la vista.
    setTimeout(() => resultRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
  }

  useEffect(() => {
    mounted.current = true
    return () => {
      // Al salir de la página el bucle se detiene; la prueba queda
      // pendiente y se reanuda sola al volver (ver autoResume).
      mounted.current = false
      loopToken.current++
    }
  }, [])

  const loadList = useCallback(async () => {
    try {
      const res = await api.get<{ data: ListResponse }>(ENDPOINT, { params: { action: 'list', url: filterUrl || undefined } })
      if (mounted.current) setList(res.data.data)
      return res.data.data
    } catch {
      if (mounted.current) toast.error(t('perflab.load_error'))
      return null
    }
  }, [filterUrl, t])

  const openRun = useCallback(async (id: number) => {
    setLoadingDetail(true)
    try {
      const res = await api.get<{ data: PerfRunDetail }>(ENDPOINT, { params: { action: 'get', id } })
      if (mounted.current) {
        setSelected(res.data.data)
        setCompare(null)
        scrollToResult()
      }
    } catch {
      toast.error(t('perflab.load_error'))
    } finally {
      if (mounted.current) setLoadingDetail(false)
    }
  }, [t])

  /** Ejecuta rondas hasta que la prueba termine (o se detenga el bucle). */
  const runLoop = useCallback(async (initial: PerfRun) => {
    const token = ++loopToken.current
    const alive = () => mounted.current && loopToken.current === token
    setActive(initial)
    setSelected(null)
    const startedAt = parseServerDate(initial.createdAt)?.getTime() ?? Date.now()
    setElapsed(Math.max(0, Math.round((Date.now() - startedAt) / 1000)))
    let transientErrors = 0
    let current = initial

    while (alive()) {
      try {
        // Una ronda puede tardar hasta ~90s (PSI). Si el proxy corta antes
        // (504), PHP sigue midiendo y el siguiente step lo detecta.
        const res = await api.post<{ data: PerfRun }>(ENDPOINT, { action: 'step', id: current.id }, { timeout: 130000 })
        if (!alive()) return
        current = res.data.data
        setActive(current)
        transientErrors = 0
        if (current.status === 'completed' || current.status === 'failed') break
        if (current.status === 'measuring') await sleep(4000)
      } catch (err) {
        if (!alive()) return
        const e = err as ApiError
        const status = e.response?.status ?? 0
        const transient = status === 0 || status === 408 || status === 429 || status >= 500
        if (!transient) {
          toast.error(e.response?.data?.error || t('perflab.step_error'))
          setActive(null)
          loadList()
          return
        }
        transientErrors++
        if (transientErrors > 12) {
          toast.error(t('perflab.step_error'))
          setActive(null)
          loadList()
          return
        }
        await sleep(5000)
      }
    }
    if (!alive()) return

    setActive(null)
    await loadList()
    if (current.status === 'completed') {
      const s = current.scoreMobile ?? current.scoreDesktop
      toast.success(t('perflab.done_toast', { score: s ?? '—' }))
      openRun(current.id)
    } else {
      toast.error(current.error || t('perflab.step_error'))
    }
  }, [loadList, openRun, t])

  // Lista inicial + reanudar automáticamente la última prueba a medias.
  useEffect(() => {
    loadList().then((data) => {
      if (!data || resumedOnce.current) return
      resumedOnce.current = true
      const recent = data.runs.find((r) => {
        if (r.status !== 'pending' && r.status !== 'measuring') return false
        const created = parseServerDate(r.createdAt)?.getTime() ?? 0
        return Date.now() - created < 15 * 60 * 1000
      })
      if (recent) runLoop(recent)
    })
  }, [loadList, runLoop])

  // Cronómetro de la prueba activa
  useEffect(() => {
    if (!active) return
    const iv = setInterval(() => setElapsed((s) => s + 1), 1000)
    return () => clearInterval(iv)
  }, [active?.id]) // eslint-disable-line react-hooks/exhaustive-deps

  const start = async () => {
    const u = url.trim()
    if (!u) { toast.error(t('perflab.url_required')); return }
    try {
      const res = await api.post<{ data: PerfRun }>(ENDPOINT, { action: 'create', url: u, strategy, runs, note })
      setNote('')
      setCompareIds([])
      runLoop(res.data.data)
      loadList()
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('perflab.create_error'))
    }
  }

  const stop = async () => {
    if (!active) return
    if (!confirm(t('perflab.stop_confirm'))) return
    const id = active.id
    loopToken.current++
    setActive(null)
    try { await api.delete(ENDPOINT, { params: { id } }) } catch { /* ya no existe */ }
    loadList()
  }

  const remove = async (run: PerfRun) => {
    if (!confirm(t('perflab.delete_confirm'))) return
    try {
      await api.delete(ENDPOINT, { params: { id: run.id } })
      if (selected?.id === run.id) setSelected(null)
      setCompareIds((ids) => ids.filter((x) => x !== run.id))
      loadList()
    } catch {
      toast.error(t('perflab.delete_error'))
    }
  }

  const saveNote = async (id: number, value: string) => {
    try {
      await api.post(ENDPOINT, { action: 'note', id, note: value })
      setSelected((s) => (s && s.id === id ? { ...s, note: value.trim() || null } : s))
      loadList()
    } catch {
      toast.error(t('perflab.note_error'))
    }
  }

  const remeasure = (run: PerfRun) => {
    setUrl(run.url)
    setStrategy(run.strategy)
    setRuns(run.runsRequested > 1 ? 3 : 1)
    topRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  const toggleCompare = (id: number) => {
    setCompareIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id].slice(-2)))
  }

  const runCompare = async () => {
    if (compareIds.length !== 2) return
    try {
      const [r1, r2] = await Promise.all(compareIds.map((id) =>
        api.get<{ data: PerfRunDetail }>(ENDPOINT, { params: { action: 'get', id } }).then((r) => r.data.data)))
      const [a, b] = r1.id < r2.id ? [r1, r2] : [r2, r1]
      setCompare({ a, b })
      setSelected(null)
      scrollToResult()
    } catch {
      toast.error(t('perflab.load_error'))
    }
  }

  const fmtElapsed = (s: number) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`

  const busy = !!active
  const runsList = list?.runs ?? []
  const progressPct = useMemo(() => {
    if (!active) return 0
    const base = active.runsDone / active.runsRequested
    // Dentro de la ronda en curso avanzamos de forma estimada (~35s por ronda)
    const inRound = Math.min(0.9, (elapsed % 35) / 35) / active.runsRequested
    return Math.min(99, Math.round((base + (active.runsDone < active.runsRequested ? inRound : 0)) * 100))
  }, [active, elapsed])

  const segBtn = (activeFlag: boolean) =>
    `flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-xs font-medium transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 ${activeFlag ? 'bg-[var(--accent-primary)] text-white shadow-sm' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)]'}`

  return (
    <div className="space-y-4" ref={topRef}>
      <div>
        <h2 className="flex items-center gap-2 text-lg font-semibold text-[var(--text-primary)]">
          <FlaskConical className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} /> {t('perflab.title')}
        </h2>
        <p className="mt-0.5 text-sm text-[var(--text-secondary)]">{t('perflab.subtitle')}</p>
      </div>

      {list && !list.hasApiKey && (
        <Card className="border-0 bg-amber-50 shadow-sm">
          <CardContent className="flex gap-2 p-3 text-sm text-amber-800">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              {t('perflab.no_api_key')}{' '}
              <Link to="/admin/settings" className="font-medium underline">{t('perflab.no_api_key_link')}</Link>
            </span>
          </CardContent>
        </Card>
      )}

      {/* Formulario */}
      <Card className="border-0 shadow-sm">
        <CardContent className="space-y-3 p-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('perflab.url_label')}</label>
              <Input value={url} onChange={(e) => setUrl(e.target.value)} placeholder={t('perflab.url_placeholder')} list="perflab-urls"
                disabled={busy} onKeyDown={(e) => { if (e.key === 'Enter' && !busy) start() }} />
              <datalist id="perflab-urls">
                {(list?.urls ?? []).map((u) => <option key={u.url} value={u.url} />)}
              </datalist>
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('perflab.device_label')}</label>
              <div className="flex gap-1 rounded-lg border border-[var(--border-default)] bg-white p-0.5 lg:w-[330px]">
                <button type="button" disabled={busy} className={segBtn(strategy === 'mobile')} onClick={() => setStrategy('mobile')}>
                  <Smartphone className="h-3.5 w-3.5" /> {t('perflab.device_mobile')}
                </button>
                <button type="button" disabled={busy} className={segBtn(strategy === 'desktop')} onClick={() => setStrategy('desktop')}>
                  <Monitor className="h-3.5 w-3.5" /> {t('perflab.device_desktop')}
                </button>
                <button type="button" disabled={busy} className={segBtn(strategy === 'both')} onClick={() => setStrategy('both')}>
                  <MonitorSmartphone className="h-3.5 w-3.5" /> {t('perflab.device_both')}
                </button>
              </div>
            </div>
          </div>

          <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('perflab.note_label')}</label>
              <Input value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} disabled={busy}
                placeholder={t('perflab.note_placeholder')} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('perflab.mode_label')}</label>
              <div className="flex gap-1 rounded-lg border border-[var(--border-default)] bg-white p-0.5 lg:w-[330px]">
                <button type="button" disabled={busy} className={segBtn(runs === 1)} onClick={() => setRuns(1)}>
                  <Zap className="h-3.5 w-3.5" /> {t('perflab.mode_quick')}
                </button>
                <button type="button" disabled={busy} className={segBtn(runs === 3)} onClick={() => setRuns(3)}>
                  <Target className="h-3.5 w-3.5" /> {t('perflab.mode_precise')}
                </button>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={start} disabled={busy}>
              {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Play className="h-4 w-4" />}
              {t('perflab.start')}
            </Button>
            <p className="text-xs text-[var(--text-tertiary)]">
              {runs === 1 ? t('perflab.mode_quick_hint') : t('perflab.mode_precise_hint')}
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Progreso */}
      {active && (
        <Card className="border-0 shadow-sm">
          <CardContent className="space-y-2 p-4">
            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="flex items-center gap-2 text-sm font-medium text-[var(--text-primary)]">
                  <Loader2 className="h-4 w-4 animate-spin text-[var(--accent-primary)]" />
                  {t('perflab.measuring', { n: Math.min(active.runsDone + 1, active.runsRequested), total: active.runsRequested })}
                </p>
                <p className="truncate text-xs text-[var(--text-tertiary)]">{active.url} · {t(`perflab.device_${active.strategy}`)}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-sm tabular-nums text-[var(--text-secondary)]">{fmtElapsed(elapsed)}</span>
                <Button variant="outline" size="sm" onClick={stop}><Square className="h-3.5 w-3.5" /> {t('perflab.stop')}</Button>
              </div>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-[var(--bg-tertiary)]">
              <div className="h-full rounded-full bg-[var(--accent-primary)] transition-all duration-1000" style={{ width: `${progressPct}%` }} />
            </div>
            <p className="text-xs text-[var(--text-tertiary)]">{t('perflab.measuring_hint')}</p>
          </CardContent>
        </Card>
      )}

      {/* Comparación / detalle */}
      <div ref={resultRef} className="scroll-mt-4" />
      {compare && <CompareView a={compare.a} b={compare.b} onClose={() => setCompare(null)} />}
      {loadingDetail && <Skeleton className="h-64 rounded-lg" />}
      {selected && !loadingDetail && (
        <div className="space-y-2">
          <div className="flex flex-wrap justify-end gap-2">
            <Button variant="outline" size="sm" onClick={() => remeasure(selected)} disabled={busy}>
              <RotateCw className="h-3.5 w-3.5" /> {t('perflab.remeasure')}
            </Button>
          </div>
          {selected.status === 'completed' ? (
            <RunResult run={selected} onSaveNote={(v) => saveNote(selected.id, v)} />
          ) : (
            <Card className="border-0 bg-red-50 shadow-sm">
              <CardContent className="flex gap-2 p-4 text-sm text-red-700">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> {selected.error || t('perflab.step_error')}
              </CardContent>
            </Card>
          )}
        </div>
      )}

      {/* Historial */}
      <Card className="border-0 shadow-sm">
        <CardContent className="p-4">
          <div className="mb-3 flex flex-wrap items-center gap-2">
            <p className="mr-auto text-sm font-semibold text-[var(--text-primary)]">{t('perflab.history')}</p>
            <select value={filterUrl} onChange={(e) => setFilterUrl(e.target.value)}
              className="h-8 max-w-[260px] rounded-md border border-[var(--border-default)] bg-white px-2 text-xs">
              <option value="">{t('perflab.all_urls')}</option>
              {(list?.urls ?? []).map((u) => <option key={u.url} value={u.url}>{u.url} ({u.total})</option>)}
            </select>
            <Button variant="outline" size="sm" disabled={compareIds.length !== 2} onClick={runCompare}>
              <GitCompareArrows className="h-3.5 w-3.5" /> {t('perflab.compare_btn', { n: compareIds.length })}
            </Button>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => loadList()} title={t('common.reload')}>
              <RefreshCw className="h-3.5 w-3.5" />
            </Button>
          </div>

          {!list ? (
            <Skeleton className="h-32 rounded-lg" />
          ) : runsList.length === 0 ? (
            <p className="py-8 text-center text-sm text-[var(--text-tertiary)]">{t('perflab.empty')}</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-default)] text-left text-[11px] uppercase tracking-wide text-[var(--text-tertiary)]">
                    <th className="w-8 py-2" title={t('perflab.compare_hint')} />
                    <th className="py-2 pr-2 font-medium">{t('perflab.col_date')}</th>
                    <th className="py-2 pr-2 font-medium">{t('perflab.col_url')}</th>
                    <th className="py-2 pr-2 font-medium">{t('perflab.col_note')}</th>
                    <th className="py-2 pr-2 text-center font-medium"><Smartphone className="inline h-3.5 w-3.5" /></th>
                    <th className="py-2 pr-2 text-center font-medium"><Monitor className="inline h-3.5 w-3.5" /></th>
                    <th className="py-2 font-medium" />
                  </tr>
                </thead>
                <tbody>
                  {runsList.map((r) => {
                    const isSel = selected?.id === r.id
                    const incomplete = r.status === 'pending' || r.status === 'measuring'
                    return (
                      <tr key={r.id}
                        className={`border-b border-[var(--border-default)] last:border-0 ${isSel ? 'bg-blue-50' : 'hover:bg-[var(--bg-secondary)]'} ${r.status === 'completed' || r.status === 'failed' ? 'cursor-pointer' : ''}`}
                        onClick={() => { if (r.status === 'completed' || r.status === 'failed') openRun(r.id) }}>
                        <td className="py-2" onClick={(e) => e.stopPropagation()}>
                          {r.status === 'completed' && (
                            <input type="checkbox" className="cursor-pointer" checked={compareIds.includes(r.id)}
                              onChange={() => toggleCompare(r.id)} title={t('perflab.compare_hint')} />
                          )}
                        </td>
                        <td className="whitespace-nowrap py-2 pr-2 text-xs text-[var(--text-secondary)]">
                          {formatDateTime(r.createdAt, i18n.language)}
                          {r.runsRequested > 1 && <Badge variant="secondary" className="ml-1.5 px-1.5 py-0 text-[10px]">×3</Badge>}
                        </td>
                        <td className="max-w-[200px] truncate py-2 pr-2 text-xs" title={r.url}>{r.url.replace(/^https?:\/\//, '')}</td>
                        <td className="max-w-[220px] truncate py-2 pr-2 text-xs italic text-[var(--text-secondary)]" title={r.note ?? ''}>{r.note ?? ''}</td>
                        {(['scoreMobile', 'scoreDesktop'] as const).map((k) => (
                          <td key={k} className="py-2 pr-2 text-center">
                            {r[k] !== null ? (
                              <span className="font-semibold tabular-nums" style={{ color: RATING_COLOR[rate('score', r[k])] }}>{r[k]}</span>
                            ) : <span className="text-[var(--text-tertiary)]">—</span>}
                          </td>
                        ))}
                        <td className="whitespace-nowrap py-2 text-right" onClick={(e) => e.stopPropagation()}>
                          {r.status === 'failed' && <Badge variant="destructive" className="mr-1" title={r.error ?? ''}>{t('perflab.status_failed')}</Badge>}
                          {incomplete && active?.id !== r.id && (
                            <Button variant="outline" size="sm" className="mr-1 h-7 text-xs" disabled={busy} onClick={() => runLoop(r)}>
                              <Play className="h-3 w-3" /> {t('perflab.resume')}
                            </Button>
                          )}
                          {incomplete && active?.id === r.id && <Loader2 className="mr-2 inline h-4 w-4 animate-spin text-[var(--accent-primary)]" />}
                          <Button variant="ghost" size="icon" className="h-7 w-7" disabled={active?.id === r.id}
                            onClick={() => remove(r)} title={t('common.delete')}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
          <p className="mt-3 text-xs text-[var(--text-tertiary)]">{t('perflab.history_hint')}</p>
        </CardContent>
      </Card>

      {/* Leyenda de colores (umbrales de Google) */}
      <div className="flex flex-wrap items-center gap-3 text-xs text-[var(--text-tertiary)]">
        {([['good', '90–100'], ['average', '50–89'], ['poor', '0–49']] as const).map(([k, label]) => (
          <span key={k} className="flex items-center gap-1">
            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: RATING_COLOR[k] }} /> {label}
          </span>
        ))}
        <span>· {t('perflab.legend')}</span>
      </div>
    </div>
  )
}
