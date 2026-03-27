import React, { useEffect, useState, useCallback } from 'react';
import { fetchTaxonomyDistribution, updateTaxonomyDistribution } from '../../api/contentApi';
import type { TaxonomyDistribution } from '../../types/content';
import { toast } from '../../components/Toast';
import { inputClass, errMsg } from './helpers';

const DEFAULT_TAXONOMIES: TaxonomyDistribution[] = [
  { content_type: 'article', label: 'Article standard', percentage: 35, is_active: true },
  { content_type: 'guide', label: 'Guide (pilier)', percentage: 10, is_active: true },
  { content_type: 'qa', label: 'Q&A / FAQ', percentage: 20, is_active: true },
  { content_type: 'comparative', label: 'Comparatif', percentage: 10, is_active: true },
  { content_type: 'news', label: 'Actualite', percentage: 10, is_active: true },
  { content_type: 'tutorial', label: 'Tutoriel', percentage: 5, is_active: true },
  { content_type: 'landing', label: 'Landing page', percentage: 5, is_active: true },
  { content_type: 'press_release', label: 'Communique presse', percentage: 5, is_active: true },
];

const TYPE_INFO: Record<string, { words: string; model: string; desc: string }> = {
  article: { words: '2000-3000', model: 'GPT-4o', desc: 'Article standard base sur les sources scrappees' },
  guide: { words: '4000-7000', model: 'GPT-4o', desc: 'Article pilier long format, recherche approfondie' },
  qa: { words: '800-2000', model: 'GPT-4o', desc: 'Question/reponse avec featured snippet' },
  comparative: { words: '2500-4000', model: 'GPT-4o', desc: 'Comparaison pays vs pays avec tableaux' },
  news: { words: '800-1500', model: 'GPT-4o-mini', desc: 'Article d\'actualite, generation rapide' },
  tutorial: { words: '1500-3000', model: 'GPT-4o', desc: 'Guide pas-a-pas avec etapes structurees' },
  landing: { words: '800-1500', model: 'GPT-4o', desc: 'Page de destination avec CTA' },
  press_release: { words: '500-1000', model: 'GPT-4o', desc: 'Format presse standard' },
};

export default function TaxonomyManager() {
  const [taxonomies, setTaxonomies] = useState<TaxonomyDistribution[]>(DEFAULT_TAXONOMIES);
  const [totalPerDay, setTotalPerDay] = useState(20);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchTaxonomyDistribution();
      const data = res.data as any;
      if (data?.distribution && data.distribution.length > 0) {
        setTaxonomies(data.distribution);
      }
      if (data?.total_articles_per_day) {
        setTotalPerDay(data.total_articles_per_day);
      }
    } catch {
      // Use defaults if API not ready yet
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const totalPercentage = taxonomies.filter(t => t.is_active).reduce((sum, t) => sum + t.percentage, 0);
  const isValid = totalPercentage === 100;

  const updateTaxonomy = (index: number, field: keyof TaxonomyDistribution, value: any) => {
    setTaxonomies(prev => {
      const updated = [...prev];
      updated[index] = { ...updated[index], [field]: value };
      return updated;
    });
    setDirty(true);
  };

  const handleSave = async () => {
    if (!isValid) {
      toast('error', `Le total des pourcentages doit etre 100% (actuellement ${totalPercentage}%).`);
      return;
    }
    setSaving(true);
    try {
      await updateTaxonomyDistribution({
        total_articles_per_day: totalPerDay,
        distribution: taxonomies,
      });
      toast('success', 'Distribution sauvegardee.');
      setDirty(false);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-72" />
        <div className="animate-pulse bg-surface2 rounded-xl h-96" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h1 className="text-xl font-semibold text-t1">Taxonomies — Repartition du contenu</h1>

      {/* Total articles per day - THE main control */}
      <div className="bg-surface border-2 border-violet/30 rounded-xl p-6">
        <div className="flex items-center gap-6">
          <div>
            <label className="block text-sm font-medium text-t1 mb-1">Total articles / jour</label>
            <p className="text-xs text-t3">Le nombre total d'articles a generer quotidiennement (toutes taxonomies confondues)</p>
          </div>
          <input type="number" min={1} max={500} value={totalPerDay}
                 onChange={e => { setTotalPerDay(Math.max(1, +e.target.value)); setDirty(true); }}
                 className="w-24 text-2xl font-bold text-center bg-surface2 border border-border rounded-xl py-3 text-t1 focus:ring-2 focus:ring-violet focus:border-violet" />
        </div>
      </div>

      {/* Percentage validation */}
      {!isValid && (
        <div className={`p-3 rounded-lg text-sm ${totalPercentage > 100 ? 'bg-danger/10 text-danger' : 'bg-amber/10 text-amber'}`}>
          Total : {totalPercentage}% — {totalPercentage > 100 ? 'Depasse 100% !' : `Il manque ${100 - totalPercentage}%`}
        </div>
      )}

      {/* Distribution table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-t3 text-xs uppercase tracking-wider border-b border-border bg-surface2/50">
              <th className="text-left py-3 px-4">Actif</th>
              <th className="text-left py-3 px-4">Taxonomie</th>
              <th className="text-left py-3 px-4">Pourcentage</th>
              <th className="text-left py-3 px-4">Repartition visuelle</th>
              <th className="text-center py-3 px-4">Articles / jour</th>
              <th className="text-left py-3 px-4">Mots</th>
              <th className="text-left py-3 px-4">Modele IA</th>
            </tr>
          </thead>
          <tbody>
            {taxonomies.map((tax, index) => {
              const info = TYPE_INFO[tax.content_type] ?? { words: '—', model: '—', desc: '' };
              const calculated = tax.is_active ? Math.round(totalPerDay * tax.percentage / 100) : 0;
              return (
                <tr key={tax.content_type} className={`border-b border-border/50 ${!tax.is_active ? 'opacity-40' : ''}`}>
                  <td className="py-3 px-4">
                    <input type="checkbox" checked={tax.is_active}
                           onChange={e => updateTaxonomy(index, 'is_active', e.target.checked)}
                           className="rounded border-border" />
                  </td>
                  <td className="py-3 px-4">
                    <p className="font-medium text-t1">{tax.label}</p>
                    <p className="text-[10px] text-t3">{info.desc}</p>
                  </td>
                  <td className="py-3 px-4 w-32">
                    <div className="flex items-center gap-2">
                      <input type="number" min={0} max={100} value={tax.percentage}
                             onChange={e => updateTaxonomy(index, 'percentage', Math.max(0, Math.min(100, +e.target.value)))}
                             disabled={!tax.is_active}
                             className="w-16 text-center text-sm bg-surface2 border border-border rounded-lg py-1.5 text-t1 disabled:opacity-50" />
                      <span className="text-xs text-t3">%</span>
                    </div>
                  </td>
                  <td className="py-3 px-4 w-48">
                    <div className="h-4 bg-surface2 rounded-full overflow-hidden">
                      <div className="h-full bg-violet rounded-full transition-all duration-300"
                           style={{ width: `${tax.is_active ? tax.percentage : 0}%` }} />
                    </div>
                  </td>
                  <td className="py-3 px-4 text-center">
                    <span className="text-lg font-bold text-t1">{calculated}</span>
                    <span className="text-xs text-t3"> /jour</span>
                  </td>
                  <td className="py-3 px-4">
                    <span className="text-xs text-t2">{info.words}</span>
                  </td>
                  <td className="py-3 px-4">
                    <span className={`px-2 py-0.5 rounded text-[10px] ${info.model === 'GPT-4o' ? 'bg-violet/20 text-violet' : 'bg-info/20 text-info'}`}>
                      {info.model}
                    </span>
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr className="bg-surface2/50 font-medium">
              <td colSpan={2} className="py-3 px-4 text-t1">Total</td>
              <td className="py-3 px-4">
                <span className={`text-sm font-bold ${isValid ? 'text-success' : 'text-danger'}`}>{totalPercentage}%</span>
              </td>
              <td className="py-3 px-4">
                <div className="h-4 bg-surface2 rounded-full overflow-hidden">
                  <div className={`h-full rounded-full ${isValid ? 'bg-success' : 'bg-danger'}`}
                       style={{ width: `${Math.min(100, totalPercentage)}%` }} />
                </div>
              </td>
              <td className="py-3 px-4 text-center">
                <span className="text-lg font-bold text-t1">{totalPerDay}</span>
              </td>
              <td colSpan={2} />
            </tr>
          </tfoot>
        </table>
      </div>

      {/* Save button */}
      {dirty && (
        <div className="flex gap-3 items-center">
          <button onClick={handleSave} disabled={saving || !isValid}
                  className="px-6 py-2.5 bg-violet hover:bg-violet/90 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors">
            {saving ? 'Sauvegarde...' : 'Sauvegarder la distribution'}
          </button>
          {!isValid && <span className="text-xs text-danger">Le total doit etre exactement 100%</span>}
        </div>
      )}

      {/* Simulation card */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h2 className="text-sm font-semibold text-t1 mb-3">Simulation production quotidienne</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {taxonomies.filter(t => t.is_active && t.percentage > 0).map(tax => {
            const count = Math.round(totalPerDay * tax.percentage / 100);
            const info = TYPE_INFO[tax.content_type];
            const avgWords = info ? parseInt(info.words.split('-')[0]) : 1500;
            return (
              <div key={tax.content_type} className="bg-surface2/50 rounded-lg p-3">
                <p className="text-[10px] text-t3 uppercase">{tax.label}</p>
                <p className="text-xl font-bold text-t1">{count}</p>
                <p className="text-[10px] text-t3">~{(count * avgWords / 1000).toFixed(0)}K mots/jour</p>
              </div>
            );
          })}
        </div>
        <p className="mt-3 text-xs text-t3">
          Total estime : ~{taxonomies.filter(t => t.is_active).reduce((sum, t) => {
            const count = Math.round(totalPerDay * t.percentage / 100);
            const avgWords = TYPE_INFO[t.content_type] ? parseInt(TYPE_INFO[t.content_type].words.split('-')[0]) : 1500;
            return sum + count * avgWords;
          }, 0).toLocaleString()} mots/jour
        </p>
      </div>
    </div>
  );
}
