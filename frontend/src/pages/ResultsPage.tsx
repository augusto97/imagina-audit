import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { ArrowLeft, RotateCw, RefreshCw } from 'lucide-react'
import Layout from '@/components/layout/Layout'
import ScoreOverview from '@/components/audit/ScoreOverview'
import ModuleCard from '@/components/audit/ModuleCard'
import EconomicImpact from '@/components/audit/EconomicImpact'
import SolutionMapping from '@/components/audit/SolutionMapping'
import CtaSection from '@/components/audit/CtaSection'
import PdfReport from '@/components/audit/PdfReport'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuditStore } from '@/store/auditStore'
import { useAudit } from '@/hooks/useAudit'
import { getAuditResult, getConfig } from '@/lib/api'
import type { AuditResult } from '@/types/audit'

export default function ResultsPage() {
  const { auditId } = useParams<{ auditId: string }>()
  const storeResult = useAuditStore((s) => s.result)
  const setConfig = useAuditStore((s) => s.setConfig)
  const { startAudit } = useAudit()

  const [result, setResult] = useState<AuditResult | null>(storeResult)
  const [loading, setLoading] = useState(!storeResult)
  const [error, setError] = useState<string | null>(null)

  /** Re-escanear el mismo sitio forzando nuevo análisis */
  const rescan = () => {
    if (!result) return
    startAudit({ url: result.url, forceRefresh: true })
  }

  useEffect(() => {
    getConfig().then(setConfig)
  }, [setConfig])

  useEffect(() => {
    if (storeResult && storeResult.id === auditId) {
      setResult(storeResult)
      setLoading(false)
      return
    }

    if (!auditId) return

    setLoading(true)
    getAuditResult(auditId)
      .then((data) => {
        setResult(data)
        setLoading(false)
      })
      .catch(() => {
        setError('No se pudo cargar la auditoría. Verifica el enlace.')
        setLoading(false)
      })
  }, [auditId, storeResult])

  if (loading) {
    return (
      <Layout>
        <div className="mx-auto max-w-5xl px-4 py-16">
          <Skeleton className="mx-auto h-48 w-48 rounded-full" />
          <div className="mt-8 space-y-4">
            <Skeleton className="h-32 w-full rounded-2xl" />
            <Skeleton className="h-32 w-full rounded-2xl" />
          </div>
        </div>
      </Layout>
    )
  }

  if (error || !result) {
    return (
      <Layout>
        <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
          <h2 className="text-xl font-semibold text-[var(--text-primary)]">Error</h2>
          <p className="mt-2 text-[var(--text-secondary)]">{error || 'Auditoría no encontrada.'}</p>
          <Link to="/" className="mt-6">
            <Button>
              <ArrowLeft className="h-4 w-4" strokeWidth={1.5} />
              Nueva auditoría
            </Button>
          </Link>
        </div>
      </Layout>
    )
  }

  return (
    <Layout showFooter={false}>
      {/* Header sticky con acciones */}
      <div className="sticky top-16 z-40 border-b border-[var(--border-default)] bg-white/90 backdrop-blur-lg">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <span className="text-sm font-semibold text-[var(--text-primary)]">{result.domain}</span>
            <span className="text-xs text-[var(--text-tertiary)]">
              {new Date(result.timestamp).toLocaleDateString('es-CO')}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <PdfReport result={result} />
            <Button variant="outline" size="sm" onClick={rescan}>
              <RefreshCw className="h-4 w-4" strokeWidth={1.5} />
              <span className="hidden sm:inline">Re-escanear</span>
            </Button>
            <Link to="/">
              <Button variant="ghost" size="sm">
                <RotateCw className="h-4 w-4" strokeWidth={1.5} />
                <span className="hidden sm:inline">Nueva Auditoría</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Score Overview */}
      <ScoreOverview result={result} />

      {/* Módulos */}
      <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        {result.modules
          .filter((m) => ['wordpress', 'security'].includes(m.id))
          .map((module, idx) => (
            <ModuleCard key={module.id} module={module} index={idx} />
          ))}

        <EconomicImpact
          estimatedMonthlyLoss={result.economicImpact.estimatedMonthlyLoss}
          currency={result.economicImpact.currency}
          explanation={result.economicImpact.explanation}
        />

        {result.modules
          .filter((m) => !['wordpress', 'security'].includes(m.id))
          .map((module, idx) => (
            <ModuleCard key={module.id} module={module} index={idx + 2} />
          ))}

        <SolutionMapping solutions={result.solutionMap} />
        <CtaSection />

        <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
          <p>Informe generado por <span className="font-medium text-[var(--accent-primary)]">Imagina Audit</span> &mdash; imaginawp.com</p>
          <p className="mt-1">
            Duración del escaneo: {(result.scanDurationMs / 1000).toFixed(1)}s
          </p>
        </div>
      </div>
    </Layout>
  )
}
