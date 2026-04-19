import axios from 'axios'
import { API_BASE_URL, DEFAULT_CONFIG } from './constants'
import { useAuthStore } from '@/store/authStore'
import type { AuditRequest, AuditResult } from '@/types/audit'

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
  const isAdmin = url.includes('/admin/')

  if (isAdmin && isMutation) {
    const token = useAuthStore.getState().csrfToken
    if (token) {
      config.headers = config.headers ?? {}
      config.headers['X-CSRF-Token'] = token
    }
  }
  return config
})

/** Ejecuta una auditoría */
export async function runAudit(request: AuditRequest): Promise<AuditResult> {
  const response = await api.post<{ success: boolean; data: AuditResult }>('/audit.php', request)
  return response.data.data
}

/** Obtiene resultado de una auditoría por ID */
export async function getAuditResult(id: string): Promise<AuditResult> {
  const response = await api.get<{ success: boolean; data: AuditResult }>('/audit-status.php', {
    params: { id },
  })
  return response.data.data
}

/** Obtiene la configuración pública */
export async function getConfig(): Promise<typeof DEFAULT_CONFIG> {
  try {
    const response = await api.get<{ success: boolean; data: typeof DEFAULT_CONFIG }>('/config.php')
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
