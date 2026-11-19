import { useEffect, useState, useMemo } from 'react'
import { useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import LeadReportNav from './LeadReportNav'
import type { AuditResult } from '@/types/audit'

import {
  type NetworkRequest,
  type CruxData,
  type ResourceBreakdownItem,
  type LighthouseAudit,
} from './waterfall/helpers'
import WaterfallView, { buildMilestones } from './waterfall/WaterfallView'

/**
 * Vista detallada de waterfall, Core Web Vitals, Page Details y Structure
 * Audits para una auditoría dada. Es el admin equivalente del Network tab
 * de DevTools + GTmetrix/Pingdom.
 *
 * Orquestador: carga los datos y delega la presentación en WaterfallView
 * (compartida con el Laboratorio de rendimiento).
 */
interface WaterfallPageProps {
  /** Override del fetcher del detalle. Default: useAdmin().fetchLeadDetail */
  fetcher?: (id: string) => Promise<AuditResult | null>
  basePath?: string
  backTo?: string | null
}

export default function WaterfallPage({ fetcher, basePath, backTo }: WaterfallPageProps = {}) {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const { fetchLeadDetail: adminFetch } = useAdmin()
  const fetchLeadDetail = fetcher ?? adminFetch
  const [result, setResult] = useState<AuditResult | null>(null)
  const [loading, setLoading] = useState(true)

  const [requests, setRequests] = useState<NetworkRequest[]>([])
  const [cruxData, setCruxData] = useState<CruxData | null>(null)
  const [resourceBreakdown, setResourceBreakdown] = useState<ResourceBreakdownItem[]>([])
  const [lighthouseAudits, setLighthouseAudits] = useState<LighthouseAudit[]>([])
  const [lcpElement, setLcpElement] = useState<Record<string, string> | null>(null)
  const [clsElements, setClsElements] = useState<Array<Record<string, unknown>>>([])
  const [mainThreadWork, setMainThreadWork] = useState<Array<{ group: string; duration: number }>>([])

  useEffect(() => {
    if (!id) return
    Promise.all([
      fetchLeadDetail(id),
      api.get('/admin/waterfall.php', { params: { id } }).then(r => r.data?.data).catch(() => null)
    ]).then(([audit, perfData]: [AuditResult, { waterfall?: NetworkRequest[]; crux?: CruxData; resourceBreakdown?: ResourceBreakdownItem[]; lighthouseAudits?: LighthouseAudit[] } | null]) => {
      setResult(audit)
      setRequests(perfData?.waterfall || [])
      setCruxData(perfData?.crux || null)
      setResourceBreakdown(perfData?.resourceBreakdown || [])
      setLighthouseAudits(perfData?.lighthouseAudits || [])
      setLcpElement((perfData as Record<string, unknown>)?.lcpElement as Record<string, string> | null ?? null)
      setClsElements(((perfData as Record<string, unknown>)?.clsElements || []) as Array<Record<string, unknown>>)
      setMainThreadWork(((perfData as Record<string, unknown>)?.mainThreadWork || []) as Array<{ group: string; duration: number }>)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail])

  // Extraer milestones de performance del audit result
  const milestones = useMemo(() => {
    if (!result) return []
    const perfModule = result.modules.find(m => m.id === 'performance')
    if (!perfModule) return []
    const val = (id: string) => {
      const m = perfModule.metrics.find(x => x.id === id)
      return m && typeof m.value === 'number' ? m.value : null
    }
    return buildMilestones({ fcp: val('fcp'), lcp: val('lcp'), tbt: val('tbt'), ttfb: val('ttfb') })
  }, [result])

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-96 rounded-lg" /></div>
  }

  if (!result || requests.length === 0) {
    return (
      <div className="space-y-4">
        {id && <LeadReportNav auditId={id} domain={result?.domain} basePath={basePath} backTo={backTo} />}
        <div className="text-center py-16 text-gray-500">
          <p className="text-lg font-medium">{t('settings.waterfall_empty_title')}</p>
          <p className="text-sm mt-1">{t('settings.waterfall_empty_hint')}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {id && <LeadReportNav auditId={id} domain={result.domain} basePath={basePath} backTo={backTo} />}
      <WaterfallView
        url={result.url}
        domain={result.domain}
        requests={requests}
        milestones={milestones}
        cruxData={cruxData}
        resourceBreakdown={resourceBreakdown}
        lighthouseAudits={lighthouseAudits}
        lcpElement={lcpElement}
        clsElements={clsElements}
        mainThreadWork={mainThreadWork}
      />
    </div>
  )
}
