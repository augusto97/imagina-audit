import { motion } from 'framer-motion'
import {
  Shield, Gauge, Search, Smartphone, Server,
  BarChart3, HardDrive, Blocks,
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import ScoreGauge from './ScoreGauge'
import MetricRow from './MetricRow'
import { getLevelLabel } from '@/lib/utils'
import type { ModuleResult } from '@/types/audit'

const iconMap: Record<string, React.ElementType> = {
  shield: Shield,
  gauge: Gauge,
  search: Search,
  smartphone: Smartphone,
  server: Server,
  'bar-chart-3': BarChart3,
  'hard-drive': HardDrive,
  blocks: Blocks,
}

const levelBadgeVariant: Record<string, 'destructive' | 'warning' | 'success' | 'secondary'> = {
  critical: 'destructive',
  warning: 'warning',
  good: 'success',
  excellent: 'success',
  info: 'secondary',
  unknown: 'secondary',
}

interface ModuleCardProps {
  module: ModuleResult
  index: number
}

export default function ModuleCard({ module, index }: ModuleCardProps) {
  const Icon = iconMap[module.icon] || Shield

  return (
    <motion.div
      id={`module-${module.id}`}
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ delay: index * 0.05 }}
    >
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--accent-primary)]/10">
                <Icon className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-[var(--text-primary)]">{module.name}</h3>
                <Badge variant={levelBadgeVariant[module.level] || 'secondary'}>
                  {getLevelLabel(module.level)}
                </Badge>
              </div>
            </div>
            <ScoreGauge
              score={module.score ?? 0}
              level={module.level}
              size="sm"
              showLabel={false}
            />
          </div>
          <p className="mt-2 text-sm text-[var(--text-secondary)]">{module.summary}</p>
        </CardHeader>

        <CardContent>
          {module.metrics.length > 0 ? (
            <div className="divide-y-0">
              {module.metrics.map((metric) => (
                <MetricRow key={metric.id} metric={metric} />
              ))}
            </div>
          ) : (
            <p className="text-sm text-[var(--text-tertiary)]">No hay métricas disponibles para este módulo.</p>
          )}

          {module.salesMessage && (
            <div className="mt-4 rounded-xl border border-[var(--accent-primary)]/20 bg-[var(--accent-primary)]/5 p-4">
              <p className="text-sm text-[var(--text-secondary)]">{module.salesMessage}</p>
            </div>
          )}
        </CardContent>
      </Card>
    </motion.div>
  )
}
