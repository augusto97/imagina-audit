import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Languages, Check, ChevronDown } from 'lucide-react'
import { SUPPORTED_LANGUAGES, LANGUAGE_NAMES, type SupportedLanguage } from '@/i18n'

/**
 * Selector de idioma. Click outside para cerrar; guarda la selección
 * en localStorage via el LanguageDetector de i18next.
 *
 * Variante 'compact' solo muestra el código (EN/ES/PT…) — ideal para
 * headers con poco espacio. Variante 'full' muestra Languages icon +
 * nombre completo.
 */

export function LanguageSwitcher({
  variant = 'compact',
  align = 'right',
}: {
  variant?: 'compact' | 'full'
  align?: 'left' | 'right'
}) {
  const { i18n } = useTranslation()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  const current = (i18n.resolvedLanguage || i18n.language || 'en').slice(0, 2) as SupportedLanguage

  useEffect(() => {
    if (!open) return
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  const changeTo = (lang: SupportedLanguage) => {
    i18n.changeLanguage(lang)
    setOpen(false)
  }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen(o => !o)}
        aria-label="Change language"
        className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-secondary)] hover:text-[var(--text-primary)]`}
      >
        {variant === 'full' && <Languages className="h-3.5 w-3.5" strokeWidth={1.5} />}
        <span className="font-mono uppercase">{current}</span>
        <ChevronDown className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className={`absolute z-50 mt-1 min-w-[150px] overflow-hidden rounded-lg border border-[var(--border-default)] bg-white shadow-lg ${align === 'right' ? 'right-0' : 'left-0'}`}>
          {SUPPORTED_LANGUAGES.map((lang) => {
            const isActive = current === lang
            return (
              <button
                key={lang}
                type="button"
                onClick={() => changeTo(lang)}
                className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs transition-colors ${
                  isActive
                    ? 'bg-[var(--accent-primary)]/10 text-[var(--accent-primary)] font-medium'
                    : 'text-[var(--text-primary)] hover:bg-[var(--bg-secondary)]'
                }`}
              >
                <span className="font-mono text-[10px] uppercase opacity-70">{lang}</span>
                <span className="flex-1">{LANGUAGE_NAMES[lang]}</span>
                {isActive && <Check className="h-3 w-3" strokeWidth={2} />}
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}
