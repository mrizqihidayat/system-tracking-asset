import { ChevronDown, ChevronRight, Plus } from 'lucide-react'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { useToast } from '../../components/ui/toast-context'
import { errorMessage } from '../../lib/api'
import { fetchAssetTypes, fetchBuildings, fetchCategories, fetchDepartments, fetchRooms } from '../../lib/lookups'
import type { AssetType, Building, Category, Department, Room } from '../../types/asset'
import { MasterDeleteModal, MasterFormModal, type MasterTarget } from './MasterModals'

type Tab = 'gedung' | 'departemen' | 'kategori'

const TABS: { key: Tab; label: string }[] = [
  { key: 'gedung', label: 'Gedung & Ruangan' },
  { key: 'departemen', label: 'Departemen' },
  { key: 'kategori', label: 'Kategori & Jenis aset' },
]

type FormState = {
  kind: string
  target?: MasterTarget
  createUrl?: string
  extra?: Record<string, unknown>
  withCode?: boolean
}

interface Data {
  buildings: Building[]
  rooms: Room[]
  departments: Department[]
  categories: Category[]
  types: AssetType[]
}

function RowActions({ onEdit, onDelete }: { onEdit?: () => void; onDelete?: () => void }) {
  return (
    <div className="flex shrink-0 gap-3 text-sm">
      {onEdit && <button className="text-brand-700 underline" onClick={onEdit}>Ubah</button>}
      {onDelete && <button className="text-red-700 underline" onClick={onDelete}>Hapus</button>}
    </div>
  )
}

/** Induk (gedung/kategori) yang bisa dibuka untuk melihat anak (ruangan/jenis aset). */
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
        <button className="flex min-w-0 items-center gap-2 text-left" onClick={onToggle} aria-expanded={open}>
          {open ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          <span className="font-medium">{title}</span>
          <span className="text-xs text-slate-500">{meta}</span>
        </button>
        {actions}
      </div>
      {open && <div className="mb-2 ml-6 rounded-md border border-slate-100 bg-slate-50 px-3">{children}</div>}
    </li>
  )
}

export default function MasterDataPage() {
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const tab = (TABS.some((t) => t.key === params.get('tab')) ? params.get('tab') : 'gedung') as Tab
  const [data, setData] = useState<Data | null>(null)
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const [form, setForm] = useState<FormState | null>(null)
  const [deleting, setDeleting] = useState<MasterTarget | null>(null)

  const load = useCallback(
    () =>
      Promise.all([fetchBuildings(), fetchRooms(), fetchDepartments(), fetchCategories(), fetchAssetTypes()])
        .then(([buildings, rooms, departments, categories, types]) => setData({ buildings, rooms, departments, categories, types }))
        .catch((e) => toast.error(errorMessage(e))),
    [toast],
  )

  useEffect(() => {
    load()
  }, [load])

  const toggle = (key: string) => setOpen((o) => ({ ...o, [key]: !o[key] }))
  const done = () => {
    setForm(null)
    setDeleting(null)
    load()
  }
  const closeDelete = useCallback(() => setDeleting(null), [])

  const childRow = (name: string, extra: ReactNode, onEdit: () => void, onDelete: () => void, key: number) => (
    <li key={key} className="flex items-center justify-between gap-3 py-2 text-sm">
      <span className="min-w-0 truncate">{name} {extra}</span>
      <RowActions onEdit={onEdit} onDelete={onDelete} />
    </li>
  )

  const addButton = (label: string, onClick: () => void) => (
    <Button variant="secondary" className="px-3! py-1.5!" onClick={onClick}>
      <Plus size={14} /> {label}
    </Button>
  )

  let body: ReactNode = <p className="text-sm text-slate-500">Memuat…</p>

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
                    const t: MasterTarget = { kind: 'Ruangan', name: r.name, url: `/rooms/${r.id}` }
                    return childRow(r.name, null, () => setForm({ kind: 'Ruangan', target: t }), () => setDeleting(t), r.id)
                  })}
                  <li className="py-2">
                    <button
                      className="flex items-center gap-1 text-sm text-brand-700"
                      onClick={() => setForm({ kind: 'Ruangan', createUrl: `/buildings/${b.id}/rooms` })}
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
      <>
        <div className="mb-2 flex justify-end">{addButton('Tambah departemen', () => setForm({ kind: 'Departemen', createUrl: '/departments' }))}</div>
        <ul className="divide-y divide-slate-100">
          {data.departments.map((d) => {
            const target: MasterTarget = { kind: 'Departemen', name: d.name, url: `/departments/${d.id}` }
            return (
              <li key={d.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                <span className="flex items-center gap-2">
                  <span className="font-medium">{d.name}</span>
                  {d.is_internal && <Badge color="blue">Internal</Badge>}
                </span>
                {d.is_internal ? (
                  <span className="text-xs text-slate-400">Tidak dapat diubah</span>
                ) : (
                  <RowActions onEdit={() => setForm({ kind: 'Departemen', target })} onDelete={() => setDeleting(target)} />
                )}
              </li>
            )
          })}
        </ul>
      </>
    )
  }

  if (data && tab === 'kategori') {
    body = (
      <>
        <div className="mb-2 flex justify-end">{addButton('Tambah kategori', () => setForm({ kind: 'Kategori', createUrl: '/categories' }))}</div>
        {data.categories.length === 0 && <p className="text-sm text-slate-500">Belum ada kategori.</p>}
        <ul className="divide-y divide-slate-100">
          {data.categories.map((c) => {
            const types = data.types.filter((t) => t.category_id === c.id)
            const target: MasterTarget = { kind: 'Kategori', name: c.name, url: `/categories/${c.id}` }
            return (
              <Group
                key={c.id}
                title={c.name}
                meta={`${types.length} jenis aset`}
                open={!!open[`c${c.id}`]}
                onToggle={() => toggle(`c${c.id}`)}
                actions={<RowActions onEdit={() => setForm({ kind: 'Kategori', target })} onDelete={() => setDeleting(target)} />}
              >
                <ul className="divide-y divide-slate-200">
                  {types.map((t) => {
                    const tt: MasterTarget = { kind: 'Jenis aset', name: t.name, url: `/asset-types/${t.id}`, code: t.code }
                    return childRow(
                      t.name,
                      <span className="font-mono text-xs text-slate-500">({t.code})</span>,
                      () => setForm({ kind: 'Jenis aset', target: tt, withCode: true }),
                      () => setDeleting(tt),
                      t.id,
                    )
                  })}
                  <li className="py-2">
                    <button
                      className="flex items-center gap-1 text-sm text-brand-700"
                      onClick={() => setForm({ kind: 'Jenis aset', createUrl: '/asset-types', extra: { category_id: c.id }, withCode: true })}
                    >
                      <Plus size={14} /> Tambah jenis aset di {c.name}
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

  return (
    <div className="max-w-4xl space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Data Master</h1>
        <p className="mt-1 text-sm text-slate-600">
          Ganti nama atau hapus gedung, ruangan, departemen, kategori, dan jenis aset. Data yang masih dipakai tidak bisa dihapus.
        </p>
      </div>

      <div className="flex gap-1 border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setParams({ tab: t.key }, { replace: true })}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
              tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <section className="rounded-lg border border-slate-200 bg-white p-5">{body}</section>

      {form && <MasterFormModal {...form} onClose={() => setForm(null)} onDone={done} />}
      {deleting && <MasterDeleteModal target={deleting} onClose={closeDelete} onDone={done} />}
    </div>
  )
}
