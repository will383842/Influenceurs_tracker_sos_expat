import api from './client';
import type {
  GeneratedArticle,
  Comparative,
  LandingPage,
  PressRelease,
  PressDossier,
  ContentCampaign,
  ContentCampaignItem,
  GenerationPreset,
  PromptTemplate,
  GenerationStats,
  GenerationLog,
  ArticleVersion,
  SeoAnalysis,
  SeoDashboard,
  HreflangMatrixEntry,
  InternalLinksGraph,
  PublishingEndpoint,
  PublicationQueueItem,
  PublicationSchedule,
  CostOverview,
  CostBreakdownEntry,
  CostTrendEntry,
  UnsplashImage,
  PaginatedResponse,
  GenerateArticleParams,
  GenerateComparativeParams,
  TopicCluster,
  QaEntry,
  KeywordTracking,
  KeywordGap,
  KeywordCannibalization,
  ArticleKeyword,
  TranslationBatch,
  TranslationOverview,
  SeoChecklist,
  QuestionCluster,
  QuestionClusterStats,
} from '../types/content';

// ============================================================
// ARTICLES
// ============================================================

export const fetchArticles = (params?: {
  status?: string;
  language?: string;
  country?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<GeneratedArticle>>('/content-gen/articles', { params });

export const fetchArticle = (id: number) =>
  api.get<GeneratedArticle>(`/content-gen/articles/${id}`);

export const generateArticle = (params: GenerateArticleParams) =>
  api.post<GeneratedArticle>('/content-gen/articles', params);

export const updateArticle = (id: number, data: Partial<GeneratedArticle>) =>
  api.put<GeneratedArticle>(`/content-gen/articles/${id}`, data);

export const deleteArticle = (id: number) =>
  api.delete(`/content-gen/articles/${id}`);

export const publishArticle = (id: number, data: { endpoint_id: number; scheduled_at?: string }) =>
  api.post(`/content-gen/articles/${id}/publish`, data);

export const unpublishArticle = (id: number) =>
  api.post(`/content-gen/articles/${id}/unpublish`);

export const duplicateArticle = (id: number) =>
  api.post<GeneratedArticle>(`/content-gen/articles/${id}/duplicate`);

export const bulkPublishArticles = (data: { article_ids: number[]; endpoint_id: number }) =>
  api.post('/content-gen/articles/bulk-publish', data);

export const bulkDeleteArticles = (ids: number[]) =>
  api.delete('/content-gen/articles/bulk-delete', { data: { article_ids: ids } });

export const fetchArticleVersions = (id: number) =>
  api.get<ArticleVersion[]>(`/content-gen/articles/${id}/versions`);

export const restoreArticleVersion = (articleId: number, versionId: number) =>
  api.post<GeneratedArticle>(`/content-gen/articles/${articleId}/versions/${versionId}/restore`);

// ============================================================
// COMPARATIVES
// ============================================================

export const fetchComparatives = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<Comparative>>('/content-gen/comparatives', { params });

export const fetchComparative = (id: number) =>
  api.get<Comparative>(`/content-gen/comparatives/${id}`);

export const generateComparative = (params: GenerateComparativeParams) =>
  api.post<Comparative>('/content-gen/comparatives', params);

export const updateComparative = (id: number, data: Partial<Comparative>) =>
  api.put<Comparative>(`/content-gen/comparatives/${id}`, data);

export const deleteComparative = (id: number) =>
  api.delete(`/content-gen/comparatives/${id}`);

export const publishComparative = (id: number, data: { endpoint_id: number; scheduled_at?: string }) =>
  api.post(`/content-gen/comparatives/${id}/publish`, data);

// ============================================================
// LANDING PAGES
// ============================================================

export const fetchLandings = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<LandingPage>>('/content-gen/landings', { params });

export const fetchLanding = (id: number) =>
  api.get<LandingPage>(`/content-gen/landings/${id}`);

export const createLanding = (data: Partial<LandingPage>) =>
  api.post<LandingPage>('/content-gen/landings', data);

export const updateLanding = (id: number, data: Partial<LandingPage>) =>
  api.put<LandingPage>(`/content-gen/landings/${id}`, data);

export const deleteLanding = (id: number) =>
  api.delete(`/content-gen/landings/${id}`);

export const publishLanding = (id: number, data: { endpoint_id: number }) =>
  api.post(`/content-gen/landings/${id}/publish`, data);

export const manageLandingCtas = (id: number, ctas: { url: string; text: string; position: string; style: string; sort_order: number }[]) =>
  api.post(`/content-gen/landings/${id}/ctas`, { ctas });

// ============================================================
// PRESS RELEASES
// ============================================================

export const fetchPressReleases = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PressRelease>>('/content-gen/press/releases', { params });

export const fetchPressRelease = (id: number) =>
  api.get<PressRelease>(`/content-gen/press/releases/${id}`);

export const createPressRelease = (data: Partial<PressRelease>) =>
  api.post<PressRelease>('/content-gen/press/releases', data);

export const updatePressRelease = (id: number, data: Partial<PressRelease>) =>
  api.put<PressRelease>(`/content-gen/press/releases/${id}`, data);

export const deletePressRelease = (id: number) =>
  api.delete(`/content-gen/press/releases/${id}`);

export const publishPressRelease = (id: number, data: { endpoint_id: number }) =>
  api.post(`/content-gen/press/releases/${id}/publish`, data);

export const exportPressReleasePdf = (id: number) =>
  api.get(`/content-gen/press/releases/${id}/export-pdf`, { responseType: 'blob' });

export const exportPressReleaseWord = (id: number) =>
  api.get(`/content-gen/press/releases/${id}/export-word`, { responseType: 'blob' });

// ============================================================
// PRESS DOSSIERS
// ============================================================

export const fetchPressDossiers = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PressDossier>>('/content-gen/press/dossiers', { params });

export const fetchPressDossier = (id: number) =>
  api.get<PressDossier>(`/content-gen/press/dossiers/${id}`);

export const createPressDossier = (data: Partial<PressDossier>) =>
  api.post<PressDossier>('/content-gen/press/dossiers', data);

export const updatePressDossier = (id: number, data: Partial<PressDossier>) =>
  api.put<PressDossier>(`/content-gen/press/dossiers/${id}`, data);

export const deletePressDossier = (id: number) =>
  api.delete(`/content-gen/press/dossiers/${id}`);

export const addDossierItem = (dossierId: number, data: { itemable_type: string; itemable_id: number }) =>
  api.post(`/content-gen/press/dossiers/${dossierId}/items`, data);

export const removeDossierItem = (dossierId: number, itemId: number) =>
  api.delete(`/content-gen/press/dossiers/${dossierId}/items/${itemId}`);

export const reorderDossierItems = (dossierId: number, itemIds: number[]) =>
  api.put(`/content-gen/press/dossiers/${dossierId}/reorder`, { item_ids: itemIds });

export const exportDossierPdf = (id: number) =>
  api.get(`/content-gen/press/dossiers/${id}/export-pdf`, { responseType: 'blob' });

// ============================================================
// CAMPAIGNS
// ============================================================

export const fetchCampaigns = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<ContentCampaign>>('/content-gen/campaigns', { params });

export const fetchCampaign = (id: number) =>
  api.get<ContentCampaign>(`/content-gen/campaigns/${id}`);

export const createCampaign = (data: Partial<ContentCampaign>) =>
  api.post<ContentCampaign>('/content-gen/campaigns', data);

export const updateCampaign = (id: number, data: Partial<ContentCampaign>) =>
  api.put<ContentCampaign>(`/content-gen/campaigns/${id}`, data);

export const deleteCampaign = (id: number) =>
  api.delete(`/content-gen/campaigns/${id}`);

export const startCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/start`);

export const pauseCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/pause`);

export const resumeCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/resume`);

export const cancelCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/cancel`);

export const fetchCampaignItems = (id: number) =>
  api.get<ContentCampaignItem[]>(`/content-gen/campaigns/${id}/items`);

// ============================================================
// GENERATION
// ============================================================

export const fetchGenerationStats = () =>
  api.get<GenerationStats>('/content-gen/generation/stats');

export const fetchGenerationHistory = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<GenerationLog>>('/content-gen/generation/history', { params });

export const fetchPresets = () =>
  api.get<GenerationPreset[]>('/content-gen/generation/presets');

export const createPreset = (data: Partial<GenerationPreset>) =>
  api.post<GenerationPreset>('/content-gen/generation/presets', data);

export const updatePreset = (id: number, data: Partial<GenerationPreset>) =>
  api.put<GenerationPreset>(`/content-gen/generation/presets/${id}`, data);

export const deletePreset = (id: number) =>
  api.delete(`/content-gen/generation/presets/${id}`);

export const fetchPromptTemplates = () =>
  api.get<PromptTemplate[]>('/content-gen/generation/prompts');

export const createPromptTemplate = (data: Partial<PromptTemplate>) =>
  api.post<PromptTemplate>('/content-gen/generation/prompts', data);

export const updatePromptTemplate = (id: number, data: Partial<PromptTemplate>) =>
  api.put<PromptTemplate>(`/content-gen/generation/prompts/${id}`, data);

export const deletePromptTemplate = (id: number) =>
  api.delete(`/content-gen/generation/prompts/${id}`);

export const testPromptTemplate = (data: { prompt_id: number; variables: Record<string, string> }) =>
  api.post<{ output: string }>('/content-gen/generation/prompts/test', data);

// ============================================================
// SEO
// ============================================================

export const fetchSeoDashboard = () =>
  api.get<SeoDashboard>('/content-gen/seo/dashboard');

export const analyzeSeo = (data: { model_type: string; model_id: number }) =>
  api.post<SeoAnalysis>('/content-gen/seo/analyze', data);

export const fetchHreflangMatrix = () =>
  api.get<HreflangMatrixEntry[]>('/content-gen/seo/hreflang-matrix');

export const fetchInternalLinksGraph = () =>
  api.get<InternalLinksGraph>('/content-gen/seo/internal-links-graph');

export const fetchOrphanedArticles = () =>
  api.get<GeneratedArticle[]>('/content-gen/seo/orphaned');

export const fixOrphanedArticle = (articleId: number) =>
  api.post('/content-gen/seo/fix-orphaned', { article_id: articleId });

// ============================================================
// PUBLISHING
// ============================================================

export const fetchEndpoints = () =>
  api.get<PublishingEndpoint[]>('/content-gen/publishing/endpoints');

export const createEndpoint = (data: Partial<PublishingEndpoint>) =>
  api.post<PublishingEndpoint>('/content-gen/publishing/endpoints', data);

export const updateEndpoint = (id: number, data: Partial<PublishingEndpoint>) =>
  api.put<PublishingEndpoint>(`/content-gen/publishing/endpoints/${id}`, data);

export const deleteEndpoint = (id: number) =>
  api.delete(`/content-gen/publishing/endpoints/${id}`);

export const fetchPublicationQueue = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PublicationQueueItem>>('/content-gen/publishing/queue', { params });

export const executeQueueItem = (id: number) =>
  api.post<PublicationQueueItem>(`/content-gen/publishing/queue/${id}/execute`);

export const cancelQueueItem = (id: number) =>
  api.post<PublicationQueueItem>(`/content-gen/publishing/queue/${id}/cancel`);

export const fetchSchedule = (endpointId: number) =>
  api.get<PublicationSchedule>(`/content-gen/publishing/endpoints/${endpointId}/schedule`);

export const updateSchedule = (endpointId: number, data: Partial<PublicationSchedule>) =>
  api.put<PublicationSchedule>(`/content-gen/publishing/endpoints/${endpointId}/schedule`, data);

// ============================================================
// COSTS
// ============================================================

export const fetchCostOverview = () =>
  api.get<CostOverview>('/content-gen/costs/overview');

export const fetchCostBreakdown = (params?: { period?: string }) =>
  api.get<CostBreakdownEntry[]>('/content-gen/costs/breakdown', { params });

export const fetchCostTrends = (params?: { days?: number }) =>
  api.get<CostTrendEntry[]>('/content-gen/costs/trends', { params });

// ============================================================
// MEDIA
// ============================================================

export const searchUnsplash = (query: string, perPage?: number) =>
  api.get<UnsplashImage[]>('/content-gen/media/unsplash', { params: { query, per_page: perPage } });

export const generateDalleImage = (prompt: string, size?: string) =>
  api.post<{ url: string }>('/content-gen/media/generate-image', { prompt, size });

// ============================================================
// CLUSTERS
// ============================================================

export const fetchClusters = (params?: {
  country?: string;
  category?: string;
  status?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<TopicCluster>>('/content-gen/clusters', { params });

export const fetchCluster = (id: number) =>
  api.get<TopicCluster>(`/content-gen/clusters/${id}`);

export const autoCluster = (data: { country: string; category?: string }) =>
  api.post<{ clusters_created: number; message: string }>('/content-gen/clusters/auto-cluster', data);

export const generateClusterBrief = (id: number) =>
  api.post<TopicCluster>(`/content-gen/clusters/${id}/brief`);

export const generateFromCluster = (id: number) =>
  api.post<TopicCluster>(`/content-gen/clusters/${id}/generate`);

export const generateClusterQa = (id: number) =>
  api.post<{ qa_created: number }>(`/content-gen/clusters/${id}/generate-qa`);

export const deleteCluster = (id: number) =>
  api.delete(`/content-gen/clusters/${id}`);

// ============================================================
// Q&A
// ============================================================

export const fetchQaEntries = (params?: {
  language?: string;
  country?: string;
  category?: string;
  status?: string;
  source_type?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<QaEntry>>('/content-gen/qa', { params });

export const fetchQaEntry = (id: number) =>
  api.get<QaEntry>(`/content-gen/qa/${id}`);

export const createQaEntry = (data: Partial<QaEntry>) =>
  api.post<QaEntry>('/content-gen/qa', data);

export const updateQaEntry = (id: number, data: Partial<QaEntry>) =>
  api.put<QaEntry>(`/content-gen/qa/${id}`, data);

export const deleteQaEntry = (id: number) =>
  api.delete(`/content-gen/qa/${id}`);

export const publishQaEntry = (id: number) =>
  api.post(`/content-gen/qa/${id}/publish`);

export const generateQaFromArticle = (articleId: number) =>
  api.post<{ qa_created: number }>('/content-gen/qa/generate-from-article', { article_id: articleId });

export const generateQaFromPaa = (data: { topic: string; country: string; language?: string }) =>
  api.post<{ qa_created: number }>('/content-gen/qa/generate-from-paa', data);

export const bulkPublishQa = (ids: number[]) =>
  api.post('/content-gen/qa/bulk-publish', { qa_ids: ids });

// ============================================================
// KEYWORDS
// ============================================================

export const fetchKeywords = (params?: {
  type?: string;
  language?: string;
  country?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<KeywordTracking>>('/content-gen/keywords', { params });

export const fetchKeywordGaps = (params?: { language?: string; country?: string }) =>
  api.get<KeywordGap[]>('/content-gen/keywords/gaps', { params });

export const fetchKeywordCannibalization = () =>
  api.get<KeywordCannibalization[]>('/content-gen/keywords/cannibalization');

export const fetchArticleKeywords = (articleId: number) =>
  api.get<ArticleKeyword[]>(`/content-gen/keywords/article/${articleId}`);

// ============================================================
// TRANSLATIONS
// ============================================================

export const fetchTranslationBatches = (params?: { status?: string; page?: number }) =>
  api.get<PaginatedResponse<TranslationBatch>>('/content-gen/translations', { params });

export const fetchTranslationOverview = () =>
  api.get<TranslationOverview[]>('/content-gen/translations/overview');

export const startTranslationBatch = (data: { target_language: string; content_type: 'article' | 'qa' | 'all' }) =>
  api.post<TranslationBatch>('/content-gen/translations/start', data);

export const fetchTranslationBatch = (id: number) =>
  api.get<TranslationBatch>(`/content-gen/translations/${id}`);

export const pauseTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/pause`);

export const resumeTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/resume`);

export const cancelTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/cancel`);

// ============================================================
// SEO CHECKLIST
// ============================================================

export const fetchSeoChecklist = (articleId: number) =>
  api.get<SeoChecklist>(`/content-gen/seo/checklist/${articleId}`);

export const evaluateSeoChecklist = (articleId: number) =>
  api.post<SeoChecklist>(`/content-gen/seo/checklist/${articleId}/evaluate`);

export const fetchFailedChecks = (articleId: number) =>
  api.get<{ failed_checks: unknown[]; total_failed: number; overall_score: number }>(`/content-gen/seo/checklist/${articleId}/failed`);

// ============================================================
// QUESTION CLUSTERS
// ============================================================

export const fetchQuestionClusters = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<QuestionCluster>>('/content-gen/question-clusters', { params });

export const fetchQuestionClusterStats = () =>
  api.get<QuestionClusterStats>('/content-gen/question-clusters/stats');

export const autoClusterQuestions = (data?: { country_slug?: string; category?: string }) =>
  api.post('/content-gen/question-clusters/auto-cluster', data);

export const fetchQuestionCluster = (id: number) =>
  api.get<QuestionCluster>(`/content-gen/question-clusters/${id}`);

export const generateQaFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-qa`);

export const generateArticleFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-article`);

export const generateBothFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-both`);

export const skipQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/skip`);

export const deleteQuestionCluster = (id: number) =>
  api.delete(`/content-gen/question-clusters/${id}`);

// ============================================================
// AUTO PIPELINE
// ============================================================

export const runAutoPipeline = (options?: { country?: string; category?: string; max_articles?: number; min_quality_score?: number; include_qa?: boolean; articles_from_questions?: boolean }) =>
  api.post('/content-gen/generation/auto-pipeline', options);

export const fetchPipelineStatus = () =>
  api.get('/content-gen/generation/pipeline-status');

// ============================================================
// DAILY SCHEDULE
// ============================================================

import type {
  DailyContentSchedule,
  DailyContentLog,
  ScheduleStatus,
} from '../types/content';

export const fetchDailySchedule = () =>
  api.get<ScheduleStatus>('/content-gen/schedule');

export const updateDailySchedule = (data: Partial<DailyContentSchedule>) =>
  api.put('/content-gen/schedule', data);

export const fetchScheduleHistory = () =>
  api.get<DailyContentLog[]>('/content-gen/schedule/history');

export const runScheduleNow = () =>
  api.post('/content-gen/schedule/run-now');

export const addCustomTitles = (titles: string[]) =>
  api.post('/content-gen/schedule/custom-titles', { titles });

// ============================================================
// QUALITY & PLAGIARISM
// ============================================================

import type { PlagiarismResult, QualityAuditResult } from '../types/content';

export const checkArticlePlagiarism = (articleId: number) =>
  api.post<PlagiarismResult>(`/content-gen/quality/${articleId}/plagiarism`);

export const fetchArticleReadability = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/readability`);

export const fetchArticleTone = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/tone`);

export const fetchArticleBrandCheck = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/brand`);

export const fetchArticleFactCheck = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/fact-check`);

export const fetchArticleFullAudit = (articleId: number) =>
  api.get<QualityAuditResult>(`/content-gen/quality/${articleId}/full-audit`);

export const improveArticleQuality = (articleId: number) =>
  api.post(`/content-gen/quality/${articleId}/improve`);

export const fetchFlaggedArticles = (params?: { status?: string; min_similarity?: number }) =>
  api.get<PaginatedResponse<GeneratedArticle>>('/content-gen/articles', {
    params: { ...params, sort_by: 'quality_score', sort_dir: 'asc' },
  });
