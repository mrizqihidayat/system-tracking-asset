import type { AssetType, Building, Category, Department, Room } from '../types/asset'
import { api } from './api'
import { queryKeys } from './queries/keys'
import { queryClient } from './query-client'

// Daftar data master dibaca lewat hook di lib/queries/master.ts (di-cache). Di sini hanya "tambah cepat"
// dari combobox: item baru langsung masuk cache daftar terkait, lalu cache master dianggap basi.

function added<T>(queryKey: readonly unknown[], item: T) {
  queryClient.setQueryData<T[]>(queryKey, (list) => (list ? [...list, item] : list))
  queryClient.invalidateQueries({ queryKey: queryKeys.master.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.rooms.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.dashboard.all })
  return item
}

export const createDepartment = async (name: string) =>
  added(queryKeys.master.departments(), (await api.post<{ data: Department }>('/departments', { name })).data.data)
export const createBuilding = async (name: string) =>
  added(queryKeys.master.buildings(), (await api.post<{ data: Building }>('/buildings', { name })).data.data)
export const createRoom = async (buildingId: number, name: string) =>
  added(queryKeys.master.rooms(buildingId), (await api.post<{ data: Room }>(`/buildings/${buildingId}/rooms`, { name })).data.data)
export const createCategory = async (name: string) =>
  added(queryKeys.master.categories(), (await api.post<{ data: Category }>('/categories', { name })).data.data)
export const createAssetType = async (categoryId: number, name: string) =>
  added(queryKeys.master.assetTypes(), (await api.post<{ data: AssetType }>('/asset-types', { category_id: categoryId, name })).data.data)
