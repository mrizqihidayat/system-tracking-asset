import { Plus, Search, SlidersHorizontal } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { AssetCode, StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
import DataTable, { type Column } from '../../components/ui/DataTable'
import { Input, Select } from '../../components/ui/FormField'
import { PageHeader } from '../../components/ui/Page'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import { listConfigs } from '../../lib/queries/list-configs'
import { useAssets } from '../../lib/queries/lists'
import { useBuildings, useCategories, useDepartments, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import { CONDITIONS, STATUSES, type AssetListItem } from '../../types/asset'

const FILTERS = [...listConfigs.assets.filters, 'search']
const MAX_LABELS = 120

export default function AssetListPage() {
  const toast = useToast()
  const list = useListParams(listConfigs.assets)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [printing, setPrinting] = useState(false)
  const [filtersOpen, setFiltersOpen] = useState(false)

  const buildingId = list.get('building_id')
  const assets = useAssets(list.params)
  const { data: categories } = useCategories()
  const { data: buildings } = useBuildings()
  const { data: departments } = useDepartments()
  const { data: rooms } = useRooms(buildingId ? Number(buildingId) : null, true)
  useErrorToast(assets.error)
  const result = assets.data

  const setFilter = (key: string, value: string) =>
    list.setFilter(key, value, key === 'building_id' ? { room_id: null } : {})

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

  const columns: Column<AssetListItem>[] = [
    {
      header: 'Pilih',
      className: 'w-10',
      mobile: 'badge',
      cell: (a) => (
        <input type="checkbox" checked={selected.has(a.id)} onChange={() => toggle(a.id)} aria-label={`Pilih ${a.code}`} />
      ),
    },
    { header: 'Kode', mobile: 'title', sortKey: 'code', cell: (a) => <AssetCode code={a.code} /> },
    {
      header: 'Nama aset',
      mobile: 'title',
      sortKey: 'name',
      cell: (a) => (
        <Link to={`/daftar-aset/${a.id}`} className="font-medium hover:text-brand-700 hover:underline">
          {a.name}
        </Link>
      ),
    },
    { header: 'Kategori', cell: (a) => a.category_name ?? '-' },
    {
      header: 'Lokasi',
      cell: (a) =>
        a.room_name ? (
          <>
            {a.room_name}
            <span className="block text-xs text-slate-500">{a.building_name}</span>
          </>
        ) : (
          '-'
        ),
    },
    { header: 'Sumber', cell: (a) => (a.source_is_internal ? 'Milik SHD' : 'Unit lain') },
    { header: 'Kondisi', sortKey: 'condition', cell: (a) => <span className="whitespace-nowrap">{a.condition}</span> },
    { header: 'Status', mobile: 'badge', sortKey: 'status', cell: (a) => <StatusBadge status={a.status} /> },
    {
      header: 'Aksi',
      className: 'text-right',
      mobile: 'hidden',
      cell: (a) => (
        <Link to={`/daftar-aset/${a.id}`} className="font-semibold text-brand-700 hover:underline">
          Detail
        </Link>
      ),
    },
  ]
  const activeFilters = listConfigs.assets.filters.filter((k) => list.get(k)).length
  const hasFilter = list.hasFilter
  const total = result?.meta.total
  const subtitle =
    total === undefined ? ' ' : hasFilter ? `${total} aset cocok dengan filter` : `${total} aset tercatat`

  const field = (label: string, control: ReactNode, className = '') => (
    <label className={`block space-y-1.5 ${className}`}>
      <span className="block text-xs font-medium text-slate-500">{label}</span>
      {control}
    </label>
  )

  return (
    <div className="space-y-4">
      <PageHeader
        title="Daftar Aset"
        description={subtitle}
        actions={
          <>
            <ExportButton
              type="assets"
              filters={() => new URLSearchParams(FILTERS.flatMap((k) => (list.get(k) ? [[k, list.get(k)]] : [])))}
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
            <Link to="/daftar-aset/baru" className={buttonClass('primary')}>
              <Plus size={16} /> Tambah aset
            </Link>
          </>
        }
      />

      <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex gap-2">
          <div className="relative flex-1">
            <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <Input
              placeholder="Cari nama, kode, atau nomor seri"
              aria-label="Cari aset"
              value={list.searchText}
              onChange={(e) => list.setSearchText(e.target.value)}
              className="pl-9"
            />
          </div>
          <Button
            variant="secondary"
            className="lg:hidden"
            aria-expanded={filtersOpen}
            onClick={() => setFiltersOpen((v) => !v)}
          >
            <SlidersHorizontal size={16} /> Filter{activeFilters > 0 ? ` (${activeFilters})` : ''}
          </Button>
        </div>
        <div className={`${filtersOpen ? 'grid' : 'hidden'} gap-3 sm:grid-cols-2 lg:grid lg:grid-cols-7`}>
        {field(
          'Kategori',
          <Select value={list.get('category_id')} onChange={(e) => setFilter('category_id', e.target.value)}>
            <option value="">Semua kategori</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>,
        )}
        {field(
          'Gedung',
          <Select value={buildingId} onChange={(e) => setFilter('building_id', e.target.value)}>
            <option value="">Semua gedung</option>
            {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </Select>,
        )}
        {field(
          'Ruangan',
          <Select value={list.get('room_id')} disabled={!buildingId} onChange={(e) => setFilter('room_id', e.target.value)}>
            <option value="">Semua ruangan</option>
            {buildingId && rooms.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </Select>,
        )}
        {field(
          'Status',
          <Select value={list.get('status')} onChange={(e) => setFilter('status', e.target.value)}>
            <option value="">Semua status</option>
            {STATUSES.map((s) => <option key={s}>{s}</option>)}
          </Select>,
        )}
        {field(
          'Sumber',
          <Select value={list.get('source_department_id')} onChange={(e) => setFilter('source_department_id', e.target.value)}>
            <option value="">Semua sumber</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>{d.name}</option>
            ))}
          </Select>,
        )}
        {field(
          'Kondisi',
          <Select value={list.get('condition')} onChange={(e) => setFilter('condition', e.target.value)}>
            <option value="">Semua kondisi</option>
            {CONDITIONS.map((c) => <option key={c}>{c}</option>)}
          </Select>,
        )}
        <div className="flex items-end">
          <Button variant="ghost" className="w-full" disabled={!hasFilter} onClick={list.reset}>
            Reset filter
          </Button>
        </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <button
          className="font-semibold text-brand-700 hover:underline disabled:text-slate-500 disabled:no-underline"
          disabled={rows.length === 0}
          onClick={togglePage}
        >
          {allOnPage ? 'Batal pilih halaman ini' : 'Pilih semua di halaman ini'}
        </button>
        {selected.size > 0 && (
          <button className="font-semibold text-brand-700 hover:underline" onClick={() => setSelected(new Set())}>
            Kosongkan pilihan ({selected.size})
          </button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(a) => a.id}
        loading={assets.isPending}
        fetching={assets.isPlaceholderData}
        unit="aset"
        emptyText={assets.isError ? 'Gagal memuat daftar aset. Coba lagi.' : hasFilter ? 'Tidak ada aset yang cocok dengan filter.' : 'Belum ada aset.'}
        meta={result?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        onSortChange={list.setSort}
      />
    </div>
  )
}
