import { useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/store/authStore'
import api from '@/lib/api'

export function useAuth() {
  const navigate = useNavigate()
  const { isAuthenticated, isLoading, setAuthenticated, setLoading, setCsrfToken } = useAuthStore()

  const checkSession = useCallback(async () => {
    try {
      const res = await api.get('/admin/session.php')
      const data = res.data?.data
      const authed = data?.authenticated === true
      setAuthenticated(authed)
      setCsrfToken(authed ? (data?.csrfToken ?? null) : null)
    } catch {
      setAuthenticated(false)
      setCsrfToken(null)
    } finally {
      setLoading(false)
    }
  }, [setAuthenticated, setLoading, setCsrfToken])

  /**
   * Paso 1 del login. Devuelve:
   *   { needs2fa: true }  → el UI debe pedir el código al usuario
   *   { needs2fa: false } → login completado, ya se seteó el auth state
   */
  const login = useCallback(async (password: string): Promise<{ needs2fa: boolean }> => {
    const res = await api.post('/admin/login.php', { password })
    const data = res.data?.data
    if (data?.needs2fa === true) {
      return { needs2fa: true }
    }
    if (data?.authenticated) {
      setAuthenticated(true)
      setCsrfToken(data?.csrfToken ?? null)
      navigate('/admin/dashboard')
    }
    return { needs2fa: false }
  }, [setAuthenticated, setCsrfToken, navigate])

  /**
   * Paso 2 del login. El servidor exige que exista una sesión pending
   * (fijada por login paso 1). Devuelve el estado auth completo.
   */
  const verify2fa = useCallback(async (code: string): Promise<{ usedRecovery: boolean }> => {
    const res = await api.post('/admin/login-2fa.php', { code })
    const data = res.data?.data
    if (data?.authenticated) {
      setAuthenticated(true)
      setCsrfToken(data?.csrfToken ?? null)
      navigate('/admin/dashboard')
    }
    return { usedRecovery: Boolean(data?.usedRecovery) }
  }, [setAuthenticated, setCsrfToken, navigate])

  const logout = useCallback(async () => {
    try {
      await api.post('/admin/logout.php')
    } catch { /* ignorar */ }
    setAuthenticated(false)
    setCsrfToken(null)
    navigate('/admin')
  }, [setAuthenticated, setCsrfToken, navigate])

  // Verificar sesión al montar
  useEffect(() => {
    checkSession()
  }, [checkSession])

  return { isAuthenticated, isLoading, login, verify2fa, logout, checkSession }
}
