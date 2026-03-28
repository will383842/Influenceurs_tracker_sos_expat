import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchSondages,
  fetchSondageResultats,
  type Sondage,
  type SondageResultats,
  type OptionResult,
} from '../../api/sondagesApi';
import { toast } from '../../components/Toast';

const STATUS_COLORS = {
  draft: 'bg-yellow-500/20 text-yellow-300',
  active: 'bg-green-500/20 text-green-300',
  closed: 'bg-surface2 text-muted',
};

const STATUS_LABELS = {
  draft: 'Brouillon',
  active: 'Actif',
  closed: 'Clos',
};

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function BarChart({ options, total }: { options: OptionResult[]; total: number }) {
  const max = Math.max(...options.map(o => o.count), 1);
  return (
    <div className="space-y-2 mt-3">
      {options.map((opt, i) => {
        const pct = total > 0 ? Math.round((opt.count / total) * 100) : 0;
        return (
          <div key={i} className="space-y-1">
            <div className="flex items-center justify-between text-xs">
              <span className="text-white truncate max-w-xs">{opt.label || `Option ${i + 1}`}</span>
              <span className="text-muted ml-2 shrink-0">{opt.count} ({pct}%)</span>
            </div>
            <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
              <div
                className="h-full bg-violet rounded-full transition-all"
                style={{ width: `${(opt.count / max) * 100}%` }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}

function ScaleDisplay({ avg, total }: { avg: number; total: number }) {
  const score = Math.round(avg);
  return (
    <div className="mt-3 flex items-center gap-4">
      <div className="text-center">
        <div className="text-3xl font-bold text-white">{avg.toFixed(1)}</div>
        <div className="text-xs text-muted mt-1">Moy. / 10</div>
      </div>
      <div className="flex-1 space-y-1">
        <div className="flex gap-1">
          {Array.from({ length: 10 }, (_, i) => (
            <div
              key={i}
              className={`flex-1 h-6 rounded text-xs flex items-center justify-center font-medium ${
                i + 1 <= score ? 'bg-violet text-white' : 'bg-surface2 text-muted'
              }`}
            >
              {i + 1}
            </div>
          ))}
        </div>
        <p className="text-xs text-muted">{total} réponses</p>
      </div>
    </div>
  );
}

export default function SondagesResultats() {
  const [sondages, setSondages] = useState<Sondage[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [resultats, setResultats] = useState<SondageResultats | null>(null);
  const [loadingResultats, setLoadingResultats] = useState(false);

  const loadSondages = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchSondages({ page: 1 });
      setSondages(res.data.data);
    } catch {
      toast('error', 'Erreur lors du chargement des sondages.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadSondages(); }, [loadSondages]);

  async function selectSondage(s: Sondage) {
    setSelectedId(s.id);
    setResultats(null);
    if (!s.synced_to_blog) return;
    setLoadingResultats(true);
    try {
      const res = await fetchSondageResultats(s.id);
      setResultats(res.data);
    } catch {
      toast('error', 'Impossible de récupérer les résultats depuis le Blog.');
    } finally {
      setLoadingResultats(false);
    }
  }

  const selected = sondages.find(s => s.id === selectedId) ?? null;

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="animate-pulse bg-surface2 rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Résultats de sondages</h2>
        <p className="text-sm text-muted mt-1">Analysez les réponses collectées sur le Blog</p>
      </div>

      {sondages.length === 0 ? (
        <div className="bg-surface border border-border rounded-xl p-16 text-center">
          <div className="text-5xl mb-4">📊</div>
          <p className="text-white font-medium mb-1">Aucun sondage créé</p>
          <p className="text-sm text-muted">Créez d'abord un sondage dans l'onglet "Sondages".</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Liste */}
          <div className="space-y-2">
            <p className="text-xs text-muted uppercase tracking-wide px-1 mb-3">
              {sondages.length} sondage{sondages.length > 1 ? 's' : ''}
            </p>
            {sondages.map(s => (
              <button
                key={s.id}
                onClick={() => selectSondage(s)}
                className={`w-full text-left px-4 py-3 rounded-xl border transition-colors ${
                  selectedId === s.id
                    ? 'bg-violet/20 border-violet text-white'
                    : 'bg-surface border-border text-muted hover:text-white hover:border-violet/40'
                }`}
              >
                <div className="font-medium text-sm truncate">{s.title}</div>
                <div className="flex items-center gap-2 mt-1.5">
                  <span className={`px-1.5 py-0.5 rounded text-xs ${STATUS_COLORS[s.status]}`}>
                    {STATUS_LABELS[s.status]}
                  </span>
                  <span className="text-xs text-muted">{s.language.toUpperCase()}</span>
                  {!s.synced_to_blog && (
                    <span className="text-xs text-yellow-400 ml-auto">Non publié</span>
                  )}
                </div>
              </button>
            ))}
          </div>

          {/* Détail */}
          <div className="lg:col-span-2">
            {!selected ? (
              <div className="bg-surface border border-border rounded-xl p-12 text-center text-muted">
                <div className="text-3xl mb-3">👈</div>
                <p className="text-sm">Sélectionnez un sondage pour voir ses résultats</p>
              </div>
            ) : !selected.synced_to_blog ? (
              <div className="bg-surface border border-border rounded-xl p-12 text-center">
                <div className="text-3xl mb-3">⚠️</div>
                <p className="text-white font-medium mb-1">Sondage non publié sur le Blog</p>
                <p className="text-sm text-muted">Cliquez sur "↑ Blog" dans l'onglet Sondages pour le synchroniser d'abord.</p>
              </div>
            ) : loadingResultats ? (
              <div className="space-y-4">
                <div className="animate-pulse bg-surface2 rounded-xl h-40" />
                <div className="animate-pulse bg-surface2 rounded-xl h-32" />
              </div>
            ) : resultats ? (
              <div className="space-y-4">
                {/* Récapitulatif */}
                <div className="bg-surface border border-border rounded-xl p-5">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <h3 className="font-title text-lg font-semibold text-white">{selected.title}</h3>
                      <p className="text-xs text-muted mt-1">Créé le {formatDate(selected.created_at)}</p>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[selected.status]}`}>
                      {STATUS_LABELS[selected.status]}
                    </span>
                  </div>
                  <div className="grid grid-cols-3 gap-4">
                    <div className="bg-surface2 rounded-lg p-3 text-center">
                      <div className="text-2xl font-bold text-white">{resultats.responses_count}</div>
                      <div className="text-xs text-muted mt-1">Réponses</div>
                    </div>
                    <div className="bg-surface2 rounded-lg p-3 text-center">
                      <div className="text-2xl font-bold text-white">{resultats.questions.length}</div>
                      <div className="text-xs text-muted mt-1">Questions</div>
                    </div>
                    <div className="bg-surface2 rounded-lg p-3 text-center">
                      <div className="text-2xl font-bold text-white">{resultats.completion_rate}%</div>
                      <div className="text-xs text-muted mt-1">Complétion</div>
                    </div>
                  </div>
                </div>

                {/* Questions */}
                {resultats.questions.map((q, i) => (
                  <div key={q.id} className="bg-surface border border-border rounded-xl p-5">
                    <div className="flex items-start justify-between mb-1">
                      <p className="text-sm font-medium text-white">
                        <span className="text-muted mr-2">Q{i + 1}.</span>{q.text}
                      </p>
                      <span className="text-xs text-muted ml-3 shrink-0">{q.total_responses} rép.</span>
                    </div>

                    {(q.type === 'single' || q.type === 'multiple') && q.options && (
                      <BarChart options={q.options} total={q.total_responses} />
                    )}

                    {q.type === 'scale' && q.avg_score !== undefined && (
                      <ScaleDisplay avg={q.avg_score} total={q.total_responses} />
                    )}

                    {q.type === 'open' && q.open_answers && (
                      <div className="mt-3 space-y-2 max-h-48 overflow-y-auto">
                        {q.open_answers.length === 0 ? (
                          <p className="text-xs text-muted">Aucune réponse libre.</p>
                        ) : (
                          q.open_answers.map((ans, j) => (
                            <div key={j} className="bg-surface2 rounded-lg px-3 py-2 text-sm text-white">
                              "{ans}"
                            </div>
                          ))
                        )}
                      </div>
                    )}
                  </div>
                ))}

                <div className="flex justify-end gap-2">
                  <button className="px-4 py-2 text-sm bg-surface2 hover:bg-surface border border-border text-muted hover:text-white rounded-lg transition-colors">
                    Exporter CSV
                  </button>
                </div>
              </div>
            ) : (
              <div className="bg-surface border border-border rounded-xl p-12 text-center text-muted">
                <p className="text-sm">Impossible de charger les résultats.</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
