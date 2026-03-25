import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

type Step = 'segment' | 'email' | 'mode' | 'launch';

const STEPS: { key: Step; label: string }[] = [
  { key: 'segment', label: 'Segment' },
  { key: 'email', label: 'Email modele' },
  { key: 'mode', label: 'Mode d\'envoi' },
  { key: 'launch', label: 'Lancement' },
];

const VARIABLES_HELP = [
  { var: '{{contactName}}', desc: 'Nom du contact' },
  { var: '{{contactCompany}}', desc: 'Nom de l\'organisation' },
  { var: '{{contactCountry}}', desc: 'Pays du contact' },
  { var: '{{contactUrl}}', desc: 'URL du site/profil' },
];

export default function ProspectionCampaignWizard() {
  const navigate = useNavigate();

  // Step navigation
  const [currentStep, setCurrentStep] = useState<Step>('segment');
  const stepIndex = STEPS.findIndex(s => s.key === currentStep);

  // Form state
  const [contactType, setContactType] = useState('');
  const [country, setCountry] = useState('');
  const [limit, setLimit] = useState(30);
  const [modelSubject, setModelSubject] = useState('');
  const [modelBody, setModelBody] = useState('');
  const [sendMode, setSendMode] = useState<'auto' | 'manual'>('manual');

  // Launch state
  const [launching, setLaunching] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; msg: string } | null>(null);

  const contactTypeCfg = CONTACT_TYPES.find(t => t.value === contactType);

  const canGoNext = () => {
    if (currentStep === 'segment') return !!contactType;
    if (currentStep === 'email') return modelSubject.length >= 5 && modelBody.length >= 50;
    if (currentStep === 'mode') return true;
    return false;
  };

  const goNext = () => {
    const idx = stepIndex;
    if (idx < STEPS.length - 1) setCurrentStep(STEPS[idx + 1].key);
  };

  const goBack = () => {
    const idx = stepIndex;
    if (idx > 0) setCurrentStep(STEPS[idx - 1].key);
  };

  const handleLaunch = async () => {
    setLaunching(true);
    setResult(null);
    try {
      // 1. Save the model email as custom_prompt + set auto_send mode
      const promptInstruction = [
        'INSTRUCTION IMPORTANTE: Tu dois utiliser l\'email modele ci-dessous comme BASE.',
        'Garde les memes URLs, liens Calendly et structure generale.',
        'Adapte et personnalise le contenu pour chaque organisation en fonction de leur activite, leur pays et ce qu\'ils font concretement.',
        'Fais des variations de formulation pour que chaque email soit UNIQUE (synonymes, tournures differentes, ordre des arguments).',
        'Ne copie PAS mot pour mot - reformule tout en gardant le meme message et les memes liens.',
        '',
        '--- EMAIL MODELE ---',
        `Objet: ${modelSubject}`,
        '',
        modelBody,
        '--- FIN DU MODELE ---',
      ].join('\n');

      await api.put(`/outreach/config/${contactType}`, {
        custom_prompt: promptInstruction,
        auto_send: sendMode === 'auto',
        ai_generation_enabled: true,
        is_active: true,
      });

      // 2. Generate emails for step 1
      const { data } = await api.post('/outreach/generate', {
        contact_type: contactType,
        country: country || undefined,
        step: 1,
        limit,
      });

      setResult({ ok: true, msg: data.message || `Campagne lancee : ${limit} emails en cours de generation` });
    } catch (err: any) {
      setResult({ ok: false, msg: err.response?.data?.message || 'Erreur lors du lancement de la campagne' });
    }
    setLaunching(false);
  };

  return (
    <div className="p-4 md:p-6 space-y-6 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">&larr; Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Lancer une campagne</h1>
      </div>

      {/* Step indicator */}
      <div className="flex items-center gap-2">
        {STEPS.map((s, i) => (
          <React.Fragment key={s.key}>
            {i > 0 && <div className="flex-1 h-px bg-border" />}
            <button
              onClick={() => i <= stepIndex && setCurrentStep(s.key)}
              disabled={i > stepIndex}
              className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                s.key === currentStep ? 'bg-violet text-white' :
                i < stepIndex ? 'bg-violet/20 text-violet-light cursor-pointer hover:bg-violet/30' :
                'bg-surface2 text-muted'
              }`}
            >
              <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold ${
                s.key === currentStep ? 'bg-white/20' :
                i < stepIndex ? 'bg-violet/30' : 'bg-surface'
              }`}>{i + 1}</span>
              <span className="hidden sm:inline">{s.label}</span>
            </button>
          </React.Fragment>
        ))}
      </div>

      {/* ═══════════════════════════════════════
          STEP 1: SEGMENT
      ═══════════════════════════════════════ */}
      {currentStep === 'segment' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
          <div>
            <h2 className="text-white font-title font-semibold text-lg">Choisir le segment</h2>
            <p className="text-muted text-sm mt-1">Selectionnez le type de contacts a cibler</p>
          </div>

          <div>
            <label className="block text-xs text-muted mb-2 font-medium">Type de contact *</label>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
              {CONTACT_TYPES.map(t => (
                <button key={t.value} onClick={() => setContactType(t.value)}
                  className={`flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm text-left transition-colors border ${
                    contactType === t.value
                      ? 'bg-violet/20 text-violet-light border-violet/50'
                      : 'bg-surface2 text-muted border-border hover:text-white hover:border-border/80'
                  }`}>
                  <span>{t.icon}</span>
                  <span className="truncate">{t.label}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs text-muted mb-1.5 font-medium">Pays (optionnel)</label>
              <input value={country} onChange={e => setCountry(e.target.value)}
                placeholder="Tous les pays (laisser vide)"
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
              <p className="text-[10px] text-muted mt-1">Tapez un pays pour cibler ou laissez vide pour tous les pays</p>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1.5 font-medium">Nombre max de contacts</label>
              <input type="number" value={limit} onChange={e => setLimit(Math.min(50, Math.max(1, Number(e.target.value))))}
                min={1} max={50}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
              <p className="text-[10px] text-muted mt-1">L'IA genere un email unique par contact (max 50)</p>
            </div>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════
          STEP 2: EMAIL MODEL
      ═══════════════════════════════════════ */}
      {currentStep === 'email' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
          <div>
            <h2 className="text-white font-title font-semibold text-lg">Email modele</h2>
            <p className="text-muted text-sm mt-1">
              Ecrivez votre email de reference. L'IA va l'adapter pour chaque {contactTypeCfg?.label || 'contact'} en personnalisant le contenu selon leur activite et leur pays, avec des variations pour que chaque email soit unique.
            </p>
          </div>

          {/* Tips */}
          <div className="bg-violet/5 border border-violet/20 rounded-lg p-4">
            <p className="text-xs text-violet-light font-medium mb-2">Conseils pour un bon email modele :</p>
            <ul className="text-xs text-muted space-y-1">
              <li>- Incluez vos <span className="text-white">URLs et liens Calendly</span> — l'IA les gardera tels quels</li>
              <li>- Ecrivez en <span className="text-white">texte brut</span> (pas de HTML) — ca maximise la delivrabilite</li>
              <li>- Gardez un ton <span className="text-white">professionnel et direct</span> — evitez les points d'exclamation et emojis</li>
              <li>- Expliquez la <span className="text-white">valeur pour eux</span> (pas pour vous) — qu'est-ce qu'ils gagnent ?</li>
              <li>- Terminez par une <span className="text-white">question simple</span> (oui/non ou rdv)</li>
            </ul>
          </div>

          <div>
            <label className="block text-xs text-muted mb-1.5 font-medium">Objet de l'email *</label>
            <input value={modelSubject} onChange={e => setModelSubject(e.target.value)}
              placeholder="Ex: Partenariat avec [votre organisation] — assistance juridique pour vos membres"
              className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
            <p className="text-[10px] text-muted mt-1">L'IA adaptera l'objet pour chaque contact</p>
          </div>

          <div>
            <label className="block text-xs text-muted mb-1.5 font-medium">Corps de l'email * (min. 50 caracteres)</label>
            <textarea value={modelBody} onChange={e => setModelBody(e.target.value)}
              rows={14}
              placeholder={`Bonjour,

Je me permets de vous contacter car [votre organisation] accompagne des expatries francophones et je pense que nous pourrions creer un partenariat gagnant-gagnant.

SOS-Expat permet a vos membres d'obtenir un avocat francophone en moins de 5 minutes, dans 197 pays. C'est gratuit pour vous, et vous recevez une commission de 10EUR par appel genere.

Concretement :
- Vos membres ont acces a une assistance juridique immediate
- Vous recevez un lien d'affiliation personnalise
- Chaque appel via votre lien = 10EUR de commission

On peut en discuter 15 min ? Voici mon lien Calendly : https://calendly.com/votre-lien

Bien cordialement,
Williams`}
              className="w-full bg-bg border border-border rounded-lg px-3 py-3 text-white text-sm focus:border-violet outline-none resize-y leading-relaxed" />
            <div className="flex justify-between items-center mt-1">
              <p className="text-[10px] text-muted">{modelBody.length} caracteres {modelBody.length < 50 && modelBody.length > 0 ? '(min. 50)' : ''}</p>
              <p className="text-[10px] text-muted">Texte brut uniquement — pas de HTML</p>
            </div>
          </div>

          {/* Variable reference */}
          <div className="bg-surface2/50 rounded-lg p-3">
            <p className="text-[10px] text-muted font-medium mb-1.5">L'IA connait deja ces infos sur chaque contact et les utilisera automatiquement :</p>
            <div className="flex flex-wrap gap-2">
              {VARIABLES_HELP.map(v => (
                <span key={v.var} className="inline-flex items-center gap-1 text-[10px] bg-bg border border-border rounded px-2 py-1">
                  <span className="text-cyan font-mono">{v.var}</span>
                  <span className="text-muted">{v.desc}</span>
                </span>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════
          STEP 3: SEND MODE
      ═══════════════════════════════════════ */}
      {currentStep === 'mode' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
          <div>
            <h2 className="text-white font-title font-semibold text-lg">Mode d'envoi</h2>
            <p className="text-muted text-sm mt-1">Choisissez comment les emails seront traites apres generation par l'IA</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Manual mode */}
            <button onClick={() => setSendMode('manual')}
              className={`text-left p-5 rounded-xl border-2 transition-all ${
                sendMode === 'manual'
                  ? 'border-violet bg-violet/10'
                  : 'border-border bg-surface2/30 hover:border-border/80'
              }`}>
              <div className="flex items-center gap-3 mb-3">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${sendMode === 'manual' ? 'bg-violet/20' : 'bg-surface2'}`}>
                  <span className="text-xl">👁️</span>
                </div>
                <div>
                  <p className="text-white font-medium">Validation manuelle</p>
                  <p className="text-xs text-muted">Recommande</p>
                </div>
              </div>
              <ul className="text-xs text-muted space-y-1.5">
                <li className="flex items-start gap-2">
                  <span className="text-emerald-400 mt-0.5">✓</span>
                  <span>Chaque email passe en <span className="text-white">file de review</span></span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-emerald-400 mt-0.5">✓</span>
                  <span>Vous pouvez <span className="text-white">editer, approuver ou rejeter</span> chaque email</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-emerald-400 mt-0.5">✓</span>
                  <span>Controle total avant envoi</span>
                </li>
              </ul>
            </button>

            {/* Auto mode */}
            <button onClick={() => setSendMode('auto')}
              className={`text-left p-5 rounded-xl border-2 transition-all ${
                sendMode === 'auto'
                  ? 'border-amber bg-amber/10'
                  : 'border-border bg-surface2/30 hover:border-border/80'
              }`}>
              <div className="flex items-center gap-3 mb-3">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${sendMode === 'auto' ? 'bg-amber/20' : 'bg-surface2'}`}>
                  <span className="text-xl">⚡</span>
                </div>
                <div>
                  <p className="text-white font-medium">Envoi automatique</p>
                  <p className="text-xs text-amber">Sans validation</p>
                </div>
              </div>
              <ul className="text-xs text-muted space-y-1.5">
                <li className="flex items-start gap-2">
                  <span className="text-amber mt-0.5">⚡</span>
                  <span>Emails generes et <span className="text-white">approuves automatiquement</span></span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-amber mt-0.5">⚡</span>
                  <span>Mis en queue PMTA <span className="text-white">immediatement</span></span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-red-400 mt-0.5">!</span>
                  <span>Pas de review — faites confiance a l'IA</span>
                </li>
              </ul>
            </button>
          </div>

          {sendMode === 'auto' && (
            <div className="bg-amber/10 border border-amber/30 rounded-lg p-3 flex items-start gap-3">
              <span className="text-amber text-sm mt-0.5">⚠</span>
              <p className="text-xs text-amber">
                En mode automatique, les emails seront generes et envoyes sans possibilite de review.
                Assurez-vous que votre email modele est bien redige.
              </p>
            </div>
          )}
        </div>
      )}

      {/* ═══════════════════════════════════════
          STEP 4: RECAP & LAUNCH
      ═══════════════════════════════════════ */}
      {currentStep === 'launch' && (
        <div className="space-y-5">
          {/* Recap */}
          <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
            <h2 className="text-white font-title font-semibold text-lg">Recap de la campagne</h2>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-surface2/50 rounded-lg p-4">
                <p className="text-[10px] text-muted uppercase tracking-wider mb-1">Segment</p>
                <div className="flex items-center gap-2">
                  {contactTypeCfg && <span className="text-lg">{contactTypeCfg.icon}</span>}
                  <span className="text-white font-medium">{contactTypeCfg?.label}</span>
                </div>
                <p className="text-xs text-muted mt-1">{country || 'Tous les pays'} — max {limit} contacts</p>
              </div>

              <div className="bg-surface2/50 rounded-lg p-4">
                <p className="text-[10px] text-muted uppercase tracking-wider mb-1">Mode d'envoi</p>
                <div className="flex items-center gap-2">
                  <span className="text-lg">{sendMode === 'manual' ? '👁️' : '⚡'}</span>
                  <span className="text-white font-medium">{sendMode === 'manual' ? 'Validation manuelle' : 'Envoi automatique'}</span>
                </div>
                <p className="text-xs text-muted mt-1">
                  {sendMode === 'manual' ? 'Emails en file de review' : 'Emails envoyes directement'}
                </p>
              </div>

              <div className="bg-surface2/50 rounded-lg p-4">
                <p className="text-[10px] text-muted uppercase tracking-wider mb-1">Sequence</p>
                <div className="flex items-center gap-2">
                  <span className="text-lg">📧</span>
                  <span className="text-white font-medium">Step 1 — Premier contact</span>
                </div>
                <p className="text-xs text-muted mt-1">Les relances (Step 2-4) seront programmees automatiquement</p>
              </div>
            </div>
          </div>

          {/* Email preview */}
          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <div className="px-5 py-3 border-b border-border bg-surface2/30">
              <p className="text-xs text-muted">Apercu de votre email modele (l'IA va l'adapter pour chaque contact)</p>
            </div>
            <div className="p-5">
              <p className="text-sm text-white font-medium mb-3">Objet: {modelSubject}</p>
              <div className="bg-bg rounded-lg p-4 text-sm text-gray-300 whitespace-pre-wrap leading-relaxed">
                {modelBody}
              </div>
            </div>
          </div>

          {/* Launch button */}
          <div className="bg-surface border border-border rounded-xl p-6">
            {!result ? (
              <div className="flex flex-col items-center gap-4">
                <p className="text-muted text-sm text-center">
                  L'IA va generer <span className="text-white font-medium">{limit} emails personnalises</span> pour les
                  <span className="text-white font-medium"> {contactTypeCfg?.label}</span>
                  {country && <> en <span className="text-white font-medium">{country}</span></>}
                </p>
                <button onClick={handleLaunch} disabled={launching}
                  className="px-8 py-3 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white font-medium rounded-xl transition-colors text-sm">
                  {launching ? (
                    <span className="flex items-center gap-2">
                      <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                      Generation en cours...
                    </span>
                  ) : (
                    `Lancer la campagne (${limit} emails)`
                  )}
                </button>
              </div>
            ) : (
              <div className="text-center space-y-4">
                <div className={`inline-flex items-center gap-3 px-5 py-3 rounded-xl ${result.ok ? 'bg-emerald-500/10 border border-emerald-500/30' : 'bg-red-500/10 border border-red-500/30'}`}>
                  <span className="text-2xl">{result.ok ? '✓' : '✗'}</span>
                  <p className={`text-sm font-medium ${result.ok ? 'text-emerald-400' : 'text-red-400'}`}>{result.msg}</p>
                </div>
                {result.ok && (
                  <div className="flex justify-center gap-3">
                    {sendMode === 'manual' ? (
                      <Link to="/prospection/emails" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                        Aller reviewer les emails
                      </Link>
                    ) : (
                      <Link to="/prospection/sequences" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                        Suivre les sequences
                      </Link>
                    )}
                    <button onClick={() => { setResult(null); setCurrentStep('segment'); }}
                      className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                      Nouvelle campagne
                    </button>
                  </div>
                )}
                {!result.ok && (
                  <button onClick={() => setResult(null)}
                    className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                    Reessayer
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════
          NAVIGATION BUTTONS
      ═══════════════════════════════════════ */}
      {currentStep !== 'launch' || !result ? (
        <div className="flex justify-between items-center">
          <button onClick={goBack} disabled={stepIndex === 0}
            className="px-4 py-2 text-muted hover:text-white text-sm transition-colors disabled:opacity-30">
            &larr; Precedent
          </button>
          {currentStep !== 'launch' && (
            <button onClick={goNext} disabled={!canGoNext()}
              className="px-6 py-2.5 bg-violet hover:bg-violet/90 disabled:opacity-30 text-white text-sm rounded-lg font-medium transition-colors">
              Suivant &rarr;
            </button>
          )}
        </div>
      ) : null}
    </div>
  );
}
