import { ArrowLeft, ArrowRight, ChevronDown, ChevronRight, EllipsisVertical, FileText, ImageOff, Pencil, TriangleAlert } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AssetCode, ConditionBadge, StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
import { Panel } from '../../components/ui/Page'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import { formatDate, formatDateTime, formatSize } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { queryKeys } from '../../lib/queries/keys'
import { useAssetDetail, useAssetEvents, useAssetFiles } from '../../lib/queries/lists'
import { useBlobUrl } from '../../lib/useBlobUrl'
import { useErrorToast } from '../../lib/useErrorToast'
import type { AssetEvent, AssetFile, FileType, Room } from '../../types/asset'
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

function Field({ label, children, wide }: { label: string; children: ReactNode; wide?: boolean }) {
  return (
    <div className={wide ? 'col-span-full' : ''}>
      <dt className="text-xs font-medium text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-sm">{children || '-'}</dd>
    </div>
  )
}

const boxClass =
  'flex aspect-square flex-col items-center justify-center gap-1 overflow-hidden rounded-lg border text-center text-xs'

function PhotoBox({ label, file, onOpen }: { label: string; file?: AssetFile; onOpen: (f: AssetFile) => void }) {
  const isImage = file?.mime_type.startsWith('image/') ?? false
  const src = useBlobUrl(file && isImage ? file.download_url : null)

  if (!file) {
    return (
      <div className={`${boxClass} border-dashed border-slate-300 bg-slate-50 p-2 text-slate-500`}>
        <ImageOff size={22} className="text-slate-400" />
        Belum ada {label.toLowerCase()}
      </div>
    )
  }
  return (
    <button
      type="button"
      onClick={() => onOpen(file)}
      aria-label={`Lihat ${label}`}
      className={`${boxClass} border-slate-200 bg-slate-50 transition-colors hover:border-brand-600`}
    >
      {isImage && src ? (
        <img src={src} alt={label} className="h-full w-full object-cover" />
      ) : (
        <>
          <FileText size={26} className="text-slate-400" />
          <span className="px-1 text-slate-600">{isImage ? 'Memuat…' : 'PDF · ketuk untuk melihat'}</span>
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
      className={`${boxClass} border-slate-200 bg-white p-2 transition-colors hover:border-brand-600`}
    >
      {src ? <img src={src} alt="Label QR" className="h-4/5 w-4/5 object-contain" /> : <span className="text-slate-500">Memuat…</span>}
      <span className="font-medium text-slate-600">Label QR</span>
    </button>
  )
}

/** Kerangka halaman detail saat data pertama kali dimuat (kepala + panel data + panel foto + riwayat). */
function DetailSkeleton() {
  return (
    <div className="space-y-5" aria-busy="true">
      <LoadingLabel />
      <div className="space-y-2">
        <Skeleton className="h-4 w-24" />
        <div className="flex gap-2 pt-1">
          <Skeleton className="h-5 w-28" />
          <Skeleton className="h-5 w-20 rounded-full" />
          <Skeleton className="h-5 w-16 rounded-full" />
        </div>
        <Skeleton className="h-7 w-72 max-w-full" />
        <Skeleton className="h-4 w-56 max-w-full" />
      </div>
      <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div className="rounded-xl border border-slate-200 bg-white p-5">
          <Skeleton className="h-4 w-24" />
          <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3">
            {Array.from({ length: 8 }, (_, i) => (
              <div key={i} className="space-y-1.5">
                <Skeleton className="h-3 w-20" />
                <Skeleton className="h-4 w-32 max-w-full" />
              </div>
            ))}
          </div>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5">
          <Skeleton className="h-4 w-28" />
          <div className="mt-5 grid grid-cols-3 gap-3">
            {Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="aspect-square rounded-lg" />)}
          </div>
        </div>
      </div>
    </div>
  )
}

export default function AssetDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [othersOpen, setOthersOpen] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const [offerLoan, setOfferLoan] = useState(false)

  const detail = useAssetDetail(id)
  const filesQuery = useAssetFiles(id ?? '')
  const eventsQuery = useAssetEvents(id ?? '')
  useErrorToast(filesQuery.error, eventsQuery.error)

  // Aset tidak ada / gagal dimuat → kembali ke daftar (perilaku sebelumnya).
  useEffect(() => {
    if (!detail.error) return
    toast.error(errorMessage(detail.error))
    navigate('/daftar-aset', { replace: true })
  }, [detail.error, navigate, toast])

  if (!detail.data) return <DetailSkeleton />

  const asset = detail.data.data
  const actions = detail.data.meta.actions
  const files = filesQuery.data ?? []
  const events = eventsQuery.data?.pages.flatMap((p) => p.data) ?? []

  const view = (request: Parameters<typeof openInNewTab>[0]) =>
    openInNewTab(request).catch((e) => toast.error(errorMessage(e)))
  const viewFile = (file: AssetFile) => view(() => api.get<Blob>(file.download_url, { responseType: 'blob' }))
  const viewLabel = () => view(() => api.get<Blob>(`/assets/${id}/label`, { responseType: 'blob' }))

  const closeModal = () => setModal(null)
  const reloadAfter = () => {
    closeModal()
    invalidate.assets(queryClient)
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
    {
      header: 'Tanggal',
      mobile: 'badge',
      cell: (e) => <span className="whitespace-nowrap text-slate-600">{formatDateTime(e.event_date)}</span>,
    },
    {
      header: 'Kegiatan',
      mobile: 'title',
      cell: (e) => (
        <>
          <span className="font-medium">{e.event_type}</span>
          {e.from_room && e.to_room && (
            <span className="flex flex-wrap items-center gap-1 text-xs text-slate-500">
              {roomLabel(e.from_room)} <ArrowRight size={12} /> {roomLabel(e.to_room)}
            </span>
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
    <li className="flex items-center justify-between py-2 text-sm">
      <span>{label}</span>
      {file ? (
        <button className="font-semibold text-brand-700 hover:underline" onClick={() => viewFile(file)}>Lihat</button>
      ) : (
        <span className="text-slate-500">Belum ada</span>
      )}
    </li>
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="min-w-0">
          <Link to="/daftar-aset" className="tap inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline">
            <ArrowLeft size={14} /> Daftar Aset
          </Link>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <AssetCode code={asset.code} className="text-sm" />
            <StatusBadge status={asset.status} />
            <ConditionBadge condition={asset.condition} />
          </div>
          <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-balance">{asset.name}</h1>
          <p className="mt-0.5 text-sm text-slate-500">
            {asset.asset_type?.name ?? '-'}
            {asset.brand && ` · ${asset.brand}`} · S/N {asset.serial_number ?? '-'}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {reportItems.length > 0 && <DropdownMenu label="Lapor rusak / hilang" trigger="Lapor rusak / hilang" items={reportItems} />}
          <Link to={`/daftar-aset/${asset.id}/ubah`} className={buttonClass('secondary')}>
            <Pencil size={16} /> Ubah data
          </Link>
          {can('pinjam') && (
            <Link to={`/peminjaman?asset_id=${asset.id}`} className={buttonClass('primary')}>
              Catat peminjaman
            </Link>
          )}
          <DropdownMenu label="Aksi lainnya" trigger={<EllipsisVertical size={18} />} items={moreItems} triggerClassName="px-2.5!" />
        </div>
      </div>

      {asset.lost_during_loan && (
        <div className="flex items-start gap-3 rounded-xl bg-bad-50 px-4 py-3 text-sm text-bad-700">
          <TriangleAlert size={18} className="mt-0.5 shrink-0" />
          <p>
            Hilang saat dipinjam oleh <b>{asset.lost_during_loan.borrower_name}</b>, {asset.lost_during_loan.borrower_unit},{' '}
            {formatDate(asset.lost_during_loan.date)}.
          </p>
        </div>
      )}

      <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <Panel title="Data aset">
          <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
            <Field label="Kategori">{asset.asset_type?.category?.name ?? '-'}</Field>
            <Field label="Jenis aset">{asset.asset_type?.name}</Field>
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
            <Field label="Kode aset Keuangan">{asset.finance_code && <span className="font-mono">{asset.finance_code}</span>}</Field>
            <Field label="Standar ruangan" wide>
              {std ? (std.is_standard ? `Wajib di ${asset.room?.name} (${std.quantity} unit)` : 'Tidak termasuk standar ruangan') : null}
            </Field>
            {asset.previous_codes && asset.previous_codes.length > 0 && (
              <Field label="Kode lama" wide>
                <span className="flex flex-wrap gap-1.5">
                  {asset.previous_codes.map((c) => <AssetCode key={c.code} code={c.code} />)}
                </span>
              </Field>
            )}
            {asset.specification && (
              <Field label="Spesifikasi" wide><span className="whitespace-pre-wrap">{asset.specification}</span></Field>
            )}
            {asset.note && (
              <Field label="Catatan" wide><span className="whitespace-pre-wrap">{asset.note}</span></Field>
            )}
          </dl>
        </Panel>

        <Panel title="Foto & dokumen">
          <div className="grid grid-cols-3 gap-3">
            <PhotoBox label="Foto aset" file={fileOf('Foto Aset')} onOpen={viewFile} />
            <PhotoBox label="Foto kondisi terakhir" file={fileOf('Foto Kondisi')} onOpen={viewFile} />
            <QrBox assetId={asset.id} onOpen={viewLabel} />
          </div>
          <ul className="mt-3 divide-y divide-slate-100 border-t border-slate-100">
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
                      <span className="min-w-0 truncate">{file.original_name} <span className="text-slate-500">· {formatSize(file.size)}</span></span>
                      <button className="shrink-0 font-semibold text-brand-700 hover:underline" onClick={() => viewFile(file)}>Lihat</button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </Panel>
      </div>

      <section className="space-y-2">
        <h2 className="font-semibold">Riwayat aset</h2>
        <DataTable
          columns={eventColumns}
          rows={events}
          rowKey={(e) => e.id}
          loading={eventsQuery.isPending}
          emptyText="Belum ada riwayat."
          dense
        />
        {eventsQuery.hasNextPage && (
          <Button variant="secondary" loading={eventsQuery.isFetchingNextPage} onClick={() => eventsQuery.fetchNextPage()}>
            Tampilkan riwayat lebih lama
          </Button>
        )}
      </section>

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
        <DeleteAssetModal
          asset={asset}
          onClose={closeModal}
          onDone={() => {
            queryClient.removeQueries({ queryKey: queryKeys.assets.detail(asset.id) })
            invalidate.assets(queryClient)
            navigate('/daftar-aset', { replace: true })
          }}
        />
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
