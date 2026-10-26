/** Aksi yang bisa dikerjakan langsung dari hasil scan (kunci API, SPEC §6). */
export const SCAN_ACTIONS: Record<string, string> = {
  pinjam: 'Pinjam',
  terima_kembali: 'Terima kembali',
  pindah_lokasi: 'Pindah lokasi',
  catat_kerusakan: 'Catat kerusakan',
  catat_kehilangan: 'Catat kehilangan',
  catat_tidak_layak_pakai: 'Catat tidak layak pakai',
  tandai_ditemukan: 'Tandai ditemukan',
}
