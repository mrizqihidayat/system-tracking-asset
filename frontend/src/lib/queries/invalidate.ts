import type { QueryClient, QueryKey } from '@tanstack/react-query'
import { queryKeys as k } from './keys'

/**
 * Invalidasi setelah mutasi, per jenis perubahan. Satu perubahan bisa menyentuh beberapa modul
 * (mis. terima kembali → status aset, hitungan QR ruangan, dashboard), jadi kelompoknya mengikuti
 * observer cache di backend. Query yang tidak sedang tampil hanya ditandai basi (tidak di-fetch).
 */
const operational: QueryKey[] = [k.assets.all, k.loans.all, k.rooms.all, k.supplyRequests.all, k.dashboard.all]

function run(qc: QueryClient, keys: QueryKey[]) {
  return Promise.all(keys.map((queryKey) => qc.invalidateQueries({ queryKey })))
}

export const invalidate = {
  /** Aset & peminjaman: status/lokasi aset memengaruhi daftar, ruangan (QR), saran, dashboard. */
  assets: (qc: QueryClient) => run(qc, operational),
  loans: (qc: QueryClient) => run(qc, operational),
  /** Stok: daftar stok, saran lokasi simpan, checklist (isi dari stok), saran permintaan, dashboard. */
  consumables: (qc: QueryClient) => run(qc, [k.consumables.all, k.master.storageLocations(), k.rooms.all, k.supplyRequests.all, k.dashboard.all]),
  /** Permintaan: riwayat, saran, panel dashboard. */
  supplyRequests: (qc: QueryClient) => run(qc, [k.supplyRequests.all, k.dashboard.all]),
  /** Katalog: daftar katalog (master), saran, standar ruangan. */
  catalog: (qc: QueryClient) => run(qc, [k.master.requestCatalog(), k.supplyRequests.all, k.rooms.all]),
  /** Standar & hasil cek ruangan (isi dari stok juga mengurangi stok). */
  rooms: (qc: QueryClient) => run(qc, [k.rooms.all, k.consumables.all, k.supplyRequests.all, k.dashboard.all]),
  /** Data master: nama tampil di hampir semua daftar → semua query dianggap basi. */
  master: (qc: QueryClient) => qc.invalidateQueries(),
}
