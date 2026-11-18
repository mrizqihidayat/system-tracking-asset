import { CircleAlert, CircleCheck, CircleSlash } from 'lucide-react'
import { Badge } from '../../components/ui/Badge'
import { formatDateTime } from '../../lib/format'
import { useEmployeeSyncStatus } from '../../lib/queries/accounts'
import { useErrorToast } from '../../lib/useErrorToast'
import type { Department } from '../../types/asset'

/**
 * Sinkron terakhir daftar pegawai & departemen dari API pegawai (hanya baca). Kegagalan tetap terlihat di sini;
 * sinkronisasi berjalan otomatis tiap hari pukul 23.59 WIB lewat agen sinkron di jaringan INALUM (T34, T44).
 */
function SyncStatusStrip() {
  const status = useEmployeeSyncStatus()
  useErrorToast(status.error)

  if (status.isPending) return <div className="h-12 animate-pulse rounded-lg bg-slate-100 motion-reduce:animate-none" aria-hidden />

  const run = status.data
  if (!run) {
    return (
      <p className="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-600">
        <CircleSlash size={16} className="shrink-0" aria-hidden /> Belum pernah disinkronkan dengan API pegawai.
      </p>
    )
  }

  const ok = run.status === 'berhasil'
  const Icon = ok ? CircleCheck : CircleAlert
  return (
    <div role={ok ? undefined : 'alert'} className={`rounded-lg px-3 py-2.5 text-sm ${ok ? 'bg-ok-50 text-ok-700' : 'bg-bad-50 text-bad-700'}`}>
      <p className="flex items-start gap-2 font-medium">
        <Icon size={16} className="mt-0.5 shrink-0" aria-hidden />
        <span>
          Sinkron terakhir: {formatDateTime(run.finished_at ?? run.started_at)} — {run.status}
        </span>
      </p>
      <p className="mt-0.5 pl-6 text-xs">
        {ok
          ? `${run.total} pegawai: ${run.created} baru, ${run.updated} diperbarui, ${run.deactivated} dinonaktifkan.`
          : (run.message ?? 'Data pegawai dan departemen lama tetap dipakai.')}
      </p>
    </div>
  )
}

interface Props {
  departments: Department[]
  onEdit: (department: Department) => void
}

/**
 * Data Master > Departemen (T34): daftar dibentuk dari API pegawai, jadi tidak ada tambah/hapus. Hanya nama
 * lengkap yang bisa diubah (kode dari API tetap). Departemen yang tidak ada lagi di API tetap tersimpan.
 */
export default function DepartmentsTab({ departments, onEdit }: Props) {
  return (
    <div className="space-y-3">
      <SyncStatusStrip />
      <ul className="divide-y divide-slate-100">
        {departments.map((d) => (
          <li key={d.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
            <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
              <span className="font-mono text-xs font-semibold text-slate-500">{d.code ?? '-'}</span>
              <span className={`font-medium ${d.is_active ? '' : 'text-slate-500'}`}>{d.name}</span>
              {d.is_internal && <Badge color="blue">Internal</Badge>}
              {!d.is_active && <Badge>Tidak ada di API</Badge>}
            </span>
            <button className="tap shrink-0 font-semibold text-brand-700 hover:underline" onClick={() => onEdit(d)}>
              Ubah nama
            </button>
          </li>
        ))}
        {departments.length === 0 && <li className="py-2.5 text-sm text-slate-500">Belum ada departemen. Daftar terisi setelah sinkronisasi pegawai.</li>}
      </ul>
    </div>
  )
}
