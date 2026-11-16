export const CONDITIONS = ['Baik', 'Rusak Ringan', 'Rusak Berat'] as const
export const STATUSES = ['Tersedia', 'Dipinjam', 'Perbaikan', 'Hilang', 'Tidak Layak Pakai'] as const
export const FILE_TYPES = ['Foto Aset', 'Foto Kondisi', 'BAST', 'Garansi', 'Lainnya'] as const

export type Condition = (typeof CONDITIONS)[number]
export type Status = (typeof STATUSES)[number]
export type FileType = (typeof FILE_TYPES)[number]

export interface Department {
  id: number
  name: string
  is_internal: boolean
}

export interface Building {
  id: number
  name: string
  slug: string
}

export interface Room {
  id: number
  building_id: number
  name: string
  slug: string
  /** Gudang / tempat penyimpanan: bisa menjadi lokasi simpan stok. */
  is_storage: boolean
  building?: Building
}

export interface Category {
  id: number
  name: string
  asset_types_count?: number
}

export interface AssetType {
  id: number
  name: string
  code: string
  category_id: number
  category?: Pick<Category, 'id' | 'name'>
}

export interface ActiveLoan {
  id: number
  borrower_name: string
  borrower_unit: string
  borrow_date: string
  due_date: string
  is_overdue: boolean
}

/** Pemesanan Direncanakan terdekat: "Dipesan untuk {tanggal}". */
export interface UpcomingBooking {
  id: number
  borrower_name: string
  borrower_unit: string
  borrow_date: string
  due_date: string
  is_unhanded: boolean
}

export interface Asset {
  id: number
  code: string
  public_token: string
  public_url: string
  name: string
  brand: string | null
  serial_number: string | null
  specification: string | null
  asset_type?: AssetType
  source_department?: Department
  room?: Room
  current_location?: { on_loan: boolean; room: Room | null; note: string | null }
  active_loan?: ActiveLoan | null
  upcoming_booking?: UpcomingBooking | null
  room_standard?: { is_standard: boolean; item_name: string | null; quantity: number | null }
  previous_codes?: { code: string; replaced_at: string }[]
  pic_name: string | null
  finance_code: string | null
  acquisition_date: string | null
  next_service_date: string | null
  condition: Condition
  status: Status
  note: string | null
  lost_during_loan?: { borrower_name: string; borrower_unit: string; date: string | null; loan_id: number } | null
}

/** Baris `GET /assets`: hanya kolom tabel; data lengkap dari `GET /assets/{id}`. */
export interface AssetListItem extends Pick<Asset, 'id' | 'code' | 'name' | 'condition' | 'status'> {
  category_name: string | null
  asset_type_name: string | null
  room_name: string | null
  building_name: string | null
  source_name: string | null
  source_is_internal: boolean
}

export interface AssetEvent {
  id: number
  event_type: string
  event_date: string
  party: string | null
  condition: Condition | null
  from_room?: Room | null
  to_room?: Room | null
  recorded_by?: { id: number; name: string }
  note: string | null
}

export interface AssetFile {
  id: number
  /** Peminjaman asal (foto kondisi saat Terima kembali); null = file biasa di aset. */
  loan_id?: number | null
  file_type: FileType
  original_name: string
  mime_type: string
  size: number
  download_url: string
}

export interface CodePreview {
  current_code: string
  new_code: string
  code_will_change: boolean
}

export interface DeleteImpact {
  loans_count: number
  active_loan: { borrower_name: string; borrower_unit: string } | null
  events_count: number
  files_count: number
  requires_confirm_code: boolean
  affected_room_standards: { room_id: number; room: string; building: string; item_name: string }[]
}
