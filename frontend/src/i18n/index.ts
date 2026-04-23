import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import en from './locales/en.json'
import es from './locales/es.json'

/**
 * i18next initialization.
 *
 * Bundles base (en, es) vienen empaquetados en el JS — garantizan que la UI
 * funcione sin depender de la red en el arranque. El resto (pt, fr, de, it,
 * o cualquier idioma que el admin registre) se carga dinámicamente desde
 * `/api/frontend-locales.php?lang=X` vía `loadLanguageBundle()` y se inyecta
 * con `i18n.addResourceBundle(...)` antes del changeLanguage.
 *
 * Orden de detección:
 *     1. localStorage   (selección explícita del usuario)
 *     2. navigator       (idioma del browser)
 *     3. htmlTag        (lang="" del <html>)
 * Fallback: 'en' (audiencia principal de CodeCanyon).
 *
 * La lista de idiomas disponibles la gestiona el admin desde el panel de
 * Idiomas; `LanguageSwitcher` la consume del store `useLanguagesStore`.
 */

export type SupportedLanguage = string

// Lista mínima que siempre está disponible — se usa como fallback si la
// llamada a /api/languages falla en el boot.
export const FALLBACK_LANGUAGES: SupportedLanguage[] = ['en', 'es']

// Mapa de nombres nativos para códigos comunes. Si el backend no provee un
// nombre nativo custom, caemos a este (o al código en mayúsculas).
export const COMMON_LANGUAGE_NAMES: Record<string, string> = {
  en: 'English',
  es: 'Español',
  pt: 'Português',
  fr: 'Français',
  de: 'Deutsch',
  it: 'Italiano',
  nl: 'Nederlands',
  ca: 'Català',
  gl: 'Galego',
  eu: 'Euskara',
  ja: '日本語',
  zh: '中文',
  ko: '한국어',
  ru: 'Русский',
  pl: 'Polski',
  tr: 'Türkçe',
}

/**
 * Baja dinámicamente el bundle de un idioma y lo inyecta en i18next. Los
 * idiomas base (en, es) ya vienen empaquetados, pero aun así los refrescamos
 * desde la API para que las traducciones editadas por el admin reemplacen
 * al bundle estático sin esperar a un rebuild del frontend.
 */
export async function loadLanguageBundle(lang: string): Promise<boolean> {
  try {
    const { default: api } = await import('@/lib/api')
    const res = await api.get<{ success: boolean; data: { lang: string; bundle: Record<string, unknown> } }>(
      '/frontend-locales.php',
      { params: { lang } },
    )
    const bundle = res.data?.data?.bundle
    if (bundle && typeof bundle === 'object') {
      i18n.addResourceBundle(lang, 'translation', bundle, true, true)
      return true
    }
  } catch { /* silent — caemos al bundle estático o al fallback */ }
  return false
}

/**
 * Cambia el idioma activo. Si el idioma no está bundleado aún, lo baja
 * primero desde la API — así no hay un flash de strings en inglés.
 */
export async function changeLanguageSafe(lang: string): Promise<void> {
  const hasResources = i18n.hasResourceBundle(lang, 'translation')
  if (!hasResources) {
    await loadLanguageBundle(lang)
  }
  await i18n.changeLanguage(lang)
}

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      en: { translation: en },
      es: { translation: es },
    },
    fallbackLng: 'en',
    // Sin supportedLngs hardcodeado — i18next acepta cualquier lang code,
    // la validación real vive en el backend (Languages::find).
    interpolation: { escapeValue: false }, // React ya escapa
    detection: {
      order: ['localStorage', 'navigator', 'htmlTag'],
      caches: ['localStorage'],
      lookupLocalStorage: 'imagina_lang',
    },
    // En dev, log de missing keys para detectar strings sin traducir
    saveMissing: import.meta.env.DEV,
    missingKeyHandler: import.meta.env.DEV
      ? (lngs, _ns, key) => console.warn(`[i18n] missing key "${key}" for ${lngs.join(',')}`)
      : undefined,
  })

// Al arrancar, refresca el bundle del idioma detectado desde la API. Esto
// asegura que las overrides editadas desde el panel se apliquen aunque el
// usuario no cambie el idioma. Lo ignoramos en server-side o fetch-fail.
if (typeof window !== 'undefined') {
  const current = (i18n.resolvedLanguage || i18n.language || 'en').slice(0, 2)
  // defer un tick para no bloquear el render inicial
  setTimeout(() => { void loadLanguageBundle(current) }, 0)
}

export default i18n
