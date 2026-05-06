const cards = document.querySelectorAll(".module-card, .glass-panel, .mission-card, .flow-card, .route-table article, .terminal-card, .brain-box, .brain-action, .resource-result");

if (!("IntersectionObserver" in window)) {
    cards.forEach((card) => {
        card.style.opacity = "1";
    });
} else {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.animate(
                        [
                            { opacity: 0, transform: "translateY(18px) scale(0.985)" },
                            { opacity: 1, transform: "translateY(0) scale(1)" },
                        ],
                        {
                            duration: 520,
                            easing: "cubic-bezier(.2,.8,.2,1)",
                            fill: "both",
                        }
                    );
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.16 }
    );

    cards.forEach((card) => observer.observe(card));
}

const commandData = document.getElementById("aegis-command-data");
const resourceData = document.getElementById("aegis-search-data");
const palette = document.querySelector("[data-command-palette]");
const searchInput = document.querySelector("[data-command-search]");
const commandResults = document.querySelector("[data-command-results]");
const commandClose = document.querySelector("[data-command-close]");
const commandOpeners = document.querySelectorAll(".js-command-open");
const commandPaletteEnabled = document.body?.dataset?.aegisCommandPalette !== "0";
const resourceSearchInput = document.querySelector("[data-resource-search]");
const resourceResults = document.querySelector("[data-resource-results]");

let commands = [];
let resources = [];
let activeCommandIndex = 0;

if (commandData) {
    try {
        commands = JSON.parse(commandData.textContent || "[]");
    } catch (_error) {
        commands = [];
    }
}

if (resourceData) {
    try {
        resources = JSON.parse(resourceData.textContent || "[]");
    } catch (_error) {
        resources = [];
    }
}

const escapeHtml = (value) =>
    String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

const lineforgeIconPaths = {
    "ai-signals": '<circle cx="7" cy="8" r="2"></circle><circle cx="17" cy="8" r="2"></circle><circle cx="12" cy="16" r="2.4"></circle><path d="M8.7 9.4l2.2 4.1"></path><path d="M15.3 9.4l-2.2 4.1"></path><path class="lf-accent" d="M5 16h2.4l1.4-3 2.1 5.8 2.1-5.1 1.3 2.3H19"></path>',
    odds: '<rect x="5" y="4" width="14" height="16" rx="3"></rect><path d="M8 8h8"></path><path d="M8 12h3.5"></path><path d="M13.5 12H16"></path><path class="lf-accent" d="M8 16h2.4l1.6-7 1.7 7H16"></path>',
    "market-analysis": '<rect x="4" y="5" width="16" height="14" rx="3"></rect><path d="M8 15V9"></path><path d="M12 15V7"></path><path d="M16 15v-4"></path><path class="lf-accent" d="M7 16h10"></path><circle class="lf-dot" cx="12" cy="7" r="1"></circle>',
    "line-movement": '<path d="M4 18h16"></path><path class="lf-accent" d="M5 15l4-4 3 2 5-6 2 2"></path><circle class="lf-dot" cx="9" cy="11" r="1.2"></circle><circle class="lf-dot" cx="17" cy="7" r="1.2"></circle>',
    "confidence-score": '<path d="M5 14a7 7 0 1 1 14 0"></path><path d="M7 18h10"></path><path class="lf-accent" d="M12 14l4-5"></path><path d="M8 14h.1"></path><path d="M16 14h.1"></path>',
    "edge-rating": '<path d="M12 4l7 6-7 10-7-10z"></path><path d="M8 10h8"></path><path class="lf-accent" d="M9.2 14h2l1-2.6 1.4 4 1.2-1.4h2"></path>',
    "injury-risk": '<path d="M12 4l7 3v5.2c0 4.2-2.7 6.8-7 7.8-4.3-1-7-3.6-7-7.8V7z"></path><path class="lf-accent" d="M12 8v8"></path><path class="lf-accent" d="M8 12h8"></path>',
    "risk-tier": '<path d="M12 4l7 4v4.4c0 4-2.7 6.3-7 7.6-4.3-1.3-7-3.6-7-7.6V8z"></path><path class="lf-accent" d="M9 14h6"></path><path class="lf-accent" d="M10 10h4"></path>',
    "bankroll-tracking": '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M7 10h4"></path><path d="M15 10h2"></path><path class="lf-accent" d="M7 15l2.4-2.4 2.2 1.8 4.2-4.4"></path>',
    "matchup-analysis": '<rect x="4" y="6" width="6.5" height="12" rx="2"></rect><rect x="13.5" y="6" width="6.5" height="12" rx="2"></rect><path class="lf-accent" d="M10.5 12h3"></path><path d="M7.3 10h.1"></path><path d="M16.7 14h.1"></path>',
    "confidence-ring": '<circle cx="12" cy="12" r="7"></circle><path class="lf-accent" d="M12 5a7 7 0 0 1 6.5 9.6"></path><path d="M9.5 12l1.8 1.8 3.4-4"></path>',
    "status-indicator": '<rect x="5" y="7" width="14" height="10" rx="5"></rect><circle class="lf-dot" cx="10" cy="12" r="2"></circle><path class="lf-accent" d="M13.5 12h2.5"></path>',
    "live-feed": '<circle class="lf-dot" cx="12" cy="12" r="2"></circle><path d="M7.5 8.5a5 5 0 0 0 0 7"></path><path d="M16.5 8.5a5 5 0 0 1 0 7"></path><path class="lf-accent" d="M4.8 6a9 9 0 0 0 0 12"></path><path class="lf-accent" d="M19.2 6a9 9 0 0 1 0 12"></path>',
};

const lineforgeIcon = (name = "ai-signals") => {
    const key = String(name || "ai-signals").toLowerCase();
    const markup = lineforgeIconPaths[key] || lineforgeIconPaths["ai-signals"];
    return `<svg class="lineforge-icon betedge-svg-icon" data-lineforge-icon="${escapeHtml(key)}" aria-hidden="true" focusable="false" viewBox="0 0 24 24">${markup}</svg>`;
};

const lineforgeSignalIconFor = (label = "") => {
    const text = String(label || "").toLowerCase();
    if (text.includes("injury") || text.includes("lineup") || text.includes("blocker")) return "injury-risk";
    if (text.includes("line") || text.includes("odds") || text.includes("book")) return "line-movement";
    if (text.includes("market") || text.includes("coverage")) return "market-analysis";
    if (text.includes("stake") || text.includes("bankroll")) return "bankroll-tracking";
    if (text.includes("manual") || text.includes("execution") || text.includes("location")) return "status-indicator";
    if (text.includes("edge")) return "edge-rating";
    if (text.includes("risk")) return "risk-tier";
    if (text.includes("probability") || text.includes("confidence") || text.includes("readiness")) return "confidence-score";
    return "ai-signals";
};

const lineforgeIconLabel = (label, icon) => `${lineforgeIcon(icon || lineforgeSignalIconFor(label))}${escapeHtml(label)}`;

document.addEventListener("pointermove", (event) => {
    document.documentElement.style.setProperty("--lf-mouse-x", `${event.clientX}px`);
    document.documentElement.style.setProperty("--lf-mouse-y", `${event.clientY}px`);
}, { passive: true });

const visibleCommands = () => {
    const query = (searchInput?.value || "").trim().toLowerCase();

    if (!query) {
        return commands.slice(0, 10);
    }

    return commands
        .filter((command) => {
            const haystack = `${command.label || ""} ${command.category || ""} ${command.keywords || ""}`.toLowerCase();
            return haystack.includes(query);
        })
        .slice(0, 10);
};

const resourceHaystack = (resource) =>
    `${resource.title || ""} ${resource.summary || ""} ${resource.category || ""} ${resource.type || ""} ${resource.keywords || ""} ${resource.href || ""}`.toLowerCase();

const rankResource = (resource, query) => {
    if (!query) {
        return Number(resource.weight || 0);
    }

    const title = String(resource.title || "").toLowerCase();
    const category = String(resource.category || "").toLowerCase();
    const type = String(resource.type || "").toLowerCase();
    const keywords = String(resource.keywords || "").toLowerCase();
    const summary = String(resource.summary || "").toLowerCase();
    const href = String(resource.href || "").toLowerCase();
    const haystack = resourceHaystack(resource);
    const tokens = query.split(/\s+/).filter((token) => token.length > 1);
    let score = 0;

    if (title === query) {
        score += 160;
    } else if (title.startsWith(query)) {
        score += 90;
    } else if (title.includes(query)) {
        score += 58;
    }

    if (category.includes(query)) score += 34;
    if (type.includes(query)) score += 24;
    if (keywords.includes(query)) score += 28;
    if (summary.includes(query)) score += 16;
    if (href.includes(query)) score += 12;

    tokens.forEach((token) => {
        if (title.includes(token)) {
            score += 28;
        } else if (haystack.includes(token)) {
            score += 10;
        }
    });

    return score > 0 ? score + Math.min(25, Number(resource.weight || 0)) : 0;
};

const visibleResources = () => {
    const query = (resourceSearchInput?.value || "").trim().toLowerCase();

    return resources
        .map((resource) => ({ ...resource, score: rankResource(resource, query) }))
        .filter((resource) => !query || resource.score > 0)
        .sort((a, b) => {
            if (b.score !== a.score) {
                return b.score - a.score;
            }

            return String(a.title || "").localeCompare(String(b.title || ""));
        })
        .slice(0, 9);
};

const renderResources = () => {
    if (!resourceResults) {
        return;
    }

    const matches = visibleResources();

    if (!matches.length) {
        resourceResults.innerHTML = '<div class="resource-empty">No matching resource yet</div>';
        return;
    }

    resourceResults.innerHTML = matches
        .map(
            (resource) => `
                <a class="resource-result" href="${escapeHtml(resource.href || "#")}" style="--accent: ${escapeHtml(resource.accent || "#ff2634")}">
                    <span class="resource-type">${escapeHtml(resource.type || "resource")} / ${escapeHtml(resource.category || "Aegis")}</span>
                    <strong>${escapeHtml(resource.title || "Untitled resource")}</strong>
                    <small>${escapeHtml(resource.summary || "")}</small>
                </a>
            `
        )
        .join("");
};

const renderCommands = () => {
    if (!commandResults) {
        return;
    }

    const matches = visibleCommands();
    activeCommandIndex = Math.min(activeCommandIndex, Math.max(0, matches.length - 1));

    if (!matches.length) {
        commandResults.innerHTML = '<div class="command-empty">No matching commands</div>';
        return;
    }

    commandResults.innerHTML = matches
        .map(
            (command, index) => `
                <a class="command-result ${index === activeCommandIndex ? "is-active" : ""}" href="${escapeHtml(command.href || "#")}" data-command-index="${index}">
                    <span class="command-category">${escapeHtml(command.category || "Command")}</span>
                    <strong>${escapeHtml(command.label || "Untitled command")}</strong>
                </a>
            `
        )
        .join("");
};

const openPalette = () => {
    if (!commandPaletteEnabled) {
        return;
    }

    if (!palette || !searchInput) {
        return;
    }

    palette.hidden = false;
    searchInput.value = "";
    activeCommandIndex = 0;
    renderCommands();
    window.requestAnimationFrame(() => searchInput.focus());
};

const closePalette = () => {
    if (palette) {
        palette.hidden = true;
    }
};

if (commandPaletteEnabled) {
    commandOpeners.forEach((opener) => opener.addEventListener("click", openPalette));
}
commandClose?.addEventListener("click", closePalette);
searchInput?.addEventListener("input", () => {
    activeCommandIndex = 0;
    renderCommands();
});

resourceSearchInput?.addEventListener("input", renderResources);
renderResources();

palette?.addEventListener("click", (event) => {
    if (event.target === palette) {
        closePalette();
    }
});

document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        openPalette();
        return;
    }

    if (!palette || palette.hidden) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        closePalette();
        return;
    }

    if (event.key === "ArrowDown") {
        event.preventDefault();
        activeCommandIndex = Math.min(activeCommandIndex + 1, visibleCommands().length - 1);
        renderCommands();
        return;
    }

    if (event.key === "ArrowUp") {
        event.preventDefault();
        activeCommandIndex = Math.max(activeCommandIndex - 1, 0);
        renderCommands();
        return;
    }

    if (event.key === "Enter") {
        const command = visibleCommands()[activeCommandIndex];
        if (command?.href) {
            window.location.href = command.href;
        }
    }
});

(() => {
    const config = window.AEGIS_CHATBOT;
    const messagesEl = document.getElementById("chatbotMessages");
    const threadsEl = document.getElementById("chatbotThreads");
    const composer = document.getElementById("chatbotComposer");
    const prompt = document.getElementById("chatbotPrompt");
    const statusEl = document.getElementById("chatbotComposerStatus");
    const usageEl = document.getElementById("chatbotUsage");
    const usageProgressEl = document.getElementById("chatbotUsageProgress");
    const settingsForm = document.getElementById("chatbotSettingsForm");
    const shell = document.getElementById("chatbotAppShell");
    const threadInput = document.getElementById("chatbotThreadId");
    const welcomeHero = document.getElementById("chatbotWelcomeHero");
    const quickPrompts = document.getElementById("chatbotQuickPrompts");
    const usageCard = document.getElementById("chatbotUsageCard");
    const settingsModal = document.getElementById("chatbotSettingsModal");
    const runtimeStatusTitle = document.getElementById("chatbotRuntimeStatusTitle");
    const runtimeStatusMeta = document.getElementById("chatbotRuntimeStatusMeta");
    const composerCsrf = composer?.querySelector('input[name="aegis_csrf"]');
    const settingsCsrf = settingsForm?.querySelector('input[name="aegis_csrf"]');
    const modelSelect = settingsForm?.querySelector('select[name="default_ai_model"]');
    const strategySelect = settingsForm?.querySelector('select[name="ai_response_strategy"]');
    const inlineModelButtons = document.querySelectorAll(".chatbot-inline-mode");
    const selectedModelLabel = document.getElementById("chatbotSelectedModelLabel");
    const selectedStrategyLabel = document.getElementById("chatbotSelectedStrategyLabel");
    const selectedTeamLabel = document.getElementById("chatbotSelectedTeamLabel");
    const modelSlotSummary = document.getElementById("chatbotModelSlotSummary");
    const modelSlots = document.getElementById("chatbotModelSlots");
    const modelLibraryStatus = document.getElementById("chatbotModelLibraryStatus");
    const builtinProfilesEl = document.getElementById("chatbotModelBuiltins");
    const customProfilesEl = document.getElementById("chatbotCustomProfiles");
    const modelProfileIdInput = document.getElementById("chatbotModelProfileId");
    const modelLabelInput = document.getElementById("chatbotModelLabel");
    const modelProviderInput = document.getElementById("chatbotModelProvider");
    const modelEndpointInput = document.getElementById("chatbotModelEndpoint");
    const modelNameInput = document.getElementById("chatbotModelName");
    const modelFamilyInput = document.getElementById("chatbotModelFamily");
    const modelRoleInput = document.getElementById("chatbotModelRole");
    const modelCompareInput = document.getElementById("chatbotModelCompare");
    const modelApiKeyInput = document.getElementById("chatbotModelApiKey");
    const modelClearApiKeyInput = document.getElementById("chatbotModelClearApiKey");
    const modelSaveButton = document.getElementById("chatbotModelSaveButton");
    const modelResetButton = document.getElementById("chatbotModelResetButton");
    const modelFormStatus = document.getElementById("chatbotModelFormStatus");
    const agentModeSelect = document.getElementById("chatbotAgentMode");
    const activeAgentsInput = document.getElementById("chatbotActiveAgents");
    const agentLibraryEl = document.getElementById("chatbotAgentLibrary");
    const agentRailEl = document.getElementById("chatbotAgentRail");
    const agentStatusEl = document.getElementById("chatbotAgentStatus");
    const activeTeamLabel = document.getElementById("chatbotActiveTeamLabel");
    const workspaceFileSummary = document.getElementById("chatbotWorkspaceFileSummary");
    const workspaceRootInput = document.getElementById("chatbotWorkspaceRootInput");
    const workspacePathInput = document.getElementById("chatbotWorkspacePathInput");
    const workspaceFilesInput = document.getElementById("chatbotWorkspaceFilesInput");
    const workspaceRootSelect = document.getElementById("chatbotWorkspaceRootSelect");
    const workspaceUpButton = document.getElementById("chatbotWorkspaceUpButton");
    const workspacePathLabel = document.getElementById("chatbotWorkspacePathLabel");
    const workspaceStatusEl = document.getElementById("chatbotWorkspaceStatus");
    const workspaceBreadcrumbsEl = document.getElementById("chatbotWorkspaceBreadcrumbs");
    const workspaceBrowserEl = document.getElementById("chatbotWorkspaceBrowser");
    const workspaceFilesEl = document.getElementById("chatbotWorkspaceFiles");
    const workspacePreviewEl = document.getElementById("chatbotWorkspacePreview");
    const workspaceButtonLabel = document.getElementById("chatbotWorkspaceButtonLabel");
    const workspaceButtonMeta = document.getElementById("chatbotWorkspaceButtonMeta");
    const activeWorkspaceLabel = document.getElementById("chatbotActiveWorkspaceLabel");
    const activeFileCount = document.getElementById("chatbotActiveFileCount");
    const uploadInput = document.getElementById("chatbotUploadInput");
    const uploadButton = document.getElementById("chatbotUploadButton");
    const uploadManagerButton = document.getElementById("chatbotUploadManagerButton");
    const uploadTray = document.getElementById("chatbotUploadTray");
    const uploadList = document.getElementById("chatbotUploadList");
    const imageToggle = document.getElementById("chatbotImageToggle");
    const creativeToggles = Array.from(document.querySelectorAll("[data-chatbot-creative-mode]"));

    if (!config || !messagesEl || !composer || !prompt) {
        return;
    }

    let currentThreadId = null;
    let sending = false;
    let creativeMode = "";
    const userInitial = String(config.displayName || "You").trim().charAt(0).toUpperCase() || "Y";
    config.modelLibrary = config.modelLibrary || { builtins: [], custom: [], slotUsage: { used: 0, limit: 0, remaining: 0 }, compareAvailable: false, secretStorage: { available: false, message: "" } };
    config.agentLibrary = Array.isArray(config.agentLibrary) ? config.agentLibrary : [];

    const strategyLabel = (value) => ({
        single: "Single",
        speed: "Speed",
        compare: "Compare",
        cascade: "Cascade",
        fallback: "Cascade",
        synthetic: "Synthetic",
    }[String(value || "single").toLowerCase()] || "Single");
    const basename = (filePath) => String(filePath || "").split("/").filter(Boolean).pop() || "File";
    const agentLimit = Math.max(1, Number(config.limits?.agentSlots || 2));
    const contextFileLimit = Math.max(0, Number(config.limits?.contextFiles || 0));
    const contextFileLabel = config.limits?.contextFileLabel || (config.limits?.unlimitedFileUploads ? "Unlimited" : `${contextFileLimit}`);

    const normalizeLibrary = (library = {}) => ({
        builtins: Array.isArray(library.builtins) ? library.builtins : [],
        custom: Array.isArray(library.custom) ? library.custom : [],
        slotUsage: library.slotUsage || { used: 0, limit: 0, remaining: 0 },
        compareAvailable: Boolean(library.compareAvailable),
        secretStorage: library.secretStorage || { available: false, message: "" },
    });
    const normalizeWorkspaceState = (state = {}) => ({
        allowed: state.allowed !== false,
        message: state.message || "",
        roots: Array.isArray(state.roots) ? state.roots : [],
        rootId: String(state.rootId || ""),
        path: String(state.path || ""),
        displayPath: String(state.displayPath || "/"),
        files: Array.isArray(state.files) ? state.files : [],
        listing: {
            entries: Array.isArray(state.listing?.entries) ? state.listing.entries : [],
            breadcrumbs: Array.isArray(state.listing?.breadcrumbs) ? state.listing.breadcrumbs : [],
        },
        uploads: {
            allowed: state.uploads?.allowed !== false,
            unlimited: Boolean(state.uploads?.unlimited),
            limit: state.uploads?.limit || contextFileLabel,
            maxFileSize: Number(state.uploads?.maxFileSize || config.limits?.uploadMaxBytes || 0),
            allowedExtensions: Array.isArray(state.uploads?.allowedExtensions) ? state.uploads.allowedExtensions : [],
            files: Array.isArray(state.uploads?.files) ? state.uploads.files : [],
        },
        preview: state.preview || null,
    });
    const summarizeAgentLabels = (ids = []) => {
        const labels = ids
            .map((id) => config.agentLibrary.find((agent) => String(agent.id || "") === String(id || "")))
            .filter(Boolean)
            .map((agent) => agent.label || "Agent");
        if (!labels.length) {
            return "Aegis Lead";
        }
        if (labels.length === 1) {
            return labels[0];
        }
        return `${labels.slice(0, 3).join(" + ")}${labels.length > 3 ? " +" : ""}`;
    };

    const allProfiles = () => [
        ...normalizeLibrary(config.modelLibrary).builtins,
        ...normalizeLibrary(config.modelLibrary).custom,
    ];

    const activeProfileId = () => String(modelSelect?.value || config.preferences?.default_ai_model || "aegis-chat");
    const activeStrategy = () => String(strategySelect?.value || config.preferences?.ai_response_strategy || "single");
    const findProfile = (profileId) => allProfiles().find((profile) => String(profile.id || "") === String(profileId || ""));
    const activeProfile = () => findProfile(activeProfileId()) || allProfiles()[0] || null;
    const activeAgentMode = () => String(agentModeSelect?.value || config.preferences?.chatbot_agent_mode || "auto");
    const parseActiveAgents = () => {
        const raw = activeAgentsInput?.value || config.preferences?.chatbot_active_agents || "[]";
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return Array.from(new Set(parsed.map((value) => String(value || "").trim()).filter(Boolean)));
            }
        } catch (_error) {
            return Array.from(new Set(String(raw).split(/[,\s]+/).map((value) => value.trim()).filter(Boolean)));
        }
        return [];
    };
    const activeAgentIds = () => parseActiveAgents().slice(0, agentLimit);
    const activeAgentSummary = () => activeAgentMode() === "auto"
        ? "Auto routing"
        : (activeAgentIds().length ? summarizeAgentLabels(activeAgentIds()) : "Aegis Lead");
    const workspaceFilePaths = () => (Array.isArray(config.workspace?.files) ? config.workspace.files : [])
        .map((file) => String(file.path || ""))
        .filter(Boolean);
    const uploadedFiles = () => Array.isArray(config.workspace?.uploads?.files) ? config.workspace.uploads.files : [];
    const activeWorkspaceRootId = () => String(workspaceRootInput?.value || config.workspace?.rootId || "");
    const activeWorkspaceRoot = () => {
        const roots = Array.isArray(config.workspace?.roots) ? config.workspace.roots : [];
        return roots.find((root) => String(root.id || "") === activeWorkspaceRootId()) || roots[0] || null;
    };
    config.workspace = normalizeWorkspaceState(config.workspace || {});

    const setModelFormStatus = (message, tone = "neutral") => {
        if (!modelFormStatus) {
            return;
        }

        modelFormStatus.textContent = message;
        modelFormStatus.dataset.state = tone;
    };

    const setStatus = (message) => {
        if (statusEl) {
            statusEl.textContent = message;
        }
    };
    const setWorkspaceStatus = (message, tone = "neutral") => {
        if (!workspaceStatusEl) {
            return;
        }
        workspaceStatusEl.textContent = message;
        workspaceStatusEl.dataset.state = tone;
    };

    const applyRuntime = (runtime = config.runtime || {}) => {
        config.runtime = runtime;
        const profile = activeProfile();
        const runtimeModel = runtime.model || profile?.label || "Aegis AI";
        const runtimeStrategy = strategyLabel(runtime.strategy || activeStrategy());
        const teamSummary = runtime.teamSummary || activeAgentSummary();
        if (runtimeStatusTitle) {
            runtimeStatusTitle.textContent = runtime.ok ? "Model connected" : "Runtime fallback";
        }
        if (runtimeStatusMeta) {
            const traceParts = [];
            if (runtime.taskRole) {
                traceParts.push(`Task lane: ${runtime.taskRole}`);
            }
            if (runtime.fallbackUsed) {
                const attemptCount = Array.isArray(runtime.fallbackAttempts) ? runtime.fallbackAttempts.length : 0;
                traceParts.push(`Fallback used after ${attemptCount} lane${attemptCount === 1 ? "" : "s"}`);
            }
            const trace = traceParts.length ? ` ${traceParts.join(". ")}.` : "";
            runtimeStatusMeta.textContent = runtime.ok
                ? `${teamSummary} is using ${runtimeModel} in ${runtimeStrategy} mode.${trace}`
                : (runtime.message || "Configured runtime unavailable. Local fallback replies will be used.");
        }
    };

    const syncTokens = () => {
        if (composerCsrf) {
            composerCsrf.value = config.csrfToken || "";
        }
        if (settingsCsrf) {
            settingsCsrf.value = config.preferencesCsrfToken || "";
        }
    };

    const updateStrategyOptions = () => {
        if (!strategySelect) {
            return;
        }

        const compareOption = strategySelect.querySelector('option[value="compare"]');
        if (compareOption) {
            const compareAllowed = Boolean(normalizeLibrary(config.modelLibrary).compareAvailable);
            compareOption.disabled = !compareAllowed && strategySelect.value !== "compare";
            compareOption.textContent = compareAllowed ? "Consensus Compare" : "Consensus Compare (Upgrade)";
        }
    };

    const updateModelChrome = () => {
        const profile = activeProfile();
        const profileLabel = profile?.label || "Aegis Chat";
        const strategy = strategyLabel(activeStrategy());

        inlineModelButtons.forEach((button) => {
            button.textContent = profileLabel;
        });

        if (selectedModelLabel) {
            selectedModelLabel.textContent = profileLabel;
        }
        if (selectedStrategyLabel) {
            selectedStrategyLabel.textContent = strategy;
        }
        if (statusEl) {
            statusEl.textContent = `${activeAgentSummary()} is using ${profileLabel} in ${strategy} mode.`;
        }
    };

    const syncWorkspaceInputs = () => {
        if (workspaceRootInput) {
            workspaceRootInput.value = config.workspace.rootId || "";
        }
        if (workspacePathInput) {
            workspacePathInput.value = config.workspace.path || "";
        }
        if (workspaceFilesInput) {
            workspaceFilesInput.value = JSON.stringify(workspaceFilePaths());
        }
        if (workspaceRootSelect) {
            workspaceRootSelect.value = config.workspace.rootId || "";
        }

        config.preferences = config.preferences || {};
        config.preferences.chatbot_workspace_root = workspaceRootInput?.value || "";
        config.preferences.chatbot_workspace_path = workspacePathInput?.value || "";
        config.preferences.chatbot_workspace_files = workspaceFilesInput?.value || "[]";
    };

    const updateAgentChrome = () => {
        const summary = activeAgentSummary();
        if (selectedTeamLabel) {
            selectedTeamLabel.textContent = summary;
        }
        if (activeTeamLabel) {
            activeTeamLabel.textContent = summary;
        }
        if (agentStatusEl) {
            agentStatusEl.textContent = activeAgentMode() === "auto"
                ? `Auto mode can route to the best specialist for each request. Up to ${agentLimit} lane(s) on this plan.`
                : `${Math.max(0, agentLimit - activeAgentIds().length)} lane(s) remaining on this plan.`;
        }
    };

    const updateWorkspaceChrome = () => {
        syncWorkspaceInputs();
        const root = activeWorkspaceRoot();
        const rootLabel = root?.label || "Workspace";
        const display = config.workspace.allowed
            ? `${rootLabel} ${config.workspace.displayPath || "/"}`
            : (uploadedFiles().length ? "Uploads enabled / server workspace locked" : "Upload files / server workspace locked");
        const contextCount = workspaceFilePaths().length + uploadedFiles().length;

        if (workspaceButtonLabel) {
            workspaceButtonLabel.textContent = rootLabel;
        }
        if (workspaceButtonMeta) {
            workspaceButtonMeta.textContent = config.workspace.allowed ? (config.workspace.displayPath || "/") : "Uploads";
        }
        if (activeWorkspaceLabel) {
            activeWorkspaceLabel.textContent = display;
        }
        if (activeFileCount) {
            activeFileCount.textContent = `${contextCount}`;
        }
        if (workspaceFileSummary) {
            workspaceFileSummary.textContent = `${contextCount} file${contextCount === 1 ? "" : "s"}`;
        }
    };

    const renderUploadSurfaces = () => {
        const uploads = uploadedFiles();
        const uploadLimit = config.workspace?.uploads?.limit || contextFileLabel;
        const uploadCopy = uploads.length
            ? uploads
                .slice(0, 8)
                .map(
                    (file) => `
                        <button type="button" class="chatbot-upload-chip" data-chatbot-delete-upload="${escapeHtml(file.id || "")}">
                            <span>${escapeHtml(file.name || "Uploaded file")}</span>
                            <strong>&times;</strong>
                        </button>
                    `
                )
                .join("")
            : '<div class="chatbot-workspace-empty">Upload project files to give Aegis real context. Pro supports unlimited uploaded file count; model context is trimmed safely.</div>';

        if (uploadTray) {
            uploadTray.innerHTML = uploads.length
                ? `<span class="chatbot-upload-summary">${escapeHtml(`${uploads.length} uploaded / ${uploadLimit}`)}</span>${uploadCopy}`
                : `<span class="chatbot-upload-summary">No uploaded files yet / ${escapeHtml(uploadLimit)} allowed</span>`;
        }

        if (uploadList) {
            uploadList.innerHTML = uploadCopy;
        }
    };

    const renderWorkspaceBrowser = () => {
        if (workspacePathLabel) {
            workspacePathLabel.textContent = config.workspace.displayPath || "/";
        }
        if (workspaceBreadcrumbsEl) {
            workspaceBreadcrumbsEl.innerHTML = config.workspace.listing.breadcrumbs.length
                ? config.workspace.listing.breadcrumbs
                    .map((crumb) => `<button type="button" data-chatbot-workspace-path="${escapeHtml(crumb.path || "")}">${escapeHtml(crumb.label || "Workspace")}</button>`)
                    .join("")
                : '<span class="chatbot-workspace-empty">No workspace breadcrumbs yet.</span>';
        }
        if (workspaceBrowserEl) {
            workspaceBrowserEl.innerHTML = config.workspace.listing.entries.length
                ? config.workspace.listing.entries
                    .map((entry) => {
                        const selected = workspaceFilePaths().includes(String(entry.path || ""));
                        const typeLabel = entry.type === "dir" ? "Folder" : String(entry.extension || "file").toUpperCase();
                        return `
                            <button
                                type="button"
                                class="chatbot-workspace-entry type-${escapeHtml(entry.type || "file")}${selected ? " is-selected" : ""}"
                                data-chatbot-workspace-entry="${escapeHtml(entry.path || "")}"
                                data-entry-type="${escapeHtml(entry.type || "file")}"
                            >
                                <strong>${escapeHtml(entry.name || "Item")}</strong>
                                <span>${escapeHtml(typeLabel)}</span>
                            </button>
                        `;
                    })
                    .join("")
                : '<div class="chatbot-workspace-empty">No matching files were found in this folder.</div>';
        }
        if (workspaceFilesEl) {
            workspaceFilesEl.innerHTML = workspaceFilePaths().length
                ? config.workspace.files
                    .map(
                        (file) => `
                            <button type="button" class="chatbot-workspace-chip" data-chatbot-workspace-remove="${escapeHtml(file.path || "")}">
                                <span>${escapeHtml(file.name || basename(file.path || ""))}</span>
                                <strong>&times;</strong>
                            </button>
                        `
                    )
                    .join("")
                : '<div class="chatbot-workspace-empty">Attach files to give the chatbot code or document context.</div>';
        }
        if (workspacePreviewEl) {
            const preview = config.workspace.preview;
            workspacePreviewEl.textContent = preview?.content
                ? `${preview.path || preview.name || "Preview"}\n\n${preview.content}${preview.truncated ? "\n\n[Preview truncated]" : ""}`
                : "Select a file to preview it here.";
        }
        if (workspaceUpButton) {
            workspaceUpButton.disabled = !config.workspace.allowed || !config.workspace.path;
        }
        setWorkspaceStatus(
            config.workspace.message || (config.workspace.allowed ? "Choose files to attach as context." : "Workspace browsing unavailable."),
            config.workspace.allowed ? "neutral" : "warn"
        );
        renderUploadSurfaces();
    };

    const renderAgentCard = (agent, compact = false) => {
        const selected = activeAgentIds().includes(String(agent.id || ""));
        const className = compact ? "chatbot-reference-agent" : "chatbot-agent-card";
        const actionAttr = compact ? 'data-chatbot-open-settings="1"' : `data-chatbot-agent="${escapeHtml(agent.id || "")}"`;
        return `
            <button type="button" class="${className} accent-${escapeHtml(agent.accent || "blue")}${selected ? " is-active" : ""}" ${actionAttr}>
                <i></i>
                <div>
                    <strong>${escapeHtml(agent.label || "Agent")}</strong>
                    <span>${escapeHtml(agent.summary || "")}</span>
                </div>
            </button>
        `;
    };

    const renderAgentLibrary = () => {
        if (agentLibraryEl) {
            agentLibraryEl.innerHTML = config.agentLibrary.map((agent) => renderAgentCard(agent, false)).join("");
        }
        if (agentRailEl) {
            agentRailEl.innerHTML = config.agentLibrary.map((agent) => renderAgentCard(agent, true)).join("");
        }
        updateAgentChrome();
    };

    const updateExperienceChrome = () => {
        updateModelChrome();
        updateAgentChrome();
        updateWorkspaceChrome();
    };

    const setUsage = (used = 0, limit = 0) => {
        if (usageEl) {
            usageEl.textContent = `${used} / ${limit}`;
        }
        if (usageProgressEl) {
            const safeLimit = Math.max(1, Number(limit || 0));
            const percentage = Math.max(0, Math.min(100, Math.round((Number(used || 0) / safeLimit) * 100)));
            usageProgressEl.style.width = `${percentage}%`;
        }
    };

    const renderModelSelectOptions = () => {
        if (!modelSelect) {
            return;
        }

        const previous = activeProfileId();
        modelSelect.innerHTML = allProfiles()
            .map(
                (profile) => `<option value="${escapeHtml(profile.id || "")}">${escapeHtml(profile.label || "Model")}</option>`
            )
            .join("");

        const selected = findProfile(previous) ? previous : (allProfiles()[0]?.id || "aegis-chat");
        modelSelect.value = selected;
        config.preferences = config.preferences || {};
        config.preferences.default_ai_model = selected;
    };

    const renderModelCard = (profile, currentId, custom = false) => {
        const activeClass = String(profile.id || "") === String(currentId || "") ? " is-active" : "";
        const family = String(profile.family || "custom").toUpperCase();
        const role = String(profile.role || "balanced");
        const roleLabel = `${role.charAt(0).toUpperCase()}${role.slice(1)}`;
        const tier = String(profile.tier || (custom ? "Custom" : "B")).toUpperCase();
        const tierLabel = tier === "CUSTOM" ? "Custom" : `${tier}-Tier`;
        const fallbackGroup = String(profile.fallback_group || role || "general");
        const fallbackLabel = `${fallbackGroup.charAt(0).toUpperCase()}${fallbackGroup.slice(1)}`;
        const providerApi = String(profile.provider_api || "ollama");
        const hardwareNote = String(profile.hardware_note || "");
        const subtitle = custom
            ? `${tierLabel} / ${providerApi.toUpperCase()} / ${roleLabel}`
            : `${tierLabel} / ${family} / ${roleLabel}`;
        const body = custom
            ? (profile.model_name || "Custom profile")
            : (profile.description || "");
        const meta = custom
            ? (profile.endpoint || "")
            : `${providerApi} - ${profile.model_name || ""}`;
        const customButtons = custom
            ? `
                <button type="button" data-chatbot-use-profile="${escapeHtml(profile.id || "")}">Use</button>
                <button type="button" data-chatbot-edit-profile="${escapeHtml(profile.id || "")}">Edit</button>
                <button type="button" data-chatbot-test-profile="${escapeHtml(profile.id || "")}">Test</button>
                <button type="button" data-chatbot-delete-profile="${escapeHtml(profile.id || "")}">Delete</button>
            `
            : `
                <button type="button" data-chatbot-use-profile="${escapeHtml(profile.id || "")}">Use</button>
                <button type="button" data-chatbot-test-profile="${escapeHtml(profile.id || "")}">Test</button>
            `;

        return `
            <article class="chatbot-model-card${custom ? " chatbot-model-card-custom" : ""}${activeClass}" data-tier="${escapeHtml(tier)}">
                <div class="chatbot-model-card-head">
                    <strong>${escapeHtml(profile.label || "Model")}</strong>
                    <span>${escapeHtml(subtitle)}</span>
                </div>
                <p>${escapeHtml(body)}</p>
                <div class="chatbot-model-tags">
                    <span>${escapeHtml(tierLabel)}</span>
                    <span>${escapeHtml(custom ? `Role: ${roleLabel}` : `Fallback: ${fallbackLabel}`)}</span>
                </div>
                <small>${escapeHtml(meta)}</small>
                ${hardwareNote ? `<small class="chatbot-model-hardware">${escapeHtml(hardwareNote)}</small>` : ""}
                <div class="chatbot-model-card-actions">
                    ${customButtons}
                </div>
            </article>
        `;
    };

    const renderModelLibrary = () => {
        config.modelLibrary = normalizeLibrary(config.modelLibrary);
        renderModelSelectOptions();
        updateStrategyOptions();

        const currentId = activeProfileId();

        if (builtinProfilesEl) {
            builtinProfilesEl.innerHTML = config.modelLibrary.builtins
                .map((profile) => renderModelCard(profile, currentId, false))
                .join("");
        }

        if (customProfilesEl) {
            customProfilesEl.innerHTML = config.modelLibrary.custom.length
                ? config.modelLibrary.custom.map((profile) => renderModelCard(profile, currentId, true)).join("")
                : '<div class="chatbot-model-empty">No custom profiles yet. Add an Ollama or OpenAI-compatible endpoint below.</div>';
        }

        if (modelSlots) {
            modelSlots.textContent = `${config.modelLibrary.slotUsage.used || 0} / ${config.modelLibrary.slotUsage.limit || 0} slots used`;
        }
        if (modelSlotSummary) {
            modelSlotSummary.textContent = `${config.modelLibrary.slotUsage.used || 0} / ${config.modelLibrary.slotUsage.limit || 0}`;
        }
        if (modelLibraryStatus) {
            modelLibraryStatus.textContent = config.modelLibrary.secretStorage?.message || "";
        }

        updateModelChrome();
    };

    const resetModelForm = (profile = null) => {
        if (modelProfileIdInput) modelProfileIdInput.value = profile?.id || "";
        if (modelLabelInput) modelLabelInput.value = profile?.label || "";
        if (modelProviderInput) modelProviderInput.value = profile?.provider_api || "ollama";
        if (modelEndpointInput) modelEndpointInput.value = profile?.endpoint || (modelProviderInput?.value === "openai" ? "http://127.0.0.1:1234" : "http://127.0.0.1:11434");
        if (modelNameInput) modelNameInput.value = profile?.model_name || "";
        if (modelFamilyInput) modelFamilyInput.value = profile?.family || "";
        if (modelRoleInput) modelRoleInput.value = profile?.role || "balanced";
        if (modelCompareInput) modelCompareInput.checked = Boolean(profile?.compare_enabled);
        if (modelApiKeyInput) modelApiKeyInput.value = "";
        if (modelClearApiKeyInput) modelClearApiKeyInput.checked = false;
        setModelFormStatus(profile ? `Editing ${profile.label || "profile"}. Save to update it.` : "Custom profiles let customers route chat through their own self-hosted or compatible runtimes.");
    };

    const buildModelPayload = () => ({
        profile_id: modelProfileIdInput?.value?.trim() || "",
        label: modelLabelInput?.value?.trim() || "",
        provider_api: modelProviderInput?.value || "ollama",
        endpoint: modelEndpointInput?.value?.trim() || "",
        model_name: modelNameInput?.value?.trim() || "",
        family: modelFamilyInput?.value?.trim() || "",
        role: modelRoleInput?.value || "balanced",
        compare_enabled: Boolean(modelCompareInput?.checked),
        api_key: modelApiKeyInput?.value || "",
        clear_api_key: Boolean(modelClearApiKeyInput?.checked),
    });

    const updateFromPreferencesResponse = (data = {}) => {
        config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
        if (data.state?.preferences) {
            config.preferences = data.state.preferences;
        }
        syncTokens();
        applyUiPreferences();
        if (activeAgentsInput && typeof config.preferences?.chatbot_active_agents === "string") {
            activeAgentsInput.value = config.preferences.chatbot_active_agents;
        }
        if (agentModeSelect && config.preferences?.chatbot_agent_mode) {
            agentModeSelect.value = config.preferences.chatbot_agent_mode;
        }
        renderModelLibrary();
        renderAgentLibrary();
        renderWorkspaceBrowser();
        updateExperienceChrome();
        applyRuntime(config.runtime || {});
    };

    const savePreferences = async () => {
        if (!settingsForm) {
            return { ok: false, message: "Settings form unavailable." };
        }

        const formData = new FormData();
        Object.entries(config.preferences || {}).forEach(([key, value]) => {
            formData.set(key, typeof value === "boolean" ? (value ? "1" : "0") : value ?? "");
        });

        new FormData(settingsForm).forEach((value, key) => {
            if (key) {
                formData.set(key, value);
            }
        });

        settingsForm.querySelectorAll('input[type="checkbox"][name]').forEach((input) => {
            if (!input.checked) {
                formData.set(input.name, "0");
            }
        });

        formData.set("aegis_csrf", config.preferencesCsrfToken || "");

        const response = await fetch(config.preferencesEndpoint, {
            method: "POST",
            headers: { "X-AEGIS-CSRF": config.preferencesCsrfToken || "" },
            credentials: "same-origin",
            body: formData,
        });

        const data = await response.json();
        updateFromPreferencesResponse(data);
        return data;
    };

    const applyModelLibraryResponse = (data = {}) => {
        config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
        config.modelLibrary = normalizeLibrary(data.library || config.modelLibrary);
        syncTokens();
        renderModelLibrary();
    };

    const loadModelLibrary = async () => {
        const response = await fetch(config.modelsEndpoint, { credentials: "same-origin" });
        const data = await response.json();
        if (!data.ok) {
            setStatus(data.message || "Model library could not load.");
            return;
        }

        applyModelLibraryResponse(data);
    };

    const useProfile = async (profileId) => {
        if (!modelSelect || !findProfile(profileId)) {
            return;
        }

        modelSelect.value = profileId;
        config.preferences = config.preferences || {};
        config.preferences.default_ai_model = profileId;
        renderModelLibrary();

        try {
            const data = await savePreferences();
            setStatus(data.ok ? "Chat routing updated." : data.message || "Settings could not be saved.");
        } catch (_error) {
            setStatus("Settings could not reach the server.");
        }
    };

    const editProfile = (profileId) => {
        const profile = findProfile(profileId);
        if (!profile) {
            return;
        }

        resetModelForm(profile);
        openSettings();
        modelLabelInput?.focus();
    };

    const testProfile = async (profileId = "") => {
        const payload = profileId
            ? { action: "test_profile", profile_id: profileId }
            : { action: "test_profile", ...buildModelPayload() };

        setModelFormStatus("Testing runtime...", "pending");

        try {
            const response = await fetch(config.modelsEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    ...payload,
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            applyModelLibraryResponse(data);

            const status = data.status || {};
            setModelFormStatus(data.message || "Profile test completed.", data.ok ? "ok" : "fail");
            setStatus(data.message || "Profile test completed.");

            if (status.profile_label || status.model) {
                applyRuntime({
                    ok: Boolean(status.ok),
                    model: status.profile_label || status.model,
                    message: status.message || "",
                    profileId: status.profile_id || profileId,
                    strategy: activeStrategy(),
                    laneCount: status.ok ? 1 : 0,
                });
            }
        } catch (_error) {
            setModelFormStatus("Model test could not reach the server.", "fail");
        }
    };

    const saveModelProfile = async () => {
        const payload = buildModelPayload();
        if (!payload.label || !payload.model_name || !payload.endpoint) {
            setModelFormStatus("Label, endpoint, and model name are required.", "fail");
            return;
        }

        setModelFormStatus("Saving profile...", "pending");

        try {
            const response = await fetch(config.modelsEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "save_profile",
                    ...payload,
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            applyModelLibraryResponse(data);

            if (!data.ok) {
                setModelFormStatus(data.message || "Profile could not be saved.", "fail");
                return;
            }

            resetModelForm();
            setModelFormStatus(data.message || "Profile saved.", "ok");
            setStatus(data.message || "Profile saved.");
        } catch (_error) {
            setModelFormStatus("Profile save could not reach the server.", "fail");
        }
    };

    const deleteProfile = async (profileId) => {
        if (!profileId || !window.confirm("Delete this custom model profile?")) {
            return;
        }

        try {
            const response = await fetch(config.modelsEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "delete_profile",
                    profile_id: profileId,
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            applyModelLibraryResponse(data);

            if (!data.ok) {
                setModelFormStatus(data.message || "Profile could not be deleted.", "fail");
                return;
            }

            if (!findProfile(activeProfileId())) {
                const fallbackId = allProfiles()[0]?.id || "aegis-chat";
                if (modelSelect) {
                    modelSelect.value = fallbackId;
                }
                config.preferences.default_ai_model = fallbackId;
                try {
                    await savePreferences();
                } catch (_error) {
                    setStatus("Settings could not reach the server.");
                }
            }

            resetModelForm();
            setModelFormStatus(data.message || "Profile deleted.", "ok");
            setStatus(data.message || "Profile deleted.");
        } catch (_error) {
            setModelFormStatus("Profile deletion could not reach the server.", "fail");
        }
    };

    const applyUiPreferences = () => {
        const prefs = config.preferences || {};
        const sidebarCollapsed = Boolean(prefs.chatbot_sidebar_collapsed);
        const showWelcome = prefs.chatbot_show_welcome !== false && prefs.chatbot_show_welcome !== "0";
        const showQuickPrompts = prefs.chatbot_show_quick_prompts !== false && prefs.chatbot_show_quick_prompts !== "0";
        const showUsageCard = prefs.chatbot_show_usage_card !== false && prefs.chatbot_show_usage_card !== "0";

        shell?.classList.toggle("is-sidebar-collapsed", sidebarCollapsed);
        welcomeHero?.classList.toggle("is-hidden", !showWelcome || shell?.classList.contains("has-conversation"));
        quickPrompts?.classList.toggle("is-hidden", !showQuickPrompts);
        usageCard?.classList.toggle("is-hidden", !showUsageCard);
    };

    const setConversationState = (hasMessages) => {
        shell?.classList.toggle("has-conversation", hasMessages);
        if (welcomeHero) {
            const showWelcome = (config.preferences?.chatbot_show_welcome ?? true) !== false && (config.preferences?.chatbot_show_welcome ?? "1") !== "0";
            welcomeHero.classList.toggle("is-hidden", !showWelcome || hasMessages);
        }
        window.requestAnimationFrame(() => autosizePrompt());
    };

    const openSettings = () => {
        if (settingsModal) {
            settingsModal.hidden = false;
            document.body.classList.add("chatbot-modal-open");
        }
    };

    const closeSettings = () => {
        if (settingsModal) {
            settingsModal.hidden = true;
            document.body.classList.remove("chatbot-modal-open");
        }
    };

    const messageNode = (role, content, model = "Aegis Chat") => {
        const article = document.createElement("article");
        article.className = `chatbot-message ${role === "user" ? "user" : "assistant"}`;

        const avatar = document.createElement("div");
        avatar.className = "chatbot-avatar";
        avatar.textContent = role === "user" ? userInitial : "A";

        const body = document.createElement("div");
        const label = document.createElement("span");
        label.textContent = role === "user" ? "You" : model || "Aegis Chat";

        const paragraph = document.createElement("p");
        paragraph.textContent = content;

        body.append(label, paragraph);
        article.append(avatar, body);
        return article;
    };

    const renderMessages = (messages = []) => {
        messagesEl.innerHTML = "";
        setConversationState(messages.length > 0);

        if (!messages.length) {
            return;
        }

        messages.forEach((message) => {
            messagesEl.append(messageNode(message.role, message.content, message.model || "Aegis Chat"));
        });
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const renderThreads = (threads = []) => {
        if (!threadsEl) {
            return;
        }

        if (!threads.length) {
            threadsEl.innerHTML = '<div class="chatbot-thread-empty">Recent chats appear here when memory is enabled.</div>';
            return;
        }

        threadsEl.innerHTML = threads
            .map(
                (thread) => `
                    <a class="${Number(thread.id) === Number(currentThreadId) ? "is-active" : ""}" href="#chat" data-thread-id="${escapeHtml(thread.id)}">
                        <strong>${escapeHtml(thread.title || "Aegis Chat")}</strong>
                        <span>${escapeHtml(thread.updated_at || "Saved")}</span>
                    </a>
                `
            )
            .join("");
    };

    const loadChat = async (threadId = null) => {
        const url = new URL(config.endpoint, window.location.href);
        if (threadId) {
            url.searchParams.set("thread_id", threadId);
        }

        const response = await fetch(url, { credentials: "same-origin" });
        const data = await response.json();

        if (!data.ok) {
            setStatus(data.message || "Aegis Chat could not load.");
            return;
        }

        currentThreadId = data.thread_id || null;
        if (threadInput) {
            threadInput.value = currentThreadId || "";
        }
        config.csrfToken = data.csrfToken || config.csrfToken;
        config.preferencesCsrfToken = data.preferencesCsrfToken || config.preferencesCsrfToken;
        syncTokens();
        renderThreads(data.threads || []);
        renderMessages(data.messages || []);
        applyRuntime(data.runtime || config.runtime || {});
        updateExperienceChrome();

        if (data.usage) {
            setUsage(data.usage.used, data.usage.limit);
        }
    };

    const autosizePrompt = () => {
        const compactConversation = shell?.classList.contains("has-conversation");
        const minHeight = window.matchMedia("(max-width: 760px)").matches ? 96 : (compactConversation ? 94 : 148);
        const maxHeight = window.matchMedia("(max-height: 760px)").matches ? 170 : (compactConversation ? 220 : 320);
        prompt.style.height = "0px";
        prompt.style.height = `${Math.min(maxHeight, Math.max(minHeight, prompt.scrollHeight))}px`;
    };

    const sendMessage = async (message, retryOnCsrf = true) => {
        if (sending) {
            return;
        }

        sending = true;
        messagesEl.append(messageNode("user", message));
        const pending = messageNode("assistant", "Thinking through that now...", `${activeAgentSummary()} · ${activeProfile()?.label || "Aegis Chat"}`);
        messagesEl.append(pending);
        setConversationState(true);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        setStatus("Aegis Chat is responding...");

        try {
            const response = await fetch(config.endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken,
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    message,
                    thread_id: currentThreadId,
                    aegis_csrf: config.csrfToken,
                }),
            });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;
            syncTokens();
            applyRuntime(data.runtime || config.runtime || {});

            if (!data.ok) {
                if (response.status === 419 && retryOnCsrf && data.csrfToken) {
                    pending.remove();
                    messagesEl.lastElementChild?.remove();
                    return sendMessage(message, false);
                }
                pending.querySelector("p").textContent = data.message || "Aegis Chat could not respond.";
                setStatus("Response failed.");
                sending = false;
                return;
            }

            currentThreadId = data.thread_id || currentThreadId;
            if (threadInput) {
                threadInput.value = currentThreadId || "";
            }
            pending.querySelector("span").textContent = data.message?.model || "Aegis Chat";
            pending.querySelector("p").textContent = data.message?.content || "";
            if (data.usage) {
                setUsage(data.usage.used, data.usage.limit);
            }
            setStatus("Saved to Aegis Chat.");
            loadChat(currentThreadId).catch(() => {});
        } catch (_error) {
            pending.querySelector("p").textContent = "Aegis Chat could not reach the server.";
            setStatus("Network error.");
        } finally {
            sending = false;
        }
    };

    const setActiveAgents = (ids = []) => {
        let next = Array.from(new Set(ids.map((value) => String(value || "").trim()).filter(Boolean))).slice(0, agentLimit);
        if (activeAgentMode() === "solo" && next.length > 1) {
            next = [next[0]];
        }
        if (activeAgentsInput) {
            activeAgentsInput.value = JSON.stringify(next);
        }
        config.preferences = config.preferences || {};
        config.preferences.chatbot_active_agents = activeAgentsInput?.value || "[]";
        renderAgentLibrary();
        updateExperienceChrome();
    };

    const applyWorkspaceState = (state = {}) => {
        config.workspace = normalizeWorkspaceState(state);
        syncWorkspaceInputs();
        renderWorkspaceBrowser();
        updateWorkspaceChrome();
    };

    const persistWorkspacePreferences = async (successMessage = "Workspace updated.") => {
        try {
            const data = await savePreferences();
            setStatus(data.ok ? successMessage : data.message || "Settings could not be saved.");
        } catch (_error) {
            setStatus("Settings could not reach the server.");
        }
    };

    const browseWorkspace = async (path = config.workspace.path || "", rootId = activeWorkspaceRootId()) => {
        if (!config.workspaceEndpoint || !config.workspace.allowed) {
            return;
        }

        setWorkspaceStatus("Loading workspace...", "pending");

        try {
            const response = await fetch(config.workspaceEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "browse",
                    root_id: rootId,
                    path,
                    files: workspaceFilePaths(),
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
            syncTokens();

            if (data.state) {
                applyWorkspaceState(data.state);
            }

            setWorkspaceStatus(data.message || "Workspace browser updated.", data.ok ? "ok" : "fail");
        } catch (_error) {
            setWorkspaceStatus("Workspace browser could not reach the server.", "fail");
        }
    };

    const previewWorkspaceFile = async (filePath) => {
        if (!config.workspaceEndpoint || !config.workspace.allowed || !filePath) {
            return null;
        }

        setWorkspaceStatus("Loading preview...", "pending");

        try {
            const response = await fetch(config.workspaceEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "read",
                    root_id: activeWorkspaceRootId(),
                    path: config.workspace.path || "",
                    file: filePath,
                    files: workspaceFilePaths(),
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
            syncTokens();

            if (!data.ok) {
                setWorkspaceStatus(data.message || "That file could not be opened.", "fail");
                return null;
            }

            if (data.state) {
                applyWorkspaceState(data.state);
            }

            setWorkspaceStatus(data.message || "Workspace file preview loaded.", "ok");
            return data.state?.preview || null;
        } catch (_error) {
            setWorkspaceStatus("Workspace preview could not reach the server.", "fail");
            return null;
        }
    };

    const attachWorkspaceFile = async (filePath) => {
        if (!filePath || !config.workspace.allowed) {
            return;
        }

        if (contextFileLimit <= 0) {
            setWorkspaceStatus("This plan does not include workspace file attachments.", "fail");
            return;
        }

        if (workspaceFilePaths().includes(filePath)) {
            await previewWorkspaceFile(filePath);
            return;
        }

        if (workspaceFilePaths().length >= contextFileLimit) {
            setWorkspaceStatus(`This plan allows up to ${contextFileLimit} attached file(s).`, "fail");
            return;
        }

        const preview = await previewWorkspaceFile(filePath);
        if (!preview) {
            return;
        }

        config.workspace.files = [
            ...config.workspace.files,
            {
                path: preview.path || filePath,
                name: preview.name || basename(filePath),
                size: preview.size || 0,
                extension: preview.extension || "",
                truncated: Boolean(preview.truncated),
            },
        ];
        config.workspace.preview = preview;
        syncWorkspaceInputs();
        renderWorkspaceBrowser();
        updateWorkspaceChrome();
        await persistWorkspacePreferences("Workspace context saved.");
    };

    const removeWorkspaceFile = async (filePath) => {
        config.workspace.files = config.workspace.files.filter((file) => String(file.path || "") !== String(filePath || ""));
        if (String(config.workspace.preview?.path || "") === String(filePath || "")) {
            config.workspace.preview = null;
        }
        syncWorkspaceInputs();
        renderWorkspaceBrowser();
        updateWorkspaceChrome();
        setWorkspaceStatus("Context file removed.", "ok");
        await persistWorkspacePreferences("Workspace context saved.");
    };

    const changeWorkspaceRoot = async (rootId) => {
        config.workspace.rootId = rootId;
        config.workspace.path = "";
        config.workspace.displayPath = "/";
        config.workspace.files = [];
        config.workspace.preview = null;
        syncWorkspaceInputs();
        renderWorkspaceBrowser();
        updateWorkspaceChrome();
        await browseWorkspace("", rootId);
        await persistWorkspacePreferences("Workspace root saved.");
    };

    const goUpWorkspace = async () => {
        const parts = String(config.workspace.path || "").split("/").filter(Boolean);
        parts.pop();
        const nextPath = parts.join("/");
        await browseWorkspace(nextPath, activeWorkspaceRootId());
        await persistWorkspacePreferences("Workspace folder saved.");
    };

    const loadWorkspace = async () => {
        if (!config.workspaceEndpoint) {
            renderWorkspaceBrowser();
            updateWorkspaceChrome();
            return;
        }

        try {
            const response = await fetch(config.workspaceEndpoint, { credentials: "same-origin" });
            const data = await response.json();
            config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
            syncTokens();
            if (data.state) {
                applyWorkspaceState(data.state);
            }
        } catch (_error) {
            setWorkspaceStatus("Workspace browser could not load.", "fail");
        }
    };

    const uploadFiles = async (files) => {
        if (!config.workspaceEndpoint || !files?.length) {
            return;
        }

        const formData = new FormData();
        formData.set("action", "upload");
        formData.set("aegis_csrf", config.preferencesCsrfToken || "");
        Array.from(files).forEach((file) => formData.append("files[]", file));

        setWorkspaceStatus("Uploading context files...", "pending");
        setStatus("Uploading files for Aegis context...");

        try {
            const response = await fetch(config.workspaceEndpoint, {
                method: "POST",
                headers: { "X-AEGIS-CSRF": config.preferencesCsrfToken || "" },
                credentials: "same-origin",
                body: formData,
            });
            const data = await response.json();
            config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
            syncTokens();

            if (data.state) {
                applyWorkspaceState(data.state);
            }

            setWorkspaceStatus(data.message || "Upload finished.", data.ok ? "ok" : "fail");
            setStatus(data.ok ? "Uploaded files are now available as chat context." : (data.message || "Upload failed."));
        } catch (_error) {
            setWorkspaceStatus("File upload could not reach the server.", "fail");
            setStatus("File upload failed.");
        } finally {
            if (uploadInput) {
                uploadInput.value = "";
            }
        }
    };

    const deleteUploadedFile = async (uploadId) => {
        if (!config.workspaceEndpoint || !uploadId) {
            return;
        }

        setWorkspaceStatus("Removing uploaded file...", "pending");

        try {
            const response = await fetch(config.workspaceEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.preferencesCsrfToken || "",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "delete_upload",
                    upload_id: uploadId,
                    aegis_csrf: config.preferencesCsrfToken || "",
                }),
            });
            const data = await response.json();
            config.preferencesCsrfToken = data.csrfToken || config.preferencesCsrfToken;
            syncTokens();

            if (data.state) {
                applyWorkspaceState(data.state);
            }

            setWorkspaceStatus(data.message || "Uploaded file removed.", data.ok ? "ok" : "fail");
            setStatus(data.message || "Uploaded file removed.");
        } catch (_error) {
            setWorkspaceStatus("Uploaded file could not be removed.", "fail");
        }
    };

    const creativeModeLabels = {
        image: "Image Studio",
        animation: "Motion Studio",
        beat: "Beat Lab",
        project: "Project Forge",
    };

    const creativeModeFromCommand = (message) => {
        const text = String(message || "").trim();
        const match = text.match(/^\/(image|img|animate|animation|motion|beat|music|song|project|app|codebase|repo|scaffold)\b/i);
        if (!match) {
            if (
                /\b(create|build|generate|make|scaffold)\b/i.test(text)
                && /\b(project|app|website|codebase|repo|repository|starter|full stack|from scratch)\b/i.test(text)
                && /\b(code|react|node|php|python|javascript|typescript|html|css|api|frontend|backend|website|app)\b/i.test(text)
            ) {
                return "project";
            }
            return "";
        }

        const command = match[1].toLowerCase();
        if (command === "image" || command === "img") {
            return "image";
        }
        if (command === "animate" || command === "animation" || command === "motion") {
            return "animation";
        }
        if (command === "project" || command === "app" || command === "codebase" || command === "repo" || command === "scaffold") {
            return "project";
        }
        return "beat";
    };

    const cleanCreativePrompt = (message, mode = "") => {
        const commandPattern = mode === "image"
            ? /^\/(?:image|img)\s*/i
            : mode === "animation"
                ? /^\/(?:animate|animation|motion)\s*/i
                : mode === "project"
                    ? /^\/(?:project|app|codebase|repo|scaffold)\s*/i
                    : /^\/(?:beat|music|song)\s*/i;
        return String(message || "").replace(commandPattern, "").trim() || String(message || "").trim();
    };

    const setCreativeMode = (mode = "") => {
        const normalized = ["image", "animation", "beat", "project"].includes(mode) ? mode : "";
        creativeMode = normalized;
        creativeToggles.forEach((button) => {
            const active = button.dataset.chatbotCreativeMode === normalized;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-pressed", active ? "true" : "false");
        });
        setStatus(normalized ? `${creativeModeLabels[normalized]} is on. Your next prompt will create a ${normalized === "beat" ? "beat sketch" : normalized === "project" ? "project ZIP" : normalized}.` : `${activeAgentSummary()} is using ${activeProfile()?.label || "Aegis Chat"} in ${strategyLabel(activeStrategy())} mode.`);
    };

    const attachGeneratedImage = (article, image = {}) => {
        const body = article?.querySelector("div:last-child");
        if (!body || !image.url) {
            return;
        }

        const wrap = document.createElement("a");
        wrap.className = "chatbot-generated-image";
        wrap.href = image.url;
        wrap.target = "_blank";
        wrap.rel = "noopener";

        const img = document.createElement("img");
        img.src = image.url;
        img.alt = image.prompt || "Generated Aegis image";

        const caption = document.createElement("span");
        caption.textContent = `${image.model || "Image model"} · ${image.size || "1024x1024"} · ${image.quality || "medium"}`;

        wrap.append(img, caption);
        body.append(wrap);
    };

    const attachGeneratedAnimation = (article, creative = {}) => {
        const body = article?.querySelector("div:last-child");
        if (!body || !creative.html) {
            return;
        }

        const wrap = document.createElement("div");
        wrap.className = "chatbot-generated-animation";

        const header = document.createElement("div");
        header.className = "chatbot-creative-preview-head";
        const title = document.createElement("strong");
        title.textContent = creative.title || "Aegis Motion Studio";
        const meta = document.createElement("span");
        meta.textContent = `${creative.duration || 6}s loop - ${Array.isArray(creative.palette) ? creative.palette.join(" / ") : "Aegis palette"}`;
        header.append(title, meta);

        const stage = document.createElement("div");
        stage.className = "chatbot-animation-stage";
        stage.innerHTML = String(creative.html || "");

        const note = document.createElement("p");
        note.textContent = "Generated as safe HTML/SVG motion that can be used as a hero card, loading state, or product preview.";

        wrap.append(header, stage, note);
        body.append(wrap);
    };

    let activeBeatStop = null;

    const triggerBeatSound = (context, sound, when, volume = 0.5, step = 0) => {
        const gain = context.createGain();
        gain.gain.setValueAtTime(Math.max(0.01, volume), when);
        gain.gain.exponentialRampToValueAtTime(0.001, when + 0.18);
        gain.connect(context.destination);

        if (sound === "snare" || sound === "hat") {
            const buffer = context.createBuffer(1, Math.max(1, Math.floor(context.sampleRate * 0.14)), context.sampleRate);
            const data = buffer.getChannelData(0);
            for (let index = 0; index < data.length; index += 1) {
                data[index] = (Math.random() * 2 - 1) * (sound === "hat" ? 0.36 : 0.8);
            }
            const noise = context.createBufferSource();
            const filter = context.createBiquadFilter();
            filter.type = "highpass";
            filter.frequency.value = sound === "hat" ? 7600 : 1600;
            noise.buffer = buffer;
            noise.connect(filter);
            filter.connect(gain);
            noise.start(when);
            noise.stop(when + (sound === "hat" ? 0.045 : 0.13));
            return;
        }

        const oscillator = context.createOscillator();
        oscillator.type = sound === "lead" ? "square" : sound === "bass" ? "sawtooth" : "sine";
        const baseFrequency = sound === "kick" ? 112 : sound === "bass" ? 54 + ((step % 5) * 6) : 260 + ((step % 7) * 28);
        oscillator.frequency.setValueAtTime(baseFrequency, when);
        if (sound === "kick") {
            oscillator.frequency.exponentialRampToValueAtTime(42, when + 0.14);
        }
        oscillator.connect(gain);
        oscillator.start(when);
        oscillator.stop(when + (sound === "lead" ? 0.12 : 0.2));
    };

    const playBeat = (creative = {}, button = null) => {
        if (activeBeatStop) {
            activeBeatStop();
            activeBeatStop = null;
            if (button?.dataset.playing === "true") {
                return;
            }
        }

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            setStatus("This browser does not support Web Audio playback.");
            return;
        }

        const context = new AudioContextClass();
        const tracks = Array.isArray(creative.tracks) ? creative.tracks : [];
        const bpm = Math.max(55, Math.min(190, Number(creative.bpm || 120)));
        const stepMs = (60 / bpm / 4) * 1000;
        const totalSteps = 16 * Math.max(1, Number(creative.bars || 4));
        let currentStep = 0;

        const setPlaying = (playing) => {
            document.querySelectorAll("[data-chatbot-play-beat]").forEach((playButton) => {
                playButton.dataset.playing = "false";
                playButton.textContent = "Play beat";
            });
            if (button && playing) {
                button.dataset.playing = "true";
                button.textContent = "Stop beat";
            }
        };

        const stop = () => {
            window.clearInterval(timer);
            setPlaying(false);
            context.close().catch(() => {});
        };

        const tick = () => {
            const step = currentStep % 16;
            const when = context.currentTime + 0.015;
            tracks.forEach((track) => {
                const steps = Array.isArray(track.steps) ? track.steps : [];
                if (Number(steps[step] || 0) > 0) {
                    triggerBeatSound(context, String(track.sound || "lead"), when, Number(track.volume || 0.5), step);
                }
            });
            currentStep += 1;
            if (currentStep >= totalSteps) {
                activeBeatStop?.();
                activeBeatStop = null;
            }
        };

        const timer = window.setInterval(tick, stepMs);
        activeBeatStop = stop;
        setPlaying(true);
        tick();
        setStatus(`Playing ${creative.title || "Aegis beat"} at ${bpm} BPM.`);
    };

    const attachGeneratedBeat = (article, creative = {}) => {
        const body = article?.querySelector("div:last-child");
        if (!body) {
            return;
        }

        const wrap = document.createElement("div");
        wrap.className = "chatbot-generated-beat";
        wrap.dataset.beat = JSON.stringify(creative);

        const header = document.createElement("div");
        header.className = "chatbot-creative-preview-head";
        const title = document.createElement("strong");
        title.textContent = creative.title || "Aegis Beat Lab";
        const meta = document.createElement("span");
        meta.textContent = `${creative.bpm || 120} BPM - ${creative.key || "Minor"} - ${creative.swing || 0}% swing`;
        header.append(title, meta);

        const grid = document.createElement("div");
        grid.className = "chatbot-beat-grid";
        (Array.isArray(creative.tracks) ? creative.tracks : []).forEach((track) => {
            const row = document.createElement("div");
            row.className = "chatbot-beat-row";
            const label = document.createElement("span");
            label.textContent = track.name || "Track";
            row.append(label);
            (Array.isArray(track.steps) ? track.steps : []).slice(0, 16).forEach((step, index) => {
                const cell = document.createElement("i");
                cell.className = Number(step || 0) > 0 ? "is-on" : "";
                cell.title = `${track.name || "Track"} step ${index + 1}`;
                row.append(cell);
            });
            grid.append(row);
        });

        const controls = document.createElement("div");
        controls.className = "chatbot-beat-controls";
        const play = document.createElement("button");
        play.type = "button";
        play.dataset.chatbotPlayBeat = "true";
        play.textContent = "Play beat";
        const copy = document.createElement("button");
        copy.type = "button";
        copy.dataset.chatbotCopyBeat = "true";
        copy.textContent = "Copy pattern";
        controls.append(play, copy);

        wrap.append(header, grid, controls);
        body.append(wrap);
    };

    const attachGeneratedProject = (article, project = {}) => {
        const body = article?.querySelector("div:last-child");
        if (!body) {
            return;
        }

        const wrap = document.createElement("div");
        wrap.className = "chatbot-generated-project";

        const header = document.createElement("div");
        header.className = "chatbot-creative-preview-head";
        const title = document.createElement("strong");
        title.textContent = project.title || "Aegis Project";
        const meta = document.createElement("span");
        meta.textContent = `${project.type || "starter"} - ${project.fileCount || 0} files`;
        header.append(title, meta);

        const summary = document.createElement("p");
        summary.textContent = project.summary || "Generated a complete starter project with files and a downloadable ZIP.";

        const fileGrid = document.createElement("div");
        fileGrid.className = "chatbot-project-file-grid";
        (Array.isArray(project.files) ? project.files : []).slice(0, 10).forEach((file) => {
            const link = document.createElement("a");
            link.href = file.url || "#";
            link.target = "_blank";
            link.rel = "noopener";
            const path = document.createElement("strong");
            path.textContent = file.path || "file";
            const size = document.createElement("span");
            const bytes = Number(file.size || 0);
            size.textContent = bytes > 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${bytes} B`;
            link.append(path, size);
            fileGrid.append(link);
        });

        const actions = document.createElement("div");
        actions.className = "chatbot-project-actions";
        const download = document.createElement("a");
        download.href = project.zipUrl || "#";
        download.className = "chatbot-project-download";
        download.textContent = "Download ZIP";
        download.setAttribute("download", `${project.slug || "aegis-project"}.zip`);
        actions.append(download);

        wrap.append(header, summary, fileGrid, actions);
        body.append(wrap);
    };

    const sendImagePrompt = async (message, retryOnCsrf = true) => {
        if (sending || !config.imageEndpoint) {
            return;
        }

        sending = true;
        const cleanPrompt = cleanCreativePrompt(message, "image");
        messagesEl.append(messageNode("user", cleanPrompt));
        const pending = messageNode("assistant", "Generating your image now...", "Aegis Image Studio");
        messagesEl.append(pending);
        setConversationState(true);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        setStatus("Aegis Image Studio is generating...");

        try {
            const response = await fetch(config.imageEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken,
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    prompt: cleanPrompt,
                    aegis_csrf: config.csrfToken,
                }),
            });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;
            syncTokens();

            if (!data.ok) {
                if (response.status === 419 && retryOnCsrf && data.csrfToken) {
                    pending.remove();
                    messagesEl.lastElementChild?.remove();
                    sending = false;
                    return sendImagePrompt(cleanPrompt, false);
                }
                pending.querySelector("p").textContent = data.message || "Aegis Image Studio could not generate that image.";
                setStatus("Image generation failed.");
                return;
            }

            pending.querySelector("span").textContent = data.message?.model || "Aegis Image Studio";
            pending.querySelector("p").textContent = data.message?.content || "Generated image from your prompt.";
            attachGeneratedImage(pending, data.image || {});
            setStatus("Image generated.");
            setCreativeMode("");
        } catch (_error) {
            pending.querySelector("p").textContent = "Aegis Image Studio could not reach the server.";
            setStatus("Image generation network error.");
        } finally {
            sending = false;
        }
    };

    const sendCreativePrompt = async (mode, message, retryOnCsrf = true) => {
        if (sending || !config.creativeEndpoint) {
            return;
        }

        sending = true;
        const action = mode === "beat" ? "beat" : "animation";
        const cleanPrompt = cleanCreativePrompt(message, action);
        const studio = action === "beat" ? "Aegis Beat Lab" : "Aegis Motion Studio";
        messagesEl.append(messageNode("user", cleanPrompt));
        const pending = messageNode("assistant", action === "beat" ? "Composing a playable beat sketch..." : "Designing a motion preview...", studio);
        messagesEl.append(pending);
        setConversationState(true);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        setStatus(`${studio} is generating...`);

        try {
            const response = await fetch(config.creativeEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken,
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    action,
                    prompt: cleanPrompt,
                    aegis_csrf: config.csrfToken,
                }),
            });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;
            syncTokens();

            if (!data.ok) {
                if (response.status === 419 && retryOnCsrf && data.csrfToken) {
                    pending.remove();
                    messagesEl.lastElementChild?.remove();
                    sending = false;
                    return sendCreativePrompt(action, cleanPrompt, false);
                }
                pending.querySelector("p").textContent = data.message || `${studio} could not generate that asset.`;
                setStatus("Creative generation failed.");
                return;
            }

            pending.querySelector("span").textContent = data.message?.model || studio;
            pending.querySelector("p").textContent = data.message?.content || "Creative asset generated.";
            if (data.creative?.type === "beat") {
                attachGeneratedBeat(pending, data.creative);
            } else {
                attachGeneratedAnimation(pending, data.creative || {});
            }
            setCreativeMode("");
            setStatus(`${studio} generated a creative asset.`);
        } catch (_error) {
            pending.querySelector("p").textContent = `${studio} could not reach the server.`;
            setStatus("Creative generation network error.");
        } finally {
            sending = false;
        }
    };

    const sendProjectPrompt = async (message, retryOnCsrf = true) => {
        if (sending || !config.projectEndpoint) {
            return;
        }

        sending = true;
        const cleanPrompt = cleanCreativePrompt(message, "project");
        messagesEl.append(messageNode("user", cleanPrompt));
        const pending = messageNode("assistant", "Building a complete project package now...", "Aegis Project Forge");
        messagesEl.append(pending);
        setConversationState(true);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        setStatus("Aegis Project Forge is generating files...");

        try {
            const response = await fetch(config.projectEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken,
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    prompt: cleanPrompt,
                    aegis_csrf: config.csrfToken,
                }),
            });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;
            syncTokens();

            if (!data.ok) {
                if (response.status === 419 && retryOnCsrf && data.csrfToken) {
                    pending.remove();
                    messagesEl.lastElementChild?.remove();
                    sending = false;
                    return sendProjectPrompt(cleanPrompt, false);
                }
                pending.querySelector("p").textContent = data.message || "Aegis Project Forge could not generate that project.";
                setStatus("Project generation failed.");
                return;
            }

            pending.querySelector("span").textContent = data.message?.model || "Aegis Project Forge";
            pending.querySelector("p").textContent = data.message?.content || "Generated a complete project package.";
            attachGeneratedProject(pending, data.project || {});
            setCreativeMode("");
            setStatus("Project ZIP generated.");
        } catch (_error) {
            pending.querySelector("p").textContent = "Aegis Project Forge could not reach the server.";
            setStatus("Project generation network error.");
        } finally {
            sending = false;
        }
    };

    composer.addEventListener("submit", async (event) => {
        event.preventDefault();
        const message = prompt.value.trim();
        if (!message) {
            return;
        }

        prompt.value = "";
        autosizePrompt();
        const requestedCreativeMode = creativeModeFromCommand(message) || creativeMode;
        if (requestedCreativeMode === "image") {
            await sendImagePrompt(message);
            return;
        }

        if (requestedCreativeMode === "animation" || requestedCreativeMode === "beat") {
            await sendCreativePrompt(requestedCreativeMode, message);
            return;
        }

        if (requestedCreativeMode === "project") {
            await sendProjectPrompt(message);
            return;
        }

        await sendMessage(message);
    });

    document.querySelectorAll("[data-chatbot-prompt]").forEach((button) => {
        button.addEventListener("click", () => {
            prompt.value = button.dataset.chatbotPrompt || button.textContent || "";
            autosizePrompt();
            prompt.focus();
        });
    });

    document.querySelectorAll("[data-chatbot-open-settings]").forEach((button) => {
        button.addEventListener("click", openSettings);
    });

    document.querySelectorAll("[data-chatbot-focus]").forEach((button) => {
        button.addEventListener("click", () => {
            prompt.focus();
        });
    });

    document.querySelectorAll("[data-chatbot-close-settings]").forEach((button) => {
        button.addEventListener("click", closeSettings);
    });

    document.querySelectorAll("[data-chatbot-toggle-sidebar]").forEach((button) => {
        button.addEventListener("click", () => {
            shell?.classList.toggle("is-sidebar-collapsed");
        });
    });

    document.querySelectorAll("[data-chatbot-new-thread]").forEach((button) => {
        button.addEventListener("click", () => {
            currentThreadId = null;
            if (threadInput) {
                threadInput.value = "";
            }
            renderMessages([]);
            threadsEl?.querySelectorAll("a").forEach((link) => link.classList.remove("is-active"));
            prompt.value = "";
            autosizePrompt();
            setStatus("New chat ready.");
            prompt.focus();
        });
    });

    threadsEl?.addEventListener("click", (event) => {
        const link = event.target.closest("[data-thread-id]");
        if (!link) {
            return;
        }

        event.preventDefault();
        loadChat(link.dataset.threadId).catch(() => setStatus("Could not load that chat."));
    });

    settingsForm?.addEventListener("change", async (event) => {
        const target = event.target;
        if (
            target instanceof HTMLElement
            && (target.closest("[data-chatbot-model-manager]") || target.closest("#chatbotSettingsWorkspace") || target.closest("#chatbotSettingsAgents"))
        ) {
            return;
        }

        try {
            const data = await savePreferences();
            setStatus(data.ok ? "Settings saved." : data.message || "Settings could not be saved.");
        } catch (_error) {
            setStatus("Settings could not reach the server.");
        }
    });

    builtinProfilesEl?.addEventListener("click", (event) => {
        const useButton = event.target.closest("[data-chatbot-use-profile]");
        if (useButton) {
            useProfile(useButton.dataset.chatbotUseProfile || "");
            return;
        }

        const testButton = event.target.closest("[data-chatbot-test-profile]");
        if (testButton) {
            testProfile(testButton.dataset.chatbotTestProfile || "");
        }
    });

    customProfilesEl?.addEventListener("click", (event) => {
        const useButton = event.target.closest("[data-chatbot-use-profile]");
        if (useButton) {
            useProfile(useButton.dataset.chatbotUseProfile || "");
            return;
        }

        const editButton = event.target.closest("[data-chatbot-edit-profile]");
        if (editButton) {
            editProfile(editButton.dataset.chatbotEditProfile || "");
            return;
        }

        const testButton = event.target.closest("[data-chatbot-test-profile]");
        if (testButton) {
            testProfile(testButton.dataset.chatbotTestProfile || "");
            return;
        }

        const deleteButton = event.target.closest("[data-chatbot-delete-profile]");
        if (deleteButton) {
            deleteProfile(deleteButton.dataset.chatbotDeleteProfile || "");
        }
    });

    modelProviderInput?.addEventListener("change", () => {
        if (!modelEndpointInput || modelEndpointInput.value.trim() !== "") {
            return;
        }

        modelEndpointInput.value = modelProviderInput.value === "openai"
            ? "http://127.0.0.1:1234"
            : "http://127.0.0.1:11434";
    });

    modelSaveButton?.addEventListener("click", saveModelProfile);
    modelResetButton?.addEventListener("click", () => resetModelForm());
    modelNameInput?.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            saveModelProfile();
        }
    });

    modelFormStatus?.addEventListener("dblclick", () => {
        testProfile();
    });

    agentModeSelect?.addEventListener("change", async () => {
        config.preferences = config.preferences || {};
        config.preferences.chatbot_agent_mode = agentModeSelect?.value || "auto";
        if ((agentModeSelect?.value || "auto") === "solo" && activeAgentIds().length > 1) {
            setActiveAgents([activeAgentIds()[0]]);
        } else {
            renderAgentLibrary();
            updateExperienceChrome();
        }

        try {
            const data = await savePreferences();
            setStatus(data.ok ? "Agent routing saved." : data.message || "Settings could not be saved.");
        } catch (_error) {
            setStatus("Settings could not reach the server.");
        }
    });

    agentLibraryEl?.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-chatbot-agent]");
        if (!button) {
            return;
        }

        const agentId = button.dataset.chatbotAgent || "";
        const current = activeAgentIds();
        const selected = current.includes(agentId);
        let next = current;

        if (activeAgentMode() === "solo") {
            next = selected ? [] : [agentId];
        } else if (selected) {
            next = current.filter((value) => value !== agentId);
        } else {
            next = [...current, agentId];
        }

        setActiveAgents(next);

        try {
            const data = await savePreferences();
            setStatus(data.ok ? "Agent team saved." : data.message || "Settings could not be saved.");
        } catch (_error) {
            setStatus("Settings could not reach the server.");
        }
    });

    agentRailEl?.addEventListener("click", openSettings);

    workspaceRootSelect?.addEventListener("change", async () => {
        await changeWorkspaceRoot(workspaceRootSelect.value || "");
    });

    workspaceUpButton?.addEventListener("click", async () => {
        await goUpWorkspace();
    });

    workspaceBreadcrumbsEl?.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-chatbot-workspace-path]");
        if (!button) {
            return;
        }

        await browseWorkspace(button.dataset.chatbotWorkspacePath || "", activeWorkspaceRootId());
        await persistWorkspacePreferences("Workspace folder saved.");
    });

    workspaceBrowserEl?.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-chatbot-workspace-entry]");
        if (!button) {
            return;
        }

        const filePath = button.dataset.chatbotWorkspaceEntry || "";
        if ((button.dataset.entryType || "") === "dir") {
            await browseWorkspace(filePath, activeWorkspaceRootId());
            await persistWorkspacePreferences("Workspace folder saved.");
            return;
        }

        await attachWorkspaceFile(filePath);
    });

    workspaceFilesEl?.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-chatbot-workspace-remove]");
        if (!button) {
            return;
        }

        await removeWorkspaceFile(button.dataset.chatbotWorkspaceRemove || "");
    });

    uploadButton?.addEventListener("click", () => uploadInput?.click());
    uploadManagerButton?.addEventListener("click", () => uploadInput?.click());
    uploadInput?.addEventListener("change", () => {
        uploadFiles(uploadInput.files).catch(() => setWorkspaceStatus("File upload failed.", "fail"));
    });
    const handleUploadDelete = async (event) => {
        const button = event.target.closest("[data-chatbot-delete-upload]");
        if (!button) {
            return;
        }

        await deleteUploadedFile(button.dataset.chatbotDeleteUpload || "");
    };
    uploadTray?.addEventListener("click", handleUploadDelete);
    uploadList?.addEventListener("click", handleUploadDelete);
    creativeToggles.forEach((button) => {
        button.addEventListener("click", () => {
            const nextMode = button.dataset.chatbotCreativeMode || "";
            setCreativeMode(creativeMode === nextMode ? "" : nextMode);
        });
    });

    messagesEl.addEventListener("click", async (event) => {
        const playButton = event.target.closest("[data-chatbot-play-beat]");
        if (playButton) {
            const card = playButton.closest(".chatbot-generated-beat");
            try {
                playBeat(JSON.parse(card?.dataset.beat || "{}"), playButton);
            } catch (_error) {
                setStatus("Beat pattern could not be played.");
            }
            return;
        }

        const copyButton = event.target.closest("[data-chatbot-copy-beat]");
        if (copyButton) {
            const card = copyButton.closest(".chatbot-generated-beat");
            try {
                await navigator.clipboard.writeText(card?.dataset.beat || "{}");
                setStatus("Beat pattern copied.");
            } catch (_error) {
                setStatus("Beat pattern could not be copied.");
            }
        }
    });

    prompt.addEventListener("input", autosizePrompt);
    window.addEventListener("resize", autosizePrompt);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && settingsModal && !settingsModal.hidden) {
            closeSettings();
        }
    });

    syncTokens();
    renderModelLibrary();
    renderAgentLibrary();
    renderWorkspaceBrowser();
    updateExperienceChrome();
    applyRuntime(config.runtime || {});
    applyUiPreferences();
    autosizePrompt();
    resetModelForm();
    setUsage(0, Number(config.limits?.messagesPerDay || usageEl?.textContent?.split("/")[1] || 0));
    loadChat().catch(() => setStatus("Aegis Chat could not load."));
    loadModelLibrary().catch(() => setStatus("Model library could not load."));
    loadWorkspace().catch(() => setWorkspaceStatus("Workspace browser could not load.", "fail"));
})();

(() => {
    const config = window.AEGIS_SPORTS;
    if (document.querySelector(".betedge-shell")) {
        return;
    }
    const board = document.getElementById("sportsLiveBoard");
    const tape = document.getElementById("sportsTape");
    const metrics = document.getElementById("sportsMetrics");
    const sourceLabel = document.getElementById("sportsSourceLabel");
    const predictionGrid = document.getElementById("sportsPredictionGrid");
    const bookGrid = document.getElementById("sportsBookGrid");
    const edgeStack = document.getElementById("sportsEdgeStack");
    const factorGrid = document.getElementById("sportsFactorGrid");
    const rulesBoard = document.getElementById("sportsRulesBoard");
    const opportunityBoard = document.getElementById("sportsOpportunityBoard");
    const performanceBoard = document.getElementById("sportsPerformanceBoard");
    const modelSourceGrid = document.getElementById("sportsModelSourceGrid");
    const alertsBoard = document.getElementById("sportsAlertsBoard");
    const timeline = document.getElementById("sportsConfidenceTimeline");
    const insightCopy = document.getElementById("sportsInsightCopy");
    const marketTitle = document.getElementById("sportsMarketTitle");
    const selectedMarket = document.getElementById("sportsSelectedMarket");
    const marketLine = document.getElementById("sportsMarketLine");
    const topPickCommand = document.getElementById("sportsTopPickCommand");
    const coverageGrid = document.getElementById("sportsCoverageGrid");
    const providerGrid = document.getElementById("sportsProviderGrid");
    const filterButtons = Array.from(document.querySelectorAll("[data-sports-filter]"));
    const viewButtons = Array.from(document.querySelectorAll(".sports-command-nav [data-sports-view], .sports-reference-topnav [data-sports-view]"));
    const viewControls = Array.from(document.querySelectorAll("[data-sports-view]"));
    const panels = Array.from(document.querySelectorAll("[data-sports-panel]"));
    const providerButtons = Array.from(document.querySelectorAll("[data-sports-provider-filter]"));
    const marketBoard = document.getElementById("sportsMarketBoard");
    const marketCount = document.getElementById("sportsMarketCount");
    const boardCount = document.getElementById("sportsBoardCount");
    const sidebarSource = document.getElementById("sportsSidebarSource");
    const sidebarRefresh = document.getElementById("sportsSidebarRefresh");
    const sidebarPick = document.getElementById("sportsSidebarPick");
    const sidebarMatch = document.getElementById("sportsSidebarMatch");
    const refreshClock = document.getElementById("sportsRefreshClock");
    const refreshDetail = document.getElementById("sportsRefreshDetail");
    const tracker = {
        root: document.getElementById("sportsGameTracker"),
        league: document.getElementById("sportsTrackerLeague"),
        title: document.getElementById("sportsTrackerTitle"),
        clock: document.getElementById("sportsTrackerClock"),
        detail: document.getElementById("sportsTrackerDetail"),
        field: document.getElementById("sportsTrackerField"),
        ball: document.getElementById("sportsTrackerBall"),
        awayName: document.getElementById("sportsTrackerAwayName"),
        awayScore: document.getElementById("sportsTrackerAwayScore"),
        homeName: document.getElementById("sportsTrackerHomeName"),
        homeScore: document.getElementById("sportsTrackerHomeScore"),
        odds: document.getElementById("sportsTrackerOdds"),
        prediction: document.getElementById("sportsTrackerPrediction"),
    };
    const pickDrawer = document.getElementById("sportsPickDrawer");
    const pickDrawerTitle = document.getElementById("sportsPickDrawerTitle");
    const pickDrawerEyebrow = document.getElementById("sportsPickDrawerEyebrow");
    const pickDrawerReason = document.getElementById("sportsPickDrawerReason");
    const pickDrawerScore = document.getElementById("sportsPickDrawerScore");
    const pickDrawerComparison = document.getElementById("sportsPickDrawerComparison");
    const pickDrawerMath = document.getElementById("sportsPickDrawerMath");
    const pickDrawerReadiness = document.getElementById("sportsPickDrawerReadiness");
    const pickDrawerFactors = document.getElementById("sportsPickDrawerFactors");
    const pickDrawerInjuries = document.getElementById("sportsPickDrawerInjuries");
    const pickDrawerProviders = document.getElementById("sportsPickDrawerProviders");
    const pickDrawerNoBet = document.getElementById("sportsPickDrawerNoBet");
    const pickDrawerManual = document.getElementById("sportsPickDrawerManual");

    if (!config || !board) {
        return;
    }

    const trendText = (trend) => (trend === "up" ? "rising" : trend === "down" ? "falling" : "steady");
    const teamMark = (team = {}) => String(team.abbr || team.short || team.name || "TM").replace(/[^A-Za-z0-9]/g, "").toUpperCase().slice(0, 2) || "TM";
    const visibleSource = (source) => {
        const text = String(source || "").toLowerCase();
        if (text.includes("live")) return "Live";
        if (text.includes("cached")) return "Cached";
        if (text.includes("partial")) return "Partial";
        return "Fallback";
    };
    let activeSportsFilter = "all";
    let activeProviderFilter = "all";
    let activeSportsView = "dashboard";
    let latestSportsState = window.AEGIS_SPORTS_STATE || {};
    let selectedGameId = String(latestSportsState.games?.[0]?.id || "");
    let sportsRefreshInFlight = false;

    const applySportsFilter = () => {
        const cards = Array.from(board.querySelectorAll(".sports-event-card, .sports-reference-live-card"));
        cards.forEach((card) => {
            const filter = activeSportsFilter;
            const status = card.dataset.statusKey || "";
            const group = card.dataset.sportGroup || "";
            const league = card.dataset.league || "";
            const visible =
                filter === "all" ||
                filter === status ||
                filter === `group:${group}` ||
                filter === `league:${league}`;
            card.hidden = !visible;
        });
    };

    filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
            activeSportsFilter = button.dataset.sportsFilter || "all";
            filterButtons.forEach((item) => item.classList.toggle("is-active", item === button));
            applySportsFilter();
        });
    });

    const showSportsView = (view = "dashboard") => {
        activeSportsView = view;
        panels.forEach((panel) => {
            const visible = (panel.dataset.sportsPanel || "dashboard") === view;
            panel.hidden = !visible;
            panel.classList.toggle("is-active", visible);
        });
        viewButtons.forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.sportsView || "dashboard") === view);
        });
        document.querySelector(".sports-command-app")?.scrollIntoView({ behavior: "auto", block: "start" });
    };

    viewControls.forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            const nextView = button.dataset.sportsView || "dashboard";
            const providerJump = button.dataset.sportsProviderJump;
            showSportsView(nextView);
            if (providerJump) {
                activeProviderFilter = providerJump;
                providerButtons.forEach((item) => item.classList.toggle("is-active", (item.dataset.sportsProviderFilter || "all") === providerJump));
                renderMarketBoard(latestSportsState, activeProviderFilter);
            }
        });
    });

    providerButtons.forEach((button) => {
        button.addEventListener("click", () => {
            activeProviderFilter = button.dataset.sportsProviderFilter || "all";
            providerButtons.forEach((item) => item.classList.toggle("is-active", item === button));
            renderMarketBoard(latestSportsState, activeProviderFilter);
        });
    });

    const teamAvatar = (team = {}) =>
        team.logo
            ? `<img src="${escapeHtml(team.logo)}" alt="${escapeHtml(team.abbr || team.name || "Team")}">`
            : `<span class="sports-reference-team-badge">${escapeHtml(teamMark(team))}</span>`;

    const predictionIndexForGame = (gameId) => {
        const predictions = Array.isArray(latestSportsState.predictions) ? latestSportsState.predictions : [];
        return predictions.findIndex((prediction) => String(prediction.gameId || "") === String(gameId || ""));
    };

    const renderSparkline = (points = []) =>
        `<div class="confidence-sparkline" aria-label="Confidence history">${points
            .map((point) => `<i style="--p: ${escapeHtml(point)}%;"><span>${escapeHtml(point)}%</span></i>`)
            .join("")}</div>`;

    const renderMarketLinks = (links = [], limit = 4) => {
        const safeLinks = Array.isArray(links) ? links.slice(0, limit) : [];
        if (!safeLinks.length) return "";

        return `
            <div class="sports-reference-offer-strip" aria-label="Sportsbook and exchange links">
                <span>Bet access</span>
                ${safeLinks
                    .map((link) => {
                        const hasPrice = link?.available && link?.price && link.price !== "--";
                        const detail = hasPrice ? `${link.line || "Line"} ${link.price}` : link?.market || "Open app";
                        return `
                            <a href="${escapeHtml(link?.url || "#")}" target="_blank" rel="noopener noreferrer" class="${link?.available ? "is-live" : ""}">
                                <strong>${escapeHtml(link?.title || "Provider")}</strong>
                                <em>${escapeHtml(detail)}</em>
                            </a>
                        `;
                    })
                    .join("")}
            </div>
        `;
    };

    const renderGames = (games = [], source = "Live scoreboard", badge = "") => {
        board.innerHTML = games
            .slice(0, 12)
            .map((game) => {
                const predictionIndex = predictionIndexForGame(game.id || "");
                const selected = String(game.id || "") === String(selectedGameId || "");
                return `
                    <article class="sports-event-card ${selected ? "is-selected" : ""}" data-sports-select-game="${escapeHtml(game.id || "")}" data-game-id="${escapeHtml(game.id || "")}" data-status-key="${escapeHtml(game.statusKey || "scheduled")}" data-sport-group="${escapeHtml(game.sportGroup || "Sports")}" data-league="${escapeHtml(game.league || "")}">
                        <div>
                            <span>${escapeHtml(game.league || "SPORT")}</span>
                            <i class="sports-reference-status-chip tone-${escapeHtml(game.statusTone || "scheduled")}">${escapeHtml(game.statusLabel || "Watch")}</i>
                        </div>
                        <strong>${escapeHtml(game.matchup || `${game.away?.abbr || "AWY"} @ ${game.home?.abbr || "HME"}`)}</strong>
                        <div class="sports-event-scoreline">
                            <span>${escapeHtml(game.away?.abbr || game.away?.name || "Away")} <b>${escapeHtml(game.away?.score ?? "0")}</b></span>
                            <span>${escapeHtml(game.home?.abbr || game.home?.name || "Home")} <b>${escapeHtml(game.home?.score ?? "0")}</b></span>
                        </div>
                        <small>${escapeHtml(`${game.clock || "Scheduled"} / ${game.detail || ""}`)}</small>
                        ${
                            predictionIndex >= 0
                                ? `<button type="button" data-sports-open-pick="${escapeHtml(predictionIndex)}">AI math</button>`
                                : ""
                        }
                    </article>
                `;
            })
            .join("");

        if (sourceLabel) {
            sourceLabel.textContent = badge || visibleSource(source);
            sourceLabel.title = source;
        }
        if (sidebarSource) {
            sidebarSource.textContent = badge || visibleSource(source);
        }
        if (sidebarRefresh) {
            sidebarRefresh.textContent = `Auto-refresh every ${Math.max(5, Number(config.refreshSeconds || 60))}s`;
        }
        if (refreshClock) {
            refreshClock.textContent = `${Math.max(5, Number(config.refreshSeconds || 60))}s refresh`;
        }
        if (boardCount) {
            boardCount.textContent = `${games.length} games loaded`;
        }
        applySportsFilter();
    };

    const renderTape = (items = []) => {
        if (!tape) return;
        tape.innerHTML = items
            .map((item) => `<span><strong>${escapeHtml(item.label || "")}</strong> ${escapeHtml(item.value || "")} ${escapeHtml(item.state || "")}</span>`)
            .join("") + '<span class="system">All Systems Operational</span>';
    };

    const renderMetrics = (items = []) => {
        if (!metrics) return;
        metrics.innerHTML = items
            .map(
                (item) => `
                    <article>
                        <span>${escapeHtml(item.label || "")}</span>
                        <strong>${escapeHtml(item.value || "")}</strong>
                    </article>
                `
            )
            .join("");
    };

    const predictionWinnerLabel = (item = {}) => {
        const direct = String(item.predictedWinner || "").trim();
        if (direct && !/^watch\s+/i.test(direct)) {
            return direct;
        }

        const comparison = item.teamComparison && typeof item.teamComparison === "object" ? item.teamComparison : {};
        let side = String(item.predictedWinnerSide || comparison.pickSide || "").trim();
        if (side !== "away" && side !== "home") {
            const awayRating = Number(comparison.away?.rating);
            const homeRating = Number(comparison.home?.rating);
            if (Number.isFinite(awayRating) && Number.isFinite(homeRating) && awayRating !== homeRating) {
                side = awayRating > homeRating ? "away" : "home";
            }
        }
        if (side === "away" || side === "home") {
            const team = comparison[side] || {};
            const label = String(team.name || team.abbr || "").trim();
            if (label) {
                return label;
            }
        }

        const matchup = String(item.matchup || "").trim();
        const cleanedPick = String(item.pick || "")
            .replace(/^postgame review:\s*/i, "")
            .replace(/^watch\s+/i, "")
            .replace(/\s+[+-]\d+(?:\.\d+)?.*$/, "")
            .trim();

        return cleanedPick && cleanedPick !== matchup ? cleanedPick : "No clear side";
    };

    const predictionWinnerMeta = (item = {}) => {
        const basis = String(item.predictedWinnerBasis || "").trim();
        const strength = String(item.predictedWinnerStrength || "").trim();
        if (basis && strength && basis !== strength) {
            return `${basis} / ${strength}`;
        }
        return basis || strength || `${item.actionLabel || "Signal"} / ${item.risk || "Managed risk"}`;
    };

    const renderPredictions = (items = []) => {
        if (!predictionGrid) return;

        const rows = items
            .slice(0, 8)
            .map((item, index) => {
                const confidence = Number.parseInt(String(item.confidenceValue || item.confidence || "50"), 10) || 50;
                const edge = item.edge || `${confidence >= 50 ? "+" : ""}${((confidence - 50) / 2.3).toFixed(1)}%`;
                const expected = item.expectedValue || `$${Math.max(12, (confidence - 48) * 3.2).toFixed(2)}`;
                return `
                    <article class="sports-reference-pick-row" data-can-bet="${item.canBet === false ? "false" : "true"}">
                        <div class="sports-reference-pick-main">
                            <i></i>
                            <div>
                                <strong>${escapeHtml(predictionWinnerLabel(item))}</strong>
                                <small>${escapeHtml(predictionWinnerMeta(item))}</small>
                            </div>
                        </div>
                        <div class="sports-reference-pick-match">${escapeHtml(item.matchup || "Matchup")}</div>
                        <div class="sports-reference-pick-market">${escapeHtml(item.market || "Spread")}</div>
                        <div class="sports-reference-pick-confidence">
                            <span><i style="width: ${escapeHtml(confidence)}%"></i></span>
                            <b>${escapeHtml(item.confidence || `${confidence}%`)}</b>
                        </div>
                        <div class="sports-reference-pick-odds">
                            <strong>${escapeHtml(item.odds || "-110")}</strong>
                            <small>Fair ${escapeHtml(item.fairOdds || "--")}</small>
                        </div>
                        <div class="sports-reference-pick-edge up">${escapeHtml(edge)}</div>
                        <div class="sports-reference-pick-ev up">${escapeHtml(expected)}</div>
                        <button type="button" class="sports-reference-info-button" data-sports-open-pick="${escapeHtml(index)}">Details</button>
                    </article>
                `;
            })
            .join("");

        predictionGrid.innerHTML = `
            <div class="sports-reference-picks-head">
                <span>Predicted Winner</span>
                <span>Match</span>
                <span>Market</span>
                <span>Research confidence</span>
                <span>Odds</span>
                <span>Edge</span>
                <span>Expected Value</span>
                <span>Info</span>
            </div>
            ${rows}
        `;
    };

    const renderTopPick = (pick = {}) => {
        if (!topPickCommand) return;
        const confidence = Number.parseInt(String(pick.confidenceValue || pick.confidence || "0"), 10) || 0;
        const why = Array.isArray(pick.why) ? pick.why.slice(0, 3) : [];
        const topLinks = Array.isArray(pick.marketLinks) ? pick.marketLinks.slice(0, 4) : [];
        topPickCommand.innerHTML = `
            <div>
                <span>${escapeHtml(pick.actionLabel || "AI Pick")}</span>
                <strong>${escapeHtml(pick.pick || "Watch the live board")}</strong>
                <small>${escapeHtml(pick.reason || "Lineforge is watching live state, schedule timing, market snapshots, and risk context before marking a signal actionable.")}</small>
            </div>
            <div>
                <span>Research confidence</span>
                <strong>${escapeHtml(pick.confidence || `${confidence}%`)}</strong>
                <button type="button" data-sports-open-pick="0">Open full breakdown</button>
            </div>
        `;
        if (sidebarPick) sidebarPick.textContent = pick.pick || "Watch the board";
        if (sidebarMatch) sidebarMatch.textContent = pick.matchup || "Best available matchup";
    };

    const renderCoverage = (coverage = {}) => {
        if (!coverageGrid) return;
        const groups = Array.isArray(coverage.groups) ? coverage.groups.slice(0, 12) : [];
        coverageGrid.innerHTML = groups
            .map(
                (group) => `
                    <div>
                        <strong>${escapeHtml(group.label || "Sports")}</strong>
                        <span>${escapeHtml(group.games ?? 0)} games</span>
                        <em>${escapeHtml(group.live ?? 0)} live / ${escapeHtml(group.scheduled ?? 0)} upcoming</em>
                    </div>
                `
            )
            .join("");
    };

    const renderProviderGrid = (access = {}) => {
        if (!providerGrid) return;
        providerGrid.innerHTML = `
            <div>
                <span>Odds feed</span>
                <strong>${access.oddsProviderConfigured ? "Connected" : "Needs API key"}</strong>
                <em>${escapeHtml(access.oddsProvider || "The Odds API")}</em>
            </div>
            <div>
                <span>Bookmakers</span>
                <strong>${escapeHtml(access.bookmakers ?? 0)}</strong>
                <em>Outbound app links</em>
            </div>
            <div>
                <span>Matched lines</span>
                <strong>${escapeHtml(access.availableLines ?? 0)}</strong>
                <em>${escapeHtml(access.matchedEvents ?? 0)} matched events</em>
            </div>
            <div>
                <span>Exchange scan</span>
                <strong>${escapeHtml(access.exchangeProvider || "Kalshi")}</strong>
                <em>${escapeHtml(access.kalshiMarketsCached ?? 0)} cached markets</em>
            </div>
        `;
    };

    const renderBooks = (items = []) => {
        if (!bookGrid) return;
        bookGrid.innerHTML = `<span>Feed</span><span>Market</span><span>Status</span><span>Age</span>${items
            .map(
                (item) => `
                    <strong>${escapeHtml(item.book || "")}</strong>
                    <em>${escapeHtml(item.line || "")}</em>
                    <strong>${escapeHtml(item.odds || "")}</strong>
                    <em>${escapeHtml(item.latency || "")}</em>
                `
            )
            .join("")}`;
    };

    const renderEdge = (items = []) => {
        if (!edgeStack) return;
        edgeStack.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <strong>${escapeHtml(item.name || "")}</strong>
                        <b>${escapeHtml(item.value || "")}</b>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </div>
                `
            )
            .join("");
    };

    const renderFactors = (items = []) => {
        if (!factorGrid) return;
        factorGrid.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <strong>${escapeHtml(item.name || "")}</strong>
                        <em>${escapeHtml(item.weight || "")}</em>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </div>
                `
            )
            .join("");
    };

    const renderRules = (items = []) => {
        if (!rulesBoard) return;
        rulesBoard.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <strong>${escapeHtml(item.name || "")}</strong>
                        <em>${escapeHtml(item.state || "")}</em>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </div>
                `
            )
            .join("");
    };

    const renderOpportunities = (items = []) => {
        if (!opportunityBoard) return;
        opportunityBoard.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <div class="sports-reference-opportunity-head">
                            <strong>${escapeHtml(item.name || "")}</strong>
                            <em>${escapeHtml(item.tag || "")}</em>
                        </div>
                        <b>${escapeHtml(item.value || "")}</b>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </div>
                `
            )
            .join("");
    };

    const renderPerformance = (items = []) => {
        if (!performanceBoard) return;
        performanceBoard.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <span>${escapeHtml(item.label || "")}</span>
                        <strong>${escapeHtml(item.value || "")}</strong>
                        <small>${escapeHtml(item.detail || "")}</small>
                    </div>
                `
            )
            .join("");
    };

    const renderAlerts = (items = []) => {
        if (!alertsBoard) return;
        alertsBoard.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <div>
                            <strong>${escapeHtml(item.name || "")}</strong>
                            <span>${escapeHtml(item.detail || "")}</span>
                        </div>
                        <small>${escapeHtml(item.time || "Now")}</small>
                    </div>
                `
            )
            .join("");
    };

    const renderMarketLine = (points = []) => {
        if (!marketLine) return;
        marketLine.innerHTML = points
            .map((point) => `<i style="--h: ${escapeHtml(point)}%"></i>`)
            .join("");
    };

    const renderInsight = (insight = {}, topPick = {}) => {
        if (!insightCopy) return;
        insightCopy.textContent =
            insight.copy ||
            topPick.reason ||
            `Our AI model predicts value on ${topPick.pick || "the top pick"} due to live score state, market drift, and the current matchup profile.`;
    };

    const renderTimeline = (points = []) => {
        if (!timeline) return;
        timeline.innerHTML = points
            .map((point) => `<i style="--p: ${escapeHtml(point)}%;"><span>${escapeHtml(point)}%</span></i>`)
            .join("");
    };

    const gameById = (gameId) => {
        const games = Array.isArray(latestSportsState.games) ? latestSportsState.games : [];
        return games.find((game) => String(game.id || "") === String(gameId || "")) || games[0] || {};
    };

    const predictionForGame = (gameId) => {
        const predictions = Array.isArray(latestSportsState.predictions) ? latestSportsState.predictions : [];
        return predictions.find((prediction) => String(prediction.gameId || "") === String(gameId || "")) || latestSportsState.topPick || predictions[0] || {};
    };

    const renderGameTracker = (game = {}, pick = {}) => {
        if (!tracker.root) return;
        const away = game.away || {};
        const home = game.home || {};
        const history = Array.isArray(game.history) && game.history.length ? game.history : [50];
        const lastPoint = Number(history[history.length - 1]) || 50;
        const ballX = Math.max(10, Math.min(90, lastPoint));
        const ballY = game.statusKey === "live" ? 55 : game.statusKey === "final" ? 70 : 40;

        tracker.root.dataset.gameId = game.id || "";
        if (tracker.league) tracker.league.textContent = game.league || "Game tracker";
        if (tracker.title) tracker.title.textContent = game.matchup || `${away.abbr || "AWY"} @ ${home.abbr || "HME"}`;
        if (tracker.clock) tracker.clock.textContent = game.clock || game.statusLabel || "Waiting";
        if (tracker.detail) tracker.detail.textContent = game.detail || "Choose a matchup to inspect live state, provider links, and the model confidence estimate.";
        if (tracker.field) tracker.field.dataset.sport = game.sportGroup || "Sports";
        if (tracker.ball) {
            tracker.ball.style.setProperty("--x", `${ballX}%`);
            tracker.ball.style.setProperty("--y", `${ballY}%`);
        }
        if (tracker.awayName) tracker.awayName.textContent = away.name || away.abbr || "Away";
        if (tracker.awayScore) tracker.awayScore.textContent = away.score ?? "0";
        if (tracker.homeName) tracker.homeName.textContent = home.name || home.abbr || "Home";
        if (tracker.homeScore) tracker.homeScore.textContent = home.score ?? "0";
        if (tracker.odds) {
            const best = game.bestLine || {};
            tracker.odds.innerHTML = `
                <span>Spread <strong>${escapeHtml(game.spread?.favoriteLine || "--")}</strong></span>
                <span>Total <strong>${escapeHtml(game.total?.over || "--")}</strong></span>
                <span>Best app <strong>${escapeHtml(best.title || "Provider links")}</strong></span>
            `;
        }
        if (tracker.prediction) {
            const predictionIndex = predictionIndexForGame(game.id || "");
            tracker.prediction.innerHTML = `
                <span>${escapeHtml(pick.market || "Model confidence estimate")}</span>
                <strong>${escapeHtml(pick.confidence || "Watch")}</strong>
                <button type="button" data-sports-open-pick="${escapeHtml(Math.max(0, predictionIndex))}">Open prediction breakdown</button>
            `;
        }
    };

    const flattenMarketRows = (state = {}) => {
        const games = Array.isArray(state.games) ? state.games : [];
        return games.flatMap((game) => {
            const links = Array.isArray(game.betLinks) ? game.betLinks : [];
            return links.slice(0, 10).map((link) => ({
                providerKey: String(link?.providerKey || link?.title || "provider").toLowerCase(),
                provider: link?.title || "Provider",
                kind: link?.kind || "Sportsbook",
                matchup: game.matchup || `${game.away?.abbr || "AWY"} @ ${game.home?.abbr || "HME"}`,
                league: game.league || "",
                statusKey: game.statusKey || "scheduled",
                statusLabel: game.statusLabel || "Watch",
                market: link?.market || "Market",
                line: link?.line || "Line",
                price: link?.price || "--",
                available: Boolean(link?.available),
                url: link?.url || "#",
                note: link?.note || "Verify eligibility, location, and final price before taking action.",
            }));
        });
    };

    const renderMarketBoard = (state = latestSportsState, filter = activeProviderFilter) => {
        if (!marketBoard) return;
        const rows = flattenMarketRows(state).filter((row) => {
            if (filter === "kalshi") return row.providerKey.includes("kalshi") || row.provider.toLowerCase().includes("kalshi");
            if (filter === "sportsbook") return row.kind.toLowerCase().includes("sportsbook");
            if (filter === "live") return row.statusKey === "live" || row.available;
            return true;
        });

        if (marketCount) {
            marketCount.textContent = `${rows.length} market${rows.length === 1 ? "" : "s"}`;
        }

        marketBoard.innerHTML = rows.length
            ? rows
                  .map((row) => {
                      const displayLine = row.price !== "--" ? `${row.line} ${row.price}` : row.line;
                      return `
                        <a class="sports-market-card ${row.available ? "is-live" : ""}" href="${escapeHtml(row.url)}" target="_blank" rel="noopener noreferrer" data-provider-key="${escapeHtml(row.providerKey)}" data-provider-kind="${escapeHtml(row.kind.toLowerCase())}" data-status-key="${escapeHtml(row.statusKey)}" data-available="${row.available ? "true" : "false"}">
                            <span>${escapeHtml(row.provider)} / ${escapeHtml(row.kind)}</span>
                            <strong>${escapeHtml(row.matchup)}</strong>
                            <div>
                                <em>${escapeHtml(row.market)}</em>
                                <b>${escapeHtml(displayLine)}</b>
                            </div>
                            <small>${escapeHtml(row.statusLabel)} / ${escapeHtml(row.note)}</small>
                        </a>
                    `;
                  })
                  .join("")
            : `<div class="sports-market-empty"><strong>No ${escapeHtml(filter)} markets on this board yet.</strong><span>Keep the feed open; Lineforge will repopulate this panel as games, sportsbook links, or prediction markets appear.</span></div>`;
    };

    const fallbackBreakdown = (pick = {}) => {
        const confidence = Number.parseInt(String(pick.confidenceValue || pick.confidence || "58"), 10) || 58;
        return {
            summary: "Research confidence is an informational model estimate. Verify the line, injury news, location eligibility, and final price before relying on the rating.",
            math: [
                { label: "Base probability", value: "50%", detail: "Neutral starting point before model context." },
                { label: "Market signal", value: pick.market || "Monitor", detail: "Spread, total, and provider depth influence confidence." },
                { label: "Model edge", value: pick.edge || "+0.0%", detail: "Estimated gap between Lineforge fair price and available market context." },
                { label: "Stake guidance", value: pick.stake || "0.00u", detail: "Suggested informational stake size with exposure clipping." },
                { label: "Calibration estimate", value: pick.confidence || `${confidence}%`, detail: "Final displayed informational estimate." },
            ],
            factors: Array.isArray(pick.why) ? pick.why : [],
            injuries: [
                {
                    label: "Injury feed",
                    value: "Not connected",
                    detail: "Connect a verified injury provider before treating player availability as live-confirmed.",
                },
            ],
        };
    };

    const renderDrawerCards = (container, items = [], emptyCopy = "No detail available yet.") => {
        if (!container) return;
        const safeItems = Array.isArray(items) ? items.filter(Boolean) : [];
        container.innerHTML = safeItems.length
            ? safeItems
                  .map((item) => {
                      if (typeof item === "string") {
                          return `<div><strong>${escapeHtml(item)}</strong></div>`;
                      }
                      return `
                        <div>
                            <span>${escapeHtml(item.label || item.title || "Signal")}</span>
                            <strong>${escapeHtml(item.value || item.market || "")}</strong>
                            <small>${escapeHtml(item.detail || item.note || "")}</small>
                        </div>
                    `;
                  })
                  .join("")
            : `<div><strong>${escapeHtml(emptyCopy)}</strong></div>`;
    };

    const renderProviderLinks = (links = []) => {
        if (!pickDrawerProviders) return;
        const safeLinks = Array.isArray(links) ? links.slice(0, 8) : [];
        pickDrawerProviders.innerHTML = safeLinks.length
            ? safeLinks
                  .map((link) => {
                      const hasPrice = link?.available && link?.price && link.price !== "--";
                      const detail = hasPrice ? `${link.line || "Line"} ${link.price}` : link?.market || link?.kind || "Open provider";
                      return `
                        <a href="${escapeHtml(link?.url || "#")}" target="_blank" rel="noopener noreferrer" class="${link?.available ? "is-live" : ""}">
                            <span>${escapeHtml(link?.title || "Provider")}</span>
                            <strong>${escapeHtml(detail)}</strong>
                            <small>${escapeHtml(link?.note || "Verify eligibility and final price before taking action.")}</small>
                        </a>
                    `;
                  })
                  .join("")
            : `<div><strong>No provider links attached yet.</strong><small>Connect an odds provider or use the sportsbook app list in the sidebar.</small></div>`;
    };

    const openPickDetail = (index = 0) => {
        if (!pickDrawer) return;
        const predictions = Array.isArray(latestSportsState.predictions) ? latestSportsState.predictions : [];
        const pick = predictions[index] || latestSportsState.topPick || predictions[0] || {};
        const breakdown = pick.breakdown || fallbackBreakdown(pick);

        if (pickDrawerEyebrow) {
            pickDrawerEyebrow.textContent = `${pick.league || "Sports"} / ${pick.market || "Market"} / ${pick.statusLabel || "Watch"}`;
        }
        if (pickDrawerTitle) {
            pickDrawerTitle.textContent = pick.pick || "Prediction breakdown";
        }
        if (pickDrawerReason) {
            pickDrawerReason.textContent = pick.reason || breakdown.summary || "Lineforge is monitoring the board for actionable context.";
        }
        renderDrawerCards(pickDrawerMath, breakdown.math || [], "No math breakdown is available for this pick yet.");
        renderDrawerCards(pickDrawerFactors, breakdown.factors || pick.why || [], "No calibration factors are attached yet.");
        renderDrawerCards(pickDrawerInjuries, breakdown.injuries || [], "No injury or availability context is attached yet.");
        renderProviderLinks(pick.marketLinks || []);

        pickDrawer.hidden = false;
        pickDrawer.setAttribute("aria-hidden", "false");
        document.body.classList.add("sports-drawer-open");
    };

    const closePickDetail = () => {
        if (!pickDrawer) return;
        pickDrawer.hidden = true;
        pickDrawer.setAttribute("aria-hidden", "true");
        document.body.classList.remove("sports-drawer-open");
    };

    document.addEventListener("click", (event) => {
        const viewControl = event.target.closest("[data-sports-view]");
        if (viewControl && !event.target.closest("[data-sports-open-pick]")) {
            event.preventDefault();
            const nextView = viewControl.dataset.sportsView || "dashboard";
            const providerJump = viewControl.dataset.sportsProviderJump;
            showSportsView(nextView);
            if (providerJump) {
                activeProviderFilter = providerJump;
                providerButtons.forEach((item) => item.classList.toggle("is-active", (item.dataset.sportsProviderFilter || "all") === providerJump));
                renderMarketBoard(latestSportsState, activeProviderFilter);
            }
            return;
        }

        const gameCard = event.target.closest("[data-sports-select-game]");
        if (gameCard && !event.target.closest("[data-sports-open-pick]")) {
            selectedGameId = gameCard.dataset.sportsSelectGame || gameCard.dataset.gameId || selectedGameId;
            Array.from(board.querySelectorAll("[data-sports-select-game]")).forEach((card) => {
                card.classList.toggle("is-selected", String(card.dataset.sportsSelectGame || card.dataset.gameId || "") === String(selectedGameId));
            });
            const game = gameById(selectedGameId);
            renderGameTracker(game, predictionForGame(selectedGameId));
            return;
        }

        const openButton = event.target.closest("[data-sports-open-pick]");
        if (openButton) {
            event.preventDefault();
            openPickDetail(Number(openButton.dataset.sportsOpenPick || 0));
            return;
        }

        if (event.target.closest("[data-sports-close-pick]")) {
            event.preventDefault();
            closePickDetail();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && pickDrawer && !pickDrawer.hidden) {
            closePickDetail();
        }
    });

    const refreshSports = async () => {
        if (sportsRefreshInFlight) {
            return;
        }
        sportsRefreshInFlight = true;
        try {
            const response = await fetch(config.endpoint, { credentials: "same-origin" });
            const data = await response.json();
            if (!data.ok || !data.state) {
                return;
            }

            const state = data.state;
            latestSportsState = state;
            if (!selectedGameId || !(state.games || []).some((game) => String(game.id || "") === String(selectedGameId))) {
                selectedGameId = String(state.games?.[0]?.id || "");
            }
            renderGames(state.games || [], state.sourceLabel || "Live scoreboard", state.sourceBadge || "");
            renderGameTracker(gameById(selectedGameId), predictionForGame(selectedGameId));
            renderMarketBoard(state, activeProviderFilter);
            renderTape(state.tape || []);
            renderMetrics(state.metrics || []);
            renderPredictions(state.predictions || []);
            renderTopPick(state.topPick || state.predictions?.[0] || {});
            renderCoverage(state.coverage || {});
            renderProviderGrid(state.marketAccess || {});
            renderBooks(state.books || []);
            renderEdge(state.edgeStack || []);
            renderFactors(state.factors || []);
            renderRules(state.rules || []);
            renderOpportunities(state.opportunities || []);
            renderPerformance(state.performance || []);
            renderAlerts(state.alerts || []);
            renderMarketLine(state.marketHistory || []);
            renderInsight(state.insight || {}, state.predictions?.[0] || {});
            renderTimeline(state.games?.[0]?.history || []);
            if (marketTitle) {
                marketTitle.textContent = state.primaryMarket || "Primary market";
            }
            if (selectedMarket) {
                selectedMarket.textContent = state.selectedMarket || state.predictions?.[0]?.pick || "Market";
            }
            if (refreshDetail && state.marketAccess?.note) {
                refreshDetail.textContent = state.marketAccess.note;
            }
        } catch (_error) {
            if (sourceLabel) {
                sourceLabel.textContent = "Live refresh paused";
            }
        } finally {
            sportsRefreshInFlight = false;
        }
    };

    const interval = Math.max(5, Number(config.refreshSeconds || 60)) * 1000;
    showSportsView(activeSportsView);
    renderGameTracker(gameById(selectedGameId), predictionForGame(selectedGameId));
    renderMarketBoard(latestSportsState, activeProviderFilter);
    window.setTimeout(refreshSports, 1000);
    window.setInterval(refreshSports, interval);
})();

(() => {
    const config = window.AEGIS_SPORTS;
    const root = document.querySelector(".betedge-shell");

    if (!config || !root) {
        return;
    }

    const board = document.getElementById("sportsLiveBoard");
    const dashboardHero = root.querySelector("[data-sports-dashboard-hero]");
    const expandedBoard = document.getElementById("sportsLiveExpanded");
    const tape = document.getElementById("sportsTape");
    const sourceLabel = document.getElementById("sportsSourceLabel");
    const dashboardPredictions = document.getElementById("sportsPredictionGrid");
    const expandedPredictions = document.getElementById("sportsPredictionExpanded");
    const marketBoard = document.getElementById("sportsMarketBoard");
    const marketCount = document.getElementById("sportsMarketCount");
    const boardCount = document.getElementById("sportsBoardCount");
    const factorGrid = document.getElementById("sportsFactorGrid");
    const coverageGrid = document.getElementById("sportsCoverageGrid");
    const providerGrid = document.getElementById("sportsProviderGrid");
    const dataSummary = document.getElementById("lineforgeDataSummary");
    const dataModules = document.getElementById("lineforgeDataModules");
    const inferenceBoard = document.getElementById("lineforgeInferenceBoard");
    const internalSystems = document.getElementById("lineforgeInternalSystems");
    const operatorWorkflows = document.getElementById("lineforgeOperatorWorkflows");
    const evolutionSummary = document.getElementById("lineforgeEvolutionSummary");
    const modelCandidates = document.getElementById("lineforgeModelCandidates");
    const marketRegimeBoard = document.getElementById("lineforgeMarketRegimeBoard");
    const signalObjectsBoard = document.getElementById("lineforgeSignalObjects");
    const adaptiveSummary = document.getElementById("lineforgeAdaptiveSummary");
    const adaptiveLayersBoard = document.getElementById("lineforgeAdaptiveLayers");
    const adaptiveOrchestration = document.getElementById("lineforgeAdaptiveOrchestration");
    const selfEvaluationBoard = document.getElementById("lineforgeSelfEvaluation");
    const historicalPatternsBoard = document.getElementById("lineforgeHistoricalPatterns");
    const operatorMemoryBoard = document.getElementById("lineforgeOperatorMemory");
    const resilienceSystemsBoard = document.getElementById("lineforgeResilienceSystems");
    const adaptiveExplanationsBoard = document.getElementById("lineforgeAdaptiveExplanations");
    const generalizedSummary = document.getElementById("lineforgeGeneralizedSummary");
    const domainAdaptersBoard = document.getElementById("lineforgeDomainAdapters");
    const universalPrimitivesBoard = document.getElementById("lineforgeUniversalPrimitives");
    const generalizedOrchestrationBoard = document.getElementById("lineforgeGeneralizedOrchestration");
    const contextualMemoryBoard = document.getElementById("lineforgeContextualMemory");
    const selfOptimizationBoard = document.getElementById("lineforgeSelfOptimization");
    const adaptiveWorkspacesBoard = document.getElementById("lineforgeAdaptiveWorkspaces");
    const generalizedGovernanceBoard = document.getElementById("lineforgeGeneralizedGovernance");
    const bookGrid = document.getElementById("sportsBookGrid");
    const opportunityBoard = document.getElementById("sportsOpportunityBoard");
    const edgeStack = document.getElementById("sportsEdgeStack");
    const rulesBoard = document.getElementById("sportsRulesBoard");
    const performanceBoard = document.getElementById("sportsPerformanceBoard");
    const modelSourceGrid = document.getElementById("sportsModelSourceGrid");
    const alertsBoard = document.getElementById("sportsAlertsBoard");
    const insightCopy = document.getElementById("sportsInsightCopy");
    const timeline = document.getElementById("sportsConfidenceTimeline");
    const marketTitle = document.getElementById("sportsMarketTitle");
    const marketLine = document.getElementById("sportsMarketLine");
    const slipList = document.getElementById("sportsSlipList");
    const slipCount = document.getElementById("sportsSlipCount");
    const parlayLabel = document.getElementById("sportsParlayLabel");
    const parlayOdds = document.getElementById("sportsParlayOdds");
    const totalWager = document.getElementById("sportsTotalWager");
    const totalWin = document.getElementById("sportsTotalWin");
    const placeBetButton = document.getElementById("sportsPlaceBet");
    const slipNote = document.getElementById("sportsSlipNote");
    const myBetsList = document.getElementById("sportsMyBetsList");
    const pickDrawer = document.getElementById("sportsPickDrawer");
    const pickDrawerTitle = document.getElementById("sportsPickDrawerTitle");
    const pickDrawerEyebrow = document.getElementById("sportsPickDrawerEyebrow");
    const pickDrawerReason = document.getElementById("sportsPickDrawerReason");
    const pickDrawerScore = document.getElementById("sportsPickDrawerScore");
    const pickDrawerComparison = document.getElementById("sportsPickDrawerComparison");
    const pickDrawerMath = document.getElementById("sportsPickDrawerMath");
    const pickDrawerReadiness = document.getElementById("sportsPickDrawerReadiness");
    const pickDrawerFactors = document.getElementById("sportsPickDrawerFactors");
    const pickDrawerInjuries = document.getElementById("sportsPickDrawerInjuries");
    const pickDrawerProviders = document.getElementById("sportsPickDrawerProviders");
    const pickDrawerNoBet = document.getElementById("sportsPickDrawerNoBet");
    const pickDrawerManual = document.getElementById("sportsPickDrawerManual");
    const filterSelect = document.getElementById("sportsLeagueSelect");
    const gameSearchInput = document.getElementById("sportsGameSearch");
    const decisionPick = document.getElementById("sportsDecisionPick");
    const decisionReason = document.getElementById("sportsDecisionReason");
    const decisionMetrics = document.getElementById("sportsDecisionMetrics");
    const decisionGrade = document.getElementById("sportsDecisionGrade");
    const decisionChecks = document.getElementById("sportsDecisionChecks");
    const decisionContext = document.getElementById("sportsDecisionContext");
    const decisionBoard = document.getElementById("sportsDecisionBoard");
    const decisionBoardCount = document.getElementById("sportsDecisionBoardCount");
    const providerSetupForm = document.getElementById("sportsProviderSetupForm");
    const providerStatus = document.getElementById("sportsProviderStatus");
    const providerReadiness = document.getElementById("sportsProviderReadiness");
    const providerSaveResult = document.getElementById("sportsProviderSaveResult");
    const executionSummary = document.getElementById("lineforgeExecutionSummary");
    const executionMode = document.getElementById("lineforgeExecutionMode");
    const executionProviders = document.getElementById("lineforgeExecutionProviders");
    const executionBalances = document.getElementById("lineforgeExecutionBalances");
    const executionRules = document.getElementById("lineforgeExecutionRules");
    const executionPositions = document.getElementById("lineforgeExecutionPositions");
    const executionOrders = document.getElementById("lineforgeExecutionOrders");
    const executionAudit = document.getElementById("lineforgeExecutionAudit");
    const executionResult = document.getElementById("lineforgeExecutionResult");
    const kalshiForm = document.getElementById("lineforgeKalshiForm");
    const riskForm = document.getElementById("lineforgeRiskForm");
    const ruleForm = document.getElementById("lineforgeRuleForm");
    const kalshiStatus = document.getElementById("lineforgeKalshiStatus");
    const riskStatus = document.getElementById("lineforgeRiskStatus");
    const ruleStatus = document.getElementById("lineforgeRuleStatus");
    const arbSummary = document.getElementById("lineforgeArbSummary");
    const arbHealthLabel = document.getElementById("lineforgeArbHealthLabel");
    const arbRefresh = document.getElementById("lineforgeArbRefresh");
    const arbProviderHealth = document.getElementById("lineforgeArbProviderHealth");
    const arbTable = document.getElementById("lineforgeArbTable");
    const arbMiddleBoard = document.getElementById("lineforgeMiddleBoard");
    const arbPositiveEvBoard = document.getElementById("lineforgePositiveEvBoard");
    const arbRejectedBoard = document.getElementById("lineforgeRejectedBoard");
    const arbDrawer = document.getElementById("lineforgeArbDrawer");
    const arbDrawerTitle = document.getElementById("lineforgeArbDrawerTitle");
    const arbDrawerMeta = document.getElementById("lineforgeArbDrawerMeta");
    const arbDrawerOdds = document.getElementById("lineforgeArbDrawerOdds");
    const arbDrawerStake = document.getElementById("lineforgeArbDrawerStake");
    const arbDrawerConsensus = document.getElementById("lineforgeArbDrawerConsensus");
    const arbDrawerWarnings = document.getElementById("lineforgeArbDrawerWarnings");
    const arbDrawerChecklist = document.getElementById("lineforgeArbDrawerChecklist");
    const arbDrawerExport = document.getElementById("lineforgeArbDrawerExport");
    const workspaceDock = document.getElementById("lineforgeWorkspaceDock");

    let latestSportsState = window.AEGIS_SPORTS_STATE || {};
    let latestProviderSettings = config.providerSettings || {};
    let latestExecutionState = config.executionState || {};
    let activeSportsFilter = "all";
    let activeSportsSearch = "";
    let activeSportsView = "dashboard";
    let activeProviderFilter = "all";
    let selectedGameId = String(latestSportsState.games?.[0]?.id || "");
    let slipItems = [];
    let sportsRefreshInFlight = false;
    let executionRefreshInFlight = false;

    const storageKey = "aegis.betedge.paperBets";
    const workspaceStorageKey = "lineforge.workspace.layout.v1";
    let workspaceState = readWorkspaceState();
    const money = (value) => `$${(Number(value) || 0).toFixed(2)}`;
    const safeHref = (value) => {
        const raw = String(value || "#").trim();
        if (!raw || raw === "#") return "#";
        if (raw.startsWith("/") || raw.startsWith("#")) return raw;
        try {
            const parsed = new URL(raw, window.location.origin);
            return ["http:", "https:"].includes(parsed.protocol) ? parsed.href : "#";
        } catch (_error) {
            return "#";
        }
    };
    const teamMark = (team = {}) => String(team.abbr || team.short || team.name || "TM").replace(/[^A-Za-z0-9]/g, "").toUpperCase().slice(0, 2) || "TM";
    const games = () => (Array.isArray(latestSportsState.games) ? latestSportsState.games : []);
    const predictions = () => (Array.isArray(latestSportsState.predictions) ? latestSportsState.predictions : []);
    const readBets = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(storageKey) || "[]");
            return Array.isArray(parsed) ? parsed : [];
        } catch (_error) {
            return [];
        }
    };
    const writeBets = (items) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(items.slice(0, 25)));
        } catch (_error) {
            // localStorage can be blocked in private contexts; current-session slip math still works.
        }
    };
    function defaultWorkspaceState() {
        return {
            mode: "command",
            density: "balanced",
            collapsed: {},
            pinned: {},
        };
    }
    function readWorkspaceState() {
        const fallback = defaultWorkspaceState();
        try {
            const parsed = JSON.parse(localStorage.getItem(workspaceStorageKey) || "{}");
            const mode = ["command", "live", "analyst", "monitoring", "signals", "markets", "execution"].includes(parsed.mode) ? parsed.mode : fallback.mode;
            const density = ["compact", "balanced", "expanded"].includes(parsed.density) ? parsed.density : fallback.density;
            return {
                mode,
                density,
                collapsed: parsed.collapsed && typeof parsed.collapsed === "object" ? parsed.collapsed : {},
                pinned: parsed.pinned && typeof parsed.pinned === "object" ? parsed.pinned : {},
            };
        } catch (_error) {
            return fallback;
        }
    }
    function writeWorkspaceState() {
        try {
            localStorage.setItem(workspaceStorageKey, JSON.stringify(workspaceState));
        } catch (_error) {
            // Workspace preferences are enhancement-only; the app remains usable without storage.
        }
    }
    const workspaceTitleForCard = (card, index = 0) => {
        const source = card.querySelector(".betedge-card-head strong, .lineforge-section-title, .lineforge-execution-kicker, h1, strong");
        const text = source?.textContent?.trim().replace(/\s+/g, " ");
        return text || `Workspace Panel ${index + 1}`;
    };
    const workspacePanelId = (card, index = 0) => {
        if (card.dataset.workspacePanelId) {
            return card.dataset.workspacePanelId;
        }
        const title = workspaceTitleForCard(card, index);
        const slug = title
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .slice(0, 42) || "panel";
        card.dataset.workspaceTitle = title;
        card.dataset.workspacePanelId = `${slug}-${index}`;
        return card.dataset.workspacePanelId;
    };
    const workspaceCards = () => Array.from(root.querySelectorAll(".betedge-card"))
        .filter((card) => !card.closest(".sports-reference-drawer, .lineforge-arb-drawer"));
    const isPrimaryWorkspaceCard = (card) => card.matches(
        "[data-sports-dashboard-hero], .betedge-decision-board-card, .betedge-live-card, .betedge-picks-card, .lineforge-arb-command, .lineforge-execution-command"
    );
    const ensureWorkspaceControls = () => {
        workspaceCards().forEach((card, index) => {
            const id = workspacePanelId(card, index);
            card.classList.add("lineforge-workspace-card");
            card.classList.toggle("is-workspace-primary", isPrimaryWorkspaceCard(card));
            const head = card.querySelector(":scope > .betedge-card-head");
            if (!head || head.querySelector(".lineforge-panel-controls")) {
                return;
            }
            const controls = document.createElement("div");
            controls.className = "lineforge-panel-controls";
            controls.innerHTML = `
                <button type="button" data-workspace-pin="${escapeHtml(id)}" title="Pin panel">Pin</button>
                <button type="button" data-workspace-collapse="${escapeHtml(id)}" title="Minimize panel">Min</button>
            `;
            head.appendChild(controls);
        });
    };
    const renderWorkspaceDock = () => {
        if (!workspaceDock) return;
        const cards = workspaceCards();
        const collapsedCards = cards.filter((card, index) => workspaceState.collapsed[workspacePanelId(card, index)]);
        workspaceDock.innerHTML = collapsedCards.map((card, index) => {
            const id = card.dataset.workspacePanelId || workspacePanelId(card, index);
            const title = card.dataset.workspaceTitle || workspaceTitleForCard(card, index);
            return `<button type="button" data-workspace-restore="${escapeHtml(id)}"><span>${escapeHtml(title)}</span><b>Restore</b></button>`;
        }).join("");
    };
    const applyWorkspaceState = () => {
        root.dataset.workspaceCurrentMode = workspaceState.mode;
        root.dataset.workspaceCurrentDensity = workspaceState.density;
        root.dataset.activeSportsView = activeSportsView;
        root.querySelectorAll("[data-workspace-mode]").forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.workspaceMode || "") === workspaceState.mode);
        });
        root.querySelectorAll("[data-workspace-density]").forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.workspaceDensity || "") === workspaceState.density);
        });
        workspaceCards().forEach((card, index) => {
            const id = workspacePanelId(card, index);
            const collapsed = Boolean(workspaceState.collapsed[id]);
            const pinned = Boolean(workspaceState.pinned[id]);
            card.classList.toggle("is-collapsed", collapsed);
            card.classList.toggle("is-pinned", pinned);
            card.querySelectorAll("[data-workspace-collapse]").forEach((button) => {
                button.textContent = collapsed ? "Open" : "Min";
                button.setAttribute("aria-pressed", collapsed ? "true" : "false");
            });
            card.querySelectorAll("[data-workspace-pin]").forEach((button) => {
                button.classList.toggle("is-active", pinned);
                button.setAttribute("aria-pressed", pinned ? "true" : "false");
            });
        });
        renderWorkspaceDock();
        bindWorkspaceControls();
    };
    function bindWorkspaceControls() {
        root.querySelectorAll("[data-workspace-mode]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                setWorkspaceMode(control.dataset.workspaceMode || "command");
            });
        });
        root.querySelectorAll("[data-workspace-density]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                setWorkspaceDensity(control.dataset.workspaceDensity || "balanced");
            });
        });
        root.querySelectorAll("[data-workspace-collapse]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                const id = control.dataset.workspaceCollapse || "";
                setWorkspacePanelCollapsed(id, !workspaceState.collapsed[id]);
            });
        });
        root.querySelectorAll("[data-workspace-pin]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleWorkspacePanelPinned(control.dataset.workspacePin || "");
            });
        });
        root.querySelectorAll("[data-workspace-restore]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                setWorkspacePanelCollapsed(control.dataset.workspaceRestore || "", false);
            });
        });
        root.querySelectorAll("[data-workspace-collapse-secondary]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                collapseSecondaryWorkspacePanels();
            });
        });
        root.querySelectorAll("[data-workspace-expand-all]").forEach((control) => {
            if (control.dataset.workspaceBound === "true") return;
            control.dataset.workspaceBound = "true";
            control.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                expandAllWorkspacePanels();
            });
        });
    }
    const syncWorkspace = () => {
        ensureWorkspaceControls();
        bindWorkspaceControls();
        applyWorkspaceState();
    };
    const setWorkspaceMode = (mode) => {
        if (!["command", "live", "analyst", "monitoring", "signals", "markets", "execution"].includes(mode)) return;
        workspaceState.mode = mode;
        writeWorkspaceState();
        const targetView = {
            command: "dashboard",
            live: "live",
            analyst: "analytics",
            monitoring: "live",
            signals: "picks",
            markets: "arbitrage",
            execution: "execution",
        }[mode];
        if (targetView && activeSportsView !== targetView) {
            showSportsView(targetView);
            return;
        }
        applyWorkspaceState();
    };
    const setWorkspaceDensity = (density) => {
        if (!["compact", "balanced", "expanded"].includes(density)) return;
        workspaceState.density = density;
        writeWorkspaceState();
        applyWorkspaceState();
    };
    const setWorkspacePanelCollapsed = (id, collapsed) => {
        if (!id) return;
        if (collapsed) {
            workspaceState.collapsed[id] = true;
        } else {
            delete workspaceState.collapsed[id];
        }
        writeWorkspaceState();
        applyWorkspaceState();
    };
    const toggleWorkspacePanelPinned = (id) => {
        if (!id) return;
        if (workspaceState.pinned[id]) {
            delete workspaceState.pinned[id];
        } else {
            workspaceState.pinned[id] = true;
            delete workspaceState.collapsed[id];
        }
        writeWorkspaceState();
        applyWorkspaceState();
    };
    const collapseSecondaryWorkspacePanels = () => {
        workspaceCards().forEach((card, index) => {
            const id = workspacePanelId(card, index);
            if (!card.classList.contains("is-workspace-primary") && !workspaceState.pinned[id]) {
                workspaceState.collapsed[id] = true;
            }
        });
        writeWorkspaceState();
        applyWorkspaceState();
    };
    const expandAllWorkspacePanels = () => {
        workspaceState.collapsed = {};
        writeWorkspaceState();
        applyWorkspaceState();
    };
    const setSlipNote = (message) => {
        if (slipNote) {
            slipNote.textContent = message;
        }
    };
    const parseAmericanOdds = (value) => {
        const match = String(value || "").match(/[+-]?\d+/);
        const odds = match ? Number.parseInt(match[0], 10) : NaN;
        return Number.isFinite(odds) && odds !== 0 ? odds : -110;
    };
    const decimalFromAmerican = (odds) => {
        const price = parseAmericanOdds(odds);
        return price > 0 ? 1 + price / 100 : 1 + 100 / Math.abs(price);
    };
    const americanFromDecimal = (decimal) => {
        const value = Number(decimal) || 1;
        if (value <= 1) return "--";
        if (value >= 2) return `+${Math.round((value - 1) * 100)}`;
        return `${Math.round(-100 / (value - 1))}`;
    };
    const toWin = (odds, stake) => (Number(stake) || 0) * (decimalFromAmerican(odds) - 1);
    const pickOdds = (pick = {}) => {
        const preferred = pick.odds || pick.fairOdds || "-110";
        const match = String(preferred).match(/[+-]?\d+/);
        return match ? match[0] : "-110";
    };
    const teamAvatar = (team = {}) => {
        const label = escapeHtml(team.abbr || team.name || "Team");
        return team.logo
            ? `<img src="${escapeHtml(team.logo)}" alt="${label}">`
            : `<span>${escapeHtml(teamMark(team))}</span>`;
    };
    const predictionIndexForGame = (gameId) => predictions().findIndex((prediction) => String(prediction.gameId || "") === String(gameId || ""));
    const normalizeFilterText = (value) => String(value || "")
        .toLowerCase()
        .replace(/&/g, " and ")
        .replace(/[_-]+/g, " ")
        .replace(/[^a-z0-9]+/g, " ")
        .trim();
    const compactFilterText = (value) => normalizeFilterText(value).replace(/\s+/g, "");
    const sportFilterAliases = {
        nba: ["nba", "basketball nba", "national basketball association", "fallback nba"],
        nfl: ["nfl", "americanfootball nfl", "american football nfl", "football nfl", "national football league", "fallback nfl"],
        mlb: ["mlb", "baseball mlb", "major league baseball", "fallback mlb"],
        nhl: ["nhl", "icehockey nhl", "ice hockey nhl", "national hockey league", "fallback nhl"],
        ufc: ["ufc", "mma", "mixed martial arts", "combat sports"],
        soccer: ["soccer", "mls", "major league soccer", "premier league", "epl", "uefa", "fifa", "la liga", "laliga", "bundesliga", "serie a", "ligue 1"],
        tennis: ["tennis", "atp", "wta", "grand slam"],
        esports: ["esports", "e sports", "league of legends", "lol", "valorant", "counter strike", "csgo", "dota"],
    };
    const aliasesForFilter = (value) => {
        const target = normalizeFilterText(value);
        return Array.from(new Set([target, ...(sportFilterAliases[target] || [])]))
            .map(normalizeFilterText)
            .filter(Boolean);
    };
    const matchesFilterValue = (values, filterValue) => {
        const targets = aliasesForFilter(filterValue);
        return values.some((value) => {
            const source = normalizeFilterText(value);
            const compactSource = compactFilterText(value);
            const sourceTokens = source.split(" ").filter(Boolean);
            if (!source) return false;
            return targets.some((target) => {
                const compactTarget = compactFilterText(target);
                const canUsePhraseMatch = target.length > 4 || compactTarget.length > 4;
                return source === target
                    || compactSource === compactTarget
                    || sourceTokens.includes(target)
                    || (canUsePhraseMatch && (source.includes(target) || compactSource.includes(compactTarget)));
            });
        });
    };
    const activeFilterParts = () => {
        const raw = String(activeSportsFilter || "all");
        if (raw === "all") {
            return { type: "all", value: "all" };
        }
        const pieces = raw.split(":");
        if (pieces.length < 2) {
            return { type: "status", value: raw };
        }
        return {
            type: normalizeFilterText(pieces.shift()),
            value: pieces.join(":"),
        };
    };
    const activeFilterLabel = () => {
        const button = Array.from(root.querySelectorAll("[data-sports-filter]")).find((item) => (item.dataset.sportsFilter || "all") === activeSportsFilter);
        return button?.textContent?.trim().replace(/\s+/g, " ") || (activeSportsFilter === "all" ? "All Sports" : activeSportsFilter.replace(/^[^:]+:/, "").toUpperCase());
    };
    const isLiveGame = (game = {}) => String(game.statusKey || "").toLowerCase() === "live";
    const gameMatchesActiveFilter = (game = {}) => {
        const filter = activeFilterParts();
        if (filter.type === "all") {
            return true;
        }
        if (filter.type === "league") {
            return matchesFilterValue([game.league, game.leagueKey, game.id], filter.value);
        }
        if (filter.type === "group") {
            return matchesFilterValue([game.sportGroup, game.league, game.leagueKey], filter.value);
        }
        return matchesFilterValue([game.statusKey, game.statusLabel, game.statusTone], filter.value);
    };
    const gameSearchText = (game = {}) => normalizeFilterText([
        game.matchup,
        game.league,
        game.leagueKey,
        game.sportGroup,
        game.statusLabel,
        game.clock,
        game.detail,
        game.venue,
        game.away?.name,
        game.away?.abbr,
        game.away?.short,
        game.home?.name,
        game.home?.abbr,
        game.home?.short,
    ].filter(Boolean).join(" "));
    const gameMatchesSearch = (game = {}) => {
        const query = normalizeFilterText(activeSportsSearch);
        if (!query) {
            return true;
        }

        const haystack = gameSearchText(game);
        const compactHaystack = compactFilterText(haystack);
        return query.split(" ").filter(Boolean).every((term) => {
            const compactTerm = compactFilterText(term);
            return haystack.includes(term) || (compactTerm.length > 1 && compactHaystack.includes(compactTerm));
        });
    };
    const filteredGames = () => games().filter((game) => gameMatchesActiveFilter(game) && gameMatchesSearch(game));
    const filteredPredictions = () => {
        const matchingIds = new Set(filteredGames().map((game) => String(game.id || "")));
        const indexed = predictions()
            .map((prediction, sourceIndex) => ({ prediction, sourceIndex }))
            .filter(({ prediction }) => matchingIds.has(String(prediction.gameId || "")));
        const indexedIds = new Set(indexed.map(({ prediction }) => String(prediction.gameId || "")));
        const gameBacked = filteredGames()
            .filter((game) => !indexedIds.has(String(game.id || "")) && game.prediction && typeof game.prediction === "object")
            .map((game) => ({ prediction: game.prediction, gameId: String(game.id || "") }));

        if (indexed.length || activeSportsFilter !== "all" || activeSportsSearch) {
            return indexed.concat(gameBacked);
        }

        return predictions().map((prediction, sourceIndex) => ({ prediction, sourceIndex }));
    };
    const emptyEventsState = () => {
        const label = escapeHtml(activeFilterLabel());
        return `
            <div class="betedge-empty-state" data-sports-empty>
                <strong>No ${label} games on this board</strong>
                <span>Try another sport, clear the search, or keep the feed open while Lineforge refreshes.</span>
            </div>
        `;
    };
    const emptyLiveEventsState = () => {
        const label = activeSportsFilter === "all" ? "live" : `${activeFilterLabel()} live`;
        return `
            <div class="betedge-empty-state" data-sports-empty>
                <strong>No ${escapeHtml(label)} games right now</strong>
                <span>Upcoming and final games are still available in View All.</span>
            </div>
        `;
    };

    const renderEventCard = (game = {}) => {
        const predictionIndex = predictionIndexForGame(game.id || "");
        const selected = String(game.id || "") === String(selectedGameId || "");
        const away = game.away || {};
        const home = game.home || {};
        const openAttribute = predictionIndex >= 0
            ? `data-sports-open-pick="${escapeHtml(predictionIndex)}"`
            : `data-sports-open-game="${escapeHtml(game.id || "")}"`;
        return `
            <article class="betedge-event-card ${selected ? "is-selected" : ""}" data-sports-select-game="${escapeHtml(game.id || "")}" data-game-id="${escapeHtml(game.id || "")}" data-status-key="${escapeHtml(game.statusKey || "scheduled")}" data-sport-group="${escapeHtml(game.sportGroup || "Sports")}" data-league="${escapeHtml(game.league || "")}" data-league-key="${escapeHtml(game.leagueKey || "")}">
                <div class="betedge-event-head">
                    <span>${escapeHtml(game.league || "League")}</span>
                    <em class="tone-${escapeHtml(game.statusTone || "scheduled")}">${escapeHtml(game.clock || game.statusLabel || "Watch")}</em>
                </div>
                <div class="betedge-team-row">
                    <div>${teamAvatar(away)}<strong>${escapeHtml(away.name || away.abbr || "Away")}</strong></div>
                    <b>${escapeHtml(away.score ?? "0")}</b>
                </div>
                <div class="betedge-team-row">
                    <div>${teamAvatar(home)}<strong>${escapeHtml(home.name || home.abbr || "Home")}</strong></div>
                    <b>${escapeHtml(home.score ?? "0")}</b>
                </div>
                <div class="betedge-market-lines">
                    <span>Spread <b>${escapeHtml(game.spread?.favoriteLine || "--")}</b><b>${escapeHtml(game.spread?.otherLine || "--")}</b></span>
                    <span>Total <b>${escapeHtml(game.total?.over || "--")}</b><b>${escapeHtml(game.total?.under || "--")}</b></span>
                </div>
                <button class="betedge-card-hit" type="button" ${openAttribute} title="Open AI breakdown"></button>
            </article>
        `;
    };

    const filterMatches = (card) => {
        const filter = activeFilterParts();
        if (filter.type === "all") {
            return true;
        }
        if (filter.type === "league") {
            return matchesFilterValue([card.dataset.league, card.dataset.leagueKey, card.dataset.gameId], filter.value);
        }
        if (filter.type === "group") {
            return matchesFilterValue([card.dataset.sportGroup, card.dataset.league, card.dataset.leagueKey], filter.value);
        }
        return matchesFilterValue([card.dataset.statusKey, card.dataset.statusTone], filter.value);
    };

    const applySportsFilter = () => {
        root.querySelectorAll(".betedge-event-card").forEach((card) => {
            card.hidden = !filterMatches(card);
        });
    };

    const gameStatusGroup = (game = {}) => {
        const status = String(game.statusKey || "").toLowerCase();
        if (status === "live") return "live";
        if (status === "final") return "final";
        return "upcoming";
    };
    const renderExpandedSections = (items = []) => {
        if (!items.length) {
            return emptyEventsState();
        }
        const sections = [
            { key: "live", title: "Live Now", detail: "Games currently in progress" },
            { key: "upcoming", title: "Upcoming", detail: "Scheduled games and pregame markets" },
            { key: "final", title: "Final", detail: "Completed games kept for review" },
        ];
        return sections.map((section) => {
            const sectionGames = items.filter((game) => gameStatusGroup(game) === section.key);
            if (!sectionGames.length) return "";
            const gameNoun = sectionGames.length === 1 ? "game" : "games";
            return `
                <section class="betedge-event-section" data-event-status-section="${escapeHtml(section.key)}">
                    <div class="betedge-event-section-head">
                        <div>
                            <strong>${escapeHtml(section.title)}</strong>
                            <small>${escapeHtml(section.detail)}</small>
                        </div>
                        <span>${escapeHtml(sectionGames.length)} ${gameNoun}</span>
                    </div>
                    <div class="betedge-event-section-grid">
                        ${sectionGames.map(renderEventCard).join("")}
                    </div>
                </section>
            `;
        }).join("");
    };

    const renderGames = () => {
        const items = games();
        const matchingItems = filteredGames();
        const dashboardItems = matchingItems.filter(isLiveGame).slice(0, 5);
        if (board) {
            board.innerHTML = dashboardItems.length ? dashboardItems.map(renderEventCard).join("") : emptyLiveEventsState();
        }
        if (expandedBoard) {
            expandedBoard.innerHTML = renderExpandedSections(matchingItems);
        }
        if (boardCount) {
            const filteredGameNoun = matchingItems.length === 1 ? "game" : "games";
            boardCount.textContent = activeSportsFilter === "all"
                ? `${items.length} games loaded`
                : `${matchingItems.length} ${activeFilterLabel()} ${filteredGameNoun}`;
        }
        if (sourceLabel) {
            sourceLabel.textContent = latestSportsState.sourceBadge || "Feed";
            sourceLabel.title = latestSportsState.sourceLabel || "";
        }
        applySportsFilter();
    };

    const setSportsFilter = (filterControl) => {
        activeSportsFilter = filterControl?.dataset?.sportsFilter || "all";
        root.querySelectorAll("[data-sports-filter]").forEach((button) => {
            button.classList.toggle("is-active", button === filterControl);
        });
        if (filterSelect && filterSelect.value !== activeSportsFilter) {
            filterSelect.value = activeSportsFilter;
        }
        renderGames();
        renderPredictions();
    };

    const renderPredictionsInto = (container, items = [], limit = 8, firstColumn = "pick") => {
        if (!container) return;
        const rows = items.slice(0, limit).map((entry, index) => {
            const item = entry?.prediction || entry || {};
            const sourceIndex = Number.isInteger(entry?.sourceIndex) ? entry.sourceIndex : index;
            const hasSourceIndex = Number.isInteger(entry?.sourceIndex);
            const gameId = String(entry?.gameId || item.gameId || "");
            const openAttribute = hasSourceIndex
                ? `data-sports-open-pick="${escapeHtml(sourceIndex)}"`
                : `data-sports-open-game="${escapeHtml(gameId)}"`;
            const confidence = Number.parseInt(String(item.confidenceValue || item.confidence || "50"), 10) || 50;
            const canBet = item.canBet !== false;
            const primaryText = firstColumn === "winner" ? predictionWinner(item) : item.pick || "Pick";
            const primaryMeta = firstColumn === "winner" ? predictionWinnerMeta(item) : "";
            return `
                <article class="betedge-pick-row" data-prediction-index="${escapeHtml(sourceIndex)}" data-can-bet="${canBet ? "true" : "false"}">
                    <div class="betedge-pick-main"><i></i><div><strong>${escapeHtml(primaryText)}</strong>${primaryMeta ? `<small>${escapeHtml(primaryMeta)}</small>` : ""}</div></div>
                    <div>${escapeHtml(item.matchup || "Matchup")}</div>
                    <div>${escapeHtml(item.market || "Market")}</div>
                    <div class="betedge-confidence"><span><i style="width: ${escapeHtml(confidence)}%"></i></span><b>${escapeHtml(item.confidence || `${confidence}%`)}</b></div>
                    <div><strong>${escapeHtml(item.odds || item.fairOdds || "--")}</strong></div>
                    <div class="up">${escapeHtml(item.edge || "+0.0%")}</div>
                    <div class="up">${escapeHtml(item.expectedValue || "$0.00")}</div>
                    <div class="betedge-pick-actions">
                        <button type="button" ${openAttribute} title="Open details"></button>
                    </div>
                </article>
            `;
        }).join("");

        container.innerHTML = `
            <div class="betedge-picks-head">
                <span>${firstColumn === "winner" ? "Predicted Winner" : "Pick"}</span>
                <span>Match</span>
                <span>Market</span>
                <span>Research confidence</span>
                <span>Odds</span>
                <span>Edge</span>
                <span>Expected Value</span>
                <span></span>
            </div>
            ${rows || `<article class="betedge-pick-row"><div class="betedge-pick-main"><i></i><strong>No model picks yet</strong></div></article>`}
        `;
    };

    const teamAliases = (team = {}) => [team.name, team.abbr, team.short]
        .map((value) => normalizeFilterText(value))
        .filter(Boolean);
    const pickMatchesTeam = (pick, team = {}) => {
        const normalizedPick = normalizeFilterText(pick);
        const compactPick = compactFilterText(pick);
        const tokens = normalizedPick.split(" ").filter(Boolean);
        return teamAliases(team).some((alias) => {
            const compactAlias = compactFilterText(alias);
            return tokens.includes(alias)
                || compactPick === compactAlias
                || (alias.length > 3 && (normalizedPick.includes(alias) || compactPick.includes(compactAlias)));
        });
    };
    const predictionWinner = (item = {}) => {
        const direct = predictionWinnerLabel(item);
        if (direct && direct !== "No clear side") {
            return direct;
        }

        const game = games().find((entry) => String(entry.id || "") === String(item.gameId || ""));
        if (game) {
            if (String(game.statusKey || "").toLowerCase() === "final") {
                const winner = game.home?.winner ? game.home : game.away;
                return winner?.name || winner?.abbr || item.pick || "Predicted winner";
            }
            if (pickMatchesTeam(item.pick, game.away) && !pickMatchesTeam(item.pick, game.home)) {
                return game.away?.name || game.away?.abbr || item.pick || "Predicted winner";
            }
            if (pickMatchesTeam(item.pick, game.home) && !pickMatchesTeam(item.pick, game.away)) {
                return game.home?.name || game.home?.abbr || item.pick || "Predicted winner";
            }
        }
        return String(item.predictedWinner || item.winner || item.pick || "Predicted winner")
            .replace(/^postgame review:\s*/i, "")
            .replace(/^watch\s+/i, "")
            .replace(/\s+[+-]\d+.*$/, "")
            .trim() || "Predicted winner";
    };

    const decisionGradeForConfidence = (confidence) => {
        if (confidence >= 76) return "A";
        if (confidence >= 68) return "B+";
        if (confidence >= 61) return "B";
        return "C";
    };
    const decisionTone = (ok) => ok ? "ok" : "warn";
    const readinessForPick = (pick = {}) => {
        if (pick.readiness && typeof pick.readiness === "object") {
            return pick.readiness;
        }

        const confidence = Number.parseInt(String(pick.confidenceValue || pick.confidence || "58"), 10) || 58;
        const statusKey = String(pick.statusKey || "scheduled").toLowerCase();
        const links = Array.isArray(pick.marketLinks) ? pick.marketLinks : [];
        const dataQuality = pick.dataQuality && typeof pick.dataQuality === "object" ? pick.dataQuality : {};
        const dataQualityScore = Number(dataQuality.score || 0);
        const sportsbookLines = links.filter((link) => link?.available && String(link?.kind || "").toLowerCase() === "sportsbook" && String(link?.price || "--") !== "--");
        const blockers = [];
        if (statusKey === "final") {
            blockers.push({ label: "Closed market", value: "No action", detail: "The game is final, so this is audit-only.", tone: "bad", status: "Needs setup" });
        }
        if (confidence < 68) {
            blockers.push({ label: "Research threshold", value: `${confidence}%`, detail: "Lineforge keeps this out of ready status until more verified evidence appears.", tone: confidence >= 60 ? "warn" : "bad", status: confidence >= 60 ? "Partial" : "Needs setup" });
        }
        if (!sportsbookLines.length) {
            blockers.push({ label: "Live sportsbook line", value: "Not confirmed", detail: "Verify the final book price manually or connect an odds feed.", tone: "warn", status: "Needs setup" });
        }
        if (dataQualityScore && dataQualityScore < 60) {
            blockers.push({ label: "Data quality", value: `${dataQualityScore}/100`, detail: "Research confidence is capped until public summaries, sportsbook prices, and history samples improve.", tone: "warn", status: dataQuality.label || "Partial" });
        }

        const score = Math.max(0, Math.min(100, Math.round((confidence * 0.34) + ((dataQualityScore || 50) * 0.36) + (sportsbookLines.length ? 22 : 6) - blockers.length * 7)));
        const label = statusKey === "final" ? "No action" : score >= 78 && sportsbookLines.length ? "Ready" : score >= 60 ? "Watch" : "Needs verification";
        const tone = label === "Ready" ? "ok" : label === "No action" ? "bad" : "warn";
        const noBetReasons = blockers.length ? blockers : [{
            label: "No major blocker detected",
            value: "Proceed to verification",
            detail: "Still confirm legal eligibility, final price, availability news, and bankroll limits.",
            tone: "ok",
            status: "Active",
        }];

        return {
            score,
            label,
            tone,
            detail: "Refresh the live state to load full server-side readiness for this pick.",
            blockerCount: blockers.length,
            availableSportsbookLines: sportsbookLines.length,
            checks: [
                { label: "Model confidence estimate", value: `${confidence}%`, detail: `Fair odds ${pick.fairOdds || "--"} before book comparison.`, tone: confidence >= 68 ? "ok" : "warn", status: confidence >= 68 ? "Active" : "Partial" },
                { label: "Data-quality confidence", value: dataQualityScore ? `${dataQualityScore}/100` : "Partial", detail: dataQuality.confidenceCap ? `Research cap ${dataQuality.confidenceCap}%. ${(dataQuality.warnings || []).slice(0, 1).join(" ")}` : "Source quality score will appear as public and odds feeds fill in.", tone: dataQualityScore >= 68 ? "ok" : "warn", status: dataQuality.label || "Partial" },
                { label: "Line shopping", value: sportsbookLines.length ? (pick.bestBook || "Live line") : "Needs live line", detail: sportsbookLines.length ? (pick.bookLine || pick.odds || "Verify price") : "Connect odds or manually verify sportsbook prices.", tone: sportsbookLines.length ? "ok" : "warn", status: sportsbookLines.length ? "Active" : "Needs setup" },
                { label: "Execution mode", value: "Manual only", detail: "Lineforge does not place wagers or bypass legal checks.", tone: "ok", status: "Active" },
            ],
            lineShopping: {
                summary: sportsbookLines.length ? `${sportsbookLines.length} live sportsbook line${sportsbookLines.length === 1 ? "" : "s"} attached.` : "No live bookmaker price is attached to this pick yet.",
                liveLines: sportsbookLines.length,
                bestBook: pick.bestBook || "Provider links",
                bestLine: pick.bookLine || pick.pick || "Market",
                bestPrice: pick.odds || "--",
                fairOdds: pick.fairOdds || "--",
                bookProbability: "--",
                modelEdge: pick.edge || "+0.0%",
                cards: [],
            },
            noBetReasons,
            manualVerification: [
                { label: "Legal location", value: "Required", detail: "Only use legal, regulated books available in your location.", tone: "warn", status: "Partial" },
                { label: "Final price", value: "Re-check", detail: "Confirm the exact line, odds, limits, and market rules before acting.", tone: "warn", status: "Partial" },
                { label: "Bankroll rule", value: pick.stake || "0.00u", detail: "Keep exposure inside the configured paper stake.", tone: "ok", status: "Active" },
            ],
        };
    };
    const renderDecisionCenter = () => {
        if (!decisionPick && !decisionMetrics && !decisionChecks) {
            return;
        }

        const pick = latestSportsState.topPick || predictions()[0] || {};
        const confidence = Number.parseInt(String(pick.confidenceValue || pick.confidence || "58"), 10) || 58;
        const readiness = readinessForPick(pick);
        const lineShopping = readiness.lineShopping || {};
        const links = Array.isArray(pick.marketLinks) ? pick.marketLinks : [];
        const sportsbookLines = links.filter((link) => link?.available && String(link?.kind || "").toLowerCase() === "sportsbook");
        const marketAccess = latestSportsState.marketAccess || {};
        const comparison = pick.teamComparison || {};
        const signals = comparison.signals || {};
        const injuryCount = Number(signals.injuryCount || 0);
        const playerCount = Number(signals.playerCount || 0);
        const bestBook = pick.bestBook || "Provider links";
        const bookLine = pick.bookLine || pick.odds || pick.fairOdds || "--";

        if (decisionPick) {
            decisionPick.textContent = predictionWinner(pick);
        }
        if (decisionReason) {
            decisionReason.textContent = pick.reason || latestSportsState.insight?.copy || "Lineforge ranks the board by model probability, market context, missing-data risk, and manual verification readiness.";
        }
        if (decisionGrade) {
            decisionGrade.innerHTML = `
                <span>Signal readiness</span>
                <strong>${escapeHtml(readiness.score ?? confidence)}</strong>
                <small>${escapeHtml(readiness.label || "Watch")}</small>
            `;
        }
        if (decisionMetrics) {
            decisionMetrics.innerHTML = `
                <div><span>${lineforgeIconLabel("Match", "matchup-analysis")}</span><strong>${escapeHtml(pick.matchup || "Best board matchup")}</strong><small>${escapeHtml(pick.league || "Sports")}</small></div>
                <div><span>${lineforgeIconLabel("Market", "market-analysis")}</span><strong>${escapeHtml(pick.market || "Monitor")}</strong><small>${escapeHtml(bookLine)}</small></div>
                <div><span>${lineforgeIconLabel("Research confidence", "confidence-score")}</span><strong>${escapeHtml(pick.confidence || `${confidence}%`)}</strong><small>Fair ${escapeHtml(pick.fairOdds || "--")}</small></div>
                <div><span>${lineforgeIconLabel("Data quality", "market-analysis")}</span><strong>${escapeHtml(pick.dataQuality?.score ? `${pick.dataQuality.score}/100` : "Partial")}</strong><small>${escapeHtml(pick.dataQuality?.label ? `${pick.dataQuality.label} / cap ${pick.dataQuality.confidenceCap || "--"}%` : "Source depth gates research confidence")}</small></div>
                <div><span>${lineforgeIconLabel("Edge", "edge-rating")}</span><strong>${escapeHtml(pick.edge || "+0.0%")}</strong><small>${escapeHtml(pick.expectedValue || "$0.00")} EV</small></div>
                <div><span>${lineforgeIconLabel("Risk", "risk-tier")}</span><strong>${escapeHtml(pick.risk || "Model risk")}</strong><small>${escapeHtml(pick.stake || "0.00u")} paper stake</small></div>
                <div><span>${lineforgeIconLabel("Readiness", "confidence-ring")}</span><strong>${escapeHtml(`${readiness.score ?? confidence}/100`)}</strong><small>${escapeHtml(readiness.label || "Watch")}</small></div>
            `;
        }
        if (decisionChecks) {
            const fallbackChecks = [
                {
                    label: "Model probability",
                    value: pick.fairProbability || `${confidence}.0%`,
                    detail: `Fair odds ${pick.fairOdds || "--"} before book comparison.`,
                    tone: decisionTone(confidence >= 68),
                },
                {
                    label: "Data quality",
                    value: pick.dataQuality?.score ? `${pick.dataQuality.score}/100` : "Partial",
                    detail: pick.dataQuality?.label ? `${pick.dataQuality.label} / research cap ${pick.dataQuality.confidenceCap || "--"}%.` : "Source depth and freshness gate the displayed calibration estimate.",
                    tone: decisionTone(Number(pick.dataQuality?.score || 0) >= 68),
                },
                {
                    label: "Best available line",
                    value: sportsbookLines.length ? bestBook : "Needs live line",
                    detail: sportsbookLines.length ? bookLine : "Add an odds API key or verify the price manually in the book.",
                    tone: decisionTone(sportsbookLines.length > 0),
                },
                {
                    label: "Injury and lineup check",
                    value: injuryCount > 0 ? `${injuryCount} listed` : "Manual check",
                    detail: playerCount > 0 ? `${playerCount} public player signals attached.` : "Confirm official injuries, scratches, lineups, and minutes restrictions.",
                    tone: decisionTone(playerCount > 0),
                },
                {
                    label: "Market coverage",
                    value: `${marketAccess.availableLines || 0} lines`,
                    detail: `${marketAccess.matchedEvents || 0} matched events across connected providers.`,
                    tone: decisionTone(Number(marketAccess.availableLines || 0) > 0),
                },
                {
                    label: "Stake discipline",
                    value: pick.stake || "0.00u",
                    detail: "Informational stake size stays clipped for bankroll and volatility control.",
                    tone: "ok",
                },
                {
                    label: "Execution mode",
                    value: "Manual only",
                    detail: "Lineforge does not place wagers or execute exchange orders for you.",
                    tone: "ok",
                },
            ];
            const checks = Array.isArray(readiness.checks) && readiness.checks.length ? readiness.checks : fallbackChecks;
            decisionChecks.innerHTML = checks.map((check) => `
                <div class="betedge-check-${escapeHtml(check.tone || "warn")}">
                    <span>${lineforgeIconLabel(check.label)}</span>
                    <strong>${escapeHtml(check.value)}</strong>
                    <small>${escapeHtml(check.detail)}</small>
                </div>
            `).join("");
        }
        if (decisionContext) {
            decisionContext.innerHTML = [
                { label: "Readiness", icon: "confidence-ring", value: `${readiness.score ?? confidence}/100`, detail: readiness.label || "Watch" },
                { label: "Edge", icon: "edge-rating", value: pick.edge || "+0.0%", detail: "Estimated model gap versus market context." },
                { label: "Line shop", icon: "line-movement", value: lineShopping.bestBook || bestBook, detail: lineShopping.summary || `${sportsbookLines.length} live lines attached.` },
                { label: "Blockers", icon: "risk-tier", value: Number(readiness.blockerCount || 0) > 0 ? String(readiness.blockerCount) : "None flagged", detail: Number(readiness.blockerCount || 0) > 0 ? "Open the drawer before acting." : "Still complete manual verification." },
            ].map((item) => `
                <div>
                    <span>${lineforgeIconLabel(item.label, item.icon)}</span>
                    <strong>${escapeHtml(item.value)}</strong>
                    <small>${escapeHtml(item.detail)}</small>
                </div>
            `).join("");
        }
    };
    const fallbackDecisionBoard = () => predictions().map((prediction, index) => {
        const readiness = readinessForPick(prediction);
        const confidence = Number.parseInt(String(prediction.confidenceValue || prediction.confidence || "58"), 10) || 58;
        const sportsbookLines = Number(readiness.availableSportsbookLines || 0);
        const blockers = Number(readiness.blockerCount || 0);
        const decisionScore = Math.max(0, Math.min(100, Math.round(
            Number(readiness.score || 0) * 0.7
            + confidence * 0.18
            + sportsbookLines * 3
            - blockers * 4
        )));
        return {
            rank: index + 1,
            predictionIndex: index,
            gameId: prediction.gameId || "",
            pick: prediction.pick || "Pick",
            matchup: prediction.matchup || "Matchup",
            league: prediction.league || "Sports",
            market: prediction.market || "Market",
            confidence: prediction.confidence || `${confidence}%`,
            confidenceValue: confidence,
            readinessScore: readiness.score || decisionScore,
            readinessLabel: readiness.label || "Watch",
            readinessTone: readiness.tone || "warn",
            decisionScore,
            edge: prediction.edge || "+0.0%",
            expectedValue: prediction.expectedValue || "$0.00",
            bestBook: readiness.lineShopping?.bestBook || prediction.bestBook || "Provider links",
            bestPrice: readiness.lineShopping?.bestPrice || prediction.odds || "--",
            blockers,
            blockerSummary: readiness.noBetReasons?.[0]?.label || readiness.detail || "Manual verification",
            action: readiness.label === "Ready"
                ? { label: "Verify and compare", tone: "ok", detail: "Re-check final line, eligibility, and stake cap." }
                : { label: blockers > 0 ? "Skip for now" : "Watch price", tone: blockers > 0 ? "bad" : "warn", detail: readiness.detail || "Open the full breakdown before acting." },
        };
    }).sort((a, b) => b.decisionScore - a.decisionScore).map((row, index) => ({ ...row, rank: index + 1 }));

    const visibleDecisionRows = () => {
        const board = Array.isArray(latestSportsState.decisionBoard) && latestSportsState.decisionBoard.length
            ? latestSportsState.decisionBoard
            : fallbackDecisionBoard();
        const matchingIds = new Set(filteredGames().map((game) => String(game.id || "")));
        if (activeSportsFilter === "all" && !activeSportsSearch) {
            return board;
        }

        return board.filter((row) => {
            const gameId = String(row.gameId || "");
            if (gameId && matchingIds.has(gameId)) {
                return true;
            }
            const query = normalizeFilterText(activeSportsSearch);
            const haystack = normalizeFilterText([row.pick, row.matchup, row.league, row.market, row.readinessLabel, row.action?.label].filter(Boolean).join(" "));
            return query ? query.split(" ").filter(Boolean).every((term) => haystack.includes(term)) : false;
        });
    };

    const renderDecisionBoard = () => {
        if (!decisionBoard) return;
        const rows = visibleDecisionRows().slice(0, 6);
        if (decisionBoardCount) {
            const noun = rows.length === 1 ? "ranked pick" : "ranked picks";
            decisionBoardCount.textContent = `${rows.length} ${noun}`;
        }
        decisionBoard.innerHTML = rows.length
            ? rows.map((row) => {
                const action = row.action || {};
                const tone = action.tone || row.readinessTone || "warn";
                const openAttribute = Number.isInteger(row.predictionIndex)
                    ? `data-sports-open-pick="${escapeHtml(row.predictionIndex)}"`
                    : `data-sports-open-game="${escapeHtml(row.gameId || "")}"`;
                return `
                    <article class="betedge-decision-row is-${escapeHtml(tone)}">
                        <div class="betedge-rank-badge">
                            <span>#${escapeHtml(row.rank || 0)}</span>
                            <strong>${escapeHtml(row.decisionScore ?? 0)}</strong>
                        </div>
                        <div class="betedge-decision-pick">
                            <strong>${escapeHtml(row.pick || "Pick")}</strong>
                            <small>${escapeHtml(row.matchup || "Matchup")} / ${escapeHtml(row.league || "Sports")}</small>
                        </div>
                        <div><span>${lineforgeIconLabel("Readiness", "confidence-ring")}</span><strong>${escapeHtml(row.readinessScore ?? 0)}/100</strong><small>${escapeHtml(row.readinessLabel || "Watch")}</small></div>
                        <div><span>${lineforgeIconLabel("Market", "market-analysis")}</span><strong>${escapeHtml(row.market || "Market")}</strong><small>${escapeHtml(row.bestBook || "Provider links")} / ${escapeHtml(row.bestPrice || "--")}</small></div>
                        <div><span>${lineforgeIconLabel("Edge", "edge-rating")}</span><strong>${escapeHtml(row.edge || "+0.0%")}</strong><small>${escapeHtml(row.expectedValue || "$0.00")} EV</small></div>
                        <div><span>${lineforgeIconLabel("Blockers", "risk-tier")}</span><strong>${escapeHtml(row.blockers ?? 0)}</strong><small>${escapeHtml(row.blockerSummary || "Manual verification")}</small></div>
                        <button type="button" class="betedge-decision-action" ${openAttribute}>
                            <span>${escapeHtml(action.label || "Open")}</span>
                            <small>${escapeHtml(action.detail || "Inspect the full pick before acting.")}</small>
                        </button>
                    </article>
                `;
            }).join("")
            : `<div class="betedge-decision-board-empty"><strong>No ranked decisions match this view</strong><span>Clear the search or switch sports to see the full board.</span></div>`;
    };
    const providerToneClass = (value = "") => {
        const text = String(value || "").toLowerCase();
        if (text.includes("connected") || text.includes("configured") || text.includes("required")) return "is-connected";
        if (text.includes("manual") || text.includes("partial")) return "is-partial";
        return "needs-setup";
    };
    const renderProviderSetup = (settings = latestProviderSettings) => {
        latestProviderSettings = settings || {};
        if (providerStatus) {
            providerStatus.textContent = latestProviderSettings.oddsConnected ? "Odds feed connected" : "Odds feed needs a key";
        }
        if (providerSaveResult && latestProviderSettings.secretStorage?.message) {
            providerSaveResult.textContent = latestProviderSettings.secretStorage.message;
        }
        if (providerReadiness) {
            const readiness = latestProviderSettings.readiness || {};
            const cards = [
                { label: "Scoreboard", icon: "live-feed", value: readiness.scoreboard || "Connected", detail: "Public live state and schedule scan." },
                { label: "Sportsbook Odds", icon: "odds", value: readiness.odds || "Needs API key", detail: latestProviderSettings.oddsSource || "Not connected" },
                { label: "Injuries", icon: "injury-risk", value: readiness.injuries || "Manual check", detail: "Late availability and lineup risk." },
                { label: "Lineups", icon: "matchup-analysis", value: readiness.lineups || "Manual check", detail: "Starting lineups, scratches, and minutes limits." },
                { label: "Execution", icon: "status-indicator", value: "Manual only", detail: "No automated wagers or exchange orders." },
            ];
            providerReadiness.innerHTML = cards.map((card) => `
                <div class="${providerToneClass(card.value)}">
                    <span>${lineforgeIconLabel(card.label, card.icon)}</span>
                    <strong>${escapeHtml(card.value)}</strong>
                    <small>${escapeHtml(card.detail)}</small>
                </div>
            `).join("");
        }
        if (providerSetupForm) {
            const setValue = (name, value) => {
                const field = providerSetupForm.elements[name];
                if (field && field.type !== "password" && field.type !== "checkbox") {
                    field.value = value || "";
                }
            };
            const keyInput = providerSetupForm.elements.odds_api_key;
            if (keyInput) {
                keyInput.placeholder = latestProviderSettings.oddsKeyMasked || "Paste key to enable sportsbook line matching";
                keyInput.value = "";
            }
            const clearInput = providerSetupForm.elements.clear_odds_api_key;
            if (clearInput) {
                clearInput.checked = false;
            }
            setValue("injury_feed_url", latestProviderSettings.injury_feed_url || "");
            setValue("lineup_feed_url", latestProviderSettings.lineup_feed_url || "");
            setValue("news_feed_url", latestProviderSettings.news_feed_url || "");
            setValue("props_feed_url", latestProviderSettings.props_feed_url || "");
            setValue("preferred_region", latestProviderSettings.preferred_region || "us");
            setValue("bankroll_unit", latestProviderSettings.bankroll_unit || "1.00");
            setValue("max_stake_units", latestProviderSettings.max_stake_units || "0.85");
        }
    };
    const executionTone = (value = "") => {
        const text = String(value || "").toLowerCase();
        if (["connected", "operational", "configured", "filled", "success"].some((word) => text.includes(word))) return "is-connected";
        if (["paused", "watching", "manual", "approval", "data_only", "dry_run", "needs"].some((word) => text.includes(word))) return "is-partial";
        if (["blocked", "degraded", "emergency", "unsupported", "critical", "error"].some((word) => text.includes(word))) return "needs-setup";
        return "is-partial";
    };
    const executionMoney = (value) => `$${(Number(value) || 0).toFixed(2)}`;
    const executionPercent = (value) => `${Math.round(Number(value) || 0)}%`;
    const executionRows = (items, emptyText, mapper) => {
        const list = Array.isArray(items) ? items : [];
        return list.length
            ? list.map(mapper).join("")
            : `<div class="lineforge-empty-state">${escapeHtml(emptyText)}</div>`;
    };
    const renderExecutionCenter = (state = latestExecutionState) => {
        latestExecutionState = state || {};
        const mode = String(latestExecutionState.mode || "paper");
        const emergency = Boolean(latestExecutionState.emergencyStop);
        const risk = latestExecutionState.riskLimits || {};
        const health = latestExecutionState.providerHealth || {};
        const connections = latestExecutionState.providerConnections || {};
        const credentialSecurity = latestExecutionState.credentialSecurity || {};

        if (executionSummary) {
            executionSummary.textContent = emergency
                ? "Emergency stop is active. Rules are paused and live execution is disabled."
                : `${mode.toUpperCase()} mode active. Live-money actions require explicit authorization, provider eligibility, risk limits, and audit logging.`;
        }

        executionMode?.querySelectorAll("[data-execution-mode]").forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.executionMode || "paper") === mode);
        });

        if (kalshiStatus) {
            const kalshi = connections.kalshi || {};
            kalshiStatus.textContent = kalshi.keyIdMasked
                ? `Kalshi ${kalshi.environment || "demo"} profile ${kalshi.status || "saved"}`
                : "Demo environment first";
        }

        if (riskStatus) {
            riskStatus.textContent = emergency || risk.emergencyDisabled
                ? "Emergency controls active"
                : `Max ${executionMoney(risk.maxStakePerOrder || 25)} / daily loss ${executionMoney(risk.maxDailyLoss || 100)}`;
        }

        if (ruleStatus) {
            const activeRules = (latestExecutionState.rules || []).filter((rule) => rule.enabled).length;
            ruleStatus.textContent = `${activeRules} active / ${(latestExecutionState.rules || []).length} total`;
        }

        if (executionProviders) {
            const cards = ["paper", "kalshi", "fanduel"].map((key) => {
                const provider = connections[key] || {};
                const providerHealth = health[key] || {};
                return `
                    <div class="${executionTone(provider.status || providerHealth.status)}">
                        <span>${lineforgeIconLabel(provider.label || key, key === "kalshi" ? "sportsbooks" : key === "paper" ? "bankroll-tracking" : "odds")}</span>
                        <strong>${escapeHtml(provider.status || providerHealth.status || "unknown")}</strong>
                        <small>${escapeHtml(provider.message || providerHealth.message || "No provider message.")}</small>
                        <em>${escapeHtml(provider.environment ? `${provider.environment} / ` : "")}${escapeHtml(provider.officialApiOnly ? "official API only" : "simulation")}</em>
                    </div>
                `;
            });
            cards.push(`
                <div class="${credentialSecurity.encryptionAvailable ? "is-connected" : "needs-setup"}">
                    <span>${lineforgeIconLabel("Credential Vault", "risk-tier")}</span>
                    <strong>${credentialSecurity.encryptionAvailable ? "Encryption ready" : "Storage blocked"}</strong>
                    <small>${escapeHtml(credentialSecurity.message || "Credential status unavailable.")}</small>
                    <em>Signer ${credentialSecurity.signerAvailable ? "available" : "missing"}</em>
                </div>
            `);
            executionProviders.innerHTML = cards.join("");
        }

        if (executionBalances) {
            executionBalances.innerHTML = `
                <div><span>Paper balance</span><strong>${executionMoney(latestExecutionState.paperBalance || 0)}</strong><small>Simulation buying power</small></div>
                <div><span>Daily realized loss</span><strong>${executionMoney(latestExecutionState.dailyRealizedLoss || 0)}</strong><small>Limit ${executionMoney(risk.maxDailyLoss || 0)}</small></div>
                <div><span>Max order</span><strong>${executionMoney(risk.maxStakePerOrder || 0)}</strong><small>Cooldown ${escapeHtml(risk.cooldownMinutes ?? 0)}m</small></div>
                <div><span>Live opt-in</span><strong>${latestExecutionState.liveEnabled ? "Enabled" : "Off"}</strong><small>${escapeHtml(latestExecutionState.liveOptInAt || "No live authorization")}</small></div>
            `;
        }

        if (riskForm) {
            const setRisk = (name, value) => {
                const field = riskForm.elements[name];
                if (!field) return;
                if (field.type === "checkbox") {
                    field.checked = Boolean(value);
                } else {
                    field.value = value ?? "";
                }
            };
            setRisk("maxStakePerOrder", risk.maxStakePerOrder ?? 25);
            setRisk("maxDailyLoss", risk.maxDailyLoss ?? 100);
            setRisk("cooldownMinutes", risk.cooldownMinutes ?? 5);
            setRisk("blockStaleMarketDataSeconds", risk.blockStaleMarketDataSeconds ?? 120);
            setRisk("selfExcluded", risk.selfExcluded);
            setRisk("emergencyDisabled", risk.emergencyDisabled);
            setRisk("requireManualConfirmation", risk.requireManualConfirmation);
            setRisk("allowLiveAuto", risk.allowLiveAuto);
        }

        if (kalshiForm) {
            const kalshi = connections.kalshi || {};
            if (kalshiForm.elements.environment) kalshiForm.elements.environment.value = kalshi.environment || "demo";
            if (kalshiForm.elements.keyId) {
                kalshiForm.elements.keyId.value = "";
                kalshiForm.elements.keyId.placeholder = kalshi.keyIdMasked || "Key ID is kept server-side";
            }
            if (kalshiForm.elements.privateKey) kalshiForm.elements.privateKey.value = "";
            if (kalshiForm.elements.clearCredentials) kalshiForm.elements.clearCredentials.checked = false;
        }

        if (executionRules) {
            executionRules.innerHTML = executionRows(latestExecutionState.rules, "No execution rules yet. Create a paused paper rule, dry-run it, then enable deliberately.", (rule) => `
                <article class="${executionTone(rule.status)}">
                    <div>
                        <span>${escapeHtml((rule.provider || "paper").toUpperCase())} / ${escapeHtml(rule.side || "YES")}</span>
                        <strong>${escapeHtml(rule.marketTicker || "MARKET")}</strong>
                        <small>WHEN probability ${escapeHtml(rule.entry?.probabilityOperator || "<=")} ${executionPercent(rule.entry?.probability)} AND research confidence >= ${executionPercent(rule.entry?.confidenceMin)}</small>
                    </div>
                    <div>
                        <span>Stake</span>
                        <strong>${escapeHtml(rule.stake?.type || "fixed")} ${executionMoney(rule.stake?.amount || 0)}</strong>
                        <small>Max price ${Math.round(Number(rule.stake?.maxPrice || 0) * 100)}c / ${escapeHtml(rule.confirmationMode || "manual")}</small>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong>${escapeHtml(rule.status || "paused")}</strong>
                        <small>${escapeHtml(rule.lastEvaluationAt || rule.updatedAt || rule.createdAt || "Not evaluated")}</small>
                    </div>
                    <div class="lineforge-rule-actions">
                        <button type="button" data-execution-toggle-rule="${escapeHtml(rule.id || "")}" data-enabled="${rule.enabled ? "0" : "1"}">${rule.enabled ? "Pause" : "Enable"}</button>
                        <button type="button" data-execution-delete-rule="${escapeHtml(rule.id || "")}">Delete</button>
                    </div>
                </article>
            `);
        }

        if (executionPositions) {
            executionPositions.innerHTML = executionRows(latestExecutionState.positions, "No open positions.", (position) => `
                <div>
                    <span>${escapeHtml(position.provider || "paper")} / ${escapeHtml(position.side || "YES")}</span>
                    <strong>${escapeHtml(position.marketTicker || "MARKET")}</strong>
                    <small>${escapeHtml(position.contracts ?? 0)} contracts @ ${Math.round(Number(position.averagePrice || 0) * 100)}c</small>
                </div>
            `);
        }

        if (executionOrders) {
            const openOrders = (latestExecutionState.orders || []).filter((order) => ["open", "resting", "pending", "filled"].includes(String(order.status || ""))).slice(-10).reverse();
            executionOrders.innerHTML = executionRows(openOrders, "No tracked orders.", (order) => `
                <div>
                    <span>${escapeHtml(order.provider || "paper")} / ${escapeHtml(order.status || "unknown")}</span>
                    <strong>${escapeHtml(order.marketTicker || "MARKET")}</strong>
                    <small>${escapeHtml(order.clientOrderId || order.id || "")} / cost ${executionMoney(order.estimatedCost || 0)}</small>
                </div>
            `);
        }

        if (executionAudit) {
            const logs = Array.isArray(latestExecutionState.auditLogs) ? latestExecutionState.auditLogs.slice(-16).reverse() : [];
            executionAudit.innerHTML = executionRows(logs, "No audit events yet.", (entry) => `
                <div class="${executionTone(entry.severity || entry.type)}">
                    <span>${escapeHtml(entry.type || "audit")} / ${escapeHtml(entry.severity || "info")}</span>
                    <strong>${escapeHtml(entry.message || "Audit event")}</strong>
                    <small>${escapeHtml(entry.createdAt || "")}</small>
                </div>
            `);
        }
    };
    const setExecutionResult = (message, tone = "neutral") => {
        if (!executionResult) return;
        executionResult.textContent = message;
        executionResult.dataset.tone = tone;
    };
    const postExecution = async (payload = {}) => {
        if (!config.executionEndpoint) {
            throw new Error("Execution Center endpoint is not configured.");
        }
        setExecutionResult("Updating Execution Center...", "neutral");
        const response = await fetch(config.executionEndpoint, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-AEGIS-CSRF": config.csrfToken || "",
            },
            body: JSON.stringify({ csrf: config.csrfToken || "", ...payload }),
        });
        const data = await response.json();
        config.csrfToken = data.csrfToken || config.csrfToken;
        if (data.state) {
            latestExecutionState = data.state;
            config.executionState = data.state;
            renderExecutionCenter(data.state);
        }
        if (!data.ok) {
            throw new Error(data.message || "Execution Center update failed.");
        }
        setExecutionResult(data.message || "Execution Center updated.", "success");
        return data;
    };
    const loadExecutionCenter = async (quiet = true) => {
        if (!config.executionEndpoint) return;
        if (executionRefreshInFlight) return;
        executionRefreshInFlight = true;
        try {
            const response = await fetch(config.executionEndpoint, { credentials: "same-origin" });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;
            if (data.ok && data.state) {
                latestExecutionState = data.state;
                config.executionState = data.state;
                renderExecutionCenter(data.state);
                if (!quiet) {
                    setExecutionResult("Execution Center synced.", "success");
                }
            }
        } catch (error) {
            if (!quiet) {
                setExecutionResult(error instanceof Error ? error.message : "Execution Center sync failed.", "error");
            }
        } finally {
            executionRefreshInFlight = false;
        }
    };
    const renderPredictions = () => {
        const items = filteredPredictions();
        renderPredictionsInto(dashboardPredictions, items, 5, "winner");
        renderPredictionsInto(expandedPredictions, items, 12, "winner");
        renderDecisionBoard();
    };

    const flattenMarketRows = (state = latestSportsState) => {
        const stateGames = Array.isArray(state.games) ? state.games : [];
        return stateGames.flatMap((game) => {
            const links = Array.isArray(game.betLinks) ? game.betLinks : [];
            return links.slice(0, 10).map((link) => ({
                providerKey: String(link?.providerKey || link?.title || "provider").toLowerCase(),
                provider: link?.title || "Provider",
                kind: link?.kind || "Sportsbook",
                matchup: game.matchup || `${game.away?.abbr || "AWY"} @ ${game.home?.abbr || "HME"}`,
                statusKey: game.statusKey || "scheduled",
                statusLabel: game.statusLabel || "Watch",
                market: link?.market || "Market",
                line: link?.line || "Line",
                price: link?.price || "--",
                available: Boolean(link?.available),
                url: link?.url || "#",
                note: link?.note || "Verify eligibility, location, and final price before taking action.",
            }));
        });
    };

    const renderMarketBoard = () => {
        if (!marketBoard) return;
        const rows = flattenMarketRows().filter((row) => {
            if (activeProviderFilter === "kalshi") return row.providerKey.includes("kalshi") || row.provider.toLowerCase().includes("kalshi");
            if (activeProviderFilter === "sportsbook") return row.kind.toLowerCase().includes("sportsbook");
            if (activeProviderFilter === "live") return row.statusKey === "live" || row.available;
            return true;
        });
        if (marketCount) {
            marketCount.textContent = `${rows.length} market${rows.length === 1 ? "" : "s"}`;
        }
        marketBoard.innerHTML = rows.length
            ? rows.map((row) => {
                const displayLine = row.price !== "--" ? `${row.line} ${row.price}` : row.line;
                return `
                    <a class="betedge-market-card ${row.available ? "is-live" : ""}" href="${escapeHtml(row.url)}" target="_blank" rel="noopener noreferrer">
                        <span>${escapeHtml(row.provider)} / ${escapeHtml(row.kind)}</span>
                        <strong>${escapeHtml(row.matchup)}</strong>
                        <div><em>${escapeHtml(row.market)}</em><b>${escapeHtml(displayLine)}</b></div>
                        <small>${escapeHtml(row.statusLabel)} / ${escapeHtml(row.note)}</small>
                    </a>
                `;
            }).join("")
            : `<div class="betedge-market-empty"><strong>No ${escapeHtml(activeProviderFilter)} markets yet.</strong><span>Keep the board open while Lineforge refreshes sportsbook links and exchange matches.</span></div>`;
    };

    const renderTape = (items = []) => {
        if (!tape) return;
        tape.innerHTML = items.map((item) => `<span><strong>${escapeHtml(item.label || "Feed")}</strong> ${escapeHtml(item.value || "")} <em>${escapeHtml(item.state || "")}</em></span>`).join("")
            + '<span class="system"><i></i> All Systems Operational</span>';
    };

    const renderAlerts = (items = []) => {
        if (!alertsBoard) return;
        alertsBoard.innerHTML = items.slice(0, 4).map((item) => `
            <div>
                <span></span>
                <div><strong>${escapeHtml(item.name || "Alert")}</strong><small>${escapeHtml(item.detail || "")}</small></div>
                <em>${escapeHtml(item.time || "Now")}</em>
            </div>
        `).join("");
    };

    const normalizedGraphPoints = (points = [], fallback = []) => {
        const source = Array.isArray(points) && points.length ? points : fallback;
        return source.map((point) => Math.max(4, Math.min(96, Number(point) || 50)));
    };
    const renderSignalGraph = (container, points = [], options = {}) => {
        if (!container) return;
        const safePoints = normalizedGraphPoints(points, options.fallback || [50, 54, 57, 62, 66, 71, 76, 78]);
        const maxIndex = Math.max(1, safePoints.length - 1);
        const coordinates = safePoints.map((point, index) => {
            const x = (index / maxIndex) * 100;
            const y = 100 - point;
            return [x, y];
        });
        const linePoints = coordinates.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(" ");
        const areaPoints = `0,100 ${linePoints} 100,100`;
        const first = safePoints[0] || 0;
        const current = safePoints[safePoints.length - 1] || 0;
        const delta = current - first;
        const graphId = `${container.id || "betedge-graph"}Gradient`;
        const tone = options.tone || "confidence";
        container.innerHTML = `
            <div class="betedge-graph-meta">
                <span>${escapeHtml(options.label || "Signal")}</span>
                <strong>${escapeHtml(Math.round(current))}%</strong>
                <em class="${delta >= 0 ? "up" : "down"}">${delta >= 0 ? "+" : ""}${escapeHtml(Math.round(delta))}</em>
            </div>
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="${escapeHtml(options.label || "Signal graph")}">
                <defs>
                    <linearGradient id="${escapeHtml(graphId)}" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="${tone === "market" ? "#8de7ff" : "#31d96b"}" stop-opacity="0.48"></stop>
                        <stop offset="100%" stop-color="${tone === "market" ? "#8de7ff" : "#31d96b"}" stop-opacity="0.02"></stop>
                    </linearGradient>
                </defs>
                <polygon points="${areaPoints}" fill="url(#${escapeHtml(graphId)})"></polygon>
                <polyline points="${linePoints}" vector-effect="non-scaling-stroke"></polyline>
                ${coordinates.map(([x, y]) => `<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="1.7" vector-effect="non-scaling-stroke"></circle>`).join("")}
            </svg>
            <div class="betedge-graph-axis"><span>Open</span><span>Live</span><span>Now</span></div>
        `;
    };
    const renderTimeline = (points = []) => {
        renderSignalGraph(timeline, points, {
            label: "Calibration Estimate",
            fallback: [50, 54, 57, 62, 66, 71, 76, 78],
            tone: "confidence",
        });
    };

    const renderMarketLine = (points = []) => {
        renderSignalGraph(marketLine, points, {
            label: "Line Movement",
            fallback: [45, 48, 52, 56, 61, 67, 71, 76],
            tone: "market",
        });
    };

    const renderChipGrid = (container, items = []) => {
        if (!container) return;
        container.innerHTML = items.map((item) => {
            const title = item.name || item.label || item.book || "Signal";
            const value = item.weight || item.value || item.odds || "";
            const detail = item.detail || item.line || item.latency || "";
            const tag = item.state || item.tag || "";
            return `<div><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong>${tag ? `<em>${escapeHtml(tag)}</em>` : ""}<small>${escapeHtml(detail)}</small></div>`;
        }).join("");
    };

    const renderProviderGrid = (marketAccess = {}) => {
        if (!providerGrid) return;
        providerGrid.innerHTML = `
            <div><span>Odds feed</span><strong>${marketAccess.oddsProviderConfigured ? "Connected" : "Needs API key"}</strong><small>${escapeHtml(marketAccess.oddsProvider || "The Odds API")}</small></div>
            <div><span>Bookmakers</span><strong>${escapeHtml(marketAccess.bookmakers ?? 0)}</strong><small>Outbound app links</small></div>
            <div><span>Matched lines</span><strong>${escapeHtml(marketAccess.availableLines ?? 0)}</strong><small>${escapeHtml(marketAccess.matchedEvents ?? 0)} matched events</small></div>
            <div><span>Exchange scan</span><strong>${escapeHtml(marketAccess.exchangeProvider || "Kalshi")}</strong><small>${escapeHtml(marketAccess.kalshiMarketsCached ?? 0)} cached markets</small></div>
        `;
    };

    const renderCoverage = (coverage = {}) => {
        const groups = Array.isArray(coverage.groups) ? coverage.groups : [];
        renderChipGrid(coverageGrid, groups.slice(0, 12).map((group) => ({
            name: group.label || "Sports",
            value: `${group.games ?? 0} games`,
            detail: `${group.live ?? 0} live / ${group.scheduled ?? 0} upcoming`,
        })));
    };

    const dataArchitecture = () => latestSportsState.dataArchitecture && typeof latestSportsState.dataArchitecture === "object"
        ? latestSportsState.dataArchitecture
        : {};

    const dataModeClass = (value) => String(value || "partial")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "partial";

    const renderDataArchitecture = () => {
        const architecture = dataArchitecture();
        const summary = architecture.summary || {};
        const modules = Array.isArray(architecture.modules) ? architecture.modules : [];
        const systems = Array.isArray(architecture.systems) ? architecture.systems : [];
        const workflows = Array.isArray(architecture.operatorWorkflows) ? architecture.operatorWorkflows : [];
        const evolution = architecture.intelligenceEvolution && typeof architecture.intelligenceEvolution === "object" ? architecture.intelligenceEvolution : {};
        const evolutionSummaryData = evolution.summary || {};
        const modelReadiness = evolution.modelReadiness || {};
        const marketStructure = evolution.marketStructure || {};
        const signalObjects = Array.isArray(evolution.signals) ? evolution.signals : [];
        const adaptive = architecture.adaptiveIntelligence && typeof architecture.adaptiveIntelligence === "object" ? architecture.adaptiveIntelligence : {};
        const adaptiveSummaryData = adaptive.summary || {};
        const adaptiveLayers = Array.isArray(adaptive.layers) ? adaptive.layers : [];
        const adaptiveRules = Array.isArray(adaptive.composition?.rules) ? adaptive.composition.rules : [];
        const selfEvaluationMonitors = Array.isArray(adaptive.selfEvaluation?.monitors) ? adaptive.selfEvaluation.monitors : [];
        const historicalPatterns = Array.isArray(adaptive.historicalPatterns?.patterns) ? adaptive.historicalPatterns.patterns : [];
        const operatorMemory = Array.isArray(adaptive.operatorMemory?.artifacts) ? adaptive.operatorMemory.artifacts : [];
        const resilienceSystems = Array.isArray(adaptive.resilience?.systems) ? adaptive.resilience.systems : [];
        const adaptiveExplanations = Array.isArray(adaptive.explanations) ? adaptive.explanations : [];
        const generalized = architecture.generalizedIntelligence && typeof architecture.generalizedIntelligence === "object" ? architecture.generalizedIntelligence : {};
        const generalizedSummaryData = generalized.summary || {};
        const domainAdapters = Array.isArray(generalized.domains) ? generalized.domains : [];
        const primitiveEvents = Array.isArray(generalized.primitives?.events) ? generalized.primitives.events : [];
        const primitiveSignals = Array.isArray(generalized.primitives?.signals) ? generalized.primitives.signals : [];
        const generalizedPipelines = Array.isArray(generalized.orchestration?.pipelines) ? generalized.orchestration.pipelines : [];
        const contextualMemory = Array.isArray(generalized.memory?.stores) ? generalized.memory.stores : [];
        const selfOptimization = Array.isArray(generalized.selfOptimization?.systems) ? generalized.selfOptimization.systems : [];
        const adaptiveWorkspaces = Array.isArray(generalized.adaptiveWorkspaces?.workspaces) ? generalized.adaptiveWorkspaces.workspaces : [];
        const generalizedGovernance = Array.isArray(generalized.governance?.systems) ? generalized.governance.systems : [];
        const signals = Array.isArray(architecture.marketInference?.signals) ? architecture.marketInference.signals : [];

        if (dataSummary) {
            const cards = [
                { label: "Mode", value: summary.mode || "Public Intelligence", detail: "Graceful fallback without premium feeds", tone: architecture.mode || "public intelligence" },
                { label: "Public modules", value: summary.activePublicModules ?? 0, detail: "Disabled / public-free / partial / premium states", tone: "public-free" },
                { label: "Game archive", value: summary.gameRowsStored ?? 0, detail: "Stored public scoreboard rows", tone: "archive" },
                { label: "Odds history", value: summary.oddsRowsStored ?? 0, detail: "Stored normalized odds snapshots", tone: "history" },
                { label: "Inference signals", value: summary.inferenceSignals ?? 0, detail: "No unverified sharp-money claims", tone: "inference" },
                {
                    label: "Warehouse",
                    value: summary.warehouseStatus || architecture.warehouse?.status || "JSONL fallback",
                    detail: `${summary.warehouseDriver || architecture.warehouse?.driver || "storage"} / ${summary.warehouseRows ?? 0} rows`,
                    tone: architecture.warehouse?.available ? "active" : "fallback",
                },
                {
                    label: "Calibration",
                    value: `${summary.calibrationClosedSamples ?? architecture.calibration?.closedSamples ?? 0} closed`,
                    detail: architecture.calibration?.brierScore != null ? `Brier ${architecture.calibration.brierScore} / error ${architecture.calibration.calibrationError ?? "--"}%` : "Collecting closed samples before accuracy claims",
                    tone: architecture.calibration?.status || "collecting-baseline",
                },
                {
                    label: "Replay",
                    value: String(summary.replayStatus || architecture.warehouse?.replay?.status || "waiting").replace(/_/g, " "),
                    detail: `${String(summary.workerStatus || architecture.warehouse?.operational?.worker?.status || "not_started").replace(/_/g, " ")} worker`,
                    tone: architecture.warehouse?.replay?.available ? "active" : "fallback",
                },
                {
                    label: "Model gate",
                    value: String(summary.modelReadiness || evolutionSummaryData.modelReadiness || "collecting_labels").replace(/_/g, " "),
                    detail: `${summary.trainableRows ?? evolutionSummaryData.trainableRows ?? 0} trainable labels`,
                    tone: summary.modelReadiness || evolutionSummaryData.modelReadiness || "collecting-labels",
                },
                {
                    label: "Adaptive network",
                    value: String(summary.adaptiveNetworkStatus || adaptiveSummaryData.networkStatus || "collecting_memory").replace(/_/g, " "),
                    detail: `${summary.adaptiveCompositeReadiness ?? adaptiveSummaryData.compositeReadiness ?? 0}/100 readiness`,
                    tone: summary.adaptiveNetworkStatus || adaptiveSummaryData.networkStatus || "collecting-memory",
                },
                {
                    label: "Generalized core",
                    value: String(summary.generalizedCoreStatus || generalizedSummaryData.status || "domain_agnostic_foundation").replace(/_/g, " "),
                    detail: `${summary.generalizedDomainAdapters ?? generalizedSummaryData.domainAdapters ?? 0} domain adapters`,
                    tone: summary.generalizedCoreStatus || generalizedSummaryData.status || "domain-agnostic-foundation",
                },
                { label: "Refresh", value: summary.refreshCadence || "Cached", detail: "UI refresh with source-budget fetches", tone: "cadence" },
            ];
            dataSummary.innerHTML = cards.map((card) => `
                <div class="is-${escapeHtml(dataModeClass(card.tone))}">
                    <span>${escapeHtml(card.label)}</span>
                    <strong>${escapeHtml(card.value)}</strong>
                    <small>${escapeHtml(card.detail)}</small>
                </div>
            `).join("");
        }

        if (dataModules) {
            dataModules.innerHTML = modules.length
                ? modules.slice(0, 12).map((module) => `
                    <div class="is-${escapeHtml(dataModeClass(module.mode))}">
                        <span>${escapeHtml(module.name || "Source")}</span>
                        <strong>${escapeHtml(module.status || "Partial")}</strong>
                        <code>${escapeHtml(module.mode || "partial")}</code>
                        <small>${escapeHtml(module.detail || module.fallback || "Fallback-ready source module.")}</small>
                    </div>
                `).join("")
                : `<div class="is-partial"><span>Source fabric</span><strong>Building</strong><code>partial</code><small>Public connectors will appear as the board gathers data.</small></div>`;
        }

        if (inferenceBoard) {
            inferenceBoard.innerHTML = signals.length
                ? signals.slice(0, 6).map((signal) => `
                    <article class="is-${escapeHtml(dataModeClass(signal.state))}">
                        <div>${lineforgeIcon(lineforgeSignalIconFor(signal.name || signal.detail || "market"))}</div>
                        <span>${escapeHtml(signal.state || "Inferred")}</span>
                        <strong>${escapeHtml(signal.name || "Market signal")}</strong>
                        <b>${escapeHtml(signal.value || "Watch")}</b>
                        <small>${escapeHtml(signal.detail || "Lineforge is monitoring public data and stored snapshots.")}</small>
                    </article>
                `).join("")
                : `<article class="is-fallback"><div>${lineforgeIcon("status-indicator")}</div><span>Fallback</span><strong>Public intelligence mode</strong><b>Active</b><small>Public data, archives, and inferred market context remain available without premium odds feeds.</small></article>`;
        }

        if (internalSystems) {
            internalSystems.innerHTML = systems.length
                ? systems.slice(0, 12).map((system) => `
                    <div class="is-${escapeHtml(dataModeClass(system.status))}">
                        <span>${escapeHtml(system.name || "Internal system")}</span>
                        <strong>${escapeHtml(system.value || system.status || "Monitoring")}</strong>
                        <em>${escapeHtml(system.status || "Active")}</em>
                        <small>${escapeHtml(system.detail || "Internal intelligence module.")}</small>
                    </div>
                `).join("")
                : `<div class="is-building"><span>Historical systems</span><strong>Building</strong><em>Partial</em><small>Snapshots and trend records will fill as the board refreshes.</small></div>`;
        }

        if (operatorWorkflows) {
            operatorWorkflows.innerHTML = workflows.length
                ? workflows.map((workflow) => `
                    <div class="is-${escapeHtml(dataModeClass(workflow.status))}">
                        <span>${escapeHtml(workflow.name || "Workflow")}</span>
                        <strong>${escapeHtml(workflow.status || "Monitoring")}</strong>
                        <small>${escapeHtml(workflow.detail || "Operational workflow state.")}</small>
                    </div>
                `).join("")
                : `<div class="is-fallback"><span>Workflow</span><strong>Collecting</strong><small>Operator workflows will appear as the intelligence warehouse fills.</small></div>`;
        }

        if (evolutionSummary) {
            const cards = [
                { label: "Layer", value: String(evolutionSummaryData.status || "transparent_learning_layer").replace(/_/g, " "), detail: "No black-box accuracy claims", tone: "active" },
                { label: "Feature rows", value: evolutionSummaryData.featureRows ?? 0, detail: `${evolutionSummaryData.featureCount ?? 0} transparent features`, tone: "extracting-features" },
                { label: "Trainable labels", value: evolutionSummaryData.trainableRows ?? 0, detail: String(evolutionSummaryData.modelReadiness || "collecting_labels").replace(/_/g, " "), tone: evolutionSummaryData.modelReadiness || "collecting-labels" },
                { label: "Market regime", value: String(evolutionSummaryData.activeRegime || "unknown").replace(/_/g, " "), detail: marketStructure.detail || "Structure before prediction hype", tone: evolutionSummaryData.activeRegime || "unknown" },
                { label: "Signal objects", value: evolutionSummaryData.signalObjects ?? signalObjects.length, detail: "Layered review objects", tone: "multi-dimensional" },
                { label: "Observability", value: String(evolutionSummaryData.observabilityStatus || evolution.observability?.status || "unknown").replace(/_/g, " "), detail: `${evolutionSummaryData.triggerCount ?? 0} active triggers`, tone: evolutionSummaryData.observabilityStatus || "watch" },
            ];
            evolutionSummary.innerHTML = cards.map((card) => `
                <div class="is-${escapeHtml(dataModeClass(card.tone))}">
                    <span>${escapeHtml(card.label)}</span>
                    <strong>${escapeHtml(card.value)}</strong>
                    <small>${escapeHtml(card.detail)}</small>
                </div>
            `).join("");
        }

        if (modelCandidates) {
            const candidates = Array.isArray(modelReadiness.candidates) ? modelReadiness.candidates : [];
            modelCandidates.innerHTML = candidates.length
                ? candidates.map((candidate) => `
                    <div class="is-${escapeHtml(dataModeClass(candidate.status))}">
                        <span>${escapeHtml(candidate.family || "Model")}</span>
                        <strong>${escapeHtml(candidate.name || "Model candidate")}</strong>
                        <code>${escapeHtml(candidate.status || "data_gated")}</code>
                        <small>${escapeHtml(candidate.message || candidate.purpose || "Training remains evidence-gated.")}</small>
                    </div>
                `).join("")
                : `<div class="is-data-gated"><span>Model candidates</span><strong>Waiting</strong><code>data_gated</code><small>Closed labels are required before model training.</small></div>`;
        }

        if (marketRegimeBoard) {
            const classifications = Array.isArray(marketStructure.classifications) ? marketStructure.classifications : [];
            marketRegimeBoard.innerHTML = classifications.length
                ? classifications.map((item) => `
                    <div class="is-${escapeHtml(dataModeClass(item.status))}">
                        <span>${escapeHtml(item.name || "Classification")}</span>
                        <strong>${escapeHtml(item.value || "Watch")}</strong>
                        <em>${escapeHtml(item.status || "monitoring")}</em>
                        <small>${escapeHtml(item.detail || marketStructure.detail || "Market structure classification.")}</small>
                    </div>
                `).join("")
                : `<div class="is-fallback"><span>Market regime</span><strong>Collecting</strong><small>Market structure classification needs odds and movement history.</small></div>`;
        }

        if (signalObjectsBoard) {
            signalObjectsBoard.innerHTML = signalObjects.length
                ? signalObjects.slice(0, 4).map((signal) => {
                    const dimensions = Array.isArray(signal.dimensions) ? signal.dimensions : [];
                    const topDimensions = dimensions.slice(0, 3).map((dimension) => `${dimension.name}: ${dimension.score}`).join(" / ");
                    return `
                        <div class="is-${escapeHtml(dataModeClass(signal.regime || "signal"))}">
                            <span>${escapeHtml(signal.market || signal.name || "Signal object")}</span>
                            <strong>${escapeHtml(signal.pick || "Review")}</strong>
                            <em>${escapeHtml(signal.readinessScore ?? 0)}/100</em>
                            <small>${escapeHtml(topDimensions || signal.explanation || "Layered signal object.")}</small>
                        </div>
                    `;
                }).join("")
                : `<div class="is-fallback"><span>Signals</span><strong>Collecting</strong><small>Signal objects appear after feature extraction.</small></div>`;
        }

        if (adaptiveSummary) {
            const cards = [
                { label: "Network", value: String(adaptiveSummaryData.networkStatus || "collecting_memory").replace(/_/g, " "), detail: adaptive.composition?.message || "Layer coordination without monolithic certainty", tone: adaptiveSummaryData.networkStatus || "collecting-memory" },
                { label: "Composite", value: `${adaptiveSummaryData.compositeReadiness ?? 0}/100`, detail: "Readiness after uncertainty penalty", tone: adaptiveSummaryData.networkStatus || "collecting-memory" },
                { label: "Layers", value: adaptiveSummaryData.layers ?? adaptiveLayers.length, detail: "Independent intelligence systems", tone: "independent-layer" },
                { label: "High uncertainty", value: adaptiveSummaryData.highUncertaintyLayers ?? 0, detail: "Layers kept conservative", tone: (adaptiveSummaryData.highUncertaintyLayers ?? 0) > 0 ? "high-uncertainty" : "active" },
                { label: "Degraded layers", value: adaptiveSummaryData.degradedLayers ?? 0, detail: "Fail independently before orchestration", tone: (adaptiveSummaryData.degradedLayers ?? 0) > 0 ? "degraded-watch" : "active" },
                { label: "Explanations", value: adaptiveSummaryData.explanations ?? adaptiveExplanations.length, detail: "Operator-readable reasoning", tone: "explainable-watch" },
            ];
            adaptiveSummary.innerHTML = cards.map((card) => `
                <div class="is-${escapeHtml(dataModeClass(card.tone))}">
                    <span>${escapeHtml(card.label)}</span>
                    <strong>${escapeHtml(card.value)}</strong>
                    <small>${escapeHtml(card.detail)}</small>
                </div>
            `).join("");
        }

        if (adaptiveLayersBoard) {
            adaptiveLayersBoard.innerHTML = adaptiveLayers.length
                ? adaptiveLayers.slice(0, 10).map((layer) => `
                    <div class="is-${escapeHtml(dataModeClass(layer.status))}">
                        <span>${escapeHtml(layer.name || "Adaptive layer")}</span>
                        <strong>${escapeHtml(layer.score ?? 0)}/100</strong>
                        <code>${escapeHtml(layer.measurement || layer.status || "watch")}</code>
                        <small>${escapeHtml(layer.detail || "Independent adaptive intelligence layer.")}</small>
                    </div>
                `).join("")
                : `<div class="is-collecting-memory"><span>Adaptive layers</span><strong>Collecting</strong><code>evidence_gated</code><small>Layered intelligence appears after Phase 3 feature extraction.</small></div>`;
        }

        if (adaptiveOrchestration) {
            adaptiveOrchestration.innerHTML = adaptiveRules.length
                ? adaptiveRules.map((rule) => `
                    <div class="is-${escapeHtml(dataModeClass(rule.status))}">
                        <span>${escapeHtml(rule.name || "Orchestration rule")}</span>
                        <strong>${escapeHtml(String(rule.status || "armed").replace(/_/g, " "))}</strong>
                        <small>${escapeHtml(rule.effect || "Adaptive coordination effect.")}</small>
                    </div>
                `).join("")
                : `<div class="is-armed"><span>Orchestration</span><strong>Armed</strong><small>Coordination rules appear after adaptive state is available.</small></div>`;
        }

        if (selfEvaluationBoard) {
            selfEvaluationBoard.innerHTML = selfEvaluationMonitors.length
                ? selfEvaluationMonitors.slice(0, 7).map((monitor) => `
                    <div class="is-${escapeHtml(dataModeClass(monitor.status))}">
                        <span>${escapeHtml(monitor.name || "Self-evaluation")}</span>
                        <strong>${escapeHtml(monitor.value || "Monitoring")}</strong>
                        <em>${escapeHtml(String(monitor.status || "watch").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(monitor.detail || "Reliability monitor.")}</small>
                    </div>
                `).join("")
                : `<div class="is-collecting-labels"><span>Self-evaluation</span><strong>Collecting</strong><small>Reliability monitors need adaptive state and warehouse history.</small></div>`;
        }

        if (historicalPatternsBoard) {
            historicalPatternsBoard.innerHTML = historicalPatterns.length
                ? historicalPatterns.map((pattern) => `
                    <div class="is-${escapeHtml(dataModeClass(pattern.status))}">
                        <span>${escapeHtml(pattern.name || "Pattern")}</span>
                        <strong>${escapeHtml(pattern.value || "Collecting")}</strong>
                        <em>${escapeHtml(String(pattern.status || "collecting").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(pattern.detail || "Historical pattern system.")}</small>
                    </div>
                `).join("")
                : `<div class="is-collecting-memory"><span>Historical patterns</span><strong>Collecting</strong><small>Similarity systems need deeper replay archives.</small></div>`;
        }

        if (operatorMemoryBoard) {
            operatorMemoryBoard.innerHTML = operatorMemory.length
                ? operatorMemory.map((artifact) => `
                    <div class="is-${escapeHtml(dataModeClass(artifact.status))}">
                        <span>${escapeHtml(artifact.name || "Operator memory")}</span>
                        <strong>${escapeHtml(artifact.value || "0 records")}</strong>
                        <em>${escapeHtml(String(artifact.status || "ready_empty").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(artifact.detail || "Operator knowledge artifact.")}</small>
                    </div>
                `).join("")
                : `<div class="is-ready-empty"><span>Operator memory</span><strong>Ready empty</strong><small>Notes, bookmarks, tags, and retrospectives are structured but empty.</small></div>`;
        }

        if (resilienceSystemsBoard) {
            resilienceSystemsBoard.innerHTML = resilienceSystems.length
                ? resilienceSystems.map((system) => `
                    <div class="is-${escapeHtml(dataModeClass(system.status))}">
                        <span>${escapeHtml(system.name || "Resilience system")}</span>
                        <strong>${escapeHtml(system.value || "Armed")}</strong>
                        <em>${escapeHtml(String(system.status || "armed").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(system.detail || "Failure-aware platform behavior.")}</small>
                    </div>
                `).join("")
                : `<div class="is-armed"><span>Resilience</span><strong>Armed</strong><small>Failure-aware systems appear after adaptive state is available.</small></div>`;
        }

        if (adaptiveExplanationsBoard) {
            adaptiveExplanationsBoard.innerHTML = adaptiveExplanations.length
                ? adaptiveExplanations.slice(0, 6).map((explanation) => `
                    <div class="is-${escapeHtml(dataModeClass(explanation.status))}">
                        <span>${escapeHtml(explanation.title || "Explanation")}</span>
                        <strong>${escapeHtml(String(explanation.status || "watch").replace(/_/g, " "))}</strong>
                        <small>${escapeHtml(`${explanation.reason || ""} ${explanation.impact || ""}`.trim() || "Adaptive explanation.")}</small>
                    </div>
                `).join("")
                : `<div class="is-explainable-watch"><span>Explanations</span><strong>Collecting</strong><small>Reasoning appears after adaptive layers evaluate.</small></div>`;
        }

        if (generalizedSummary) {
            const cards = [
                { label: "Core", value: String(generalizedSummaryData.status || "domain_agnostic_foundation").replace(/_/g, " "), detail: generalized.identity?.name || "adaptive probabilistic decision infrastructure", tone: generalizedSummaryData.status || "domain-agnostic-foundation" },
                { label: "Readiness", value: `${generalizedSummaryData.coreReadiness ?? 0}/100`, detail: "Built on adaptive infrastructure", tone: generalizedSummaryData.status || "domain-agnostic-foundation" },
                { label: "Domains", value: generalizedSummaryData.domainAdapters ?? domainAdapters.length, detail: `${generalizedSummaryData.activeDomains ?? 0} active adapter`, tone: "active-domain" },
                { label: "Events", value: generalizedSummaryData.eventPrimitives ?? primitiveEvents.length, detail: "Universal event model", tone: "defined" },
                { label: "Signals", value: generalizedSummaryData.signalPrimitives ?? primitiveSignals.length, detail: "Universal signal model", tone: "defined" },
                { label: "Governance", value: generalizedSummaryData.governanceSystems ?? generalizedGovernance.length, detail: "Controls remain first-class", tone: generalized.governance?.status || "governance-first" },
            ];
            generalizedSummary.innerHTML = cards.map((card) => `
                <div class="is-${escapeHtml(dataModeClass(card.tone))}">
                    <span>${escapeHtml(card.label)}</span>
                    <strong>${escapeHtml(card.value)}</strong>
                    <small>${escapeHtml(card.detail)}</small>
                </div>
            `).join("");
        }

        if (domainAdaptersBoard) {
            domainAdaptersBoard.innerHTML = domainAdapters.length
                ? domainAdapters.map((domain) => `
                    <div class="is-${escapeHtml(dataModeClass(domain.status))}">
                        <span>${escapeHtml(domain.name || "Domain adapter")}</span>
                        <strong>${escapeHtml(domain.readiness ?? 0)}/100</strong>
                        <code>${escapeHtml(domain.status || "planned_domain")}</code>
                        <small>${escapeHtml(domain.detail || "Domain adapter blueprint.")}</small>
                    </div>
                `).join("")
                : `<div class="is-adapter-blueprint"><span>Domain adapters</span><strong>Blueprint</strong><code>planned</code><small>Sports is the active adapter; other domains require official data sources.</small></div>`;
        }

        if (universalPrimitivesBoard) {
            const primitiveRows = [...primitiveEvents.slice(0, 3), ...primitiveSignals.slice(0, 3)];
            universalPrimitivesBoard.innerHTML = primitiveRows.length
                ? primitiveRows.map((primitive) => `
                    <div class="is-${escapeHtml(dataModeClass(primitive.status))}">
                        <span>${escapeHtml(primitive.name || "Primitive")}</span>
                        <strong>${escapeHtml(primitive.value || "Defined")}</strong>
                        <small>${escapeHtml(primitive.detail || "Universal intelligence primitive.")}</small>
                    </div>
                `).join("")
                : `<div class="is-defined"><span>Primitives</span><strong>Defined</strong><small>Universal event and signal primitives are waiting for state.</small></div>`;
        }

        if (generalizedOrchestrationBoard) {
            generalizedOrchestrationBoard.innerHTML = generalizedPipelines.length
                ? generalizedPipelines.map((pipeline) => `
                    <div class="is-${escapeHtml(dataModeClass(pipeline.status))}">
                        <span>${escapeHtml(pipeline.name || "Pipeline")}</span>
                        <strong>${escapeHtml(pipeline.value || "Active")}</strong>
                        <em>${escapeHtml(String(pipeline.status || "armed").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(pipeline.detail || "Orchestration pipeline.")}</small>
                    </div>
                `).join("")
                : `<div class="is-armed"><span>Orchestration</span><strong>Armed</strong><small>Universal pipelines appear when generalized state is present.</small></div>`;
        }

        if (contextualMemoryBoard) {
            contextualMemoryBoard.innerHTML = contextualMemory.length
                ? contextualMemory.slice(0, 8).map((store) => `
                    <div class="is-${escapeHtml(dataModeClass(store.status))}">
                        <span>${escapeHtml(store.name || "Memory")}</span>
                        <strong>${escapeHtml(store.value || "0")}</strong>
                        <em>${escapeHtml(String(store.status || "memory_active").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(store.detail || "Contextual memory store.")}</small>
                    </div>
                `).join("")
                : `<div class="is-memory-warming"><span>Memory</span><strong>Warming</strong><small>Contextual memory stores need warehouse snapshots.</small></div>`;
        }

        if (selfOptimizationBoard) {
            selfOptimizationBoard.innerHTML = selfOptimization.length
                ? selfOptimization.map((system) => `
                    <div class="is-${escapeHtml(dataModeClass(system.status))}">
                        <span>${escapeHtml(system.name || "Optimization")}</span>
                        <strong>${escapeHtml(system.value || "Watch")}</strong>
                        <em>${escapeHtml(String(system.status || "planned").replace(/_/g, " "))}</em>
                        <small>${escapeHtml(system.detail || "Self-optimization system.")}</small>
                    </div>
                `).join("")
                : `<div class="is-planned"><span>Optimization</span><strong>Planned</strong><small>Self-optimization needs operational history and calibration.</small></div>`;
        }

        if (adaptiveWorkspacesBoard) {
            adaptiveWorkspacesBoard.innerHTML = adaptiveWorkspaces.length
                ? adaptiveWorkspaces.map((workspace) => `
                    <div class="is-${escapeHtml(dataModeClass(workspace.status))}">
                        <span>${escapeHtml(workspace.name || "Workspace")}</span>
                        <strong>${escapeHtml(String(workspace.status || "armed").replace(/_/g, " "))}</strong>
                        <small>${escapeHtml(`${workspace.trigger || ""} ${workspace.detail || ""}`.trim() || "Context-aware workspace trigger.")}</small>
                    </div>
                `).join("")
                : `<div class="is-armed"><span>Adaptive workspaces</span><strong>Armed</strong><small>Workspace triggers appear when generalized state is present.</small></div>`;
        }

        if (generalizedGovernanceBoard) {
            generalizedGovernanceBoard.innerHTML = generalizedGovernance.length
                ? generalizedGovernance.slice(0, 8).map((system) => `
                    <div class="is-${escapeHtml(dataModeClass(system.status))}">
                        <span>${escapeHtml(system.name || "Governance")}</span>
                        <strong>${escapeHtml(String(system.status || "active").replace(/_/g, " "))}</strong>
                        <small>${escapeHtml(system.detail || "Governance control.")}</small>
                    </div>
                `).join("")
                : `<div class="is-governance-first"><span>Governance</span><strong>First-class</strong><small>Governance controls appear when generalized state is present.</small></div>`;
        }
    };

    const renderBooks = (items = []) => {
        if (!bookGrid) return;
        bookGrid.innerHTML = items.length
            ? items.map((item) => `<div><span>${escapeHtml(item.book || "Feed")}</span><strong>${escapeHtml(item.line || "Market")}</strong><small>${escapeHtml(item.odds || "--")} / ${escapeHtml(item.latency || "Now")}</small></div>`).join("")
            : `<div><span>Feed</span><strong>No book rows yet</strong><small>Provider links will appear when market data is available.</small></div>`;
    };

    const renderFeedGrid = (container, items = []) => {
        if (!container) return;
        container.innerHTML = items.map((item) => `
            <div>
                <span>${escapeHtml(item.name || item.label || "Item")}</span>
                <strong>${escapeHtml(item.value || item.state || "")}</strong>
                ${item.tag ? `<em>${escapeHtml(item.tag)}</em>` : ""}
                <small>${escapeHtml(item.detail || "")}</small>
            </div>
        `).join("");
    };

    const arbState = () => latestSportsState.arbitrage && typeof latestSportsState.arbitrage === "object"
        ? latestSportsState.arbitrage
        : {};
    const arbOpportunities = () => Array.isArray(arbState().opportunities) ? arbState().opportunities : [];
    const arbGradeClass = (grade) => `grade-${String(grade || "c").toLowerCase().replace(/[^a-z0-9]+/g, "") || "c"}`;
    const arbOdds = (value) => {
        const price = Number(value);
        if (!Number.isFinite(price) || price === 0) return "--";
        return price > 0 ? `+${Math.round(price)}` : `${Math.round(price)}`;
    };
    const arbPercent = (value, digits = 2) => `${(Number(value) || 0).toFixed(digits)}%`;
    const arbAge = (value, label = "") => label || (Number.isFinite(Number(value)) ? `${Math.round(Number(value))}s` : "No odds");
    const arbEmpty = (title, detail) => `
        <div class="lineforge-arb-empty">
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(detail)}</span>
        </div>
    `;

    const renderArbSummary = (summary = {}) => {
        if (!arbSummary) return;
        const cards = [
            {
                label: "Active arbitrage",
                value: summary.activeArbs ?? 0,
                detail: `${summary.normalizedOdds ?? 0} normalized odds rows`,
                tone: Number(summary.activeArbs || 0) > 0 ? "good" : "neutral",
            },
            {
                label: "Best guaranteed ROI",
                value: arbPercent(summary.bestGuaranteedRoi ?? 0),
                detail: "Pure-arb ROI before user-entered limits",
                tone: Number(summary.bestGuaranteedRoi || 0) > 0 ? "good" : "neutral",
            },
            {
                label: "Average freshness",
                value: summary.averageFreshnessLabel || "No odds",
                detail: `${summary.freshRows ?? 0} rows inside the fresh window`,
                tone: Number(summary.averageFreshnessSeconds || 99999) <= 180 ? "good" : "warn",
            },
            {
                label: "Connected books",
                value: summary.connectedBooks ?? 0,
                detail: "Provider books with normalized outcomes",
                tone: Number(summary.connectedBooks || 0) >= 2 ? "good" : "warn",
            },
            {
                label: "Rejected matches",
                value: summary.rejectedOpportunities ?? 0,
                detail: "Bad, stale, unmatched, or non-arb markets",
                tone: Number(summary.rejectedOpportunities || 0) > 0 ? "warn" : "neutral",
            },
            {
                label: "Provider health",
                value: String(summary.providerHealth || "needs_data").replace(/_/g, " "),
                detail: "Execution remains manual/data-only here",
                tone: summary.providerHealth === "operational" ? "good" : "warn",
            },
        ];
        arbSummary.innerHTML = cards.map((card) => `
            <div class="lineforge-arb-summary-card is-${escapeHtml(card.tone)}">
                <span>${escapeHtml(card.label)}</span>
                <strong>${escapeHtml(card.value)}</strong>
                <small>${escapeHtml(card.detail)}</small>
            </div>
        `).join("");
    };

    const renderArbProviderHealth = (arbitrage = {}) => {
        if (!arbProviderHealth) return;
        const providers = Array.isArray(arbitrage.providerHealth?.providers)
            ? arbitrage.providerHealth.providers
            : (Array.isArray(arbitrage.supportedSources) ? arbitrage.supportedSources : []);
        if (arbHealthLabel) {
            arbHealthLabel.textContent = `Overall: ${String(arbitrage.providerHealth?.overall || "needs_data").replace(/_/g, " ")}`;
        }
        if (arbRefresh) {
            const refresh = arbitrage.refresh || {};
            const cadence = refresh.intervalSeconds ? `${refresh.intervalSeconds}s cache` : "Provider-budget cache";
            arbRefresh.textContent = cadence;
        }
        arbProviderHealth.innerHTML = providers.length
            ? providers.map((provider) => {
                const status = provider.status || "not_configured";
                const mode = provider.execution || provider.mode || "data_only";
                return `
                    <div class="lineforge-arb-provider is-${escapeHtml(String(status).replace(/[^a-z0-9_-]+/gi, "-").toLowerCase())}">
                        <span>${escapeHtml(provider.name || "Provider")}</span>
                        <strong>${escapeHtml(String(status).replace(/_/g, " "))}</strong>
                        <small>${escapeHtml(mode)}${provider.message ? ` - ${escapeHtml(provider.message)}` : ""}</small>
                    </div>
                `;
            }).join("")
            : arbEmpty("No provider adapters configured", "Connect an official/licensed odds feed to populate the comparison engine.");
    };

    const renderArbTable = (items = []) => {
        if (!arbTable) return;
        if (!items.length) {
            arbTable.innerHTML = arbEmpty("No pure arbitrage detected", "Lineforge still compares markets and logs rejections, middles, and +EV candidates separately.");
            return;
        }
        arbTable.innerHTML = `
            <div class="lineforge-arb-table-scroll">
                <div class="lineforge-arb-row lineforge-arb-head">
                    <span>Sport</span>
                    <span>Event</span>
                    <span>Market</span>
                    <span>Outcome A</span>
                    <span>Book A</span>
                    <span>Odds A</span>
                    <span>Outcome B</span>
                    <span>Book B</span>
                    <span>Odds B</span>
                    <span>Arb %</span>
                    <span>Stake</span>
                    <span>Profit</span>
                    <span>Age</span>
                    <span>Grade</span>
                    <span>Action</span>
                </div>
                ${items.map((item, index) => {
                    const legs = Array.isArray(item.stakePlan) ? item.stakePlan : [];
                    const legA = legs[0] || {};
                    const legB = legs[1] || {};
                    const extraLegs = legs.length > 2 ? `+${legs.length - 2}` : "";
                    return `
                        <div class="lineforge-arb-row">
                            <span>${escapeHtml(item.sport || item.league || "Sports")}</span>
                            <strong title="${escapeHtml(item.event || "")}">${escapeHtml(item.event || "Event")}</strong>
                            <span>${escapeHtml(item.market || "Market")}${item.line && item.line !== "na" ? ` ${escapeHtml(item.line)}` : ""}</span>
                            <span>${escapeHtml(legA.selection || "--")}</span>
                            <span>${escapeHtml(legA.book || "--")}</span>
                            <b>${escapeHtml(arbOdds(legA.americanOdds))}</b>
                            <span>${escapeHtml(legB.selection || extraLegs || "--")}</span>
                            <span>${escapeHtml(legB.book || (extraLegs ? "Multiple" : "--"))}</span>
                            <b>${escapeHtml(arbOdds(legB.americanOdds))}</b>
                            <em class="lineforge-arb-roi">${escapeHtml(arbPercent(item.arbMarginPercent ?? item.roiPercent ?? 0, 3))}</em>
                            <span>${escapeHtml(money(item.totalStake || 0))}</span>
                            <strong class="lineforge-arb-profit">${escapeHtml(money(item.guaranteedProfit || 0))}</strong>
                            <span>${escapeHtml(arbAge(item.dataAgeSeconds, item.dataAgeLabel))}</span>
                            <span class="lineforge-arb-grade ${escapeHtml(arbGradeClass(item.grade))}">${escapeHtml(item.grade || "C")}</span>
                            <button type="button" data-arb-open="${escapeHtml(index)}">Inspect</button>
                        </div>
                    `;
                }).join("")}
            </div>
        `;
    };

    const renderArbSignalBoard = (container, items = [], type = "middle") => {
        if (!container) return;
        if (!items.length) {
            const label = type === "positive" ? "No +EV outliers" : "No middle/scalp candidates";
            const detail = type === "positive"
                ? "Consensus prices are not far enough from any book to flag positive EV."
                : "Line gaps are either absent or not strong enough to separate from normal market spread.";
            container.innerHTML = arbEmpty(label, detail);
            return;
        }
        container.innerHTML = items.slice(0, 12).map((item) => `
            <article class="lineforge-arb-signal">
                <div>
                    <span>${escapeHtml(item.type || item.market || (type === "positive" ? "Positive EV" : "Middle"))}</span>
                    <strong>${escapeHtml(item.event || "Event")}</strong>
                    <small>${escapeHtml(item.detail || item.warning || `${item.selection || ""} ${item.book || ""}`)}</small>
                </div>
                <b>${type === "positive" ? escapeHtml(arbPercent(item.edgePercent || 0)) : escapeHtml(item.line || item.grade || "Watch")}</b>
            </article>
        `).join("");
    };

    const renderArbRejected = (items = []) => {
        if (!arbRejectedBoard) return;
        if (!items.length) {
            arbRejectedBoard.innerHTML = arbEmpty("No rejected market matches", "Rejected opportunities appear here when markets are stale, unmatched, suspended, or not true arbitrage.");
            return;
        }
        arbRejectedBoard.innerHTML = items.slice(0, 14).map((item) => `
            <article class="lineforge-arb-reject">
                <div>
                    <span>${escapeHtml(item.market || "Market")} ${item.line && item.line !== "na" ? escapeHtml(item.line) : ""}</span>
                    <strong>${escapeHtml(item.event || "Event")}</strong>
                    <small>${escapeHtml(item.reason || "Rejected by matching engine")}</small>
                </div>
                <div>
                    <b>${escapeHtml(item.arbSum === null || item.arbSum === undefined ? "--" : Number(item.arbSum).toFixed(4))}</b>
                    <em>${escapeHtml(item.matchingConfidence ?? 0)}% match</em>
                </div>
            </article>
        `).join("");
    };

    const closeArbDetail = () => {
        if (!arbDrawer) return;
        arbDrawer.hidden = true;
        document.body.classList.remove("lineforge-arb-open");
    };

    const openArbDetail = (index = 0) => {
        const item = arbOpportunities()[Number(index)];
        if (!item || !arbDrawer) return;
        const stakePlan = Array.isArray(item.stakePlan) ? item.stakePlan : [];
        const allOdds = Array.isArray(item.allOdds) ? item.allOdds : [];
        const bestKeys = new Set(stakePlan.map((leg) => `${leg.providerKey || ""}|${leg.selectionKey || ""}`));
        const consensus = item.consensus?.outcomes && typeof item.consensus.outcomes === "object"
            ? Object.values(item.consensus.outcomes)
            : [];

        if (arbDrawerTitle) {
            arbDrawerTitle.textContent = `${item.event || "Market"} - ${item.market || "Arbitrage"}`;
        }
        if (arbDrawerMeta) {
            arbDrawerMeta.textContent = `${item.sport || item.league || "Sports"} / ${arbPercent(item.roiPercent || 0)} ROI / grade ${item.grade || "C"} / ${arbAge(item.dataAgeSeconds, item.dataAgeLabel)}`;
        }
        if (arbDrawerOdds) {
            arbDrawerOdds.innerHTML = allOdds.length
                ? allOdds.slice(0, 80).map((row) => {
                    const isBest = bestKeys.has(`${row.providerKey || ""}|${row.selectionKey || ""}`);
                    const margin = Number(row.sportsbookMargin || 0) * 100;
                    const noVig = Number(row.noVigProbability || 0) * 100;
                    const raw = Number(row.rawImpliedProbability || 0) * 100;
                    return `
                        <div class="lineforge-arb-odds-row ${isBest ? "is-best" : ""}">
                            <span>${escapeHtml(row.providerName || "Book")}</span>
                            <strong>${escapeHtml(row.selection || "Outcome")}</strong>
                            <b>${escapeHtml(arbOdds(row.oddsAmerican))}</b>
                            <small>Raw ${escapeHtml(raw.toFixed(1))}% / no-vig ${escapeHtml(noVig.toFixed(1))}% / margin ${escapeHtml(margin.toFixed(1))}% / age ${escapeHtml(arbAge(row.ageSeconds))}</small>
                        </div>
                    `;
                }).join("")
                : arbEmpty("No odds rows attached", "The audit row did not include sportsbook odds rows for this opportunity.");
        }
        if (arbDrawerStake) {
            arbDrawerStake.innerHTML = stakePlan.length
                ? stakePlan.map((leg) => `
                    <div class="lineforge-arb-stake-leg">
                        <span>${escapeHtml(leg.book || "Book")}</span>
                        <strong>${escapeHtml(leg.selection || "Outcome")} ${escapeHtml(arbOdds(leg.americanOdds))}</strong>
                        <small>Stake ${escapeHtml(money(leg.stake || 0))} / payout ${escapeHtml(money(leg.expectedPayout || 0))}</small>
                    </div>
                `).join("") + `
                    <div class="lineforge-arb-stake-total">
                        <span>Total stake ${escapeHtml(money(item.totalStake || 0))}</span>
                        <strong>Guaranteed profit ${escapeHtml(money(item.guaranteedProfit || 0))}</strong>
                        <small>Guaranteed payout ${escapeHtml(money(item.guaranteedPayout || 0))} / arb sum ${escapeHtml(Number(item.arbSum || 0).toFixed(6))}</small>
                    </div>
                `
                : arbEmpty("No stake plan", "Stake sizing requires at least two best-outcome legs.");
        }
        if (arbDrawerConsensus) {
            arbDrawerConsensus.innerHTML = consensus.length
                ? consensus.map((row) => `
                    <div class="lineforge-arb-consensus-row">
                        <span>${escapeHtml(row.selection || row.selectionKey || "Outcome")}</span>
                        <strong>${escapeHtml(arbPercent((Number(row.weightedNoVigProbability) || 0) * 100))}</strong>
                        <small>Median decimal ${(Number(row.medianDecimalOdds) || 0).toFixed(3)} / ${escapeHtml(row.providerCount || 0)} books</small>
                    </div>
                `).join("")
                : arbEmpty("No consensus data", "No-vig consensus needs multiple books on the same normalized market.");
        }
        if (arbDrawerWarnings) {
            const warnings = Array.isArray(item.riskWarnings) ? item.riskWarnings : [];
            arbDrawerWarnings.innerHTML = warnings.length
                ? warnings.map((warning) => `<div class="lineforge-arb-warning">${escapeHtml(warning)}</div>`).join("")
                : `<div class="lineforge-arb-warning is-clear">No engine warnings. Still re-check every price manually.</div>`;
        }
        if (arbDrawerChecklist) {
            const checklist = Array.isArray(item.manualChecklist) ? item.manualChecklist : [];
            arbDrawerChecklist.innerHTML = checklist.map((step) => `<div class="lineforge-arb-check">${escapeHtml(step)}</div>`).join("");
        }
        if (arbDrawerExport) {
            arbDrawerExport.textContent = item.exportSlipNotes || "No slip notes generated.";
        }

        arbDrawer.hidden = false;
        document.body.classList.add("lineforge-arb-open");
    };

    const renderArbitrageDashboard = (arbitrage = arbState()) => {
        const summary = arbitrage.summary || {};
        renderArbSummary(summary);
        renderArbProviderHealth(arbitrage);
        renderArbTable(Array.isArray(arbitrage.opportunities) ? arbitrage.opportunities : []);
        const middles = [
            ...(Array.isArray(arbitrage.middles) ? arbitrage.middles : []),
            ...(Array.isArray(arbitrage.scalps) ? arbitrage.scalps : []),
        ];
        renderArbSignalBoard(arbMiddleBoard, middles, "middle");
        renderArbSignalBoard(arbPositiveEvBoard, Array.isArray(arbitrage.positiveEv) ? arbitrage.positiveEv : [], "positive");
        renderArbRejected(Array.isArray(arbitrage.rejected) ? arbitrage.rejected : []);
    };

    const renderAnalytics = () => {
        renderChipGrid(factorGrid, latestSportsState.factors || []);
        renderCoverage(latestSportsState.coverage || {});
        renderProviderGrid(latestSportsState.marketAccess || {});
        renderBooks(latestSportsState.books || []);
        renderDataArchitecture();
        renderFeedGrid(opportunityBoard, latestSportsState.opportunities || []);
        renderChipGrid(edgeStack, latestSportsState.edgeStack || []);
        renderFeedGrid(rulesBoard, latestSportsState.rules || []);
        renderFeedGrid(performanceBoard, latestSportsState.performance || []);
        renderModelSources();
        renderArbitrageDashboard();
    };

    const renderInsight = () => {
        if (insightCopy) {
            insightCopy.textContent = latestSportsState.insight?.copy || latestSportsState.topPick?.reason || predictions()[0]?.reason || "Lineforge is monitoring live status, market snapshots, and risk context.";
        }
        if (marketTitle) {
            marketTitle.textContent = latestSportsState.primaryMarket || "Primary market";
        }
        renderTimeline(games()[0]?.history || []);
        renderMarketLine(latestSportsState.marketHistory || games()[0]?.history || []);
    };

    const showSportsView = (view = "dashboard") => {
        activeSportsView = view;
        const workspaceModeByView = {
            dashboard: "command",
            live: "monitoring",
            picks: "signals",
            analytics: "analyst",
            arbitrage: "markets",
            execution: "execution",
        };
        if (workspaceModeByView[view] && workspaceState.mode !== workspaceModeByView[view]) {
            workspaceState.mode = workspaceModeByView[view];
            writeWorkspaceState();
        }
        if (dashboardHero) {
            dashboardHero.hidden = view !== "dashboard";
            dashboardHero.classList.toggle("is-active", view === "dashboard");
        }
        root.querySelectorAll("[data-sports-panel]").forEach((panel) => {
            const visible = (panel.dataset.sportsPanel || "dashboard") === view;
            panel.hidden = !visible;
            panel.classList.toggle("is-active", visible);
        });
        root.querySelectorAll("[data-sports-view]").forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.sportsView || "") === view);
        });
        syncWorkspace();
        root.querySelector(".betedge-main")?.scrollTo({ top: 0, behavior: "smooth" });
    };

    root.querySelectorAll("[data-sports-view]").forEach((control) => {
        if (control.dataset.sportsViewBound === "true") return;
        control.dataset.sportsViewBound = "true";
        control.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            showSportsView(control.dataset.sportsView || "dashboard");
            const providerJump = control.dataset.sportsProviderJump;
            if (providerJump) {
                activeProviderFilter = providerJump;
                root.querySelectorAll("[data-sports-provider-filter]").forEach((button) => {
                    button.classList.toggle("is-active", (button.dataset.sportsProviderFilter || "all") === providerJump);
                });
                renderMarketBoard();
            }
        });
    });

    const hydrateSlip = () => {
        if (!slipList) {
            slipItems = [];
            return;
        }
        const initial = Array.from(slipList?.querySelectorAll("[data-initial-slip]") || []);
        slipItems = initial.map((node, index) => ({
            id: `${node.dataset.initialSlip || index}:${node.dataset.slipPick || "pick"}:${node.dataset.slipMatchup || ""}:${node.dataset.slipMarket || ""}`,
            predictionIndex: Number(node.dataset.initialSlip || index),
            pick: node.dataset.slipPick || "Pick",
            market: node.dataset.slipMarket || "Market",
            matchup: node.dataset.slipMatchup || "Matchup",
            odds: node.dataset.slipOdds || "-110",
            stake: Number(node.dataset.slipStake || 100),
        }));
        if (!slipItems.length) {
            predictions().filter((item) => item.canBet !== false).slice(0, 2).forEach((item, index) => {
                slipItems.push({
                    id: `${index}:${item.pick || "pick"}`,
                    predictionIndex: index,
                    pick: item.pick || "Pick",
                    market: item.market || "Market",
                    matchup: item.matchup || "Matchup",
                    odds: pickOdds(item),
                    stake: 100,
                });
            });
        }
    };

    const renderSlip = () => {
        if (!slipList) return;
        slipList.innerHTML = slipItems.length
            ? slipItems.map((item, index) => {
                const stake = Math.max(0, Number(item.stake) || 0);
                return `
                    <div class="betedge-slip-item" data-slip-index="${escapeHtml(index)}">
                        <div><strong>${escapeHtml(item.pick)}</strong><b>${escapeHtml(item.odds)}</b></div>
                        <span>${escapeHtml(item.market)}</span>
                        <button type="button" data-sports-remove-slip="${escapeHtml(index)}" title="Remove pick">x</button>
                        <small>${escapeHtml(item.matchup)}</small>
                        <label><span>$</span><input type="number" min="1" step="1" value="${escapeHtml(stake.toFixed(0))}" data-sports-slip-stake="${escapeHtml(index)}"></label>
                        <em>To Win: ${money(toWin(item.odds, stake))}</em>
                    </div>
                `;
            }).join("")
            : `<div class="betedge-my-bets"><div><strong>No picks in the slip.</strong><span>Add a model pick from the AI table.</span></div></div>`;

        const totalStake = slipItems.reduce((sum, item) => sum + Math.max(0, Number(item.stake) || 0), 0);
        const decimal = slipItems.reduce((product, item) => product * decimalFromAmerican(item.odds), 1);
        const payout = slipItems.length ? totalStake * Math.max(0, decimal - 1) : 0;

        if (slipCount) slipCount.textContent = String(slipItems.length);
        if (parlayLabel) parlayLabel.textContent = `${slipItems.length > 1 ? "Parlay" : "Straight"} (${slipItems.length} Pick${slipItems.length === 1 ? "" : "s"})`;
        if (parlayOdds) parlayOdds.textContent = slipItems.length ? americanFromDecimal(decimal) : "--";
        if (totalWager) totalWager.textContent = money(totalStake);
        if (totalWin) totalWin.textContent = money(payout);
        if (placeBetButton) placeBetButton.disabled = !slipItems.length;
    };

    const addSlipFromPrediction = (index) => {
        if (!slipList) return;
        const pick = predictions()[index];
        if (!pick) return;
        if (pick.canBet === false) {
            setSlipNote("That market is closed for new tracking.");
            return;
        }
        const id = `${index}:${pick.pick || "pick"}:${pick.matchup || ""}:${pick.market || ""}`;
        if (slipItems.some((item) => item.id === id || (item.pick === (pick.pick || "Pick") && item.matchup === (pick.matchup || "Matchup") && item.market === (pick.market || "Market")))) {
            setSlipNote("That pick is already in your slip.");
            return;
        }
        slipItems.push({
            id,
            predictionIndex: index,
            pick: pick.pick || "Pick",
            market: pick.market || "Market",
            matchup: pick.matchup || "Matchup",
            odds: pickOdds(pick),
            stake: 100,
        });
        setSlipNote("Pick added.");
        renderSlip();
    };

    const renderMyBets = () => {
        if (!myBetsList) return;
        const bets = readBets();
        myBetsList.innerHTML = bets.length
            ? bets.map((bet) => `
                <div>
                    <strong>${escapeHtml(bet.label || "Tracked slip")}</strong>
                    <span>${escapeHtml(bet.picks || "")}</span>
                    <small>${escapeHtml(bet.createdAt || "")} / Wager ${escapeHtml(bet.wager || "$0.00")} / To win ${escapeHtml(bet.toWin || "$0.00")}</small>
                </div>
            `).join("")
            : `<div><strong>No tracked bets yet.</strong><span>Place the paper slip to save it here.</span></div>`;
    };

    const placePaperBet = () => {
        if (!slipItems.length) {
            setSlipNote("Add a pick before placing a paper bet.");
            return;
        }
        const totalStake = slipItems.reduce((sum, item) => sum + Math.max(0, Number(item.stake) || 0), 0);
        const decimal = slipItems.reduce((product, item) => product * decimalFromAmerican(item.odds), 1);
        const bets = readBets();
        bets.unshift({
            label: slipItems.length > 1 ? `Parlay ${americanFromDecimal(decimal)}` : `${slipItems[0].pick} ${slipItems[0].odds}`,
            picks: slipItems.map((item) => item.pick).join(" / "),
            wager: money(totalStake),
            toWin: money(totalStake * Math.max(0, decimal - 1)),
            createdAt: new Date().toLocaleString([], { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }),
        });
        writeBets(bets);
        slipItems = [];
        setSlipNote("Paper bet tracked.");
        renderSlip();
        renderMyBets();
        root.querySelector('[data-sports-slip-tab="bets"]')?.click();
    };

    const fallbackBreakdown = (pick = {}) => {
        const confidence = Number.parseInt(String(pick.confidenceValue || pick.confidence || "58"), 10) || 58;
        return {
            summary: "Lineforge treats research confidence as a weighted checklist. It adds available signals and subtracts risk for missing lineup, fatigue, advanced metric, and player matchup data.",
            score: {
                confidence: pick.confidence || `${confidence}%`,
                fairProbability: pick.fairProbability || `${confidence}%`,
                fairOdds: pick.fairOdds || "--",
                edge: pick.edge || "+0.0%",
                risk: pick.risk || "Model risk",
                detail: "This is a probability estimate for comparison and monitoring, not a guarantee.",
            },
            steps: [
                { label: "1. Start neutral", value: "50%", detail: "Every pick begins from a neutral 50% baseline." },
                { label: "2. Add game context", value: pick.statusLabel || "Watch", detail: "Live, scheduled, and final games are scored differently." },
                { label: "3. Add market evidence", value: pick.market || "Monitor", detail: "Spread, total, and sportsbook depth raise or lower the calibration estimate." },
                { label: "4. Add model evidence", value: pick.edge || "+0.0%", detail: "Team-strength proxy, form proxy, market disagreement, and model agreement are blended." },
                { label: "5. Subtract uncertainty", value: "Applied", detail: "Missing injuries, lineups, fatigue, and player matchup feeds keep research confidence conservative." },
                { label: "6. Calibration estimate", value: pick.confidence || `${confidence}%`, detail: "Final displayed informational estimate." },
            ],
            factors: [
                { label: "Core team strength", value: "Partial", status: "Partial", detail: "Using score, status, and line proxies until full team ratings are connected." },
                { label: "Recent form", value: "Proxy", status: "Partial", detail: "Using trend history as a momentum proxy." },
                { label: "Betting market data", value: pick.market || "Monitor", status: "Partial", detail: "Connect live odds for deeper market disagreement." },
                { label: "Injuries and lineups", value: "Needs API key", status: "Needs setup", detail: "Player availability is not live-confirmed yet." },
            ],
            missingInputs: [{ label: "Injury feed", value: "Needs API key", detail: "Connect a verified injury/lineup provider before treating player availability as confirmed." }],
        };
    };

    const drawerToneClass = (value = "") => {
        const text = String(value).toLowerCase();
        if (text === "ok" || text.includes("ready") || text.includes("connect") || text.includes("active") || text.includes("proceed")) return "is-connected";
        if (text === "warn" || text.includes("watch") || text.includes("manual") || text.includes("partial") || text.includes("proxy") || text.includes("designed") || text.includes("re-check") || text.includes("confirm")) return "is-partial";
        if (text === "bad" || text.includes("no action") || text.includes("closed") || text.includes("disabled") || text.includes("need") || text.includes("missing") || text.includes("not live")) return "needs-setup";
        if (text.includes("-")) return "is-risk";
        return "is-neutral";
    };

    const renderDrawerScore = (pick = {}, breakdown = {}) => {
        if (!pickDrawerScore) return;
        const score = breakdown.score || {};
        const readiness = readinessForPick(pick);
        pickDrawerScore.innerHTML = `
            <div class="is-primary">
                <span>Calibration estimate</span>
                <strong>${escapeHtml(score.confidence || pick.confidence || "Watch")}</strong>
                <small>${escapeHtml(score.detail || "Informational probability estimate, not a guarantee.")}</small>
            </div>
            <div>
                <span>Fair probability</span>
                <strong>${escapeHtml(score.fairProbability || pick.fairProbability || pick.confidence || "--")}</strong>
                <small>Model probability before comparing available market prices.</small>
            </div>
            <div>
                <span>Market disagreement</span>
                <strong>${escapeHtml(score.edge || pick.edge || "+0.0%")}</strong>
                <small>${escapeHtml(score.risk || pick.risk || "Risk adjusted")}</small>
            </div>
            <div class="${drawerToneClass(readiness.tone || readiness.label || "")}">
                <span>Signal readiness</span>
                <strong>${escapeHtml(`${readiness.score ?? "--"}/100`)}</strong>
                <small>${escapeHtml(`${readiness.label || "Watch"} - ${readiness.detail || "Complete manual verification."}`)}</small>
            </div>
        `;
    };

    const renderTeamComparison = (comparison = {}) => {
        if (!pickDrawerComparison) return;
        const rows = Array.isArray(comparison.rows) ? comparison.rows.filter(Boolean) : [];
        const away = comparison.away || {};
        const home = comparison.home || {};
        const sideCard = (team, label) => `
            <div>
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(team.abbr || team.name || label)}</strong>
                <small>${escapeHtml(team.name || "")}</small>
                <b>${escapeHtml(String(team.probability || "50%"))}</b>
                <em>${escapeHtml(String(team.rating || 50))}/100 Lineforge rating</em>
            </div>
        `;

        pickDrawerComparison.innerHTML = `
            <div class="betedge-comparison-score">
                ${sideCard(away, "Away")}
                <div class="betedge-comparison-vs">
                    <span>Baseline</span>
                    <strong>50 / 50</strong>
                    <small>${escapeHtml(comparison.summary || "Lineforge starts neutral, then compares both teams with available evidence.")}</small>
                </div>
                ${sideCard(home, "Home")}
            </div>
            <div class="betedge-comparison-table">
                <div class="betedge-comparison-head">
                    <span>Factor</span>
                    <span>${escapeHtml(away.abbr || "Away")}</span>
                    <span>${escapeHtml(home.abbr || "Home")}</span>
                    <span>Read</span>
                </div>
                ${rows.length ? rows.map((row) => `
                    <div class="betedge-comparison-row">
                        <span>
                            <strong>${escapeHtml(row.label || "Signal")}</strong>
                            <small>${escapeHtml(row.detail || "")}</small>
                        </span>
                        <b>${escapeHtml(String(row.away || "--"))}</b>
                        <b>${escapeHtml(String(row.home || "--"))}</b>
                        <em>${escapeHtml(String(row.edge || "Even"))}</em>
                    </div>
                `).join("") : `
                    <div class="betedge-comparison-row">
                        <span><strong>No comparison yet</strong><small>Refresh after the public feed attaches matchup context.</small></span>
                        <b>--</b><b>--</b><em>Pending</em>
                    </div>
                `}
            </div>
        `;
    };

    const renderDrawerCards = (container, items = [], emptyCopy = "No detail available yet.") => {
        if (!container) return;
        const safeItems = Array.isArray(items) ? items.filter(Boolean) : [];
        container.innerHTML = safeItems.length
            ? safeItems.map((item) => {
                if (typeof item === "string") {
                    return `<div><strong>${escapeHtml(item)}</strong></div>`;
                }
                return `<div class="${drawerToneClass(item.status || item.tone || item.value || "")}"><span>${escapeHtml(item.label || item.title || "Signal")}</span><strong>${escapeHtml(item.value || item.market || item.status || "")}</strong><small>${escapeHtml(item.detail || item.note || "")}</small></div>`;
            }).join("")
            : `<div><strong>${escapeHtml(emptyCopy)}</strong></div>`;
    };

    const renderProviderLinks = (pick = {}) => {
        if (!pickDrawerProviders) return;
        const readiness = readinessForPick(pick);
        const lineShopping = readiness.lineShopping || {};
        const lineCards = Array.isArray(lineShopping.cards) ? lineShopping.cards.filter(Boolean) : [];
        const links = Array.isArray(pick.marketLinks) ? pick.marketLinks.slice(0, 8) : [];
        const summary = `
            <div class="${drawerToneClass(readiness.tone || readiness.label || "")}">
                <span>Line-shopping read</span>
                <strong>${escapeHtml(lineShopping.bestBook || pick.bestBook || "Provider links")}</strong>
                <small>${escapeHtml(lineShopping.summary || "Verify the exact line, odds, limits, and eligibility inside the provider before acting.")}</small>
            </div>
        `;
        const cards = lineCards.map((card) => `
            <div class="${drawerToneClass(card.status || card.tone || card.value || "")}">
                <span>${escapeHtml(card.label || "Line check")}</span>
                <strong>${escapeHtml(card.value || "")}</strong>
                <small>${escapeHtml(card.detail || "")}</small>
            </div>
        `).join("");
        const providerCards = links.length
            ? links.map((link) => {
                const hasPrice = link?.available && link?.price && link.price !== "--";
                const detail = hasPrice ? `${link.line || "Line"} ${link.price}` : link?.market || link?.kind || "Open provider";
                return `
                    <a href="${escapeHtml(safeHref(link?.url || "#"))}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" class="${link?.available ? "is-live" : ""}">
                        <span>${escapeHtml(link?.title || "Provider")} / ${escapeHtml(link?.kind || "Sportsbook")}</span>
                        <strong>${escapeHtml(detail)}</strong>
                        <small>${escapeHtml(link?.note || "Verify eligibility and final price before taking action.")}</small>
                    </a>
                `;
            }).join("")
            : `<div class="needs-setup"><span>Provider links</span><strong>No links attached yet.</strong><small>Connect an odds provider or use the sportsbook app list in the market board.</small></div>`;

        pickDrawerProviders.innerHTML = summary + cards + providerCards;
    };

    const renderFactorMatrix = (container, items = []) => {
        if (!container) return;
        const safeItems = Array.isArray(items) ? items.filter(Boolean) : [];
        container.innerHTML = safeItems.length
            ? safeItems.map((item) => {
                if (typeof item === "string") {
                    return `<div class="betedge-factor-card is-neutral"><div><span>${escapeHtml(item)}</span><em>Signal</em></div></div>`;
                }
                const status = item.status || "Signal";
                return `
                    <div class="betedge-factor-card ${drawerToneClass(status)}">
                        <div><span>${escapeHtml(item.label || item.title || "Factor")}</span><em>${escapeHtml(status)}</em></div>
                        <strong>${escapeHtml(item.value || item.market || "")}</strong>
                        <small>${escapeHtml(item.detail || item.note || "")}</small>
                    </div>
                `;
            }).join("")
            : `<div class="betedge-factor-card"><strong>No factor checklist is available yet.</strong></div>`;
    };

    const renderModelSources = () => {
        if (!modelSourceGrid) return;
        const sources = Array.isArray(latestSportsState.modelSources) ? latestSportsState.modelSources : [];
        modelSourceGrid.innerHTML = sources.length
            ? sources.map((source) => `
                <div class="${drawerToneClass(source.status || "")}">
                    <span>${escapeHtml(source.name || "Data source")}</span>
                    <strong>${escapeHtml(source.status || "Needs setup")}</strong>
                    <code>${escapeHtml(source.env || "Configuration")}</code>
                    <small>${escapeHtml(source.detail || "Connect this source to improve calibration and research scoring.")}</small>
                </div>
            `).join("")
            : `<div><strong>No model source settings are available yet.</strong><small>Refresh the sports state to load source requirements.</small></div>`;
    };

    const predictionFromGame = (game = {}) => {
        if (game.prediction && typeof game.prediction === "object") {
            return game.prediction;
        }

        const statusKey = String(game.statusKey || "scheduled").toLowerCase();
        const matchup = game.matchup || `${game.away?.abbr || "AWY"} @ ${game.home?.abbr || "HME"}`;
        const hasSpread = game.spread?.favoriteLine && game.spread.favoriteLine !== "--";
        const hasTotal = game.total?.over && game.total.over !== "--";
        const confidenceValue = statusKey === "live" ? 71 : statusKey === "final" ? 55 : 64;
        const market = statusKey === "final" ? "Audit" : hasSpread ? "Spread" : hasTotal ? "Total" : "Monitor";
        const pick = statusKey === "final"
            ? `Postgame review: ${game.home?.winner ? (game.home?.abbr || game.home?.name || "HOME") : (game.away?.abbr || game.away?.name || "AWAY")}`
            : hasSpread
                ? game.spread.favoriteLine
                : hasTotal
                    ? game.total.over
                    : `Watch ${matchup}`;

        return {
            gameId: String(game.id || ""),
            pick,
            matchup,
            market,
            league: game.league || "Sports",
            sportGroup: game.sportGroup || "Sports",
            statusKey,
            statusLabel: game.statusLabel || (statusKey === "live" ? "Live" : statusKey === "final" ? "Final" : "Upcoming"),
            confidenceValue,
            confidence: `${confidenceValue}%`,
            fairProbability: `${confidenceValue}.0%`,
            odds: hasSpread || hasTotal ? "Feed snapshot" : "--",
            edge: statusKey === "final" ? "+0.0%" : "+6.4%",
            expectedValue: statusKey === "final" ? "$0.00" : "$38.70",
            risk: statusKey === "live" ? "Live volatility" : statusKey === "final" ? "Closed market" : "Pregame risk",
            reason: game.detail || "Lineforge is monitoring this matchup from the public scoreboard.",
        };
    };

    const openPredictionDetail = (pick = {}) => {
        if (!pickDrawer) return;
        const breakdown = pick.breakdown || fallbackBreakdown(pick);
        const readiness = readinessForPick(pick);
        if (pickDrawerEyebrow) pickDrawerEyebrow.textContent = `${pick.league || "Sports"} / ${pick.market || "Market"} / ${pick.statusLabel || "Watch"}`;
        if (pickDrawerTitle) pickDrawerTitle.textContent = pick.pick || "Prediction breakdown";
        if (pickDrawerReason) pickDrawerReason.textContent = pick.reason || breakdown.summary || "Lineforge is monitoring the board for actionable context.";
        renderDrawerScore(pick, breakdown);
        renderTeamComparison(breakdown.comparison || pick.teamComparison || {});
        renderDrawerCards(pickDrawerMath, breakdown.steps || breakdown.math || [], "No rating steps are available for this pick yet.");
        renderDrawerCards(pickDrawerReadiness, readiness.checks || [], "No readiness checklist is available for this pick yet.");
        renderFactorMatrix(pickDrawerFactors, breakdown.factors || pick.why || []);
        renderProviderLinks(pick);
        renderDrawerCards(pickDrawerNoBet, readiness.noBetReasons || [], "No major no-bet blocker is flagged yet.");
        renderDrawerCards(pickDrawerManual, readiness.manualVerification || [], "No manual verification checklist is attached yet.");
        renderDrawerCards(pickDrawerInjuries, breakdown.missingInputs || breakdown.injuries || [], "No missing inputs are attached yet.");
        pickDrawer.hidden = false;
        pickDrawer.setAttribute("aria-hidden", "false");
        document.body.classList.add("sports-drawer-open");
    };

    const openPickDetail = (index = 0) => {
        openPredictionDetail(predictions()[index] || latestSportsState.topPick || predictions()[0] || {});
    };

    const openGameDetail = (gameId = "") => {
        const game = games().find((entry) => String(entry.id || "") === String(gameId || ""));
        if (!game) {
            openPickDetail(0);
            return;
        }

        const predictionIndex = predictionIndexForGame(game.id || "");
        if (predictionIndex >= 0) {
            openPickDetail(predictionIndex);
            return;
        }

        openPredictionDetail(predictionFromGame(game));
    };

    const closePickDetail = () => {
        if (!pickDrawer) return;
        pickDrawer.hidden = true;
        pickDrawer.setAttribute("aria-hidden", "true");
        document.body.classList.remove("sports-drawer-open");
    };

    const safelyOpenPickDetail = (index = 0) => {
        root.dataset.sportsLastPickClick = String(index);
        try {
            openPickDetail(index);
            root.dataset.sportsLastPickStatus = pickDrawer && !pickDrawer.hidden ? "opened" : "blocked";
            root.dataset.sportsLastPickError = "";
        } catch (error) {
            root.dataset.sportsLastPickStatus = "error";
            root.dataset.sportsLastPickError = error instanceof Error ? error.message : String(error);
        }
    };

    const bindPickButtons = () => {
        root.querySelectorAll("[data-sports-open-pick]").forEach((button) => {
            if (button.dataset.sportsPickBound === "true") {
                return;
            }
            button.dataset.sportsPickBound = "true";
            button.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                safelyOpenPickDetail(Number(button.dataset.sportsOpenPick || 0));
            });
        });
    };

    const renderAll = () => {
        if (!selectedGameId || !games().some((game) => String(game.id || "") === String(selectedGameId))) {
            selectedGameId = String(games()[0]?.id || "");
        }
        renderGames();
        renderPredictions();
        renderDecisionCenter();
        renderProviderSetup();
        renderExecutionCenter();
        bindPickButtons();
        renderMarketBoard();
        renderTape(latestSportsState.tape || []);
        renderAlerts(latestSportsState.alerts || []);
        renderAnalytics();
        renderInsight();
        syncWorkspace();
    };

    root.addEventListener("click", (event) => {
        const workspaceModeControl = event.target.closest("[data-workspace-mode]");
        if (workspaceModeControl) {
            event.preventDefault();
            setWorkspaceMode(workspaceModeControl.dataset.workspaceMode || "command");
            return;
        }

        const workspaceDensityControl = event.target.closest("[data-workspace-density]");
        if (workspaceDensityControl) {
            event.preventDefault();
            setWorkspaceDensity(workspaceDensityControl.dataset.workspaceDensity || "balanced");
            return;
        }

        const workspaceCollapse = event.target.closest("[data-workspace-collapse]");
        if (workspaceCollapse) {
            event.preventDefault();
            const id = workspaceCollapse.dataset.workspaceCollapse || "";
            setWorkspacePanelCollapsed(id, !workspaceState.collapsed[id]);
            return;
        }

        const workspacePin = event.target.closest("[data-workspace-pin]");
        if (workspacePin) {
            event.preventDefault();
            toggleWorkspacePanelPinned(workspacePin.dataset.workspacePin || "");
            return;
        }

        const workspaceRestore = event.target.closest("[data-workspace-restore]");
        if (workspaceRestore) {
            event.preventDefault();
            setWorkspacePanelCollapsed(workspaceRestore.dataset.workspaceRestore || "", false);
            return;
        }

        if (event.target.closest("[data-workspace-collapse-secondary]")) {
            event.preventDefault();
            collapseSecondaryWorkspacePanels();
            return;
        }

        if (event.target.closest("[data-workspace-expand-all]")) {
            event.preventDefault();
            expandAllWorkspacePanels();
            return;
        }

        const viewControl = event.target.closest("[data-sports-view]");
        if (viewControl) {
            event.preventDefault();
            showSportsView(viewControl.dataset.sportsView || "dashboard");
            return;
        }

        const arbOpen = event.target.closest("[data-arb-open]");
        if (arbOpen) {
            event.preventDefault();
            openArbDetail(Number(arbOpen.dataset.arbOpen || 0));
            return;
        }

        if (event.target.closest("[data-arb-close]")) {
            event.preventDefault();
            closeArbDetail();
            return;
        }

        const filterControl = event.target.closest("[data-sports-filter]");
        if (filterControl) {
            setSportsFilter(filterControl);
            return;
        }

        const providerControl = event.target.closest("[data-sports-provider-filter]");
        if (providerControl) {
            activeProviderFilter = providerControl.dataset.sportsProviderFilter || "all";
            root.querySelectorAll("[data-sports-provider-filter]").forEach((button) => button.classList.toggle("is-active", button === providerControl));
            renderMarketBoard();
            return;
        }

        const openGameButton = event.target.closest("[data-sports-open-game]");
        if (openGameButton) {
            event.preventDefault();
            event.stopPropagation();
            openGameDetail(openGameButton.dataset.sportsOpenGame || "");
            return;
        }

        const gameCard = event.target.closest("[data-sports-select-game]");
        if (gameCard && !event.target.closest("[data-sports-open-pick], [data-sports-open-game]")) {
            selectedGameId = gameCard.dataset.sportsSelectGame || gameCard.dataset.gameId || selectedGameId;
            root.querySelectorAll("[data-sports-select-game]").forEach((card) => {
                card.classList.toggle("is-selected", String(card.dataset.sportsSelectGame || card.dataset.gameId || "") === String(selectedGameId));
            });
            return;
        }

        const removeSlip = event.target.closest("[data-sports-remove-slip]");
        if (removeSlip) {
            event.preventDefault();
            slipItems.splice(Number(removeSlip.dataset.sportsRemoveSlip || 0), 1);
            renderSlip();
            return;
        }

        if (event.target.closest("[data-sports-clear-slip]")) {
            event.preventDefault();
            slipItems = [];
            setSlipNote("Selections cleared.");
            renderSlip();
            return;
        }

        if (event.target.closest("[data-sports-clear-bets]")) {
            event.preventDefault();
            writeBets([]);
            renderMyBets();
            return;
        }

        const slipTab = event.target.closest("[data-sports-slip-tab]");
        if (slipTab) {
            event.preventDefault();
            const target = slipTab.dataset.sportsSlipTab || "slip";
            root.querySelectorAll("[data-sports-slip-tab]").forEach((button) => button.classList.toggle("is-active", button === slipTab));
            root.querySelectorAll("[data-sports-slip-pane]").forEach((pane) => {
                const visible = (pane.dataset.sportsSlipPane || "slip") === target;
                pane.hidden = !visible;
                pane.classList.toggle("is-active", visible);
            });
            return;
        }

        if (event.target.closest("#sportsPlaceBet")) {
            event.preventDefault();
            placePaperBet();
            return;
        }

        const openButton = event.target.closest("[data-sports-open-pick]");
        if (openButton) {
            event.preventDefault();
            safelyOpenPickDetail(Number(openButton.dataset.sportsOpenPick || 0));
        }
    });

    root.addEventListener("input", (event) => {
        const search = event.target.closest("[data-sports-game-search]");
        if (search) {
            activeSportsSearch = search.value || "";
            renderGames();
            renderPredictions();
            return;
        }

        const input = event.target.closest("[data-sports-slip-stake]");
        if (!input) return;
        const index = Number(input.dataset.sportsSlipStake || 0);
        if (!slipItems[index]) return;
        slipItems[index].stake = Math.max(0, Number(input.value) || 0);
        renderSlip();
    });

    root.addEventListener("change", (event) => {
        const select = event.target.closest("[data-sports-filter-select]");
        if (!select) return;
        activeSportsFilter = select.value || "all";
        root.querySelectorAll("[data-sports-filter]").forEach((button) => {
            button.classList.toggle("is-active", (button.dataset.sportsFilter || "all") === activeSportsFilter);
        });
        renderGames();
        renderPredictions();
    });

    root.querySelectorAll("[data-sports-filter]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            setSportsFilter(button);
        });
    });

    document.addEventListener("click", (event) => {
        if (event.target.closest("[data-sports-close-pick]")) {
            event.preventDefault();
            closePickDetail();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && arbDrawer && !arbDrawer.hidden) {
            closeArbDetail();
            return;
        }
        if (event.key === "Escape" && pickDrawer && !pickDrawer.hidden) {
            closePickDetail();
        }
    });

    const refreshSports = async () => {
        if (sportsRefreshInFlight) {
            return;
        }
        sportsRefreshInFlight = true;
        try {
            const response = await fetch(config.endpoint, { credentials: "same-origin" });
            const data = await response.json();
            if (!data.ok || !data.state) {
                return;
            }
            if (data.providerSettings) {
                latestProviderSettings = data.providerSettings;
                config.providerSettings = data.providerSettings;
            }
            latestSportsState = data.state;
            renderAll();
        } catch (_error) {
            if (sourceLabel) {
                sourceLabel.textContent = "Refresh paused";
            }
        } finally {
            sportsRefreshInFlight = false;
        }
    };

    providerSetupForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!config.providerEndpoint) {
            if (providerSaveResult) providerSaveResult.textContent = "Provider settings endpoint is not configured.";
            return;
        }

        const form = new FormData(providerSetupForm);
        const payload = Object.fromEntries(form.entries());
        payload.clear_odds_api_key = form.get("clear_odds_api_key") === "1";
        if (providerSaveResult) providerSaveResult.textContent = "Saving provider setup...";

        try {
            const response = await fetch(config.providerEndpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken || payload.csrf || "",
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!data.ok) {
                throw new Error(data.message || "Provider setup could not be saved.");
            }

            config.csrfToken = data.csrfToken || config.csrfToken;
            latestProviderSettings = data.settings || latestProviderSettings;
            config.providerSettings = latestProviderSettings;
            renderProviderSetup(latestProviderSettings);
            if (providerSaveResult) providerSaveResult.textContent = data.message || "Provider settings saved.";
            await refreshSports();
        } catch (error) {
            if (providerSaveResult) {
                providerSaveResult.textContent = error instanceof Error ? error.message : "Provider setup could not be saved.";
            }
        }
    });

    executionMode?.querySelectorAll("[data-execution-mode]").forEach((button) => {
        button.addEventListener("click", async () => {
            const mode = button.dataset.executionMode || "paper";
            const payload = { action: "setMode", mode };
            if (mode === "live") {
                const approved = window.confirm("Enable live mode? Live-money rules still require provider eligibility, risk checks, and explicit rule authorization.");
                if (!approved) return;
                payload.confirmLive = true;
            }
            try {
                await postExecution(payload);
            } catch (error) {
                setExecutionResult(error instanceof Error ? error.message : "Mode change failed.", "error");
            }
        });
    });

    root.querySelectorAll("[data-execution-action]").forEach((button) => {
        button.addEventListener("click", async () => {
            const action = button.dataset.executionAction || "";
            const payload = { action };
            if (action === "emergencyStop") {
                const approved = window.confirm("Activate emergency stop? This pauses all pending rules and disables live execution.");
                if (!approved) return;
                payload.cancelOpenOrders = true;
            }
            try {
                const data = await postExecution(payload);
                if (Array.isArray(data.evaluation)) {
                    setExecutionResult(`${data.message || "Evaluation complete"} ${data.evaluation.length} rule results logged.`, "success");
                }
            } catch (error) {
                setExecutionResult(error instanceof Error ? error.message : "Execution action failed.", "error");
            }
        });
    });

    kalshiForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = new FormData(kalshiForm);
        const payload = Object.fromEntries(form.entries());
        payload.action = "connectKalshi";
        payload.clearCredentials = form.get("clearCredentials") === "1";
        try {
            await postExecution(payload);
        } catch (error) {
            setExecutionResult(error instanceof Error ? error.message : "Kalshi profile could not be saved.", "error");
        }
    });

    riskForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = new FormData(riskForm);
        const riskLimits = Object.fromEntries(form.entries());
        ["selfExcluded", "emergencyDisabled", "requireManualConfirmation", "allowLiveAuto"].forEach((name) => {
            riskLimits[name] = form.get(name) === "1";
        });
        try {
            await postExecution({ action: "saveRiskLimits", riskLimits });
        } catch (error) {
            setExecutionResult(error instanceof Error ? error.message : "Risk limits could not be saved.", "error");
        }
    });

    ruleForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = new FormData(ruleForm);
        const rule = Object.fromEntries(form.entries());
        rule.allowDuplicates = form.get("allowDuplicates") === "1";
        try {
            await postExecution({ action: "createRule", rule });
            ruleForm.reset();
            if (ruleForm.elements.marketTicker) {
                ruleForm.elements.marketTicker.value = "KXEXAMPLE-26MAY05-LF";
            }
            setExecutionResult("Paused rule created. Run a dry-run before enabling it.", "success");
        } catch (error) {
            setExecutionResult(error instanceof Error ? error.message : "Rule could not be created.", "error");
        }
    });

    executionRules?.addEventListener("click", async (event) => {
        const toggle = event.target.closest("[data-execution-toggle-rule]");
        if (toggle) {
            event.preventDefault();
            try {
                await postExecution({
                    action: "toggleRule",
                    ruleId: toggle.dataset.executionToggleRule || "",
                    enabled: toggle.dataset.enabled === "1",
                });
            } catch (error) {
                setExecutionResult(error instanceof Error ? error.message : "Rule could not be toggled.", "error");
            }
            return;
        }

        const deleteButton = event.target.closest("[data-execution-delete-rule]");
        if (deleteButton) {
            event.preventDefault();
            const approved = window.confirm("Delete this execution rule?");
            if (!approved) return;
            try {
                await postExecution({
                    action: "deleteRule",
                    ruleId: deleteButton.dataset.executionDeleteRule || "",
                });
            } catch (error) {
                setExecutionResult(error instanceof Error ? error.message : "Rule could not be deleted.", "error");
            }
        }
    });

    hydrateSlip();
    renderAll();
    bindPickButtons();
    renderSlip();
    renderMyBets();
    showSportsView(activeSportsView);
    loadExecutionCenter(false);

    const interval = Math.max(5, Number(config.refreshSeconds || 60)) * 1000;
    window.setTimeout(refreshSports, 1000);
    window.setInterval(refreshSports, interval);
    window.setInterval(() => loadExecutionCenter(true), Math.max(15000, interval));
})();

(() => {
    const config = window.AEGIS_MARKETS;
    const tickerTape = document.getElementById("marketsTickerTape");
    const statusbar = document.getElementById("marketsStatusbar");
    const metrics = document.getElementById("marketsMetrics");
    const overviewGrid = document.getElementById("marketsOverviewGrid");
    const accountValue = document.getElementById("marketsAccountValue");
    const accountChange = document.getElementById("marketsAccountChange");
    const buyingPower = document.getElementById("marketsBuyingPower");
    const cashBalance = document.getElementById("marketsCashBalance");
    const selectedSymbol = document.getElementById("marketsSelectedSymbol");
    const selectedVenue = document.getElementById("marketsSelectedVenue");
    const selectedPrice = document.getElementById("marketsSelectedPrice");
    const selectedChange = document.getElementById("marketsSelectedChange");
    const candles = document.getElementById("marketsCandles");
    const chartStats = document.getElementById("marketsChartStats");
    const bookTitle = document.getElementById("marketsBookTitle");
    const bookAsks = document.getElementById("marketsBookAsks");
    const bookMidPrice = document.getElementById("marketsBookMidPrice");
    const bookMidChange = document.getElementById("marketsBookMidChange");
    const bookBids = document.getElementById("marketsBookBids");
    const bookBuyShare = document.getElementById("marketsBookBuyShare");
    const bookSellShare = document.getElementById("marketsBookSellShare");
    const allocationTotal = document.getElementById("marketsAllocationTotal");
    const allocationList = document.getElementById("marketsAllocationList");
    const topMovers = document.getElementById("marketsTopMovers");
    const insightList = document.getElementById("marketsInsightList");
    const news = document.getElementById("marketsNews");
    const watchlist = document.getElementById("marketsWatchlist");
    const positions = document.getElementById("marketsPositionsBoard");
    const orders = document.getElementById("marketsOrdersBoard");
    const regimeBoard = document.getElementById("marketsRegimeBoard");
    const signalBlendBoard = document.getElementById("marketsSignalBlendBoard");
    const heatmapBoard = document.getElementById("marketsOpportunityHeatmap");
    const multiAssetBoard = document.getElementById("marketsMultiAssetBoard");
    const executionBoard = document.getElementById("marketsExecutionBoard");
    const exitBoard = document.getElementById("marketsExitBoard");
    const portfolioBoard = document.getElementById("marketsPortfolioBoard");
    const agentsBoard = document.getElementById("marketsAgentsBoard");
    const aiBoard = document.getElementById("marketsAiBoard");
    const riskBoard = document.getElementById("marketsRiskBoard");
    const backtestBoard = document.getElementById("marketsBacktestBoard");
    const dataBoard = document.getElementById("marketsDataBoard");
    const performanceBoard = document.getElementById("marketsPerformanceBoard");
    const signalFeed = document.getElementById("marketsSignalFeed");
    const productBoard = document.getElementById("marketsProductBoard");
    const speculativeRadar = document.getElementById("marketsSpeculativeRadar");
    const executionBadge = document.getElementById("marketsExecutionBadge");
    const executionRouteLabel = document.getElementById("marketsExecutionRouteLabel");
    const executionSummary = document.getElementById("marketsExecutionSummary");
    const executionStatusLabel = document.getElementById("marketsExecutionStatusLabel");
    const executionDetail = document.getElementById("marketsExecutionDetail");
    const ticketSymbol = document.getElementById("marketsTicketSymbol");
    const ticketInputSymbol = document.getElementById("marketsTicketInputSymbol");
    const ticketRouteLabel = document.getElementById("marketsTicketRouteLabel");
    const ticketPay = document.getElementById("marketsTicketPay");
    const ticketAvailable = document.getElementById("marketsTicketAvailable");
    const ticketReceive = document.getElementById("marketsTicketReceive");
    const ticketQuantity = document.getElementById("marketsTicketQuantity");
    const ticketLimit = document.getElementById("marketsTicketLimit");
    const ticketStop = document.getElementById("marketsTicketStop");
    const ticketButton = document.getElementById("marketsTicketButton");
    const ticketFee = document.getElementById("marketsTicketFee");
    const ticketSlippage = document.getElementById("marketsTicketSlippage");
    const ticketNote = document.getElementById("marketsTicketNote");
    const ticketStatus = document.getElementById("marketsTicketStatus");
    const ticketSide = document.getElementById("marketsTicketSide");
    const ticketRoute = document.getElementById("marketsTicketRoute");
    const ticketOrderType = document.getElementById("marketsTicketOrderType");

    if (!config || !tickerTape) {
        return;
    }

    let selectedAssetPrice = 0;
    let selectedAssetClass = "stock";
    let sendingOrder = false;
    const ticketState = {
        side: "buy",
        route: "paper",
        orderType: "market",
    };

    const changeClass = (value) => String(value || "").trim().startsWith("-") ? "down" : "up";
    const parseNumber = (value) => {
        const normalized = String(value || "").replace(/[^0-9.\-]/g, "");
        const parsed = Number.parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const guessAssetClass = (symbol) => String(symbol || "").includes("/") ? "crypto" : "stock";

    const setTicketStatus = (message, tone = "neutral") => {
        if (!ticketStatus) {
            return;
        }
        ticketStatus.textContent = message;
        ticketStatus.dataset.tone = tone;
    };

    const setActiveButtons = (container, key, value) => {
        if (!container) {
            return;
        }
        container.querySelectorAll("button").forEach((button) => {
            button.classList.toggle("is-active", button.dataset[key] === value);
        });
    };

    const syncTicketEstimate = () => {
        if (!ticketReceive) {
            return;
        }

        const notional = parseNumber(ticketPay?.value);
        const qty = parseNumber(ticketQuantity?.value);
        const price = parseNumber(ticketLimit?.value) || selectedAssetPrice || 0;

        if (qty > 0) {
            ticketReceive.value = String(qty);
            return;
        }

        if (notional > 0 && price > 0) {
            ticketReceive.value = (notional / price).toFixed(price >= 1000 ? 5 : 3);
            return;
        }

        ticketReceive.value = "0.000";
    };

    const renderTape = (target, items, footer = false) => {
        if (!target) return;
        if (footer) {
            target.innerHTML = items
                .map((item) => `<span><strong>${escapeHtml(item.label || "")}</strong> ${escapeHtml(item.value || "")} ${escapeHtml(item.state || "")}</span>`)
                .join("") + '<span class="system">System Status <strong>All Systems Operational</strong></span>';
            return;
        }

        target.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <strong>${escapeHtml(item.label || "")}</strong>
                        <span>${escapeHtml(item.value || "")}</span>
                        <em class="${changeClass(item.state)}">${escapeHtml(item.state || "")}</em>
                    </div>
                `
            )
            .join("");
    };

    const renderMetrics = (items = []) => {
        if (!metrics) return;
        metrics.innerHTML = items
            .map(
                (item) => `
                    <article>
                        <span>${escapeHtml(item.label || "")}</span>
                        <strong>${escapeHtml(item.value || "")}</strong>
                    </article>
                `
            )
            .join("");
    };

    const renderOverview = (items = []) => {
        if (!overviewGrid) return;
        overviewGrid.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-overview-card" data-ticket-symbol="${escapeHtml(item.symbol || "")}" data-ticket-price="${escapeHtml(item.price || "")}" data-ticket-asset-class="${escapeHtml(item.assetClass || guessAssetClass(item.symbol || ""))}">
                        <div class="markets-reference-asset-head">
                            <i class="markets-reference-asset-icon ${escapeHtml(item.iconClass || "blue")}">${escapeHtml(item.icon || "?")}</i>
                            <div>
                                <span>${escapeHtml(item.label || item.name || "")}</span>
                                <small>${escapeHtml(item.symbol || "")}</small>
                            </div>
                        </div>
                        <strong>${escapeHtml(item.price || "")}</strong>
                        <div>
                            <em class="${changeClass(item.change)}">${escapeHtml(item.change || "")}</em>
                            <b>${escapeHtml(item.deltaMoney || "")}</b>
                        </div>
                        <div class="markets-reference-sparkline">
                            ${(item.spark || []).map((point) => `<i style="--h: ${escapeHtml(point)}%"></i>`).join("")}
                        </div>
                    </article>
                `
            )
            .join("");
    };

    const renderSelectedAsset = (asset = {}) => {
        if (selectedSymbol) {
            selectedSymbol.textContent = asset.symbol || "";
        }
        if (selectedVenue) {
            selectedVenue.textContent = `${asset.name || "Asset"} · ${asset.venue || "Market"}`;
        }
        if (selectedPrice) {
            selectedPrice.textContent = asset.price || "";
        }
        if (selectedChange) {
            selectedChange.textContent = asset.change || "";
        }
        if (selectedVenue) {
            selectedVenue.textContent = `${asset.name || "Asset"} - ${asset.venue || "Market"}`;
        }
        selectedAssetPrice = parseNumber(asset.price || 0);
        selectedAssetClass = asset.assetClass || guessAssetClass(asset.symbol || "");
        if (candles) {
            candles.innerHTML = (asset.history || [])
                .map((point, index) => `<i class="${index % 4 === 0 ? "down" : "up"}" style="--h: ${escapeHtml(point)}%"></i>`)
                .join("");
        }
        if (chartStats) {
            chartStats.innerHTML = (asset.stats || [])
                .map((stat) => `<div><span>${escapeHtml(stat.label || "")}</span><strong>${escapeHtml(stat.value || "")}</strong></div>`)
                .join("");
        }
        if (bookTitle) {
            bookTitle.textContent = `${asset.symbol || "Asset"} Orders`;
        }
        if (bookAsks) {
            bookAsks.innerHTML = `<span>Price</span><span>Size</span><span>Total</span>${(asset.orderBook || [])
                .map(
                    (level) => `
                        <strong class="down">${escapeHtml(level.ask || "")}</strong>
                        <em>${escapeHtml(level.askSize || "")}</em>
                        <b>${escapeHtml(level.askTotal || "")}</b>
                    `
                )
                .join("")}`;
        }
        if (bookMidPrice) {
            bookMidPrice.textContent = asset.price || "";
        }
        if (bookMidChange) {
            bookMidChange.textContent = asset.change || "";
        }
        if (bookBids) {
            bookBids.innerHTML = (asset.orderBook || [])
                .map(
                    (level) => `
                        <strong class="up">${escapeHtml(level.bid || "")}</strong>
                        <em>${escapeHtml(level.bidSize || "")}</em>
                        <b>${escapeHtml(level.bidTotal || "")}</b>
                    `
                )
                .join("");
        }
        if (bookBuyShare) {
            bookBuyShare.textContent = `B ${asset.buyShare || "50%"}`;
        }
        if (bookSellShare) {
            bookSellShare.textContent = `S ${asset.sellShare || "50%"}`;
        }
        if (ticketInputSymbol && document.activeElement !== ticketInputSymbol) {
            ticketInputSymbol.value = asset.symbol || ticketInputSymbol.value;
        }
        if (ticketLimit && ticketState.orderType !== "market" && document.activeElement !== ticketLimit) {
            ticketLimit.value = asset.price || ticketLimit.value;
        }
        syncTicketEstimate();
    };

    const renderAllocation = (items = [], total = "") => {
        if (allocationTotal) {
            allocationTotal.textContent = total;
        }
        if (!allocationList) return;
        allocationList.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <span><i style="background: ${escapeHtml(item.color || "#3b82f6")}"></i>${escapeHtml(item.label || "")}</span>
                        <strong>${escapeHtml(item.value || "")}</strong>
                    </div>
                `
            )
            .join("");
    };

    const renderAssetList = (target, items = []) => {
        if (!target) return;
        target.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <div class="markets-reference-asset-line">
                            <i class="markets-reference-asset-icon ${escapeHtml(item.iconClass || "blue")}">${escapeHtml(item.icon || "?")}</i>
                            <span class="markets-reference-asset-copy">
                                <strong>${escapeHtml(item.symbol || "")}</strong>
                                <span>${escapeHtml(item.name || item.detail || "")}</span>
                            </span>
                        </div>
                        <div>
                            <em class="${changeClass(item.change)}">${escapeHtml(item.change || item.score || "")}</em>
                            <span>${escapeHtml(item.price || item.target || "")}</span>
                        </div>
                    </div>
                `
            )
            .join("");
    };

    const renderInsights = (items = []) => {
        if (!insightList) return;
        insightList.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <div class="markets-reference-asset-line">
                            <i class="markets-reference-asset-icon ${escapeHtml(item.iconClass || "blue")}">${escapeHtml(item.icon || "?")}</i>
                            <span class="markets-reference-asset-copy">
                                <strong>${escapeHtml(item.symbol || "")}</strong>
                                <span>${escapeHtml(item.detail || "")}</span>
                                <small>${escapeHtml(item.target || "")}</small>
                            </span>
                        </div>
                        <em>${escapeHtml(item.score || "")}</em>
                    </div>
                `
            )
            .join("");
    };

    const renderNews = (items = []) => {
        if (!news) return;
        news.innerHTML = items
            .map(
                (item) => `
                    <div>
                        <strong>${escapeHtml(item.headline || "")}</strong>
                        <span>${escapeHtml(item.time || "")} · ${escapeHtml(item.source || "")}</span>
                    </div>
                `
            )
            .join("");
        news.innerHTML = news.innerHTML.replaceAll("\u00b7", "-").replaceAll("\u00c2\u00b7", "-");
    };

    const renderWatchlist = (items = []) => {
        if (!watchlist) return;
        watchlist.innerHTML = items
            .map(
                (item) => `
                    <div class="markets-reference-watchlist-item" data-ticket-symbol="${escapeHtml(item.symbol || "")}" data-ticket-price="${escapeHtml(item.price || "")}" data-ticket-asset-class="${escapeHtml(item.assetClass || guessAssetClass(item.symbol || ""))}">
                        <div class="markets-reference-asset-line">
                            <i class="markets-reference-asset-icon ${escapeHtml(item.iconClass || "blue")}">${escapeHtml(item.icon || "?")}</i>
                            <span class="markets-reference-asset-copy">
                                <strong>${escapeHtml(item.symbol || "")}</strong>
                                <span>${escapeHtml(item.name || "")}</span>
                            </span>
                        </div>
                        <div>
                            <b>${escapeHtml(item.price || "")}</b>
                            <em class="${changeClass(item.change)}">${escapeHtml(item.change || "")}</em>
                        </div>
                    </div>
                `
            )
            .join("");
    };

    const renderSpeculativeRadar = (items = []) => {
        if (!speculativeRadar) return;
        speculativeRadar.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-module-card" data-ticket-symbol="${escapeHtml(item.symbol || "")}" data-ticket-price="${escapeHtml(item.price || "")}" data-ticket-asset-class="${escapeHtml(item.assetClass || guessAssetClass(item.symbol || ""))}">
                        <div class="markets-reference-module-head">
                            <strong>${escapeHtml(item.symbol || "")}</strong>
                            <em>${escapeHtml(item.state || "")}</em>
                        </div>
                        <b>${escapeHtml(item.price || "")} <span class="${changeClass(item.change)}">${escapeHtml(item.change || "")}</span></b>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </article>
                `
            )
            .join("");
    };

    const renderModuleBoard = (target, items = []) => {
        if (!target) return;
        target.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-module-card">
                        <div class="markets-reference-module-head">
                            <strong>${escapeHtml(item.name || "")}</strong>
                            <em>${escapeHtml(item.tag || "")}</em>
                        </div>
                        <b>${escapeHtml(item.value || "")}</b>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </article>
                `
            )
            .join("");
    };

    const renderHeatmap = (items = []) => {
        if (!heatmapBoard) return;
        heatmapBoard.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-heat-card">
                        <div class="markets-reference-module-head">
                            <strong>${escapeHtml(item.name || "")}</strong>
                            <em>${escapeHtml(item.state || "")}</em>
                        </div>
                        <div class="markets-reference-heat-meter">
                            <i style="width: ${escapeHtml(item.score || 0)}%"></i>
                        </div>
                        <b>${escapeHtml(item.score || 0)}/100</b>
                        <span>${escapeHtml(item.detail || "")}</span>
                    </article>
                `
            )
            .join("");
    };

    const renderFeed = (target, items = []) => {
        if (!target) return;
        target.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-feed-item">
                        <div>
                            <strong>${escapeHtml(item.title || "")}</strong>
                            <span>${escapeHtml(item.detail || "")}</span>
                        </div>
                        <small>${escapeHtml(item.time || "")}</small>
                    </article>
                `
            )
            .join("");
    };

    const renderPositions = (items = []) => {
        if (!positions) return;
        positions.innerHTML = items
            .map(
                (item) => `
                    <article class="markets-reference-position-card">
                        <div class="markets-reference-module-head">
                            <strong>${escapeHtml(item.symbol || "")}</strong>
                            <em>${escapeHtml(item.side || "")}</em>
                        </div>
                        <b>${escapeHtml(item.pnl || "")}</b>
                        <span>Qty ${escapeHtml(item.qty || "")} at ${escapeHtml(item.avg || "")} | Last ${escapeHtml(item.market || "")}</span>
                    </article>
                `
            )
            .join("");
    };

    const renderTicket = (ticket = {}) => {
        if (ticketSymbol) {
            ticketSymbol.textContent = `${ticket.symbol || "Asset"} Order Ticket`;
        }
        if (ticketInputSymbol && document.activeElement !== ticketInputSymbol) {
            ticketInputSymbol.value = ticket.symbol || ticketInputSymbol.value;
        }
        if (ticketRouteLabel) {
            ticketRouteLabel.textContent = ticketState.route === "broker" ? "Broker" : (ticket.routeLabel || "Paper");
        }
        if (ticketPay) {
            ticketPay.value = ticket.payValue || "";
        }
        if (ticketAvailable) {
            ticketAvailable.value = ticket.availableValue || "";
        }
        if (ticketReceive) {
            ticketReceive.value = ticket.receiveValue || "";
        }
        if (ticketQuantity) {
            ticketQuantity.value = ticket.quantityValue || "";
        }
        if (ticketLimit && document.activeElement !== ticketLimit) {
            ticketLimit.value = ticket.limitValue || "";
        }
        if (ticketStop && document.activeElement !== ticketStop) {
            ticketStop.value = ticket.stopValue || "";
        }
        if (ticketButton) {
            ticketButton.textContent = ticket.buttonLabel || "Place Order";
        }
        if (ticketFee) {
            ticketFee.textContent = ticket.fee || "";
        }
        if (ticketSlippage) {
            ticketSlippage.textContent = ticket.slippage || "";
        }
        if (ticketNote) {
            ticketNote.textContent = ticket.note || "";
        }
        syncTicketEstimate();
    };

    const renderAccount = (account = {}) => {
        if (accountValue) {
            accountValue.textContent = account.portfolioValue || "";
        }
        if (accountChange) {
            accountChange.textContent = `${account.dayChangeSigned || ""} (${account.dayChangePercent || ""})`;
        }
        if (buyingPower) {
            buyingPower.textContent = account.buyingPower || "";
        }
        if (cashBalance) {
            cashBalance.textContent = account.cashBalance || "";
        }
    };

    const renderExecutionStatus = (execution = {}) => {
        if (executionBadge) {
            executionBadge.textContent = execution.statusLabel || "Paper only";
        }
        if (executionRouteLabel) {
            executionRouteLabel.textContent = execution.routeLabel || "Paper trading";
        }
        if (executionSummary) {
            executionSummary.textContent = execution.summary || "";
        }
        if (executionStatusLabel) {
            executionStatusLabel.textContent = execution.statusLabel || "";
        }
        if (executionDetail) {
            executionDetail.textContent = execution.detail || "";
        }

        if (ticketRoute) {
            ticketRoute.querySelectorAll("button").forEach((button) => {
                if (button.dataset.route === "broker") {
                    button.disabled = !execution.liveAllowed;
                }
            });
            if (!execution.liveAllowed && ticketState.route === "broker") {
                ticketState.route = "paper";
                setActiveButtons(ticketRoute, "route", ticketState.route);
            }
        }

        if (ticketRouteLabel) {
            ticketRouteLabel.textContent = ticketState.route === "broker" ? (execution.routeLabel || "Broker") : "Paper";
        }
    };

    const applyState = (state = {}) => {
        renderTape(tickerTape, state.tape || []);
        renderTape(statusbar, state.tape || [], true);
        renderMetrics(state.metrics || []);
        renderOverview(state.overviewCards || []);
        renderSelectedAsset(state.selectedAsset || {});
        renderAccount(state.account || {});
        renderAllocation(state.allocation || [], state.account?.portfolioValue || "");
        renderAssetList(topMovers, state.topMovers || []);
        renderInsights(state.insights || []);
        renderNews(state.news || []);
        renderWatchlist(state.watchlist || []);
        renderSpeculativeRadar(state.speculativeRadar || []);
        renderPositions(state.positions || []);
        renderFeed(orders, state.orders || []);
        renderModuleBoard(regimeBoard, state.regimeDesk || []);
        renderModuleBoard(signalBlendBoard, state.signalBlend || []);
        renderHeatmap(state.heatmap || []);
        renderModuleBoard(multiAssetBoard, state.multiAsset || []);
        renderModuleBoard(executionBoard, state.executionDesk || []);
        renderModuleBoard(exitBoard, state.exitEngine || []);
        renderModuleBoard(portfolioBoard, state.portfolioIntel || []);
        renderModuleBoard(agentsBoard, state.agents || []);
        renderModuleBoard(aiBoard, state.aiWorkflow || []);
        renderModuleBoard(riskBoard, state.riskDesk || []);
        renderModuleBoard(backtestBoard, state.backtests || []);
        renderModuleBoard(dataBoard, state.dataInfra || []);
        renderModuleBoard(performanceBoard, state.performance || []);
        renderFeed(signalFeed, state.signalFeed || []);
        renderModuleBoard(productBoard, state.productLanes || []);
        renderExecutionStatus(state.executionStatus || {});
        renderTicket(state.paperTicket || {});
    };

    const useTicketAsset = (symbol, price, assetClass) => {
        if (ticketInputSymbol && symbol) {
            ticketInputSymbol.value = symbol;
        }
        if (ticketLimit && price && ticketState.orderType !== "market") {
            ticketLimit.value = price;
        }
        selectedAssetPrice = parseNumber(price || selectedAssetPrice);
        selectedAssetClass = assetClass || guessAssetClass(symbol || "");
        syncTicketEstimate();
        setTicketStatus(`Ticket updated for ${symbol || "the selected asset"}.`, "ok");
    };

    const submitOrder = async () => {
        if (sendingOrder || !ticketButton) {
            return;
        }

        const symbol = ticketInputSymbol?.value?.trim() || "";
        const payload = {
            symbol,
            asset_class: selectedAssetClass || guessAssetClass(symbol),
            side: ticketState.side,
            order_type: ticketState.orderType,
            requested_route: ticketState.route,
            time_in_force: selectedAssetClass === "crypto" ? "gtc" : "day",
            notional_usd: parseNumber(ticketPay?.value),
            qty: parseNumber(ticketQuantity?.value),
            limit_price: parseNumber(ticketLimit?.value),
            stop_price: parseNumber(ticketStop?.value),
            reference_price: selectedAssetPrice || parseNumber(ticketLimit?.value),
            aegis_csrf: config.csrfToken || "",
        };

        sendingOrder = true;
        ticketButton.disabled = true;
        setTicketStatus("Submitting order...", "pending");

        try {
            const response = await fetch(config.executionEndpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-AEGIS-CSRF": config.csrfToken || "",
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            config.csrfToken = data.csrfToken || config.csrfToken;

            if (!data.ok) {
                setTicketStatus(data.message || "Order submission failed.", "fail");
                return;
            }

            if (data.state) {
                applyState(data.state);
            } else {
                await refreshMarkets();
            }
            setTicketStatus(data.message || "Order submitted.", "ok");
        } catch (_error) {
            setTicketStatus("The order request could not reach the server.", "fail");
        } finally {
            sendingOrder = false;
            ticketButton.disabled = false;
        }
    };

    const refreshMarkets = async () => {
        try {
            const response = await fetch(config.endpoint, { credentials: "same-origin" });
            const data = await response.json();
            if (!data.ok || !data.state) {
                return;
            }

            applyState(data.state);
        } catch (_error) {
            setTicketStatus("Live refresh paused. Existing board state is still visible.", "warn");
        }
    };

    const handleAssetClick = (event) => {
        const target = event.target.closest("[data-ticket-symbol]");
        if (!target) {
            return;
        }

        useTicketAsset(
            target.dataset.ticketSymbol || "",
            target.dataset.ticketPrice || "",
            target.dataset.ticketAssetClass || ""
        );
    };

    ticketSide?.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-side]");
        if (!button) {
            return;
        }
        ticketState.side = button.dataset.side || "buy";
        setActiveButtons(ticketSide, "side", ticketState.side);
    });

    ticketRoute?.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-route]");
        if (!button || button.disabled) {
            return;
        }
        ticketState.route = button.dataset.route || "paper";
        setActiveButtons(ticketRoute, "route", ticketState.route);
        if (ticketRouteLabel) {
            ticketRouteLabel.textContent = ticketState.route === "broker" ? "Broker" : "Paper";
        }
        setTicketStatus(
            ticketState.route === "broker"
                ? "Broker route selected. The server will enforce plan and provider checks."
                : "Paper route selected. No live capital will be routed.",
            "neutral"
        );
    });

    ticketOrderType?.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-order-type]");
        if (!button) {
            return;
        }
        ticketState.orderType = button.dataset.orderType || "market";
        setActiveButtons(ticketOrderType, "orderType", ticketState.orderType);
        if (ticketLimit) {
            ticketLimit.disabled = ticketState.orderType === "market";
        }
        if (ticketStop) {
            ticketStop.disabled = ticketState.orderType !== "stop" && ticketState.orderType !== "stop_limit";
        }
        syncTicketEstimate();
    });

    [overviewGrid, watchlist, speculativeRadar].forEach((target) => target?.addEventListener("click", handleAssetClick));
    [ticketPay, ticketQuantity, ticketLimit, ticketInputSymbol].forEach((input) => input?.addEventListener("input", syncTicketEstimate));
    ticketButton?.addEventListener("click", submitOrder);

    if (ticketLimit) {
        ticketLimit.disabled = ticketState.orderType === "market";
    }
    if (ticketStop) {
        ticketStop.disabled = ticketState.orderType !== "stop" && ticketState.orderType !== "stop_limit";
    }
    syncTicketEstimate();

    const interval = Math.max(5, Number(config.refreshSeconds || 60)) * 1000;
    window.setTimeout(refreshMarkets, 1000);
    window.setInterval(refreshMarkets, interval);
})();
