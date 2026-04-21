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
  home: HomeCms
  form: FormCms
  header: HeaderCms
  footer: FooterCms
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

const INITIAL: PublicConfig = {
  ...DEFAULT_CONFIG,
  logoUrl: '',
  logoCollapsedUrl: '',
  faviconUrl: '',
  brandPrimaryColor: '#3B82F6',
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
      set({ config: merged, loaded: true })
    } catch {
      set({ loaded: true })
    }
  },
}))
