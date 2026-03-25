import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface HubStats {
  emails_sent: number;
  emails_opened: number;
  pending_review: number;
  active_sequences: number;
  eligible_contacts: number;
  configured_types: number;
  bounce_rate: number;
  open_rate: number;
  reply_rate: number;
  alerts_count: number;
}

export default function ProspectionHub() {
  const [stats, setStats] = useState<HubStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const [statsRes, alertsRes, seqRes] = await Promise.all([
          api.get('/outreach/stats'),
          api.get('/outreach/alerts').catch(() => ({ data: [] })),
          api.get('/outreach/sequences?status=active').catch(() => ({ data: { total: 0 } })),
        ]);
        const g = statsRes.data.global;
        const sent = g?.sent || 0;
        setStats({
          emails_sent: sent,
          emails_opened: g?.opened || 0,
          pending_review: g?.pending_review || 0,
          active_sequences: seqRes.data?.total || seqRes.data?.data?.length || 0,
          eligible_contacts: g?.total || 0,
          configured_types: statsRes.data.by_type?.length || 0,
          bounce_rate: sent > 0 ? Math.round((g?.bounced || 0) / sent * 100) : 0,
          open_rate: sent > 0 ? Math.round((g?.opened || 0) / sent * 100) : 0,
          reply_rate: sent > 0 ? Math.round((g?.replied || 0) / sent * 100) : 0,
          alerts_count: Array.isArray(alertsRes.data) ? alertsRes.data.length : 0,
        });
      } catch { /* ignore */ }
      setLoading(false);
    })();
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const kpis = [
    { label: 'Emails envoyes', value: stats?.emails_sent || 0, color: 'text-cyan' },
    { label: 'Taux ouverture', value: `${stats?.open_rate || 0}%`, color: 'text-blue-400' },
    { label: 'Taux reponse', value: `${stats?.reply_rate || 0}%`, color: 'text-green-400' },
    { label: 'Bounce rate', value: `${stats?.bounce_rate || 0}%`, color: (stats?.bounce_rate || 0) > 5 ? 'text-red-400' : 'text-muted' },
  ];

  const cards = [
    {
      to: '/prospection/campaign', icon: '🚀', title: 'Lancer une campagne',
      description: 'Choisir un segment, ecrire un email modele, l\'IA l\'adapte pour chaque contact',
      badge: null,
    },
    {
      to: '/prospection/overview', icon: '📊', title: 'Vue d\'ensemble',
      description: 'KPIs, funnel de conversion, stats par step et par type, alertes',
      badge: stats && stats.alerts_count > 0 ? { text: `${stats.alerts_count} alerte${stats.alerts_count > 1 ? 's' : ''}`, color: 'bg-red-500/20 text-red-400' } : null,
    },
    {
      to: '/prospection/emails', icon: '✉️', title: 'Emails',
      description: 'Generer avec l\'IA, reviewer, approuver et suivre les envois',
      badge: stats && stats.pending_review > 0 ? { text: `${stats.pending_review} en review`, color: 'bg-amber/20 text-amber' } : null,
    },
    {
      to: '/prospection/sequences', icon: '🔄', title: 'Sequences',
      description: 'Suivi des sequences multi-step, timeline par contact, pause/stop',
      badge: stats ? { text: `${stats.active_sequences} actives`, color: 'bg-emerald-500/20 text-emerald-400' } : null,
    },
    {
      to: '/prospection/contacts', icon: '👥', title: 'Contacts eligibles',
      description: 'Contacts avec email verifie, prets pour la prospection',
      badge: null,
    },
    {
      to: '/prospection/config', icon: '⚙️', title: 'Configuration',
      description: 'Delais entre steps, Calendly, prompts IA, domaines, warm-up',
      badge: null,
    },
    {
      to: '/admin/campaigns', icon: '🚀', title: 'Campagnes de recherche',
      description: 'Recherche automatique de contacts par IA (Perplexity)',
      badge: null,
    },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-title font-bold text-white">Prospection Email</h1>
        <p className="text-muted text-sm mt-1">Generation IA, sequences multi-step, suivi et statistiques</p>
      </div>

      {/* Alert banner */}
      {stats && stats.alerts_count > 0 && (
        <Link to="/prospection/overview" className="block bg-red-500/10 border border-red-500/30 rounded-xl p-4 hover:border-red-500/50 transition-colors">
          <div className="flex items-center gap-3">
            <span className="text-red-400 text-lg">⚠</span>
            <div>
              <p className="text-red-400 text-sm font-medium">{stats.alerts_count} alerte{stats.alerts_count > 1 ? 's' : ''} active{stats.alerts_count > 1 ? 's' : ''}</p>
              <p className="text-red-400/60 text-xs">Cliquez pour voir les details dans la vue d'ensemble</p>
            </div>
          </div>
        </Link>
      )}

      {/* KPIs row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {kpis.map((kpi, i) => (
          <div key={i} className="bg-surface border border-border rounded-xl p-4">
            <p className="text-[10px] text-muted uppercase tracking-wider mb-1">{kpi.label}</p>
            <p className={`text-2xl font-bold font-title ${kpi.color}`}>{kpi.value}</p>
          </div>
        ))}
      </div>

      {/* Hub cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {cards.map(card => (
          <Link key={card.to} to={card.to}
            className="bg-surface border border-border rounded-xl p-5 hover:border-violet/50 transition-all group">
            <div className="flex items-start justify-between mb-2">
              <div className="flex items-center gap-3">
                <span className="text-2xl">{card.icon}</span>
                <h3 className="text-white font-title font-semibold group-hover:text-violet-light transition-colors">{card.title}</h3>
              </div>
              {card.badge && (
                <span className={`px-2 py-0.5 text-[10px] rounded-full font-medium ${card.badge.color}`}>{card.badge.text}</span>
              )}
            </div>
            <p className="text-muted text-sm">{card.description}</p>
          </Link>
        ))}
      </div>

      {/* Quick actions */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="text-white font-title font-semibold mb-3">Actions rapides</h3>
        <div className="flex flex-wrap gap-3">
          <Link to="/prospection/campaign" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors font-medium">
            Lancer une campagne
          </Link>
          {stats && stats.pending_review > 0 && (
            <Link to="/prospection/emails" className="px-4 py-2 bg-amber/20 text-amber text-sm rounded-lg hover:bg-amber/30 transition-colors">
              Reviewer {stats.pending_review} email{stats.pending_review > 1 ? 's' : ''}
            </Link>
          )}
          <Link to="/prospection/config" className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
            Configurer les types
          </Link>
          <Link to="/prospection/overview" className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
            Voir les statistiques
          </Link>
        </div>
      </div>

      {/* How it works */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="text-white font-title font-semibold mb-4">Comment ca marche</h3>
        <div className="flex flex-col md:flex-row items-start gap-4">
          {[
            { step: '1', title: 'Configurer', desc: 'Definir les delais, prompts et limites par type de contact', icon: '⚙️' },
            { step: '2', title: 'Generer', desc: 'L\'IA cree un email personnalise pour chaque contact', icon: '🤖' },
            { step: '3', title: 'Reviewer', desc: 'Approuver, editer ou rejeter chaque email avant envoi', icon: '👁️' },
            { step: '4', title: 'Envoyer', desc: 'Envoi automatique avec warm-up progressif des domaines', icon: '📤' },
            { step: '5', title: 'Suivre', desc: 'Sequences auto: relances J+3, J+7, J+14 si pas de reponse', icon: '📊' },
          ].map((s, i) => (
            <React.Fragment key={i}>
              {i > 0 && <div className="hidden md:flex items-center text-muted/30 text-lg pt-4">&rarr;</div>}
              <div className="flex-1 text-center">
                <div className="w-10 h-10 rounded-full bg-violet/20 flex items-center justify-center mx-auto mb-2">
                  <span className="text-lg">{s.icon}</span>
                </div>
                <p className="text-white text-sm font-medium">{s.title}</p>
                <p className="text-[11px] text-muted mt-1">{s.desc}</p>
              </div>
            </React.Fragment>
          ))}
        </div>
      </div>
    </div>
  );
}
