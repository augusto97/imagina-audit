import { motion } from 'framer-motion'
import { useEffect, useState, type ReactNode } from 'react'
import { Card, CardContent } from '@/components/ui/card'

/**
 * Tile principal de un KPI del dashboard — contador animado, icono con
 * gradiente, subtexto opcional con la comparativa (hoy/semana/mes).
 */
export function KPICard({
  label,
  value,
  suffix,
  icon,
  gradient,
  bgGlow,
  delay = 0,
  subtext,
  onClick,
}: {
  label: string
  value: number
  suffix?: string
  icon: ReactNode
  gradient: string
  bgGlow: string
  delay?: number
  subtext?: ReactNode
  onClick?: () => void
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay }}
    >
      <Card
        className={`relative overflow-hidden border-0 shadow-sm transition-shadow hover:shadow-md ${onClick ? 'cursor-pointer' : ''}`}
        onClick={onClick}
      >
        <CardContent className="p-5">
          <div className="flex items-start justify-between">
            <div className="min-w-0 flex-1">
              <p className="text-xs font-medium uppercase tracking-wide text-[var(--text-tertiary)]">{label}</p>
              <p className="mt-2 text-3xl font-bold text-[var(--text-primary)]">
                <AnimatedNumber value={value} suffix={suffix} />
              </p>
              {subtext && <div className="mt-1.5 text-xs text-[var(--text-secondary)]">{subtext}</div>}
            </div>
            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${gradient} shadow-sm`}>
              {icon}
            </div>
          </div>
          <div className={`pointer-events-none absolute -bottom-4 -right-4 h-24 w-24 rounded-full ${bgGlow} blur-2xl`} />
        </CardContent>
      </Card>
    </motion.div>
  )
}

function AnimatedNumber({ value, suffix = '' }: { value: number; suffix?: string }) {
  const [display, setDisplay] = useState(0)
  useEffect(() => {
    const duration = 1200
    const startTime = performance.now()
    const step = (now: number) => {
      const progress = Math.min((now - startTime) / duration, 1)
      const eased = 1 - Math.pow(1 - progress, 3)
      setDisplay(Math.round(eased * value * 10) / 10)
      if (progress < 1) requestAnimationFrame(step)
    }
    requestAnimationFrame(step)
  }, [value])
  return <>{Number.isInteger(value) ? Math.round(display) : display.toFixed(1)}{suffix}</>
}
