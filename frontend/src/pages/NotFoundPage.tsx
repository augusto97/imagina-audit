import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import Layout from '@/components/layout/Layout'

export default function NotFoundPage() {
  return (
    <Layout>
      <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
        <h1 className="text-6xl font-bold text-[var(--accent-primary)]">404</h1>
        <p className="mt-4 text-lg text-[var(--text-secondary)]">Página no encontrada</p>
        <Link to="/" className="mt-6">
          <Button variant="outline">
            <ArrowLeft className="h-4 w-4" strokeWidth={1.5} />
            Volver al inicio
          </Button>
        </Link>
      </div>
    </Layout>
  )
}
