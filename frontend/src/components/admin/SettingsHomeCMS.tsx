import { useEffect, useState } from 'react'
import { Loader2, Save, Search, FileText, Eye } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAdmin } from '@/hooks/useAdmin'
import { useConfigStore } from '@/store/configStore'

interface HomeForm {
  seoTitle: string
  seoDescription: string
  seoOgImage: string
  heroHeadline: string
  heroSubheadline: string
  formButtonText: string
  formMicrocopy: string
  featuresTitle: string
  trustText: string
}

const FIELD_LIMITS = {
  seoTitle: 70,       // Google corta ~60-70 chars
  seoDescription: 160,
}

const SETTING_KEYS: Record<keyof HomeForm, string> = {
  seoTitle: 'homeSeoTitle',
  seoDescription: 'homeSeoDescription',
  seoOgImage: 'homeSeoOgImage',
  heroHeadline: 'homeHeroHeadline',
  heroSubheadline: 'homeHeroSubheadline',
  formButtonText: 'homeFormButtonText',
  formMicrocopy: 'homeFormMicrocopy',
  featuresTitle: 'homeFeaturesTitle',
  trustText: 'homeTrustText',
}

export default function SettingsHomeCMS() {
  const { fetchSettings, updateSettings } = useAdmin()
  const reloadConfig = useConfigStore((s) => s.reload)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<HomeForm>({
    seoTitle: '', seoDescription: '', seoOgImage: '',
    heroHeadline: '', heroSubheadline: '',
    formButtonText: '', formMicrocopy: '',
    featuresTitle: '', trustText: '',
  })

  useEffect(() => {
    fetchSettings().then((data: Record<string, string>) => {
      setForm({
        seoTitle:        data.homeSeoTitle        ?? data.home_seo_title        ?? '',
        seoDescription:  data.homeSeoDescription  ?? data.home_seo_description  ?? '',
        seoOgImage:      data.homeSeoOgImage      ?? data.home_seo_og_image     ?? '',
        heroHeadline:    data.homeHeroHeadline    ?? data.home_hero_headline    ?? '',
        heroSubheadline: data.homeHeroSubheadline ?? data.home_hero_subheadline ?? '',
        formButtonText:  data.homeFormButtonText  ?? data.home_form_button_text ?? '',
        formMicrocopy:   data.homeFormMicrocopy   ?? data.home_form_microcopy   ?? '',
        featuresTitle:   data.homeFeaturesTitle   ?? data.home_features_title   ?? '',
        trustText:       data.homeTrustText       ?? data.home_trust_text       ?? '',
      })
      setLoading(false)
    })
  }, [fetchSettings])

  const update = <K extends keyof HomeForm>(key: K, value: HomeForm[K]) => {
    setForm((f) => ({ ...f, [key]: value }))
  }

  const save = async () => {
    setSaving(true)
    try {
      const payload: Record<string, string> = {}
      for (const k of Object.keys(SETTING_KEYS) as (keyof HomeForm)[]) {
        payload[SETTING_KEYS[k]] = form[k]
      }
      await updateSettings(payload)
      toast.success('Home actualizado')
      reloadConfig()
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Home pública</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          SEO y textos visibles en la landing pública ({window.location.origin}/).
        </p>
      </div>

      <Tabs defaultValue="seo">
        <TabsList>
          <TabsTrigger value="seo"><Search className="h-4 w-4 mr-1" strokeWidth={1.5} /> SEO</TabsTrigger>
          <TabsTrigger value="texts"><FileText className="h-4 w-4 mr-1" strokeWidth={1.5} /> Textos</TabsTrigger>
          <TabsTrigger value="preview"><Eye className="h-4 w-4 mr-1" strokeWidth={1.5} /> Preview</TabsTrigger>
        </TabsList>

        {/* SEO */}
        <TabsContent value="seo">
          <Card>
            <CardHeader><CardTitle>Metadatos del home</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label>Título SEO (&lt;title&gt;)</Label>
                  <span className={`text-[11px] ${form.seoTitle.length > FIELD_LIMITS.seoTitle ? 'text-red-500' : 'text-[var(--text-tertiary)]'}`}>
                    {form.seoTitle.length} / {FIELD_LIMITS.seoTitle}
                  </span>
                </div>
                <Input
                  value={form.seoTitle}
                  onChange={(e) => update('seoTitle', e.target.value)}
                  placeholder="Auditoría WordPress gratuita · Tu Marca"
                />
              </div>

              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label>Meta description</Label>
                  <span className={`text-[11px] ${form.seoDescription.length > FIELD_LIMITS.seoDescription ? 'text-red-500' : 'text-[var(--text-tertiary)]'}`}>
                    {form.seoDescription.length} / {FIELD_LIMITS.seoDescription}
                  </span>
                </div>
                <Textarea
                  rows={3}
                  value={form.seoDescription}
                  onChange={(e) => update('seoDescription', e.target.value)}
                  placeholder="Descripción corta que aparece en resultados de búsqueda."
                />
              </div>

              <div className="space-y-1.5">
                <Label>Imagen para redes sociales (og:image)</Label>
                <Input
                  value={form.seoOgImage}
                  onChange={(e) => update('seoOgImage', e.target.value)}
                  placeholder="https://tusitio.com/og-image.jpg (1200×630 recomendado)"
                />
                <p className="text-xs text-[var(--text-tertiary)]">
                  URL absoluta. Aparece al compartir el link en redes sociales.
                </p>
              </div>

              {/* Preview Google */}
              <div className="mt-6 rounded-lg border border-[var(--border-default)] bg-white p-4">
                <p className="text-[11px] uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Preview de Google</p>
                <div className="font-serif">
                  <div className="text-[13px] text-[#4d5156]">{window.location.hostname}</div>
                  <div className="text-[18px] text-[#1a0dab] truncate">{form.seoTitle || 'Título SEO'}</div>
                  <div className="text-[13px] text-[#4d5156] line-clamp-2">{form.seoDescription || 'Meta description...'}</div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Textos */}
        <TabsContent value="texts">
          <Card>
            <CardHeader><CardTitle>Textos del home</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1.5">
                <Label>Titular principal (hero)</Label>
                <Input value={form.heroHeadline} onChange={(e) => update('heroHeadline', e.target.value)} />
                <p className="text-xs text-[var(--text-tertiary)]">El título grande que se ve al cargar la página.</p>
              </div>

              <div className="space-y-1.5">
                <Label>Subtítulo (hero)</Label>
                <Textarea rows={2} value={form.heroSubheadline} onChange={(e) => update('heroSubheadline', e.target.value)} />
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Texto del botón del formulario</Label>
                  <Input value={form.formButtonText} onChange={(e) => update('formButtonText', e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>Micro-copy bajo el formulario</Label>
                  <Input value={form.formMicrocopy} onChange={(e) => update('formMicrocopy', e.target.value)} />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label>Título de la sección de features (8 áreas)</Label>
                <Input value={form.featuresTitle} onChange={(e) => update('featuresTitle', e.target.value)} />
              </div>

              <div className="space-y-1.5">
                <Label>Trust bar (texto de confianza)</Label>
                <Input value={form.trustText} onChange={(e) => update('trustText', e.target.value)} />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Preview */}
        <TabsContent value="preview">
          <Card>
            <CardHeader><CardTitle>Vista previa del hero</CardTitle></CardHeader>
            <CardContent>
              <div className="rounded-lg border border-[var(--border-default)] bg-gradient-to-b from-white to-[var(--bg-secondary)] p-8 text-center">
                <h1 className="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">
                  {form.heroHeadline || 'Titular principal'}
                </h1>
                <p className="mt-3 text-sm sm:text-base text-[var(--text-secondary)] max-w-xl mx-auto">
                  {form.heroSubheadline || 'Subtítulo'}
                </p>
                <div className="mt-6">
                  <button className="rounded px-5 py-2.5 text-sm font-medium text-white" style={{ backgroundColor: 'var(--accent-primary)' }}>
                    {form.formButtonText || 'Botón'}
                  </button>
                </div>
                <p className="mt-3 text-xs text-[var(--text-tertiary)]">
                  {form.formMicrocopy || 'micro-copy...'}
                </p>
                <hr className="my-6 border-[var(--border-default)]" />
                <h2 className="text-lg font-semibold text-[var(--text-primary)]">
                  {form.featuresTitle || 'Título de features'}
                </h2>
                <hr className="my-6 border-[var(--border-default)]" />
                <p className="text-sm text-[var(--text-secondary)]">
                  {form.trustText || 'Trust bar'}
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        Guardar cambios
      </Button>
    </div>
  )
}
