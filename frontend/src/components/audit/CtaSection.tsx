import { motion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { MessageCircle, ExternalLink } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useAuditStore } from '@/store/auditStore'

export default function CtaSection() {
  const { t } = useTranslation()
  const config = useAuditStore((s) => s.config)

  const whatsappUrl = `https://wa.me/${config.companyWhatsapp.replace(/[^0-9+]/g, '')}?text=${encodeURIComponent(t('public.cta_whatsapp_message'))}`

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
    >
      <Card className="overflow-hidden border-[var(--accent-primary)]/30">
        <div className="bg-gradient-to-br from-[#F0FDFE] to-white">
          <CardContent className="p-8 sm:p-12 text-center">
            <h2 className="text-2xl font-bold text-[var(--text-primary)] sm:text-3xl">
              {config.ctaTitle}
            </h2>
            <p className="mx-auto mt-4 max-w-2xl text-[var(--text-secondary)]">
              {config.ctaDescription}
            </p>

            <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
              <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
                <Button size="xl" variant="success" className="w-full sm:w-auto">
                  <MessageCircle className="h-5 w-5" strokeWidth={1.5} />
                  {config.ctaButtonWhatsappText}
                </Button>
              </a>
              <a href={config.companyPlansUrl} target="_blank" rel="noopener noreferrer">
                <Button size="xl" variant="outline" className="w-full sm:w-auto">
                  <ExternalLink className="h-5 w-5" strokeWidth={1.5} />
                  {config.ctaButtonPlansText}
                </Button>
              </a>
            </div>

            <div className="mt-8 flex flex-wrap items-center justify-center gap-4 text-xs text-[var(--text-secondary)]">
              {['Elementor', 'WP Rocket', 'Rank Math', 'Cloudflare', 'WooCommerce'].map((tool) => (
                <span key={tool} className="rounded-full border border-[var(--border-default)] bg-white px-3 py-1 font-medium shadow-sm">
                  {tool}
                </span>
              ))}
            </div>

            <p className="mt-4 text-sm text-[var(--text-tertiary)]"
              dangerouslySetInnerHTML={{ __html: t('public.cta_experience').replace(/<yellow>/g, '<span class="highlight-yellow">').replace(/<\/yellow>/g, '</span>') }}
            />
          </CardContent>
        </div>
      </Card>
    </motion.div>
  )
}
