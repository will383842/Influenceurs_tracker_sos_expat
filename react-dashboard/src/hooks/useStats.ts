import { useQuery } from '@tanstack/react-query';
import api from '../api/client';
import type { StatsData } from '../types/influenceur';

/**
 * useStats — React Query-backed dashboard stats.
 * Same public API: { stats, loading }.
 */
export function useStats() {
  const query = useQuery({
    queryKey: ['stats'],
    queryFn: async () => {
      const { data } = await api.get<StatsData>('/stats');
      return data;
    },
    staleTime: 60_000,
  });

  return { stats: query.data ?? null, loading: query.isLoading };
}
