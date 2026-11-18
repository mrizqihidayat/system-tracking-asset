import { useEffect, useState } from 'react'

/** Hitung mundur detik sampai kode boleh dikirim ulang. */
export function useCountdown() {
  const [seconds, setSeconds] = useState(0)

  useEffect(() => {
    if (seconds <= 0) return
    const timer = setTimeout(() => setSeconds((s) => s - 1), 1000)
    return () => clearTimeout(timer)
  }, [seconds])

  return [seconds, setSeconds] as const
}
