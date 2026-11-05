import type { Department, Room } from './asset'

export interface RequestCatalog {
  id: number
  item_name: string
  provider_department: Department | null
  must_return: boolean
  lead_time_days: number
  note: string | null
}

export type RequestStatus = 'Diajukan' | 'Diterima' | 'Dikembalikan' | 'Dibatalkan'

/** Dampak perubahan status ke stok / Ada ruangan. */
export interface StatusImpact {
  message: string
  clamped: boolean
}

export interface SupplyRequest {
  id: number
  request_date: string
  /** Barang stok yang diminta (null bila tanpa tautan atau barangnya sudah dihapus). */
  consumable_id: number | null
  /** Baris standar Barang departemen lain yang ditambah saat Diterima. */
  room_standard_id: number | null
  item_name: string
  quantity: number
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
  room_standards: number
  total: number
}

export interface SuggestionItem {
  source: 'kekurangan_ruangan' | 'stok'
  item_name: string
  catalog_name: string | null
  quantity: number
  catalog_id: number | null
  room: { id: number; name: string; building: string } | null
  room_standard_id: number | null
  consumable_id: number | null
  stock_status?: string
  has_open_request: boolean
}

export interface SuggestionGroup {
  provider_department: { id: number; name: string }
  items: SuggestionItem[]
}

export type ConsumableStatus = 'Aman' | 'Menipis' | 'Habis'

export interface ConsumableItem {
  id: number
  name: string
  category: string | null
  unit: string
  quantity: number
  min_quantity: number
  status: ConsumableStatus
  provider_department: Department | null
  storage_location: string | null
  /** Permintaan Diajukan terbaru untuk barang ini. */
  open_request?: { id: number; created_at: string | null } | null
  note: string | null
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
  room_standard_id?: number | null
  item_name?: string
  provider_department_id?: number | null
  quantity?: number
  room_id?: number | null
}
