import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft, RotateCw, RefreshCw, Share2, LinkIcon } from 'lucide-react'
import { toast } from 'sonner'
import Layout from '@/components/layout/Layout'
import ScoreOverview from '@/components/audit/ScoreOverview'
import ModuleCard from '@/components/audit/ModuleCard'
import EconomicImpact from '@/components/audit/EconomicImpact'
import SolutionMapping from '@/components/audit/SolutionMapping'
import CtaSection from '@/components/audit/CtaSection'
import PdfReport from '@/components/audit/PdfReport'
import HistorySection from '@/components/audit/HistorySection'
import TechStackSection from '@/components/audit/TechStackSection'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuditStore } from '@/store/auditStore'
import { useAudit } from '@/hooks/useAudit'
import { getAuditResult, getConfig } from '@/lib/api'
import type { AuditResult } from '@/types/audit'

/** Genera mensaje de WhatsApp con resumen del informe */
function buildWhatsAppMessage(result: AuditResult, baseUrl: string): string {
  const moduleLines = result.modules.map((m) => {
    const emojis: Record<string, string> = {
      security: '🛡️', performance: '⚡', seo: '🔍', wordpress: '🧩',
      mobile: '📱', infrastructure: '🖥️', conversion: '📊',
    }
    return `${emojis[m.id] || '📋'} ${m.name}: ${m.score ?? '-'}/100`
  }).join('\n')

  return `🔍 *Auditoría Web — ${result.domain}*
📅 ${new Date(result.timestamp).toLocaleDateString('es-CO')}

📊 *Score Global: ${result.globalScore}/100*

${moduleLines}

⚠️ *${result.totalIssues.critical} problemas críticos* y *${result.totalIssues.warning} advertencias*.

📄 Ver informe completo:
${baseUrl}/results/${result.id}

_Informe generado por Imagina Audit_`
}

export default function ResultsPage() {
  const { auditId } = useParams<{ auditId: string }>()
  const navigate = useNavigate()
  const storeResult = useAuditStore((s) => s.result)
  const setConfig = useAuditStore((s) => s.setConfig)
  const config = useAuditStore((s) => s.config)
  const { startAudit } = useAudit()

  const [result, setResult] = useState<AuditResult | null>(storeResult)
  const [loading, setLoading] = useState(!storeResult)
  const [error, setError] = useState<string | null>(null)

  const rescan = () => {
    if (!result) return
    // Navegar al home y ejecutar el escaneo desde ahí
    navigate('/')
    // Pequeño delay para que el HomePage monte antes de disparar el escaneo
    setTimeout(() => startAudit({ url: result.url, forceRefresh: true }), 50)
  }

  const copyLink = () => {
    navigator.clipboard.writeText(window.location.href)
    toast.success('Link copiado al portapapeles')
  }

  const shareWhatsApp = () => {
    if (!result) return
    const baseUrl = window.location.origin
    const msg = buildWhatsAppMessage(result, baseUrl)
    const waNumber = config.companyWhatsapp.replace(/[^0-9]/g, '')
    window.open(`https://wa.me/${waNumber}?text=${encodeURIComponent(msg)}`, '_blank')
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
      .then((data) => { setResult(data); setLoading(false) })
      .catch(() => { setError('No se pudo cargar la auditoría.'); setLoading(false) })
  }, [auditId, storeResult])

  if (loading) {
    return (
      <Layout>
        <div className="mx-auto max-w-5xl px-4 py-16">
          <Skeleton className="mx-auto h-48 w-48 rounded-full" />
          <div className="mt-8 space-y-4">
            {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-32 w-full rounded-2xl" />)}
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
            <Button><ArrowLeft className="h-4 w-4" strokeWidth={1.5} /> Nueva auditoría</Button>
          </Link>
        </div>
      </Layout>
    )
  }

  return (
    <Layout showFooter={false}>
      {/* Header sticky */}
      <div className="sticky top-16 z-40 border-b border-[var(--border-default)] bg-white/90 backdrop-blur-lg">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <span className="text-sm font-semibold text-[var(--text-primary)]">{result.domain}</span>
            <span className="text-xs text-[var(--text-tertiary)]">
              {new Date(result.timestamp).toLocaleDateString('es-CO')}
            </span>
          </div>
          <div className="flex items-center gap-1.5">
            <PdfReport result={result} />
            <Button variant="ghost" size="sm" onClick={shareWhatsApp} title="Compartir por WhatsApp">
              <Share2 className="h-4 w-4" strokeWidth={1.5} />
              <span className="hidden lg:inline">WhatsApp</span>
            </Button>
            <Button variant="ghost" size="sm" onClick={copyLink} title="Copiar link del informe">
              <LinkIcon className="h-4 w-4" strokeWidth={1.5} />
            </Button>
            <Button variant="outline" size="sm" onClick={rescan}>
              <RefreshCw className="h-4 w-4" strokeWidth={1.5} />
              <span className="hidden sm:inline">Re-escanear</span>
            </Button>
            <Link to="/">
              <Button variant="ghost" size="sm">
                <RotateCw className="h-4 w-4" strokeWidth={1.5} />
                <span className="hidden sm:inline">Nueva</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>

      <ScoreOverview result={result} />

      <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        {/* Historial (solo se muestra si hay más de 1 auditoría) */}
        <HistorySection domain={result.domain} />

        {/* Stack tecnológico (informativo) */}
        {result.techStack && <TechStackSection techStack={result.techStack} />}

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
          <p className="mt-1">Duración del escaneo: {(result.scanDurationMs / 1000).toFixed(1)}s</p>
        </div>
      </div>
    </Layout>
  )
}
