import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, ChevronsUpDown } from 'lucide-react'
import type { ReactNode } from 'react'
import type { PaginationMeta } from '../../types/api'
import Button from './Button'
import { ListSkeletonItems, LoadingLabel, TableSkeletonRows } from './Skeleton'

export interface Column<T> {
  header: string
  cell: (row: T) => ReactNode
  className?: string
  /** Kelas tambahan khusus judul kolom (mis. judul di tengah, isi tetap rata kiri). */
  headerClassName?: string
  /**
   * Tampilan di layar sempit (< md), tempat tabel menjadi daftar kartu:
   * `title` = baris judul kartu (kiri atas), `badge` = pojok kanan atas,
   * `hidden` = tidak ditampilkan, kosong = baris "label: nilai".
   */
  mobile?: 'title' | 'badge' | 'hidden'
  /** Kunci sort server (lihat SPEC "Daftar (umum)"); kolom jadi bisa diklik bila tabel punya onSortChange. */
  sortKey?: string
}

interface Props<T> {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => string | number
  /** Muat pertama (belum ada data) → baris skeleton. */
  loading?: boolean
  /** Sedang memuat halaman/filter baru sementara data lama masih tampil → baris diredupkan. */
  fetching?: boolean
  emptyText?: string
  meta?: PaginationMeta
  /** Satuan di footer: "Menampilkan N dari M {unit}". */
  unit?: string
  onPageChange?: (page: number) => void
  /** Urutan aktif: `kunci` naik, `-kunci` turun, kosong = bawaan. */
  sort?: string
  onSortChange?: (sort: string) => void
}

/** Klik berulang: naik → turun → urutan bawaan. */
const nextSort = (current: string | undefined, key: string) =>
  current === key ? `-${key}` : current === `-${key}` ? '' : key

function SortHeader({ label, sortKey, sort, onSortChange }: { label: string; sortKey: string; sort?: string; onSortChange: (s: string) => void }) {
  const direction = sort === sortKey ? 'ascending' : sort === `-${sortKey}` ? 'descending' : undefined
  const Icon = direction === 'ascending' ? ArrowUp : direction === 'descending' ? ArrowDown : ChevronsUpDown
  return (
    <button
      type="button"
      onClick={() => onSortChange(nextSort(sort, sortKey))}
      className={`-mx-1 inline-flex items-center gap-1 rounded px-1 font-semibold hover:text-slate-800 ${direction ? 'text-slate-800' : ''}`}
    >
      {label}
      <Icon size={13} className={direction ? '' : 'text-slate-400'} aria-hidden />
    </button>
  )
}

export default function DataTable<T>({
  columns, rows, rowKey, loading, fetching, emptyText = 'Belum ada data.', meta, unit = 'data', onPageChange,
  sort, onSortChange,
}: Props<T>) {
  const skeleton = loading && rows.length === 0
  const empty = !skeleton && rows.length === 0 ? emptyText : null
  const titleCols = columns.filter((c) => c.mobile === 'title')
  const badgeCols = columns.filter((c) => c.mobile === 'badge')
  const detailCols = columns.filter((c) => !c.mobile)
  const dim = fetching ? 'opacity-60 transition-opacity' : 'transition-opacity'

  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-busy={skeleton || fetching || undefined}>
      {skeleton && <LoadingLabel />}
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
            <tr>
              {columns.map((c, i) => {
                const direction = c.sortKey && sort === c.sortKey ? 'ascending' : c.sortKey && sort === `-${c.sortKey}` ? 'descending' : undefined
                return (
                  <th
                    key={`${c.header}-${i}`}
                    aria-sort={direction}
                    className={`whitespace-nowrap px-4 py-2.5 font-semibold ${c.className ?? ''} ${c.headerClassName ?? ''}`}
                  >
                    {c.sortKey && onSortChange ? (
                      <SortHeader label={c.header} sortKey={c.sortKey} sort={sort} onSortChange={onSortChange} />
                    ) : (
                      c.header
                    )}
                  </th>
                )
              })}
            </tr>
          </thead>
          <tbody className={`divide-y divide-slate-100 ${dim}`}>
            {skeleton ? (
              <TableSkeletonRows columns={columns.length} />
            ) : empty ? (
              <tr><td colSpan={columns.length} className="px-4 py-10 text-center text-slate-500">{empty}</td></tr>
            ) : (
              rows.map((row) => (
                <tr key={rowKey(row)} className="transition-colors hover:bg-slate-50">
                  {columns.map((c, i) => (
                    <td key={`${c.header}-${i}`} className={`px-4 py-3 align-middle ${c.className ?? ''}`}>{c.cell(row)}</td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <ul className={`divide-y divide-slate-100 md:hidden ${dim}`}>
        {skeleton ? (
          <ListSkeletonItems />
        ) : empty ? (
          <li className="px-4 py-10 text-center text-sm text-slate-500">{empty}</li>
        ) : (
          rows.map((row) => (
            <li key={rowKey(row)} className="space-y-2.5 px-4 py-3.5 text-sm">
              {(titleCols.length > 0 || badgeCols.length > 0) && (
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 space-y-1">
                    {titleCols.map((c, i) => <div key={i}>{c.cell(row)}</div>)}
                  </div>
                  {badgeCols.length > 0 && (
                    <div className="flex shrink-0 items-center gap-2">
                      {badgeCols.map((c, i) => <div key={i}>{c.cell(row)}</div>)}
                    </div>
                  )}
                </div>
              )}
              {detailCols.length > 0 && (
                <dl className="grid grid-cols-[minmax(0,7.5rem)_minmax(0,1fr)] gap-x-3 gap-y-1.5">
                  {detailCols.map((c, i) => (
                    <div key={i} className="contents">
                      {c.header && <dt className="text-slate-500">{c.header}</dt>}
                      <dd className={`min-w-0 ${c.header ? '' : 'col-span-2'}`}>{c.cell(row)}</dd>
                    </div>
                  ))}
                </dl>
              )}
            </li>
          ))
        )}
      </ul>

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-2.5 text-sm text-slate-600">
          <span>
            {rows.length} dari {meta.total} {unit}
            <span className="hidden sm:inline"> · hal. {meta.current_page} dari {meta.last_page}</span>
          </span>
          <div className="flex gap-2">
            <Button
              variant="secondary"
              aria-label="Halaman sebelumnya"
              disabled={meta.current_page <= 1}
              onClick={() => onPageChange?.(meta.current_page - 1)}
              className="px-3!"
            >
              <ChevronLeft size={16} /> <span className="hidden sm:inline">Sebelumnya</span>
            </Button>
            <Button
              variant="secondary"
              aria-label="Halaman berikutnya"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => onPageChange?.(meta.current_page + 1)}
              className="px-3!"
            >
              <span className="hidden sm:inline">Berikutnya</span> <ChevronRight size={16} />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
