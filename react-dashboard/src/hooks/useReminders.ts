import { useState, useEffect, useCallback, useRef } from 'react';
import api from '../api/client';
import type { ReminderWithInfluenceur } from '../types/influenceur';

const POLL_INTERVAL = 5 * 60 * 1000; // 5 minutes

export function useReminders() {
  const [reminders, setReminders] = useState<ReminderWithInfluenceur[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchReminders = useCallback(async () => {
    try {
      const { data } = await api.get<ReminderWithInfluenceur[]>('/reminders');
      setReminders(data);
    } catch (err) {
      console.error('Failed to fetch reminders:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  // Stable ref to avoid resetting the interval on every render
  const fetchRef = useRef(fetchReminders);
  fetchRef.current = fetchReminders;

  useEffect(() => {
    fetchRef.current();
    const interval = setInterval(() => fetchRef.current(), POLL_INTERVAL);
    return () => clearInterval(interval);
  }, []);

  const dismiss = useCallback(async (id: number, notes?: string) => {
    await api.post(`/reminders/${id}/dismiss`, { notes });
    setReminders(prev => prev.filter(r => r.id !== id));
  }, []);

  const markDone = useCallback(async (id: number) => {
    await api.post(`/reminders/${id}/done`);
    setReminders(prev => prev.filter(r => r.id !== id));
  }, []);

  return { reminders, loading, refresh: fetchReminders, dismiss, markDone };
}
