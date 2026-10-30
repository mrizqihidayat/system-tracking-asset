import { useEffect } from 'react'
import { useToast } from '../components/ui/toast-context'
import { errorMessage } from './api'

/** Tampilkan toast untuk error query pertama (sekali per error baru), seperti perilaku fetch sebelumnya. */
export function useErrorToast(...errors: unknown[]) {
  const toast = useToast()
  const error = errors.find(Boolean)
  useEffect(() => {
    if (error) toast.error(errorMessage(error))
  }, [error, toast])
}
