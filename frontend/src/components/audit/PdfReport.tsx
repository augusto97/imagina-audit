import { useCallback, useState } from 'react'
import { Download, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getLevelLabel } from '@/lib/utils'
import type { AuditResult } from '@/types/audit'

interface PdfReportProps {
  result: AuditResult
}

export default function PdfReport({ result }: PdfReportProps) {
  const [generating, setGenerating] = useState(false)

  const generatePdf = useCallback(async () => {
    setGenerating(true)
    try {
      const { jsPDF } = await import('jspdf')
      const doc = new jsPDF({ unit: 'mm', format: 'a4' })
      const W = 210
      const margin = 15
      const contentW = W - margin * 2
      let y = 0

      const colors = {
        primary: [12, 192, 223] as [number, number, number],
        dark: [15, 23, 42] as [number, number, number],
        gray: [100, 116, 139] as [number, number, number],
        lightGray: [148, 163, 184] as [number, number, number],
        white: [255, 255, 255] as [number, number, number],
        bg: [248, 250, 251] as [number, number, number],
        critical: [239, 68, 68] as [number, number, number],
        warning: [245, 158, 11] as [number, number, number],
        good: [16, 185, 129] as [number, number, number],
      }

      const levelColor = (level: string): [number, number, number] => {
        const map: Record<string, [number, number, number]> = {
          critical: colors.critical, warning: colors.warning,
          good: colors.good, excellent: [5, 150, 105],
          info: colors.gray, unknown: colors.gray,
        }
        return map[level] || colors.gray
      }

      // Asegura que haya espacio; si no, nueva página
      const ensureSpace = (needed: number) => {
        if (y + needed > 280) {
          doc.addPage()
          y = margin
        }
      }

      // ===== PORTADA =====
      // Franja superior turquesa
      doc.setFillColor(...colors.primary)
      doc.rect(0, 0, W, 50, 'F')

      doc.setFont('helvetica', 'bold')
      doc.setFontSize(28)
      doc.setTextColor(...colors.white)
      doc.text('Imagina Audit', W / 2, 25, { align: 'center' })
      doc.setFontSize(13)
      doc.setFont('helvetica', 'normal')
      doc.text('Informe de Auditoría Web', W / 2, 35, { align: 'center' })

      // Dominio
      y = 70
      doc.setFontSize(11)
      doc.setTextColor(...colors.lightGray)
      doc.text('Sitio analizado', W / 2, y, { align: 'center' })
      y += 8
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(22)
      doc.setTextColor(...colors.dark)
      doc.text(result.domain, W / 2, y, { align: 'center' })
      y += 7
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(9)
      doc.setTextColor(...colors.lightGray)
      doc.text(result.url, W / 2, y, { align: 'center' })

      // Círculo de score
      y += 20
      const circleColor = levelColor(result.globalLevel)
      doc.setDrawColor(...circleColor)
      doc.setLineWidth(2)
      doc.circle(W / 2, y + 18, 18)
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(28)
      doc.setTextColor(...circleColor)
      doc.text(String(result.globalScore), W / 2, y + 18, { align: 'center', baseline: 'middle' })
      doc.setFontSize(9)
      doc.setTextColor(...colors.lightGray)
      doc.text('/100', W / 2, y + 28, { align: 'center' })

      y += 45
      doc.setFontSize(14)
      doc.setTextColor(...circleColor)
      doc.setFont('helvetica', 'bold')
      doc.text(getLevelLabel(result.globalLevel), W / 2, y, { align: 'center' })

      // Badges de issues
      y += 12
      doc.setFontSize(10)
      const badges = []
      if (result.totalIssues.critical > 0) badges.push({ text: `${result.totalIssues.critical} Críticos`, color: colors.critical })
      if (result.totalIssues.warning > 0) badges.push({ text: `${result.totalIssues.warning} Importantes`, color: colors.warning })
      if (result.totalIssues.good > 0) badges.push({ text: `${result.totalIssues.good} Correctos`, color: colors.good })

      const badgeW = 35
      const badgeStart = W / 2 - (badges.length * (badgeW + 4)) / 2
      badges.forEach((b, i) => {
        const bx = badgeStart + i * (badgeW + 4)
        doc.setFillColor(...b.color)
        doc.roundedRect(bx, y - 4, badgeW, 7, 2, 2, 'F')
        doc.setTextColor(...colors.white)
        doc.setFontSize(8)
        doc.text(b.text, bx + badgeW / 2, y, { align: 'center' })
      })

      // Fecha
      y += 16
      doc.setFontSize(9)
      doc.setTextColor(...colors.lightGray)
      doc.text(
        `Fecha: ${new Date(result.timestamp).toLocaleDateString('es-CO')}  ·  Duración: ${(result.scanDurationMs / 1000).toFixed(1)}s`,
        W / 2, y, { align: 'center' }
      )

      // ===== MÓDULOS =====
      doc.addPage()
      y = margin

      // Título sección
      doc.setFillColor(...colors.primary)
      doc.rect(margin, y, contentW, 8, 'F')
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(12)
      doc.setTextColor(...colors.white)
      doc.text('Detalle por Módulos', margin + 4, y + 5.5)
      y += 14

      for (const mod of result.modules) {
        ensureSpace(25 + mod.metrics.length * 7)

        // Header del módulo
        doc.setFillColor(...colors.bg)
        doc.roundedRect(margin, y, contentW, 10, 2, 2, 'F')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(11)
        doc.setTextColor(...colors.dark)
        doc.text(mod.name, margin + 3, y + 6.5)

        const scoreColor = levelColor(mod.level)
        doc.setTextColor(...scoreColor)
        doc.text(`${mod.score ?? '-'}/100`, margin + contentW - 3, y + 6.5, { align: 'right' })
        y += 13

        // Métricas
        for (const metric of mod.metrics) {
          ensureSpace(8)
          const dotColor = levelColor(metric.level)
          doc.setFillColor(...dotColor)
          doc.circle(margin + 4, y + 1, 1.5, 'F')

          doc.setFont('helvetica', 'normal')
          doc.setFontSize(9)
          doc.setTextColor(...colors.dark)
          doc.text(metric.name, margin + 9, y + 2.5)

          doc.setTextColor(...colors.lightGray)
          doc.setFontSize(8)
          const displayVal = metric.displayValue.length > 45 ? metric.displayValue.slice(0, 42) + '...' : metric.displayValue
          doc.text(displayVal, margin + contentW - 2, y + 2.5, { align: 'right' })

          y += 6.5
        }

        // Línea separadora
        y += 3
        doc.setDrawColor(...(colors.bg))
        doc.setLineWidth(0.3)
        doc.line(margin, y, margin + contentW, y)
        y += 5
      }

      // ===== SOLUCIONES =====
      if (result.solutionMap.length > 0) {
        ensureSpace(40)
        if (y > 50) {
          doc.addPage()
          y = margin
        }

        doc.setFillColor(...colors.primary)
        doc.rect(margin, y, contentW, 8, 'F')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(12)
        doc.setTextColor(...colors.white)
        doc.text('Plan de Soluciones', margin + 4, y + 5.5)
        y += 14

        // Encabezado tabla
        doc.setFillColor(...colors.bg)
        doc.rect(margin, y, contentW, 7, 'F')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(8)
        doc.setTextColor(...colors.gray)
        doc.text('Problema', margin + 8, y + 4.5)
        doc.text('Solución Imagina WP', margin + contentW / 2 + 5, y + 4.5)
        y += 9

        for (const sol of result.solutionMap.slice(0, 15)) {
          ensureSpace(14)
          const dotColor = levelColor(sol.level)
          doc.setFillColor(...dotColor)
          doc.circle(margin + 3, y + 1, 1.5, 'F')

          doc.setFont('helvetica', 'normal')
          doc.setFontSize(7.5)

          doc.setTextColor(...colors.dark)
          const probLines = doc.splitTextToSize(sol.problem, contentW / 2 - 10)
          doc.text(probLines.slice(0, 2), margin + 8, y + 2)

          doc.setTextColor(...colors.gray)
          const solLines = doc.splitTextToSize(sol.solution, contentW / 2 - 5)
          doc.text(solLines.slice(0, 2), margin + contentW / 2 + 5, y + 2)

          const lineHeight = Math.max(probLines.length, solLines.length, 1) * 3.5
          y += Math.max(lineHeight, 6) + 2
        }
      }

      // ===== FOOTER =====
      ensureSpace(30)
      y += 10
      doc.setDrawColor(...colors.bg)
      doc.setLineWidth(0.5)
      doc.line(margin, y, margin + contentW, y)
      y += 8

      doc.setFont('helvetica', 'bold')
      doc.setFontSize(13)
      doc.setTextColor(...colors.primary)
      doc.text('Imagina WP', W / 2, y, { align: 'center' })
      y += 6
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(9)
      doc.setTextColor(...colors.lightGray)
      doc.text('Especialistas exclusivos en WordPress · 15 años de experiencia', W / 2, y, { align: 'center' })
      y += 5
      doc.text('imaginawp.com', W / 2, y, { align: 'center' })

      // Guardar
      doc.save(`auditoria-${result.domain}-${new Date().toISOString().slice(0, 10)}.pdf`)
    } catch (err) {
      console.error('Error generando PDF:', err)
    } finally {
      setGenerating(false)
    }
  }, [result])

  return (
    <Button variant="outline" size="sm" onClick={generatePdf} disabled={generating} title="Descargar PDF" className="h-8 px-2 sm:px-3">
      {generating
        ? <Loader2 className="h-4 w-4 animate-spin" strokeWidth={1.5} />
        : <Download className="h-4 w-4" strokeWidth={1.5} />
      }
      <span className="hidden sm:inline">{generating ? 'Generando...' : 'Descargar PDF'}</span>
    </Button>
  )
}
