import { ChevronDown, ChevronRight, EllipsisVertical, FileText } from 'lucide-react'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { StatusBadge } from '../../components/ui/Badge'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import { formatDate, formatDateTime, formatSize } from '../../lib/format'
import { useBlobUrl } from '../../lib/useBlobUrl'
import type { Asset, AssetEvent, AssetFile, FileType, Room } from '../../types/asset'
import DeleteAssetModal from './modals/DeleteAssetModal'
import FoundModal from './modals/FoundModal'
import MoveLocationModal from './modals/MoveLocationModal'
import ReportModal, { type ReportMode } from './modals/ReportModal'
import ServiceModal from './modals/ServiceModal'

const roomLabel = (room?: Room | null) => (room ? `${room.name}, ${room.building?.name ?? ''}` : '-')

type ModalState =
  | { kind: 'report'; mode: ReportMode }
  | { kind: 'found' | 'service' | 'move' | 'delete' }
  | null

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-sm">{children || '-'}</dd>
    </div>
  )
}

function Card({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-lg border border-slate-200 bg-white p-5">
      <h2 className="mb-4 font-semibold">{title}</h2>
      {children}
    </section>
  )
}

const boxClass = 'flex aspect-square flex-col items-center justify-center gap-1 overflow-hidden rounded-md border text-center text-xs'

function PhotoBox({ label, file, onOpen }: { label: string; file?: AssetFile; onOpen: (f: AssetFile) => void }) {
  const isImage = file?.mime_type.startsWith('image/') ?? false
  const src = useBlobUrl(file && isImage ? file.download_url : null)

  if (!file) {
    return <div className={`${boxClass} border-dashed border-slate-300 p-2 text-slate-400`}>Belum ada {label.toLowerCase()}</div>
  }
  return (
    <button
      type="button"
      onClick={() => onOpen(file)}
      aria-label={`Lihat ${label}`}
      className={`${boxClass} border-slate-200 bg-slate-50 hover:border-brand-500`}
    >
      {isImage && src ? (
        <img src={src} alt={label} className="h-full w-full object-cover" />
      ) : (
        <>
          <FileText size={28} className="text-slate-400" />
          <span className="px-1 text-slate-600">{isImage ? 'Memuat…' : 'PDF · klik untuk melihat'}</span>
        </>
      )}
    </button>
  )
}

function QrBox({ assetId, onOpen }: { assetId: number; onOpen: () => void }) {
  const src = useBlobUrl(`/assets/${assetId}/qr`)
  return (
    <button
      type="button"
      onClick={onOpen}
      aria-label="Lihat label QR (PDF)"
      className={`${boxClass} border-slate-200 bg-slate-50 p-2 hover:border-brand-500`}
    >
      {src ? <img src={src} alt="Label QR" className="h-4/5 w-4/5 object-contain" /> : <span className="text-slate-400">Memuat…</span>}
      <span className="text-slate-600">Label QR</span>
    </button>
  )
}

export default function AssetDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const [asset, setAsset] = useState<Asset | null>(null)
  const [actions, setActions] = useState<string[]>([])
  const [events, setEvents] = useState<AssetEvent[]>([])
  const [files, setFiles] = useState<AssetFile[]>([])
  const [loading, setLoading] = useState(true)
  const [othersOpen, setOthersOpen] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const [offerLoan, setOfferLoan] = useState(false)

  const fetchData = useCallback(
    () =>
      Promise.all([
        api.get<{ data: Asset; meta: { actions: string[] } }>(`/assets/${id}`),
        api.get<{ data: AssetEvent[] }>(`/assets/${id}/events`),
        api.get<{ data: AssetFile[] }>(`/assets/${id}/files`),
      ]).then(([a, e, f]) => {
        setAsset(a.data.data)
        setActions(a.data.meta.actions)
        setEvents(e.data.data)
        setFiles(f.data.data)
      }),
    [id],
  )

  useEffect(() => {
    fetchData()
      .catch((e) => {
        toast.error(errorMessage(e))
        navigate('/daftar-aset', { replace: true })
      })
      .finally(() => setLoading(false))
  }, [fetchData, navigate, toast])

  if (loading || !asset) return <p className="text-slate-500">Memuat…</p>

  const view = (request: Parameters<typeof openInNewTab>[0]) =>
    openInNewTab(request).catch((e) => toast.error(errorMessage(e)))
  const viewFile = (file: AssetFile) => view(() => api.get<Blob>(file.download_url, { responseType: 'blob' }))
  const viewLabel = () => view(() => api.get<Blob>(`/assets/${id}/label`, { responseType: 'blob' }))

  const closeModal = () => setModal(null)
  const reloadAfter = () => {
    closeModal()
    fetchData().catch((e) => toast.error(errorMessage(e)))
  }

  const fileOf = (type: FileType) => files.find((f) => f.file_type === type)
  const others = files.filter((f) => f.file_type === 'Lainnya')
  const loc = asset.current_location
  const currentRoom = loc?.on_loan && loc.room ? loc.room : asset.room
  const std = asset.room_standard
  const can = (action: string) => actions.includes(action)

  const reportItems: MenuItem[] = [
    can('catat_kerusakan') && { label: 'Catat kerusakan', onClick: () => setModal({ kind: 'report', mode: 'rusak' }) },
    can('catat_kehilangan') && { label: 'Catat kehilangan', onClick: () => setModal({ kind: 'report', mode: 'hilang' }) },
    can('catat_tidak_layak_pakai') && {
      label: 'Catat tidak layak pakai',
      onClick: () => setModal({ kind: 'report', mode: 'tidak_layak_pakai' }),
    },
  ].filter((i): i is MenuItem => !!i)

  const moreItems: MenuItem[] = [
    can('tandai_ditemukan') && { label: 'Tandai ditemukan', onClick: () => setModal({ kind: 'found' }) },
    (asset.status === 'Tersedia' || asset.status === 'Perbaikan') && {
      label: 'Catat servis',
      onClick: () => setModal({ kind: 'service' }),
    },
    can('pindah_lokasi') && { label: 'Pindah lokasi', onClick: () => setModal({ kind: 'move' }) },
    { label: 'Hapus aset', danger: true, onClick: () => setModal({ kind: 'delete' }) },
  ].filter((i): i is MenuItem => !!i)

  const eventColumns: Column<AssetEvent>[] = [
    { header: 'Tanggal', cell: (e) => formatDateTime(e.event_date) },
    {
      header: 'Kegiatan',
      cell: (e) => (
        <>
          <span className="font-medium">{e.event_type}</span>
          {e.from_room && e.to_room && (
            <span className="block text-xs text-slate-500">{roomLabel(e.from_room)} → {roomLabel(e.to_room)}</span>
          )}
          {e.note && <span className="block whitespace-pre-wrap text-xs text-slate-500">{e.note}</span>}
        </>
      ),
    },
    { header: 'Pihak', cell: (e) => e.party ?? '-' },
    { header: 'Kondisi', cell: (e) => e.condition ?? '-' },
    { header: 'Dicatat oleh', cell: (e) => e.recorded_by?.name ?? '-' },
  ]

  const docRow = (label: string, file?: AssetFile) => (
    <li className="flex items-center justify-between py-2.5 text-sm">
      <span>{label}</span>
      {file ? (
        <button className="text-brand-700 underline" onClick={() => viewFile(file)}>Lihat</button>
      ) : (
        <span className="text-slate-400">Belum ada</span>
      )}
    </li>
  )

  return (
    <div className="max-w-6xl space-y-5">
      <nav className="text-sm text-slate-500">
        <Link to="/daftar-aset" className="text-brand-700 underline">Daftar Aset</Link> · {asset.code}
      </nav>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{asset.name}</h1>
          <p className="mt-1 font-mono text-sm text-slate-500">
            {asset.code} · S/N {asset.serial_number ?? '-'}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {reportItems.length > 0 && <DropdownMenu label="Lapor rusak / hilang" trigger="Lapor rusak / hilang" items={reportItems} />}
          <Link
            to={`/daftar-aset/${asset.id}/ubah`}
            className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Ubah data
          </Link>
          {can('pinjam') && (
            <Link
              to={`/peminjaman?asset_id=${asset.id}`}
              className="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
            >
              Catat peminjaman
            </Link>
          )}
          <DropdownMenu label="Aksi lainnya" trigger={<EllipsisVertical size={18} />} items={moreItems} triggerClassName="px-2!" />
        </div>
      </div>

      {asset.lost_during_loan && (
        <div className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">
          Hilang saat dipinjam oleh {asset.lost_during_loan.borrower_name}, {asset.lost_during_loan.borrower_unit},{' '}
          {formatDate(asset.lost_during_loan.date)}.
        </div>
      )}

      <div className="grid items-start gap-5 lg:grid-cols-2">
        <Card title="Data aset">
          <dl className="grid grid-cols-2 gap-x-6 gap-y-4">
            <Field label="Kategori">{asset.asset_type?.category?.name}</Field>
            <Field label="Sumber">{asset.source_department?.name}</Field>
            <Field label="Gedung">{currentRoom?.building?.name}</Field>
            <Field label="Ruangan saat ini">
              {currentRoom?.name ?? (loc?.on_loan ? loc.note : null)}
              {loc?.on_loan && <span className="block text-xs text-slate-500">Dipinjam · asal: {roomLabel(asset.room)}</span>}
            </Field>
            <Field label="Penanggung jawab">{asset.pic_name}</Field>
            <Field label="Kondisi">{asset.condition}</Field>
            <Field label="Status"><StatusBadge status={asset.status} /></Field>
            <Field label="Tanggal perolehan">{asset.acquisition_date ? formatDate(asset.acquisition_date) : null}</Field>
            <Field label="Servis berikutnya">{asset.next_service_date ? formatDate(asset.next_service_date) : null}</Field>
            <Field label="Kode aset Keuangan"><span className="font-mono">{asset.finance_code}</span></Field>
            <div className="col-span-2">
              <Field label="Standar ruangan">
                {std ? (std.is_standard ? `Wajib di ${asset.room?.name} (${std.quantity} unit)` : 'Tidak termasuk standar ruangan') : null}
              </Field>
            </div>
            {asset.asset_type && <Field label="Jenis aset">{asset.asset_type.name}</Field>}
            {asset.brand && <Field label="Merek">{asset.brand}</Field>}
            {asset.previous_codes && asset.previous_codes.length > 0 && (
              <Field label="Kode lama">{asset.previous_codes.map((c) => c.code).join(', ')}</Field>
            )}
            {asset.specification && (
              <div className="col-span-2"><Field label="Spesifikasi"><span className="whitespace-pre-wrap">{asset.specification}</span></Field></div>
            )}
            {asset.note && (
              <div className="col-span-2"><Field label="Catatan"><span className="whitespace-pre-wrap">{asset.note}</span></Field></div>
            )}
          </dl>
        </Card>

        <Card title="Foto & dokumen">
          <div className="grid grid-cols-3 gap-3">
            <PhotoBox label="Foto aset" file={fileOf('Foto Aset')} onOpen={viewFile} />
            <PhotoBox label="Foto kondisi terakhir" file={fileOf('Foto Kondisi')} onOpen={viewFile} />
            <QrBox assetId={asset.id} onOpen={viewLabel} />
          </div>
          <ul className="mt-4 divide-y divide-slate-100 border-t border-slate-100">
            {docRow('BAST penerimaan', fileOf('BAST'))}
            {docRow('Kartu garansi', fileOf('Garansi'))}
          </ul>
          {others.length > 0 && (
            <div className="border-t border-slate-100 pt-3">
              <button
                className="flex items-center gap-1 text-sm font-medium text-slate-700"
                onClick={() => setOthersOpen((v) => !v)}
                aria-expanded={othersOpen}
              >
                {othersOpen ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                Dokumen lain ({others.length})
              </button>
              {othersOpen && (
                <ul className="mt-1 divide-y divide-slate-100">
                  {others.map((file) => (
                    <li key={file.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                      <span className="min-w-0 truncate">{file.original_name} <span className="text-slate-400">· {formatSize(file.size)}</span></span>
                      <button className="shrink-0 text-brand-700 underline" onClick={() => viewFile(file)}>Lihat</button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </Card>
      </div>

      <Card title="Riwayat aset">
        <DataTable columns={eventColumns} rows={events} rowKey={(e) => e.id} emptyText="Belum ada riwayat." />
      </Card>

      {modal?.kind === 'report' && <ReportModal asset={asset} mode={modal.mode} onClose={closeModal} onDone={reloadAfter} />}
      {modal?.kind === 'found' && (
        <FoundModal
          asset={asset}
          onClose={closeModal}
          onDone={(available) => {
            reloadAfter()
            setOfferLoan(available)
          }}
        />
      )}
      {modal?.kind === 'service' && <ServiceModal asset={asset} onClose={closeModal} onDone={reloadAfter} />}
      {modal?.kind === 'move' && <MoveLocationModal asset={asset} onClose={closeModal} onDone={reloadAfter} />}
      {modal?.kind === 'delete' && (
        <DeleteAssetModal asset={asset} onClose={closeModal} onDone={() => navigate('/daftar-aset', { replace: true })} />
      )}

      <ConfirmDialog
        open={offerLoan}
        title="Aset ditemukan"
        confirmLabel="Langsung pinjamkan"
        onConfirm={() => navigate(`/peminjaman?asset_id=${asset.id}`)}
        onClose={() => setOfferLoan(false)}
      >
        {asset.code} kini berstatus Tersedia. Ingin langsung mencatat peminjamannya?
      </ConfirmDialog>
    </div>
  )
}
