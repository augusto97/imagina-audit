// Constantes de la aplicación

export const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'

/** Configuración por defecto del branding */
export const DEFAULT_CONFIG = {
  companyName: 'Imagina WP',
  companyUrl: 'https://imaginawp.com',
  companyWhatsapp: '+573001234567',
  companyEmail: 'hola@imaginawp.com',
  companyPlansUrl: 'https://imaginawp.com/mensualidad',
  logoUrl: '',
  ctaTitle: 'Todos estos problemas tienen solución',
  ctaDescription: 'En Imagina WP somos especialistas exclusivos en WordPress con más de 15 años de experiencia.',
  ctaButtonWhatsappText: 'Hablar con un Experto por WhatsApp',
  ctaButtonPlansText: 'Ver Planes y Precios',
  plans: [
    { name: 'Basic', price: '97', currency: 'USD' },
    { name: 'Pro', price: '197', currency: 'USD' },
    { name: 'Custom', price: 'Cotizar', currency: 'USD' },
  ],
  salesMessages: {} as Record<string, string>,
  leadCapture: {
    mode: 'upfront' as 'upfront' | 'gated',
    requireEmail: true,
    requireName: false,
    requireWhatsapp: false,
  },
}

/** Iconos de módulos (nombres de Lucide) */
export const MODULE_ICONS: Record<string, string> = {
  wordpress: 'blocks',
  security: 'shield',
  performance: 'gauge',
  seo: 'search',
  mobile: 'smartphone',
  infrastructure: 'server',
  conversion: 'bar-chart-3',
  page_health: 'activity',
  wp_internal: 'database',
}

/**
 * Nombres de módulos por defecto (fallback). Para el grid de la home y
 * los settings de mensajes preferimos resolver `public.module_name_<id>`
 * desde i18n; este record queda como red de seguridad si la key no
 * existe (idiomas dinámicos sin esa clave todavía).
 */
export const MODULE_NAMES: Record<string, string> = {
  wordpress: 'WordPress',
  security: 'Security',
  performance: 'Performance',
  seo: 'SEO',
  mobile: 'Mobile',
  infrastructure: 'Infrastructure',
  conversion: 'Conversion',
  page_health: 'Page health',
  wp_internal: 'Internal analysis',
}

/** Emojis de módulos para el feature grid */
export const MODULE_EMOJIS: Record<string, string> = {
  wordpress: '🧩',
  security: '🛡️',
  performance: '⚡',
  seo: '🔍',
  mobile: '📱',
  infrastructure: '🖥️',
  conversion: '📊',
  page_health: '🩺',
  wp_internal: '🗄️',
}

