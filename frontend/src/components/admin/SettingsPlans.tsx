import { useEffect, useState } from 'react'
import { Loader2, Save, Plus, X } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

interface Plan { name: string; price: string; currency: string }

export default function SettingsPlans() {
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [plans, setPlans] = useState<Plan[]>([])

  useEffect(() => {
    fetchSettings().then((data: { plans?: Plan[] }) => {
      setPlans(data.plans || [{ name: 'Basic', price: '97', currency: 'USD' }])
      setLoading(false)
    })
  }, [fetchSettings])

  const updatePlan = (idx: number, field: keyof Plan, value: string) => {
    setPlans((prev) => prev.map((p, i) => i === idx ? { ...p, [field]: value } : p))
  }

  const addPlan = () => setPlans((prev) => [...prev, { name: '', price: '', currency: 'USD' }])
  const removePlan = (idx: number) => { if (plans.length > 1) setPlans((prev) => prev.filter((_, i) => i !== idx)) }

  const save = async () => {
    setSaving(true)
    try {
      await updateSettings({ plans })
      toast.success('Planes guardados')
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-64 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Planes y Precios</h1>
        <p className="text-sm text-[var(--text-secondary)]">Se muestran en la tabla de soluciones y el CTA del informe</p>
      </div>

      <Card>
        <CardHeader><CardTitle>Planes</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {plans.map((plan, idx) => (
            <div key={idx} className="flex flex-wrap items-center gap-2">
              <Input value={plan.name} onChange={(e) => updatePlan(idx, 'name', e.target.value)} placeholder="Nombre" className="w-40" />
              <Input value={plan.price} onChange={(e) => updatePlan(idx, 'price', e.target.value)} placeholder="Precio" className="w-28" />
              <select value={plan.currency} onChange={(e) => updatePlan(idx, 'currency', e.target.value)}
                className="h-10 rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm">
                <option value="USD">USD</option>
                <option value="COP">COP</option>
                <option value="EUR">EUR</option>
              </select>
              {plans.length > 1 && (
                <Button variant="ghost" size="icon" className="h-8 w-8 text-red-400" onClick={() => removePlan(idx)}><X className="h-4 w-4" /></Button>
              )}
            </div>
          ))}
          <Button variant="outline" size="sm" onClick={addPlan}><Plus className="h-4 w-4" strokeWidth={1.5} /> Agregar Plan</Button>
        </CardContent>
      </Card>

      {/* Preview */}
      <Card>
        <CardContent className="p-5">
          <p className="text-xs font-medium text-[var(--text-tertiary)] mb-3">Vista previa</p>
          <div className="flex flex-wrap gap-2">
            {plans.filter((p) => p.name).map((p, i) => (
              <Badge key={i} variant="secondary" className="text-sm px-3 py-1">
                {p.name}: ${p.price} {p.currency}
              </Badge>
            ))}
          </div>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        Guardar Planes
      </Button>
    </div>
  )
}
