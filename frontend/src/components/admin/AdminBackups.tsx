import { useEffect, useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Archive, Download, RotateCcw, Trash2, Plus, Database, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import api from '@/lib/api'
import { API_BASE_URL } from '@/lib/constants'

interface BackupEntry {
  filename: string
  sizeBytes: number
  createdAt: string
}

/**
 * /admin/backups — gestión de backups de la base de datos.
 *
 * Lista backups existentes en backend/storage/backups/, permite crear
 * uno nuevo bajo demanda, descargar, restaurar (con confirmación) o
 * eliminar. Respeta la retención configurada en el .env
 * (BACKUP_RETENTION_COUNT, default 10).
 */
export default function AdminBackups() {
  const { t, i18n } = useTranslation()
  const [driver, setDriver] = useState('sqlite')
  const [backups, setBackups] = useState<BackupEntry[]>([])
  const [retention, setRetention] = useState(10)
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [restoring, setRestoring] = useState<string | null>(null)
  const [confirmRestore, setConfirmRestore] = useState<BackupEntry | null>(null)
  const [confirmDelete, setConfirmDelete] = useState<BackupEntry | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/admin/backup.php')
      const data = res.data?.data
      setDriver(data?.driver ?? 'sqlite')
      setBackups(data?.backups ?? [])
      setRetention(data?.retention ?? 10)
    } catch { /* ignore */ }
    setLoading(false)
  }, [])

  useEffect(() => { reload() }, [reload])

  const handleCreate = async () => {
    setCreating(true)
    try {
      const res = await api.post('/admin/backup.php', { action: 'create' })
      toast.success(t('admin_backups.toast_created', { name: res.data?.data?.filename ?? '' }))
      await reload()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? t('admin_backups.toast_create_error'))
    }
    setCreating(false)
  }

  const handleRestore = async () => {
    if (!confirmRestore) return
    const filename = confirmRestore.filename
    setRestoring(filename)
    setConfirmRestore(null)
    try {
      await api.post('/admin/backup.php', { action: 'restore', filename })
      toast.success(t('admin_backups.toast_restored', { name: filename }))
      await reload()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? t('admin_backups.toast_restore_error'), { duration: 10000 })
    }
    setRestoring(null)
  }

  const handleDelete = async () => {
    if (!confirmDelete) return
    const filename = confirmDelete.filename
    setConfirmDelete(null)
    try {
      await api.post('/admin/backup.php', { action: 'delete', filename })
      toast.success(t('admin_backups.toast_deleted'))
      await reload()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? t('admin_backups.toast_delete_error'))
    }
  }

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(1)} MB`
    return `${(bytes / 1073741824).toFixed(2)} GB`
  }

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Archive className="h-6 w-6 text-[var(--accent-primary)]" />
            {t('admin_backups.title')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">{t('admin_backups.subtitle')}</p>
        </div>
        <Button onClick={handleCreate} disabled={creating}>
          {creating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
          {t('admin_backups.create')}
        </Button>
      </div>

      <Card className="border-blue-200 bg-blue-50/30">
        <CardContent className="pt-4 pb-4 text-xs space-y-1 text-[var(--text-secondary)]">
          <p className="flex items-center gap-2">
            <Database className="h-3.5 w-3.5 text-blue-600" />
            <span>{t('admin_backups.driver')}: <strong>{driver === 'mysql' ? 'MySQL/MariaDB' : 'SQLite'}</strong></span>
          </p>
          <p>{t('admin_backups.retention_hint', { count: retention })}</p>
          <p className="text-[10px] text-[var(--text-tertiary)]">{t('admin_backups.cron_hint')}</p>
        </CardContent>
      </Card>

      {loading ? (
        <Skeleton className="h-64 rounded-2xl" />
      ) : backups.length === 0 ? (
        <Card>
          <CardContent className="py-12 text-center text-sm text-[var(--text-tertiary)]">
            {t('admin_backups.empty')}
          </CardContent>
        </Card>
      ) : (
        <div className="rounded-lg border border-[var(--border-default)] overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
              <tr>
                <th className="px-3 py-2">{t('admin_backups.col_file')}</th>
                <th className="px-3 py-2">{t('admin_backups.col_created')}</th>
                <th className="px-3 py-2 text-right">{t('admin_backups.col_size')}</th>
                <th className="px-3 py-2 text-right w-40">{t('admin_backups.col_actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-default)]">
              {backups.map(b => (
                <tr key={b.filename} className="hover:bg-[var(--bg-secondary)]">
                  <td className="px-3 py-2 font-mono text-xs">
                    {b.filename}
                    {b.filename.endsWith('.gz') && <Badge variant="outline" className="ml-2 text-[9px]">gzip</Badge>}
                  </td>
                  <td className="px-3 py-2 text-xs text-[var(--text-secondary)]">
                    {new Date(b.createdAt).toLocaleString(i18n.language || 'en')}
                  </td>
                  <td className="px-3 py-2 text-xs text-right tabular-nums">{formatSize(b.sizeBytes)}</td>
                  <td className="px-3 py-2 text-right">
                    <div className="inline-flex items-center gap-1">
                      <a
                        href={`${API_BASE_URL}/admin/backup.php?download=${encodeURIComponent(b.filename)}`}
                        className="inline-flex items-center gap-1 text-xs text-[var(--accent-primary)] hover:underline px-1.5"
                        title={t('admin_backups.download')}
                      >
                        <Download className="h-3.5 w-3.5" />
                      </a>
                      <button
                        type="button"
                        onClick={() => setConfirmRestore(b)}
                        disabled={restoring === b.filename}
                        className="inline-flex items-center gap-1 text-xs text-amber-700 hover:bg-amber-50 px-1.5 py-1 rounded"
                        title={t('admin_backups.restore')}
                      >
                        {restoring === b.filename ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RotateCcw className="h-3.5 w-3.5" />}
                      </button>
                      <button
                        type="button"
                        onClick={() => setConfirmDelete(b)}
                        className="inline-flex items-center gap-1 text-xs text-red-600 hover:bg-red-50 px-1.5 py-1 rounded"
                        title={t('admin_backups.delete')}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Restore confirm */}
      <Dialog open={!!confirmRestore} onOpenChange={(o) => !o && setConfirmRestore(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-amber-600" />
              {t('admin_backups.restore_title')}
            </DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('admin_backups.restore_warning')}</p>
          {confirmRestore && (
            <p className="text-xs font-mono bg-[var(--bg-secondary)] rounded p-2">
              {confirmRestore.filename}
            </p>
          )}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmRestore(null)}>{t('common.cancel')}</Button>
            <Button variant="destructive" onClick={handleRestore}>
              <RotateCcw className="h-4 w-4" />
              {t('admin_backups.restore')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirm */}
      <Dialog open={!!confirmDelete} onOpenChange={(o) => !o && setConfirmDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('admin_backups.delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('admin_backups.delete_body')}</p>
          {confirmDelete && (
            <p className="text-xs font-mono bg-[var(--bg-secondary)] rounded p-2">
              {confirmDelete.filename}
            </p>
          )}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('common.cancel')}</Button>
            <Button variant="destructive" onClick={handleDelete}>
              <Trash2 className="h-4 w-4" />
              {t('common.delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
