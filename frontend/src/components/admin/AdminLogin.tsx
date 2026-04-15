import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Lock, Shield, Loader2, ArrowRight } from 'lucide-react'
import { motion } from 'framer-motion'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
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
    <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8] px-4">
      {/* Fondo decorativo */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-[var(--accent-primary)]/5 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-[var(--accent-yellow)]/10 blur-3xl" />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.96 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
        className="relative w-full max-w-sm"
      >
        <div className="rounded-2xl border border-[var(--border-default)] bg-white p-8 shadow-xl shadow-black/[0.03]">
          {/* Logo */}
          <div className="mb-8 flex flex-col items-center gap-3">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8] shadow-lg shadow-[var(--accent-primary)]/25">
              <Shield className="h-7 w-7 text-white" strokeWidth={2} />
            </div>
            <div className="text-center">
              <h1 className="text-xl font-bold text-[var(--text-primary)]">Bienvenido</h1>
              <p className="text-sm text-[var(--text-tertiary)] mt-0.5">Ingresa al panel de administración</p>
            </div>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">Contraseña</label>
              <div className="relative">
                <Lock className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
                <Input
                  {...register('password', { required: true })}
                  type="password"
                  placeholder="Ingresa tu contraseña"
                  className="h-11 pl-10 bg-[var(--bg-secondary)] border-[var(--border-default)]"
                  autoFocus
                  disabled={loading}
                />
              </div>
            </div>
            <Button type="submit" className="w-full h-11" disabled={loading}>
              {loading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <>
                  Ingresar
                  <ArrowRight className="h-4 w-4 ml-1" strokeWidth={1.5} />
                </>
              )}
            </Button>
          </form>

          <p className="mt-6 text-center text-[11px] text-[var(--text-tertiary)]">
            Imagina Audit &middot; Panel de Administración
          </p>
        </div>
      </motion.div>
    </div>
  )
}
