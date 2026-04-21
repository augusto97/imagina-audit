import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Save, Upload, Image as ImageIcon, X } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { useConfigStore } from '@/store/configStore'

type AssetType = 'logo' | 'logo_collapsed' | 'favicon'

const PRESETS = ['#3B82F6', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#111827']

export default function SettingsBranding() {
  const { t } = useTranslation()
  const { fetchSettings, updateSettings, uploadBrandAsset } = useAdmin()
  const reloadConfig = useConfigStore((s) => s.reload)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [color, setColor] = useState('#3B82F6')
  const [logoUrl, setLogoUrl] = useState('')
  const [logoCollapsedUrl, setLogoCollapsedUrl] = useState('')
  const [faviconUrl, setFaviconUrl] = useState('')
  const [uploadingType, setUploadingType] = useState<AssetType | null>(null)

  useEffect(() => {
    fetchSettings().then((data: Record<string, string>) => {
      setColor(data.brandPrimaryColor ?? data.brand_primary_color ?? '#3B82F6')
      setLogoUrl(data.logoUrl ?? data.logo_url ?? '')
      setLogoCollapsedUrl(data.logoCollapsedUrl ?? data.logo_collapsed_url ?? '')
      setFaviconUrl(data.faviconUrl ?? data.favicon_url ?? '')
      setLoading(false)
    })
  }, [fetchSettings])

  const upload = async (type: AssetType, file: File) => {
    if (!/^image\/(jpe?g|png)$/i.test(file.type)) {
      toast.error(t('settings.branding_format_error'))
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      toast.error(t('settings.branding_size_error'))
      return
    }
    setUploadingType(type)
    try {
      const res = await uploadBrandAsset(type, file)
      if (res) {
        if (type === 'logo') setLogoUrl(res.url)
        if (type === 'logo_collapsed') setLogoCollapsedUrl(res.url)
        if (type === 'favicon') setFaviconUrl(res.url)
        toast.success(t('settings.branding_uploaded'))
        reloadConfig()
      }
    } catch { toast.error(t('settings.branding_upload_error')) }
    setUploadingType(null)
  }

  const clearAsset = async (type: AssetType) => {
    const key = type === 'logo' ? 'logoUrl' : type === 'logo_collapsed' ? 'logoCollapsedUrl' : 'faviconUrl'
    try {
      await updateSettings({ [key]: '' })
      if (type === 'logo') setLogoUrl('')
      if (type === 'logo_collapsed') setLogoCollapsedUrl('')
      if (type === 'favicon') setFaviconUrl('')
      toast.success(t('settings.branding_removed'))
      reloadConfig()
    } catch { toast.error(t('settings.branding_remove_error')) }
  }

  const saveColor = async () => {
    setSaving(true)
    try {
      await updateSettings({ brandPrimaryColor: color })
      toast.success(t('settings.branding_color_saved'))
      reloadConfig()
    } catch { toast.error(t('settings.save_error')) }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.branding_title')}</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          {t('settings.branding_subtitle')}
        </p>
      </div>

      {/* Color principal */}
      <Card>
        <CardHeader><CardTitle>{t('settings.branding_color_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-3">
            <input
              type="color"
              value={color}
              onChange={(e) => setColor(e.target.value)}
              className="h-10 w-16 cursor-pointer rounded border border-[var(--border-default)] bg-transparent"
            />
            <Input
              value={color}
              onChange={(e) => setColor(e.target.value)}
              className="w-32 font-mono"
              maxLength={7}
            />
            <div className="flex items-center gap-2">
              {PRESETS.map((c) => (
                <button
                  key={c}
                  type="button"
                  onClick={() => setColor(c)}
                  className={`h-7 w-7 rounded-full border-2 transition-transform hover:scale-110 ${c.toLowerCase() === color.toLowerCase() ? 'border-[var(--text-primary)]' : 'border-transparent'}`}
                  style={{ backgroundColor: c }}
                  aria-label={c}
                />
              ))}
            </div>
          </div>

          {/* Preview */}
          <div className="rounded-lg border border-[var(--border-default)] p-4 bg-[var(--bg-secondary)]">
            <p className="text-xs uppercase tracking-wider text-[var(--text-tertiary)] mb-3">{t('settings.branding_preview')}</p>
            <div className="flex flex-wrap items-center gap-3">
              <button style={{ backgroundColor: color }} className="rounded px-4 py-2 text-sm font-medium text-white shadow">
                {t('settings.branding_preview_button')}
              </button>
              <span style={{ color }} className="text-sm font-semibold underline">
                {t('settings.branding_preview_text')}
              </span>
              <span className="inline-flex h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                <span style={{ backgroundColor: color, width: '72%' }} className="h-full" />
              </span>
            </div>
          </div>

          <Button onClick={saveColor} disabled={saving}>
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
            {t('settings.branding_save_color')}
          </Button>
        </CardContent>
      </Card>

      {/* Logos y favicon */}
      <Card>
        <CardHeader><CardTitle>{t('settings.branding_assets_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-5">
          <AssetUploader
            label={t('settings.branding_logo_main')}
            hint={t('settings.branding_logo_main_hint')}
            currentUrl={logoUrl}
            uploading={uploadingType === 'logo'}
            onSelect={(f) => upload('logo', f)}
            onClear={() => clearAsset('logo')}
            previewBg="#f6f6f6"
            maxHeight={64}
          />

          <AssetUploader
            label={t('settings.branding_logo_collapsed')}
            hint={t('settings.branding_logo_collapsed_hint')}
            currentUrl={logoCollapsedUrl}
            uploading={uploadingType === 'logo_collapsed'}
            onSelect={(f) => upload('logo_collapsed', f)}
            onClear={() => clearAsset('logo_collapsed')}
            previewBg="#f6f6f6"
            maxHeight={48}
          />

          <AssetUploader
            label={t('settings.branding_favicon')}
            hint={t('settings.branding_favicon_hint')}
            currentUrl={faviconUrl}
            uploading={uploadingType === 'favicon'}
            onSelect={(f) => upload('favicon', f)}
            onClear={() => clearAsset('favicon')}
            previewBg="#ffffff"
            maxHeight={32}
          />
        </CardContent>
      </Card>
    </div>
  )
}

function AssetUploader({
  label, hint, currentUrl, uploading, onSelect, onClear, previewBg, maxHeight,
}: {
  label: string
  hint: string
  currentUrl: string
  uploading: boolean
  onSelect: (file: File) => void
  onClear: () => void
  previewBg: string
  maxHeight: number
}) {
  const { t } = useTranslation()
  const inputRef = useRef<HTMLInputElement>(null)
  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <p className="text-xs text-[var(--text-tertiary)]">{hint}</p>
      <div className="flex flex-wrap items-center gap-3">
        <div
          className="flex h-20 w-32 items-center justify-center rounded-lg border border-[var(--border-default)] overflow-hidden"
          style={{ backgroundColor: previewBg }}
        >
          {currentUrl ? (
            <img src={currentUrl} alt={label} style={{ maxHeight, maxWidth: '100%' }} />
          ) : (
            <ImageIcon className="h-6 w-6 text-[var(--text-tertiary)]" strokeWidth={1.5} />
          )}
        </div>
        <input
          ref={inputRef}
          type="file"
          accept="image/jpeg,image/png"
          className="hidden"
          onChange={(e) => {
            const f = e.target.files?.[0]
            if (f) onSelect(f)
            if (inputRef.current) inputRef.current.value = ''
          }}
        />
        <Button variant="outline" onClick={() => inputRef.current?.click()} disabled={uploading}>
          {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" strokeWidth={1.5} />}
          {currentUrl ? t('settings.branding_replace') : t('settings.branding_upload')}
        </Button>
        {currentUrl && (
          <Button variant="ghost" size="sm" onClick={onClear}>
            <X className="h-4 w-4" strokeWidth={1.5} /> {t('settings.branding_remove')}
          </Button>
        )}
      </div>
    </div>
  )
}
