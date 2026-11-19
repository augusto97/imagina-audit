import { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import {
  Download, RefreshCw, Loader2, CheckCircle2, XCircle, Circle, AlertTriangle, ExternalLink,
  RotateCcw, ShieldCheck, ShieldAlert, History, Settings2, Undo2, Copy,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Skeleton } from '@/components/ui/skeleton'
import api from '@/lib/api'
import { API_BASE_URL } from '@/lib/constants'

type UpdStatus = 'idle' | 'running' | 'switched' | 'completed' | 'failed' | 'cancelled' | 'rolled_back'

interface Release {
  version: string
  tag: string
  name: string
  body?: string
  publishedAt: string
  htmlUrl: string
  zip: { size: number }
  manifest?: { notices?: string[]; autoInstall?: boolean } | null
}

interface UpdState {
  id?: string
  kind?: string
  status: UpdStatus
  step?: string
  fromVersion?: string
  toVersion?: string
  checks?: Array<{ name: string; ok: boolean; detail: string; blocking: boolean }>
  notices?: string[]
  log?: Array<{ at: number; step: string; level: string; msg: string }>
  error?: string | null
  opsToken?: string
  rescueToken?: string
  verified?: boolean
}

interface Exposure {
  checkedAt: number
  reachable: boolean
  server: string
  exposed: string[]
  protected: string[]
  envExposed: boolean
  nginxSnippet: string | null
}

interface HistoryItem { id: string; kind: string; from: string; to: string; status: UpdStatus; finishedAt: number; error: string | null }

interface UpdStatusResponse {
  installed: string
  deployed: boolean
  latest: { checkedAt: number; release: Release | null; error: string | null } | null
  updateAvailable: boolean
  state: UpdState
  busy: boolean
  previous: { version: string } | null
  history: HistoryItem[]
  workDir: string
  workDirInsideWebRoot: boolean
  settings: { repo: string; autoInstall: boolean; appUrlConfigured: boolean }
  exposure?: Exposure | null
}

type ApiError = { response?: { status?: number; data?: { error?: string } } }

const ENDPOINT = '/admin/update.php'
const STEPS = ['preflight', 'download', 'extract', 'backup', 'migrate', 'env', 'switch', 'finalize'] as const
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))
const rescueUrl = () => `${API_BASE_URL.replace(/\/api\/?$/, '')}/update-rescue.php`

const fmtDate = (ts: number | string | null | undefined, lang: string) => {
  if (!ts) return '—'
  const d = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts)
  return isNaN(d.getTime()) ? '—' : d.toLocaleString(lang, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/**
 * Actualizaciones desde GitHub Releases. Instalación en pasos guiados por
 * el navegador (cada paso es una petición corta): requisitos → descarga y
 * sha256 → descompresión → copia de la base → migraciones con el código
 * nuevo → .env → cambio de versión + verificación → finalización.
 */
export default function AdminUpdates() {
  const { t, i18n } = useTranslation()
  const [data, setData] = useState<UpdStatusResponse | null>(null)
  const [state, setState] = useState<UpdState | null>(null)
  const [running, setRunning] = useState(false)
  const [checking, setChecking] = useState(false)
  const [repo, setRepo] = useState('')
  const loopToken = useRef(0)
  const mounted = useRef(true)
  const resumed = useRef(false)

  useEffect(() => {
    mounted.current = true
    return () => { mounted.current = false; loopToken.current++ }
  }, [])

  const load = useCallback(async () => {
    try {
      const res = await api.get<{ data: UpdStatusResponse }>(ENDPOINT)
      if (!mounted.current) return null
      setData((prev) => ({ ...res.data.data, exposure: res.data.data.exposure ?? prev?.exposure }))
      setState(res.data.data.state)
      setRepo(res.data.data.settings.repo)
      return res.data.data
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.load_error'))
      return null
    }
  }, [t])

  const rescue = useCallback(async (token: string | undefined) => {
    try {
      const r = await axios.get(rescueUrl(), { params: { token, format: 'json' }, timeout: 60000 })
      toast.error(r.data?.message || t('updates.rolled_back'))
    } catch (e) {
      const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message
      toast.error(msg || t('updates.rescue_failed'))
    }
  }, [t])

  /** Avanza los pasos hasta que la actualización termina. */
  const runLoop = useCallback(async (initial: UpdState) => {
    const token = ++loopToken.current
    const alive = () => mounted.current && loopToken.current === token
    setRunning(true)
    let current = initial
    let transient = 0
    let finalizeFailures = 0

    while (alive() && (current.status === 'running' || current.status === 'switched')) {
      try {
        if (current.status === 'running' && current.step === 'migrate' && current.opsToken) {
          // Migraciones: las ejecuta el código NUEVO en una petición aparte.
          const r = await axios.get(`${API_BASE_URL}/update-migrate.php`, { params: { token: current.opsToken }, timeout: 620000 })
          current = r.data.data as UpdState
        } else {
          const r = await api.post<{ data: { state: UpdState } }>(ENDPOINT, { action: 'step' }, { timeout: 330000 })
          current = r.data.data.state
        }
        if (!alive()) return
        setState(current)
        transient = 0
        if (current.status === 'switched') {
          // El código nuevo respondió pero aún no pudo finalizar (p.ej. el
          // servidor sigue sirviendo la versión vieja): reintentar unas
          // veces y, si no se resuelve, volver atrás con el rescate.
          if (++finalizeFailures >= 6) {
            await rescue(current.rescueToken)
            await load()
            setRunning(false)
            return
          }
          await sleep(3000)
        } else {
          finalizeFailures = 0
        }
      } catch (err) {
        if (!alive()) return
        const status = (err as ApiError).response?.status ?? 0
        if (status === 409) { await sleep(3000); continue }
        // Tras el cambio de versión, la finalización la responde el código
        // nuevo: si falla una y otra vez, volver atrás con el rescate.
        if (current.status === 'switched') {
          if (++finalizeFailures >= 4) {
            await rescue(current.rescueToken)
            await load()
            setRunning(false)
            return
          }
          await sleep(4000)
          continue
        }
        if (status === 0 || status === 408 || status === 429 || status >= 500) {
          transient++
          if (transient > 15) { toast.error(t('updates.step_error')); break }
          await sleep(4000)
          const fresh = await load()
          if (fresh) current = fresh.state
          continue
        }
        toast.error((err as ApiError).response?.data?.error || t('updates.step_error'))
        break
      }
    }
    if (!alive()) return
    setRunning(false)
    if (current.status === 'completed') {
      await load()
      toast.success(t('updates.done_toast', { v: current.toVersion }))
      // El navegador aún tiene el bundle viejo: recargar para usar el nuevo.
      setTimeout(() => window.location.reload(), 2500)
    } else {
      load()
    }
  }, [load, rescue, t])

  useEffect(() => {
    load().then((d) => {
      // Si quedó una actualización a medias (pestaña cerrada), continuarla.
      if (d && !resumed.current && (d.state.status === 'running' || d.state.status === 'switched') && d.busy) {
        resumed.current = true
        runLoop(d.state)
      }
    })
  }, [load, runLoop])

  const check = async () => {
    setChecking(true)
    try {
      const r = await api.post<{ data: UpdStatusResponse }>(ENDPOINT, { action: 'check' })
      setData((prev) => ({ ...r.data.data, exposure: r.data.data.exposure ?? prev?.exposure }))
      setState(r.data.data.state)
      if (r.data.data.latest?.error) toast.error(r.data.data.latest.error)
      else toast.success(r.data.data.updateAvailable ? t('updates.found', { v: r.data.data.latest?.release?.version }) : t('updates.up_to_date'))
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.load_error'))
    } finally {
      setChecking(false)
    }
  }

  const install = async () => {
    const v = data?.latest?.release?.version
    if (!v || !confirm(t('updates.install_confirm', { v }))) return
    try {
      const r = await api.post<{ data: { state: UpdState } }>(ENDPOINT, { action: 'begin' })
      setState(r.data.data.state)
      runLoop(r.data.data.state)
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.step_error'))
    }
  }

  const [checkingExposure, setCheckingExposure] = useState(false)
  const recheckExposure = async () => {
    setCheckingExposure(true)
    try {
      const r = await api.post<{ data: UpdStatusResponse }>(ENDPOINT, { action: 'exposure' }, { timeout: 60000 })
      setData(r.data.data)
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.load_error'))
    } finally {
      setCheckingExposure(false)
    }
  }

  const copySnippet = async (text: string) => {
    try { await navigator.clipboard.writeText(text); toast.success(t('common.copied')) } catch { /* sin portapapeles */ }
  }

  const cancel = async () => {
    if (!confirm(t('updates.cancel_confirm'))) return
    loopToken.current++
    try {
      await api.post(ENDPOINT, { action: 'cancel' })
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.step_error'))
    }
    setRunning(false)
    load()
  }

  const rollback = async () => {
    const v = data?.previous?.version
    if (!v || !confirm(t('updates.rollback_confirm', { v }))) return
    try {
      const r = await api.post<{ data: { state: UpdState } }>(ENDPOINT, { action: 'rollback' }, { timeout: 330000 })
      setState(r.data.data.state)
      runLoop(r.data.data.state)
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.step_error'))
    }
  }

  const saveSettings = async (patch: { repo?: string; autoInstall?: boolean }) => {
    try {
      const r = await api.post<{ data: UpdStatusResponse }>(ENDPOINT, { action: 'settings', ...patch })
      setData((prev) => ({ ...r.data.data, exposure: r.data.data.exposure ?? prev?.exposure }))
      setRepo(r.data.data.settings.repo)
      toast.success(t('updates.settings_saved'))
    } catch (err) {
      toast.error((err as ApiError).response?.data?.error || t('updates.load_error'))
    }
  }

  if (!data || !state) {
    return <div className="space-y-4"><Skeleton className="h-8 w-56" /><Skeleton className="h-40 rounded-lg" /></div>
  }

  const rel = data.latest?.release ?? null
  const active = state.status === 'running' || state.status === 'switched'
  const currentIdx = STEPS.indexOf((state.step ?? 'preflight') as typeof STEPS[number])
  const canCancel = state.status === 'running' && currentIdx >= 0 && currentIdx < STEPS.indexOf('switch')
  // Los avisos del release solo tienen sentido antes de instalarlo.
  const notices = active ? (state.notices ?? []) : (data.updateAvailable ? (rel?.manifest?.notices ?? []) : [])
  const exp = data.exposure ?? null
  const expBad = !!exp && (exp.exposed.length > 0 || exp.envExposed)
  const showResult = !active && state.id && ['completed', 'failed', 'cancelled', 'rolled_back'].includes(state.status)

  return (
    <div className="space-y-4">
      <div>
        <h2 className="flex items-center gap-2 text-lg font-semibold text-[var(--text-primary)]">
          <Download className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} /> {t('updates.title')}
        </h2>
        <p className="mt-0.5 text-sm text-[var(--text-secondary)]">{t('updates.subtitle')}</p>
      </div>

      {!data.deployed && (
        <Card className="border-0 bg-amber-50 shadow-sm">
          <CardContent className="flex gap-2 p-3 text-sm text-amber-800">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> {t('updates.not_deployed')}
          </CardContent>
        </Card>
      )}

      {/* Versión instalada / disponible */}
      <Card className="border-0 shadow-sm">
        <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <p className="text-sm text-[var(--text-secondary)]">
              {t('updates.installed')}: <span className="font-semibold text-[var(--text-primary)]">v{data.installed}</span>
            </p>
            {data.updateAvailable && rel ? (
              <p className="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant="success">{t('updates.available', { v: rel.version })}</Badge>
                <span className="text-xs text-[var(--text-tertiary)]">{t('updates.published', { date: fmtDate(rel.publishedAt, i18n.language) })}</span>
                {!rel.body && rel.htmlUrl && (
                  <a href={rel.htmlUrl} target="_blank" rel="noreferrer" className="flex items-center gap-1 text-xs text-[var(--accent-primary)] hover:underline">
                    {t('updates.see_notes')} <ExternalLink className="h-3 w-3" />
                  </a>
                )}
              </p>
            ) : !rel && data.latest?.error ? (
              <p className="flex items-center gap-1.5 text-sm text-amber-700"><AlertTriangle className="h-4 w-4" /> {t('updates.check_failed')}</p>
            ) : (
              <p className="flex items-center gap-1.5 text-sm text-emerald-700"><CheckCircle2 className="h-4 w-4" /> {t('updates.up_to_date')}</p>
            )}
            <p className="text-xs text-[var(--text-tertiary)]">
              {t('updates.last_check', { date: fmtDate(data.latest?.checkedAt, i18n.language) })} · {data.settings.repo}
            </p>
            {data.latest?.error && <p className="text-xs text-red-600">{data.latest.error}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={check} disabled={checking || running}>
              {checking ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />} {t('updates.check_now')}
            </Button>
            {data.updateAvailable && rel && (
              <Button size="sm" onClick={install} disabled={running || active || !data.deployed}>
                <Download className="h-3.5 w-3.5" /> {t('updates.install', { v: rel.version })}
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {notices.length > 0 && (
        <Card className="border-0 bg-amber-50 shadow-sm">
          <CardContent className="space-y-1 p-3 text-sm text-amber-800">
            <p className="font-medium">{t('updates.notices_title')}</p>
            {notices.map((n, i) => <p key={i} className="flex gap-1.5"><AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" /> {n}</p>)}
          </CardContent>
        </Card>
      )}

      {/* Progreso */}
      {active && (
        <Card className="border-0 shadow-sm">
          <CardContent className="space-y-3 p-4">
            <div className="flex items-center justify-between gap-2">
              <p className="flex items-center gap-2 text-sm font-semibold text-[var(--text-primary)]">
                <Loader2 className="h-4 w-4 animate-spin text-[var(--accent-primary)]" />
                {state.kind === 'rollback'
                  ? t('updates.rolling_back', { from: state.fromVersion, to: state.toVersion })
                  : t('updates.installing', { from: state.fromVersion, to: state.toVersion })}
              </p>
              {canCancel && <Button variant="outline" size="sm" onClick={cancel}>{t('updates.cancel')}</Button>}
            </div>
            <ol className="space-y-1.5">
              {STEPS.filter((s) => state.kind !== 'rollback' || ['switch', 'finalize'].includes(s)).map((s) => {
                const idx = STEPS.indexOf(s)
                const done = idx < currentIdx || (s === 'switch' && state.status === 'switched')
                const now = !done && idx === currentIdx
                return (
                  <li key={s} className="flex items-center gap-2 text-sm">
                    {done ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : now ? <Loader2 className="h-4 w-4 animate-spin text-[var(--accent-primary)]" /> : <Circle className="h-4 w-4 text-[var(--text-tertiary)]" />}
                    <span className={done ? 'text-[var(--text-secondary)]' : now ? 'font-medium text-[var(--text-primary)]' : 'text-[var(--text-tertiary)]'}>{t(`updates.step_${s}`)}</span>
                  </li>
                )
              })}
            </ol>
            <p className="text-xs text-[var(--text-tertiary)]">{t('updates.dont_close')}</p>
          </CardContent>
        </Card>
      )}

      {/* Resultado de la última actualización */}
      {showResult && (
        <Card className={`border-0 shadow-sm ${state.status === 'completed' ? 'bg-emerald-50' : state.status === 'cancelled' ? 'bg-[var(--bg-secondary)]' : 'bg-red-50'}`}>
          <CardContent className="space-y-2 p-4 text-sm">
            <p className="flex items-center gap-2 font-semibold">
              {state.status === 'completed' ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <XCircle className="h-4 w-4 text-red-600" />}
              {t(`updates.result_${state.status}`, { from: state.fromVersion, to: state.toVersion })}
            </p>
            {state.error && <p className="text-red-700">{state.error}</p>}
            {!!state.checks?.some((c) => !c.ok) && (
              <ul className="space-y-0.5 text-xs">
                {state.checks.map((c) => (
                  <li key={c.name} className={c.ok ? 'text-[var(--text-secondary)]' : 'font-medium text-red-700'}>{c.ok ? '✓' : '✗'} {c.detail}</li>
                ))}
              </ul>
            )}
            {!!state.log?.length && (
              <details className="text-xs text-[var(--text-secondary)]">
                <summary className="cursor-pointer">{t('updates.show_log')}</summary>
                <ul className="mt-1 space-y-0.5 font-mono">
                  {state.log.map((l, i) => <li key={i} className={l.level === 'error' ? 'text-red-700' : l.level === 'warn' ? 'text-amber-700' : ''}>[{l.step}] {l.msg}</li>)}
                </ul>
              </details>
            )}
          </CardContent>
        </Card>
      )}

      {/* Notas del release */}
      {data.updateAvailable && rel?.body && (
        <Card className="border-0 shadow-sm">
          <CardContent className="space-y-2 p-4">
            <div className="flex items-center justify-between">
              <p className="text-sm font-semibold text-[var(--text-primary)]">{t('updates.release_notes', { v: rel.version })}</p>
              {rel.htmlUrl && (
                <a href={rel.htmlUrl} target="_blank" rel="noreferrer" className="flex items-center gap-1 text-xs text-[var(--accent-primary)] hover:underline">
                  GitHub <ExternalLink className="h-3 w-3" />
                </a>
              )}
            </div>
            <div className="max-h-80 overflow-y-auto whitespace-pre-wrap rounded-md bg-[var(--bg-secondary)] p-3 text-xs leading-relaxed text-[var(--text-secondary)]">{rel.body}</div>
          </CardContent>
        </Card>
      )}

      {/* Protección de carpetas: comprobación real por HTTP */}
      {exp && (
        <Card className={`border-0 shadow-sm ${expBad ? 'bg-red-50' : ''}`}>
          <CardContent className="space-y-2 p-4 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="flex items-center gap-2 font-semibold text-[var(--text-primary)]">
                {expBad ? <ShieldAlert className="h-4 w-4 text-red-600" /> : <ShieldCheck className="h-4 w-4 text-emerald-600" />}
                {t('updates.exposure_title')}
              </p>
              <Button variant="outline" size="sm" onClick={recheckExposure} disabled={checkingExposure}>
                {checkingExposure ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />} {t('updates.exposure_recheck')}
              </Button>
            </div>
            {!exp.reachable ? (
              <p className="text-[var(--text-secondary)]">{t('updates.exposure_unreachable')}</p>
            ) : expBad ? (
              <>
                <p className="text-red-700">
                  {t('updates.exposure_bad', { list: [...exp.exposed.map((d) => `/${d}/`), ...(exp.envExposed ? ['.env'] : [])].join(', ') })}
                </p>
                {exp.server === 'Nginx' || exp.server === 'Cloudflare' || exp.server === '' ? (
                  <>
                    <p className="text-xs text-[var(--text-secondary)]">{t(exp.server === 'Nginx' ? 'updates.exposure_fix_nginx' : 'updates.exposure_fix_unknown')}</p>
                    {exp.nginxSnippet && (
                      <div className="relative">
                        <pre className="overflow-x-auto rounded-md bg-gray-900 p-3 text-[11px] leading-relaxed text-gray-100">{exp.nginxSnippet}</pre>
                        <Button variant="secondary" size="sm" className="absolute right-2 top-2 h-7" onClick={() => copySnippet(exp.nginxSnippet!)}>
                          <Copy className="h-3 w-3" /> {t('updates.exposure_copy')}
                        </Button>
                      </div>
                    )}
                  </>
                ) : (
                  <p className="text-xs text-[var(--text-secondary)]">{t('updates.exposure_fix_apache', { server: exp.server })}</p>
                )}
              </>
            ) : (
              <p className="text-emerald-700">{t('updates.exposure_ok', { server: exp.server || t('updates.exposure_server_unknown') })}</p>
            )}
            <p className="text-xs text-[var(--text-tertiary)]">{t('updates.exposure_hint', { date: fmtDate(exp.checkedAt, i18n.language) })}</p>
          </CardContent>
        </Card>
      )}

      {/* Ajustes */}
      <Card className="border-0 shadow-sm">
        <CardContent className="space-y-4 p-4">
          <p className="flex items-center gap-2 text-sm font-semibold text-[var(--text-primary)]"><Settings2 className="h-4 w-4" /> {t('updates.settings')}</p>
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-[var(--text-primary)]">{t('updates.auto_install')}</p>
              <p className="text-xs text-[var(--text-secondary)]">{t('updates.auto_install_hint')}</p>
              {data.settings.autoInstall && !data.settings.appUrlConfigured && (
                <p className="mt-1 text-xs text-amber-700">{t('updates.auto_install_app_url')}</p>
              )}
            </div>
            <Switch checked={data.settings.autoInstall} onCheckedChange={(v) => saveSettings({ autoInstall: v })} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('updates.repo_label')}</label>
            <div className="flex gap-2">
              <Input value={repo} onChange={(e) => setRepo(e.target.value)} placeholder="owner/repo" className="h-8 max-w-xs text-sm" />
              <Button variant="outline" size="sm" onClick={() => saveSettings({ repo })} disabled={repo === data.settings.repo}>{t('common.save')}</Button>
            </div>
            <p className="mt-1 text-xs text-[var(--text-tertiary)]">{t('updates.repo_hint')}</p>
          </div>
          <div className="flex gap-2 rounded-md bg-[var(--bg-secondary)] p-3 text-xs text-[var(--text-secondary)]">
            <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-600" />
            <span>{t('updates.safety')}</span>
          </div>
        </CardContent>
      </Card>

      {/* Volver atrás */}
      {data.previous && (
        <Card className="border-0 shadow-sm">
          <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p className="flex items-center gap-2 text-sm font-semibold text-[var(--text-primary)]"><Undo2 className="h-4 w-4" /> {t('updates.previous_title')}</p>
              <p className="text-xs text-[var(--text-secondary)]">{t('updates.previous_hint', { v: data.previous.version })}</p>
            </div>
            <Button variant="outline" size="sm" onClick={rollback} disabled={running || active}>
              <RotateCcw className="h-3.5 w-3.5" /> {t('updates.rollback', { v: data.previous.version })}
            </Button>
          </CardContent>
        </Card>
      )}

      {/* Historial */}
      {data.history.length > 0 && (
        <Card className="border-0 shadow-sm">
          <CardContent className="p-4">
            <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--text-primary)]"><History className="h-4 w-4" /> {t('updates.history')}</p>
            <div className="divide-y divide-[var(--border-default)]">
              {data.history.map((h, i) => (
                <div key={`${h.id}-${i}`} className="flex flex-wrap items-center gap-2 py-1.5 text-xs">
                  <span className="w-32 shrink-0 text-[var(--text-tertiary)]">{fmtDate(h.finishedAt, i18n.language)}</span>
                  <span className="font-medium">v{h.from} → v{h.to}</span>
                  {h.kind !== 'manual' && <Badge variant="secondary">{t(`updates.kind_${h.kind}`)}</Badge>}
                  <Badge variant={h.status === 'completed' ? 'success' : h.status === 'cancelled' ? 'secondary' : 'destructive'}>{t(`updates.status_${h.status}`)}</Badge>
                  {h.error && <span className="w-full truncate text-[var(--text-tertiary)]" title={h.error}>{h.error}</span>}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
