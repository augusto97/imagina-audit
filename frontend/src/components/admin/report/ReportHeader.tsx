import { memo } from 'react'
import { useTranslation } from 'react-i18next'
import { ExternalLink, Printer, Pin, PinOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AuditResult } from '@/types/audit'

/**
 * Cabecera del reporte técnico: título, fecha, link al sitio, toggle de
 * protección anti-borrado, imprimir.
 *
 * El "volver" y la navegación entre vistas del lead la maneja LeadReportNav
 * que se monta encima de este componente.
 */
export const ReportHeader = memo(function ReportHeader({
  result,
  isPinned,
  onTogglePin,
}: {
  result: AuditResult
  isPinned: boolean
  /** Omitir esta prop oculta el botón de pin (uso admin-only). */
  onTogglePin?: () => void
}) {
  const { t, i18n } = useTranslation()
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div className="flex items-center gap-2">
          <h2 className="text-lg font-semibold text-[var(--text-primary)]">{t('report.header_technical_report')}</h2>
          {isPinned && (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold uppercase tracking-wider">
              <Pin className="h-3 w-3 fill-current" strokeWidth={2} /> {t('report.header_protected')}
            </span>
          )}
        </div>
        <div className="flex items-center gap-2 mt-0.5">
          <a href={result.url} target="_blank" rel="noreferrer" className="text-sm text-[var(--accent-primary)] hover:underline flex items-center gap-1">
            {result.domain} <ExternalLink className="h-3 w-3" />
          </a>
          <span className="text-xs text-[var(--text-tertiary)]">
            {new Date(result.timestamp).toLocaleDateString(i18n.language, { day: 'numeric', month: 'long', year: 'numeric' })}
          </span>
        </div>
      </div>
      <div className="flex gap-2">
        {onTogglePin && (
          <Button
            variant={isPinned ? 'default' : 'outline'}
            size="sm"
            onClick={onTogglePin}
            className={isPinned ? 'bg-amber-500 hover:bg-amber-600 text-white' : ''}
          >
            {isPinned
              ? <><PinOff className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.header_unprotect')}</>
              : <><Pin className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.header_protect')}</>}
          </Button>
        )}
        <Button variant="outline" size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.header_print')}
        </Button>
      </div>
    </div>
  )
})
