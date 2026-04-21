import { useEffect, useRef, useState } from 'react'
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
      toast.error('Formato no permitido. Solo JPG y PNG.')
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      toast.error('Archivo demasiado grande. Máximo 2 MB.')
      return
    }
    setUploadingType(type)
    try {
      const res = await uploadBrandAsset(type, file)
      if (res) {
        if (type === 'logo') setLogoUrl(res.url)
        if (type === 'logo_collapsed') setLogoCollapsedUrl(res.url)
        if (type === 'favicon') setFaviconUrl(res.url)
        toast.success('Imagen subida')
        reloadConfig()
      }
    } catch { toast.error('Error al subir la imagen') }
    setUploadingType(null)
  }

  const clearAsset = async (type: AssetType) => {
    const key = type === 'logo' ? 'logoUrl' : type === 'logo_collapsed' ? 'logoCollapsedUrl' : 'faviconUrl'
    try {
      await updateSettings({ [key]: '' })
      if (type === 'logo') setLogoUrl('')
      if (type === 'logo_collapsed') setLogoCollapsedUrl('')
      if (type === 'favicon') setFaviconUrl('')
      toast.success('Imagen eliminada')
      reloadConfig()
    } catch { toast.error('No se pudo eliminar') }
  }

  const saveColor = async () => {
    setSaving(true)
    try {
      await updateSettings({ brandPrimaryColor: color })
      toast.success('Color guardado')
      reloadConfig()
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Branding</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          Color principal, logos y favicon del sitio público.
        </p>
      </div>

      {/* Color principal */}
      <Card>
        <CardHeader><CardTitle>Color principal</CardTitle></CardHeader>
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
            <p className="text-xs uppercase tracking-wider text-[var(--text-tertiary)] mb-3">Vista previa</p>
            <div className="flex flex-wrap items-center gap-3">
              <button style={{ backgroundColor: color }} className="rounded px-4 py-2 text-sm font-medium text-white shadow">
                Botón primario
              </button>
              <span style={{ color }} className="text-sm font-semibold underline">
                Texto con acento
              </span>
              <span className="inline-flex h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                <span style={{ backgroundColor: color, width: '72%' }} className="h-full" />
              </span>
            </div>
          </div>

          <Button onClick={saveColor} disabled={saving}>
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
            Guardar color
          </Button>
        </CardContent>
      </Card>

      {/* Logos y favicon */}
      <Card>
        <CardHeader><CardTitle>Logos y favicon</CardTitle></CardHeader>
        <CardContent className="space-y-5">
          <AssetUploader
            label="Logo principal"
            hint="Se muestra en el header del sitio público. Recomendado: PNG con fondo transparente, altura ~40 px."
            currentUrl={logoUrl}
            uploading={uploadingType === 'logo'}
            onSelect={(f) => upload('logo', f)}
            onClear={() => clearAsset('logo')}
            previewBg="#f6f6f6"
            maxHeight={64}
          />

          <AssetUploader
            label="Logo colapsado / isotipo"
            hint="Versión reducida (solo símbolo) para sidebars y espacios pequeños. Ideal: cuadrado 80×80 px."
            currentUrl={logoCollapsedUrl}
            uploading={uploadingType === 'logo_collapsed'}
            onSelect={(f) => upload('logo_collapsed', f)}
            onClear={() => clearAsset('logo_collapsed')}
            previewBg="#f6f6f6"
            maxHeight={48}
          />

          <AssetUploader
            label="Favicon"
            hint="Icono del navegador. Recomendado: PNG cuadrado 32×32 o 64×64."
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
          {currentUrl ? 'Reemplazar' : 'Subir imagen'}
        </Button>
        {currentUrl && (
          <Button variant="ghost" size="sm" onClick={onClear}>
            <X className="h-4 w-4" strokeWidth={1.5} /> Quitar
          </Button>
        )}
      </div>
    </div>
  )
}
