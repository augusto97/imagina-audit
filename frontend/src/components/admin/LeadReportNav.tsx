import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, FileText, BarChart3, Database, FileText as DetailIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'

/**
 * Cabecera + navegación entre las vistas de un audit.
 * Agnóstico al modo: con basePath='/admin/leads' se comporta como antes,
 * con basePath='/account/audits' sirve a la vista de usuario dueño.
 */

interface Props {
  auditId: string
  domain?: string
  /** Ruta raíz del detalle sin slash final. Default: /admin/leads */
  basePath?: string
  /** Ruta del botón "volver". Null oculta el botón. */
  backTo?: string | null
  backLabelKey?: string
}

export default function LeadReportNav({
  auditId,
  domain,
  basePath = '/admin/leads',
  backTo = '/admin/leads',
  backLabelKey = 'settings.leadnav_back',
}: Props) {
  const { t } = useTranslation()
  const location = useLocation()
  const navigate = useNavigate()
  const path = location.pathname

  const base = `${basePath}/${auditId}`
  const tabs = [
    { to: base,                label: t('settings.leadnav_lead'),      icon: DetailIcon, exact: true },
    { to: `${base}/report`,    label: t('settings.leadnav_report'),    icon: FileText },
    { to: `${base}/waterfall`, label: t('settings.leadnav_waterfall'), icon: BarChart3 },
    { to: `${base}/internal`,  label: t('settings.leadnav_internal'),  icon: Database },
  ]

  return (
    <div className="flex flex-wrap items-center gap-3">
      {backTo !== null && (
        <Button variant="ghost" size="sm" onClick={() => navigate(backTo)}>
          <ArrowLeft className="h-4 w-4" strokeWidth={1.5} /> {t(backLabelKey)}
        </Button>
      )}
      {domain && (
        <h1 className="truncate text-xl font-bold text-[var(--text-primary)]">{domain}</h1>
      )}
      <nav className="ml-auto flex flex-wrap gap-1.5">
        {tabs.map((tab) => {
          const isActive = tab.exact ? path === tab.to : path.startsWith(tab.to)
          const Icon = tab.icon
          return (
            <Link
              key={tab.to}
              to={tab.to}
              className={`inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${
                isActive
                  ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)] text-white'
                  : 'border-[var(--border-default)] bg-white text-[var(--text-secondary)] hover:border-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
              }`}
            >
              <Icon className="h-3.5 w-3.5" strokeWidth={1.5} />
              {tab.label}
            </Link>
          )
        })}
      </nav>
    </div>
  )
}
