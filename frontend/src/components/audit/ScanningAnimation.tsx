import { useState, useEffect, useRef } from 'react'
import { motion } from 'framer-motion'
import { CheckCircle, Loader2, Clock, AlertCircle } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuditStore } from '@/store/auditStore'
import { SCAN_STEPS } from '@/lib/constants'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'

type StepState = 'pending' | 'scanning' | 'done'

export default function ScanningAnimation() {
  const { status, error, request, reset } = useAuditStore()
  const navigate = useNavigate()
  const [stepStates, setStepStates] = useState<StepState[]>(SCAN_STEPS.map(() => 'pending'))
  const [progress, setProgress] = useState(0)
  const [currentStepIndex, setCurrentStepIndex] = useState(0)
  const animationDone = useRef(false)

  useEffect(() => {
    if (status !== 'scanning' && status !== 'completed') return

    let stepIdx = 0
    const totalDuration = SCAN_STEPS.reduce((sum, s) => sum + s.duration, 0)
    let elapsedMs = 0

    const advanceStep = () => {
      if (stepIdx >= SCAN_STEPS.length) {
        animationDone.current = true
        return
      }

      setStepStates((prev) => {
        const next = [...prev]
        next[stepIdx] = 'scanning'
        return next
      })
      setCurrentStepIndex(stepIdx)

      const stepDuration = SCAN_STEPS[stepIdx].duration

      setTimeout(() => {
        setStepStates((prev) => {
          const next = [...prev]
          next[stepIdx] = 'done'
          return next
        })
        elapsedMs += stepDuration
        setProgress(Math.min(100, Math.round((elapsedMs / totalDuration) * 100)))
        stepIdx++
        advanceStep()
      }, stepDuration)
    }

    advanceStep()
  }, [])

  useEffect(() => {
    if (status === 'completed' && animationDone.current) {
      const result = useAuditStore.getState().result
      if (result) navigate(`/results/${result.id}`)
    }
  }, [status, navigate])

  if (status === 'error') {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--bg-secondary)] px-4">
        <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }}>
          <Card className="w-full max-w-md text-center">
            <CardContent className="pt-8 pb-8">
              <AlertCircle className="mx-auto h-12 w-12 text-[var(--color-critical)]" strokeWidth={1.5} />
              <h2 className="mt-4 text-lg font-semibold text-[var(--text-primary)]">Error al analizar</h2>
              <p className="mt-2 text-sm text-[var(--text-secondary)]">{error}</p>
              <Button onClick={() => { reset(); navigate('/') }} className="mt-6">Intentar de nuevo</Button>
            </CardContent>
          </Card>
        </motion.div>
      </div>
    )
  }

  const domain = request?.url ? new URL(request.url.startsWith('http') ? request.url : `https://${request.url}`).hostname : ''

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--bg-secondary)] px-4">
      {/* Animated background grid */}
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
        </div>

        {/* Progress */}
        <div className="mb-8">
          <div className="mb-2 flex items-center justify-between text-xs text-[var(--text-tertiary)]">
            <span>Progreso</span>
            <span className="font-medium">{progress}%</span>
          </div>
          <Progress value={progress} className="h-2" />
        </div>

        {/* Step list */}
        <Card>
          <CardContent className="py-4 px-4">
            <div className="space-y-0">
              {SCAN_STEPS.map((step, idx) => (
                <motion.div
                  key={step.id}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: idx * 0.1 }}
                  className="flex items-center gap-3 py-2"
                >
                  {stepStates[idx] === 'done' && <CheckCircle className="h-4 w-4 shrink-0 text-emerald-500" strokeWidth={2} />}
                  {stepStates[idx] === 'scanning' && <Loader2 className="h-4 w-4 shrink-0 animate-spin text-[var(--accent-primary)]" strokeWidth={2} />}
                  {stepStates[idx] === 'pending' && <Clock className="h-4 w-4 shrink-0 text-[var(--text-tertiary)]" strokeWidth={1.5} />}
                  <span className={`text-sm ${stepStates[idx] === 'done' ? 'text-[var(--text-secondary)]' : stepStates[idx] === 'scanning' ? 'text-[var(--text-primary)] font-medium' : 'text-[var(--text-tertiary)]'}`}>
                    {step.label}
                  </span>
                </motion.div>
              ))}
            </div>
          </CardContent>
        </Card>

        <p className="mt-4 text-center text-xs text-[var(--text-tertiary)] animate-scan-pulse">
          {currentStepIndex < SCAN_STEPS.length ? SCAN_STEPS[currentStepIndex].label : 'Finalizando análisis...'}
        </p>
      </motion.div>
    </div>
  )
}
