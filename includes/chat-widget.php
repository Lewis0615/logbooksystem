<?php
/**
 * Real-Time Chat Widget
 * St. Dominic Savio College - Visitor Management System
 * 
 * This file should be included on every page that needs the chat functionality
 */

// Only show chat if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'];
    $current_user_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
    
    // Show chat widget
    ?>
    
    <!-- FLOATING CHAT WIDGET -->
    <div id="chatWidget" class="chat-widget">
        <!-- Chat Toggle Button -->
        <div class="chat-toggle-btn" id="chatToggleBtn">
            <div class="chat-icon">
                <i class="fas fa-comments"></i>
                <span class="chat-badge" id="chatBadge" style="display: none;">0</span>
            </div>
            <div class="chat-pulse"></div>
        </div>
        
        <!-- Chat Window -->
        <div class="chat-window" id="chatWindow" style="display: none;">
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-header-info">
                    <h6 class="chat-title">
                        <i class="fas fa-shield-alt me-2"></i>
                        Security Communication
                    </h6>
                    <span class="chat-subtitle">
                        <?php 
                        if ($current_user_role === ROLE_GUARD) {
                            echo 'Contact Administration';
                        } else if ($current_user_role === ROLE_ADMIN || $current_user_role === ROLE_SUPERVISOR) {
                            echo 'Guard & Team Communication';
                        } else {
                            echo 'Team Communication';
                        }
                        ?>
                    </span>
                </div>
                <div class="chat-actions">
                    <button class="chat-action-btn" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button class="chat-action-btn" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <div class="chat-loading">
                    <div class="chat-loading-icon">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div class="chat-loading-text">Loading messages...</div>
                </div>
            </div>
            
            <!-- Chat Input -->
            <div class="chat-input-section">
                <?php if ($current_user_role === ROLE_GUARD): ?>
                <div class="chat-recipient-bar">
                    <span class="chat-recipient-label"><i class="fas fa-paper-plane me-1"></i>To:</span>
                    <select id="chatReceiverRole" class="chat-recipient-select">
                        <option value="admin">Administration (Admin)</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="chat-input-wrapper">
                    <textarea class="chat-input" id="chatInput" 
                              placeholder="Type your message..." 
                              rows="1"
                              oninput="autoResizeTextarea(this)"></textarea>
                    <button class="chat-send-btn" id="chatSendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chat-typing-indicator" id="chatTyping" style="display: none;">
                    <span>Someone is typing...</span>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* ─── CHAT WIDGET STYLES ─── */
        .chat-widget {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            font-family: 'DM Sans', -apple-system, sans-serif;
        }
        
        /* ─── TOGGLE BUTTON ─── */
        .chat-toggle-btn {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--green-600, #16a34a), var(--green-500, #22c55e));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 32px rgba(22, 163, 74, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 10000;
        }
        
        .chat-toggle-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 40px rgba(22, 163, 74, 0.5);
        }
        
        .chat-toggle-btn:active {
            transform: translateY(0) scale(0.98);
        }
        
        .chat-icon {
            color: white;
            font-size: 22px;
            position: relative;
            z-index: 2;
            pointer-events: none;
        }
        
        .chat-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            border: 2px solid white;
        }
        
        .chat-pulse {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(22, 163, 74, 0.3);
            transform: translate(-50%, -50%);
            animation: chatPulse 2s infinite;
            pointer-events: none;
            z-index: 1;
        }
        
        @keyframes chatPulse {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            70% { transform: translate(-50%, -50%) scale(1.4); opacity: 0; }
            100% { transform: translate(-50%, -50%) scale(1.4); opacity: 0; }
        }
        
        /* ─── CHAT WINDOW ─── */
        .chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 360px;
            max-height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 80px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid #e5e7eb;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .chat-window.show {
            opacity: 1;
        }
        
        /* ─── CHAT HEADER ─── */
        .chat-header {
            background: linear-gradient(135deg, var(--green-600, #16a34a), var(--green-500, #22c55e));
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .chat-header-info {
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .chat-title {
            margin: 0 0 2px;
            font-size: 14px;
            font-weight: 700;
        }
        
        .chat-subtitle {
            font-size: 11px;
            opacity: 0.8;
            font-weight: 500;
        }
        
        .chat-actions {
            display: flex;
            gap: 4px;
            position: relative;
            z-index: 1;
        }
        
        .chat-action-btn {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all 0.2s;
        }
        
        .chat-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        /* ─── CHAT MESSAGES ─── */
        .chat-messages {
            height: 300px;
            overflow-y: auto;
            padding: 16px;
            background: #f9fafb;
        }
        
        .chat-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6b7280;
        }
        
        .chat-loading-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: var(--green-500, #22c55e);
        }
        
        .chat-loading-text {
            font-size: 12px;
            font-weight: 500;
        }
        
        .chat-message {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-message.sent {
            align-items: flex-end;
        }
        
        .chat-message.received {
            align-items: flex-start;
        }
        
        .chat-message-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 13px;
            line-height: 1.4;
            word-wrap: break-word;
            position: relative;
        }
        
        .chat-message.sent .chat-message-bubble {
            background: linear-gradient(135deg, var(--green-500, #22c55e), var(--green-400, #4ade80));
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .chat-message.received .chat-message-bubble {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
        }
        
        .chat-message-meta {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .chat-message.sent .chat-message-meta {
            text-align: right;
        }
        
        .chat-message.received .chat-message-meta {
            text-align: left;
        }
        
        /* ─── CHAT INPUT ─── */
        .chat-input-section {
            padding: 16px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .chat-input-wrapper {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        
        .chat-input {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 13px;
            font-family: inherit;
            resize: none;
            outline: none;
            transition: all 0.2s;
            max-height: 100px;
            line-height: 1.4;
        }
        
        .chat-input:focus {
            border-color: var(--green-400, #4ade80);
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        
        .chat-send-btn {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--green-500, #22c55e), var(--green-400, #4ade80));
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .chat-send-btn:hover:not(:disabled) {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
        }
        
        .chat-send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .chat-typing-indicator {
            margin-top: 8px;
            font-size: 11px;
            color: #6b7280;
            font-style: italic;
        }
        
        /* ─── RECIPIENT BAR (guard only) ─── */
        .chat-recipient-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 2px 10px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 10px;
        }

        .chat-recipient-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            white-space: nowrap;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .chat-recipient-select {
            flex: 1;
            border: 1.5px solid #e5e7eb;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            font-family: inherit;
            font-weight: 600;
            color: #374151;
            background: #f9fafb;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 28px;
        }

        .chat-recipient-select:focus {
            border-color: var(--green-400, #4ade80);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }

        /* ─── EMPTY STATE ─── */
        .chat-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #6b7280;
        }
        
        .chat-empty-icon {
            font-size: 32px;
            color: var(--green-400, #4ade80);
            margin-bottom: 12px;
        }
        
        .chat-empty-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .chat-empty-subtitle {
            font-size: 11px;
            opacity: 0.7;
        }
        
        /* ─── RESPONSIVE ─── */
        @media (max-width: 480px) {
            .chat-widget {
                bottom: 16px;
                right: 16px;
            }
            
            .chat-window {
                width: calc(100vw - 32px);
                right: -16px;
            }
        }
    </style>
    
    <script>
        // Chat widget variables
        let chatIsOpen = false;
        let chatRefreshInterval = null;
        let lastMessageId = 0;
        let isLoadingMessages = false;
        
        // Wait for both DOM and jQuery to be ready
        function initChatWidget() {
            console.log('Chat widget initializing...');
            
            // Check if jQuery is available
            if (typeof jQuery === 'undefined') {
                console.error('jQuery not loaded! Chat widget requires jQuery.');
                return;
            }
            
            initializeChat();
            
            // Bind click events (backup for inline onclick)
            $('#chatToggleBtn').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Chat toggle button clicked via jQuery');
                toggleChat();
            });
            
            // Also bind to close buttons in header
            $('.chat-action-btn').off('click').on('click', function(e) {
                e.preventDefault();
                const title = $(this).attr('title');
                if (title === 'Close') {
                    toggleChat();
                } else if (title === 'Refresh') {
                    refreshChat();
                }
            });
            
            // Bind send button
            $('#chatSendBtn').off('click').on('click', function(e) {
                e.preventDefault();
                sendMessage();
            });
            
            // Handle enter key in textarea
            $('#chatInput').off('keypress').on('keypress', function(e) {
                handleChatKeypress(e);
            });
            
            // Auto-resize textarea on input
            $('#chatInput').off('input').on('input', function() {
                autoResizeTextarea(this);
            });
            
            console.log('Chat widget event handlers bound');
        }
        
        // Poll until jQuery is available, then initialize
        (function pollForJQuery() {
            if (typeof jQuery !== 'undefined') {
                $(document).ready(initChatWidget);
            } else {
                setTimeout(pollForJQuery, 50);
            }
        })();
        
        function initializeChat() {
            console.log('Chat initialized');
            // Load initial messages
            loadChatMessages();
            
            // Set up auto-refresh
            chatRefreshInterval = setInterval(loadChatMessages, 5000);
            
            // Load unread count for badge
            updateChatBadge();
        }
        
        function toggleChat() {
            console.log('toggleChat called, current state:', chatIsOpen);
            chatIsOpen = !chatIsOpen;
            
            const chatWindow = document.getElementById('chatWindow');
            
            if (chatIsOpen) {
                console.log('Opening chat window');
                chatWindow.style.display = 'block';
                // Trigger reflow to ensure transition works
                chatWindow.offsetHeight;
                chatWindow.classList.add('show');
                
                // Focus input
                const chatInput = document.getElementById('chatInput');
                if (chatInput) {
                    setTimeout(() => chatInput.focus(), 250);
                }
                markMessagesAsRead();
            } else {
                console.log('Closing chat window');
                chatWindow.classList.remove('show');
                setTimeout(() => {
                    chatWindow.style.display = 'none';
                }, 200);
            }
        }
        
        // Use a root-relative URL computed from the actual page path so it works
        // regardless of whether the user accesses via localhost, 127.0.0.1, or LAN IP.
        const chatAjaxUrl = (function() {
            const parts = window.location.pathname.split('/');
            parts.pop(); // remove current filename
            parts.pop(); // go up one directory (out of admin/)
            return parts.join('/') + '/ajax/chat-messages.php';
        })();

        function loadChatMessages(showLoading = false) {
            if (isLoadingMessages) return;

            const isIncremental = (lastMessageId > 0) && !showLoading;
            isLoadingMessages = true;

            if (showLoading) {
                lastMessageId = 0; // full reload
                $('#chatMessages').html(`
                    <div class="chat-loading">
                        <div class="chat-loading-icon">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="chat-loading-text">Loading messages...</div>
                    </div>
                `);
            }

            $.ajax({
                url: chatAjaxUrl,
                method: 'GET',
                cache: false,
                dataType: 'json',
                data: { last_message_id: lastMessageId },
                success: function(response) {
                    if (response.success) {
                        if (response.messages && response.messages.length > 0) {
                            lastMessageId = Math.max(...response.messages.map(m => parseInt(m.id)));
                            if (isIncremental) {
                                // Remove empty state if shown, then append new messages
                                $('#chatMessages .chat-empty-state').remove();
                                appendChatMessages(response.messages);
                            } else {
                                displayChatMessages(response.messages);
                            }
                        } else if (!isIncremental) {
                            // Only show empty state on full (re)load with no messages
                            displayChatMessages([]);
                        }
                        // If incremental and no new messages, do nothing — preserve existing display
                    } else {
                        console.error('Chat error:', response.message);
                        if (!isIncremental) {
                            $('#chatMessages').html(`
                                <div class="chat-empty-state">
                                    <div class="chat-empty-icon"><i class="fas fa-exclamation-circle"></i></div>
                                    <div class="chat-empty-title">Error Loading Messages</div>
                                    <div class="chat-empty-subtitle">${response.message || 'Unknown error'}</div>
                                </div>
                            `);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Chat AJAX error:', xhr.status, error, xhr.responseText);
                    let errorMessage = 'Unable to load messages';
                    if (xhr.status === 401) errorMessage = 'Session expired - please refresh the page';
                    else if (xhr.status === 404) errorMessage = 'Chat service not found';
                    else if (xhr.status === 500) errorMessage = 'Server error - please try again';
                    else if (xhr.status === 0) errorMessage = 'Network error';

                    // Show error on both initial and incremental loads
                    if (!isIncremental) {
                        $('#chatMessages').html(`
                            <div class="chat-empty-state">
                                <div class="chat-empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                <div class="chat-empty-title">Connection Error (${xhr.status})</div>
                                <div class="chat-empty-subtitle">${errorMessage}</div>
                                <button onclick="loadChatMessages(true)" style="margin-top: 12px; padding: 6px 16px; background: var(--green-500); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                    <i class="fas fa-redo me-1"></i>Retry
                                </button>
                            </div>
                        `);
                    } else {
                        // Incremental error – show small non-intrusive notice
                        console.warn('Incremental chat poll failed:', xhr.status, errorMessage);
                    }
                },
                complete: function() {
                    isLoadingMessages = false;
                }
            });
        }
        
        function buildMessageHTML(messages) {
            const currentUserId = <?php echo $current_user_id; ?>;
            let html = '';
            messages.forEach(function(message) {
                const isSent = parseInt(message.sender_id) === currentUserId;
                const messageClass = isSent ? 'sent' : 'received';
                const timestamp = formatChatTime(message.created_at);
                html += `
                    <div class="chat-message ${messageClass}">
                        <div class="chat-message-bubble">${escapeHtml(message.message)}</div>
                        <div class="chat-message-meta">${!isSent ? escapeHtml(message.sender_name) + ' &bull; ' : ''}${timestamp}</div>
                    </div>
                `;
            });
            return html;
        }

        function displayChatMessages(messages) {
            if (!messages || !Array.isArray(messages) || messages.length === 0) {
                $('#chatMessages').html(`
                    <div class="chat-empty-state">
                        <div class="chat-empty-icon"><i class="fas fa-comments"></i></div>
                        <div class="chat-empty-title">No messages yet</div>
                        <div class="chat-empty-subtitle">Start a conversation with the team</div>
                    </div>
                `);
                return;
            }
            $('#chatMessages').html(buildMessageHTML(messages));
            scrollChatToBottom();
        }

        function appendChatMessages(messages) {
            if (!messages || messages.length === 0) return;
            $('#chatMessages').append(buildMessageHTML(messages));
            scrollChatToBottom();
        }
        
        function sendMessage() {
            const message = $('#chatInput').val().trim();
            if (!message) return;

            // Get receiver role from dropdown (guard only), fallback to null for admin/supervisor
            const receiverRoleEl = document.getElementById('chatReceiverRole');
            const receiverRole = receiverRoleEl ? receiverRoleEl.value : null;

            // Show which role will receive the message in placeholder briefly
            if (receiverRoleEl) {
                const label = receiverRoleEl.options[receiverRoleEl.selectedIndex].text;
                $('#chatInput').attr('placeholder', 'Sending to ' + label + '...');
                setTimeout(() => $('#chatInput').attr('placeholder', 'Type your message...'), 1500);
            }
            
            // Disable send button
            $('#chatSendBtn').prop('disabled', true);

            const postData = {
                action: 'send',
                message: message,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            };
            if (receiverRole) postData.receiver_role = receiverRole;
            
            $.ajax({
                url: chatAjaxUrl,
                method: 'POST',
                dataType: 'json',
                data: postData,
                success: function(response) {
                    if (response.success) {
                        $('#chatInput').val('');
                        autoResizeTextarea($('#chatInput')[0]);
                        loadChatMessages();
                    } else {
                        alert('Failed to send message: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to send message. Please try again.');
                },
                complete: function() {
                    $('#chatSendBtn').prop('disabled', false);
                    $('#chatInput').focus();
                }
            });
        }
        
        function handleChatKeypress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }
        
        function autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
        }
        
        function refreshChat() {
            loadChatMessages(true);
        }
        
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function updateChatBadge() {
            $.ajax({
                url: chatAjaxUrl,
                method: 'GET',
                cache: false,
                dataType: 'json',
                data: { action: 'unread_count' },
                success: function(response) {
                    if (response.success) {
                        const count = response.unread_count;
                        if (count > 0) {
                            $('#chatBadge').text(count > 99 ? '99+' : count).show();
                        } else {
                            $('#chatBadge').hide();
                        }
                    }
                }
            });
        }
        
        function markMessagesAsRead() {
            $.ajax({
                url: chatAjaxUrl,
                method: 'POST',
                data: {
                    action: 'mark_read',
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                }
            });
            $('#chatBadge').hide();
        }
        
        function formatChatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) { // Less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // Less than 1 hour
                return Math.floor(diff / 60000) + 'm ago';
            } else if (diff < 86400000) { // Less than 1 day
                return Math.floor(diff / 3600000) + 'h ago';
            } else {
                return date.toLocaleDateString();
            }
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Clean up on page unload
        $(window).on('beforeunload', function() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
            }
        });
    </script>
    
    <?php
}
?>