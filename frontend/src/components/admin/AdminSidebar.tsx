import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, LogOut,
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
      <div className={cn("py-3 border-b border-gray-200", collapsed ? "px-2 flex justify-center" : "px-4")}>
        <div className="flex items-center gap-2">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-orange-500 text-white text-xs font-bold">
            IA
          </div>
          {!collapsed && (
            <span className="text-sm font-semibold text-gray-900">Imagina Audit</span>
          )}
        </div>
      </div>

      {/* Nav */}
      <nav className={cn("flex-1 overflow-y-auto py-2", collapsed ? "px-1.5" : "px-2")}>
        {navSections.map((section) => (
          <div key={section.title} className="mb-2">
            {!collapsed && (
              <p className="mb-0.5 px-2 pt-2 text-[11px] font-medium text-gray-400 uppercase tracking-wide">
                {section.title}
              </p>
            )}
            {collapsed && <div className="my-1 mx-1 border-t border-gray-100" />}
            <div className="space-y-px">
              {section.items.map(({ to, icon: Icon, label }) => {
                const link = (
                  <NavLink
                    key={to}
                    to={to}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'group flex items-center rounded text-[13px] transition-colors',
                        collapsed ? 'justify-center p-2' : 'gap-2 px-2 py-1.5',
                        isActive
                          ? 'bg-gray-100 text-gray-900 font-medium'
                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
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
      <div className={cn("border-t border-gray-200 py-1.5", collapsed ? "px-1.5" : "px-2")}>
        {collapsed ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <button onClick={logout} className="flex w-full items-center justify-center rounded p-2 text-gray-400 hover:bg-gray-50 hover:text-red-500 transition-colors cursor-pointer">
                <LogOut className="h-4 w-4" strokeWidth={1.5} />
              </button>
            </TooltipTrigger>
            <TooltipContent side="right">Cerrar sesión</TooltipContent>
          </Tooltip>
        ) : (
          <button
            onClick={logout}
            className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-[13px] text-gray-500 hover:bg-gray-50 hover:text-red-500 transition-colors cursor-pointer"
          >
            <LogOut className="h-4 w-4" strokeWidth={1.5} />
            Cerrar sesión
          </button>
        )}
      </div>
    </div>
  )
}
