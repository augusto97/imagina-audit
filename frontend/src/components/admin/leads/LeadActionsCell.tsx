import { useState, type MouseEvent } from 'react'
import { FileText, MessageCircle, Pin, PinOff, Trash2, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import type { Lead } from '@/types/lead'

/**
 * Columna de acciones de la fila de un lead. Reducida a lo esencial — el
 * click en el resto de la fila abre el detalle (donde LeadReportNav ya
 * ofrece Waterfall, Reporte Técnico y Análisis Interno), así que aquí
 * solo mostramos:
 *   - Abrir lead (ícono grande, obvio)
 *   - WhatsApp (si aplica)
 *   - Pin / Unpin (protección)
 *   - Eliminar (bloqueado si está pinned; confirm inline)
 *
 * Todos los botones hacen stopPropagation para no colisionar con el
 * onClick de la fila contenedora.
 */
export function LeadActionsCell({
  lead,
  onOpen,
  onTogglePin,
  onDelete,
}: {
  lead: Lead
  onOpen: (lead: Lead) => void
  onTogglePin: (lead: Lead) => Promise<void> | void
  onDelete: (lead: Lead) => Promise<void> | void
}) {
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [pinBusy, setPinBusy] = useState(false)

  const stop = (fn: () => void) => (e: MouseEvent) => { e.stopPropagation(); fn() }

  const togglePin = stop(async () => {
    setPinBusy(true)
    try { await onTogglePin(lead) } finally { setPinBusy(false) }
  })

  if (confirmingDelete) {
    return (
      <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
        <Button variant="destructive" size="sm" className="h-8 text-xs" onClick={() => { onDelete(lead); setConfirmingDelete(false) }}>
          Confirmar
        </Button>
        <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => setConfirmingDelete(false)}>
          Cancelar
        </Button>
      </div>
    )
  }

  return (
    <div className="flex justify-end gap-0.5">
      <Tooltip>
        <TooltipTrigger asChild>
          <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--accent-primary)]" onClick={stop(() => onOpen(lead))}>
            <FileText className="h-4 w-4" strokeWidth={1.5} />
          </Button>
        </TooltipTrigger>
        <TooltipContent>Abrir lead</TooltipContent>
      </Tooltip>

      {lead.leadWhatsapp && (
        <Tooltip>
          <TooltipTrigger asChild>
            <a
              href={`https://wa.me/${lead.leadWhatsapp.replace(/[^0-9]/g, '')}`}
              target="_blank"
              rel="noreferrer"
              onClick={(e) => e.stopPropagation()}
            >
              <Button variant="ghost" size="icon" className="h-8 w-8 text-emerald-500">
                <MessageCircle className="h-4 w-4" strokeWidth={1.5} />
              </Button>
            </a>
          </TooltipTrigger>
          <TooltipContent>WhatsApp</TooltipContent>
        </Tooltip>
      )}

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className={`h-8 w-8 ${lead.isPinned ? 'text-amber-500 hover:text-amber-600' : 'text-[var(--text-tertiary)] hover:text-amber-500'}`}
            disabled={pinBusy}
            onClick={togglePin}
          >
            {pinBusy
              ? <Loader2 className="h-4 w-4 animate-spin" />
              : lead.isPinned
                ? <PinOff className="h-4 w-4" strokeWidth={1.5} />
                : <Pin className="h-4 w-4" strokeWidth={1.5} />}
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          {lead.isPinned ? 'Quitar protección' : 'Proteger del borrado automático'}
        </TooltipContent>
      </Tooltip>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-red-400 hover:text-red-600 disabled:opacity-30"
            disabled={lead.isPinned}
            onClick={stop(() => setConfirmingDelete(true))}
          >
            <Trash2 className="h-4 w-4" strokeWidth={1.5} />
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          {lead.isPinned ? 'Desprotege primero' : 'Eliminar'}
        </TooltipContent>
      </Tooltip>
    </div>
  )
}
