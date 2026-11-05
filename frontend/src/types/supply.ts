import type { Department, Room } from './asset'

export interface RequestCatalog {
  id: number
  item_name: string
  provider_department: Department | null
  must_return: boolean
  lead_time_days: number
  note: string | null
}

export type RequestStatus = 'Diajukan' | 'Diterima sebagian' | 'Diterima' | 'Dikembalikan' | 'Dibatalkan'

/** Dampak perubahan status ke stok. */
export interface StatusImpact {
  message: string
  clamped: boolean
}

export interface SupplyRequest {
  id: number
  request_date: string
  /** Barang stok yang diminta (null bila tanpa tautan atau barangnya sudah dihapus). */
  consumable_id: number | null
  item_name: string
  quantity: number
  /** Jumlah yang sudah masuk stok (barang stok). */
  received_quantity: number
  purpose: string | null
  status: RequestStatus
  is_unresponded: boolean
  display_status: RequestStatus | 'Belum direspons'
  response_due_date: string | null
  /** Tautan katalog (null bila tanpa katalog atau katalognya sudah dihapus). Lead time & must_return = snapshot. */
  catalog: { id: number; item_name: string; must_return: boolean; lead_time_days: number | null } | null
  /** Lewat jalur apa permintaan dikirim; null untuk permintaan lama (sebelum T26g). */
  request_method: string | null
  /** Snapshot dari katalog saat permintaan dibuat/diubah (tetap walau katalog diedit/dihapus). */
  lead_time_days: number | null
  must_return: boolean
  provider_department: Department | null
  room: Room | null
  note: string | null
}

/** Pemakaian item katalog (popup sebelum hapus). */
export interface CatalogUsage {
  supply_requests: number
  total: number
}

export interface SuggestionItem {
  item_name: string
  quantity: number
  consumable_id: number
  stock_status: string
  has_open_request: boolean
}

export interface SuggestionGroup {
  provider_department: { id: number; name: string }
  items: SuggestionItem[]
}

export type ConsumableStatus = 'Aman' | 'Menipis' | 'Habis'

export interface ConsumableCategory {
  id: number
  name: string
  consumables_count?: number
}

export interface ConsumableItem {
  id: number
  name: string
  consumable_category_id: number | null
  category: string | null
  unit: string
  quantity: number
  min_quantity: number
  status: ConsumableStatus
  provider_department: Department | null
  /** Lokasi simpan (opsional). */
  room: Room | null
  /** Permintaan Diajukan terbaru untuk barang ini. */
  open_request?: { id: number; created_at: string | null } | null
  note: string | null
}

export type StockTransactionType = 'Barang Masuk' | 'Pemakaian' | 'Koreksi' | 'Penghapusan'

/** Satu baris kartu stok. quantity bertanda, balance_after = stok setelah transaksi. */
export interface StockTransaction {
  id: number
  type: StockTransactionType
  quantity: number
  balance_after: number
  transaction_date: string
  reason: string | null
  note: string | null
  is_opening: boolean
  room: { id: number; name: string; building: string } | null
  supply_request_id: number | null
  created_by: string | null
}

export interface ConsumableUsage {
  room_id: number
  room: string
  building: string
  item_name: string
}

/** Isian awal form permintaan (dari saran, query Kelengkapan Ruangan, atau tombol biasa). */
export interface RequestPrefill {
  catalog_id?: number | null
  consumable_id?: number | null
  item_name?: string
  provider_department_id?: number | null
  quantity?: number
  room_id?: number | null
}
