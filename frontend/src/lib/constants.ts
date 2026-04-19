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

/** Nombres de módulos en español */
export const MODULE_NAMES: Record<string, string> = {
  wordpress: 'WordPress',
  security: 'Seguridad',
  performance: 'Rendimiento',
  seo: 'SEO',
  mobile: 'Móvil',
  infrastructure: 'Infraestructura',
  conversion: 'Conversión',
  page_health: 'Salud de Página',
  wp_internal: 'Análisis Interno',
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

/** Pasos simulados del escaneo */
export const SCAN_STEPS = [
  { id: 'fetch', label: 'Descargando página...', duration: 2000 },
  { id: 'wordpress', label: 'Detectando WordPress...', duration: 3000 },
  { id: 'security', label: 'Analizando seguridad...', duration: 3000 },
  { id: 'performance', label: 'Consultando Google PageSpeed...', duration: 8000 },
  { id: 'seo', label: 'Verificando SEO...', duration: 3000 },
  { id: 'mobile', label: 'Evaluando compatibilidad móvil...', duration: 2000 },
  { id: 'infrastructure', label: 'Analizando infraestructura...', duration: 2000 },
  { id: 'page_health', label: 'Verificando salud de página...', duration: 2000 },
  { id: 'conversion', label: 'Detectando herramientas de marketing...', duration: 2000 },
  { id: 'compile', label: 'Compilando resultados...', duration: 2000 },
]
