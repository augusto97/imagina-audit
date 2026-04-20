import { formatSize, type ResourceBreakdownItem } from './helpers'

const BREAKDOWN_COLORS: Record<string, string> = {
  script: '#FFC107',
  stylesheet: '#2196F3',
  image: '#9C27B0',
  font: '#E91E63',
  document: '#4CAF50',
  other: '#9E9E9E',
  total: '#404040',
  'third-party': '#FF5722',
  media: '#FF5722',
}

/**
 * Desglose del peso y número de requests por tipo de recurso.
 */
export function PageDetailsSection({ data }: { data: ResourceBreakdownItem[] }) {
  const total = data.find(d => d.resourceType === 'total')
  const items = data.filter(d => d.resourceType !== 'total' && d.transferSize > 0)
  const maxSize = Math.max(...items.map(d => d.transferSize), 1)
  const totalSize = total?.transferSize || items.reduce((s, d) => s + d.transferSize, 0)
  const totalReqs = total?.requestCount || items.reduce((s, d) => s + d.requestCount, 0)

  return (
    <div className="mt-8 space-y-4">
      <h2 className="text-lg font-bold text-gray-900">Page Details</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
        {/* Size by type */}
        <div>
          <div className="flex items-baseline justify-between mb-3">
            <span className="text-sm font-medium text-gray-700">Total Page Size</span>
            <span className="text-lg font-bold text-gray-900">{formatSize(totalSize)}</span>
          </div>
          <div className="flex h-6 rounded overflow-hidden mb-3">
            {items.map(d => (
              <div key={d.resourceType} title={`${d.label}: ${formatSize(d.transferSize)}`}
                style={{ width: `${(d.transferSize / totalSize) * 100}%`, backgroundColor: BREAKDOWN_COLORS[d.resourceType] || '#9E9E9E' }}
                className="h-full" />
            ))}
          </div>
          <div className="space-y-1.5">
            {items.sort((a, b) => b.transferSize - a.transferSize).map(d => (
              <div key={d.resourceType} className="flex items-center gap-2 text-xs">
                <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: BREAKDOWN_COLORS[d.resourceType] || '#9E9E9E' }} />
                <span className="w-16 text-gray-500 capitalize">{d.label}</span>
                <div className="flex-1 bg-gray-100 rounded-full h-2">
                  <div className="h-full rounded-full" style={{ width: `${(d.transferSize / maxSize) * 100}%`, backgroundColor: BREAKDOWN_COLORS[d.resourceType] || '#9E9E9E' }} />
                </div>
                <span className="w-16 text-right text-gray-700 font-medium tabular-nums">{formatSize(d.transferSize)}</span>
              </div>
            ))}
          </div>
        </div>
        {/* Requests by type */}
        <div>
          <div className="flex items-baseline justify-between mb-3">
            <span className="text-sm font-medium text-gray-700">Total Page Requests</span>
            <span className="text-lg font-bold text-gray-900">{totalReqs}</span>
          </div>
          <div className="flex h-6 rounded overflow-hidden mb-3">
            {items.map(d => (
              <div key={d.resourceType} title={`${d.label}: ${d.requestCount}`}
                style={{ width: `${(d.requestCount / totalReqs) * 100}%`, backgroundColor: BREAKDOWN_COLORS[d.resourceType] || '#9E9E9E' }}
                className="h-full" />
            ))}
          </div>
          <div className="space-y-1.5">
            {items.sort((a, b) => b.requestCount - a.requestCount).map(d => (
              <div key={d.resourceType} className="flex items-center gap-2 text-xs">
                <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: BREAKDOWN_COLORS[d.resourceType] || '#9E9E9E' }} />
                <span className="w-16 text-gray-500 capitalize">{d.label}</span>
                <span className="text-gray-700 font-medium">{d.requestCount} <span className="text-gray-400">({((d.requestCount / totalReqs) * 100).toFixed(0)}%)</span></span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
