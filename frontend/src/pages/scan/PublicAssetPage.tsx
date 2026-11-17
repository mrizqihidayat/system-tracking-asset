import { useEffect, useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Brand } from '../../components/layout/AppLayout'
import { AssetCode, ConditionBadge, StatusBadge } from '../../components/ui/Badge'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { skeletonWidth } from '../../components/ui/skeleton-width'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import type { Condition, Status } from '../../types/asset'

interface PublicAsset {
  code: string
  name: string
  category: string
  asset_type: string
  specification: string | null
  source: { name: string; is_internal: boolean }
  building: string
  room: string
  current_location: { on_loan: boolean; building: string | null; room: string | null; note: string | null }
  pic_name: string | null
  condition: Condition
  status: Status
  acquisition_date: string | null
  next_service_date: string | null
  finance_code: string | null
  room_standard: { is_standard: boolean; item_name: string | null }
  /** Pemesanan terdekat, tanpa data peminjam. */
  upcoming_booking: { borrow_date: string } | null
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-2.5 text-sm">
      <dt className="shrink-0 text-slate-500">{label}</dt>
      <dd className="min-w-0 text-right">{children || '-'}</dd>
    </div>
  )
}

/** Halaman publik QR (tanpa login): data aset read-only. Aksi dilakukan staf lewat hasil scan setelah login. */
export default function PublicAssetPage() {
  const { token = '' } = useParams()
  const [asset, setAsset] = useState<PublicAsset | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get<{ data: PublicAsset }>(`/public/assets/${encodeURIComponent(token)}`)
      .then((res) => setAsset(res.data.data))
      .catch((e) => setError(errorMessage(e)))
  }, [token])

  const loc = asset?.current_location

  return (
    <div className="min-h-screen">
      {/* Tautan QR yang tersebar tidak boleh masuk mesin pencari. */}
      <meta name="robots" content="noindex" />
      <header className="bg-rail px-4 py-4 text-white">
        <div className="mx-auto max-w-lg">
          <Brand compact />
        </div>
      </header>

      <main className="mx-auto max-w-lg space-y-4 px-4 py-5">
        {error && <p role="alert" className="mt-6 text-center text-slate-600">{error}</p>}
        {!error && !asset && (
          <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-5" aria-busy="true">
            <LoadingLabel />
            <Skeleton className="h-5 w-28" />
            <Skeleton className="h-6 w-3/4" />
            {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className={`h-4 ${skeletonWidth(i)}`} />)}
          </section>
        )}
        {asset && (
          <>
            <section className="rounded-xl border border-slate-200 bg-white p-5">
              <div className="flex flex-wrap items-center gap-2">
                <AssetCode code={asset.code} className="text-sm" />
                <StatusBadge status={asset.status} />
                <ConditionBadge condition={asset.condition} />
              </div>
              <h1 className="mt-2 text-xl font-bold tracking-tight text-balance">{asset.name}</h1>
              <p className="mt-0.5 text-sm text-slate-500">{asset.asset_type}</p>
              <dl className="mt-3 divide-y divide-slate-100 border-t border-slate-100">
                <Row label="Kategori">{asset.category}</Row>
                <Row label="Spesifikasi">{asset.specification && <span className="whitespace-pre-line break-words">{asset.specification}</span>}</Row>
                <Row label="Sumber">{asset.source.name}</Row>
                <Row label="Lokasi asal">{asset.room}, {asset.building}</Row>
                <Row label="Lokasi saat ini">
                  {loc?.on_loan
                    ? `Dipinjam · ${loc.room ? `${loc.room}, ${loc.building}` : (loc.note ?? '')}`
                    : `${asset.room}, ${asset.building}`}
                </Row>
                {asset.upcoming_booking && (
                  <Row label="Pemesanan">Dipesan untuk {formatDate(asset.upcoming_booking.borrow_date)}</Row>
                )}
                <Row label="Penanggung jawab">{asset.pic_name}</Row>
                <Row label="Tanggal perolehan">{asset.acquisition_date ? formatDate(asset.acquisition_date) : null}</Row>
                <Row label="Servis berikutnya">{asset.next_service_date ? formatDate(asset.next_service_date) : null}</Row>
                <Row label="Kode aset Keuangan">{asset.finance_code && <span className="font-mono">{asset.finance_code}</span>}</Row>
                <Row label="Standar ruangan">
                  {asset.room_standard.is_standard
                    ? `Termasuk standar ruangan (${asset.room_standard.item_name})`
                    : 'Tidak termasuk standar'}
                </Row>
              </dl>
            </section>

            <p className="text-center text-sm">
              <Link to="/login" className="font-semibold text-brand-700 hover:underline">Masuk staf SHD</Link>
            </p>
          </>
        )}
      </main>
    </div>
  )
}
