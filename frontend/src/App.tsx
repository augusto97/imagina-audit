import { lazy, Suspense, useEffect } from 'react'
import { Routes, Route } from 'react-router-dom'
import { Toaster } from 'sonner'
import { Loader2 } from 'lucide-react'
import HomePage from './pages/HomePage'
import ResultsPage from './pages/ResultsPage'
import ComparePage from './pages/ComparePage'
import NotFoundPage from './pages/NotFoundPage'
import { useConfigStore } from './store/configStore'

const AdminPage = lazy(() => import('./pages/AdminPage'))
const UserLoginPage = lazy(() => import('./pages/UserLoginPage'))
const UserAccountPage = lazy(() => import('./pages/UserAccountPage'))

function App() {
  const reloadConfig = useConfigStore((s) => s.reload)

  // Carga inicial del config público (color, logos, textos del home, SEO).
  // Al mismo tiempo aplica el color primario al CSS y el favicon al DOM.
  useEffect(() => { reloadConfig() }, [reloadConfig])

  return (
    <>
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
    </>
  )
}

export default App
