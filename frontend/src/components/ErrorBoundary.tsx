import { Component, type ReactNode } from 'react'
import i18n from '@/i18n'

interface Props { children: ReactNode; fallback?: ReactNode }
interface State { error: string | null }

/**
 * Boundary genérico. Antes solo el AdminPage tenía boundary; un error
 * de render en ResultsPage (p.ej. un result_json viejo con shape distinto
 * post-recalculate) le dejaba al prospecto pantalla blanca. Reusarlo en
 * `<Routes>` previene eso.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error: error.message }
  }

  componentDidCatch(error: Error, info: { componentStack?: string | null }) {
    // En dev queremos verlo en consola; en prod no exponer stack al user.
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.error('[ErrorBoundary]', error, info.componentStack)
    }
  }

  render() {
    if (this.state.error) {
      if (this.props.fallback) return this.props.fallback
      return (
        <div className="flex min-h-screen items-center justify-center bg-[var(--bg-primary)] p-8">
          <div className="max-w-md text-center">
            <p className="text-lg font-semibold text-red-600">{i18n.t('dashboard.error_title')}</p>
            <pre className="mt-3 max-h-64 overflow-auto rounded bg-red-50 p-3 text-left text-xs text-red-700">{this.state.error}</pre>
            <button
              onClick={() => window.location.reload()}
              className="mt-4 text-sm text-blue-600 underline"
            >
              {i18n.t('common.reload')}
            </button>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
