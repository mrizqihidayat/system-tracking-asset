import type { Department, Room } from './asset'

export type CountMethod = 'QR' | 'Manual'
export type ChecklistResult = 'Sesuai' | 'Kurang' | 'Belum dicek'

export interface ReadinessSummary {
  total_items: number
  shortage_items: number
  unchecked_items: number
  is_complete: boolean
}

export interface ReadinessRoom extends ReadinessSummary {
  id: number
  name: string
  slug: string
  last_checked_at: string | null
  last_checked_by: string | null
}

export interface ReadinessBuilding {
  id: number
  name: string
  slug: string
  rooms_count: number
  incomplete_rooms_count: number
  rooms: ReadinessRoom[]
}

export type Fulfillment =
  | { type: 'stok_shd'; consumable: { id: number; name: string; unit: string; quantity: number } }
  | { type: 'ambil_dari'; note: string }
  | {
      type: 'minta_ke'
      catalog: { id: number; item_name: string; provider_department: Department | null; request_method: string | null }
    }

export interface RoomStandard {
  id: number
  room_id: number
  item_name: string
  source_department: Department | null
  standard_quantity: number
  count_method: CountMethod
  asset_type: { id: number; name: string; code: string } | null
  present_quantity: number | null
  difference: number | null
  shortage: number | null
  result: ChecklistResult
  fulfillment: Fulfillment | null
  can_fill_from_stock: boolean
}

export interface Checklist {
  data: RoomStandard[]
  meta: {
    room: Room & { last_checked_at?: string | null }
    last_checked_by: string | null
    summary: ReadinessSummary
  }
}

export interface Consumable {
  id: number
  name: string
  unit: string
  quantity: number
}

export interface CatalogItem {
  id: number
  item_name: string
  provider_department: Department | null
  request_method: string | null
}
