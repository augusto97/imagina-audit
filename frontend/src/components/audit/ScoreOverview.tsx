import { motion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import ScoreGauge from './ScoreGauge'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { getLevelLabel } from '@/lib/utils'
import type { AuditResult } from '@/types/audit'

interface ScoreOverviewProps {
  result: AuditResult
}

export default function ScoreOverview({ result }: ScoreOverviewProps) {
  const { t } = useTranslation()
  const scrollToModule = (moduleId: string) => {
    const el = document.getElementById(`module-${moduleId}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  return (
    <section className="hero-gradient py-12 sm:py-16">
      <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        {/* Global score */}
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.6 }}
          className="flex flex-col items-center gap-4"
        >
          <div className="text-center">
            <p className="text-sm text-[var(--text-tertiary)]">{t('public.score_global_prefix')}</p>
            <h1 className="text-xl font-bold text-[var(--text-primary)] sm:text-2xl">{result.domain}</h1>
          </div>

          <ScoreGauge score={result.globalScore} level={result.globalLevel} size="lg" />

          <div className="flex flex-wrap justify-center gap-2">
            {result.totalIssues.critical > 0 && (
              <Badge variant="destructive">{result.totalIssues.critical} {t('public.score_critical')}</Badge>
            )}
            {result.totalIssues.warning > 0 && (
              <Badge variant="warning">{result.totalIssues.warning} {t('public.score_important')}</Badge>
            )}
            {result.totalIssues.good > 0 && (
              <Badge variant="success">{result.totalIssues.good} {t('public.score_correct')}</Badge>
            )}
          </div>
        </motion.div>

        {/* Module mini-cards */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.6 }}
          className="mt-10 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-7"
        >
          {result.modules.map((module, i) => (
            <motion.div
              key={module.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 + i * 0.05 }}
            >
              <Card
                className="cursor-pointer py-3 px-2 hover:border-[var(--accent-primary)] hover:shadow-md transition-all text-center"
                onClick={() => scrollToModule(module.id)}
              >
                <ScoreGauge score={module.score ?? 0} level={module.level} size="sm" showLabel={false} animate={false} />
                <p className="text-[10px] font-medium text-[var(--text-tertiary)] mt-1.5 leading-tight">{module.name}</p>
                <Badge
                  variant={module.level === 'critical' ? 'destructive' : module.level === 'warning' ? 'warning' : 'success'}
                  className="mt-1 text-[9px] px-1.5 py-0"
                >
                  {getLevelLabel(module.level)}
                </Badge>
              </Card>
            </motion.div>
          ))}
        </motion.div>
      </div>
    </section>
  )
}
