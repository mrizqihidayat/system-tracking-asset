import { useEffect, useState } from 'react'
import { FormField, Input } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api, errorMessage } from '../../../lib/api'
import type { Asset, DeleteImpact } from '../../../types/asset'
import ActionModal from './ActionModal'

interface Props {
  asset: Asset
  onClose: () => void
  onDone: () => void
}

export default function DeleteAssetModal({ asset, onClose, onDone }: Props) {
  const toast = useToast()
  const [impact, setImpact] = useState<DeleteImpact | null>(null)
  const [code, setCode] = useState('')
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    api
      .get<{ data: DeleteImpact }>(`/assets/${asset.id}/delete-impact`)
      .then((res) => setImpact(res.data.data))
      .catch((e) => setLoadError(errorMessage(e)))
  }, [asset.id])

  const needsCode = impact?.requires_confirm_code ?? false
  const codeMatches = code.trim().toLowerCase() === asset.code.toLowerCase()

  const submit = async () => {
    await api.delete(`/assets/${asset.id}`, { data: needsCode ? { confirm_code: code.trim() } : {} })
    toast.success(`Aset ${asset.code} dihapus permanen.`)
    onDone()
  }

  return (
    <ActionModal
      title={`Hapus aset — ${asset.code}`}
      submitLabel="Hapus permanen"
      danger
      submitDisabled={!impact || (needsCode && !codeMatches)}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) =>
        !impact ? (
          <p className={`text-sm ${loadError ? 'text-red-700' : 'text-slate-500'}`}>
            {loadError || 'Memeriksa dampak penghapusan…'}
          </p>
        ) : (
          <>
            <p className="text-sm">
              <b>{asset.name}</b> akan dihapus <b>permanen</b> beserta data turunannya. Tindakan ini tidak bisa dibatalkan.
            </p>
            <ul className="list-disc space-y-1 pl-5 text-sm text-slate-700">
              <li>{impact.loans_count} riwayat peminjaman ikut terhapus</li>
              <li>{impact.events_count} baris riwayat aset ikut terhapus</li>
              <li>{impact.files_count} file (foto &amp; dokumen) ikut terhapus</li>
            </ul>
            {impact.active_loan && (
              <p className="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800">
                Aset ini sedang dipinjam oleh <b>{impact.active_loan.borrower_name}</b> ({impact.active_loan.borrower_unit}).
                Peminjaman aktifnya ikut terhapus.
              </p>
            )}
            {impact.affected_room_standards.length > 0 && (
              <div className="rounded-md border border-amber-300 bg-amber-50 p-2 text-sm text-amber-900">
                Mengurangi jumlah &ldquo;Ada&rdquo; pada standar ruangan:
                <ul className="mt-1 list-disc pl-5">
                  {impact.affected_room_standards.map((s) => (
                    <li key={`${s.room_id}-${s.item_name}`}>{s.item_name} — {s.room}, {s.building}</li>
                  ))}
                </ul>
              </div>
            )}
            {needsCode && (
              <FormField label={`Ketik kode aset ${asset.code} untuk mengonfirmasi`} required error={errors.confirm_code}>
                <Input value={code} onChange={(e) => setCode(e.target.value)} error={errors.confirm_code} placeholder={asset.code} autoComplete="off" />
              </FormField>
            )}
          </>
        )
      }
    </ActionModal>
  )
}
