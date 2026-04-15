import { motion } from 'framer-motion'
import Layout from '@/components/layout/Layout'
import AuditForm from '@/components/audit/AuditForm'
import ScanningAnimation from '@/components/audit/ScanningAnimation'
import { useAuditStore } from '@/store/auditStore'
import { MODULE_EMOJIS, MODULE_NAMES } from '@/lib/constants'

const moduleIds = ['security', 'performance', 'seo', 'wordpress', 'mobile', 'infrastructure', 'conversion', 'backups']

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.1 },
  },
}

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.5 } },
}

export default function HomePage() {
  const { status } = useAuditStore()

  // Mostrar animación de escaneo si está en progreso
  if (status === 'scanning') {
    return <ScanningAnimation />
  }

  return (
    <Layout>
      {/* Hero Section */}
      <section className="hero-gradient relative overflow-hidden">
        <div className="mx-auto max-w-4xl px-4 pb-16 pt-16 sm:px-6 sm:pt-24 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7 }}
            className="text-center"
          >
            <h1 className="text-3xl font-bold tracking-tight text-[var(--text-primary)] sm:text-4xl lg:text-5xl">
              Auditoría Gratuita de tu{' '}
              <span className="text-[var(--accent-primary)]">WordPress</span>
            </h1>
            <p className="mx-auto mt-4 max-w-2xl text-base text-[var(--text-secondary)] sm:text-lg">
              Descubre en 30 segundos qué tan seguro, rápido y optimizado está tu sitio web
            </p>
          </motion.div>

          {/* Formulario */}
          <motion.div
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.2 }}
            className="mt-10"
          >
            <AuditForm />
          </motion.div>
        </div>
      </section>

      {/* Features Grid — 8 módulos */}
      <section className="py-16">
        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
          <motion.h2
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            className="mb-8 text-center text-xl font-semibold text-[var(--text-primary)] sm:text-2xl"
          >
            Analizamos 8 áreas clave de tu sitio
          </motion.h2>

          <motion.div
            variants={containerVariants}
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true }}
            className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4"
          >
            {moduleIds.map((id) => (
              <motion.div
                key={id}
                variants={itemVariants}
                className="glass-card flex flex-col items-center gap-2 p-4 text-center transition-all hover:border-[var(--border-hover)] hover:shadow-lg"
              >
                <span className="text-2xl">{MODULE_EMOJIS[id]}</span>
                <span className="text-sm font-medium text-[var(--text-primary)]">
                  {MODULE_NAMES[id]}
                </span>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* Trust Bar */}
      <section className="border-t border-[var(--border-default)] py-12">
        <div className="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
          <p className="text-sm text-[var(--text-tertiary)]">
            Con la experiencia de 15 años de maestría exclusiva en WordPress
          </p>
          <div className="mt-4 flex flex-wrap items-center justify-center gap-6 text-xs text-[var(--text-tertiary)]">
            {['Elementor', 'WP Rocket', 'Rank Math', 'Gravity Forms', 'Cloudflare', 'WooCommerce'].map((tool) => (
              <span key={tool} className="rounded-full border border-[var(--border-default)] px-3 py-1">
                {tool}
              </span>
            ))}
          </div>
        </div>
      </section>
    </Layout>
  )
}
