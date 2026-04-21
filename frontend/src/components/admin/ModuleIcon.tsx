import {
  Blocks,
  Shield,
  Gauge,
  Search,
  Smartphone,
  Server,
  BarChart3,
  Activity,
  Database,
  HelpCircle,
  type LucideIcon,
} from 'lucide-react'

const ICONS: Record<string, LucideIcon> = {
  wordpress: Blocks,
  security: Shield,
  performance: Gauge,
  seo: Search,
  mobile: Smartphone,
  infrastructure: Server,
  conversion: BarChart3,
  page_health: Activity,
  wp_internal: Database,
}

/**
 * Icono Lucide del módulo. Monocromo, mismo set que usa el sidebar.
 * Evita los emoji nativos del sistema (que se ven distinto en cada OS).
 */
export function ModuleIcon({ id, className = 'h-4 w-4' }: { id: string; className?: string }) {
  const Icon = ICONS[id] || HelpCircle
  return <Icon className={className} strokeWidth={1.75} />
}
