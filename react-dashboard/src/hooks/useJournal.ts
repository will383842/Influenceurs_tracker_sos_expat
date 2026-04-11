import { useCallback, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/client';
import type { ActivityLogEntry, JournalToday, JournalWeekDay, ContactType } from '../types/influenceur';

interface JournalEntriesParams {
  date?: string;
  manual_only?: boolean;
  contact_type?: string;
}

/**
 * useJournal — React Query-backed journal hook.
 * Public API preserved: { entries, todaySummary, weekly, loading, loadEntries, loadToday, loadWeekly, addNote }.
 */
export function useJournal() {
  const qc = useQueryClient();
  const [entriesParams, setEntriesParams] = useState<JournalEntriesParams>({});

  const entriesQuery = useQuery<ActivityLogEntry[]>({
    queryKey: ['journal', 'entries', entriesParams],
    queryFn: async () => {
      const { data } = await api.get('/journal', { params: entriesParams });
      return (data.data ?? data) as ActivityLogEntry[];
    },
    enabled: false,
    staleTime: 30_000,
  });

  const todayQuery = useQuery<JournalToday>({
    queryKey: ['journal', 'today'],
    queryFn: async () => {
      const { data } = await api.get<JournalToday>('/journal/today');
      return data;
    },
    enabled: false,
    staleTime: 30_000,
  });

  const weeklyQuery = useQuery<JournalWeekDay[]>({
    queryKey: ['journal', 'weekly'],
    queryFn: async () => {
      const { data } = await api.get<JournalWeekDay[]>('/journal/weekly');
      return data;
    },
    enabled: false,
    staleTime: 60_000,
  });

  const addNoteMutation = useMutation({
    mutationFn: async ({ note, contactType }: { note: string; contactType?: ContactType }) => {
      const { data } = await api.post<ActivityLogEntry>('/journal', {
        note,
        contact_type: contactType ?? null,
      });
      return data;
    },
    onSuccess: (entry) => {
      qc.setQueryData<ActivityLogEntry[]>(['journal', 'entries', entriesParams], (prev) =>
        prev ? [entry, ...prev] : [entry],
      );
      qc.invalidateQueries({ queryKey: ['journal', 'today'] });
    },
  });

  const loadEntries = useCallback(
    async (params?: JournalEntriesParams) => {
      if (params) setEntriesParams(params);
      await entriesQuery.refetch();
    },
    [entriesQuery],
  );

  const loadToday = useCallback(async () => {
    await todayQuery.refetch();
  }, [todayQuery]);

  const loadWeekly = useCallback(async () => {
    await weeklyQuery.refetch();
  }, [weeklyQuery]);

  const addNote = useCallback(
    async (note: string, contactType?: ContactType) => {
      try {
        return await addNoteMutation.mutateAsync({ note, contactType });
      } catch {
        return null;
      }
    },
    [addNoteMutation],
  );

  return {
    entries: entriesQuery.data ?? [],
    todaySummary: todayQuery.data ?? null,
    weekly: weeklyQuery.data ?? [],
    loading: entriesQuery.isFetching,
    loadEntries,
    loadToday,
    loadWeekly,
    addNote,
  };
}
