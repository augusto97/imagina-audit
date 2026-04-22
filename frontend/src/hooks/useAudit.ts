import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuditStore } from '@/store/auditStore'
import { useUserAuthStore } from '@/store/userAuthStore'
import { startAudit, getScanProgress, getAuditResult } from '@/lib/api'
import type { AuditRequest } from '@/types/audit'

/**
 * Devuelve el path al que navegar tras un audit completado. Si hay
 * sesión de user activa, va a la vista owner (/account/audits/:id) que
 * tiene los 4 tabs (detail, report, snapshot, waterfall). Si no, usa
 * el /results/:id público.
 */
function auditViewPath(auditId: string): string {
  const isUser = useUserAuthStore.getState().isAuthenticated
  return isUser ? `/account/audits/${auditId}` : `/results/${auditId}`
}

const POLL_INTERVAL_MS = 1500
// 15 min máximo — cubre el caso de cola llena (ej. 30 audits esperando con 3 slots)
const POLL_TIMEOUT_MS = 15 * 60_000

/**
 * Hook para ejecutar auditorías.
 *
 * Flujo:
 * 1. POST /api/audit. Respuesta inmediata con `cached:true/false` + `auditId`.
 * 2. Si `cached`: fetch del resultado ya guardado y navegación directa.
 * 3. Si no: polling a /api/scan-progress cada 1.5s hasta status=completed.
 * 4. Cuando termina: fetch del resultado final y navega a /results/:id.
 *
 * El polling tiene timeout de 3 minutos por si algo se cuelga en el backend.
 */
export function useAudit() {
  const navigate = useNavigate()
  const { status, result, error, setScanning, setProgress, setResult, setError, reset } = useAuditStore()

  const startPolling = useCallback(async (auditId: string): Promise<void> => {
    const deadline = Date.now() + POLL_TIMEOUT_MS

    while (Date.now() < deadline) {
      await new Promise(r => setTimeout(r, POLL_INTERVAL_MS))

      try {
        const progress = await getScanProgress(auditId)
        setProgress(progress)

        if (progress.status === 'completed') {
          const auditResult = await getAuditResult(auditId)
          setResult(auditResult)
          navigate(auditViewPath(auditId))
          return
        }

        if (progress.status === 'failed') {
          setError(progress.error || 'El análisis falló.')
          return
        }
        // 'running' → seguir iterando
      } catch (err) {
        // 404 al inicio es normal (el progreso aún no se escribió);
        // lo ignoramos y reintentamos. Otros errores se muestran.
        const axiosErr = err as { response?: { status?: number; data?: { error?: string } } }
        if (axiosErr.response?.status === 404) {
          continue
        }
        setError(axiosErr.response?.data?.error || 'Error consultando el estado del análisis.')
        return
      }
    }

    setError('El análisis tardó demasiado. Intenta nuevamente.')
  }, [setProgress, setResult, setError, navigate])

  const runAudit = useCallback(async (request: AuditRequest) => {
    setScanning(request)

    try {
      const response = await startAudit(request)

      if (response.cached && response.result) {
        // Camino rápido: resultado cacheado
        setResult(response.result)
        navigate(auditViewPath(response.auditId))
        return
      }

      // Camino background: polling
      await startPolling(response.auditId)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      if (axiosErr.response?.data?.error) {
        setError(axiosErr.response.data.error)
        return
      }
      const message = err instanceof Error ? err.message : 'Ocurrió un error al analizar el sitio.'
      setError(message)
    }
  }, [setScanning, setResult, setError, navigate, startPolling])

  return {
    status,
    result,
    error,
    startAudit: runAudit,
    reset,
  }
}
