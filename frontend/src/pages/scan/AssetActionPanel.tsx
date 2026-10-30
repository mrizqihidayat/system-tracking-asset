import { useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AssetCode, ConditionBadge, StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import Modal from '../../components/ui/Modal'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import type { Asset } from '../../types/asset'
import FoundModal from '../assets/modals/FoundModal'
import MoveLocationModal from '../assets/modals/MoveLocationModal'
import ReportModal, { type ReportMode } from '../assets/modals/ReportModal'
import { SCAN_ACTIONS } from './scanActions'

const reportModes: Record<string, ReportMode> = {
  catat_kerusakan: 'rusak',
  catat_kehilangan: 'hilang',
  catat_tidak_layak_pakai: 'tidak_layak_pakai',
}

interface Props {
  asset: Asset
  actions: string[]
  matchedBy?: string | null
  /** Aksi yang langsung dibuka (dari halaman publik setelah login). */
  initialAction?: string | null
  onClose: () => void
  /** Dipanggil setelah aksi tersimpan; induk memuat ulang aset. */
  onChanged: () => void
}

const roomLabel = (room?: { name: string; building?: { name: string } } | null) =>
  room ? `${room.name}${room.building ? `, ${room.building.name}` : ''}` : '-'

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-2 text-sm">
      <dt className="shrink-0 text-slate-500">{label}</dt>
      <dd className="min-w-0 text-right">{children || '-'}</dd>
    </div>
  )
}

/** Modal hasil scan: ringkasan aset + tombol aksi sesuai status; aksi membuka form di modal berikutnya. */
export default function AssetActionPanel({ asset, actions, matchedBy, initialAction, onClose, onChanged }: Props) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [action, setAction] = useState<string | null>(
    initialAction && actions.includes(initialAction) ? initialAction : null,
  )
  const [offerLoan, setOfferLoan] = useState(false)

  const loanUrl = `/peminjaman?asset_id=${asset.id}`
  const back = () => setAction(null)
  const done = () => {
    setAction(null)
    invalidate.assets(queryClient)
    onChanged()
  }

  const run = (key: string) => {
    if (key === 'pinjam' || key === 'terima_kembali') navigate(loanUrl)
    else setAction(key)
  }

  const loc = asset.current_location
  const available = actions.filter((a) => a in SCAN_ACTIONS)

  return (
    <>
      <Modal
        open={action === null && !offerLoan}
        title={asset.name}
        onClose={onClose}
        footer={
          <>
            <Button variant="secondary" onClick={onClose}>Tutup</Button>
            <Link to={`/daftar-aset/${asset.id}`} className={buttonClass('ghost', 'sm:mr-auto sm:-order-1')}>
              Buka detail aset
            </Link>
          </>
        }
      >
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <AssetCode code={asset.code} className="text-sm" />
          <StatusBadge status={asset.status} />
          <ConditionBadge condition={asset.condition} />
        </div>
        {matchedBy === 'previous_code' && (
          <div role="alert" className="mb-3 rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
            Kode yang dipindai adalah kode lama. Kode aset saat ini: <b>{asset.code}</b>.
          </div>
        )}
        <dl className="divide-y divide-slate-100">
          <Row label="Jenis">{asset.asset_type?.name}</Row>
          <Row label="Spesifikasi"><span className="whitespace-pre-wrap">{asset.specification}</span></Row>
          <Row label="Sumber">{asset.source_department?.name}</Row>
          <Row label="Lokasi asal">{roomLabel(asset.room)}</Row>
          {loc?.on_loan && <Row label="Lokasi peminjaman">{loc.room ? roomLabel(loc.room) : loc.note}</Row>}
          {asset.active_loan && (
            <Row label="Peminjam">
              {asset.active_loan.borrower_name}, {asset.active_loan.borrower_unit} · kembali {formatDate(asset.active_loan.due_date)}
            </Row>
          )}
        </dl>
        {available.length > 0 && (
          <div className="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
            {available.map((key) => (
              <Button key={key} variant={key === 'pinjam' ? 'primary' : 'secondary'} onClick={() => run(key)}>
                {SCAN_ACTIONS[key]}
              </Button>
            ))}
          </div>
        )}
      </Modal>

      {action && action in reportModes && (
        <ReportModal asset={asset} mode={reportModes[action]} onClose={back} onDone={done} />
      )}
      {action === 'pindah_lokasi' && <MoveLocationModal asset={asset} onClose={back} onDone={done} />}
      {action === 'tandai_ditemukan' && (
        <FoundModal
          asset={asset}
          onClose={back}
          onDone={(isAvailable) => {
            done()
            setOfferLoan(isAvailable)
          }}
        />
      )}
      <ConfirmDialog
        open={offerLoan}
        title="Aset ditemukan"
        confirmLabel="Langsung pinjamkan"
        onConfirm={() => navigate(loanUrl)}
        onClose={() => setOfferLoan(false)}
      >
        {asset.code} kini berstatus Tersedia. Ingin langsung mencatat peminjamannya?
      </ConfirmDialog>
    </>
  )
}
