import { useState } from 'react'
import type { LighthouseAudit } from './helpers'

const IMPACT_COLORS: Record<string, { bg: string; text: string; label: string }> = {
  high: { bg: 'bg-red-100', text: 'text-red-700', label: 'High' },
  medium: { bg: 'bg-amber-100', text: 'text-amber-700', label: 'Med' },
  low: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Low' },
  info: { bg: 'bg-gray-100', text: 'text-gray-500', label: 'N/A' },
  none: { bg: 'bg-emerald-100', text: 'text-emerald-700', label: 'None' },
}

/**
 * Lista de Lighthouse audits agrupados por impacto. Tiene su propio
 * estado local para el audit expandido y el toggle de "mostrar aprobados".
 */
export function StructureAuditsSection({ audits }: { audits: LighthouseAudit[] }) {
  const [expandedAudit, setExpandedAudit] = useState<string | null>(null)
  const [showNone, setShowNone] = useState(false)

  const withImpact = audits.filter(a => a.impact !== 'none' && a.impact !== 'info')
  const noImpact = audits.filter(a => a.impact === 'none' || a.impact === 'info')

  return (
    <div className="mt-8 space-y-4">
      <h2 className="text-lg font-bold text-gray-900">Structure Audits</h2>
      <p className="text-xs text-gray-500">Lighthouse performance audits sorted by impact. Click to expand.</p>

      <div className="border border-gray-200 rounded-lg overflow-hidden">
        <div className="grid grid-cols-[60px_1fr_auto] gap-0 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wider">
          <div className="px-3 py-2">Impact</div>
          <div className="px-3 py-2">Audit</div>
          <div className="px-3 py-2 text-right">Value</div>
        </div>

        {withImpact.map(a => {
          const style = IMPACT_COLORS[a.impact] || IMPACT_COLORS.none
          const isOpen = expandedAudit === a.id
          return (
            <div key={a.id}>
              <div
                className={`grid grid-cols-[60px_1fr_auto] gap-0 border-b border-gray-100 text-xs cursor-pointer ${isOpen ? 'bg-blue-50' : 'hover:bg-gray-50'}`}
                onClick={() => setExpandedAudit(isOpen ? null : a.id)}
              >
                <div className="px-3 py-2.5">
                  <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${style.bg} ${style.text}`}>{style.label}</span>
                </div>
                <div className="px-3 py-2.5 text-gray-700">{a.title}</div>
                <div className="px-3 py-2.5 text-right text-gray-500">{a.displayValue}</div>
              </div>
              {isOpen && (
                <div className="bg-gray-50 border-b border-gray-200 px-4 py-3 text-xs text-gray-600">
                  <p className="whitespace-pre-line">{a.description.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')}</p>
                </div>
              )}
            </div>
          )
        })}

        {/* Toggle for no-impact audits */}
        {noImpact.length > 0 && (
          <div
            className="px-3 py-2 text-xs text-center text-gray-400 cursor-pointer hover:bg-gray-50 border-b border-gray-100"
            onClick={() => setShowNone(!showNone)}
          >
            {showNone ? 'Hide' : 'Show'} {noImpact.length} passed audits {showNone ? '▲' : '▼'}
          </div>
        )}

        {showNone && noImpact.map(a => {
          const style = IMPACT_COLORS[a.impact] || IMPACT_COLORS.none
          return (
            <div key={a.id} className="grid grid-cols-[60px_1fr_auto] gap-0 border-b border-gray-100 text-xs">
              <div className="px-3 py-2">
                <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${style.bg} ${style.text}`}>{style.label}</span>
              </div>
              <div className="px-3 py-2 text-gray-500">{a.title}</div>
              <div className="px-3 py-2 text-right text-gray-400">{a.displayValue}</div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
