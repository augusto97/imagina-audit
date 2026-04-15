import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="border-t border-[var(--border-default)] bg-[var(--bg-secondary)]">
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center gap-2 text-center text-sm text-[var(--text-tertiary)]">
          <p>
            &copy; {new Date().getFullYear()}{' '}
            <a
              href="https://imaginawp.com"
              target="_blank"
              rel="noopener noreferrer"
              className="text-[var(--text-secondary)] hover:text-[var(--accent-primary)] transition-colors"
            >
              Imagina WP
            </a>
            {' '}&middot; Especialistas exclusivos en WordPress
          </p>
          <div className="flex items-center gap-3 text-xs">
            <span>15 años de experiencia en WordPress</span>
            <span>&middot;</span>
            <Link to="/admin" className="text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] transition-colors">Admin</Link>
          </div>
        </div>
      </div>
    </footer>
  )
}
