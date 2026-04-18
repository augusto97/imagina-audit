import { motion } from 'framer-motion'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import SemaphoreIcon from './SemaphoreIcon'
import type { SolutionItem } from '@/types/audit'

interface SolutionMappingProps {
  solutions: SolutionItem[]
}

export default function SolutionMapping({ solutions }: SolutionMappingProps) {
  if (solutions.length === 0) return null

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
    >
      <Card>
        <CardHeader>
          <CardTitle>Mapa de Soluciones</CardTitle>
          <p className="text-sm text-[var(--text-secondary)]">
            Cada problema detectado tiene una solución que Imagina WP incluye en sus planes de soporte.
          </p>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {solutions.map((item, idx) => (
              <div key={idx} className="flex gap-3 border-b border-[var(--border-default)] pb-3 last:border-0 last:pb-0">
                <div className="shrink-0 mt-0.5"><SemaphoreIcon level={item.level} /></div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm text-[var(--text-secondary)] break-words">{item.problem}</p>
                  <p className="text-sm text-[var(--text-primary)] mt-1 break-words">{item.solution}</p>
                </div>
                <Badge variant="outline" className="shrink-0 self-start">{item.includedInPlan}</Badge>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </motion.div>
  )
}
