import { useState } from 'react'
import { Menu, X, Shield } from 'lucide-react'
import { motion, AnimatePresence } from 'framer-motion'
import AdminSidebar from './AdminSidebar'

interface AdminLayoutProps {
  children: React.ReactNode
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <div className="flex h-screen bg-[#F4F6F8]">
      {/* Sidebar desktop */}
      <aside className="hidden w-[260px] shrink-0 bg-white md:block overflow-y-auto shadow-[1px_0_0_var(--border-default)]">
        <AdminSidebar />
      </aside>

      {/* Sidebar mobile overlay */}
      <AnimatePresence>
        {sidebarOpen && (
          <div className="fixed inset-0 z-50 md:hidden">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-black/40 backdrop-blur-sm"
              onClick={() => setSidebarOpen(false)}
            />
            <motion.aside
              initial={{ x: -280 }}
              animate={{ x: 0 }}
              exit={{ x: -280 }}
              transition={{ type: 'spring', damping: 25, stiffness: 300 }}
              className="relative z-10 h-full w-[260px] bg-white shadow-2xl"
            >
              <button
                onClick={() => setSidebarOpen(false)}
                className="absolute right-3 top-5 rounded-lg p-1.5 text-[var(--text-tertiary)] hover:bg-[var(--bg-tertiary)] cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
              <AdminSidebar onNavigate={() => setSidebarOpen(false)} />
            </motion.aside>
          </div>
        )}
      </AnimatePresence>

      {/* Contenido principal */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Header mobile */}
        <header className="flex h-14 items-center gap-3 border-b border-[var(--border-default)] bg-white px-4 md:hidden">
          <button onClick={() => setSidebarOpen(true)} className="rounded-xl p-2 text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] active:scale-95 transition-all cursor-pointer">
            <Menu className="h-5 w-5" strokeWidth={1.5} />
          </button>
          <div className="flex items-center gap-2">
            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8]">
              <Shield className="h-4 w-4 text-white" strokeWidth={2} />
            </div>
            <span className="text-sm font-bold text-[var(--text-primary)]">Admin</span>
          </div>
        </header>

        {/* Área de contenido con scroll */}
        <main className="flex-1 overflow-y-auto">
          <div className="mx-auto max-w-6xl p-5 sm:p-8">
            <motion.div
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.3 }}
            >
              {children}
            </motion.div>
          </div>
        </main>
      </div>
    </div>
  )
}
