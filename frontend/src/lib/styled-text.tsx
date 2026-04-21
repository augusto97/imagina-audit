import type { ReactNode } from 'react'

/**
 * Renderiza texto con marcas tipo markdown para permitir que el admin
 * resalte palabras clave en los textos editables del home.
 *
 *   **palabra**   → acento (color primario de la marca, bold)
 *   ==palabra==   → highlight amarillo (clase .highlight-yellow)
 *
 * Parser simple por tokens — no intenta ser un markdown completo. Si el
 * admin no usa las marcas, el texto se renderiza tal cual.
 */
export function renderStyledText(text: string): ReactNode[] {
  if (!text) return []

  // Tokeniza por los dos patrones a la vez. Capturamos qué tipo es para
  // decidir cómo envolverlo.
  const tokenRegex = /(\*\*([^*]+)\*\*|==([^=]+)==)/g
  const parts: ReactNode[] = []
  let lastIndex = 0
  let match: RegExpExecArray | null
  let key = 0

  while ((match = tokenRegex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push(text.slice(lastIndex, match.index))
    }
    if (match[2] !== undefined) {
      // **accent**
      parts.push(
        <span key={key++} className="text-[var(--accent-primary)]">{match[2]}</span>
      )
    } else if (match[3] !== undefined) {
      // ==highlight==
      parts.push(
        <span key={key++} className="highlight-yellow">{match[3]}</span>
      )
    }
    lastIndex = match.index + match[0].length
  }
  if (lastIndex < text.length) {
    parts.push(text.slice(lastIndex))
  }
  return parts
}
