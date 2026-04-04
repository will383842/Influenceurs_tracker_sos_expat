import api from './client';

// ============================================================
// TYPES
// ============================================================

export interface RssFeed {
  id: number;
  name: string;
  url: string;
  language: string;
  country: string | null;
  category: string | null;
  active: boolean;
  fetch_interval_hours: number;
  last_fetched_at: string | null;
  items_fetched_count: number;
  relevance_threshold: number;
  notes: string | null;
  items_pending_count?: number;
  items_published_count?: number;
  items_irrelevant_count?: number;
  items_failed_count?: number;
  items_skipped_count?: number;
  items_total_count?: number;
}

export interface RssFeedItem {
  id: number;
  feed_id: number;
  feed?: { id: number; name: string };
  guid: string;
  title: string;
  url: string;
  source_name: string | null;
  published_at: string | null;
  original_excerpt: string | null;
  language: string;
  country: string | null;
  relevance_score: number | null;
  relevance_category: string | null;
  relevance_reason: string | null;
  status: 'pending' | 'generating' | 'published' | 'skipped' | 'irrelevant' | 'failed';
  similarity_score: number | null;
  blog_article_uuid: string | null;
  generated_at: string | null;
  error_message: string | null;
}

export interface CreateRssFeedData {
  name: string;
  url: string;
  language: string;
  country?: string;
  category?: string;
  active?: boolean;
  fetch_interval_hours?: number;
  relevance_threshold?: number;
  notes?: string;
}

export interface NewsQuotaSettings {
  quota: number;
  generated_today: number;
  last_reset_date: string;
}

export interface NewsItemFilters {
  status?: string;
  feed_id?: number;
  relevance_score_min?: number;
  date_from?: string;
  page?: number;
  sort?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface NewsStats {
  items_by_status: Record<string, number>;
  active_feeds: number;
  quota: { daily_limit: number; generated_today: number; remaining: number };
}

export interface NewsProgress {
  status?: string;
  completed?: number;
  total?: number;
  current_title?: string;
  log?: Array<{ type: string; msg: string }>;
}

// ============================================================
// FEEDS
// ============================================================

export const getRssFeeds = () =>
  api.get<{ data: RssFeed[] }>('/news/feeds');

export const createRssFeed = (data: CreateRssFeedData) =>
  api.post<{ data: RssFeed }>('/news/feeds', data);

export const updateRssFeed = (id: number, data: Partial<CreateRssFeedData>) =>
  api.put<{ data: RssFeed }>(`/news/feeds/${id}`, data);

export const deleteRssFeed = (id: number) =>
  api.delete(`/news/feeds/${id}`);

export const fetchFeedNow = (id: number) =>
  api.post<{ message: string }>(`/news/feeds/${id}/fetch-now`);

// ============================================================
// SETTINGS / QUOTA
// ============================================================

export const getNewsSettings = () =>
  api.get<{ data: NewsQuotaSettings }>('/news/settings');

export const updateNewsSettings = (quota: number) =>
  api.put<{ data: NewsQuotaSettings }>('/news/settings', { quota });

// ============================================================
// ITEMS
// ============================================================

export const getNewsItems = (filters?: NewsItemFilters) =>
  api.get<PaginatedResponse<RssFeedItem>>('/news/items', { params: filters });

export const generateItem = (id: number) =>
  api.post<{ message: string }>(`/news/items/${id}/generate`);

export const skipItem = (id: number) =>
  api.post<{ message: string }>(`/news/items/${id}/skip`);

export const unpublishItem = (id: number) =>
  api.post<{ message: string; item_id: number }>(`/news/items/${id}/unpublish`);

export const generateBatch = (params: { limit?: number; feed_id?: number; min_relevance?: number }) =>
  api.post<{ dispatched: number; remaining_quota: number }>('/news/items/generate-batch', params);

// ============================================================
// STATS & PROGRESS
// ============================================================

export const getNewsStats = () =>
  api.get<NewsStats>('/news/stats');

export const getNewsProgress = () =>
  api.get<{ data: NewsProgress }>('/news/progress');
