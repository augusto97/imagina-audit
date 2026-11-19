import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App'
import './i18n'      // inicializa i18next antes del render
import './index.css'

// Tras una actualización los chunks con hash de la versión anterior ya no
// existen: una pestaña abierta que pida uno recibe un error de carga.
// Recargamos UNA vez (guardia en sessionStorage para no entrar en bucle).
window.addEventListener('vite:preloadError', (event) => {
  event.preventDefault()
  try {
    const last = Number(sessionStorage.getItem('imagina_preload_reload') || '0')
    if (Date.now() - last < 30000) return
    sessionStorage.setItem('imagina_preload_reload', String(Date.now()))
  } catch { /* sin storage: recargar igual */ }
  window.location.reload()
})

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>
)
