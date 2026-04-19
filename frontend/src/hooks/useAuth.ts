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

  const login = useCallback(async (password: string) => {
    const res = await api.post('/admin/login.php', { password })
    const data = res.data?.data
    if (data?.authenticated) {
      setAuthenticated(true)
      setCsrfToken(data?.csrfToken ?? null)
      navigate('/admin/dashboard')
    }
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

  return { isAuthenticated, isLoading, login, logout, checkSession }
}
