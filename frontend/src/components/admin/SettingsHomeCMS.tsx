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

        {/* Preview — una maqueta visual del home real con los textos editados */}
        <TabsContent value="preview">
          <Card>
            <CardHeader>
              <CardTitle>Vista previa del home</CardTitle>
              <p className="text-xs text-[var(--text-tertiary)] mt-1">
                Muestra cómo quedará el home con los textos editados. No refleja los cambios hasta que guardes.
              </p>
            </CardHeader>
            <CardContent>
              <HomePreview form={form} />
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

/**
 * Maqueta simplificada del home público con los textos que el admin está
 * editando. Replica la estructura visual del HomePage real: hero + grid
 * de 8 módulos + trust bar.
 */
function HomePreview({ form }: { form: HomeForm }) {
  const moduleIds = ['security', 'performance', 'seo', 'wordpress', 'mobile', 'infrastructure', 'conversion', 'page_health']
  const moduleLabels: Record<string, { icon: string; name: string }> = {
    security: { icon: '🛡️', name: 'Seguridad' },
    performance: { icon: '⚡', name: 'Rendimiento' },
    seo: { icon: '🔍', name: 'SEO' },
    wordpress: { icon: '🧩', name: 'WordPress' },
    mobile: { icon: '📱', name: 'Móvil' },
    infrastructure: { icon: '🖥️', name: 'Infraestructura' },
    conversion: { icon: '📊', name: 'Conversión' },
    page_health: { icon: '🩺', name: 'Salud de Página' },
  }
  const microItems = (form.formMicrocopy || '').split(/\s*[·•|]\s*/).filter(Boolean)
  const tools = ['Elementor', 'WP Rocket', 'Rank Math', 'Gravity Forms', 'Cloudflare', 'WooCommerce']

  return (
    <div className="rounded-xl border border-[var(--border-default)] overflow-hidden shadow-sm">
      {/* Fake browser chrome */}
      <div className="flex items-center gap-1.5 bg-[#ececec] px-3 py-1.5 border-b border-[var(--border-default)]">
        <span className="h-2.5 w-2.5 rounded-full bg-[#ff5f57]" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#ffbd2e]" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#28c840]" />
        <span className="ml-3 text-[11px] text-[#666] font-mono">{window.location.hostname}/</span>
      </div>

      {/* Hero */}
      <div className="bg-gradient-to-b from-white to-[var(--bg-secondary)] px-8 py-12 text-center">
        <h1 className="text-2xl sm:text-4xl font-bold text-[var(--text-primary)]">
          {form.heroHeadline || <span className="italic text-[var(--text-tertiary)]">Titular principal (vacío)</span>}
        </h1>
        <p className="mx-auto mt-3 max-w-xl text-sm sm:text-base text-[var(--text-secondary)]">
          {form.heroSubheadline || <span className="italic">Subtítulo (vacío)</span>}
        </p>

        {/* Form fake */}
        <div className="mx-auto mt-8 max-w-md rounded-xl border border-[var(--border-default)] bg-white p-4 shadow-sm">
          <div className="rounded border border-[var(--border-default)] px-3 py-2 text-left text-xs text-[var(--text-tertiary)]">
            https://tusitio.com
          </div>
          <button className="mt-3 w-full rounded px-5 py-2.5 text-sm font-medium text-white" style={{ backgroundColor: 'var(--accent-primary)' }}>
            {form.formButtonText || 'Botón del formulario'}
          </button>
          <div className="mt-3 flex flex-wrap justify-center gap-x-3 gap-y-1 text-[11px] text-[var(--text-tertiary)]">
            {microItems.length > 0 ? microItems.map((m) => <span key={m}>{m}</span>) : <span className="italic">micro-copy (vacío)</span>}
          </div>
        </div>
      </div>

      {/* Features */}
      <div className="bg-white px-8 py-10">
        <h2 className="mb-6 text-center text-lg sm:text-xl font-bold text-[var(--text-primary)]">
          {form.featuresTitle || <span className="italic text-[var(--text-tertiary)]">Título de features (vacío)</span>}
        </h2>
        <div className="mx-auto grid max-w-2xl grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
          {moduleIds.map((id) => (
            <div key={id} className="flex flex-col items-center gap-1 rounded-lg border border-[var(--border-default)] bg-white p-3 text-center">
              <span className="text-xl">{moduleLabels[id].icon}</span>
              <span className="text-[11px] font-semibold text-[var(--text-primary)]">{moduleLabels[id].name}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Trust bar */}
      <div className="border-t border-[var(--border-default)] bg-[var(--bg-secondary)] px-8 py-8 text-center">
        <p className="text-xs sm:text-sm font-medium text-[var(--text-secondary)]">
          {form.trustText || <span className="italic text-[var(--text-tertiary)]">Trust bar (vacío)</span>}
        </p>
        <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
          {tools.map((t) => (
            <span key={t} className="rounded-full border border-[var(--border-default)] bg-white px-3 py-1 text-[10px] font-medium text-[var(--text-secondary)]">{t}</span>
          ))}
        </div>
      </div>
    </div>
  )
}
