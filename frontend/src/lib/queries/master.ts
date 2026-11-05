import { queryOptions, useQuery } from '@tanstack/react-query'
import type { AssetType, Building, Category, Department, Room } from '../../types/asset'
import type { ConsumableCategory, RequestCatalog } from '../../types/supply'
import { api } from '../api'
import { MASTER_STALE_TIME } from '../query-client'
import { queryKeys } from './keys'

/** Data master/dropdown: jarang berubah → staleTime panjang, tidak di-fetch ulang setiap form dibuka. */
const list = async <T>(url: string, params?: Record<string, unknown>) =>
  (await api.get<{ data: T[] }>(url, { params })).data.data

const master = <T>(queryKey: readonly unknown[], url: string, params?: Record<string, unknown>) =>
  queryOptions({ queryKey, queryFn: () => list<T>(url, params), staleTime: MASTER_STALE_TIME, gcTime: MASTER_STALE_TIME })

export const masterQueries = {
  departments: () => master<Department>(queryKeys.master.departments(), '/departments'),
  buildings: () => master<Building>(queryKeys.master.buildings(), '/buildings'),
  rooms: (buildingId?: number | null) =>
    master<Room>(queryKeys.master.rooms(buildingId), '/rooms', buildingId ? { building_id: buildingId } : undefined),
  categories: () => master<Category>(queryKeys.master.categories(), '/categories'),
  consumableCategories: () => master<ConsumableCategory>(queryKeys.master.consumableCategories(), '/consumable-categories'),
  assetTypes: () => master<AssetType>(queryKeys.master.assetTypes(), '/asset-types'),
  requestCatalog: () => master<RequestCatalog>(queryKeys.master.requestCatalog(), '/request-catalog'),
  storageLocations: () => master<string>(queryKeys.master.storageLocations(), '/lookups/storage-locations'),
  requestMethods: () => master<string>(queryKeys.master.requestMethods(), '/lookups/request-methods'),
}

const EMPTY: never[] = []

/** Daftar master dengan nilai awal [] agar form tidak perlu menangani undefined. */
const useMaster = <T>(options: ReturnType<typeof master<T>>, enabled = true) => {
  const query = useQuery({ ...options, enabled })
  return { ...query, data: (query.data ?? EMPTY) as T[] }
}

export const useDepartments = () => useMaster(masterQueries.departments())
export const useBuildings = () => useMaster(masterQueries.buildings())
/** `buildingId` kosong + `requireBuilding` → tidak memuat (dropdown ruangan menunggu gedung dipilih). */
export const useRooms = (buildingId?: number | null, requireBuilding = false) =>
  useMaster(masterQueries.rooms(buildingId), !requireBuilding || !!buildingId)
export const useCategories = () => useMaster(masterQueries.categories())
export const useConsumableCategories = () => useMaster(masterQueries.consumableCategories())
export const useAssetTypes = () => useMaster(masterQueries.assetTypes())
export const useRequestCatalog = () => useMaster(masterQueries.requestCatalog())
export const useStorageLocations = () => useMaster(masterQueries.storageLocations())
export const useRequestMethods = () => useMaster(masterQueries.requestMethods())
