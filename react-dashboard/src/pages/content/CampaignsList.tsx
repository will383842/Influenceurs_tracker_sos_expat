import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchCampaigns,
  startCampaign,
  pauseCampaign,
  resumeCampaign,
  cancelCampaign,
  deleteCampaign,
} from '../../api/contentApi';
import type { ContentCampaign, CampaignStatus, CampaignType, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<CampaignStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  running: 'bg-success/20 text-success animate-pulse',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-blue-500/20 text-blue-400',
  cancelled: 'bg-danger/20 text-danger',
};

const STATUS_LABELS: Record<CampaignStatus, string> = {
  draft: 'Brouillon',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Termine',
  cancelled: 'Annule',
};

const TYPE_LABELS: Record<CampaignType, string> = {
  country_coverage: 'Couverture pays',
  thematic: 'Thematique',
  pillar_cluster: 'Pilier / Cluster',
  comparative_series: 'Serie comparative',
  custom: 'Personnalise',
};

const TYPE_COLORS: Record<CampaignType, string> = {
  country_coverage: 'bg-blue-500/20 text-blue-400',
  thematic: 'bg-violet/20 text-violet-light',
  pillar_cluster: 'bg-amber/20 text-amber',
  comparative_series: 'bg-success/20 text-success',
  custom: 'bg-muted/20 text-muted',
};

function cents(n: number): string {
  return (n / 100).toFixed(2);
}

// ── Component ───────────────────────────────────────────────
export default function CampaignsList() {
  const navigate = useNavigate();
  const [campaigns, setCampaigns] = useState<ContentCampaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadCampaigns = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCampaigns({ page });
      const data = res.data as unknown as PaginatedResponse<ContentCampaign>;
      setCampaigns(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadCampaigns(1); }, [loadCampaigns]);

  const execAction = async (id: number, action: string) => {
    setActionLoading(id);
    try {
      switch (action) {
        case 'start':
          await startCampaign(id);
          toast('success', 'Campagne demarree.');
          break;
        case 'pause':
          await pauseCampaign(id);
          toast('success', 'Campagne mise en pause.');
          break;
        case 'resume':
          await resumeCampaign(id);
          toast('success', 'Campagne reprise.');
          break;
        case 'cancel':
          await cancelCampaign(id);
          toast('success', 'Campagne annulee.');
          break;
        case 'delete':
          await deleteCampaign(id);
          toast('success', 'Campagne supprimee.');
          break;
      }
      loadCampaigns(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleAction = (id: number, action: string) => {
    if (action === 'cancel') {
      setConfirmAction({ title: 'Annuler la campagne', message: 'Confirmer l\'annulation de cette campagne ?', action: () => execAction(id, action) });
    } else if (action === 'delete') {
      setConfirmAction({ title: 'Supprimer la campagne', message: 'Cette action est irreversible. Confirmer la suppression ?', action: () => execAction(id, action) });
    } else {
      execAction(id, action);
    }
  };

  // Stat cards
  const totalCampaigns = pagination.total;
  const runningCount = campaigns.filter(c => c.status === 'running').length;
  const completedCount = campaigns.filter(c => c.status === 'completed').length;
  const totalArticles = campaigns.reduce((s, c) => s + c.completed_items, 0);

  const statCards = [
    { label: 'Total campagnes', value: totalCampaigns, color: 'text-violet bg-violet/20' },
    { label: 'En cours', value: runningCount, color: 'text-success bg-success/20' },
    { label: 'Terminees', value: completedCount, color: 'text-blue-400 bg-blue-500/20' },
    { label: 'Articles generes', value: totalArticles, color: 'text-amber bg-amber/20' },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Campagnes</h2>
        <button
          onClick={() => navigate('/content/campaigns/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouvelle campagne
        </button>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
            <p className={`text-2xl font-bold mt-2 ${card.color.split(' ')[0]}`}>{card.value}</p>
          </div>
        ))}
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        {error && (
          <div className="flex items-center justify-between bg-danger/10 border border-danger/30 rounded-lg p-3 mb-4">
            <p className="text-danger text-sm">{error}</p>
            <button onClick={() => loadCampaigns(1)} className="text-xs text-danger hover:text-red-300 transition-colors">Reessayer</button>
          </div>
        )}

        {loading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map(i => (
              <div key={i} className="animate-pulse bg-surface2 rounded-lg h-14" />
            ))}
          </div>
        ) : campaigns.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucune campagne. Creez une campagne pour generer du contenu en masse.</p>
            <button onClick={() => navigate('/content/campaigns/new')} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Creer une campagne
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Nom</th>
                  <th className="pb-3 pr-4">Type</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">Progression</th>
                  <th className="pb-3 pr-4">Cout</th>
                  <th className="pb-3 pr-4">Demarre</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {campaigns.map(campaign => {
                  const progressPct = campaign.total_items > 0
                    ? Math.round((campaign.completed_items / campaign.total_items) * 100)
                    : 0;
                  return (
                    <tr key={campaign.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-4 cursor-pointer" onClick={() => navigate(`/content/campaigns/${campaign.id}`)}>
                        <span className="text-white font-medium truncate block max-w-[200px] hover:text-violet-light transition-colors">
                          {campaign.name}
                        </span>
                        {campaign.description && <p className="text-xs text-muted truncate max-w-[200px]">{campaign.description}</p>}
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] ${TYPE_COLORS[campaign.campaign_type]}`}>
                          {TYPE_LABELS[campaign.campaign_type]}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[campaign.status]}`}>
                          {STATUS_LABELS[campaign.status]}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <div className="flex items-center gap-2">
                          <div className="w-20 h-1.5 bg-surface2 rounded-full overflow-hidden">
                            <div className="h-full bg-violet rounded-full transition-all" style={{ width: `${progressPct}%` }} />
                          </div>
                          <span className="text-xs text-muted">{campaign.completed_items}/{campaign.total_items}</span>
                        </div>
                        {campaign.failed_items > 0 && (
                          <p className="text-[10px] text-danger mt-0.5">{campaign.failed_items} echec(s)</p>
                        )}
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">${cents(campaign.total_cost_cents)}</td>
                      <td className="py-3 pr-4 text-muted text-xs">
                        {campaign.started_at
                          ? new Date(campaign.started_at).toLocaleDateString('fr-FR')
                          : '-'}
                      </td>
                      <td className="py-3">
                        <div className="flex items-center gap-2">
                          <button onClick={() => navigate(`/content/campaigns/${campaign.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                            Voir
                          </button>
                          {campaign.status === 'draft' && (
                            <button
                              onClick={() => handleAction(campaign.id, 'start')}
                              disabled={actionLoading === campaign.id}
                              className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                            >
                              Demarrer
                            </button>
                          )}
                          {campaign.status === 'running' && (
                            <button
                              onClick={() => handleAction(campaign.id, 'pause')}
                              disabled={actionLoading === campaign.id}
                              className="text-xs text-amber hover:text-yellow-300 transition-colors disabled:opacity-50"
                            >
                              Pause
                            </button>
                          )}
                          {campaign.status === 'paused' && (
                            <button
                              onClick={() => handleAction(campaign.id, 'resume')}
                              disabled={actionLoading === campaign.id}
                              className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                            >
                              Reprendre
                            </button>
                          )}
                          {(campaign.status === 'running' || campaign.status === 'paused') && (
                            <button
                              onClick={() => handleAction(campaign.id, 'cancel')}
                              disabled={actionLoading === campaign.id}
                              className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                            >
                              Annuler
                            </button>
                          )}
                          <button
                            onClick={() => handleAction(campaign.id, 'delete')}
                            disabled={actionLoading === campaign.id}
                            className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                          >
                            Suppr
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {pagination.last_page > 1 && (
          <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
            <span className="text-xs text-muted">{pagination.total} campagne(s)</span>
            <div className="flex gap-2">
              <button onClick={() => loadCampaigns(pagination.current_page - 1)} disabled={pagination.current_page <= 1} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">Precedent</button>
              <span className="px-3 py-1 text-xs text-muted">{pagination.current_page} / {pagination.last_page}</span>
              <button onClick={() => loadCampaigns(pagination.current_page + 1)} disabled={pagination.current_page >= pagination.last_page} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">Suivant</button>
            </div>
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
