/**
 * Query key terpusat. Kunci level atas (`all`) dipakai untuk invalidasi seluruh resource,
 * kunci turunan untuk daftar (dengan parameter) dan detail.
 */
export type ListParams = Record<string, string | number>

export const queryKeys = {
  dashboard: {
    all: ['dashboard'] as const,
    cards: () => ['dashboard', 'cards'] as const,
    roomReadiness: () => ['dashboard', 'room-readiness'] as const,
    panels: () => ['dashboard', 'panels'] as const,
  },
  assets: {
    all: ['assets'] as const,
    list: (params: ListParams) => ['assets', 'list', params] as const,
    detail: (id: number | string) => ['assets', 'detail', String(id)] as const,
    events: (id: number | string) => ['assets', 'events', String(id)] as const,
    files: (id: number | string) => ['assets', 'files', String(id)] as const,
  },
  loans: {
    all: ['loans'] as const,
    list: (params: ListParams) => ['loans', 'list', params] as const,
  },
  consumables: {
    all: ['consumables'] as const,
    list: (params: ListParams) => ['consumables', 'list', params] as const,
  },
  supplyRequests: {
    all: ['supply-requests'] as const,
    list: (params: ListParams) => ['supply-requests', 'list', params] as const,
    suggestions: () => ['supply-requests', 'suggestions'] as const,
    statusImpact: (id: number, status: string) => ['supply-requests', 'status-impact', id, status] as const,
  },
  rooms: {
    all: ['room-readiness'] as const,
    readiness: () => ['room-readiness', 'summary'] as const,
    checklist: (roomId: number) => ['room-readiness', 'checklist', roomId] as const,
  },
  master: {
    all: ['master'] as const,
    departments: () => ['master', 'departments'] as const,
    buildings: () => ['master', 'buildings'] as const,
    rooms: (buildingId?: number | null) => ['master', 'rooms', buildingId ?? 'all'] as const,
    categories: () => ['master', 'categories'] as const,
    assetTypes: () => ['master', 'asset-types'] as const,
    requestCatalog: () => ['master', 'request-catalog'] as const,
    storageLocations: () => ['master', 'storage-locations'] as const,
  },
}
