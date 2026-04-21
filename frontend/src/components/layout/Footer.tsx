import { Link } from 'react-router-dom'
import { Separator } from '@/components/ui/separator'
import { useConfigStore } from '@/store/configStore'

export default function Footer() {
  const { companyName, companyUrl, footer } = useConfigStore((s) => s.config)

  return (
    <footer className="border-t border-[var(--border-default)] bg-[var(--bg-secondary)]">
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center gap-3 text-center text-sm text-[var(--text-tertiary)]">
          <p>
            &copy; {new Date().getFullYear()}{' '}
            <a
              href={companyUrl || 'https://imaginawp.com'}
              target="_blank"
              rel="noopener noreferrer"
              className="text-[var(--text-secondary)] hover:text-[var(--accent-primary)] transition-colors font-medium"
            >
              {companyName || 'Imagina WP'}
            </a>
            {footer?.tagline ? <> &middot; {footer.tagline}</> : null}
          </p>
          <Separator className="max-w-xs" />
          <div className="flex items-center gap-3 text-xs">
            {footer?.experienceText && <span>{footer.experienceText}</span>}
            {footer?.experienceText && (footer?.privacyUrl || <span>&middot;</span>) && <span>&middot;</span>}
            {footer?.privacyUrl && footer?.privacyText && (
              <>
                <a
                  href={footer.privacyUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] transition-colors"
                >
                  {footer.privacyText}
                </a>
                <span>&middot;</span>
              </>
            )}
            <Link to="/admin" className="text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] transition-colors">
              Admin
            </Link>
          </div>
        </div>
      </div>
    </footer>
  )
}
