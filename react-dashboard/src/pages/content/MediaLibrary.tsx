import React, { useState } from 'react';
import {
  searchUnsplash,
  generateDalleImage,
} from '../../api/contentApi';
import type { UnsplashImage } from '../../types/content';
import { toast } from '../../components/Toast';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

const DALLE_SIZES = [
  { value: '1024x1024', label: '1024x1024 (carre)' },
  { value: '1792x1024', label: '1792x1024 (paysage)' },
  { value: '1024x1792', label: '1024x1792 (portrait)' },
];

type Tab = 'unsplash' | 'dalle';

export default function MediaLibrary() {
  const [tab, setTab] = useState<Tab>('unsplash');

  // Unsplash state
  const [unsplashQuery, setUnsplashQuery] = useState('');
  const [unsplashResults, setUnsplashResults] = useState<UnsplashImage[]>([]);
  const [unsplashLoading, setUnsplashLoading] = useState(false);
  const [unsplashError, setUnsplashError] = useState<string | null>(null);
  const [copiedUrl, setCopiedUrl] = useState<string | null>(null);

  // DALL-E state
  const [dallePrompt, setDallePrompt] = useState('');
  const [dalleSize, setDalleSize] = useState('1024x1024');
  const [dalleResult, setDalleResult] = useState<string | null>(null);
  const [dalleLoading, setDalleLoading] = useState(false);
  const [dalleError, setDalleError] = useState<string | null>(null);

  const handleUnsplashSearch = async () => {
    if (!unsplashQuery.trim()) return;
    setUnsplashLoading(true);
    setUnsplashError(null);
    try {
      const res = await searchUnsplash(unsplashQuery, 12);
      setUnsplashResults((res.data as unknown as UnsplashImage[]) ?? []);
    } catch (err: unknown) {
      setUnsplashError(err instanceof Error ? err.message : 'Erreur de recherche');
    } finally {
      setUnsplashLoading(false);
    }
  };

  const handleCopyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedUrl(url);
      setTimeout(() => setCopiedUrl(null), 2000);
    } catch { toast('info', 'Copie echouee, utilisez Ctrl+C.'); }
  };

  const handleDalleGenerate = async () => {
    if (!dallePrompt.trim()) return;
    setDalleLoading(true);
    setDalleError(null);
    setDalleResult(null);
    try {
      const res = await generateDalleImage(dallePrompt, dalleSize);
      setDalleResult((res.data as unknown as { url: string }).url);
    } catch (err: unknown) {
      setDalleError(err instanceof Error ? err.message : 'Erreur de generation');
    } finally {
      setDalleLoading(false);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Bibliotheque medias</h2>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        <button
          onClick={() => setTab('unsplash')}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
            tab === 'unsplash' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          Unsplash
        </button>
        <button
          onClick={() => setTab('dalle')}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
            tab === 'dalle' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          DALL-E
        </button>
      </div>

      {/* Unsplash tab */}
      {tab === 'unsplash' && (
        <div className="space-y-4">
          <div className="flex gap-3">
            <input
              type="text"
              placeholder="Rechercher des images..."
              value={unsplashQuery}
              onChange={e => setUnsplashQuery(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleUnsplashSearch()}
              className={inputClass + ' flex-1'}
            />
            <button
              onClick={handleUnsplashSearch}
              disabled={unsplashLoading || !unsplashQuery.trim()}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              {unsplashLoading ? 'Recherche...' : 'Rechercher'}
            </button>
          </div>

          {unsplashError && <p className="text-danger text-sm">{unsplashError}</p>}

          {unsplashLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {[1, 2, 3, 4, 5, 6].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-48" />)}
            </div>
          ) : unsplashResults.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {unsplashResults.map((img, idx) => (
                <div key={idx} className="bg-surface border border-border rounded-xl overflow-hidden group">
                  <div className="relative aspect-video">
                    <img
                      src={img.thumb_url}
                      alt={img.alt_text}
                      className="w-full h-full object-cover"
                      loading="lazy"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                      <button
                        onClick={() => handleCopyUrl(img.url)}
                        className="px-3 py-1.5 bg-violet text-white text-sm rounded-lg"
                      >
                        {copiedUrl === img.url ? 'Copie !' : 'Copy URL'}
                      </button>
                    </div>
                  </div>
                  <div className="p-3 space-y-1">
                    <p className="text-xs text-muted truncate">{img.attribution}</p>
                    <p className="text-xs text-muted">{img.width} x {img.height}</p>
                  </div>
                </div>
              ))}
            </div>
          ) : unsplashQuery && !unsplashLoading ? (
            <p className="text-center py-8 text-muted text-sm">Aucun resultat pour "{unsplashQuery}"</p>
          ) : (
            <p className="text-center py-8 text-muted text-sm">Entrez un terme de recherche pour trouver des images</p>
          )}
        </div>
      )}

      {/* DALL-E tab */}
      {tab === 'dalle' && (
        <div className="space-y-4">
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <div>
              <label className="text-xs text-muted uppercase tracking-wide mb-1 block">Prompt</label>
              <textarea
                rows={3}
                placeholder="Decrivez l'image a generer..."
                value={dallePrompt}
                onChange={e => setDallePrompt(e.target.value)}
                className={inputClass + ' w-full resize-none'}
              />
            </div>

            <div className="flex items-end gap-4">
              <div>
                <label className="text-xs text-muted uppercase tracking-wide mb-1 block">Taille</label>
                <select
                  value={dalleSize}
                  onChange={e => setDalleSize(e.target.value)}
                  className={inputClass}
                >
                  {DALLE_SIZES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </div>

              <div className="text-xs text-muted">
                Cout estime: ~$0.08
              </div>

              <button
                onClick={handleDalleGenerate}
                disabled={dalleLoading || !dallePrompt.trim()}
                className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50 ml-auto"
              >
                {dalleLoading ? 'Generation...' : 'Generer'}
              </button>
            </div>
          </div>

          {dalleError && <p className="text-danger text-sm">{dalleError}</p>}

          {dalleLoading && (
            <div className="bg-surface border border-border rounded-xl p-8 flex items-center justify-center">
              <div className="animate-pulse text-muted text-sm">Generation de l'image en cours...</div>
            </div>
          )}

          {dalleResult && (
            <div className="bg-surface border border-border rounded-xl overflow-hidden">
              <img src={dalleResult} alt="DALL-E generated" className="w-full max-w-2xl mx-auto" />
              <div className="p-4 flex items-center justify-between">
                <p className="text-xs text-muted truncate flex-1">{dalleResult}</p>
                <button
                  onClick={() => handleCopyUrl(dalleResult!)}
                  className="px-3 py-1 text-xs bg-violet/20 text-violet hover:bg-violet/30 rounded-lg transition-colors ml-3"
                >
                  {copiedUrl === dalleResult ? 'Copie !' : 'Copy URL'}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
