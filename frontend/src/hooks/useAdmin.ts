import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/store/authStore'
import api from '@/lib/api'

/**
 * Hook con métodos para todos los endpoints admin
 * Redirige a /admin si recibe 401
 */
export function useAdmin() {
  const navigate = useNavigate()
  const setAuthenticated = useAuthStore((s) => s.setAuthenticated)

  const handleError = useCallback((err: unknown) => {
    if (typeof err === 'object' && err !== null && 'response' in err) {
      const axiosErr = err as { response?: { status?: number } }
      if (axiosErr.response?.status === 401) {
        setAuthenticated(false)
        navigate('/admin')
      }
    }
    throw err
  }, [navigate, setAuthenticated])

  const fetchDashboard = useCallback(async () => {
    try {
      const res = await api.get('/admin/dashboard.php')
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchLeads = useCallback(async (params: Record<string, string | number>) => {
    try {
      const res = await api.get('/admin/leads.php', { params })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchLeadDetail = useCallback(async (id: string) => {
    try {
      const res = await api.get('/admin/lead-detail.php', { params: { id } })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteLead = useCallback(async (id: string) => {
    try {
      await api.delete('/admin/leads.php', { params: { id } })
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchSettings = useCallback(async () => {
    try {
      const res = await api.get('/admin/settings.php')
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchQueueStatus = useCallback(async () => {
    try {
      const res = await api.get('/admin/queue-status.php')
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const pinAudit = useCallback(async (auditId: string, pinned: boolean) => {
    try {
      const res = await api.post('/admin/pin-audit.php', { auditId, pinned })
      return res.data.data as { auditId: string; isPinned: boolean }
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchRetentionPreview = useCallback(async (months: number) => {
    try {
      const res = await api.get('/admin/retention-preview.php', { params: { months } })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const updateSettings = useCallback(async (data: Record<string, unknown>) => {
    try {
      await api.put('/admin/settings.php', data)
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchVulnerabilities = useCallback(async (params: Record<string, string | number>) => {
    try {
      const res = await api.get('/admin/vulnerabilities.php', { params })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const createVulnerability = useCallback(async (data: Record<string, unknown>) => {
    try {
      const res = await api.post('/admin/vulnerabilities.php', data)
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const updateVulnerability = useCallback(async (data: Record<string, unknown>) => {
    try {
      await api.put('/admin/vulnerabilities.php', data)
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteVulnerability = useCallback(async (id: number) => {
    try {
      await api.delete('/admin/vulnerabilities.php', { params: { id } })
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchPluginVault = useCallback(async () => {
    try {
      const res = await api.get('/admin/plugin-vault.php')
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const refreshPluginVault = useCallback(async (slug: string, force = false) => {
    try {
      const res = await api.post('/admin/plugin-vault.php', { slug, force })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchSnapshotReport = useCallback(async (auditId: string) => {
    try {
      const res = await api.get('/admin/snapshot-report.php', { params: { audit_id: auditId } })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  /**
   * Sube un asset de branding (logo, logo_collapsed, favicon).
   * Retorna la URL pública relativa donde quedó guardado.
   *
   * Importante: NO fijamos Content-Type. Axios detecta FormData y genera
   * `multipart/form-data; boundary=...` automáticamente. Si lo forzamos
   * nosotros, el servidor no encuentra el boundary y el parsing falla.
   */
  const uploadBrandAsset = useCallback(async (type: 'logo' | 'logo_collapsed' | 'favicon', file: File) => {
    try {
      const form = new FormData()
      form.append('type', type)
      form.append('file', file)
      const res = await api.post('/admin/upload.php', form, {
        headers: { 'Content-Type': undefined as unknown as string },
      })
      return res.data.data as { url: string; type: string; filename: string }
    } catch (err) { handleError(err) }
  }, [handleError])

  return {
    fetchDashboard, fetchLeads, fetchLeadDetail, deleteLead,
    fetchSettings, updateSettings, fetchQueueStatus,
    pinAudit, fetchRetentionPreview,
    fetchVulnerabilities, createVulnerability, updateVulnerability, deleteVulnerability,
    uploadBrandAsset,
    fetchSnapshotReport,
    fetchPluginVault, refreshPluginVault,
  }
}
