import { skeletonWidth } from './skeleton-width'

/**
 * Placeholder saat data pertama kali dimuat (menggantikan teks "Memuat…"). Datar (slate-100), tanpa
 * bayangan, denyut dimatikan bila pengguna memilih kurangi gerakan. Teks "Memuat…" tetap untuk pembaca layar.
 */
export function Skeleton({ className = '' }: { className?: string }) {
  return <span aria-hidden className={`block animate-pulse rounded bg-slate-100 motion-reduce:animate-none ${className}`} />
}

export const LoadingLabel = () => <span className="sr-only">Memuat…</span>


/** Baris tabel skeleton (desktop); dipakai DataTable saat muat pertama. */
export function TableSkeletonRows({ columns, rows = 5 }: { columns: number; rows?: number }) {
  return (
    <>
      {Array.from({ length: rows }, (_, r) => (
        <tr key={r}>
          {Array.from({ length: columns }, (_, c) => (
            <td key={c} className="px-4 py-3.5">
              <Skeleton className={`h-4 ${skeletonWidth(r + c)}`} />
            </td>
          ))}
        </tr>
      ))}
    </>
  )
}

/** Kartu baris skeleton (layar sempit). */
export function ListSkeletonItems({ rows = 4 }: { rows?: number }) {
  return (
    <>
      {Array.from({ length: rows }, (_, r) => (
        <li key={r} className="space-y-2.5 px-4 py-3.5">
          <div className="flex items-start justify-between gap-3">
            <Skeleton className="h-4 w-1/2" />
            <Skeleton className="h-5 w-16 rounded-full" />
          </div>
          <Skeleton className={`h-3.5 ${skeletonWidth(r)}`} />
          <Skeleton className={`h-3.5 ${skeletonWidth(r + 2)}`} />
        </li>
      ))}
    </>
  )
}

/** Tabel skeleton berdiri sendiri (panel + header), untuk halaman yang belum punya kolom. */
export function TableSkeleton({ columns = 5, rows = 5 }: { columns?: number; rows?: number }) {
  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-busy="true">
      <LoadingLabel />
      <table className="hidden w-full md:table">
        <thead className="border-b border-slate-200 bg-slate-50">
          <tr>
            {Array.from({ length: columns }, (_, c) => (
              <th key={c} className="px-4 py-3"><Skeleton className="h-3 w-16" /></th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          <TableSkeletonRows columns={columns} rows={rows} />
        </tbody>
      </table>
      <ul className="divide-y divide-slate-100 md:hidden">
        <ListSkeletonItems rows={Math.min(rows, 4)} />
      </ul>
    </div>
  )
}

/** Kartu angka dashboard (bentuk sama dengan StatCard). */
export function CardSkeleton() {
  return (
    <div className="rounded-xl border border-slate-200 bg-white px-4 py-3.5" aria-hidden>
      <Skeleton className="h-4 w-24" />
      <Skeleton className="mt-2.5 h-7 w-12" />
      <Skeleton className="mt-2 h-3 w-32" />
    </div>
  )
}

/** Isi panel berupa daftar baris (dipakai di dalam Panel flush). */
export function ListSkeleton({ rows = 3 }: { rows?: number }) {
  return (
    <ul className="divide-y divide-slate-100 border-t border-slate-100" aria-busy="true">
      <LoadingLabel />
      {Array.from({ length: rows }, (_, r) => (
        <li key={r} className="flex items-center justify-between gap-3 px-5 py-3">
          <Skeleton className={`h-4 ${skeletonWidth(r)}`} />
          <Skeleton className="h-5 w-16 shrink-0 rounded-full" />
        </li>
      ))}
    </ul>
  )
}
