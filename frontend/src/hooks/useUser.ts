import { useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useUserAuthStore, type CurrentUser, type UserQuota } from '@/store/userAuthStore'
import api from '@/lib/api'

interface SessionResponse {
  authenticated: boolean
  user?: CurrentUser
  quota?: UserQuota | null
  csrfToken?: string
}

interface UserAudit {
  id: string
  url: string
  domain: string
  globalScore: number
  globalLevel: string
  isWordPress: boolean
  scanDurationMs: number
  createdAt: string
}

interface UserAuditsPage {
  audits: UserAudit[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface ProjectSummary {
  id: number
  name: string
  url: string
  domain: string
  notes: string | null
  icon: string | null
  color: string | null
  createdAt: string
  sharingEnabled: boolean
  auditCount: number
  openChecklistCount: number
  latestAudit: { id: string; globalScore: number; globalLevel: string; createdAt: string } | null
}

export interface ProjectsListResponse {
  projects: ProjectSummary[]
  total: number
  quota: { maxProjects: number; used: number; remaining: number | null; unlimited: boolean }
}

export interface ProjectChecklistItem {
  metricId: string
  name: string
  description: string
  recommendation: string
  imaginaSolution: string
  moduleId: string
  moduleName: string
  status: 'open' | 'done' | 'ignored'
  severity: string | null
  note: string | null
  userModified: boolean
  completedAt: string | null
  updatedAt: string
}

export interface ProjectDetail {
  project: {
    id: number
    name: string
    url: string
    domain: string
    notes: string | null
    icon: string | null
    color: string | null
    createdAt: string
    sharing: { enabled: boolean; token: string | null }
  }
  audits: UserAudit[]
  checklistSummary: { open: number; done: number; ignored: number }
  evolution: {
    latestAuditId: string
    previousAuditId: string
    scoreDelta: number
    latestScore: number
    previousScore: number
    issuesDelta: { critical: number; warning: number }
    wordpress: { previousVersion: string | null; latestVersion: string | null; changed: boolean } | null
    plugins: { added: string[]; removed: string[]; kept: string[] }
  } | null
}

/**
 * Hook de sesión del usuario (separado del admin). Maneja login/logout,
 * mantiene el estado en `userAuthStore`, y recarga desde /api/user/session
 * al montar para que un refresh de la página no pierda la sesión.
 */
export function useUser() {
  const navigate = useNavigate()
  const { isAuthenticated, isLoading, user, quota, csrfToken, setSession, setLoading, clear } = useUserAuthStore()

  const checkSession = useCallback(async () => {
    try {
      const res = await api.get<{ success: boolean; data: SessionResponse }>('/user/session.php')
      const data = res.data?.data
      if (data?.authenticated && data.user) {
        setSession({
          user: data.user,
          quota: data.quota ?? null,
          csrfToken: data.csrfToken ?? null,
        })
      } else {
        clear()
      }
    } catch {
      clear()
    } finally {
      setLoading(false)
    }
  }, [setSession, setLoading, clear])

  const login = useCallback(async (email: string, password: string): Promise<boolean> => {
    const res = await api.post<{ success: boolean; data: SessionResponse }>('/user/login.php', { email, password })
    const data = res.data?.data
    if (data?.authenticated && data.user) {
      setSession({
        user: data.user,
        quota: data.quota ?? null,
        csrfToken: data.csrfToken ?? null,
      })
      return true
    }
    return false
  }, [setSession])

  const logout = useCallback(async () => {
    try {
      await api.post('/user/logout.php')
    } catch { /* ignorar */ }
    clear()
    navigate('/login')
  }, [clear, navigate])

  const fetchAudits = useCallback(async (page = 1, limit = 20): Promise<UserAuditsPage | null> => {
    try {
      const res = await api.get<{ success: boolean; data: UserAuditsPage }>('/user/audits.php', {
        params: { page, limit },
      })
      return res.data?.data ?? null
    } catch {
      return null
    }
  }, [])

  // ─── Projects (P5) ──────────────────────────────────────────────
  const fetchProjects = useCallback(async (): Promise<ProjectsListResponse | null> => {
    try {
      const res = await api.get<{ success: boolean; data: ProjectsListResponse }>('/user/projects.php')
      return res.data?.data ?? null
    } catch {
      return null
    }
  }, [])

  const fetchProject = useCallback(async (id: number): Promise<ProjectDetail | null> => {
    try {
      const res = await api.get<{ success: boolean; data: ProjectDetail }>('/user/projects.php', {
        params: { id },
      })
      return res.data?.data ?? null
    } catch {
      return null
    }
  }, [])

  const createProject = useCallback(async (body: Record<string, unknown>) => {
    const res = await api.post<{ success: boolean; data: { id: number } }>('/user/projects.php', body)
    return res.data?.data
  }, [])

  const updateProject = useCallback(async (body: Record<string, unknown>) => {
    const res = await api.put('/user/projects.php', body)
    return res.data?.data
  }, [])

  const deleteProject = useCallback(async (id: number) => {
    await api.delete('/user/projects.php', { params: { id } })
  }, [])

  const fetchProjectChecklist = useCallback(async (projectId: number): Promise<ProjectChecklistItem[] | null> => {
    try {
      const res = await api.get<{ success: boolean; data: { items: ProjectChecklistItem[] } }>('/user/project-checklist.php', {
        params: { project_id: projectId },
      })
      return res.data?.data?.items ?? null
    } catch {
      return null
    }
  }, [])

  const updateChecklistItem = useCallback(async (projectId: number, metricId: string, patch: { status?: 'open' | 'done' | 'ignored'; note?: string }) => {
    await api.put('/user/project-checklist.php', { projectId, metricId, ...patch })
  }, [])

  const enableProjectShare = useCallback(async (projectId: number, rotate = false): Promise<{ enabled: boolean; token: string }> => {
    const res = await api.post<{ success: boolean; data: { enabled: boolean; token: string } }>('/user/project-share.php', { projectId, rotate })
    return res.data.data
  }, [])

  const disableProjectShare = useCallback(async (projectId: number) => {
    await api.delete('/user/project-share.php', { params: { project_id: projectId } })
  }, [])

  // ─── Audit detail views (P5.10) ─────────────────────────────────
  // Hit los mismos endpoints admin; el backend valida ownership via
  // AuditAccess::require cuando no hay sesión admin. Silenciamos el
  // throw en error — el caller se encarga del estado.
  const fetchAuditDetail = useCallback(async (id: string) => {
    try {
      const res = await api.get('/admin/lead-detail.php', { params: { id } })
      return res.data?.data ?? null
    } catch { return null }
  }, [])

  const fetchAuditSnapshotReport = useCallback(async (auditId: string) => {
    try {
      const res = await api.get('/admin/snapshot-report.php', { params: { audit_id: auditId } })
      return res.data?.data ?? null
    } catch { return null }
  }, [])

  const fetchAuditWaterfall = useCallback(async (id: string) => {
    try {
      const res = await api.get('/admin/waterfall.php', { params: { id } })
      return res.data?.data ?? null
    } catch { return null }
  }, [])

  const fetchAuditChecklist = useCallback(async (auditId: string) => {
    try {
      const res = await api.get('/admin/checklist.php', { params: { audit_id: auditId } })
      return res.data?.data ?? null
    } catch { return null }
  }, [])

  // Check session al montar
  useEffect(() => {
    checkSession()
  }, [checkSession])

  return {
    isAuthenticated,
    isLoading,
    user,
    quota,
    csrfToken,
    login,
    logout,
    checkSession,
    fetchAudits,
    fetchProjects,
    fetchProject,
    createProject,
    updateProject,
    deleteProject,
    fetchProjectChecklist,
    updateChecklistItem,
    enableProjectShare,
    disableProjectShare,
    fetchAuditDetail,
    fetchAuditSnapshotReport,
    fetchAuditWaterfall,
    fetchAuditChecklist,
  }
}
