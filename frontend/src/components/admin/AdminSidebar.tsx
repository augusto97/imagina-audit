import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, LogOut, Shield, ExternalLink,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuth } from '@/hooks/useAuth'

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
}

export default function AdminSidebar({ onNavigate }: AdminSidebarProps) {
  const { logout } = useAuth()

  return (
    <div className="flex h-full flex-col">
      {/* Logo + branding */}
      <div className="px-5 py-5">
        <div className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8] shadow-sm">
            <Shield className="h-5 w-5 text-white" strokeWidth={2} />
          </div>
          <div>
            <p className="text-sm font-bold text-[var(--text-primary)] leading-tight">Imagina Audit</p>
            <p className="text-[10px] text-[var(--text-tertiary)] leading-tight">Panel de administración</p>
          </div>
        </div>
      </div>

      {/* Navegación agrupada */}
      <nav className="flex-1 overflow-y-auto px-3 pb-4">
        {navSections.map((section) => (
          <div key={section.title} className="mb-4">
            <p className="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              {section.title}
            </p>
            <div className="space-y-0.5">
              {section.items.map(({ to, icon: Icon, label }) => (
                <NavLink
                  key={to}
                  to={to}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    cn(
                      'group flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-medium transition-all duration-150',
                      isActive
                        ? 'bg-[var(--accent-primary)] text-white shadow-sm shadow-[var(--accent-primary)]/25'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'
                    )
                  }
                >
                  <Icon className="h-4 w-4 shrink-0" strokeWidth={1.5} />
                  {label}
                </NavLink>
              ))}
            </div>
          </div>
        ))}
      </nav>

      {/* Footer sidebar */}
      <div className="border-t border-[var(--border-default)] px-3 py-3 space-y-1">
        <a
          href="/"
          target="_blank"
          rel="noreferrer"
          className="flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-medium text-[var(--text-tertiary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-primary)] transition-all"
        >
          <ExternalLink className="h-4 w-4" strokeWidth={1.5} />
          Ver herramienta pública
        </a>
        <button
          onClick={logout}
          className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-medium text-[var(--text-tertiary)] hover:bg-red-50 hover:text-red-500 transition-all cursor-pointer"
        >
          <LogOut className="h-4 w-4" strokeWidth={1.5} />
          Cerrar sesión
        </button>
      </div>
    </div>
  )
}
