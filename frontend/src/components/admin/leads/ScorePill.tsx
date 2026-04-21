/**
 * Píldora compacta con el score y color según nivel. A diferencia de la
 * versión anterior, soporta los 5 niveles de Scoring.php:
 * critical / deficient / regular / good / excellent. Cae a un tono
 * neutro si llega un string inesperado.
 */
export function ScorePill({ score, level }: { score: number; level: string }) {
  const colors: Record<string, string> = {
    critical:  'bg-red-100 text-red-700 ring-red-200',
    deficient: 'bg-orange-100 text-orange-700 ring-orange-200',
    regular:   'bg-amber-100 text-amber-700 ring-amber-200',
    warning:   'bg-amber-100 text-amber-700 ring-amber-200', // alias legacy
    good:      'bg-emerald-50 text-emerald-700 ring-emerald-200',
    excellent: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
    info:      'bg-blue-50 text-blue-700 ring-blue-200',
  }
  const cls = colors[level] || 'bg-gray-100 text-gray-600 ring-gray-200'
  return (
    <span className={`inline-flex min-w-[42px] items-center justify-center rounded-full px-2.5 py-1 text-xs font-bold tabular-nums ring-1 ${cls}`}>
      {score}
    </span>
  )
}
