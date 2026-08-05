(() => {
    "use strict";

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

    document.querySelectorAll("[data-countdown]").forEach(timer => {
        const target = new Date(timer.dataset.countdown).getTime();
        const render = () => {
            const remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
            const minutes = Math.floor(remaining / 60).toString().padStart(2, "0");
            const seconds = (remaining % 60).toString().padStart(2, "0");
            timer.textContent = `${minutes}:${seconds}`;
        };
        render();
        window.setInterval(render, 1000);
    });

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

    const liveRoot = document.querySelector("[data-live-poll]");
    if (liveRoot) {
        let fingerprint = liveRoot.dataset.state;
        const endpoint = liveRoot.dataset.livePoll;
        const interval = Number(liveRoot.dataset.pollInterval || 5000);
        const poll = async () => {
            try {
                const response = await fetch(endpoint, { headers: { Accept: "application/json" }, cache: "no-store" });
                if (!response.ok) return;
                const envelope = await response.json();
                if (!envelope.ok) return;
                const state = envelope.data;
                const next = [
                    state.eventStatus,
                    state.presentationId,
                    state.presentationStatus,
                    state.publicVoteCount,
                    state.jurorVoteCount,
                    state.currentActorHasVoted,
                    state.timerIsPaused
                ].join("|");
                const current = (fingerprint || "").split("|");
                const candidate = next.split("|");
                document.querySelectorAll("[data-public-count]").forEach(item => item.textContent = state.publicVoteCount);
                document.querySelectorAll("[data-jury-count]").forEach(item => item.textContent = state.jurorVoteCount);
                document.querySelectorAll("[data-public-track]").forEach(item => {
                    item.style.width = `${Math.min(100, state.publicVoteCount * 5)}%`;
                });
                document.querySelectorAll("[data-jury-track]").forEach(item => {
                    const total = Number(item.closest("[data-live-poll]")?.dataset.jurorTotal || 0);
                    if (total > 0) item.style.width = `${Math.min(100, state.jurorVoteCount * 100 / total)}%`;
                });
                const structuralChanged = current.slice(0, 3).join("|") !== candidate.slice(0, 3).join("|")
                    || current[5] !== candidate[5]
                    || current[6] !== candidate[6];
                fingerprint = next;
                liveRoot.dataset.state = next;
                if (structuralChanged) window.location.reload();
            } catch {
                document.body.classList.add("connection-lost");
            }
        };
        window.setInterval(poll, interval + Math.floor(Math.random() * 900));
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

    window.setTimeout(() => {
        document.querySelectorAll(".toast").forEach(toast => toast.classList.add("toast-hidden"));
    }, 4500);
})();
