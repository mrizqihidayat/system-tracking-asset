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
    filters: ['status', 'id', 'provider_department_id'],
    perPage: 10,
    // Chip status memakai status tampilan (Diajukan tanpa yang Belum direspons) → display_status di API.
    toApi: ({ status, ...rest }) => (status ? { ...rest, display_status: status } : rest),
  },
} satisfies Record<string, ListConfig>
