import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import type { SuggestionGroup } from '../../types/supply'

interface Props {
  groups: SuggestionGroup[]
  /** Buka popup permintaan sekaligus untuk barang yang belum diminta di grup ini. */
  onRequest: (group: SuggestionGroup) => void
  /** Tampilkan riwayat permintaan ke departemen ini. */
  onShowHistory: (departmentId: number) => void
}

const isHabis = (status: string) => status === 'Habis'

/**
 * Saran permintaan (SPEC §9): kartu per departemen penyedia, tersusun mengalir sehingga tidak ada
 * ruang kosong walau departemennya banyak. Barang yang sudah punya permintaan terbuka diringkas satu baris.
 */
export default function SuggestionPanel({ groups, onRequest, onShowHistory }: Props) {
  const items = groups.flatMap((g) => g.items)
  const habis = items.filter((i) => isHabis(i.stock_status)).length
  const menipis = items.length - habis

  return (
    <section className="rounded-xl border border-slate-200 bg-white">
      <div className="flex flex-col gap-2 px-5 pb-3 pt-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="font-semibold">Saran permintaan</h2>
          <p className="text-xs text-slate-500">Dari stok habis pakai yang habis atau menipis, dikelompokkan per penyedia</p>
        </div>
        <div className="flex shrink-0 gap-3 text-xs font-medium">
          {habis > 0 && (
            <span className="inline-flex items-center gap-1.5 text-bad-700">
              <span className="size-2 rounded-full bg-bar-bad" aria-hidden /> Habis {habis}
            </span>
          )}
          {menipis > 0 && (
            <span className="inline-flex items-center gap-1.5 text-warn-700">
              <span className="size-2 rounded-full bg-bar-warn" aria-hidden /> Menipis {menipis}
            </span>
          )}
        </div>
      </div>

      <div className="grid items-start gap-3 border-t border-slate-100 p-4 sm:grid-cols-[repeat(auto-fill,minmax(17rem,1fr))]">
        {groups.map((g) => {
          const needed = g.items.filter((i) => !i.has_open_request)
          const requested = g.items.filter((i) => i.has_open_request)
          return (
            <article key={g.provider_department.id} className="overflow-hidden rounded-lg border border-slate-200">
              <header className="flex items-baseline gap-2 bg-slate-50 px-3 py-2">
                <h3 className="text-sm font-semibold">{g.provider_department.name}</h3>
                <p className="text-xs text-slate-500">
                  {needed.length > 0 ? `${needed.length} item perlu diminta` : 'Semua sudah diminta'}
                  {needed.length > 0 && requested.length > 0 && ` · ${requested.length} sudah diminta`}
                </p>
              </header>
              {needed.length > 0 && (
                <ul className="divide-y divide-slate-100">
                  {needed.map((i) => (
                    <li key={i.consumable_id} className="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-3 py-2 text-sm">
                      <span className="min-w-0 truncate font-medium">{i.item_name}</span>
                      <span className="tabular-nums text-xs text-slate-500">× {i.quantity}</span>
                      <Badge color={isHabis(i.stock_status) ? 'red' : 'amber'}>{i.stock_status}</Badge>
                    </li>
                  ))}
                </ul>
              )}
              {requested.length > 0 && (
                <div className="flex items-start justify-between gap-3 border-t border-slate-100 px-3 py-2 text-xs text-slate-500">
                  <p className="min-w-0">{requested.map((i) => i.item_name).join(', ')} sudah diminta</p>
                  <button type="button" className="tap shrink-0 font-semibold text-brand-700 hover:underline" onClick={() => onShowHistory(g.provider_department.id)}>
                    Lihat di riwayat
                  </button>
                </div>
              )}
              {needed.length > 0 && (
                <div className="border-t border-slate-100 p-3">
                  <Button className="w-full" onClick={() => onRequest(g)}>
                    Buat permintaan ke {g.provider_department.name} ({needed.length} item)
                  </Button>
                </div>
              )}
            </article>
          )
        })}
      </div>
    </section>
  )
}
