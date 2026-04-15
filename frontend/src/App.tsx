import { Routes, Route } from 'react-router-dom'
import { Toaster } from 'sonner'
import HomePage from './pages/HomePage'
import ResultsPage from './pages/ResultsPage'
import NotFoundPage from './pages/NotFoundPage'

function App() {
  return (
    <>
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/results/:auditId" element={<ResultsPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
      <Toaster
        position="top-right"
        toastOptions={{
          style: {
            background: 'var(--bg-secondary)',
            border: '1px solid var(--border-default)',
            color: 'var(--text-primary)',
          },
        }}
      />
    </>
  )
}

export default App
