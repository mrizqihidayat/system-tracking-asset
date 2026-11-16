import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import type { SuggestionGroup } from '../../types/supply'

interface Props {
  groups: SuggestionGroup[]
  /** Buka popup permintaan sekaligus untuk barang yang belum diminta di grup ini. */
  onRequest: (group: SuggestionGroup) => void
}

const isHabis = (status: string) => status === 'Habis'

/**
 * Saran permintaan (SPEC §9): kartu per departemen penyedia, tersusun mengalir sehingga tidak ada
 * ruang kosong walau departemennya banyak. Hanya barang yang belum punya permintaan terbuka yang tampil;
 * jumlah yang sudah diminta cukup disebut di judul kartu.
 */
export default function SuggestionPanel({ groups, onRequest }: Props) {
  const cards = groups
    .map((g) => ({ group: g, needed: g.items.filter((i) => !i.has_open_request), requested: g.items.filter((i) => i.has_open_request).length }))
    .filter((c) => c.needed.length > 0)
  if (cards.length === 0) return null

  const needed = cards.flatMap((c) => c.needed)
  const habis = needed.filter((i) => isHabis(i.stock_status)).length
  const menipis = needed.length - habis

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
        {cards.map(({ group: g, needed: items, requested }) => (
          <article key={g.provider_department.id} className="overflow-hidden rounded-lg border border-slate-200">
            <header className="flex items-baseline gap-2 bg-slate-50 px-3 py-2">
              <h3 className="text-sm font-semibold">{g.provider_department.name}</h3>
              <p className="text-xs text-slate-500">
                {items.length} item perlu diminta
                {requested > 0 && ` · ${requested} sudah diminta`}
              </p>
            </header>
            <ul className="divide-y divide-slate-100">
              {items.map((i) => (
                <li key={i.consumable_id} className="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-3 py-2 text-sm">
                  <span className="min-w-0 truncate font-medium">{i.item_name}</span>
                  <span className="tabular-nums text-xs text-slate-500">× {i.quantity}</span>
                  <Badge color={isHabis(i.stock_status) ? 'red' : 'amber'}>{i.stock_status}</Badge>
                </li>
              ))}
            </ul>
            <div className="border-t border-slate-100 p-3">
              <Button className="w-full" onClick={() => onRequest(g)}>
                Buat permintaan ke {g.provider_department.name} ({items.length} item)
              </Button>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}
