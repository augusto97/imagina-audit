import { motion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import {
  Shield, Gauge, Search, Smartphone, Server,
  BarChart3, HardDrive, Blocks, HeartPulse,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Accordion, AccordionRadixItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion'
import { Separator } from '@/components/ui/separator'
import ScoreGauge from './ScoreGauge'
import SemaphoreIcon from './SemaphoreIcon'
import { getLevelLabel, getLevelColor } from '@/lib/utils'
import type { ModuleResult } from '@/types/audit'

const iconMap: Record<string, React.ElementType> = {
  shield: Shield, gauge: Gauge, search: Search, smartphone: Smartphone,
  server: Server, 'bar-chart-3': BarChart3, 'hard-drive': HardDrive, blocks: Blocks,
  'heart-pulse': HeartPulse,
}

const levelBadgeVariant: Record<string, 'destructive' | 'warning' | 'success' | 'secondary'> = {
  critical: 'destructive', warning: 'warning', good: 'success', excellent: 'success',
  info: 'secondary', unknown: 'secondary',
}

interface ModuleCardProps {
  module: ModuleResult
  index: number
}

export default function ModuleCard({ module, index }: ModuleCardProps) {
  const { t } = useTranslation()
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
        <CardContent className="pt-6">
          {/* Module header */}
          <div className="flex items-center justify-between gap-2 mb-1">
            <div className="flex items-center gap-3 min-w-0">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-primary)]/10">
                <Icon className="h-4.5 w-4.5 text-[var(--accent-primary)]" strokeWidth={1.5} />
              </div>
              <div className="min-w-0">
                <h3 className="text-sm sm:text-base font-semibold text-[var(--text-primary)] truncate">{module.name}</h3>
                <div className="flex items-center gap-2 mt-0.5">
                  <Badge variant={levelBadgeVariant[module.level] || 'secondary'}>
                    {getLevelLabel(module.level)}
                  </Badge>
                  <span className="text-sm font-bold sm:hidden" style={{ color: getLevelColor(module.level) }}>{module.score ?? '—'}</span>
                </div>
              </div>
            </div>
            <div className="hidden sm:block shrink-0">
              <ScoreGauge score={module.score ?? 0} level={module.level} size="sm" showLabel={false} />
            </div>
          </div>

          <p className="text-sm text-[var(--text-secondary)] mb-4">{module.summary}</p>

          <Separator className="mb-2" />

          {/* Metrics */}
          {module.metrics.length > 0 ? (
            <Accordion type="multiple" className="w-full">
              {module.metrics.map((metric) => (
                <AccordionRadixItem key={metric.id} value={metric.id} className="border-b border-[var(--border-default)] last:border-0">
                  <AccordionTrigger className="py-3 text-sm">
                    <div className="flex items-center gap-3 pr-2 min-w-0 flex-1">
                      <SemaphoreIcon level={metric.level} />
                      <span className="flex-1 text-left truncate font-normal">{metric.name}</span>
                      <span className="text-xs text-[var(--text-tertiary)] max-w-[40%] text-right break-words line-clamp-2 font-normal">
                        {metric.displayValue}
                      </span>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pl-7 text-sm">
                      <p className="text-[var(--text-secondary)]">{metric.description}</p>
                      {metric.recommendation && (
                        <div className="rounded-lg bg-[var(--bg-tertiary)] p-3">
                          <p className="text-xs font-semibold text-[var(--text-tertiary)] mb-1">{t('public.module_recommendation')}</p>
                          <p className="text-[var(--text-secondary)]">{metric.recommendation}</p>
                        </div>
                      )}
                      {metric.imaginaSolution && (
                        <div className="rounded-lg border border-[var(--accent-primary)]/20 bg-[var(--accent-primary)]/5 p-3">
                          <p className="text-xs font-semibold text-[var(--accent-primary)] mb-1">{t('public.module_imagina_solution')}</p>
                          <p className="text-[var(--text-secondary)]">{metric.imaginaSolution}</p>
                        </div>
                      )}
                    </div>
                  </AccordionContent>
                </AccordionRadixItem>
              ))}
            </Accordion>
          ) : (
            <p className="text-sm text-[var(--text-tertiary)] py-4">{t('public.module_no_metrics')}</p>
          )}

          {module.salesMessage && (
            <div className="mt-4 rounded-lg border border-[var(--accent-primary)]/20 bg-[var(--accent-primary)]/5 p-4">
              <p className="text-sm text-[var(--text-secondary)]">{module.salesMessage}</p>
            </div>
          )}
        </CardContent>
      </Card>
    </motion.div>
  )
}
