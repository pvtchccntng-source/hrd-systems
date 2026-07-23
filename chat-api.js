// =============================================================
// API DATA STREAMS & DATA WIRE BACKEND TRANSACTION HANDLERS
// =============================================================

// Array to hold local, unsent messages to survive background polling refreshes
window.optimisticMessages = window.optimisticMessages || [];

window.syncChatWire = async function() {
    if (!window.currentUser) return;
    
    // ?? BUG FIX 1: Generate a unique Request ID for this specific fetch cycle
    window.currentSyncRequestId = (window.currentSyncRequestId || 0) + 1;
    const mySyncRequestId = window.currentSyncRequestId;

    // ?? NEW FIX: Abort the previous lingering fetch request to free up network sockets
    if (window.syncAbortController) {
        window.syncAbortController.abort();
    }
    window.syncAbortController = new AbortController();

    try {
        const response = await fetch(`chat_handler.php?action=fetch&username=${encodeURIComponent(window.currentUser)}&is_open=${window.isChatOpen ? 1 : 0}`, {
            credentials: 'same-origin',
            signal: window.syncAbortController.signal // <--- Attach the abort signal here
        });
        
        if (response.status === 401) {
            window.handleSessionExpired();
            return;
        }

        if (!response.ok) throw new Error(`HTTP network anomaly: ${response.status}`);
        const data = await response.json();
        
        // ?? BUG FIX 2: If a newer fetch started...
        if (window.currentSyncRequestId !== mySyncRequestId) {
            return; 
        }
        
        if (data.messages) {
            const container = document.getElementById('chat-messages-container');
            const shouldScroll = container.scrollHeight - container.scrollTop <= container.clientHeight + 120;
            const badge = document.getElementById('chat-unread-badge');
            
            if (window.highestMessageIdAlerted === 0 && data.messages.length > 0) {
                window.highestMessageIdAlerted = Math.max(...data.messages.map(m => parseInt(m.id)));
            }

            container.innerHTML = '';
            data.messages.forEach((msg) => {
                if (msg.sender === 'System') {
                    const msgElement = document.createElement('div');
                    msgElement.classList.add('flex', 'justify-center', 'w-full', 'my-2', 'clear-both');
                    msgElement.innerHTML = `
                        <div class="bg-slate-100/80 text-slate-500 text-[10px] font-medium px-3 py-0.5 rounded-full border border-slate-200/60 shadow-xs italic tracking-wide select-none">
                            ${window.escapeHTML(msg.message)}
                        </div>
                    `;
                    container.appendChild(msgElement);
                    return;
                }

                const isMe = msg.sender === window.currentUser;
                const msgIdInt = parseInt(msg.id);
                
                if (msgIdInt > window.highestMessageIdAlerted && !isMe) {
                    window.highestMessageIdAlerted = msgIdInt;
                }

const timestamp = new Date(msg.created_at).toLocaleString([], { 
    month: 'short', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: true 
});
                const editedLabel = parseInt(msg.is_edited) ? ' <span class="text-[9px] opacity-60">(edited)</span>' : '';

                const msgElement = document.createElement('div');
                msgElement.classList.add('flex', 'flex-col', 'w-full');
                
                if (parseInt(msg.is_deleted)) {
                    msgElement.innerHTML = `
                        <div class="max-w-[85%] p-2.5 rounded-2xl text-xs italic text-slate-400 border border-dashed border-slate-200 ${isMe ? 'self-end rounded-tr-none' : 'self-start rounded-tl-none'}">
                            ${isMe ? 'You unsent a message' : 'This message was unsent'}
                        </div>
                    `;
                } else {
                    const reactionCounts = {};
                    (msg.reactions || []).forEach(r => {
                        reactionCounts[r.emoji] = (reactionCounts[r.emoji] || 0) + 1;
                    });
                    
                    let reactionsMarkup = '';
                    if (Object.keys(reactionCounts).length > 0) {
                        reactionsMarkup = `<div class="flex gap-1 mt-1 flex-wrap ${isMe ? 'justify-end' : 'justify-start'}">`;
                        for (const [emoji, count] of Object.entries(reactionCounts)) {
                            reactionsMarkup += `
                                <button onclick="window.sendReaction(${msg.id}, '${emoji}')" class="bg-white hover:bg-slate-100 text-slate-800 px-1.5 py-0.5 rounded-full text-[10px] flex items-center gap-1 border border-slate-200/80 transition-colors shadow-xs">
                                    <span>${emoji}</span> <span class="font-bold text-[9px] text-slate-500">${count}</span>
                                </button>
                            `;
                        }
                        reactionsMarkup += `</div>`;
                    }

                    const othersWhoSeen = (msg.seen_by || []).filter(user => 
                        user.toLowerCase() !== msg.sender.toLowerCase() && 
                        user.toLowerCase() !== (msg.sender_display_name || '').toLowerCase()
                    );
                    
                    let statusReceiptMarkup = '';
                    if (othersWhoSeen.length > 0) {
                        statusReceiptMarkup = `<div class="text-[9px] text-slate-400 font-medium mt-1 flex items-center gap-0.5 ${isMe ? 'justify-end' : 'justify-start'}">
                            <i data-lucide="check-check" class="w-3 h-3 text-emerald-500"></i> Seen by ${othersWhoSeen.join(', ')}
                        </div>`;
                    } else if (isMe) {
                        statusReceiptMarkup = `<div class="text-[9px] text-slate-400 mt-1 flex items-center gap-0.5 justify-end">
                            <i data-lucide="check" class="w-3 h-3 text-slate-400"></i> Delivered
                        </div>`;
                    }

                    let structuralQuoteMarkup = '';
                    if (msg.reply_to_id) {
                        const displaySender = window.escapeHTML(msg.reply_sender || 'User');
                        const displayText = parseInt(msg.reply_parent_deleted) ? 'Original message unsent' : window.escapeHTML(msg.reply_message || 'Attachment file data');
                        structuralQuoteMarkup = `
                            <div class="bg-slate-200/60 text-slate-600 rounded-xl p-2 mb-1 text-[11px] border-l-4 border-slate-400 max-w-[90%] truncate text-left select-none ${isMe ? 'self-end' : 'self-start'}">
                                <span class="font-bold block text-[9px] opacity-80">${displaySender}</span>
                                <span class="italic">${displayText}</span>
                            </div>
                        `;
                    }

                    let structuralMediaMarkup = '';
                    if (msg.file_path) {
                        if (msg.file_type === 'image') {
                            structuralMediaMarkup = `
                                <div class="mt-1.5 max-w-[200px] overflow-hidden rounded-xl border border-slate-200 shadow-xs cursor-pointer hover:opacity-90 transition-opacity">
                                    <a href="${msg.file_path}" target="_blank">
                                        <img src="${msg.file_path}" class="w-full h-auto object-cover max-h-40" alt="Shared graphics file block">
                                    </a>
                                </div>
                            `;
                        } else {
                            const cleanFileName = msg.file_path.substring(msg.file_path.indexOf('_') + 1);
                            structuralMediaMarkup = `
                                <a href="${msg.file_path}" target="_blank" class="mt-1.5 flex items-center gap-2 p-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition-colors border border-slate-200 text-slate-700 max-w-[85%] text-left">
                                    <i data-lucide="file-down" class="w-4 h-4 text-slate-500 shrink-0"></i>
                                    <div class="truncate flex flex-col">
                                        <span class="font-medium text-[11px] truncate">${window.escapeHTML(cleanFileName)}</span>
                                        <span class="text-[9px] text-slate-400 uppercase tracking-wider font-bold">Download File</span>
                                    </div>
                                </a>
                            `;
                        }
                    }

                    let processedMessageContent = window.escapeHTML(msg.message);
                    if (msg.message) {
                        processedMessageContent = processedMessageContent.replace(/@\[([^\]]+)\]/g, '<span class="bg-emerald-500/20 text-emerald-400 px-1 py-0.5 rounded-md font-semibold font-mono border border-emerald-500/20 shadow-xs">@$1</span>');
                    }

                    msgElement.innerHTML = `
                        ${structuralQuoteMarkup}
                        <div class="flex items-center gap-2 group w-full relative ${isMe ? 'flex-row-reverse' : 'flex-row'}">
                            <div class="max-w-[85%] p-2.5 rounded-2xl text-xs shadow-xs transition-all flex flex-col ${isMe ? 'bg-slate-900 text-white self-end rounded-tr-none' : 'bg-white text-slate-800 border border-slate-200 self-start rounded-tl-none'}">
                                <span class="font-bold text-[10px] ${isMe ? 'text-emerald-400' : 'text-slate-500'} mb-0.5 block text-left">${window.escapeHTML(msg.sender_display_name || msg.sender)}</span>
                                ${msg.message ? (() => {
                                    const gifMatch = msg.message.match(/^\[GIF:(.+)\]$/);
if (gifMatch) {
    const gifFileName = gifMatch[1];
    return `
        <div class="mt-1 max-w-[140px] overflow-hidden rounded-xl border border-slate-200/40 shadow-xs bg-slate-50">
            <img src="gif/${gifFileName}" class="w-full h-auto object-cover" alt="Chat GIF Asset">
        </div>
    `;
}
                                    return `<p class="leading-relaxed break-words text-left raw-text-container">${processedMessageContent}</p>`;
                                })() : ''}
                                ${structuralMediaMarkup}
                                <span class="text-[9px] text-right mt-1 opacity-70 block">${timestamp}${editedLabel}</span>
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-all duration-150 bg-white border border-slate-200 rounded-xl p-1 shadow-md absolute -top-8 ${isMe ? 'right-2' : 'left-2'} z-10">
                                <button onclick="window.sendReaction(${msg.id}, '??')" class="hover:scale-130 active:scale-95 transition-transform px-0.5 text-sm">??</button>
                                <button onclick="window.sendReaction(${msg.id}, '??')" class="hover:scale-130 active:scale-95 transition-transform px-0.5 text-sm">??</button>
                                <button onclick="window.sendReaction(${msg.id}, '??')" class="hover:scale-130 active:scale-95 transition-transform px-0.5 text-sm">??</button>
                                <button onclick="window.sendReaction(${msg.id}, '??')" class="hover:scale-130 active:scale-95 transition-transform px-0.5 text-sm">??</button>
                                <button onclick="window.sendReaction(${msg.id}, '??')" class="hover:scale-130 active:scale-95 transition-transform px-0.5 text-sm">??</button>
                                <div class="w-px h-3 bg-slate-200 mx-1"></div>
                                <button onclick="window.initiateReplyMode(${msg.id}, this)" title="Reply" class="p-1 hover:bg-slate-100 rounded-lg text-slate-500 transition-colors">
                                    <i data-lucide="reply" class="w-3 h-3"></i>
                                </button>
                                ${isMe ? `
                                    ${msg.message ? `
                                    <button onclick="window.initiateEditMode(${msg.id}, this)" title="Edit Text" class="p-1 hover:bg-slate-100 rounded-lg text-slate-500 transition-colors">
                                        <i data-lucide="pencil" class="w-3 h-3"></i>
                                    </button>
                                    ` : ''}
                                    <button onclick="window.triggerMessageDeletion(${msg.id})" title="Unsend" class="p-1 hover:bg-rose-50 rounded-lg text-rose-600 transition-colors">
                                        <i data-lucide="trash-2" class="w-3 h-3"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                        ${reactionsMarkup}
                        ${statusReceiptMarkup}
                    `;
                }
                container.appendChild(msgElement);
            });

            // ?? OPTIMISTIC UI FIX: Append any active local "sending..." elements right here 
            // so they don't disappear when the background polling loop clears the container.
            if (window.optimisticMessages && window.optimisticMessages.length > 0) {
                window.optimisticMessages.forEach((optMsg) => {
                    window.appendOptimisticUIToContainer(optMsg);
                });
            }

            lucide.createIcons();

            if (window.isChatOpen) {
                if (shouldScroll || data.messages.length > window.totalMessagesCached) {
                    window.scrollChatToBottom();
                }
                badge.innerText = "0";
                badge.classList.add('hidden');
            } else {
                let dynamicUnreadCount = 0;
                data.messages.forEach(msg => {
                    const isMe = msg.sender === window.currentUser;
                    const hasSeen = msg.seen_by && msg.seen_by.includes(window.currentUser);
                    if (!isMe && !hasSeen && !parseInt(msg.is_deleted) && msg.sender !== 'System') {
                        dynamicUnreadCount++;
                    }
                });
                if (dynamicUnreadCount > 0) {
                    badge.innerText = dynamicUnreadCount;
                    badge.classList.remove('hidden');
                } else {
                    badge.innerText = "0";
                    badge.classList.add('hidden');
                }
            }
            window.totalMessagesCached = data.messages.length;
        }
} catch (err) {
        if (err.name === 'AbortError') {
            return; // Silently ignore requests that we intentionally cancelled
        }
        console.error("Stream sync error:", err);
    }
};

// ?? OPTIMISTIC UI HELPER: Generates the DOM representation for instant client display
window.appendOptimisticUIToContainer = function(msg) {
    const container = document.getElementById('chat-messages-container');
    if (!container) return;

  const timestamp = new Date(msg.created_at).toLocaleString([], { 
    month: 'short', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: true 
});
    const msgElement = document.createElement('div');
    msgElement.classList.add('flex', 'flex-col', 'w-full', 'optimistic-sending-node');

    let structuralMediaMarkup = '';
    if (msg.file_path) {
        if (msg.file_type === 'image') {
            structuralMediaMarkup = `
                <div class="mt-1.5 max-w-[200px] overflow-hidden rounded-xl border border-slate-200/80 shadow-xs opacity-60">
                    <img src="${msg.file_path}" class="w-full h-auto object-cover max-h-40" alt="Uploading graphic asset...">
                </div>
            `;
        } else {
            structuralMediaMarkup = `
                <div class="mt-1.5 flex items-center gap-2 p-2 rounded-xl bg-slate-100 border border-slate-200 text-slate-400 max-w-[85%] text-left opacity-60">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-slate-400 animate-spin shrink-0"></i>
                    <div class="truncate flex flex-col">
                        <span class="font-medium text-[11px] truncate text-slate-500">Uploading File...</span>
                    </div>
                </div>
            `;
        }
    }

    let processedMessageContent = window.escapeHTML(msg.message);
    if (msg.message) {
        processedMessageContent = processedMessageContent.replace(/@\[([^\]]+)\]/g, '<span class="bg-emerald-500/20 text-emerald-400 px-1 py-0.5 rounded-md font-semibold font-mono border border-emerald-500/20 shadow-xs">@$1</span>');
    }

    msgElement.innerHTML = `
        <div class="flex items-center gap-2 group w-full relative flex-row-reverse opacity-75">
            <div class="max-w-[85%] p-2.5 rounded-2xl text-xs shadow-xs transition-all flex flex-col bg-slate-900 text-white self-end rounded-tr-none border border-slate-800">
                <span class="font-bold text-[10px] text-emerald-400 mb-0.5 block text-left">${window.escapeHTML(msg.sender_display_name || msg.sender)}</span>
                ${msg.message ? `<p class="leading-relaxed break-words text-left raw-text-container">${processedMessageContent}</p>` : ''}
                ${structuralMediaMarkup}
                <span class="text-[9px] text-right mt-1 opacity-50 block">${timestamp}</span>
            </div>
        </div>
        <div class="text-[9px] text-slate-400 mt-1 flex items-center gap-1 justify-end italic select-none animate-pulse">
            <i data-lucide="refresh-cw" class="w-2.5 h-2.5 animate-spin text-slate-400"></i> Sending...
        </div>
    `;
    container.appendChild(msgElement);
};

window.updateProfileFullName = async function(event) {
    event.preventDefault();
    if (!window.currentUser) return;

    const inputField = document.getElementById('profile-fullname-input');
    const updatedName = inputField.value.trim();
    if (!updatedName) return;

    try {
        const response = await fetch('chat_handler.php?action=profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ full_name: updatedName }),
            credentials: 'same-origin'
        });

        if (response.status === 401) { 
            window.handleSessionExpired();
            return; 
        }

        if (!response.ok) throw new Error(`Server execution fault: ${response.status}`);
        const data = await response.json();

        if (data.success) {
            window.triggerToastAlert("Display name updated successfully!", false);
            window.currentUser = updatedName;
            window.toggleProfileSettings();
            window.syncChatWire(); 
            window.downloadSystemWorkspaceDirectory(); 
        } else {
            window.triggerToastAlert(data.error || "Could not complete profile save task.", true);
        }
    } catch (err) {
        console.error("Profile dispatch stream crash:", err);
        window.triggerToastAlert("Network error encountered during save operation.", true);
    }
};

window.loadGifsFromServer = async function() {
    const grid = document.getElementById('gif-list-grid');
    grid.innerHTML = `<div class="col-span-3 text-center text-[10px] text-slate-400 py-4 animate-pulse">Loading custom wire elements...</div>`;
    try {
        const response = await fetch('chat_handler.php?action=list_gifs', { credentials: 'same-origin' });
        if (!response.ok) throw new Error("Failed to scan folder asset arrays");
        
        const data = await response.json();
        grid.innerHTML = '';
        
if (data.gifs && data.gifs.length > 0) {
    data.gifs.forEach(gifName => {
        const imgBtn = document.createElement('button');
        imgBtn.type = 'button';
        imgBtn.className = "bg-slate-50 border border-slate-100 rounded-lg p-1 hover:border-emerald-500 hover:bg-slate-100 transition-all overflow-hidden focus:outline-none flex justify-center items-center h-16 shadow-xs";
        imgBtn.onclick = () => window.sendCustomGifMessage(gifName);
        imgBtn.innerHTML = `<img src="gif/${gifName}" class="w-full h-full object-cover rounded-md" alt="${gifName}">`; // Updated path
        grid.appendChild(imgBtn);
    });
}else {
            grid.innerHTML = `<div class="col-span-3 text-center text-[10px] text-slate-400 py-4">No custom GIFs found inside /gif/</div>`;
        }
    } catch (err) {
        console.error("GIF indexing runtime execution break:", err);
        grid.innerHTML = `<div class="col-span-3 text-center text-[10px] text-rose-500 py-4">Error accessing local media directories.</div>`;
    }
};

window.sendCustomGifMessage = async function(gifName) {
    if (!window.currentUser) return;
    document.getElementById('gif-picker-panel').classList.add('hidden');
    const formDataPayload = new FormData();
    formDataPayload.append('sender', window.currentUser);
    formDataPayload.append('message', `[GIF:${gifName}]`);
    
    if (window.currentReplyMessageId) {
        formDataPayload.append('reply_to_id', window.currentReplyMessageId);
        window.cancelReplyState();
    }

    try {
        const response = await fetch('chat_handler.php?action=send', { 
            method: 'POST', 
            body: formDataPayload,
            credentials: 'same-origin'
        });
        if (response.status === 401) { window.handleSessionExpired(); return; }
        if (response.ok) {
            const data = await response.json();
            if (data.success) window.syncChatWire();
        }
    } catch (err) { 
        console.error("GIF payload dispatch error:", err);
    }
};

window.sendReaction = async function(messageId, emojiCharacter) {
    if (!window.currentUser) return;
    try {
        const response = await fetch('chat_handler.php?action=react', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message_id: messageId,
                username: window.currentUser,
                emoji: emojiCharacter
            }),
            credentials: 'same-origin'
        });
        if (response.status === 401) { window.handleSessionExpired(); return; }
        if (response.ok) {
            const data = await response.json();
            if (data.success) window.syncChatWire();
        }
    } catch (err) { console.error(err); }
};

window.triggerMessageDeletion = async function(id) {
    if (!window.currentUser) return;
    if (!confirm("Are you sure you want to unsend this message?")) return;
    try {
        const response = await fetch('chat_handler.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id }),
            credentials: 'same-origin'
        });
        if (response.status === 401) { window.handleSessionExpired(); return; }
        if (response.ok) {
            const data = await response.json();
            if (data.success) window.syncChatWire();
        }
    } catch (err) { console.error(err); }
};

window.dispatchChatMessage = async function(event) {
    if (!window.currentUser) return;
    event.preventDefault();
    const input = document.getElementById('chat-input-field');
    const fileInput = document.getElementById('chat-file-input');
    const msgText = input.value.trim();
    const isEditing = window.currentEditMessageId !== null;

    if (isEditing) {
        if (!msgText) return;
        const targetEditId = window.currentEditMessageId;
        try {
            const response = await fetch('chat_handler.php?action=edit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: targetEditId, message: msgText }),
                credentials: 'same-origin'
            });
            if (response.status === 401) { 
                window.handleSessionExpired();
                return; 
            }
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    input.value = "";
                    window.cancelEditingState();
                    window.syncChatWire();
                    window.triggerToastAlert("Message updated successfully!", false);
                } else {
                    window.triggerToastAlert(data.error || "Could not save message modifications.", true);
                }
            } else {
                throw new Error(`Server returned status code ${response.status}`);
            }
        } catch (err) { 
            console.error("Message edit crash:", err);
            window.triggerToastAlert("Network error encountered during edit operation.", true);
        }
        return;
    }

    // Guard to ensure we aren't dispatching completely empty payloads
    if (!msgText && fileInput.files.length === 0) return;

    // ?? 1. OPTIMISTIC UPDATE: Generate tracking details for instant layout rendering
    const tempMessageId = 'temp_' + Date.now();
    let localBlobPath = null;
    let localBlobType = null;
    
    if (fileInput.files.length > 0) {
        localBlobPath = URL.createObjectURL(fileInput.files[0]);
        localBlobType = fileInput.files[0].type.startsWith('image/') ? 'image' : 'document';
    }

    const temporaryPayloadNode = {
        id: tempMessageId,
        sender: window.currentUser,
        sender_display_name: window.currentUser,
        message: msgText,
        file_path: localBlobPath,
        file_type: localBlobType,
        created_at: new Date().toISOString()
    };

    // Store temporarily so standard syncChatWire background loops don't erase it
    window.optimisticMessages.push(temporaryPayloadNode);

    // Prepare true network payload properties
    const formDataPayload = new FormData();
    formDataPayload.append('sender', window.currentUser);
    formDataPayload.append('message', msgText);
    
    if (window.currentReplyMessageId) formDataPayload.append('reply_to_id', window.currentReplyMessageId);
    if (fileInput.files.length > 0) formDataPayload.append('attachment', fileInput.files[0]);

    // ?? 2. INSTANT RESPONSIVENESS: Clear UI text inputs immediately without waiting
    input.value = "";
    if (window.currentReplyMessageId) window.cancelReplyState();
    
    // ?? DO NOT clear the file attachment here! It breaks the fetch stream.

    // Print to the screen instantly and focus scrollbar directly down
    window.appendOptimisticUIToContainer(temporaryPayloadNode);
    window.scrollChatToBottom();

    try {
        const response = await fetch('chat_handler.php?action=send', { 
            method: 'POST', 
            body: formDataPayload,
            credentials: 'same-origin'
        });

        // ?? FIX: Clear the file input ONLY AFTER the network request has processed it
        window.clearSelectedAttachment();

        if (response.status === 401) { window.handleSessionExpired(); return; }
        
        if (response.ok) {
            const data = await response.json();
            
            // ?? 3. CLEAN UP TRACKING NODE: Remove temporary entry once DB confirms receipt
            if (data.success) {
                window.optimisticMessages = window.optimisticMessages.filter(m => m.id !== tempMessageId);
                window.syncChatWire();
            } else {
                // ?? FIX: Clear the temporary message if the server rejects it (e.g., file too large)
                window.optimisticMessages = window.optimisticMessages.filter(m => m.id !== tempMessageId);
                window.syncChatWire();
                window.triggerToastAlert(data.error || "Failed to send file.", true);
            }
        } else {
            throw new Error(`Server returned ${response.status}`);
        }
    } catch (err) { 
        console.error("Message dispatch error:", err);
        // Ensure UI clears even on failure
        window.clearSelectedAttachment(); 
        // Clean up tracking node if delivery fails completely
        window.optimisticMessages = window.optimisticMessages.filter(m => m.id !== tempMessageId);
        window.syncChatWire();
        window.triggerToastAlert("Message delivery failed. Please check your connection.", true);
    } 
};
