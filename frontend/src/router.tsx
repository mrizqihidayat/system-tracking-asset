import { createBrowserRouter } from 'react-router-dom'
import AppLayout from './components/layout/AppLayout'
import RequireAuth from './components/RequireAuth'
import LoginPage from './pages/LoginPage'
import AssetDetailPage from './pages/assets/AssetDetailPage'
import AssetFormPage from './pages/assets/AssetFormPage'
import AssetListPage from './pages/assets/AssetListPage'
import LoanPage from './pages/loans/LoanPage'
import RoomsPage from './pages/rooms/RoomsPage'
import ConsumablesPage from './pages/consumables/ConsumablesPage'
import RequestsPage from './pages/requests/RequestsPage'
import DashboardPage from './pages/DashboardPage'
import MasterDataPage from './pages/master/MasterDataPage'
import PlaceholderPage from './pages/PlaceholderPage'
import PublicAssetPage from './pages/scan/PublicAssetPage'
import ScanCodePage from './pages/scan/ScanCodePage'
import ScanPage from './pages/scan/ScanPage'

const page = (title: string, task: string) => <PlaceholderPage title={title} task={task} />

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  // Halaman publik QR (SPEC §6): /aset/{public_token}, tanpa login.
  { path: '/aset/:token', element: <PublicAssetPage /> },
  {
    element: (
      <RequireAuth>
        <AppLayout />
      </RequireAuth>
    ),
    children: [
      { path: '/', element: <DashboardPage /> },
      { path: '/daftar-aset', element: <AssetListPage /> },
      { path: '/daftar-aset/baru', element: <AssetFormPage /> },
      { path: '/daftar-aset/:id', element: <AssetDetailPage /> },
      { path: '/daftar-aset/:id/ubah', element: <AssetFormPage /> },
      { path: '/scan', element: <ScanPage /> },
      { path: '/scan/:code', element: <ScanCodePage /> },
      { path: '/peminjaman', element: <LoanPage /> },
      { path: '/kelengkapan-ruangan', element: <RoomsPage /> },
      { path: '/kelengkapan-ruangan/:building', element: <RoomsPage /> },
      { path: '/kelengkapan-ruangan/:building/:room', element: <RoomsPage /> },
      { path: '/permintaan', element: <RequestsPage /> },
      { path: '/stok', element: <ConsumablesPage /> },
      { path: '/pengaturan/data-master', element: <MasterDataPage /> },
      { path: '*', element: page('Halaman tidak ditemukan', '—') },
    ],
  },
])
