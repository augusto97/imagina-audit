import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { motion } from 'framer-motion'
import { GitCompareArrows, Loader2, Trophy, Minus, ArrowLeft } from 'lucide-react'
import Layout from '@/components/layout/Layout'
import ScoreGauge from '@/components/audit/ScoreGauge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { MODULE_EMOJIS } from '@/lib/constants'
import api from '@/lib/api'
import type { AuditResult } from '@/types/audit'

interface CompareData {
  audit1: AuditResult
  audit2: AuditResult
  comparison: {
    winner: 'url1' | 'url2' | 'tie'
    scoreDifference: number
    moduleComparison: Array<{
      moduleId: string; moduleName: string; score1: number; score2: number; winner: string
    }>
  }
}

export default function ComparePage() {
  const [data, setData] = useState<CompareData | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit } = useForm<{ url1: string; url2: string }>()

  const onSubmit = async (form: { url1: string; url2: string }) => {
    setLoading(true)
    setError(null)
    setData(null)
    try {
      const url1 = form.url1.startsWith('http') ? form.url1 : `https://${form.url1}`
      const url2 = form.url2.startsWith('http') ? form.url2 : `https://${form.url2}`
      const res = await api.post('/compare.php', { url1, url2 })
      setData(res.data.data)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error || 'Error al comparar los sitios'
      setError(msg)
    }
    setLoading(false)
  }

  return (
    <Layout>
      <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        {!data ? (
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-center">
            <GitCompareArrows className="mx-auto h-12 w-12 text-[var(--accent-primary)]" strokeWidth={1} />
            <h1 className="mt-4 text-3xl font-bold text-[var(--text-primary)]">
              Compara Dos Sitios <span className="highlight-yellow">WordPress</span>
            </h1>
            <p className="mt-2 text-[var(--text-secondary)]">Descubre cuál está mejor optimizado en seguridad, velocidad y SEO</p>

            <Card className="mx-auto mt-8 max-w-2xl shadow-lg">
              <CardContent className="p-6 sm:p-8">
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Sitio 1</label>
                      <Input {...register('url1', { required: true })} placeholder="https://tusitio.com" disabled={loading} />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Sitio 2</label>
                      <Input {...register('url2', { required: true })} placeholder="https://competidor.com" disabled={loading} />
                    </div>
                  </div>
                  <Button type="submit" size="xl" className="w-full" disabled={loading}>
                    {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <GitCompareArrows className="h-5 w-5" strokeWidth={1.5} />}
                    {loading ? 'Comparando... (puede tardar hasta 2 min)' : 'Comparar'}
                  </Button>
                  {error && <p className="text-sm text-red-500">{error}</p>}
                  <p className="text-xs text-[var(--text-tertiary)]">El análisis puede tardar hasta 2 minutos</p>
                </form>
              </CardContent>
            </Card>
          </motion.div>
        ) : (
          <CompareResults data={data} onReset={() => setData(null)} />
        )}
      </div>
    </Layout>
  )
}

function CompareResults({ data, onReset }: { data: CompareData; onReset: () => void }) {
  const { audit1, audit2, comparison } = data
  const winnerLabel = comparison.winner === 'url1' ? audit1.domain : comparison.winner === 'url2' ? audit2.domain : 'Empate'

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-8">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button variant="ghost" size="sm" onClick={onReset}><ArrowLeft className="h-4 w-4" /> Nueva comparación</Button>
      </div>

      {/* Score cara a cara */}
      <Card className="overflow-hidden border-0 shadow-lg">
        <CardContent className="p-8">
          <div className="grid grid-cols-3 items-center gap-4">
            <div className="text-center">
              <ScoreGauge score={audit1.globalScore} level={audit1.globalLevel} size="md" />
              <p className="mt-2 text-sm font-semibold text-[var(--text-primary)]">{audit1.domain}</p>
            </div>
            <div className="text-center">
              {comparison.winner === 'tie' ? (
                <div className="flex flex-col items-center gap-1">
                  <Minus className="h-8 w-8 text-[var(--text-tertiary)]" />
                  <Badge variant="secondary">Empate</Badge>
                </div>
              ) : (
                <div className="flex flex-col items-center gap-1">
                  <Trophy className="h-8 w-8 text-amber-500" strokeWidth={1.5} />
                  <Badge variant="success">{winnerLabel} gana por {comparison.scoreDifference} pts</Badge>
                </div>
              )}
              <p className="mt-1 text-[10px] text-[var(--text-tertiary)]">VS</p>
            </div>
            <div className="text-center">
              <ScoreGauge score={audit2.globalScore} level={audit2.globalLevel} size="md" />
              <p className="mt-2 text-sm font-semibold text-[var(--text-primary)]">{audit2.domain}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabla por módulo */}
      <Card className="border-0 shadow-sm">
        <CardHeader><CardTitle>Comparación por Módulo</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {comparison.moduleComparison.map((mod, i) => {
            const max = Math.max(mod.score1, mod.score2, 1)
            const w1 = (mod.score1 / max) * 100
            const w2 = (mod.score2 / max) * 100
            return (
              <motion.div key={mod.moduleId} initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: i * 0.05 }}
                className="rounded-xl border border-[var(--border-default)] p-4">
                <div className="mb-2 flex items-center justify-between text-sm">
                  <span className="font-medium">{MODULE_EMOJIS[mod.moduleId] || ''} {mod.moduleName}</span>
                  <div className="flex items-center gap-3 text-xs">
                    <span className={mod.winner === 'url1' ? 'font-bold text-[var(--accent-primary)]' : 'text-[var(--text-tertiary)]'}>{mod.score1}</span>
                    <span className="text-[var(--text-tertiary)]">vs</span>
                    <span className={mod.winner === 'url2' ? 'font-bold text-violet-500' : 'text-[var(--text-tertiary)]'}>{mod.score2}</span>
                  </div>
                </div>
                <div className="flex gap-1 h-3">
                  <motion.div initial={{ width: 0 }} animate={{ width: `${w1}%` }} transition={{ duration: 0.8, delay: i * 0.05 }}
                    className="rounded-l-full bg-[var(--accent-primary)] min-w-[4px]" />
                  <motion.div initial={{ width: 0 }} animate={{ width: `${w2}%` }} transition={{ duration: 0.8, delay: i * 0.05 + 0.1 }}
                    className="rounded-r-full bg-violet-400 min-w-[4px]" />
                </div>
              </motion.div>
            )
          })}
        </CardContent>
      </Card>

      {/* Links a informes individuales */}
      <div className="grid gap-4 sm:grid-cols-2">
        <a href={`/results/${audit1.id}`} target="_blank" rel="noreferrer">
          <Button variant="outline" className="w-full">Ver informe completo — {audit1.domain}</Button>
        </a>
        <a href={`/results/${audit2.id}`} target="_blank" rel="noreferrer">
          <Button variant="outline" className="w-full">Ver informe completo — {audit2.domain}</Button>
        </a>
      </div>
    </motion.div>
  )
}
