import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createLanding } from '../../api/contentApi';
import type { LandingPage, LandingSection } from '../../types/content';
import { toast } from '../../components/Toast';
import { FormField } from '../../components/FormField';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors w-full';

const LANG_OPTIONS = [
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'Anglais' },
  { value: 'de', label: 'Allemand' },
  { value: 'es', label: 'Espagnol' },
  { value: 'pt', label: 'Portugais' },
];

const SECTION_TYPES: { value: LandingSection['type']; label: string }[] = [
  { value: 'hero', label: 'Hero' },
  { value: 'features', label: 'Features' },
  { value: 'testimonials', label: 'Temoignages' },
  { value: 'cta', label: 'Call to Action' },
  { value: 'faq', label: 'FAQ' },
  { value: 'content', label: 'Contenu libre' },
];

// ── Component ───────────────────────────────────────────────
export default function LandingCreate() {
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);

  // Form state
  const [title, setTitle] = useState('');
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');
  const [sections, setSections] = useState<{ type: LandingSection['type']; content: string }[]>([
    { type: 'hero', content: '' },
  ]);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  const clearError = (field: string) => {
    setErrors(prev => {
      const next = { ...prev };
      delete next[field];
      return next;
    });
  };

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};
    if (!title.trim()) newErrors.title = 'Le titre est requis';
    if (sections.length === 0) newErrors.sections = 'Au moins une section est requise';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const addSection = () => {
    setSections(prev => [...prev, { type: 'content', content: '' }]);
    clearError('sections');
  };

  const removeSection = (index: number) => {
    setSections(prev => prev.filter((_, i) => i !== index));
  };

  const updateSection = (index: number, field: 'type' | 'content', value: string) => {
    setSections(prev => prev.map((s, i) =>
      i === index
        ? { ...s, [field]: field === 'type' ? value as LandingSection['type'] : value }
        : s
    ));
  };

  const moveSection = (index: number, direction: 'up' | 'down') => {
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= sections.length) return;
    const updated = [...sections];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    setSections(updated);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setSubmitting(true);
    try {
      const landingSections: LandingSection[] = sections.map(s => ({
        type: s.type,
        content: { text: s.content },
      }));

      const res = await createLanding({
        title: title.trim(),
        language,
        country: country.trim() || null,
        sections: landingSections,
      });
      const landing = res.data as unknown as LandingPage;
      toast('success', 'Landing page creee !');
      navigate(`/content/landings/${landing.id}`);
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
        <button onClick={() => navigate('/content/landings')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          Retour aux landings
        </button>
        <h2 className="font-title text-2xl font-bold text-white">Nouvelle landing page</h2>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6 max-w-3xl">
        {/* Title */}
        <FormField label="Titre" required error={errors.title}>
          <input
            type="text"
            value={title}
            onChange={e => { setTitle(e.target.value); clearError('title'); }}
            onBlur={() => { if (!title.trim()) setErrors(prev => ({ ...prev, title: 'Le titre est requis' })); }}
            placeholder="Ex: Solution d'assistance juridique pour expatries"
            className={inputClass + (errors.title ? ' border-red-500' : '')}
          />
        </FormField>

        {/* Row: Language, Country */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Langue" required>
            <select value={language} onChange={e => setLanguage(e.target.value)} className={inputClass}>
              {LANG_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </FormField>
          <FormField label="Pays (optionnel)">
            <input
              type="text"
              value={country}
              onChange={e => setCountry(e.target.value)}
              placeholder="Ex: france"
              className={inputClass}
            />
          </FormField>
        </div>

        {/* Sections builder */}
        <div>
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-medium text-gray-300">
              Sections ({sections.length})
              {errors.sections && <span className="text-red-400 ml-2 text-xs">{errors.sections}</span>}
            </h3>
            <button type="button" onClick={addSection} className="text-xs text-violet hover:text-violet-light transition-colors">
              + Ajouter une section
            </button>
          </div>

          <div className="space-y-3">
            {sections.map((section, index) => (
              <div key={index} className="bg-surface border border-border rounded-xl p-4">
                <div className="flex items-center gap-3 mb-3">
                  <span className="text-xs text-muted font-mono">#{index + 1}</span>
                  <select
                    value={section.type}
                    onChange={e => updateSection(index, 'type', e.target.value)}
                    className={inputClass + ' w-44'}
                  >
                    {SECTION_TYPES.map(st => (
                      <option key={st.value} value={st.value}>{st.label}</option>
                    ))}
                  </select>
                  <div className="flex-1" />
                  <button
                    type="button"
                    onClick={() => moveSection(index, 'up')}
                    disabled={index === 0}
                    className="text-muted hover:text-white disabled:opacity-30 transition-colors"
                    title="Monter"
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 15l7-7 7 7" /></svg>
                  </button>
                  <button
                    type="button"
                    onClick={() => moveSection(index, 'down')}
                    disabled={index === sections.length - 1}
                    className="text-muted hover:text-white disabled:opacity-30 transition-colors"
                    title="Descendre"
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" /></svg>
                  </button>
                  <button
                    type="button"
                    onClick={() => removeSection(index)}
                    className="text-danger hover:text-red-300 transition-colors"
                    title="Supprimer"
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
                <textarea
                  value={section.content}
                  onChange={e => updateSection(index, 'content', e.target.value)}
                  placeholder="Contenu de la section..."
                  rows={3}
                  className={inputClass + ' resize-y'}
                />
              </div>
            ))}
          </div>
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={submitting || !title.trim()}
          className="w-full py-3 bg-violet hover:bg-violet/90 text-white font-bold rounded-lg transition-colors disabled:opacity-50 text-sm"
        >
          {submitting ? 'Creation en cours...' : 'Creer la landing page'}
        </button>
      </form>
    </div>
  );
}
