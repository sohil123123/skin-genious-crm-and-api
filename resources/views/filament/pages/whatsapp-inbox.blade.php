<x-filament-panels::page>
    <style>
        /* Hide page header and prevent outer scrollbars */
        .fi-header {
            display: none !important;
        }
        html, body {
            overflow: hidden !important;
        }
        .fi-page-header-main-ctn {
            padding-block: unset !important;
        }
        .fi-main, .fi-main-ctn, .fi-page, .fi-content {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            height: calc(100vh - 64px) !important;
            overflow: hidden !important;
        }
        .wa-inbox-container {
            display: flex;
            height: calc(100vh - 64px);
            min-height: calc(100vh - 64px);
            border-radius: 0;
            border: none;
            background: #eae6df;
            margin: 0 !important;
        }
        .dark .wa-inbox-container { border: none; background: #0b141a; }

        /* Left Panel - Conversations */
        .wa-sidebar { width: 360px; min-width: 300px; border-right: 1px solid #e9edef; display: flex; flex-direction: column; background: white; }
        .dark .wa-sidebar { border-color: #2f3b43; background: #111b21; }

        .wa-sidebar-header { padding: 12px 16px; border-bottom: 1px solid #e9edef; background: #f0f2f5; }
        .dark .wa-sidebar-header { border-color: #2f3b43; background: #202c33; }

        .wa-search { width: 100%; padding: 8px 12px; border-radius: 8px; border: none; font-size: 14px; background: white; box-shadow: 0 1px 1px rgba(0,0,0,0.06); }
        .dark .wa-search { background: #202c33; color: white; box-shadow: none; }
        .wa-search:focus { outline: none; background: white; }
        .dark .wa-search:focus { background: #202c33; }

        .wa-filters { display: flex; gap: 4px; margin-top: 8px; }
        .wa-filter-btn { padding: 4px 10px; border-radius: 16px; font-size: 12px; border: 1px solid #e9edef; background: white; cursor: pointer; transition: all 0.2s; color: #54656f; }
        .dark .wa-filter-btn { border-color: #2a3942; background: #202c33; color: #aebac1; }
        .wa-filter-btn.active { background: #00a884; color: white; border-color: #00a884; }

        .wa-conversation-list { flex: 1; overflow-y: auto; background: white; }
        .dark .wa-conversation-list { background: #111b21; }

        .wa-conversation-item { display: flex; align-items: center; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f0f2f5; transition: background 0.15s; gap: 12px; }
        .dark .wa-conversation-item { border-color: #222e35; }
        .wa-conversation-item:hover { background: #f5f6f6; }
        .dark .wa-conversation-item:hover { background: #202c33; }
        .wa-conversation-item.active { background: #efeae2; border-left: none; }
        .dark .wa-conversation-item.active { background: #2a3942; border-left: none; }

        .wa-avatar { width: 48px; height: 48px; border-radius: 50%; background: #dfe5e7; display: flex; align-items: center; justify-content: center; color: #54656f; font-weight: 600; font-size: 18px; flex-shrink: 0; }
        .dark .wa-avatar { background: #6a7c85; color: #d1d7db; }
        .wa-conv-info { flex: 1; min-width: 0; }
        .wa-conv-name { font-weight: 600; font-size: 15px; color: #111b21; display: flex; align-items: center; gap: 4px; }
        .dark .wa-conv-name { color: #e9edef; }
        .wa-conv-phone { font-size: 12px; color: #667781; }
        .dark .wa-conv-phone { color: #8696a0; }
        .wa-conv-preview { font-size: 13px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .dark .wa-conv-preview { color: #8696a0; }
        .wa-conv-meta { text-align: right; flex-shrink: 0; }
        .wa-conv-time { font-size: 11px; color: #667781; }
        .dark .wa-conv-time { color: #8696a0; }
        .wa-unread-badge { background: #25D366; color: white; border-radius: 50%; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; margin-top: 4px; }
        .wa-starred { color: #f59e0b; }

        /* Right Panel - Chat */
        .wa-chat-panel { flex: 1; display: flex; flex-direction: column; background-color: #efeae2; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23b4b4b4' fill-opacity='0.08'%3E%3Cpath fill-rule='evenodd' d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zM11 65c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48-25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z'/%3E%3C/g%3E%3C/svg%3E"); }
        .dark .wa-chat-panel { background-color: #0b141a; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath fill-rule='evenodd' d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zM11 65c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48-25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z'/%3E%3C/g%3E%3C/svg%3E"); }

        .wa-chat-header { padding: 10px 16px; background: #f0f2f5; border-bottom: 1px solid #e9edef; display: flex; align-items: center; gap: 12px; }
        .dark .wa-chat-header { background: #202c33; border-color: #2f3b43; }
        .wa-chat-header-info { flex: 1; }
        .wa-chat-header-name { font-weight: 600; font-size: 16px; color: #111b21; }
        .dark .wa-chat-header-name { color: #e9edef; }
        .wa-chat-header-status { font-size: 12px; color: #667781; }
        .dark .wa-chat-header-status { color: #8696a0; }
        .wa-window-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .wa-window-open { background: rgba(0, 168, 132, 0.15); color: #00a884; }
        .wa-window-closed { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .wa-chat-header-actions { display: flex; gap: 8px; }
        .wa-header-btn { padding: 6px; border-radius: 6px; cursor: pointer; color: #54656f; transition: all 0.15s; background: none; border: none; }
        .wa-header-btn:hover { background: rgba(0,0,0,0.05); }
        .dark .wa-header-btn { color: #aebac1; }
        .dark .wa-header-btn:hover { background: rgba(255,255,255,0.08); }

        /* Messages Area */
        .wa-messages { flex: 1; overflow-y: auto; padding: 5px 5% 10px 5%; display: flex; flex-direction: column; gap: 8px; }
        .wa-messages::-webkit-scrollbar { width: 6px; }
        .wa-messages::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 3px; }
        .dark .wa-messages::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }

        .wa-message { max-width: 65%; padding: 6px 9px 8px 9px; border-radius: 7.5px; position: relative; font-size: 14.2px; line-height: 1.45; word-wrap: break-word; box-shadow: 0 1px 0.5px rgba(0, 0, 0, 0.13); }

        .wa-message-incoming { background: white; align-self: flex-start; border-top-left-radius: 0; color: #111b21; }
        .wa-message-incoming::before { content: ""; position: absolute; top: 0; left: -8px; width: 8px; height: 13px; background: white; clip-path: polygon(100% 0, 0 0, 100% 100%); }
        .dark .wa-message-incoming { background: #202c33; color: #e9edef; }
        .dark .wa-message-incoming::before { background: #202c33; }

        .wa-message-outgoing { background: #d9fdd3; align-self: flex-end; border-top-right-radius: 0; color: #111b21; }
        .wa-message-outgoing::before { content: ""; position: absolute; top: 0; right: -8px; width: 8px; height: 13px; background: #d9fdd3; clip-path: polygon(0 0, 100% 0, 0 100%); }
        .dark .wa-message-outgoing { background: #005c4b; color: #e9edef; }
        .dark .wa-message-outgoing::before { background: #005c4b; }

        .wa-message-failed { background: #fee2e2; border: 1px solid #fecaca; }
        .dark .wa-message-failed { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3); }

        .wa-message-template { border-left: 3px solid #f59e0b; }
        .wa-template-tag { font-size: 10px; color: #92400e; background: rgba(245, 158, 11, 0.2); padding: 1px 6px; border-radius: 8px; margin-bottom: 4px; display: inline-block; }
        .dark .wa-template-tag { color: #fbbf24; }

        .wa-message-text { display: inline; }
        .wa-message-media { margin-bottom: 6px; }
        .wa-message-media img { max-width: 280px; max-height: 200px; border-radius: 6px; cursor: pointer; }
        .wa-message-media video { max-width: 280px; max-height: 200px; border-radius: 6px; }
        .wa-message-media audio { max-width: 260px; }
        .wa-message-media-doc { display: flex; align-items: center; gap: 8px; padding: 8px; background: rgba(0,0,0,0.05); border-radius: 6px; }
        .dark .wa-message-media-doc { background: rgba(255,255,255,0.08); }

        .wa-message-footer { float: right; margin-top: 4px; margin-left: 8px; display: inline-flex; align-items: center; gap: 3px; position: relative; bottom: -4px; right: -2px; }
        .wa-message-time { font-size: 11px; color: #667781; }
        .dark .wa-message-time { color: #8696a0; }
        .wa-message-status { display: inline-flex; align-items: center; }

        .wa-reply-context {
            padding: 5px 9px;
            margin-bottom: 5px;
            border-left: 4px solid #00a884;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 4px;
            font-size: 12.5px;
            color: #667781;
            display: flex;
            flex-direction: column;
            gap: 2px;
            cursor: pointer;
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
            min-width: 150px;
        }
        .dark .wa-reply-context {
            background: rgba(255, 255, 255, 0.06);
            color: #aebac1;
        }
        .wa-reply-sender {
            font-weight: 600;
            color: #00a884;
            font-size: 11.5px;
        }
        .wa-reply-text {
            color: #54656f;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dark .wa-reply-text {
            color: #d1d7db;
        }

        @keyframes wa-message-flash {
            0% { background-color: rgba(245, 158, 11, 0.35); }
            100% { }
        }
        .wa-message-highlight {
            animation: wa-message-flash 1.5s ease-out;
        }

        .wa-reply-trigger {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            padding: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            color: #667781;
            z-index: 10;
            border: 1px solid rgba(0,0,0,0.05);
            transition: color 0.15s, background 0.15s;
        }
        .dark .wa-reply-trigger {
            background: #202c33;
            color: #aebac1;
            border-color: rgba(255,255,255,0.05);
        }
        .wa-reply-trigger:hover {
            color: #00a884;
            background: white;
        }
        .dark .wa-reply-trigger:hover {
            color: #00a884;
            background: #2a3942;
        }

        .wa-message-incoming .wa-reply-trigger {
            right: -32px;
        }
        .wa-message-incoming .wa-reply-trigger::after {
            content: '';
            position: absolute;
            top: -15px;
            bottom: -15px;
            left: -40px;
            right: -10px;
            background: transparent;
        }

        .wa-message-outgoing .wa-reply-trigger {
            left: -32px;
        }
        .wa-message-outgoing .wa-reply-trigger::after {
            content: '';
            position: absolute;
            top: -15px;
            bottom: -15px;
            right: -40px;
            left: -10px;
            background: transparent;
        }

        .wa-message:hover .wa-reply-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .wa-date-divider {
            position: sticky;
            top: 0;
            z-index: 20;
            text-align: center;
            margin: 0 0 12px 0;
            pointer-events: none;
        }
        .wa-date-divider span {
            background: white;
            padding: 6px 12px;
            border-radius: 7.5px;
            font-size: 12.5px;
            color: #54656f;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
            text-transform: uppercase;
            font-weight: 500;
            pointer-events: auto;
            display: inline-block;
        }
        .dark .wa-date-divider span {
            background: #182229;
            color: #8696a0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        /* Input Area */
        .wa-input-area { padding: 10px 16px; background: #f0f2f5; border-top: 1px solid #e9edef; }
        .dark .wa-input-area { background: #202c33; border-color: #2f3b43; }

        .wa-reply-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 12px;
            margin-bottom: 8px;
            background: white;
            border-left: 4px solid #00a884;
            border-radius: 6px;
            font-size: 13px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .wa-reply-bar {
            background: #2a3942;
            border-color: #00a884;
        }
        .wa-reply-bar-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
            flex: 1;
        }
        .wa-reply-bar-title {
            font-weight: 600;
            color: #00a884;
            font-size: 12px;
        }
        .wa-reply-bar-desc {
            color: #667781;
            font-size: 12.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dark .wa-reply-bar-desc {
            color: #aebac1;
        }
        .wa-reply-close { cursor: pointer; padding: 2px; border-radius: 50%; color: #667781; }
        .dark .wa-reply-close { color: #aebac1; }
        .wa-reply-close:hover { background: rgba(0,0,0,0.05); }

        .wa-input-row { display: flex; align-items: center; gap: 8px; }
        .wa-text-input { flex: 1; padding: 10px 14px; border-radius: 8px; border: none; font-size: 15px; resize: none; max-height: 120px; min-height: 40px; line-height: 1.4; background: white; }
        .dark .wa-text-input { background: #2a3942; color: white; }
        .wa-text-input:focus { outline: none; }

        .wa-send-btn { width: 40px; height: 40px; border-radius: 50%; background: #00a884; color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; flex-shrink: 0; }
        .wa-send-btn:hover { background: #008f72; }
        .wa-send-btn:disabled { background: rgba(0,0,0,0.1); cursor: not-allowed; }
        .dark .wa-send-btn:disabled { background: rgba(255,255,255,0.05); }

        .wa-window-closed-bar { text-align: center; padding: 16px 20px; background: #ffeec1; border-top: 1px solid #ffd875; border-radius: 8px; margin: 12px; }
        .dark .wa-window-closed-bar { background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.2); }

        .wa-template-btn { padding: 8px 16px; border-radius: 20px; background: #00a884; color: white; border: none; cursor: pointer; font-size: 13.5px; font-weight: 500; transition: background 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.15); }
        .wa-template-btn:hover { background: #008f72; }

        /* Emoji Picker classes */
        .wa-emoji-picker {
            position: absolute; bottom: 44px; left: 0; z-index: 50;
            background: white; border: 1px solid #e9edef; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 12px; width: 260px;
        }
        .dark .wa-emoji-picker {
            background: #222e35; border-color: #2f3b43;
        }
        .wa-emoji-btn {
            font-size: 20px; padding: 4px; background: none; border: none;
            cursor: pointer; border-radius: 6px; transition: background 0.15s;
        }
        .wa-emoji-btn:hover {
            background: rgba(0,0,0,0.05);
        }
        .dark .wa-emoji-btn:hover {
            background: rgba(255,255,255,0.08);
        }

        .wa-no-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #8696a0; }
        .wa-no-chat-icon { font-size: 64px; margin-bottom: 16px; color: #8696a0; opacity: 0.8; }
        .wa-no-chat-text { font-size: 18px; font-weight: 500; color: #111b21; }
        .dark .wa-no-chat-text { color: #e9edef; }
        .wa-no-chat-sub { font-size: 14px; margin-top: 4px; color: #667781; }
        .dark .wa-no-chat-sub { color: #8696a0; }

        /* Webhook / Template Modal WhatsApp Bubble Style */
        .wa-bubble-preview {
            background-color: #efeae2;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23b4b4b4' fill-opacity='0.08'%3E%3Cpath fill-rule='evenodd' d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zM11 65c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48-25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z'/%3E%3C/g%3E%3C/svg%3E");
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: flex-start;
        }
        .dark .wa-bubble-preview {
            background-color: #0b141a;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath fill-rule='evenodd' d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zM11 65c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48-25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z'/%3E%3C/g%3E%3C/svg%3E");
            border-color: rgba(255, 255, 255, 0.05);
        }
        .wa-bubble-msg {
            background: white;
            border-radius: 8px;
            border-top-left-radius: 0;
            padding: 10px 14px;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
            max-width: 85%;
            font-size: 14px;
            color: #111b21;
            line-height: 1.45;
            position: relative;
        }
        .dark .wa-bubble-msg {
            background: #202c33;
            color: #e9edef;
        }
        .wa-bubble-time {
            font-size: 10px;
            color: #667781;
            text-align: right;
            margin-top: 4px;
            display: block;
        }
        .dark .wa-bubble-time {
            color: #8696a0;
        }

        .wa-clinic-select {
            width: 100%;
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid #e9edef;
            font-size: 13px;
            background: white;
            color: #54656f;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            cursor: pointer;
            margin-top: 8px;
            outline: none;
            transition: border-color 0.15s;
        }
        .dark .wa-clinic-select {
            background: #202c33;
            border-color: #2f3b43;
            color: #d1d7db;
            box-shadow: none;
        }
        .wa-clinic-select:focus {
            border-color: #00a884;
        }
    </style>

    <div class="wa-inbox-container" wire:poll.5s>
        {{-- Left Panel: Conversation List --}}
        <div class="wa-sidebar">
            <div class="wa-sidebar-header">
                <input type="text"
                       class="wa-search"
                       placeholder="🔍 Search contacts..."
                       wire:model.live.debounce.300ms="searchQuery" />

                <div class="wa-filters">
                    <button class="wa-filter-btn {{ $filterType === 'all' ? 'active' : '' }}"
                            wire:click="setFilter('all')">All</button>
                    <button class="wa-filter-btn {{ $filterType === 'unread' ? 'active' : '' }}"
                            wire:click="setFilter('unread')">Unread</button>
                    <button class="wa-filter-btn {{ $filterType === 'starred' ? 'active' : '' }}"
                            wire:click="setFilter('starred')">⭐ Starred</button>
                    <button class="wa-filter-btn {{ $filterType === 'archived' ? 'active' : '' }}"
                            wire:click="setFilter('archived')">🗄️ Archived</button>
                </div>

                <select wire:model.live="filterClinicId" class="wa-clinic-select">
                    <option value="">🏢 All Clinics</option>
                    @foreach($this->clinicsForFilter as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wa-conversation-list">
                @forelse($this->conversations as $conv)
                    <div class="wa-conversation-item {{ $activeConversationId === $conv->id ? 'active' : '' }}"
                         wire:click="selectConversation({{ $conv->id }})"
                         wire:key="conv-{{ $conv->id }}">

                        <div class="wa-avatar">
                            {{ strtoupper(substr($conv->display_name, 0, 1)) }}
                        </div>

                        <div class="wa-conv-info">
                            <div class="wa-conv-name">
                                {{ $conv->display_name }}
                                @if($conv->is_starred)
                                    <span class="wa-starred">⭐</span>
                                @endif
                            </div>
                            @if($conv->user?->clinic?->name)
                                <div style="font-size: 11px; color: #00a884; font-weight: 600; display: flex; align-items: center; gap: 4px; margin-top: 1px;">
                                    🏢 {{ $conv->user->clinic->name }}
                                </div>
                            @endif
                            <div class="wa-conv-phone">{{ $conv->phone_number }}</div>
                            <div class="wa-conv-preview">{{ $conv->last_message_preview ?? 'No messages yet' }}</div>
                        </div>

                        <div class="wa-conv-meta">
                            @if($conv->last_message_at)
                                <div class="wa-conv-time">
                                    @if($conv->last_message_at->isToday())
                                        {{ $conv->last_message_at->format('h:i A') }}
                                    @elseif($conv->last_message_at->isYesterday())
                                        Yesterday
                                    @elseif($conv->last_message_at->gt(now()->subDays(7)))
                                        {{ $conv->last_message_at->format('l') }}
                                    @else
                                        {{ $conv->last_message_at->format('d/m/Y') }}
                                    @endif
                                </div>
                            @endif
                            @if($conv->unread_count > 0)
                                <div class="wa-unread-badge">{{ $conv->unread_count }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="padding: 32px; text-align: center; color: rgba(var(--gray-400), 1);">
                        <div style="font-size: 32px; margin-bottom: 8px;">💬</div>
                        <div>No conversations found</div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right Panel: Chat View --}}
        <div class="wa-chat-panel">
            @if($this->activeConversation)
                {{-- Chat Header --}}
                <div class="wa-chat-header">
                    <div class="wa-avatar" style="width: 40px; height: 40px; font-size: 16px;">
                        {{ strtoupper(substr($this->activeConversation->display_name, 0, 1)) }}
                    </div>

                    <div class="wa-chat-header-info">
                        <div class="wa-chat-header-name" style="display: flex; align-items: center; gap: 8px;">
                            <span>{{ $this->activeConversation->display_name }}</span>
                            @if($this->activeConversation->user?->clinic?->name)
                                <span style="font-size: 11px; background: rgba(0, 168, 132, 0.12); color: #00a884; padding: 2px 8px; border-radius: 9999px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; display: inline-flex; align-items: center;">
                                    🏢 {{ $this->activeConversation->user->clinic->name }}
                                </span>
                            @endif
                        </div>
                        <div class="wa-chat-header-status">
                            {{ $this->activeConversation->phone_number }}
                            @if($this->activeConversation->user)
                                · Client
                            @endif
                        </div>
                    </div>

                    <div>
                        @if($this->activeConversation->isWindowOpen())
                            <span class="wa-window-badge wa-window-open">
                                🟢 Window Open · {{ $this->activeConversation->window_remaining }}
                            </span>
                        @else
                            <span class="wa-window-badge wa-window-closed">
                                🔴 Window Closed
                            </span>
                        @endif
                    </div>

                    <div class="wa-chat-header-actions" style="display: flex; align-items: center; gap: 8px;">
                        <button class="wa-header-btn" wire:click="mountAction('newConversation')" title="New Chat" style="color: #00a884; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #00a884; padding: 4px 8px; border-radius: 6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 14px; height: 14px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            New Chat
                        </button>
                        <button class="wa-header-btn" wire:click="toggleStar" title="Star">
                            {{ $this->activeConversation->is_starred ? '⭐' : '☆' }}
                        </button>
                        @if($this->activeConversation->is_archived)
                            <button class="wa-header-btn" wire:click="toggleArchiveConversation" title="Unarchive" style="color: #00a884;">📤 Unarchive</button>
                        @else
                            <button class="wa-header-btn" wire:click="toggleArchiveConversation" title="Archive">🗄️ Archive</button>
                        @endif
                    </div>
                </div>

                {{-- Messages --}}
                <div class="wa-messages" id="wa-messages-container"
                     x-init="
                        $el.scrollTop = $el.scrollHeight;
                        const observer = new MutationObserver(() => {
                            $el.scrollTop = $el.scrollHeight;
                        });
                        observer.observe($el, { childList: true, subtree: true });

                        // Multi-stage timeouts to ensure scroll is at bottom as images/css finish rendering
                        [50, 150, 300, 600, 1000].forEach(delay => {
                            setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, delay);
                        });
                     "
                     @load.capture="$el.scrollTop = $el.scrollHeight">

                    @php
                        $groupedMessages = $this->messages->groupBy(function ($msg) {
                            return $msg->created_at->format('Y-m-d');
                        });
                    @endphp

                    @forelse($groupedMessages as $dateStr => $dayMessages)
                        <div class="wa-day-group" style="display: flex; flex-direction: column; gap: 8px;">
                            <div class="wa-date-divider">
                                <span>
                                    @php
                                        $firstMsgDate = $dayMessages->first()->created_at;
                                    @endphp
                                    @if($firstMsgDate->isToday())
                                        Today
                                    @elseif($firstMsgDate->isYesterday())
                                        Yesterday
                                    @else
                                        {{ $firstMsgDate->format('M d, Y') }}
                                    @endif
                                </span>
                            </div>

                            @foreach($dayMessages as $msg)
                                @php
                                    $isOutgoing = ($msg->direction?->value ?? $msg->direction) === 'outgoing';
                                    $isFailed = ($msg->status?->value ?? $msg->status) === 'failed';
                                    $isTemplate = ($msg->type?->value ?? $msg->type) === 'template';
                                @endphp

                                <div id="msg-{{ $msg->id }}"
                                     class="wa-message {{ $isOutgoing ? 'wa-message-outgoing' : 'wa-message-incoming' }} {{ $isFailed ? 'wa-message-failed' : '' }} {{ $isTemplate ? 'wa-message-template' : '' }}"
                                     wire:key="msg-{{ $msg->id }}">

                                    @if($isTemplate && $msg->template_name)
                                        <div class="wa-template-tag">📋 {{ $msg->template_name }}</div>
                                    @endif

                                    {{-- Hover Reply Button --}}
                                    <button class="wa-reply-trigger" wire:click="setReplyTo({{ $msg->id }})" title="Reply">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px;">
                                            <path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 01-.025 1.06L3.622 7.25h10.003a5.375 5.375 0 010 10.75H13a.75.75 0 010-1.5h.625a3.875 3.875 0 000-7.75H3.622l4.146 3.957a.75.75 0 01-1.036 1.085l-5.5-5.25a.75.75 0 010-1.085l5.5-5.25a.75.75 0 011.06.025z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    @if($msg->isReply())
                                        @php
                                            $repliedMsg = $msg->getReplyToMessage();
                                            $repliedSender = '';
                                            $repliedText = '';
                                            if ($repliedMsg) {
                                                $repliedOutgoing = ($repliedMsg->direction?->value ?? $repliedMsg->direction) === 'outgoing';
                                                $repliedSender = $repliedOutgoing ? 'You' : ($this->activeConversation->display_name ?? $repliedMsg->phone_number);

                                                if ($repliedMsg->text_body) {
                                                    $repliedText = $repliedMsg->text_body;
                                                } else {
                                                    $repliedType = $repliedMsg->type?->value ?? $repliedMsg->type;
                                                    $repliedText = match ($repliedType) {
                                                        'image' => '📷 Photo',
                                                        'video' => '🎥 Video',
                                                        'audio' => '🎵 Audio',
                                                        'document' => '📄 Document',
                                                        'sticker' => '🎨 Sticker',
                                                        default => 'Message',
                                                    };
                                                }
                                            } else {
                                                $repliedSender = 'Original Message';
                                                $repliedText = 'Reply to a message';
                                            }
                                        @endphp
                                        <div class="wa-reply-context"
                                             @if($repliedMsg)
                                                 onclick="const el = document.getElementById('msg-{{ $repliedMsg->id }}'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('wa-message-highlight'); setTimeout(() => el.classList.remove('wa-message-highlight'), 1500); }"
                                             @endif>
                                            <div class="wa-reply-sender">{{ $repliedSender }}</div>
                                            <div class="wa-reply-text">{{ \Illuminate\Support\Str::limit($repliedText, 60) }}</div>
                                        </div>
                                    @endif

                                    {{-- Media Content --}}
                                    @if($msg->hasMedia())
                                        <div class="wa-message-media">
                                            @php $type = $msg->type?->value ?? $msg->type; @endphp

                                            @if($type === 'image')
                                                @if($msg->media_display_url)
                                                    <img src="{{ $msg->media_display_url }}" alt="Image"
                                                         onclick="window.open(this.src, '_blank')" />
                                                @else
                                                    <div class="wa-message-media-doc">📷 Image (downloading...)</div>
                                                @endif
                                            @elseif($type === 'video')
                                                @if($msg->media_display_url)
                                                    <video controls preload="metadata">
                                                        <source src="{{ $msg->media_display_url }}" type="{{ $msg->media_mime_type ?? 'video/mp4' }}">
                                                    </video>
                                                @else
                                                    <div class="wa-message-media-doc">🎥 Video (downloading...)</div>
                                                @endif
                                            @elseif($type === 'audio')
                                                @if($msg->media_display_url)
                                                    <audio controls preload="metadata">
                                                        <source src="{{ $msg->media_display_url }}" type="{{ $msg->media_mime_type ?? 'audio/ogg' }}">
                                                    </audio>
                                                @else
                                                    <div class="wa-message-media-doc">🎵 Audio (downloading...)</div>
                                                @endif
                                            @elseif($type === 'document')
                                                <div class="wa-message-media-doc">
                                                    📄 <a href="{{ $msg->media_display_url ?? '#' }}" target="_blank"
                                                          style="color: inherit; text-decoration: underline;">
                                                        {{ $msg->media_filename ?? 'Document' }}
                                                    </a>
                                                </div>
                                            @elseif($type === 'sticker')
                                                @if($msg->media_display_url)
                                                    <img src="{{ $msg->media_display_url }}" alt="Sticker"
                                                         style="max-width: 150px; max-height: 150px;" />
                                                @else
                                                    <div class="wa-message-media-doc">🎨 Sticker</div>
                                                @endif
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Text Content --}}
                                    @if($msg->text_body)
                                        <span class="wa-message-text">{!! nl2br(e($msg->text_body)) !!}</span>
                                    @endif

                                    {{-- Location --}}
                                    @if(($msg->type?->value ?? $msg->type) === 'location' && is_array($msg->content))
                                        <span class="wa-message-text">
                                            📍 {{ $msg->content['name'] ?? 'Location' }}
                                            @if(!empty($msg->content['address']))
                                                <br><small>{{ $msg->content['address'] }}</small>
                                            @endif
                                        </span>
                                    @endif

                                    {{-- Message Footer --}}
                                    <span class="wa-message-footer">
                                        <span class="wa-message-time">{{ $msg->created_at->format('h:i A') }}</span>

                                        @if($isOutgoing)
                                            @php $statusVal = $msg->status?->value ?? $msg->status; @endphp
                                            <span class="wa-message-status">
                                                @if($statusVal === 'read')
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 15" width="16" height="15" style="width: 16px; height: 15px; color: #53bdeb; display: inline-block; vertical-align: middle;"><path fill="currentColor" d="M15.01 3.47l-8 8 -3.12-3.13-.7.7 3.82 3.83 8.7-8.7-.7-.7zm-4.7 0L9.6 4.17 6.31 7.46l-3.12-3.13-.7.7 3.82 3.83 4.4-4.4-.7-.7z"/></svg>
                                                @elseif($statusVal === 'delivered')
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 15" width="16" height="15" style="width: 16px; height: 15px; color: #8696a0; display: inline-block; vertical-align: middle;"><path fill="currentColor" d="M15.01 3.47l-8 8 -3.12-3.13-.7.7 3.82 3.83 8.7-8.7-.7-.7zm-4.7 0L9.6 4.17 6.31 7.46l-3.12-3.13-.7.7 3.82 3.83 4.4-4.4-.7-.7z"/></svg>
                                                @elseif($statusVal === 'sent')
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 15" width="16" height="15" style="width: 16px; height: 15px; color: #8696a0; display: inline-block; vertical-align: middle;"><path fill="currentColor" d="M10.91 3.47L4.6 9.78 1.48 6.66l-.7.7 3.82 3.82 7.01-7.01-.7-.7z" /></svg>
                                                @elseif($statusVal === 'pending')
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="12" height="12" style="width: 12px; height: 12px; color: #8696a0; display: inline-block; vertical-align: middle;" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="7"></circle><polyline points="8 4 8 8 11 9"></polyline></svg>
                                                @elseif($statusVal === 'failed')
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="12" height="12" style="width: 12px; height: 12px; color: #ef4444; display: inline-block; vertical-align: middle;" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="7"></circle><line x1="8" y1="5" x2="8" y2="9"></line><line x1="8" y1="11" x2="8.01" y2="11"></line></svg>
                                                @endif
                                            </span>
                                        @endif
                                    </span>

                                    @if($isFailed)
                                        <div style="font-size: 11px; color: #ef4444; margin-top: 4px; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid rgba(239, 68, 68, 0.15); padding-top: 4px;">
                                            <span style="font-style: italic;">⚠️ {{ $msg->failed_reason ?? 'Failed to send' }}</span>
                                            <button wire:click="retryMessage({{ $msg->id }})"
                                                    style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 2px; font-weight: 600; text-transform: uppercase; transition: opacity 0.15s;"
                                                    onmouseover="this.style.opacity='0.8'"
                                                    onmouseout="this.style.opacity='1'">
                                                🔄 Resend
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="wa-no-chat" style="opacity: 0.5;">
                            <div>💬</div>
                            <div>No messages yet. Send the first message!</div>
                        </div>
                    @endforelse
                </div>

                {{-- Input Area --}}
                @if($this->activeConversation->isWindowOpen())
                    <div class="wa-input-area">
                        @if($replyToMessageId)
                            @php
                                $replyingToMsg = $this->getReplyingToMessage();
                                $replyingSender = '';
                                $replyingText = '';
                                if ($replyingToMsg) {
                                    $replyingOutgoing = ($replyingToMsg->direction?->value ?? $replyingToMsg->direction) === 'outgoing';
                                    $replyingSender = $replyingOutgoing ? 'You' : ($this->activeConversation->display_name ?? $replyingToMsg->phone_number);

                                    if ($replyingToMsg->text_body) {
                                        $replyingText = $replyingToMsg->text_body;
                                    } else {
                                        $replyingType = $replyingToMsg->type?->value ?? $replyingToMsg->type;
                                        $replyingText = match ($replyingType) {
                                            'image' => '📷 Photo',
                                            'video' => '🎥 Video',
                                            'audio' => '🎵 Audio',
                                            'document' => '📄 Document',
                                            'sticker' => '🎨 Sticker',
                                            default => 'Message',
                                        };
                                    }
                                }
                            @endphp
                            @if($replyingToMsg)
                                <div class="wa-reply-bar">
                                    <div class="wa-reply-bar-info">
                                        <div class="wa-reply-bar-title">↩ Replying to {{ $replyingSender }}</div>
                                        <div class="wa-reply-bar-desc">{{ $replyingText }}</div>
                                    </div>
                                    <button class="wa-reply-close" wire:click="clearReplyTo">✕</button>
                                </div>
                            @endif
                        @endif

                        {{-- Hidden File Input --}}
                        <input type="file" id="media-upload-input" wire:model="uploadedFile" style="display: none;" />

                        {{-- Uploaded File Preview Panel --}}
                        @if($uploadedFile)
                            <div style="padding: 10px; background: rgba(0,0,0,0.03); border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(0,0,0,0.05);">
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                    @if(str_starts_with($uploadedFile->getMimeType(), 'image/'))
                                        <img src="{{ $uploadedFile->temporaryUrl() }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" />
                                    @else
                                        <div style="width: 50px; height: 50px; background: #e2f5ec; color: #0df; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 20px;">
                                            📄
                                        </div>
                                    @endif
                                    <div style="min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 600; color: #111b21; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                            {{ $uploadedFile->getClientOriginalName() }}
                                        </div>
                                        <div style="font-size: 11px; color: #667781;">
                                            {{ round($uploadedFile->getSize() / 1024) }} KB
                                        </div>
                                    </div>
                                </div>
                                <button type="button" wire:click="$set('uploadedFile', null)" style="background: none; border: none; cursor: pointer; padding: 4px; font-size: 16px; color: #667781; transition: color 0.1s;">
                                    ✕
                                </button>
                            </div>
                        @endif

                        <div class="wa-input-row">
                            <!-- Emoji / Smiley Icon with Custom Alpine Popover -->
                            <div x-data="{ open: false }" style="position: relative; flex-shrink: 0;">
                                <button type="button" @click="open = !open" class="wa-header-btn" style="padding: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" style="width: 24px; height: 24px; opacity: 0.75;">
                                        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-3.5-9c.828 0 1.5-.672 1.5-1.5S9.328 8 8.5 8 7 8.672 7 9.5s.672 1.5 1.5 1.5zm7 0c.828 0 1.5-.672 1.5-1.5S15.328 8 14.5 8s-1.5.672-1.5 1.5.672 1.5 1.5 1.5zm-3.5 6.5c2.33 0 4.311-1.46 5.082-3.5H6.918c.771 2.04 2.752 3.5 5.082 3.5z" />
                                    </svg>
                                </button>

                                <div x-show="open" @click.outside="open = false" class="wa-emoji-picker" style="display: none;">
                                    <div style="font-size: 11px; font-weight: bold; color: #8696a0; text-transform: uppercase; margin-bottom: 8px;">Emojis</div>
                                    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; max-height: 180px; overflow-y: auto; padding: 2px;">
                                        @foreach(['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😋', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💅', '🤳', '💪', '🧠', '👀', '👅', '👄', '💋', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝'] as $emoji)
                                            <button type="button" @click="$wire.messageText += '{{ $emoji }}'; open = false" class="wa-emoji-btn">
                                                {{ $emoji }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Attachment / Plus Icon -->
                            <button type="button" class="wa-header-btn" style="padding: 8px; margin-right: 4px; flex-shrink: 0;" onclick="document.getElementById('media-upload-input').click()">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px; opacity: 0.75;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>

                            <!-- Text Area -->
                            <textarea class="wa-text-input"
                                      wire:model="messageText"
                                      placeholder="Type a message..."
                                      rows="1"
                                      wire:keydown.enter.prevent="sendMessage"
                                      x-data
                                      x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                            ></textarea>

                            <!-- Send Button -->
                            <button class="wa-send-btn"
                                    wire:click="sendMessage"
                                    @if(empty(trim($messageText)) && !$uploadedFile) disabled @endif>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" class="text-white" style="width: 20px; height: 20px;">
                                    <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="wa-window-closed-bar">
                        <div style="margin-bottom: 8px; font-size: 14px; color: rgba(var(--gray-600), 1);">
                            🔒 The 24-hour messaging window is closed. Send a template message to re-engage.
                        </div>
                        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                            @foreach($this->templates->take(5) as $tmpl)
                                <button class="wa-template-btn"
                                        wire:click="selectTemplateForSending({{ $tmpl->id }})"
                                        wire:loading.attr="disabled"
                                        style="display: inline-flex; align-items: center; gap: 6px;">
                                    <svg wire:loading wire:target="selectTemplateForSending({{ $tmpl->id }})" class="animate-spin" style="width: 14px; height: 14px; color: white;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="selectTemplateForSending({{ $tmpl->id }})">📋</span>
                                    <span>{{ $tmpl->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                {{-- No Conversation Selected --}}
                <div class="wa-no-chat">
                    <div class="wa-no-chat-icon">💬</div>
                    <div class="wa-no-chat-text">Skin Genious WhatsApp</div>
                    <div class="wa-no-chat-sub" style="margin-bottom: 16px;">Select a conversation to start chatting</div>
                    <button type="button"
                            wire:click="mountAction('newConversation')"
                            style="background: #00a884; color: white; border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Chat
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Template Variables Popup Modal --}}
    <x-filament::modal id="template-vars-modal" width="xl">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" style="width: 24px; height: 24px; color: #10b981;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
                <span class="text-lg font-bold text-gray-900 dark:text-white">Fill Template Parameters</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Template: <span class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-0.5 rounded font-mono text-xs">{{ $this->selectedTemplateForModal?->name }}</span>
        </x-slot>

        <div class="space-y-6 py-2">
            <!-- Live Preview Section -->
            <div class="bg-gray-50/50 dark:bg-gray-900/30 p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        Live Preview
                    </span>
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono">
                        {{ $this->selectedTemplateForModal?->language ?? 'en' }}
                    </span>
                </div>
                <div class="wa-bubble-preview">
                    <div class="wa-bubble-msg shadow-md">
                        @php
                            $previewText = $this->selectedTemplateForModal?->body_text ?? '';
                            foreach ($this->templatePlaceholders as $placeholder) {
                                $typedVal = $this->templateParameterValues['param_' . $placeholder] ?? '';
                                if ($typedVal !== '') {
                                    $highlighted = '<span class="px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-950/60 text-green-700 dark:text-green-300 font-bold border border-green-200 dark:border-green-800/40 text-xs">' . e($typedVal) . '</span>';
                                } else {
                                    $highlighted = '<span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-bold border border-amber-200 dark:border-amber-800/40 text-xs">' . e($placeholder) . '</span>';
                                }
                                $previewText = str_replace('{{' . $placeholder . '}}', $highlighted, $previewText);
                            }
                        @endphp
                        {!! nl2br($previewText) !!}
                        <span class="wa-bubble-time">
                            {{ now()->format('h:i A') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Inputs Section -->
            <div class="space-y-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                    <svg width="16" height="16" class="text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Enter Variable Details
                </span>

                <div class="grid gap-4">
                    @foreach($this->templatePlaceholders as $placeholder)
                        @php
                            $label = is_numeric($placeholder) ? "Variable {$placeholder}" : ucwords(str_replace('_', ' ', $placeholder));
                            $iconType = 'default';
                            if (str_contains(strtolower($placeholder), 'name')) {
                                $iconType = 'name';
                            } elseif (str_contains(strtolower($placeholder), 'date') || str_contains(strtolower($placeholder), 'time')) {
                                $iconType = 'date';
                            } elseif (str_contains(strtolower($placeholder), 'clinic')) {
                                $iconType = 'clinic';
                            }
                        @endphp
                        <div class="p-3 bg-white dark:bg-gray-800/40 rounded-lg border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                {{ $label }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-slot name="prefix">
                                    @if($iconType === 'name')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" style="width: 16px; height: 16px; color: #10b981;">
                                            <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.25 0 4.354-.743 6.058-2.01a1.228 1.228 0 00.41-1.413 5.961 5.961 0 00-4.14-3.791a3.5 3.5 0 00-4.656 0 5.96 5.96 0 00-4.14 3.792z" />
                                        </svg>
                                    @elseif($iconType === 'date')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" style="width: 16px; height: 16px; color: #10b981;">
                                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                                        </svg>
                                    @elseif($iconType === 'clinic')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" style="width: 16px; height: 16px; color: #10b981;">
                                            <path fill-rule="evenodd" d="M4 16.5v-9A2.5 2.5 0 016.5 5h7A2.5 2.5 0 0116 7.5v9a.75.75 0 01-1.5 0v-1.5h-9v1.5a.75.75 0 01-1.5 0zM6.75 10a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zm5.25-.75a.75.75 0 000 1.5h1.5a.75.75 0 000-1.5h-1.5zM6.75 13a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zm5.25-.75a.75.75 0 000 1.5h1.5a.75.75 0 000-1.5h-1.5z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" style="width: 16px; height: 16px; color: #10b981;">
                                            <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 113 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                            <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                        </svg>
                                    @endif
                                </x-slot>
                                <x-filament::input
                                    type="text"
                                    wire:model.live.debounce.150ms="templateParameterValues.param_{{ $placeholder }}"
                                    placeholder="Enter {{ strtolower($label) }}..."
                                    required
                                    class="py-2 text-sm"
                                />
                            </x-filament::input.wrapper>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button x-on:click="$dispatch('close-modal', { id: 'template-vars-modal' })" color="gray">
                    Cancel
                </x-filament::button>
                <x-filament::button wire:click="sendTemplateWithParameters" color="success" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled">
                    Send Message
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <script>
        document.addEventListener('play-notification-sound', () => {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

                // Beep 1 (A5 note)
                const osc1 = audioCtx.createOscillator();
                const gain1 = audioCtx.createGain();
                osc1.connect(gain1);
                gain1.connect(audioCtx.destination);
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(880, audioCtx.currentTime);
                gain1.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain1.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);

                osc1.start(audioCtx.currentTime);
                osc1.stop(audioCtx.currentTime + 0.1);

                // Beep 2 (G5 note, staggered by 0.12s)
                const osc2 = audioCtx.createOscillator();
                const gain2 = audioCtx.createGain();
                osc2.connect(gain2);
                gain2.connect(audioCtx.destination);
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(784, audioCtx.currentTime + 0.12);
                gain2.gain.setValueAtTime(0.2, audioCtx.currentTime + 0.12);
                gain2.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);

                osc2.start(audioCtx.currentTime + 0.12);
                osc2.stop(audioCtx.currentTime + 0.3);
            } catch (e) {
                console.error('Failed to play synthesized sound:', e);
            }
        });
    </script>
</x-filament-panels::page>
