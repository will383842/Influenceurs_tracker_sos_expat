import { useCallback, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as contentApi from '../api/contentApi';
import type {
  GeneratedArticle,
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

type ArticleListParams = {
  status?: string;
  language?: string;
  country?: string;
  search?: string;
  page?: number;
};

export function useContentArticles() {
  const qc = useQueryClient();
  const [params, setParams] = useState<ArticleListParams>({});

  const query = useQuery<PaginatedResponse<GeneratedArticle>>({
    queryKey: ['content', 'articles', params],
    queryFn: async () => {
      const { data } = await contentApi.fetchArticles(params);
      return data;
    },
    enabled: false,
    staleTime: 30_000,
  });

  const invalidate = useCallback(
    () => qc.invalidateQueries({ queryKey: ['content', 'articles'] }),
    [qc],
  );

  const removeMutation = useMutation({
    mutationFn: (id: number) => contentApi.deleteArticle(id),
    onSuccess: invalidate,
  });

  const bulkRemoveMutation = useMutation({
    mutationFn: (ids: number[]) => contentApi.bulkDeleteArticles(ids),
    onSuccess: invalidate,
  });

  const load = useCallback(
    async (newParams?: ArticleListParams) => {
      if (newParams) setParams(newParams);
      await query.refetch();
    },
    [query],
  );

  const remove = useCallback((id: number) => removeMutation.mutateAsync(id), [removeMutation]);
  const bulkRemove = useCallback(
    (ids: number[]) => bulkRemoveMutation.mutateAsync(ids).then(() => undefined),
    [bulkRemoveMutation],
  );

  return {
    articles: query.data?.data ?? [],
    loading: query.isFetching,
    error: query.error ? (query.error as Error).message : null,
    pagination: {
      current_page: query.data?.current_page ?? 1,
      last_page: query.data?.last_page ?? 1,
      total: query.data?.total ?? 0,
    },
    load,
    remove,
    bulkRemove,
  };
}

// ============================================================
// GENERATION
// ============================================================

export function useContentGeneration() {
  const [error, setError] = useState<string | null>(null);

  const articleMutation = useMutation({
    mutationFn: (params: GenerateArticleParams) => contentApi.generateArticle(params),
    onError: (err: unknown) => {
      setError(err instanceof Error ? err.message : 'Erreur lors de la génération');
    },
  });

  const comparativeMutation = useMutation({
    mutationFn: (params: GenerateComparativeParams) => contentApi.generateComparative(params),
    onError: (err: unknown) => {
      setError(err instanceof Error ? err.message : 'Erreur lors de la génération du comparatif');
    },
  });

  const generateArticle = useCallback(
    async (params: GenerateArticleParams) => {
      setError(null);
      try {
        const { data } = await articleMutation.mutateAsync(params);
        return data;
      } catch {
        return null;
      }
    },
    [articleMutation],
  );

  const generateComparative = useCallback(
    async (params: GenerateComparativeParams) => {
      setError(null);
      try {
        const { data } = await comparativeMutation.mutateAsync(params);
        return data;
      } catch {
        return null;
      }
    },
    [comparativeMutation],
  );

  return {
    generating: articleMutation.isPending || comparativeMutation.isPending,
    error,
    generateArticle,
    generateComparative,
  };
}

// ============================================================
// CAMPAIGNS
// ============================================================

export function useContentCampaigns() {
  const qc = useQueryClient();
  const [params, setParams] = useState<Record<string, unknown>>({});

  const query = useQuery<PaginatedResponse<ContentCampaign>>({
    queryKey: ['content', 'campaigns', params],
    queryFn: async () => {
      const { data } = await contentApi.fetchCampaigns(params);
      return data;
    },
    enabled: false,
    staleTime: 30_000,
  });

  const invalidate = useCallback(
    () => qc.invalidateQueries({ queryKey: ['content', 'campaigns'] }),
    [qc],
  );

  const load = useCallback(
    async (newParams?: Record<string, unknown>) => {
      if (newParams) setParams(newParams);
      await query.refetch();
    },
    [query],
  );

  const startMutation = useMutation({
    mutationFn: (id: number) => contentApi.startCampaign(id),
    onSuccess: invalidate,
  });
  const pauseMutation = useMutation({
    mutationFn: (id: number) => contentApi.pauseCampaign(id),
    onSuccess: invalidate,
  });
  const resumeMutation = useMutation({
    mutationFn: (id: number) => contentApi.resumeCampaign(id),
    onSuccess: invalidate,
  });
  const cancelMutation = useMutation({
    mutationFn: (id: number) => contentApi.cancelCampaign(id),
    onSuccess: invalidate,
  });
  const removeMutation = useMutation({
    mutationFn: (id: number) => contentApi.deleteCampaign(id),
    onSuccess: invalidate,
  });

  const start = useCallback((id: number) => startMutation.mutateAsync(id).then((r) => r.data), [startMutation]);
  const pause = useCallback((id: number) => pauseMutation.mutateAsync(id).then((r) => r.data), [pauseMutation]);
  const resume = useCallback((id: number) => resumeMutation.mutateAsync(id).then((r) => r.data), [resumeMutation]);
  const cancel = useCallback((id: number) => cancelMutation.mutateAsync(id).then((r) => r.data), [cancelMutation]);
  const remove = useCallback((id: number) => removeMutation.mutateAsync(id).then(() => undefined), [removeMutation]);

  return {
    campaigns: query.data?.data ?? [],
    loading: query.isFetching,
    error: query.error ? (query.error as Error).message : null,
    pagination: {
      current_page: query.data?.current_page ?? 1,
      last_page: query.data?.last_page ?? 1,
      total: query.data?.total ?? 0,
    },
    load,
    start,
    pause,
    resume,
    cancel,
    remove,
  };
}

// ============================================================
// COSTS
// ============================================================

export function useCosts() {
  const query = useQuery<CostOverview>({
    queryKey: ['content', 'costs'],
    queryFn: async () => {
      const { data } = await contentApi.fetchCostOverview();
      return data;
    },
    enabled: false,
    staleTime: 60_000,
  });

  const load = useCallback(async () => {
    await query.refetch();
  }, [query]);

  return { overview: query.data ?? null, loading: query.isFetching, load };
}

// ============================================================
// SEO DASHBOARD
// ============================================================

export function useSeoDashboard() {
  const query = useQuery<SeoDashboard>({
    queryKey: ['content', 'seo-dashboard'],
    queryFn: async () => {
      const { data } = await contentApi.fetchSeoDashboard();
      return data;
    },
    enabled: false,
    staleTime: 60_000,
  });

  const load = useCallback(async () => {
    await query.refetch();
  }, [query]);

  return { dashboard: query.data ?? null, loading: query.isFetching, load };
}

// ============================================================
// GENERATION STATS
// ============================================================

export function useGenerationStats() {
  const query = useQuery<GenerationStats>({
    queryKey: ['content', 'generation-stats'],
    queryFn: async () => {
      const { data } = await contentApi.fetchGenerationStats();
      return data;
    },
    enabled: false,
    staleTime: 30_000,
  });

  const load = useCallback(async () => {
    await query.refetch();
  }, [query]);

  return { stats: query.data ?? null, loading: query.isFetching, load };
}

// ============================================================
// PUBLISHING
// ============================================================

export function usePublishing() {
  const qc = useQueryClient();
  const [queueParams, setQueueParams] = useState<Record<string, unknown>>({});

  const endpointsQuery = useQuery<PublishingEndpoint[]>({
    queryKey: ['publishing', 'endpoints'],
    queryFn: async () => {
      const { data } = await contentApi.fetchEndpoints();
      return data;
    },
    enabled: false,
    staleTime: 60_000,
  });

  const queueQuery = useQuery<PaginatedResponse<PublicationQueueItem>>({
    queryKey: ['publishing', 'queue', queueParams],
    queryFn: async () => {
      const { data } = await contentApi.fetchPublicationQueue(queueParams);
      return data;
    },
    enabled: false,
    staleTime: 15_000,
  });

  const invalidateQueue = useCallback(
    () => qc.invalidateQueries({ queryKey: ['publishing', 'queue'] }),
    [qc],
  );

  const executeMutation = useMutation({
    mutationFn: (id: number) => contentApi.executeQueueItem(id),
    onSuccess: invalidateQueue,
  });

  const cancelMutation = useMutation({
    mutationFn: (id: number) => contentApi.cancelQueueItem(id),
    onSuccess: invalidateQueue,
  });

  const loadEndpoints = useCallback(async () => {
    await endpointsQuery.refetch();
  }, [endpointsQuery]);

  const loadQueue = useCallback(
    async (params?: Record<string, unknown>) => {
      if (params) setQueueParams(params);
      await queueQuery.refetch();
    },
    [queueQuery],
  );

  const executeItem = useCallback(
    (id: number) => executeMutation.mutateAsync(id).then((r) => r.data),
    [executeMutation],
  );
  const cancelItem = useCallback(
    (id: number) => cancelMutation.mutateAsync(id).then((r) => r.data),
    [cancelMutation],
  );

  return {
    endpoints: endpointsQuery.data ?? [],
    queue: queueQuery.data?.data ?? [],
    loading: endpointsQuery.isFetching || queueQuery.isFetching,
    loadEndpoints,
    loadQueue,
    executeItem,
    cancelItem,
  };
}
