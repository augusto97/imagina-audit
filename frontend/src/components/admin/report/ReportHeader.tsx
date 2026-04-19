import { memo } from 'react'
import { ArrowLeft, ExternalLink, Printer } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AuditResult } from '@/types/audit'

/**
 * Cabecera del reporte técnico: volver, dominio, fecha, imprimir.
 */
export const ReportHeader = memo(function ReportHeader({
  result,
  onBack,
}: {
  result: AuditResult
  onBack: () => void
}) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" strokeWidth={1.5} />
        </Button>
        <div>
          <h1 className="text-xl font-bold text-[var(--text-primary)]">Reporte Técnico</h1>
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
      <Button variant="outline" size="sm" onClick={() => window.print()}>
        <Printer className="h-4 w-4 mr-1" strokeWidth={1.5} /> Imprimir
      </Button>
    </div>
  )
})
