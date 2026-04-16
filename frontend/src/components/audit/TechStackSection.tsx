import { motion } from 'framer-motion'
import {
  Server, Blocks, Layout, ShoppingCart, Zap, Search,
  ShieldCheck, Code2, Palette, Type, Globe, BarChart3, Cpu, Wifi,
  MapPin, Calendar,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface TechStack {
  server?: string | null
  cms?: string | null
  pageBuilder?: string[]
  ecommerce?: string[]
  cachePlugin?: string[]
  seoPlugin?: string[]
  securityPlugin?: string[]
  jsLibraries?: string[]
  cssFramework?: string[]
  fonts?: string[]
  cdn?: string | null
  analytics?: string[]
  phpVersion?: string | null
  httpProtocol?: string | null
}

interface TechStackSectionProps {
  techStack: TechStack
}

interface TechCategory {
  label: string
  icon: React.ElementType
  values: string[]
  color: string
}

export default function TechStackSection({ techStack }: TechStackSectionProps) {
  if (!techStack) return null

  // Construir categorías con sus valores
  const categories: TechCategory[] = []

  const add = (label: string, icon: React.ElementType, values: (string | null | undefined)[] | string | null | undefined, color: string) => {
    const arr = Array.isArray(values) ? values.filter(Boolean) as string[] : (values ? [values] : [])
    if (arr.length > 0) {
      categories.push({ label, icon, values: arr, color })
    }
  }

  add('Servidor', Server, techStack.server, 'bg-slate-100 text-slate-600')
  add('CMS', Blocks, techStack.cms, 'bg-[var(--accent-primary)]/10 text-[var(--accent-primary)]')
  add('Page Builder', Layout, techStack.pageBuilder, 'bg-violet-50 text-violet-600')
  add('Ecommerce', ShoppingCart, techStack.ecommerce, 'bg-emerald-50 text-emerald-600')
  add('Cache', Zap, techStack.cachePlugin, 'bg-amber-50 text-amber-600')
  add('SEO', Search, techStack.seoPlugin, 'bg-blue-50 text-blue-600')
  add('Seguridad', ShieldCheck, techStack.securityPlugin, 'bg-red-50 text-red-600')
  add('JavaScript', Code2, techStack.jsLibraries, 'bg-yellow-50 text-yellow-700')
  add('CSS Framework', Palette, techStack.cssFramework, 'bg-pink-50 text-pink-600')
  add('Fuentes', Type, techStack.fonts, 'bg-indigo-50 text-indigo-600')
  add('CDN', Globe, techStack.cdn, 'bg-cyan-50 text-cyan-600')
  add('Analytics', BarChart3, techStack.analytics, 'bg-teal-50 text-teal-600')
  add('PHP', Cpu, techStack.phpVersion, 'bg-purple-50 text-purple-600')
  add('Protocolo HTTP', Wifi, techStack.httpProtocol, 'bg-gray-100 text-gray-600')

  // Hosting info
  const hi = (techStack as Record<string, unknown>).hostingInfo as Record<string, unknown> | undefined
  if (hi) {
    const hostingVals: string[] = []
    if (hi.provider) hostingVals.push(String(hi.provider))
    if (hi.ip) hostingVals.push(String(hi.ip))
    if (hi.city || hi.country) hostingVals.push([hi.city, hi.country].filter(Boolean).join(', '))
    add('Hosting', MapPin, hostingVals, 'bg-orange-50 text-orange-600')
  }

  // Domain info
  const di = (techStack as Record<string, unknown>).domainInfo as Record<string, unknown> | undefined
  if (di) {
    const domainVals: string[] = []
    if (di.registrar) domainVals.push(String(di.registrar))
    if (di.expiryDate) {
      const days = di.daysUntilExpiry as number | null
      domainVals.push(`Expira: ${String(di.expiryDate)}${days !== null && days !== undefined ? ` (${days}d)` : ''}`)
    }
    if (di.createdDate) domainVals.push(`Desde: ${String(di.createdDate)}`)
    add('Dominio', Calendar, domainVals, 'bg-lime-50 text-lime-700')
  }

  if (categories.length === 0) return null

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
    >
      <Card className="border-0 shadow-sm overflow-hidden">
        <CardHeader className="pb-3">
          <div className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--accent-primary)]/10">
              <Code2 className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.5} />
            </div>
            <div>
              <CardTitle className="text-base">Stack Tecnológico</CardTitle>
              <p className="text-xs text-[var(--text-tertiary)] mt-0.5">Tecnologías detectadas (informativo, no afecta la puntuación)</p>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((cat, i) => {
              const Icon = cat.icon
              return (
                <motion.div
                  key={cat.label}
                  initial={{ opacity: 0, scale: 0.95 }}
                  whileInView={{ opacity: 1, scale: 1 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.03 }}
                  className="rounded-xl border border-[var(--border-default)]/60 p-3"
                >
                  <div className="flex items-center gap-2 mb-2">
                    <div className={`flex h-6 w-6 items-center justify-center rounded-md ${cat.color}`}>
                      <Icon className="h-3.5 w-3.5" strokeWidth={1.5} />
                    </div>
                    <span className="text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wide">
                      {cat.label}
                    </span>
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    {cat.values.map((v) => (
                      <span
                        key={v}
                        className="inline-block rounded-lg bg-[var(--bg-tertiary)] px-2.5 py-1 text-xs font-medium text-[var(--text-primary)]"
                      >
                        {v}
                      </span>
                    ))}
                  </div>
                </motion.div>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </motion.div>
  )
}
