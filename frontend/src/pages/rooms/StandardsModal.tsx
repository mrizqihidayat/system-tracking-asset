import { useState } from 'react'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import type { RoomStandard } from '../../types/room'
import StandardFormModal from './StandardFormModal'

interface Props {
  roomId: number
  roomName: string
  standards: RoomStandard[]
  onClose: () => void
  onChanged: () => void
}

/** "Atur standar ruangan": daftar standar diisi manual per ruangan (tanpa salin dari ruangan lain). */
export default function StandardsModal({ roomId, roomName, standards, onClose, onChanged }: Props) {
  const toast = useToast()
  const [editing, setEditing] = useState<RoomStandard | 'new' | null>(null)
  const [deleting, setDeleting] = useState<RoomStandard | null>(null)
  const [busy, setBusy] = useState(false)

  const remove = async () => {
    if (!deleting) return
    setBusy(true)
    try {
      await api.delete(`/room-standards/${deleting.id}`)
      toast.success(`Standar "${deleting.item_name}" dihapus.`)
      setDeleting(null)
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <Modal
        open={editing === null && deleting === null}
        title={`Atur standar — ${roomName}`}
        onClose={onClose}
        footer={
          <>
            <Button variant="secondary" onClick={onClose}>Tutup</Button>
            <Button onClick={() => setEditing('new')}>Tambah standar</Button>
          </>
        }
      >
        {standards.length === 0 ? (
          <p className="text-sm text-slate-500">Belum ada standar perlengkapan untuk ruangan ini.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {standards.map((s) => (
              <li key={s.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                <div className="min-w-0">
                  <p className="font-medium">{s.item_name} <span className="text-slate-500">× {s.standard_quantity}</span></p>
                  <p className="text-xs text-slate-500">
                    {s.source_department?.name} · {s.needs_completion ? 'Perlu dilengkapi' : s.count_method === 'QR' ? `Aset · ${s.asset_type?.name ?? '-'}` : s.fulfillment?.type === 'unit_lain' ? 'Barang departemen lain' : 'Stok Habis Pakai'}
                  </p>
                </div>
                <div className="flex shrink-0 gap-3">
                  <button className="font-semibold text-brand-700 hover:underline" onClick={() => setEditing(s)}>Ubah</button>
                  <button className="font-semibold text-bad-700 hover:underline" onClick={() => setDeleting(s)}>Hapus</button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Modal>

      {editing && (
        <StandardFormModal
          roomId={roomId}
          roomName={roomName}
          standard={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onDone={() => {
            setEditing(null)
            onChanged()
          }}
        />
      )}

      <ConfirmDialog
        open={deleting !== null}
        title="Hapus standar"
        confirmLabel="Hapus"
        danger
        loading={busy}
        onConfirm={remove}
        onClose={() => setDeleting(null)}
      >
        Hapus standar "{deleting?.item_name}" dari {roomName}? Tindakan ini permanen.
      </ConfirmDialog>
    </>
  )
}
