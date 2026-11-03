import type { ListConfig } from '../useListParams'

/**
 * Konfigurasi tabel per halaman (konstanta modul: dipakai halaman & prefetch sidebar, sehingga
 * query key-nya sama dan data hasil prefetch langsung terpakai).
 */
export const listConfigs = {
  assets: {
    filters: ['category_id', 'building_id', 'room_id', 'source_department_id', 'condition', 'status'],
  },
  activeLoans: { prefix: 'aktif_', perPage: 10, fixed: { status: 'Dipinjam' } },
  loanHistory: { filters: ['status'], perPage: 10 },
  consumables: { filters: ['status'], perPage: 20 },
  supplyRequests: {
    filters: ['status'],
    perPage: 10,
    // "Belum direspons" adalah status tampilan; API memakai flag unresponded.
    toApi: ({ status, ...rest }) => (status === 'Belum direspons' ? { ...rest, unresponded: 1 } : status ? { ...rest, status } : rest),
  },
} satisfies Record<string, ListConfig>
