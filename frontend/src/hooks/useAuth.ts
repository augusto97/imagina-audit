import { useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/store/authStore'
import api from '@/lib/api'

export function useAuth() {
  const navigate = useNavigate()
  const { isAuthenticated, isLoading, setAuthenticated, setLoading } = useAuthStore()

  const checkSession = useCallback(async () => {
    try {
      const res = await api.get('/admin/session.php')
      setAuthenticated(res.data?.data?.authenticated === true)
    } catch {
      setAuthenticated(false)
    } finally {
      setLoading(false)
    }
  }, [setAuthenticated, setLoading])

  const login = useCallback(async (password: string) => {
    const res = await api.post('/admin/login.php', { password })
    if (res.data?.data?.authenticated) {
      setAuthenticated(true)
      navigate('/admin/dashboard')
    }
  }, [setAuthenticated, navigate])

  const logout = useCallback(async () => {
    try {
      await api.post('/admin/logout.php')
    } catch { /* ignorar */ }
    setAuthenticated(false)
    navigate('/admin')
  }, [setAuthenticated, navigate])

  // Verificar sesión al montar
  useEffect(() => {
    checkSession()
  }, [checkSession])

  return { isAuthenticated, isLoading, login, logout, checkSession }
}
