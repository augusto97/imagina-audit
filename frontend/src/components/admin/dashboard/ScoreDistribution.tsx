import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { BarChart3 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { DashboardData } from '@/types/dashboard'

/**
 * Distribución histórica de scores en 5 buckets. Usa los mismos colores
 * que los semáforos del informe público para que la asociación sea
 * inmediata (rojo=crítico, verde oscuro=excelente).
 */
export function ScoreDistribution({ data }: { data: DashboardData['scoreDistribution'] }) {
  const chartData = [
    { name: 'Crítico',    range: '0-29',  value: data.critical,  color: '#EF4444' },
    { name: 'Deficiente', range: '30-49', value: data.deficient, color: '#F97316' },
    { name: 'Regular',    range: '50-69', value: data.regular,   color: '#FBBF24' },
    { name: 'Bueno',      range: '70-89', value: data.good,      color: '#34D399' },
    { name: 'Excelente',  range: '90-100', value: data.excellent, color: '#059669' },
  ]
  const total = chartData.reduce((sum, b) => sum + b.value, 0)

  return (
    <Card className="border-0 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <BarChart3 className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
            <CardTitle className="text-base">Distribución de scores</CardTitle>
          </div>
          <span className="text-xs text-[var(--text-tertiary)]">{total} auditorías totales</span>
        </div>
      </CardHeader>
      <CardContent>
        {total === 0 ? (
          <div className="py-8 text-center text-sm text-[var(--text-tertiary)]">Sin datos todavía</div>
        ) : (
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={chartData} barCategoryGap="20%">
              <XAxis
                dataKey="name"
                tick={{ fontSize: 11, fill: 'var(--text-secondary)' }}
                axisLine={false}
                tickLine={false}
              />
              <YAxis
                tick={{ fontSize: 10, fill: 'var(--text-tertiary)' }}
                axisLine={false}
                tickLine={false}
                width={30}
                allowDecimals={false}
              />
              <Tooltip
                cursor={{ fill: 'var(--bg-tertiary)', radius: 6 }}
                contentStyle={{
                  background: 'white',
                  border: 'none',
                  borderRadius: '12px',
                  boxShadow: '0 4px 20px rgba(0,0,0,0.08)',
                  fontSize: '12px',
                  padding: '8px 12px',
                }}
                formatter={(v: number, _: string, p: { payload?: { range: string; name: string } }) => [
                  `${v} (${total > 0 ? Math.round((v / total) * 100) : 0}%)`,
                  `${p.payload?.name} ${p.payload?.range ? `(${p.payload.range})` : ''}`,
                ]}
              />
              <Bar dataKey="value" radius={[6, 6, 2, 2]}>
                {chartData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}
