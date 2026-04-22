import { useEffect } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import LeadDetail from '@/components/admin/LeadDetail'
import TechnicalReport from '@/components/admin/TechnicalReport'
import SnapshotReport from '@/components/admin/SnapshotReport'
import WaterfallPage from '@/components/admin/WaterfallPage'
import { useUser } from '@/hooks/useUser'

/**
 * Wrapper user-side de las vistas de audit. Renderiza el componente
 * admin que corresponde (LeadDetail / TechnicalReport / SnapshotReport /
 * WaterfallPage) pero con fetchers del user y basePath=/account/audits.
 *
 * El view se selecciona por la prop `view` — las rutas de App.tsx
 * montan 4 instancias, una por cada tab.
 */
interface Props {
  view: 'detail' | 'report' | 'internal' | 'waterfall'
}

export default function UserAuditViewPage({ view }: Props) {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const {
    isLoading,
    isAuthenticated,
    fetchAuditDetail,
    fetchAuditSnapshotReport,
  } = useUser()

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      // Preservamos el destino en state para redirigir post-login
      navigate('/login', { replace: true, state: { from: location.pathname } })
    }
  }, [isLoading, isAuthenticated, navigate, location.pathname])

  if (isLoading || !isAuthenticated) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  // Determinamos a dónde vuelve el botón "back". Si el audit está atado a
  // un proyecto, idealmente volvería a /account/projects/:id — pero eso
  // requiere saber el projectId acá. Por ahora, volvemos a /account para
  // que el user encuentre sus audits, y desde ahí navegue al proyecto.
  const backTo = '/account'

  const common = {
    basePath: '/account/audits',
    backTo,
  }

  return (
    <div className="min-h-screen bg-[#F4F6F8] p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-6xl">
        {view === 'detail' && (
          <LeadDetail
            key={id}
            fetcher={fetchAuditDetail}
            {...common}
          />
        )}
        {view === 'report' && (
          <TechnicalReport
            key={id}
            fetcher={fetchAuditDetail}
            readOnlyChecklist
            hideAdminControls
            {...common}
          />
        )}
        {view === 'internal' && (
          <SnapshotReport
            key={id}
            fetcher={fetchAuditSnapshotReport}
            hideUploader
            {...common}
          />
        )}
        {view === 'waterfall' && (
          <WaterfallPage
            key={id}
            fetcher={fetchAuditDetail}
            {...common}
          />
        )}
      </div>
    </div>
  )
}
