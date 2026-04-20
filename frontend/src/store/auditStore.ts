import { create } from 'zustand'
import type { AuditResult, AuditStatus, AuditRequest, AuditProgress } from '@/types/audit'
import { DEFAULT_CONFIG } from '@/lib/constants'

interface AuditState {
  /** Estado actual del proceso de auditoría */
  status: AuditStatus
  /** Datos de la solicitud de auditoría */
  request: AuditRequest | null
  /** Resultado de la auditoría completada */
  result: AuditResult | null
  /** Mensaje de error si falló */
  error: string | null
  /** Progreso real reportado por el backend durante el scan */
  progress: AuditProgress | null
  /** Configuración del backend (branding, textos) */
  config: typeof DEFAULT_CONFIG

  setScanning: (request: AuditRequest) => void
  setProgress: (progress: AuditProgress) => void
  setResult: (result: AuditResult) => void
  setError: (error: string) => void
  reset: () => void
  setConfig: (config: typeof DEFAULT_CONFIG) => void
}

export const useAuditStore = create<AuditState>((set) => ({
  status: 'idle',
  request: null,
  result: null,
  error: null,
  progress: null,
  config: DEFAULT_CONFIG,

  setScanning: (request) => set({
    status: 'scanning',
    request,
    result: null,
    error: null,
    progress: null,
  }),

  setProgress: (progress) => set({ progress }),

  setResult: (result) => set({
    status: 'completed',
    result,
    error: null,
  }),

  setError: (error) => set({
    status: 'error',
    error,
  }),

  reset: () => set({
    status: 'idle',
    request: null,
    result: null,
    error: null,
    progress: null,
  }),

  setConfig: (config) => set({ config }),
}))
