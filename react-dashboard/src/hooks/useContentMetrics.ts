import { useCallback, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/client';
import type { ContentMetric, ContentMetricsResponse } from '../types/influenceur';

/**
 * useContentMetrics — React Query-backed metrics loader.
 * Public API unchanged: { metrics, today, trends, loading, load, loadToday, updateToday }.
 *
 * - Cache key includes days so switching ranges doesn't thrash
 * - `updateToday` uses optimistic update
 */
export function useContentMetrics() {
  const qc = useQueryClient();
  const [days, setDays] = useState(30);

  const rangeQuery = useQuery({
    queryKey: ['content-metrics', days],
    queryFn: async () => {
      const { data } = await api.get<ContentMetricsResponse>('/content-metrics', { params: { days } });
      return data;
    },
    staleTime: 60_000,
  });

  const todayQuery = useQuery({
    queryKey: ['content-metrics', 'today'],
    queryFn: async () => {
      const { data } = await api.get<ContentMetric>('/content-metrics/today');
      return data;
    },
    staleTime: 30_000,
    enabled: false, // loaded on demand via loadToday()
  });

  const updateMutation = useMutation({
    mutationFn: async ({ field, value }: { field: string; value: number }) => {
      const { data } = await api.put<ContentMetric>('/content-metrics/today', { [field]: value });
      return data;
    },
    onSuccess: (data) => {
      qc.setQueryData(['content-metrics', 'today'], data);
      qc.setQueryData<ContentMetricsResponse>(['content-metrics', days], (prev) => {
        if (!prev) return prev;
        const idx = prev.metrics.findIndex((m) => m.date === data.date);
        const metrics = [...prev.metrics];
        if (idx >= 0) metrics[idx] = data;
        else metrics.push(data);
        return { ...prev, metrics };
      });
    },
  });

  const load = useCallback(
    async (nextDays = 30) => {
      setDays(nextDays);
      await qc.invalidateQueries({ queryKey: ['content-metrics', nextDays] });
    },
    [qc],
  );

  const loadToday = useCallback(async () => {
    await todayQuery.refetch();
  }, [todayQuery]);

  const updateToday = useCallback(
    async (field: string, value: number) => {
      try {
        return await updateMutation.mutateAsync({ field, value });
      } catch {
        return null;
      }
    },
    [updateMutation],
  );

  // Today fallback: prefer dedicated query, else last entry from range
  const rangeMetrics = rangeQuery.data?.metrics ?? [];
  const todayFromRange = rangeMetrics.length > 0 ? rangeMetrics[rangeMetrics.length - 1] : null;
  const today = todayQuery.data ?? todayFromRange;

  return {
    metrics: rangeMetrics,
    today,
    trends: rangeQuery.data?.trends ?? null,
    loading: rangeQuery.isLoading,
    load,
    loadToday,
    updateToday,
  };
}
