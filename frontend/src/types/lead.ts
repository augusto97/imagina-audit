/**
 * Mirror del payload de GET /admin/leads.php y POST /admin/leads-bulk.php.
 */

export interface Lead {
  id: string
  url: string
  domain: string
  leadName: string | null
  leadEmail: string | null
  leadWhatsapp: string | null
  leadCompany: string | null
  globalScore: number
  globalLevel: string
  isWordPress: boolean
  isPinned: boolean
  hasSnapshot: boolean
  createdAt: string
  hasContactInfo: boolean
}

export interface LeadsSummary {
  total: number
  withContact: number
  critical: number
  wordpress: number
  pinned: number
  withSnapshot: number
  thisWeek: number
}

export interface LeadsResponse {
  leads: Lead[]
  total: number
  page: number
  limit: number
  totalPages: number
  summary: LeadsSummary
}

export interface LeadsQueryParams {
  page?: number
  limit?: number
  filter?: 'all' | 'with_contact' | 'critical' | 'warning' | 'this_week' | 'this_month'
  sort?: 'date_desc' | 'date_asc' | 'score_asc' | 'score_desc' | 'domain_asc'
  search?: string
  wp?: 'any' | 'yes' | 'no'
  snapshot?: 'any' | 'yes' | 'no'
  pinned?: 'any' | 'yes' | 'no'
}

export interface BulkActionResult {
  processed: number
  skipped: number
  action: 'delete' | 'pin' | 'unpin'
}
