import { useQuery } from '@tanstack/react-query';
import api from '../api/client';

interface ContentSourceNav {
  id: number;
  name: string;
  slug: string;
  status: string;
  total_countries: number;
  total_articles: number;
}

/**
 * useContentSources — React Query-backed nav list of content sources.
 * Cached for 2 minutes; shared across pages.
 */
export function useContentSources(): ContentSourceNav[] {
  const { data } = useQuery<ContentSourceNav[]>({
    queryKey: ['content', 'sources', 'nav'],
    queryFn: async () => {
      const res = await api.get('/content/sources');
      return res.data as ContentSourceNav[];
    },
    staleTime: 120_000,
    retry: 0,
  });
  return data ?? [];
}
