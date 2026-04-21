import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import en from './locales/en.json'
import es from './locales/es.json'

/**
 * i18next initialization.
 *
 * - Bundles shipped: en (source of truth), es.
 *   pt/fr/de/it se generarán con IA en la fase AI — inicialmente caen
 *   al fallback en.
 * - Orden de detección del idioma:
 *     1. localStorage   (selección explícita del usuario)
 *     2. navigator       (idioma del browser)
 *     3. htmlTag        (lang="" del <html>)
 *   fallback = en (audiencia principal de CodeCanyon).
 * - DB overrides: al cargar el /api/config, mergeamos los overrides en
 *   los recursos de i18next con i18n.addResources(lang, ns, obj). Esto
 *   se hace en configStore.reload() (se implementa en P3).
 */

export type SupportedLanguage = 'en' | 'es' | 'pt' | 'fr' | 'de' | 'it'

export const SUPPORTED_LANGUAGES: SupportedLanguage[] = ['en', 'es', 'pt', 'fr', 'de', 'it']

export const LANGUAGE_NAMES: Record<SupportedLanguage, string> = {
  en: 'English',
  es: 'Español',
  pt: 'Português',
  fr: 'Français',
  de: 'Deutsch',
  it: 'Italiano',
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
    supportedLngs: SUPPORTED_LANGUAGES,
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

export default i18n
