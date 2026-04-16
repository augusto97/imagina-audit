import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search, Eye, MessageCircle, Trash2, Copy, ChevronLeft, ChevronRight, SearchX, Download, FileText } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'

interface Lead {
  id: string; url: string; domain: string; leadName: string | null
  leadEmail: string | null; leadWhatsapp: string | null; globalScore: number
  globalLevel: string; createdAt: string; hasContactInfo: boolean
}

function ScorePill({ score, level }: { score: number; level: string }) {
  const colors: Record<string, string> = {
    critical: 'bg-red-100 text-red-700 ring-red-200',
    warning: 'bg-amber-100 text-amber-700 ring-amber-200',
    good: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    excellent: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
  }
  return (
    <span className={`inline-flex items-center justify-center min-w-[40px] rounded-full px-2.5 py-1 text-xs font-bold ring-1 ${colors[level] || 'bg-gray-100 text-gray-600 ring-gray-200'}`}>
      {score}
    </span>
  )
}

export default function LeadsTable() {
  const navigate = useNavigate()
  const { fetchLeads, deleteLead } = useAdmin()
  const [leads, setLeads] = useState<Lead[]>([])
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(0)
  const [page, setPage] = useState(1)
  const [filter, setFilter] = useState('all')
  const [sort, setSort] = useState('date_desc')
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<string | null>(null)

  const loadLeads = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchLeads({ page, limit: 20, filter, sort, search })
      setLeads(data.leads)
      setTotal(data.total)
      setTotalPages(data.totalPages)
    } catch { /* handled */ }
    setLoading(false)
  }, [fetchLeads, page, filter, sort, search])

  useEffect(() => { loadLeads() }, [loadLeads])

  const [searchInput, setSearchInput] = useState('')
  useEffect(() => {
    const t = setTimeout(() => { setSearch(searchInput); setPage(1) }, 500)
    return () => clearTimeout(t)
  }, [searchInput])

  const handleDelete = async (id: string) => {
    try {
      await deleteLead(id)
      toast.success('Auditoría eliminada')
      setDeleteId(null)
      loadLeads()
    } catch { toast.error('Error al eliminar') }
  }

  const copyEmail = (email: string) => {
    navigator.clipboard.writeText(email)
    toast.success('Email copiado')
  }

  const [exporting, setExporting] = useState(false)
  const exportCsv = async () => {
    setExporting(true)
    try {
      const res = await api.get('/admin/export-leads.php', { params: { filter, search }, responseType: 'blob' })
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
      <h1 className="text-2xl font-bold text-[var(--text-primary)]">Leads y Auditorías</h1>

      {/* Filters bar */}
      <div className="flex flex-wrap items-center gap-2">
        <Select value={filter} onValueChange={(v) => { setFilter(v); setPage(1) }}>
          <SelectTrigger className="w-[150px]"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem value="with_contact">Con contacto</SelectItem>
            <SelectItem value="critical">Score crítico</SelectItem>
            <SelectItem value="warning">Score bajo</SelectItem>
            <SelectItem value="this_week">Esta semana</SelectItem>
            <SelectItem value="this_month">Este mes</SelectItem>
          </SelectContent>
        </Select>

        <Select value={sort} onValueChange={(v) => { setSort(v); setPage(1) }}>
          <SelectTrigger className="w-[160px]"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="date_desc">Más recientes</SelectItem>
            <SelectItem value="date_asc">Más antiguos</SelectItem>
            <SelectItem value="score_asc">Peor score</SelectItem>
            <SelectItem value="score_desc">Mejor score</SelectItem>
          </SelectContent>
        </Select>

        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
          <Input value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Buscar por dominio, nombre o email..." className="pl-9" />
        </div>

        <Button variant="outline" size="sm" onClick={exportCsv} disabled={exporting}>
          <Download className="h-4 w-4" strokeWidth={1.5} />
          <span className="hidden sm:inline">{exporting ? 'Exportando...' : 'CSV'}</span>
        </Button>
      </div>

      {/* Table */}
      <Card className="py-0 overflow-hidden">
        <CardContent className="p-0">
          {loading ? (
            <div className="p-6 space-y-3">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-11 rounded-lg" />)}</div>
          ) : leads.length === 0 ? (
            <div className="flex flex-col items-center gap-3 py-16 text-[var(--text-tertiary)]">
              <SearchX className="h-10 w-10" strokeWidth={1} />
              <p className="text-sm">No se encontraron resultados</p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead>Fecha</TableHead>
                  <TableHead>Dominio</TableHead>
                  <TableHead className="hidden md:table-cell">Nombre</TableHead>
                  <TableHead className="hidden lg:table-cell">Email</TableHead>
                  <TableHead className="hidden lg:table-cell">WhatsApp</TableHead>
                  <TableHead>Score</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {leads.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="text-xs text-[var(--text-tertiary)] whitespace-nowrap">
                      {new Date(l.createdAt).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: '2-digit' })}
                    </TableCell>
                    <TableCell className="font-medium">
                      <a href={l.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">{l.domain}</a>
                    </TableCell>
                    <TableCell className="text-[var(--text-secondary)] hidden md:table-cell">{l.leadName || '—'}</TableCell>
                    <TableCell className="hidden lg:table-cell">
                      {l.leadEmail ? (
                        <button onClick={() => copyEmail(l.leadEmail!)} className="text-blue-600 hover:underline text-xs truncate max-w-[150px] block cursor-pointer">{l.leadEmail}</button>
                      ) : <span className="text-[var(--text-tertiary)]">—</span>}
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                      {l.leadWhatsapp ? (
                        <a href={`https://wa.me/${l.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer" className="text-emerald-500 hover:underline text-xs">{l.leadWhatsapp}</a>
                      ) : <span className="text-[var(--text-tertiary)]">—</span>}
                    </TableCell>
                    <TableCell>
                      <ScorePill score={l.globalScore} level={l.globalLevel} />
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-0.5">
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <a href={`/results/${l.id}`} target="_blank" rel="noreferrer"><Button variant="ghost" size="icon" className="h-8 w-8"><Eye className="h-4 w-4" strokeWidth={1.5} /></Button></a>
                          </TooltipTrigger>
                          <TooltipContent>Ver informe</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8 text-blue-600" onClick={() => navigate(`/admin/leads/${l.id}/report`)}><FileText className="h-4 w-4" strokeWidth={1.5} /></Button>
                          </TooltipTrigger>
                          <TooltipContent>Reporte técnico</TooltipContent>
                        </Tooltip>
                        {l.leadEmail && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => copyEmail(l.leadEmail!)}><Copy className="h-4 w-4" strokeWidth={1.5} /></Button>
                            </TooltipTrigger>
                            <TooltipContent>Copiar email</TooltipContent>
                          </Tooltip>
                        )}
                        {l.leadWhatsapp && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <a href={`https://wa.me/${l.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer"><Button variant="ghost" size="icon" className="h-8 w-8 text-emerald-500"><MessageCircle className="h-4 w-4" strokeWidth={1.5} /></Button></a>
                            </TooltipTrigger>
                            <TooltipContent>WhatsApp</TooltipContent>
                          </Tooltip>
                        )}
                        {deleteId === l.id ? (
                          <div className="flex gap-1">
                            <Button variant="destructive" size="sm" className="h-8 text-xs" onClick={() => handleDelete(l.id)}>Confirmar</Button>
                            <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => setDeleteId(null)}>Cancelar</Button>
                          </div>
                        ) : (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8 text-red-400 hover:text-red-600" onClick={() => setDeleteId(l.id)}><Trash2 className="h-4 w-4" strokeWidth={1.5} /></Button>
                            </TooltipTrigger>
                            <TooltipContent>Eliminar</TooltipContent>
                          </Tooltip>
                        )}
                      </div>
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
