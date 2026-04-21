import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Save } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { MODULE_NAMES } from '@/lib/constants'
import { ModuleIcon } from './ModuleIcon'

export default function SettingsScoring() {
  const { t } = useTranslation()
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [weights, setWeights] = useState<Record<string, number>>({})
  const [thresholds, setThresholds] = useState({ excellent: 90, good: 70, warning: 50, critical: 30 })
  const [testScore, setTestScore] = useState(72)

  useEffect(() => {
    fetchSettings().then((data: { moduleWeights?: Record<string, number>; thresholds?: typeof thresholds }) => {
      setWeights(data.moduleWeights || {})
      if (data.thresholds) setThresholds(data.thresholds)
      setLoading(false)
    })
  }, [fetchSettings])

  // Los módulos se derivan de lo que devuelve el backend en moduleWeights:
  // así al agregar uno nuevo (e.g. page_health, wp_internal) aparece
  // automáticamente aquí sin cambios en el frontend.
  const moduleIds = Object.keys(weights)

  const totalWeight = Object.values(weights).reduce((s, v) => s + v, 0)
  const isSumValid = Math.abs(totalWeight - 1.0) < 0.005

  const getTestLevel = () => {
    if (testScore >= thresholds.excellent) return { label: t('settings.scoring_level_excellent'), variant: 'success' as const }
    if (testScore >= thresholds.good) return { label: t('settings.scoring_level_good'), variant: 'success' as const }
    if (testScore >= thresholds.warning) return { label: t('settings.scoring_level_warning'), variant: 'warning' as const }
    return { label: t('settings.scoring_level_critical'), variant: 'destructive' as const }
  }

  const save = async () => {
    setSaving(true)
    try {
      // Guardar pesos como settings individuales
      const payload: Record<string, unknown> = {}
      for (const id of moduleIds) {
        payload[`weight_${id}`] = weights[id] ?? 0.1
      }
      payload.threshold_excellent = thresholds.excellent
      payload.threshold_good = thresholds.good
      payload.threshold_warning = thresholds.warning
      payload.threshold_critical = thresholds.critical
      await updateSettings(payload)
      toast.success(t('settings.scoring_saved'))
    } catch { toast.error(t('settings.save_error')) }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-64 rounded-2xl" />

  const thresholdRows = [
    { key: 'excellent' as const, label: t('settings.scoring_level_excellent'), variant: 'success' as const },
    { key: 'good' as const,      label: t('settings.scoring_level_good'),      variant: 'success' as const },
    { key: 'warning' as const,   label: t('settings.scoring_level_warning'),   variant: 'warning' as const },
    { key: 'critical' as const,  label: t('settings.scoring_level_critical'),  variant: 'destructive' as const },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.scoring_title')}</h1>
      </div>

      {/* Pesos */}
      <Card>
        <CardHeader><CardTitle>{t('settings.scoring_weights_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {moduleIds.map((id) => (
            <div key={id} className="flex items-center gap-3">
              <Label className="w-40 flex items-center gap-2">
                <ModuleIcon id={id} className="h-4 w-4 text-[var(--text-secondary)]" />
                {MODULE_NAMES[id]}
              </Label>
              <input
                type="range" min="0" max="0.5" step="0.01"
                value={weights[id] ?? 0.1}
                onChange={(e) => setWeights({ ...weights, [id]: parseFloat(e.target.value) })}
                className="flex-1 accent-[var(--accent-primary)]"
              />
              <span className="w-12 text-right text-sm font-mono text-[var(--text-secondary)]">
                {(weights[id] ?? 0.1).toFixed(2)}
              </span>
            </div>
          ))}

          <div className="mt-4 pt-3 border-t border-[var(--border-default)]">
            <Badge variant={isSumValid ? 'success' : 'destructive'}>
              {isSumValid
                ? t('settings.scoring_sum_ok', { total: totalWeight.toFixed(2) })
                : t('settings.scoring_sum_bad', { total: totalWeight.toFixed(2) })}
            </Badge>
          </div>
        </CardContent>
      </Card>

      {/* Umbrales */}
      <Card>
        <CardHeader><CardTitle>{t('settings.scoring_thresholds_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {thresholdRows.map((row) => (
            <div key={row.key} className="flex items-center gap-3">
              <Badge variant={row.variant} className="w-28 justify-center">{row.label}</Badge>
              <span className="text-sm text-[var(--text-secondary)]">{t('settings.scoring_score_gte')}</span>
              <Input
                type="number" min={0} max={100}
                value={thresholds[row.key]}
                onChange={(e) => setThresholds({ ...thresholds, [row.key]: parseInt(e.target.value) || 0 })}
                className="w-20"
              />
            </div>
          ))}

          <div className="mt-4 pt-3 border-t border-[var(--border-default)] flex items-center gap-3">
            <span className="text-sm text-[var(--text-secondary)]">{t('settings.scoring_test_prefix')}</span>
            <Input type="number" value={testScore} onChange={(e) => setTestScore(parseInt(e.target.value) || 0)} className="w-20" />
            <span className="text-sm text-[var(--text-secondary)]">{t('settings.scoring_test_suffix')}</span>
            <Badge variant={getTestLevel().variant}>{getTestLevel().label}</Badge>
          </div>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving || !isSumValid}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        {t('settings.scoring_save')}
      </Button>
    </div>
  )
}
