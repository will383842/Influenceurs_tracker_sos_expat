import { useState, useCallback } from 'react';
import api from '../api/client';
import type { ContentMetric, ContentMetricsResponse } from '../types/influenceur';

export function useContentMetrics() {
  const [metrics, setMetrics] = useState<ContentMetric[]>([]);
  const [today, setToday] = useState<ContentMetric | null>(null);
  const [trends, setTrends] = useState<ContentMetricsResponse['trends']>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (days = 30) => {
    setLoading(true);
    try {
      const { data } = await api.get<ContentMetricsResponse>('/content-metrics', { params: { days } });
      setMetrics(data.metrics);
      setTrends(data.trends);
      if (data.metrics.length > 0) {
        setToday(data.metrics[data.metrics.length - 1]);
      }
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  const loadToday = useCallback(async () => {
    try {
      const { data } = await api.get<ContentMetric>('/content-metrics/today');
      setToday(data);
    } catch { /* ignore */ }
  }, []);

  const updateToday = useCallback(async (field: string, value: number) => {
    try {
      const { data } = await api.put<ContentMetric>('/content-metrics/today', { [field]: value });
      setToday(data);
      // Also update in the metrics array
      setMetrics(prev => {
        const idx = prev.findIndex(m => m.date === data.date);
        if (idx >= 0) {
          const copy = [...prev];
          copy[idx] = data;
          return copy;
        }
        return [...prev, data];
      });
      return data;
    } catch { return null; }
  }, []);

  return { metrics, today, trends, loading, load, loadToday, updateToday };
}
