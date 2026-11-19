import { useTranslation } from 'react-i18next'
/**
 * Grid con LCP element, CLS elements y Main Thread work breakdown.
 * Se muestra solo si hay al menos uno de los tres datos.
 */
export function PerformanceDetails({
  lcpElement,
  clsElements,
  mainThreadWork,
}: {
  lcpElement: Record<string, string> | null
  clsElements: Array<Record<string, unknown>>
  mainThreadWork: Array<{ group: string; duration: number }>
}) {
  const { t } = useTranslation()
  if (!lcpElement && clsElements.length === 0 && mainThreadWork.length === 0) {
    return null
  }

  return (
    <div className="mt-8 space-y-4">
      <h2 className="text-lg font-bold text-gray-900">{t('settings.wf_perf_details')}</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {lcpElement && (
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="text-xs text-gray-500 mb-1">{t('settings.wf_lcp_element')}</div>
            <div className="text-sm font-medium text-gray-800 mb-2">{lcpElement.nodeLabel || lcpElement.selector}</div>
            {lcpElement.snippet && <code className="block text-[10px] text-gray-500 bg-gray-50 p-2 rounded overflow-x-auto whitespace-pre">{lcpElement.snippet}</code>}
          </div>
        )}
        {clsElements.length > 0 && (
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="text-xs text-gray-500 mb-1">{t('settings.wf_cls_elements', { count: clsElements.length })}</div>
            <div className="space-y-2">
              {clsElements.map((el, i) => (
                <div key={i} className="text-xs">
                  <span className="font-medium text-gray-700">{String(el.nodeLabel || el.selector)}</span>
                  <span className="text-amber-600 ml-2">{t('settings.wf_shift')}: {Number(el.score).toFixed(3)}</span>
                </div>
              ))}
            </div>
          </div>
        )}
        {mainThreadWork.length > 0 && (
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="text-xs text-gray-500 mb-2">{t('settings.wf_main_thread')}</div>
            <div className="space-y-1.5">
              {mainThreadWork.map((w, i) => (
                <div key={i} className="flex items-center justify-between text-xs">
                  <span className="text-gray-600">{w.group}</span>
                  <span className="font-medium text-gray-800 tabular-nums">{w.duration}ms</span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
