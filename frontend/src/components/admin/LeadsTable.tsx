import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search, ChevronLeft, ChevronRight, SearchX, Download, Copy } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import { LeadsSummaryTiles } from './leads/LeadsSummaryTiles'
import { DomainCell } from './leads/DomainCell'
import { LeadActionsCell } from './leads/LeadActionsCell'
import { ScorePill } from './leads/ScorePill'
import type { Lead, LeadsSummary } from '@/types/lead'

type MainFilter = 'all' | 'with_contact' | 'critical' | 'warning' | 'this_week' | 'this_month'
type Sort = 'date_desc' | 'date_asc' | 'score_asc' | 'score_desc' | 'domain_asc'
type Dimensional = 'any' | 'yes' | 'no'

/**
 * Tabla de leads completa. Composición:
 *   - SummaryTiles (7 contadores globales, clickeables para filtrar)
 *   - Controls row (search + sort + CSV)
 *   - Tabla con DomainCell + ScorePill + LeadActionsCell
 *   - Paginación
 *
 * El click en una fila abre el detalle del lead (los action icons hacen
 * stopPropagation). El filtro activo se sincroniza con los tiles
 * (un solo filtro activo a la vez, para que sea fácil entender el estado).
 */
export default function LeadsTable() {
  const navigate = useNavigate()
  const { fetchLeads, deleteLead, pinAudit } = useAdmin()
  const [leads, setLeads] = useState<Lead[]>([])
  const [summary, setSummary] = useState<LeadsSummary | null>(null)
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(0)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)

  const [mainFilter, setMainFilter] = useState<MainFilter>('all')
  const [filterWp, setFilterWp] = useState<Dimensional>('any')
  const [filterSnap, setFilterSnap] = useState<Dimensional>('any')
  const [filterPinned, setFilterPinned] = useState<Dimensional>('any')
  const [sort, setSort] = useState<Sort>('date_desc')

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  useEffect(() => {
    const t = setTimeout(() => { setSearch(searchInput); setPage(1) }, 500)
    return () => clearTimeout(t)
  }, [searchInput])

  const loadLeads = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchLeads({
        page, limit: 20,
        filter: mainFilter,
        sort, search,
        wp: filterWp, snapshot: filterSnap, pinned: filterPinned,
      })
      setLeads(data.leads)
      setTotal(data.total)
      setTotalPages(data.totalPages)
      setSummary(data.summary || null)
    } catch { /* handled */ }
    setLoading(false)
  }, [fetchLeads, page, mainFilter, sort, search, filterWp, filterSnap, filterPinned])

  useEffect(() => { loadLeads() }, [loadLeads])

  // ─── Handlers de acciones de fila ────────────────────────────────
  const handleOpen = useCallback((lead: Lead) => navigate(`/admin/leads/${lead.id}`), [navigate])

  const handleTogglePin = useCallback(async (lead: Lead) => {
    try {
      await pinAudit(lead.id, !lead.isPinned)
      toast.success(lead.isPinned ? 'Protección retirada' : 'Informe protegido')
      loadLeads()
    } catch { toast.error('Error al cambiar protección') }
  }, [pinAudit, loadLeads])

  const handleDelete = useCallback(async (lead: Lead) => {
    try {
      await deleteLead(lead.id)
      toast.success('Auditoría eliminada')
      loadLeads()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number; data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error || 'Error al eliminar')
    }
  }, [deleteLead, loadLeads])

  const copyEmail = (email: string, e: React.MouseEvent) => {
    e.stopPropagation()
    navigator.clipboard.writeText(email)
    toast.success('Email copiado')
  }

  // ─── Filter derivado (qué tile está activo) ─────────────────────
  const activeFilter = filterWp === 'yes' ? 'wp_yes'
    : filterSnap === 'yes' ? 'snap_yes'
    : filterPinned === 'yes' ? 'pin_yes'
    : mainFilter

  const applyTileFilter = (key: string) => {
    // Reset todos los filtros al cambiar — un solo tile activo a la vez
    setFilterWp('any'); setFilterSnap('any'); setFilterPinned('any')
    setPage(1)
    if (key === 'wp_yes') { setMainFilter('all'); setFilterWp('yes') }
    else if (key === 'snap_yes') { setMainFilter('all'); setFilterSnap('yes') }
    else if (key === 'pin_yes') { setMainFilter('all'); setFilterPinned('yes') }
    else setMainFilter(key as MainFilter)
  }

  // ─── CSV Export ─────────────────────────────────────────────────
  const [exporting, setExporting] = useState(false)
  const exportCsv = async () => {
    setExporting(true)
    try {
      const res = await api.get('/admin/export-leads.php', {
        params: { filter: mainFilter, search, wp: filterWp, snapshot: filterSnap, pinned: filterPinned },
        responseType: 'blob',
      })
      const url = URL.createObjectURL(res.data)
      const a = document.createElement('a')
      a.href = url
      a.download = `imagina-audit-leads-${new Date().toISOString().slice(0, 10)}.csv`
      a.click()
      URL.revokeObjectURL(url)
      toast.success('CSV exportado')
    } catch { toast.error('Error al exportar') }
    setExporting(false)
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Leads y Auditorías</h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">
          Click en una fila para abrir el lead. Usa los tiles para filtrar rápido por dimensión.
        </p>
      </div>

      {/* Summary tiles (clickeables) */}
      {summary && (
        <LeadsSummaryTiles
          summary={summary}
          activeFilter={activeFilter}
          onFilter={applyTileFilter}
        />
      )}

      {/* Controls */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-[240px] flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
          <Input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Buscar por dominio, nombre, email o empresa..."
            className="pl-9"
          />
        </div>
        <Select value={sort} onValueChange={(v) => { setSort(v as Sort); setPage(1) }}>
          <SelectTrigger className="w-[170px]"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="date_desc">Más recientes</SelectItem>
            <SelectItem value="date_asc">Más antiguos</SelectItem>
            <SelectItem value="score_asc">Peor score</SelectItem>
            <SelectItem value="score_desc">Mejor score</SelectItem>
            <SelectItem value="domain_asc">Dominio A-Z</SelectItem>
          </SelectContent>
        </Select>
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={exporting}>
          <Download className="h-4 w-4" strokeWidth={1.5} />
          <span className="hidden sm:inline">{exporting ? 'Exportando...' : 'CSV'}</span>
        </Button>
      </div>

      {/* Table */}
      <Card className="overflow-hidden py-0">
        <CardContent className="p-0">
          {loading ? (
            <div className="space-y-3 p-6">
              {[...Array(5)].map((_, i) => <Skeleton key={i} className="h-12 rounded-lg" />)}
            </div>
          ) : leads.length === 0 ? (
            <div className="flex flex-col items-center gap-3 py-16 text-[var(--text-tertiary)]">
              <SearchX className="h-10 w-10" strokeWidth={1} />
              <p className="text-sm">No se encontraron resultados</p>
              {(mainFilter !== 'all' || filterWp !== 'any' || filterSnap !== 'any' || filterPinned !== 'any' || search) && (
                <Button variant="outline" size="sm" onClick={() => {
                  setMainFilter('all'); setFilterWp('any'); setFilterSnap('any'); setFilterPinned('any')
                  setSearchInput(''); setSearch(''); setPage(1)
                }}>
                  Limpiar filtros
                </Button>
              )}
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="w-[110px]">Fecha</TableHead>
                  <TableHead>Dominio</TableHead>
                  <TableHead className="hidden lg:table-cell">Email</TableHead>
                  <TableHead className="hidden lg:table-cell">WhatsApp</TableHead>
                  <TableHead className="w-[80px]">Score</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {leads.map((lead) => (
                  <TableRow
                    key={lead.id}
                    onClick={() => handleOpen(lead)}
                    className="cursor-pointer hover:bg-[var(--bg-tertiary)]/40"
                  >
                    <TableCell className="whitespace-nowrap text-xs text-[var(--text-tertiary)]">
                      <div>
                        <span>{new Date(lead.createdAt).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: '2-digit' })}</span>
                        <span className="block text-[10px] text-gray-400">
                          {new Date(lead.createdAt).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <DomainCell lead={lead} />
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                      {lead.leadEmail ? (
                        <button
                          onClick={(e) => copyEmail(lead.leadEmail!, e)}
                          className="group inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                          title="Copiar email"
                        >
                          <span className="max-w-[180px] truncate">{lead.leadEmail}</span>
                          <Copy className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                        </button>
                      ) : <span className="text-[var(--text-tertiary)]">—</span>}
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                      {lead.leadWhatsapp ? (
                        <a
                          href={`https://wa.me/${lead.leadWhatsapp.replace(/[^0-9]/g, '')}`}
                          target="_blank"
                          rel="noreferrer"
                          onClick={(e) => e.stopPropagation()}
                          className="text-xs text-emerald-500 hover:underline"
                        >
                          {lead.leadWhatsapp}
                        </a>
                      ) : <span className="text-[var(--text-tertiary)]">—</span>}
                    </TableCell>
                    <TableCell>
                      <ScorePill score={lead.globalScore} level={lead.globalLevel} />
                    </TableCell>
                    <TableCell className="text-right">
                      <LeadActionsCell
                        lead={lead}
                        onOpen={handleOpen}
                        onTogglePin={handleTogglePin}
                        onDelete={handleDelete}
                      />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--text-tertiary)]">
            {(page - 1) * 20 + 1}–{Math.min(page * 20, total)} de {total}
          </span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
              <ChevronLeft className="h-4 w-4" /> Anterior
            </Button>
            <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage(page + 1)}>
              Siguiente <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
