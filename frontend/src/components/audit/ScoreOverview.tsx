import { motion } from 'framer-motion'
import ScoreGauge from './ScoreGauge'
import { Badge } from '@/components/ui/badge'
import type { AuditResult } from '@/types/audit'

interface ScoreOverviewProps {
  result: AuditResult
}

export default function ScoreOverview({ result }: ScoreOverviewProps) {
  const scrollToModule = (moduleId: string) => {
    const el = document.getElementById(`module-${moduleId}`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }

  return (
    <section className="hero-gradient py-12 sm:py-16">
      <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        {/* Score global */}
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.6 }}
          className="flex flex-col items-center gap-4"
        >
          <div className="text-center">
            <p className="text-sm text-[var(--text-tertiary)]">Puntuación global de</p>
            <h1 className="text-xl font-bold text-[var(--text-primary)] sm:text-2xl">{result.domain}</h1>
          </div>

          <ScoreGauge score={result.globalScore} level={result.globalLevel} size="lg" />

          {/* Badges de issues */}
          <div className="flex flex-wrap justify-center gap-2">
            {result.totalIssues.critical > 0 && (
              <Badge variant="destructive">{result.totalIssues.critical} Críticos</Badge>
            )}
            {result.totalIssues.warning > 0 && (
              <Badge variant="warning">{result.totalIssues.warning} Importantes</Badge>
            )}
            {result.totalIssues.good > 0 && (
              <Badge variant="success">{result.totalIssues.good} Correctos</Badge>
            )}
          </div>
        </motion.div>

        {/* Mini-gauges de módulos */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.6 }}
          className="mt-10 grid grid-cols-4 gap-3 sm:grid-cols-8"
        >
          {result.modules.map((module) => (
            <button
              key={module.id}
              type="button"
              onClick={() => scrollToModule(module.id)}
              className="glass-card flex flex-col items-center gap-1 p-3 transition-all hover:border-[var(--border-hover)] cursor-pointer"
            >
              <ScoreGauge
                score={module.score ?? 0}
                level={module.level}
                size="sm"
                showLabel={false}
                animate={false}
              />
              <span className="text-[10px] text-[var(--text-tertiary)] text-center leading-tight">
                {module.name}
              </span>
            </button>
          ))}
        </motion.div>
      </div>
    </section>
  )
}
