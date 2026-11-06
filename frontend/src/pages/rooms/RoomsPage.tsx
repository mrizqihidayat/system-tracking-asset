import { Clock, Pencil, Plus, Trash2, Warehouse } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { controlClass } from '../../components/ui/control-class'
import DataTable, { type Column } from '../../components/ui/DataTable'
import { PageHeader } from '../../components/ui/Page'
import { LoadingLabel, Skeleton, TableSkeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { createBuilding, createRoom } from '../../lib/lookups'
import { invalidate } from '../../lib/queries/invalidate'
import { queryKeys } from '../../lib/queries/keys'
import { useChecklist, useReadiness } from '../../lib/queries/lists'
import { prefetchRoomOptions } from '../../lib/queries/prefetch'
import { useErrorToast } from '../../lib/useErrorToast'
import type { Checklist, ChecklistResult, RoomAssetGroup, RoomConsumable } from '../../types/room'
import FillFromStockModal from './FillFromStockModal'
import NameModal from './NameModal'
import StandardFormModal, { type StandardTarget } from './StandardFormModal'

const BASE = '/kelengkapan-ruangan'

const resultColor = { Sesuai: 'green', Kurang: 'red', 'Belum dicek': 'amber' } as const
const stockColor = { Aman: 'green', Menipis: 'amber', Habis: 'red' } as const

const chip = (active: boolean) =>
  `inline-flex min-h-8 shrink-0 items-center whitespace-nowrap rounded-full border px-3 text-[13px] font-semibold transition-colors ${
    active ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
  }`

/** Tombol ikon kecil di kolom Aksi (Ubah / Hapus). */
const iconButton =
  'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900'

// HP: chip digeser horizontal; sm ke atas: membungkus.
const chipRow = '-mx-4 flex min-w-0 flex-1 gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0'
// Label di kiri sebaris dengan chip (sm ke atas), di atas chip pada HP.
const selectorRow = 'flex flex-col gap-1.5 sm:flex-row sm:items-start sm:gap-3'
const selectorLabel = 'shrink-0 text-xs font-medium text-slate-500 sm:w-16 sm:pt-2'

/** Form permintaan terisi barang, penyedia, ruangan, dan jumlah (bawaan = kekurangan). */
const requestUrl = (roomId: number, s: RoomConsumable, quantity = s.shortage) => {
  const p = new URLSearchParams({ room_id: String(roomId), consumable_id: String(s.consumable.id), item_name: s.consumable.name })
  if (s.consumable.provider_department) p.set('provider_department_id', String(s.consumable.provider_department.id))
  if (quantity) p.set('quantity', String(quantity))
  return `/permintaan?${p}`
}

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
              <Link to={`/daftar-aset/${a.id}`} className="hover:text-brand-700 hover:underline">{a.code}</Link>
            </span>
          ))}
      {hidden.length > 0 && <span title={hidden.map((a) => a.code).join(', ')}> +{hidden.length}</span>}
      {notCounted.length > 0 && <span className="block text-warn-700">{notCounted.join(' · ')}</span>}
    </span>
  )
}

const differenceCell = (difference: number | null) =>
  difference === null ? (
    <span className="text-slate-500">—</span>
  ) : (
    <span className={`tabular-nums ${difference < 0 ? 'font-semibold text-bad-700' : ''}`}>{difference}</span>
  )

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
  const [actual, setActual] = useState<Record<number, string>>({})
  const [actualFrom, setActualFrom] = useState<Checklist | null>(null)
  const [saving, setSaving] = useState(false)
  const [filling, setFilling] = useState<RoomConsumable | null>(null)
  const [modal, setModal] = useState<'building' | 'room' | null>(null)
  const [standardTarget, setStandardTarget] = useState<StandardTarget | null>(null)
  const [removing, setRemoving] = useState<RoomConsumable | null>(null)
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

  // Data checklist baru (ruangan lain / setelah simpan) → isi ulang kolom Ada barang habis pakai.
  if (checklist && checklist !== actualFrom) {
    setActualFrom(checklist)
    setActual(
      Object.fromEntries(
        checklist.data.consumables.map((s) => [s.id, s.present_quantity?.toString() ?? '']),
      ),
    )
  }

  const reloadAll = () => invalidate.rooms(queryClient)
  const reloadReadiness = () => queryClient.invalidateQueries({ queryKey: queryKeys.rooms.readiness() })

  // Checklist ruangan lain yang masih tampil dianggap belum dimuat.
  const checklistReady = checklist !== null && checklist.meta.room.id === roomId

  const assetRows = checklistReady ? checklist.data.assets : []

  // Pratinjau langsung: barang habis pakai memakai angka yang sedang diketik.
  const consumableRows = useMemo(
    () =>
      (checklistReady ? checklist.data.consumables : []).map((s) => {
        const raw = actual[s.id] ?? ''
        const present = raw === '' ? null : Number(raw)
        const difference = present === null ? null : present - s.standard_quantity
        const result: ChecklistResult = present === null ? 'Belum dicek' : difference! < 0 ? 'Kurang' : 'Sesuai'
        return { ...s, present_quantity: present, difference, result }
      }),
    [checklist, checklistReady, actual],
  )

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
  const shortages = assetRows.filter((g) => g.result === 'Kurang').length + consumableRows.filter((s) => s.result === 'Kurang').length
  const roomLabel = room && building ? `${room.name} · ${building.name}` : ''
  const canSave = !!roomId && consumableRows.length > 0

  const saveCheck = async () => {
    if (!roomId) return
    const items = consumableRows
      .filter((s) => (actual[s.id] ?? '') !== '')
      .map((s) => ({ id: s.id, actual_quantity: Number(actual[s.id]) }))
    setSaving(true)
    try {
      await api.post(`/rooms/${roomId}/check`, { items })
      toast.success(`Hasil cek ${room?.name} tersimpan.`)
      await reloadAll()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setSaving(false)
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
    { header: 'Jumlah', cell: (g) => <span className="tabular-nums">{g.total}</span> },
    { header: 'Standar', cell: (g) => <span className="tabular-nums">{g.standard_quantity}</span> },
    { header: 'Ada', cell: (g) => <span className="tabular-nums">{g.present_quantity}</span> },
    { header: 'Selisih', cell: (g) => differenceCell(g.difference) },
    {
      header: 'Hasil',
      mobile: 'badge',
      cell: (g) => (g.result ? <Badge color={resultColor[g.result]}>{g.result}</Badge> : <span className="text-slate-500">—</span>),
    },
    {
      header: '',
      className: 'w-px whitespace-nowrap text-right',
      cell: (g) => (
        <button
          type="button"
          className={`${iconButton} md:ml-auto`}
          aria-label={`Atur standar ${g.asset_type.name}`}
          title="Atur standar"
          onClick={() => setStandardTarget({ kind: 'asset', group: g })}
        >
          <Pencil size={15} />
        </button>
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
        </>
      ),
    },
    { header: 'Standar', cell: (s) => <span className="tabular-nums">{s.standard_quantity}</span> },
    {
      header: 'Ada',
      cell: (s) => (
        <input
          type="number"
          min={0}
          value={actual[s.id] ?? ''}
          onChange={(e) => setActual((a) => ({ ...a, [s.id]: e.target.value }))}
          placeholder="—"
          aria-label={`Jumlah ada ${s.item_name}`}
          inputMode="numeric"
          className={`${controlClass()} w-24! tabular-nums`}
        />
      ),
    },
    { header: 'Selisih', cell: (s) => differenceCell(s.difference) },
    { header: 'Hasil', mobile: 'badge', cell: (s) => <Badge color={resultColor[s.result]}>{s.result}</Badge> },
    {
      header: 'Aksi',
      cell: (s) => (
        <div className="space-y-1">
          {s.can_fill_from_stock && (
            <Button variant="secondary" className="min-h-8! px-3! text-xs!" onClick={() => setFilling(s)}>
              Isi dari stok
            </Button>
          )}
          {s.can_request && roomId && (
            <Link to={requestUrl(roomId, s)} className="block text-xs font-semibold text-brand-700 hover:underline">Ajukan permintaan</Link>
          )}
        </div>
      ),
    },
    {
      header: '',
      className: 'w-px whitespace-nowrap text-right',
      cell: (s) => (
        <div className="flex items-center gap-1 md:justify-end">
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
    <div className={`space-y-5 ${canSave ? 'pb-20 lg:pb-0' : ''}`}>
      <PageHeader
        title="Kelengkapan Ruangan"
        description={`Aset dan barang habis pakai per ruangan · ${totalRooms} ruangan di ${buildings.length} gedung`}
        actions={
          <>
            {/* Status cek ruangan terpilih, di samping tombol Simpan hasil cek. */}
            {room && (
              <p className="flex items-center gap-1.5 text-xs text-slate-500 sm:mr-2">
                <Clock size={14} className="shrink-0" aria-hidden />
                <span>
                  {room.last_checked_at
                    ? `Dicek ${formatDateTime(room.last_checked_at)} oleh ${room.last_checked_by ?? '-'}`
                    : 'Belum pernah dicek'}
                  {checklist && checklist.meta.summary.unchecked_items > 0 && ` · ${checklist.meta.summary.unchecked_items} belum dicek`}
                  {checklist && !room.is_storage && checklist.meta.summary.total_items === 0 && ' · Belum ada standar'}
                </span>
              </p>
            )}
            <ExportButton type="rooms" filters={() => ({ building_id: building?.id })} />
            <Button loading={saving} disabled={!canSave} onClick={saveCheck} className="max-lg:hidden!">Simpan hasil cek</Button>
          </>
        }
      />

      <section className="space-y-2.5 rounded-xl border border-slate-200 bg-white p-4">
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

      {room && !room.is_storage && shortages > 0 && (
        <div className="rounded-xl bg-warn-50 px-5 py-4 text-sm text-warn-700">
          <b>{room.name} kurang {shortages} item.</b>
          {consumableRows.some((s) => s.result === 'Kurang') && ' Barang habis pakai dapat diambil dari stok, atau ajukan permintaan jika stok habis.'}
        </div>
      )}

      {room && (
        <>
          <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 className="font-semibold">Aset di ruangan</h3>
                <p className="text-sm text-slate-500">Otomatis dari aset berlokasi asal di ruangan ini. Standar opsional per jenis.</p>
              </div>
              <Button variant="secondary" onClick={() => setStandardTarget({ kind: 'asset', group: null })}>
                <Plus size={16} /> Tambah standar jenis
              </Button>
            </div>
            <DataTable
              columns={assetColumns}
              rows={assetRows}
              rowKey={(g) => g.asset_type.id}
              loading={!checklistReady}
              emptyText="Belum ada aset di ruangan ini."
            />
          </section>

          <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 className="font-semibold">Barang habis pakai</h3>
                <p className="text-sm text-slate-500">Barang dari Stok dengan jumlah standar. Isi kolom Ada, lalu klik “Simpan hasil cek”.</p>
              </div>
              <Button variant="secondary" onClick={() => setStandardTarget({ kind: 'consumable', row: null })}>
                <Plus size={16} /> Tambah barang
              </Button>
            </div>
            <DataTable
              columns={consumableColumns}
              rows={consumableRows}
              rowKey={(s) => s.id}
              loading={!checklistReady}
              emptyText="Belum ada barang habis pakai. Klik “Tambah barang” untuk menambah."
            />
          </section>

          {!!checklist && checklistReady && checklist.data.stored_stock.length > 0 && (
            <section className="rounded-xl border border-slate-200 bg-white p-5">
              <h3 className="font-semibold">Stok tersimpan di sini</h3>
              <p className="text-sm text-slate-500">Hanya informasi; tidak dihitung sebagai Ada di ruangan ini.</p>
              <ul className="mt-2 divide-y divide-slate-100">
                {checklist.data.stored_stock.map((s) => (
                  <li key={s.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <span className="min-w-0 font-medium">{s.name}</span>
                    <span className="flex shrink-0 items-center gap-3">
                      <span className="tabular-nums">{s.quantity} {s.unit}</span>
                      <Badge color={stockColor[s.status]}>{s.status}</Badge>
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </>
      )}
      {canSave && (
        <div className="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
          <Button loading={saving} onClick={saveCheck} className="w-full">Simpan hasil cek</Button>
        </div>
      )}
      {building && building.rooms.length === 0 && (
        <p className="text-sm text-slate-500">Belum ada ruangan di {building.name}. Tambah ruangan dengan tombol +.</p>
      )}

      {standardTarget && roomId && (
        <StandardFormModal
          roomId={roomId}
          roomName={roomLabel}
          target={standardTarget}
          usedAssetTypeIds={assetRows.map((g) => g.asset_type.id)}
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
      <ConfirmDialog
        open={removing !== null}
        title="Lepas barang dari ruangan"
        confirmLabel="Lepas"
        danger
        loading={busy}
        onConfirm={removeConsumable}
        onClose={() => setRemoving(null)}
      >
        Lepas "{removing?.item_name}" dari {roomLabel}? Stok barang tidak berubah.
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
          onClose={() => setModal(null)}
          onSubmit={async (name) => {
            const r = await createRoom(building.id, name)
            await reloadReadiness()
            setModal(null)
            navigate(`${BASE}/${building.slug}/${r.slug}`)
          }}
        />
      )}
    </div>
  )
}
