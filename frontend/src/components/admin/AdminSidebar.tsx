import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, LogOut, Shield,
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
      <div className={cn("py-4 border-b border-white/10", collapsed ? "px-3 flex justify-center" : "px-4")}>
        <div className="flex items-center gap-2">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-primary)]">
            <Shield className="h-4 w-4 text-white" strokeWidth={2} />
          </div>
          {!collapsed && (
            <span className="text-sm font-semibold text-white">Imagina Audit</span>
          )}
        </div>
      </div>

      {/* Nav */}
      <nav className={cn("flex-1 overflow-y-auto py-3", collapsed ? "px-2" : "px-3")}>
        {navSections.map((section) => (
          <div key={section.title} className="mb-3">
            {!collapsed && (
              <p className="mb-1 px-2 text-[10px] font-medium uppercase tracking-widest text-gray-500">
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
                        'group flex items-center rounded-md text-[13px] font-medium transition-colors',
                        collapsed ? 'justify-center p-2' : 'gap-2.5 px-2 py-1.5',
                        isActive
                          ? 'bg-white/15 text-white'
                          : 'text-gray-400 hover:bg-white/8 hover:text-gray-200'
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

      {/* Logout */}
      <div className={cn("border-t border-white/10 py-2", collapsed ? "px-2" : "px-3")}>
        {collapsed ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <button onClick={logout} className="flex w-full items-center justify-center rounded-md p-2 text-gray-500 hover:bg-white/8 hover:text-red-400 transition-colors cursor-pointer">
                <LogOut className="h-4 w-4" strokeWidth={1.5} />
              </button>
            </TooltipTrigger>
            <TooltipContent side="right">Cerrar sesión</TooltipContent>
          </Tooltip>
        ) : (
          <button
            onClick={logout}
            className="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-[13px] font-medium text-gray-500 hover:bg-white/8 hover:text-red-400 transition-colors cursor-pointer"
          >
            <LogOut className="h-4 w-4" strokeWidth={1.5} />
            Cerrar sesión
          </button>
        )}
      </div>
    </div>
  )
}
