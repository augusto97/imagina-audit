import { useCallback, useState } from 'react'
import { Download, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getLevelLabel, formatCurrency } from '@/lib/utils'
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
      const H = 297
      const m = 15
      const cW = W - m * 2
      let y = 0
      let pageNum = 0

      const C = {
        accent: [12, 192, 223] as const,
        dark: [15, 23, 42] as const,
        text: [51, 65, 85] as const,
        muted: [148, 163, 184] as const,
        light: [241, 245, 249] as const,
        white: [255, 255, 255] as const,
        critical: [239, 68, 68] as const,
        warning: [245, 158, 11] as const,
        good: [16, 185, 129] as const,
        excellent: [5, 150, 105] as const,
      }

      type RGB = [number, number, number]
      const lc = (level: string): RGB => {
        const map: Record<string, RGB> = { critical: [...C.critical], warning: [...C.warning], good: [...C.good], excellent: [...C.excellent], info: [...C.muted], unknown: [...C.muted] }
        return map[level] || [...C.muted]
      }

      const ensure = (need: number) => {
        if (y + need > H - 20) { newPage(); return true }
        return false
      }

      const newPage = () => {
        doc.addPage()
        y = m
        pageNum++
        // Footer on previous page
        addFooter(pageNum)
      }

      const addFooter = (page: number) => {
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(7)
        doc.setTextColor(...C.muted)
        doc.text(`Imagina Audit · ${result.domain} · ${new Date(result.timestamp).toLocaleDateString('es-CO')}`, m, H - 8)
        doc.text(`Página ${page}`, W - m, H - 8, { align: 'right' })
      }

      const sectionTitle = (title: string) => {
        ensure(15)
        doc.setFillColor(...C.accent)
        doc.rect(m, y, cW, 8, 'F')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(11)
        doc.setTextColor(...C.white)
        doc.text(title, m + 4, y + 5.5)
        y += 12
      }

      const wrap = (text: string, maxW: number, maxLines = 3): string[] => {
        return doc.splitTextToSize(text, maxW).slice(0, maxLines) as string[]
      }

      // ========== PORTADA ==========
      pageNum = 1
      doc.setFillColor(...C.accent)
      doc.rect(0, 0, W, 55, 'F')
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(26)
      doc.setTextColor(...C.white)
      doc.text('Informe de Auditoría Web', W / 2, 22, { align: 'center' })
      doc.setFontSize(12)
      doc.setFont('helvetica', 'normal')
      doc.text('Análisis completo de seguridad, rendimiento y SEO', W / 2, 33, { align: 'center' })
      doc.setFontSize(10)
      doc.text(result.url, W / 2, 45, { align: 'center' })

      // Score central
      y = 80
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(14)
      doc.setTextColor(...C.dark)
      doc.text(result.domain, W / 2, y, { align: 'center' })

      y += 15
      const sc = lc(result.globalLevel)
      doc.setDrawColor(...sc)
      doc.setLineWidth(2.5)
      doc.circle(W / 2, y + 20, 22)
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(32)
      doc.setTextColor(...sc)
      doc.text(String(result.globalScore), W / 2, y + 20, { align: 'center', baseline: 'middle' })
      doc.setFontSize(10)
      doc.setTextColor(...C.muted)
      doc.text('/100', W / 2, y + 32, { align: 'center' })
      y += 42
      doc.setFontSize(16)
      doc.setTextColor(...sc)
      doc.text(getLevelLabel(result.globalLevel), W / 2, y, { align: 'center' })

      // Issue badges
      y += 12
      doc.setFontSize(9)
      const badges = [
        { text: `${result.totalIssues.critical} Críticos`, color: [...C.critical] as RGB },
        { text: `${result.totalIssues.warning} Importantes`, color: [...C.warning] as RGB },
        { text: `${result.totalIssues.good} Correctos`, color: [...C.good] as RGB },
      ].filter(b => parseInt(b.text) > 0)

      const bW = 32
      const bStart = W / 2 - (badges.length * (bW + 3)) / 2
      badges.forEach((b, i) => {
        const bx = bStart + i * (bW + 3)
        doc.setFillColor(...b.color)
        doc.roundedRect(bx, y - 3.5, bW, 7, 2, 2, 'F')
        doc.setTextColor(...C.white)
        doc.setFontSize(8)
        doc.text(b.text, bx + bW / 2, y + 0.5, { align: 'center' })
      })

      // Module scores grid
      y += 18
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(10)
      doc.setTextColor(...C.dark)
      doc.text('Puntuación por Módulo', W / 2, y, { align: 'center' })
      y += 8

      const cols = 4
      const colW = cW / cols
      result.modules.forEach((mod, i) => {
        const col = i % cols
        const row = Math.floor(i / cols)
        const x = m + col * colW
        const my = y + row * 14
        const msc = lc(mod.level)

        doc.setFillColor(...C.light)
        doc.roundedRect(x + 1, my, colW - 2, 12, 1.5, 1.5, 'F')
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(7.5)
        doc.setTextColor(...C.text)
        doc.text(mod.name, x + 3, my + 5)
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(10)
        doc.setTextColor(...msc)
        doc.text(mod.score != null ? String(mod.score) : '—', x + colW - 4, my + 7, { align: 'right' })
      })

      y += Math.ceil(result.modules.length / cols) * 14 + 5

      // Fecha y duración
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(8)
      doc.setTextColor(...C.muted)
      doc.text(`Fecha: ${new Date(result.timestamp).toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' })}  ·  Duración: ${(result.scanDurationMs / 1000).toFixed(1)}s  ·  WordPress: ${result.isWordPress ? 'Sí' : 'No'}`, W / 2, y, { align: 'center' })
      addFooter(1)

      // ========== RESUMEN EJECUTIVO ==========
      newPage()
      sectionTitle('Resumen Ejecutivo')

      // Problemas críticos primero
      const allIssues: Array<{ name: string; module: string; level: string; desc: string; rec: string }> = []
      result.modules.forEach(mod => {
        mod.metrics.forEach(metric => {
          if (metric.level === 'critical' || metric.level === 'warning') {
            allIssues.push({
              name: metric.name,
              module: mod.name,
              level: metric.level,
              desc: metric.description,
              rec: metric.recommendation,
            })
          }
        })
      })

      if (allIssues.length === 0) {
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(10)
        doc.setTextColor(...C.good)
        doc.text('No se detectaron problemas críticos. El sitio está en buen estado general.', m, y)
        y += 10
      } else {
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(9)
        doc.setTextColor(...C.text)
        doc.text(`Se detectaron ${allIssues.length} problemas que requieren atención:`, m, y)
        y += 7

        allIssues.slice(0, 20).forEach((issue, idx) => {
          ensure(18)
          const ic = lc(issue.level)

          // Number + dot
          doc.setFillColor(...ic)
          doc.circle(m + 3, y + 1.5, 1.5, 'F')
          doc.setFont('helvetica', 'bold')
          doc.setFontSize(8)
          doc.setTextColor(...C.dark)
          doc.text(`${idx + 1}. ${issue.name}`, m + 7, y + 2.5)

          // Module badge
          doc.setFontSize(6.5)
          doc.setTextColor(...C.muted)
          doc.text(`[${issue.module}]`, m + cW, y + 2.5, { align: 'right' })
          y += 5

          // Description
          doc.setFont('helvetica', 'normal')
          doc.setFontSize(7.5)
          doc.setTextColor(...C.text)
          const descLines = wrap(issue.desc, cW - 10, 2)
          doc.text(descLines, m + 7, y)
          y += descLines.length * 3.2

          // Recommendation
          if (issue.rec) {
            doc.setTextColor(...C.accent)
            doc.setFontSize(7)
            const recLines = wrap('→ ' + issue.rec, cW - 10, 2)
            doc.text(recLines, m + 7, y)
            y += recLines.length * 3 + 2
          } else {
            y += 2
          }
        })
      }

      // ========== DETALLE POR MÓDULO ==========
      for (const mod of result.modules) {
        newPage()
        sectionTitle(`${mod.name} — ${mod.score != null ? mod.score + '/100' : 'N/A'}`)

        // Summary
        doc.setFont('helvetica', 'italic')
        doc.setFontSize(8.5)
        doc.setTextColor(...C.text)
        const sumLines = wrap(mod.summary, cW)
        doc.text(sumLines, m, y)
        y += sumLines.length * 3.5 + 4

        // Each metric
        for (const metric of mod.metrics) {
          ensure(22)

          // Dot + name + value
          const dotC = lc(metric.level)
          doc.setFillColor(...dotC)
          doc.circle(m + 3, y + 1.5, 1.5, 'F')

          doc.setFont('helvetica', 'bold')
          doc.setFontSize(8.5)
          doc.setTextColor(...C.dark)
          doc.text(metric.name, m + 7, y + 2.5)

          doc.setFont('helvetica', 'normal')
          doc.setFontSize(7.5)
          doc.setTextColor(...C.muted)
          const dv = metric.displayValue.length > 40 ? metric.displayValue.slice(0, 37) + '...' : metric.displayValue
          doc.text(dv, m + cW, y + 2.5, { align: 'right' })
          y += 5

          // Description
          doc.setFont('helvetica', 'normal')
          doc.setFontSize(7)
          doc.setTextColor(...C.text)
          const mDescLines = wrap(metric.description, cW - 8, 3)
          doc.text(mDescLines, m + 7, y)
          y += mDescLines.length * 3

          // Recommendation (if problem)
          if (metric.recommendation && (metric.level === 'critical' || metric.level === 'warning')) {
            doc.setFontSize(7)
            doc.setTextColor(...C.accent)
            const mRecLines = wrap('Cómo corregir: ' + metric.recommendation, cW - 8, 2)
            doc.text(mRecLines, m + 7, y)
            y += mRecLines.length * 3
          }

          y += 3
        }
      }

      // ========== IMPACTO ECONÓMICO ==========
      if (result.economicImpact.estimatedMonthlyLoss > 0) {
        ensure(25)
        sectionTitle('Impacto Económico Estimado')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(14)
        doc.setTextColor(...C.warning)
        doc.text(`~${formatCurrency(result.economicImpact.estimatedMonthlyLoss, result.economicImpact.currency)}/mes`, m, y + 2)
        y += 8
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(8)
        doc.setTextColor(...C.text)
        const impLines = wrap(result.economicImpact.explanation, cW)
        doc.text(impLines, m, y)
        y += impLines.length * 3.5 + 5
      }

      // ========== MAPA DE SOLUCIONES ==========
      if (result.solutionMap.length > 0) {
        newPage()
        sectionTitle('Plan de Soluciones — Imagina WP')

        doc.setFillColor(...C.light)
        doc.rect(m, y, cW, 6, 'F')
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(7)
        doc.setTextColor(...C.text)
        doc.text('Problema', m + 7, y + 4)
        doc.text('Solución', m + cW * 0.55, y + 4)
        doc.text('Plan', m + cW - 2, y + 4, { align: 'right' })
        y += 8

        for (const sol of result.solutionMap.slice(0, 25)) {
          ensure(12)
          const dotC = lc(sol.level)
          doc.setFillColor(...dotC)
          doc.circle(m + 3, y + 1, 1.2, 'F')

          doc.setFont('helvetica', 'normal')
          doc.setFontSize(6.5)
          doc.setTextColor(...C.dark)
          const pLines = wrap(sol.problem, cW * 0.5 - 10, 2)
          doc.text(pLines, m + 7, y + 1.5)

          doc.setTextColor(...C.text)
          const sLines = wrap(sol.solution, cW * 0.4, 2)
          doc.text(sLines, m + cW * 0.55, y + 1.5)

          doc.setFontSize(6)
          doc.setTextColor(...C.muted)
          doc.text(sol.includedInPlan, m + cW - 2, y + 1.5, { align: 'right' })

          y += Math.max(pLines.length, sLines.length) * 3 + 3
        }
      }

      // ========== CONTRAPORTADA ==========
      newPage()
      y = H / 2 - 30
      doc.setFillColor(...C.accent)
      doc.rect(0, y - 20, W, 80, 'F')

      doc.setFont('helvetica', 'bold')
      doc.setFontSize(22)
      doc.setTextColor(...C.white)
      doc.text('Todos estos problemas', W / 2, y, { align: 'center' })
      doc.text('tienen solución', W / 2, y + 10, { align: 'center' })

      doc.setFont('helvetica', 'normal')
      doc.setFontSize(10)
      doc.text('Imagina WP · Especialistas exclusivos en WordPress', W / 2, y + 25, { align: 'center' })
      doc.text('15 años de experiencia · imaginawp.com', W / 2, y + 32, { align: 'center' })

      y = H / 2 + 35
      doc.setTextColor(...C.dark)
      doc.setFontSize(9)
      doc.text('Generado por Imagina Audit · ' + new Date().toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }), W / 2, y, { align: 'center' })
      addFooter(pageNum)

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
