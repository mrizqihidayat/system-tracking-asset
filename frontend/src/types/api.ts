export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
  /** Kode mesin, mis. `password_change_required` (403). */
  code?: string
}
