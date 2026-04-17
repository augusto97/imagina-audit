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
  const [search, setSearch] = useState('')
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

  const filtered = useMemo(() => {
    let items = requests
    if (filterType !== 'All') {
      items = items.filter(r => (TYPE_LABELS[r.resourceType] || 'Other') === filterType)
    }
    if (search) {
      const q = search.toLowerCase()
      items = items.filter(r => r.url.toLowerCase().includes(q))
    }
    return items
  }, [requests, filterType, search])

  const maxTime = useMemo(() => {
    if (requests.length === 0) return 1
    // Use p95 endTime to prevent one outlier from crushing all bars
    const sorted = [...requests].sort((a, b) => a.endTime - b.endTime)
    const p95Index = Math.floor(sorted.length * 0.95)
    return Math.max(sorted[p95Index]?.endTime ?? 1, 1)
  }, [requests])

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
            <button
              key={t}
              onClick={() => setFilterType(t)}
              className={`px-2.5 py-1 rounded text-xs font-medium transition-colors cursor-pointer ${filterType === t ? 'text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'}`}
              style={filterType === t ? { backgroundColor: t === 'All' ? '#404040' : (TYPE_COLORS[Object.keys(TYPE_LABELS).find(k => TYPE_LABELS[k] === t) || ''] || '#404040') } : {}}
            >
              {t}
            </button>
          ))}
        </div>
      </div>

      {/* Time scale header */}
      {maxTime > 1 && (
        <div className="flex items-end px-1">
          <div style={{ width: '280px' }} className="shrink-0" />
          <div className="flex-1 flex justify-between text-[10px] text-gray-400 border-b border-gray-200 pb-1">
            {[0, 0.25, 0.5, 0.75, 1].map(pct => (
              <span key={pct}>{(maxTime * pct / 1000).toFixed(1)}s</span>
            ))}
          </div>
        </div>
      )}

      {/* Table */}
      <div className="border border-gray-200 rounded-lg overflow-hidden">
        {/* Table header */}
        <div className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wider">
          <div className="px-3 py-2">URL</div>
          <div className="px-1 py-2">Status</div>
          <div className="px-1 py-2 text-right">Size</div>
          <div className="px-3 py-2">Timeline</div>
        </div>

        {/* Rows */}
        <div className="max-h-[70vh] overflow-y-auto">
          {filtered.map((req, i) => {
            const barLeft = Math.min((req.startTime / maxTime) * 100, 99)
            const barWidth = Math.min(Math.max(((req.endTime - req.startTime) / maxTime) * 100, 0.5), 100 - barLeft)
            const color = TYPE_COLORS[req.resourceType] || TYPE_COLORS.Other
            const duration = req.endTime - req.startTime

            return (
              <div
                key={i}
                className="grid grid-cols-[minmax(140px,1fr)_45px_55px_3fr] gap-0 border-b border-gray-100 hover:bg-blue-50/30 text-xs group cursor-default"
              >
                {/* URL */}
                <div className="px-3 py-1.5 flex items-center gap-1.5 min-w-0" title={req.url}>
                  <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: color }} />
                  <span className="truncate text-gray-700">{extractFilename(req.url)}</span>
                </div>

                {/* Status */}
                <div className="px-1 py-1.5 flex items-center">
                  <span className={`${req.statusCode >= 400 ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                    {req.statusCode || '—'}
                  </span>
                </div>

                {/* Size */}
                <div className="px-1 py-1.5 text-right text-gray-500 tabular-nums">
                  {formatSize(req.transferSize)}
                </div>

                {/* Timeline bar */}
                <div className="px-2 py-1.5 flex items-center">
                  <div className="relative w-full h-5"
                    title={`${req.url}\n${req.resourceType} · ${req.statusCode} · ${formatSize(req.transferSize)}\nStart: ${(req.startTime / 1000).toFixed(2)}s\nDuration: ${duration < 1000 ? duration.toFixed(0) + 'ms' : (duration / 1000).toFixed(2) + 's'}\nProtocol: ${req.protocol || '—'}`}
                  >
                    <div
                      className="absolute h-full rounded-sm opacity-80 group-hover:opacity-100 transition-opacity"
                      style={{
                        left: `${barLeft}%`,
                        width: `${barWidth}%`,
                        backgroundColor: color,
                        minWidth: '2px',
                      }}
                    />
                    <span
                      className="absolute text-[10px] text-gray-400 top-0.5 hidden group-hover:inline"
                      style={{ left: `${barLeft + barWidth + 0.5}%` }}
                    >
                      {duration < 1000 ? `${duration.toFixed(0)}ms` : `${(duration / 1000).toFixed(2)}s`}
                    </span>
                  </div>
                </div>
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

        <div className="max-h-[70vh] overflow-y-auto">
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
