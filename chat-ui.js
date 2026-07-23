// =============================================================
// HELPER FUNCTIONS
// =============================================================
function formatSmartChatTime(dbTimestamp) {
    // Convert the database timestamp string into a JavaScript Date object
    const date = new Date(dbTimestamp);
    const now = new Date();

    // Check if the message is from today
    const isToday = date.getDate() === now.getDate() && 
                    date.getMonth() === now.getMonth() && 
                    date.getFullYear() === now.getFullYear();
    
    // Create a date object for exactly yesterday
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    
    // Check if the message is from yesterday
    const isYesterday = date.getDate() === yesterday.getDate() && 
                        date.getMonth() === yesterday.getMonth() && 
                        date.getFullYear() === yesterday.getFullYear();

    // Format the time part (e.g., "03:15 PM")
    const timeString = date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });

    // Return the smart string
    if (isToday) {
        return timeString; 
    } else if (isYesterday) {
        return "Yesterday at " + timeString;
    } else {
        // For older messages: "Jul 18 at 03:15 PM"
        const dateString = date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
        return dateString + " at " + timeString;
    }
}


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

// Handles showing the preview bar when a user selects a file
window.handleFileSelectionEvent = function(input) {
    const previewBar = document.getElementById('attachment-preview-bar');
    const fileNameDisplay = document.getElementById('preview-file-name');
    const fileIcon = document.getElementById('preview-file-icon');

    if (input.files && input.files.length > 0) {
        const file = input.files[0];
        
        // Update the text to the file's name
        fileNameDisplay.textContent = file.name;
        
        // Optionally change the icon based on file type
        if (file.type.startsWith('image/')) {
            fileIcon.setAttribute('data-lucide', 'image');
        } else {
            fileIcon.setAttribute('data-lucide', 'file-text');
        }
        
        // Re-render lucide icons to apply the change
        if (window.lucide) {
            lucide.createIcons();
        }
        
        // Reveal the preview bar
        previewBar.classList.remove('hidden');
    }
};

// Clears the selected file if the user cancels or submits
window.clearSelectedAttachment = function() {
    const input = document.getElementById('chat-file-input');
    const previewBar = document.getElementById('attachment-preview-bar');
    
    input.value = ''; // Clear the actual input
    previewBar.classList.add('hidden'); // Hide the UI bar
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
