import { Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
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
import type { Checklist, ChecklistResult, RoomStandard } from '../../types/room'
import NameModal from './NameModal'
import StandardsModal from './StandardsModal'

const BASE = '/kelengkapan-ruangan'

const resultColor = { Sesuai: 'green', Kurang: 'red', 'Belum dicek': 'amber' } as const

const chip = (active: boolean) =>
  `inline-flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold transition-colors ${
    active ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
  }`

// HP: chip digeser horizontal; sm ke atas: membungkus.
const chipRow = '-mx-5 flex gap-2 overflow-x-auto px-5 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0'

const requestUrl = (roomId: number, s?: RoomStandard) => {
  const p = new URLSearchParams({ room_id: String(roomId) })
  const f = s?.fulfillment
  if (f?.type === 'stok') {
    p.set('consumable_id', String(f.consumable.id))
    p.set('item_name', f.consumable.name)
    if (f.consumable.provider_department) p.set('provider_department_id', String(f.consumable.provider_department.id))
  } else if (f?.type === 'unit_lain' && s) {
    p.set('room_standard_id', String(s.id))
    p.set('item_name', s.item_name)
    if (f.catalog) p.set('catalog_id', String(f.catalog.id))
    else if (f.provider_department) p.set('provider_department_id', String(f.provider_department.id))
  }
  if (s?.shortage) p.set('quantity', String(s.shortage))
  return `/permintaan?${p}`
}

const MAX_ASSET_CODES = 3
const NOT_COUNTED_LABELS = { Dipinjam: 'sedang dipinjam', Perbaikan: 'dalam perbaikan', Hilang: 'hilang' } as const

/** Kode aset yang terhitung "Ada" (klik menuju detail) + keterangan aset yang tidak dihitung. */
function AssetCodes({ standard }: { standard: RoomStandard }) {
  const shown = standard.assets.slice(0, MAX_ASSET_CODES)
  const hidden = standard.assets.slice(MAX_ASSET_CODES)
  const notCounted = (Object.keys(NOT_COUNTED_LABELS) as (keyof typeof NOT_COUNTED_LABELS)[])
    .filter((status) => standard.not_counted[status])
    .map((status) => `${standard.not_counted[status]} ${NOT_COUNTED_LABELS[status]}`)

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

export default function RoomsPage() {
  const { building: buildingSlug, room: roomSlug } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const readiness = useReadiness()
  const buildings = readiness.data ?? null
  const [actual, setActual] = useState<Record<number, string>>({})
  const [actualFrom, setActualFrom] = useState<Checklist | null>(null)
  const [saving, setSaving] = useState(false)
  const [filling, setFilling] = useState<number | null>(null)
  const [modal, setModal] = useState<'standards' | 'building' | 'room' | null>(null)

  // "Buat permintaan" membuka form berisi gedung & ruangan: datanya disiapkan dari sini.
  useEffect(() => prefetchRoomOptions(queryClient), [queryClient])

  // Slug URL → id dari data yang sudah dimuat (API tetap memakai id).
  const building = buildings?.find((b) => b.slug === buildingSlug)
  const room = building?.rooms.find((r) => r.slug === roomSlug)
  const roomId = room?.id

  const checklistQuery = useChecklist(roomId)
  const checklist = checklistQuery.data ?? null
  useErrorToast(readiness.error, checklistQuery.error)

  // Data checklist baru (ruangan lain / setelah simpan) → isi ulang kolom Ada baris Manual.
  if (checklist && checklist !== actualFrom) {
    setActualFrom(checklist)
    setActual(
      Object.fromEntries(
        checklist.data.filter((s) => s.count_method === 'Manual').map((s) => [s.id, s.present_quantity?.toString() ?? '']),
      ),
    )
  }

  const reloadAll = () => invalidate.rooms(queryClient)
  const reloadReadiness = () => queryClient.invalidateQueries({ queryKey: queryKeys.rooms.readiness() })

  // Checklist ruangan lain yang masih tampil dianggap belum dimuat.
  const checklistReady = checklist !== null && checklist.meta.room.id === roomId

  // Pratinjau langsung: baris Manual memakai angka yang sedang diketik.
  const rows = useMemo(
    () =>
      (checklistReady ? checklist.data : []).map((s) => {
        if (s.count_method !== 'Manual') return s
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
        <div className="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-5">
          {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-10 w-28 rounded-full" />)}
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
  const manualRows = rows.filter((s) => s.count_method === 'Manual')
  const shortages = rows.filter((s) => s.result === 'Kurang')
  const roomLabel = room && building ? `${room.name} · ${building.name}` : ''
  const canSave = !!roomId && manualRows.length > 0

  const saveCheck = async () => {
    if (!roomId) return
    const items = manualRows
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

  const fillFromStock = async (s: RoomStandard) => {
    setFilling(s.id)
    try {
      const res = await api.post<{ message: string }>(`/room-standards/${s.id}/fill-from-stock`)
      toast.success(res.data.message)
      await reloadAll()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setFilling(null)
    }
  }

  // Kalimat banner: "Kekurangan dapat diisi dari stok atau diminta ke departemen penyedia."
  const hasRequestable = shortages.some((s) => s.can_request)
  const ways = [
    ...(shortages.some((s) => s.can_fill_from_stock) ? ['diisi dari stok'] : []),
    ...(hasRequestable ? ['diminta ke departemen penyedia'] : []),
  ]
  const waysText = ways.length ? ` Kekurangan dapat ${ways.join(' atau ')}.` : ''

  const columns: Column<RoomStandard>[] = [
    {
      header: 'Perlengkapan standar',
      mobile: 'title',
      cell: (s) => (
        <>
          <span className="font-medium">{s.item_name}</span>
          {s.needs_completion ? (
            <span className="block text-xs font-medium text-warn-700">Perlu dilengkapi</span>
          ) : s.count_method === 'QR' ? (
            <AssetCodes standard={s} />
          ) : (
            <span className="block text-xs text-slate-500">{s.fulfillment?.type === 'unit_lain' ? 'Barang departemen lain' : 'Stok Habis Pakai'}</span>
          )}
        </>
      ),
    },
    {
      header: 'Sumber',
      cell: (s) => (s.source_department ? (s.source_department.is_internal ? `Milik ${s.source_department.name}` : s.source_department.name) : '-'),
    },
    { header: 'Standar', cell: (s) => <span className="tabular-nums">{s.standard_quantity}</span> },
    {
      header: 'Ada',
      cell: (s) =>
        s.count_method === 'Manual' ? (
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
        ) : (
          s.present_quantity
        ),
    },
    {
      header: 'Selisih',
      cell: (s) =>
        s.difference === null ? (
          <span className="text-slate-500">—</span>
        ) : (
          <span className={`tabular-nums ${s.difference < 0 ? 'font-semibold text-bad-700' : ''}`}>{s.difference}</span>
        ),
    },
    { header: 'Hasil', mobile: 'badge', cell: (s) => <Badge color={resultColor[s.result]}>{s.result}</Badge> },
    {
      header: 'Pemenuhan',
      cell: (s) => {
        if (s.needs_completion) {
          return (
            <button type="button" className="text-xs font-semibold text-brand-700 hover:underline" onClick={() => setModal('standards')}>
              Lengkapi
            </button>
          )
        }
        const f = s.fulfillment
        const text = !f
          ? '-'
          : f.type === 'unit_lain'
            ? `Minta ke ${f.provider_department?.name ?? 'departemen penyedia'}`
            : `Stok: ${f.consumable.name} (${f.consumable.quantity} ${f.consumable.unit})`
        return (
          <div className="space-y-1">
            <span className="block text-xs text-slate-600">{text}</span>
            {s.can_fill_from_stock && (
              <Button variant="secondary" className="min-h-8! px-3! text-xs!" loading={filling === s.id} onClick={() => fillFromStock(s)}>
                Isi dari stok
              </Button>
            )}
            {s.can_request && roomId && (
              <Link to={requestUrl(roomId, s)} className="block text-xs font-semibold text-brand-700 hover:underline">Buat permintaan</Link>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <div className={`space-y-5 ${canSave ? 'pb-20 lg:pb-0' : ''}`}>
      <PageHeader
        title="Kelengkapan Ruangan"
        description={`Isi ruangan dibanding standar perlengkapan · ${totalRooms} ruangan di ${buildings.length} gedung`}
        actions={
          <>
            <ExportButton type="rooms" filters={() => ({ building_id: building?.id })} />
            <Button variant="secondary" disabled={!roomId} onClick={() => setModal('standards')}>Atur standar ruangan</Button>
            <Button loading={saving} disabled={!canSave} onClick={saveCheck} className="max-lg:hidden!">Simpan hasil cek</Button>
          </>
        }
      />

      <section className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <div>
          <p className="mb-2 text-sm font-medium text-slate-500">Gedung</p>
          <div className={chipRow}>
            {buildings.map((b) => (
              <button key={b.id} type="button" className={chip(b.id === building?.id)}
                onClick={() => navigate(b.rooms[0] ? `${BASE}/${b.slug}/${b.rooms[0].slug}` : `${BASE}/${b.slug}`)}>
                {b.name}
              </button>
            ))}
            <button type="button" className={chip(false)} onClick={() => setModal('building')} aria-label="Tambah gedung">
              <Plus size={16} />
            </button>
          </div>
        </div>
        {building && (
          <div className="border-t border-slate-100 pt-4">
            <p className="mb-2 text-sm font-medium text-slate-500">Ruangan di {building.name}</p>
            <div className={chipRow}>
              {building.rooms.map((r) => (
                <button key={r.id} type="button" className={chip(r.id === roomId)}
                  onClick={() => navigate(`${BASE}/${building.slug}/${r.slug}`)}
                  title={r.shortage_items ? `Kurang ${r.shortage_items} item` : r.total_items ? 'Lengkap' : 'Belum ada standar'}>
                  {r.name}
                  {r.shortage_items > 0 && (
                    <span className={`ml-2 rounded-full px-1.5 text-xs tabular-nums ${r.id === roomId ? 'bg-white/20' : 'bg-bad-50 text-bad-700'}`}>
                      −{r.shortage_items}
                    </span>
                  )}
                </button>
              ))}
              <button type="button" className={chip(false)} onClick={() => setModal('room')} aria-label="Tambah ruangan">
                <Plus size={16} />
              </button>
            </div>
          </div>
        )}
      </section>

      {room && shortages.length > 0 && (
        <div className="flex flex-col gap-3 rounded-xl bg-warn-50 px-5 py-4 text-sm text-warn-700 sm:flex-row sm:items-center sm:justify-between">
          <p>
            <b>{room.name} kurang {shortages.length} item.</b>
            {waysText}
          </p>
          {hasRequestable && roomId && (
            <Link to={requestUrl(roomId)} className={buttonClass('secondary', 'shrink-0')}>
              Buat permintaan
            </Link>
          )}
        </div>
      )}

      {room && (
        <section className="space-y-3">
          <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <h2 className="font-semibold">Checklist {roomLabel}</h2>
            <span className="text-sm text-slate-500">
              {room.last_checked_at
                ? `Dicek terakhir ${formatDateTime(room.last_checked_at)} oleh ${room.last_checked_by ?? '-'}`
                : 'Belum pernah dicek'}
              {checklist && checklist.meta.summary.unchecked_items > 0 && ` · ${checklist.meta.summary.unchecked_items} belum dicek`}
            </span>
          </div>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(s) => s.id}
            loading={!checklistReady}
            emptyText="Belum ada standar perlengkapan. Klik “Atur standar ruangan” untuk menambah."
          />
          {manualRows.length > 0 && (
            <p className="text-xs text-slate-500">
              Isi kolom Ada untuk barang stok, lalu klik “Simpan hasil cek”. Baris aset dihitung otomatis dari aset di ruangan ini.
            </p>
          )}
        </section>
      )}
      {canSave && (
        <div className="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
          <Button loading={saving} onClick={saveCheck} className="w-full">Simpan hasil cek</Button>
        </div>
      )}
      {building && building.rooms.length === 0 && (
        <p className="text-sm text-slate-500">Belum ada ruangan di {building.name}. Tambah ruangan dengan tombol +.</p>
      )}

      {modal === 'standards' && roomId && (
        <StandardsModal
          roomId={roomId}
          roomName={roomLabel}
          standards={checklist?.data ?? []}
          onClose={() => setModal(null)}
          onChanged={reloadAll}
        />
      )}
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
          title={`Tambah ruangan — ${building.name}`}
          label="Nama ruangan"
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
