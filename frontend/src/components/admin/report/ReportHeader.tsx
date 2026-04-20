import { memo } from 'react'
import { ArrowLeft, ExternalLink, Printer, Pin, PinOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AuditResult } from '@/types/audit'

/**
 * Cabecera del reporte técnico: volver, dominio, fecha, toggle de
 * protección anti-borrado, imprimir.
 */
export const ReportHeader = memo(function ReportHeader({
  result,
  isPinned,
  onBack,
  onTogglePin,
}: {
  result: AuditResult
  isPinned: boolean
  onBack: () => void
  onTogglePin: () => void
}) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" strokeWidth={1.5} />
        </Button>
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-xl font-bold text-[var(--text-primary)]">Reporte Técnico</h1>
            {isPinned && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold uppercase tracking-wider">
                <Pin className="h-3 w-3 fill-current" strokeWidth={2} /> Protegido
              </span>
            )}
          </div>
          <div className="flex items-center gap-2 mt-0.5">
            <a href={result.url} target="_blank" rel="noreferrer" className="text-sm text-[var(--accent-primary)] hover:underline flex items-center gap-1">
              {result.domain} <ExternalLink className="h-3 w-3" />
            </a>
            <span className="text-xs text-[var(--text-tertiary)]">
              {new Date(result.timestamp).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' })}
            </span>
          </div>
        </div>
      </div>
      <div className="flex gap-2">
        <Button
          variant={isPinned ? 'default' : 'outline'}
          size="sm"
          onClick={onTogglePin}
          className={isPinned ? 'bg-amber-500 hover:bg-amber-600 text-white' : ''}
        >
          {isPinned
            ? <><PinOff className="h-4 w-4 mr-1" strokeWidth={1.5} /> Quitar protección</>
            : <><Pin className="h-4 w-4 mr-1" strokeWidth={1.5} /> Proteger</>}
        </Button>
        <Button variant="outline" size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4 mr-1" strokeWidth={1.5} /> Imprimir
        </Button>
      </div>
    </div>
  )
})
