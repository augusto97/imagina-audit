import { Routes, Route, Navigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import AdminLogin from '@/components/admin/AdminLogin'
import AdminLayout from '@/components/admin/AdminLayout'
import DashboardPage from '@/components/admin/DashboardPage'
import LeadsTable from '@/components/admin/LeadsTable'
import LeadDetail from '@/components/admin/LeadDetail'
import SettingsGeneral from '@/components/admin/SettingsGeneral'
import SettingsMessages from '@/components/admin/SettingsMessages'
import SettingsPlans from '@/components/admin/SettingsPlans'
import SettingsScoring from '@/components/admin/SettingsScoring'
import VulnerabilityManager from '@/components/admin/VulnerabilityManager'
import TechnicalReport from '@/components/admin/TechnicalReport'

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
      <Routes>
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="leads" element={<LeadsTable />} />
        <Route path="leads/:id" element={<LeadDetail />} />
        <Route path="leads/:id/report" element={<TechnicalReport />} />
        <Route path="settings" element={<SettingsGeneral />} />
        <Route path="messages" element={<SettingsMessages />} />
        <Route path="plans" element={<SettingsPlans />} />
        <Route path="scoring" element={<SettingsScoring />} />
        <Route path="vulnerabilities" element={<VulnerabilityManager />} />
        <Route path="*" element={<Navigate to="dashboard" replace />} />
      </Routes>
    </AdminLayout>
  )
}
