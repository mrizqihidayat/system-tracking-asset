import type { QueryClient } from '@tanstack/react-query'
import { buildListParams } from '../useListParams'
import { dashboardQueries } from './dashboard'
import { listConfigs } from './list-configs'
import { listQueries } from './lists'
import { masterQueries } from './master'

const PREFETCH_DWELL_MS = 300

const empty = () => new URLSearchParams()

/**
 * Satu request utama per menu sidebar. Memakai query key yang sama dengan halaman (tanpa filter), dan
 * prefetchQuery tidak mengambil ulang selama data masih segar (staleTime di query-client.ts / master.ts).
 */
const routes: Record<string, (qc: QueryClient) => unknown> = {
  '/': (qc) => qc.prefetchQuery(dashboardQueries.cards()),
  '/daftar-aset': (qc) => qc.prefetchQuery(listQueries.assets(buildListParams(empty(), listConfigs.assets))),
  '/peminjaman': (qc) => qc.prefetchQuery(listQueries.loans(buildListParams(empty(), listConfigs.activeLoans))),
  '/kelengkapan-ruangan': (qc) => qc.prefetchQuery(listQueries.readiness()),
  '/permintaan': (qc) => qc.prefetchQuery(listQueries.supplyRequests(buildListParams(empty(), listConfigs.supplyRequests))),
  '/stok': (qc) => qc.prefetchQuery(listQueries.consumables(buildListParams(empty(), listConfigs.consumables))),
  '/pengaturan/data-master': (qc) => qc.prefetchQuery(masterQueries.buildings()),
}

let dwellTimer: ReturnType<typeof setTimeout> | undefined

/** Prefetch hanya jika kursor/fokus diam di menu; melintas cepat tidak mengirim request. */
export function prefetchRouteOnDwell(qc: QueryClient, path: string) {
  clearTimeout(dwellTimer)
  dwellTimer = setTimeout(() => routes[path]?.(qc), PREFETCH_DWELL_MS)
}

export function cancelRoutePrefetch() {
  clearTimeout(dwellTimer)
}
