import { Routes, Route, Navigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { Component, type ReactNode } from 'react'
import { useAuth } from '@/hooks/useAuth'
import AdminLogin from '@/components/admin/AdminLogin'
import AdminLayout from '@/components/admin/AdminLayout'
import DashboardPage from '@/components/admin/DashboardPage'
import LeadsTable from '@/components/admin/LeadsTable'
import LeadDetail from '@/components/admin/LeadDetail'
import SettingsGeneral from '@/components/admin/SettingsGeneral'
import SettingsBranding from '@/components/admin/SettingsBranding'
import SettingsPluginVault from '@/components/admin/SettingsPluginVault'
import SettingsHomeCMS from '@/components/admin/SettingsHomeCMS'
import SettingsMessages from '@/components/admin/SettingsMessages'
import SettingsPlans from '@/components/admin/SettingsPlans'
import SettingsScoring from '@/components/admin/SettingsScoring'
import SettingsQueue from '@/components/admin/SettingsQueue'
import SettingsRetention from '@/components/admin/SettingsRetention'
import SystemHealth from '@/components/admin/SystemHealth'
import VulnerabilityManager from '@/components/admin/VulnerabilityManager'
import TechnicalReport from '@/components/admin/TechnicalReport'
import SnapshotReport from '@/components/admin/SnapshotReport'
import WaterfallPage from '@/components/admin/WaterfallPage'

class ErrorBoundary extends Component<{ children: ReactNode }, { error: string | null }> {
  state = { error: null as string | null }
  static getDerivedStateFromError(error: Error) { return { error: error.message } }
  render() {
    if (this.state.error) return (
      <div className="p-8 text-center">
        <p className="text-red-600 font-bold">Error en el panel</p>
        <pre className="mt-2 text-xs text-left bg-red-50 p-4 rounded overflow-auto max-h-64">{this.state.error}</pre>
        <button onClick={() => window.location.reload()} className="mt-4 text-blue-600 underline">Recargar</button>
      </div>
    )
    return this.props.children
  }
}

export default function AdminPage() {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  if (!isAuthenticated) {
    return <AdminLogin />
  }

  return (
    <AdminLayout>
      <ErrorBoundary>
      <Routes>
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="leads" element={<LeadsTable />} />
        <Route path="leads/:id" element={<LeadDetail />} />
        <Route path="leads/:id/report" element={<TechnicalReport />} />
        <Route path="leads/:id/internal" element={<SnapshotReport />} />
        <Route path="leads/:id/waterfall" element={<WaterfallPage />} />
        <Route path="settings" element={<SettingsGeneral />} />
        <Route path="branding" element={<SettingsBranding />} />
        <Route path="plugin-vault" element={<SettingsPluginVault />} />
        <Route path="home" element={<SettingsHomeCMS />} />
        <Route path="messages" element={<SettingsMessages />} />
        <Route path="plans" element={<SettingsPlans />} />
        <Route path="scoring" element={<SettingsScoring />} />
        <Route path="queue" element={<SettingsQueue />} />
        <Route path="retention" element={<SettingsRetention />} />
        <Route path="health" element={<SystemHealth />} />
        <Route path="vulnerabilities" element={<VulnerabilityManager />} />
        <Route path="*" element={<Navigate to="dashboard" replace />} />
      </Routes>
      </ErrorBoundary>
    </AdminLayout>
  )
}
