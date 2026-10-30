import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { ReactNode } from 'react'
import type { PaginationMeta } from '../../types/api'
import Button from './Button'

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
}

interface Props<T> {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => string | number
  loading?: boolean
  emptyText?: string
  meta?: PaginationMeta
  /** Satuan di footer: "Menampilkan N dari M {unit}". */
  unit?: string
  onPageChange?: (page: number) => void
}

export default function DataTable<T>({
  columns, rows, rowKey, loading, emptyText = 'Belum ada data.', meta, unit = 'data', onPageChange,
}: Props<T>) {
  const status = loading ? 'Memuat…' : rows.length === 0 ? emptyText : null
  const titleCols = columns.filter((c) => c.mobile === 'title')
  const badgeCols = columns.filter((c) => c.mobile === 'badge')
  const detailCols = columns.filter((c) => !c.mobile)

  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
            <tr>
              {columns.map((c, i) => (
                <th key={`${c.header}-${i}`} className={`whitespace-nowrap px-4 py-2.5 font-semibold ${c.className ?? ''} ${c.headerClassName ?? ''}`}>{c.header}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {status ? (
              <tr><td colSpan={columns.length} className="px-4 py-10 text-center text-slate-500">{status}</td></tr>
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

      <ul className="divide-y divide-slate-100 md:hidden">
        {status ? (
          <li className="px-4 py-10 text-center text-sm text-slate-500">{status}</li>
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
