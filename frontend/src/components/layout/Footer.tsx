export default function Footer() {
  return (
    <footer className="border-t border-[var(--border-default)] bg-[var(--bg-primary)]">
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
          <p>15 años de experiencia en WordPress</p>
        </div>
      </div>
    </footer>
  )
}
