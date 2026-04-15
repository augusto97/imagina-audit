import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { FileSearch, UserCheck, CalendarDays, Gauge, Eye, MessageCircle } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { useAdmin } from '@/hooks/useAdmin'
import { getLevelClassName } from '@/lib/utils'

interface DashboardData {
  totalAudits: number
  totalLeads: number
  auditsToday: number
  averageScore: number
  scoreDistribution: { critical: number; deficient: number; regular: number; good: number; excellent: number }
  recentAudits: Array<{
    id: string; domain: string; leadName: string | null; leadEmail: string | null
    leadWhatsapp: string | null; globalScore: number; globalLevel: string; createdAt: string
    hasContactInfo: boolean
  }>
}

export default function DashboardPage() {
  const { fetchDashboard } = useAdmin()
  const [data, setData] = useState<DashboardData | null>(null)

  useEffect(() => {
    fetchDashboard().then(setData)
  }, [fetchDashboard])

  if (!data) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-48" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-28 rounded-2xl" />)}
        </div>
        <Skeleton className="h-64 rounded-2xl" />
      </div>
    )
  }

  const chartData = [
    { name: 'Crítico', value: data.scoreDistribution.critical, color: '#EF4444' },
    { name: 'Deficiente', value: data.scoreDistribution.deficient, color: '#F97316' },
    { name: 'Regular', value: data.scoreDistribution.regular, color: '#F59E0B' },
    { name: 'Bueno', value: data.scoreDistribution.good, color: '#10B981' },
    { name: 'Excelente', value: data.scoreDistribution.excellent, color: '#059669' },
  ]

  const stats = [
    { label: 'Total Auditorías', value: data.totalAudits, icon: FileSearch, color: 'text-[var(--accent-primary)]' },
    { label: 'Leads con Contacto', value: data.totalLeads, icon: UserCheck, color: 'text-emerald-500' },
    { label: 'Auditorías Hoy', value: data.auditsToday, icon: CalendarDays, color: 'text-amber-500' },
    { label: 'Score Promedio', value: data.averageScore, icon: Gauge, color: 'text-[var(--accent-primary)]', suffix: '/100' },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Dashboard</h1>
        <p className="text-sm text-[var(--text-secondary)]">Resumen general de auditorías y leads</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, i) => (
          <motion.div key={stat.label} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.1 }}>
            <Card>
              <CardContent className="flex items-center gap-4 p-5">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--bg-tertiary)]`}>
                  <stat.icon className={`h-5 w-5 ${stat.color}`} strokeWidth={1.5} />
                </div>
                <div>
                  <p className="text-xs text-[var(--text-tertiary)]">{stat.label}</p>
                  <p className={`text-2xl font-bold ${stat.color}`}>
                    {stat.value}{stat.suffix || ''}
                  </p>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        ))}
      </div>

      {/* Gráfico */}
      <Card>
        <CardHeader><CardTitle>Distribución de Scores</CardTitle></CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={chartData}>
              <XAxis dataKey="name" tick={{ fontSize: 12, fill: 'var(--text-secondary)' }} axisLine={false} tickLine={false} />
              <YAxis tick={{ fontSize: 12, fill: 'var(--text-tertiary)' }} axisLine={false} tickLine={false} />
              <Tooltip contentStyle={{ background: 'white', border: '1px solid var(--border-default)', borderRadius: '8px', fontSize: '13px' }} />
              <Bar dataKey="value" radius={[6, 6, 0, 0]}>
                {chartData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* Tabla recientes */}
      <Card>
        <CardHeader><CardTitle>Últimas Auditorías</CardTitle></CardHeader>
        <CardContent>
          {data.recentAudits.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-8 text-center text-[var(--text-tertiary)]">
              <FileSearch className="h-10 w-10" strokeWidth={1} />
              <p className="text-sm">Aún no hay auditorías</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-default)] text-left text-xs text-[var(--text-tertiary)]">
                    <th className="pb-3 pr-3">Fecha</th>
                    <th className="pb-3 pr-3">Dominio</th>
                    <th className="pb-3 pr-3">Contacto</th>
                    <th className="pb-3 pr-3">Score</th>
                    <th className="pb-3">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recentAudits.map((a) => (
                    <tr key={a.id} className="border-b border-[var(--border-default)] last:border-0">
                      <td className="py-2.5 pr-3 text-xs text-[var(--text-tertiary)]">
                        {new Date(a.createdAt).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                      </td>
                      <td className="py-2.5 pr-3 font-medium">{a.domain}</td>
                      <td className="py-2.5 pr-3">
                        <Badge variant={a.hasContactInfo ? 'success' : 'secondary'}>
                          {a.hasContactInfo ? 'Sí' : 'No'}
                        </Badge>
                      </td>
                      <td className="py-2.5 pr-3">
                        <span className={`font-bold ${getLevelClassName(a.globalLevel)}`}>{a.globalScore}</span>
                      </td>
                      <td className="py-2.5">
                        <div className="flex gap-1">
                          <a href={`/results/${a.id}`} target="_blank" rel="noreferrer">
                            <Button variant="ghost" size="icon" className="h-8 w-8"><Eye className="h-4 w-4" strokeWidth={1.5} /></Button>
                          </a>
                          {a.leadWhatsapp && (
                            <a href={`https://wa.me/${a.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer">
                              <Button variant="ghost" size="icon" className="h-8 w-8 text-emerald-500"><MessageCircle className="h-4 w-4" strokeWidth={1.5} /></Button>
                            </a>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
