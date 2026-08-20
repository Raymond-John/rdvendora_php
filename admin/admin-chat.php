<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';   // permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR CHAT PAGE ----------
if (!adminHasPermission('chat', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to access the chat.</p><a href="admin">Go to Dashboard</a></div>');
}

// Create chat table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `vendor_id` INT(11) NOT NULL,
    `sender_type` ENUM('admin','vendor') NOT NULL,
    `message` TEXT NOT NULL,
    `audio_url` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle sending a message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $vendor_id = intval($_POST['vendor_id']);
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (vendor_id, sender_type, message) VALUES (?, 'admin', ?)");
        $stmt->bind_param("is", $vendor_id, $message);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
    }
    exit;
}

// Get all vendors (active stores with user details)
$vendors = [];
$vendorQuery = $conn->query("
    SELECT s.user_id, u.full_name, s.store_name, s.id as store_id, u.last_active
    FROM stores s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.store_name ASC
");
if ($vendorQuery) {
    while ($row = $vendorQuery->fetch_assoc()) {
        // Last message and unread count
        $lastMsg = $conn->query("SELECT message, created_at, sender_type FROM chat_messages WHERE vendor_id = {$row['user_id']} ORDER BY created_at DESC LIMIT 1");
        $last = $lastMsg->fetch_assoc();
        $row['last_message'] = $last ? htmlspecialchars(substr($last['message'], 0, 50)) : 'No messages yet';
        $row['last_time'] = $last ? date('H:i', strtotime($last['created_at'])) : '';
        $unreadResult = $conn->query("SELECT COUNT(*) as cnt FROM chat_messages WHERE vendor_id = {$row['user_id']} AND sender_type = 'vendor' AND is_read = 0");
        $row['unread'] = $unreadResult->fetch_assoc()['cnt'];
        
        // Online status
        $lastActive = strtotime($row['last_active']);
        $row['is_online'] = (time() - $lastActive) < 120; // 2 minutes
        $vendors[] = $row;
    }
}

$adminPageTitle = 'Admin Chat - RD Vendora';
$adminPageHeading = 'Chat';
$adminPageSubtitle = 'Platform support chat';
$adminSearchPlaceholder = 'Search vendors...';
$adminShowHeader = false;
$adminHeadExtra = '<script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>';
$adminPageStyles = <<<'CSS'
.admin-app:has(.chat-layout) .main-content {
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.chat-layout {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    margin: calc(var(--topbar-height) + 1rem) 2rem 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 1px solid var(--border-primary);
    box-shadow: var(--shadow);
}
.vendor-list {
    width: 320px;
    border-right: 1px solid var(--border-primary);
    overflow-y: auto;
    background: var(--bg-secondary);
}
.vendor-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-primary);
    cursor: pointer;
    position: relative;
}
.vendor-item:hover { background: var(--bg-tertiary); }
.vendor-item.active {
    background: var(--primary-light);
    border-left: 3px solid var(--primary);
}
.vendor-avatar {
    width: 48px; height: 48px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    flex-shrink: 0;
    position: relative;
}
.online-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border-radius: 50%;
    border: 2px solid var(--bg-secondary);
}
.vendor-info { flex: 1; min-width: 0; }
.vendor-name { font-weight: 600; margin-bottom: 0.2rem; }
.vendor-last-msg {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.unread-badge {
    background: var(--primary);
    color: white;
    border-radius: 2rem;
    padding: 2px 8px;
    font-size: 0.7rem;
    font-weight: bold;
}
.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--bg-primary);
}
.chat-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-primary);
    background: var(--bg-secondary);
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.call-controls { display: flex; gap: 0.5rem; }
.admin-app .chat-header .call-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.admin-app .chat-header .call-btn:hover {
    background: var(--primary);
    color: white;
}
.admin-app .chat-header .audio-call:hover { background: #10b981; }
.admin-app .chat-header .video-call:hover { background: #6366f1; }
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.admin-app .chat-messages .message {
    max-width: 70%;
    padding: 0.5rem 1rem;
    margin: 0;
    border-radius: 1rem;
    word-wrap: break-word;
    font-size: 0.875rem;
}
.admin-app .chat-messages .message-admin {
    background: var(--gradient-primary);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 0.25rem;
}
.admin-app .chat-messages .message-vendor {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    align-self: flex-start;
    border-bottom-left-radius: 0.25rem;
}
.message-time {
    font-size: 0.65rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    text-align: right;
}
.typing-indicator {
    font-size: 0.75rem;
    color: var(--text-muted);
    padding: 0.25rem 1rem;
    font-style: italic;
}
.chat-input-area {
    padding: 1rem;
    border-top: 1px solid var(--border-primary);
    background: var(--bg-secondary);
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.admin-app .chat-input-area input {
    flex: 1;
    width: auto;
    padding: 0.6rem 1rem;
    border-radius: 2rem;
    border: 1px solid var(--border-primary);
    background: var(--bg-tertiary);
    color: var(--text-primary);
    outline: none;
}
.admin-app .chat-input-area button {
    background: var(--gradient-primary);
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: 2rem;
    font-weight: 600;
    width: auto;
}
.admin-app .chat-input-area .mic-btn {
    background: var(--bg-tertiary);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    padding: 0;
    font-size: 1.2rem;
    color: var(--text-secondary);
}
.mic-btn.recording {
    background: var(--error);
    color: white;
    animation: chatPulse 1s infinite;
}
@keyframes chatPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.local-video, .remote-video {
    width: 200px;
    height: 150px;
    background: #000;
    border-radius: 8px;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    display: none;
    object-fit: cover;
}
.remote-video { right: 240px; }
#callControls {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
    display: none;
    background: var(--bg-secondary);
    padding: 8px 12px;
    border-radius: 2rem;
    box-shadow: var(--shadow-lg);
}
.admin-app #callControls button {
    background: var(--error);
    color: #fff;
    padding: 0.45rem 1rem;
    border-radius: 2rem;
    width: auto;
}
@media (max-width: 768px) {
    .chat-layout {
        margin: calc(var(--topbar-height) + 0.5rem) 0.5rem 0.5rem;
        flex-direction: column;
        height: calc(100vh - var(--topbar-height) - 1rem);
    }
    .vendor-list { width: 100%; max-height: 200px; border-right: none; border-bottom: 1px solid var(--border-primary); }
    .local-video, .remote-video { width: 120px; height: 90px; }
    .remote-video { right: 140px; }
}
CSS;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="chat-layout">
        <div class="vendor-list" id="vendorList">
            <?php foreach ($vendors as $v): ?>
                <div class="vendor-item" data-vendor-id="<?= $v['user_id'] ?>" data-vendor-name="<?= htmlspecialchars($v['store_name'] ?? $v['full_name']) ?>">
                    <div class="vendor-avatar">
                        <?= strtoupper(substr($v['store_name'] ?? $v['full_name'], 0, 1)) ?>
                        <?php if ($v['is_online']): ?><span class="online-dot"></span><?php endif; ?>
                    </div>
                    <div class="vendor-info">
                        <div class="vendor-name"><?= htmlspecialchars($v['store_name'] ?? $v['full_name']) ?></div>
                        <div class="vendor-last-msg"><?= $v['last_message'] ?></div>
                    </div>
                    <?php if ($v['unread'] > 0): ?>
                        <span class="unread-badge"><?= $v['unread'] ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-area" id="chatArea">
            <div class="chat-header" id="chatHeader">
                <span>Select a vendor to start chatting</span>
                <div class="call-controls" id="callControlsHeader" style="display: none;"></div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="typing-indicator" id="typingIndicator" style="display: none;">Vendor is typing...</div>
            <div class="chat-input-area" id="chatInputArea" style="display: none;">
                <input type="text" id="messageInput" placeholder="Type a message...">
                <button type="button" id="sendBtn">Send</button>
                <button type="button" class="mic-btn" id="micBtn">🎤</button>
            </div>
        </div>
</div>

<video id="localVideo" class="local-video" autoplay muted playsinline></video>
<video id="remoteVideo" class="remote-video" autoplay playsinline></video>
<div id="callControls">
    <button type="button" id="endCallBtn">End Call</button>
</div>
<div class="modal" id="incomingCallModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:var(--bg-secondary,#fff); padding:1.5rem; border-radius:16px; text-align:center; max-width:360px; width:90%;">
        <h3 style="margin:0 0 .5rem;">Incoming call</h3>
        <p id="adminCallTypeText" style="margin:0 0 1rem; color:var(--text-secondary,#64748b);">Vendor is calling...</p>
        <div style="display:flex; gap:.75rem; justify-content:center;">
            <button type="button" id="adminAcceptCallBtn" style="background:#10b981;color:#fff;border:0;border-radius:999px;padding:.65rem 1.25rem;font-weight:600;cursor:pointer;">Accept</button>
            <button type="button" id="adminDeclineCallBtn" style="background:#ef4444;color:#fff;border:0;border-radius:999px;padding:.65rem 1.25rem;font-weight:600;cursor:pointer;">Decline</button>
        </div>
    </div>
</div>
<script>
    const searchInput = document.getElementById('adminSearchInput');
    const vendorItems = document.querySelectorAll('.vendor-item');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            vendorItems.forEach(item => {
                const name = item.querySelector('.vendor-name')?.innerText.toLowerCase() || '';
                if (name.includes(term)) item.style.display = 'flex';
                else item.style.display = 'none';
            });
        });
    }

    // ---------------------- Chat Logic ----------------------
    let currentVendorId = null;
    let currentVendorName = '';
    let typingInterval = null;
    let isTyping = false;
    let vendorPeerId = null;
    let peer, localStream, currentCall, pendingCall = null;
    const PEER_ICE = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ]
    };
    
    // Activity ping (online status)
    setInterval(() => {
        fetch('../chat_update_activity', { method: 'POST' });
    }, 30000);
    
    async function fetchVendorPeerIdFromDB(vendorId) {
        try {
            const res = await fetch(`../chat_get_peer_id?vendor_id=${vendorId}`);
            const data = await res.json();
            if (data.peer_id) {
                vendorPeerId = data.peer_id;
                return true;
            }
        } catch (e) {
            console.error('Peer lookup failed', e);
        }
        return false;
    }

    function showMedia(el, stream, visible) {
        if (!el) return;
        el.srcObject = stream || null;
        el.style.display = visible ? 'block' : 'none';
        if (visible && stream) {
            el.play?.().catch(() => {});
        }
    }
    
    function loadMessages() {
        if (!currentVendorId) return;
        fetch(`../chat_get_messages?action=get_messages&vendor_id=${currentVendorId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('chatMessages');
                    container.innerHTML = '';
                    data.messages.forEach(msg => {
                        // Skip internal peer ID messages
                        if (msg.message.startsWith('__PEER_ID__')) {
                            // Save peer ID to DB if from vendor
                            if (msg.sender_type === 'vendor') {
                                const peerId = msg.message.replace('__PEER_ID__', '');
                                vendorPeerId = peerId;
                                fetch('../chat_save_peer_id', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `vendor_id=${currentVendorId}&peer_id=${encodeURIComponent(peerId)}`
                                }).catch(() => {});
                            }
                            return;
                        }
                        // Also handle request for peer ID
                        if (msg.message === '__REQUEST_PEER_ID__' && msg.sender_type === 'vendor') {
                            // Admin should resend its peer ID
                            if (window.myPeerId) {
                                sendMessage(`__PEER_ID__${window.myPeerId}`);
                            }
                            return;
                        }
                        const msgDiv = document.createElement('div');
                        msgDiv.className = `message ${msg.sender_type === 'admin' ? 'message-admin' : 'message-vendor'}`;
                        if (msg.audio_url) {
                            msgDiv.innerHTML = `<div class="audio-message"><audio controls src="${escapeHtml(msg.audio_url)}"></audio><div class="message-time">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div></div>`;
                        } else {
                            msgDiv.innerHTML = `${escapeHtml(msg.message)}<div class="message-time">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>`;
                        }
                        container.appendChild(msgDiv);
                    });
                    container.scrollTop = container.scrollHeight;
                    markAsRead(currentVendorId);
                }
            });
    }

    function markAsRead(vendorId) {
        fetch('../chat_mark_read', { method: 'POST' });
        const activeItem = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"]`);
        if (activeItem) {
            const badge = activeItem.querySelector('.unread-badge');
            if (badge) badge.remove();
        }
    }

    function sendMessage(msg) {
        if (!msg.trim() || !currentVendorId) return;
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send_message&vendor_id=${currentVendorId}&message=${encodeURIComponent(msg)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (!String(msg).startsWith('__')) {
                    document.getElementById('messageInput').value = '';
                }
                loadMessages();
            } else if (!String(msg).startsWith('__')) {
                alert('Failed to send message');
            }
        });
    }

    async function selectVendor(vendorId, vendorName) {
        currentVendorId = vendorId;
        currentVendorName = vendorName;
        vendorPeerId = null;
        window.peerIdSentToVendor = false;
        const headerHtml = `<span>Chat with ${escapeHtml(vendorName)}</span>
                            <div class="call-controls">
                                <button class="call-btn audio-call" id="audioCallBtn" title="Voice Call">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </button>
                                <button class="call-btn video-call" id="videoCallBtn" title="Video Call">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="5" width="14" height="14" rx="2"/>
                                        <polygon points="22 7 16 12 22 17"/>
                                    </svg>
                                </button>
                            </div>`;
        document.getElementById('chatHeader').innerHTML = headerHtml;
        document.getElementById('chatInputArea').style.display = 'flex';
        loadMessages();
        // Attach call button events
        document.getElementById('audioCallBtn')?.addEventListener('click', () => startCall('audio'));
        document.getElementById('videoCallBtn')?.addEventListener('click', () => startCall('video'));
        // Update active class
        document.querySelectorAll('.vendor-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.vendorId == vendorId) el.classList.add('active');
        });
        
        // Get peer ID from DB first
        const found = await fetchVendorPeerIdFromDB(vendorId);
        if (!found) {
            sendMessage('__REQUEST_PEER_ID__');
        }
        
        // Send admin's peer ID
        if (window.myPeerId) {
            sendMessage(`__PEER_ID__${window.myPeerId}`);
            window.peerIdSentToVendor = true;
            fetch('../chat_save_peer_id', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `peer_id=${encodeURIComponent(window.myPeerId)}`
            }).catch(() => {});
        }
        // Start typing detection
        setupTyping();
    }

    // Typing indicator logic
    function sendTypingStart() {
        if (isTyping) return;
        isTyping = true;
        fetch('../chat_typing_ping', { method: 'POST', body: 'action=start' });
        if (typingInterval) clearInterval(typingInterval);
        typingInterval = setInterval(() => {
            fetch('../chat_typing_ping', { method: 'POST', body: 'action=start' });
        }, 3000);
    }
    function sendTypingStop() {
        if (!isTyping) return;
        isTyping = false;
        clearInterval(typingInterval);
        fetch('../chat_typing_ping', { method: 'POST', body: 'action=stop' });
    }
    function setupTyping() {
        const input = document.getElementById('messageInput');
        if (!input) return;
        input.removeEventListener('focus', sendTypingStart);
        input.removeEventListener('input', sendTypingStart);
        input.removeEventListener('blur', sendTypingStop);
        input.addEventListener('focus', sendTypingStart);
        input.addEventListener('input', sendTypingStart);
        input.addEventListener('blur', sendTypingStop);
    }
    function checkTyping() {
        if (!currentVendorId) return;
        fetch(`../chat_get_typing.php?user_id=${currentVendorId}`)
            .then(res => res.json())
            .then(data => {
                const indicator = document.getElementById('typingIndicator');
                if (data.typing) indicator.style.display = 'block';
                else indicator.style.display = 'none';
            });
    }
    setInterval(checkTyping, 2000);

    document.querySelectorAll('.vendor-item').forEach(el => {
        el.addEventListener('click', () => {
            const vid = el.dataset.vendorId;
            const vname = el.dataset.vendorName;
            selectVendor(vid, vname);
        });
    });

    document.getElementById('sendBtn').addEventListener('click', () => {
        const input = document.getElementById('messageInput');
        if (input.value.trim()) sendMessage(input.value.trim());
    });
    document.getElementById('messageInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage(e.target.value.trim());
    });

    // Auto-refresh messages every 3 seconds
    setInterval(() => {
        if (currentVendorId) loadMessages();
    }, 3000);

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    

    // ---------------------- Audio Messages ----------------------
    let mediaRecorder;
    let audioChunks = [];
    let recording = false;

    async function startRecording() {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        mediaRecorder.ondataavailable = event => audioChunks.push(event.data);
        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');
            formData.append('vendor_id', currentVendorId);
            const res = await fetch('../chat_upload_audio', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) loadMessages();
            else alert('Audio upload failed');
        };
        mediaRecorder.start();
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
    }

    const micBtn = document.getElementById('micBtn');
    micBtn.addEventListener('click', () => {
        if (!currentVendorId) { alert('Select a vendor first'); return; }
        if (!recording) {
            startRecording();
            recording = true;
            micBtn.classList.add('recording');
        } else {
            stopRecording();
            recording = false;
            micBtn.classList.remove('recording');
        }
    });

    // ---------------------- Voice/Video Calls (PeerJS) ----------------------
    function initPeer() {
        if (typeof Peer === 'undefined') {
            console.error('PeerJS failed to load');
            return;
        }
        peer = new Peer({ config: PEER_ICE, debug: 1 });
        peer.on('open', id => {
            window.myPeerId = id;
            fetch('../chat_save_peer_id', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `peer_id=${encodeURIComponent(id)}`
            }).catch(() => {});
            if (currentVendorId && !window.peerIdSentToVendor) {
                sendMessage(`__PEER_ID__${id}`);
                window.peerIdSentToVendor = true;
            }
        });
        peer.on('disconnected', () => {
            try { peer.reconnect(); } catch (e) {}
        });
        peer.on('error', err => console.error('Peer error:', err));
        peer.on('call', call => {
            pendingCall = call;
            const type = call.metadata?.type || 'audio';
            const modal = document.getElementById('incomingCallModal');
            const label = document.getElementById('adminCallTypeText');
            if (label) label.textContent = `Vendor is calling (${type === 'video' ? 'Video' : 'Voice'})...`;
            if (modal) modal.style.display = 'flex';
        });
    }

    async function answerPendingCall() {
        if (!pendingCall) return;
        const call = pendingCall;
        const type = call.metadata?.type || 'audio';
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true });
            showMedia(document.getElementById('localVideo'), localStream, type === 'video');
            call.answer(localStream);
            call.on('stream', remoteStream => {
                showMedia(document.getElementById('remoteVideo'), remoteStream, true);
            });
            call.on('close', () => endCall());
            currentCall = call;
            document.getElementById('callControls').style.display = 'block';
            document.getElementById('incomingCallModal').style.display = 'none';
            pendingCall = null;
        } catch (err) {
            alert('Could not access camera/mic: ' + err.message);
            declinePendingCall();
        }
    }

    function declinePendingCall() {
        if (pendingCall) {
            try { pendingCall.close(); } catch (e) {}
        }
        pendingCall = null;
        const modal = document.getElementById('incomingCallModal');
        if (modal) modal.style.display = 'none';
    }

    async function startCall(type) {
        if (!peer || peer.destroyed) {
            alert('Calling system is still connecting. Try again in a second.');
            initPeer();
            return;
        }
        if (!vendorPeerId && currentVendorId) {
            await fetchVendorPeerIdFromDB(currentVendorId);
        }
        if (!vendorPeerId) {
            sendMessage('__REQUEST_PEER_ID__');
            alert('Vendor is not ready for calls yet. Ask them to keep Vendor Chat open, then try again.');
            return;
        }
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true });
            showMedia(document.getElementById('localVideo'), localStream, type === 'video');
            const call = peer.call(vendorPeerId, localStream, { metadata: { type: type } });
            if (!call) {
                alert('Could not start the call. Refresh both chat pages and try again.');
                endCall();
                return;
            }
            call.on('stream', remoteStream => {
                showMedia(document.getElementById('remoteVideo'), remoteStream, true);
            });
            call.on('close', () => endCall());
            call.on('error', err => {
                console.error(err);
                alert('Call failed: ' + (err.message || err.type || 'connection error'));
                endCall();
            });
            currentCall = call;
            document.getElementById('callControls').style.display = 'block';
        } catch (err) {
            alert('Could not access camera/mic: ' + err.message + '\nAllow microphone' + (type === 'video' ? '/camera' : '') + ' permissions and use HTTPS.');
        }
    }

    function endCall() {
        if (currentCall) {
            try { currentCall.close(); } catch (e) {}
        }
        if (localStream) localStream.getTracks().forEach(track => track.stop());
        showMedia(document.getElementById('localVideo'), null, false);
        showMedia(document.getElementById('remoteVideo'), null, false);
        document.getElementById('callControls').style.display = 'none';
        currentCall = null;
        localStream = null;
    }

    document.getElementById('endCallBtn')?.addEventListener('click', endCall);
    document.getElementById('adminAcceptCallBtn')?.addEventListener('click', answerPendingCall);
    document.getElementById('adminDeclineCallBtn')?.addEventListener('click', declinePendingCall);
    
    // Initialize peer
    initPeer();
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
