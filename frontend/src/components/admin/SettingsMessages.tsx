import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Loader2, Save } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { useAdmin } from '@/hooks/useAdmin'
import { Skeleton } from '@/components/ui/skeleton'
import { MODULE_EMOJIS, MODULE_NAMES } from '@/lib/constants'

const moduleIds = ['wordpress', 'security', 'performance', 'seo', 'mobile', 'infrastructure', 'conversion']

export default function SettingsMessages() {
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const { register, handleSubmit, reset, watch } = useForm()

  useEffect(() => {
    fetchSettings().then((data: Record<string, unknown>) => {
      const flat: Record<string, unknown> = {}
      const msgs = data.salesMessages as Record<string, string> || {}
      for (const id of moduleIds) flat[`sales_${id}`] = msgs[id] || ''
      flat.ctaTitle = data.ctaTitle
      flat.ctaDescription = data.ctaDescription
      flat.ctaButtonWhatsappText = data.ctaButtonWhatsappText
      flat.ctaButtonPlansText = data.ctaButtonPlansText
      reset(flat)
      setLoading(false)
    })
  }, [fetchSettings, reset])

  const onSubmit = async (data: Record<string, unknown>) => {
    setSaving(true)
    try {
      const salesMessages: Record<string, string> = {}
      for (const id of moduleIds) {
        salesMessages[id] = (data[`sales_${id}`] as string) || ''
      }
      await updateSettings({
        salesMessages,
        ctaTitle: data.ctaTitle,
        ctaDescription: data.ctaDescription,
        ctaButtonWhatsappText: data.ctaButtonWhatsappText,
        ctaButtonPlansText: data.ctaButtonPlansText,
      })
      toast.success('Mensajes guardados correctamente')
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <div className="space-y-4">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}</div>

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Textos y Mensajes</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">Mensajes de venta que aparecen en cada módulo del informe</p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        <Tabs defaultValue="modules">
          <TabsList>
            <TabsTrigger value="modules">Mensajes por Módulo</TabsTrigger>
            <TabsTrigger value="cta">CTA Final</TabsTrigger>
          </TabsList>

          <TabsContent value="modules">
            <Card>
              <CardContent className="space-y-5 pt-6">
                {moduleIds.map((id) => (
                  <div key={id} className="space-y-1.5">
                    <Label>{MODULE_EMOJIS[id]} {MODULE_NAMES[id]}</Label>
                    <Textarea {...register(`sales_${id}`)} rows={3} placeholder={`Mensaje de venta para ${MODULE_NAMES[id]}...`} />
                  </div>
                ))}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="cta">
            <Card>
              <CardHeader><CardTitle>Textos del CTA Final</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-1.5">
                  <Label>Título del CTA</Label>
                  <Input {...register('ctaTitle')} />
                </div>
                <div className="space-y-1.5">
                  <Label>Descripción del CTA</Label>
                  <Textarea {...register('ctaDescription')} rows={3} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>Texto botón WhatsApp</Label>
                    <Input {...register('ctaButtonWhatsappText')} />
                  </div>
                  <div className="space-y-1.5">
                    <Label>Texto botón Planes</Label>
                    <Input {...register('ctaButtonPlansText')} />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Preview */}
            <Card className="mt-4 border-[var(--accent-primary)]/20 bg-gradient-to-br from-[#F0FDFE] to-white">
              <CardContent className="text-center pt-6">
                <p className="text-xs font-medium text-[var(--text-tertiary)] mb-3">Vista previa</p>
                <h3 className="text-lg font-bold text-[var(--text-primary)]">{watch('ctaTitle') as string || 'Título'}</h3>
                <p className="mt-2 text-sm text-[var(--text-secondary)]">{watch('ctaDescription') as string || 'Descripción'}</p>
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                  <Button size="sm" variant="success">{watch('ctaButtonWhatsappText') as string || 'WhatsApp'}</Button>
                  <Button size="sm" variant="outline">{watch('ctaButtonPlansText') as string || 'Planes'}</Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        <Button type="submit" disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
          Guardar Mensajes
        </Button>
      </form>
    </div>
  )
}
