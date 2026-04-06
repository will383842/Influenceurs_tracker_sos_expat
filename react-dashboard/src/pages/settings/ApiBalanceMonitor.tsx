import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';

/**
 * API Balance Monitor — Check health of all AI API keys.
 *
 * Tests each API key with a minimal call (1 token) and shows:
 * - Status: ACTIVE / EMPTY / ERROR
 * - Direct link to billing page to add funds
 * - Last check timestamp
 */

interface ApiStatus {
  name: string;
  key: string;
  status: 'active' | 'empty' | 'error' | 'checking' | 'unknown';
  message: string | null;
  billingUrl: string;
  icon: string;
  usedBy: string;
}

const API_CONFIGS: Omit<ApiStatus, 'status' | 'message'>[] = [
  {
    name: 'Anthropic (Claude)',
    key: 'anthropic',
    billingUrl: 'https://console.anthropic.com/settings/billing',
    icon: '🟣',
    usedBy: 'News RSS, Q/R, Fiches Pays, Auto Q/R, Keyword Discovery',
  },
  {
    name: 'OpenAI (GPT-4o)',
    key: 'openai',
    billingUrl: 'https://platform.openai.com/settings/organization/billing/overview',
    icon: '🟢',
    usedBy: 'Articles, Comparatifs, Traductions (GPT-4o-mini)',
  },
  {
    name: 'Perplexity (Sonar)',
    key: 'perplexity',
    billingUrl: 'https://www.perplexity.ai/settings/api',
    icon: '🔵',
    usedBy: 'Recherche articles, Recherche comparatifs (phase research)',
  },
  {
    name: 'Tavily (Web Search)',
    key: 'tavily',
    billingUrl: 'https://app.tavily.com/home',
    icon: '🟠',
    usedBy: 'Fiches Pays 7 etapes (recherche web temps reel)',
  },
];

export default function ApiBalanceMonitor() {
  const [statuses, setStatuses] = useState<ApiStatus[]>(
    API_CONFIGS.map(c => ({ ...c, status: 'unknown', message: null }))
  );
  const [checking, setChecking] = useState(false);
  const [lastCheck, setLastCheck] = useState<string | null>(null);
  const [telegramStatus, setTelegramStatus] = useState<string>('');

  const checkAll = useCallback(async () => {
    setChecking(true);
    setStatuses(prev => prev.map(s => ({ ...s, status: 'checking', message: null })));

    try {
      const res = await api.get('/settings/api-health');
      const results = res.data?.results ?? {};

      setStatuses(prev => prev.map(s => {
        const r = results[s.key];
        if (!r) return { ...s, status: 'unknown', message: 'Pas de reponse' };
        return {
          ...s,
          status: r.status as ApiStatus['status'],
          message: r.message ?? null,
        };
      }));
      setLastCheck(new Date().toLocaleString('fr-FR'));
    } catch (e) {
      setStatuses(prev => prev.map(s => ({ ...s, status: 'error', message: 'Erreur API MC' })));
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => { checkAll(); }, [checkAll]);

  const sendTelegramTest = async () => {
    setTelegramStatus('Envoi...');
    try {
      await api.post('/settings/api-health/telegram-test');
      setTelegramStatus('Alerte Telegram envoyee !');
    } catch {
      setTelegramStatus('Erreur envoi Telegram');
    }
  };

  const hasEmpty = statuses.some(s => s.status === 'empty');

  const statusStyle = (status: ApiStatus['status']) => {
    switch (status) {
      case 'active': return { bg: 'bg-emerald-500/10', text: 'text-emerald-400', border: 'border-emerald-500/30', label: 'ACTIF' };
      case 'empty': return { bg: 'bg-red-500/10', text: 'text-red-400', border: 'border-red-500/30', label: 'SOLDE A ZERO' };
      case 'error': return { bg: 'bg-amber-500/10', text: 'text-amber-400', border: 'border-amber-500/30', label: 'ERREUR' };
      case 'checking': return { bg: 'bg-blue-500/10', text: 'text-blue-400', border: 'border-blue-500/30', label: 'VERIFICATION...' };
      default: return { bg: 'bg-muted/10', text: 'text-muted', border: 'border-border/30', label: 'INCONNU' };
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Soldes API IA</h1>
          <p className="text-muted text-sm mt-1">
            Monitoring des comptes API pour la generation de contenu
            {lastCheck && <span className="ml-2">— Derniere verification : {lastCheck}</span>}
          </p>
        </div>
        <button onClick={checkAll} disabled={checking}
          className="px-4 py-2 rounded-lg bg-violet text-white text-sm font-medium hover:bg-violet/80 transition-all disabled:opacity-40">
          {checking ? '⏳ Verification...' : '🔄 Verifier maintenant'}
        </button>
      </div>

      {/* Alert banner if any account is empty */}
      {hasEmpty && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4 flex items-center gap-3">
          <span className="text-2xl">🚨</span>
          <div>
            <p className="text-red-400 font-semibold">Compte(s) API a zero !</p>
            <p className="text-red-400/70 text-sm">La generation de contenu est bloquee. Rechargez immediatement.</p>
          </div>
        </div>
      )}

      {/* API Cards */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {statuses.map(s => {
          const style = statusStyle(s.status);
          return (
            <div key={s.key}
              className={`bg-surface/60 backdrop-blur border ${style.border} rounded-xl p-5 transition-all`}>
              <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-3">
                  <span className="text-2xl">{s.icon}</span>
                  <div>
                    <h3 className="text-white font-semibold">{s.name}</h3>
                    <p className="text-muted text-xs mt-0.5">{s.usedBy}</p>
                  </div>
                </div>
                <span className={`px-3 py-1 rounded-full text-xs font-bold ${style.bg} ${style.text}`}>
                  {style.label}
                </span>
              </div>

              {s.message && (
                <p className="text-sm text-muted bg-bg/50 rounded-lg px-3 py-2 mb-3 font-mono text-xs">
                  {s.message}
                </p>
              )}

              <a href={s.billingUrl} target="_blank" rel="noopener noreferrer"
                className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  s.status === 'empty'
                    ? 'bg-red-600 text-white hover:bg-red-700'
                    : 'bg-surface border border-border/30 text-muted hover:text-white'
                }`}>
                {s.status === 'empty' ? '💳 Recharger maintenant' : '🔗 Page facturation'}
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
              </a>
            </div>
          );
        })}
      </div>

      {/* Telegram Alerts */}
      <div className="bg-surface/60 backdrop-blur border border-border/20 rounded-xl p-6">
        <h2 className="text-lg font-semibold text-white mb-2">🔔 Alertes Telegram</h2>
        <p className="text-muted text-sm mb-4">
          Une verification automatique est effectuee chaque jour a 08:00 UTC.
          Si un compte est a zero, vous recevez une alerte Telegram immediate.
          Un rapport journalier est aussi envoye avec le statut de chaque API.
        </p>
        <div className="flex gap-3">
          <button onClick={sendTelegramTest}
            className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-all">
            📨 Envoyer un test Telegram
          </button>
          {telegramStatus && (
            <span className="text-sm text-emerald-400 self-center">{telegramStatus}</span>
          )}
        </div>
      </div>
    </div>
  );
}
