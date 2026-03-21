import { useState, useCallback } from 'react';
import api from '../api/client';
import type { ActivityLogEntry, JournalToday, JournalWeekDay, ContactType } from '../types/influenceur';

export function useJournal() {
  const [entries, setEntries] = useState<ActivityLogEntry[]>([]);
  const [todaySummary, setTodaySummary] = useState<JournalToday | null>(null);
  const [weekly, setWeekly] = useState<JournalWeekDay[]>([]);
  const [loading, setLoading] = useState(false);

  const loadEntries = useCallback(async (params?: { date?: string; manual_only?: boolean; contact_type?: string }) => {
    setLoading(true);
    try {
      const { data } = await api.get('/journal', { params });
      setEntries(data.data ?? data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  const loadToday = useCallback(async () => {
    try {
      const { data } = await api.get<JournalToday>('/journal/today');
      setTodaySummary(data);
    } catch { /* ignore */ }
  }, []);

  const loadWeekly = useCallback(async () => {
    try {
      const { data } = await api.get<JournalWeekDay[]>('/journal/weekly');
      setWeekly(data);
    } catch { /* ignore */ }
  }, []);

  const addNote = useCallback(async (note: string, contactType?: ContactType) => {
    try {
      const { data } = await api.post<ActivityLogEntry>('/journal', {
        note,
        contact_type: contactType ?? null,
      });
      setEntries(prev => [data, ...prev]);
      // Refresh today summary
      loadToday();
      return data;
    } catch { return null; }
  }, [loadToday]);

  return { entries, todaySummary, weekly, loading, loadEntries, loadToday, loadWeekly, addNote };
}
