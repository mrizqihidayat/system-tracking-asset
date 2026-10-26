import { Plus } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { createBuilding, createRoom } from '../../lib/lookups'
import type { Checklist, ChecklistResult, ReadinessBuilding, RoomStandard } from '../../types/room'
import NameModal from './NameModal'
import StandardsModal from './StandardsModal'

const BASE = '/kelengkapan-ruangan'

const resultColor = { Sesuai: 'green', Kurang: 'amber', 'Belum dicek': 'slate' } as const

const chip = (active: boolean, activeClass: string) =>
  `rounded-md border px-4 py-2 text-sm font-medium transition ${
    active ? `${activeClass} border-transparent text-white` : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
  }`

const requestUrl = (roomId: number, s?: RoomStandard) => {
  const p = new URLSearchParams({ room_id: String(roomId) })
  if (s?.fulfillment?.type === 'minta_ke') p.set('catalog_id', String(s.fulfillment.catalog.id))
  if (s?.shortage) p.set('quantity', String(s.shortage))
  return `/permintaan?${p}`
}

export default function RoomsPage() {
  const { building: buildingSlug, room: roomSlug } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const [buildings, setBuildings] = useState<ReadinessBuilding[] | null>(null)
  const [checklist, setChecklist] = useState<Checklist | null>(null)
  const [actual, setActual] = useState<Record<number, string>>({})
  const [saving, setSaving] = useState(false)
  const [filling, setFilling] = useState<number | null>(null)
  const [modal, setModal] = useState<'standards' | 'building' | 'room' | null>(null)

  const loadReadiness = useCallback(
    () =>
      api
        .get<{ data: ReadinessBuilding[] }>('/room-readiness')
        .then((res) => setBuildings(res.data.data))
        .catch((e) => toast.error(errorMessage(e))),
    [toast],
  )

  useEffect(() => {
    loadReadiness()
  }, [loadReadiness])

  // Slug URL → id dari data yang sudah dimuat (API tetap memakai id).
  const building = buildings?.find((b) => b.slug === buildingSlug)
  const room = building?.rooms.find((r) => r.slug === roomSlug)
  const roomId = room?.id

  const loadChecklist = useCallback(
    (id: number | undefined) => {
      if (!id) return Promise.resolve()
      return api
        .get<Checklist>(`/rooms/${id}/checklist`)
        .then((res) => {
          setChecklist(res.data)
          setActual(
            Object.fromEntries(
              res.data.data.filter((s) => s.count_method === 'Manual').map((s) => [s.id, s.present_quantity?.toString() ?? '']),
            ),
          )
        })
        .catch((e) => toast.error(errorMessage(e)))
    },
    [toast],
  )

  useEffect(() => {
    loadChecklist(roomId)
  }, [loadChecklist, roomId])

  const reloadAll = () => Promise.all([loadChecklist(roomId), loadReadiness()])

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

  if (!buildings) return <p className="text-slate-500">Memuat…</p>

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

  // Kalimat banner: "Kekurangan dapat diambil dari X, diisi dari stok SHD, atau diminta ke unit penyedia."
  const ways = [
    ...new Set(
      shortages.map((s) =>
        s.fulfillment?.type === 'ambil_dari'
          ? `diambil dari ${s.fulfillment.note}`
          : s.fulfillment?.type === 'stok_shd'
            ? 'diisi dari stok SHD'
            : s.fulfillment?.type === 'minta_ke'
              ? 'diminta ke unit penyedia'
              : null,
      ),
    ),
  ].filter(Boolean) as string[]
  const waysText = ways.length ? ` Kekurangan dapat ${ways.slice(0, -1).join(', ')}${ways.length > 1 ? ' atau ' : ''}${ways.at(-1)}.` : ''
  const hasRequestable = shortages.some((s) => s.fulfillment?.type === 'minta_ke' || !s.source_department?.is_internal)

  const columns: Column<RoomStandard>[] = [
    {
      header: 'Perlengkapan standar',
      cell: (s) => (
        <>
          {s.item_name}
          <span className="block text-xs text-slate-500">
            {s.count_method === 'QR' ? `Otomatis QR · ${s.asset_type?.name ?? '-'}` : 'Dicek manual'}
          </span>
        </>
      ),
    },
    {
      header: 'Sumber',
      cell: (s) => (s.source_department ? (s.source_department.is_internal ? `Milik ${s.source_department.name}` : s.source_department.name) : '-'),
    },
    { header: 'Standar', cell: (s) => s.standard_quantity },
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
            className="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
          />
        ) : (
          s.present_quantity
        ),
    },
    {
      header: 'Selisih',
      cell: (s) =>
        s.difference === null ? (
          <span className="text-slate-400">—</span>
        ) : (
          <span className={s.difference < 0 ? 'font-semibold text-amber-700' : ''}>{s.difference}</span>
        ),
    },
    { header: 'Hasil', cell: (s) => <Badge color={resultColor[s.result]}>{s.result}</Badge> },
    {
      header: 'Ambil dari / Minta ke',
      cell: (s) => {
        const f = s.fulfillment
        const text =
          f?.type === 'stok_shd'
            ? `Stok SHD: ${f.consumable.name} (${f.consumable.quantity} ${f.consumable.unit})`
            : f?.type === 'ambil_dari'
              ? f.note
              : f?.type === 'minta_ke'
                ? `${f.catalog.provider_department?.name ?? 'Unit lain'}`
                : '-'
        return (
          <div className="space-y-1">
            <span className="block text-xs text-slate-600">{text}</span>
            {s.can_fill_from_stock && (
              <Button variant="secondary" className="px-2! py-1! text-xs!" loading={filling === s.id} onClick={() => fillFromStock(s)}>
                Isi dari stok
              </Button>
            )}
            {s.result === 'Kurang' && f?.type === 'minta_ke' && roomId && (
              <Link to={requestUrl(roomId, s)} className="block text-xs text-brand-700 underline">Buat permintaan</Link>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <div className="max-w-7xl space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Kelengkapan Ruangan</h1>
          <p className="mt-1 text-sm text-slate-600">
            Membandingkan isi ruangan terhadap standar perlengkapan default · {totalRooms} ruangan di {buildings.length} gedung
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ExportButton type="rooms" filters={() => ({ building_id: building?.id })} />
          <Button variant="secondary" disabled={!roomId} onClick={() => setModal('standards')}>Atur standar ruangan</Button>
          <Button loading={saving} disabled={!roomId || manualRows.length === 0} onClick={saveCheck}>Simpan hasil cek</Button>
        </div>
      </div>

      <section className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Gedung</p>
          <div className="flex flex-wrap gap-2">
            {buildings.map((b) => (
              <button key={b.id} type="button" className={chip(b.id === building?.id, 'bg-slate-900')}
                onClick={() => navigate(b.rooms[0] ? `${BASE}/${b.slug}/${b.rooms[0].slug}` : `${BASE}/${b.slug}`)}>
                {b.name}
              </button>
            ))}
            <button type="button" className={chip(false, '')} onClick={() => setModal('building')} aria-label="Tambah gedung">
              <Plus size={16} />
            </button>
          </div>
        </div>
        {building && (
          <div className="border-t border-slate-100 pt-4">
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Ruangan di {building.name}</p>
            <div className="flex flex-wrap gap-2">
              {building.rooms.map((r) => (
                <button key={r.id} type="button" className={chip(r.id === roomId, 'bg-brand-700')}
                  onClick={() => navigate(`${BASE}/${building.slug}/${r.slug}`)}
                  title={r.shortage_items ? `Kurang ${r.shortage_items} item` : r.total_items ? 'Lengkap' : 'Belum ada standar'}>
                  {r.name}
                  {r.shortage_items > 0 && (
                    <span className={`ml-2 rounded-full px-1.5 text-xs ${r.id === roomId ? 'bg-white/20' : 'bg-amber-100 text-amber-800'}`}>
                      −{r.shortage_items}
                    </span>
                  )}
                </button>
              ))}
              <button type="button" className={chip(false, '')} onClick={() => setModal('room')} aria-label="Tambah ruangan">
                <Plus size={16} />
              </button>
            </div>
          </div>
        )}
      </section>

      {room && shortages.length > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
          <p>
            <b>{room.name} kurang {shortages.length} item.</b>
            {waysText}
          </p>
          {hasRequestable && roomId && (
            <Link to={requestUrl(roomId)} className="rounded-md bg-amber-800 px-4 py-2 font-medium text-white hover:bg-amber-900">
              Buat permintaan
            </Link>
          )}
        </div>
      )}

      {room && (
        <section className="rounded-lg border border-slate-200 bg-white p-5">
          <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
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
            <p className="mt-3 text-xs text-slate-500">
              Isi kolom Ada untuk baris manual, lalu klik “Simpan hasil cek”. Baris QR dihitung otomatis dari aset di ruangan ini.
            </p>
          )}
        </section>
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
            await loadReadiness()
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
            await loadReadiness()
            setModal(null)
            navigate(`${BASE}/${building.slug}/${r.slug}`)
          }}
        />
      )}
    </div>
  )
}
