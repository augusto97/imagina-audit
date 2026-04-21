import { useEffect, useState, useCallback } from 'react'
import { CheckCircle2, AlertTriangle, XCircle, Activity, RefreshCw, Clock, Info } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import api from '@/lib/api'

interface Check {
  id: string
  label: string
  status: 'ok' | 'warn' | 'fail'
  message: string
  details?: Record<string, unknown>
}

interface CronItem {
  name: string
  label: string
  description: string
  intervalHuman: string
  lastRunAt: string | null
  lastDurationSec: number | null
  ageHuman: string | null
  status: 'ok' | 'warning' | 'critical' | 'never'
  message: string
}

interface CronHealth {
  overallOk: boolean
  counts: { ok: number; warning: number; critical: number; never: number }
  items: CronItem[]
}

interface Diag {
  overall: 'ok' | 'warn' | 'fail'
  summary: { ok: number; warn: number; fail: number }
  checks: Check[]
  cronHealth: CronHealth | null
  generatedAt: string
}

/**
 * Panel de self-check del sistema. Llama al endpoint público /api/diag.php
 * (no requiere auth, pero lo renderizamos solo desde dentro del admin
 * para que los usuarios finales no lo vean).
 */
export default function SystemHealth() {
  const [diag, setDiag] = useState<Diag | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      // Cache-buster en query param: garantiza respuesta fresca incluso si
      // un proxy intermedio ignora los headers no-cache del backend.
      const res = await api.get('/diag.php', { params: { _t: Date.now() } })
      setDiag(res.data.data as Diag)
    } catch {
      toast.error('No se pudo cargar el diagnóstico')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  if (loading && !diag) return <Skeleton className="h-96 rounded-2xl" />
  if (!diag) return null

  const statusColor = {
    ok: 'text-emerald-600 bg-emerald-50 border-emerald-200',
    warn: 'text-amber-600 bg-amber-50 border-amber-200',
    fail: 'text-red-600 bg-red-50 border-red-200',
  }
  const statusIcon = {
    ok: <CheckCircle2 className="h-5 w-5" strokeWidth={2} />,
    warn: <AlertTriangle className="h-5 w-5" strokeWidth={2} />,
    fail: <XCircle className="h-5 w-5" strokeWidth={2} />,
  }
  const overallLabel = {
    ok: 'Todo en orden',
    warn: 'Con advertencias',
    fail: 'Problemas críticos detectados',
  }

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <Activity className="h-6 w-6 text-[var(--accent-primary)]" /> Estado del sistema
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">
            Verifica que todos los componentes del backend están funcionando correctamente.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={load} disabled={loading}>
          <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} strokeWidth={1.5} />
          Revisar de nuevo
        </Button>
      </div>

      {/* Resumen general */}
      <Card className={`border-2 ${statusColor[diag.overall]}`}>
        <CardContent className="py-4 px-5 flex items-center gap-4">
          <div className={statusColor[diag.overall]}>{statusIcon[diag.overall]}</div>
          <div className="flex-1">
            <p className="font-bold text-lg">{overallLabel[diag.overall]}</p>
            <p className="text-sm opacity-75">
              {diag.summary.ok} OK · {diag.summary.warn} advertencias · {diag.summary.fail} fallos
            </p>
          </div>
          <p className="text-xs text-[var(--text-tertiary)]">
            {new Date(diag.generatedAt).toLocaleTimeString('es-CO')}
          </p>
        </CardContent>
      </Card>

      {/* Cron health */}
      {diag.cronHealth && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
              Tareas automáticas (cron)
              <span className="ml-2 text-xs font-normal text-[var(--text-tertiary)]">
                {diag.cronHealth.counts.ok} ok
                {diag.cronHealth.counts.warning > 0 && <> · {diag.cronHealth.counts.warning} atrasados</>}
                {diag.cronHealth.counts.critical > 0 && <> · {diag.cronHealth.counts.critical} críticos</>}
                {diag.cronHealth.counts.never > 0 && <> · {diag.cronHealth.counts.never} nunca</>}
              </span>
            </CardTitle>
            <p className="mt-1 text-xs text-[var(--text-tertiary)]">
              Si alguna tarea aparece "nunca ejecutada" o "atrasada", el cron del sistema no está configurado.
              Revisa el panel de ServerAvatar → Cron Jobs y verifica que cada línea apunta al script correcto.
            </p>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {diag.cronHealth.items.map((cron) => {
                const bg =
                  cron.status === 'ok'       ? 'border-emerald-200 bg-emerald-50 text-emerald-900' :
                  cron.status === 'warning'  ? 'border-amber-200   bg-amber-50   text-amber-900' :
                  cron.status === 'critical' ? 'border-red-200     bg-red-50     text-red-900'   :
                                               'border-gray-200    bg-gray-50    text-gray-700'
                const icon =
                  cron.status === 'ok'       ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> :
                  cron.status === 'warning'  ? <AlertTriangle className="h-4 w-4 text-amber-600" /> :
                  cron.status === 'critical' ? <XCircle className="h-4 w-4 text-red-600" />        :
                                               <Info className="h-4 w-4 text-gray-500" />
                return (
                  <div key={cron.name} className={`rounded-lg border p-3 ${bg}`}>
                    <div className="flex items-start gap-3">
                      <div className="mt-0.5 shrink-0">{icon}</div>
                      <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <p className="font-medium text-[var(--text-primary)]">{cron.label}</p>
                          <code className="rounded bg-white/70 px-1.5 py-0.5 text-[10px] font-mono text-[var(--text-tertiary)]">
                            cada {cron.intervalHuman}
                          </code>
                        </div>
                        <p className="mt-0.5 text-xs opacity-85">{cron.description}</p>
                        <p className="mt-1 text-[11px] opacity-85">
                          <b>{cron.message}</b>
                          {cron.lastRunAt && (
                            <> · última vez hace <b>{cron.ageHuman}</b> ({new Date(cron.lastRunAt).toLocaleString('es-CO')})</>
                          )}
                        </p>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Lista de checks */}
      <Card>
        <CardHeader>
          <CardTitle>Verificaciones individuales</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {diag.checks.map((check) => (
              <div
                key={check.id}
                className={`rounded-lg border p-3 ${statusColor[check.status]}`}
              >
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 shrink-0">{statusIcon[check.status]}</div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-[var(--text-primary)]">{check.label}</p>
                    <p className="text-sm opacity-85 mt-0.5">{check.message}</p>
                    {check.details && Object.keys(check.details).length > 0 && check.status !== 'ok' && (
                      <details className="mt-2">
                        <summary className="text-xs cursor-pointer opacity-75 hover:opacity-100">
                          Ver detalles
                        </summary>
                        <pre className="mt-1 text-[10px] bg-white/50 p-2 rounded overflow-x-auto">
                          {JSON.stringify(check.details, null, 2)}
                        </pre>
                      </details>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Ayuda */}
      {diag.overall !== 'ok' && (
        <Card>
          <CardHeader>
            <CardTitle>Cómo resolver</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-[var(--text-secondary)]">
            <p><b>Extensiones PHP faltantes:</b> contacta a tu hoster para habilitarlas. Si usas ServerAvatar o similar, actívalas desde el panel (PHP Modules).</p>
            <p><b>Carpetas no escribibles:</b> la app intentó auto-arreglar los permisos pero no lo logró. Desde el File Manager cambia <code>cache/</code>, <code>logs/</code> y <code>database/</code> a permisos <b>755</b>.</p>
            <p><b>Cron drain-queue no activo:</b> desde el panel de tu hosting configura un cron <code>*/5 * * * * php /ruta/al/sitio/cron/drain-queue.php</code>.</p>
            <p><b>Base de datos con problemas:</b> si faltan tablas, borra <code>database/audit.db</code> (si existe) y recarga la página — el schema se recrea automáticamente.</p>
            <p><b>Google PageSpeed inalcanzable:</b> tu hosting puede tener firewall saliente. Pregunta al soporte si pueden abrir requests a googleapis.com.</p>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
