import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Users, Settings, MessageSquare,
  CreditCard, SlidersHorizontal, ShieldAlert, LogOut, Shield,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuth } from '@/hooks/useAuth'

const navItems = [
  { to: '/admin/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/admin/leads', icon: Users, label: 'Leads y Auditorías' },
  { to: '/admin/settings', icon: Settings, label: 'Configuración' },
  { to: '/admin/messages', icon: MessageSquare, label: 'Textos y Mensajes' },
  { to: '/admin/plans', icon: CreditCard, label: 'Planes y Precios' },
  { to: '/admin/scoring', icon: SlidersHorizontal, label: 'Scoring' },
  { to: '/admin/vulnerabilities', icon: ShieldAlert, label: 'Vulnerabilidades' },
]

interface AdminSidebarProps {
  onNavigate?: () => void
}

export default function AdminSidebar({ onNavigate }: AdminSidebarProps) {
  const { logout } = useAuth()

  return (
    <div className="flex h-full flex-col">
      {/* Logo */}
      <div className="flex items-center gap-2 px-4 py-5">
        <Shield className="h-6 w-6 text-[var(--accent-primary)]" strokeWidth={1.5} />
        <span className="text-base font-bold text-[var(--text-primary)]">
          Imagina <span className="text-[var(--accent-primary)]">Admin</span>
        </span>
      </div>

      <div className="mx-4 h-px bg-[var(--border-default)]" />

      {/* Navegación */}
      <nav className="flex-1 space-y-1 px-3 py-4">
        {navItems.map(({ to, icon: Icon, label }) => (
          <NavLink
            key={to}
            to={to}
            onClick={onNavigate}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-[var(--accent-primary)]/10 text-[var(--accent-primary)] border-l-3 border-[var(--accent-primary)]'
                  : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'
              )
            }
          >
            <Icon className="h-[18px] w-[18px] shrink-0" strokeWidth={1.5} />
            {label}
          </NavLink>
        ))}
      </nav>

      <div className="mx-4 h-px bg-[var(--border-default)]" />

      {/* Cerrar sesión */}
      <div className="px-3 py-4">
        <button
          onClick={logout}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[var(--text-secondary)] hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
        >
          <LogOut className="h-[18px] w-[18px]" strokeWidth={1.5} />
          Cerrar Sesión
        </button>
      </div>
    </div>
  )
}
