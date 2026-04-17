import { useEffect, useState, useMemo, useCallback, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, ExternalLink, Filter, Loader2, Microscope } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import type { AuditResult } from '@/types/audit'

interface NetworkRequest {
  url: string
  resourceType: string
  startTime: number
  endTime: number
  transferSize: number
  resourceSize: number
  statusCode: number
  mimeType: string
  protocol: string
}

interface WptRequest extends NetworkRequest {
  dns: number
  connect: number
  ssl: number
  ttfb: number
  download: number
}

interface WptResult {
  status: string
  testId: string
  summary: { loadTime: number; fullyLoaded: number; ttfb: number; bytesIn: number; requests: number }
  waterfall: WptRequest[]
  webpagetestUrl: string
}

const TYPE_COLORS: Record<string, string> = {
  Document: '#4CAF50',
  Stylesheet: '#2196F3',
  Script: '#FFC107',
  Image: '#9C27B0',
  Font: '#E91E63',
  XHR: '#00BCD4',
  Fetch: '#00BCD4',
  Media: '#FF5722',
  Other: '#9E9E9E',
}

const TYPE_LABELS: Record<string, string> = {
  Document: 'HTML',
  Stylesheet: 'CSS',
  Script: 'JS',
  Image: 'Images',
  Font: 'Fonts',
  XHR: 'XHR',
  Fetch: 'XHR',
  Media: 'Media',
  Other: 'Other',
}

function formatSize(bytes: number): string {
  if (bytes === 0) return '0'
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / (1024 * 1024)).toFixed(2) + 'MB'
}

function extractFilename(url: string): string {
  try {
    const u = new URL(url)
    const path = u.pathname
    const file = path.split('/').pop() || path
    return file.length > 45 ? file.substring(0, 42) + '...' : file
  } catch {
    return url.substring(0, 45)
  }
}


export default function WaterfallPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { fetchLeadDetail } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [loading, setLoading] = useState(true)
  const [filterType, setFilterType] = useState('All')
  const [filterOrigin, setFilterOrigin] = useState<'all' | 'local' | 'external'>('all')
  const [search, setSearch] = useState('')
  const [expandedRow, setExpandedRow] = useState<number | null>(null)
  const [sortBy, setSortBy] = useState<'default' | 'url' | 'status' | 'size' | 'duration' | 'start'>('default')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc')
  const [wptLoading, setWptLoading] = useState(false)
  const [wptStatus, setWptStatus] = useState('')
  const [wptResult, setWptResult] = useState<WptResult | null>(null)
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const runDeepAnalysis = useCallback(async () => {
    if (!result) return
    setWptLoading(true)
    setWptStatus('Enviando test...')
    setWptResult(null)
    try {
      const res = await api.post('/admin/webpagetest.php', { url: result.url })
      const testId = res.data?.data?.testId
      if (!testId) throw new Error('No testId')
      setWptStatus('Test enviado. Esperando resultados (30-60s)...')

      // Poll every 5s
      pollRef.current = setInterval(async () => {
        try {
          const poll = await api.get('/admin/webpagetest.php', { params: { testId } })
          const d = poll.data?.data
          if (d?.status === 'completed') {
            if (pollRef.current) clearInterval(pollRef.current)
            setWptResult(d)
            setWptLoading(false)
            setWptStatus('')
            toast.success('Análisis profundo completado')
          } else if (d?.status === 'running') {
            setWptStatus(d.statusText || 'En progreso...')
          }
        } catch {
          if (pollRef.current) clearInterval(pollRef.current)
          setWptLoading(false)
          setWptStatus('')
          toast.error('Error al obtener resultados')
        }
      }, 5000)
    } catch (e: unknown) {
      setWptLoading(false)
      setWptStatus('')
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error || 'Error al iniciar análisis'
      toast.error(msg)
    }
  }, [result])

  useEffect(() => {
    return () => { if (pollRef.current) clearInterval(pollRef.current) }
  }, [])

  const [requests, setRequests] = useState<NetworkRequest[]>([])

  useEffect(() => {
    if (!id) return
    // Load audit result and waterfall data in parallel
    Promise.all([
      fetchLeadDetail(id),
      api.get('/admin/waterfall.php', { params: { id } }).then(r => r.data?.data).catch(() => [])
    ]).then(([audit, waterfallData]: [AuditResult, NetworkRequest[]]) => {
      setResult(audit)
      // Try dedicated waterfall endpoint first, fall back to inline data
      const wf = (waterfallData && waterfallData.length > 0)
        ? waterfallData
        : ((audit as unknown as Record<string, unknown>).waterfall as NetworkRequest[]) || []
      setRequests(wf)
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
    // Sort
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

  // Extract performance milestones from audit result
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
    // Use p95 endTime to prevent one outlier from crushing all bars
    const sorted = [...requests].sort((a, b) => a.endTime - b.endTime)
    const p95Index = Math.floor(sorted.length * 0.95)
    return Math.max(sorted[p95Index]?.endTime ?? 1, 1)
  }, [requests])

  // Calculate grid ticks dynamically based on maxTime
  const gridTicks = useMemo(() => {
    if (maxTime <= 1) return []
    // Choose a nice interval
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
    // Also use p95 for the displayed total
    const sorted = [...filtered].sort((a, b) => a.endTime - b.endTime)
    const p95Index = Math.floor(sorted.length * 0.95)
    const minStart = Math.min(...filtered.map(r => r.startTime))
    return (sorted[p95Index]?.endTime ?? 0) - minStart
  }, [filtered])

  const handleSort = (field: typeof sortBy) => {
    if (field === sortBy) {
      if (sortDir === 'asc') setSortDir('desc')
      else { setSortBy('default'); setSortDir('asc') } // third click resets
    } else {
      setSortBy(field)
      setSortDir(field === 'size' || field === 'duration' ? 'desc' : 'asc') // size/duration default desc
    }
    setExpandedRow(null)
  }

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-96 rounded-lg" /></div>
  }

  if (!result || requests.length === 0) {
    return (
      <div className="space-y-4">
        <Button variant="ghost" size="sm" onClick={() => navigate(-1)}>
          <ArrowLeft className="h-4 w-4" /> Volver
        </Button>
        <div className="text-center py-16 text-gray-500">
          <p className="text-lg font-medium">No hay datos de waterfall disponibles</p>
          <p className="text-sm mt-1">Los datos se generan con la API de Google PageSpeed al ejecutar la auditoría.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-xl font-bold text-gray-900">Waterfall Chart</h1>
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <a href={result.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline flex items-center gap-1">
                {result.domain} <ExternalLink className="h-3 w-3" />
              </a>
              <span>&middot;</span>
              <span>{requests.length} requests</span>
              <span>&middot;</span>
              <span>{formatSize(totalSize)}</span>
              <span>&middot;</span>
              <span>{(totalDuration / 1000).toFixed(2)}s</span>
            </div>
          </div>
        </div>
        <Button variant="outline" size="sm" onClick={runDeepAnalysis} disabled={wptLoading}>
          {wptLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Microscope className="h-4 w-4" strokeWidth={1.5} />}
          {wptLoading ? wptStatus : 'Análisis Profundo'}
        </Button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative flex-1 max-w-xs">
          <Filter className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
          <Input value={search} onChange={e => setSearch(e.target.value)} placeholder="Filtrar por URL..." className="pl-8 h-8 text-xs" />
        </div>
        <div className="flex gap-1">
          {types.map(t => (
            <button key={t} onClick={() => setFilterType(t)}
              className={`px-2.5 py-1 rounded text-xs font-medium transition-colors cursor-pointer ${filterType === t ? 'text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}
              style={filterType === t ? { backgroundColor: t === 'All' ? '#404040' : (TYPE_COLORS[Object.keys(TYPE_LABELS).find(k => TYPE_LABELS[k] === t) || ''] || '#404040') } : {}}>
              {t}
            </button>
          ))}
        </div>
        <div className="flex gap-1 border-l border-gray-200 pl-2">
          {([['all', 'Todos'], ['local', 'Local'], ['external', 'Externo']] as const).map(([v, label]) => (
            <button key={v} onClick={() => setFilterOrigin(v)}
              className={`px-2.5 py-1 rounded text-xs font-medium cursor-pointer ${filterOrigin === v ? 'bg-gray-700 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}>
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Time scale with milestone markers */}
      {gridTicks.length > 0 && (
        <div className="flex items-end">
          <div style={{ minWidth: '240px' }} className="shrink-0" />
          <div className="flex-1 relative h-8">
            {/* Tick labels */}
            {gridTicks.map(t => (
              <span key={t} className="absolute bottom-0 -translate-x-1/2 text-[10px] text-gray-400 tabular-nums" style={{ left: `${(t / maxTime) * 100}%` }}>
                {fmtTick(t)}
              </span>
            ))}
            {/* Milestone labels above ticks */}
            {milestones.map(m => (
              <span key={m.label} className="absolute top-0 -translate-x-1/2 text-[9px] font-bold tabular-nums" style={{ left: `${Math.min((m.time / maxTime) * 100, 98)}%`, color: m.color }}>
                {m.label} {fmtTick(m.time)}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Table */}
      <div className="border border-gray-200 rounded-lg overflow-hidden">
        {/* Table header */}
        <div className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wider select-none">
          <SortHeader label="URL" field="url" current={sortBy} dir={sortDir} onSort={handleSort} className="px-3 py-2" />
          <SortHeader label="Status" field="status" current={sortBy} dir={sortDir} onSort={handleSort} className="px-1 py-2" />
          <SortHeader label="Size" field="size" current={sortBy} dir={sortDir} onSort={handleSort} className="px-1 py-2 text-right justify-end" />
          <SortHeader label="Timeline" field="start" current={sortBy} dir={sortDir} onSort={handleSort} className="px-3 py-2" />
        </div>

        {/* Rows */}
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
                  {/* URL */}
                  <div className="px-3 py-1.5 flex items-center gap-1.5 min-w-0">
                    <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: color }} />
                    <span className="truncate text-gray-700">{extractFilename(req.url)}</span>
                  </div>
                  {/* Status */}
                  <div className="px-1 py-1.5">
                    <span className={req.statusCode >= 400 ? 'text-red-600 font-medium' : 'text-gray-500'}>{req.statusCode || '—'}</span>
                  </div>
                  {/* Size */}
                  <div className="px-1 py-1.5 text-right text-gray-500 tabular-nums">{formatSize(req.transferSize)}</div>
                  {/* Timeline bar with grid */}
                  <div className="py-1 flex items-center">
                    <div className="relative w-full h-5">
                      {/* Grid lines */}
                      {gridTicks.map(t => (
                        <div key={t} className="absolute top-0 w-px h-full bg-gray-100" style={{ left: `${(t / maxTime) * 100}%` }} />
                      ))}
                      {/* Milestone lines */}
                      {milestones.map(m => (
                        <div key={m.label} className="absolute top-0 w-px h-full opacity-30" style={{ left: `${Math.min((m.time / maxTime) * 100, 100)}%`, backgroundColor: m.color }} />
                      ))}
                      {/* Bar */}
                      <div className="absolute h-full rounded-sm z-[1]" style={{ left: `${barLeft}%`, width: `${barWidth}%`, backgroundColor: color, minWidth: '2px', opacity: 0.85 }} />
                      {/* Duration label */}
                      <span className="absolute text-[10px] font-medium top-0.5 whitespace-nowrap tabular-nums z-[2]" style={{ left: `${Math.min(barLeft + barWidth + 0.5, 88)}%`, color }}>
                        {fmtTime(duration)}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Expanded detail panel */}
                {isExpanded && (
                  <div className="bg-gray-50 border-b border-gray-200 px-4 py-3 text-xs">
                    <div className="mb-2">
                      <a href={req.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline break-all text-[11px]">{req.url}</a>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-1.5 text-gray-600">
                      <div><span className="text-gray-400">Tipo:</span> <span className="font-medium">{req.resourceType}</span></div>
                      <div><span className="text-gray-400">Status:</span> <span className="font-medium">{req.statusCode}</span></div>
                      <div><span className="text-gray-400">Protocolo:</span> <span className="font-medium">{req.protocol || '—'}</span></div>
                      <div><span className="text-gray-400">MIME:</span> <span className="font-medium">{req.mimeType || '—'}</span></div>
                      <div><span className="text-gray-400">Tamaño:</span> <span className="font-medium">{formatSize(req.transferSize)}</span></div>
                      <div><span className="text-gray-400">Sin comprimir:</span> <span className="font-medium">{formatSize(req.resourceSize)}</span></div>
                      <div><span className="text-gray-400">Inicio:</span> <span className="font-medium">{fmtTime(req.startTime)}</span></div>
                      <div><span className="text-gray-400">Fin:</span> <span className="font-medium">{fmtTime(req.endTime)}</span></div>
                      <div><span className="text-gray-400">Duración:</span> <span className="font-medium text-gray-900">{fmtTime(duration)}</span></div>
                    </div>
                  </div>
                )}
              </div>
            )
          })}
        </div>

        {/* Footer */}
        <div className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 bg-gray-50 border-t border-gray-200 text-xs font-medium text-gray-600">
          <div className="px-3 py-2">{filtered.length} Requests</div>
          <div className="px-1 py-2"></div>
          <div className="px-1 py-2 text-right">{formatSize(totalSize)}</div>
          <div className="px-3 py-2">{(totalDuration / 1000).toFixed(2)}s</div>
        </div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-3 text-xs text-gray-500">
        {Object.entries(TYPE_LABELS).filter(([, v], i, arr) => arr.findIndex(([, v2]) => v2 === v) === i).map(([key, label]) => (
          <div key={key} className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: TYPE_COLORS[key] }} />
            {label}
          </div>
        ))}
      </div>

      {/* WebPageTest Deep Analysis Results */}
      {wptResult && <DeepAnalysisResults data={wptResult} />}
    </div>
  )
}

/* === Deep Analysis Results (WebPageTest) === */

const TIMING_COLORS = {
  dns: '#8BC34A',
  connect: '#FF9800',
  ssl: '#9C27B0',
  ttfb: '#2196F3',
  download: '#4CAF50',
}

function DeepAnalysisResults({ data }: { data: WptResult }) {
  const [dFilterType, setDFilterType] = useState('All')
  const [dSearch, setDSearch] = useState('')

  const filtered = useMemo(() => {
    let items = data.waterfall
    if (dFilterType !== 'All') items = items.filter(r => (TYPE_LABELS[r.resourceType] || 'Other') === dFilterType)
    if (dSearch) { const q = dSearch.toLowerCase(); items = items.filter(r => r.url.toLowerCase().includes(q)) }
    return items
  }, [data.waterfall, dFilterType, dSearch])

  const maxTime = Math.max(...data.waterfall.map(r => r.endTime), 1)
  const types = ['All', ...Array.from(new Set(data.waterfall.map(r => TYPE_LABELS[r.resourceType] || 'Other')))]

  return (
    <div className="mt-8 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-gray-900">Análisis Profundo (WebPageTest)</h2>
          <div className="flex flex-wrap gap-4 text-xs text-gray-500 mt-1">
            <span>TTFB: <b>{data.summary.ttfb}ms</b></span>
            <span>Load: <b>{(data.summary.loadTime / 1000).toFixed(2)}s</b></span>
            <span>Fully Loaded: <b>{(data.summary.fullyLoaded / 1000).toFixed(2)}s</b></span>
            <span>Requests: <b>{data.summary.requests}</b></span>
            <span>Size: <b>{formatSize(data.summary.bytesIn)}</b></span>
          </div>
        </div>
        <a href={data.webpagetestUrl} target="_blank" rel="noreferrer">
          <Button variant="ghost" size="sm" className="text-blue-600 text-xs">
            <ExternalLink className="h-3.5 w-3.5" /> Ver en WebPageTest
          </Button>
        </a>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative flex-1 max-w-xs">
          <Filter className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
          <Input value={dSearch} onChange={e => setDSearch(e.target.value)} placeholder="Filtrar..." className="pl-8 h-8 text-xs" />
        </div>
        <div className="flex gap-1">
          {types.map(t => (
            <button key={t} onClick={() => setDFilterType(t)}
              className={`px-2.5 py-1 rounded text-xs font-medium transition-colors cursor-pointer ${dFilterType === t ? 'text-white bg-gray-700' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}>
              {t}
            </button>
          ))}
        </div>
      </div>

      {/* Timing legend */}
      <div className="flex gap-4 text-[10px] text-gray-500">
        {Object.entries(TIMING_COLORS).map(([k, c]) => (
          <div key={k} className="flex items-center gap-1">
            <span className="w-3 h-2 rounded-sm" style={{ backgroundColor: c }} />
            {k.toUpperCase()}
          </div>
        ))}
      </div>

      {/* Table with detailed timing */}
      <div className="border border-gray-200 rounded-lg overflow-hidden">
        <div className="grid grid-cols-[minmax(180px,2fr)_55px_65px_1fr] gap-0 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wider">
          <div className="px-3 py-2">URL</div>
          <div className="px-2 py-2">Status</div>
          <div className="px-2 py-2 text-right">Size</div>
          <div className="px-3 py-2">Timeline (DNS / Connect / SSL / TTFB / Download)</div>
        </div>

        <div>
          {filtered.map((req, i) => {
            const barLeft = (req.startTime / maxTime) * 100
            const totalDur = req.endTime - req.startTime
            const segments = [
              { key: 'dns', ms: req.dns, color: TIMING_COLORS.dns },
              { key: 'connect', ms: req.connect, color: TIMING_COLORS.connect },
              { key: 'ssl', ms: req.ssl, color: TIMING_COLORS.ssl },
              { key: 'ttfb', ms: req.ttfb, color: TIMING_COLORS.ttfb },
              { key: 'download', ms: req.download, color: TIMING_COLORS.download },
            ].filter(s => s.ms > 0)

            return (
              <div key={i} className="grid grid-cols-[minmax(180px,2fr)_55px_65px_1fr] gap-0 border-b border-gray-100 hover:bg-blue-50/30 text-xs group"
                title={`${req.url}\nDNS: ${req.dns}ms · Connect: ${req.connect}ms · SSL: ${req.ssl}ms · TTFB: ${req.ttfb}ms · Download: ${req.download}ms`}>
                <div className="px-3 py-1.5 flex items-center gap-1.5 min-w-0">
                  <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: TYPE_COLORS[req.resourceType] || TYPE_COLORS.Other }} />
                  <span className="truncate text-gray-700">{extractFilename(req.url)}</span>
                </div>
                <div className="px-2 py-1.5">
                  <span className={req.statusCode >= 400 ? 'text-red-600' : 'text-gray-500'}>{req.statusCode || '—'}</span>
                </div>
                <div className="px-2 py-1.5 text-right text-gray-500 tabular-nums">{formatSize(req.transferSize)}</div>
                <div className="px-3 py-1.5 flex items-center">
                  <div className="relative w-full h-4">
                    {/* Stacked timing segments */}
                    {segments.length > 0 ? (
                      <div className="absolute h-full flex" style={{ left: `${barLeft}%` }}>
                        {segments.map(s => (
                          <div key={s.key} className="h-full opacity-80 group-hover:opacity-100 first:rounded-l-sm last:rounded-r-sm"
                            style={{ width: `${Math.max((s.ms / maxTime) * 100, 0.3)}vw`, maxWidth: `${(s.ms / maxTime) * 100}%`, backgroundColor: s.color, minWidth: '1px' }} />
                        ))}
                      </div>
                    ) : (
                      <div className="absolute h-full rounded-sm opacity-80" style={{ left: `${barLeft}%`, width: `${Math.max((totalDur / maxTime) * 100, 0.5)}%`, backgroundColor: TYPE_COLORS[req.resourceType] || '#9E9E9E', minWidth: '2px' }} />
                    )}
                    <span className="absolute text-[10px] text-gray-400 top-0.5 hidden group-hover:inline" style={{ left: `${barLeft + Math.max((totalDur / maxTime) * 100, 1) + 0.5}%` }}>
                      {totalDur < 1000 ? `${totalDur.toFixed(0)}ms` : `${(totalDur / 1000).toFixed(2)}s`}
                    </span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}

function SortHeader({ label, field, current, dir, onSort, className = '' }: {
  label: string
  field: string
  current: string
  dir: 'asc' | 'desc'
  onSort: (f: 'default' | 'url' | 'status' | 'size' | 'duration' | 'start') => void
  className?: string
}) {
  const active = current === field
  return (
    <div
      className={`flex items-center gap-1 cursor-pointer hover:text-gray-700 ${active ? 'text-gray-700' : ''} ${className}`}
      onClick={() => onSort(field as 'url' | 'status' | 'size' | 'duration' | 'start')}
    >
      {label}
      {active && <span className="text-[10px]">{dir === 'asc' ? '▲' : '▼'}</span>}
    </div>
  )
}
