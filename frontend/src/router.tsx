import { createBrowserRouter } from 'react-router-dom'
import AppLayout from './components/layout/AppLayout'
import RequireAuth from './components/RequireAuth'
import LoginPage from './pages/LoginPage'
import ChangePasswordPage from './pages/ChangePasswordPage'
import AssetDetailPage from './pages/assets/AssetDetailPage'
import AssetFormPage from './pages/assets/AssetFormPage'
import AssetListPage from './pages/assets/AssetListPage'
import LoanPage from './pages/loans/LoanPage'
import RoomsPage from './pages/rooms/RoomsPage'
import ConsumablesPage from './pages/consumables/ConsumablesPage'
import RequestsPage from './pages/requests/RequestsPage'
import DashboardPage from './pages/DashboardPage'
import EmailRequiredPage from './pages/EmailRequiredPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import LegacyMasterRedirect from './pages/settings/LegacyMasterRedirect'
import SettingsPage from './pages/settings/SettingsPage'
import NotFoundPage from './pages/NotFoundPage'
import PublicAssetPage from './pages/scan/PublicAssetPage'
import ScanCodePage from './pages/scan/ScanCodePage'
import ScanPage from './pages/scan/ScanPage'

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  // Lupa password via OTP (T40, SPEC §15): tanpa login.
  { path: '/lupa-password', element: <ForgotPasswordPage /> },
  // Form wajib isi email untuk akun tanpa email (T40).
  {
    path: '/isi-email',
    element: (
      <RequireAuth>
        <EmailRequiredPage />
      </RequireAuth>
    ),
  },
  // Halaman publik QR (SPEC §6): /aset/{public_token}, tanpa login.
  { path: '/aset/:token', element: <PublicAssetPage /> },
  // Ganti password (SPEC §15): dibuka dari menu akun di sidebar; wajib setelah password direset staf SHD (T40).
  {
    path: '/ganti-password',
    element: (
      <RequireAuth>
        <ChangePasswordPage />
      </RequireAuth>
    ),
  },
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
      { path: '/pengaturan', element: <SettingsPage /> },
      { path: '/pengaturan/data-master', element: <LegacyMasterRedirect /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
