import { ChevronDown, ChevronRight, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { AssetCode, Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { PageHeader } from '../../components/ui/Page'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { skeletonWidth } from '../../components/ui/skeleton-width'
import { invalidate } from '../../lib/queries/invalidate'
import { useAssetTypes, useBuildings, useCategories, useConsumableCategories, useDepartments, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import type { AssetType, Building, Category, Department, Room } from '../../types/asset'
import type { ConsumableCategory } from '../../types/supply'
import { getUser } from '../../lib/auth'
import AccountsTab from './AccountsTab'
import DepartmentsTab from './DepartmentsTab'
import { MasterDeleteModal, MasterFormModal, type MasterTarget } from './MasterModals'

type Tab = 'gedung' | 'departemen' | 'jenis' | 'kategori-stok' | 'akun'

const TABS: { key: Tab; label: string }[] = [
  { key: 'gedung', label: 'Gedung & Ruangan' },
  { key: 'departemen', label: 'Departemen' },
  { key: 'jenis', label: 'Kategori & Jenis aset' },
  { key: 'kategori-stok', label: 'Kategori stok' },
  { key: 'akun', label: 'Akun' },
]

type FormState = {
  kind: string
  target?: MasterTarget
  createUrl?: string
  withCode?: boolean
  withStorage?: boolean
  categoryId?: number
}

interface Data {
  buildings: Building[]
  rooms: Room[]
  departments: Department[]
  categories: Category[]
  types: AssetType[]
  consumableCategories: ConsumableCategory[]
}

function RowActions({ onEdit, onDelete }: { onEdit?: () => void; onDelete?: () => void }) {
  return (
    <div className="flex shrink-0 gap-3 text-sm">
      {onEdit && <button className="tap font-semibold text-brand-700 hover:underline" onClick={onEdit}>Ubah</button>}
      {onDelete && <button className="tap font-semibold text-bad-700 hover:underline" onClick={onDelete}>Hapus</button>}
    </div>
  )
}

/** Gedung yang bisa dibuka untuk melihat ruangannya. */
function Group({ title, meta, open, onToggle, actions, children }: {
  title: string
  meta: string
  open: boolean
  onToggle: () => void
  actions: ReactNode
  children: ReactNode
}) {
  return (
    <li className="py-1">
      <div className="flex items-center justify-between gap-3 py-2">
        <button className="flex min-h-10 min-w-0 items-center gap-2 text-left" onClick={onToggle} aria-expanded={open}>
          {open ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          <span className="font-medium">{title}</span>
          <span className="text-xs text-slate-500">{meta}</span>
        </button>
        {actions}
      </div>
      {open && <div className="mb-2 ml-6 rounded-lg bg-slate-50 px-3">{children}</div>}
    </li>
  )
}

export default function MasterDataPage() {
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  // Tab Akun hanya untuk akun pegawai SHD; akun bersama tidak melihatnya (API juga menolak 403).
  const tabs = getUser()?.source === 'api' ? TABS : TABS.filter((t) => t.key !== 'akun')
  const tab = (tabs.some((t) => t.key === params.get('tab')) ? params.get('tab') : 'gedung') as Tab
  const queries = [useBuildings(), useRooms(), useDepartments(), useCategories(), useAssetTypes(), useConsumableCategories()] as const
  useErrorToast(...queries.map((q) => q.error))
  const [buildings, rooms, departments, categories, types, consumableCategories] = queries
  const data: Data | null = queries.every((q) => q.isSuccess)
    ? {
        buildings: buildings.data, rooms: rooms.data, departments: departments.data,
        categories: categories.data, types: types.data, consumableCategories: consumableCategories.data,
      }
    : null
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const [form, setForm] = useState<FormState | null>(null)
  const [deleting, setDeleting] = useState<MasterTarget | null>(null)

  const toggle = (key: string) => setOpen((o) => ({ ...o, [key]: !o[key] }))
  const done = () => {
    setForm(null)
    setDeleting(null)
    invalidate.master(queryClient)
  }
  const closeDelete = useCallback(() => setDeleting(null), [])

  const childRow = (name: string, extra: ReactNode, onEdit: () => void, onDelete: () => void, key: number) => (
    <li key={key} className="flex items-center justify-between gap-3 py-2 text-sm">
      <span className="min-w-0 truncate">{name} {extra}</span>
      <RowActions onEdit={onEdit} onDelete={onDelete} />
    </li>
  )

  /** `short`: label ringkas di HP (dua tombol berdampingan tetap muat di kartu). */
  const addButton = (label: string, onClick: () => void, short?: string) => (
    <Button variant="secondary" onClick={onClick} className={`min-h-9! w-full sm:w-auto ${short ? 'px-3 sm:px-4' : ''}`}>
      <Plus size={16} />
      {short ? (
        <>
          <span className="sm:hidden">{short}</span>
          <span className="hidden sm:inline">{label}</span>
        </>
      ) : (
        label
      )}
    </Button>
  )

  let body: ReactNode = (
    <ul className="divide-y divide-slate-100" aria-busy="true">
      <LoadingLabel />
      {Array.from({ length: 5 }, (_, i) => (
        <li key={i} className="flex items-center justify-between gap-3 py-3.5">
          <Skeleton className={`h-4 ${skeletonWidth(i)} max-w-64`} />
          <Skeleton className="h-4 w-20" />
        </li>
      ))}
    </ul>
  )

  if (data && tab === 'gedung') {
    body = (
      <>
        <div className="mb-2 flex justify-end">{addButton('Tambah gedung', () => setForm({ kind: 'Gedung', createUrl: '/buildings' }))}</div>
        {data.buildings.length === 0 && <p className="text-sm text-slate-500">Belum ada gedung.</p>}
        <ul className="divide-y divide-slate-100">
          {data.buildings.map((b) => {
            const rooms = data.rooms.filter((r) => r.building_id === b.id)
            const target: MasterTarget = { kind: 'Gedung', name: b.name, url: `/buildings/${b.id}` }
            return (
              <Group
                key={b.id}
                title={b.name}
                meta={`${rooms.length} ruangan`}
                open={!!open[`b${b.id}`]}
                onToggle={() => toggle(`b${b.id}`)}
                actions={<RowActions onEdit={() => setForm({ kind: 'Gedung', target })} onDelete={() => setDeleting(target)} />}
              >
                <ul className="divide-y divide-slate-200">
                  {rooms.map((r) => {
                    const t: MasterTarget = { kind: 'Ruangan', name: r.name, url: `/rooms/${r.id}`, isStorage: r.is_storage }
                    return childRow(r.name, r.is_storage ? <Badge color="blue">Gudang</Badge> : null, () => setForm({ kind: 'Ruangan', target: t, withStorage: true }), () => setDeleting(t), r.id)
                  })}
                  <li className="py-2">
                    <button
                      className="flex min-h-9 items-center gap-1 text-sm font-semibold text-brand-700 hover:underline"
                      onClick={() => setForm({ kind: 'Ruangan', createUrl: `/buildings/${b.id}/rooms`, withStorage: true })}
                    >
                      <Plus size={14} /> Tambah ruangan di {b.name}
                    </button>
                  </li>
                </ul>
              </Group>
            )
          })}
        </ul>
      </>
    )
  }

  if (data && tab === 'departemen') {
    body = (
      <DepartmentsTab
        departments={data.departments}
        onEdit={(d) => setForm({ kind: 'Departemen', target: { kind: 'Departemen', name: d.name, url: `/departments/${d.id}` } })}
      />
    )
  }

  if (data && tab === 'jenis') {
    const typeForm = (extra: Partial<FormState>) => setForm({ kind: 'Jenis aset', withCode: true, ...extra })
    body = (
      <>
        <div className="mb-2 grid grid-cols-2 gap-2 sm:flex sm:justify-end">
          {addButton('Tambah kategori', () => setForm({ kind: 'Kategori', createUrl: '/categories' }), 'Kategori')}
          {data.categories.length > 0 && addButton('Tambah jenis aset', () => typeForm({ createUrl: '/asset-types' }), 'Jenis aset')}
        </div>
        {data.categories.length === 0 && <p className="text-sm text-slate-500">Belum ada kategori. Tambah kategori dulu, lalu jenis asetnya.</p>}
        <ul className="divide-y divide-slate-100">
          {data.categories.map((c) => {
            const types = data.types.filter((t) => t.category_id === c.id)
            const target: MasterTarget = { kind: 'Kategori', name: c.name, url: `/categories/${c.id}` }
            return (
              <Group
                key={c.id}
                title={c.name}
                meta={`${types.length} jenis`}
                open={!!open[`c${c.id}`]}
                onToggle={() => toggle(`c${c.id}`)}
                actions={<RowActions onEdit={() => setForm({ kind: 'Kategori', target })} onDelete={() => setDeleting(target)} />}
              >
                <ul className="divide-y divide-slate-200">
                  {types.map((t) => {
                    const tt: MasterTarget = { kind: 'Jenis aset', name: t.name, url: `/asset-types/${t.id}`, code: t.code, categoryId: t.category_id }
                    return childRow(t.name, <AssetCode code={t.code} />, () => typeForm({ target: tt }), () => setDeleting(tt), t.id)
                  })}
                  <li className="py-2">
                    <button
                      className="flex min-h-9 items-center gap-1 text-sm font-semibold text-brand-700 hover:underline"
                      onClick={() => typeForm({ createUrl: '/asset-types', categoryId: c.id })}
                    >
                      <Plus size={14} /> Tambah jenis di {c.name}
                    </button>
                  </li>
                </ul>
              </Group>
            )
          })}
        </ul>
      </>
    )
  }

  if (data && tab === 'kategori-stok') {
    body = (
      <>
        <div className="mb-2 flex justify-end">{addButton('Tambah kategori stok', () => setForm({ kind: 'Kategori stok', createUrl: '/consumable-categories' }))}</div>
        {data.consumableCategories.length === 0 && <p className="text-sm text-slate-500">Belum ada kategori stok.</p>}
        <ul className="divide-y divide-slate-100">
          {data.consumableCategories.map((c) => {
            const target: MasterTarget = { kind: 'Kategori stok', name: c.name, url: `/consumable-categories/${c.id}` }
            return (
              <li key={c.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                <span className="flex min-w-0 items-center gap-2">
                  <span className="font-medium">{c.name}</span>
                  <span className="text-xs text-slate-500">{c.consumables_count ?? 0} barang</span>
                </span>
                <RowActions onEdit={() => setForm({ kind: 'Kategori stok', target })} onDelete={() => setDeleting(target)} />
              </li>
            )
          })}
        </ul>
      </>
    )
  }

  if (tab === 'akun') body = <AccountsTab />

  return (
    <div className="max-w-4xl space-y-5">
      <PageHeader
        title="Data Master"
        description="Kelola gedung, ruangan, departemen, kategori dan jenis aset, kategori stok, serta akun pengguna. Data yang masih digunakan tidak dapat dihapus."
      />

      <div className="flex gap-1 overflow-x-auto border-b border-slate-200 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
        {tabs.map((t) => (
          <button
            key={t.key}
            role="tab"
            aria-selected={tab === t.key}
            onClick={() => setParams({ tab: t.key }, { replace: true })}
            className={`-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors ${
              tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-900'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <section className="rounded-xl border border-slate-200 bg-white p-5">{body}</section>

      {form && (
        <MasterFormModal
          {...form}
          categories={form.kind === 'Jenis aset' ? data?.categories : undefined}
          onClose={() => setForm(null)}
          onDone={done}
        />
      )}
      {deleting && <MasterDeleteModal target={deleting} onClose={closeDelete} onDone={done} />}
    </div>
  )
}
