import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface ArticleDetail {
  id: number;
  title: string;
  slug: string;
  url: string;
  category: string | null;
  content_text: string | null;
  word_count: number;
  language: string;
  meta_title: string | null;
  meta_description: string | null;
  is_guide: boolean;
  scraped_at: string | null;
  images: { url: string; alt: string }[] | null;
  source?: { id: number; name: string; slug: string };
  country?: { id: number; name: string; slug: string } | null;
}

interface ArticleLink {
  id: number;
  url: string;
  original_url: string;
  domain: string;
  anchor_text: string | null;
  context: string | null;
  link_type: string;
  is_affiliate: boolean;
  occurrences: number;
}

export default function ContentArticlePage() {
  const { id } = useParams<{ id: string }>();
  const [article, setArticle] = useState<ArticleDetail | null>(null);
  const [links, setLinks] = useState<ArticleLink[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'content' | 'links' | 'meta'>('content');

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    api.get(`/content/articles/${id}`)
      .then((res) => {
        setArticle(res.data.article);
        setLinks(res.data.links);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!article) return <div className="p-6 text-muted">Article non trouve</div>;

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted">
        <Link to="/content" className="hover:text-white">Content</Link>
        <span>/</span>
        {article.source && (
          <>
            <Link to={`/content/${article.source.slug}`} className="hover:text-white">{article.source.name}</Link>
            <span>/</span>
          </>
        )}
        {article.country && (
          <>
            <Link
              to={`/content/${article.source?.slug}/${article.country.slug}`}
              className="hover:text-white"
            >
              {article.country.name}
            </Link>
            <span>/</span>
          </>
        )}
      </div>

      {/* Title */}
      <div>
        <h1 className="font-title text-2xl font-bold text-white">{article.title}</h1>
        <div className="flex items-center gap-3 mt-2 text-sm text-muted">
          {article.category && (
            <span className="px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">{article.category}</span>
          )}
          {article.is_guide && (
            <span className="px-2 py-0.5 bg-cyan/20 text-cyan rounded text-xs">Guide</span>
          )}
          <span>{article.word_count.toLocaleString()} mots</span>
          <span>{links.length} liens externes</span>
          <a href={article.url} target="_blank" rel="noopener noreferrer" className="text-cyan hover:underline">
            Voir l'original
          </a>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {[
          { key: 'content', label: 'Contenu' },
          { key: 'links', label: `Liens (${links.length})` },
          { key: 'meta', label: 'Metadata' },
        ].map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key as 'content' | 'links' | 'meta')}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key
                ? 'border-violet text-white'
                : 'border-transparent text-muted hover:text-white'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Content tab */}
      {tab === 'content' && (
        <div className="bg-surface border border-border rounded-xl p-6">
          <div className="prose prose-invert prose-sm max-w-none">
            <pre className="whitespace-pre-wrap text-gray-300 text-sm font-sans leading-relaxed">
              {article.content_text || 'Aucun contenu disponible'}
            </pre>
          </div>
        </div>
      )}

      {/* Links tab */}
      {tab === 'links' && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">URL</th>
                <th className="px-4 py-3 font-medium">Anchor</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium text-center">Aff.</th>
              </tr>
            </thead>
            <tbody>
              {links.map((l) => (
                <tr key={l.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-2">
                    <a
                      href={l.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-cyan hover:underline"
                    >
                      {l.domain}
                    </a>
                    {l.context && (
                      <div className="text-xs text-muted mt-1 max-w-lg truncate" title={l.context}>
                        {l.context}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-2 text-gray-400">{l.anchor_text || '-'}</td>
                  <td className="px-4 py-2">
                    <span className="px-2 py-0.5 bg-gray-700 rounded text-xs text-gray-300">{l.link_type}</span>
                  </td>
                  <td className="px-4 py-2 text-center">
                    {l.is_affiliate && <span className="text-amber text-xs font-bold">AFF</span>}
                  </td>
                </tr>
              ))}
              {links.length === 0 && (
                <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">Aucun lien externe</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Meta tab */}
      {tab === 'meta' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
          <div>
            <div className="text-xs text-muted mb-1">Meta Title</div>
            <div className="text-white text-sm">{article.meta_title || '-'}</div>
          </div>
          <div>
            <div className="text-xs text-muted mb-1">Meta Description</div>
            <div className="text-gray-300 text-sm">{article.meta_description || '-'}</div>
          </div>
          <div>
            <div className="text-xs text-muted mb-1">URL</div>
            <a href={article.url} target="_blank" rel="noopener noreferrer" className="text-cyan text-sm hover:underline">
              {article.url}
            </a>
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div>
              <div className="text-xs text-muted mb-1">Langue</div>
              <div className="text-white text-sm">{article.language}</div>
            </div>
            <div>
              <div className="text-xs text-muted mb-1">Mots</div>
              <div className="text-white text-sm">{article.word_count.toLocaleString()}</div>
            </div>
            <div>
              <div className="text-xs text-muted mb-1">Scrape</div>
              <div className="text-white text-sm">
                {article.scraped_at ? new Date(article.scraped_at).toLocaleString('fr-FR') : '-'}
              </div>
            </div>
          </div>
          {article.images && article.images.length > 0 && (
            <div>
              <div className="text-xs text-muted mb-2">Images ({article.images.length})</div>
              <div className="space-y-1">
                {article.images.slice(0, 10).map((img, i) => (
                  <div key={i} className="text-xs text-gray-400 truncate">{img.url}</div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
