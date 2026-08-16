(function ($) {
    'use strict';

    if (typeof SiteBuilderData === 'undefined') return;

    // ---- Module-level state for cross-cutting concerns ----
    // anyRunning is shared by both forms so navigation handlers can see "is any import live".
    var anyRunning = false;

    /**
     * Smart recovery from a batch AJAX failure. Two main cases:
     *
     * 1) Transient (504/502/503/timeout/network drop): PHP may still be
     *    running. Wait a moment, then ask the server via check_active what
     *    the current DB offset is. If it advanced past our last known value,
     *    PHP survived — continue. If it didn't advance for two consecutive
     *    polls, PHP truly died — fall back to the resume banner.
     *
     * 2) Hard error: give up, surface the banner so user can retry manually.
     *
     * All timeouts here are console-logged so an operator can diagnose via F12.
     */
    function handleBatchFailure(xhr, textStatus, state, retryFn) {
        var status = xhr.status;
        var isTransient = (status === 504 || status === 502 || status === 503 || status === 0 || textStatus === 'timeout');
        console.log('[SiteBuilder] batch failed:', {
            status: status,
            textStatus: textStatus,
            isTransient: isTransient,
            retriesUsed: state.retriesUsed || 0,
            lastKnownProcessed: state.processed || 0
        });

        if (!isTransient) {
            // Hard fail — server returned a real error. Show final message.
            if (state.showResultFn) {
                state.showResultFn('failed', 'Сетевая ошибка: HTTP ' + status);
            }
            return;
        }

        state.retriesUsed = (state.retriesUsed || 0) + 1;
        if (state.retriesUsed > 3) {
            // Three transient failures in a row — PHP is genuinely stuck.
            // Show the resume banner so user can manually intervene.
            if (state.showResultFn) {
                state.showResultFn('failed',
                    'Сервер не отвечает после нескольких попыток (HTTP ' + status + '). ' +
                    'Импорт сохранён в БД. Обновите страницу (F5) — появится баннер «Продолжить импорт».');
            }
            return;
        }

        // Update UI to show we're diagnosing
        if (state.updateProgressFn) {
            state.updateProgressFn(state.processed || 0, state.total || 0,
                'Проверяю статус сервера (попытка ' + state.retriesUsed + '/3)…');
        }

        // Wait 10 seconds, then poll check_active to see if PHP recovered
        setTimeout(function () {
            $.ajax({
                url: SiteBuilderData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'site_builder_check_active',
                    nonce: SiteBuilderData.nonce
                },
                timeout: 15000
            }).done(function (resp) {
                if (!resp || !resp.success || !resp.data) {
                    // check_active itself failed — try full retry
                    console.log('[SiteBuilder] check_active gave no data, retrying batch');
                    retryFn();
                    return;
                }
                if (!resp.data.active) {
                    // Server says nothing is running — either finished or was cancelled
                    console.log('[SiteBuilder] check_active says no active import — likely completed');
                    if (state.showResultFn) {
                        state.showResultFn('completed',
                            'Импорт завершён (обнаружено после восстановления). Обновите страницу для проверки результатов.');
                    }
                    return;
                }
                var currentDbProcessed = resp.data.import.processed;
                var advanced = currentDbProcessed > (state.processed || 0);
                console.log('[SiteBuilder] server offset check:',
                    'was=' + (state.processed || 0), 'now=' + currentDbProcessed,
                    'advanced=' + advanced);
                if (advanced) {
                    // PHP did survive and advance the offset — continue as normal.
                    state.processed = currentDbProcessed;
                    if (state.updateProgressFn) {
                        state.updateProgressFn(currentDbProcessed, resp.data.import.total,
                            'Восстановлено, продолжаю…');
                    }
                    state.retriesUsed = 0; // reset since we found forward progress
                    retryFn();
                } else {
                    // No advance — PHP is probably stuck on the current task.
                    // Try one more full retry; on next fail we'll bail to banner.
                    retryFn();
                }
            }).fail(function () {
                // Even check_active failed — server is probably down. One more try.
                console.log('[SiteBuilder] check_active also failed, retrying batch anyway');
                retryFn();
            });
        }, 10000);
    }

    // ---- Tab navigation & unload protection ----
    // Intercept clicks on other tabs while an import is running. If user confirms,
    // best-effort cancel via sendBeacon, then let the navigation happen.
    $('.nav-tab-wrapper.sb-tabs .nav-tab').not('.nav-tab-active').on('click', function (e) {
        if (!anyRunning) return;
        if (!confirm(SiteBuilderData.strings.confirmNav)) {
            e.preventDefault();
            return;
        }
        sendBeaconCancel();
    });

    // beforeunload: just shows the browser's "Leave page?" dialog. Crucially we do NOT
    // send the cancel here — beforeunload fires before the user picks "Cancel"/"Leave",
    // so an unconditional cancel would kill the import even if they choose to stay.
    $(window).on('beforeunload', function (e) {
        if (!anyRunning) return;
        e.returnValue = SiteBuilderData.strings.confirmUnload;
        return SiteBuilderData.strings.confirmUnload;
    });

    // pagehide/unload: fires only when the page is actually being unloaded (the user
    // confirmed they want to leave, or the tab is being closed). Safe place to cancel.
    // We listen to both because pagehide is the modern standard but some browsers/contexts
    // still rely on unload.
    $(window).on('pagehide unload', function () {
        if (!anyRunning) return;
        sendBeaconCancel();
    });

    function sendBeaconCancel() {
        // sendBeacon is fire-and-forget — works during unload where regular AJAX fails.
        if (!navigator.sendBeacon || !currentRunningState) return;
        var fd = new FormData();
        fd.append('action', 'site_builder_cancel');
        fd.append('nonce', SiteBuilderData.nonce);
        fd.append('import_id', currentRunningState.importId);
        try { navigator.sendBeacon(SiteBuilderData.ajaxUrl, fd); } catch (err) {}
    }

    // Pointer to the running state object (only ever one importer runs at a time)
    var currentRunningState = null;

    // ---- Clear-lock button (shown only when activeImport exists) ----
    $('#sb-clear-lock-btn').on('click', function () {
        var $btn = $(this);
        if (!confirm('Прервать активный импорт и сбросить блокировку?')) return;
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-no-alt').addClass('dashicons-update');
        $.post(SiteBuilderData.ajaxUrl, {
            action: 'site_builder_clear_lock',
            nonce: SiteBuilderData.nonce
        }).done(function () {
            // Reload so the active-import card disappears and forms unlock.
            anyRunning = false; // suppress beforeunload from this handler's reload
            location.reload();
        }).fail(function () {
            alert('Не удалось сбросить блокировку. Попробуйте ещё раз или обновите страницу через 5 минут (lock протухнет автоматически).');
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-no-alt');
        });
    });

    // ---- "Copy as table" buttons on the Report tab ----
    // Builds TSV (tab-separated) text, which Excel and Google Sheets paste correctly
    // as a proper grid. Falls back to plain selection if clipboard API is unavailable.
    $('.sb-copy-table').on('click', function () {
        var $btn = $(this);
        var tableId = $btn.data('target');
        var $table = $('#' + tableId);
        if (!$table.length) return;

        var tsv = [];
        $table.find('tr').each(function () {
            var row = [];
            $(this).find('th, td').each(function () {
                // Strip action-button cells (just icons)
                var $cell = $(this).clone();
                $cell.find('.button').remove();
                var text = $cell.text().trim().replace(/\s+/g, ' ');
                row.push(text);
            });
            // Skip empty rows (the action column collapsing)
            if (row.some(function (c) { return c !== ''; })) {
                tsv.push(row.join('\t'));
            }
        });

        var text = tsv.join('\n');
        var originalText = $btn.html();

        function flashOk() {
            $btn.html('<span class="dashicons dashicons-yes"></span> Скопировано');
            setTimeout(function () { $btn.html(originalText); }, 1500);
        }
        function flashFail() {
            $btn.html('<span class="dashicons dashicons-no"></span> Ошибка');
            setTimeout(function () { $btn.html(originalText); }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(flashOk).catch(flashFail);
        } else {
            // Fallback: temporary textarea + execCommand('copy')
            var $tmp = $('<textarea></textarea>')
                .val(text)
                .css({ position: 'fixed', top: 0, left: 0, opacity: 0 })
                .appendTo('body');
            $tmp[0].select();
            try {
                document.execCommand('copy');
                flashOk();
            } catch (e) {
                flashFail();
            }
            $tmp.remove();
        }
    });

    /**
     * Generic importer wiring. Each tab (create, add) instantiates this with its own
     * element selectors and AJAX action name.
     *
     * Required cfg fields:
     *   ids: { startBtn, cancelBtn, formCard, progressCard, progressBar, progressCount,
     *          progressTotal, progressLabel, progressTitle, resultCard, resultTitle,
     *          resultMessage, scheduleMode, daysRow, immediateRow, waitWeekRow,
     *          folder, days, immediate, waitWeek }
     *   action: AJAX action name for start (e.g. 'site_builder_create_start')
     *   confirmation: { input, status, formCard, keyword } | null
     */
    function wireImporter(cfg) {
        var $startBtn = $('#' + cfg.ids.startBtn);
        if (!$startBtn.length) return;

        var $cancelBtn      = $('#' + cfg.ids.cancelBtn);
        var $progressCard   = $('#' + cfg.ids.progressCard);
        var $resultCard     = $('#' + cfg.ids.resultCard);
        var $progressBar    = $('#' + cfg.ids.progressBar);
        var $progressCount  = $('#' + cfg.ids.progressCount);
        var $progressTotal  = $('#' + cfg.ids.progressTotal);
        var $progressLabel  = $('#' + cfg.ids.progressLabel);
        var $progressTitle  = $('#' + cfg.ids.progressTitle);
        var $resultTitle    = $('#' + cfg.ids.resultTitle);
        var $resultMessage  = $('#' + cfg.ids.resultMessage);
        var $scheduleMode   = $('#' + cfg.ids.scheduleMode);
        var $daysRow        = $('#' + cfg.ids.daysRow);
        var $immediateRow   = $('#' + cfg.ids.immediateRow);
        var $waitWeekRow    = $('#' + cfg.ids.waitWeekRow);

        var state = { running: false, cancelled: false, importId: 0, offset: 0, total: 0 };

        function updateScheduleVisibility() {
            var mode = $scheduleMode.val();
            if (mode === 'period') { $daysRow.show(); } else { $daysRow.hide(); }
            if (mode === 'instant') {
                $immediateRow.hide();
                $waitWeekRow.hide();
            } else {
                $immediateRow.show();
                $waitWeekRow.show();
            }
        }
        $scheduleMode.on('change', updateScheduleVisibility);
        updateScheduleVisibility();

        // Optional: УДАЛИТЬ confirmation (only for CREATE when site has pages)
        if (cfg.confirmation) {
            var $confirmInput  = $('#' + cfg.confirmation.input);
            var $confirmStatus = $('#' + cfg.confirmation.status);
            var $formCard      = $('#' + cfg.confirmation.formCard);
            var keyword        = cfg.confirmation.keyword;

            function updateStartButtonState() {
                if (!$confirmInput.length) {
                    $startBtn.prop('disabled', false);
                    return;
                }
                var confirmed = $confirmInput.val() === keyword;
                $startBtn.prop('disabled', !confirmed);
                if ($confirmInput.val() === '') {
                    $confirmStatus.text('').removeClass('sb-ok sb-fail');
                } else if (confirmed) {
                    $confirmStatus.text('подтверждено').removeClass('sb-fail').addClass('sb-ok');
                    $formCard.removeAttr('data-locked');
                } else {
                    $confirmStatus.text(SiteBuilderData.strings.wipeMismatch).removeClass('sb-ok').addClass('sb-fail');
                }
            }
            if ($confirmInput.length) {
                $confirmInput.on('input', function () {
                    if ($confirmInput.val() === keyword) {
                        $formCard.removeAttr('data-locked');
                    } else {
                        $formCard.attr('data-locked', '1');
                    }
                    updateStartButtonState();
                });
                updateStartButtonState();
            } else {
                // No confirmation field on the page after all — enable the button.
                $startBtn.prop('disabled', false);
            }
        } else {
            // No confirmation required — ensure the button is enabled regardless of its
            // initial HTML state. (CREATE button is `disabled` by default to defend against
            // a flash of clickable state before JS runs.)
            $startBtn.prop('disabled', false);
        }

        function updateProgress(processed, total, label) {
            var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
            $progressBar.css('width', pct + '%');
            $progressCount.text(processed);
            $progressTotal.text(total);
            if (label) $progressLabel.text(label);
        }

        function showResult(status, message) {
            $progressCard.hide();
            $resultCard.removeClass('sb-result-failed sb-result-cancelled');
            if (status === 'completed') {
                $resultTitle.text(SiteBuilderData.strings.completed);
            } else if (status === 'cancelled') {
                $resultTitle.text(SiteBuilderData.strings.cancelled);
                $resultCard.addClass('sb-result-cancelled');
            } else {
                $resultTitle.text(SiteBuilderData.strings.failed);
                $resultCard.addClass('sb-result-failed');
            }
            $resultMessage.html(''); // clear
            if (message) {
                $resultMessage.append(document.createTextNode(message + ' '));
            }
            // For any non-failure terminal status, link to the Report tab. (Failures are still
            // worth investigating, so we show it for them too.)
            if (status !== 'cancelled') {
                var reportUrl = SiteBuilderData.reportUrl;
                var link = $('<a></a>')
                    .attr('href', reportUrl)
                    .text('Посмотреть отчёт →');
                $resultMessage.append(link);
            }
            $resultCard.show();
            $cancelBtn.hide();
            $startBtn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-controls-play');
            state.running = false;
            anyRunning = false;
            currentRunningState = null;
        }

        function processBatch() {
            if (state.cancelled) return;
            $.ajax({
                url: SiteBuilderData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'site_builder_process_batch',
                    nonce: SiteBuilderData.nonce,
                    import_id: state.importId
                    // P0 — no offset sent. Server reads it from DB.
                },
                // AJAX-level timeout — jQuery without this waits FOREVER for
                // a hung PHP request, and .fail() never fires. 90 seconds is
                // just above the typical nginx proxy_read_timeout (60s), so
                // we normally get the nginx 504 first; but if nginx itself is
                // unusually patient, we cut the wire ourselves.
                timeout: 90000
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.data && resp.data.message) || SiteBuilderData.strings.genericError;
                    showResult('failed', msg);
                    return;
                }
                var data = resp.data;
                state.total = data.total || state.total;
                state.processed = data.processed || 0;
                state.retriesUsed = 0; // reset on success
                updateProgress(data.processed, state.total, data.current_label || '');
                if (data.done) {
                    showResult('completed', 'Обработано задач: ' + data.processed + ' из ' + state.total);
                    return;
                }
                setTimeout(processBatch, data.time_boxed ? 50 : 100);
            }).fail(function (xhr, textStatus) {
                handleBatchFailure(xhr, textStatus, state, processBatch);
            });
        }

        $startBtn.on('click', function () {
            if (state.running) return;
            $resultCard.hide();
            $progressCard.show();
            $progressTitle.text(SiteBuilderData.strings.starting);
            $startBtn.prop('disabled', true).find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update');
            $cancelBtn.show();

            var payload = {
                action: cfg.action,
                nonce: SiteBuilderData.nonce,
                folder: $('#' + cfg.ids.folder).val(),
                schedule_mode: $scheduleMode.val(),
                days: $('#' + cfg.ids.days).val(),
                immediate_count: $('#' + cfg.ids.immediate).val(),
                wait_week: $('#' + cfg.ids.waitWeek).is(':checked') ? 1 : 0
            };
            if (cfg.confirmation) {
                payload.confirmation = $('#' + cfg.confirmation.input).val() || '';
            }
            // Mode is set by FSR's two-button UI just before triggering the click;
            // we read it back here so it travels with the start payload.
            if (typeof cfg.payloadExtras === 'function') {
                var extras = cfg.payloadExtras() || {};
                Object.keys(extras).forEach(function (k) { payload[k] = extras[k]; });
            }

            $.post(SiteBuilderData.ajaxUrl, payload).done(function (resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.data && resp.data.message) || SiteBuilderData.strings.genericError;
                    showResult('failed', msg);
                    return;
                }
                state.running = true;
                state.cancelled = false;
                state.importId = resp.data.import_id;
                state.offset = 0;
                state.total = resp.data.total || 0;
                state.processed = 0;
                state.retriesUsed = 0;
                // Give handleBatchFailure access to the same progress/result
                // helpers the wireImporter closure uses.
                state.updateProgressFn = updateProgress;
                state.showResultFn = showResult;
                anyRunning = true;
                currentRunningState = state;
                $progressTitle.text(SiteBuilderData.strings.inProgress);
                updateProgress(0, state.total, '');
                processBatch();
            }).fail(function (xhr) {
                showResult('failed', 'Сетевая ошибка при запуске: HTTP ' + xhr.status);
            });
        });

        $cancelBtn.on('click', function () {
            if (!state.running) return;
            if (!confirm(SiteBuilderData.strings.confirmCancel)) return;
            state.cancelled = true;
            $.post(SiteBuilderData.ajaxUrl, {
                action: 'site_builder_cancel',
                nonce: SiteBuilderData.nonce,
                import_id: state.importId
            }).always(function () {
                showResult('cancelled', 'Импорт прерван пользователем. Уже созданные страницы остаются — используйте «Откат» для отмены.');
            });
        });

        // If the server reports an active import from elsewhere (another tab, a previous
        // session that was abandoned), disable this form's start button.
        if (SiteBuilderData.activeImport) {
            $startBtn.prop('disabled', true)
                     .attr('title', SiteBuilderData.strings.lockBlocked);
        }
    }

    // === CREATE tab ===
    wireImporter({
        action: 'site_builder_create_start',
        ids: {
            startBtn:       'sb-start-btn',
            cancelBtn:      'sb-cancel-btn',
            progressCard:   'sb-progress-card',
            progressBar:    'sb-progress-bar',
            progressCount:  'sb-progress-count',
            progressTotal:  'sb-progress-total',
            progressLabel:  'sb-progress-current-label',
            progressTitle:  'sb-progress-title',
            resultCard:     'sb-result-card',
            resultTitle:    'sb-result-title',
            resultMessage:  'sb-result-message',
            scheduleMode:   'sb-schedule-mode',
            daysRow:        'sb-days-row',
            immediateRow:   'sb-immediate-count-row',
            waitWeekRow:    'sb-wait-week-row',
            folder:         'sb-folder',
            days:           'sb-days',
            immediate:      'sb-immediate-count',
            waitWeek:       'sb-wait-week'
        },
        confirmation: (SiteBuilderData.existingPages > 0) ? {
            input:    'sb-confirmation',
            status:   'sb-confirm-status',
            formCard: 'sb-create-form-card',
            keyword:  SiteBuilderData.wipeKeyword
        } : null
    });

    // === ADD tab ===
    wireImporter({
        action: 'site_builder_add_start',
        ids: {
            startBtn:       'sb-add-start-btn',
            cancelBtn:      'sb-add-cancel-btn',
            progressCard:   'sb-add-progress-card',
            progressBar:    'sb-add-progress-bar',
            progressCount:  'sb-add-progress-count',
            progressTotal:  'sb-add-progress-total',
            progressLabel:  'sb-add-progress-current-label',
            progressTitle:  'sb-add-progress-title',
            resultCard:     'sb-add-result-card',
            resultTitle:    'sb-add-result-title',
            resultMessage:  'sb-add-result-message',
            scheduleMode:   'sb-add-schedule-mode',
            daysRow:        'sb-add-days-row',
            immediateRow:   'sb-add-immediate-count-row',
            waitWeekRow:    'sb-add-wait-week-row',
            folder:         'sb-add-folder',
            days:           'sb-add-days',
            immediate:      'sb-add-immediate-count',
            waitWeek:       'sb-add-wait-week'
        },
        confirmation: null
    });

    // === MD RESTORE tab ===
    // Uses the same wireImporter as CREATE/ADD. The schedule-related field IDs point
    // to elements that don't exist on this tab — jQuery treats those gracefully and
    // the backend (md_start) ignores the schedule parameters entirely.
    wireImporter({
        action: 'site_builder_md_start',
        ids: {
            startBtn:       'sb-md-start-btn',
            cancelBtn:      'sb-md-cancel-btn',
            progressCard:   'sb-md-progress-card',
            progressBar:    'sb-md-progress-bar',
            progressCount:  'sb-md-progress-count',
            progressTotal:  'sb-md-progress-total',
            progressLabel:  'sb-md-progress-current-label',
            progressTitle:  'sb-md-progress-title',
            resultCard:     'sb-md-result-card',
            resultTitle:    'sb-md-result-title',
            resultMessage:  'sb-md-result-message',
            scheduleMode:   'sb-md-no-such-id',
            daysRow:        'sb-md-no-such-id',
            immediateRow:   'sb-md-no-such-id',
            waitWeekRow:    'sb-md-no-such-id',
            folder:         'sb-md-folder',
            days:           'sb-md-no-such-id',
            immediate:      'sb-md-no-such-id',
            waitWeek:       'sb-md-no-such-id'
        },
        confirmation: null
    });

    // === FSR Import tab ===
    // FSR is the canonical archive format for v1.0.0 (Next.js-like file system routing).
    // The tab has two stages: (1) field mapping (sets up which meta_keys SEO data
    // goes into), (2) import (folder name → Start). DOM-switching between them.

    (function () {
        var $grid = $('#sb-fsr-mapping-grid');

        // If we're on the FSR tab (has #sb-fsr-action-block but no mapping grid),
        // just render the action block right away — mapping has moved to Settings.
        if ($('#sb-fsr-action-block').length && typeof renderActionBlock === 'function') {
            renderActionBlock();
        }

        if (!$grid.length) return; // mapping grid not on this page

        var $showPrivate    = $('#sb-fsr-show-private');
        var $saveBtn        = $('#sb-fsr-save-mapping-btn');
        var $saveStatus     = $('#sb-fsr-mapping-save-status');

        var allKeys = [];
        var slots = [];

        // Renders the action block (start buttons) at the bottom of step 2.
        // On an empty site → single "Создать сайт" button.
        // On a site with existing pages → two buttons (recreate + add) plus a
        // confirmation input for the destructive option.
        // The currently-chosen mode is stored on a hidden input that the
        // wireImporter's payloadExtras reads at submit time.
        function renderActionBlock() {
            var $block = $('#sb-fsr-action-block');
            if (!$block.length) return;

            var existing = (SiteBuilderData && SiteBuilderData.existingPages) || 0;

            var html = '<input type="hidden" id="sb-fsr-mode" value="create">';

            if (existing > 0) {
                html += '<div class="notice notice-warning inline" style="margin:16px 0;">';
                html +=   '<p><strong>На сайте уже есть страницы (' + existing + ' шт.).</strong> Выберите режим импорта:</p>';
                html += '</div>';

                // RECREATE — destructive, needs confirmation
                html += '<div class="sb-fsr-action-card">';
                html +=   '<h3>Создать сайт заново</h3>';
                html +=   '<p>Все существующие страницы будут <strong>удалены</strong>, меню пересозданы, лого/иконка/стили заменены на новые. Это полная переустановка.</p>';
                html +=   '<p>';
                html +=     '<label>Введите <code>УДАЛИТЬ</code> для подтверждения: ';
                html +=     '<input type="text" id="sb-fsr-confirmation" placeholder="УДАЛИТЬ" autocomplete="off" style="width:160px">';
                html +=     '</label>';
                html +=   '</p>';
                html +=   '<button type="button" class="button button-primary button-large sb-fsr-action-btn" data-mode="create" disabled>';
                html +=     '<span class="dashicons dashicons-controls-play"></span> Создать сайт заново';
                html +=   '</button>';
                html += '</div>';

                // ADD — safe, no confirmation
                html += '<div class="sb-fsr-action-card">';
                html +=   '<h3>Добавить страницы к существующему сайту</h3>';
                html +=   '<p>Импортируются только <strong>новые</strong> страницы (с уникальным slug + parent). Существующие — пропускаются. Меню расширяются, лого/иконка/стили <strong>не меняются</strong>.</p>';
                html +=   '<button type="button" class="button button-primary button-large sb-fsr-action-btn" data-mode="add">';
                html +=     '<span class="dashicons dashicons-plus-alt2"></span> Добавить страницы';
                html +=   '</button>';
                html += '</div>';
            } else {
                html += '<div class="sb-fsr-action-card">';
                html +=   '<p>На сайте пока нет страниц — будет создан новый сайт из архива.</p>';
                html +=   '<button type="button" class="button button-primary button-large sb-fsr-action-btn" data-mode="create">';
                html +=     '<span class="dashicons dashicons-controls-play"></span> Создать сайт';
                html +=   '</button>';
                html += '</div>';
            }

            // Note: the hidden #sb-fsr-start-btn and #sb-fsr-cancel-btn buttons
            // live in the static HTML (tab-fsr.php) so wireImporter's click
            // handlers are attached at page load. Don't recreate them here.

            $block.html(html);

            // Enable Create button only when confirmation matches keyword
            var keyword = (SiteBuilderData && SiteBuilderData.wipeKeyword) || 'УДАЛИТЬ';
            var $conf = $('#sb-fsr-confirmation');
            if ($conf.length) {
                $conf.on('input', function () {
                    var ok = ($(this).val() || '').trim() === keyword;
                    $('.sb-fsr-action-btn[data-mode="create"]').prop('disabled', !ok);
                });
            }

            // Visible buttons → set mode, copy confirmation into the input the
            // backend expects, then trigger the (hidden) wireImporter start button.
            $('.sb-fsr-action-btn').on('click', function () {
                var mode = $(this).data('mode') || 'create';
                $('#sb-fsr-mode').val(mode);
                $('#sb-fsr-start-btn').trigger('click');
            });
        }

        function escHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function renderGrid(payload) {
            slots = payload.slots || [];

            if (slots.length === 0) {
                $grid.html('<p class="sb-fsr-empty-list">Конфигурация слотов не загружена.</p>');
                $saveBtn.prop('disabled', true);
                return;
            }

            var html = '';
            slots.forEach(function (slot) {
                var selectedSet = {};
                (slot.selected || []).forEach(function (k) { selectedSet[k] = true; });

                html += '<div class="sb-fsr-mapping-row" data-slot="' + escHtml(slot.slot) + '">';
                html +=   '<div class="sb-fsr-fixed-field">';
                html +=     '<strong>' + escHtml(slot.label) + '</strong>';
                html +=     '<p class="description">' + slot.hint + '</p>';
                html +=     '<span class="sb-fsr-selected-count"></span>';
                html +=   '</div>';
                html +=   '<div class="sb-fsr-field-picker">';
                html +=     '<input type="text" class="sb-fsr-filter-input" placeholder="Фильтр по имени поля…">';
                html +=     '<div class="sb-fsr-keys-list">';

                (slot.options || []).forEach(function (opt) {
                    var key        = opt.key;
                    var isPrivate  = key.charAt(0) === '_';
                    var isSelected = !!selectedSet[key];
                    var hide       = (isPrivate && !isSelected);

                    var cls = 'sb-fsr-key-item';
                    if (isPrivate) cls += ' sb-fsr-private';

                    var badge = '';
                    if (opt.in_db) {
                        badge = '<span class="sb-fsr-key-badge sb-fsr-badge-existing" title="Поле уже есть в базе данных">есть в БД</span>';
                    } else if (opt.recommended) {
                        badge = '<span class="sb-fsr-key-badge sb-fsr-badge-known" title="Поле популярного SEO-плагина — будет создано при сохранении">будет создано</span>';
                    } else {
                        badge = '<span class="sb-fsr-key-badge sb-fsr-badge-custom" title="Пользовательский ключ">свой</span>';
                    }

                    html += '<label class="' + cls + '"' + (hide ? ' style="display:none"' : '') + '>';
                    html +=   '<input type="checkbox" data-slot="' + escHtml(slot.slot) + '"';
                    html +=          ' value="' + escHtml(key) + '"';
                    html +=          (isSelected ? ' checked' : '') + '>';
                    html +=   '<code>' + escHtml(key) + '</code>';
                    html +=   badge;
                    html += '</label>';
                });

                html +=     '</div>';
                html +=     '<div class="sb-fsr-custom-key">';
                html +=       '<input type="text" class="sb-fsr-custom-key-input"';
                html +=              ' placeholder="Свой meta_key — например, _custom_seo_title" maxlength="255">';
                html +=       '<button type="button" class="button sb-fsr-custom-key-add">Добавить</button>';
                html +=     '</div>';
                html +=   '</div>';
                html += '</div>';
            });

            $grid.html(html);
            wireGridInteractions();
            updateSummary();
        }

        function wireGridInteractions() {
            $grid.find('.sb-fsr-mapping-row').each(function () {
                var $row = $(this);
                var $filter = $row.find('.sb-fsr-filter-input');
                var $count = $row.find('.sb-fsr-selected-count');
                var slotName = $row.data('slot');

                function updateCount() {
                    var n = $row.find('input[type=checkbox]:checked').length;
                    $count.text(n > 0 ? 'Выбрано: ' + n : 'Не выбрано');
                }
                updateCount();

                $row.on('change', 'input[type=checkbox]', function () {
                    updateCount();
                    updateSummary();
                });

                $filter.on('input', function () {
                    applyFilters($row);
                });

                // Add custom key: validate, prepend to keys list, check it
                $row.find('.sb-fsr-custom-key-add').on('click', function () {
                    var $input = $row.find('.sb-fsr-custom-key-input');
                    var key = ($input.val() || '').trim();
                    if (!/^[A-Za-z0-9_:\-]{1,255}$/.test(key)) {
                        $input.css('border-color', '#b32d2e').focus();
                        return;
                    }
                    $input.css('border-color', '');
                    // Skip if already in the list
                    var $existing = $row.find('.sb-fsr-keys-list input[value="' + key.replace(/"/g, '\\"') + '"]');
                    if ($existing.length) {
                        $existing.prop('checked', true).trigger('change');
                        $input.val('');
                        return;
                    }
                    var isPrivate = key.charAt(0) === '_';
                    var cls = 'sb-fsr-key-item' + (isPrivate ? ' sb-fsr-private' : '');
                    var html = '<label class="' + cls + '">';
                    html +=     '<input type="checkbox" data-slot="' + escHtml(slotName) + '"';
                    html +=            ' value="' + escHtml(key) + '" checked>';
                    html +=     '<code>' + escHtml(key) + '</code>';
                    html +=     '<span class="sb-fsr-key-badge sb-fsr-badge-custom" title="Пользовательский ключ">свой</span>';
                    html += '</label>';
                    $row.find('.sb-fsr-keys-list').prepend(html);
                    $input.val('');
                    updateCount();
                    updateSummary();
                });

                // Allow Enter in the input to trigger Add
                $row.find('.sb-fsr-custom-key-input').on('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $row.find('.sb-fsr-custom-key-add').trigger('click');
                    }
                });
            });
        }

        function applyFilters($row) {
            var $filter = $row.find('.sb-fsr-filter-input');
            var q = ($filter.val() || '').toLowerCase().trim();
            var showPriv = $showPrivate.is(':checked');
            $row.find('.sb-fsr-key-item').each(function () {
                var $item = $(this);
                var text = $item.find('code').text().toLowerCase();
                var isPrivate = $item.hasClass('sb-fsr-private');
                var isChecked = $item.find('input').is(':checked');
                var matches = !q || text.indexOf(q) !== -1;
                if (matches && (!isPrivate || showPriv || isChecked)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        }

        function updateSummary() {
            var total = $grid.find('input[type=checkbox]:checked').length;
            $summary.text(total > 0 ? 'Маппинг сохранён: ' + total + ' полей выбрано' : '');
        }

        $showPrivate.on('change', function () {
            $grid.find('.sb-fsr-mapping-row').each(function () {
                applyFilters($(this));
            });
        });

        $saveBtn.on('click', function () {
            $saveStatus.removeClass('sb-fsr-error').text('Сохранение…');
            $saveBtn.prop('disabled', true);

            var mapping = {};
            $grid.find('input[type=checkbox]:checked').each(function () {
                var slot = $(this).data('slot');
                var key = $(this).val();
                if (!mapping[slot]) mapping[slot] = [];
                mapping[slot].push(key);
            });

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'site_builder_fsr_save_mapping',
                    nonce: SiteBuilderData.nonce,
                    mapping: mapping
                }
            }).done(function (resp) {
                $saveBtn.prop('disabled', false);
                if (resp && resp.success) {
                    $saveStatus.text('✓ Сохранено').removeClass('sb-fsr-error');
                } else {
                    $saveStatus.addClass('sb-fsr-error')
                        .text((resp && resp.data && resp.data.message) || 'Не удалось сохранить');
                }
            }).fail(function () {
                $saveBtn.prop('disabled', false);
                $saveStatus.addClass('sb-fsr-error').text('Ошибка сети — попробуйте ещё раз');
            });
        });

        // Initial load of the mapping
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'site_builder_fsr_get_mapping',
                nonce: SiteBuilderData.nonce
            }
        }).done(function (resp) {
            if (resp && resp.success && resp.data) {
                renderGrid(resp.data);
            } else {
                $grid.html('<p class="sb-fsr-empty-list">Не удалось загрузить список полей.</p>');
            }
        }).fail(function () {
            $grid.html('<p class="sb-fsr-empty-list">Ошибка сети при загрузке списка полей.</p>');
        });
    })();

    // Wire up the actual import button on step 2 (folder name → Start) using the
    // standard importer plumbing. FSR has a [DLY] schedule but no immediate-count.
    wireImporter({
        action: 'site_builder_fsr_start',
        ids: {
            startBtn:       'sb-fsr-start-btn',
            cancelBtn:      'sb-fsr-cancel-btn',
            progressCard:   'sb-fsr-progress-card',
            progressBar:    'sb-fsr-progress-bar',
            progressCount:  'sb-fsr-progress-count',
            progressTotal:  'sb-fsr-progress-total',
            progressLabel:  'sb-fsr-progress-current-label',
            progressTitle:  'sb-fsr-progress-title',
            resultCard:     'sb-fsr-result-card',
            resultTitle:    'sb-fsr-result-title',
            resultMessage:  'sb-fsr-result-message',
            scheduleMode:   'sb-fsr-schedule-mode',
            daysRow:        'sb-fsr-days-row',
            immediateRow:   'sb-fsr-no-such-id',
            waitWeekRow:    'sb-fsr-wait-week-row',
            folder:         'sb-fsr-folder',
            days:           'sb-fsr-days',
            immediate:      'sb-fsr-no-such-id',
            waitWeek:       'sb-fsr-wait-week'
        },
        confirmation: null,
        // Tell the backend which mode the user picked (create vs add) and pass
        // the confirmation token along when present. Both fields live in the
        // dynamically-rendered action block; we read them at submit time.
        payloadExtras: function () {
            return {
                mode:         $('#sb-fsr-mode').val() || 'create',
                confirmation: $('#sb-fsr-confirmation').val() || ''
            };
        }
    });

    // === ROLLBACK tab ===
    // Standalone wiring (no schedule, no form fields, no confirmation token —
    // just one button that fires the rollback_start AJAX and tracks progress).
    (function () {
        var $startBtn = $('#sb-rollback-start-btn');
        if (!$startBtn.length) return;

        var $progressCard  = $('#sb-rollback-progress-card');
        var $resultCard    = $('#sb-rollback-result-card');
        var $progressBar   = $('#sb-rollback-progress-bar');
        var $progressCount = $('#sb-rollback-progress-count');
        var $progressTotal = $('#sb-rollback-progress-total');
        var $progressLabel = $('#sb-rollback-progress-current-label');
        var $progressTitle = $('#sb-rollback-progress-title');
        var $resultTitle   = $('#sb-rollback-result-title');
        var $resultMessage = $('#sb-rollback-result-message');
        var $formCard      = $('#sb-rollback-form-card');

        var state = { running: false, importId: 0, offset: 0, total: 0 };

        if (SiteBuilderData.activeImport) {
            $startBtn.prop('disabled', true)
                     .attr('title', SiteBuilderData.strings.lockBlocked);
        }

        function updateProgress(processed, total, label) {
            var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
            $progressBar.css('width', pct + '%');
            $progressCount.text(processed);
            $progressTotal.text(total);
            if (label) $progressLabel.text(label);
        }

        function showResult(status, message) {
            $progressCard.hide();
            $resultCard.removeClass('sb-result-failed sb-result-cancelled');
            if (status === 'completed') {
                $resultTitle.text('Откат завершён');
            } else {
                $resultTitle.text('Откат упал с ошибкой');
                $resultCard.addClass('sb-result-failed');
            }
            $resultMessage.text(message || '');
            $resultCard.show();
            $formCard.hide();
            state.running = false;
            anyRunning = false;
            currentRunningState = null;
        }

        function processBatch() {
            $.post(SiteBuilderData.ajaxUrl, {
                action: 'site_builder_process_batch',
                nonce: SiteBuilderData.nonce,
                import_id: state.importId
                // No offset — server reads from DB (see P0 note above)
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.data && resp.data.message) || SiteBuilderData.strings.genericError;
                    showResult('failed', msg);
                    return;
                }
                var data = resp.data;
                state.total = data.total || state.total;
                updateProgress(data.processed, state.total, data.current_label || '');
                if (data.done) {
                    showResult('completed', 'Обработано задач: ' + data.processed + ' из ' + state.total);
                    return;
                }
                setTimeout(processBatch, data.time_boxed ? 50 : 100);
            }).fail(function (xhr) {
                var status = xhr.status;
                var isTransient = (status === 504 || status === 502 || status === 503 || status === 0);
                if (isTransient && !state.retriedThisBatch) {
                    state.retriedThisBatch = true;
                    setTimeout(function () {
                        state.retriedThisBatch = false;
                        processBatch();
                    }, 5000);
                    return;
                }
                showResult('failed', 'Сетевая ошибка: HTTP ' + status);
            });
        }

        $startBtn.on('click', function () {
            if (state.running) return;
            if (!confirm('Откатить последний импорт? Это удалит созданные плагином страницы и картинки, восстановит файлы темы. Действие необратимо.')) return;

            $resultCard.hide();
            $progressCard.show();
            $progressTitle.text('Откат запускается…');
            $startBtn.prop('disabled', true).find('.dashicons').removeClass('dashicons-undo').addClass('dashicons-update');

            $.post(SiteBuilderData.ajaxUrl, {
                action: 'site_builder_rollback_start',
                nonce: SiteBuilderData.nonce
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.data && resp.data.message) || SiteBuilderData.strings.genericError;
                    showResult('failed', msg);
                    return;
                }
                state.running = true;
                state.importId = resp.data.import_id;
                state.offset = 0;
                state.total = resp.data.total || 0;
                anyRunning = true;
                currentRunningState = state;
                $progressTitle.text('Откат идёт');
                updateProgress(0, state.total, '');
                processBatch();
            }).fail(function (xhr) {
                showResult('failed', 'Сетевая ошибка при запуске: HTTP ' + xhr.status);
            });
        });
    })();

    // === Theme tab — variant constructor ===
    (function () {
        var $buildBtn = $('#sb-theme-build-btn');
        if (!$buildBtn.length) return; // not on theme tab

        var $status = $('#sb-theme-build-status');

        // Highlight selected variant card on radio change for visual feedback
        $('.sb-theme-variant input[type=radio]').on('change', function () {
            var name = $(this).attr('name');
            $('input[name="' + name + '"]').each(function () {
                $(this).closest('.sb-theme-variant').toggleClass('sb-theme-variant-selected', $(this).is(':checked'));
            });
        });

        // Horizontal scroller: arrow buttons + disable when at edge
        $('.sb-theme-scroller').each(function () {
            var $scroller = $(this);
            var $row = $scroller.find('.sb-theme-variants-row');
            var $left = $scroller.find('.sb-theme-scroll-left');
            var $right = $scroller.find('.sb-theme-scroll-right');
            var updateArrows = function () {
                var el = $row[0];
                if (!el) return;
                $left.prop('disabled', el.scrollLeft <= 4);
                $right.prop('disabled', el.scrollLeft + el.clientWidth >= el.scrollWidth - 4);
                // Hide entirely if content fits
                var fits = el.scrollWidth <= el.clientWidth + 4;
                $left.toggle(!fits);
                $right.toggle(!fits);
            };
            $left.on('click', function () { $row[0].scrollBy({ left: -260, behavior: 'smooth' }); });
            $right.on('click', function () { $row[0].scrollBy({ left: 260, behavior: 'smooth' }); });
            $row.on('scroll', updateArrows);
            $(window).on('resize', updateArrows);
            updateArrows();
            // Scroll selected variant into view initially
            var $selected = $scroller.find('.sb-theme-variant-selected');
            if ($selected.length) {
                var offset = $selected[0].offsetLeft - $row[0].clientWidth / 2 + $selected[0].clientWidth / 2;
                $row[0].scrollLeft = Math.max(0, offset);
                setTimeout(updateArrows, 50);
            }
        });

        $buildBtn.on('click', function () {
            $status.removeClass('sb-theme-error sb-theme-success').text('Генерация…');
            $buildBtn.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'site_builder_theme_build',
                    nonce: SiteBuilderData.nonce,
                    header: $('input[name="sb-theme-header"]:checked').val() || '',
                    footer: $('input[name="sb-theme-footer"]:checked').val() || '',
                    style:  $('input[name="sb-theme-style"]:checked').val() || '',
                    // Random-choice flags per category. When on, backend ignores
                    // the header/footer/style values above and picks randomly
                    // from list_variants(). The user's selection is still sent
                    // as the fallback for when the box is off.
                    random_header: $('.sb-theme-random-checkbox[data-key="header"]').is(':checked') ? 1 : 0,
                    random_footer: $('.sb-theme-random-checkbox[data-key="footer"]').is(':checked') ? 1 : 0,
                    random_style:  $('.sb-theme-random-checkbox[data-key="style"]').is(':checked')  ? 1 : 0
                }
            }).done(function (resp) {
                $buildBtn.prop('disabled', false);
                if (resp && resp.success) {
                    $status.addClass('sb-theme-success').text('✓ ' + (resp.data.message || 'Тема готова'));
                    // Reload the page after a short pause so the "active" badge updates
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    var msg = (resp && resp.data && resp.data.message) || 'Не удалось сгенерировать тему';
                    $status.addClass('sb-theme-error').text('✗ ' + msg);
                }
            }).fail(function () {
                $buildBtn.prop('disabled', false);
                $status.addClass('sb-theme-error').text('✗ Ошибка сети');
            });
        });

        // Persist the random-choice checkboxes on change so state survives page
        // reload. Save silently — no visible status, this is a low-stakes
        // preference and error would only manifest at next generation anyway.
        $('.sb-theme-random-checkbox').on('change', function () {
            var choices = {};
            $('.sb-theme-random-checkbox').each(function () {
                choices[$(this).data('key')] = $(this).is(':checked') ? 1 : 0;
            });
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'site_builder_theme_random_choices',
                    nonce: SiteBuilderData.nonce,
                    choices: choices
                }
            });
        });

        // "Выбрать случайные варианты" shortcut — check all three random
        // boxes, fire their change event so state persists, then smooth-scroll
        // to the build button so the user's next tap is on it.
        $('#sb-theme-randomize-all-btn').on('click', function () {
            $('.sb-theme-random-checkbox').prop('checked', true).trigger('change');
            var $target = $('#sb-theme-build-btn');
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 80
                }, 400);
            }
        });

        // Instant save for module option checkboxes (breadcrumbs etc). No theme
        // regeneration needed — modules read the option from DB on each render.
        $('.sb-theme-module-option').on('change', function () {
            var $cb = $(this);
            var key = $cb.data('option-key');
            var val = $cb.is(':checked');
            var $st = $cb.closest('td').find('.sb-theme-opt-status');
            var options = {};
            options[key] = val ? 1 : 0;

            $st.text('Сохраняем…').css('color', '');
            $cb.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'site_builder_theme_module_options',
                    nonce: SiteBuilderData.nonce,
                    options: options
                }
            }).done(function (resp) {
                $cb.prop('disabled', false);
                if (resp && resp.success) {
                    $st.text('✓ Сохранено').css('color', '#00a32a');
                    setTimeout(function () { $st.text(''); }, 2000);
                } else {
                    $st.text('✗ Не удалось сохранить').css('color', '#d63638');
                }
            }).fail(function () {
                $cb.prop('disabled', false);
                $st.text('✗ Ошибка сети').css('color', '#d63638');
            });
        });
    })();

    // ============================================================
    // P0 — Resume-banner. On every page load, ask the server if there's
    // an import stranded in 'running' state (typically after 504 timeout
    // where PHP finished but the client tab closed). If so, show a banner
    // above the tab content with Continue/Cancel buttons.
    //
    // The banner sits above ALL admin tabs so the operator sees it
    // regardless of which tab they land on. Continue uses the same
    // processBatch loop the original start flow uses — but with the
    // server-side offset (processed_count in DB), not any client state.
    // ============================================================
    (function () {
        // Try multiple selectors — the plugin page wrapper class has varied
        // across versions. Also fall back to WordPress-standard #wpbody-content
        // so the banner ALWAYS appears somewhere visible even if wrapper class
        // is missing.
        var $wrap = $('.site-builder-wrap');
        if (!$wrap.length) $wrap = $('.wrap.site-builder-wrap');
        if (!$wrap.length) $wrap = $('.wrap:has(h1:contains("Site Builder"))').first();
        if (!$wrap.length) {
            console.log('[SiteBuilder] resume-banner: не найдена обёртка страницы плагина');
            return;
        }

        console.log('[SiteBuilder] resume-banner: проверяю активный импорт…');
        $.ajax({
            url: SiteBuilderData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'site_builder_check_active',
                nonce: SiteBuilderData.nonce
            },
            timeout: 15000
        }).done(function (resp) {
            console.log('[SiteBuilder] check_active response:', resp);
            if (!resp || !resp.success || !resp.data) {
                console.log('[SiteBuilder] check_active: некорректный ответ');
                return;
            }
            if (!resp.data.active) {
                console.log('[SiteBuilder] check_active: активных импортов нет');
                return;
            }
            console.log('[SiteBuilder] найден активный импорт, рендерю баннер:', resp.data.import);
            renderResumeBanner(resp.data.import);
        }).fail(function (xhr, textStatus) {
            console.log('[SiteBuilder] check_active failed:', xhr.status, textStatus);
        });

        function renderResumeBanner(imp) {
            // Hide the static PHP-side banner (if present) — we're replacing
            // it with our interactive version that has a Continue button.
            $wrap.find('.sb-active-import').hide();

            var pct = imp.total > 0 ? Math.round((imp.processed / imp.total) * 100) : 0;
            var typeLabel = ({
                'fsr':      'FSR-импорт',
                'add':      'Добавление страниц',
                'md_restore':'MD Restore',
                'create':   'Создание сайта',
                'rollback': 'Откат импорта'
            })[imp.type] || imp.type;

            var $banner = $(
                '<div class="sb-resume-banner">' +
                    '<div class="sb-resume-icon">↻</div>' +
                    '<div class="sb-resume-body">' +
                        '<div class="sb-resume-title">Прерванный импорт готов к продолжению</div>' +
                        '<div class="sb-resume-meta">' +
                            typeLabel + ' &middot; папка <code>' + esc(imp.folder_name) + '</code> &middot; ' +
                            'выполнено <strong>' + imp.processed + '</strong> из <strong>' + imp.total + '</strong> задач (' + pct + '%)' +
                        '</div>' +
                        '<div class="sb-resume-progress"><div class="sb-resume-progress-bar" style="width:' + pct + '%"></div></div>' +
                    '</div>' +
                    '<div class="sb-resume-actions">' +
                        '<button type="button" class="button button-primary sb-resume-continue">Продолжить</button>' +
                        '<button type="button" class="button sb-resume-cancel">Отменить</button>' +
                    '</div>' +
                '</div>'
            );
            $wrap.prepend($banner);

            $banner.find('.sb-resume-continue').on('click', function () {
                resumeImport(imp, $banner);
            });
            $banner.find('.sb-resume-cancel').on('click', function () {
                if (!confirm('Отменить прерванный импорт? Уже созданные страницы останутся, но продолжение будет невозможно.')) return;
                var $btn = $(this).prop('disabled', true).text('Отмена…');
                $.post(SiteBuilderData.ajaxUrl, {
                    action: 'site_builder_cancel',
                    nonce: SiteBuilderData.nonce,
                    import_id: imp.id
                }).always(function () {
                    $banner.slideUp(200, function () { $banner.remove(); });
                });
            });
        }

        function resumeImport(imp, $banner) {
            // Replace banner content with a live progress view — same look
            // as the normal in-progress import so the operator sees exactly
            // what's happening.
            $banner.find('.sb-resume-actions').remove();
            var $label = $('<div class="sb-resume-current"></div>');
            $banner.find('.sb-resume-body').append($label);
            var $bar = $banner.find('.sb-resume-progress-bar');
            var $meta = $banner.find('.sb-resume-meta');

            var state = {
                importId: imp.id,
                total: imp.total,
                processed: imp.processed,
                retriesUsed: 0,
                cancelled: false,
                // Wire helpers so handleBatchFailure can update this banner
                updateProgressFn: function (processed, total, label) {
                    var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
                    $bar.css('width', pct + '%');
                    $meta.html('Выполнено <strong>' + processed + '</strong> из <strong>' + total + '</strong> (' + pct + '%)');
                    $label.text(label || '');
                },
                showResultFn: function (kind, message) {
                    $banner.find('.sb-resume-title').text(
                        kind === 'completed' ? '✓ Импорт завершён' : 'Не удалось продолжить импорт'
                    );
                    if (kind === 'completed') $banner.addClass('sb-resume-done');
                    $label.text(message || '');
                }
            };

            function tick() {
                if (state.cancelled) return;
                $.ajax({
                    url: SiteBuilderData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'site_builder_process_batch',
                        nonce: SiteBuilderData.nonce,
                        import_id: state.importId
                    },
                    timeout: 90000
                }).done(function (resp) {
                    if (!resp || !resp.success) {
                        $banner.find('.sb-resume-title').text('Не удалось продолжить импорт');
                        return;
                    }
                    var d = resp.data;
                    state.processed = d.processed || 0;
                    state.total = d.total || state.total;
                    state.retriesUsed = 0;
                    state.updateProgressFn(d.processed, d.total, d.current_label || '');
                    if (d.done) {
                        state.showResultFn('completed', 'Обработано задач: ' + d.processed + ' из ' + d.total);
                        return;
                    }
                    setTimeout(tick, d.time_boxed ? 50 : 100);
                }).fail(function (xhr, textStatus) {
                    handleBatchFailure(xhr, textStatus, state, tick);
                });
            }
            tick();
        }

        function esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    })();

})(jQuery);
