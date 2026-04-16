import { useState } from 'react'
import { Menu, X, PanelLeftClose, PanelLeft, ExternalLink } from 'lucide-react'
import { motion, AnimatePresence } from 'framer-motion'
import { Button } from '@/components/ui/button'
import AdminSidebar from './AdminSidebar'

interface AdminLayoutProps {
  children: React.ReactNode
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)

  return (
    <div className="flex h-screen bg-white">
      {/* Sidebar desktop — dark */}
      <aside
        className={`hidden md:flex flex-col shrink-0 bg-[#1a1a2e] transition-all duration-300 ${collapsed ? 'w-[60px]' : 'w-[220px]'}`}
      >
        <div className="flex-1 overflow-y-auto overflow-x-hidden">
          <AdminSidebar collapsed={collapsed} />
        </div>
        <div className="border-t border-white/10 p-2">
          <button
            onClick={() => setCollapsed(!collapsed)}
            className={`flex items-center rounded-md text-xs text-gray-400 hover:text-white hover:bg-white/10 transition-colors w-full cursor-pointer ${collapsed ? 'justify-center p-2' : 'gap-2 px-3 py-2'}`}
          >
            {collapsed ? <PanelLeft className="h-3.5 w-3.5" strokeWidth={1.5} /> : <PanelLeftClose className="h-3.5 w-3.5" strokeWidth={1.5} />}
            {!collapsed && <span>Colapsar</span>}
          </button>
        </div>
      </aside>

      {/* Sidebar mobile overlay */}
      <AnimatePresence>
        {mobileOpen && (
          <div className="fixed inset-0 z-50 md:hidden">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-black/50"
              onClick={() => setMobileOpen(false)}
            />
            <motion.aside
              initial={{ x: -240 }}
              animate={{ x: 0 }}
              exit={{ x: -240 }}
              transition={{ type: 'spring', damping: 25, stiffness: 300 }}
              className="relative z-10 h-full w-[220px] bg-[#1a1a2e] shadow-2xl"
            >
              <button
                onClick={() => setMobileOpen(false)}
                className="absolute right-3 top-4 rounded-md p-1 text-gray-400 hover:text-white cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
              <AdminSidebar onNavigate={() => setMobileOpen(false)} />
            </motion.aside>
          </div>
        )}
      </AnimatePresence>

      {/* Main content */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Top bar — always visible */}
        <header className="flex h-12 items-center justify-between border-b border-[var(--border-default)] bg-white px-4 sm:px-6">
          <div className="flex items-center gap-3">
            <button onClick={() => setMobileOpen(true)} className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 md:hidden cursor-pointer">
              <Menu className="h-5 w-5" strokeWidth={1.5} />
            </button>
            <span className="text-sm font-semibold text-gray-900">Imagina Audit</span>
            <span className="text-xs text-gray-400">Panel de administración</span>
          </div>
          <div className="flex items-center gap-2">
            <a href="/" target="_blank" rel="noreferrer">
              <Button variant="ghost" size="sm" className="text-gray-500 text-xs h-8">
                <ExternalLink className="h-3.5 w-3.5" strokeWidth={1.5} />
                <span className="hidden sm:inline">Ver herramienta</span>
              </Button>
            </a>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto bg-[#fafafa]">
          <div className="p-5 sm:p-6">
            {children}
          </div>
        </main>
      </div>
    </div>
  )
}
