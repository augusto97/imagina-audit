import axios from 'axios'
import { API_BASE_URL, DEFAULT_CONFIG } from './constants'
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
