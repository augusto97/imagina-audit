import { Link, useLocation, useNavigate } from 'react-router-dom'
import { ArrowLeft, FileText, BarChart3, Database, FileText as DetailIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'

/**
 * Cabecera + navegación entre las vistas de un lead.
 * Se usa en LeadDetail, TechnicalReport, WaterfallPage y SnapshotReport
 * para que el operador pueda saltar entre las cuatro vistas sin volver atrás.
 *
 * El botón de la vista activa queda resaltado (sólido + accent) y los
 * demás quedan como outline.
 */

interface Props {
  auditId: string
  domain?: string         // si no se pasa, se omite el título
  showBackToList?: boolean // botón "Volver" a la lista de leads
}

export default function LeadReportNav({ auditId, domain, showBackToList = true }: Props) {
  const location = useLocation()
  const navigate = useNavigate()
  const path = location.pathname

  const tabs = [
    { to: `/admin/leads/${auditId}`,           label: 'Lead',             icon: DetailIcon, exact: true },
    { to: `/admin/leads/${auditId}/report`,    label: 'Reporte técnico',  icon: FileText },
    { to: `/admin/leads/${auditId}/waterfall`, label: 'Waterfall',        icon: BarChart3 },
    { to: `/admin/leads/${auditId}/internal`,  label: 'Análisis interno', icon: Database },
  ]

  return (
    <div className="flex flex-wrap items-center gap-3">
      {showBackToList && (
        <Button variant="ghost" size="sm" onClick={() => navigate('/admin/leads')}>
          <ArrowLeft className="h-4 w-4" strokeWidth={1.5} /> Leads
        </Button>
      )}
      {domain && (
        <h1 className="truncate text-xl font-bold text-[var(--text-primary)]">{domain}</h1>
      )}
      <nav className="ml-auto flex flex-wrap gap-1.5">
        {tabs.map((t) => {
          const isActive = t.exact ? path === t.to : path.startsWith(t.to)
          const Icon = t.icon
          return (
            <Link
              key={t.to}
              to={t.to}
              className={`inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${
                isActive
                  ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)] text-white'
                  : 'border-[var(--border-default)] bg-white text-[var(--text-secondary)] hover:border-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
              }`}
            >
              <Icon className="h-3.5 w-3.5" strokeWidth={1.5} />
              {t.label}
            </Link>
          )
        })}
      </nav>
    </div>
  )
}
