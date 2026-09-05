(() => {
    "use strict";

    // Dispositivo público persistente (LocalStorage + Cookie sync)
    const initDevicePersistence = () => {
        let deviceId = null;
        try {
            deviceId = localStorage.getItem("innovamente_device_id");
            if (!deviceId) {
                deviceId = "d_" + ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
                    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
                );
                localStorage.setItem("innovamente_device_id", deviceId);
            }
        } catch {
            // fallback si localStorage está restringido
        }

        if (deviceId) {
            document.querySelectorAll("[data-device-id-input]").forEach(input => {
                input.value = deviceId;
            });
            try {
                document.cookie = `innovamente_client_device=${deviceId}; max-age=31536000; path=/; SameSite=Lax`;
            } catch {}
        }

        const nameInput = document.getElementById("display-name");
        if (nameInput && !nameInput.value) {
            try {
                const savedName = localStorage.getItem("innovamente_display_name");
                if (savedName) nameInput.value = savedName;
            } catch {}
        }

        const accessCard = document.querySelector(".access-card");
        if (accessCard) {
            accessCard.addEventListener("submit", () => {
                const nameVal = document.getElementById("display-name")?.value;
                if (nameVal) {
                    try { localStorage.setItem("innovamente_display_name", nameVal.trim()); } catch {}
                }
            });
        }
    };
    initDevicePersistence();

    const segmented = document.querySelector("[data-segmented-code]");
    if (segmented) {
        const inputs = [...segmented.querySelectorAll("input[data-code-character]")];
        const hidden = document.querySelector(segmented.dataset.target);
        const submit = segmented.closest("form")?.querySelector("button[type=submit]");
        const sync = () => {
            const code = inputs.map(input => input.value).join("").toUpperCase();
            if (hidden) hidden.value = code;
            if (submit) submit.disabled = code.length !== inputs.length;
        };
        const initial = (hidden?.value || "").replace(/[^a-z0-9]/gi, "").toUpperCase();
        inputs.forEach((input, index) => {
            input.value = initial[index] || "";
            input.addEventListener("input", event => {
                event.target.value = event.target.value.replace(/[^a-z0-9]/gi, "").toUpperCase().slice(-1);
                if (event.target.value && inputs[index + 1]) inputs[index + 1].focus();
                sync();
            });
            input.addEventListener("keydown", event => {
                if (event.key === "Backspace" && !input.value && inputs[index - 1]) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = "";
                }
                if (event.key === "ArrowLeft" && inputs[index - 1]) inputs[index - 1].focus();
                if (event.key === "ArrowRight" && inputs[index + 1]) inputs[index + 1].focus();
            });
            input.addEventListener("paste", event => {
                event.preventDefault();
                const value = event.clipboardData.getData("text").replace(/[^a-z0-9]/gi, "").toUpperCase();
                inputs.forEach((item, position) => item.value = value[position] || "");
                inputs[Math.max(0, Math.min(value.length, inputs.length) - 1)]?.focus();
                sync();
            });
        });
        sync();
    }

    document.querySelectorAll("[data-menu-toggle]").forEach(button => {
        button.addEventListener("click", () => document.body.classList.toggle("admin-menu-open"));
    });

    document.querySelectorAll("[data-confirm]").forEach(element => {
        element.addEventListener("click", event => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });

    const eventRows = [...document.querySelectorAll("[data-event-row]")];
    if (eventRows.length) {
        const search = document.querySelector("[data-event-search]");
        const status = document.querySelector("[data-event-status]");
        const count = document.querySelector("[data-event-count]");
        const filterEvents = () => {
            const term = (search?.value || "").trim().toLowerCase();
            const selectedStatus = status?.value || "";
            let visible = 0;
            eventRows.forEach(row => {
                const matchesTerm = !term || row.dataset.name.includes(term) || row.dataset.code.includes(term);
                const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
                const show = matchesTerm && matchesStatus;
                row.hidden = !show;
                if (show) visible++;
            });
            if (count) count.textContent = `${visible} registros`;
        };
        search?.addEventListener("input", filterEvents);
        status?.addEventListener("change", filterEvents);
    }

    const ballotForm = document.getElementById("ballot-form");
    const reviewDialog = document.getElementById("vote-review");
    if (ballotForm && reviewDialog) {
        const openButton = document.querySelector("[data-open-review]");
        const list = reviewDialog.querySelector("[data-review-list]");
        const updateBallotProgress = () => {
            const total = ballotForm.querySelectorAll("input[type=radio]:checked").length;
            const all = ballotForm.querySelectorAll("[data-criterion]").length;
            const percentage = all === 0 ? 0 : Math.round(total / all * 100);
            document.querySelector("[data-ballot-progress]")?.style.setProperty("--progress", `${percentage}%`);
            const label = document.querySelector("[data-progress-label]");
            if (label) label.textContent = `${percentage}%`;
            if (openButton) openButton.disabled = total < all;
        };
        openButton?.addEventListener("click", () => {
            const groups = [...ballotForm.querySelectorAll("[data-criterion]")];
            const missing = groups.filter(group => !group.querySelector("input[type=radio]:checked"));
            groups.forEach(group => group.classList.toggle("has-error", missing.includes(group)));
            if (missing.length) {
                missing[0].scrollIntoView({ behavior: "smooth", block: "center" });
                missing[0].querySelector("input")?.focus();
                return;
            }
            list.innerHTML = "";
            groups.forEach(group => {
                const selected = group.querySelector("input[type=radio]:checked");
                const item = document.createElement("li");
                item.innerHTML = `<span>${group.dataset.criterion}</span><strong>${selected.value} / ${selected.dataset.max}</strong>`;
                list.appendChild(item);
            });
            reviewDialog.showModal();
        });
        reviewDialog.querySelector("[data-close-dialog]")?.addEventListener("click", () => reviewDialog.close());
        ballotForm.querySelectorAll("input[type=radio]").forEach(input => {
            input.addEventListener("change", () => {
                input.closest("[data-criterion]")?.classList.remove("has-error");
                updateBallotProgress();
            });
        });
        updateBallotProgress();
    }

    // ==========================================
    // Cronómetro Maestro Sincronizado (Master Timer)
    // ==========================================
    const formatTimerDisplay = (seconds) => {
        if (seconds === null || seconds === undefined || isNaN(seconds)) return "--:--";
        const total = Math.max(0, Math.floor(seconds));
        const mins = Math.floor(total / 60).toString().padStart(2, "0");
        const secs = (total % 60).toString().padStart(2, "0");
        return `${mins}:${secs}`;
    };

    const activeMasterTimers = [];

    const registerTimerElement = (element) => {
        let timerObj = activeMasterTimers.find(t => t.el === element);
        if (!timerObj) {
            timerObj = {
                el: element,
                remaining: null,
                syncedAt: performance.now(),
                isPaused: false
            };
            activeMasterTimers.push(timerObj);
        }

        let remaining = null;
        if (element.dataset.timerRemaining !== undefined && element.dataset.timerRemaining !== "") {
            const parsed = parseInt(element.dataset.timerRemaining, 10);
            if (!isNaN(parsed)) remaining = parsed;
        } else if (element.dataset.countdown) {
            const target = new Date(element.dataset.countdown).getTime();
            if (!isNaN(target)) {
                remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
            }
        }

        timerObj.remaining = remaining;
        timerObj.syncedAt = performance.now();
        timerObj.isPaused = element.dataset.timerPaused === "1" || element.hasAttribute("data-paused-timer");

        if (timerObj.remaining !== null) {
            element.textContent = formatTimerDisplay(timerObj.remaining);
        }

        return timerObj;
    };

    document.querySelectorAll("[data-countdown], [data-paused-timer], [data-timer-remaining]").forEach(registerTimerElement);

    // Tick local cada 1 segundo con reloj monotónico
    window.setInterval(() => {
        const now = performance.now();
        activeMasterTimers.forEach(t => {
            if (!document.body.contains(t.el)) return;
            if (t.remaining === null || t.remaining === undefined) return;
            if (t.isPaused) {
                t.el.textContent = formatTimerDisplay(t.remaining);
                return;
            }
            const elapsed = Math.floor((now - t.syncedAt) / 1000);
            const currentRemaining = Math.max(0, t.remaining - elapsed);
            t.el.textContent = formatTimerDisplay(currentRemaining);
        });
    }, 1000);

    const syncMasterTimer = (secondsRemaining, endsAt, isPaused) => {
        const now = performance.now();
        const timers = document.querySelectorAll("[data-countdown], [data-paused-timer], [data-timer-remaining]");
        timers.forEach(el => {
            let t = registerTimerElement(el);
            if (secondsRemaining !== null && secondsRemaining !== undefined && !isNaN(secondsRemaining)) {
                t.remaining = parseInt(secondsRemaining, 10);
                t.syncedAt = now;
                t.isPaused = Boolean(isPaused);
                el.dataset.timerRemaining = t.remaining;
                el.dataset.timerPaused = t.isPaused ? "1" : "0";
                if (endsAt) el.dataset.countdown = endsAt;
                el.textContent = formatTimerDisplay(t.remaining);
            } else {
                t.remaining = null;
                el.textContent = "--:--";
            }
        });
    };

    document.querySelectorAll("[data-live-clock]").forEach(clock => {
        const render = () => {
            clock.textContent = new Intl.DateTimeFormat(undefined, {
                hour: "2-digit",
                minute: "2-digit",
                hour12: false
            }).format(new Date());
        };
        render();
        window.setInterval(render, 1000);
    });

    const resultsGate = document.querySelector("[data-results-gate]");
    if (resultsGate) {
        const duration = Number(resultsGate.dataset.resultsDuration || 30000);
        const stateEndpoint = resultsGate.dataset.resultsState;
        const isPublished = resultsGate.dataset.resultsPublished === "true";
        const isFresh = resultsGate.dataset.resultsFresh === "true";
        const forceAnimation = resultsGate.dataset.resultsForceAnimation === "true";
        const revealKey = `innovamente-results:${resultsGate.dataset.eventCode}:round-${resultsGate.dataset.roundNumber}`;
        const publishedContent = resultsGate.querySelector(".results-published-content");
        const stageLabel = resultsGate.querySelector("[data-results-stage]");
        let calculationStarted = false;

        // Limpiar parámetro 'transition' de la URL si existe para que recargar la página no vuelva a forzar la animación
        if (window.location.search.includes("transition=")) {
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete("transition");
            window.history.replaceState({}, "", cleanUrl.pathname + (cleanUrl.searchParams.toString() ? "?" + cleanUrl.searchParams.toString() : ""));
        }

        const storage = {
            get: key => { try { return window.sessionStorage.getItem(key); } catch { return null; } },
            set: (key, value) => { try { window.sessionStorage.setItem(key, value); } catch {} },
            remove: key => { try { window.sessionStorage.removeItem(key); } catch {} }
        };
        const reveal = () => {
            resultsGate.classList.remove("is-waiting", "is-calculating");
            resultsGate.classList.add("is-revealed");
            publishedContent?.setAttribute("aria-hidden", "false");
        };

        const skipBtn = resultsGate.querySelector("[data-results-skip]");
        if (skipBtn) {
            skipBtn.addEventListener("click", () => {
                storage.set(revealKey, "revealed");
                reveal();
            });
        }

        const calculate = reloadAfter => {
            if (calculationStarted) return;
            calculationStarted = true;
            resultsGate.classList.remove("is-waiting", "is-revealed");
            resultsGate.classList.add("is-calculating");
            resultsGate.style.setProperty("--results-duration", `${duration}ms`);
            const stages = [
                "Recopilando las evaluaciones recibidas…",
                "Validando votos y ponderaciones…",
                "Ordenando las posiciones finales…"
            ];
            if (stageLabel) stageLabel.textContent = stages[0];
            window.setTimeout(() => { if (stageLabel) stageLabel.textContent = stages[1]; }, duration / 3);
            window.setTimeout(() => { if (stageLabel) stageLabel.textContent = stages[2]; }, duration * 2 / 3);
            window.setTimeout(() => {
                storage.set(revealKey, "revealed");
                if (reloadAfter) window.location.reload();
                else reveal();
            }, duration);
        };

        if (isPublished) {
            // Solo animar si fue publicado recientemente (<= 5 min) Y no ha sido revelado previamente en esta sesión
            if (!isFresh || (!forceAnimation && storage.get(revealKey) === "revealed")) {
                reveal();
            } else {
                calculate(false);
            }
        } else {
            storage.remove(revealKey);
            const checkPublication = async () => {
                try {
                    const response = await fetch(stateEndpoint, { headers: { Accept: "application/json" }, cache: "no-store" });
                    if (!response.ok) return;
                    const envelope = await response.json();
                    if (envelope.ok && envelope.data.eventStatus === "Published") calculate(true);
                } catch {
                    document.body.classList.add("connection-lost");
                }
            };
            window.setInterval(checkPublication, 3000 + Math.floor(Math.random() * 700));
        }
    }

    const liveRoot = document.querySelector("[data-live-poll]");
    if (liveRoot) {
        let fingerprint = liveRoot.dataset.state;
        const endpoint = liveRoot.dataset.livePoll;
        const interval = Number(liveRoot.dataset.pollInterval || 3000);
        const poll = async () => {
            try {
                const response = await fetch(endpoint, { headers: { Accept: "application/json" }, cache: "no-store" });
                if (!response.ok) return;
                const envelope = await response.json();
                if (!envelope.ok) return;
                const state = envelope.data;

                // 1. Sincronizar el cronómetro maestro en pantalla
                syncMasterTimer(state.timerRemainingSeconds, state.timerEndsAt, state.timerIsPaused);

                // 2. Si el evento fue publicado, redirigir a resultados si procede
                if (liveRoot.dataset.resultsRedirect && state.eventStatus === "Published") {
                    window.location.replace(liveRoot.dataset.resultsRedirect);
                    return;
                }

                // 3. Actualizar conteo de votos del público y del jurado
                document.querySelectorAll("[data-public-count]").forEach(item => item.textContent = state.publicVoteCount);
                document.querySelectorAll("[data-jury-count]").forEach(item => item.textContent = state.jurorVoteCount);
                document.querySelectorAll("[data-public-track]").forEach(item => {
                    item.style.width = `${Math.min(100, state.publicVoteCount * 5)}%`;
                });
                document.querySelectorAll("[data-jury-track]").forEach(item => {
                    const total = Number(item.closest("[data-live-poll]")?.dataset.jurorTotal || 0);
                    if (total > 0) item.style.width = `${Math.min(100, state.jurorVoteCount * 100 / total)}%`;
                });

                // 4. Actualizar estado individual de jurados en mesa de control
                if (Array.isArray(state.jurors)) {
                    state.jurors.forEach(juror => {
                        const row = document.querySelector(`[data-juror-row="${juror.id}"]`);
                        if (!row) return;
                        const icon = row.querySelector(".juror-status-icon");
                        const badge = row.querySelector(".juror-status-badge");
                        if (juror.hasVoted) {
                            if (icon) {
                                icon.textContent = "check_circle";
                                icon.style.color = "var(--success, #16a34a)";
                            }
                            if (badge) {
                                badge.className = "badge badge-sm badge-success juror-status-badge";
                                badge.textContent = "Voto recibido";
                            }
                        } else {
                            if (icon) {
                                icon.textContent = "pending";
                                icon.style.color = "var(--text-muted)";
                            }
                            if (badge) {
                                badge.className = "badge badge-sm badge-muted juror-status-badge";
                                badge.textContent = "Pendiente";
                            }
                        }
                    });
                }

                // 5. Si estamos en una pantalla con formulario de votación (papeleta de votación activa):
                const hasActiveBallotForm = !!document.querySelector("#ballot-form, form[data-ballot-form], .ballot-form");
                const noAutoReload = liveRoot.dataset.noReload === "true" || hasActiveBallotForm;

                if (noAutoReload) {
                    // Si la votación sigue abierta para este equipo, NUNCA recargar la página (protege los votos del usuario)
                    if (state.presentationStatus === "VotingOpen") {
                        return;
                    }
                    // Si el operador cerró la votación o cambió de presentación, salir de la papeleta y volver al lobby
                    const lobbyUrl = liveRoot.dataset.lobbyUrl || (window.location.pathname.includes("jurado") ? "/jurado/panel" : "/evento");
                    window.location.replace(lobbyUrl);
                    return;
                }

                // 6. Comparar cambios estructurales para recarga de pantalla (solo en lobbies, proyección y control en vivo)
                const next = [
                    state.eventStatus,
                    state.roundNumber,
                    state.presentationId,
                    state.presentationStatus,
                    state.publicVoteCount,
                    state.jurorVoteCount,
                    state.currentActorHasVoted ? "true" : "false",
                    state.timerIsPaused ? "true" : "false",
                    state.participantFingerprint || ""
                ].join("|");

                if (!fingerprint) {
                    fingerprint = next;
                    liveRoot.dataset.state = next;
                    return;
                }

                const current = fingerprint.split("|");
                const candidate = next.split("|");
                const norm = val => (val === null || val === undefined ? "" : String(val).trim().toLowerCase());

                // Solo recargar si cambia el estado del evento, la ronda, la presentación o su estatus, o si el actor votó
                const stageChanged = norm(current[0]) !== norm(candidate[0])
                    || norm(current[1]) !== norm(candidate[1])
                    || norm(current[2]) !== norm(candidate[2])
                    || norm(current[3]) !== norm(candidate[3]);
                const actorVoteChanged = norm(current[6]) !== norm(candidate[6]);
                const structuralChanged = stageChanged || actorVoteChanged;

                fingerprint = next;
                liveRoot.dataset.state = next;

                if (structuralChanged) {
                    const activeModal = document.querySelector(".modal-backdrop:not([style*='display: none'])");
                    const justOpenedVoting = norm(current[3]) !== "votingopen" && norm(candidate[3]) === "votingopen";

                    // a) Si la votación se acaba de abrir en el lobby, cerrar modal y recargar para mostrar el botón de votar
                    if (justOpenedVoting) {
                        if (activeModal && activeModal.id === "public-team-modal") {
                            activeModal.style.display = "none";
                        }
                        window.location.reload();
                        return;
                    }

                    // b) Si el modal de reiniciar ronda está abierto en control en vivo, no recargar para no interrumpir al operador
                    if (activeModal && activeModal.id === "restart-round-modal") {
                        return;
                    }

                    // c) Si el usuario está leyendo detalles del equipo y la presentación sigue siendo la misma, no recargar
                    if (activeModal && activeModal.id === "public-team-modal") {
                        const samePresentation = norm(current[2]) === norm(candidate[2]);
                        if (samePresentation) {
                            return;
                        }
                    }

                    window.location.reload();
                }
            } catch {
                document.body.classList.add("connection-lost");
            }
        };
        // Polling estricto cada 3 segundos
        window.setInterval(poll, interval);
    }

    document.querySelectorAll("[data-rubric-editor]").forEach(form => {
        const list = form.querySelector("[data-criterion-list]");
        const template = form.querySelector("[data-rubric-template]");
        const addButton = form.querySelector("[data-add-criterion]");
        if (!list || !template || !addButton) return;

        const renumber = () => {
            [...list.querySelectorAll("[data-criterion-row]")].forEach((row, index) => {
                row.querySelector("[data-criterion-number]").textContent = index + 1;
                row.querySelectorAll("input[name], select[name], textarea[name]").forEach(field => {
                    field.name = field.name.replace(/criteria\[(?:\d+|__index__)\]/i, `criteria[${index}]`);
                });
            });
        };

        addButton.addEventListener("click", () => {
            const fragment = template.content.cloneNode(true);
            list.append(fragment);
            renumber();
            list.querySelector("[data-criterion-row]:last-child input[name$='[name]']")?.focus();
        });
        list.addEventListener("click", event => {
            const button = event.target.closest("[data-remove-criterion]");
            if (!button) return;
            const rows = list.querySelectorAll("[data-criterion-row]");
            if (rows.length === 1) {
                window.alert("Cada rúbrica necesita al menos un criterio.");
                return;
            }
            button.closest("[data-criterion-row]")?.remove();
            renumber();
        });
    });

    document.querySelectorAll("[data-member-list]").forEach(editor => {
        const list = editor.querySelector("[data-member-rows]");
        const template = editor.querySelector("[data-member-template]");
        const addButton = editor.querySelector("[data-add-member]");
        const count = editor.querySelector("[data-member-count]");
        const limit = Number(editor.dataset.memberLimit || 30);
        const prefix = editor.dataset.memberPrefix || "team-member";
        if (!list || !template || !addButton) return;

        const rows = () => [...list.querySelectorAll("[data-member-row]")];
        const refresh = () => {
            const currentRows = rows();
            currentRows.forEach((row, index) => {
                const input = row.querySelector("input[name='members[]']");
                if (!input) return;
                input.id = `${prefix}-${index}`;
                input.setAttribute("aria-label", `Nombre del integrante ${index + 1}`);
            });
            const completed = currentRows.filter(row => row.querySelector("input")?.value.trim()).length;
            if (count) count.textContent = `${completed} ${completed === 1 ? "integrante" : "integrantes"}`;
            addButton.disabled = currentRows.length >= limit;
        };
        const appendRow = () => {
            if (rows().length >= limit) return;
            list.append(template.content.cloneNode(true));
            refresh();
            rows().at(-1)?.querySelector("input")?.focus();
        };

        addButton.addEventListener("click", appendRow);
        list.addEventListener("click", event => {
            const button = event.target.closest("[data-remove-member]");
            if (!button) return;
            const currentRows = rows();
            if (currentRows.length === 1) {
                const input = currentRows[0].querySelector("input");
                if (input) input.value = "";
                input?.focus();
            } else {
                button.closest("[data-member-row]")?.remove();
            }
            refresh();
        });
        list.addEventListener("input", refresh);
        list.addEventListener("keydown", event => {
            if (event.key !== "Enter" || !event.target.matches("input[name='members[]']")) return;
            event.preventDefault();
            const currentRows = rows();
            const currentIndex = currentRows.indexOf(event.target.closest("[data-member-row]"));
            if (currentIndex < currentRows.length - 1) {
                currentRows[currentIndex + 1].querySelector("input")?.focus();
            } else {
                appendRow();
            }
        });
        refresh();
    });

    const jurorInput = document.querySelector("[data-juror-search]");
    if (jurorInput) {
        const results = document.querySelector("[data-juror-results]");
        const titleInput = document.querySelector("[data-juror-title]");
        const emailInput = document.querySelector("[data-juror-email]");
        let timer;
        const clearResults = () => {
            if (results) results.replaceChildren();
        };
        jurorInput.addEventListener("input", () => {
            window.clearTimeout(timer);
            const term = jurorInput.value.trim();
            if (term.length < 2) {
                clearResults();
                return;
            }
            timer = window.setTimeout(async () => {
                try {
                    const response = await fetch(`/api/admin/jurados/buscar?query=${encodeURIComponent(term)}`, {
                        headers: { Accept: "application/json" },
                        cache: "no-store"
                    });
                    const envelope = await response.json();
                    if (!response.ok || !envelope.ok || jurorInput.value.trim() !== term || !results) return;
                    results.replaceChildren();
                    envelope.data.forEach(juror => {
                        const option = document.createElement("button");
                        option.type = "button";
                        option.className = "juror-suggestion";
                        const name = document.createElement("strong");
                        name.textContent = juror.name;
                        const detail = document.createElement("span");
                        detail.textContent = [juror.title, juror.email].filter(Boolean).join(" · ") || "Datos registrados";
                        option.append(name, detail);
                        option.addEventListener("click", () => {
                            jurorInput.value = juror.name;
                            if (titleInput && juror.title) titleInput.value = juror.title;
                            if (emailInput && juror.email) emailInput.value = juror.email;
                            clearResults();
                        });
                        results.append(option);
                    });
                } catch {
                    clearResults();
                }
            }, 220);
        });
        document.addEventListener("click", event => {
            if (!event.target.closest("[data-juror-picker]")) clearResults();
        });
    }

    // Generic Modal Open/Close
    document.querySelectorAll("[data-modal-open]").forEach(btn => {
        btn.addEventListener("click", () => {
            const modalId = btn.dataset.modalOpen;
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = "flex";
        });
    });

    document.querySelectorAll("[data-modal-close]").forEach(btn => {
        btn.addEventListener("click", () => {
            const modalId = btn.dataset.modalClose;
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = "none";
        });
    });

    document.querySelectorAll(".modal-backdrop").forEach(backdrop => {
        backdrop.addEventListener("click", e => {
            if (e.target === backdrop) backdrop.style.display = "none";
        });
    });

    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            document.querySelectorAll(".modal-backdrop").forEach(m => m.style.display = "none");
            const sm = document.querySelector("[data-switcher-menu]");
            if (sm) sm.style.display = "none";
        }
    });

    // Team details modal for public & jury lobbies
    document.querySelectorAll("[data-team-modal]").forEach(elem => {
        elem.addEventListener("click", e => {
            e.stopPropagation();
            try {
                const data = JSON.parse(elem.dataset.teamModal);
                const modal = document.getElementById("public-team-modal");
                if (!modal) return;
                const nameEl = document.getElementById("modal-team-name");
                const numEl = document.getElementById("modal-team-number");
                const projEl = document.getElementById("modal-team-project");
                const areaEl = document.getElementById("modal-team-area");
                const descEl = document.getElementById("modal-team-desc");
                if (nameEl) nameEl.textContent = data.name || "Equipo";
                if (numEl) numEl.innerHTML = `<span class="material-symbols-outlined" style="font-size: 15px;">groups</span> #${data.number || 1}`;
                if (projEl) projEl.textContent = data.project || "Propuesta de innovación";
                if (areaEl) areaEl.textContent = data.area || "Innovación";
                if (descEl) descEl.textContent = data.description || "Sin descripción detallada.";

                const membersBox = document.getElementById("modal-team-members-box");
                const membersList = document.getElementById("modal-team-members-list");
                if (membersList && membersBox) {
                    membersList.innerHTML = "";
                    if (data.members && data.members.length) {
                        membersBox.style.display = "block";
                        data.members.forEach(m => {
                            const trimmed = String(m).trim();
                            if (!trimmed) return;
                            const chip = document.createElement("div");
                            chip.className = "team-member-chip";
                            chip.style.cssText = "background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 999px; padding: 4px 12px 4px 6px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #1E293B; box-shadow: 0 1px 2px rgba(0,0,0,0.04);";
                            const initial = (trimmed[0] || "M").toUpperCase();
                            chip.innerHTML = `<span style="width: 24px; height: 24px; border-radius: 50%; background: #0C58C7; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">${initial}</span><span>${trimmed}</span>`;
                            membersList.appendChild(chip);
                        });
                    } else {
                        membersBox.style.display = "none";
                    }
                }
                modal.style.display = "flex";
            } catch (err) {
                console.error("Error parsing team modal data", err);
            }
        });
    });

    // Juror participation history modal
    document.querySelectorAll("[data-view-history]").forEach(elem => {
        elem.addEventListener("click", e => {
            e.stopPropagation();
            try {
                const history = JSON.parse(elem.dataset.viewHistory || "[]");
                const jurorName = elem.dataset.jurorName || elem.closest("tr")?.querySelector(".table-title")?.textContent?.trim() || "Jurado";
                const modal = document.getElementById("juror-history-modal");
                const title = document.getElementById("history-modal-title");
                const tbody = document.getElementById("history-modal-body");
                if (!modal || !tbody) return;

                if (title) title.textContent = `Historial de Participación · ${jurorName}`;
                tbody.innerHTML = "";

                if (!history.length) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">Sin asignaciones registradas.</td></tr>`;
                } else {
                    history.forEach(h => {
                        const tr = document.createElement("tr");
                        tr.innerHTML = `
                            <td><strong>${h.eventName}</strong></td>
                            <td><code>${h.eventCode}</code></td>
                            <td><small>Peso ${h.weight}</small></td>
                            <td><span class="badge ${h.jurorStatus === 'Active' ? 'badge-success' : 'badge-muted'}">${h.jurorStatus === 'Active' ? 'Activo' : 'Inactivo'}</span></td>
                            <td><small class="table-subtitle">${h.createdAt}</small></td>
                            <td style="text-align: right;">
                                <a class="button button-sm button-ghost" href="${h.eventUrl}" title="Ver jurados del evento">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">launch</span>
                                </a>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
                modal.style.display = "flex";
            } catch (err) {
                console.error("Error viewing juror history", err);
            }
        });
    });

    // Global Juror search filter
    const jurorSearch = document.querySelector("[data-juror-search]");
    if (jurorSearch) {
        const rows = [...document.querySelectorAll("[data-juror-row]")];
        const count = document.querySelector("[data-jurors-count]");
        jurorSearch.addEventListener("input", () => {
            const term = jurorSearch.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(r => {
                const matches = !term ||
                    (r.dataset.name && r.dataset.name.includes(term)) ||
                    (r.dataset.email && r.dataset.email.includes(term)) ||
                    (r.dataset.title && r.dataset.title.includes(term));
                r.hidden = !matches;
                if (matches) visible++;
            });
            if (count) count.textContent = `${visible} jurados mostrados`;
        });
    }

    // Contextual Event Switcher Dropdown
    const switcherToggle = document.querySelector("[data-switcher-toggle]");
    const switcherMenu = document.querySelector("[data-switcher-menu]");
    if (switcherToggle && switcherMenu) {
        switcherToggle.addEventListener("click", e => {
            e.stopPropagation();
            const isOpen = switcherMenu.style.display === "block";
            switcherMenu.style.display = isOpen ? "none" : "block";
        });
        document.addEventListener("click", e => {
            if (!e.target.closest("[data-event-switcher]")) {
                switcherMenu.style.display = "none";
            }
        });
    }

    // Live Metrics Polling for Admin Dashboard
    const liveMetricsContainer = document.querySelector("[data-live-metrics-url]");
    if (liveMetricsContainer) {
        const metricsUrl = liveMetricsContainer.dataset.liveMetricsUrl;
        const pollMetrics = async () => {
            try {
                const res = await fetch(metricsUrl, { headers: { Accept: "application/json" }, cache: "no-store" });
                const json = await res.json();
                if (res.ok && json.ok && json.data) {
                    const d = json.data;
                    const update = (key, val) => {
                        const el = document.querySelector(`[data-metric="${key}"]`);
                        if (el) el.textContent = typeof val === "number" ? val.toLocaleString() : val;
                    };
                    update("activeEvents", d.activeEvents);
                    update("connectedUsers", d.connectedUsers);
                    update("totalVotes", d.totalVotes);
                    update("activeJurors", d.activeJurors);
                }
            } catch {}
        };
        window.setInterval(pollMetrics, 6000);
    }

    window.setTimeout(() => {
        document.querySelectorAll(".toast").forEach(toast => toast.classList.add("toast-hidden"));
    }, 4500);
})();
