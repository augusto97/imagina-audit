import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
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
 * Cancelación: cada scan tiene un token (ref). Si el componente se
 * desmonta o se lanza un nuevo scan, el token actual se invalida y los
 * polls viejos se ignoran. Esto evita que un loop residual (hasta 15 min
 * de vida) pise el store con un resultado obsoleto mientras corre el nuevo.
 */
export function useAudit() {
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { status, result, error, setScanning, setProgress, setResult, setError, reset } = useAuditStore()

  // Token monotónico para identificar el scan activo. Cada nuevo scan
  // incrementa el ref; el polling guarda su valor inicial y aborta si
  // observa que el ref se ha movido.
  //
  // IMPORTANTE: NO invalidar al desmontar. useAudit() se llama desde
  // múltiples componentes (AuditForm, ScanningAnimation, ResultsPage),
  // y cada uno tiene su propio useEffect cleanup. Si un componente que
  // arrancó el polling se desmonta (p.ej. HomePage→ScanningAnimation),
  // el cleanup mataba el polling recién iniciado y nunca se hacía la
  // primera petición a /scan-progress.php. Eso causaba el "5% para
  // siempre" que el usuario reportaba.
  const activeTokenRef = useRef(0)

  const startPolling = useCallback(async (auditId: string, token: number): Promise<void> => {
    const deadline = Date.now() + POLL_TIMEOUT_MS
    const isStale = () => activeTokenRef.current !== token

    while (Date.now() < deadline) {
      await new Promise(r => setTimeout(r, POLL_INTERVAL_MS))
      if (isStale()) return  // user navegó / re-escaneó

      try {
        const progress = await getScanProgress(auditId)
        if (isStale()) return
        setProgress(progress)

        if (progress.status === 'completed') {
          const auditResult = await getAuditResult(auditId)
          if (isStale()) return
          setResult(auditResult)
          navigate(auditViewPath(auditId))
          return
        }

        if (progress.status === 'failed') {
          setError(progress.error || t('public.scan_error_failed'))
          return
        }
        // 'running' → seguir iterando
      } catch (err) {
        if (isStale()) return
        // 404 al inicio es normal (el progreso aún no se escribió);
        // lo ignoramos y reintentamos. Otros errores se muestran.
        const axiosErr = err as { response?: { status?: number; data?: { error?: string } } }
        if (axiosErr.response?.status === 404) {
          continue
        }
        setError(axiosErr.response?.data?.error || t('public.scan_error_progress'))
        return
      }
    }

    if (!isStale()) setError(t('public.scan_error_timeout'))
  }, [setProgress, setResult, setError, navigate, t])

  const runAudit = useCallback(async (request: AuditRequest) => {
    // Invalidar cualquier polling vivo (de un scan anterior en esta misma
    // sesión) antes de arrancar el nuevo. Sin esto, el loop viejo podía
    // sobrevivir hasta 15 minutos pisando el store.
    const token = ++activeTokenRef.current
    setScanning(request)

    try {
      const response = await startAudit(request)
      if (activeTokenRef.current !== token) return

      if (response.cached && response.result) {
        // Camino rápido: resultado cacheado
        setResult(response.result)
        navigate(auditViewPath(response.auditId))
        return
      }

      // Camino background: polling
      await startPolling(response.auditId, token)
    } catch (err: unknown) {
      if (activeTokenRef.current !== token) return
      const axiosErr = err as { response?: { data?: { error?: string } } }
      if (axiosErr.response?.data?.error) {
        setError(axiosErr.response.data.error)
        return
      }
      const message = err instanceof Error ? err.message : t('public.scan_error_generic')
      setError(message)
    }
  }, [setScanning, setResult, setError, navigate, startPolling, t])

  const cancel = useCallback(() => {
    activeTokenRef.current++
  }, [])

  return {
    status,
    result,
    error,
    startAudit: runAudit,
    reset,
    cancel,
  }
}
