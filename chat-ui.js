// =============================================================
// VIEWPORT PANELS & UI INTERACTION CONTROLLERS
// =============================================================
window.toggleChatSystem = function() {
    const panel = document.getElementById('chat-box-panel');
    const badge = document.getElementById('chat-unread-badge');
    window.isChatOpen = !window.isChatOpen;
    
    if (window.isChatOpen) {
        panel.classList.remove('hidden');
        if (window.currentUser) {
            window.unreadCount = 0;
            badge.innerText = "0";
            badge.classList.add('hidden');
            window.scrollChatToBottom();
            document.getElementById('chat-input-field').focus();
        }
    } else {
        panel.classList.add('hidden');
        if (window.isProfileBarOpen) window.toggleProfileSettings();
        window.closeMentionMenuPanel(); 
    }
    if (window.currentUser) window.syncChatWire();
};

window.toggleProfileSettings = function() {
    if (!window.currentUser) return;
    const profileBar = document.getElementById('profile-settings-bar');
    window.isProfileBarOpen = !window.isProfileBarOpen;
    
    if (window.isProfileBarOpen) {
        profileBar.classList.remove('hidden');
        document.getElementById('profile-fullname-input').focus();
    } else {
        profileBar.classList.add('hidden');
        document.getElementById('profile-fullname-input').value = "";
    }
};

window.handleFileSelectionEvent = function(input) {
    if (!window.currentUser) return;
    if (window.currentEditMessageId) window.cancelEditingState();
    const file = input.files[0];
    if (!file) return;

    document.getElementById('preview-file-name').innerText = file.name;
    const iconElement = document.getElementById('preview-file-icon');
    if (file.type.startsWith('image/')) {
        iconElement.setAttribute('data-lucide', 'image');
    } else {
        iconElement.setAttribute('data-lucide', 'file-text');
    }
    lucide.createIcons();
    document.getElementById('attachment-preview-bar').classList.remove('hidden');
    document.getElementById('chat-input-field').removeAttribute('required');
};

window.clearSelectedAttachment = function() {
    if (!window.currentUser) return;
    document.getElementById('chat-file-input').value = "";
    document.getElementById('attachment-preview-bar').classList.add('hidden');
    document.getElementById('chat-input-field').setAttribute('required', 'true');
};

window.toggleGifPicker = function() {
    if (!window.currentUser) return;
    const pickerPanel = document.getElementById('gif-picker-panel');
    const isHidden = pickerPanel.classList.contains('hidden');
    
    if (isHidden) {
        pickerPanel.classList.remove('hidden');
        window.loadGifsFromServer();
    } else {
        pickerPanel.classList.add('hidden');
    }
};

window.initiateReplyMode = function(id, element) {
    if (!window.currentUser) return;
    if (window.currentEditMessageId) window.cancelEditingState();
    window.currentReplyMessageId = id;
    const groupNode = element.closest('.group');
    const userHeader = groupNode.querySelector('.font-bold').innerText;
    const rawTextContainer = groupNode.querySelector('.raw-text-container');
    const msgText = rawTextContainer ? rawTextContainer.innerText : "Attachment file element asset item";

    document.getElementById('reply-target-user').innerText = userHeader;
    document.getElementById('reply-target-text').innerText = msgText;
    document.getElementById('reply-indicator-bar').classList.remove('hidden');
    document.getElementById('chat-input-field').focus();
};

window.cancelReplyState = function() {
    if (!window.currentUser) return;
    window.currentReplyMessageId = null;
    document.getElementById('reply-indicator-bar').classList.add('hidden');
};

window.initiateEditMode = function(id, element) {
    if (!window.currentUser) return;
    if (window.currentReplyMessageId) window.cancelReplyState();
    window.clearSelectedAttachment();
    window.currentEditMessageId = id;
    const msgText = element.closest('.group').querySelector('.raw-text-container').innerText;
    document.getElementById('chat-input-field').value = msgText;
    document.getElementById('edit-indicator-bar').classList.remove('hidden');
    document.getElementById('chat-input-field').focus();
    document.getElementById('submit-icon').setAttribute('data-lucide', 'check');
    lucide.createIcons();
};

window.cancelEditingState = function() {
    if (!window.currentUser) return;
    window.currentEditMessageId = null;
    document.getElementById('chat-input-field').value = "";
    document.getElementById('edit-indicator-bar').classList.add('hidden');
    document.getElementById('submit-icon').setAttribute('data-lucide', 'send');
    lucide.createIcons();
};