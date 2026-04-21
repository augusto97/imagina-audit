import { motion } from 'framer-motion'
import { Link } from 'react-router-dom'
import { Pin, Eye, MessageCircle, FileSearch } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { getLevelClassName } from '@/lib/utils'
import type { DashboardData } from '@/types/dashboard'

/**
 * Últimas 10 auditorías con acciones rápidas. Enriquecido con badges de
 * WordPress vs externo y el pin de protección contra retención, para que
 * el operador identifique a ojo cuáles son clientes activos.
 */
export function RecentAuditsTable({ audits }: { audits: DashboardData['recentAudits'] }) {
  if (audits.length === 0) {
    return (
      <Card className="border-0 shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Últimas auditorías</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center gap-3 py-12 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--bg-tertiary)]">
              <FileSearch className="h-7 w-7 text-[var(--text-tertiary)]" strokeWidth={1} />
            </div>
            <div>
              <p className="text-sm font-medium text-[var(--text-secondary)]">Aún no hay auditorías</p>
              <p className="mt-0.5 text-xs text-[var(--text-tertiary)]">Realiza tu primera auditoría desde la página principal.</p>
            </div>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="border-0 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">Últimas auditorías</CardTitle>
          <Link to="/admin/leads" className="text-xs text-[var(--accent-primary)] hover:underline">
            Ver todas →
          </Link>
        </div>
      </CardHeader>
      <CardContent>
        <div className="-mx-6 overflow-x-auto">
          <table className="w-full min-w-[600px] text-sm">
            <thead>
              <tr className="text-left text-[11px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                <th className="px-6 pb-3">Dominio</th>
                <th className="px-3 pb-3">Tipo</th>
                <th className="px-3 pb-3">Fecha</th>
                <th className="px-3 pb-3">Contacto</th>
                <th className="px-3 pb-3">Score</th>
                <th className="px-3 pb-3" />
              </tr>
            </thead>
            <tbody>
              {audits.map((a, i) => (
                <motion.tr
                  key={a.id}
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  transition={{ delay: 0.05 + i * 0.03 }}
                  className="border-t border-[var(--border-default)]/60 transition-colors hover:bg-[var(--bg-tertiary)]/40"
                >
                  <td className="px-6 py-3">
                    <div className="flex items-center gap-2.5">
                      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-primary)]/8 text-xs font-bold text-[var(--accent-primary)]">
                        {a.domain.charAt(0).toUpperCase()}
                      </div>
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5">
                          <span className="truncate font-medium text-[var(--text-primary)]">{a.domain}</span>
                          {a.isPinned && <Pin className="h-3 w-3 shrink-0 fill-amber-500 text-amber-500" strokeWidth={2} />}
                        </div>
                        {a.leadName && (
                          <div className="truncate text-[10px] text-[var(--text-tertiary)]">{a.leadName}</div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-3 py-3">
                    {a.isWordPress
                      ? <Badge variant="secondary" className="text-[10px]">WordPress</Badge>
                      : <Badge variant="outline" className="text-[10px]">Externo</Badge>}
                  </td>
                  <td className="px-3 py-3 text-xs text-[var(--text-tertiary)]">
                    {new Date(a.createdAt).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' })}
                  </td>
                  <td className="px-3 py-3">
                    <Badge variant={a.hasContactInfo ? 'success' : 'secondary'} className="text-[10px]">
                      {a.hasContactInfo ? 'Sí' : 'No'}
                    </Badge>
                  </td>
                  <td className="px-3 py-3">
                    <span className={`text-sm font-bold tabular-nums ${getLevelClassName(a.globalLevel)}`}>{a.globalScore}</span>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex gap-1">
                      <Link to={`/admin/leads/${a.id}`}>
                        <Button variant="ghost" size="icon" className="h-7 w-7 rounded-lg" title="Ver en admin">
                          <Eye className="h-3.5 w-3.5" strokeWidth={1.5} />
                        </Button>
                      </Link>
                      {a.leadWhatsapp && (
                        <a href={`https://wa.me/${a.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer">
                          <Button variant="ghost" size="icon" className="h-7 w-7 rounded-lg text-emerald-500" title="WhatsApp">
                            <MessageCircle className="h-3.5 w-3.5" strokeWidth={1.5} />
                          </Button>
                        </a>
                      )}
                    </div>
                  </td>
                </motion.tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}
