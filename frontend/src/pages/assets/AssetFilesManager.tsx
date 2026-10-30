import { useQueryClient } from '@tanstack/react-query'
import { useRef, useState } from 'react'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import { formatSize } from '../../lib/format'
import { queryKeys } from '../../lib/queries/keys'
import { useAssetFiles } from '../../lib/queries/lists'
import { useErrorToast } from '../../lib/useErrorToast'
import { Skeleton } from '../../components/ui/Skeleton'
import type { AssetFile, FileType } from '../../types/asset'

const MAX_BYTES = 5 * 1024 * 1024
const ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf'

const SLOTS: { type: FileType; label: string }[] = [
  { type: 'Foto Aset', label: 'Foto aset' },
  { type: 'Foto Kondisi', label: 'Foto kondisi terakhir' },
  { type: 'BAST', label: 'BAST penerimaan' },
  { type: 'Garansi', label: 'Kartu garansi' },
]

/** Pengelolaan file di form Ubah. Upload/hapus langsung tersimpan (tanpa tombol Simpan form). */
export default function AssetFilesManager({ assetId }: { assetId: number }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const filesQuery = useAssetFiles(assetId)
  useErrorToast(filesQuery.error)
  const files = filesQuery.data ?? []
  const loading = filesQuery.isPending
  const [busy, setBusy] = useState<string | null>(null)
  const [toDelete, setToDelete] = useState<AssetFile | null>(null)
  const inputs = useRef<Record<string, HTMLInputElement | null>>({})

  const load = () => queryClient.invalidateQueries({ queryKey: queryKeys.assets.files(assetId) })

  const upload = async (type: FileType, file: File | undefined, key: string) => {
    if (!file) return
    if (file.size > MAX_BYTES) return toast.error('Ukuran file maksimal 5 MB.')
    if (!ACCEPT.split(',').includes(file.type)) return toast.error('Tipe file harus JPG, PNG, WebP, atau PDF.')

    const body = new FormData()
    body.append('file_type', type)
    body.append('file', file)
    setBusy(key)
    try {
      const res = await api.post<{ meta?: { replaced?: boolean } }>(`/assets/${assetId}/files`, body)
      toast.success(res.data.meta?.replaced ? 'File diganti.' : 'File diunggah.')
      await load()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(null)
    }
  }

  const remove = async () => {
    if (!toDelete) return
    setBusy(`del-${toDelete.id}`)
    try {
      await api.delete(`/asset-files/${toDelete.id}`)
      toast.success('File dihapus.')
      setToDelete(null)
      await load()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(null)
    }
  }

  const view = (file: AssetFile) =>
    openInNewTab(() => api.get<Blob>(file.download_url, { responseType: 'blob' })).catch((e) => toast.error(errorMessage(e)))

  const picker = (key: string, type: FileType) => (
    <input
      ref={(el) => {
        inputs.current[key] = el
      }}
      type="file"
      accept={ACCEPT}
      className="hidden"
      onChange={(e) => {
        const file = e.target.files?.[0]
        e.target.value = ''
        upload(type, file, key)
      }}
    />
  )

  const others = files.filter((f) => f.file_type === 'Lainnya')

  return (
    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
      <div>
        <h2 className="font-semibold">Foto &amp; dokumen</h2>
        <p className="mt-0.5 text-xs text-slate-500">
          Perubahan file langsung tersimpan. JPG, PNG, WebP, atau PDF, maksimal 5 MB per file.
        </p>
      </div>
      {loading ? (
        <div className="space-y-3" aria-busy="true">
          <span className="sr-only">Memuat…</span>
          {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-10 w-full rounded-lg" />)}
        </div>
      ) : (
        <>
          <ul className="divide-y divide-slate-100 text-sm">
            {SLOTS.map(({ type, label }) => {
              const file = files.find((f) => f.file_type === type)
              return (
                <li key={type} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                  <span className="min-w-0">
                    <span className="block font-medium">{label}</span>
                    {file ? (
                      <span className="block break-all text-xs text-slate-500">{file.original_name} · {formatSize(file.size)}</span>
                    ) : (
                      <span className="block text-xs text-slate-500">Belum ada</span>
                    )}
                  </span>
                  <span className="flex shrink-0 gap-2">
                    {picker(type, type)}
                    {file && <Button variant="secondary" className="px-3!" onClick={() => view(file)}>Lihat</Button>}
                    <Button variant="secondary" className="px-3!" loading={busy === type} onClick={() => inputs.current[type]?.click()}>
                      {file ? 'Ganti' : 'Unggah'}
                    </Button>
                    {file && (
                      <Button variant="secondary" className="px-3! text-bad-700!" onClick={() => setToDelete(file)}>Hapus</Button>
                    )}
                  </span>
                </li>
              )
            })}
          </ul>

          <div className="space-y-2 border-t border-slate-100 pt-3 text-sm">
            <div className="flex items-center justify-between gap-2">
              <span className="font-medium">Dokumen lain ({others.length})</span>
              {picker('Lainnya', 'Lainnya')}
              <Button variant="secondary" className="px-3!" loading={busy === 'Lainnya'} onClick={() => inputs.current.Lainnya?.click()}>
                Tambah dokumen
              </Button>
            </div>
            {others.length > 0 && (
              <ul className="divide-y divide-slate-100">
                {others.map((file) => (
                  <li key={file.id} className="flex items-center justify-between gap-2 py-2">
                    <span className="min-w-0 break-all">{file.original_name} <span className="text-slate-500">· {formatSize(file.size)}</span></span>
                    <span className="flex shrink-0 gap-2">
                      <Button variant="secondary" className="px-3!" onClick={() => view(file)}>Lihat</Button>
                      <Button variant="secondary" className="px-3! text-bad-700!" onClick={() => setToDelete(file)}>Hapus</Button>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </>
      )}

      <ConfirmDialog
        open={toDelete !== null}
        title="Hapus file?"
        confirmLabel="Hapus"
        danger
        loading={busy !== null && busy.startsWith('del-')}
        onConfirm={remove}
        onClose={() => setToDelete(null)}
      >
        {toDelete && <>File <b>{toDelete.original_name}</b> akan dihapus permanen.</>}
      </ConfirmDialog>
    </section>
  )
}
