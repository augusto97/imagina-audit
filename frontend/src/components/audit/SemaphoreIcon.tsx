import { cn } from '@/lib/utils'
import type { SemaphoreLevel } from '@/types/audit'

interface SemaphoreIconProps {
  level: SemaphoreLevel
  size?: 'sm' | 'md'
}

const levelColors: Record<SemaphoreLevel, string> = {
  critical: 'bg-red-500',
  warning: 'bg-amber-500',
  good: 'bg-emerald-500',
  excellent: 'bg-emerald-600',
  info: 'bg-gray-500',
  unknown: 'bg-gray-500',
}

export default function SemaphoreIcon({ level, size = 'md' }: SemaphoreIconProps) {
  const sizeClass = size === 'sm' ? 'h-2 w-2' : 'h-3 w-3'

  return (
    <span className={cn('inline-block rounded-full shrink-0', sizeClass, levelColors[level])} />
  )
}
