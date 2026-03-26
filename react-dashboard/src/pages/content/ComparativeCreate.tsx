import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { generateComparative } from '../../api/contentApi';
import { FormField } from '../../components/FormField';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

export default function ComparativeCreate() {
  const navigate = useNavigate();
  const [title, setTitle] = useState('');
  const [entities, setEntities] = useState<string[]>(['', '']);
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');
  const [keywordsInput, setKeywordsInput] = useState('');
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const clearFieldError = (field: string) => setFieldErrors(prev => { const n = { ...prev }; delete n[field]; return n; });

  const addEntity = () => {
    if (entities.length >= 5) return;
    setEntities([...entities, '']);
  };

  const removeEntity = (index: number) => {
    if (entities.length <= 2) return;
    setEntities(entities.filter((_, i) => i !== index));
  };

  const updateEntity = (index: number, value: string) => {
    const updated = [...entities];
    updated[index] = value;
    setEntities(updated);
  };

  const handleGenerate = async () => {
    const validEntities = entities.filter(e => e.trim());
    const newErrors: Record<string, string> = {};
    if (!title.trim()) newErrors.title = 'Le titre est requis';
    if (validEntities.length < 2) newErrors.entities = 'Au moins 2 entites sont requises';
    if (!language) newErrors.language = 'La langue est requise';
    setFieldErrors(newErrors);
    if (Object.keys(newErrors).length > 0) {
      setError('Veuillez corriger les erreurs ci-dessus.');
      return;
    }
    setGenerating(true);
    setError(null);
    try {
      const keywords = keywordsInput.split(',').map(k => k.trim()).filter(Boolean);
      const res = await generateComparative({
        title,
        entities: validEntities,
        language,
        country: country || undefined,
        keywords: keywords.length > 0 ? keywords : undefined,
      });
      const created = res.data;
      navigate(`/content/comparatives/${created.id}`);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur lors de la generation';
      setError(msg);
    } finally {
      setGenerating(false);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6 max-w-2xl">
      {/* Header */}
      <div>
        <button onClick={() => navigate('/content/comparatives')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
          Retour aux comparatifs
        </button>
        <h2 className="font-title text-2xl font-bold text-white">Nouveau comparatif</h2>
        <p className="text-sm text-muted mt-1">Generez un comparatif entre 2 a 5 entites.</p>
      </div>

      {error && (
        <div className="bg-danger/10 border border-danger/30 rounded-xl p-3">
          <p className="text-danger text-sm">{error}</p>
        </div>
      )}

      {/* Form */}
      <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
        {/* Title */}
        <FormField label="Titre" required error={fieldErrors.title}>
          <input
            type="text"
            value={title}
            onChange={e => { setTitle(e.target.value); clearFieldError('title'); }}
            onBlur={() => { if (!title.trim()) setFieldErrors(prev => ({ ...prev, title: 'Le titre est requis' })); }}
            placeholder="Ex: Comparatif des banques pour expatries en France"
            className={inputClass + ' w-full' + (fieldErrors.title ? ' border-red-500' : '')}
          />
        </FormField>

        {/* Entities */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <label className="text-xs text-muted uppercase tracking-wide">
              Entites a comparer ({entities.length}/5)
              {fieldErrors.entities && <span className="text-red-400 ml-2 text-xs normal-case">{fieldErrors.entities}</span>}
            </label>
            {entities.length < 5 && (
              <button onClick={addEntity} className="text-xs text-violet hover:text-violet-light transition-colors">
                + Ajouter
              </button>
            )}
          </div>
          <div className="space-y-2">
            {entities.map((entity, i) => (
              <div key={i} className="flex items-center gap-2">
                <input
                  type="text"
                  value={entity}
                  onChange={e => updateEntity(i, e.target.value)}
                  placeholder={`Entite ${i + 1}`}
                  className={inputClass + ' flex-1'}
                />
                {entities.length > 2 && (
                  <button onClick={() => removeEntity(i)} className="text-xs text-danger hover:text-red-300 transition-colors px-2 py-2">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Language & Country */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="text-xs text-muted uppercase tracking-wide block mb-1">Langue</label>
            <select value={language} onChange={e => setLanguage(e.target.value)} className={inputClass + ' w-full'}>
              <option value="fr">Francais</option>
              <option value="en">English</option>
              <option value="es">Espanol</option>
              <option value="de">Deutsch</option>
              <option value="pt">Portugues</option>
            </select>
          </div>
          <div>
            <label className="text-xs text-muted uppercase tracking-wide block mb-1">Pays (optionnel)</label>
            <input type="text" value={country} onChange={e => setCountry(e.target.value)} placeholder="Ex: france" className={inputClass + ' w-full'} />
          </div>
        </div>

        {/* Keywords */}
        <div>
          <label className="text-xs text-muted uppercase tracking-wide block mb-1">Mots-cles (separes par des virgules)</label>
          <input
            type="text"
            value={keywordsInput}
            onChange={e => setKeywordsInput(e.target.value)}
            placeholder="Ex: banque expatrie, compte bancaire, frais bancaires"
            className={inputClass + ' w-full'}
          />
          {keywordsInput && (
            <div className="flex flex-wrap gap-1 mt-2">
              {keywordsInput.split(',').map(k => k.trim()).filter(Boolean).map(k => (
                <span key={k} className="px-2 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">{k}</span>
              ))}
            </div>
          )}
        </div>

        {/* Generate button */}
        <div className="flex justify-end gap-3 pt-2">
          <button onClick={() => navigate('/content/comparatives')} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">
            Annuler
          </button>
          <button
            onClick={handleGenerate}
            disabled={generating || !title || entities.filter(e => e.trim()).length < 2}
            className="px-6 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
          >
            {generating ? 'Generation en cours...' : 'Generer le comparatif'}
          </button>
        </div>
      </div>
    </div>
  );
}
