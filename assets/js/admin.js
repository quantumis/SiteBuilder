(function ($) {
    'use strict';

    if (typeof SiteBuilderData === 'undefined') return;

    // ---- Module-level state for cross-cutting concerns ----
    // anyRunning is shared by both forms so navigation handlers can see "is any import live".
    var anyRunning = false;

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
            $resultMessage.text(message || '');
            $resultCard.show();
            $cancelBtn.hide();
            $startBtn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-controls-play');
            state.running = false;
            anyRunning = false;
            currentRunningState = null;
        }

        function processBatch() {
            if (state.cancelled) return;
            $.post(SiteBuilderData.ajaxUrl, {
                action: 'site_builder_process_batch',
                nonce: SiteBuilderData.nonce,
                import_id: state.importId,
                offset: state.offset
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.data && resp.data.message) || SiteBuilderData.strings.genericError;
                    showResult('failed', msg);
                    return;
                }
                var data = resp.data;
                state.offset = (typeof data.next_offset !== 'undefined') ? data.next_offset : (data.processed || state.offset);
                state.total = data.total || state.total;
                updateProgress(data.processed, state.total, data.current_label || '');
                if (data.done) {
                    showResult('completed', 'Обработано задач: ' + data.processed + ' из ' + state.total);
                    return;
                }
                setTimeout(processBatch, 100);
            }).fail(function (xhr) {
                showResult('failed', 'Сетевая ошибка: HTTP ' + xhr.status);
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

})(jQuery);
