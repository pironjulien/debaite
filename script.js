let isDebating = false;
let currentAbortController = null;

let currentStepIndex = 0;
let memArgA = "";
let memArgB = "";
let globalSubject = "";
let accessState = null;
let debateRunId = 0;
let currentDebateId = "";

const ACCESS_STATUS_URL = "api/session";
const ACCESS_DEVICE_KEY = "debaiteDeviceId";
const ACCESS_DEBATE_KEY = "debaiteTrialDebateId";

const UI_STRINGS = {
  fr: {
    resume: "REPRISE",
    stop: "STOP",
    clear: "EFFACER",
    print: "IMPRIMER",
    logout: "Se déconnecter",
    contact: "Contact",
    googleButton: "Continuer avec Google",
    verifying: "Vérification...",
    subjectPlaceholder: "Sujet du débat...",
    unavailablePlaceholder: "Connexion Google requise",
    helperDefault: "Collisions cognitives entre deux paradigmes divergents.",
    helperUnlimited: "Accès illimité validé.",
    helperTrial: "Un débat d’essai est disponible avec ce compte Google.",
    helperTrialUsed: "Ce compte Google a déjà utilisé son essai.",
    helperGoogleConfig: "Connexion Google requise avant ouverture publique.",
    helperGoogleRequired: "Connectez-vous avec Google pour lancer l’essai unique.",
    unlimitedAccess: "Accès illimité",
    googleTrial: "Essai Google · 1 débat",
    trialUsed: "Essai déjà utilisé",
    googleConfig: "Google à configurer",
    googleRequired: "Connexion Google requise",
    googleDisabled: "Google n’est pas encore configuré.",
    googleDenied: "Ce compte Google n’est pas autorisé.",
    googleError: "Connexion Google impossible.",
    googleOk: "Connexion Google validée.",
    printEmpty: "Aucune conversation à imprimer.",
    phaseIntro: "INTRODUCTION",
    phaseArgument: "ARGUMENTATION",
    phaseRebuttal: "RÉFUTATION",
    phaseConclusion: "CONCLUSION",
    phaseSystemError: "ERREUR SYSTÈME",
    emptyReply: "Réponse vide générée par l'IA.",
    cognitiveError: "Erreur Cognitive",
    languageDirective: "Réponds exclusivement en français."
  },
  en: {
    resume: "RESUME",
    stop: "STOP",
    clear: "CLEAR",
    print: "PRINT",
    logout: "Sign out",
    contact: "Contact",
    googleButton: "Continue with Google",
    verifying: "Checking...",
    subjectPlaceholder: "Debate topic...",
    unavailablePlaceholder: "Google sign-in required",
    helperDefault: "Cognitive collision between two divergent paradigms.",
    helperUnlimited: "Unlimited access confirmed.",
    helperTrial: "One trial debate is available with this Google account.",
    helperTrialUsed: "This Google account has already used its trial.",
    helperGoogleConfig: "Google sign-in must be configured before public launch.",
    helperGoogleRequired: "Sign in with Google to launch the one-time trial.",
    unlimitedAccess: "Unlimited access",
    googleTrial: "Google trial · 1 debate",
    trialUsed: "Trial already used",
    googleConfig: "Google setup required",
    googleRequired: "Google sign-in required",
    googleDisabled: "Google is not configured yet.",
    googleDenied: "This Google account is not authorized.",
    googleError: "Google sign-in failed.",
    googleOk: "Google sign-in confirmed.",
    printEmpty: "No conversation to print.",
    phaseIntro: "INTRODUCTION",
    phaseArgument: "ARGUMENT",
    phaseRebuttal: "REBUTTAL",
    phaseConclusion: "CONCLUSION",
    phaseSystemError: "SYSTEM ERROR",
    emptyReply: "The AI returned an empty answer.",
    cognitiveError: "Cognitive error",
    languageDirective: "Answer exclusively in English."
  }
};

const uiLanguage = resolveUiLanguage();

function resolveUiLanguage() {
  const language = (navigator.languages?.[0] || navigator.language || "fr").toLowerCase();
  return language.startsWith("fr") ? "fr" : "en";
}

function t(key) {
  return UI_STRINGS[uiLanguage]?.[key] || UI_STRINGS.fr[key] || key;
}

function bindConversationActions() {
  const actions = {
    "btn-resume": resumeDebate,
    "btn-stop": stopDebate,
    "btn-restart": resetApp,
    "btn-print": printConversation
  };

  Object.entries(actions).forEach(([id, handler]) => {
    const button = document.getElementById(id);
    if (button) button.addEventListener("click", handler);
  });
}

function setNodeHidden(id, hidden) {
  const node = document.getElementById(id);
  if (node) node.hidden = hidden;
}

// ==========================================
// VRAI ÉCLAIR PROCÉDURAL (CANVAS API)
// ==========================================
let megaLightningTrigger = false;

function initLightning() {
  const canvas = document.getElementById("lightning-canvas");
  if(!canvas) return;
  const ctx = canvas.getContext("2d");
  
  function drawFrame() {
    requestAnimationFrame(drawFrame);
    
    // Fade Out effectif pour créer le trail électrique
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = "rgba(0, 0, 0, 0.25)";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    if(document.body.classList.contains("state-debating")) return;

    // Déclencheur stochastique
    let chance = megaLightningTrigger ? 1.0 : 0.08;
    if (Math.random() > chance) return;

    ctx.globalCompositeOperation = 'lighter';
    ctx.beginPath();
    
    let x = 0; 
    let y = canvas.height / 2 + (Math.random() - 0.5) * 50;
    ctx.moveTo(x, y);
    
    while (x < canvas.width) {
        x += Math.random() * 30 + 10;
        y += (Math.random() - 0.5) * 80;
        let centerPull = ((canvas.height / 2) - y) * 0.1;
        y += centerPull;
        ctx.lineTo(x, y);
    }
    
    // Glow 
    ctx.lineWidth = Math.random() * 2.5 + (megaLightningTrigger ? 4 : 1);
    ctx.strokeStyle = `rgba(0, 229, 255, ${Math.random() * 0.5 + 0.5})`;
    ctx.shadowBlur = megaLightningTrigger ? 50 : 30;
    ctx.shadowColor = "#00e5ff";
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(0, canvas.height / 2);
    ctx.lineWidth = megaLightningTrigger ? 3 : 1;
    ctx.strokeStyle = "rgba(255,255,255,0.9)";
    ctx.shadowBlur = 0;
    ctx.stroke();

    megaLightningTrigger = false; // consume trigger
  }
  drawFrame();
}

// ==========================================
// INTELLIGENCE DE MOUVEMENT STOCHASTIQUE
// ==========================================
let swarmWanderId = null;

function initAvatarsSwarm() {
    const a1 = document.getElementById('avatar-a');
    const a2 = document.getElementById('avatar-b');
    if (!a1 || !a2) return;
    
    function logicTick() {
        if (document.body.classList.contains("state-debating")) {
            // Nettoyage absolu pour céder la place au CSS statique du chat
            a1.style.transform = ''; a2.style.transform = '';
            a1.style.filter = ''; a2.style.filter = '';
            a1.style.transition = ''; a2.style.transition = '';
            return;
        }

        if (window.matchMedia("(max-width: 720px)").matches) {
            a1.style.transform = '';
            a2.style.transform = '';
            a1.style.filter = '';
            a2.style.filter = '';
            a1.style.transition = '';
            a2.style.transition = '';
            swarmWanderId = setTimeout(logicTick, 1200);
            return;
        }

        const isClash = Math.random() > 0.65; // 35% de chance de choquer violemment
        
        if (isClash) {
            // Physique de Collision Frontale (Aucun chevauchement d'orbites)
            const winner = Math.random() > 0.5 ? 'left' : 'right';
            
            // Les avatars font 330px. Le point de contact parfait = Différence de 330px entre leurs transform X.
            // Le "winner" décale le point d'impact dans le camp de l'autre de 85px.
            let crashA1 = winner === 'left' ? -85 : -245; 
            let crashA2 = winner === 'left' ? 245 : 85;   

            a1.style.zIndex = "55"; a2.style.zIndex = "55"; // Layering pur, sur la même profondeur géométrique
            
            a1.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            a2.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            
            // IMPACT FORCE
            a1.style.transform = `translate(${crashA1}px, -50%) scale(1.05) rotate(${winner==='left'? 4 : -2}deg)`;
            a2.style.transform = `translate(${crashA2}px, -50%) scale(1.05) rotate(${winner==='right'? -4 : 2}deg)`;
            a1.style.filter = "drop-shadow(0 0 30px rgba(0,229,255,0.9)) contrast(1.4)";
            a2.style.filter = "drop-shadow(0 0 30px rgba(255,0,85,0.9)) contrast(1.4)";

            setTimeout(() => megaLightningTrigger = true, 150); // Eclair de foudre à l'instant T de l'impact

            setTimeout(() => {
                if (document.body.classList.contains("state-debating")) return;
                // REBOND ELASTIQUE ET SEPARATION KINETIQUE IMMEDIATS
                a1.style.transition = 'all 0.8s cubic-bezier(0.25, 1, 0.5, 1)';
                a2.style.transition = 'all 0.8s cubic-bezier(0.25, 1, 0.5, 1)';
                a1.style.transform = `translate(-360px, -50%) scale(0.95)`;
                a2.style.transform = `translate(120px, -50%) scale(0.95)`;
                a1.style.filter = ""; a2.style.filter = "";
            }, 300); // 300ms après la pénétration du choc, l'énergie les jette en arrière
            
            swarmWanderId = setTimeout(logicTick, 2800 + Math.random() * 1500);
        } else {
            // FLOTTEMENT ORGANIQUE CONTINU (Fluid Pathing)
            // L'astuce : transition plus longue que le timeout = l'IA change d'avis en plein mouvement, sans jamais s'arrêter.
            a1.style.transition = `all 3.5s linear`;
            a2.style.transition = `all 3.5s linear`;
            
            let xA = -350 + (Math.random() * 120);
            let yA = -50 + (Math.random() * 30 - 15);
            let xB = 50 + (Math.random() * 120);
            let yB = -50 + (Math.random() * 30 - 15);

            let sA = 0.95 + Math.random() * 0.1;
            let sB = 0.95 + Math.random() * 0.1;

            a1.style.transform = `translate(${xA}px, ${yA}%) scale(${sA})`;
            a2.style.transform = `translate(${xB}px, ${yB}%) scale(${sB})`;
            a1.style.filter = ""; a2.style.filter = "";

            // Le tick se rappelle AVANT la fin de la transition (3000ms), 
            // ce qui produit un lissage vectoriel "flottant" continu (Lerp).
            swarmWanderId = setTimeout(logicTick, 1500 + Math.random() * 1000);
        }
    }
    logicTick();
}

document.addEventListener('DOMContentLoaded', () => {
  applyStaticTranslations();
  bindConversationActions();
  initLightning();
  initAvatarsSwarm();
  initAccess();
  updateConversationActions();
  const input = document.getElementById("subject");
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !isDebating) startDebate();
  });
});

function applyStaticTranslations() {
  document.documentElement.lang = uiLanguage;
  const staticLabels = {
    "btn-resume": "resume",
    "btn-stop": "stop",
    "btn-restart": "clear",
    "btn-print": "print",
    "access-logout": "logout",
    "access-contact": "contact",
    "access-login-label": "googleButton",
    "access-label": "verifying"
  };

  Object.entries(staticLabels).forEach(([id, key]) => {
    const node = document.getElementById(id);
    if (node) node.textContent = t(key);
  });

  const input = document.getElementById("subject");
  if (input) input.placeholder = t("subjectPlaceholder");
  const helper = document.querySelector(".helper-text");
  if (helper) helper.textContent = t("helperDefault");
}

async function initAccess() {
  setHomeInputEnabled(false);
  const logout = document.getElementById("access-logout");
  if (logout) {
    logout.addEventListener("click", logoutAccess);
  }

  await refreshAccessStatus();
  setHomeInputEnabled(Boolean(accessState?.canGenerate));
  showAuthResultFromUrl();
}

function getDeviceId() {
  try {
    let id = localStorage.getItem(ACCESS_DEVICE_KEY);
    if (!/^[a-f0-9-]{16,64}$/i.test(id || "")) {
      id = window.crypto?.randomUUID ? window.crypto.randomUUID() : fallbackDeviceId();
      localStorage.setItem(ACCESS_DEVICE_KEY, id);
    }
    return id;
  } catch {
    return fallbackDeviceId();
  }
}

function fallbackDeviceId() {
  if (!window.crypto?.getRandomValues) {
    return `${Date.now().toString(16)}${Math.random().toString(16).slice(2)}`;
  }
  const bytes = new Uint8Array(16);
  window.crypto.getRandomValues(bytes);
  return Array.from(bytes, byte => byte.toString(16).padStart(2, "0")).join("");
}

function accessHeaders(extra = {}) {
  const headers = {
    "X-Debaite-Device": getDeviceId(),
    ...extra
  };
  if (accessState?.csrfToken) {
    headers["X-Debaite-CSRF"] = accessState.csrfToken;
  }
  return headers;
}

async function refreshAccessStatus() {
  try {
    const response = await fetch(ACCESS_STATUS_URL, {
      headers: accessHeaders(),
      cache: "no-store"
    });
    const data = await response.json();
    accessState = data.access;
  } catch {
    accessState = {
      canGenerate: false,
      googleEnabled: false,
      contactUrl: "https://twitter.com/julienpironfr",
      trial: { remaining: 0, limit: 1, blocked: true }
    };
  }
  updateAccessUI();
}

async function logoutAccess() {
  if (!accessState?.csrfToken) return;
  const response = await fetch("api/logout", {
    method: "POST",
    headers: accessHeaders()
  });
  const data = await response.json().catch(() => null);
  if (data?.access) {
    accessState = data.access;
    updateAccessUI();
  }
}

function updateAccessUI() {
  const panel = document.getElementById("access-panel");
  const label = document.getElementById("access-label");
  const login = document.getElementById("access-login");
  const contact = document.getElementById("access-contact");
  const logout = document.getElementById("access-logout");
  const helper = document.querySelector(".helper-text");
  if (!panel || !label) return;

  document.body.classList.toggle("access-blocked", !accessState?.canGenerate);
  document.body.classList.toggle("access-ready", Boolean(accessState?.canGenerate));
  document.body.classList.toggle("access-authenticated", Boolean(accessState?.authenticated));

  if (accessState?.unlimited) {
    panel.dataset.state = "unlimited";
    label.textContent = accessState.email ? `${t("unlimitedAccess")} · ${accessState.email}` : t("unlimitedAccess");
    if (helper) helper.textContent = t("helperUnlimited");
  } else if (accessState?.authenticated) {
    panel.dataset.state = accessState?.canGenerate ? "trial" : "blocked";
    label.textContent = accessState?.canGenerate ? t("googleTrial") : t("trialUsed");
    if (helper) helper.textContent = accessState?.canGenerate
      ? t("helperTrial")
      : t("helperTrialUsed");
  } else if (accessState?.canGenerate) {
    panel.dataset.state = "trial";
    label.textContent = t("googleTrial");
    if (helper) helper.textContent = t("helperDefault");
  } else if (!accessState?.googleEnabled) {
    panel.dataset.state = "blocked";
    label.textContent = t("googleConfig");
    if (helper) helper.textContent = t("helperGoogleConfig");
  } else {
    panel.dataset.state = "blocked";
    label.textContent = t("googleRequired");
    if (helper) helper.textContent = t("helperGoogleRequired");
  }

  if (login) {
    login.hidden = !accessState?.googleEnabled || accessState?.authenticated || accessState?.unlimited || accessState?.canGenerate;
  }
  if (contact) {
    contact.href = accessState?.contactUrl || "https://twitter.com/julienpironfr";
    contact.hidden = Boolean(accessState?.canGenerate) || (accessState?.googleEnabled && !accessState?.authenticated);
  }
  if (logout) {
    logout.hidden = !accessState?.authenticated;
  }
}

function setHomeInputEnabled(enabled) {
  const input = document.getElementById("subject");
  if (!input || isDebating) return;
  input.disabled = !enabled;
  input.placeholder = enabled ? t("subjectPlaceholder") : t("unavailablePlaceholder");
  document.body.classList.toggle("prompt-ready", Boolean(enabled));
  input.closest(".home-interaction")?.classList.toggle("is-unlocked", Boolean(enabled));
}

function showAuthResultFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const auth = params.get("auth");
  if (!auth) return;

  const messages = {
    "google-disabled": t("googleDisabled"),
    "google-denied": t("googleDenied"),
    "google-error": t("googleError"),
    "google-ok": t("googleOk")
  };
  const helper = document.querySelector(".helper-text");
  if (messages[auth] && helper) helper.textContent = messages[auth];
  history.replaceState({}, "", window.location.pathname);
}

function createClientId() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return fallbackDeviceId();
}

function ensureTrialDebateId() {
  if (accessState?.unlimited) {
    currentDebateId = createClientId();
    return currentDebateId;
  }

  const activeDebateId = accessState?.trial?.activeDebateId;
  if (/^[a-f0-9-]{16,80}$/i.test(activeDebateId || "")) {
    currentDebateId = activeDebateId;
    try {
      localStorage.setItem(ACCESS_DEBATE_KEY, activeDebateId);
    } catch {
      // Local storage may be blocked; the server-side Google quota remains authoritative.
    }
    return currentDebateId;
  }

  try {
    const stored = localStorage.getItem(ACCESS_DEBATE_KEY);
    if (/^[a-f0-9-]{16,80}$/i.test(stored || "")) {
      currentDebateId = stored;
      return currentDebateId;
    }
    currentDebateId = createClientId();
    localStorage.setItem(ACCESS_DEBATE_KEY, currentDebateId);
    return currentDebateId;
  } catch {
    currentDebateId = createClientId();
    return currentDebateId;
  }
}

function clearTrialDebateId() {
  if (accessState?.unlimited) return;
  currentDebateId = "";
  try {
    localStorage.removeItem(ACCESS_DEBATE_KEY);
  } catch {
    // Local storage may be blocked; the server-side Google quota remains authoritative.
  }
}

function isActiveRun(runId) {
  return isDebating && runId === debateRunId;
}

function removeSpeakingState() {
  document.getElementById("avatar-a")?.classList.remove("speaking");
  document.getElementById("avatar-b")?.classList.remove("speaking");
}

function removePendingBubbles() {
  document.querySelectorAll(".msg-wrapper.is-pending").forEach((node) => node.remove());
}

function cancelActiveDebate({ removePending = true } = {}) {
  debateRunId += 1;
  if (currentAbortController) {
    currentAbortController.abort();
    currentAbortController = null;
  }
  isDebating = false;
  removeSpeakingState();
  if (removePending) removePendingBubbles();
  unlockUI();
  setNodeHidden("btn-stop", true);
  setNodeHidden("btn-resume", true);
  updateConversationActions();
}

function stopDebate() {
  const shouldOfferResume = isDebating && currentDebateId && currentStepIndex > 0;
  cancelActiveDebate();
  if (shouldOfferResume) setNodeHidden("btn-resume", false);
}

function resetApp() {
  cancelActiveDebate();
  document.body.className = "state-home";
  updateAccessUI();
  document.getElementById("arena").innerHTML = "";
  document.getElementById("subject").value = "";
  setHomeInputEnabled(Boolean(accessState?.canGenerate));

  setNodeHidden("btn-stop", true);
  setNodeHidden("btn-resume", true);
  updateConversationActions();

  setTimeout(() => document.getElementById("subject").focus(), 100);
}

function lockUI() {
  isDebating = true;
  document.getElementById("subject").disabled = true;
  setNodeHidden("btn-stop", false);
  setNodeHidden("btn-resume", true);
  document.getElementById("btn-stop").disabled = false;
  document.getElementById("btn-restart").disabled = false;
}

function unlockUI() {
  isDebating = false;
  document.getElementById("btn-restart").disabled = false;
}

function updateConversationActions() {
  const printButton = document.getElementById("btn-print");
  if (!printButton) return;
  printButton.disabled = !document.querySelector("#arena .msg-wrapper");
}

function printConversation() {
  if (!document.querySelector("#arena .msg-wrapper")) {
    const helper = document.querySelector(".helper-text");
    if (helper) helper.textContent = t("printEmpty");
    return;
  }
  window.print();
}

async function startDebate() {
  if (isDebating) return;
  if (!accessState) {
    await refreshAccessStatus();
  }
  if (!accessState?.canGenerate) {
    updateAccessUI();
    return;
  }
  globalSubject = document.getElementById("subject").value.trim();
  if (!globalSubject) return;

  currentDebateId = ensureTrialDebateId();
  document.body.className = "state-debating";
  document.getElementById("arena").innerHTML = "";
  updateConversationActions();

  currentStepIndex = 0;
  memArgA = "";
  memArgB = "";

  executeDebateLoop();
}

async function resumeDebate() {
  if (isDebating || currentStepIndex === 0 || !currentDebateId) return;
  executeDebateLoop();
}

function waitForNextStep(ms, signal) {
  return new Promise((resolve) => {
    const timeoutId = window.setTimeout(resolve, ms);
    if (!signal) return;
    signal.addEventListener("abort", () => {
      window.clearTimeout(timeoutId);
      resolve();
    }, { once: true });
  });
}

async function executeDebateLoop() {
  const runId = debateRunId + 1;
  debateRunId = runId;
  lockUI();
  const abortController = new AbortController();
  currentAbortController = abortController;

  const bots = {
    A: {
      side: "left", elementId: "avatar-a",
      sys: `Tu participes à un débat intellectuel. Ta posture est l'AFFIRMATION. Tu dois valider, défendre et démontrer la véracité, la pertinence ou le bénéfice du sujet abordé. ${t("languageDirective")}`
    },
    B: {
      side: "right", elementId: "avatar-b",
      sys: `Tu participes à un débat intellectuel. Ta posture est la CONTRADICTION. Tu dois invalider, critiquer et démontrer la fausseté, les limites ou la nocivité du sujet abordé vis-à-vis de la posture d'affirmation. ${t("languageDirective")}`
    }
  };

  const steps = [
    { bot: "A", phase: t("phaseIntro"), prompt: `Sujet de la réflexion : "${globalSubject}". Rédige une introduction qui initie ta posture d'Approprobation et de Défense. 90 mots max. L'idée est de poser les bases de la discussion.` },
    { bot: "B", phase: t("phaseIntro"), prompt: `Sujet de la réflexion : "${globalSubject}". Rédige une intro qui incarne une Rébellion intellectuelle et une critique ferme contre le postulat de base. 90 mots max. Pose les bases de ton opposition.` },
    { bot: "A", phase: t("phaseArgument"), prompt: `Développe ton meilleur argument sur "${globalSubject}" sous l'angle de la justification ou de l'utilité. 130 mots max. Tente de prouver ton point de vue de manière sourcée.`, saveTo: "memArgA" },
    { bot: "B", phase: t("phaseArgument"), prompt: `Sur "${globalSubject}", construis un argumentaire implacable pour contredire l'angle de validation et déconstruire le concept. 130 mots max. Sois incisif.`, saveTo: "memArgB" },
    { bot: "A", phase: t("phaseRebuttal"), prompt: `Ta partie adverse (Contradiction) vient de formuler cette pensée concrète : "{VAR_B}". Réponds directement à ce passage pour infirmer sa critique et ramener la pertinence sur TON analyse de "${globalSubject}". 130 mots max.` },
    { bot: "B", phase: t("phaseRebuttal"), prompt: `Ton adversaire (Affirmation) vient de formuler cette pensée concrète : "{VAR_A}". Réfute explicitement sa logique en prouvant la faiblesse de ce qu'il vient de dire. 130 mots max.` },
    { bot: "A", phase: t("phaseConclusion"), prompt: `Sujet initial: "${globalSubject}". Conclus l'échange de façon majestueuse, ancrant ton point de vue validant dans le marbre. 100 mots max.` },
    { bot: "B", phase: t("phaseConclusion"), prompt: `Sujet initial: "${globalSubject}". Referme ce débat avec une conclusion mordante illustrant pourquoi s'opposer à cette notion est l'unique forme de lucidité. 100 mots max.` }
  ];

  try {
    while (currentStepIndex < steps.length && isActiveRun(runId)) {
      const step = steps[currentStepIndex];
      const bot = bots[step.bot];
      const botAvatarNode = document.getElementById(bot.elementId);

      botAvatarNode.classList.add("speaking");
      const bubble = createBubble(bot.side, step.phase);

      let finalPrompt = step.prompt.replace("{VAR_A}", memArgA).replace("{VAR_B}", memArgB);
      finalPrompt = `${t("languageDirective")}\n\n${finalPrompt}`;

      const reply = await generateWithRetry(bot.sys, finalPrompt, abortController.signal);
      if (!isActiveRun(runId)) {
        bubble.remove();
        break;
      }

      if (step.saveTo === "memArgA") memArgA = reply;
      if (step.saveTo === "memArgB") memArgB = reply;

      finalizeBubble(bubble, reply);
      botAvatarNode.classList.remove("speaking");

      currentStepIndex++;

      if (!isActiveRun(runId)) break;
      await waitForNextStep(1500, abortController.signal);
    }

    if (isActiveRun(runId) && currentStepIndex >= steps.length) {
      unlockUI();
      setNodeHidden("btn-stop", true);
      setNodeHidden("btn-resume", true);
      if (!accessState?.canGenerate) clearTrialDebateId();
    }
  } catch (err) {
    if (err.name !== 'AbortError' && isActiveRun(runId)) {
      const bubble = createBubble("left", t("phaseSystemError"));
      finalizeBubble(bubble, err.message);
      unlockUI();
      setNodeHidden("btn-stop", true);
      setNodeHidden("btn-resume", true);
    }
  } finally {
    if (currentAbortController === abortController) currentAbortController = null;
    removeSpeakingState();
    updateConversationActions();
  }
}

async function generateWithRetry(systemInstruction, promptText, signal) {
  const response = await fetch("api/generate", {
    method: "POST", headers: accessHeaders({ "Content-Type": "application/json" }), signal: signal,
    body: JSON.stringify({
      systemInstruction,
      prompt: promptText,
      debateId: currentDebateId || ensureTrialDebateId()
    })
  });

  const data = await response.json().catch(() => null);
  if (data?.access) {
    accessState = data.access;
    updateAccessUI();
  }

  if (!response.ok) {
    if (data?.code === "trial_exhausted") clearTrialDebateId();
    throw new Error((data && data.error) ? data.error : t("cognitiveError"));
  }

  if(!data || !data.text) return t("emptyReply");
  return data.text;
}

function createBubble(side, phase) {
  const arena = document.getElementById("arena");
  const wrapper = document.createElement("div");
  wrapper.className = `msg-wrapper ${side} is-pending`;
  wrapper.innerHTML = `
    <div class="msg-phase-badge">${phase}</div>
    <div class="msg-bubble">
      <div class="render-content">
        <div class="typing"><div class="typing-line"></div></div>
      </div>
    </div>
  `;
  arena.appendChild(wrapper);
  updateConversationActions();
  wrapper.scrollIntoView({ behavior: 'smooth', block: 'end' });
  return wrapper;
}

function finalizeBubble(wrapperNode, text) {
  const renderBox = wrapperNode.querySelector(".render-content");
  wrapperNode.classList.remove("is-pending");
  let formatted = escapeHTML(text)
     .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
     .replace(/\*(.*?)\*/g, '<em>$1</em>')
     .replace(/\n\n/g, '</p><p>')
     .replace(/\n/g, '<br/>');
  renderBox.innerHTML = `<p>${formatted}</p>`;
  updateConversationActions();
  wrapperNode.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function escapeHTML(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
