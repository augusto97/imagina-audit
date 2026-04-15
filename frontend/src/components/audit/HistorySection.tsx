import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { TrendingUp, TrendingDown, Minus, History } from 'lucide-react'
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, ReferenceLine } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import api from '@/lib/api'

interface HistoryEntry {
  id: string
  globalScore: number
  globalLevel: string
  createdAt: string
}

interface HistoryData {
  domain: string
  totalAudits: number
  history: HistoryEntry[]
  trend: 'improving' | 'declining' | 'stable' | 'insufficient_data'
}

interface HistorySectionProps {
  domain: string
}

export default function HistorySection({ domain }: HistorySectionProps) {
  const [data, setData] = useState<HistoryData | null>(null)

  useEffect(() => {
    api.get('/history.php', { params: { domain } })
      .then((res) => {
        if (res.data?.data?.totalAudits > 1) {
          setData(res.data.data)
        }
      })
      .catch(() => {})
  }, [domain])

  if (!data || data.totalAudits <= 1) return null

  const chartData = [...data.history].reverse().map((h) => ({
    date: new Date(h.createdAt).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }),
    score: h.globalScore,
  }))

  const first = data.history[data.history.length - 1]
  const last = data.history[0]
  const diff = last.globalScore - first.globalScore

  const trendConfig = {
    improving: { icon: TrendingUp, label: 'Mejorando', variant: 'success' as const, color: '#10B981' },
    declining: { icon: TrendingDown, label: 'Deteriorándose', variant: 'destructive' as const, color: '#EF4444' },
    stable: { icon: Minus, label: 'Estable', variant: 'secondary' as const, color: '#64748B' },
    insufficient_data: { icon: Minus, label: 'Datos insuficientes', variant: 'secondary' as const, color: '#64748B' },
  }

  const trend = trendConfig[data.trend]
  const TrendIcon = trend.icon

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }}>
      <Card className="border-0 shadow-sm overflow-hidden">
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <History className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
              <CardTitle className="text-base">Historial — {domain}</CardTitle>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant="secondary" className="text-[10px]">{data.totalAudits} auditorías</Badge>
              <Badge variant={trend.variant} className="text-[10px]">
                <TrendIcon className="h-3 w-3 mr-0.5" /> {trend.label}
              </Badge>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {/* Gráfico */}
          <ResponsiveContainer width="100%" height={180}>
            <LineChart data={chartData} margin={{ top: 10, right: 10, bottom: 0, left: -20 }}>
              <XAxis dataKey="date" tick={{ fontSize: 11, fill: 'var(--text-tertiary)' }} axisLine={false} tickLine={false} />
              <YAxis domain={[0, 100]} tick={{ fontSize: 11, fill: 'var(--text-tertiary)' }} axisLine={false} tickLine={false} />
              <ReferenceLine y={70} stroke="#10B981" strokeDasharray="3 3" strokeOpacity={0.3} />
              <ReferenceLine y={50} stroke="#F59E0B" strokeDasharray="3 3" strokeOpacity={0.3} />
              <Tooltip contentStyle={{ background: 'white', border: 'none', borderRadius: '12px', boxShadow: '0 4px 20px rgba(0,0,0,0.08)', fontSize: '13px' }} />
              <Line
                type="monotone" dataKey="score" stroke={trend.color} strokeWidth={2.5}
                dot={{ r: 4, fill: 'white', stroke: trend.color, strokeWidth: 2 }}
                activeDot={{ r: 6, fill: trend.color }}
              />
            </LineChart>
          </ResponsiveContainer>

          {/* Resumen */}
          <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--text-secondary)]">
            <span>Primera: {new Date(first.createdAt).toLocaleDateString('es-CO')} ({first.globalScore}/100)</span>
            <span>Actual: {new Date(last.createdAt).toLocaleDateString('es-CO')} ({last.globalScore}/100)</span>
            {diff > 0 && <span className="text-emerald-500 font-medium">+{diff} puntos</span>}
            {diff < 0 && <span className="text-red-500 font-medium">{diff} puntos</span>}
          </div>
        </CardContent>
      </Card>
    </motion.div>
  )
}
