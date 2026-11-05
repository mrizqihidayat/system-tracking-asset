import type { Department, Room } from './asset'

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

/** Bagian "Aset di ruangan": per jenis. Standar 0 = tanpa baris standar, tampil jumlah Ada tanpa Hasil. */
export interface RoomAssetGroup {
  asset_type: { id: number; name: string; code: string }
  total: number
  assets: { id: number; code: string; status: string }[]
  /** Jumlah aset yang tidak dihitung "Ada", per status. */
  not_counted: Partial<Record<'Dipinjam' | 'Perbaikan' | 'Hilang', number>>
  standard_id: number | null
  standard_quantity: number
  present_quantity: number
  difference: number | null
  result: ChecklistResult | null
}

/** Bagian "Barang habis pakai": barang dari Stok, jumlah Ada diisi saat cek. */
export interface RoomConsumable {
  id: number
  room_id: number
  item_name: string
  standard_quantity: number
  consumable: {
    id: number
    name: string
    unit: string
    quantity: number
    min_quantity: number
    provider_department: Department | null
  }
  present_quantity: number | null
  difference: number | null
  shortage: number | null
  result: ChecklistResult
  can_fill_from_stock: boolean
  can_request: boolean
}

export interface Checklist {
  data: {
    assets: RoomAssetGroup[]
    consumables: RoomConsumable[]
  }
  meta: {
    room: Room & { last_checked_at?: string | null }
    last_checked_by: string | null
    summary: ReadinessSummary
  }
}
