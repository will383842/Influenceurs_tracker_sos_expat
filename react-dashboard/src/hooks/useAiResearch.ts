import { useState, useCallback, useRef, useEffect } from 'react';
import api from '../api/client';
import type { AiResearchSession, ContactType } from '../types/influenceur';

export function useAiResearch() {
  const [session, setSession] = useState<AiResearchSession | null>(null);
  const [history, setHistory] = useState<AiResearchSession[]>([]);
  const [launching, setLaunching] = useState(false);
  const [importing, setImporting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Stop polling on unmount
  useEffect(() => {
    return () => { if (pollRef.current) clearInterval(pollRef.current); };
  }, []);

  const launch = useCallback(async (contactType: ContactType, country: string, language = 'fr') => {
    setLaunching(true);
    setError(null);
    try {
      const { data } = await api.post<AiResearchSession>('/ai-research/launch', {
        contact_type: contactType,
        country,
        language,
      });
      setSession(data);

      // Start polling every 2s until completed/failed
      if (pollRef.current) clearInterval(pollRef.current);
      pollRef.current = setInterval(async () => {
        try {
          const { data: updated } = await api.get<AiResearchSession>(`/ai-research/${data.id}`);
          setSession(updated);
          if (updated.status === 'completed' || updated.status === 'failed') {
            if (pollRef.current) clearInterval(pollRef.current);
            pollRef.current = null;
          }
        } catch { /* ignore polling errors */ }
      }, 2000);

      return data;
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur lors du lancement';
      setError(msg);
      return null;
    } finally {
      setLaunching(false);
    }
  }, []);

  const importContacts = useCallback(async (sessionId: number, indices: number[]) => {
    setImporting(true);
    try {
      const { data } = await api.post(`/ai-research/${sessionId}/import`, { contact_indices: indices });
      // Refresh session to get updated counts
      const { data: updated } = await api.get<AiResearchSession>(`/ai-research/${sessionId}`);
      setSession(updated);
      return data as { imported: number; skipped: number };
    } catch {
      setError('Erreur lors de l\'import');
      return null;
    } finally {
      setImporting(false);
    }
  }, []);

  const importAll = useCallback(async (sessionId: number) => {
    setImporting(true);
    try {
      const { data } = await api.post(`/ai-research/${sessionId}/import-all`);
      const { data: updated } = await api.get<AiResearchSession>(`/ai-research/${sessionId}`);
      setSession(updated);
      return data as { imported: number; skipped: number };
    } catch {
      setError('Erreur lors de l\'import');
      return null;
    } finally {
      setImporting(false);
    }
  }, []);

  const loadHistory = useCallback(async () => {
    try {
      const { data } = await api.get('/ai-research');
      setHistory(data.data ?? data);
    } catch { /* ignore */ }
  }, []);

  return {
    session, history, launching, importing, error,
    launch, importContacts, importAll, loadHistory,
    setSession, setError,
  };
}
