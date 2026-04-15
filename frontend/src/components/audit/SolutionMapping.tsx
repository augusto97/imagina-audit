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
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border-default)] text-left text-xs text-[var(--text-tertiary)]">
                  <th className="pb-3 pr-4">Estado</th>
                  <th className="pb-3 pr-4">Problema</th>
                  <th className="pb-3 pr-4">Solución Imagina WP</th>
                  <th className="pb-3">Plan</th>
                </tr>
              </thead>
              <tbody>
                {solutions.map((item, idx) => (
                  <tr key={idx} className="border-b border-[var(--border-default)] last:border-0">
                    <td className="py-3 pr-4">
                      <SemaphoreIcon level={item.level} />
                    </td>
                    <td className="py-3 pr-4 text-[var(--text-secondary)]">{item.problem}</td>
                    <td className="py-3 pr-4 text-[var(--text-primary)]">{item.solution}</td>
                    <td className="py-3">
                      <Badge variant="outline">{item.includedInPlan}</Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </motion.div>
  )
}
