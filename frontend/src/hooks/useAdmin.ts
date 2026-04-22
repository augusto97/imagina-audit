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

  const bulkLeads = useCallback(async (ids: string[], action: 'delete' | 'pin' | 'unpin') => {
    try {
      const res = await api.post('/admin/leads-bulk.php', { ids, action })
      return res.data.data as { processed: number; skipped: number; action: string }
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

  const fetch2faStatus = useCallback(async () => {
    try {
      const res = await api.get('/admin/2fa.php')
      return res.data.data as { enabled: boolean; recoveryCodesLeft: number }
    } catch (err) { handleError(err) }
  }, [handleError])

  const setup2fa = useCallback(async () => {
    try {
      const res = await api.post('/admin/2fa.php?action=setup', {})
      return res.data.data as { secret: string; otpauthUri: string; issuer: string; label: string }
    } catch (err) { handleError(err) }
  }, [handleError])

  const enable2fa = useCallback(async (secret: string, code: string) => {
    const res = await api.post('/admin/2fa.php?action=enable', { secret, code })
    return res.data.data as { enabled: boolean; recoveryCodes: string[] }
  }, [])

  const disable2fa = useCallback(async (password: string, code: string) => {
    const res = await api.post('/admin/2fa.php?action=disable', { password, code })
    return res.data.data as { enabled: boolean }
  }, [])

  const regenerateRecoveryCodes = useCallback(async (code: string) => {
    const res = await api.post('/admin/2fa.php?action=regenerate-recovery', { code })
    return res.data.data as { recoveryCodes: string[] }
  }, [])

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
  const fetchTranslationsMeta = useCallback(async () => {
    try {
      const res = await api.get('/admin/translations.php', { params: { meta: 'namespaces' } })
      return res.data.data as { namespaces: string[]; languages: string[]; defaultLang: string }
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchTranslations = useCallback(async (lang: string, namespace: string) => {
    try {
      const res = await api.get('/admin/translations.php', { params: { lang, namespace } })
      return res.data.data as {
        lang: string
        namespace: string
        totalKeys: number
        overriddenCount: number
        items: Array<{
          key: string
          value: string
          defaultValue: string | null
          sourceValue: string | null
          overridden: boolean
          source: 'manual' | 'ai' | 'import' | null
          aiProvider: string | null
          reviewed: boolean
          updatedAt: string | null
        }>
      }
    } catch (err) { handleError(err) }
  }, [handleError])

  const updateTranslation = useCallback(async (lang: string, namespace: string, key: string, value: string, opts: { source?: string; aiProvider?: string | null; reviewed?: boolean } = {}) => {
    try {
      await api.put('/admin/translations.php', {
        lang, namespace, key, value,
        source: opts.source ?? 'manual',
        aiProvider: opts.aiProvider ?? null,
        reviewed: opts.reviewed ?? true,
      })
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteTranslation = useCallback(async (lang: string, namespace: string, key?: string) => {
    try {
      const params: Record<string, string> = { lang, namespace }
      if (key) params.key = key
      await api.delete('/admin/translations.php', { params })
    } catch (err) { handleError(err) }
  }, [handleError])

  const aiTranslate = useCallback(async (payload: {
    provider?: 'chatgpt' | 'claude' | 'google'
    sourceLang: string
    targetLang: string
    namespace: string
    items: Array<{ key: string; text: string; context?: string }>
    persist?: boolean
  }) => {
    const res = await api.post('/admin/ai-translate.php', payload)
    return res.data.data as {
      provider: string
      providerName: string
      totalCount: number
      okCount: number
      errorCount: number
      translations: Array<{ key: string; text: string; translated: string | null; ok: boolean; error?: string }>
    }
  }, [])

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

  // ─── Users & quota plans (P4) ─────────────────────────────────────
  const fetchUsers = useCallback(async (params: Record<string, string | number> = {}) => {
    try {
      const res = await api.get('/admin/users.php', { params })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const createUser = useCallback(async (body: Record<string, unknown>) => {
    try {
      const res = await api.post('/admin/users.php', body)
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const updateUser = useCallback(async (body: Record<string, unknown>) => {
    try {
      const res = await api.put('/admin/users.php', body)
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteUser = useCallback(async (id: number) => {
    try {
      await api.delete('/admin/users.php', { params: { id } })
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchUserPlans = useCallback(async () => {
    try {
      const res = await api.get('/admin/plans.php')
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const createUserPlan = useCallback(async (body: Record<string, unknown>) => {
    try {
      const res = await api.post('/admin/plans.php', body)
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const updateUserPlan = useCallback(async (body: Record<string, unknown>) => {
    try {
      const res = await api.put('/admin/plans.php', body)
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteUserPlan = useCallback(async (id: number) => {
    try {
      await api.delete('/admin/plans.php', { params: { id } })
    } catch (err) { handleError(err) }
  }, [handleError])

  const fetchAdminProjects = useCallback(async (params: Record<string, string | number> = {}) => {
    try {
      const res = await api.get('/admin/projects.php', { params })
      return res.data.data
    } catch (err) { handleError(err) }
  }, [handleError])

  const deleteAdminProject = useCallback(async (id: number) => {
    try {
      await api.delete('/admin/projects.php', { params: { id } })
    } catch (err) { handleError(err) }
  }, [handleError])

  return {
    fetchDashboard, fetchLeads, fetchLeadDetail, deleteLead, bulkLeads,
    fetchSettings, updateSettings, fetchQueueStatus,
    pinAudit, fetchRetentionPreview,
    fetchVulnerabilities, createVulnerability, updateVulnerability, deleteVulnerability,
    uploadBrandAsset,
    fetchSnapshotReport,
    fetchPluginVault, refreshPluginVault,
    fetch2faStatus, setup2fa, enable2fa, disable2fa, regenerateRecoveryCodes,
    fetchTranslationsMeta, fetchTranslations, updateTranslation, deleteTranslation, aiTranslate,
    fetchUsers, createUser, updateUser, deleteUser,
    fetchUserPlans, createUserPlan, updateUserPlan, deleteUserPlan,
    fetchAdminProjects, deleteAdminProject,
  }
}
