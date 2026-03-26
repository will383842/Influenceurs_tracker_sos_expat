import React, { useEffect, useState, useCallback } from 'react';
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend,
} from 'recharts';
import {
  fetchCostOverview,
  fetchCostBreakdown,
  fetchCostTrends,
} from '../../api/contentApi';
import type { CostOverview, CostBreakdownEntry, CostTrendEntry } from '../../types/content';

const SERVICE_COLORS: Record<string, string> = {
  openai: '#8b5cf6',
  perplexity: '#3b82f6',
  dalle: '#f59e0b',
  anthropic: '#10b981',
};

type Period = 'day' | 'week' | 'month';

function formatCost(cents: number): string {
  return '$' + (cents / 100).toFixed(2);
}

function budgetPct(used: number, budget: number): number {
  if (budget <= 0) return 0;
  return Math.min(Math.round((used / budget) * 100), 100);
}

export default function CostsDashboard() {
  const [overview, setOverview] = useState<CostOverview | null>(null);
  const [breakdown, setBreakdown] = useState<CostBreakdownEntry[]>([]);
  const [trends, setTrends] = useState<CostTrendEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState<Period>('month');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [ovRes, bkRes, trRes] = await Promise.all([
        fetchCostOverview(),
        fetchCostBreakdown({ period }),
        fetchCostTrends({ days: 30 }),
      ]);
      setOverview(ovRes.data as unknown as CostOverview);
      setBreakdown((bkRes.data as unknown as CostBreakdownEntry[]) ?? []);
      setTrends((trRes.data as unknown as CostTrendEntry[]) ?? []);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [period]);

  useEffect(() => { load(); }, [load]);

  // Chart data
  const allServices = Array.from(new Set(trends.flatMap(t => Object.keys(t.by_service))));
  const chartData = trends.map(t => {
    const entry: Record<string, unknown> = { date: t.date.slice(5) }; // MM-DD
    for (const svc of allServices) {
      entry[svc] = ((t.by_service[svc] ?? 0) / 100);
    }
    return entry;
  });

  // Breakdown totals
  const totalBreakdownCost = breakdown.reduce((s, e) => s + e.total_cost_cents, 0);
  const totalBreakdownTokens = breakdown.reduce((s, e) => s + e.total_tokens, 0);

  const dailyPct = overview ? budgetPct(overview.today_cents, overview.daily_budget_cents) : 0;
  const monthlyPct = overview ? budgetPct(overview.this_month_cents, overview.monthly_budget_cents) : 0;
  const budgetRemaining = overview ? overview.monthly_budget_cents - overview.this_month_cents : 0;
  const budgetRemainingPct = overview && overview.monthly_budget_cents > 0
    ? Math.round((budgetRemaining / overview.monthly_budget_cents) * 100) : 100;

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <h2 className="font-title text-2xl font-bold text-white">Couts IA</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-28" />)}
        </div>
        <div className="animate-pulse bg-surface border border-border rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Couts IA</h2>
      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Warning banners */}
      {overview?.is_over_monthly && (
        <div className="bg-danger/20 border border-danger/50 rounded-xl p-4 text-danger text-sm font-medium">
          Budget mensuel depasse ! ({formatCost(overview.this_month_cents)} / {formatCost(overview.monthly_budget_cents)})
        </div>
      )}
      {overview?.is_over_daily && !overview?.is_over_monthly && (
        <div className="bg-amber/20 border border-amber/50 rounded-xl p-4 text-amber text-sm font-medium">
          Budget journalier depasse ! ({formatCost(overview.today_cents)} / {formatCost(overview.daily_budget_cents)})
        </div>
      )}

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Today */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Aujourd'hui</span>
          <p className="text-2xl font-bold text-white mt-2">{formatCost(overview?.today_cents ?? 0)}</p>
          <div className="mt-2 w-full h-1.5 bg-surface2 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full ${dailyPct > 100 ? 'bg-danger' : dailyPct > 80 ? 'bg-amber' : 'bg-violet'}`}
              style={{ width: `${Math.min(dailyPct, 100)}%` }}
            />
          </div>
          <span className="text-xs text-muted">{dailyPct}% du budget journalier</span>
        </div>

        {/* This week */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Cette semaine</span>
          <p className="text-2xl font-bold text-white mt-2">{formatCost(overview?.this_week_cents ?? 0)}</p>
        </div>

        {/* This month */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Ce mois</span>
          <p className="text-2xl font-bold text-white mt-2">{formatCost(overview?.this_month_cents ?? 0)}</p>
          <div className="mt-2 w-full h-1.5 bg-surface2 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full ${monthlyPct > 100 ? 'bg-danger' : monthlyPct > 80 ? 'bg-amber' : 'bg-violet'}`}
              style={{ width: `${Math.min(monthlyPct, 100)}%` }}
            />
          </div>
          <span className="text-xs text-muted">{monthlyPct}% du budget mensuel</span>
        </div>

        {/* Budget remaining */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Budget restant</span>
          <p className={`text-2xl font-bold mt-2 ${budgetRemaining < 0 ? 'text-danger' : 'text-success'}`}>
            {formatCost(Math.max(budgetRemaining, 0))}
          </p>
          <span className="text-xs text-muted">{budgetRemainingPct}%</span>
        </div>
      </div>

      {/* Trends Chart */}
      {chartData.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Tendances 30 jours</h3>
          <ResponsiveContainer width="100%" height={300}>
            <AreaChart data={chartData} margin={{ left: 10, right: 10 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#333" />
              <XAxis dataKey="date" tick={{ fill: '#999', fontSize: 11 }} />
              <YAxis tick={{ fill: '#999', fontSize: 11 }} tickFormatter={v => `$${v}`} />
              <Tooltip
                contentStyle={{ background: '#1e1e2e', border: '1px solid #333', borderRadius: 8, color: '#fff' }}
                formatter={(value: number) => [`$${value.toFixed(2)}`, '']}
              />
              <Legend />
              {allServices.map(svc => (
                <Area
                  key={svc}
                  type="monotone"
                  dataKey={svc}
                  stackId="1"
                  stroke={SERVICE_COLORS[svc] ?? '#666'}
                  fill={SERVICE_COLORS[svc] ?? '#666'}
                  fillOpacity={0.4}
                />
              ))}
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Breakdown table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-title text-lg font-semibold text-white">Ventilation</h3>
          <div className="flex gap-1">
            {(['day', 'week', 'month'] as Period[]).map(p => (
              <button
                key={p}
                onClick={() => setPeriod(p)}
                className={`px-3 py-1 text-xs rounded-lg transition-colors ${
                  period === p ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
                }`}
              >
                {p === 'day' ? 'Jour' : p === 'week' ? 'Semaine' : 'Mois'}
              </button>
            ))}
          </div>
        </div>

        {breakdown.length === 0 ? (
          <p className="text-center py-8 text-muted text-sm">Aucune donnee pour cette periode</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Service</th>
                  <th className="pb-3 pr-4">Modele</th>
                  <th className="pb-3 pr-4">Operation</th>
                  <th className="pb-3 pr-4">Appels</th>
                  <th className="pb-3 pr-4">Tokens</th>
                  <th className="pb-3">Cout</th>
                </tr>
              </thead>
              <tbody>
                {breakdown.map((entry, idx) => (
                  <tr key={idx} className="border-b border-border/50">
                    <td className="py-3 pr-4">
                      <span className="flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full" style={{ background: SERVICE_COLORS[entry.service] ?? '#666' }} />
                        <span className="text-white capitalize">{entry.service}</span>
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted">{entry.model}</td>
                    <td className="py-3 pr-4 text-muted capitalize">{entry.operation}</td>
                    <td className="py-3 pr-4 text-white">{entry.count}</td>
                    <td className="py-3 pr-4 text-white">{entry.total_tokens.toLocaleString()}</td>
                    <td className="py-3 text-white font-medium">{formatCost(entry.total_cost_cents)}</td>
                  </tr>
                ))}
                {/* Total row */}
                <tr className="border-t-2 border-border font-bold">
                  <td className="py-3 pr-4 text-white" colSpan={3}>TOTAL</td>
                  <td className="py-3 pr-4 text-white">{breakdown.reduce((s, e) => s + e.count, 0)}</td>
                  <td className="py-3 pr-4 text-white">{totalBreakdownTokens.toLocaleString()}</td>
                  <td className="py-3 text-white">{formatCost(totalBreakdownCost)}</td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
