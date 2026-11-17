import {
  Boxes, ChevronsUpDown, ClipboardCheck, HandHelping, LayoutDashboard, Menu, PackageOpen, Send, Settings, X,
} from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import DropdownMenu from '../ui/DropdownMenu'
import { cancelRoutePrefetch, prefetchRouteOnDwell } from '../../lib/queries/prefetch'
import { getUser, loginId } from '../../lib/auth'
import { usePublicSettings } from '../../lib/settings'
import { useLogout } from '../../lib/useLogout'

// Isi modul mengikuti SPEC §3. Scan QR dibuka dari tombol di Dashboard (keputusan pengguna), tidak di menu.
// Pengaturan (T40) terbuka untuk semua akun; tab di dalamnya yang dibatasi.
const menu = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/daftar-aset', label: 'Daftar Aset', icon: Boxes },
  { to: '/peminjaman', label: 'Peminjaman', icon: HandHelping },
  { to: '/kelengkapan-ruangan', label: 'Kelengkapan Ruangan', icon: ClipboardCheck },
  { to: '/permintaan', label: 'Permintaan ke Unit Lain', icon: Send },
  { to: '/stok', label: 'Stok Habis Pakai', icon: PackageOpen },
  { to: '/pengaturan', label: 'Pengaturan', icon: Settings },
]

const itemClass = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${
    isActive ? 'bg-white font-semibold text-brand-800' : 'font-medium text-white/85 hover:bg-white/10 hover:text-white'
  }`

/** Logo kecil + judul web dari Pengaturan (T40). */
export function Brand({ compact = false }: { compact?: boolean }) {
  const settings = usePublicSettings()

  return (
    <div className="flex items-center gap-3">
      <span className={`flex shrink-0 items-center justify-center rounded-lg bg-white ${compact ? 'h-8 w-8' : 'h-10 w-10'}`}>
        <img src={settings.favicon_url ?? '/icon.png'} alt="" className={compact ? 'w-5' : 'w-7'} />
      </span>
      <span className="min-w-0 leading-tight">
        <span className={`block truncate font-bold ${compact ? 'text-base' : 'text-[17px]'}`}>{settings.site_title}</span>
        {!compact && <span className="block text-xs text-white/70">Sistem Informasi Aset</span>}
      </span>
    </div>
  )
}

export default function AppLayout() {
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const user = getUser()

  const prefetchProps = (to: string) => ({
    onMouseEnter: () => prefetchRouteOnDwell(queryClient, to),
    onMouseLeave: cancelRoutePrefetch,
    onFocus: () => prefetchRouteOnDwell(queryClient, to),
    onBlur: cancelRoutePrefetch,
  })

  const logout = useLogout()

  return (
    <div className="min-h-screen lg:pl-60">
      {open && <div className="fixed inset-0 z-20 bg-slate-900/40 lg:hidden" onClick={() => setOpen(false)} />}
      <aside
        className={`fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-rail px-3 py-5 text-white transition-transform duration-200 lg:w-60 lg:translate-x-0 ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between pb-6">
          <Brand />
          <button
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white/85 hover:bg-white/10 lg:hidden"
            onClick={() => setOpen(false)}
            aria-label="Tutup menu"
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto">
          {menu.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              onClick={() => setOpen(false)}
              {...prefetchProps(to)}
              className={itemClass}
            >
              <Icon size={18} className="shrink-0" />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="mt-4 border-t border-white/15 pt-4">
          <div className="flex items-center gap-3 px-1">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-semibold">
              {(user?.name ?? '?').charAt(0).toUpperCase()}
            </span>
            <div className="min-w-0 flex-1 leading-tight">
              <p className="truncate text-sm font-semibold">{user?.name ?? '-'}</p>
              <p className="truncate text-xs text-white/70">
                {user ? (user.source === 'api' ? loginId(user) : `Akun eksternal ${loginId(user)}`) : 'Admin Aset SHD'}
              </p>
            </div>
            {/* Menu akun: Ganti password atau Keluar. */}
            <DropdownMenu
              label="Menu akun"
              trigger={<ChevronsUpDown size={18} />}
              items={[
                {
                  label: 'Ganti password',
                  onClick: () => {
                    setOpen(false)
                    navigate('/ganti-password')
                  },
                },
                { label: 'Keluar', onClick: logout, danger: true },
              ]}
              triggerClassName="min-h-10! w-10 shrink-0 px-0! border-0! bg-transparent! text-white/85! hover:bg-white/10! hover:text-white!"
            />
          </div>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col">
        <header className="sticky top-0 z-10 flex h-14 items-center gap-3 bg-rail px-4 text-white lg:hidden">
          <button
            onClick={() => setOpen(true)}
            aria-label="Buka menu"
            className="-ml-1 flex h-10 w-10 items-center justify-center rounded-lg hover:bg-white/10"
          >
            <Menu size={22} />
          </button>
          <Brand compact />
        </header>
        <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
