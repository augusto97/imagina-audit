import { Link } from 'react-router-dom'
import { Shield, GitCompareArrows, ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'

export default function Header() {
  return (
    <header className="sticky top-0 z-50 w-full border-b border-[var(--border-default)] bg-white/90 backdrop-blur-lg">
      <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link to="/" className="flex items-center gap-2 text-[var(--text-primary)] hover:opacity-90 transition-opacity">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8]">
            <Shield className="h-4.5 w-4.5 text-white" strokeWidth={2} />
          </div>
          <span className="text-base font-bold tracking-tight">
            Imagina <span className="text-[var(--accent-primary)]">Audit</span>
          </span>
        </Link>

        <nav className="flex items-center gap-1">
          <Link to="/compare">
            <Button variant="ghost" size="sm" className="text-[var(--text-secondary)]">
              <GitCompareArrows className="h-4 w-4" strokeWidth={1.5} />
              <span className="hidden sm:inline">Comparar</span>
            </Button>
          </Link>
          <a href="https://imaginawp.com" target="_blank" rel="noopener noreferrer">
            <Button variant="ghost" size="sm" className="text-[var(--text-secondary)]">
              <ExternalLink className="h-3.5 w-3.5" strokeWidth={1.5} />
              <span className="hidden sm:inline">imaginawp.com</span>
            </Button>
          </a>
        </nav>
      </div>
    </header>
  )
}
