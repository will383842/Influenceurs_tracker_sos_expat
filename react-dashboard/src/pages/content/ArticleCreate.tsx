import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { generateArticle, fetchPresets, fetchCostOverview } from '../../api/contentApi';
import type { GenerationPreset, CostOverview, GenerateArticleParams, GeneratedArticle, ContentType } from '../../types/content';
import { toast } from '../../components/Toast';
import { FormField } from '../../components/FormField';
import { inputClass as sharedInputClass, cents as sharedCents, budgetColor as sharedBudgetColor, errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const LANG_OPTIONS = [
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'Anglais' },
  { value: 'de', label: 'Allemand' },
  { value: 'es', label: 'Espagnol' },
  { value: 'pt', label: 'Portugais' },
  { value: 'ru', label: 'Russe' },
  { value: 'zh', label: 'Chinois' },
  { value: 'ar', label: 'Arabe' },
  { value: 'hi', label: 'Hindi' },
];

const TYPE_OPTIONS: { value: ContentType; label: string }[] = [
  { value: 'article', label: 'Article' },
  { value: 'guide', label: 'Guide' },
  { value: 'news', label: 'Actualite' },
  { value: 'tutorial', label: 'Tutoriel' },
];

const TONE_OPTIONS = [
  { value: 'professional', label: 'Professionnel' },
  { value: 'casual', label: 'Decontracte' },
  { value: 'expert', label: 'Expert' },
  { value: 'friendly', label: 'Amical' },
];

const LENGTH_OPTIONS = [
  { value: 'short', label: 'Court (~800 mots)' },
  { value: 'medium', label: 'Moyen (~1500 mots)' },
  { value: 'long', label: 'Long (~2500 mots)' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors w-full';

function cents(n: number): string {
  return (n / 100).toFixed(2);
}

function budgetColor(pct: number): string {
  if (pct >= 80) return 'bg-danger';
  if (pct >= 50) return 'bg-amber';
  return 'bg-success';
}

type ToneValue = 'professional' | 'casual' | 'expert' | 'friendly';
type LengthValue = 'short' | 'medium' | 'long';

// ── Component ───────────────────────────────────────────────
export default function ArticleCreate() {
  const navigate = useNavigate();
  const [presets, setPresets] = useState<GenerationPreset[]>([]);
  const [costs, setCosts] = useState<CostOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Form state
  const [topic, setTopic] = useState('');
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');
  const [contentType, setContentType] = useState<ContentType>('article');
  const [keywords, setKeywords] = useState<string[]>([]);
  const [keywordInput, setKeywordInput] = useState('');
  const [instructions, setInstructions] = useState('');
  const [presetId, setPresetId] = useState<number | ''>('');
  const [tone, setTone] = useState<ToneValue>('professional');
  const [articleLength, setArticleLength] = useState<LengthValue>('medium');

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});
  const clearError = (field: string) => setErrors(prev => { const n = { ...prev }; delete n[field]; return n; });

  // Advanced options
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [generateFaq, setGenerateFaq] = useState(true);
  const [faqCount, setFaqCount] = useState(8);
  const [researchSources, setResearchSources] = useState(true);
  const [unsplashImages, setUnsplashImages] = useState(true);
  const [dalleImages, setDalleImages] = useState(false);
  const [internalLinks, setInternalLinks] = useState(true);
  const [affiliateLinks, setAffiliateLinks] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [presetsRes, costsRes] = await Promise.all([
        fetchPresets(),
        fetchCostOverview(),
      ]);
      setPresets((presetsRes.data as unknown as GenerationPreset[]) ?? []);
      setCosts(costsRes.data as unknown as CostOverview);
    } catch {
      // non-blocking
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const addKeyword = () => {
    const kw = keywordInput.trim();
    if (kw && !keywords.includes(kw)) {
      setKeywords(prev => [...prev, kw]);
    }
    setKeywordInput('');
  };

  const removeKeyword = (kw: string) => {
    setKeywords(prev => prev.filter(k => k !== kw));
  };

  const handleKeywordKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addKeyword();
    }
  };

  const applyPreset = (preset: GenerationPreset) => {
    setPresetId(preset.id);
    const cfg = preset.config as Record<string, unknown>;
    if (cfg.language && typeof cfg.language === 'string') setLanguage(cfg.language);
    if (cfg.content_type && typeof cfg.content_type === 'string') setContentType(cfg.content_type as ContentType);
    if (cfg.tone && typeof cfg.tone === 'string') setTone(cfg.tone as ToneValue);
    if (cfg.length && typeof cfg.length === 'string') setArticleLength(cfg.length as LengthValue);
    if (typeof cfg.generate_faq === 'boolean') setGenerateFaq(cfg.generate_faq);
    if (typeof cfg.research_sources === 'boolean') setResearchSources(cfg.research_sources);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const newErrors: Record<string, string> = {};
    if (!topic.trim()) newErrors.topic = 'Le sujet est requis';
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return;
    setSubmitting(true);
    try {
      const params: GenerateArticleParams = {
        topic: topic.trim(),
        language,
        content_type: contentType,
        tone,
        length: articleLength,
        generate_faq: generateFaq,
        research_sources: researchSources,
        auto_internal_links: internalLinks,
        auto_affiliate_links: affiliateLinks,
      };
      if (country.trim()) params.country = country.trim();
      if (keywords.length > 0) params.keywords = keywords;
      if (instructions.trim()) params.instructions = instructions.trim();
      if (presetId) params.preset_id = presetId as number;
      if (generateFaq) params.faq_count = faqCount;
      if (unsplashImages) params.image_source = 'unsplash';
      else if (dalleImages) params.image_source = 'dalle';
      else params.image_source = 'none';

      const res = await generateArticle(params);
      const article = res.data as unknown as GeneratedArticle;
      toast('success', 'Generation lancee !');
      navigate(`/content/articles/${article.id}`);
    } catch (err: unknown) {
      toast('error', errMsg(err));
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="grid grid-cols-1 lg:grid-cols-10 gap-6">
          <div className="lg:col-span-7 space-y-4">
            {[1, 2, 3, 4, 5].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-14" />)}
          </div>
          <div className="lg:col-span-3 animate-pulse bg-surface2 rounded-xl h-64" />
        </div>
      </div>
    );
  }

  const dailyPct = costs && costs.daily_budget_cents > 0
    ? Math.min(100, Math.round((costs.today_cents / costs.daily_budget_cents) * 100))
    : 0;
  const monthlyPct = costs && costs.monthly_budget_cents > 0
    ? Math.min(100, Math.round((costs.this_month_cents / costs.monthly_budget_cents) * 100))
    : 0;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div>
        <button onClick={() => navigate('/content/articles')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          Retour aux articles
        </button>
        <h2 className="font-title text-2xl font-bold text-white">Generer un article</h2>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 lg:grid-cols-10 gap-6">
          {/* Left column -- Form (70%) */}
          <div className="lg:col-span-7 space-y-5">
            {/* Topic */}
            <FormField label="Sujet / Titre" required error={errors.topic}>
              <input
                type="text"
                value={topic}
                onChange={e => { setTopic(e.target.value); clearError('topic'); }}
                onBlur={() => { if (!topic.trim()) setErrors(prev => ({ ...prev, topic: 'Le sujet est requis' })); }}
                placeholder="Ex: Comment obtenir un visa de travail en Allemagne"
                className={inputClass + ' text-base' + (errors.topic ? ' border-red-500' : '')}
                required
              />
            </FormField>

            {/* Row: Language, Country, Type */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Langue</label>
                <select value={language} onChange={e => setLanguage(e.target.value)} className={inputClass}>
                  {LANG_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Pays</label>
                <input type="text" value={country} onChange={e => setCountry(e.target.value)} placeholder="Ex: allemagne" className={inputClass} />
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Type de contenu</label>
                <select value={contentType} onChange={e => setContentType(e.target.value as ContentType)} className={inputClass}>
                  {TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
            </div>

            {/* Keywords */}
            <div>
              <label className="block text-xs text-muted uppercase tracking-wide mb-1">Mots-cles</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={keywordInput}
                  onChange={e => setKeywordInput(e.target.value)}
                  onKeyDown={handleKeywordKeyDown}
                  placeholder="Entrez un mot-cle et appuyez Entree"
                  className={inputClass}
                />
                <button type="button" onClick={addKeyword} className="px-3 py-2 bg-surface2 text-muted hover:text-white border border-border rounded-lg text-sm transition-colors shrink-0">
                  +
                </button>
              </div>
              {keywords.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-2">
                  {keywords.map(kw => (
                    <span key={kw} className="inline-flex items-center gap-1 px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">
                      {kw}
                      <button type="button" onClick={() => removeKeyword(kw)} className="hover:text-white transition-colors">&times;</button>
                    </span>
                  ))}
                </div>
              )}
            </div>

            {/* Instructions */}
            <div>
              <label className="block text-xs text-muted uppercase tracking-wide mb-1">Instructions (optionnel)</label>
              <textarea
                value={instructions}
                onChange={e => setInstructions(e.target.value)}
                placeholder="Instructions specifiques pour l'IA..."
                rows={3}
                className={inputClass + ' resize-y'}
              />
            </div>

            {/* Row: Preset, Tone, Length */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Preset</label>
                <select value={presetId} onChange={e => setPresetId(e.target.value ? Number(e.target.value) : '')} className={inputClass}>
                  <option value="">Aucun preset</option>
                  {presets.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Ton</label>
                <select value={tone} onChange={e => setTone(e.target.value as ToneValue)} className={inputClass}>
                  {TONE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Longueur</label>
                <select value={articleLength} onChange={e => setArticleLength(e.target.value as LengthValue)} className={inputClass}>
                  {LENGTH_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
            </div>

            {/* Advanced options */}
            <div className="bg-surface border border-border rounded-xl">
              <button
                type="button"
                onClick={() => setShowAdvanced(!showAdvanced)}
                className="w-full flex items-center justify-between px-5 py-3 text-sm text-muted hover:text-white transition-colors"
              >
                <span>Options avancees</span>
                <svg className={`w-4 h-4 transition-transform ${showAdvanced ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              {showAdvanced && (
                <div className="px-5 pb-5 space-y-3 border-t border-border pt-3">
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={generateFaq} onChange={e => setGenerateFaq(e.target.checked)} className="accent-violet" />
                      Generer FAQ
                    </label>
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={researchSources} onChange={e => setResearchSources(e.target.checked)} className="accent-violet" />
                      Rechercher des sources
                    </label>
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={unsplashImages} onChange={e => { setUnsplashImages(e.target.checked); if (e.target.checked) setDalleImages(false); }} className="accent-violet" />
                      Images Unsplash
                    </label>
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={dalleImages} onChange={e => { setDalleImages(e.target.checked); if (e.target.checked) setUnsplashImages(false); }} className="accent-violet" />
                      Images DALL-E
                    </label>
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={internalLinks} onChange={e => setInternalLinks(e.target.checked)} className="accent-violet" />
                      Liens internes
                    </label>
                    <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                      <input type="checkbox" checked={affiliateLinks} onChange={e => setAffiliateLinks(e.target.checked)} className="accent-violet" />
                      Liens affilies
                    </label>
                  </div>
                  {generateFaq && (
                    <div>
                      <label className="block text-xs text-muted mb-1">Nombre de FAQ: {faqCount}</label>
                      <input type="range" min={4} max={20} value={faqCount} onChange={e => setFaqCount(Number(e.target.value))} className="w-full accent-violet" />
                      <div className="flex justify-between text-[10px] text-muted"><span>4</span><span>20</span></div>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={submitting || !topic.trim()}
              className="w-full py-3 bg-violet hover:bg-violet/90 text-white font-bold rounded-lg transition-colors disabled:opacity-50 text-sm"
            >
              {submitting ? 'Generation en cours...' : 'Generer l\'article'}
            </button>
            <p className="text-xs text-muted text-center">Cout estime: ~$0.15 - $0.50 selon la longueur et les options</p>
          </div>

          {/* Right column -- Sidebar (30%) */}
          <div className="lg:col-span-3 space-y-4">
            {/* Quick presets */}
            {presets.length > 0 && (
              <div className="bg-surface border border-border rounded-xl p-5">
                <h4 className="font-title font-semibold text-white mb-3">Presets rapides</h4>
                <div className="space-y-2">
                  {presets.slice(0, 5).map(preset => (
                    <button
                      key={preset.id}
                      type="button"
                      onClick={() => applyPreset(preset)}
                      className={`w-full text-left p-3 rounded-lg border transition-colors text-sm ${
                        presetId === preset.id ? 'border-violet/50 bg-violet/10' : 'border-border hover:border-violet/30 bg-surface2/50'
                      }`}
                    >
                      <p className="text-white font-medium">{preset.name}</p>
                      {preset.description && <p className="text-xs text-muted mt-0.5 line-clamp-2">{preset.description}</p>}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Budget gauge */}
            {costs && (
              <div className="bg-surface border border-border rounded-xl p-5">
                <h4 className="font-title font-semibold text-white mb-3">Budget</h4>
                <div className="space-y-4">
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-xs text-muted">Quotidien</span>
                      <span className="text-xs text-muted">${cents(costs.today_cents)} / ${cents(costs.daily_budget_cents)}</span>
                    </div>
                    <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
                      <div className={`h-full rounded-full ${budgetColor(dailyPct)}`} style={{ width: `${dailyPct}%` }} />
                    </div>
                  </div>
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-xs text-muted">Mensuel</span>
                      <span className="text-xs text-muted">${cents(costs.this_month_cents)} / ${cents(costs.monthly_budget_cents)}</span>
                    </div>
                    <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
                      <div className={`h-full rounded-full ${budgetColor(monthlyPct)}`} style={{ width: `${monthlyPct}%` }} />
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </form>
    </div>
  );
}
