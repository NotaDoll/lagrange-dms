                function setupComplaintSummaries(selected, csrfToken, selectionChanged) {
                    const generateButton = document.getElementById('anGenerateSummary');
                    const generateLabel = document.getElementById('anGenerateLabel');
                    const trigger = document.getElementById('anSummaryTrigger');
                    const historyButton = document.getElementById('anSummaryHistory');
                    const overlay = document.getElementById('anSummaryOverlay');
                    const title = document.getElementById('anSummaryTitle');
                    const loading = document.getElementById('anSummaryLoading');
                    const errorMessage = document.getElementById('anSummaryError');
                    const panels = overlay.querySelectorAll('[data-summary-panel]');
                    const backButton = document.getElementById('anSummaryBack');
                    const retryButton = document.getElementById('anSummaryRetry');
                    const historyItems = document.getElementById('anHistoryItems');
                    const previous = document.getElementById('anHistoryPrevious');
                    const next = document.getElementById('anHistoryNext');
                    let opener;
                    let lastHistoryItem;
                    let historyPage = 1;
                    let retry;
                    let generating = false;
                    let requestVersion = 0;

                    function updateButton() {
                        const count = selected.size;
                        generateButton.disabled = generating || count === 0;
                        generateLabel.textContent = generating ? 'Generating…' : 'Generate Summary' + (count ? ' (' + count + ')' : '');
                        generateButton.setAttribute('aria-busy', String(generating));
                        trigger.title = generateButton.title = count ? '' : 'Select complaints to summarize';
                    }

                    function updateSummarySelection() {
                        try {
                            if (selected.size) {
                                sessionStorage.setItem(selectionKey, JSON.stringify({ filters: initialFilters, ids: [...selected] }));
                            } else {
                                sessionStorage.removeItem(selectionKey);
                            }
                        } catch (error) {
                            // The selected IDs remain available in memory.
                        }
                        updateButton();
                    }

                    function showPanel(panel, heading, showBack = false) {
                        title.textContent = heading;
                        panels.forEach(element => { element.hidden = element.dataset.summaryPanel !== panel; });
                        backButton.hidden = !showBack;
                        retryButton.hidden = panel !== 'error';
                        if (overlay.classList.contains('show')) { title.focus(); }
                    }

                    function showError(error, onRetry, heading) {
                        errorMessage.textContent = error.message;
                        retry = onRetry;
                        showPanel('error', heading);
                    }

                    function openOverlay(button) {
                        opener = button;
                        bootstrap.Modal.getOrCreateInstance(overlay).show();
                    }

                    overlay.addEventListener('shown.bs.modal', () => title.focus());
                    overlay.addEventListener('hide.bs.modal', () => { requestVersion++; });
                    overlay.addEventListener('hidden.bs.modal', () => {
                        (opener?.disabled ? historyButton : opener)?.focus();
                    });
                    retryButton.addEventListener('click', () => retry());
                    backButton.addEventListener('click', () => {
                        if (opener === historyButton) {
                            requestVersion++;
                            showPanel('history', 'Summary History');
                            lastHistoryItem?.focus();
                        } else {
                            bootstrap.Modal.getInstance(overlay).hide();
                        }
                    });

                    async function request(url, fallback, { method = 'GET', body } = {}) {
                        let response;
                        try {
                            response = await fetch(url, {
                                method,
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: body === undefined ? undefined : JSON.stringify(body),
                            });
                        } catch (error) {
                            throw new Error(fallback);
                        }

                        if (response.status === 204) { return; }
                        const data = await response.json().catch(() => null);
                        if (!response.ok || !data) {
                            throw new Error([422, 502].includes(response.status) && data?.message ? data.message : fallback);
                        }
                        return data;
                    }

                    function textElement(tag, text, className = '') {
                        const element = document.createElement(tag);
                        element.textContent = text;
                        element.className = className;
                        return element;
                    }

                    function renderSummary(summary) {
                        const count = summary.complaint_count;
                        document.getElementById('anSummaryCount').textContent = 'Summary of ' + count + ' selected ' + (count === 1 ? 'complaint' : 'complaints');
                        const period = summary.period_start === summary.period_end
                            ? summary.period_start
                            : [summary.period_start, summary.period_end].filter(Boolean).join(' – ');
                        const chips = summary.categories.map(category => textElement('span', category, 'an-badge text-capitalize'));
                        if (period) { chips.unshift(textElement('span', period, 'an-badge')); }
                        document.getElementById('anSummaryChips').replaceChildren(...chips);
                        document.getElementById('anSummaryGenerated').textContent = 'Generated ' + summary.created_at + ' · ' + summary.created_ago;
                        document.getElementById('anSummaryText').textContent = summary.summary;
                        document.getElementById('anSummaryIncluded').open = false;
                        document.getElementById('anSummaryComplaints').replaceChildren(...summary.complaints.map(complaint =>
                            textElement('li', [complaint.tenant, complaint.category, complaint.date].join(' · '))
                        ));
                        document.getElementById('anSummaryMissing').hidden = !summary.complaints_missing;
                        showPanel('detail', 'Complaints Summary', true);
                    }

                    async function generate(ids) {
                        if (generating || !ids.length) { return; }
                        const version = ++requestVersion;
                        generating = true;
                        updateButton();
                        loading.textContent = 'Generating summary…';
                        showPanel('loading', 'Complaints Summary');

                        try {
                            const summary = await request(generateButton.dataset.url, 'Could not generate a summary. Please try again.', {
                                method: 'POST',
                                body: { complaint_ids: ids },
                            });
                            selected.clear();
                            selectionChanged();
                            if (version === requestVersion) { renderSummary(summary); }
                        } catch (error) {
                            if (version === requestVersion) {
                                showError(error, () => generate(ids), 'Complaints Summary');
                            }
                        } finally {
                            generating = false;
                            updateButton();
                        }
                    }

                    async function loadHistory(page) {
                        const version = ++requestVersion;
                        loading.textContent = 'Loading history…';
                        showPanel('loading', 'Summary History');
                        const url = new URL(historyButton.dataset.url);
                        url.searchParams.set('page', page);

                        try {
                            const result = await request(url, 'Could not load summary history. Please try again.');
                            if (version !== requestVersion) { return; }
                            historyPage = result.current_page;
                            lastHistoryItem = null;
                            historyItems.replaceChildren(...result.data.map(renderHistoryItem));
                            document.getElementById('anSummaryEmpty').hidden = result.data.length !== 0;
                            document.getElementById('anHistoryPages').classList.toggle('d-none', result.last_page <= 1);
                            document.getElementById('anHistoryPage').textContent = 'Page ' + historyPage + ' of ' + result.last_page;
                            previous.disabled = historyPage <= 1;
                            next.disabled = historyPage >= result.last_page;
                            showPanel('history', 'Summary History');
                        } catch (error) {
                            if (version === requestVersion) { showError(error, () => loadHistory(page), 'Summary History'); }
                        }
                    }

                    async function openSavedSummary(url) {
                        const version = ++requestVersion;
                        loading.textContent = 'Loading summary…';
                        showPanel('loading', 'Complaints Summary', true);

                        try {
                            const summary = await request(url, 'Could not open this summary. It may have been deleted.');
                            if (version === requestVersion) { renderSummary(summary); }
                        } catch (error) {
                            if (version === requestVersion) {
                                showError(error, () => openSavedSummary(url), 'Complaints Summary');
                                backButton.hidden = false;
                            }
                        }
                    }

                    async function deleteSummary(url, page) {
                        const version = ++requestVersion;
                        loading.textContent = 'Deleting summary…';
                        showPanel('loading', 'Summary History');

                        try {
                            await request(url, 'Could not delete this summary. Please try again.', { method: 'DELETE' });
                            if (version === requestVersion) { loadHistory(page); }
                        } catch (error) {
                            if (version === requestVersion) { showError(error, () => deleteSummary(url, page), 'Summary History'); }
                        }
                    }

                    function renderHistoryItem(summary) {
                        const item = textElement('li', '', 'list-group-item px-0 d-flex align-items-start gap-2');
                        const open = textElement('button', '', 'btn text-start flex-grow-1 px-2');
                        open.type = 'button';
                        open.append(
                            textElement('span', summary.created_at + ' · ' + summary.created_ago, 'd-block fw-semibold'),
                            textElement('span', [summary.complaint_count + (summary.complaint_count === 1 ? ' complaint' : ' complaints'), ...summary.categories].join(' · '), 'd-block small text-muted'),
                            textElement('span', summary.preview, 'd-block text-break'),
                        );
                        open.addEventListener('click', () => {
                            lastHistoryItem = open;
                            openSavedSummary(summary.url);
                        });

                        const remove = textElement('button', 'Delete', 'btn btn-sm btn-outline-danger mt-1');
                        remove.type = 'button';
                        remove.setAttribute('aria-label', 'Delete summary from ' + summary.created_at);
                        remove.addEventListener('click', () => {
                            if (window.confirm('Delete this complaint summary?')) {
                                const page = historyItems.children.length === 1 ? Math.max(1, historyPage - 1) : historyPage;
                                deleteSummary(summary.url, page);
                            }
                        });
                        item.append(open, remove);
                        return item;
                    }

                    generateButton.addEventListener('click', () => {
                        generate([...selected]);
                        openOverlay(generateButton);
                    });
                    historyButton.addEventListener('click', () => {
                        loadHistory(1);
                        openOverlay(historyButton);
                    });
                    previous.addEventListener('click', () => loadHistory(historyPage - 1));
                    next.addEventListener('click', () => loadHistory(historyPage + 1));

                    return updateSummarySelection;
                }
