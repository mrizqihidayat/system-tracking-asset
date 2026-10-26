import type { ReactNode } from 'react'
import type { PaginationMeta } from '../../types/api'
import Button from './Button'

export interface Column<T> {
  header: string
  cell: (row: T) => ReactNode
  className?: string
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
  return (
    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="bg-slate-100 text-xs uppercase text-slate-600">
            <tr>
              {columns.map((c) => (
                <th key={c.header} className={`px-3 py-2 font-semibold ${c.className ?? ''}`}>{c.header}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr><td colSpan={columns.length} className="px-3 py-8 text-center text-slate-500">Memuat…</td></tr>
            ) : rows.length === 0 ? (
              <tr><td colSpan={columns.length} className="px-3 py-8 text-center text-slate-500">{emptyText}</td></tr>
            ) : (
              rows.map((row) => (
                <tr key={rowKey(row)} className="hover:bg-slate-50">
                  {columns.map((c) => (
                    <td key={c.header} className={`px-3 py-2 ${c.className ?? ''}`}>{c.cell(row)}</td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between border-t border-slate-200 px-3 py-2 text-sm text-slate-600">
          <span>Menampilkan {rows.length} dari {meta.total} {unit}</span>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPageChange?.(meta.current_page - 1)}>
              Sebelumnya
            </Button>
            <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange?.(meta.current_page + 1)}>
              Berikutnya
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
