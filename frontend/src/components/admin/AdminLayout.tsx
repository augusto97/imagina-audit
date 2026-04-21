import { useState } from 'react'
import { Menu, X, PanelLeftClose, PanelLeft, ExternalLink, LogOut, Settings } from 'lucide-react'
import { motion, AnimatePresence } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/hooks/useAuth'
import { LanguageSwitcher } from '@/components/LanguageSwitcher'
import AdminSidebar from './AdminSidebar'

interface AdminLayoutProps {
  children: React.ReactNode
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const { logout } = useAuth()
  const navigate = useNavigate()

  return (
    <div className="flex h-screen bg-white">
      {/* Sidebar desktop */}
      <aside
        className={`hidden md:flex flex-col shrink-0 bg-[#f6f6f6] border-r border-[#e5e5e5] transition-all duration-300 ${collapsed ? 'w-[60px]' : 'w-[210px]'}`}
      >
        <div className="flex-1 overflow-y-auto overflow-x-hidden">
          <AdminSidebar collapsed={collapsed} />
        </div>
        <div className="border-t border-[#e5e5e5] p-1.5">
          <button
            onClick={() => setCollapsed(!collapsed)}
            className={`flex items-center rounded text-xs text-[#999] hover:text-[#404040] hover:bg-[#ebebeb] transition-colors w-full cursor-pointer ${collapsed ? 'justify-center p-2' : 'gap-2 px-3 py-1.5'}`}
          >
            {collapsed ? <PanelLeft className="h-3.5 w-3.5" /> : <PanelLeftClose className="h-3.5 w-3.5" />}
            {!collapsed && <span>Colapsar</span>}
          </button>
        </div>
      </aside>

      {/* Sidebar mobile */}
      <AnimatePresence>
        {mobileOpen && (
          <div className="fixed inset-0 z-50 md:hidden">
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="absolute inset-0 bg-black/30" onClick={() => setMobileOpen(false)} />
            <motion.aside initial={{ x: -220 }} animate={{ x: 0 }} exit={{ x: -220 }} transition={{ type: 'spring', damping: 25, stiffness: 300 }} className="relative z-10 h-full w-[210px] bg-[#f6f6f6] border-r border-[#e5e5e5] shadow-lg">
              <button onClick={() => setMobileOpen(false)} className="absolute right-2 top-3 rounded p-1 text-[#999] hover:text-[#404040] cursor-pointer">
                <X className="h-4 w-4" />
              </button>
              <AdminSidebar onNavigate={() => setMobileOpen(false)} />
            </motion.aside>
          </div>
        )}
      </AnimatePresence>

      {/* Main */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Top bar */}
        <header className="flex h-11 items-center justify-between border-b border-[#e5e5e5] bg-[#f6f6f6] px-4 sm:px-5">
          <div className="flex items-center gap-3">
            <button onClick={() => setMobileOpen(true)} className="rounded p-1 text-[#666] hover:bg-[#ebebeb] md:hidden cursor-pointer">
              <Menu className="h-5 w-5" />
            </button>
            <span className="text-[13px] font-medium text-[#404040]">Imagina Audit</span>
          </div>
          <div className="flex items-center gap-1">
            <LanguageSwitcher variant="compact" align="right" />
            <a href="/" target="_blank" rel="noreferrer">
              <Button variant="ghost" size="sm" className="text-[#666] text-xs h-7 px-2 hover:text-[#404040] hover:bg-[#ebebeb]">
                <ExternalLink className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Ver herramienta</span>
              </Button>
            </a>
            <Button variant="ghost" size="sm" className="text-[#666] text-xs h-7 px-2 hover:text-[#404040] hover:bg-[#ebebeb]" onClick={() => navigate('/admin/settings')}>
              <Settings className="h-3.5 w-3.5" />
            </Button>
            <Button variant="ghost" size="sm" className="text-[#666] text-xs h-7 px-2 hover:text-red-500 hover:bg-red-50" onClick={logout}>
              <LogOut className="h-3.5 w-3.5" />
            </Button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto bg-white">
          <div className="p-5 sm:p-6">
            {children}
          </div>
        </main>
      </div>
    </div>
  )
}
