import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface Country {
  id: number;
  name: string;
  slug: string;
  continent: string | null;
  articles_count: number;
  scraped_at: string | null;
}

interface Article {
  id: number;
  title: string;
  slug: string;
  url: string;
  category: string | null;
  word_count: number;
  is_guide: boolean;
  scraped_at: string | null;
  links_count: number;
}

export default function ContentCountryPage() {
  const { sourceSlug, countrySlug } = useParams<{ sourceSlug: string; countrySlug: string }>();
  const [country, setCountry] = useState<Country | null>(null);
  const [articles, setArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    if (!sourceSlug || !countrySlug) return;
    setLoading(true);
    setError(null);
    api.get(`/content/sources/${sourceSlug}/countries/${countrySlug}`, {
      params: { page, per_page: 50 },
    })
      .then((res) => {
        setCountry(res.data.country);
        // Backend returns paginated articles: { data: [...], last_page, total, ... }
        const paginated = res.data.articles;
        setArticles(paginated.data);
        setLastPage(paginated.last_page);
        setTotal(paginated.total);
      })
      .catch(() => setError('Erreur lors du chargement'))
      .finally(() => setLoading(false));
  }, [sourceSlug, countrySlug, page]);

  const categoryBadge = (cat: string | null) => {
    if (!cat) return null;
    const colors: Record<string, string> = {
      visa: 'bg-blue-900/30 text-blue-400',
      logement: 'bg-violet/20 text-violet-light',
      sante: 'bg-green-900/30 text-green-400',
      emploi: 'bg-amber/20 text-amber',
      transport: 'bg-cyan/20 text-cyan',
      education: 'bg-pink-900/30 text-pink-400',
      banque: 'bg-yellow-900/30 text-yellow-400',
      culture: 'bg-purple-900/30 text-purple-400',
      demarches: 'bg-orange-900/30 text-orange-400',
      telecom: 'bg-teal-900/30 text-teal-400',
    };
    return (
      <span className={`px-2 py-0.5 rounded text-xs ${colors[cat] || 'bg-gray-700 text-gray-400'}`}>
        {cat}
      </span>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status" aria-label="Chargement">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Breadcrumb + header */}
      <div>
        <div className="flex items-center gap-2 text-sm text-muted">
          <Link to="/content" className="hover:text-white">Content</Link>
          <span>/</span>
          <Link to={`/content/${sourceSlug}`} className="hover:text-white capitalize">{sourceSlug}</Link>
          <span>/</span>
        </div>
        <h1 className="font-title text-2xl font-bold text-white mt-1">{country?.name || countrySlug}</h1>
        {country && (
          <p className="text-muted text-sm">
            {country.continent} &middot; {total} articles
            {country.scraped_at && <> &middot; Scrape le {new Date(country.scraped_at).toLocaleDateString('fr-FR')}</>}
          </p>
        )}
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm">{error}</div>
      )}

      {/* Articles table */}
      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Titre</th>
              <th className="px-4 py-3 font-medium">Categorie</th>
              <th className="px-4 py-3 font-medium text-right">Mots</th>
              <th className="px-4 py-3 font-medium text-right">Liens ext.</th>
              <th className="px-4 py-3 font-medium text-center">Guide</th>
              <th className="px-4 py-3 font-medium">Scrape</th>
            </tr>
          </thead>
          <tbody>
            {articles.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-muted">
                  Aucun article scrape pour ce pays
                </td>
              </tr>
            ) : (
              articles.map((a) => (
                <tr key={a.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                  <td className="px-4 py-2">
                    <Link
                      to={`/content/articles/${a.id}`}
                      className="text-violet-light hover:underline font-medium"
                    >
                      {a.title || a.slug}
                    </Link>
                    <div className="text-xs text-muted truncate max-w-md">{a.url}</div>
                  </td>
                  <td className="px-4 py-2">{categoryBadge(a.category)}</td>
                  <td className="px-4 py-2 text-right text-white">{a.word_count.toLocaleString()}</td>
                  <td className="px-4 py-2 text-right text-white">{a.links_count}</td>
                  <td className="px-4 py-2 text-center">
                    {a.is_guide && <span className="text-cyan text-xs font-bold">GUIDE</span>}
                  </td>
                  <td className="px-4 py-2 text-muted text-xs">
                    {a.scraped_at ? new Date(a.scraped_at).toLocaleDateString('fr-FR') : '-'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button
            onClick={() => setPage(Math.max(1, page - 1))}
            disabled={page === 1}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30"
            aria-label="Page precedente"
          >
            Prec.
          </button>
          <span className="text-sm text-muted">Page {page} / {lastPage}</span>
          <button
            onClick={() => setPage(Math.min(lastPage, page + 1))}
            disabled={page === lastPage}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30"
            aria-label="Page suivante"
          >
            Suiv.
          </button>
        </div>
      )}
    </div>
  );
}
