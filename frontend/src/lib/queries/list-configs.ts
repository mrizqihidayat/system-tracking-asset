import type { ListConfig } from '../useListParams'

/**
 * Konfigurasi tabel per halaman (konstanta modul: dipakai halaman & prefetch sidebar, sehingga
 * query key-nya sama dan data hasil prefetch langsung terpakai).
 */
export const listConfigs = {
  assets: {
    filters: ['category_id', 'building_id', 'room_id', 'status'],
  },
  plannedLoans: { prefix: 'rencana_', perPage: 10, fixed: { status: 'Direncanakan' } },
  activeLoans: { prefix: 'aktif_', perPage: 10, fixed: { status: 'Dipinjam' } },
  loanHistory: { filters: ['status'], perPage: 10 },
  consumables: { filters: ['status', 'building_id', 'room_id'], perPage: 20 },
  supplyRequests: {
    filters: ['status', 'id'],
    perPage: 10,
    // "Belum direspons" adalah status tampilan; API memakai flag unresponded.
    toApi: ({ status, ...rest }) => (status === 'Belum direspons' ? { ...rest, unresponded: 1 } : status ? { ...rest, status } : rest),
  },
} satisfies Record<string, ListConfig>
