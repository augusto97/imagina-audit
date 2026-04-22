import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import i18n from '@/i18n'

/**
 * Combina clases CSS con soporte para Tailwind merge
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Formatea un número como moneda
 */
export function formatCurrency(amount: number, currency = 'USD'): string {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

/**
 * Formatea milisegundos a segundos legibles
 */
export function formatMs(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

/**
 * Obtiene el color CSS para un nivel de semáforo
 */
export function getLevelColor(level: string): string {
  const colors: Record<string, string> = {
    critical: 'var(--color-critical)',
    warning: 'var(--color-warning)',
    good: 'var(--color-good)',
    excellent: 'var(--color-excellent)',
    info: 'var(--color-info)',
    unknown: 'var(--color-info)',
  }
  return colors[level] || colors.info
}

/**
 * Obtiene la clase Tailwind para un nivel de semáforo
 */
export function getLevelClassName(level: string): string {
  const classes: Record<string, string> = {
    critical: 'text-red-500',
    warning: 'text-amber-500',
    good: 'text-emerald-500',
    excellent: 'text-emerald-600',
    info: 'text-gray-500',
    unknown: 'text-gray-500',
  }
  return classes[level] || classes.info
}

/**
 * Obtiene la etiqueta legible para un nivel de semáforo.
 * Usa i18next para localizar — se importa dinámicamente para evitar
 * dependencia circular con el store de config.
 */
export function getLevelLabel(level: string): string {
  const keys: Record<string, string> = {
    critical:  'common.level_critical',
    warning:   'common.level_warning',
    good:      'common.level_good',
    excellent: 'common.level_excellent',
    info:      'common.level_info',
    unknown:   'common.level_unknown',
  }
  return i18n.t(keys[level] || keys.unknown)
}
