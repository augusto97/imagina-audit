import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { getLevelColor, getLevelLabel } from '@/lib/utils'
import type { SemaphoreLevel } from '@/types/audit'

interface ScoreGaugeProps {
  score: number
  level: SemaphoreLevel
  size?: 'sm' | 'md' | 'lg'
  showLabel?: boolean
  animate?: boolean
}

export default function ScoreGauge({
  score,
  level,
  size = 'md',
  showLabel = true,
  animate = true,
}: ScoreGaugeProps) {
  const [displayScore, setDisplayScore] = useState(animate ? 0 : score)

  // Animación del número
  useEffect(() => {
    if (!animate) {
      setDisplayScore(score)
      return
    }

    let start = 0
    const duration = 1500
    const startTime = performance.now()

    const step = (currentTime: number) => {
      const elapsed = currentTime - startTime
      const progress = Math.min(elapsed / duration, 1)
      // Easing ease-out
      const eased = 1 - Math.pow(1 - progress, 3)
      start = Math.round(eased * score)
      setDisplayScore(start)

      if (progress < 1) {
        requestAnimationFrame(step)
      }
    }

    requestAnimationFrame(step)
  }, [score, animate])

  const dimensions = {
    sm: { size: 80, stroke: 6, fontSize: 'text-lg', labelSize: 'text-[10px]' },
    md: { size: 140, stroke: 8, fontSize: 'text-3xl', labelSize: 'text-xs' },
    lg: { size: 200, stroke: 10, fontSize: 'text-5xl', labelSize: 'text-sm' },
  }

  const d = dimensions[size]
  const radius = (d.size - d.stroke) / 2
  const circumference = 2 * Math.PI * radius
  const strokeDashoffset = circumference - (score / 100) * circumference
  const color = getLevelColor(level)
  const labelText = getLevelLabel(level)

  return (
    <div className="flex flex-col items-center gap-1">
      <div className="relative" style={{ width: d.size, height: d.size }}>
        <svg width={d.size} height={d.size} className="-rotate-90">
          {/* Fondo del círculo */}
          <circle
            cx={d.size / 2}
            cy={d.size / 2}
            r={radius}
            fill="none"
            stroke="var(--bg-tertiary)"
            strokeWidth={d.stroke}
          />
          {/* Arco de progreso */}
          <motion.circle
            cx={d.size / 2}
            cy={d.size / 2}
            r={radius}
            fill="none"
            stroke={color}
            strokeWidth={d.stroke}
            strokeLinecap="round"
            strokeDasharray={circumference}
            initial={{ strokeDashoffset: circumference }}
            animate={{ strokeDashoffset: animate ? strokeDashoffset : strokeDashoffset }}
            transition={{ duration: 1.5, ease: 'easeOut' }}
          />
        </svg>

        {/* Score en el centro */}
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className={`font-bold ${d.fontSize}`} style={{ color }}>
            {displayScore}
          </span>
          {size !== 'sm' && (
            <span className={`${d.labelSize} text-[var(--text-tertiary)]`}>/100</span>
          )}
        </div>
      </div>

      {showLabel && (
        <span className={`font-medium ${d.labelSize}`} style={{ color }}>
          {labelText}
        </span>
      )}
    </div>
  )
}
