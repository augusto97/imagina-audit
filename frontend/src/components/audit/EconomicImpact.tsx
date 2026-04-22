import { motion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { DollarSign, TrendingDown } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { formatCurrency } from '@/lib/utils'

interface EconomicImpactProps {
  estimatedMonthlyLoss: number
  currency: string
  explanation: string
}

export default function EconomicImpact({ estimatedMonthlyLoss, currency, explanation }: EconomicImpactProps) {
  const { t } = useTranslation()
  if (estimatedMonthlyLoss <= 0) return null

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
    >
      <Card className="border-amber-200 bg-amber-50/50">
        <CardContent className="p-6">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100">
              <DollarSign className="h-6 w-6 text-amber-600" strokeWidth={1.5} />
            </div>
            <div className="flex-1">
              <div className="flex items-center gap-2">
                <h3 className="text-lg font-semibold text-[var(--text-primary)]">{t('public.economic_impact_title')}</h3>
                <TrendingDown className="h-4 w-4 text-amber-600" strokeWidth={1.5} />
              </div>
              <p className="mt-1 text-2xl font-bold text-amber-600">
                {t('public.economic_impact_unit', { amount: formatCurrency(estimatedMonthlyLoss, currency) })}
              </p>
              <p className="mt-2 text-sm text-[var(--text-secondary)]">{explanation}</p>
              <p className="mt-1 text-xs text-[var(--text-tertiary)]">
                {t('public.economic_impact_disclaimer')}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </motion.div>
  )
}
