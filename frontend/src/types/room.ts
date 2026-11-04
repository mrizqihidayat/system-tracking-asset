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

/** Stok Habis Pakai: barang beserta penyedia & katalognya. Barang unit lain: penyedia & katalognya. */
export type Fulfillment =
  | {
      type: 'stok'
      consumable: {
        id: number
        name: string
        unit: string
        quantity: number
        catalog_id: number | null
        provider_department: Department | null
      }
    }
  | {
      type: 'unit_lain'
      catalog: { id: number; item_name: string } | null
      provider_department: Department | null
    }

export interface RoomStandard {
  id: number
  room_id: number
  item_name: string
  source_department: Department | null
  standard_quantity: number
  count_method: CountMethod
  asset_type: { id: number; name: string; code: string } | null
  /** Baris aset: aset yang terhitung "Ada". */
  assets: { id: number; code: string }[]
  /** Baris aset: jumlah aset jenis ini di ruangan yang tidak dihitung, per status. */
  not_counted: Partial<Record<'Dipinjam' | 'Perbaikan' | 'Hilang', number>>
  present_quantity: number | null
  difference: number | null
  shortage: number | null
  result: ChecklistResult
  fulfillment: Fulfillment | null
  /** Baris SHD tanpa barang stok: tanpa aksi sampai dilengkapi. */
  needs_completion: boolean
  can_fill_from_stock: boolean
  can_request: boolean
}

export interface Checklist {
  data: RoomStandard[]
  meta: {
    room: Room & { last_checked_at?: string | null }
    last_checked_by: string | null
    summary: ReadinessSummary
  }
}
