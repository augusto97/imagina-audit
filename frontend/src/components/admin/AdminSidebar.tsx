import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, LogOut, Shield, ExternalLink,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuth } from '@/hooks/useAuth'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'

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
      { to: '/admin/messages', icon: MessageSquare, label: 'Textos y Mensajes' },
      { to: '/admin/plans', icon: CreditCard, label: 'Planes y Precios' },
      { to: '/admin/scoring', icon: SlidersHorizontal, label: 'Scoring' },
    ],
  },
  {
    title: 'Seguridad',
    items: [
      { to: '/admin/vulnerabilities', icon: ShieldAlert, label: 'Vulnerabilidades' },
    ],
  },
]

interface AdminSidebarProps {
  onNavigate?: () => void
  collapsed?: boolean
}

export default function AdminSidebar({ onNavigate, collapsed = false }: AdminSidebarProps) {
  const { logout } = useAuth()

  return (
    <div className="flex h-full flex-col">
      {/* Logo */}
      <div className={cn("py-5", collapsed ? "px-3 flex justify-center" : "px-5")}>
        <div className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8] shadow-sm">
            <Shield className="h-5 w-5 text-white" strokeWidth={2} />
          </div>
          {!collapsed && (
            <div>
              <p className="text-sm font-bold text-[var(--text-primary)] leading-tight">Imagina Audit</p>
              <p className="text-[10px] text-[var(--text-tertiary)] leading-tight">Panel de administración</p>
            </div>
          )}
        </div>
      </div>

      {/* Nav */}
      <nav className={cn("flex-1 overflow-y-auto pb-4", collapsed ? "px-2" : "px-3")}>
        {navSections.map((section) => (
          <div key={section.title} className="mb-4">
            {!collapsed && (
              <p className="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                {section.title}
              </p>
            )}
            <div className="space-y-0.5">
              {section.items.map(({ to, icon: Icon, label }) => {
                const link = (
                  <NavLink
                    key={to}
                    to={to}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'group flex items-center rounded-lg text-[13px] font-medium transition-all duration-150',
                        collapsed ? 'justify-center p-2.5' : 'gap-2.5 px-3 py-2',
                        isActive
                          ? 'bg-[var(--accent-primary)] text-white shadow-sm shadow-[var(--accent-primary)]/25'
                          : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'
                      )
                    }
                  >
                    <Icon className="h-4 w-4 shrink-0" strokeWidth={1.5} />
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

      {/* Footer */}
      <div className={cn("border-t border-[var(--border-default)] py-3", collapsed ? "px-2" : "px-3")}>
        {collapsed ? (
          <div className="space-y-1">
            <Tooltip>
              <TooltipTrigger asChild>
                <a href="/" target="_blank" rel="noreferrer" className="flex items-center justify-center rounded-lg p-2.5 text-[var(--text-tertiary)] hover:bg-[var(--bg-tertiary)] transition-all">
                  <ExternalLink className="h-4 w-4" strokeWidth={1.5} />
                </a>
              </TooltipTrigger>
              <TooltipContent side="right">Ver herramienta pública</TooltipContent>
            </Tooltip>
            <Tooltip>
              <TooltipTrigger asChild>
                <button onClick={logout} className="flex w-full items-center justify-center rounded-lg p-2.5 text-[var(--text-tertiary)] hover:bg-red-50 hover:text-red-500 transition-all cursor-pointer">
                  <LogOut className="h-4 w-4" strokeWidth={1.5} />
                </button>
              </TooltipTrigger>
              <TooltipContent side="right">Cerrar sesión</TooltipContent>
            </Tooltip>
          </div>
        ) : (
          <div className="space-y-1">
            <a
              href="/"
              target="_blank"
              rel="noreferrer"
              className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-[var(--text-tertiary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-primary)] transition-all"
            >
              <ExternalLink className="h-4 w-4" strokeWidth={1.5} />
              Ver herramienta pública
            </a>
            <button
              onClick={logout}
              className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-[var(--text-tertiary)] hover:bg-red-50 hover:text-red-500 transition-all cursor-pointer"
            >
              <LogOut className="h-4 w-4" strokeWidth={1.5} />
              Cerrar sesión
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
