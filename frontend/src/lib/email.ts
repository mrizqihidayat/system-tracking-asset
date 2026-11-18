/** Samarkan email untuk ditampilkan: huruf pertama + ••• + domain, mis. `a•••@inalum.id`. */
export function maskEmail(email: string): string {
  const at = email.lastIndexOf('@')
  if (at < 1) return '•••'
  return `${email[0]}•••${email.slice(at)}`
}
