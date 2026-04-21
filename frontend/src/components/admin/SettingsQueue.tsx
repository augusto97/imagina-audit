import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Save, Server, AlertTriangle, CheckCircle } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

interface QueueStatus {
  concurrency: { running: number; queued: number; maxConcurrent: number; utilizationPct: number }
  lastHour: { completed: number; failed: number; avgDurationSec: number }
  runningJobs: Array<{ auditId: string; url: string; ageSec: number }>
  problematicUrls: Array<{ url: string; failures: number; lastError: string }>
  system: {
    totalRamMb: number | null
    availableRamMb: number | null
    detectionSource: 'proc' | 'cgroup' | 'free' | 'manual' | 'none'
    manualOverrideMb: number | null
    phpMemoryLimitMb: number
    phpVersion: string
    recommendedConcurrency: number | null
    diagnostics: {
      openBasedir: string
      disableFunctions: string
      procMeminfo: 'ok' | 'not_found' | 'not_readable' | 'blocked_by_open_basedir'
      cgroupV2: string
      cgroupV1: string
      shellExec: 'ok' | 'missing_function' | 'disabled'
      hint: string
    }
  }
  recommendationTable: Array<{ minMb: number; maxMb: number; concurrency: number; label: string }>
}

export default function SettingsQueue() {
  const { t } = useTranslation()
  const { fetchSettings, updateSettings, fetchQueueStatus } = useAdmin()
  const [status, setStatus] = useState<QueueStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [maxConcurrent, setMaxConcurrent] = useState(3)
  const [staleSeconds, setStaleSeconds] = useState(180)
  const [failureCacheMin, setFailureCacheMin] = useState(10)
  const [maxAttempts, setMaxAttempts] = useState(3)
  const [manualRamMb, setManualRamMb] = useState<string>('')

  // Carga inicial: settings + status
  useEffect(() => {
    Promise.all([fetchSettings(), fetchQueueStatus()]).then(([settings, queueStatus]) => {
      if (settings) {
        setMaxConcurrent(Number(settings.auditMaxConcurrent ?? settings.audit_max_concurrent ?? 3))
        setStaleSeconds(Number(settings.auditStaleSeconds ?? settings.audit_stale_seconds ?? 180))
        setFailureCacheMin(Number(settings.auditFailureCacheMinutes ?? settings.audit_failure_cache_minutes ?? 10))
        setMaxAttempts(Number(settings.auditMaxAttempts ?? settings.audit_max_attempts ?? 3))
        const ramOverride = settings.systemTotalRamMb ?? settings.system_total_ram_mb
        setManualRamMb(ramOverride ? String(ramOverride) : '')
      }
      if (queueStatus) setStatus(queueStatus as QueueStatus)
      setLoading(false)
    })
  }, [fetchSettings, fetchQueueStatus])

  // Polling del status cada 3s (solo el status, no los settings)
  useEffect(() => {
    if (loading) return
    const timer = setInterval(() => {
      fetchQueueStatus().then((s) => { if (s) setStatus(s as QueueStatus) })
    }, 3000)
    return () => clearInterval(timer)
  }, [loading, fetchQueueStatus])

  const save = async () => {
    setSaving(true)
    try {
      const payload: Record<string, unknown> = {
        auditMaxConcurrent: maxConcurrent,
        auditStaleSeconds: staleSeconds,
        auditFailureCacheMinutes: failureCacheMin,
        auditMaxAttempts: maxAttempts,
      }
      const ramNum = manualRamMb.trim() === '' ? 0 : parseInt(manualRamMb, 10)
      payload.systemTotalRamMb = Number.isFinite(ramNum) && ramNum > 0 ? ramNum : ''
      await updateSettings(payload)
      fetchQueueStatus().then((s) => { if (s) setStatus(s as QueueStatus) })
      toast.success(t('settings.queue_saved'))
    } catch { toast.error(t('settings.save_error')) }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  const recommended = status?.system.recommendedConcurrency
  const ramMb = status?.system.totalRamMb
  const ramLabel = ramMb ? `${(ramMb / 1024).toFixed(1)} GB (${ramMb} MB)` : t('settings.queue_ram_not_detected')
  const detectionSource = status?.system.detectionSource ?? 'none'
  const sourceLabel: Record<string, string> = {
    proc: t('settings.queue_source_proc'),
    cgroup: t('settings.queue_source_cgroup'),
    free: t('settings.queue_source_free'),
    manual: t('settings.queue_source_manual'),
    none: t('settings.queue_source_none'),
  }
  const isOverRecommended = recommended !== null && recommended !== undefined && maxConcurrent > recommended
  const isAtRecommended = recommended === maxConcurrent

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.queue_title')}</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          {t('settings.queue_subtitle')}
        </p>
      </div>

      {/* Estado en vivo */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5 text-[var(--accent-primary)]" /> {t('settings.queue_live_card')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <StatCard
              label={t('settings.queue_running_now')}
              value={status?.concurrency.running ?? 0}
              suffix={` / ${status?.concurrency.maxConcurrent ?? 0}`}
              color={status && status.concurrency.utilizationPct > 80 ? 'amber' : 'emerald'}
            />
            <StatCard
              label={t('settings.queue_queued')}
              value={status?.concurrency.queued ?? 0}
              color={status && status.concurrency.queued > 5 ? 'amber' : 'gray'}
            />
            <StatCard
              label={t('settings.queue_completed_1h')}
              value={status?.lastHour.completed ?? 0}
              color="emerald"
            />
            <StatCard
              label={t('settings.queue_failed_1h')}
              value={status?.lastHour.failed ?? 0}
              color={status && status.lastHour.failed > 3 ? 'red' : 'gray'}
            />
          </div>

          {status && status.lastHour.avgDurationSec > 0 && (
            <p className="mt-4 text-sm text-[var(--text-secondary)]">
              <span dangerouslySetInnerHTML={{ __html: t('settings.queue_avg_duration', { sec: status.lastHour.avgDurationSec.toFixed(1) }) }} />
              {status.concurrency.queued > 0 && (
                <span dangerouslySetInnerHTML={{ __html: t('settings.queue_eta', { min: Math.ceil((status.concurrency.queued * status.lastHour.avgDurationSec) / Math.max(1, status.concurrency.maxConcurrent) / 60) }) }} />
              )}
            </p>
          )}

          {status && status.runningJobs.length > 0 && (
            <div className="mt-4">
              <p className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">
                {t('settings.queue_active_jobs')}
              </p>
              <div className="space-y-1 text-xs">
                {status.runningJobs.map((j) => (
                  <div key={j.auditId} className="flex items-center justify-between gap-2 p-2 rounded bg-[var(--bg-secondary)]">
                    <span className="truncate font-mono">{new URL(j.url).hostname}</span>
                    <span className={`tabular-nums ${j.ageSec > 120 ? 'text-amber-600' : 'text-[var(--text-tertiary)]'}`}>
                      {j.ageSec.toFixed(0)}s
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {status && status.problematicUrls.length > 0 && (
            <div className="mt-4">
              <p className="text-xs font-bold uppercase tracking-wider text-red-600 mb-2">
                {t('settings.queue_problematic')}
              </p>
              <div className="space-y-1 text-xs">
                {status.problematicUrls.map((p) => (
                  <div key={p.url} className="p-2 rounded bg-red-50 border border-red-200">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate font-mono">{p.url}</span>
                      <Badge variant="destructive" className="shrink-0">{t('settings.queue_failures_count', { count: p.failures })}</Badge>
                    </div>
                    {p.lastError && <p className="mt-1 text-[10px] text-red-700">{p.lastError}</p>}
                  </div>
                ))}
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Información del sistema */}
      <Card>
        <CardHeader>
          <CardTitle>{t('settings.queue_resources_card')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">{t('settings.queue_ram_server')}</p>
              <p className="font-bold text-[var(--text-primary)]">{ramLabel}</p>
              <p className="text-[10px] text-[var(--text-tertiary)] mt-0.5">{t('settings.queue_source_label', { source: sourceLabel[detectionSource] })}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">{t('settings.queue_ram_available')}</p>
              <p className="font-bold text-[var(--text-primary)]">
                {status?.system.availableRamMb ? `${(status.system.availableRamMb / 1024).toFixed(1)} GB` : '—'}
              </p>
            </div>
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">{t('settings.queue_php_limit')}</p>
              <p className="font-bold text-[var(--text-primary)]">{status?.system.phpMemoryLimitMb ?? '?'} MB</p>
            </div>
          </div>

          {detectionSource === 'none' && status?.system.diagnostics && (
            <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm space-y-2">
              <div className="flex items-start gap-2">
                <AlertTriangle className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-semibold text-amber-900">{t('settings.queue_detect_warning')}</p>
                  <p className="text-amber-900">{status.system.diagnostics.hint}</p>
                </div>
              </div>
              <details className="text-xs text-amber-900/80 pl-6">
                <summary className="cursor-pointer font-medium">{t('settings.queue_diagnostic_title')}</summary>
                <dl className="mt-1 space-y-0.5 font-mono">
                  <div>/proc/meminfo: <b>{status.system.diagnostics.procMeminfo}</b></div>
                  <div>cgroup v2: <b>{status.system.diagnostics.cgroupV2}</b></div>
                  <div>cgroup v1: <b>{status.system.diagnostics.cgroupV1}</b></div>
                  <div>shell_exec: <b>{status.system.diagnostics.shellExec}</b></div>
                  {status.system.diagnostics.openBasedir && (
                    <div className="break-all">open_basedir: <b>{status.system.diagnostics.openBasedir}</b></div>
                  )}
                </dl>
              </details>
              <details className="text-xs pl-6">
                <summary className="cursor-pointer font-medium text-amber-900/80">
                  {t('settings.queue_manual_ram_title')}
                </summary>
                <div className="flex items-center gap-2 mt-2">
                  <Input
                    type="number" min={256} max={131072} step={256}
                    value={manualRamMb}
                    onChange={(e) => setManualRamMb(e.target.value)}
                    placeholder={t('settings.queue_manual_ram_placeholder')}
                    className="w-48 h-8"
                  />
                  {manualRamMb && (
                    <Button variant="ghost" size="sm" onClick={() => setManualRamMb('')}>{t('settings.queue_manual_ram_clear')}</Button>
                  )}
                </div>
              </details>
            </div>
          )}

          {recommended !== null && recommended !== undefined && (
            <div className={`rounded-lg border p-3 text-sm ${isAtRecommended ? 'bg-emerald-50 border-emerald-200' : isOverRecommended ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
              {isAtRecommended && <CheckCircle className="h-4 w-4 inline-block mr-2 text-emerald-600" />}
              {isOverRecommended && <AlertTriangle className="h-4 w-4 inline-block mr-2 text-amber-600" />}
              <span>
                <span dangerouslySetInnerHTML={{ __html: t('settings.queue_recommend', { count: recommended }) }} />
                {isAtRecommended && t('settings.queue_recommend_match')}
                {isOverRecommended && t('settings.queue_recommend_over', { current: maxConcurrent })}
              </span>
            </div>
          )}

          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">
              {t('settings.queue_recommend_table')}
            </p>
            <div className="rounded-lg border border-[var(--border-default)] overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-[var(--bg-secondary)]">
                  <tr>
                    <th className="text-left px-3 py-2 font-semibold">{t('settings.queue_table_ram')}</th>
                    <th className="text-left px-3 py-2 font-semibold">{t('settings.queue_table_concurrent')}</th>
                  </tr>
                </thead>
                <tbody>
                  {status?.recommendationTable.map((row) => {
                    const isMatch = ramMb !== null && ramMb !== undefined && ramMb >= row.minMb && ramMb <= row.maxMb
                    return (
                      <tr key={row.minMb} className={`border-t border-[var(--border-default)] ${isMatch ? 'bg-[var(--accent-primary)]/5 font-medium' : ''}`}>
                        <td className="px-3 py-2">
                          {row.label}
                          {isMatch && <Badge variant="secondary" className="ml-2 text-[10px]">{t('settings.queue_table_your_server')}</Badge>}
                        </td>
                        <td className="px-3 py-2">{row.concurrency}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Configuración */}
      <Card>
        <CardHeader>
          <CardTitle>{t('settings.queue_params_card')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label className="flex items-center gap-2">
              {t('settings.queue_max_concurrent')}
              {recommended !== null && recommended !== undefined && (
                <Badge variant="secondary" className="text-[10px]">{t('settings.queue_max_recommended', { count: recommended })}</Badge>
              )}
            </Label>
            <div className="flex items-center gap-3 mt-1">
              <input
                type="range" min={1} max={15} step={1}
                value={maxConcurrent}
                onChange={(e) => setMaxConcurrent(parseInt(e.target.value))}
                className="flex-1 accent-[var(--accent-primary)]"
              />
              <span className="w-10 text-right font-mono font-bold">{maxConcurrent}</span>
            </div>
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              {t('settings.queue_max_hint')}
            </p>
          </div>

          <div>
            <Label>{t('settings.queue_stale')}</Label>
            <Input
              type="number" min={30} max={600}
              value={staleSeconds}
              onChange={(e) => setStaleSeconds(parseInt(e.target.value) || 180)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              {t('settings.queue_stale_hint')}
            </p>
          </div>

          <div>
            <Label>{t('settings.queue_failure_cache')}</Label>
            <Input
              type="number" min={0} max={60}
              value={failureCacheMin}
              onChange={(e) => setFailureCacheMin(parseInt(e.target.value) || 0)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              {t('settings.queue_failure_hint')}
            </p>
          </div>

          <div>
            <Label>{t('settings.queue_max_attempts')}</Label>
            <Input
              type="number" min={1} max={10}
              value={maxAttempts}
              onChange={(e) => setMaxAttempts(parseInt(e.target.value) || 3)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              {t('settings.queue_attempts_hint')}
            </p>
          </div>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        {t('settings.queue_save')}
      </Button>
    </div>
  )
}

function StatCard({ label, value, suffix, color }: {
  label: string
  value: number
  suffix?: string
  color: 'emerald' | 'amber' | 'red' | 'gray'
}) {
  const colors = {
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    red: 'text-red-600',
    gray: 'text-[var(--text-primary)]',
  }
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3">
      <p className="text-xs text-[var(--text-tertiary)]">{label}</p>
      <p className={`text-2xl font-bold tabular-nums ${colors[color]}`}>
        {value}{suffix && <span className="text-sm font-normal text-[var(--text-tertiary)]">{suffix}</span>}
      </p>
    </div>
  )
}
