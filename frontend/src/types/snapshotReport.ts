/**
 * Formato estructurado del endpoint GET /admin/snapshot-report.php
 * Mirror de SnapshotReportBuilder::build() en PHP.
 */

export interface SnapshotIssue {
  severity: 'critical' | 'warning' | 'info'
  title: string
  action: string
}

export interface SnapshotMeta {
  siteName: string
  siteUrl: string
  generatedAt: string
  generatorVersion: string
  uploadedAt: string
}

export interface PluginItem {
  slug: string
  name: string
  version: string
  author: string
  uri: string
  description: string
  isActive: boolean
  hasUpdate: boolean
  updateVersion: string | null
  autoUpdate: boolean
  networkActive: boolean
  requiresWp: string
  requiresPhp: string
  vulnerabilities: Array<{
    name: string
    cve: string
    severity: string
    cvssScore: number | null
    fixedIn: string | null
    unfixed: boolean
  }>
  vulnerabilityStatus: 'safe' | 'outdated' | 'vulnerable' | 'outdated_vulnerable'
}

export interface SnapshotReport {
  overview: {
    site: Record<string, string | boolean>
    kpis: Record<string, number | string | boolean | Record<string, boolean>>
  }
  environment: {
    wordpress: Record<string, string | boolean>
    php: Record<string, string | number | boolean | string[] | Record<string, boolean>>
    database: Record<string, string>
    server: Record<string, string | boolean>
    issues: SnapshotIssue[]
  }
  plugins: {
    summary: Record<string, number>
    items: PluginItem[]
    muPlugins: Array<{ name: string; version: string; author: string; file: string }>
    dropins: Array<{ name: string; version: string; author: string; file: string }>
  }
  themes: {
    summary: Record<string, number | string | boolean>
    items: Array<Record<string, string | boolean | null>>
    issues: SnapshotIssue[]
  }
  security: {
    summary: { critical: number; warning: number; good: number }
    items: Array<{ id: string; label: string; value: unknown; status: string; note: string }>
    issues: SnapshotIssue[]
  }
  performance: {
    summary: Record<string, string | boolean>
    issues: SnapshotIssue[]
  }
  database: {
    summary: Record<string, number | string>
    topTables: Array<Record<string, string | number>>
    myisamTables: Array<{ name: string; rows: number }>
    postCounts: Record<string, number>
    issues: SnapshotIssue[]
  }
  cron: {
    summary: Record<string, number | boolean>
    topHooks: Array<{ hook: string; count: number }>
    overdue: Array<Record<string, string>>
    upcoming: Array<Record<string, string | number>>
    issues: SnapshotIssue[]
  }
  media: {
    summary: Record<string, string | number>
    byType: Array<{ group: string; count: number }>
    mimeDetail: Array<{ mime: string; count: number }>
    issues: SnapshotIssue[]
  }
  users: {
    summary: { totalUsers: number; administrators: number; uniqueRoles: number }
    roles: Array<{ slug: string; name: string; userCount: number; capCount: number }>
    issues: SnapshotIssue[]
  }
  content: {
    summary: Record<string, number>
    postTypes: Array<Record<string, string | boolean>>
    taxonomies: Array<Record<string, string | boolean>>
    topRestNs: Array<{ namespace: string; routes: number }>
    issues: SnapshotIssue[]
  }
}

export interface SnapshotReportResponse {
  meta: SnapshotMeta
  report: SnapshotReport
}
