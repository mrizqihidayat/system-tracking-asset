import { ChevronDown, Clock, EllipsisVertical, History, Pencil, Plus, Trash2, TriangleAlert, Warehouse } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import { Badge, ConditionBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
import LoadError from '../../components/ui/LoadError'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { PageHeader } from '../../components/ui/Page'
import Select from '../../components/ui/Select'
import { LoadingLabel, Skeleton, TableSkeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { createBuilding, createRoom } from '../../lib/lookups'
import { invalidate } from '../../lib/queries/invalidate'
import { queryKeys } from '../../lib/queries/keys'
import { useChecklist, useReadiness } from '../../lib/queries/lists'
import { prefetchRoomOptions } from '../../lib/queries/prefetch'
import { useErrorToast } from '../../lib/useErrorToast'
import type { Condition } from '../../types/asset'
import type { Checklist, RoomAssetGroup, RoomConsumable, RoomStoredStock } from '../../types/room'
import AdjustRoomStockModal from './AdjustRoomStockModal'
import FillFromStockModal from './FillFromStockModal'
import RoomCardModal from './RoomCardModal'
import NameModal from './NameModal'
import StandardFormModal, { type StandardTarget } from './StandardFormModal'

const BASE = '/kelengkapan-ruangan'

const resultColor = { Sesuai: 'green', Kurang: 'red', 'Belum dicek': 'amber' } as const
const stockColor = { Aman: 'green', Menipis: 'amber', Habis: 'red' } as const

const chip = (active: boolean) =>
  `inline-flex min-h-10 lg:min-h-8 shrink-0 items-center whitespace-nowrap rounded-full border px-3 text-[13px] font-semibold transition-colors ${
    active ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
  }`

/** Tombol ikon kecil di kolom Aksi (Ubah / Hapus). */
const iconButton =
  'flex h-10 w-10 lg:h-8 lg:w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900'

// HP: chip digeser horizontal; sm ke atas: membungkus.
const chipRow = '-mx-4 flex min-w-0 flex-1 gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0'
// Label di kiri sebaris dengan chip (desktop); HP/tablet: label hanya untuk pembaca layar.
const selectorRow = 'flex flex-col gap-1.5 sm:flex-row sm:items-start sm:gap-3'
const selectorLabel = 'shrink-0 text-xs font-medium text-slate-500 sm:w-16 sm:pt-2 max-lg:sr-only'

/** Tombol menu ⋮ di kartu HP/tablet (pengganti ikon aksi tabel desktop). */
const cardMenuTrigger = 'px-3!'

/** Tombol ikon "+" 40 px di HP (pilih gedung/ruangan, judul bagian). */
const phoneIconButton = 'w-10 px-0!'
/** Tombol judul bagian: teks di tablet/desktop, ikon "+" 40 px di HP. */
const sectionAddButton = 'max-sm:w-10 max-sm:px-0!'

/** Lencana Hasil di kartu HP: "Kurang N" bila ada kekurangan. */
function ResultBadge({ result, shortage }: { result: keyof typeof resultColor; shortage: number }) {
  return <Badge color={resultColor[result]}>{result === 'Kurang' && shortage > 0 ? `Kurang ${shortage}` : result}</Badge>
}

/** Kondisi ringkas untuk kartu HP: satu aset → "Baik"; banyak → "2 Baik, 1 Rusak Ringan". */
const conditionText = (g: RoomAssetGroup) =>
  g.assets.length === 0
    ? ''
    : g.assets.length === 1
      ? g.assets[0].condition
      : (Object.entries(g.conditions) as [Condition, number][]).map(([condition, count]) => `${count} ${condition}`).join(', ')

/**
 * Kartu HP (< sm): baris 1 nama + lencana + menu ⋮, baris berikutnya ringkasan, lalu tombol aksi.
 * Lencana & ⋮ dinaikkan sedikit agar sejajar dengan baris nama (tombol ⋮ setinggi 40 px).
 */
function PhoneCard({ title, badge, menu, children, actions }: {
  title: string
  badge?: ReactNode
  menu: ReactNode
  children: ReactNode
  actions?: ReactNode
}) {
  return (
    <div className="space-y-3">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1 space-y-0.5">
          <p className="font-medium">{title}</p>
          {children}
        </div>
        <div className="-mt-2.5 flex shrink-0 items-center gap-2">
          {badge}
          {menu}
        </div>
      </div>
      {actions}
    </div>
  )
}

/** Form permintaan terisi barang, penyedia, ruangan, dan jumlah (bawaan = kekurangan). */
const requestUrl = (roomId: number, s: RoomConsumable, quantity = s.shortage) => {
  const p = new URLSearchParams({ room_id: String(roomId), consumable_id: String(s.consumable.id), item_name: s.consumable.name })
  if (s.consumable.provider_department) p.set('provider_department_id', String(s.consumable.provider_department.id))
  if (quantity) p.set('quantity', String(quantity))
  return `/permintaan?${p}`
}

/** Kolom angka/lencana rata tengah (tabel desktop; kartu HP tidak terpengaruh). */
const CENTER = 'text-center'

/**
 * Lebar tetap kolom tabel Aset di ruangan & Barang habis pakai agar kedua tabel lurus:
 * nama (sisa ruang) | Standar | Jumlah | Selisih | Hasil | Kondisi/Aksi | ikon. Hanya tabel desktop.
 */
const COL = {
  standard: `${CENTER} lg:w-24`,
  quantity: `${CENTER} lg:w-36`,
  difference: `${CENTER} lg:w-24`,
  result: `${CENTER} lg:w-32`,
  extra: `${CENTER} lg:w-48`,
  icons: 'lg:w-36 whitespace-nowrap text-right',
}

/** Tombol sekunder kecil di judul bagian. */
const compactButton = 'min-h-10! lg:min-h-8! px-3! text-xs!'

const MAX_ASSET_CODES = 5
const NOT_COUNTED_LABELS = { Dipinjam: 'sedang dipinjam', Perbaikan: 'dalam perbaikan', Hilang: 'hilang' } as const

/** Kode aset (klik menuju detail) + keterangan aset yang tidak dihitung "Ada". */
function AssetCodes({ group }: { group: RoomAssetGroup }) {
  const shown = group.assets.slice(0, MAX_ASSET_CODES)
  const hidden = group.assets.slice(MAX_ASSET_CODES)
  const notCounted = (Object.keys(NOT_COUNTED_LABELS) as (keyof typeof NOT_COUNTED_LABELS)[])
    .filter((status) => group.not_counted[status])
    .map((status) => `${group.not_counted[status]} ${NOT_COUNTED_LABELS[status]}`)

  return (
    <span className="block text-xs text-slate-500">
      {shown.length === 0
        ? 'Belum ada aset'
        : shown.map((a, i) => (
            <span key={a.id}>
              {i > 0 && ', '}
              <Link to={`/daftar-aset/${a.id}`} className="tap hover:text-brand-700 hover:underline">{a.code}</Link>
            </span>
          ))}
      {hidden.length > 0 && <span title={hidden.map((a) => a.code).join(', ')}> +{hidden.length}</span>}
      {notCounted.length > 0 && <span className="block text-warn-700">{notCounted.join(' · ')}</span>}
    </span>
  )
}

/**
 * Kolom Kondisi: satu aset → lencana kondisi; banyak aset → lencana per kondisi + jumlah.
 * Hover lencana menampilkan kode aset dengan kondisi itu.
 */
function ConditionSummary({ group }: { group: RoomAssetGroup }) {
  if (group.assets.length === 0) return <span className="text-slate-500">—</span>
  if (group.assets.length === 1) return <ConditionBadge condition={group.assets[0].condition} />

  return (
    <span className="flex flex-wrap gap-1 lg:justify-center">
      {(Object.entries(group.conditions) as [Condition, number][]).map(([condition, count]) => (
        <span
          key={condition}
          title={group.assets.filter((a) => a.condition === condition).map((a) => a.code).join(', ')}
        >
          <ConditionBadge condition={condition} label={`${count} ${condition}`} />
        </span>
      ))}
    </span>
  )
}

const conditionColumn: Column<RoomAssetGroup> = { header: 'Kondisi', className: CENTER, cell: (g) => <ConditionSummary group={g} /> }

/** Judul bagian tabel: judul, keterangan singkat, dan aksi opsional di kanan. */
function SectionHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return (
    <div className={`flex flex-wrap justify-between gap-2 ${description ? 'items-end' : 'items-center'}`}>
      <div>
        <h3 className="font-semibold">{title}</h3>
        {description && <p className="text-xs text-slate-500">{description}</p>}
      </div>
      {action}
    </div>
  )
}

const differenceCell = (difference: number | null) =>
  difference === null ? (
    <span className="text-slate-500">—</span>
  ) : (
    <span className={`tabular-nums ${difference < 0 ? 'font-semibold text-bad-700' : ''}`}>{difference}</span>
  )

const storedAssetColumns: Column<RoomAssetGroup>[] = [
  {
    header: 'Jenis aset',
    mobile: 'title',
    cell: (g) => (
      <>
        <span className="font-medium">{g.asset_type.name}</span>
        <AssetCodes group={g} />
      </>
    ),
  },
  { header: 'Jumlah', className: CENTER, cell: (g) => <span className="tabular-nums">{g.total}</span> },
  conditionColumn,
]

const storedStockColumns: Column<RoomStoredStock>[] = [
  { header: 'Barang', mobile: 'title', cell: (s) => <span className="font-medium">{s.name}</span> },
  { header: 'Jumlah', className: CENTER, cell: (s) => <span className="tabular-nums">{s.quantity} {s.unit}</span> },
  // Status dihitung backend dari batas minimum barang.
  { header: 'Status', className: CENTER, mobile: 'badge', cell: (s) => <Badge color={stockColor[s.status]}>{s.status}</Badge> },
]

/** Tampilan gudang: hanya Aset tersimpan & Stok tersimpan, tanpa standar dan cek. */
function StorageRoomView({ checklist, loading, error, onRetry }: { checklist: Checklist | null; loading: boolean; error?: unknown; onRetry?: () => void }) {
  return (
    <div className="grid items-start gap-4 lg:grid-cols-2">
      <section className="min-w-0 space-y-2">
        <SectionHeader title="Aset tersimpan" description="Aset yang terdaftar dengan lokasi asal di gudang ini." />
        <DataTable
          dense
          columns={storedAssetColumns}
          rows={loading ? [] : (checklist?.data.assets ?? [])}
          rowKey={(g) => g.asset_type.id}
          loading={loading}
          error={error}
          onRetry={onRetry}
          emptyText="Belum ada aset tersimpan di gudang ini."
        />
      </section>

      <section className="min-w-0 space-y-2">
        <SectionHeader title="Stok tersimpan" description="Barang habis pakai yang disimpan di gudang ini." />
        <DataTable
          dense
          columns={storedStockColumns}
          rows={loading ? [] : (checklist?.data.stored_stock ?? [])}
          rowKey={(s) => s.id}
          loading={loading}
          error={error}
          onRetry={onRetry}
          emptyText="Belum ada stok tersimpan di gudang ini."
        />
      </section>
    </div>
  )
}

export default function RoomsPage() {
  const { building: buildingSlug, room: roomSlug } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const readiness = useReadiness()
  // Ruangan gudang selalu di urutan paling akhir (urutan lain tetap seperti dari server).
  const buildings = useMemo(
    () =>
      readiness.data?.map((b) => ({ ...b, rooms: [...b.rooms].sort((x, y) => Number(x.is_storage) - Number(y.is_storage)) })) ??
      null,
    [readiness.data],
  )
  const [confirmingCheck, setConfirmingCheck] = useState(false)
  const [saving, setSaving] = useState(false)
  const [filling, setFilling] = useState<RoomConsumable | null>(null)
  const [adjusting, setAdjusting] = useState<RoomConsumable | null>(null)
  const [card, setCard] = useState<RoomConsumable | null>(null)
  const [modal, setModal] = useState<'building' | 'room' | null>(null)
  const [standardTarget, setStandardTarget] = useState<StandardTarget | null>(null)
  const [removing, setRemoving] = useState<RoomConsumable | null>(null)
  const [removingAsset, setRemovingAsset] = useState<RoomAssetGroup | null>(null)
  const [busy, setBusy] = useState(false)

  // "Buat permintaan" membuka form berisi gedung & ruangan: datanya disiapkan dari sini.
  useEffect(() => prefetchRoomOptions(queryClient), [queryClient])

  // Slug URL → id dari data yang sudah dimuat (API tetap memakai id).
  const building = buildings?.find((b) => b.slug === buildingSlug)
  const room = building?.rooms.find((r) => r.slug === roomSlug)
  const roomId = room?.id

  const checklistQuery = useChecklist(roomId)
  const checklist = checklistQuery.data ?? null
  useErrorToast(readiness.error, checklistQuery.error)

  const reloadAll = () => invalidate.rooms(queryClient)
  const reloadReadiness = () => queryClient.invalidateQueries({ queryKey: queryKeys.rooms.readiness() })

  // Checklist ruangan lain yang masih tampil dianggap belum dimuat.
  const checklistReady = checklist !== null && checklist.meta.room.id === roomId

  const assetRows = checklistReady ? checklist.data.assets : []
  // Setiap perubahan barang langsung tersimpan (popup Catat perubahan), jadi baris diambil apa adanya dari server.
  const consumableRows = checklistReady ? checklist.data.consumables : []

  if (!buildings) {
    return (
      <div className="space-y-5" aria-busy="true">
        <LoadingLabel />
        <div className="space-y-2">
          <Skeleton className="h-7 w-56" />
          <Skeleton className="h-4 w-80 max-w-full" />
        </div>
        <div className="flex flex-wrap gap-1.5 rounded-xl border border-slate-200 bg-white p-4">
          {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-8 w-24 rounded-full" />)}
        </div>
        <TableSkeleton columns={6} />
      </div>
    )
  }

  // /kelengkapan-ruangan atau slug tidak cocok → gedung & ruangan pertama.
  if (!building || (!room && building.rooms.length > 0)) {
    const b = building ?? buildings.find((x) => x.rooms.length > 0) ?? buildings[0]
    if (b && (b.slug !== buildingSlug || b.rooms[0])) {
      return <Navigate to={b.rooms[0] ? `${BASE}/${b.slug}/${b.rooms[0].slug}` : `${BASE}/${b.slug}`} replace />
    }
  }

  const totalRooms = buildings.reduce((n, b) => n + b.rooms_count, 0)
  // Sama dengan kartu Dashboard "N dari {dinilai} ruangan belum lengkap · {gudang} gudang tidak dinilai" (T45, UAT U5).
  const storageRooms = buildings.reduce((n, b) => n + b.rooms.filter((r) => r.is_storage).length, 0)
  const roomCountText = `${totalRooms} ruangan di ${buildings.length} gedung${storageRooms > 0 ? ` (${totalRooms - storageRooms} dinilai, ${storageRooms} gudang)` : ''}`
  const shortages = assetRows.filter((g) => g.result === 'Kurang').length + consumableRows.filter((s) => s.result === 'Kurang').length
  const roomLabel = room && building ? `${room.name} · ${building.name}` : ''
  const isStorage = !!room?.is_storage
  // Ringkasan untuk dialog Tandai sudah dicek (kosong = semua sesuai, cukup konfirmasi singkat).
  const assetShortages = assetRows.filter((g) => g.result === 'Kurang').length
  const consumableShortages = consumableRows.filter((s) => s.result === 'Kurang').length
  const uncheckedItems = consumableRows.filter((s) => s.result === 'Belum dicek').length

  const markChecked = async () => {
    if (!roomId) return
    setSaving(true)
    try {
      await api.post(`/rooms/${roomId}/check`)
      toast.success(`${room?.name} ditandai sudah dicek.`)
      setConfirmingCheck(false)
      await reloadAll()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setSaving(false)
    }
  }

  const removeAssetStandard = async () => {
    if (!removingAsset?.standard_id) return
    setBusy(true)
    try {
      await api.delete(`/room-standards/${removingAsset.standard_id}`)
      toast.success(`Standar ${removingAsset.asset_type.name} dihapus.`)
      setRemovingAsset(null)
      await reloadAll()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  const removeConsumable = async () => {
    if (!removing) return
    setBusy(true)
    try {
      await api.delete(`/room-standards/${removing.id}`)
      toast.success(`${removing.item_name} dilepas dari ${room?.name}.`)
      setRemoving(null)
      await reloadAll()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  // Menu ⋮ kartu HP & tablet (pengganti ikon aksi tabel desktop).
  const assetMenu = (g: RoomAssetGroup) => (
    <DropdownMenu
      label={`Aksi ${g.asset_type.name}`}
      trigger={<EllipsisVertical size={16} />}
      triggerClassName={cardMenuTrigger}
      items={[
        { label: 'Atur standar', onClick: () => setStandardTarget({ kind: 'asset', group: g }) },
        ...(g.standard_id !== null ? [{ label: 'Hapus standar', danger: true, onClick: () => setRemovingAsset(g) }] : []),
      ]}
    />
  )
  const consumableMenu = (s: RoomConsumable) => (
    <DropdownMenu
      label={`Aksi ${s.item_name}`}
      trigger={<EllipsisVertical size={16} />}
      triggerClassName={cardMenuTrigger}
      items={[
        { label: 'Kartu ruangan', onClick: () => setCard(s) },
        { label: 'Ubah', onClick: () => setStandardTarget({ kind: 'consumable', row: s }) },
        { label: 'Hapus', danger: true, onClick: () => setRemoving(s) },
      ]}
    />
  )

  // Kartu HP (< sm). Standar 0 → "{Ada} unit"; Selisih tidak tampil terpisah, lencana menunjukkan "Kurang N".
  const assetPhoneCard = (g: RoomAssetGroup) => {
    const amount = g.standard_quantity > 0 ? `${g.present_quantity} dari ${g.standard_quantity}` : `${g.present_quantity} unit`
    const condition = conditionText(g)
    return (
      <PhoneCard
        title={g.asset_type.name}
        badge={g.result && <ResultBadge result={g.result} shortage={Math.max(0, g.standard_quantity - g.present_quantity)} />}
        menu={assetMenu(g)}
      >
        <AssetCodes group={g} />
        <p className="tabular-nums">{condition ? `${amount} · ${condition}` : amount}</p>
      </PhoneCard>
    )
  }
  // Aksi utama: Isi dari stok, atau Ajukan permintaan bila stok habis (backend tidak pernah mengizinkan keduanya).
  const consumablePhoneCard = (s: RoomConsumable) => (
    <PhoneCard
      title={s.item_name}
      badge={<ResultBadge result={s.result} shortage={s.shortage ?? 0} />}
      menu={consumableMenu(s)}
      actions={
        <div className="flex flex-wrap gap-2">
          {s.can_fill_from_stock ? (
            <Button variant="secondary" className="flex-1" onClick={() => setFilling(s)}>Isi dari stok</Button>
          ) : s.can_request && roomId ? (
            <Link to={requestUrl(roomId, s)} className={buttonClass('secondary', 'flex-1')}>Ajukan permintaan</Link>
          ) : null}
          <Button variant="secondary" className="flex-1" onClick={() => setAdjusting(s)}>Catat perubahan</Button>
        </div>
      }
    >
      <p className="text-xs text-slate-500">
        Stok {s.consumable.quantity} {s.consumable.unit} · {s.consumable.provider_department?.name ?? '-'}
      </p>
      <p className="tabular-nums">{s.recorded_quantity} dari {s.standard_quantity}</p>
    </PhoneCard>
  )

  const assetColumns: Column<RoomAssetGroup>[] = [
    {
      header: 'Jenis aset',
      mobile: 'title',
      cell: (g) => (
        <>
          <span className="font-medium">{g.asset_type.name}</span>
          <AssetCodes group={g} />
        </>
      ),
    },
    { header: 'Standar', className: COL.standard, mobile: 'stat', cell: (g) => <span className="tabular-nums">{g.standard_quantity}</span> },
    // Jumlah = aset yang dihitung Ada (tanpa Dipinjam/Perbaikan/Hilang; keterangannya di bawah nama jenis).
    { header: 'Jumlah', className: COL.quantity, mobile: 'stat', cell: (g) => <span className="tabular-nums">{g.present_quantity}</span> },
    { header: 'Selisih', className: COL.difference, mobile: 'stat', cell: (g) => differenceCell(g.difference) },
    {
      header: 'Hasil', className: COL.result,
      mobile: 'badge',
      cell: (g) => (g.result ? <Badge color={resultColor[g.result]}>{g.result}</Badge> : <span className="text-slate-500">—</span>),
    },
    { ...conditionColumn, className: COL.extra, mobile: 'stat' },
    // Kartu tablet: ikon diganti menu ⋮ di samping lencana Hasil.
    { header: '', mobile: 'badge', cardOnly: true, cell: assetMenu },
    {
      header: '',
      className: COL.icons,
      mobile: 'hidden',
      cell: (g) => (
        <div className="flex items-center gap-1 lg:justify-end">
          <button
            type="button"
            className={iconButton}
            aria-label={`Atur standar ${g.asset_type.name}`}
            title="Atur standar"
            onClick={() => setStandardTarget({ kind: 'asset', group: g })}
          >
            <Pencil size={15} />
          </button>
          {/* Hanya jenis yang punya standar (> 0); standar 0 tidak disimpan sebagai baris. */}
          {g.standard_id !== null && (
            <button
              type="button"
              className={`${iconButton} hover:bg-bad-50! hover:text-bad-700!`}
              aria-label={`Hapus standar ${g.asset_type.name}`}
              title="Hapus standar"
              onClick={() => setRemovingAsset(g)}
            >
              <Trash2 size={15} />
            </button>
          )}
        </div>
      ),
    },
  ]

  const consumableColumns: Column<RoomConsumable>[] = [
    {
      header: 'Barang',
      mobile: 'title',
      cell: (s) => (
        <>
          <span className="font-medium">{s.item_name}</span>
          <span className="block text-xs text-slate-500">
            Stok {s.consumable.quantity} {s.consumable.unit} · {s.consumable.provider_department?.name ?? '-'}
          </span>
          {s.last_usage && (
            <span className="block text-xs text-slate-500">
              Terpakai {s.last_usage.quantity} {s.consumable.unit} pada cek {formatDate(s.last_usage.date)}
            </span>
          )}
        </>
      ),
    },
    { header: 'Standar', className: COL.standard, mobile: 'stat', cell: (s) => <span className="tabular-nums">{s.standard_quantity}</span> },
    {
      header: 'Jumlah', className: COL.quantity, mobile: 'stat',
      // Saldo catatan; Belum dicek tetap tampil angkanya (Hasil = Belum dicek). Perubahan lewat popup.
      cell: (s) => (
        <div>
          <span className={`tabular-nums ${s.checked_at ? '' : 'text-slate-500'}`}>{s.recorded_quantity}</span>
          <button
            type="button"
            className="tap block text-xs font-semibold text-brand-700 hover:underline lg:mx-auto"
            onClick={() => setAdjusting(s)}
          >
            Catat perubahan
          </button>
        </div>
      ),
    },
    { header: 'Selisih', className: COL.difference, mobile: 'stat', cell: (s) => differenceCell(s.difference) },
    { header: 'Hasil', className: COL.result, mobile: 'badge', cell: (s) => <Badge color={resultColor[s.result]}>{s.result}</Badge> },
    {
      header: 'Aksi', className: COL.extra,
      // Kartu: baris bawah tanpa label, tidak tampil bila tidak ada aksi; tombol & tautan sebaris (< lg).
      mobile: 'footer',
      cell: (s) => (!s.can_fill_from_stock && !(s.can_request && roomId) ? null : (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 lg:block lg:space-y-1">
          {s.can_fill_from_stock && (
            <Button variant="secondary" className="min-h-10! lg:min-h-8! px-3! text-xs!" onClick={() => setFilling(s)}>
              Isi dari stok
            </Button>
          )}
          {s.can_request && roomId && (
            <Link to={requestUrl(roomId, s)} className="tap block text-xs font-semibold text-brand-700 hover:underline">Ajukan permintaan</Link>
          )}
        </div>
      )),
    },
    // Kartu tablet: ikon diganti menu ⋮ di samping lencana Hasil.
    { header: '', mobile: 'badge', cardOnly: true, cell: consumableMenu },
    {
      header: '',
      className: COL.icons,
      mobile: 'hidden',
      cell: (s) => (
        <div className="flex items-center gap-1 lg:justify-end">
          <button
            type="button"
            className={iconButton}
            aria-label={`Kartu ruangan ${s.item_name}`}
            title="Kartu ruangan"
            onClick={() => setCard(s)}
          >
            <History size={15} />
          </button>
          <button
            type="button"
            className={iconButton}
            aria-label={`Ubah ${s.item_name}`}
            title="Ubah"
            onClick={() => setStandardTarget({ kind: 'consumable', row: s })}
          >
            <Pencil size={15} />
          </button>
          <button
            type="button"
            className={`${iconButton} hover:bg-bad-50! hover:text-bad-700!`}
            aria-label={`Hapus ${s.item_name}`}
            title="Hapus"
            onClick={() => setRemoving(s)}
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ]

  return (
    <div className="space-y-3">
      <PageHeader
        title="Kelengkapan Ruangan"
        description={`Aset dan barang habis pakai per ruangan · ${roomCountText}`}
        actions={
          <>
            {/* Status cek ruangan terpilih (hanya berubah lewat Tandai sudah dicek). */}
            {room && isStorage && (
              <p className="flex items-center gap-2 text-xs text-slate-500 sm:mr-2">
                <Badge>Gudang</Badge>
                <span>Gudang tidak dicek terhadap standar</span>
              </p>
            )}
            {room && !isStorage && (
              <p className="flex items-center gap-1.5 text-xs text-slate-500 sm:mr-2">
                <Clock size={14} className="shrink-0" aria-hidden />
                <span>
                  {room.last_checked_at
                    ? `Dicek ${formatDateTime(room.last_checked_at)} oleh ${room.last_checked_by ?? '-'}`
                    : 'Belum pernah dicek'}
                  {checklist && checklist.meta.summary.unchecked_items > 0 && ` · ${checklist.meta.summary.unchecked_items} belum dicek`}
                  {checklist && checklist.meta.summary.total_items === 0 && ' · Belum ada standar'}
                </span>
              </p>
            )}
            <ExportButton type="rooms" filters={() => ({ building_id: building?.id })} />
            {room && !isStorage && <Button onClick={() => setConfirmingCheck(true)} disabled={!checklistReady}>Tandai sudah dicek</Button>}
          </>
        }
      />

      {readiness.error && !readiness.data && (
        <LoadError className="rounded-xl border border-slate-200 bg-white" onRetry={() => readiness.refetch()} retrying={readiness.isFetching} />
      )}

      <section className="space-y-2.5 rounded-xl border border-slate-200 bg-white p-4 max-sm:hidden">
        <div className={selectorRow}>
          <p className={selectorLabel}>Gedung</p>
          <div className={chipRow}>
            {buildings.map((b) => (
              <button key={b.id} type="button" className={chip(b.id === building?.id)}
                onClick={() => navigate(b.rooms[0] ? `${BASE}/${b.slug}/${b.rooms[0].slug}` : `${BASE}/${b.slug}`)}>
                {b.name}
              </button>
            ))}
            <button type="button" className={chip(false)} onClick={() => setModal('building')} aria-label="Tambah gedung">
              <Plus size={14} />
            </button>
          </div>
        </div>
        {building && (
          <div className={`${selectorRow} border-t border-slate-100 pt-2.5`}>
            <p className={selectorLabel} title={`Ruangan di ${building.name}`}>Ruangan</p>
            <div className={chipRow}>
              {building.rooms.map((r) => (
                <button key={r.id} type="button" className={chip(r.id === roomId)}
                  onClick={() => navigate(`${BASE}/${building.slug}/${r.slug}`)}
                  title={r.is_storage ? 'Gudang' : r.shortage_items ? `Kurang ${r.shortage_items} item` : r.total_items ? 'Lengkap' : 'Belum ada standar'}>
                  {r.name}
                  {r.is_storage && <Warehouse size={14} className="ml-1.5 shrink-0" role="img" aria-label="Gudang" />}
                  {!r.is_storage && r.shortage_items > 0 && (
                    <span className={`ml-1.5 rounded-full px-1.5 text-[11px] tabular-nums bg-bad-50 text-bad-700`}>
                      −{r.shortage_items}
                    </span>
                  )}
                </button>
              ))}
              <button type="button" className={chip(false)} onClick={() => setModal('room')} aria-label="Tambah ruangan">
                <Plus size={14} />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* HP: dropdown gedung & ruangan menggantikan deretan chip (daftar pilihan z-50, di atas header sticky). */}
      <section className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2.5 rounded-xl border border-slate-200 bg-white p-4 sm:hidden">
        <span className="text-xs font-medium text-slate-500">Gedung</span>
        <Select aria-label="Gedung" value={building?.id ?? ''} onChange={(e) => {
          const b = buildings.find((x) => x.id === Number(e.target.value))
          if (b) navigate(b.rooms[0] ? `${BASE}/${b.slug}/${b.rooms[0].slug}` : `${BASE}/${b.slug}`)
        }}>
          <option value="">{buildings.length ? 'Pilih gedung' : 'Belum ada gedung'}</option>
          {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Select>
        <Button variant="secondary" className={phoneIconButton} onClick={() => setModal('building')} aria-label="Tambah gedung">
          <Plus size={18} />
        </Button>
        {building && (
          <>
            <span className="text-xs font-medium text-slate-500">Ruangan</span>
            <Select aria-label="Ruangan" value={roomId ?? ''} onChange={(e) => {
              const r = building.rooms.find((x) => x.id === Number(e.target.value))
              if (r) navigate(`${BASE}/${building.slug}/${r.slug}`)
            }}>
              <option value="">{building.rooms.length ? 'Pilih ruangan' : 'Belum ada ruangan'}</option>
              {building.rooms.map((r) => (
                <option key={r.id} value={r.id}>
                  {/* Format "−N" sama dengan chip tablet/desktop; teks pendek agar tidak terpotong di kolom HP. */}
                  {r.is_storage ? `${r.name} (gudang)` : r.shortage_items > 0 ? `${r.name} (−${r.shortage_items})` : r.name}
                </option>
              ))}
            </Select>
            <Button variant="secondary" className={phoneIconButton} onClick={() => setModal('room')} aria-label="Tambah ruangan">
              <Plus size={18} />
            </Button>
          </>
        )}
      </section>

      {room && !isStorage && shortages > 0 && (
        <>
          <div role="alert" className="flex items-start gap-3 rounded-xl border border-warn-700/40 bg-warn-50 px-4 py-3 text-sm text-warn-700 max-sm:hidden">
            <TriangleAlert size={18} className="mt-0.5 shrink-0" aria-hidden />
            <p>
              <b>{room.name} kurang {shortages} item.</b>
              {consumableRows.some((s) => s.result === 'Kurang') && ' Barang habis pakai dapat diambil dari stok, atau ajukan permintaan jika stok habis.'}
            </p>
          </div>
          {/* HP: satu baris, penjelasan lengkap saat diketuk. */}
          <div role="alert" className="sm:hidden">
            <details className="group rounded-xl border border-warn-700/40 bg-warn-50 text-sm text-warn-700">
              <summary className="flex min-h-10 cursor-pointer list-none items-center gap-2 px-4 py-2 font-semibold [&::-webkit-details-marker]:hidden">
                <TriangleAlert size={18} className="shrink-0" aria-hidden />
                Kurang {shortages} item
                <ChevronDown size={16} className="ml-auto shrink-0 transition-transform group-open:rotate-180" aria-hidden />
              </summary>
              <p className="px-4 pb-3">
                <b>{room.name} kurang {shortages} item.</b>
                {consumableRows.some((s) => s.result === 'Kurang') && ' Barang habis pakai dapat diambil dari stok, atau ajukan permintaan jika stok habis.'}
              </p>
            </details>
          </div>
        </>
      )}

      {room && isStorage && <StorageRoomView checklist={checklist} loading={!checklistReady} error={checklistQuery.error} onRetry={() => checklistQuery.refetch()} />}

      {room && !isStorage && (
        <>
          <section className="space-y-1.5">
            <SectionHeader
              title="Aset di ruangan"
              action={
                <Button variant="secondary" className={`${compactButton} ${sectionAddButton}`} aria-label="Tambah standar jenis"
                  onClick={() => setStandardTarget({ kind: 'asset', group: null })}>
                  <Plus size={14} className="max-sm:size-4.5" /> <span className="max-sm:hidden">Tambah standar jenis</span>
                </Button>
              }
            />
            <DataTable
              dense
              phoneCard={assetPhoneCard}
              columns={assetColumns}
              rows={assetRows}
              rowKey={(g) => g.asset_type.id}
              loading={!checklistReady}
              error={checklistQuery.error}
              onRetry={() => checklistQuery.refetch()}
              retrying={checklistQuery.isFetching}
              emptyText="Belum ada aset di ruangan ini."
            />
          </section>

          <section className="space-y-1.5">
            <SectionHeader
              title="Barang habis pakai"
              action={
                <Button variant="secondary" className={`${compactButton} ${sectionAddButton}`} aria-label="Tambah barang"
                  onClick={() => setStandardTarget({ kind: 'consumable', row: null })}>
                  <Plus size={14} className="max-sm:size-4.5" /> <span className="max-sm:hidden">Tambah barang</span>
                </Button>
              }
            />
            <DataTable
              dense
              phoneCard={consumablePhoneCard}
              columns={consumableColumns}
              rows={consumableRows}
              rowKey={(s) => s.id}
              loading={!checklistReady}
              error={checklistQuery.error}
              onRetry={() => checklistQuery.refetch()}
              retrying={checklistQuery.isFetching}
              emptyText="Belum ada barang habis pakai. Klik “Tambah barang” untuk menambah."
            />
          </section>
        </>
      )}
      {building && building.rooms.length === 0 && (
        <p className="text-sm text-slate-500">Belum ada ruangan di {building.name}. Tambah ruangan dengan tombol +.</p>
      )}

      {standardTarget && roomId && (
        <StandardFormModal
          roomId={roomId}
          roomName={roomLabel}
          target={standardTarget}
          // Hanya jenis yang sudah punya standar (> 0); jenis dengan standar 0 (mis. aset ada tanpa standar) tetap bisa dipilih.
          usedAssetTypeIds={assetRows.filter((g) => g.standard_id !== null).map((g) => g.asset_type.id)}
          usedConsumableIds={consumableRows.map((s) => s.consumable.id)}
          onClose={() => setStandardTarget(null)}
          onDone={() => {
            setStandardTarget(null)
            reloadAll()
          }}
        />
      )}
      {filling && room && roomId && (
        <FillFromStockModal
          row={filling}
          roomName={roomLabel}
          requestHref={(quantity) => requestUrl(roomId, filling, quantity)}
          onClose={() => setFilling(null)}
          onDone={() => {
            setFilling(null)
            reloadAll()
          }}
        />
      )}
      {card && <RoomCardModal row={card} roomName={roomLabel} onClose={() => setCard(null)} />}
      {adjusting && (
        <AdjustRoomStockModal
          row={adjusting}
          roomName={roomLabel}
          onClose={() => setAdjusting(null)}
          onDone={() => {
            setAdjusting(null)
            reloadAll()
          }}
        />
      )}
      <ConfirmDialog
        open={confirmingCheck}
        title="Tandai sudah dicek"
        confirmLabel="Tandai sudah dicek"
        loading={saving}
        onConfirm={markChecked}
        onClose={() => setConfirmingCheck(false)}
      >
        {assetShortages + consumableShortages + uncheckedItems === 0 ? (
          `Tandai ${roomLabel} sudah dicek?`
        ) : (
          <span className="block space-y-1">
            {assetShortages > 0 && <span className="block">Ada {assetShortages} aset Kurang. Aset hilang dicatat lewat detail aset.</span>}
            {consumableShortages > 0 && <span className="block">Ada {consumableShortages} barang habis pakai Kurang.</span>}
            {uncheckedItems > 0 && <span className="block">{uncheckedItems} barang belum dicek akan dianggap sesuai catatan.</span>}
          </span>
        )}
      </ConfirmDialog>
      <ConfirmDialog
        open={removingAsset !== null}
        title="Hapus standar jenis aset"
        confirmLabel="Hapus"
        danger
        loading={busy}
        onConfirm={removeAssetStandard}
        onClose={() => setRemovingAsset(null)}
      >
        Hapus standar {removingAsset?.asset_type.name} di {roomLabel}? Standar kembali 0. Aset di ruangan ini tidak berubah.
      </ConfirmDialog>
      <ConfirmDialog
        open={removing !== null}
        title="Lepas barang dari ruangan"
        confirmLabel="Lepas"
        danger
        loading={busy}
        onConfirm={removeConsumable}
        onClose={() => setRemoving(null)}
      >
        Lepas "{removing?.item_name}" dari {roomLabel}? Stok barang tidak berubah. Riwayat barang di ruangan ini tetap tersimpan dan
        berlanjut jika barang ditambahkan lagi.
      </ConfirmDialog>
      {modal === 'building' && (
        <NameModal
          title="Tambah gedung"
          label="Nama gedung"
          onClose={() => setModal(null)}
          onSubmit={async (name) => {
            const b = await createBuilding(name)
            await reloadReadiness()
            setModal(null)
            navigate(`${BASE}/${b.slug}`)
          }}
        />
      )}
      {modal === 'room' && building && (
        <NameModal
          title={`Tambah ruangan • ${building.name}`}
          label="Nama ruangan"
          placeholder="Misal: Lt. 8 – Ruang A"
          withStorage
          onClose={() => setModal(null)}
          onSubmit={async (name, isStorage) => {
            const r = await createRoom(building.id, name, isStorage)
            await reloadReadiness()
            setModal(null)
            navigate(`${BASE}/${building.slug}/${r.slug}`)
          }}
        />
      )}
    </div>
  )
}
