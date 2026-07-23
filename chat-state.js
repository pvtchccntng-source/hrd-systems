// =============================================================
// GLOBAL STATE ENGINE CONFIGURATION
// =============================================================
window.isChatOpen = false;
window.isProfileBarOpen = false; 
window.unreadCount = 0;
window.chatPollTimer = null;
window.totalMessagesCached = 0;
window.currentEditMessageId = null;
window.currentReplyMessageId = null;
window.highestMessageIdAlerted = 0;
window.syncAbortController = null;

// @MENTION SYSTEM TRACKING STATES
window.appCachedUsers = [];
window.mentionSearchTerm = "";
window.mentionStartIdx = -1;
window.selectedMentionRowIdx = 0;
window.activeFilteredMatches = [];

// =============================================================
// CORE LOOKUP UTILITIES
// =============================================================
window.escapeHTML = function(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[tag] || tag));
};

window.scrollChatToBottom = function() {
    const container = document.getElementById('chat-messages-container');
    if (container) container.scrollTop = container.scrollHeight;
};

window.triggerToastAlert = function(messageText, isFailure = false) {
    const toastBlock = document.getElementById('toast');
    const iconNode = document.getElementById('toast-icon');
    const messageNode = document.getElementById('toast-message');

    messageNode.innerText = messageText;
    if (isFailure) {
        iconNode.setAttribute('data-lucide', 'alert-circle');
        iconNode.className = "w-5 h-5 text-rose-400";
    } else {
        iconNode.setAttribute('data-lucide', 'check-circle');
        iconNode.className = "w-5 h-5 text-emerald-400";
    }
    
    lucide.createIcons();
    toastBlock.classList.remove('translate-y-20', 'opacity-0');
    
    setTimeout(() => {
        toastBlock.classList.add('translate-y-20', 'opacity-0');
    }, 3500);
};

window.handleSessionExpired = function() {
    console.warn("User workspace context dropping. Polling paused.");
    if (window.chatPollTimer) {
        clearInterval(window.chatPollTimer);
        window.chatPollTimer = null;
    }
    
    // ?? Notify the user on screen
    window.triggerToastAlert("Session expired! Reloading login terminal...", true);
    
    // ?? Refresh index.php automatically after 2 seconds
    setTimeout(() => {
        window.location.reload();
    }, 2000);
};
