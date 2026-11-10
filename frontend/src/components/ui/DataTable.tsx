import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, ChevronsUpDown } from 'lucide-react'
import type { ReactNode } from 'react'
import type { PaginationMeta } from '../../types/api'
import { ListSkeletonItems, LoadingLabel, TableSkeletonRows } from './Skeleton'

/** Tombol halaman: ikon saja, kecil, tanpa bingkai (40 px di HP/tablet agar mudah disentuh). */
const PAGE_BUTTON =
  'flex h-10 w-10 lg:h-8 lg:w-8 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 disabled:pointer-events-none disabled:text-slate-300'

export interface Column<T> {
  header: string
  cell: (row: T) => ReactNode
  className?: string
  /** Kelas tambahan khusus judul kolom (mis. judul di tengah, isi tetap rata kiri). */
  headerClassName?: string
  /** Isi judul kolom di tabel lebar (mis. kotak centang pilih semua); `header` tetap dipakai di kartu HP. */
  headerContent?: ReactNode
  /**
   * Tampilan di layar sempit (< lg), tempat tabel menjadi daftar kartu (tablet: 2 kolom):
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
  /** Baris lebih rapat (judul, isi, dan footer). */
  dense?: boolean
  /** Tinggi tabel tetap (tabel lebar): baris kosong ditambahkan sampai `meta.per_page`, tiap baris setinggi dua baris teks. */
  fixedHeight?: boolean
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
  sort, onSortChange, dense, fixedHeight,
}: Props<T>) {
  const headPad = dense ? 'py-2' : 'py-2.5'
  const cellPad = dense ? 'py-2' : 'py-3'
  // Setinggi dua baris teks: baris yang isinya turun ke baris kedua (mis. "Alat Pelatihan") tidak lebih tinggi dari yang lain.
  const rowHeight = fixedHeight ? (dense ? 'h-15' : 'h-17') : ''
  const fillerCount = fixedHeight && meta && rows.length > 0 ? Math.max(0, meta.per_page - rows.length) : 0
  const skeleton = loading && rows.length === 0
  const empty = !skeleton && rows.length === 0 ? emptyText : null
  const titleCols = columns.filter((c) => c.mobile === 'title')
  const badgeCols = columns.filter((c) => c.mobile === 'badge')
  const detailCols = columns.filter((c) => !c.mobile)
  const dim = fetching ? 'opacity-60 transition-opacity' : 'transition-opacity'

  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-busy={skeleton || fetching || undefined}>
      {skeleton && <LoadingLabel />}
      <div className="hidden overflow-x-auto lg:block">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
            <tr>
              {columns.map((c, i) => {
                const direction = c.sortKey && sort === c.sortKey ? 'ascending' : c.sortKey && sort === `-${c.sortKey}` ? 'descending' : undefined
                return (
                  <th
                    key={`${c.header}-${i}`}
                    aria-sort={direction}
                    className={`whitespace-nowrap px-4 ${headPad} font-semibold ${c.className ?? ''} ${c.headerClassName ?? ''}`}
                  >
                    {c.headerContent ?? (c.sortKey && onSortChange ? (
                      <SortHeader label={c.header} sortKey={c.sortKey} sort={sort} onSortChange={onSortChange} />
                    ) : (
                      c.header
                    ))}
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
              <>
                {rows.map((row) => (
                  <tr key={rowKey(row)} className={`transition-colors hover:bg-slate-50 ${rowHeight}`}>
                    {columns.map((c, i) => (
                      <td key={`${c.header}-${i}`} className={`px-4 ${cellPad} align-middle ${c.className ?? ''}`}>{c.cell(row)}</td>
                    ))}
                  </tr>
                ))}
                {Array.from({ length: fillerCount }, (_, i) => (
                  <tr key={`filler-${i}`} className={rowHeight} aria-hidden>
                    <td colSpan={columns.length} />
                  </tr>
                ))}
              </>
            )}
          </tbody>
        </table>
      </div>

      {/* HP: satu kolom; tablet (md–lg): grid 2 kolom dengan garis pemisah antar kartu. */}
      <ul className={`divide-slate-100 lg:hidden ${skeleton || empty ? 'divide-y' : 'max-md:divide-y md:grid md:grid-cols-2'} ${dim}`}>
        {skeleton ? (
          <ListSkeletonItems />
        ) : empty ? (
          <li className="px-4 py-10 text-center text-sm text-slate-500">{empty}</li>
        ) : (
          rows.map((row) => (
            <li
              key={rowKey(row)}
              className="space-y-2.5 px-4 py-3.5 text-sm md:border-t md:border-slate-100 md:odd:border-r md:nth-[-n+2]:border-t-0"
            >
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
        <div className={`flex items-center justify-between gap-3 border-t border-slate-200 px-4 ${dense ? 'py-1.5' : 'py-2.5'} text-sm text-slate-600`}>
          <span>
            {rows.length} dari {meta.total} {unit}
          </span>
          <div className="flex items-center gap-1">
            <button
              type="button"
              aria-label="Halaman sebelumnya"
              disabled={meta.current_page <= 1}
              onClick={() => onPageChange?.(meta.current_page - 1)}
              className={PAGE_BUTTON}
            >
              <ChevronLeft size={16} />
            </button>
            <span className="min-w-12 text-center tabular-nums" aria-label={`Halaman ${meta.current_page} dari ${meta.last_page}`}>
              {meta.current_page} / {meta.last_page}
            </span>
            <button
              type="button"
              aria-label="Halaman berikutnya"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => onPageChange?.(meta.current_page + 1)}
              className={PAGE_BUTTON}
            >
              <ChevronRight size={16} />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
