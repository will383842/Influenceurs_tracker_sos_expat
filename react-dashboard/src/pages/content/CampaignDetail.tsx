import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchCampaign,
  fetchCampaignItems,
  startCampaign,
  pauseCampaign,
  resumeCampaign,
  cancelCampaign,
} from '../../api/contentApi';
import type { ContentCampaign, ContentCampaignItem, CampaignStatus } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const CAMPAIGN_STATUS_COLORS: Record<CampaignStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  running: 'bg-success/20 text-success animate-pulse',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-success/20 text-success',
  cancelled: 'bg-danger/20 text-danger',
};

const CAMPAIGN_STATUS_LABELS: Record<CampaignStatus, string> = {
  draft: 'Brouillon',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Termine',
  cancelled: 'Annule',
};

const ITEM_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  completed: 'bg-success/20 text-success',
  failed: 'bg-danger/20 text-danger',
  skipped: 'bg-muted/20 text-muted line-through',
};

const ITEM_STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  generating: 'Generation...',
  completed: 'Termine',
  failed: 'Echoue',
  skipped: 'Ignore',
};

const CAMPAIGN_TYPE_LABELS: Record<string, string> = {
  country_coverage: 'Couverture pays',
  thematic: 'Thematique',
  pillar_cluster: 'Pilier/Cluster',
  comparative_series: 'Serie comparative',
  custom: 'Personnalise',
};

function formatNumber(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K';
  return String(n);
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ── Component ───────────────────────────────────────────────
export default function CampaignDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [campaign, setCampaign] = useState<ContentCampaign | null>(null);
  const [items, setItems] = useState<ContentCampaignItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);
  const refreshRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadData = useCallback(async () => {
    if (!id) return;
    try {
      const [campRes, itemsRes] = await Promise.all([
        fetchCampaign(Number(id)),
        fetchCampaignItems(Number(id)),
      ]);
      setCampaign(campRes.data as unknown as ContentCampaign);
      setItems((itemsRes.data as unknown as ContentCampaignItem[]) ?? []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    }
  }, [id]);

  const loadInitial = useCallback(async () => {
    setLoading(true);
    setError(null);
    await loadData();
    setLoading(false);
  }, [loadData]);

  useEffect(() => { loadInitial(); }, [loadInitial]);

  // Auto-refresh when running
  useEffect(() => {
    if (campaign?.status === 'running') {
      refreshRef.current = setInterval(() => { loadData(); }, 5000);
    } else if (refreshRef.current) {
      clearInterval(refreshRef.current);
      refreshRef.current = null;
    }
    return () => {
      if (refreshRef.current) clearInterval(refreshRef.current);
    };
  }, [campaign?.status, loadData]);

  const execCampaignAction = async (action: string) => {
    if (!campaign) return;
    setActionLoading(action);
    try {
      if (action === 'start') await startCampaign(campaign.id);
      else if (action === 'pause') await pauseCampaign(campaign.id);
      else if (action === 'resume') await resumeCampaign(campaign.id);
      else if (action === 'cancel') await cancelCampaign(campaign.id);
      toast('success', action === 'start' ? 'Campagne demarree.' : action === 'pause' ? 'Campagne en pause.' : action === 'resume' ? 'Campagne reprise.' : 'Campagne annulee.');
      await loadData();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleAction = (action: string) => {
    if (action === 'cancel') {
      setConfirmAction({ title: 'Annuler la campagne', message: 'Confirmer l\'annulation de cette campagne ?', action: () => execCampaignAction(action) });
    } else {
      execCampaignAction(action);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="animate-pulse bg-surface2 rounded-xl h-24 mb-4" />
        <div className="space-y-3">
          {[1, 2, 3, 4, 5].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}
        </div>
      </div>
    );
  }

  if (error || !campaign) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Campagne introuvable'}</p>
          <button onClick={() => navigate('/content/campaigns')} className="text-sm text-violet hover:text-violet-light transition-colors">Retour aux campagnes</button>
        </div>
      </div>
    );
  }

  const progressPercent = campaign.total_items > 0
    ? Math.round((campaign.completed_items / campaign.total_items) * 100)
    : 0;
  const costDollars = (campaign.total_cost_cents / 100).toFixed(2);

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/campaigns')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour aux campagnes
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{campaign.name}</h2>
          <div className="flex items-center gap-3 mt-2 flex-wrap">
            <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">
              {CAMPAIGN_TYPE_LABELS[campaign.campaign_type] ?? campaign.campaign_type}
            </span>
            <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${CAMPAIGN_STATUS_COLORS[campaign.status]}`}>
              {CAMPAIGN_STATUS_LABELS[campaign.status]}
            </span>
            {campaign.started_at && <span className="text-xs text-muted">Debut: {formatDate(campaign.started_at)}</span>}
            {campaign.completed_at && <span className="text-xs text-muted">Fin: {formatDate(campaign.completed_at)}</span>}
          </div>
          {campaign.description && <p className="text-sm text-muted mt-2">{campaign.description}</p>}
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {campaign.status === 'draft' && (
            <button onClick={() => handleAction('start')} disabled={!!actionLoading} className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
              {actionLoading === 'start' ? 'Demarrage...' : 'Demarrer'}
            </button>
          )}
          {campaign.status === 'running' && (
            <button onClick={() => handleAction('pause')} disabled={!!actionLoading} className="px-4 py-1.5 bg-amber/80 hover:bg-amber text-white text-sm rounded-lg transition-colors disabled:opacity-50">
              {actionLoading === 'pause' ? 'Pause...' : 'Pause'}
            </button>
          )}
          {campaign.status === 'paused' && (
            <button onClick={() => handleAction('resume')} disabled={!!actionLoading} className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
              {actionLoading === 'resume' ? 'Reprise...' : 'Reprendre'}
            </button>
          )}
          {(campaign.status === 'draft' || campaign.status === 'running' || campaign.status === 'paused') && (
            <button onClick={() => handleAction('cancel')} disabled={!!actionLoading} className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors disabled:opacity-50">
              Annuler
            </button>
          )}
        </div>
      </div>

      {/* Progress */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-4">
            <span className="text-sm text-white font-medium">{campaign.completed_items}/{campaign.total_items} items</span>
            {campaign.failed_items > 0 && (
              <span className="text-sm text-danger">{campaign.failed_items} echec(s)</span>
            )}
          </div>
          <div className="flex items-center gap-4">
            <span className="text-sm text-muted">Cout: ${costDollars}</span>
            <span className="text-sm font-bold text-white">{progressPercent}%</span>
          </div>
        </div>
        <div className="w-full h-3 bg-surface2 rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              campaign.status === 'running' ? 'bg-success' :
              campaign.status === 'completed' ? 'bg-success' :
              campaign.status === 'cancelled' ? 'bg-danger' :
              'bg-violet'
            }`}
            style={{ width: `${progressPercent}%` }}
          />
        </div>
        {campaign.status === 'running' && (
          <p className="text-xs text-muted mt-2 animate-pulse">Actualisation automatique toutes les 5 secondes...</p>
        )}
      </div>

      {/* Items table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title font-semibold text-white mb-4">Items ({items.length})</h3>
        {items.length === 0 ? (
          <p className="text-center text-muted text-sm py-6">Aucun item dans cette campagne.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">#</th>
                  <th className="pb-3 pr-4">Titre</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">Article</th>
                  <th className="pb-3 pr-4">Erreur</th>
                  <th className="pb-3">Planifie</th>
                </tr>
              </thead>
              <tbody>
                {items.sort((a, b) => a.sort_order - b.sort_order).map(item => (
                  <tr key={item.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                    <td className="py-3 pr-4 text-muted text-xs">{item.sort_order}</td>
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[300px]">{item.title_hint}</span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${ITEM_STATUS_COLORS[item.status] ?? 'bg-muted/20 text-muted'}`}>
                        {ITEM_STATUS_LABELS[item.status] ?? item.status}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      {item.itemable ? (
                        <button
                          onClick={() => {
                            const article = item.itemable;
                            if (article && 'slug' in article) {
                              navigate(`/content/articles/${article.id}`);
                            }
                          }}
                          className="text-xs text-violet hover:text-violet-light transition-colors"
                        >
                          Voir article
                        </button>
                      ) : (
                        <span className="text-xs text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3 pr-4">
                      {item.error_message ? (
                        <span className="text-xs text-danger truncate block max-w-[200px]" title={item.error_message}>
                          {item.error_message.length > 50 ? item.error_message.slice(0, 50) + '...' : item.error_message}
                        </span>
                      ) : (
                        <span className="text-xs text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3">
                      {item.scheduled_at ? (
                        <span className="text-xs text-muted">{formatDate(item.scheduled_at)}</span>
                      ) : (
                        <span className="text-xs text-muted">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant="danger"
        confirmLabel="Confirmer"
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
