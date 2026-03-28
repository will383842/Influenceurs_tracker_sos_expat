import api from './client';

export type SondageStatus = 'draft' | 'active' | 'closed';
export type QuestionType = 'single' | 'multiple' | 'open' | 'scale';

export interface SondageQuestion {
  id: number;
  sondage_id: number;
  text: string;
  type: QuestionType;
  options: string[] | null;
  sort_order: number;
}

export interface Sondage {
  id: number;
  external_id: string;
  title: string;
  description: string | null;
  status: SondageStatus;
  language: string;
  closes_at: string | null;
  synced_to_blog: boolean;
  last_synced_at: string | null;
  questions: SondageQuestion[];
  created_at: string;
  updated_at: string;
}

export interface PaginatedSondages {
  data: Sondage[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

export interface SondageFormData {
  title: string;
  description: string | null;
  status: SondageStatus;
  language: string;
  closes_at: string | null;
  questions: Array<{
    text: string;
    type: QuestionType;
    options?: string[];
  }>;
}

// ── Option result for stats ──────────────────────────────────────
export interface OptionResult {
  label: string;
  count: number;
}

export interface QuestionResult {
  id: number;
  text: string;
  type: QuestionType;
  total_responses: number;
  options?: OptionResult[];
  open_answers?: string[];
  avg_score?: number;
}

export interface SondageResultats {
  sondage_id: string; // external_id
  responses_count: number;
  completion_rate: number;
  questions: QuestionResult[];
}

// ── API calls ────────────────────────────────────────────────────

export const fetchSondages = (params?: { status?: string; language?: string; page?: number }) =>
  api.get<PaginatedSondages>('/sondages', { params });

export const fetchSondage = (id: number) =>
  api.get<Sondage>(`/sondages/${id}`);

export const createSondage = (data: SondageFormData) =>
  api.post<Sondage>('/sondages', data);

export const updateSondage = (id: number, data: Partial<SondageFormData>) =>
  api.put<Sondage>(`/sondages/${id}`, data);

export const deleteSondage = (id: number) =>
  api.delete(`/sondages/${id}`);

export const syncSondageToBlog = (id: number) =>
  api.post<{ message: string }>(`/sondages/${id}/sync`);

export const fetchSondageResultats = (id: number) =>
  api.get<SondageResultats>(`/sondages/${id}/resultats`);
