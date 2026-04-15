import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuditStore } from '@/store/auditStore'
import { runAudit } from '@/lib/api'
import type { AuditRequest } from '@/types/audit'

/**
 * Hook para ejecutar auditorías
 * Gestiona el estado global y la navegación
 */
export function useAudit() {
  const navigate = useNavigate()
  const { status, result, error, setScanning, setResult, setError, reset } = useAuditStore()

  const startAudit = useCallback(async (request: AuditRequest) => {
    setScanning(request)

    try {
      const auditResult = await runAudit(request)
      setResult(auditResult)

      // Navegar a resultados
      navigate(`/results/${auditResult.id}`)
    } catch (err: unknown) {
      const message = err instanceof Error
        ? err.message
        : 'Ocurrió un error al analizar el sitio. Intenta nuevamente.'

      // Extraer mensaje del backend si viene en la respuesta
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { error?: string } } }
        if (axiosErr.response?.data?.error) {
          setError(axiosErr.response.data.error)
          return
        }
      }

      setError(message)
    }
  }, [setScanning, setResult, setError, navigate])

  return {
    status,
    result,
    error,
    startAudit,
    reset,
  }
}
