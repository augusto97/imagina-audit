import { create } from 'zustand'
import { getConfig } from '@/lib/api'
import { DEFAULT_CONFIG } from '@/lib/constants'

export interface HomeCms {
  seoTitle: string
  seoDescription: string
  seoOgImage: string
  heroHeadline: string
  heroSubheadline: string
  formButtonText: string
  formMicrocopy: string
  featuresTitle: string
  trustText: string
}

export interface FormCms {
  placeholderUrl: string
  placeholderName: string
  placeholderEmail: string
  placeholderWhatsapp: string
}

export interface HeaderCms {
  compareText: string
  externalText: string
  externalUrl: string
}

export interface FooterCms {
  tagline: string
  experienceText: string
  privacyUrl: string
  privacyText: string
}

export interface PublicConfig {
  companyName: string
  companyUrl: string
  companyWhatsapp: string
  companyEmail: string
  companyPlansUrl: string
  logoUrl: string
  logoCollapsedUrl: string
  faviconUrl: string
  brandPrimaryColor: string
  ctaTitle: string
  ctaDescription: string
  ctaButtonWhatsappText: string
  ctaButtonPlansText: string
  plans: Array<{ name: string; price: string; currency: string }>
  salesMessages: Record<string, string>
  leadCapture: LeadCaptureCfg
  home: HomeCms
  form: FormCms
  header: HeaderCms
  footer: FooterCms
}

export interface LeadCaptureCfg {
  mode: 'upfront' | 'gated'
  requireEmail: boolean
  requireName: boolean
  requireWhatsapp: boolean
}

const DEFAULT_HOME: HomeCms = {
  seoTitle: 'Auditoría WordPress gratuita · Imagina Audit',
  seoDescription: 'Analiza tu sitio WordPress en 30 segundos. Seguridad, rendimiento, SEO y más.',
  seoOgImage: '',
  heroHeadline: 'Auditoría Gratuita de tu WordPress',
  heroSubheadline: 'Descubre en 30 segundos qué tan seguro, rápido y optimizado está tu sitio web',
  formButtonText: 'Auditar Mi Sitio Gratis',
  formMicrocopy: 'Sin instalar nada · 100% externo · Resultados en 30 seg',
  featuresTitle: 'Analizamos 8 áreas clave de tu sitio',
  trustText: 'Con la experiencia de 15 años de maestría exclusiva en WordPress',
}

const DEFAULT_FORM: FormCms = {
  placeholderUrl: 'https://tusitio.com',
  placeholderName: 'Tu nombre',
  placeholderEmail: 'tu@email.com',
  placeholderWhatsapp: '+57...',
}

const DEFAULT_HEADER: HeaderCms = {
  compareText: 'Comparar',
  externalText: 'imaginawp.com',
  externalUrl: 'https://imaginawp.com',
}

const DEFAULT_FOOTER: FooterCms = {
  tagline: 'Especialistas exclusivos en WordPress',
  experienceText: '15 años de experiencia',
  privacyUrl: '',
  privacyText: 'Política de privacidad',
}

// Cache de branding crítico (logo + color) en localStorage para evitar
// el FOUC: antes el primer render usaba el logo default de la app, y al
// llegar la respuesta de /api/config.php se sustituía por el real (flash
// visible). Cacheamos esos campos para que el primer render ya use los
// valores reales del último fetch exitoso.
const CACHE_KEY = 'imagina_branding_cache_v1'
type BrandingCache = Pick<PublicConfig, 'logoUrl' | 'logoCollapsedUrl' | 'faviconUrl' | 'brandPrimaryColor' | 'companyName'>

function readBrandingCache(): Partial<BrandingCache> {
  if (typeof window === 'undefined') return {}
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as Partial<BrandingCache>
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch { return {} }
}

function writeBrandingCache(cfg: PublicConfig) {
  if (typeof window === 'undefined') return
  try {
    const payload: BrandingCache = {
      logoUrl: cfg.logoUrl,
      logoCollapsedUrl: cfg.logoCollapsedUrl,
      faviconUrl: cfg.faviconUrl,
      brandPrimaryColor: cfg.brandPrimaryColor,
      companyName: cfg.companyName,
    }
    localStorage.setItem(CACHE_KEY, JSON.stringify(payload))
  } catch { /* private mode / quota */ }
}

const cachedBranding = readBrandingCache()

const INITIAL: PublicConfig = {
  ...DEFAULT_CONFIG,
  logoUrl: cachedBranding.logoUrl ?? '',
  logoCollapsedUrl: cachedBranding.logoCollapsedUrl ?? '',
  faviconUrl: cachedBranding.faviconUrl ?? '',
  brandPrimaryColor: cachedBranding.brandPrimaryColor ?? '#3B82F6',
  companyName: cachedBranding.companyName ?? DEFAULT_CONFIG.companyName,
  home: DEFAULT_HOME,
  form: DEFAULT_FORM,
  header: DEFAULT_HEADER,
  footer: DEFAULT_FOOTER,
}

interface ConfigStore {
  config: PublicConfig
  loaded: boolean
  reload: () => Promise<void>
}

/**
 * Aplica el color primario y el favicon al DOM.
 * Se llama en cada carga del config — así los cambios del admin
 * se reflejan al instante sin refrescar la página.
 */
function applyBrandingToDocument(cfg: PublicConfig) {
  if (typeof document === 'undefined') return

  if (cfg.brandPrimaryColor) {
    document.documentElement.style.setProperty('--accent-primary', cfg.brandPrimaryColor)
    document.documentElement.style.setProperty('--accent-hover', cfg.brandPrimaryColor)
  }

  if (cfg.faviconUrl) {
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.href = cfg.faviconUrl
  }

  if (cfg.home?.seoTitle) document.title = cfg.home.seoTitle

  if (cfg.home?.seoDescription) {
    let meta = document.querySelector<HTMLMetaElement>('meta[name="description"]')
    if (!meta) {
      meta = document.createElement('meta')
      meta.name = 'description'
      document.head.appendChild(meta)
    }
    meta.content = cfg.home.seoDescription
  }

  if (cfg.home?.seoOgImage) {
    let og = document.querySelector<HTMLMetaElement>('meta[property="og:image"]')
    if (!og) {
      og = document.createElement('meta')
      og.setAttribute('property', 'og:image')
      document.head.appendChild(og)
    }
    og.content = cfg.home.seoOgImage
  }
}

// Aplicar el branding cacheado al DOM antes del primer render — así el
// color primario y el favicon no parpadean entre el default y el real.
if (cachedBranding.brandPrimaryColor || cachedBranding.faviconUrl) {
  applyBrandingToDocument(INITIAL)
}

export const useConfigStore = create<ConfigStore>((set) => ({
  config: INITIAL,
  loaded: false,
  reload: async () => {
    try {
      const data = await getConfig() as unknown as Partial<PublicConfig>
      const merged: PublicConfig = {
        ...INITIAL,
        ...data,
        home:   { ...DEFAULT_HOME,   ...(data.home   ?? {}) },
        form:   { ...DEFAULT_FORM,   ...(data.form   ?? {}) },
        header: { ...DEFAULT_HEADER, ...(data.header ?? {}) },
        footer: { ...DEFAULT_FOOTER, ...(data.footer ?? {}) },
      }
      applyBrandingToDocument(merged)
      writeBrandingCache(merged)
      set({ config: merged, loaded: true })
    } catch {
      set({ loaded: true })
    }
  },
}))
