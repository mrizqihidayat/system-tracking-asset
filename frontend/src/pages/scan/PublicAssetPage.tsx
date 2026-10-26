import { useEffect, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ConditionBadge, StatusBadge } from '../../components/ui/Badge'
import { api, errorMessage } from '../../lib/api'
import { getToken } from '../../lib/auth'
import { formatDate } from '../../lib/format'
import type { Condition, Status } from '../../types/asset'
import { SCAN_ACTIONS } from './scanActions'

interface PublicAsset {
  code: string
  name: string
  category: string
  asset_type: string
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
  actions: string[]
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-2 text-sm">
      <dt className="shrink-0 text-slate-500">{label}</dt>
      <dd className="text-right">{children || '-'}</dd>
    </div>
  )
}

/** Halaman publik QR (tanpa login): data aset read-only + aksi khusus staf SHD. */
export default function PublicAssetPage() {
  const { token = '' } = useParams()
  const navigate = useNavigate()
  const [asset, setAsset] = useState<PublicAsset | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get<{ data: PublicAsset }>(`/public/assets/${encodeURIComponent(token)}`)
      .then((res) => setAsset(res.data.data))
      .catch((e) => setError(errorMessage(e)))
  }, [token])

  // Sudah login → langsung ke form; belum → login dulu, lalu kembali otomatis ke form yang sama.
  const goAction = (key: string) => {
    const target = `/scan/${encodeURIComponent(token)}?aksi=${key}`
    navigate(getToken() ? target : `/login?next=${encodeURIComponent(target)}`)
  }

  const loc = asset?.current_location
  const buttons = (asset?.actions ?? []).filter((a) => a in SCAN_ACTIONS && a !== 'terima_kembali')

  return (
    <div className="mx-auto min-h-screen max-w-lg bg-slate-50 p-4">
      {error && <p role="alert" className="mt-10 text-center text-slate-600">{error}</p>}
      {!error && !asset && <p className="mt-10 text-center text-slate-500">Memuat…</p>}
      {asset && (
        <div className="space-y-4">
          <section className="rounded-lg border border-slate-200 bg-white p-5">
            <h1 className="text-xl font-semibold">{asset.name}</h1>
            <p className="mt-1 font-mono text-sm text-slate-500">{asset.code}</p>
            <dl className="mt-3 divide-y divide-slate-100">
              <Row label="Kategori">{asset.category}</Row>
              <Row label="Jenis">{asset.asset_type}</Row>
              <Row label="Sumber">{asset.source.name}</Row>
              <Row label="Lokasi asal">{asset.room}, {asset.building}</Row>
              <Row label="Lokasi saat ini">
                {loc?.on_loan
                  ? `Dipinjam · ${loc.room ? `${loc.room}, ${loc.building}` : (loc.note ?? '')}`
                  : `${asset.room}, ${asset.building}`}
              </Row>
              <Row label="Penanggung jawab">{asset.pic_name}</Row>
              <Row label="Kondisi"><ConditionBadge condition={asset.condition} /></Row>
              <Row label="Status"><StatusBadge status={asset.status} /></Row>
              <Row label="Tanggal perolehan">{asset.acquisition_date ? formatDate(asset.acquisition_date) : null}</Row>
              <Row label="Servis berikutnya">{asset.next_service_date ? formatDate(asset.next_service_date) : null}</Row>
              <Row label="Kode aset Keuangan"><span className="font-mono">{asset.finance_code}</span></Row>
              <Row label="Standar ruangan">
                {asset.room_standard.is_standard
                  ? `Termasuk standar ruangan (${asset.room_standard.item_name})`
                  : 'Tidak termasuk standar'}
              </Row>
            </dl>
          </section>

          {(asset.actions.includes('terima_kembali') || buttons.length > 0) && (
            <section className="rounded-lg border border-slate-200 bg-white p-5">
              <h2 className="font-semibold">Aksi</h2>
              <p className="mb-3 text-xs text-slate-500">Khusus staf SHD — Anda akan diminta login.</p>
              <div className="flex flex-wrap gap-2">
                {[...buttons, ...(asset.actions.includes('terima_kembali') ? ['terima_kembali'] : [])].map((key) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => goAction(key)}
                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                  >
                    {SCAN_ACTIONS[key]}
                    <span className="block text-[11px] font-normal text-slate-400">Khusus staf SHD</span>
                  </button>
                ))}
              </div>
            </section>
          )}
          <p className="text-center text-xs text-slate-400">
            <Link to="/login" className="underline">Masuk staf SHD</Link>
          </p>
        </div>
      )}
    </div>
  )
}
