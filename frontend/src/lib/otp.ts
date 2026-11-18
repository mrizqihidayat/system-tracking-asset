export const OTP_LENGTH = 6

/** Hanya angka, dipotong sampai panjang kode (untuk ketikan, tempel, dan isi otomatis dari SMS). */
export const otpDigits = (text: string, max = OTP_LENGTH) => text.replace(/\D/g, '').slice(0, max)

interface OtpEdit {
  value: string
  /** Kotak yang menerima fokus berikutnya. */
  focus: number
}

/**
 * Masukkan angka di kotak `index`. Satu angka menimpa kotak itu; beberapa angka (tempel/SMS) mengisi dari kotak
 * itu ke kanan. Kotak kosong di depan diisi lebih dulu supaya kode selalu berurutan tanpa celah.
 */
export function otpInsert(value: string, index: number, text: string, length = OTP_LENGTH): OtpEdit {
  const digits = otpDigits(text, length)
  if (!digits) return { value, focus: index }

  const pos = Math.min(index, value.length)
  const next = digits.length === 1 ? value.slice(0, pos) + digits + value.slice(pos + 1) : value.slice(0, pos) + digits
  return { value: next.slice(0, length), focus: Math.min(pos + digits.length, length - 1) }
}

/** Backspace/Delete di kotak `index`: hapus angkanya, atau angka sebelumnya bila kotak itu kosong. */
export function otpBackspace(value: string, index: number): OtpEdit {
  if (index < value.length) return { value: value.slice(0, index) + value.slice(index + 1), focus: index }
  if (index === 0) return { value, focus: 0 }
  return { value: value.slice(0, index - 1) + value.slice(index), focus: index - 1 }
}
