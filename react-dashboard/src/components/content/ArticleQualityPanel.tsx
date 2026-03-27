import { useState } from 'react';
import { checkArticlePlagiarism, fetchArticleFullAudit, improveArticleQuality } from '../../api/contentApi';
import type { PlagiarismResult, QualityAuditResult } from '../../types/content';

interface Props {
  articleId: number;
  qualityScore: number | null;
  seoScore: number | null;
  readabilityScore: number | null;
}

export default function ArticleQualityPanel({ articleId, qualityScore, seoScore, readabilityScore }: Props) {
  const [plagiarism, setPlagiarism] = useState<PlagiarismResult | null>(null);
  const [audit, setAudit] = useState<QualityAuditResult | null>(null);
  const [loading, setLoading] = useState<'plagiarism' | 'audit' | 'improve' | null>(null);
  const [error, setError] = useState<string | null>(null);

  const runPlagiarismCheck = async () => {
    setLoading('plagiarism');
    setError(null);
    try {
      const { data } = await checkArticlePlagiarism(articleId);
      setPlagiarism(data);
    } catch (e: any) {
      setError(e.response?.data?.message || 'Erreur lors de la vérification plagiat');
    } finally {
      setLoading(null);
    }
  };

  const runFullAudit = async () => {
    setLoading('audit');
    setError(null);
    try {
      const { data } = await fetchArticleFullAudit(articleId);
      setAudit(data);
    } catch (e: any) {
      setError(e.response?.data?.message || 'Erreur lors de l\'audit');
    } finally {
      setLoading(null);
    }
  };

  const runImprove = async () => {
    setLoading('improve');
    setError(null);
    try {
      await improveArticleQuality(articleId);
      setError(null);
      alert('Job d\'amélioration dispatché. L\'article sera mis à jour automatiquement.');
    } catch (e: any) {
      setError(e.response?.data?.message || 'Erreur');
    } finally {
      setLoading(null);
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'original': return 'text-green-400 bg-green-500/20';
      case 'similar': return 'text-yellow-400 bg-yellow-500/20';
      case 'plagiarized': return 'text-red-400 bg-red-500/20';
      default: return 'text-gray-400 bg-gray-500/20';
    }
  };

  const scoreColor = (score: number | null) => {
    if (!score) return 'text-gray-400';
    if (score >= 80) return 'text-green-400';
    if (score >= 60) return 'text-yellow-400';
    return 'text-red-400';
  };

  return (
    <div className="space-y-4">
      {/* Scores actuels */}
      <div className="grid grid-cols-3 gap-3">
        <div className="bg-[#1a1a2e] rounded-lg p-3 text-center">
          <div className="text-xs text-gray-400 mb-1">Quality</div>
          <div className={`text-2xl font-bold ${scoreColor(qualityScore)}`}>
            {qualityScore ?? '—'}
          </div>
        </div>
        <div className="bg-[#1a1a2e] rounded-lg p-3 text-center">
          <div className="text-xs text-gray-400 mb-1">SEO</div>
          <div className={`text-2xl font-bold ${scoreColor(seoScore)}`}>
            {seoScore ?? '—'}
          </div>
        </div>
        <div className="bg-[#1a1a2e] rounded-lg p-3 text-center">
          <div className="text-xs text-gray-400 mb-1">Lisibilite</div>
          <div className={`text-2xl font-bold ${scoreColor(readabilityScore ? Math.round(readabilityScore) : null)}`}>
            {readabilityScore ? Math.round(readabilityScore) : '—'}
          </div>
        </div>
      </div>

      {/* Actions */}
      <div className="flex gap-2">
        <button
          onClick={runPlagiarismCheck}
          disabled={loading !== null}
          className="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 rounded-md transition-colors"
        >
          {loading === 'plagiarism' ? 'Verification...' : 'Verifier plagiat'}
        </button>
        <button
          onClick={runFullAudit}
          disabled={loading !== null}
          className="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 rounded-md transition-colors"
        >
          {loading === 'audit' ? 'Audit...' : 'Audit complet'}
        </button>
        <button
          onClick={runImprove}
          disabled={loading !== null}
          className="px-3 py-1.5 text-sm bg-amber-600 hover:bg-amber-700 disabled:bg-gray-600 rounded-md transition-colors"
        >
          {loading === 'improve' ? 'En cours...' : 'Ameliorer'}
        </button>
      </div>

      {error && (
        <div className="bg-red-500/20 text-red-400 px-3 py-2 rounded-md text-sm">{error}</div>
      )}

      {/* Plagiarism Results */}
      {plagiarism && (
        <div className="bg-[#1a1a2e] rounded-lg p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold">Resultat Plagiat</h4>
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColor(plagiarism.status)}`}>
              {plagiarism.status === 'original' ? 'Original' :
               plagiarism.status === 'similar' ? 'Similaire' : 'Plagie'}
            </span>
          </div>

          {/* Similarity bar */}
          <div>
            <div className="flex justify-between text-xs text-gray-400 mb-1">
              <span>Similarite</span>
              <span>{plagiarism.similarity_percent.toFixed(1)}%</span>
            </div>
            <div className="h-2 bg-gray-700 rounded-full overflow-hidden">
              <div
                className={`h-full rounded-full transition-all ${
                  plagiarism.similarity_percent >= 35 ? 'bg-red-500' :
                  plagiarism.similarity_percent >= 20 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${Math.min(plagiarism.similarity_percent, 100)}%` }}
              />
            </div>
          </div>

          <div className="text-xs text-gray-400">
            {plagiarism.unique_shingles} / {plagiarism.total_shingles} segments uniques
          </div>

          {/* Matches */}
          {plagiarism.matches.length > 0 && (
            <div className="space-y-2">
              <h5 className="text-xs font-medium text-gray-300">Articles similaires :</h5>
              {plagiarism.matches.map((match, i) => (
                <div key={i} className="bg-[#0f0f23] rounded p-2 text-xs">
                  <div className="flex justify-between items-start mb-1">
                    <span className="text-gray-300 font-medium truncate max-w-[70%]">
                      #{match.article_id} — {match.article_title}
                    </span>
                    <span className={`px-1.5 py-0.5 rounded font-medium ${
                      match.similarity >= 35 ? 'bg-red-500/20 text-red-400' :
                      match.similarity >= 20 ? 'bg-yellow-500/20 text-yellow-400' :
                      'bg-green-500/20 text-green-400'
                    }`}>
                      {match.similarity.toFixed(1)}%
                    </span>
                  </div>
                  {match.matching_phrases.length > 0 && (
                    <div className="mt-1 space-y-1">
                      {match.matching_phrases.slice(0, 3).map((phrase, j) => (
                        <p key={j} className="text-gray-500 italic border-l-2 border-red-500/30 pl-2">
                          "{phrase.length > 120 ? phrase.slice(0, 120) + '...' : phrase}"
                        </p>
                      ))}
                      {match.matching_phrases.length > 3 && (
                        <p className="text-gray-600">+{match.matching_phrases.length - 3} phrases...</p>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Full Audit Results */}
      {audit && (
        <div className="bg-[#1a1a2e] rounded-lg p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold">Audit Complet</h4>
            <span className={`text-lg font-bold ${scoreColor(audit.overall_score)}`}>
              {audit.overall_score}/100
            </span>
          </div>

          <div className="grid grid-cols-2 gap-2 text-xs">
            <div className="bg-[#0f0f23] rounded p-2">
              <div className="text-gray-400">Plagiat</div>
              <div className={statusColor(audit.plagiarism.status) + ' font-medium mt-1 inline-block px-1.5 py-0.5 rounded'}>
                {audit.plagiarism.status} ({audit.plagiarism.similarity_percent.toFixed(1)}%)
              </div>
            </div>
            <div className="bg-[#0f0f23] rounded p-2">
              <div className="text-gray-400">Lisibilite</div>
              <div className="text-gray-200 mt-1">
                Flesch: {audit.readability.flesch_score} — {audit.readability.grade_level}
              </div>
            </div>
            <div className="bg-[#0f0f23] rounded p-2">
              <div className="text-gray-400">Ton</div>
              <div className="text-gray-200 mt-1">
                {audit.tone.detected_tone} (formalite: {Math.round(audit.tone.formality * 100)}%)
              </div>
            </div>
            <div className="bg-[#0f0f23] rounded p-2">
              <div className="text-gray-400">Marque</div>
              <div className={`mt-1 ${audit.brand.compliant ? 'text-green-400' : 'text-red-400'}`}>
                {audit.brand.compliant ? 'Conforme' : 'Non conforme'} ({audit.brand.score}/100)
              </div>
              {audit.brand.issues.length > 0 && (
                <ul className="text-gray-500 mt-1">
                  {audit.brand.issues.slice(0, 2).map((issue, i) => (
                    <li key={i}>- {issue}</li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
