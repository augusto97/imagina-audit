import { useEffect, useState } from 'react'
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
  }
  recommendationTable: Array<{ minMb: number; maxMb: number; concurrency: number; label: string }>
}

export default function SettingsQueue() {
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
      toast.success('Configuración de cola guardada')
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  const recommended = status?.system.recommendedConcurrency
  const ramMb = status?.system.totalRamMb
  const ramLabel = ramMb ? `${(ramMb / 1024).toFixed(1)} GB (${ramMb} MB)` : 'No detectada'
  const detectionSource = status?.system.detectionSource ?? 'none'
  const sourceLabel: Record<string, string> = {
    proc: '/proc/meminfo',
    cgroup: 'cgroup',
    free: 'free -m',
    manual: 'configurado manualmente',
    none: 'sin detección',
  }
  const isOverRecommended = recommended !== null && recommended !== undefined && maxConcurrent > recommended
  const isAtRecommended = recommended === maxConcurrent

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Cola de Auditorías</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          Control de concurrencia, reintentos y retención.
        </p>
      </div>

      {/* Estado en vivo */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5 text-[var(--accent-primary)]" /> Estado en vivo
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <StatCard
              label="Corriendo ahora"
              value={status?.concurrency.running ?? 0}
              suffix={` / ${status?.concurrency.maxConcurrent ?? 0}`}
              color={status && status.concurrency.utilizationPct > 80 ? 'amber' : 'emerald'}
            />
            <StatCard
              label="En cola"
              value={status?.concurrency.queued ?? 0}
              color={status && status.concurrency.queued > 5 ? 'amber' : 'gray'}
            />
            <StatCard
              label="Completados (1h)"
              value={status?.lastHour.completed ?? 0}
              color="emerald"
            />
            <StatCard
              label="Fallidos (1h)"
              value={status?.lastHour.failed ?? 0}
              color={status && status.lastHour.failed > 3 ? 'red' : 'gray'}
            />
          </div>

          {status && status.lastHour.avgDurationSec > 0 && (
            <p className="mt-4 text-sm text-[var(--text-secondary)]">
              Duración media de audit: <b>{status.lastHour.avgDurationSec.toFixed(1)}s</b>
              {status.concurrency.queued > 0 && (
                <> · Tiempo estimado para el último en la cola: <b>~{Math.ceil((status.concurrency.queued * status.lastHour.avgDurationSec) / Math.max(1, status.concurrency.maxConcurrent) / 60)} min</b></>
              )}
            </p>
          )}

          {status && status.runningJobs.length > 0 && (
            <div className="mt-4">
              <p className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">
                Jobs activos
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
                URLs con fallos repetidos (última hora)
              </p>
              <div className="space-y-1 text-xs">
                {status.problematicUrls.map((p) => (
                  <div key={p.url} className="p-2 rounded bg-red-50 border border-red-200">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate font-mono">{p.url}</span>
                      <Badge variant="destructive" className="shrink-0">{p.failures} fallos</Badge>
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
          <CardTitle>Recursos detectados</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">RAM del servidor</p>
              <p className="font-bold text-[var(--text-primary)]">{ramLabel}</p>
              <p className="text-[10px] text-[var(--text-tertiary)] mt-0.5">Fuente: {sourceLabel[detectionSource]}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">RAM disponible</p>
              <p className="font-bold text-[var(--text-primary)]">
                {status?.system.availableRamMb ? `${(status.system.availableRamMb / 1024).toFixed(1)} GB` : '—'}
              </p>
            </div>
            <div>
              <p className="text-xs text-[var(--text-tertiary)]">PHP memory_limit</p>
              <p className="font-bold text-[var(--text-primary)]">{status?.system.phpMemoryLimitMb ?? '?'} MB</p>
            </div>
          </div>

          <div className="rounded-lg border border-[var(--border-default)] p-3 bg-[var(--bg-secondary)]">
            <Label className="text-sm">Forzar RAM total (MB)</Label>
            <div className="flex items-center gap-2 mt-1">
              <Input
                type="number" min={256} max={131072} step={256}
                value={manualRamMb}
                onChange={(e) => setManualRamMb(e.target.value)}
                placeholder="ej. 1536 para 1.5 GB"
                className="w-48"
              />
              {manualRamMb && (
                <Button variant="ghost" size="sm" onClick={() => setManualRamMb('')}>Limpiar</Button>
              )}
            </div>
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              {detectionSource === 'none'
                ? 'Tu hosting restringe la lectura de /proc/meminfo. Ingresa la RAM total (en MB) de tu plan de hosting — normalmente aparece en la confirmación del proveedor (ServerAvatar, cPanel, etc.).'
                : 'Sobrescribe la RAM detectada automáticamente. Útil si el valor detectado no coincide con el plan contratado.'}
            </p>
          </div>

          {recommended !== null && recommended !== undefined && (
            <div className={`rounded-lg border p-3 text-sm ${isAtRecommended ? 'bg-emerald-50 border-emerald-200' : isOverRecommended ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
              {isAtRecommended && <CheckCircle className="h-4 w-4 inline-block mr-2 text-emerald-600" />}
              {isOverRecommended && <AlertTriangle className="h-4 w-4 inline-block mr-2 text-amber-600" />}
              <span>
                Recomendado para tu hardware: <b>{recommended} slots concurrentes</b>.
                {isAtRecommended && ' Tu configuración actual coincide.'}
                {isOverRecommended && ` Tu configuración actual (${maxConcurrent}) está por encima — posible riesgo de OOM bajo carga.`}
              </span>
            </div>
          )}

          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">
              Tabla de recomendaciones
            </p>
            <div className="rounded-lg border border-[var(--border-default)] overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-[var(--bg-secondary)]">
                  <tr>
                    <th className="text-left px-3 py-2 font-semibold">RAM del servidor</th>
                    <th className="text-left px-3 py-2 font-semibold">audit_max_concurrent</th>
                  </tr>
                </thead>
                <tbody>
                  {status?.recommendationTable.map((row) => {
                    const isMatch = ramMb !== null && ramMb !== undefined && ramMb >= row.minMb && ramMb <= row.maxMb
                    return (
                      <tr key={row.minMb} className={`border-t border-[var(--border-default)] ${isMatch ? 'bg-[var(--accent-primary)]/5 font-medium' : ''}`}>
                        <td className="px-3 py-2">
                          {row.label}
                          {isMatch && <Badge variant="secondary" className="ml-2 text-[10px]">tu servidor</Badge>}
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
          <CardTitle>Parámetros</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label className="flex items-center gap-2">
              Audits simultáneos (slots)
              {recommended !== null && recommended !== undefined && (
                <Badge variant="secondary" className="text-[10px]">recomendado: {recommended}</Badge>
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
              Cuántos audits pueden correr en paralelo. El resto se encola.
            </p>
          </div>

          <div>
            <Label>Tiempo máximo por audit (segundos)</Label>
            <Input
              type="number" min={30} max={600}
              value={staleSeconds}
              onChange={(e) => setStaleSeconds(parseInt(e.target.value) || 180)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              Pasado este tiempo, un audit 'running' se considera huérfano y se libera.
            </p>
          </div>

          <div>
            <Label>Cache de fallo por URL (minutos)</Label>
            <Input
              type="number" min={0} max={60}
              value={failureCacheMin}
              onChange={(e) => setFailureCacheMin(parseInt(e.target.value) || 0)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              Si una URL falló, durante N minutos rechazamos nuevos audits a la misma URL sin ejecutarlos.
            </p>
          </div>

          <div>
            <Label>Máximo de intentos por audit</Label>
            <Input
              type="number" min={1} max={10}
              value={maxAttempts}
              onChange={(e) => setMaxAttempts(parseInt(e.target.value) || 3)}
              className="mt-1 w-32"
            />
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              Tras N intentos fallidos, el audit se abandona permanentemente.
            </p>
          </div>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        Guardar configuración de cola
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
