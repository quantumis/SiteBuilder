(function ($) {
    'use strict';

    if (typeof SiteBuilderData === 'undefined') return;

    var $confirmInput = $('#sb-confirmation');
    var $confirmStatus = $('#sb-confirm-status');
    var $startBtn = $('#sb-start-btn');
    var $cancelBtn = $('#sb-cancel-btn');
    var $formCard = $('#sb-create-form-card');
    var $progressCard = $('#sb-progress-card');
    var $resultCard = $('#sb-result-card');
    var $progressBar = $('#sb-progress-bar');
    var $progressCount = $('#sb-progress-count');
    var $progressTotal = $('#sb-progress-total');
    var $progressLabel = $('#sb-progress-current-label');
    var $progressTitle = $('#sb-progress-title');
    var $scheduleMode = $('#sb-schedule-mode');
    var $daysRow = $('#sb-days-row');
    var $immediateRow = $('#sb-immediate-count-row');
    var $resultTitle = $('#sb-result-title');
    var $resultMessage = $('#sb-result-message');

    var state = {
        running: false,
        cancelled: false,
        importId: 0,
        offset: 0,
        total: 0
    };

    function updateScheduleVisibility() {
        var mode = $scheduleMode.val();
        if (mode === 'period') {
            $daysRow.show();
        } else {
            $daysRow.hide();
        }
        if (mode === 'instant') {
            $immediateRow.hide();
        } else {
            $immediateRow.show();
        }
    }
    $scheduleMode.on('change', updateScheduleVisibility);
    updateScheduleVisibility();

    function updateStartButtonState() {
        var hasWarning = SiteBuilderData.existingPages > 0;
        if (!hasWarning) {
            $startBtn.prop('disabled', false);
            return;
        }
        var confirmed = $confirmInput.val() === SiteBuilderData.wipeKeyword;
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
    $confirmInput.on('input', function () {
        if ($confirmInput.val() === SiteBuilderData.wipeKeyword) {
            $formCard.removeAttr('data-locked');
        } else {
            $formCard.attr('data-locked', '1');
        }
        updateStartButtonState();
    });
    updateStartButtonState();

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
            var msg = 'Сетевая ошибка: HTTP ' + xhr.status;
            showResult('failed', msg);
        });
    }

    function updateProgress(processed, total, label) {
        var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
        $progressBar.css('width', pct + '%');
        $progressCount.text(processed);
        $progressTotal.text(total);
        if (label) $progressLabel.text(label);
    }

    $startBtn.on('click', function () {
        if (state.running) return;
        $resultCard.hide();
        $progressCard.show();
        $progressTitle.text(SiteBuilderData.strings.starting);
        $startBtn.prop('disabled', true).find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update');
        $cancelBtn.show();

        var payload = {
            action: 'site_builder_create_start',
            nonce: SiteBuilderData.nonce,
            folder: $('#sb-folder').val(),
            schedule_mode: $scheduleMode.val(),
            days: $('#sb-days').val(),
            immediate_count: $('#sb-immediate-count').val(),
            confirmation: $confirmInput.val()
        };

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

})(jQuery);
