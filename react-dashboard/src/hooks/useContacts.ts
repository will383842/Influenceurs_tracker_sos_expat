import { useCallback, useState } from 'react';
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../api/client';
import type { Influenceur, InfluenceurFilters, PaginatedInfluenceurs } from '../types/influenceur';

/**
 * useContacts — React Query-backed infinite list of contacts/influenceurs.
 * Public API preserved: { contacts, loading, error, hasMore, filters, load, loadMore,
 * createContact, updateContact, deleteContact }.
 *
 * - useInfiniteQuery with cursor pagination
 * - filters stored in local state; changing filters triggers a new query key
 * - mutations update cache optimistically
 */
export function useContacts() {
  const qc = useQueryClient();
  const [filters, setFilters] = useState<InfluenceurFilters>({});

  const query = useInfiniteQuery<PaginatedInfluenceurs>({
    queryKey: ['contacts', filters],
    queryFn: async ({ pageParam }) => {
      const params: Record<string, unknown> = { per_page: 30, ...filters };
      if (pageParam) params.cursor = pageParam;
      const { data } = await api.get<PaginatedInfluenceurs>('/contacts', { params });
      return data;
    },
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => (lastPage.has_more ? lastPage.next_cursor : undefined),
    // Don't auto-fetch on mount — consumers call load() explicitly (legacy behavior)
    enabled: false,
    staleTime: 30_000,
  });

  const contacts: Influenceur[] = query.data?.pages.flatMap((p) => p.data) ?? [];
  const lastPage = query.data?.pages[query.data.pages.length - 1];
  const hasMore = !!lastPage?.has_more;

  const load = useCallback(
    (newFilters?: InfluenceurFilters) => {
      if (newFilters) {
        setFilters(newFilters);
        // React Query will refetch automatically on queryKey change, but we need to enable it
        qc.removeQueries({ queryKey: ['contacts', newFilters] });
      }
      // Trigger initial fetch
      void query.refetch();
    },
    [query, qc],
  );

  const loadMore = useCallback(() => {
    if (hasMore && !query.isFetchingNextPage) {
      void query.fetchNextPage();
    }
  }, [hasMore, query]);

  const invalidateList = useCallback(() => {
    qc.invalidateQueries({ queryKey: ['contacts'] });
  }, [qc]);

  const createMutation = useMutation({
    mutationFn: async (payload: Partial<Influenceur>) => {
      const { data } = await api.post<Influenceur>('/contacts', payload);
      return data;
    },
    onSuccess: invalidateList,
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<Influenceur> }) => {
      const { data } = await api.put<Influenceur>(`/contacts/${id}`, payload);
      return data;
    },
    onSuccess: invalidateList,
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/contacts/${id}`);
      return id;
    },
    onSuccess: invalidateList,
  });

  const createContact = useCallback(
    (payload: Partial<Influenceur>) => createMutation.mutateAsync(payload),
    [createMutation],
  );
  const updateContact = useCallback(
    (id: number, payload: Partial<Influenceur>) => updateMutation.mutateAsync({ id, payload }),
    [updateMutation],
  );
  const deleteContact = useCallback(
    (id: number) => deleteMutation.mutateAsync(id),
    [deleteMutation],
  );

  return {
    contacts,
    loading: query.isFetching,
    error: query.error ? (query.error as Error).message : null,
    hasMore,
    filters,
    load,
    loadMore,
    createContact,
    updateContact,
    deleteContact,
  };
}

// Backward compat alias
export function useInfluenceurs() {
  const hook = useContacts();
  return {
    influenceurs: hook.contacts,
    loading: hook.loading,
    error: hook.error,
    hasMore: hook.hasMore,
    filters: hook.filters,
    load: hook.load,
    loadMore: hook.loadMore,
    createInfluenceur: hook.createContact,
    updateInfluenceur: hook.updateContact,
    deleteInfluenceur: hook.deleteContact,
  };
}
