import { useSearchParams } from 'react-router-dom'
import { PageHeader } from '../../components/ui/Page'
import { getUser } from '../../lib/auth'
import MasterDataPage from '../master/MasterDataPage'
import { AppearanceTab, ContentTab, SecurityTab } from './SettingsTabs'

type Tab = 'tampilan' | 'konten' | 'keamanan' | 'data-master'

const TABS: { key: Tab; label: string; employeeOnly: boolean }[] = [
  { key: 'tampilan', label: 'Tampilan', employeeOnly: true },
  { key: 'konten', label: 'Konten', employeeOnly: true },
  { key: 'keamanan', label: 'Keamanan', employeeOnly: true },
  { key: 'data-master', label: 'Data Master', employeeOnly: false },
]

/**
 * Pengaturan (T40, SPEC §15). Semua akun bisa membuka halaman ini, tetapi tab Tampilan, Konten, dan Keamanan
 * hanya untuk akun pegawai SHD (API juga menolak 403). Akun bersama hanya melihat Data Master tanpa bagian Akun.
 */
export default function SettingsPage() {
  const [params, setParams] = useSearchParams()
  const isEmployee = getUser()?.source === 'api'
  const tabs = TABS.filter((t) => isEmployee || !t.employeeOnly)
  const tab = (tabs.find((t) => t.key === params.get('tab')) ?? tabs[0]).key

  return (
    <div className="max-w-4xl space-y-5">
      <PageHeader
        title="Pengaturan"
        description={
          isEmployee
            ? 'Atur tampilan, tulisan, keamanan akun, dan data master aplikasi.'
            : 'Kelola data master: gedung, ruangan, departemen, kategori dan jenis aset, serta kategori stok.'
        }
      />

      {tabs.length > 1 && (
        <div className="flex gap-1 overflow-x-auto border-b border-slate-200 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Bagian pengaturan">
          {tabs.map((t) => (
            <button
              key={t.key}
              role="tab"
              aria-selected={tab === t.key}
              onClick={() => setParams({ tab: t.key }, { replace: true })}
              className={`-mb-px min-h-10 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors ${
                tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-900'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>
      )}

      {tab === 'tampilan' && <AppearanceTab />}
      {tab === 'konten' && <ContentTab />}
      {tab === 'keamanan' && <SecurityTab />}
      {tab === 'data-master' && <MasterDataPage />}
    </div>
  )
}
