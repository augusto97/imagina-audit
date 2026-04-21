import { ComposedChart, Bar, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { TrendingUp } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { DashboardData } from '@/types/dashboard'

/**
 * Chart de 30 días combinando:
 *  - Barras azules: cantidad de auditorías por día
 *  - Línea verde: score promedio de ese día (solo donde hubo auditorías)
 *
 * Útil para detectar picos de demanda y correlacionar con calidad
 * (¿los días con muchos audits tienen score diferente?).
 */
export function TrendChart({ data }: { data: DashboardData['trend30d'] }) {
  const chartData = data.map(d => ({
    ...d,
    day: formatDay(d.date),
  }))

  const hasAnyAudit = data.some(d => d.count > 0)

  return (
    <Card className="border-0 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-center gap-2">
          <TrendingUp className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
          <CardTitle className="text-base">Actividad últimos 30 días</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {!hasAnyAudit ? (
          <div className="flex items-center justify-center py-12 text-sm text-[var(--text-tertiary)]">
            Sin auditorías en los últimos 30 días
          </div>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <ComposedChart data={chartData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border-default)" vertical={false} />
              <XAxis
                dataKey="day"
                tick={{ fontSize: 10, fill: 'var(--text-tertiary)' }}
                axisLine={false}
                tickLine={false}
                interval="preserveStartEnd"
                minTickGap={20}
              />
              <YAxis
                yAxisId="count"
                tick={{ fontSize: 10, fill: 'var(--text-tertiary)' }}
                axisLine={false}
                tickLine={false}
                width={30}
                allowDecimals={false}
              />
              <YAxis
                yAxisId="score"
                orientation="right"
                domain={[0, 100]}
                tick={{ fontSize: 10, fill: 'var(--text-tertiary)' }}
                axisLine={false}
                tickLine={false}
                width={30}
              />
              <Tooltip
                cursor={{ fill: 'var(--bg-tertiary)' }}
                contentStyle={{
                  background: 'white',
                  border: 'none',
                  borderRadius: '12px',
                  boxShadow: '0 4px 20px rgba(0,0,0,0.08)',
                  fontSize: '12px',
                  padding: '8px 12px',
                }}
                labelFormatter={(_, payload) => (payload?.[0]?.payload as { date: string })?.date}
                formatter={((value: unknown, name: unknown) => {
                  const v = typeof value === 'number' ? value : 0
                  if (name === 'count') return [v, 'Auditorías'] as [number, string]
                  if (name === 'avgScore') return [v.toFixed(1), 'Score prom.'] as [string, string]
                  return [v, String(name ?? '')] as [number, string]
                }) as never}
              />
              <Bar yAxisId="count" dataKey="count" fill="var(--accent-primary)" radius={[4, 4, 0, 0]} />
              <Line
                yAxisId="score"
                type="monotone"
                dataKey="avgScore"
                stroke="#10B981"
                strokeWidth={2}
                dot={false}
                connectNulls
              />
            </ComposedChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}

function formatDay(iso: string): string {
  try {
    return new Date(iso + 'T00:00:00').toLocaleDateString('es-CO', { day: 'numeric', month: 'short' })
  } catch {
    return iso
  }
}
