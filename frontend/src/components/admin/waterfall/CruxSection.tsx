import type { CruxData } from './helpers'

const CRUX_COLORS: Record<string, string> = { FAST: '#0CCE6B', AVERAGE: '#FFA400', SLOW: '#FF4E42' }

/**
 * Core Web Vitals reales vía Chrome User Experience Report (ventana de 28 días).
 */
export function CruxSection({ data }: { data: CruxData }) {
  const catLabel = data.overallCategory === 'FAST' ? 'Passed' : data.overallCategory === 'AVERAGE' ? 'Needs Improvement' : 'Poor'
  const catColor = CRUX_COLORS[data.overallCategory || ''] || '#999'

  return (
    <div className="mt-8 space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold text-gray-900">Core Web Vitals (Real Users)</h2>
        <span className="text-sm font-bold px-3 py-1 rounded-full" style={{ color: catColor, backgroundColor: catColor + '15' }}>
          {catLabel}
        </span>
      </div>
      <p className="text-xs text-gray-500">Based on Chrome User Experience Report (CrUX) — real data from the last 28 days.</p>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {data.metrics.map(m => {
          const val = m.percentile
          const color = CRUX_COLORS[m.category || ''] || '#999'
          const fmtVal = m.label === 'CLS' ? (val !== null ? (val / 100).toFixed(2) : '—') : (val !== null ? (val < 1000 ? `${val}ms` : `${(val / 1000).toFixed(1)}s`) : '—')
          const catText = m.category === 'FAST' ? 'Good' : m.category === 'AVERAGE' ? 'Needs Improvement' : 'Poor'

          return (
            <div key={m.id} className="border border-gray-200 rounded-lg p-4">
              <div className="text-xs text-gray-500 mb-1">{m.label}</div>
              <div className="text-2xl font-bold tabular-nums" style={{ color }}>{fmtVal}</div>
              <div className="text-xs font-medium mt-1 px-2 py-0.5 rounded inline-block" style={{ color, backgroundColor: color + '15' }}>{catText}</div>
              {m.distributions.length === 3 && (
                <div className="flex h-2 rounded-full overflow-hidden mt-3">
                  <div style={{ width: `${m.distributions[0].proportion * 100}%` }} className="bg-[#0CCE6B]" title={`Good: ${(m.distributions[0].proportion * 100).toFixed(0)}%`} />
                  <div style={{ width: `${m.distributions[1].proportion * 100}%` }} className="bg-[#FFA400]" title={`Needs Improvement: ${(m.distributions[1].proportion * 100).toFixed(0)}%`} />
                  <div style={{ width: `${m.distributions[2].proportion * 100}%` }} className="bg-[#FF4E42]" title={`Poor: ${(m.distributions[2].proportion * 100).toFixed(0)}%`} />
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
