import { motion } from 'framer-motion'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
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
      <Card className="overflow-hidden">
        <CardHeader>
          <CardTitle>Mapa de Soluciones</CardTitle>
          <p className="text-sm text-[var(--text-secondary)]">
            Cada problema detectado tiene una solución que Imagina WP incluye en sus planes de soporte.
          </p>
        </CardHeader>
        <CardContent className="px-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-10"></TableHead>
                <TableHead>Problema</TableHead>
                <TableHead className="hidden sm:table-cell">Solución Imagina WP</TableHead>
                <TableHead className="text-right">Plan</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {solutions.map((item, idx) => (
                <TableRow key={idx}>
                  <TableCell><SemaphoreIcon level={item.level} /></TableCell>
                  <TableCell>
                    <span className="text-[var(--text-secondary)]">{item.problem}</span>
                    <span className="block sm:hidden text-xs text-[var(--text-primary)] mt-1">{item.solution}</span>
                  </TableCell>
                  <TableCell className="hidden sm:table-cell text-[var(--text-primary)]">{item.solution}</TableCell>
                  <TableCell className="text-right"><Badge variant="outline">{item.includedInPlan}</Badge></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </motion.div>
  )
}
