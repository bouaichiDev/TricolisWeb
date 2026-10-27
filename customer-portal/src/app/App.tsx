import { QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { BrowserRouter } from 'react-router-dom'

import { AuthProvider } from '@/app/auth/AuthProvider'
import { createQueryClient } from '@/app/queryClient'
import { AppRouter } from '@/app/router/AppRouter'
import { Toaster } from '@/shared/components/ui/sonner'

export function App() {
  const [queryClient] = useState(createQueryClient)

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <AppRouter />
          <Toaster position="top-right" richColors closeButton />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
