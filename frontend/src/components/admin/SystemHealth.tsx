import { useEffect, useState, useCallback } from 'react'
import { CheckCircle2, AlertTriangle, XCircle, Activity, RefreshCw } from 'lucide-react'
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

interface Diag {
  overall: 'ok' | 'warn' | 'fail'
  summary: { ok: number; warn: number; fail: number }
  checks: Check[]
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
      const res = await api.get('/diag.php')
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
