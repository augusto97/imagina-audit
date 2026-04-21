import { Pin, PinOff, Trash2, X, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

/**
 * Barra sticky que aparece arriba de la tabla cuando hay filas
 * seleccionadas. Ofrece pin/unpin/delete en lote.
 */
export function LeadsBulkBar({
  count,
  busy,
  onClear,
  onPin,
  onUnpin,
  onDelete,
}: {
  count: number
  busy: boolean
  onClear: () => void
  onPin: () => void
  onUnpin: () => void
  onDelete: () => void
}) {
  if (count === 0) return null

  return (
    <div className="sticky top-0 z-10 flex flex-wrap items-center gap-2 rounded-lg border border-[var(--accent-primary)] bg-[var(--accent-primary)] px-3 py-2 shadow-sm">
      <span className="inline-flex items-center gap-2 text-sm font-medium text-white">
        <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-white px-1.5 text-[11px] font-bold text-[var(--accent-primary)]">
          {count}
        </span>
        seleccionad{count === 1 ? 'o' : 'os'}
      </span>

      <div className="ml-auto flex flex-wrap gap-1">
        <Button variant="secondary" size="sm" onClick={onPin} disabled={busy} className="bg-white/95 hover:bg-white">
          {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Pin className="h-3.5 w-3.5" strokeWidth={1.5} />}
          Proteger
        </Button>
        <Button variant="secondary" size="sm" onClick={onUnpin} disabled={busy} className="bg-white/95 hover:bg-white">
          {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <PinOff className="h-3.5 w-3.5" strokeWidth={1.5} />}
          Desproteger
        </Button>
        <Button variant="destructive" size="sm" onClick={onDelete} disabled={busy}>
          {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" strokeWidth={1.5} />}
          Eliminar
        </Button>
        <Button variant="ghost" size="icon" onClick={onClear} disabled={busy} className="text-white hover:bg-white/20 hover:text-white">
          <X className="h-4 w-4" strokeWidth={1.5} />
        </Button>
      </div>
    </div>
  )
}
