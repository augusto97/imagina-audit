import { create } from 'zustand'
import api from '@/lib/api'
import { COMMON_LANGUAGE_NAMES, FALLBACK_LANGUAGES } from '@/i18n'

/**
 * Lista dinámica de idiomas disponibles en el frontend público. Lo que
 * aparece en el LanguageSwitcher viene de /api/languages.php — el admin
 * decide qué idiomas activar y cuáles exponer públicamente.
 *
 * Al arrancar la app cargamos esta lista; si la API falla, caemos al par
 * en/es para no dejar la UI sin switcher.
 */

export interface PublicLanguage {
  code: string
  name: string        // Nombre canónico (inglés)
  nativeName: string  // Nombre en su propio idioma — lo que se muestra al user
}

interface LanguagesStore {
  languages: PublicLanguage[]
  loaded: boolean
  loading: boolean
  load: () => Promise<void>
}

const FALLBACK: PublicLanguage[] = FALLBACK_LANGUAGES.map((code) => ({
  code,
  name: COMMON_LANGUAGE_NAMES[code] ?? code.toUpperCase(),
  nativeName: COMMON_LANGUAGE_NAMES[code] ?? code.toUpperCase(),
}))

export const useLanguagesStore = create<LanguagesStore>((set, get) => ({
  languages: FALLBACK,
  loaded: false,
  loading: false,
  load: async () => {
    if (get().loading) return
    set({ loading: true })
    try {
      const res = await api.get<{ success: boolean; data: { languages: PublicLanguage[]; default: string } }>(
        '/languages.php',
      )
      const list = res.data?.data?.languages ?? []
      if (Array.isArray(list) && list.length > 0) {
        set({ languages: list, loaded: true, loading: false })
        return
      }
    } catch { /* ignorar; usamos fallback */ }
    set({ loaded: true, loading: false })
  },
}))
