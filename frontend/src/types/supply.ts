import type { Department, Room } from './asset'

export interface RequestCatalog {
  id: number
  item_name: string
  provider_department: Department | null
  request_method: string
  must_return: boolean
  lead_time_days: number
  note: string | null
}

export type RequestStatus = 'Diajukan' | 'Diterima' | 'Dikembalikan' | 'Dibatalkan'

export interface SupplyRequest {
  id: number
  request_date: string
  item_name: string
  quantity: number
  purpose: string | null
  status: RequestStatus
  is_unresponded: boolean
  display_status: RequestStatus | 'Belum direspons'
  response_due_date: string | null
  catalog: { id: number; item_name: string; request_method: string; must_return: boolean; lead_time_days: number } | null
  provider_department: Department | null
  room: Room | null
  note: string | null
}

export interface SuggestionItem {
  source: 'kekurangan_ruangan' | 'stok'
  item_name: string
  quantity: number
  catalog_id: number | null
  room: { id: number; name: string; building: string } | null
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
  catalog: { id: number; item_name: string; request_method: string } | null
  room: Room | null
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
  item_name?: string
  provider_department_id?: number | null
  quantity?: number
  room_id?: number | null
}
