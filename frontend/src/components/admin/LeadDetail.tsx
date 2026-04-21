import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Mail, MessageCircle, ExternalLink } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import LeadReportNav from './LeadReportNav'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import ScoreOverview from '@/components/audit/ScoreOverview'
import ModuleCard from '@/components/audit/ModuleCard'
import EconomicImpact from '@/components/audit/EconomicImpact'
import SolutionMapping from '@/components/audit/SolutionMapping'
import { useAdmin } from '@/hooks/useAdmin'
import type { AuditResult } from '@/types/audit'

export default function LeadDetail() {
  const { id } = useParams<{ id: string }>()
  const { fetchLeadDetail } = useAdmin()
  const [result, setResult] = useState<(AuditResult & { leadName?: string; leadEmail?: string; leadWhatsapp?: string; leadCompany?: string }) | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    fetchLeadDetail(id).then((data: AuditResult) => {
      setResult(data)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail])

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-48 rounded-2xl" /><Skeleton className="h-64 rounded-2xl" /></div>
  }

  if (!result) {
    return <div className="text-center py-12 text-[var(--text-secondary)]">Auditoría no encontrada</div>
  }

  return (
    <div className="space-y-6">
      {/* Header con navegación entre vistas del lead */}
      {id && <LeadReportNav auditId={id} domain={result.domain} />}

      {/* Datos del lead */}
      <Card>
        <CardContent className="p-5">
          <div className="flex flex-wrap items-center gap-4 text-sm">
            {result.leadName && <Badge variant="secondary">{result.leadName}</Badge>}
            {result.leadEmail && (
              <a href={`mailto:${result.leadEmail}`} className="flex items-center gap-1 text-[var(--accent-primary)] hover:underline">
                <Mail className="h-3.5 w-3.5" strokeWidth={1.5} /> {result.leadEmail}
              </a>
            )}
            {result.leadWhatsapp && (
              <a href={`https://wa.me/${result.leadWhatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer" className="flex items-center gap-1 text-emerald-500 hover:underline">
                <MessageCircle className="h-3.5 w-3.5" strokeWidth={1.5} /> {result.leadWhatsapp}
              </a>
            )}
            {result.leadCompany && <span className="text-[var(--text-secondary)]">{result.leadCompany}</span>}
            <a href={result.url} target="_blank" rel="noreferrer" className="flex items-center gap-1 text-[var(--text-tertiary)] hover:text-[var(--accent-primary)]">
              <ExternalLink className="h-3.5 w-3.5" strokeWidth={1.5} /> Ver sitio
            </a>
          </div>
        </CardContent>
      </Card>

      {/* Reutilizar componentes de resultados */}
      <ScoreOverview result={result} />

      <div className="space-y-6">
        {result.modules.map((module, idx) => (
          <ModuleCard key={module.id} module={module} index={idx} />
        ))}

        <EconomicImpact
          estimatedMonthlyLoss={result.economicImpact.estimatedMonthlyLoss}
          currency={result.economicImpact.currency}
          explanation={result.economicImpact.explanation}
        />

        <SolutionMapping solutions={result.solutionMap} />
      </div>
    </div>
  )
}
