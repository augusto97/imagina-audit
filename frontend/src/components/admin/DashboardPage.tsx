import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { FileSearch, UserCheck, CalendarDays, Gauge, Eye, MessageCircle, TrendingUp } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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

/** Contador animado */
function AnimatedNumber({ value, suffix = '' }: { value: number; suffix?: string }) {
  const [display, setDisplay] = useState(0)
  useEffect(() => {
    let start = 0
    const duration = 1200
    const startTime = performance.now()
    const step = (now: number) => {
      const progress = Math.min((now - startTime) / duration, 1)
      const eased = 1 - Math.pow(1 - progress, 3)
      start = Math.round(eased * value * 10) / 10
      setDisplay(start)
      if (progress < 1) requestAnimationFrame(step)
    }
    requestAnimationFrame(step)
  }, [value])
  return <>{Number.isInteger(value) ? Math.round(display) : display.toFixed(1)}{suffix}</>
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
          {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-32 rounded-2xl" />)}
        </div>
        <Skeleton className="h-72 rounded-2xl" />
      </div>
    )
  }

  const chartData = [
    { name: 'Crítico', value: data.scoreDistribution.critical, color: '#EF4444' },
    { name: 'Deficiente', value: data.scoreDistribution.deficient, color: '#F97316' },
    { name: 'Regular', value: data.scoreDistribution.regular, color: '#FBBF24' },
    { name: 'Bueno', value: data.scoreDistribution.good, color: '#34D399' },
    { name: 'Excelente', value: data.scoreDistribution.excellent, color: '#059669' },
  ]

  const stats = [
    { label: 'Total Auditorías', value: data.totalAudits, icon: FileSearch, gradient: 'from-[#0CC0DF] to-[#0A9DB8]', bg: 'bg-[#0CC0DF]/8' },
    { label: 'Leads con Contacto', value: data.totalLeads, icon: UserCheck, gradient: 'from-emerald-400 to-emerald-600', bg: 'bg-emerald-500/8' },
    { label: 'Auditorías Hoy', value: data.auditsToday, icon: CalendarDays, gradient: 'from-amber-400 to-amber-600', bg: 'bg-amber-500/8' },
    { label: 'Score Promedio', value: data.averageScore, icon: Gauge, gradient: 'from-violet-400 to-violet-600', bg: 'bg-violet-500/8', suffix: '/100' },
  ]

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Dashboard</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">Resumen general de auditorías y leads</p>
      </div>

      {/* Stats con gradientes */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, i) => (
          <motion.div key={stat.label} initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.08 }}>
            <Card className="relative overflow-hidden border-0 shadow-sm hover:shadow-md transition-shadow">
              <CardContent className="p-5">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium text-[var(--text-tertiary)] uppercase tracking-wide">{stat.label}</p>
                    <p className="mt-2 text-3xl font-bold text-[var(--text-primary)]">
                      <AnimatedNumber value={stat.value} suffix={stat.suffix} />
                    </p>
                  </div>
                  <div className={`flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br ${stat.gradient} shadow-sm`}>
                    <stat.icon className="h-5 w-5 text-white" strokeWidth={1.5} />
                  </div>
                </div>
                {/* Decoración sutil */}
                <div className={`absolute -bottom-4 -right-4 h-24 w-24 rounded-full ${stat.bg} blur-2xl`} />
              </CardContent>
            </Card>
          </motion.div>
        ))}
      </div>

      {/* Gráfico */}
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.35 }}>
        <Card className="border-0 shadow-sm">
          <CardHeader className="pb-2">
            <div className="flex items-center gap-2">
              <TrendingUp className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
              <CardTitle className="text-base">Distribución de Scores</CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={chartData} barCategoryGap="20%">
                <XAxis dataKey="name" tick={{ fontSize: 12, fill: 'var(--text-secondary)' }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: 'var(--text-tertiary)' }} axisLine={false} tickLine={false} width={30} />
                <Tooltip
                  cursor={{ fill: 'var(--bg-tertiary)', radius: 8 }}
                  contentStyle={{ background: 'white', border: 'none', borderRadius: '12px', boxShadow: '0 4px 20px rgba(0,0,0,0.08)', fontSize: '13px', padding: '8px 14px' }}
                />
                <Bar dataKey="value" radius={[8, 8, 4, 4]}>
                  {chartData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </motion.div>

      {/* Tabla recientes */}
      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.45 }}>
        <Card className="border-0 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Últimas Auditorías</CardTitle>
          </CardHeader>
          <CardContent>
            {data.recentAudits.length === 0 ? (
              <div className="flex flex-col items-center gap-3 py-12 text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--bg-tertiary)]">
                  <FileSearch className="h-7 w-7 text-[var(--text-tertiary)]" strokeWidth={1} />
                </div>
                <div>
                  <p className="text-sm font-medium text-[var(--text-secondary)]">Aún no hay auditorías</p>
                  <p className="text-xs text-[var(--text-tertiary)] mt-0.5">Realiza tu primera auditoría desde la página principal.</p>
                </div>
              </div>
            ) : (
              <div className="overflow-x-auto -mx-6">
                <table className="w-full text-sm min-w-[600px]">
                  <thead>
                    <tr className="text-left text-[11px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                      <th className="px-6 pb-3">Dominio</th>
                      <th className="px-4 pb-3">Fecha</th>
                      <th className="px-4 pb-3">Contacto</th>
                      <th className="px-4 pb-3">Score</th>
                      <th className="px-4 pb-3"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recentAudits.map((a, i) => (
                      <motion.tr
                        key={a.id}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.5 + i * 0.04 }}
                        className="border-t border-[var(--border-default)]/60 hover:bg-[var(--bg-tertiary)]/40 transition-colors"
                      >
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--accent-primary)]/8 text-xs font-bold text-[var(--accent-primary)]">
                              {a.domain.charAt(0).toUpperCase()}
                            </div>
                            <span className="font-medium text-[var(--text-primary)]">{a.domain}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-xs text-[var(--text-tertiary)]">
                          {new Date(a.createdAt).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' })}
                        </td>
                        <td className="px-4 py-3">
                          <Badge variant={a.hasContactInfo ? 'success' : 'secondary'} className="text-[10px]">
                            {a.hasContactInfo ? 'Sí' : 'No'}
                          </Badge>
                        </td>
                        <td className="px-4 py-3">
                          <span className={`text-sm font-bold ${getLevelClassName(a.globalLevel)}`}>{a.globalScore}</span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex gap-1">
                            <a href={`/results/${a.id}`} target="_blank" rel="noreferrer">
                              <Button variant="ghost" size="icon" className="h-7 w-7 rounded-lg"><Eye className="h-3.5 w-3.5" strokeWidth={1.5} /></Button>
                            </a>
                            {a.leadWhatsapp && (
                              <a href={`https://wa.me/${a.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer">
                                <Button variant="ghost" size="icon" className="h-7 w-7 rounded-lg text-emerald-500"><MessageCircle className="h-3.5 w-3.5" strokeWidth={1.5} /></Button>
                              </a>
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
      </motion.div>
    </div>
  )
}
