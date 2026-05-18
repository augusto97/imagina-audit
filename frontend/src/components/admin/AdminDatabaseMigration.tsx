import { useEffect, useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Database, ArrowRight, CheckCircle2, AlertCircle, Play, RefreshCw, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import api from '@/lib/api'

/**
 * /admin/migrate-database — herramienta de migración SQLite → MySQL.
 *
 * Solo visible/útil cuando el driver actual es SQLite. Si la app ya
 * está en MySQL, la página muestra un estado informativo y nada más.
 *
 * Flujo:
 *   1. Mostrar conteos actuales en SQLite (preview).
 *   2. Formulario de credenciales MySQL + botón Test.
 *   3. Botón Migrar → ejecuta y muestra resultado por tabla.
 *   4. Botón Switch driver → escribe .env y promete reload.
 */

interface Status {
  currentDriver: string
  canMigrate: boolean
  envHasMysql: boolean
  sourceTableCounts?: Record<string, number | null>
  sourceTotalRows?: number
}

interface MigrationResult {
  ok: boolean
  schemaMigrationsApplied: number
  rowsCopied: number
  durationSeconds: number
  tables: Record<string, { source: number; copied: number; target: number; skipped: boolean }>
}

interface MysqlConfig {
  host: string
  port: string
  name: string
  user: string
  password: string
  charset: string
}

export default function AdminDatabaseMigration() {
  const { t } = useTranslation()
  const [status, setStatus] = useState<Status | null>(null)
  const [loading, setLoading] = useState(true)
  const [config, setConfig] = useState<MysqlConfig>({
    host: 'localhost', port: '3306', name: '', user: '', password: '', charset: 'utf8mb4',
  })
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ ok: boolean; message: string } | null>(null)
  const [migrating, setMigrating] = useState(false)
  const [result, setResult] = useState<MigrationResult | null>(null)
  const [switching, setSwitching] = useState(false)

  const reload = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/admin/migrate-database.php')
      setStatus(res.data?.data as Status)
    } catch { /* ignore */ }
    setLoading(false)
  }, [])

  useEffect(() => { reload() }, [reload])

  const handleTest = async () => {
    setTesting(true)
    setTestResult(null)
    try {
      const res = await api.post('/admin/migrate-database.php', { action: 'test', ...config })
      setTestResult({ ok: true, message: 'MySQL ' + (res.data?.data?.version ?? '') })
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      setTestResult({ ok: false, message: e.response?.data?.error ?? 'Connection failed' })
    }
    setTesting(false)
  }

  const handleMigrate = async () => {
    if (!confirm(t('admin_db_migration.confirm_run'))) return
    setMigrating(true)
    setResult(null)
    try {
      const res = await api.post('/admin/migrate-database.php', { action: 'run', ...config })
      setResult(res.data?.data as MigrationResult)
      toast.success(t('admin_db_migration.migration_done'))
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? 'Migration failed', { duration: 10000 })
    }
    setMigrating(false)
  }

  const handleSwitch = async () => {
    if (!confirm(t('admin_db_migration.confirm_switch'))) return
    setSwitching(true)
    try {
      await api.post('/admin/migrate-database.php', { action: 'switch', ...config })
      toast.success(t('admin_db_migration.switched'))
      // Hard reload — el próximo boot usa el driver nuevo
      setTimeout(() => window.location.reload(), 800)
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? 'Switch failed')
      setSwitching(false)
    }
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <Database className="h-6 w-6 text-[var(--accent-primary)]" />
          {t('admin_db_migration.title')}
        </h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">{t('admin_db_migration.subtitle')}</p>
      </div>

      {/* Current driver */}
      <Card>
        <CardContent className="pt-5 flex items-center gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <span className="text-xs uppercase font-bold text-[var(--text-tertiary)]">{t('admin_db_migration.current_driver')}:</span>
            <Badge variant={status?.currentDriver === 'mysql' ? 'success' : 'secondary'}>
              {status?.currentDriver === 'mysql' ? 'MySQL' : 'SQLite'}
            </Badge>
          </div>
          {status?.currentDriver === 'mysql' && (
            <span className="text-xs text-emerald-700 flex items-center gap-1">
              <ShieldCheck className="h-3.5 w-3.5" />
              {t('admin_db_migration.already_mysql')}
            </span>
          )}
          <Button variant="ghost" size="sm" onClick={reload} className="ml-auto">
            <RefreshCw className="h-3.5 w-3.5" />
            {t('admin_db_migration.refresh')}
          </Button>
        </CardContent>
      </Card>

      {!status?.canMigrate ? (
        <Card>
          <CardContent className="py-8 text-center text-sm text-[var(--text-secondary)]">
            <ShieldCheck className="h-8 w-8 text-emerald-600 mx-auto mb-2" />
            {t('admin_db_migration.nothing_to_do')}
          </CardContent>
        </Card>
      ) : (
        <>
          {/* Source preview */}
          {status?.sourceTableCounts && (
            <Card>
              <CardContent className="pt-5 space-y-2">
                <h3 className="font-semibold text-sm">{t('admin_db_migration.source_preview')}</h3>
                <p className="text-xs text-[var(--text-tertiary)]">{t('admin_db_migration.source_total', { count: status.sourceTotalRows ?? 0 })}</p>
                <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3 text-xs">
                  {Object.entries(status.sourceTableCounts).map(([table, count]) => (
                    <div key={table} className="flex items-center justify-between rounded border border-[var(--border-default)] px-2 py-1">
                      <span className="font-mono text-[10px] text-[var(--text-secondary)]">{table}</span>
                      <span className="font-bold tabular-nums">{count ?? '—'}</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Target form */}
          <Card>
            <CardContent className="pt-5 space-y-4">
              <div>
                <h3 className="font-semibold text-sm flex items-center gap-2">
                  <ArrowRight className="h-4 w-4 text-[var(--accent-primary)]" />
                  {t('admin_db_migration.target_title')}
                </h3>
                <p className="text-xs text-[var(--text-tertiary)] mt-1">{t('admin_db_migration.target_subtitle')}</p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('admin_db_migration.field_host')} value={config.host} onChange={v => setConfig(c => ({ ...c, host: v }))} />
                <Field label={t('admin_db_migration.field_port')} value={config.port} onChange={v => setConfig(c => ({ ...c, port: v }))} />
                <Field label={t('admin_db_migration.field_db')} value={config.name} onChange={v => setConfig(c => ({ ...c, name: v }))} />
                <Field label={t('admin_db_migration.field_user')} value={config.user} onChange={v => setConfig(c => ({ ...c, user: v }))} />
                <Field label={t('admin_db_migration.field_password')} value={config.password} onChange={v => setConfig(c => ({ ...c, password: v }))} type="password" className="sm:col-span-2" />
              </div>

              <div className="flex items-center gap-2 pt-1 flex-wrap">
                <Button variant="outline" onClick={handleTest} disabled={testing || !config.name || !config.user}>
                  {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Database className="h-4 w-4" />}
                  {t('admin_db_migration.test')}
                </Button>
                {testResult && (
                  <span className={`text-xs flex items-center gap-1.5 ${testResult.ok ? 'text-emerald-700' : 'text-red-600'}`}>
                    {testResult.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertCircle className="h-3.5 w-3.5" />}
                    {testResult.message}
                  </span>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Run migration */}
          <Card className="border-amber-200 bg-amber-50/30">
            <CardContent className="pt-5 space-y-3">
              <p className="text-xs text-amber-800 flex items-start gap-2">
                <AlertCircle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                {t('admin_db_migration.run_warning')}
              </p>
              <div className="flex items-center gap-2">
                <Button onClick={handleMigrate} disabled={migrating || !testResult?.ok}>
                  {migrating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Play className="h-4 w-4" />}
                  {migrating ? t('admin_db_migration.migrating') : t('admin_db_migration.run')}
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Result */}
          {result && (
            <Card className="border-emerald-200 bg-emerald-50/30">
              <CardContent className="pt-5 space-y-3">
                <div className="flex items-center gap-2 text-emerald-700">
                  <CheckCircle2 className="h-5 w-5" />
                  <span className="font-semibold">{t('admin_db_migration.result_title')}</span>
                </div>
                <p className="text-xs text-[var(--text-secondary)]">
                  {t('admin_db_migration.result_summary', {
                    rows: result.rowsCopied,
                    seconds: result.durationSeconds,
                  })}
                </p>
                <details className="text-xs">
                  <summary className="cursor-pointer text-[var(--text-tertiary)]">{t('admin_db_migration.result_per_table')}</summary>
                  <div className="mt-2 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(result.tables).map(([table, info]) => (
                      <div key={table} className="flex items-center justify-between rounded border border-emerald-200 px-2 py-1 text-[11px] bg-white">
                        <span className="font-mono">{table}</span>
                        <span className="tabular-nums">{info.source} → {info.target}</span>
                      </div>
                    ))}
                  </div>
                </details>

                {/* Switch */}
                <div className="pt-2 border-t border-emerald-200">
                  <p className="text-xs text-[var(--text-secondary)] mb-2">{t('admin_db_migration.switch_explainer')}</p>
                  <Button variant="default" onClick={handleSwitch} disabled={switching}>
                    {switching ? <Loader2 className="h-4 w-4 animate-spin" /> : <ArrowRight className="h-4 w-4" />}
                    {t('admin_db_migration.switch_button')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}

function Field({ label, value, onChange, type='text', className }: {
  label: string; value: string; onChange: (v: string) => void; type?: string; className?: string
}) {
  return (
    <div className={className}>
      <Label className="text-xs">{label}</Label>
      <Input type={type} value={value} onChange={e => onChange(e.target.value)} className="mt-1" />
    </div>
  )
}
