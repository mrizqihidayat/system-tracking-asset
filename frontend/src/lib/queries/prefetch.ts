import type { QueryClient } from '@tanstack/react-query'
import { buildListParams } from '../useListParams'
import { dashboardQueries } from './dashboard'
import { listConfigs } from './list-configs'
import { assetQueries, listQueries } from './lists'
import { masterQueries } from './master'

const empty = () => new URLSearchParams()

/**
 * Prefetch data halaman saat menu sidebar disorot. Memakai query key yang sama dengan halaman (tanpa
 * filter), dan prefetchQuery tidak mengambil ulang data yang masih segar.
 */
const routes: Record<string, (qc: QueryClient) => unknown[]> = {
  '/': (qc) => [
    qc.prefetchQuery(dashboardQueries.cards()),
    qc.prefetchQuery(dashboardQueries.roomReadiness()),
    qc.prefetchQuery(dashboardQueries.panels()),
  ],
  '/daftar-aset': (qc) => [
    qc.prefetchQuery(listQueries.assets(buildListParams(empty(), listConfigs.assets))),
    qc.prefetchQuery(masterQueries.categories()),
    qc.prefetchQuery(masterQueries.buildings()),
    qc.prefetchQuery(masterQueries.departments()),
  ],
  '/peminjaman': (qc) => [
    qc.prefetchQuery(listQueries.loans(buildListParams(empty(), listConfigs.activeLoans))),
    qc.prefetchQuery(listQueries.loans(buildListParams(empty(), listConfigs.loanHistory))),
    qc.prefetchQuery(masterQueries.departments()),
  ],
  '/kelengkapan-ruangan': (qc) => [qc.prefetchQuery(listQueries.readiness())],
  '/permintaan': (qc) => [
    qc.prefetchQuery(listQueries.suggestions()),
    qc.prefetchQuery(masterQueries.requestCatalog()),
    qc.prefetchQuery(listQueries.supplyRequests(buildListParams(empty(), listConfigs.supplyRequests))),
  ],
  '/stok': (qc) => [qc.prefetchQuery(listQueries.consumables(buildListParams(empty(), listConfigs.consumables)))],
  '/pengaturan/data-master': (qc) => [
    qc.prefetchQuery(masterQueries.buildings()),
    qc.prefetchQuery(masterQueries.rooms()),
    qc.prefetchQuery(masterQueries.departments()),
    qc.prefetchQuery(masterQueries.categories()),
    qc.prefetchQuery(masterQueries.assetTypes()),
  ],
}

export function prefetchRoute(qc: QueryClient, path: string) {
  routes[path]?.(qc)
}

/** Detail aset (data, riwayat halaman pertama, file) saat baris tabel disorot. */
export function prefetchAsset(qc: QueryClient, id: number) {
  qc.prefetchQuery(assetQueries.detail(id))
  qc.prefetchInfiniteQuery(assetQueries.events(id))
  qc.prefetchQuery(assetQueries.files(id))
}
