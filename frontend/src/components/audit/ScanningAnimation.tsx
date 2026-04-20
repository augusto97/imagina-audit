import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { CheckCircle, Loader2, Clock, AlertCircle, Users } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuditStore } from '@/store/auditStore'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'

/** Orden de steps que muestra la UI (coincide con backend/lib/AuditProgress.php). */
const DISPLAY_STEPS = [
  { id: 'fetch', label: 'Descargando página' },
  { id: 'wordpress', label: 'Detectando WordPress' },
  { id: 'security', label: 'Analizando seguridad' },
  { id: 'performance', label: 'Consultando Google PageSpeed' },
  { id: 'seo', label: 'Verificando SEO' },
  { id: 'mobile', label: 'Evaluando experiencia móvil' },
  { id: 'infrastructure', label: 'Analizando infraestructura' },
  { id: 'conversion', label: 'Detectando herramientas de marketing' },
  { id: 'page_health', label: 'Verificando salud de página' },
  { id: 'techstack', label: 'Detectando stack tecnológico' },
  { id: 'compile', label: 'Compilando resultados' },
]

type StepState = 'pending' | 'scanning' | 'done'

function stepStateFor(stepId: string, currentStep: string | undefined, allSteps: string[]): StepState {
  if (!currentStep) return 'pending'
  const currentIdx = allSteps.indexOf(currentStep)
  const thisIdx = allSteps.indexOf(stepId)
  if (currentIdx === -1 || thisIdx === -1) return 'pending'
  if (thisIdx < currentIdx) return 'done'
  if (thisIdx === currentIdx) return 'scanning'
  return 'pending'
}

export default function ScanningAnimation() {
  const { status, error, request, progress, reset } = useAuditStore()
  const navigate = useNavigate()
  const [elapsedSec, setElapsedSec] = useState(0)

  useEffect(() => {
    if (status !== 'scanning') return
    const startedAt = progress?.startedAt ? progress.startedAt * 1000 : Date.now()
    const timer = setInterval(() => {
      setElapsedSec(Math.max(0, Math.floor((Date.now() - startedAt) / 1000)))
    }, 1000)
    return () => clearInterval(timer)
  }, [status, progress?.startedAt])

  if (status === 'error') {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--bg-secondary)] px-4">
        <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }}>
          <Card className="w-full max-w-md text-center">
            <CardContent className="pt-8 pb-8">
              <AlertCircle className="mx-auto h-12 w-12 text-[var(--color-critical)]" strokeWidth={1.5} />
              <h2 className="mt-4 text-lg font-semibold text-[var(--text-primary)]">No pudimos analizar este sitio</h2>
              <p className="mt-2 text-sm text-[var(--text-secondary)]">{error}</p>
              <p className="mt-4 text-xs text-[var(--text-tertiary)]">
                Si el problema persiste, verifica que el sitio esté online.<br />
                Espera unos minutos antes de reintentar.
              </p>
              <Button onClick={() => { reset(); navigate('/') }} className="mt-6">Volver</Button>
            </CardContent>
          </Card>
        </motion.div>
      </div>
    )
  }

  const domain = request?.url ? new URL(request.url.startsWith('http') ? request.url : `https://${request.url}`).hostname : ''

  // Pantalla de cola: el audit aún no arrancó
  if (progress?.status === 'queued') {
    const position = progress.position ?? 0
    const total = progress.totalInQueue ?? 0
    // Estimación heurística: 45s por audit que falta procesar, dividido por
    // slots concurrentes (asumimos 3 como default del backend)
    const estimatedWaitSec = Math.max(0, Math.ceil((position - 1) * 45 / 3))
    const waitLabel = estimatedWaitSec < 60
      ? `~${estimatedWaitSec} segundos`
      : `~${Math.ceil(estimatedWaitSec / 60)} minuto${Math.ceil(estimatedWaitSec / 60) > 1 ? 's' : ''}`

    return (
      <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--bg-secondary)] px-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="relative z-10 w-full max-w-md">
          <div className="mb-8 text-center">
            <p className="text-sm text-[var(--text-tertiary)]">Esperando turno</p>
            <h2 className="mt-1 text-2xl font-bold text-[var(--accent-primary)]">{domain}</h2>
          </div>

          <Card>
            <CardContent className="py-8 px-6 text-center">
              <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--accent-primary)]/10">
                <Users className="h-8 w-8 text-[var(--accent-primary)]" strokeWidth={1.5} />
              </div>
              <h3 className="text-xl font-bold text-[var(--text-primary)]">
                Tu análisis está en cola
              </h3>
              <p className="mt-3 text-sm text-[var(--text-secondary)]">
                Hay <span className="font-bold text-[var(--text-primary)]">{total}</span> análisis en cola.
                Tu posición: <span className="font-bold text-[var(--accent-primary)]">#{position}</span>
              </p>
              <p className="mt-2 text-sm text-[var(--text-secondary)]">
                Tiempo estimado de espera: <span className="font-medium text-[var(--text-primary)]">{waitLabel}</span>
              </p>
              <div className="mt-6">
                <Loader2 className="mx-auto h-5 w-5 animate-spin text-[var(--text-tertiary)]" strokeWidth={1.5} />
                <p className="mt-2 text-xs text-[var(--text-tertiary)]">
                  Arrancará automáticamente cuando sea tu turno.
                </p>
              </div>
            </CardContent>
          </Card>

          <p className="mt-4 text-center text-xs text-[var(--text-tertiary)]">
            Puedes dejar esta ventana abierta — te avisaremos en cuanto termine.
          </p>
        </motion.div>
      </div>
    )
  }

  // Pantalla de ejecución: audit corriendo con progreso real
  const displayProgress = progress?.progress ?? 5
  const currentLabel = progress?.currentLabel ?? 'Iniciando análisis...'
  const allStepIds = DISPLAY_STEPS.map(s => s.id)

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--bg-secondary)] px-4">
      {/* Grid animado de fondo */}
      <div className="absolute inset-0 overflow-hidden opacity-[0.03]">
        <div
          className="animate-grid-move absolute inset-0"
          style={{
            backgroundImage: 'linear-gradient(var(--accent-primary) 1px, transparent 1px), linear-gradient(90deg, var(--accent-primary) 1px, transparent 1px)',
            backgroundSize: '40px 40px',
          }}
        />
      </div>

      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="relative z-10 w-full max-w-md">
        <div className="mb-8 text-center">
          <p className="text-sm text-[var(--text-tertiary)]">Escaneando</p>
          <h2 className="mt-1 text-2xl font-bold text-[var(--accent-primary)]">{domain}</h2>
          {elapsedSec > 0 && (
            <p className="mt-1 text-xs text-[var(--text-tertiary)] tabular-nums">
              {elapsedSec}s transcurridos
            </p>
          )}
        </div>

        <div className="mb-8">
          <div className="mb-2 flex items-center justify-between text-xs text-[var(--text-tertiary)]">
            <span>Progreso</span>
            <span className="font-medium tabular-nums">{displayProgress}%</span>
          </div>
          <Progress value={displayProgress} className="h-2" />
        </div>

        <Card>
          <CardContent className="py-4 px-4">
            <div className="space-y-0">
              {DISPLAY_STEPS.map((step, idx) => {
                const state = stepStateFor(step.id, progress?.currentStep, allStepIds)
                return (
                  <motion.div
                    key={step.id}
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: idx * 0.05 }}
                    className="flex items-center gap-3 py-2"
                  >
                    {state === 'done' && <CheckCircle className="h-4 w-4 shrink-0 text-emerald-500" strokeWidth={2} />}
                    {state === 'scanning' && <Loader2 className="h-4 w-4 shrink-0 animate-spin text-[var(--accent-primary)]" strokeWidth={2} />}
                    {state === 'pending' && <Clock className="h-4 w-4 shrink-0 text-[var(--text-tertiary)]" strokeWidth={1.5} />}
                    <span className={`text-sm ${state === 'done' ? 'text-[var(--text-secondary)]' : state === 'scanning' ? 'text-[var(--text-primary)] font-medium' : 'text-[var(--text-tertiary)]'}`}>
                      {step.label}
                    </span>
                  </motion.div>
                )
              })}
            </div>
          </CardContent>
        </Card>

        <p className="mt-4 text-center text-xs text-[var(--text-tertiary)] animate-scan-pulse">
          {currentLabel}
        </p>
      </motion.div>
    </div>
  )
}
