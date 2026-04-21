import { NavLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, Shield, ShieldCheck, Server, Archive, Activity, Palette, Home, Package, Languages,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import { useConfigStore } from '@/store/configStore'

// Estructura declarativa: cada item tiene un labelKey que se resuelve vía
// useTranslation en el render. Así puedes añadir un módulo nuevo con solo
// 2 líneas (catalog entry + clave en locales/*).
const NAV_SECTIONS = [
  {
    titleKey: 'nav.general_section',
    items: [
      { to: '/admin/dashboard', icon: LayoutDashboard, labelKey: 'nav.dashboard' },
      { to: '/admin/leads',     icon: Users,           labelKey: 'nav.leads' },
    ],
  },
  {
    titleKey: 'nav.config_section',
    items: [
      { to: '/admin/settings',  icon: Settings,           labelKey: 'nav.general_settings' },
      { to: '/admin/branding',  icon: Palette,            labelKey: 'nav.branding' },
      { to: '/admin/home',      icon: Home,               labelKey: 'nav.home_cms' },
      { to: '/admin/messages',  icon: MessageSquare,      labelKey: 'nav.messages' },
      { to: '/admin/plans',     icon: CreditCard,         labelKey: 'nav.plans' },
      { to: '/admin/scoring',   icon: SlidersHorizontal,  labelKey: 'nav.scoring' },
      { to: '/admin/queue',     icon: Server,             labelKey: 'nav.queue' },
      { to: '/admin/retention',    icon: Archive,            labelKey: 'nav.retention' },
      { to: '/admin/translations', icon: Languages,          labelKey: 'nav.translations' },
      { to: '/admin/health',       icon: Activity,           labelKey: 'nav.health' },
    ],
  },
  {
    titleKey: 'nav.security_section',
    items: [
      { to: '/admin/security',        icon: ShieldCheck, labelKey: 'nav.twofa' },
      { to: '/admin/vulnerabilities', icon: ShieldAlert, labelKey: 'nav.vulnerabilities' },
      { to: '/admin/plugin-vault',    icon: Package,     labelKey: 'nav.plugin_vault' },
    ],
  },
] as const

interface AdminSidebarProps {
  onNavigate?: () => void
  collapsed?: boolean
}

export default function AdminSidebar({ onNavigate, collapsed = false }: AdminSidebarProps) {
  const { t } = useTranslation()
  const { logoUrl, logoCollapsedUrl, companyName } = useConfigStore((s) => s.config)
  const displayLogo = collapsed ? (logoCollapsedUrl || logoUrl) : logoUrl

  return (
    <div className="flex h-full flex-col">
      {/* Logo */}
      <div className={cn("h-11 flex items-center border-b border-[#e5e5e5]", collapsed ? "px-2 justify-center" : "px-4")}>
        {displayLogo ? (
          <img
            src={displayLogo}
            alt={companyName || 'Logo'}
            className={collapsed ? "h-7 w-7 object-contain" : "h-7 max-w-[160px] object-contain"}
          />
        ) : (
          <div className="flex items-center gap-2">
            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8]">
              <Shield className="h-4 w-4 text-white" strokeWidth={2} />
            </div>
            {!collapsed && (
              <span className="text-[13px] font-semibold text-[#333]">{companyName || 'Imagina Audit'}</span>
            )}
          </div>
        )}
      </div>

      {/* Nav */}
      <nav className={cn("flex-1 overflow-y-auto", collapsed ? "px-1.5 py-3" : "px-2 py-2")}>
        {NAV_SECTIONS.map((section, sectionIdx) => (
          <div key={section.titleKey} className={collapsed ? (sectionIdx > 0 ? "mt-3" : "") : "mb-2"}>
            {!collapsed && (
              <p className="mb-0.5 px-2 pt-2 text-[11px] font-medium text-[#999] uppercase tracking-wide">
                {t(section.titleKey)}
              </p>
            )}
            <div className={collapsed ? "space-y-0.5" : "space-y-px"}>
              {section.items.map(({ to, icon: Icon, labelKey }) => {
                const label = t(labelKey)
                const link = (
                  <NavLink
                    key={to}
                    to={to}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'group flex items-center rounded text-[13px] transition-colors',
                        collapsed ? 'justify-center h-9 w-9 mx-auto' : 'gap-2 px-2 py-1.5',
                        isActive
                          ? 'bg-[#e8e8e8] text-[#111] font-medium'
                          : 'text-[#555] hover:bg-[#ebebeb] hover:text-[#111]'
                      )
                    }
                  >
                    <Icon className={cn("shrink-0", collapsed ? "h-[18px] w-[18px]" : "h-4 w-4")} strokeWidth={1.5} />
                    {!collapsed && label}
                  </NavLink>
                )

                if (collapsed) {
                  return (
                    <Tooltip key={to}>
                      <TooltipTrigger asChild>{link}</TooltipTrigger>
                      <TooltipContent side="right">{label}</TooltipContent>
                    </Tooltip>
                  )
                }
                return link
              })}
            </div>
          </div>
        ))}
      </nav>
    </div>
  )
}
