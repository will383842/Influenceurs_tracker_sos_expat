// ============================================================
// TYPES — Synchronized with Laravel backend (fusion)
// ============================================================

// 19 contact types (was 6)
export type ContactType =
  | 'influenceur' | 'group_admin' | 'blogger' | 'partner' | 'school' | 'association'
  | 'chatter' | 'tiktoker' | 'youtuber' | 'instagramer' | 'backlink'
  | 'travel_agency' | 'real_estate' | 'translator' | 'insurer'
  | 'enterprise' | 'press' | 'lawyer' | 'job_board';

// 14 pipeline statuses (was 6)
export type PipelineStatus =
  | 'new' | 'prospect' | 'contacted1' | 'contacted2' | 'contacted3'
  | 'contacted' | 'negotiating' | 'replied' | 'meeting'
  | 'active' | 'signed' | 'refused' | 'inactive' | 'lost';

// Keep backward compat alias
export type Status = PipelineStatus;

// 11 platforms (was 10)
export type Platform =
  | 'instagram' | 'tiktok' | 'youtube' | 'linkedin'
  | 'x' | 'facebook' | 'pinterest' | 'podcast' | 'blog' | 'newsletter' | 'website';

// 12 channels (was 6)
export type ContactChannel =
  | 'email' | 'instagram' | 'linkedin' | 'whatsapp' | 'phone'
  | 'tiktok' | 'youtube' | 'facebook' | 'x' | 'telegram' | 'contact_form' | 'other';

// 8 interaction results (was 5)
export type ContactResult =
  | 'sent' | 'opened' | 'clicked' | 'replied' | 'refused' | 'registered' | 'no_answer' | 'bounced';

export type ReminderStatus = 'pending' | 'dismissed' | 'done';

export type UserRole = 'admin' | 'manager' | 'member' | 'researcher';

// ============================================================
// MODELS
// ============================================================

export interface TeamMember {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  contact_types?: ContactType[] | null;
  territories?: string[] | null;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

export interface Contact {
  id: number;
  influenceur_id: number;
  user_id: number;
  date: string;
  channel: ContactChannel;
  direction: 'inbound' | 'outbound';
  result: ContactResult;
  subject: string | null;
  sender: string | null;
  message: string | null;
  reply: string | null;
  notes: string | null;
  email_opened_at: string | null;
  email_clicked_at: string | null;
  template_used: string | null;
  rank: number;
  user?: Pick<TeamMember, 'id' | 'name'>;
  created_at: string;
}

export interface Reminder {
  id: number;
  influenceur_id: number;
  due_date: string;
  status: ReminderStatus;
  dismissed_by: number | null;
  dismissed_at: string | null;
  notes: string | null;
  days_elapsed?: number;
}

export interface Influenceur {
  id: number;
  contact_type: ContactType;
  name: string;
  company: string | null;
  position: string | null;
  handle: string | null;
  avatar_url: string | null;
  platforms: Platform[];
  primary_platform: Platform;
  followers: number | null;
  followers_secondary: Record<string, number> | null;
  niche: string | null;
  country: string | null;
  language: string | null;
  timezone: string | null;
  email: string | null;
  phone: string | null;
  profile_url: string | null;
  profile_url_domain: string | null;
  website_url: string | null;
  status: PipelineStatus;
  deal_value_cents: number;
  deal_probability: number;
  expected_close_date: string | null;
  assigned_to: number | null;
  assigned_to_user?: Pick<TeamMember, 'id' | 'name'> | null;
  reminder_days: number;
  reminder_active: boolean;
  last_contact_at: string | null;
  partnership_date: string | null;
  notes: string | null;
  tags: string[] | null;
  score: number;
  source: string | null;
  // Scraped data
  scraper_status: 'completed' | 'failed' | 'pending' | 'skipped' | null;
  scraped_at: string | null;
  scraped_emails: string[] | null;
  scraped_phones: string[] | null;
  scraped_social: Record<string, string> | null;
  scraped_addresses: string[] | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  contacts?: Contact[];
  pending_reminder?: Reminder | null;
  days_elapsed?: number;
  is_valid_for_objective?: boolean;
}

export interface PaginatedInfluenceurs {
  data: Influenceur[];
  next_cursor: string | null;
  has_more: boolean;
}

export interface InfluenceurFilters {
  contact_type?: ContactType;
  status?: PipelineStatus;
  platform?: Platform;
  assigned_to?: number;
  has_reminder?: boolean;
  country?: string;
  language?: string;
  search?: string;
}

// ============================================================
// STATS
// ============================================================

export interface StatsData {
  total: number;
  byStatus: Record<string, number>;
  byContactType?: Record<string, number>;
  responseRate: number;
  conversionRate: number;
  newThisMonth: number;
  active: number;
  contactsEvolution: { week: string; count: number }[];
  byPlatform: { primary_platform: string; count: number }[];
  responseByPlatform: { platform: string; rate: number; total: number }[];
  teamActivity: { user_id: number; count: number; user: Pick<TeamMember, 'id' | 'name'> }[];
  funnel: { stage: string; count: number }[];
  recentActivity: ActivityLogEntry[];
}

export interface ActivityLogEntry {
  id: number;
  user_id: number;
  influenceur_id: number | null;
  action: string;
  details: Record<string, unknown> | null;
  is_manual: boolean;
  manual_note: string | null;
  contact_type: ContactType | null;
  created_at: string;
  user?: Pick<TeamMember, 'id' | 'name'>;
  influenceur?: Pick<Influenceur, 'id' | 'name'> | null;
}

// ============================================================
// AI RESEARCH
// ============================================================

export interface AiResearchSession {
  id: number;
  user_id: number;
  contact_type: ContactType;
  country: string;
  language: string;
  claude_response: string | null;
  perplexity_response: string | null;
  tavily_response: string | null;
  parsed_contacts: ParsedContact[] | null;
  excluded_domains: string[] | null;
  contacts_found: number;
  contacts_imported: number;
  contacts_duplicates: number;
  tokens_used: number;
  cost_cents: number;
  status: 'pending' | 'running' | 'completed' | 'failed';
  started_at: string | null;
  completed_at: string | null;
  error_message: string | null;
  created_at: string;
}

export interface ParsedContact {
  name: string;
  email: string | null;
  phone: string | null;
  profile_url: string | null;
  profile_url_domain: string | null;
  country: string;
  contact_type: string;
  platforms: string[];
  followers: number | null;
  notes: string | null;
  source: string;
  web_source: string | null;
  reliability_score: number;        // 1-5 (5 = fully verified)
  reliability_reason: string | null;
  has_email: boolean;
  has_phone: boolean;
  has_url: boolean;
}

// ============================================================
// EMAIL TEMPLATES
// ============================================================

export interface EmailTemplate {
  id: number;
  contact_type: ContactType;
  language: string;
  name: string;
  subject: string;
  body: string;
  variables: string[] | null;
  is_active: boolean;
  step: number;
  delay_days: number;
  created_at: string;
  updated_at: string;
}

export interface OutreachMessage {
  subject: string;
  body: string;
  template_id: number;
  template_name: string;
  step: number;
  influenceur_id?: number;
  influenceur_name?: string;
  email?: string | null;
}

// ============================================================
// CONTENT ENGINE
// ============================================================

export interface ContentMetric {
  id: number;
  date: string;
  landing_pages: number;
  articles: number;
  indexed_pages: number;
  top10_positions: number;
  position_zero: number;
  ai_cited: number;
  daily_visits: number;
  calls_generated: number;
  revenue_cents: number;
  search_console_data: Record<string, unknown> | null;
  analytics_data: Record<string, unknown> | null;
}

export interface ContentMetricsResponse {
  metrics: ContentMetric[];
  summary: Omit<ContentMetric, 'id' | 'date' | 'search_console_data' | 'analytics_data' | 'created_at' | 'updated_at'> | null;
  trends: {
    visits_growth: number;
    calls_growth: number;
    revenue_growth: number;
  } | null;
}

// ============================================================
// JOURNAL
// ============================================================

export interface JournalToday {
  total_actions: number;
  manual_entries: ActivityLogEntry[];
  by_action: Record<string, number>;
  by_contact_type: Record<string, number>;
}

export interface JournalWeekDay {
  date: string;
  total: number;
  manual_count: number;
}

// ============================================================
// OBJECTIVES & REMINDERS (existing, kept)
// ============================================================

export interface ReminderWithInfluenceur extends Reminder {
  influenceur: Pick<Influenceur, 'id' | 'name' | 'status' | 'last_contact_at' | 'primary_platform' | 'contact_type'> & {
    assigned_to_user?: Pick<TeamMember, 'id' | 'name'> | null;
  };
}

export interface Objective {
  id: number;
  user_id: number;
  contact_type?: ContactType | null;
  continent: string | null;
  countries: string[] | null;
  language: string | null;
  niche: string | null;
  target_count: number;
  deadline: string;
  is_active: boolean;
  created_by: number;
  created_at: string;
}

export interface ObjectiveWithProgress extends Objective {
  current_count: number;
  percentage: number;
  days_remaining: number;
  is_overdue?: boolean;
}

export interface ObjectiveProgress {
  has_objectives: boolean;
  objectives: ObjectiveWithProgress[];
  global_progress: {
    total_target: number;
    total_current: number;
    percentage: number;
  };
}

export interface ResearcherStat {
  id: number;
  name: string;
  email: string;
  last_login_at: string | null;
  total_created: number;
  valid_count: number;
  created_today: number;
  created_this_week: number;
  created_this_month: number;
  objectives: ObjectiveWithProgress[];
}

// ============================================================
// COVERAGE
// ============================================================

export interface CoverageData {
  by_country: { country: string; total: number }[];
  by_language: { language: string; total: number }[];
  by_continent: { continent: string; total: number; countries_count: number; countries: { country: string; total: number }[] }[];
  countries_covered: number;
  languages_covered: number;
  total_influenceurs: number;
}
