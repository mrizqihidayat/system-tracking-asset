import { Plus, Search, SlidersHorizontal } from 'lucide-react'
import { useState } from 'react'
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
import { useBuildings, useCategories, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import { STATUSES, type AssetListItem } from '../../types/asset'

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
  const someOnPage = !allOnPage && rows.some((a) => selected.has(a.id))
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
      // HP: kotak centang di pojok kanan atas kartu, posisi sama di semua kartu; lencana status di sebelah kirinya (T45, UAT M1).
      mobile: 'corner',
      headerContent: (
        <input
          type="checkbox"
          checked={allOnPage}
          ref={(el) => {
            if (el) el.indeterminate = someOnPage
          }}
          disabled={rows.length === 0}
          onChange={togglePage}
          aria-label={allOnPage ? 'Batal pilih semua di halaman ini' : 'Pilih semua di halaman ini'}
          title={allOnPage ? 'Batal pilih semua di halaman ini' : 'Pilih semua di halaman ini'}
        />
      ),
      // Tampilan kotak tetap 16 px; label berpadding + margin negatif memperluas area sentuh menjadi 44 px di HP tanpa menggeser tata letak.
      cell: (a) => (
        <label className="-m-3.5 inline-flex cursor-pointer p-3.5 lg:m-0 lg:p-0">
          <input type="checkbox" checked={selected.has(a.id)} onChange={() => toggle(a.id)} aria-label={`Pilih ${a.code}`} />
        </label>
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
          <span className="whitespace-nowrap">
            {a.room_name}
            <span className="text-slate-500"> · {a.building_name}</span>
          </span>
        ) : (
          '-'
        ),
    },
    { header: 'Sumber', cell: (a) => a.source_name ?? '-' },
    { header: 'Kondisi', sortKey: 'condition', cell: (a) => <span className="whitespace-nowrap">{a.condition}</span> },
    { header: 'Status', mobile: 'badge', sortKey: 'status', cell: (a) => <StatusBadge status={a.status} /> },
    {
      header: 'Detail',
      className: 'text-right',
      mobile: 'footer',
      // HP: tombol memenuhi lebar kartu; laptop: tautan biasa di ujung kanan tabel.
      footerClassName: 'w-full',
      cell: (a) => (
        <Link
          to={`/daftar-aset/${a.id}`}
          className="font-semibold text-brand-700 hover:underline max-lg:flex max-lg:min-h-9 max-lg:items-center max-lg:justify-center max-lg:rounded-lg max-lg:bg-brand-50 max-lg:hover:bg-brand-100 max-lg:hover:no-underline"
        >
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

  return (
    <div className="space-y-4">
      <PageHeader
        title="Daftar Aset"
        description={subtitle}
        actions={
          // HP: Ekspor & Cetak berdampingan memenuhi lebar, Tambah aset satu baris penuh di bawahnya.
          <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
            <ExportButton
              type="assets"
              filters={() => new URLSearchParams(FILTERS.flatMap((k) => (list.get(k) ? [[k, list.get(k)]] : [])))}
              className="min-w-0 px-3 sm:px-4"
            />
            <Button
              variant="secondary"
              className="min-w-0 px-3 sm:px-4"
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
            <Link to="/daftar-aset/baru" className={buttonClass('primary', 'col-span-2')}>
              <Plus size={16} /> Tambah aset
            </Link>
          </div>
        }
      />

      <div className="rounded-xl border border-slate-200 bg-white p-3">
        <div className="flex flex-col gap-2 xl:flex-row xl:items-center">
          <div className="flex gap-2 xl:min-w-56 xl:flex-1">
            <div className="relative flex-1">
              <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <Input
                placeholder="Nama, kode, atau nomor seri"
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
          {/* Tanpa label di atas: teks "Semua …" menjadi labelnya, nama filter tetap dibacakan lewat aria-label. */}
          <div className={`${filtersOpen ? 'grid' : 'hidden'} grid-cols-2 gap-2 sm:grid-cols-3 lg:grid lg:grid-cols-5 xl:flex xl:items-center`}>
            <Select clearable className="xl:w-40" aria-label="Kategori" value={list.get('category_id')} onChange={(e) => setFilter('category_id', e.target.value)}>
              <option value="">Semua kategori</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </Select>
            <Select clearable className="xl:w-40" aria-label="Gedung" value={buildingId} onChange={(e) => setFilter('building_id', e.target.value)}>
              <option value="">Semua gedung</option>
              {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </Select>
            <Select clearable className="xl:w-40" aria-label="Ruangan" value={list.get('room_id')} disabled={!buildingId} onChange={(e) => setFilter('room_id', e.target.value)}>
              <option value="">Semua ruangan</option>
              {buildingId && rooms.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </Select>
            <Select clearable className="xl:w-40" aria-label="Status" value={list.get('status')} onChange={(e) => setFilter('status', e.target.value)}>
              <option value="">Semua status</option>
              {STATUSES.map((st) => <option key={st}>{st}</option>)}
            </Select>
            <Button variant="ghost" className="col-span-2 px-3! sm:col-span-1" disabled={!hasFilter} onClick={list.reset}>
              Reset
            </Button>
          </div>
        </div>
      </div>

      {/* Tabel lebar: pilih semua lewat kotak centang di judul kolom; baris ini hanya muncul saat ada pilihan.
          HP (kartu, tanpa judul kolom): tautan pilih semua tetap tampil. */}
      <div className={`${selected.size > 0 ? 'flex' : 'flex lg:hidden'} flex-wrap items-center gap-x-4 gap-y-1 text-sm`}>
        <button
          className="font-semibold text-brand-700 hover:underline disabled:text-slate-500 disabled:no-underline tap lg:hidden"
          disabled={rows.length === 0}
          onClick={togglePage}
        >
          {allOnPage ? 'Batal pilih halaman ini' : 'Pilih semua di halaman ini'}
        </button>
        {selected.size > 0 && (
          <button className="tap font-semibold text-brand-700 hover:underline" onClick={() => setSelected(new Set())}>
            Kosongkan pilihan ({selected.size})
          </button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(a) => a.id}
        loading={assets.isPending}
        error={assets.error}
        onRetry={() => assets.refetch()}
        retrying={assets.isFetching}
        fetching={assets.isPlaceholderData}
        unit="aset"
        emptyText={assets.isError ? 'Gagal memuat daftar aset. Coba lagi.' : hasFilter ? 'Tidak ada aset yang cocok dengan filter.' : 'Belum ada aset.'}
        meta={result?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        onSortChange={list.setSort}
        dense
        fixedHeight
      />
    </div>
  )
}
