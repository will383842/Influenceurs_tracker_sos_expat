import React, { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { useJournal } from '../hooks/useJournal';
import { CONTACT_TYPES, getContactType } from '../lib/constants';
import type { ContactType } from '../types/influenceur';

export default function Journal() {
  const { todaySummary, weekly, loading, loadToday, loadWeekly, addNote } = useJournal();
  const [noteText, setNoteText] = useState('');
  const [noteType, setNoteType] = useState<ContactType | ''>('');

  useEffect(() => {
    loadToday();
    loadWeekly();
  }, [loadToday, loadWeekly]);

  const handleAddNote = async () => {
    if (!noteText.trim()) return;
    await addNote(noteText.trim(), noteType || undefined);
    setNoteText('');
  };

  const tooltipStyle = { backgroundColor: '#101419', border: '1px solid #1e2530', borderRadius: 8, color: '#e2e8f0' };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">📝 Journal d'Activité</h2>
        <p className="text-muted text-sm mt-1">
          {todaySummary ? `${todaySummary.total_actions} actions aujourd'hui` : 'Chargement...'}
        </p>
      </div>

      {/* Quick log input */}
      <div className="bg-surface border border-border rounded-xl p-4">
        <div className="flex gap-2">
          <select value={noteType} onChange={e => setNoteType(e.target.value as ContactType | '')}
            className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none min-w-[140px]">
            <option value="">📌 Général</option>
            {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
          </select>
          <input
            value={noteText}
            onChange={e => setNoteText(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') handleAddNote(); }}
            className="flex-1 bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-violet"
            placeholder="Action réalisée... (Entrée pour valider)"
          />
          <button onClick={handleAddNote}
            className="bg-violet hover:bg-violet/80 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors whitespace-nowrap">
            + Log
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Today's entries */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">
            Aujourd'hui ({todaySummary?.total_actions ?? 0} actions)
          </h3>
          {todaySummary?.manual_entries && todaySummary.manual_entries.length > 0 ? (
            <div className="space-y-2">
              {todaySummary.manual_entries.map(entry => {
                const ct = entry.contact_type ? getContactType(entry.contact_type) : null;
                const time = entry.details?.time as string ?? new Date(entry.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                return (
                  <div key={entry.id} className="flex items-start gap-3 py-2 border-b border-border last:border-0">
                    <span className="text-xs text-muted min-w-[40px]">{time}</span>
                    {ct && <span>{ct.icon}</span>}
                    <p className="text-sm text-white flex-1">{entry.manual_note}</p>
                  </div>
                );
              })}
            </div>
          ) : (
            <p className="text-muted text-sm">Aucune note manuelle aujourd'hui</p>
          )}

          {/* By action type */}
          {todaySummary?.by_action && Object.keys(todaySummary.by_action).length > 0 && (
            <div className="mt-4 pt-4 border-t border-border">
              <p className="text-xs text-muted mb-2">Actions automatiques</p>
              <div className="flex flex-wrap gap-2">
                {Object.entries(todaySummary.by_action).map(([action, count]) => (
                  <span key={action} className="text-[11px] bg-surface2 text-muted px-2 py-1 rounded">
                    {action}: {count}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Weekly chart */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Résumé hebdomadaire</h3>
          {weekly.length > 0 ? (
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={weekly}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
                <XAxis dataKey="date" stroke="#6b7280" tick={{ fontSize: 10 }}
                  tickFormatter={(d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short' })} />
                <YAxis stroke="#6b7280" tick={{ fontSize: 10 }} />
                <Tooltip contentStyle={tooltipStyle} />
                <Bar dataKey="total" fill="#7c3aed" radius={[4, 4, 0, 0]} name="Total" />
                <Bar dataKey="manual_count" fill="#06b6d4" radius={[4, 4, 0, 0]} name="Notes manuelles" />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm">Pas encore de données cette semaine</p>
          )}
        </div>
      </div>

      {/* By contact type */}
      {todaySummary?.by_contact_type && Object.keys(todaySummary.by_contact_type).length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-3">Par type de contact</h3>
          <div className="flex flex-wrap gap-3">
            {Object.entries(todaySummary.by_contact_type).map(([type, count]) => {
              const ct = getContactType(type as ContactType);
              return (
                <div key={type} className="flex items-center gap-2 bg-surface2 rounded-lg px-3 py-2">
                  <span>{ct.icon}</span>
                  <span className="text-sm text-white">{ct.label}</span>
                  <span className="text-sm font-bold" style={{ color: ct.color }}>{count}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
