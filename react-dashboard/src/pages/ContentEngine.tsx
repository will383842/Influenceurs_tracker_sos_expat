import React, { useEffect, useState } from 'react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { useContentMetrics } from '../hooks/useContentMetrics';
import { CONTENT_METRICS_CONFIG } from '../lib/constants';

export default function ContentEngine() {
  const { metrics, today, trends, loading, load, updateToday } = useContentMetrics();
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [editValue, setEditValue] = useState('');

  useEffect(() => { load(30); }, [load]);

  const handleStartEdit = (key: string, currentValue: number) => {
    setEditingKey(key);
    setEditValue(key === 'revenue_cents' ? String(currentValue / 100) : String(currentValue));
  };

  const handleSave = async () => {
    if (!editingKey) return;
    const value = editingKey === 'revenue_cents'
      ? Math.round(parseFloat(editValue) * 100)
      : parseInt(editValue) || 0;
    await updateToday(editingKey, value);
    setEditingKey(null);
  };

  const tooltipStyle = { backgroundColor: '#101419', border: '1px solid #1e2530', borderRadius: 8, color: '#e2e8f0' };

  if (loading && !today) {
    return (
      <div className="p-4 md:p-6 flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">📡 Content Engine</h2>
        <p className="text-muted text-sm mt-1">Suivi SEO & performance — cliquez sur un chiffre pour le modifier</p>
      </div>

      {/* 9 KPI cards in 3x3 grid */}
      <div className="grid grid-cols-3 gap-3">
        {CONTENT_METRICS_CONFIG.map(({ key, label, icon, color }) => {
          const value = today?.[key as keyof typeof today] as number ?? 0;
          const displayValue = key === 'revenue_cents' ? (value / 100).toFixed(0) + '€' : value.toLocaleString();
          const isEditing = editingKey === key;

          return (
            <div key={key} className="bg-surface border border-border rounded-xl p-4 text-center hover:border-violet/20 transition-colors">
              <p className="text-xs text-muted mb-1">{icon} {label}</p>
              {isEditing ? (
                <input
                  autoFocus
                  value={editValue}
                  onChange={e => setEditValue(e.target.value)}
                  onBlur={handleSave}
                  onKeyDown={e => { if (e.key === 'Enter') handleSave(); if (e.key === 'Escape') setEditingKey(null); }}
                  className="w-full text-center text-2xl font-bold bg-bg border border-violet rounded-lg px-2 py-1 outline-none"
                  style={{ color }}
                />
              ) : (
                <p
                  onClick={() => handleStartEdit(key, value)}
                  className="text-2xl font-bold font-title cursor-pointer hover:opacity-80 transition-opacity border-b border-dashed border-transparent hover:border-gray-600"
                  style={{ color }}>
                  {displayValue}
                </p>
              )}
            </div>
          );
        })}
      </div>

      {/* Trends */}
      {trends && (
        <div className="grid grid-cols-3 gap-3">
          {[
            { label: 'Visites', value: trends.visits_growth, suffix: '%' },
            { label: 'Appels', value: trends.calls_growth, suffix: '%' },
            { label: 'Revenue', value: trends.revenue_growth, suffix: '%' },
          ].map(t => (
            <div key={t.label} className="bg-surface border border-border rounded-xl p-4 text-center">
              <p className="text-xs text-muted">{t.label} (tendance 30j)</p>
              <p className={`text-lg font-bold font-title ${t.value >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                {t.value >= 0 ? '↑' : '↓'} {Math.abs(t.value)}{t.suffix}
              </p>
            </div>
          ))}
        </div>
      )}

      {/* Evolution chart */}
      {metrics.length > 1 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Évolution (30 jours)</h3>
          <ResponsiveContainer width="100%" height={250}>
            <AreaChart data={metrics}>
              <defs>
                <linearGradient id="gradVisits" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#06b6d4" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#06b6d4" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="gradCalls" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="date" stroke="#6b7280" tick={{ fontSize: 10 }}
                tickFormatter={(d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 10 }} />
              <Tooltip contentStyle={tooltipStyle} />
              <Area type="monotone" dataKey="daily_visits" stroke="#06b6d4" fill="url(#gradVisits)" strokeWidth={2} name="Visites" />
              <Area type="monotone" dataKey="calls_generated" stroke="#10b981" fill="url(#gradCalls)" strokeWidth={2} name="Appels" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}
