import { Link } from 'react-router-dom'
import { Shield } from 'lucide-react'

export default function Header() {
  return (
    <header className="sticky top-0 z-50 w-full border-b border-[var(--border-default)] bg-[var(--bg-primary)]/90 backdrop-blur-lg">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link to="/" className="flex items-center gap-2 text-[var(--text-primary)] hover:opacity-90 transition-opacity">
          <Shield className="h-7 w-7 text-[var(--accent-primary)]" strokeWidth={1.5} />
          <span className="text-lg font-bold tracking-tight">
            Imagina <span className="text-[var(--accent-primary)]">Audit</span>
          </span>
        </Link>

        <nav className="flex items-center gap-4">
          <a
            href="https://imaginawp.com"
            target="_blank"
            rel="noopener noreferrer"
            className="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors"
          >
            imaginawp.com
          </a>
        </nav>
      </div>
    </header>
  )
}
