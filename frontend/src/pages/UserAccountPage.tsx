import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { useUser } from '@/hooks/useUser'

/**
 * Stub: la versión completa con dashboard de cuota + historial se
 * implementa en P4.6. Por ahora garantiza que el route /account
 * protege la sesión y redirige a /login si no hay user.
 */
export default function UserAccountPage() {
  const navigate = useNavigate()
  const { isLoading, isAuthenticated, user } = useUser()

  useEffect(() => {
    if (!isLoading && !isAuthenticated) navigate('/login', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  if (isLoading || !isAuthenticated) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)] p-8">
      <div className="rounded-2xl border border-[var(--border-default)] bg-white p-8 shadow-sm">
        <p className="text-sm text-[var(--text-secondary)]">
          Signed in as <strong>{user?.email}</strong>
        </p>
      </div>
    </div>
  )
}
