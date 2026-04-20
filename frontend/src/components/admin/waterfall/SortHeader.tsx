type SortField = 'default' | 'url' | 'status' | 'size' | 'duration' | 'start'

/**
 * Encabezado clickeable de columna con indicador de dirección de sort.
 * 3 clicks: asc → desc → default (sin sort).
 */
export function SortHeader({
  label,
  field,
  current,
  dir,
  onSort,
  className = '',
}: {
  label: string
  field: string
  current: string
  dir: 'asc' | 'desc'
  onSort: (f: SortField) => void
  className?: string
}) {
  const active = current === field
  return (
    <div
      className={`flex items-center gap-1 cursor-pointer hover:text-gray-700 ${active ? 'text-gray-700' : ''} ${className}`}
      onClick={() => onSort(field as Exclude<SortField, 'default'>)}
    >
      {label}
      {active && <span className="text-[10px]">{dir === 'asc' ? '▲' : '▼'}</span>}
    </div>
  )
}
