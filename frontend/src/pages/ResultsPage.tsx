import { lazy, Suspense, useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, RotateCw, RefreshCw, Share2, LinkIcon } from 'lucide-react'
import { toast } from 'sonner'
import Layout from '@/components/layout/Layout'
import ScoreOverview from '@/components/audit/ScoreOverview'
import ModuleCard from '@/components/audit/ModuleCard'
import EconomicImpact from '@/components/audit/EconomicImpact'
import SolutionMapping from '@/components/audit/SolutionMapping'
import CtaSection from '@/components/audit/CtaSection'
import PdfReport from '@/components/audit/PdfReport'
// HistorySection arrastra Recharts (~341 KB). Lazy + Suspense lo saca del
// preload de la landing pública: solo se descarga si el visitante abre
// resultados de un dominio con historial.
const HistorySection = lazy(() => import('@/components/audit/HistorySection'))
import TechStackSection from '@/components/audit/TechStackSection'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuditStore } from '@/store/auditStore'
import { useUserAuthStore } from '@/store/userAuthStore'
import { useAudit } from '@/hooks/useAudit'
import { getAuditResult, getConfig } from '@/lib/api'
import LeadGate from '@/components/audit/LeadGate'
import type { AuditResult } from '@/types/audit'

/** Genera mensaje de WhatsApp con resumen del informe */
function buildWhatsAppMessage(
  result: AuditResult,
  baseUrl: string,
  t: (key: string, opts?: Record<string, unknown>) => string,
  lang: string,
): string {
  const emojis: Record<string, string> = {
    security: '🛡️', performance: '⚡', seo: '🔍', wordpress: '🧩',
    mobile: '📱', infrastructure: '🖥️', conversion: '📊',
  }
  const moduleLines = result.modules
    .map((m) => `${emojis[m.id] || '📋'} ${m.name}: ${m.score ?? '-'}/100`)
    .join('\n')
  const date = new Date(result.timestamp).toLocaleDateString(lang, { day: 'numeric', month: 'short', year: 'numeric' })

  return [
    t('public.share_wa_title', { domain: result.domain }),
    `📅 ${date}`,
    '',
    t('public.share_wa_score', { score: result.globalScore }),
    '',
    moduleLines,
    '',
    t('public.share_wa_issues', { critical: result.totalIssues.critical, warning: result.totalIssues.warning }),
    '',
    t('public.share_wa_report'),
    `${baseUrl}/results/${result.id}`,
    '',
    t('public.share_wa_footer'),
  ].join('\n')
}

export default function ResultsPage() {
  const { t, i18n } = useTranslation()
  const { auditId } = useParams<{ auditId: string }>()
  const navigate = useNavigate()
  const storeResult = useAuditStore((s) => s.result)
  const setConfig = useAuditStore((s) => s.setConfig)
  const config = useAuditStore((s) => s.config)
  const { startAudit } = useAudit()

  const [result, setResult] = useState<AuditResult | null>(storeResult)
  const [loading, setLoading] = useState(!storeResult)
  const [error, setError] = useState<string | null>(null)
  const [unlocked, setUnlocked] = useState(false)
  const isUser = useUserAuthStore((s) => s.isAuthenticated)

  // Gating (modo 'gated'): el informe detallado se bloquea hasta capturar
  // el contacto. NO aplica si: el modo es 'upfront', el lead ya fue
  // capturado (audit con email/whatsapp), el viewer es un usuario logueado
  // (ya identificado), o ya desbloqueó en este navegador.
  const lc = config.leadCapture
  const leadCaptured = (result as (AuditResult & { _leadCaptured?: boolean }) | null)?._leadCaptured === true
  const alreadyUnlockedLocal = !!auditId && (() => {
    try { return localStorage.getItem(`unlocked_${auditId}`) === '1' } catch { return false }
  })()
  const shouldGate = lc?.mode === 'gated' && !leadCaptured && !isUser && !unlocked && !alreadyUnlockedLocal

  const rescan = () => {
    if (!result) return
    // Navegar al home y ejecutar el escaneo desde ahí
    navigate('/')
    // Pequeño delay para que el HomePage monte antes de disparar el escaneo
    setTimeout(() => startAudit({ url: result.url, forceRefresh: true }), 50)
  }

  const copyLink = () => {
    navigator.clipboard.writeText(window.location.href)
    toast.success(t('public.results_link_copied'))
  }

  const shareWhatsApp = () => {
    if (!result) return
    const baseUrl = window.location.origin
    const msg = buildWhatsAppMessage(result, baseUrl, t, i18n.language)
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
      .catch(() => { setError(t('public.results_load_error')); setLoading(false) })
  }, [auditId, storeResult, t])

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
          <h2 className="text-xl font-semibold text-[var(--text-primary)]">{t('public.results_error_title')}</h2>
          <p className="mt-2 text-[var(--text-secondary)]">{error || t('public.results_error_body')}</p>
          <Link to="/" className="mt-6">
            <Button><ArrowLeft className="h-4 w-4" strokeWidth={1.5} /> {t('public.results_new_audit')}</Button>
          </Link>
        </div>
      </Layout>
    )
  }

  return (
    <Layout showFooter={false}>
      {/* Header sticky */}
      <div className="sticky top-14 z-40 border-b border-[var(--border-default)] bg-white/90 backdrop-blur-lg">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-2 px-3 py-2 sm:px-6 sm:py-3 overflow-hidden">
          <div className="flex items-center gap-2 min-w-0 shrink">
            <span className="text-sm font-semibold text-[var(--text-primary)] truncate">{result.domain}</span>
            <span className="text-xs text-[var(--text-tertiary)] hidden sm:inline whitespace-nowrap">
              {new Date(result.timestamp).toLocaleDateString(i18n.language)}
            </span>
          </div>
          <div className="flex items-center shrink-0">
            {/* PDF / compartir / copiar exponen el informe completo — ocultos
                hasta desbloquear en modo gated. */}
            {!shouldGate && <PdfReport result={result} />}
            {!shouldGate && (
              <Button variant="ghost" size="icon" className="h-8 w-8" onClick={shareWhatsApp} title={t('public.results_share_whatsapp')}>
                <Share2 className="h-4 w-4" strokeWidth={1.5} />
              </Button>
            )}
            {!shouldGate && (
              <Button variant="ghost" size="icon" className="h-8 w-8" onClick={copyLink} title={t('public.results_copy_link')}>
                <LinkIcon className="h-4 w-4" strokeWidth={1.5} />
              </Button>
            )}
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={rescan} title={t('public.results_rescan')}>
              <RefreshCw className="h-4 w-4" strokeWidth={1.5} />
            </Button>
            <Link to="/">
              <Button variant="ghost" size="icon" className="h-8 w-8" title={t('public.results_new_audit_title')}>
                <RotateCw className="h-4 w-4" strokeWidth={1.5} />
              </Button>
            </Link>
          </div>
        </div>
      </div>

      <ScoreOverview result={result} />

      {shouldGate ? (
        <div className="relative mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
          {/* Adelanto difuminado: genera deseo — ve que hay contenido pero
              no lo puede leer hasta entregar su contacto. */}
          <div className="pointer-events-none max-h-[560px] select-none overflow-hidden blur-[7px]" aria-hidden="true">
            <div className="space-y-6">
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
            </div>
          </div>
          {/* Fade inferior */}
          <div className="pointer-events-none absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-[var(--bg-primary)] via-[var(--bg-primary)]/80 to-transparent" />
          {/* Gate flotante */}
          <div className="absolute inset-0 flex items-start justify-center px-4 pt-12 sm:pt-20">
            <LeadGate
              auditId={result.id}
              requireEmail={lc?.requireEmail ?? true}
              requireName={lc?.requireName ?? false}
              requireWhatsapp={lc?.requireWhatsapp ?? false}
              score={result.globalScore}
              onUnlock={() => setUnlocked(true)}
            />
          </div>
        </div>
      ) : (
        <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
          {/* Historial (solo se muestra si hay más de 1 auditoría) */}
          <Suspense fallback={null}>
            <HistorySection domain={result.domain} />
          </Suspense>

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
            <p>{t('public.results_footer_generated')} <span className="font-medium text-[var(--accent-primary)]">Imagina Audit</span> &mdash; imaginawp.com</p>
            <p className="mt-1">{t('public.results_footer_duration', { sec: (result.scanDurationMs / 1000).toFixed(1) })}</p>
          </div>
        </div>
      )}
    </Layout>
  )
}
