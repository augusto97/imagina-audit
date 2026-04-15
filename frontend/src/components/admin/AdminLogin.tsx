import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Lock, Shield, Loader2 } from 'lucide-react'
import { motion } from 'framer-motion'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import { useAuth } from '@/hooks/useAuth'

export default function AdminLogin() {
  const { login } = useAuth()
  const [loading, setLoading] = useState(false)
  const { register, handleSubmit } = useForm<{ password: string }>()

  const onSubmit = async (data: { password: string }) => {
    setLoading(true)
    try {
      await login(data.password)
    } catch {
      toast.error('Contraseña incorrecta')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)] px-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-sm"
      >
        <Card className="shadow-lg">
          <CardContent className="p-8">
            <div className="mb-6 flex flex-col items-center gap-2">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--accent-primary)]/10">
                <Shield className="h-6 w-6 text-[var(--accent-primary)]" strokeWidth={1.5} />
              </div>
              <h1 className="text-lg font-bold text-[var(--text-primary)]">Panel de Administración</h1>
              <p className="text-sm text-[var(--text-tertiary)]">Imagina Audit</p>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
                <Input
                  {...register('password', { required: true })}
                  type="password"
                  placeholder="Contraseña"
                  className="pl-10"
                  autoFocus
                  disabled={loading}
                />
              </div>
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Ingresar'}
              </Button>
            </form>
          </CardContent>
        </Card>
      </motion.div>
    </div>
  )
}
