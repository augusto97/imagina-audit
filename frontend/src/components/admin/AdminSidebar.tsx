import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, Shield, Server, Archive, Activity, Palette, Home, Package,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import { useConfigStore } from '@/store/configStore'

const navSections = [
  {
    title: 'General',
    items: [
      { to: '/admin/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
      { to: '/admin/leads', icon: Users, label: 'Leads y Auditorías' },
    ],
  },
  {
    title: 'Configuración',
    items: [
      { to: '/admin/settings', icon: Settings, label: 'General' },
      { to: '/admin/branding', icon: Palette, label: 'Branding' },
      { to: '/admin/home', icon: Home, label: 'Home pública' },
      { to: '/admin/messages', icon: MessageSquare, label: 'Textos y Mensajes' },
      { to: '/admin/plans', icon: CreditCard, label: 'Planes y Precios' },
      { to: '/admin/scoring', icon: SlidersHorizontal, label: 'Scoring' },
      { to: '/admin/queue', icon: Server, label: 'Cola de Auditorías' },
      { to: '/admin/retention', icon: Archive, label: 'Retención de Informes' },
      { to: '/admin/health', icon: Activity, label: 'Estado del Sistema' },
    ],
  },
  {
    title: 'Seguridad',
    items: [
      { to: '/admin/vulnerabilities', icon: ShieldAlert, label: 'Vulnerabilidades' },
      { to: '/admin/plugin-vault',    icon: Package,     label: 'Plugin Vault' },
    ],
  },
]

interface AdminSidebarProps {
  onNavigate?: () => void
  collapsed?: boolean
}

export default function AdminSidebar({ onNavigate, collapsed = false }: AdminSidebarProps) {
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
        {navSections.map((section, sectionIdx) => (
          <div key={section.title} className={collapsed ? (sectionIdx > 0 ? "mt-3" : "") : "mb-2"}>
            {!collapsed && (
              <p className="mb-0.5 px-2 pt-2 text-[11px] font-medium text-[#999] uppercase tracking-wide">
                {section.title}
              </p>
            )}
            <div className={collapsed ? "space-y-0.5" : "space-y-px"}>
              {section.items.map(({ to, icon: Icon, label }) => {
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
