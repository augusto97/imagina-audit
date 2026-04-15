import { useEffect, useState, useCallback } from 'react'
import { motion } from 'framer-motion'
import { Search, Eye, MessageCircle, Trash2, Copy, ChevronLeft, ChevronRight, SearchX } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { getLevelClassName } from '@/lib/utils'

interface Lead {
  id: string; url: string; domain: string; leadName: string | null
  leadEmail: string | null; leadWhatsapp: string | null; globalScore: number
  globalLevel: string; createdAt: string; hasContactInfo: boolean
}

export default function LeadsTable() {
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
    } catch { /* manejado por useAdmin */ }
    setLoading(false)
  }, [fetchLeads, page, filter, sort, search])

  useEffect(() => { loadLeads() }, [loadLeads])

  // Debounce de búsqueda
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

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Leads y Auditorías</h1>
      </div>

      {/* Filtros */}
      <div className="flex flex-wrap items-center gap-3">
        <select value={filter} onChange={(e) => { setFilter(e.target.value); setPage(1) }}
          className="h-10 rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm text-[var(--text-primary)]">
          <option value="all">Todos</option>
          <option value="with_contact">Con contacto</option>
          <option value="critical">Score crítico</option>
          <option value="warning">Score bajo</option>
          <option value="this_week">Esta semana</option>
          <option value="this_month">Este mes</option>
        </select>
        <select value={sort} onChange={(e) => { setSort(e.target.value); setPage(1) }}
          className="h-10 rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm text-[var(--text-primary)]">
          <option value="date_desc">Más recientes</option>
          <option value="date_asc">Más antiguos</option>
          <option value="score_asc">Peor score</option>
          <option value="score_desc">Mejor score</option>
        </select>
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
          <Input value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Buscar por dominio, nombre o email..." className="pl-10" />
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          {loading ? (
            <div className="p-6 space-y-3">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-12 rounded-lg" />)}</div>
          ) : leads.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-12 text-[var(--text-tertiary)]">
              <SearchX className="h-10 w-10" strokeWidth={1} />
              <p className="text-sm">No se encontraron resultados</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-default)] text-left text-xs text-[var(--text-tertiary)]">
                    <th className="px-4 py-3">Fecha</th>
                    <th className="px-4 py-3">Dominio</th>
                    <th className="px-4 py-3">Nombre</th>
                    <th className="px-4 py-3">Email</th>
                    <th className="px-4 py-3">WhatsApp</th>
                    <th className="px-4 py-3">Score</th>
                    <th className="px-4 py-3">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {leads.map((l) => (
                    <motion.tr key={l.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="border-b border-[var(--border-default)] last:border-0 hover:bg-[var(--bg-tertiary)]/50">
                      <td className="px-4 py-2.5 text-xs text-[var(--text-tertiary)] whitespace-nowrap">
                        {new Date(l.createdAt).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: '2-digit' })}
                      </td>
                      <td className="px-4 py-2.5 font-medium">
                        <a href={l.url} target="_blank" rel="noreferrer" className="text-[var(--accent-primary)] hover:underline">{l.domain}</a>
                      </td>
                      <td className="px-4 py-2.5 text-[var(--text-secondary)]">{l.leadName || '—'}</td>
                      <td className="px-4 py-2.5">{l.leadEmail ? <button onClick={() => copyEmail(l.leadEmail!)} className="text-[var(--accent-primary)] hover:underline text-xs truncate max-w-[150px] block cursor-pointer">{l.leadEmail}</button> : <span className="text-[var(--text-tertiary)]">—</span>}</td>
                      <td className="px-4 py-2.5">{l.leadWhatsapp ? <a href={`https://wa.me/${l.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer" className="text-emerald-500 hover:underline text-xs">{l.leadWhatsapp}</a> : <span className="text-[var(--text-tertiary)]">—</span>}</td>
                      <td className="px-4 py-2.5"><span className={`font-bold ${getLevelClassName(l.globalLevel)}`}>{l.globalScore}</span></td>
                      <td className="px-4 py-2.5">
                        <div className="flex gap-1">
                          <a href={`/results/${l.id}`} target="_blank" rel="noreferrer"><Button variant="ghost" size="icon" className="h-7 w-7"><Eye className="h-3.5 w-3.5" strokeWidth={1.5} /></Button></a>
                          {l.leadEmail && <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => copyEmail(l.leadEmail!)}><Copy className="h-3.5 w-3.5" strokeWidth={1.5} /></Button>}
                          {l.leadWhatsapp && <a href={`https://wa.me/${l.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer"><Button variant="ghost" size="icon" className="h-7 w-7 text-emerald-500"><MessageCircle className="h-3.5 w-3.5" strokeWidth={1.5} /></Button></a>}
                          {deleteId === l.id ? (
                            <div className="flex gap-1">
                              <Button variant="destructive" size="sm" className="h-7 text-xs" onClick={() => handleDelete(l.id)}>Confirmar</Button>
                              <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => setDeleteId(null)}>Cancelar</Button>
                            </div>
                          ) : (
                            <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-600" onClick={() => setDeleteId(l.id)}><Trash2 className="h-3.5 w-3.5" strokeWidth={1.5} /></Button>
                          )}
                        </div>
                      </td>
                    </motion.tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Paginación */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--text-tertiary)]">Mostrando {(page - 1) * 20 + 1}-{Math.min(page * 20, total)} de {total}</span>
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
