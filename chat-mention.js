// =============================================================
// @MENTION AUTOCOMPLETE SUGGESTION ENGINE
// =============================================================
window.downloadSystemWorkspaceDirectory = async function() {
    try {
        const response = await fetch('chat_handler.php?action=list_users', { credentials: 'same-origin' });
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                window.appCachedUsers = [{ username: 'everyone', full_name: 'Everyone' }, ...data.users];
            }
        }
    } catch (err) {
        console.error("Failed loading autocomplete users context mapping:", err);
    }
};

window.createAutocompleteContainerPanel = function() {
    const inputField = document.getElementById('chat-input-field');
    if (!inputField) return;

    let menuBox = document.getElementById('mention-autocomplete-menu');
    if (!menuBox) {
        menuBox = document.createElement('div');
        menuBox.id = 'mention-autocomplete-menu';
        menuBox.className = "absolute hidden bg-white/95 backdrop-blur-md border border-slate-200/80 rounded-xl shadow-xl max-h-44 overflow-y-auto w-52 z-50 text-xs flex flex-col p-1 bottom-14 left-4 transition-all duration-150 transform scale-95 origin-bottom-left";
        inputField.parentNode.insertBefore(menuBox, inputField);
    }
};

window.scanInputFieldForMentions = function(event) {
    const input = event.target;
    const text = input.value;
    const caretPos = input.selectionStart;

    const textBeforeCaret = text.substring(0, caretPos);
    const lastAtIdx = textBeforeCaret.lastIndexOf('@');

    if (lastAtIdx !== -1) {
        const textAfterAt = textBeforeCaret.substring(lastAtIdx + 1);
        if (!textAfterAt.includes(' ')) {
            window.mentionStartIdx = lastAtIdx;
            window.mentionSearchTerm = textAfterAt.toLowerCase();
            window.evaluateFilterMatches();
            return;
        }
    }
    window.closeMentionMenuPanel();
};

window.evaluateFilterMatches = function() {
    if (!window.mentionSearchTerm) {
        window.activeFilteredMatches = [...window.appCachedUsers];
    } else {
        window.activeFilteredMatches = window.appCachedUsers.filter(u => 
            (u.full_name && u.full_name.toLowerCase().includes(window.mentionSearchTerm)) ||
            (u.username && u.username.toLowerCase().includes(window.mentionSearchTerm))
        );
    }

    if (window.activeFilteredMatches.length > 0) {
        if (window.selectedMentionRowIdx >= window.activeFilteredMatches.length) {
            window.selectedMentionRowIdx = 0;
        }
        window.renderMentionSuggestions();
    } else {
        window.closeMentionMenuPanel();
    }
};

window.renderMentionSuggestions = function() {
    const box = document.getElementById('mention-autocomplete-menu');
    if (!box) return;

    box.innerHTML = '';
    window.activeFilteredMatches.forEach((user, idx) => {
        const btn = document.createElement('button');
        btn.type = "button";
        btn.className = `w-full text-left px-3 py-2 rounded-lg font-medium transition-colors flex flex-col ${
            idx === window.selectedMentionRowIdx 
            ? 'bg-slate-900 text-white' 
            : 'text-slate-700 hover:bg-slate-100'
        }`;
        
        const displayTitle = user.full_name || user.username;
        const subtitleMarkup = user.username && user.username !== user.full_name ? `<span class="text-[9px] ${idx === window.selectedMentionRowIdx ? 'text-slate-400' : 'text-slate-400'}">@${user.username}</span>` : '';
       
        btn.innerHTML = `<span>${window.escapeHTML(displayTitle)}</span>${subtitleMarkup}`;
        btn.onclick = () => window.confirmSelectedMention(user);
        box.appendChild(btn);
    });
    box.classList.remove('hidden', 'scale-95', 'opacity-0');
};

window.confirmSelectedMention = function(userObj) {
    const input = document.getElementById('chat-input-field');
    const text = input.value;
    const caretPos = input.selectionStart;

    const handleString = userObj.username || userObj.full_name;
    const replacementToken = `@[${handleString}] `;
    const leftStringSegment = text.substring(0, window.mentionStartIdx);
    const rightStringSegment = text.substring(caretPos);

    input.value = leftStringSegment + replacementToken + rightStringSegment;
    window.closeMentionMenuPanel();
    const newCaretPos = window.mentionStartIdx + replacementToken.length;
    input.setSelectionRange(newCaretPos, newCaretPos);
    input.focus();
};

window.interceptInputKeydowns = function(event) {
    const box = document.getElementById('mention-autocomplete-menu');
    if (!box || box.classList.contains('hidden')) return;
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            window.selectedMentionRowIdx = (window.selectedMentionRowIdx + 1) % window.activeFilteredMatches.length;
            window.renderMentionSuggestions();
            break;
        case 'ArrowUp':
            event.preventDefault();
            window.selectedMentionRowIdx = (window.selectedMentionRowIdx - 1 + window.activeFilteredMatches.length) % window.activeFilteredMatches.length;
            window.renderMentionSuggestions();
            break;
        case 'Enter':
            event.preventDefault();
            if (window.activeFilteredMatches[window.selectedMentionRowIdx]) {
                window.confirmSelectedMention(window.activeFilteredMatches[window.selectedMentionRowIdx]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            window.closeMentionMenuPanel();
            break;
    }
};

window.closeMentionMenuPanel = function() {
    const box = document.getElementById('mention-autocomplete-menu');
    if (box) {
        box.classList.add('hidden', 'scale-95', 'opacity-0');
    }
    window.selectedMentionRowIdx = 0;
    window.activeFilteredMatches = [];
};
