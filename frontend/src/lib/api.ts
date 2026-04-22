import axios from 'axios'
import { API_BASE_URL, DEFAULT_CONFIG } from './constants'
import { useAuthStore } from '@/store/authStore'
import { useUserAuthStore } from '@/store/userAuthStore'
import type { AuditRequest, AuditResult, AuditProgress } from '@/types/audit'

/** Cliente HTTP configurado para el backend PHP */
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
  timeout: 90000, // 90 segundos para dar margen al escaneo
})

/**
 * Interceptor que añade el CSRF token en requests a /admin/* que mutan estado.
 * El token se obtiene de authStore (lo guarda useAuth tras login/session check).
 */
api.interceptors.request.use((config) => {
  const url = config.url ?? ''
  const method = (config.method ?? 'get').toLowerCase()
  const isMutation = ['post', 'put', 'delete', 'patch'].includes(method)

  // CSRF del admin sólo para /admin/*; CSRF del user sólo para /user/*
  // mutaciones. Son dos tokens distintos porque las sesiones son independientes.
  if (isMutation) {
    if (url.includes('/admin/')) {
      const token = useAuthStore.getState().csrfToken
      if (token) {
        config.headers = config.headers ?? {}
        config.headers['X-CSRF-Token'] = token
      }
    } else if (url.includes('/user/')) {
      const token = useUserAuthStore.getState().csrfToken
      if (token) {
        config.headers = config.headers ?? {}
        config.headers['X-CSRF-Token'] = token
      }
    }
  }
  return config
})

/**
 * Recupera el token CSRF desde /admin/session.php. Se usa cuando el backend
 * rechaza un request con 403 CSRF (sesión renovada en segundo plano, pestaña
 * dejada abierta, etc.) — refrescamos y reintentamos una vez.
 */
async function refreshCsrfToken(): Promise<string | null> {
  try {
    const res = await axios.get(`${API_BASE_URL}/admin/session.php`, {
      withCredentials: true,
      timeout: 10000,
    })
    const token = res.data?.data?.csrfToken ?? null
    useAuthStore.getState().setCsrfToken(token)
    return token
  } catch {
    return null
  }
}

/**
 * Interceptor de respuesta: si el backend devuelve 403 "Token CSRF inválido o
 * ausente" (típicamente por sesión renovada o pestaña vieja), refrescamos el
 * token y reintentamos la petición una sola vez. Si falla de nuevo, el error
 * fluye al caller para que decida (toast, re-login, etc.).
 */
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status
    const errMsg = (error?.response?.data?.error ?? '').toString()
    const isCsrfError = status === 403 && /CSRF/i.test(errMsg)
    const config = error?.config ?? {}

    if (isCsrfError && !config.__csrfRetried) {
      config.__csrfRetried = true
      const fresh = await refreshCsrfToken()
      if (fresh) {
        config.headers = config.headers ?? {}
        config.headers['X-CSRF-Token'] = fresh
        return api.request(config)
      }
    }
    return Promise.reject(error)
  }
)

/**
 * Resultado del POST /api/audit.
 *
 * - `cached=true`: hay resultado cacheado <24h, se devuelve completo.
 * - `cached=false`: se reservó un auditId y el audit corre en background.
 *   El cliente debe sondear `getScanProgress(auditId)` hasta status=completed
 *   y luego leer el resultado con `getAuditResult(auditId)`.
 */
export interface StartAuditResponse {
  cached: boolean
  auditId: string
  result?: AuditResult
  queued?: boolean
}

/** Arranca una auditoría. No bloquea HTTP durante el scan. */
export async function startAudit(request: AuditRequest): Promise<StartAuditResponse> {
  const { default: i18n } = await import('@/i18n')
  const lang = (i18n.language || 'en').split('-')[0]
  const response = await api.post<{ success: boolean; data: StartAuditResponse }>(
    '/audit.php',
    { ...request, lang },
    { timeout: 15000 }, // la respuesta es inmediata; 15s es margen generoso
  )
  return response.data.data
}

/** Consulta el progreso de un audit en curso (polling). */
export async function getScanProgress(auditId: string): Promise<AuditProgress> {
  const response = await api.get<{ success: boolean; data: AuditProgress }>(
    '/scan-progress.php',
    { params: { id: auditId }, timeout: 10000 },
  )
  return response.data.data
}

/** Obtiene resultado de una auditoría por ID */
export async function getAuditResult(id: string): Promise<AuditResult> {
  const response = await api.get<{ success: boolean; data: AuditResult }>('/audit-status.php', {
    params: { id },
  })
  return response.data.data
}

/** Obtiene la configuración pública. Se pasa el idioma activo para que los
 *  fallbacks no editados desde admin vengan en el idioma correcto. */
export async function getConfig(): Promise<typeof DEFAULT_CONFIG> {
  try {
    const { default: i18n } = await import('@/i18n')
    const lang = (i18n.language || 'en').split('-')[0]
    const response = await api.get<{ success: boolean; data: typeof DEFAULT_CONFIG }>('/config.php', {
      params: { lang },
    })
    return response.data.data
  } catch {
    return DEFAULT_CONFIG
  }
}

/** Healthcheck del backend */
export async function checkHealth(): Promise<boolean> {
  try {
    const response = await api.get('/health.php')
    return response.data?.data?.status === 'healthy'
  } catch {
    return false
  }
}

export default api
