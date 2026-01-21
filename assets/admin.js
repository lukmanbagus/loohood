jQuery(document).ready(function($) {
    'use strict';

    function toBoolInt(value) {
        return Number(value) ? 1 : 0;
    }

    var currentStep = 1;
    var totalSteps = 4;
    var loohoodGithubToken = toBoolInt(loohoodAjax.githubToken);
    var loohoodGithubRepo = toBoolInt(loohoodAjax.githubRepo);
    var loohoodRepoCloned = toBoolInt(loohoodAjax.repoCloned);
    var loohoodGitConfigured = toBoolInt(loohoodAjax.gitConfigured);
    var loohoodCloudflareToken = toBoolInt(loohoodAjax.cloudflareToken);
    var loohoodCloudflareProject = toBoolInt(loohoodAjax.cloudflareProject);
    var loohoodGithubConnected = toBoolInt(loohoodAjax.githubConnected);
    var loohoodGithubRepoReady = toBoolInt(loohoodAjax.githubRepoReady);
    var loohoodCloudflareConnected = toBoolInt(loohoodAjax.cloudflareConnected);
    var loohoodCloudflareProjectReady = toBoolInt(loohoodAjax.cloudflareProjectReady);
    var deployLogsPollTimer = null;
    var deployLastLogIndex = 0;
    var deployLogsPollInFlight = false;

    function updateProgress(step) {
        var progress = ((step - 1) / totalSteps) * 100;
        $('.loohood-progress-fill').css('width', progress + '%');
    }

    function showStep(step) {
        $('.loohood-step').removeClass('active').hide();
        $('#loohood-step-' + step).addClass('active').fadeIn();
        $('.loohood-step-navigation').show();
        updateProgress(step);
        updateStepNavigation(step);
    }

    function updateStepNavigation(step) {
        var maxStep = getMaxCompletedStep();
        $('.loohood-step-item').each(function() {
            var stepNum = $(this).data('step');
            var $item = $(this);
            var $num = $item.find('.loohood-step-nav-number');
            var $label = $item.find('.loohood-step-nav-label');

            // Reset all classes first
            $item.removeClass('is-current is-completed is-disabled opacity-50 opacity-100');
            
            // Reset number classes - remove all possible state classes
            $num.attr('class', 'loohood-step-nav-number w-10 h-10 rounded-full flex items-center justify-center font-extrabold');
            
            // Reset label classes
            $label.attr('class', 'loohood-step-nav-label text-xs font-semibold');

            if (stepNum === step) {
                // Current step - orange active state
                $item.addClass('is-current opacity-100');
                $num.addClass('bg-[#f49300] text-white ring-4 ring-orange-100');
                $label.addClass('text-[#f49300] font-bold');
                return;
            }

            if (stepNum <= maxStep) {
                // Completed step - green
                $item.addClass('is-completed opacity-100');
                $num.addClass('bg-green-600 text-white ring-4 ring-green-100');
                $label.addClass('text-green-600');
                return;
            }

            // Disabled/upcoming step - gray
            $item.addClass('is-disabled opacity-50');
            $num.addClass('bg-slate-200 text-slate-500');
            $label.addClass('text-slate-400');
        });
    }

    function getMaxCompletedStep() {
        var completed = 0;
        if (loohoodGithubConnected) completed = 1;
        if (loohoodGithubRepoReady) completed = 2;
        if (loohoodCloudflareConnected) completed = 3;
        if (loohoodCloudflareProjectReady) completed = 4;
        return completed;
    }

    function showError(message) {
        $('#loohood-error').fadeIn();
        $('#loohood-error-message').text(message);
    }

    function hideError() {
        $('#loohood-error').hide();
    }

    function runStep(step, data) {
        data = data || {};
        data.step = step;
        data.nonce = loohoodAjax.nonce;
        data.action = 'loohood_run_setup';

        var $btn = $('#loohood-step-' + step + '-btn');
        $btn.prop('disabled', true).text('Processing...');

        clearTerminal();
        logToTerminal('info', 'Starting Step ' + step + '...');

        switch(step) {
            case 1:
                logToTerminal('command', 'Connecting to GitHub API...');
                logToTerminal('info', 'Validating GitHub token...');
                break;
            case 2:
                logToTerminal('command', 'Creating GitHub repository...');
                logToTerminal('info', 'Cloning repository to /wp-content/uploads/[repo_name]...');
                logToTerminal('info', 'Creating index.html...');
                logToTerminal('info', 'Committing and pushing to GitHub...');
                break;
            case 3:
                logToTerminal('command', 'Connecting to Cloudflare...');
                logToTerminal('info', 'Verifying Cloudflare API token...');
                break;
            case 4:
                logToTerminal('command', 'Creating Cloudflare Pages project...');
                logToTerminal('info', 'Connecting GitHub repository to Cloudflare Pages...');
                if (data.custom_domain) {
                    logToTerminal('info', 'Adding custom domain: ' + data.custom_domain + ' ...');
                }
                break;
        }

        $.ajax({
            url: loohoodAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    logToTerminal('success', '✓ Step ' + step + ' completed successfully');

                    if (response.data.logs && response.data.logs.length > 0) {
                        response.data.logs.forEach(function(log) {
                            logToTerminal(log.type, log.message);
                        });
                    }

                    if (step === 4) {
                        loohoodCloudflareProject = 1;
                        loohoodCloudflareProjectReady = 1;
                        showSuccess(response.data);
                    } else if (step === 2) {
                        $('#loohood-step-' + step).hide();
                        loohoodGithubRepo = 1;
                        loohoodRepoCloned = 1;
                        loohoodGitConfigured = 1;
                        loohoodGithubConnected = 1;
                        loohoodGithubRepoReady = 1;
                        currentStep = 3;

                        showStep(currentStep);

                        if (response.data.project_name) {
                            $('#loohood-project-name').val(response.data.project_name);
                        }
                    } else {
                        $('#loohood-step-' + step).hide();
                        
                        // Update state variables BEFORE showStep so navigation updates correctly
                        if (step === 1) {
                            loohoodGithubToken = 1;
                            loohoodGithubConnected = 1;
                        } else if (step === 3) {
                            loohoodCloudflareToken = 1;
                            loohoodCloudflareConnected = 1;
                        }
                        
                        currentStep++;
                        showStep(currentStep);
                    }
                } else {
                    logToTerminal('error', '✗ Error: ' + (response.data.message || 'An error occurred'));
                    showError(response.data.message || 'An error occurred');
                    $btn.prop('disabled', false).text('Retry');
                }
            },
            error: function() {
                logToTerminal('error', '✗ Network error. Please try again.');
                showError('Network error. Please try again.');
                $btn.prop('disabled', false).text('Retry');
            }
        });
    }

    function logToTerminal(type, message) {
        if (!$('#loohood-terminal-output').length) return;

        var $output = $('#loohood-terminal-output');
        var timestamp = new Date().toLocaleTimeString([], { hour12: false });
        var safeType = type || 'info';
        var safeMessage = escapeHtml(message);

        if ($output.hasClass('space-y-2')) {
            var c = terminalColorClass(safeType);
            $output.append(
                '<div class="flex gap-3">' +
                    '<span class="text-slate-500 shrink-0">' + escapeHtml(timestamp) + '</span>' +
                    '<span class="' + c + '">➜</span>' +
                    '<span class="text-slate-300">' + safeMessage + '</span>' +
                '</div>'
            );
        } else {
            $output.append(
                '<div><span class="timestamp">[' + escapeHtml(timestamp) + ']</span> <span class="' + escapeHtml(safeType) + '">' + safeMessage + '</span></div>'
            );
        }

        $output.scrollTop($output[0].scrollHeight);
    }

    function clearTerminal() {
        $('#loohood-terminal-output').empty();
    }

    function showSuccess(data) {
        $('.loohood-step').removeClass('active').hide();
        $('#loohood-success').fadeIn();
        $('.loohood-progress-fill').css('width', '100%');
        $('.loohood-step-navigation').hide();

        if (data.github_url) {
            $('#loohood-github-link').attr('href', data.github_url);
        }
        if (data.project_url) {
            $('#loohood-cloudflare-preview-link').attr('href', data.project_url).show();
        } else {
            $('#loohood-cloudflare-preview-link').hide();
        }

        if (data.custom_domain && data.cloudflare_url) {
            $('#loohood-cloudflare-link').attr('href', data.cloudflare_url).show();
        } else if (data.project_url) {
            $('#loohood-cloudflare-link').attr('href', data.project_url).show();
        } else {
            $('#loohood-cloudflare-link').hide();
        }
    }

    function buildSuccessDataFromOptions() {
        return {
            github_url: loohoodAjax.githubUrl || '',
            project_url: loohoodAjax.cloudflarePreviewUrl || '',
            cloudflare_url: loohoodAjax.cloudflareUrl || '',
            custom_domain: loohoodAjax.customDomain || ''
        };
    }

    function determineStartingStep() {
        if (loohoodCloudflareConnected) return 4;
        if (loohoodGithubRepoReady) return 3;
        if (loohoodGithubConnected) return 2;
        return 1;
    }

    $(document).on('click', '#loohood-open-github-cloudflare-app', function(e) {
        e.preventDefault();

        var url = $(this).attr('data-install-url') || 'https://github.com/apps/cloudflare-workers-and-pages/installations/select_target';
        var popupWidth = 900;
        var popupHeight = 700;

        var dualScreenLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
        var dualScreenTop = window.screenTop !== undefined ? window.screenTop : window.screenY;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || screen.height;

        var left = dualScreenLeft + Math.max(0, (viewportWidth - popupWidth) / 2);
        var top = dualScreenTop + Math.max(0, (viewportHeight - popupHeight) / 2);

        var features = [
            'scrollbars=yes',
            'resizable=yes',
            'width=' + popupWidth,
            'height=' + popupHeight,
            'top=' + top,
            'left=' + left
        ].join(',');

        var popup = window.open(url, 'loohoodCloudflareGitHubApp', features);
        if (popup && !popup.closed) {
            popup.focus();
            return;
        }

        window.location.href = url;
    });

    $('#loohood-step-1-btn').on('click', function() {
        var token = $('#loohood-github-token').val().trim();
        if (!token) {
            alert('Please enter GitHub token');
            return;
        }
        runStep(1, { github_token: token });
    });

    $('#loohood-step-2-btn').on('click', function() {
        var repoName = $('#loohood-repo-name').val().trim();
        if (!repoName) {
            alert('Please enter repository name');
            return;
        }
        runStep(2, { repo_name: repoName });
    });

    $('#loohood-step-3-btn').on('click', function() {
        var cloudflareToken = $('#loohood-cloudflare-token').val().trim();
        if (!cloudflareToken) {
            alert('Please enter Cloudflare token');
            return;
        }
        runStep(3, { cloudflare_token: cloudflareToken });
    });

    $('#loohood-step-4-btn').on('click', function() {
        var projectName = $('#loohood-project-name').val().trim();
        var customDomain = $('#loohood-custom-domain').val() ? $('#loohood-custom-domain').val().trim() : '';

        if (!projectName) {
            alert('Please enter project name');
            return;
        }
        var data = { project_name: projectName };
        if (customDomain) {
            data.custom_domain = customDomain;
        }
        runStep(4, data);
    });

    $('#loohood-retry-btn').on('click', function() {
        hideError();
        $('#loohood-step-' + currentStep + '-btn').prop('disabled', false).text('Retry');
    });

    $('.loohood-step-item').on('click', function() {
        var step = $(this).data('step');
        var maxStep = getMaxCompletedStep();

        if (step <= maxStep || step < currentStep) {
            hideError();
            currentStep = step;
            showStep(currentStep);
        }
    });

    $(document).on('click', '#loohood-dashboard-root form button[name="loohood_export"], #loohood-dashboard-root form input[name="loohood_export"]', function(e) {
        e.preventDefault();
        var $form = $(this).closest('form');
        var $btn = $(this);

        if ($btn.hasClass('disabled')) {
            return;
        }

        deployLastLogIndex = 0;

        if (!$btn.data('original-label')) {
            $btn.data('original-label', $btn.is('input') ? $btn.val() : $btn.text());
        }

        if ($btn.is('input')) {
            $btn.val('Deploying...');
        } else {
            $btn.text('Deploying...');
        }

        $btn.addClass('disabled').prop('disabled', true);

        $('#loohood-deploy-status').fadeIn();
        $('#loohood-status-text').text('Exporting static files...');

        openTerminal();
        appendTerminalOutput('info', 'Starting deployment process...');
        appendTerminalOutput('info', 'Connecting to server...');
        startDeployLogsPolling();

        $.ajax({
            url: loohoodAjax.ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=loohood_stream_deploy&nonce=' + encodeURIComponent(loohoodAjax.nonce),
            success: function(response) {
                stopDeployLogsPolling(true);
                if (response.success) {
                    appendTerminalOutput('success', '✓ Deployment completed successfully!');
                    $('#loohood-status-icon').text('✅');
                    $('#loohood-status-text').text('Deploy completed successfully!');
                    maybeShowTerminalCloseButton();

                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    appendTerminalOutput('error', '✗ Deployment failed: ' + (response.data.message || 'Unknown error'));
                    $('#loohood-status-icon').text('❌');
                    $('#loohood-status-text').text('Deploy failed. Please check logs.');
                    $btn.prop('disabled', false).removeClass('disabled');
                    if ($btn.is('input')) {
                        $btn.val($btn.data('original-label') || 'Deploy Now');
                    } else {
                        $btn.text($btn.data('original-label') || 'Deploy Now');
                    }
                    maybeShowTerminalCloseButton();
                }
            },
            error: function(xhr, status, error) {
                stopDeployLogsPolling(true);
                appendTerminalOutput('error', '✗ Network error: ' + error);
                $('#loohood-status-icon').text('❌');
                $('#loohood-status-text').text('Deploy failed. Please check logs.');
                $btn.prop('disabled', false).removeClass('disabled');
                if ($btn.is('input')) {
                    $btn.val($btn.data('original-label') || 'Deploy Now');
                } else {
                    $btn.text($btn.data('original-label') || 'Deploy Now');
                }
                maybeShowTerminalCloseButton();
            }
        });
    });

    $('#loohood-add-custom-domain-btn').on('click', function() {
        var domain = $('#loohood-admin-custom-domain').val() ? $('#loohood-admin-custom-domain').val().trim() : '';

        if (!domain) {
            alert('Please enter a domain');
            return;
        }

        openTerminal();
        appendTerminalOutput('info', 'Adding custom domain: ' + domain + ' ...');

        $.ajax({
            url: loohoodAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'loohood_add_custom_domain',
                nonce: loohoodAjax.nonce,
                domain: domain
            },
            success: function(response) {
                if (response && response.success) {
                    appendTerminalOutput('success', '✓ Custom domain request submitted');
                    if (response.data && response.data.logs && Array.isArray(response.data.logs)) {
                        response.data.logs.forEach(function(log) {
                            appendTerminalOutput(log.type, log.message);
                        });
                    }
                    maybeShowTerminalCloseButton();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                    return;
                }

                appendTerminalOutput('error', '✗ Error: ' + ((response && response.data && response.data.message) ? response.data.message : 'An error occurred'));
                maybeShowTerminalCloseButton();
            },
            error: function(xhr, status, error) {
                appendTerminalOutput('error', '✗ Network error: ' + (error || 'Unknown error'));
                maybeShowTerminalCloseButton();
            }
        });
    });

    function fetchDeployLogs() {
        if (deployLogsPollInFlight) {
            return;
        }
        deployLogsPollInFlight = true;

        $.ajax({
            url: loohoodAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'loohood_get_deploy_logs',
                nonce: loohoodAjax.nonce
            },
            success: function(response) {
                if (!response || !response.success || !response.data || !response.data.logs) {
                    return;
                }

                var logs = response.data.logs;
                if (!Array.isArray(logs)) {
                    return;
                }

                if (deployLastLogIndex > logs.length) {
                    deployLastLogIndex = 0;
                }

                var newLogs = logs.slice(deployLastLogIndex);
                if (newLogs.length === 0) {
                    return;
                }

                newLogs.forEach(function(log) {
                    appendTerminalLog(log);
                });

                deployLastLogIndex = logs.length;
            },
            complete: function() {
                deployLogsPollInFlight = false;
            }
        });
    }

    function startDeployLogsPolling() {
        stopDeployLogsPolling(false);
        fetchDeployLogs();
        deployLogsPollTimer = setInterval(fetchDeployLogs, 1000);
    }

    function stopDeployLogsPolling(finalFetch) {
        if (deployLogsPollTimer) {
            clearInterval(deployLogsPollTimer);
            deployLogsPollTimer = null;
        }
        if (finalFetch) {
            fetchDeployLogs();
        }
    }

    function isInlineTerminal() {
        var $terminal = $('#loohood-terminal-modal');
        return $terminal.length && $terminal.hasClass('loohood-terminal-inline');
    }

    function maybeShowTerminalCloseButton() {
        if (isInlineTerminal()) {
            return;
        }
        $('#loohood-close-terminal-btn').show();
    }

    function openTerminal() {
        if ($('#loohood-terminal-output').length) {
            $('#loohood-terminal-output').empty();
        }

        if (isInlineTerminal()) {
            $('#loohood-close-terminal-btn').hide();
            return;
        }

        if ($('#loohood-terminal-modal').length) {
            $('#loohood-terminal-modal').fadeIn();
            $('#loohood-close-terminal-btn').hide();
            document.body.style.overflow = 'hidden';
        }
    }

    function closeTerminal() {
        if (isInlineTerminal()) {
            return;
        }
        if ($('#loohood-terminal-modal').length) {
            $('#loohood-terminal-modal').fadeOut();
            document.body.style.overflow = 'auto';
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function terminalColorClass(type) {
        switch (type) {
            case 'success':
                return 'text-green-400';
            case 'error':
                return 'text-rose-400';
            case 'warning':
                return 'text-amber-300';
            case 'command':
                return 'text-orange-300';
            default:
                return 'text-sky-400';
        }
    }

    function appendTerminalOutput(type, message) {
        var $output = $('#loohood-terminal-output');
        if (!$output.length) {
            return;
        }

        var timestamp = new Date().toLocaleTimeString([], { hour12: false });
        var safeMessage = escapeHtml(message);
        var safeType = type || 'info';

        if ($output.hasClass('space-y-2')) {
            var c = terminalColorClass(safeType);
            $output.append(
                '<div class="flex gap-3">' +
                    '<span class="text-slate-500 shrink-0">' + escapeHtml(timestamp) + '</span>' +
                    '<span class="' + c + '">➜</span>' +
                    '<span class="' + c + ' font-bold">' + safeMessage + '</span>' +
                '</div>'
            );
        } else {
            $output.append(
                '<div><span class="timestamp">[' + escapeHtml(timestamp) + ']</span> <span class="' + escapeHtml(safeType) + '">' + safeMessage + '</span></div>'
            );
        }

        $output.scrollTop($output[0].scrollHeight);
    }

    function appendTerminalLog(log) {
        if (!log || typeof log.message !== 'string') {
            return;
        }

        var $output = $('#loohood-terminal-output');
        if (!$output.length) {
            return;
        }

        var ts = log.timestamp
            ? new Date(log.timestamp * 1000).toLocaleTimeString([], { hour12: false })
            : new Date().toLocaleTimeString([], { hour12: false });
        var type = log.type || 'info';

        var safeMessage = escapeHtml(log.message);
        var safeType = type || 'info';

        if ($output.hasClass('space-y-2')) {
            var c = terminalColorClass(safeType);
            $output.append(
                '<div class="flex gap-3">' +
                    '<span class="text-slate-500 shrink-0">' + escapeHtml(ts) + '</span>' +
                    '<span class="' + c + '">➜</span>' +
                    '<span class="text-slate-300">' + safeMessage + '</span>' +
                '</div>'
            );
        } else {
            $output.append(
                '<div><span class="timestamp">[' + escapeHtml(ts) + ']</span> <span class="' + escapeHtml(safeType) + '">' + safeMessage + '</span></div>'
            );
        }

        $output.scrollTop($output[0].scrollHeight);
    }

    $('#loohood-view-logs').on('click', function() {
        $('#loohood-deploy-logs').slideToggle();

        if ($('#loohood-deploy-logs').is(':visible')) {
            $.ajax({
                url: loohoodAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'loohood_get_deploy_logs',
                    nonce: loohoodAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.logs) {
                        var logHtml = '';
                        response.data.logs.forEach(function(log) {
                            var timestamp = new Date(log.timestamp * 1000).toLocaleTimeString();
                            var colorClass = log.type || 'info';
                            logHtml += '<span class="timestamp">[' + timestamp + ']</span> <span class="' + colorClass + '">' + log.message + '</span><br>';
                        });
                        $('#loohood-deploy-logs').html(logHtml || 'No logs available');
                    }
                }
            });
        }
    });

    function openTokenModal(tokenType) {
        if (!$('#loohood-token-modal').length) {
            return;
        }

        var title = 'Update Token';
        var placeholder = '';

        if (tokenType === 'github') {
            title = 'Update GitHub Token';
            placeholder = 'ghp_xxxxxxxxxxxx';
        } else if (tokenType === 'cloudflare') {
            title = 'Update Cloudflare Token';
            placeholder = 'Your Cloudflare API Token';
        }

        $('#loohood-token-modal-title').text(title);
        $('#loohood-token-modal-type').val(tokenType);
        $('#loohood-token-modal-input').val('').attr('placeholder', placeholder);
        $('#loohood-token-modal-status').text('').css('color', '');

        $('#loohood-token-modal').fadeIn();
        document.body.style.overflow = 'hidden';
    }

    function closeTokenModal() {
        if (!$('#loohood-token-modal').length) {
            return;
        }
        $('#loohood-token-modal').fadeOut();
        document.body.style.overflow = 'auto';
    }

    $(document).on('click', '.loohood-change-token-btn', function() {
        var tokenType = $(this).data('token-type');
        openTokenModal(tokenType);
    });

    $(document).on('click', '#loohood-token-modal-cancel', function() {
        closeTokenModal();
    });

    $(document).on('click', '#loohood-token-modal .loohood-modal-close', function() {
        closeTokenModal();
    });

    $(document).on('click', '#loohood-token-modal', function(e) {
        if (e.target === this) {
            closeTokenModal();
        }
    });

    $(document).on('click', '#loohood-token-modal-save', function() {
        var tokenType = $('#loohood-token-modal-type').val();
        var token = $('#loohood-token-modal-input').val() ? $('#loohood-token-modal-input').val().trim() : '';

        if (!tokenType) {
            $('#loohood-token-modal-status').text('Token type is missing.').css('color', '#dc3232');
            return;
        }
        if (!token) {
            $('#loohood-token-modal-status').text('Please enter a token.').css('color', '#dc3232');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Updating...');
        $('#loohood-token-modal-status').text('Validating token...').css('color', '#f49300');

        $.ajax({
            url: loohoodAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'loohood_update_token',
                nonce: loohoodAjax.nonce,
                token_type: tokenType,
                token: token
            },
            success: function(response) {
                if (response && response.success) {
                    $('#loohood-token-modal-status').text('Token updated successfully.').css('color', '#46b450');
                    setTimeout(function() {
                        location.reload();
                    }, 800);
                    return;
                }

                var message = (response && response.data && response.data.message) ? response.data.message : 'An error occurred';
                $('#loohood-token-modal-status').text(message).css('color', '#dc3232');
            },
            error: function(xhr, status, error) {
                $('#loohood-token-modal-status').text('Network error: ' + (error || 'Unknown error')).css('color', '#dc3232');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Update Token');
            }
        });
    });

    if ($('#loohood-step-1').length) {
        if (loohoodCloudflareProjectReady) {
            showSuccess(buildSuccessDataFromOptions());
        } else {
            currentStep = determineStartingStep();
            showStep(currentStep);
            updateStepNavigation(currentStep);
        }
    }

    window.closeTerminal = closeTerminal;
    window.openTerminal = openTerminal;
});
