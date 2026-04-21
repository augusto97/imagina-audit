import { useTranslation } from 'react-i18next'
import { X, Filter } from 'lucide-react'
import { Button } from '@/components/ui/button'

export interface FilterChip {
  key: string         // identificador único para el remove
  label: string       // texto visible al usuario
  onRemove: () => void
}

/**
 * Barra con las "chips" de filtros activos. Cada chip muestra el filtro
 * aplicado con una X para removerlo individualmente. Si hay 2+ chips
 * aparece un botón 'Limpiar todo'.
 *
 * Si no hay ningún filtro activo, el componente no renderiza nada.
 */
export function ActiveFiltersBar({
  chips,
  onClearAll,
}: {
  chips: FilterChip[]
  onClearAll: () => void
}) {
  const { t } = useTranslation()
  if (chips.length === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-[var(--border-default)] bg-[var(--bg-secondary)] px-3 py-2">
      <span className="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        <Filter className="h-3 w-3" strokeWidth={1.5} /> {t('leads.filters_active')}
      </span>
      {chips.map((chip) => (
        <button
          key={chip.key}
          type="button"
          onClick={chip.onRemove}
          className="inline-flex items-center gap-1 rounded-full border border-[var(--accent-primary)]/30 bg-white px-2 py-0.5 text-[11px] font-medium text-[var(--accent-primary)] transition-colors hover:bg-[var(--accent-primary)] hover:text-white"
        >
          {chip.label}
          <X className="h-2.5 w-2.5" strokeWidth={2} />
        </button>
      ))}
      {chips.length >= 2 && (
        <Button variant="ghost" size="sm" onClick={onClearAll} className="ml-auto h-6 text-[11px]">
          {t('leads.clear_all')}
        </Button>
      )}
    </div>
  )
}
