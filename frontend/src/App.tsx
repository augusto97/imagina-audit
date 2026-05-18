import { lazy, Suspense, useEffect, useState } from 'react'
import { Routes, Route, useLocation, useNavigate } from 'react-router-dom'
import { Toaster } from 'sonner'
import { Loader2 } from 'lucide-react'
import HomePage from './pages/HomePage'
import ResultsPage from './pages/ResultsPage'
import ComparePage from './pages/ComparePage'
import NotFoundPage from './pages/NotFoundPage'
import { useConfigStore } from './store/configStore'
import api from './lib/api'

const AdminPage = lazy(() => import('./pages/AdminPage'))
const SetupPage = lazy(() => import('./pages/SetupPage'))
const UserLoginPage = lazy(() => import('./pages/UserLoginPage'))
const UserAccountPage = lazy(() => import('./pages/UserAccountPage'))
const UserProjectsPage = lazy(() => import('./pages/UserProjectsPage'))
const UserProjectDetailPage = lazy(() => import('./pages/UserProjectDetailPage'))
const SharedProjectPage = lazy(() => import('./pages/SharedProjectPage'))
const UserAuditViewPage = lazy(() => import('./pages/UserAuditViewPage'))

function App() {
  const reloadConfig = useConfigStore((s) => s.reload)

  // Carga inicial del config público (color, logos, textos del home, SEO).
  // Al mismo tiempo aplica el color primario al CSS y el favicon al DOM.
  useEffect(() => { reloadConfig() }, [reloadConfig])

  return (
    <SetupGate>
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/results/:auditId" element={<ResultsPage />} />
        <Route path="/compare" element={<ComparePage />} />
        <Route path="/admin/*" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <AdminPage />
          </Suspense>
        } />
        <Route path="/login" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserLoginPage />
          </Suspense>
        } />
        <Route path="/account" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserAccountPage />
          </Suspense>
        } />
        <Route path="/account/projects" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserProjectsPage />
          </Suspense>
        } />
        <Route path="/account/projects/:id" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserProjectDetailPage />
          </Suspense>
        } />
        <Route path="/account/audits/:id" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserAuditViewPage view="detail" />
          </Suspense>
        } />
        <Route path="/account/audits/:id/report" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserAuditViewPage view="report" />
          </Suspense>
        } />
        <Route path="/account/audits/:id/internal" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserAuditViewPage view="internal" />
          </Suspense>
        } />
        <Route path="/account/audits/:id/waterfall" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <UserAuditViewPage view="waterfall" />
          </Suspense>
        } />
        <Route path="/shared/:token" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <SharedProjectPage />
          </Suspense>
        } />
        <Route path="/setup" element={
          <Suspense fallback={
            <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
              <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
            </div>
          }>
            <SetupPage />
          </Suspense>
        } />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
      <Toaster
        position="top-right"
        toastOptions={{
          style: {
            background: 'var(--bg-primary)',
            border: '1px solid var(--border-default)',
            color: 'var(--text-primary)',
          },
        }}
      />
    </SetupGate>
  )
}

/**
 * SetupGate — bloquea las rutas reales hasta que la app está instalada.
 * Si /api/setup/status indica installed=false y la ruta actual no es
 * /setup, redirige a /setup. Una vez instalado, el componente es un
 * pass-through.
 *
 * El check se hace una sola vez al montar; el resultado se cachea en
 * estado local. Errores de red (sin backend) caen al modo "asumir
 * instalado" para no bloquear el dev local.
 */
function SetupGate({ children }: { children: React.ReactNode }) {
  const [checking, setChecking] = useState(true)
  const [installed, setInstalled] = useState(true) // optimista: si la API falla, no bloqueamos
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        const res = await api.get('/setup/status.php')
        const isInstalled = !!res.data?.data?.installed
        if (!cancelled) setInstalled(isInstalled)
      } catch { /* asumimos instalado si el endpoint no responde */ }
      if (!cancelled) setChecking(false)
    })()
    return () => { cancelled = true }
  }, [])

  useEffect(() => {
    if (checking) return
    if (!installed && location.pathname !== '/setup') {
      navigate('/setup', { replace: true })
    }
  }, [checking, installed, location.pathname, navigate])

  if (checking) {
    return (
      <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }
  return <>{children}</>
}

export default App
