import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Save, Archive, AlertTriangle, Pin, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

const MONTH_VALUES = [3, 6, 12, 24] as const

interface Preview {
  months: number
  cutoffDate: string
  totalAudits: number
  pinnedAudits: number
  wouldDelete: number
  wouldKeep: number
  estimatedBytesFreed: number
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(1)} MB`
  return `${(bytes / 1073741824).toFixed(2)} GB`
}

export default function SettingsRetention() {
  const { t, i18n } = useTranslation()
  const { fetchSettings, updateSettings, fetchRetentionPreview } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [enabled, setEnabled] = useState(false)
  const [months, setMonths] = useState(6)
  const [preview, setPreview] = useState<Preview | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  useEffect(() => {
    fetchSettings().then((data) => {
      if (data) {
        setEnabled(!!(data.auditsRetentionEnabled ?? data.audits_retention_enabled))
        setMonths(Number(data.auditsRetentionMonths ?? data.audits_retention_months ?? 6))
      }
      setLoading(false)
    })
  }, [fetchSettings])

  // Recalcular preview cuando cambia months
  useEffect(() => {
    if (loading) return
    setPreviewLoading(true)
    fetchRetentionPreview(months).then((p) => {
      if (p) setPreview(p as Preview)
      setPreviewLoading(false)
    })
  }, [months, loading, fetchRetentionPreview])

  const save = async () => {
    setSaving(true)
    try {
      await updateSettings({
        auditsRetentionEnabled: enabled,
        auditsRetentionMonths: months,
      })
      toast.success(t('settings.retention_saved'))
    } catch { toast.error(t('settings.save_error')) }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  const monthLabel = (v: number) => t(`settings.retention_months_${v}` as 'settings.retention_months_3')

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.retention_title')}</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          {t('settings.retention_subtitle_prefix')}
          <Pin className="inline-block h-3 w-3 mx-1 text-amber-500 fill-amber-500" strokeWidth={2} />
          {t('settings.retention_subtitle_suffix')}
        </p>
      </div>

      {/* Toggle master */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Archive className="h-5 w-5 text-[var(--accent-primary)]" /> {t('settings.retention_toggle_card')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
              className="mt-1 h-4 w-4 accent-[var(--accent-primary)]"
            />
            <div>
              <p className="font-medium text-[var(--text-primary)]">{t('settings.retention_toggle_label')}</p>
              <p className="text-sm text-[var(--text-secondary)] mt-1">
                {t('settings.retention_toggle_hint')}
              </p>
            </div>
          </label>

          {enabled && (
            <div className="mt-6 pt-6 border-t border-[var(--border-default)]">
              <Label className="font-medium">{t('settings.retention_keep_label')}</Label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
                {MONTH_VALUES.map((value) => (
                  <button
                    key={value}
                    onClick={() => setMonths(value)}
                    className={`rounded-lg border px-3 py-2 text-sm font-medium transition-colors cursor-pointer ${
                      months === value
                        ? 'bg-[var(--accent-primary)] border-[var(--accent-primary)] text-white'
                        : 'bg-white border-[var(--border-default)] text-[var(--text-secondary)] hover:border-[var(--accent-primary)]'
                    }`}
                  >
                    {monthLabel(value)}
                  </button>
                ))}
              </div>
              <p className="text-xs text-[var(--text-tertiary)] mt-2">
                {t('settings.retention_cutoff_prefix')}{' '}
                <span className="font-mono">
                  {preview?.cutoffDate ? new Date(preview.cutoffDate).toLocaleDateString(i18n.language) : '—'}
                </span>{' '}
                {t('settings.retention_cutoff_suffix')}
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Preview */}
      {enabled && (
        <Card>
          <CardHeader>
            <CardTitle>{t('settings.retention_impact_card')}</CardTitle>
          </CardHeader>
          <CardContent>
            {previewLoading || !preview ? (
              <div className="flex items-center gap-2 text-sm text-[var(--text-tertiary)]">
                <Loader2 className="h-4 w-4 animate-spin" /> {t('settings.retention_calculating')}
              </div>
            ) : (
              <div className="space-y-4">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <StatCard label={t('settings.retention_stat_total')} value={preview.totalAudits} color="gray" />
                  <StatCard label={t('settings.retention_stat_keep')} value={preview.wouldKeep} color="emerald" />
                  <StatCard label={t('settings.retention_stat_delete')} value={preview.wouldDelete} color={preview.wouldDelete > 0 ? 'red' : 'gray'} />
                  <StatCard label={t('settings.retention_stat_pinned')} value={preview.pinnedAudits} color="amber" icon={<Pin className="h-3 w-3" />} />
                </div>
                {preview.wouldDelete > 0 && (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                    <AlertTriangle className="h-4 w-4 inline-block mr-2 text-amber-600" />
                    <span dangerouslySetInnerHTML={{ __html: t('settings.retention_warn_delete', { count: preview.wouldDelete, size: formatBytes(preview.estimatedBytesFreed) }) }} />
                  </div>
                )}
                {preview.wouldDelete === 0 && preview.totalAudits > 0 && (
                  <p className="text-sm text-[var(--text-secondary)]">
                    {t('settings.retention_none_to_delete', { months })}
                  </p>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Cómo proteger */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Pin className="h-5 w-5 text-amber-500 fill-amber-500" /> {t('settings.retention_how_card')}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-[var(--text-secondary)]">
          <p dangerouslySetInnerHTML={{ __html: t('settings.retention_how_1') }} />
          <p dangerouslySetInnerHTML={{ __html: t('settings.retention_how_2') }} />
          <p>
            {t('settings.retention_how_3')}
            <Trash2 className="inline-block h-3.5 w-3.5 mx-0.5" />
          </p>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        {t('settings.retention_save')}
      </Button>
    </div>
  )
}

function StatCard({ label, value, color, icon }: {
  label: string
  value: number
  color: 'emerald' | 'amber' | 'red' | 'gray'
  icon?: React.ReactNode
}) {
  const colors = {
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    red: 'text-red-600',
    gray: 'text-[var(--text-primary)]',
  }
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3">
      <p className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">{icon}{label}</p>
      <p className={`text-2xl font-bold tabular-nums ${colors[color]}`}>{value}</p>
    </div>
  )
}
