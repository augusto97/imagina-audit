import { useTranslation } from 'react-i18next'
import { Blocks, Database, Pin, ExternalLink } from 'lucide-react'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import type { Lead } from '@/types/lead'

/**
 * Celda "Dominio" enriquecida: avatar circular con la inicial, dominio
 * con link al sitio real, y 3 mini-badges a la derecha:
 *   - WordPress (azul)
 *   - Snapshot conectado (emerald)
 *   - Protegido del borrado (amber)
 *
 * Debajo del dominio, nombre + empresa del lead cuando existen.
 */
export function DomainCell({ lead }: { lead: Lead }) {
  const { t } = useTranslation()
  const subline = [lead.leadName, lead.leadCompany].filter(Boolean).join(' · ')

  return (
    <div className="flex items-center gap-2.5 min-w-0">
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-primary)]/8 text-xs font-bold text-[var(--accent-primary)]">
        {lead.domain.charAt(0).toUpperCase()}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          {/* Domain con link externo al sitio real */}
          <a
            href={lead.url}
            target="_blank"
            rel="noreferrer"
            onClick={(e) => e.stopPropagation()}
            className="truncate font-medium text-[var(--text-primary)] hover:text-[var(--accent-primary)]"
            title={lead.url}
          >
            {lead.domain}
          </a>
          <ExternalLink className="h-3 w-3 shrink-0 text-[var(--text-tertiary)]" />

          {/* Mini-badges */}
          {lead.isWordPress && (
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="inline-flex items-center rounded-md bg-blue-50 px-1 py-0.5 text-[9px] font-semibold text-blue-700 ring-1 ring-blue-200">
                  <Blocks className="h-2.5 w-2.5" strokeWidth={2} />
                </span>
              </TooltipTrigger>
              <TooltipContent>{t('leads.tooltip_wordpress')}</TooltipContent>
            </Tooltip>
          )}
          {lead.hasSnapshot && (
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="inline-flex items-center rounded-md bg-emerald-50 px-1 py-0.5 text-[9px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
                  <Database className="h-2.5 w-2.5" strokeWidth={2} />
                </span>
              </TooltipTrigger>
              <TooltipContent>{t('leads.tooltip_snapshot')}</TooltipContent>
            </Tooltip>
          )}
          {lead.isPinned && (
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="inline-flex items-center rounded-md bg-amber-50 px-1 py-0.5 ring-1 ring-amber-200">
                  <Pin className="h-2.5 w-2.5 fill-amber-500 text-amber-500" strokeWidth={2} />
                </span>
              </TooltipTrigger>
              <TooltipContent>{t('leads.tooltip_pinned')}</TooltipContent>
            </Tooltip>
          )}
        </div>
        {subline && (
          <div className="mt-0.5 truncate text-[11px] text-[var(--text-tertiary)]">{subline}</div>
        )}
      </div>
    </div>
  )
}
