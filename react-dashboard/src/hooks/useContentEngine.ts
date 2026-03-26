import { useState, useCallback } from 'react';
import * as contentApi from '../api/contentApi';
import type {
  GeneratedArticle,
  Comparative,
  ContentCampaign,
  CostOverview,
  SeoDashboard,
  GenerationStats,
  PublishingEndpoint,
  PublicationQueueItem,
  GenerateArticleParams,
  GenerateComparativeParams,
  PaginatedResponse,
} from '../types/content';

// ============================================================
// ARTICLES
// ============================================================

export function useContentArticles() {
  const [articles, setArticles] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<{ current_page: number; last_page: number; total: number }>({
    current_page: 1,
    last_page: 1,
    total: 0,
  });

  const load = useCallback(async (params?: { status?: string; language?: string; country?: string; search?: string; page?: number }) => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await contentApi.fetchArticles(params);
      setArticles(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors du chargement des articles';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const remove = useCallback(async (id: number) => {
    await contentApi.deleteArticle(id);
    setArticles(prev => prev.filter(a => a.id !== id));
  }, []);

  const bulkRemove = useCallback(async (ids: number[]) => {
    await contentApi.bulkDeleteArticles(ids);
    setArticles(prev => prev.filter(a => !ids.includes(a.id)));
  }, []);

  return { articles, loading, error, pagination, load, remove, bulkRemove };
}

// ============================================================
// GENERATION
// ============================================================

export function useContentGeneration() {
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const generateArticle = useCallback(async (params: GenerateArticleParams) => {
    setGenerating(true);
    setError(null);
    try {
      const { data } = await contentApi.generateArticle(params);
      return data;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors de la génération';
      setError(message);
      return null;
    } finally {
      setGenerating(false);
    }
  }, []);

  const generateComparative = useCallback(async (params: GenerateComparativeParams) => {
    setGenerating(true);
    setError(null);
    try {
      const { data } = await contentApi.generateComparative(params);
      return data;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors de la génération du comparatif';
      setError(message);
      return null;
    } finally {
      setGenerating(false);
    }
  }, []);

  return { generating, error, generateArticle, generateComparative };
}

// ============================================================
// CAMPAIGNS
// ============================================================

export function useContentCampaigns() {
  const [campaigns, setCampaigns] = useState<ContentCampaign[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<{ current_page: number; last_page: number; total: number }>({
    current_page: 1,
    last_page: 1,
    total: 0,
  });

  const load = useCallback(async (params?: Record<string, unknown>) => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await contentApi.fetchCampaigns(params);
      setCampaigns(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors du chargement des campagnes';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const start = useCallback(async (id: number) => {
    const { data } = await contentApi.startCampaign(id);
    setCampaigns(prev => prev.map(c => c.id === id ? data : c));
    return data;
  }, []);

  const pause = useCallback(async (id: number) => {
    const { data } = await contentApi.pauseCampaign(id);
    setCampaigns(prev => prev.map(c => c.id === id ? data : c));
    return data;
  }, []);

  const resume = useCallback(async (id: number) => {
    const { data } = await contentApi.resumeCampaign(id);
    setCampaigns(prev => prev.map(c => c.id === id ? data : c));
    return data;
  }, []);

  const cancel = useCallback(async (id: number) => {
    const { data } = await contentApi.cancelCampaign(id);
    setCampaigns(prev => prev.map(c => c.id === id ? data : c));
    return data;
  }, []);

  const remove = useCallback(async (id: number) => {
    await contentApi.deleteCampaign(id);
    setCampaigns(prev => prev.filter(c => c.id !== id));
  }, []);

  return { campaigns, loading, error, pagination, load, start, pause, resume, cancel, remove };
}

// ============================================================
// COSTS
// ============================================================

export function useCosts() {
  const [overview, setOverview] = useState<CostOverview | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await contentApi.fetchCostOverview();
      setOverview(data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  return { overview, loading, load };
}

// ============================================================
// SEO DASHBOARD
// ============================================================

export function useSeoDashboard() {
  const [dashboard, setDashboard] = useState<SeoDashboard | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await contentApi.fetchSeoDashboard();
      setDashboard(data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  return { dashboard, loading, load };
}

// ============================================================
// GENERATION STATS
// ============================================================

export function useGenerationStats() {
  const [stats, setStats] = useState<GenerationStats | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await contentApi.fetchGenerationStats();
      setStats(data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  return { stats, loading, load };
}

// ============================================================
// PUBLISHING
// ============================================================

export function usePublishing() {
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [queue, setQueue] = useState<PublicationQueueItem[]>([]);
  const [loading, setLoading] = useState(false);

  const loadEndpoints = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await contentApi.fetchEndpoints();
      setEndpoints(data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  const loadQueue = useCallback(async (params?: Record<string, unknown>) => {
    setLoading(true);
    try {
      const { data } = await contentApi.fetchPublicationQueue(params);
      setQueue(data.data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  const executeItem = useCallback(async (id: number) => {
    const { data } = await contentApi.executeQueueItem(id);
    setQueue(prev => prev.map(q => q.id === id ? data : q));
    return data;
  }, []);

  const cancelItem = useCallback(async (id: number) => {
    const { data } = await contentApi.cancelQueueItem(id);
    setQueue(prev => prev.map(q => q.id === id ? data : q));
    return data;
  }, []);

  return { endpoints, queue, loading, loadEndpoints, loadQueue, executeItem, cancelItem };
}
