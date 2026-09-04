import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

import { App } from '@/app/App'
import '@/app/i18n'
import './index.css'

const container = document.getElementById('root')

if (container === null) {
  throw new Error('Élément racine introuvable.')
}

createRoot(container).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
