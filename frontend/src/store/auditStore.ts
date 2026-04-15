import { create } from 'zustand'
import type { AuditResult, AuditStatus, AuditRequest } from '@/types/audit'
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
  /** Configuración del backend (branding, textos) */
  config: typeof DEFAULT_CONFIG

  // Acciones
  setScanning: (request: AuditRequest) => void
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
  config: DEFAULT_CONFIG,

  setScanning: (request) => set({
    status: 'scanning',
    request,
    result: null,
    error: null,
  }),

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
  }),

  setConfig: (config) => set({ config }),
}))
