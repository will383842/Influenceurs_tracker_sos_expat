import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface Country { id: number; name: string; slug: string; continent: string; guide_url: string; scraped_at: string | null; }
interface Article { id: number; title: string; url: string; category: string | null; section: string; word_count: number; is_guide: boolean; meta_description: string | null; }
interface ExtLink { id: number; url: string; domain: string; anchor_text: string | null; link_type: string; is_affiliate: boolean; occurrences: number; }
interface Business { id: number; name: string; contact_email: string | null; contact_phone: string | null; website: string | null; city: string | null; category: string | null; subcategory: string | null; is_premium: boolean; }
interface Category { category: string; count: number; }

export default function CountryProfileDetail() {
  const { countrySlug } = useParams<{ countrySlug: string }>();
  const [country, setCountry] = useState<Country | null>(null);
  const [articles, setArticles] = useState<Article[]>([]);
  const [links, setLinks] = useState<ExtLink[]>([]);
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'articles' | 'liens' | 'entreprises'>('articles');

  useEffect(() => {
    if (!countrySlug) return;
    api.get(`/content/country-profiles/${countrySlug}`)
      .then((res) => {
        setCountry(res.data.country);
        setArticles(res.data.articles);
        setLinks(res.data.links);
        setBusinesses(res.data.businesses);
        setCategories(res.data.categories);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [countrySlug]);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;
  if (!country) return <div className="p-6 text-muted">Pays non trouve</div>;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <Link to="/content/countries" className="text-muted hover:text-white text-sm">&larr; Fiches Pays</Link>
        <h1 className="font-title text-2xl font-bold text-white mt-1">{country.name}</h1>
        <p className="text-muted text-sm">{country.continent}</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{articles.length}</div>
          <div className="text-xs text-muted">Articles</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{links.length}</div>
          <div className="text-xs text-muted">Liens externes</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{businesses.length}</div>
          <div className="text-xs text-muted">Entreprises</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{categories.length}</div>
          <div className="text-xs text-muted">Categories</div>
        </div>
      </div>

      {/* Categories badges */}
      {categories.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {categories.map((c) => (
            <span key={c.category} className="px-3 py-1 bg-violet/20 text-violet-light rounded-full text-xs">
              {c.category} ({c.count})
            </span>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {([
          { key: 'articles' as const, label: `Articles (${articles.length})` },
          { key: 'liens' as const, label: `Liens (${links.length})` },
          { key: 'entreprises' as const, label: `Entreprises (${businesses.length})` },
        ]).map((t) => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
            }`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* Articles tab */}
      {tab === 'articles' && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Titre</th>
              <th className="px-4 py-3 font-medium">Categorie</th>
              <th className="px-4 py-3 font-medium">Section</th>
              <th className="px-4 py-3 font-medium text-right">Mots</th>
            </tr></thead>
            <tbody>
              {articles.map((a) => (
                <tr key={a.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-2">
                    <div className="text-white font-medium">{a.title}</div>
                    {a.meta_description && <div className="text-xs text-muted truncate max-w-lg">{a.meta_description}</div>}
                  </td>
                  <td className="px-4 py-2">
                    {a.category && <span className="px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">{a.category}</span>}
                  </td>
                  <td className="px-4 py-2 text-muted text-xs capitalize">{a.section}</td>
                  <td className="px-4 py-2 text-right text-white">{a.word_count.toLocaleString()}</td>
                </tr>
              ))}
              {articles.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">Aucun article</td></tr>}
            </tbody>
          </table>
        </div>
      )}

      {/* Links tab */}
      {tab === 'liens' && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Domaine</th>
              <th className="px-4 py-3 font-medium">Anchor</th>
              <th className="px-4 py-3 font-medium">Type</th>
              <th className="px-4 py-3 font-medium text-center">Affilie</th>
              <th className="px-4 py-3 font-medium text-right">Occ.</th>
            </tr></thead>
            <tbody>
              {links.map((l) => (
                <tr key={l.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-2"><a href={l.url} target="_blank" rel="noopener noreferrer" className="text-cyan text-xs hover:underline">{l.domain}</a></td>
                  <td className="px-4 py-2 text-gray-400 text-xs truncate max-w-xs">{l.anchor_text || '-'}</td>
                  <td className="px-4 py-2"><span className="px-2 py-0.5 bg-gray-700 rounded text-xs text-gray-300">{l.link_type}</span></td>
                  <td className="px-4 py-2 text-center">{l.is_affiliate && <span className="text-amber text-xs font-bold">AFF</span>}</td>
                  <td className="px-4 py-2 text-right text-white">{l.occurrences}</td>
                </tr>
              ))}
              {links.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Aucun lien</td></tr>}
            </tbody>
          </table>
        </div>
      )}

      {/* Businesses tab */}
      {tab === 'entreprises' && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Entreprise</th>
              <th className="px-4 py-3 font-medium">Email</th>
              <th className="px-4 py-3 font-medium">Telephone</th>
              <th className="px-4 py-3 font-medium">Ville</th>
              <th className="px-4 py-3 font-medium">Categorie</th>
            </tr></thead>
            <tbody>
              {businesses.map((b) => (
                <tr key={b.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-2">
                    <div className="flex items-center gap-2">
                      {b.is_premium && <span className="text-amber text-[10px]">PRO</span>}
                      <div>
                        <div className="text-white font-medium">{b.name}</div>
                        {b.website && <a href={b.website} target="_blank" rel="noopener noreferrer" className="text-xs text-cyan hover:underline">{b.website.replace(/^https?:\/\//, '').slice(0, 40)}</a>}
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-2">{b.contact_email ? <a href={`mailto:${b.contact_email}`} className="text-green-400 text-xs">{b.contact_email}</a> : <span className="text-muted/30 text-xs">-</span>}</td>
                  <td className="px-4 py-2">{b.contact_phone ? <span className="text-cyan text-xs">{b.contact_phone}</span> : <span className="text-muted/30 text-xs">-</span>}</td>
                  <td className="px-4 py-2 text-gray-400 text-xs">{b.city || '-'}</td>
                  <td className="px-4 py-2 text-gray-400 text-xs">{b.category}{b.subcategory ? ` > ${b.subcategory}` : ''}</td>
                </tr>
              ))}
              {businesses.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Aucune entreprise</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
