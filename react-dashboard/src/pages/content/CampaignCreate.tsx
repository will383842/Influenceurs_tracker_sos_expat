import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createCampaign, startCampaign } from '../../api/contentApi';
import type { ContentCampaign, CampaignType, CampaignConfig } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { FormField } from '../../components/FormField';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors w-full';

const CAMPAIGN_TYPES: { value: CampaignType; label: string; description: string; icon: string }[] = [
  { value: 'country_coverage', label: 'Couverture pays', description: 'Generer des articles pour chaque theme dans un pays donne', icon: '\uD83C\uDF0D' },
  { value: 'thematic', label: 'Thematique', description: 'Serie d\'articles sur un ou plusieurs sujets', icon: '\uD83D\uDCDA' },
  { value: 'pillar_cluster', label: 'Pilier / Cluster', description: 'Article pilier + articles satellites pour le maillage interne', icon: '\uD83D\uDD17' },
  { value: 'comparative_series', label: 'Serie comparative', description: 'Comparatifs entre pays ou services', icon: '\u2696\uFE0F' },
  { value: 'custom', label: 'Personnalise', description: 'Titres libres definis manuellement', icon: '\u270F\uFE0F' },
];

const THEME_OPTIONS = [
  'visa', 'logement', 'sante', 'emploi', 'banque', 'education',
  'demenagement', 'fiscalite', 'assurance', 'permis-conduire', 'retraite', 'famille',
];

const LANGUAGE_OPTIONS = [
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'Anglais' },
  { value: 'de', label: 'Allemand' },
  { value: 'es', label: 'Espagnol' },
  { value: 'pt', label: 'Portugais' },
  { value: 'ru', label: 'Russe' },
  { value: 'zh', label: 'Chinois' },
  { value: 'ar', label: 'Arabe' },
];

// ── Component ───────────────────────────────────────────────
export default function CampaignCreate() {
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [confirmStart, setConfirmStart] = useState<{ campaignId: number } | null>(null);

  // Form state
  const [name, setName] = useState('');
  const [campaignType, setCampaignType] = useState<CampaignType>('country_coverage');
  const [articlesPerDay, setArticlesPerDay] = useState(3);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});
  const clearError = (field: string) => setErrors(prev => { const n = { ...prev }; delete n[field]; return n; });

  // Config: country_coverage
  const [country, setCountry] = useState('');
  const [selectedThemes, setSelectedThemes] = useState<string[]>([]);
  const [selectedLangs, setSelectedLangs] = useState<string[]>(['fr']);

  // Config: thematic
  const [topics, setTopics] = useState<string[]>([]);
  const [topicInput, setTopicInput] = useState('');

  // Config: custom
  const [customTitles, setCustomTitles] = useState<string[]>(['']);

  const toggleTheme = (theme: string) => {
    setSelectedThemes(prev => prev.includes(theme) ? prev.filter(t => t !== theme) : [...prev, theme]);
  };

  const toggleLang = (lang: string) => {
    setSelectedLangs(prev => prev.includes(lang) ? prev.filter(l => l !== lang) : [...prev, lang]);
  };

  const addTopic = () => {
    const t = topicInput.trim();
    if (t && !topics.includes(t)) {
      setTopics(prev => [...prev, t]);
    }
    setTopicInput('');
  };

  const removeTopic = (t: string) => {
    setTopics(prev => prev.filter(x => x !== t));
  };

  const handleTopicKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addTopic();
    }
  };

  const addCustomTitle = () => {
    setCustomTitles(prev => [...prev, '']);
  };

  const updateCustomTitle = (index: number, value: string) => {
    setCustomTitles(prev => prev.map((t, i) => i === index ? value : t));
  };

  const removeCustomTitle = (index: number) => {
    setCustomTitles(prev => prev.filter((_, i) => i !== index));
  };

  // Estimate
  const estimateItems = (): number => {
    switch (campaignType) {
      case 'country_coverage':
        return Math.max(1, selectedThemes.length * selectedLangs.length);
      case 'thematic':
        return Math.max(1, topics.length * selectedLangs.length);
      case 'custom':
        return customTitles.filter(t => t.trim()).length;
      default:
        return selectedLangs.length || 1;
    }
  };

  const estimatedItems = estimateItems();
  const estimatedCost = (estimatedItems * 25); // ~$0.25 per article in cents
  const estimatedDays = Math.ceil(estimatedItems / Math.max(1, articlesPerDay));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const newErrors: Record<string, string> = {};
    if (!name.trim()) newErrors.name = 'Le nom est requis';
    if (selectedLangs.length === 0) newErrors.languages = 'Au moins une langue est requise';

    switch (campaignType) {
      case 'country_coverage':
        if (!country.trim()) newErrors.country = 'Le pays est requis';
        if (selectedThemes.length === 0) newErrors.themes = 'Au moins un theme est requis';
        break;
      case 'thematic':
        if (topics.length === 0) newErrors.topics = 'Au moins un sujet est requis';
        break;
      case 'custom':
        if (customTitles.filter(t => t.trim()).length === 0) newErrors.titles = 'Au moins un titre est requis';
        break;
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return;

    setSubmitting(true);
    try {
      const config: CampaignConfig = {
        articles_per_day: articlesPerDay,
        languages: selectedLangs,
      };

      switch (campaignType) {
        case 'country_coverage':
          config.country = country.trim();
          config.themes = selectedThemes;
          break;
        case 'thematic':
          config.themes = topics;
          break;
        case 'custom': {
          const titles = customTitles.filter(t => t.trim());
          (config as Record<string, unknown>).custom_titles = titles;
          break;
        }
        default:
          break;
      }

      const res = await createCampaign({
        name: name.trim(),
        campaign_type: campaignType,
        config,
      });
      const campaign = res.data as unknown as ContentCampaign;

      toast('success', 'Campagne creee !');
      setConfirmStart({ campaignId: campaign.id });
    } catch (err: unknown) {
      toast('error', errMsg(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div>
        <button onClick={() => navigate('/content/campaigns')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          Retour aux campagnes
        </button>
        <h2 className="font-title text-2xl font-bold text-white">Creer une campagne</h2>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Name */}
        <FormField label="Nom de la campagne" required error={errors.name}>
          <input
            type="text"
            value={name}
            onChange={e => { setName(e.target.value); clearError('name'); }}
            onBlur={() => { if (!name.trim()) setErrors(prev => ({ ...prev, name: 'Le nom est requis' })); }}
            placeholder="Ex: Couverture expatriation Allemagne"
            className={inputClass + (errors.name ? ' border-red-500' : '')}
            required
          />
        </FormField>

        {/* Type selection */}
        <div>
          <label className="block text-xs text-muted uppercase tracking-wide mb-3">Type de campagne</label>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {CAMPAIGN_TYPES.map(ct => (
              <button
                key={ct.value}
                type="button"
                onClick={() => setCampaignType(ct.value)}
                className={`text-left p-4 rounded-xl border transition-colors ${
                  campaignType === ct.value
                    ? 'border-violet/50 bg-violet/10'
                    : 'border-border bg-surface hover:border-violet/30'
                }`}
              >
                <div className="text-lg mb-1">{ct.icon}</div>
                <p className="text-white font-medium text-sm">{ct.label}</p>
                <p className="text-xs text-muted mt-1">{ct.description}</p>
              </button>
            ))}
          </div>
        </div>

        {/* Dynamic config based on type */}
        <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <h3 className="font-title font-semibold text-white">Configuration</h3>

          {/* country_coverage */}
          {campaignType === 'country_coverage' && (
            <>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Pays</label>
                <input type="text" value={country} onChange={e => setCountry(e.target.value)} placeholder="Ex: allemagne" className={inputClass} />
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-2">Themes</label>
                <div className="flex flex-wrap gap-2">
                  {THEME_OPTIONS.map(theme => (
                    <button
                      key={theme}
                      type="button"
                      onClick={() => toggleTheme(theme)}
                      className={`px-3 py-1.5 rounded-lg text-xs border transition-colors ${
                        selectedThemes.includes(theme)
                          ? 'border-violet bg-violet/20 text-violet-light'
                          : 'border-border text-muted hover:text-white hover:border-violet/30'
                      }`}
                    >
                      {theme}
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-2">Langues</label>
                <div className="flex flex-wrap gap-2">
                  {LANGUAGE_OPTIONS.map(lang => (
                    <button
                      key={lang.value}
                      type="button"
                      onClick={() => toggleLang(lang.value)}
                      className={`px-3 py-1.5 rounded-lg text-xs border transition-colors ${
                        selectedLangs.includes(lang.value)
                          ? 'border-violet bg-violet/20 text-violet-light'
                          : 'border-border text-muted hover:text-white hover:border-violet/30'
                      }`}
                    >
                      {lang.label}
                    </button>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* thematic */}
          {campaignType === 'thematic' && (
            <>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Sujets</label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={topicInput}
                    onChange={e => setTopicInput(e.target.value)}
                    onKeyDown={handleTopicKeyDown}
                    placeholder="Entrez un sujet et appuyez Entree"
                    className={inputClass}
                  />
                  <button type="button" onClick={addTopic} className="px-3 py-2 bg-surface2 text-muted hover:text-white border border-border rounded-lg text-sm transition-colors shrink-0">+</button>
                </div>
                {topics.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {topics.map(t => (
                      <span key={t} className="inline-flex items-center gap-1 px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">
                        {t}
                        <button type="button" onClick={() => removeTopic(t)} className="hover:text-white transition-colors">&times;</button>
                      </span>
                    ))}
                  </div>
                )}
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-2">Langues</label>
                <div className="flex flex-wrap gap-2">
                  {LANGUAGE_OPTIONS.map(lang => (
                    <button
                      key={lang.value}
                      type="button"
                      onClick={() => toggleLang(lang.value)}
                      className={`px-3 py-1.5 rounded-lg text-xs border transition-colors ${
                        selectedLangs.includes(lang.value)
                          ? 'border-violet bg-violet/20 text-violet-light'
                          : 'border-border text-muted hover:text-white hover:border-violet/30'
                      }`}
                    >
                      {lang.label}
                    </button>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* pillar_cluster / comparative_series */}
          {(campaignType === 'pillar_cluster' || campaignType === 'comparative_series') && (
            <>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Sujets</label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={topicInput}
                    onChange={e => setTopicInput(e.target.value)}
                    onKeyDown={handleTopicKeyDown}
                    placeholder="Entrez un sujet et appuyez Entree"
                    className={inputClass}
                  />
                  <button type="button" onClick={addTopic} className="px-3 py-2 bg-surface2 text-muted hover:text-white border border-border rounded-lg text-sm transition-colors shrink-0">+</button>
                </div>
                {topics.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {topics.map(t => (
                      <span key={t} className="inline-flex items-center gap-1 px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">
                        {t}
                        <button type="button" onClick={() => removeTopic(t)} className="hover:text-white transition-colors">&times;</button>
                      </span>
                    ))}
                  </div>
                )}
              </div>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-2">Langues</label>
                <div className="flex flex-wrap gap-2">
                  {LANGUAGE_OPTIONS.map(lang => (
                    <button
                      key={lang.value}
                      type="button"
                      onClick={() => toggleLang(lang.value)}
                      className={`px-3 py-1.5 rounded-lg text-xs border transition-colors ${
                        selectedLangs.includes(lang.value)
                          ? 'border-violet bg-violet/20 text-violet-light'
                          : 'border-border text-muted hover:text-white hover:border-violet/30'
                      }`}
                    >
                      {lang.label}
                    </button>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* custom */}
          {campaignType === 'custom' && (
            <div>
              <label className="block text-xs text-muted uppercase tracking-wide mb-2">Titres d'articles</label>
              <div className="space-y-2">
                {customTitles.map((title, index) => (
                  <div key={index} className="flex gap-2">
                    <input
                      type="text"
                      value={title}
                      onChange={e => updateCustomTitle(index, e.target.value)}
                      placeholder={`Titre #${index + 1}`}
                      className={inputClass}
                    />
                    {customTitles.length > 1 && (
                      <button type="button" onClick={() => removeCustomTitle(index)} className="px-2 text-danger hover:text-red-300 transition-colors shrink-0">&times;</button>
                    )}
                  </div>
                ))}
              </div>
              <button type="button" onClick={addCustomTitle} className="mt-2 text-xs text-violet hover:text-violet-light transition-colors">
                + Ajouter un titre
              </button>
            </div>
          )}

          {/* Articles per day */}
          <div>
            <label className="block text-xs text-muted uppercase tracking-wide mb-1">Articles par jour: {articlesPerDay}</label>
            <input
              type="range"
              min={1}
              max={10}
              value={articlesPerDay}
              onChange={e => setArticlesPerDay(Number(e.target.value))}
              className="w-full accent-violet"
            />
            <div className="flex justify-between text-[10px] text-muted"><span>1</span><span>10</span></div>
          </div>
        </div>

        {/* Preview */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-3">Estimation</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <p className="text-xs text-muted">Articles estimes</p>
              <p className="text-xl font-bold text-white">{estimatedItems}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Cout estime</p>
              <p className="text-xl font-bold text-white">~${(estimatedCost / 100).toFixed(2)}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Duree estimee</p>
              <p className="text-xl font-bold text-white">{estimatedDays} jour(s)</p>
            </div>
          </div>
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={submitting || !name.trim()}
          className="w-full py-3 bg-violet hover:bg-violet/90 text-white font-bold rounded-lg transition-colors disabled:opacity-50 text-sm"
        >
          {submitting ? 'Creation en cours...' : 'Creer la campagne'}
        </button>
      </form>

      <ConfirmModal
        open={!!confirmStart}
        title="Demarrer la campagne"
        message="La campagne a ete creee. Voulez-vous la demarrer immediatement ?"
        confirmLabel="Demarrer"
        cancelLabel="Plus tard"
        onConfirm={async () => {
          if (confirmStart) {
            try {
              await startCampaign(confirmStart.campaignId);
              toast('success', 'Campagne demarree.');
            } catch (err) {
              toast('error', errMsg(err));
            }
            navigate(`/content/campaigns/${confirmStart.campaignId}`);
          }
          setConfirmStart(null);
        }}
        onCancel={() => {
          if (confirmStart) navigate(`/content/campaigns/${confirmStart.campaignId}`);
          setConfirmStart(null);
        }}
      />
    </div>
  );
}
