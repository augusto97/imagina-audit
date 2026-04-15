import { useCallback } from 'react'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getLevelLabel } from '@/lib/utils'
import type { AuditResult } from '@/types/audit'

interface PdfReportProps {
  result: AuditResult
}

export default function PdfReport({ result }: PdfReportProps) {
  const generatePdf = useCallback(async () => {
    // Importar html2pdf.js dinámicamente
    const html2pdf = (await import('html2pdf.js')).default

    // Crear el HTML del informe
    const container = document.createElement('div')
    container.innerHTML = buildPdfHtml(result)
    container.style.position = 'absolute'
    container.style.left = '-9999px'
    document.body.appendChild(container)

    try {
      await html2pdf()
        .set({
          margin: [10, 10, 10, 10],
          filename: `auditoria-${result.domain}-${new Date().toISOString().slice(0, 10)}.pdf`,
          image: { type: 'jpeg', quality: 0.95 },
          html2canvas: { scale: 2, useCORS: true },
          jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        } as Record<string, unknown>)
        .from(container)
        .save()
    } finally {
      document.body.removeChild(container)
    }
  }, [result])

  return (
    <Button variant="outline" size="sm" onClick={generatePdf}>
      <Download className="h-4 w-4" strokeWidth={1.5} />
      Descargar PDF
    </Button>
  )
}

/** Genera el HTML para el PDF */
function buildPdfHtml(result: AuditResult): string {
  const levelColor = (level: string) => {
    const colors: Record<string, string> = {
      critical: '#EF4444', warning: '#F59E0B', good: '#10B981',
      excellent: '#059669', info: '#6B7280', unknown: '#6B7280',
    }
    return colors[level] || '#6B7280'
  }

  const modulesHtml = result.modules.map((m) => `
    <div style="margin-bottom: 16px; page-break-inside: avoid;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <h3 style="margin: 0; font-size: 16px; color: #1F2937;">${m.name}</h3>
        <span style="font-weight: bold; color: ${levelColor(m.level)}; font-size: 18px;">${m.score ?? '-'}/100</span>
      </div>
      <p style="color: #6B7280; font-size: 12px; margin: 4px 0;">${m.summary}</p>
      ${m.metrics.map((metric) => `
        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #E5E7EB;">
          <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${levelColor(metric.level)};"></span>
          <span style="flex: 1; font-size: 12px; color: #374151;">${metric.name}</span>
          <span style="font-size: 11px; color: #9CA3AF;">${metric.displayValue}</span>
        </div>
      `).join('')}
    </div>
  `).join('')

  const solutionsHtml = result.solutionMap.slice(0, 15).map((s) => `
    <tr>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB;">
        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${levelColor(s.level)};"></span>
      </td>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB; font-size: 11px; color: #374151;">${s.problem}</td>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB; font-size: 11px; color: #1F2937;">${s.solution}</td>
    </tr>
  `).join('')

  return `
    <div style="font-family: 'Inter', Arial, sans-serif; color: #1F2937; max-width: 700px;">
      <!-- Portada -->
      <div style="text-align: center; padding: 40px 20px; page-break-after: always;">
        <h1 style="font-size: 28px; color: #3B82F6; margin-bottom: 8px;">Imagina Audit</h1>
        <h2 style="font-size: 20px; font-weight: normal; color: #6B7280;">Informe de Auditoría Web</h2>
        <div style="margin: 40px 0;">
          <p style="font-size: 14px; color: #9CA3AF;">Sitio analizado</p>
          <p style="font-size: 22px; font-weight: bold; color: #1F2937;">${result.domain}</p>
          <p style="font-size: 12px; color: #9CA3AF; margin-top: 4px;">${result.url}</p>
        </div>
        <div style="margin: 40px auto; width: 120px; height: 120px; border-radius: 50%; border: 6px solid ${levelColor(result.globalLevel)}; display: flex; align-items: center; justify-content: center;">
          <div style="text-align: center;">
            <span style="font-size: 36px; font-weight: bold; color: ${levelColor(result.globalLevel)};">${result.globalScore}</span>
            <br>
            <span style="font-size: 12px; color: #9CA3AF;">/100</span>
          </div>
        </div>
        <p style="font-size: 16px; font-weight: 600; color: ${levelColor(result.globalLevel)};">${getLevelLabel(result.globalLevel)}</p>
        <p style="font-size: 12px; color: #9CA3AF; margin-top: 20px;">Fecha: ${new Date(result.timestamp).toLocaleDateString('es-CO')}</p>
      </div>

      <!-- Resumen de módulos -->
      <div style="padding: 20px;">
        <h2 style="font-size: 20px; margin-bottom: 16px; color: #1F2937;">Resumen por Módulos</h2>
        ${modulesHtml}
      </div>

      <!-- Soluciones -->
      ${result.solutionMap.length > 0 ? `
        <div style="padding: 20px; page-break-before: always;">
          <h2 style="font-size: 20px; margin-bottom: 16px; color: #1F2937;">Plan de Soluciones</h2>
          <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
              <tr style="background: #F3F4F6;">
                <th style="padding: 8px; text-align: left; width: 30px;"></th>
                <th style="padding: 8px; text-align: left; color: #6B7280;">Problema</th>
                <th style="padding: 8px; text-align: left; color: #6B7280;">Solución Imagina WP</th>
              </tr>
            </thead>
            <tbody>
              ${solutionsHtml}
            </tbody>
          </table>
        </div>
      ` : ''}

      <!-- Footer -->
      <div style="padding: 20px; text-align: center; margin-top: 30px; border-top: 1px solid #E5E7EB;">
        <p style="font-size: 14px; color: #3B82F6; font-weight: 600;">Imagina WP</p>
        <p style="font-size: 12px; color: #9CA3AF;">Especialistas exclusivos en WordPress &middot; 15 años de experiencia</p>
        <p style="font-size: 11px; color: #9CA3AF; margin-top: 4px;">imaginawp.com</p>
      </div>
    </div>
  `
}
