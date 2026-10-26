import { useEffect, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import { Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import { fetchBuildings, fetchCategories, fetchDepartments, fetchRooms } from '../../lib/lookups'
import type { Paginated } from '../../types/api'
import { CONDITIONS, STATUSES, type Asset, type Building, type Category, type Department, type Room } from '../../types/asset'

const FILTERS = ['category_id', 'building_id', 'room_id', 'source_department_id', 'condition', 'status', 'search'] as const
const MAX_LABELS = 120

export default function AssetListPage() {
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const [result, setResult] = useState<Paginated<Asset> | null>(null)
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [printing, setPrinting] = useState(false)
  const [searchText, setSearchText] = useState(params.get('search') ?? '')

  const [categories, setCategories] = useState<Category[]>([])
  const [buildings, setBuildings] = useState<Building[]>([])
  const [departments, setDepartments] = useState<Department[]>([])
  const [rooms, setRooms] = useState<Room[]>([])

  const buildingId = params.get('building_id')
  const query = params.toString()

  useEffect(() => {
    Promise.all([fetchCategories(), fetchBuildings(), fetchDepartments()])
      .then(([c, b, d]) => {
        setCategories(c)
        setBuildings(b)
        setDepartments(d)
      })
      .catch((e) => toast.error(errorMessage(e)))
  }, [toast])

  useEffect(() => {
    if (!buildingId) return
    fetchRooms(Number(buildingId))
      .then(setRooms)
      .catch((e) => toast.error(errorMessage(e)))
  }, [buildingId, toast])

  useEffect(() => {
    let cancelled = false
    api
      .get<Paginated<Asset>>('/assets', { params: new URLSearchParams(query) })
      .then((res) => !cancelled && setResult(res.data))
      .catch((e) => !cancelled && toast.error(errorMessage(e)))
      .finally(() => !cancelled && setLoading(false))
    return () => {
      cancelled = true
    }
  }, [query, toast])

  // Pencarian ditunda agar tidak memanggil API di setiap ketikan.
  useEffect(() => {
    const t = setTimeout(() => {
      if (searchText === (params.get('search') ?? '')) return
      setFilter('search', searchText)
    }, 400)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchText])

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key === 'building_id') next.delete('room_id')
    next.delete('page')
    setLoading(true)
    setParams(next, { replace: true })
  }

  const resetFilters = () => {
    setSearchText('')
    setLoading(true)
    setParams({}, { replace: true })
  }

  const toggle = (id: number) =>
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })

  const rows = result?.data ?? []
  const allOnPage = rows.length > 0 && rows.every((a) => selected.has(a.id))
  const togglePage = () =>
    setSelected((prev) => {
      const next = new Set(prev)
      rows.forEach((a) => (allOnPage ? next.delete(a.id) : next.add(a.id)))
      return next
    })

  const printLabels = async () => {
    setPrinting(true)
    try {
      await openInNewTab(() => api.post('/assets/labels', { ids: [...selected] }, { responseType: 'blob' }))
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setPrinting(false)
    }
  }

  const columns: Column<Asset>[] = [
    {
      header: 'Pilih',
      className: 'w-10',
      cell: (a) => (
        <input type="checkbox" checked={selected.has(a.id)} onChange={() => toggle(a.id)} aria-label={`Pilih ${a.code}`} />
      ),
    },
    { header: 'Kode', cell: (a) => <span className="font-mono">{a.code}</span> },
    { header: 'Nama aset', cell: (a) => a.name },
    { header: 'Kategori', cell: (a) => a.asset_type?.category?.name ?? '-' },
    {
      header: 'Lokasi',
      cell: (a) =>
        a.room ? (
          <>
            {a.room.name}
            <span className="block text-xs text-slate-500">{a.room.building?.name}</span>
          </>
        ) : (
          '-'
        ),
    },
    { header: 'Sumber', cell: (a) => (a.source_department?.is_internal ? 'Milik SHD' : 'Unit lain') },
    { header: 'Kondisi', cell: (a) => a.condition },
    { header: 'Status', cell: (a) => <StatusBadge status={a.status} /> },
    {
      header: 'Aksi',
      className: 'text-right',
      cell: (a) => (
        <Link to={`/daftar-aset/${a.id}`} className="text-brand-700 underline">
          Detail
        </Link>
      ),
    },
  ]

  const hasFilter = FILTERS.some((k) => params.get(k))
  const total = result?.meta.total
  const subtitle =
    total === undefined ? ' ' : hasFilter ? `${total} aset cocok dengan filter` : `${total} aset tercatat`

  const field = (label: string, control: ReactNode, className = '') => (
    <label className={`block space-y-1 ${className}`}>
      <span className="text-xs text-slate-500">{label}</span>
      {control}
    </label>
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Daftar Aset</h1>
          <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ExportButton
            type="assets"
            filters={() => new URLSearchParams(FILTERS.flatMap((k) => (params.get(k) ? [[k, params.get(k)!]] : [])))}
          />
          <Button
            variant="secondary"
            disabled={selected.size === 0 || selected.size > MAX_LABELS}
            loading={printing}
            onClick={printLabels}
            title={
              selected.size === 0
                ? 'Centang aset yang labelnya ingin dicetak'
                : selected.size > MAX_LABELS
                  ? `Maksimal ${MAX_LABELS} label per cetak`
                  : undefined
            }
          >
            Cetak label QR{selected.size > 0 ? ` (${selected.size})` : ''}
          </Button>
          <Link
            to="/daftar-aset/baru"
            className="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
          >
            Tambah aset
          </Link>
        </div>
      </div>

      <div className="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        {field(
          'Cari aset',
          <Input placeholder="Nama atau kode aset" value={searchText} onChange={(e) => setSearchText(e.target.value)} />,
          'lg:col-span-3',
        )}
        {field(
          'Kategori',
          <Select value={params.get('category_id') ?? ''} onChange={(e) => setFilter('category_id', e.target.value)}>
            <option value="">Semua kategori</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>,
        )}
        {field(
          'Gedung',
          <Select value={buildingId ?? ''} onChange={(e) => setFilter('building_id', e.target.value)}>
            <option value="">Semua gedung</option>
            {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </Select>,
        )}
        {field(
          'Ruangan',
          <Select value={params.get('room_id') ?? ''} disabled={!buildingId} onChange={(e) => setFilter('room_id', e.target.value)}>
            <option value="">Semua ruangan</option>
            {buildingId && rooms.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </Select>,
        )}
        {field(
          'Status',
          <Select value={params.get('status') ?? ''} onChange={(e) => setFilter('status', e.target.value)}>
            <option value="">Semua status</option>
            {STATUSES.map((s) => <option key={s}>{s}</option>)}
          </Select>,
          'lg:col-span-2',
        )}
        {field(
          'Sumber',
          <Select value={params.get('source_department_id') ?? ''} onChange={(e) => setFilter('source_department_id', e.target.value)}>
            <option value="">Semua sumber</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>{d.is_internal ? `${d.name} (Milik SHD)` : d.name}</option>
            ))}
          </Select>,
          'lg:col-span-2',
        )}
        {field(
          'Kondisi',
          <Select value={params.get('condition') ?? ''} onChange={(e) => setFilter('condition', e.target.value)}>
            <option value="">Semua kondisi</option>
            {CONDITIONS.map((c) => <option key={c}>{c}</option>)}
          </Select>,
        )}
        <div className="flex items-end">
          <Button variant="secondary" className="w-full" disabled={!hasFilter} onClick={resetFilters}>
            Reset
          </Button>
        </div>
      </div>

      <div className="flex items-center gap-3 text-sm text-slate-600">
        <button
          className="text-brand-700 underline disabled:text-slate-400 disabled:no-underline"
          disabled={rows.length === 0}
          onClick={togglePage}
        >
          {allOnPage ? 'Batal pilih halaman ini' : 'Pilih semua di halaman ini'}
        </button>
        {selected.size > 0 && (
          <button className="text-brand-700 underline" onClick={() => setSelected(new Set())}>
            Kosongkan pilihan ({selected.size})
          </button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(a) => a.id}
        loading={loading}
        unit="aset"
        emptyText={hasFilter ? 'Tidak ada aset yang cocok dengan filter.' : 'Belum ada aset.'}
        meta={result?.meta}
        onPageChange={(p) => {
          const next = new URLSearchParams(params)
          next.set('page', String(p))
          setLoading(true)
          setParams(next)
        }}
      />
    </div>
  )
}
