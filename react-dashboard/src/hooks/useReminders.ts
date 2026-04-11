import { useCallback } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/client';
import type { ReminderWithInfluenceur } from '../types/influenceur';

const POLL_INTERVAL = 5 * 60 * 1000; // 5 minutes
const REMINDERS_KEY = ['reminders'] as const;

async function fetchReminders(): Promise<ReminderWithInfluenceur[]> {
  const { data } = await api.get<ReminderWithInfluenceur[]>('/reminders');
  return data;
}

/**
 * useReminders — React Query-backed. Same public API as legacy hook:
 * { reminders, loading, refresh, dismiss, markDone }
 *
 * - Cached at key ['reminders']
 * - Polls every 5 minutes via refetchInterval
 * - Optimistic removal on dismiss/markDone
 */
export function useReminders() {
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: REMINDERS_KEY,
    queryFn: fetchReminders,
    refetchInterval: POLL_INTERVAL,
    staleTime: 60_000,
  });

  const removeFromCache = useCallback(
    (id: number) => {
      qc.setQueryData<ReminderWithInfluenceur[]>(REMINDERS_KEY, (prev) =>
        prev ? prev.filter((r) => r.id !== id) : prev,
      );
    },
    [qc],
  );

  const dismissMutation = useMutation({
    mutationFn: async ({ id, notes }: { id: number; notes?: string }) => {
      await api.post(`/reminders/${id}/dismiss`, { notes });
      return id;
    },
    onSuccess: removeFromCache,
  });

  const doneMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/reminders/${id}/done`);
      return id;
    },
    onSuccess: removeFromCache,
  });

  const dismiss = useCallback(
    (id: number, notes?: string) => dismissMutation.mutateAsync({ id, notes }),
    [dismissMutation],
  );

  const markDone = useCallback(
    (id: number) => doneMutation.mutateAsync(id),
    [doneMutation],
  );

  const refresh = useCallback(() => qc.invalidateQueries({ queryKey: REMINDERS_KEY }), [qc]);

  return {
    reminders: query.data ?? [],
    loading: query.isLoading,
    refresh,
    dismiss,
    markDone,
  };
}
