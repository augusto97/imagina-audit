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
      const html2pdf = (await import('html2pdf.js')).default

      // Crear contenedor VISIBLE (html2canvas necesita que sea renderizable)
      const container = document.createElement('div')
      container.innerHTML = buildPdfHtml(result)
      container.style.cssText = 'position:fixed;top:0;left:0;width:210mm;z-index:-9999;background:#fff;'
      document.body.appendChild(container)

      // Esperar a que el navegador renderice el contenido
      await new Promise((r) => setTimeout(r, 300))

      await html2pdf()
        .set({
          margin: [8, 8, 8, 8],
          filename: `auditoria-${result.domain}-${new Date().toISOString().slice(0, 10)}.pdf`,
          image: { type: 'jpeg', quality: 0.95 },
          html2canvas: {
            scale: 2,
            useCORS: true,
            logging: false,
            windowWidth: 794, // A4 en px a 96dpi
          },
          jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        } as Record<string, unknown>)
        .from(container)
        .save()

      document.body.removeChild(container)
    } catch (err) {
      console.error('Error generando PDF:', err)
    } finally {
      setGenerating(false)
    }
  }, [result])

  return (
    <Button variant="outline" size="sm" onClick={generatePdf} disabled={generating}>
      {generating
        ? <Loader2 className="h-4 w-4 animate-spin" strokeWidth={1.5} />
        : <Download className="h-4 w-4" strokeWidth={1.5} />
      }
      {generating ? 'Generando...' : 'Descargar PDF'}
    </Button>
  )
}

/** Genera el HTML para el PDF — SIN flexbox, solo estilos que html2canvas soporta */
function buildPdfHtml(result: AuditResult): string {
  const levelColor = (level: string) => {
    const colors: Record<string, string> = {
      critical: '#EF4444', warning: '#F59E0B', good: '#10B981',
      excellent: '#059669', info: '#6B7280', unknown: '#6B7280',
    }
    return colors[level] || '#6B7280'
  }

  const modulesHtml = result.modules.map((m) => `
    <div style="margin-bottom: 20px; padding: 16px; border: 1px solid #E5E7EB; border-radius: 8px;">
      <table style="width: 100%; margin-bottom: 8px;">
        <tr>
          <td style="text-align: left;"><h3 style="margin: 0; font-size: 15px; color: #1F2937;">${m.name}</h3></td>
          <td style="text-align: right; font-weight: bold; color: ${levelColor(m.level)}; font-size: 18px;">${m.score ?? '-'}/100</td>
        </tr>
      </table>
      <p style="color: #6B7280; font-size: 11px; margin: 4px 0 12px 0;">${m.summary}</p>
      ${m.metrics.map((metric) => `
        <table style="width: 100%; border-bottom: 1px solid #F3F4F6; margin-bottom: 2px;">
          <tr>
            <td style="width: 16px; padding: 5px 0;">
              <div style="width: 10px; height: 10px; border-radius: 50%; background: ${levelColor(metric.level)}; display: inline-block;"></div>
            </td>
            <td style="font-size: 11px; color: #374151; padding: 5px 8px;">${metric.name}</td>
            <td style="font-size: 10px; color: #9CA3AF; text-align: right; padding: 5px 0; white-space: nowrap;">${metric.displayValue}</td>
          </tr>
        </table>
      `).join('')}
    </div>
  `).join('')

  const solutionsHtml = result.solutionMap.slice(0, 15).map((s) => `
    <tr>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB; width: 20px;">
        <div style="width: 8px; height: 8px; border-radius: 50%; background: ${levelColor(s.level)}; display: inline-block;"></div>
      </td>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB; font-size: 10px; color: #374151;">${s.problem}</td>
      <td style="padding: 6px 8px; border-bottom: 1px solid #E5E7EB; font-size: 10px; color: #1F2937;">${s.solution}</td>
    </tr>
  `).join('')

  return `
    <div style="font-family: Arial, Helvetica, sans-serif; color: #1F2937; width: 100%; background: #fff; padding: 20px;">

      <!-- Portada -->
      <div style="text-align: center; padding: 30px 20px 40px 20px;">
        <h1 style="font-size: 28px; color: #3B82F6; margin: 0 0 4px 0;">Imagina Audit</h1>
        <h2 style="font-size: 18px; font-weight: normal; color: #6B7280; margin: 0;">Informe de Auditoría Web</h2>

        <div style="margin: 30px 0;">
          <p style="font-size: 13px; color: #9CA3AF; margin: 0;">Sitio analizado</p>
          <p style="font-size: 22px; font-weight: bold; color: #1F2937; margin: 4px 0;">${result.domain}</p>
          <p style="font-size: 11px; color: #9CA3AF; margin: 0;">${result.url}</p>
        </div>

        <div style="margin: 30px auto; width: 120px; height: 120px; border-radius: 50%; border: 6px solid ${levelColor(result.globalLevel)}; text-align: center; line-height: 120px;">
          <span style="font-size: 36px; font-weight: bold; color: ${levelColor(result.globalLevel)}; vertical-align: middle;">${result.globalScore}</span>
          <span style="font-size: 12px; color: #9CA3AF; vertical-align: middle;">/100</span>
        </div>

        <p style="font-size: 16px; font-weight: 600; color: ${levelColor(result.globalLevel)}; margin: 8px 0;">${getLevelLabel(result.globalLevel)}</p>

        <table style="margin: 16px auto;">
          <tr>
            ${result.totalIssues.critical > 0 ? `<td style="padding: 2px 8px; background: #FEE2E2; color: #DC2626; border-radius: 12px; font-size: 11px; font-weight: 600;">${result.totalIssues.critical} Críticos</td>` : ''}
            ${result.totalIssues.warning > 0 ? `<td style="padding: 2px 8px; background: #FEF3C7; color: #D97706; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 4px;">${result.totalIssues.warning} Importantes</td>` : ''}
            ${result.totalIssues.good > 0 ? `<td style="padding: 2px 8px; background: #D1FAE5; color: #059669; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 4px;">${result.totalIssues.good} Correctos</td>` : ''}
          </tr>
        </table>

        <p style="font-size: 11px; color: #9CA3AF; margin-top: 20px;">Fecha: ${new Date(result.timestamp).toLocaleDateString('es-CO')} &middot; Duración: ${(result.scanDurationMs / 1000).toFixed(1)}s</p>
      </div>

      <!-- Resumen de módulos -->
      <div style="padding: 0;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0; color: #1F2937; border-bottom: 2px solid #3B82F6; padding-bottom: 8px;">Detalle por Módulos</h2>
        ${modulesHtml}
      </div>

      <!-- Soluciones -->
      ${result.solutionMap.length > 0 ? `
        <div style="padding: 20px 0;">
          <h2 style="font-size: 18px; margin: 0 0 16px 0; color: #1F2937; border-bottom: 2px solid #3B82F6; padding-bottom: 8px;">Plan de Soluciones</h2>
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #F3F4F6;">
                <th style="padding: 8px; text-align: left; width: 30px;"></th>
                <th style="padding: 8px; text-align: left; color: #6B7280; font-size: 11px;">Problema</th>
                <th style="padding: 8px; text-align: left; color: #6B7280; font-size: 11px;">Solución Imagina WP</th>
              </tr>
            </thead>
            <tbody>
              ${solutionsHtml}
            </tbody>
          </table>
        </div>
      ` : ''}

      <!-- Footer -->
      <div style="text-align: center; margin-top: 30px; padding-top: 16px; border-top: 1px solid #E5E7EB;">
        <p style="font-size: 14px; color: #3B82F6; font-weight: 600; margin: 0;">Imagina WP</p>
        <p style="font-size: 11px; color: #9CA3AF; margin: 4px 0 0 0;">Especialistas exclusivos en WordPress &middot; 15 años de experiencia</p>
        <p style="font-size: 10px; color: #9CA3AF; margin: 4px 0 0 0;">imaginawp.com</p>
      </div>
    </div>
  `
}
