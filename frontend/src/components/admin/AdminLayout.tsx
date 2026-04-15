import { useState } from 'react'
import { Menu, X } from 'lucide-react'
import AdminSidebar from './AdminSidebar'

interface AdminLayoutProps {
  children: React.ReactNode
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <div className="flex h-screen bg-[var(--bg-secondary)]">
      {/* Sidebar desktop */}
      <aside className="hidden w-64 shrink-0 border-r border-[var(--border-default)] bg-white md:block">
        <AdminSidebar />
      </aside>

      {/* Sidebar mobile overlay */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={() => setSidebarOpen(false)} />
          <aside className="relative z-10 h-full w-64 bg-white shadow-xl">
            <button
              onClick={() => setSidebarOpen(false)}
              className="absolute right-3 top-4 rounded-lg p-1 text-[var(--text-tertiary)] hover:bg-[var(--bg-tertiary)] cursor-pointer"
            >
              <X className="h-5 w-5" />
            </button>
            <AdminSidebar onNavigate={() => setSidebarOpen(false)} />
          </aside>
        </div>
      )}

      {/* Contenido principal */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Header mobile */}
        <header className="flex h-14 items-center border-b border-[var(--border-default)] bg-white px-4 md:hidden">
          <button onClick={() => setSidebarOpen(true)} className="rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] cursor-pointer">
            <Menu className="h-5 w-5" />
          </button>
          <span className="ml-3 text-sm font-bold text-[var(--text-primary)]">
            Imagina <span className="text-[var(--accent-primary)]">Admin</span>
          </span>
        </header>

        {/* Área de contenido */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          {children}
        </main>
      </div>
    </div>
  )
}
