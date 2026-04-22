import { useEffect, useState, useMemo } from 'react'
import { useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ExternalLink, Filter } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import LeadReportNav from './LeadReportNav'
import type { AuditResult } from '@/types/audit'

import {
  type NetworkRequest,
  type CruxData,
  type ResourceBreakdownItem,
  type LighthouseAudit,
  TYPE_COLORS,
  TYPE_LABELS,
  formatSize,
  extractFilename,
} from './waterfall/helpers'
import { SortHeader } from './waterfall/SortHeader'
import { PageDetailsSection } from './waterfall/PageDetailsSection'
import { CruxSection } from './waterfall/CruxSection'
import { StructureAuditsSection } from './waterfall/StructureAuditsSection'
import { PerformanceDetails } from './waterfall/PerformanceDetails'

/**
 * Vista detallada de waterfall, Core Web Vitals, Page Details y Structure
 * Audits para una auditoría dada. Es el admin equivalente del Network tab
 * de DevTools + GTmetrix/Pingdom.
 *
 * Orquestador: carga los datos, compone filtros + tabla + secciones.
 * Toda la presentación por sección está en `waterfall/`.
 */
export default function WaterfallPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const { fetchLeadDetail } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [loading, setLoading] = useState(true)
  const [filterType, setFilterType] = useState('All')
  const [filterOrigin, setFilterOrigin] = useState<'all' | 'local' | 'external'>('all')
  const [search, setSearch] = useState('')
  const [expandedRow, setExpandedRow] = useState<number | null>(null)
  const [sortBy, setSortBy] = useState<'default' | 'url' | 'status' | 'size' | 'duration' | 'start'>('default')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc')

  const [requests, setRequests] = useState<NetworkRequest[]>([])
  const [cruxData, setCruxData] = useState<CruxData | null>(null)
  const [resourceBreakdown, setResourceBreakdown] = useState<ResourceBreakdownItem[]>([])
  const [lighthouseAudits, setLighthouseAudits] = useState<LighthouseAudit[]>([])
  const [lcpElement, setLcpElement] = useState<Record<string, string> | null>(null)
  const [clsElements, setClsElements] = useState<Array<Record<string, unknown>>>([])
  const [mainThreadWork, setMainThreadWork] = useState<Array<{ group: string; duration: number }>>([])

  useEffect(() => {
    if (!id) return
    Promise.all([
      fetchLeadDetail(id),
      api.get('/admin/waterfall.php', { params: { id } }).then(r => r.data?.data).catch(() => null)
    ]).then(([audit, perfData]: [AuditResult, { waterfall?: NetworkRequest[]; crux?: CruxData; resourceBreakdown?: ResourceBreakdownItem[]; lighthouseAudits?: LighthouseAudit[] } | null]) => {
      setResult(audit)
      setRequests(perfData?.waterfall || [])
      setCruxData(perfData?.crux || null)
      setResourceBreakdown(perfData?.resourceBreakdown || [])
      setLighthouseAudits(perfData?.lighthouseAudits || [])
      setLcpElement((perfData as Record<string, unknown>)?.lcpElement as Record<string, string> | null ?? null)
      setClsElements(((perfData as Record<string, unknown>)?.clsElements || []) as Array<Record<string, unknown>>)
      setMainThreadWork(((perfData as Record<string, unknown>)?.mainThreadWork || []) as Array<{ group: string; duration: number }>)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail])

  const siteDomain = result ? new URL(result.url).hostname : ''

  const filtered = useMemo(() => {
    let items = requests
    if (filterType !== 'All') {
      items = items.filter(r => (TYPE_LABELS[r.resourceType] || 'Other') === filterType)
    }
    if (filterOrigin !== 'all') {
      items = items.filter(r => {
        try {
          const h = new URL(r.url).hostname
          const isLocal = h === siteDomain || h.endsWith('.' + siteDomain)
          return filterOrigin === 'local' ? isLocal : !isLocal
        } catch { return true }
      })
    }
    if (search) {
      const q = search.toLowerCase()
      items = items.filter(r => r.url.toLowerCase().includes(q))
    }
    if (sortBy !== 'default') {
      items = [...items].sort((a, b) => {
        let va: number | string = 0, vb: number | string = 0
        if (sortBy === 'url') { va = a.url.toLowerCase(); vb = b.url.toLowerCase() }
        else if (sortBy === 'status') { va = a.statusCode; vb = b.statusCode }
        else if (sortBy === 'size') { va = a.transferSize; vb = b.transferSize }
        else if (sortBy === 'duration') { va = a.endTime - a.startTime; vb = b.endTime - b.startTime }
        else if (sortBy === 'start') { va = a.startTime; vb = b.startTime }
        if (va < vb) return sortDir === 'asc' ? -1 : 1
        if (va > vb) return sortDir === 'asc' ? 1 : -1
        return 0
      })
    }
    return items
  }, [requests, filterType, filterOrigin, search, siteDomain, sortBy, sortDir])

  // Extraer milestones de performance del audit result
  const milestones = useMemo(() => {
    if (!result) return []
    const marks: Array<{ label: string; time: number; color: string }> = []
    const perfModule = result.modules.find(m => m.id === 'performance')
    if (!perfModule) return marks
    for (const metric of perfModule.metrics) {
      if (metric.id === 'fcp' && typeof metric.value === 'number') marks.push({ label: 'FCP', time: metric.value, color: '#10B981' })
      if (metric.id === 'lcp' && typeof metric.value === 'number') marks.push({ label: 'LCP', time: metric.value, color: '#F59E0B' })
      if (metric.id === 'tbt' && typeof metric.value === 'number') marks.push({ label: 'TBT', time: metric.value, color: '#EF4444' })
      if (metric.id === 'ttfb' && typeof metric.value === 'number') marks.push({ label: 'TTFB', time: metric.value, color: '#6366F1' })
    }
    return marks.filter(m => m.time > 0).sort((a, b) => a.time - b.time)
  }, [result])

  const maxTime = useMemo(() => {
    if (requests.length === 0) return 1
    // Usar p95 para que un outlier no aplaste todas las barras
    const sorted = [...requests].sort((a, b) => a.endTime - b.endTime)
    const p95Index = Math.floor(sorted.length * 0.95)
    return Math.max(sorted[p95Index]?.endTime ?? 1, 1)
  }, [requests])

  const gridTicks = useMemo(() => {
    if (maxTime <= 1) return []
    const intervals = [50, 100, 200, 250, 500, 1000, 2000, 5000, 10000, 20000, 50000]
    const targetCount = 8
    let interval = intervals[0]
    for (const iv of intervals) {
      if (maxTime / iv <= targetCount) { interval = iv; break }
    }
    const ticks: number[] = []
    for (let t = 0; t <= maxTime; t += interval) {
      ticks.push(t)
    }
    return ticks
  }, [maxTime])

  const fmtTick = (ms: number) => ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`

  const types = useMemo(() => {
    const set = new Set(requests.map(r => TYPE_LABELS[r.resourceType] || 'Other'))
    return ['All', ...Array.from(set)]
  }, [requests])

  const totalSize = useMemo(() => filtered.reduce((s, r) => s + r.transferSize, 0), [filtered])
  const totalDuration = useMemo(() => {
    if (filtered.length === 0) return 0
    const sorted = [...filtered].sort((a, b) => a.endTime - b.endTime)
    const p95Index = Math.floor(sorted.length * 0.95)
    const minStart = Math.min(...filtered.map(r => r.startTime))
    return (sorted[p95Index]?.endTime ?? 0) - minStart
  }, [filtered])

  const handleSort = (field: typeof sortBy) => {
    if (field === sortBy) {
      if (sortDir === 'asc') setSortDir('desc')
      else { setSortBy('default'); setSortDir('asc') } // tercer click resetea
    } else {
      setSortBy(field)
      setSortDir(field === 'size' || field === 'duration' ? 'desc' : 'asc')
    }
    setExpandedRow(null)
  }

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-96 rounded-lg" /></div>
  }

  if (!result || requests.length === 0) {
    return (
      <div className="space-y-4">
        {id && <LeadReportNav auditId={id} domain={result?.domain} />}
        <div className="text-center py-16 text-gray-500">
          <p className="text-lg font-medium">{t('settings.waterfall_empty_title')}</p>
          <p className="text-sm mt-1">{t('settings.waterfall_empty_hint')}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {id && <LeadReportNav auditId={id} domain={result.domain} />}
      <div>
        <h2 className="text-lg font-semibold text-[var(--text-primary)]">{t('settings.waterfall_title')}</h2>
        <div className="flex items-center gap-2 text-sm text-[var(--text-tertiary)] mt-0.5">
          <a href={result.url} target="_blank" rel="noreferrer" className="text-[var(--accent-primary)] hover:underline flex items-center gap-1">
            {result.domain} <ExternalLink className="h-3 w-3" />
          </a>
          <span>&middot;</span>
          <span>{t('settings.waterfall_requests', { count: requests.length })}</span>
          <span>&middot;</span>
          <span>{formatSize(totalSize)}</span>
          <span>&middot;</span>
          <span>{(totalDuration / 1000).toFixed(2)}s</span>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative flex-1 max-w-xs">
          <Filter className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
          <Input value={search} onChange={e => setSearch(e.target.value)} placeholder={t('settings.waterfall_search_placeholder')} className="pl-8 h-8 text-xs" />
        </div>
        <div className="flex gap-1">
          {types.map(type => (
            <button key={type} onClick={() => setFilterType(type)}
              className={`px-2.5 py-1 rounded text-xs font-medium transition-colors cursor-pointer ${filterType === type ? 'text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}
              style={filterType === type ? { backgroundColor: type === 'All' ? '#404040' : (TYPE_COLORS[Object.keys(TYPE_LABELS).find(k => TYPE_LABELS[k] === type) || ''] || '#404040') } : {}}>
              {type === 'All' ? t('settings.waterfall_origin_all') : type}
            </button>
          ))}
        </div>
        <div className="flex gap-1 border-l border-gray-200 pl-2">
          {([['all', t('settings.waterfall_origin_all')], ['local', t('settings.waterfall_origin_local')], ['external', t('settings.waterfall_origin_external')]] as const).map(([v, label]) => (
            <button key={v} onClick={() => setFilterOrigin(v as 'all' | 'local' | 'external')}
              className={`px-2.5 py-1 rounded text-xs font-medium cursor-pointer ${filterOrigin === v ? 'bg-gray-700 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}>
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Escala de tiempo con marcadores de milestones */}
      {gridTicks.length > 0 && (
        <div className="flex items-end">
          <div style={{ minWidth: '240px' }} className="shrink-0" />
          <div className="flex-1 relative h-8">
            {gridTicks.map(t => (
              <span key={t} className="absolute bottom-0 -translate-x-1/2 text-[10px] text-gray-400 tabular-nums" style={{ left: `${(t / maxTime) * 100}%` }}>
                {fmtTick(t)}
              </span>
            ))}
            {milestones.map(m => (
              <span key={m.label} className="absolute top-0 -translate-x-1/2 text-[9px] font-bold tabular-nums" style={{ left: `${Math.min((m.time / maxTime) * 100, 98)}%`, color: m.color }}>
                {m.label} {fmtTick(m.time)}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Tabla */}
      <div className="border border-gray-200 rounded-lg overflow-hidden">
        <div className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wider select-none">
          <SortHeader label="URL" field="url" current={sortBy} dir={sortDir} onSort={handleSort} className="px-3 py-2" />
          <SortHeader label="Status" field="status" current={sortBy} dir={sortDir} onSort={handleSort} className="px-1 py-2" />
          <SortHeader label="Size" field="size" current={sortBy} dir={sortDir} onSort={handleSort} className="px-1 py-2 text-right justify-end" />
          <SortHeader label="Timeline" field="start" current={sortBy} dir={sortDir} onSort={handleSort} className="px-3 py-2" />
        </div>

        <div>
          {filtered.map((req, i) => {
            const barLeft = Math.min((req.startTime / maxTime) * 100, 99)
            const barWidth = Math.min(Math.max(((req.endTime - req.startTime) / maxTime) * 100, 0.5), 100 - barLeft)
            const color = TYPE_COLORS[req.resourceType] || TYPE_COLORS.Other
            const duration = req.endTime - req.startTime
            const isExpanded = expandedRow === i
            const fmtTime = (ms: number) => ms < 1000 ? `${ms.toFixed(0)}ms` : `${(ms / 1000).toFixed(2)}s`

            return (
              <div key={i}>
                <div
                  className={`grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 border-b border-gray-100 text-xs cursor-pointer select-none ${isExpanded ? 'bg-blue-50' : 'hover:bg-gray-50'}`}
                  onClick={() => setExpandedRow(isExpanded ? null : i)}
                >
                  <div className="px-3 py-1.5 flex items-center gap-1.5 min-w-0">
                    <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: color }} />
                    <span className="truncate text-gray-700">{extractFilename(req.url)}</span>
                  </div>
                  <div className="px-1 py-1.5">
                    <span className={req.statusCode >= 400 ? 'text-red-600 font-medium' : 'text-gray-500'}>{req.statusCode || '—'}</span>
                  </div>
                  <div className="px-1 py-1.5 text-right text-gray-500 tabular-nums">{formatSize(req.transferSize)}</div>
                  <div className="py-1 flex items-center">
                    <div className="relative w-full h-5">
                      {gridTicks.map(t => (
                        <div key={t} className="absolute top-0 w-px h-full bg-gray-100" style={{ left: `${(t / maxTime) * 100}%` }} />
                      ))}
                      {milestones.map(m => (
                        <div key={m.label} className="absolute top-0 w-px h-full opacity-30" style={{ left: `${Math.min((m.time / maxTime) * 100, 100)}%`, backgroundColor: m.color }} />
                      ))}
                      <div className="absolute h-full rounded-sm z-[1]" style={{ left: `${barLeft}%`, width: `${barWidth}%`, backgroundColor: color, minWidth: '2px', opacity: 0.85 }} />
                      <span className="absolute text-[10px] font-medium top-0.5 whitespace-nowrap tabular-nums z-[2]" style={{ left: `${Math.min(barLeft + barWidth + 0.5, 88)}%`, color }}>
                        {fmtTime(duration)}
                      </span>
                    </div>
                  </div>
                </div>

                {isExpanded && (
                  <div className="bg-gray-50 border-b border-gray-200 px-4 py-3 text-xs">
                    <div className="mb-2">
                      <a href={req.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline break-all text-[11px]">{req.url}</a>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-1.5 text-gray-600">
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_type')}:</span> <span className="font-medium">{req.resourceType}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_status')}:</span> <span className="font-medium">{req.statusCode}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_protocol')}:</span> <span className="font-medium">{req.protocol || '—'}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_mime')}:</span> <span className="font-medium">{req.mimeType || '—'}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_size')}:</span> <span className="font-medium">{formatSize(req.transferSize)}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_uncompressed')}:</span> <span className="font-medium">{formatSize(req.resourceSize)}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_start')}:</span> <span className="font-medium">{fmtTime(req.startTime)}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_end')}:</span> <span className="font-medium">{fmtTime(req.endTime)}</span></div>
                      <div><span className="text-gray-400">{t('settings.waterfall_detail_duration')}:</span> <span className="font-medium text-gray-900">{fmtTime(duration)}</span></div>
                    </div>
                  </div>
                )}
              </div>
            )
          })}
        </div>

        {/* Footer */}
        <div className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 bg-gray-50 border-t border-gray-200 text-xs font-medium text-gray-600">
          <div className="px-3 py-2">{t('settings.waterfall_footer_requests', { count: filtered.length })}</div>
          <div className="px-1 py-2"></div>
          <div className="px-1 py-2 text-right">{formatSize(totalSize)}</div>
          <div className="px-3 py-2">{(totalDuration / 1000).toFixed(2)}s</div>
        </div>
      </div>

      {/* Leyenda */}
      <div className="flex flex-wrap gap-3 text-xs text-gray-500">
        {Object.entries(TYPE_LABELS).filter(([, v], i, arr) => arr.findIndex(([, v2]) => v2 === v) === i).map(([key, label]) => (
          <div key={key} className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: TYPE_COLORS[key] }} />
            {label}
          </div>
        ))}
      </div>

      {/* Sub-secciones */}
      {resourceBreakdown.length > 0 && <PageDetailsSection data={resourceBreakdown} />}
      <PerformanceDetails lcpElement={lcpElement} clsElements={clsElements} mainThreadWork={mainThreadWork} />
      {cruxData && cruxData.metrics.length > 0 && <CruxSection data={cruxData} />}
      {lighthouseAudits.length > 0 && <StructureAuditsSection audits={lighthouseAudits} />}
    </div>
  )
}
