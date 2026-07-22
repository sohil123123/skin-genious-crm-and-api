<x-filament-panels::page>
    <style>
        .meta-library-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1c1e21;
        }
        .meta-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 16px 24px;
            border-radius: 12px;
            border: 1px solid #e4e6eb;
            margin-bottom: 20px;
        }
        .meta-title {
            font-size: 20px;
            font-weight: 700;
            color: #1c1e21;
        }
        .meta-subtitle {
            font-size: 13px;
            color: #65676b;
            margin-top: 2px;
        }
        .meta-btn-primary {
            background-color: #0064d1;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }
        .meta-btn-primary:hover {
            background-color: #0053b1;
        }
        .meta-search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .meta-search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            font-size: 14px;
            border: 1px solid #ced0d4;
            border-radius: 8px;
            background: #ffffff;
            outline: none;
        }
        .meta-search-input:focus {
            border-color: #0064d1;
            box-shadow: 0 0 0 2px rgba(0, 100, 209, 0.2);
        }
        .meta-search-icon {
            position: absolute;
            left: 14px;
            top: 11px;
            color: #8a8d91;
            font-size: 14px;
        }
        .meta-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) {
            .meta-layout {
                grid-template-columns: 1fr;
            }
        }
        .meta-sidebar {
            background: #ffffff;
            border: 1px solid #e4e6eb;
            border-radius: 12px;
            padding: 20px 16px;
        }
        .meta-category-section {
            padding-bottom: 16px;
            border-bottom: 1px solid #e4e6eb;
            margin-bottom: 16px;
        }
        .meta-category-title {
            font-size: 14px;
            font-weight: 700;
            color: #1c1e21;
            margin-bottom: 16px;
        }
        .meta-radio-label {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #1c1e21;
            cursor: pointer;
            margin-bottom: 14px;
            user-select: none;
            transition: color 0.15s;
        }
        .meta-radio-label:last-child {
            margin-bottom: 0;
        }
        .meta-radio-custom {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #8a8d91;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            flex-shrink: 0;
            transition: border-color 0.15s;
        }
        .meta-radio-label.active .meta-radio-custom {
            border-color: #0064d1;
        }
        .meta-radio-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background-color: #0064d1;
            opacity: 0;
            transform: scale(0);
            transition: all 0.15s ease-in-out;
        }
        .meta-radio-label.active .meta-radio-dot {
            opacity: 1;
            transform: scale(1);
        }
        .meta-filter-accordion {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 500;
            color: #1c1e21;
            padding: 10px 0;
            cursor: pointer;
            user-select: none;
        }
        .meta-filter-accordion-chevron {
            font-size: 10px;
            color: #65676b;
        }
        .meta-results-count {
            font-size: 13px;
            color: #65676b;
            margin-bottom: 16px;
            font-weight: 400;
        }
        .meta-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 20px;
        }
        .meta-card {
            background: #ffffff;
            border: 1px solid #e4e6eb;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .meta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .meta-card-canvas {
            background: #efeae2;
            padding: 16px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-image: radial-gradient(#d8d2c9 1px, transparent 0);
            background-size: 12px 12px;
        }
        .meta-chat-bubble {
            background: #ffffff;
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            line-height: 1.5;
            color: #111b21;
            box-shadow: 0 1px 0.5px rgba(11,20,26,0.13);
            border: 1px solid #e9edef;
        }
        .meta-token {
            background: #d1fae5;
            color: #065f46;
            font-family: monospace;
            font-weight: 600;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 11px;
        }
        .meta-chat-button {
            margin-top: 8px;
            background: #ffffff;
            color: #00a884;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 12px;
            border-radius: 6px;
            border: 1px solid #e9edef;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 1px 0.5px rgba(11,20,26,0.08);
        }
        .meta-card-footer {
            padding: 12px 16px;
            background: #ffffff;
            border-top: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .meta-card-name {
            font-size: 11px;
            color: #8a8d91;
            font-family: monospace;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .meta-use-btn {
            background: #25d366;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: background 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .meta-use-btn:hover {
            background: #20bd5a;
        }
    </style>

    <div class="meta-library-container">
        <!-- Header Bar -->
        <div class="meta-header-bar">
            <div>
                <div class="meta-title">WhatsApp Manager &rsaquo; Template library</div>
                <div class="meta-subtitle">Choose from official pre-approved WhatsApp templates to start sending messages faster.</div>
            </div>
            <a href="{{ \App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource::getUrl('create') }}" class="meta-btn-primary">
                <span>+</span>
                <span>Create new template</span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="meta-search-box">
            <span class="meta-search-icon">🔍</span>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search {{ strtolower($activeCategory) }} templates..."
                class="meta-search-input"
            />
        </div>

        <!-- Main Layout -->
        <div class="meta-layout">
            <!-- Sidebar Filters -->
            <div class="meta-sidebar">
                <div class="meta-category-section">
                    <div class="meta-category-title">Category</div>

                    @php
                        $counts = $this->getCategoryCounts();
                    @endphp

                    <!-- Utility Radio Option -->
                    <div
                        class="meta-radio-label {{ $activeCategory === 'UTILITY' ? 'active' : '' }}"
                        wire:click="selectCategory('UTILITY')"
                    >
                        <div class="meta-radio-custom">
                            <div class="meta-radio-dot"></div>
                        </div>
                        <span>Utility ({{ $counts['UTILITY'] ?? 0 }})</span>
                    </div>

                    <!-- Authentication Radio Option -->
                    <div
                        class="meta-radio-label {{ $activeCategory === 'AUTHENTICATION' ? 'active' : '' }}"
                        wire:click="selectCategory('AUTHENTICATION')"
                    >
                        <div class="meta-radio-custom">
                            <div class="meta-radio-dot"></div>
                        </div>
                        <span>Authentication ({{ $counts['AUTHENTICATION'] ?? 0 }})</span>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div>
                @php
                    $templates = $this->getFilteredTemplates();
                @endphp

                <div class="meta-results-count">
                    Showing {{ count($templates) }} of {{ count($templates) }} results
                </div>

                @if(empty($templates))
                    <div style="background: #ffffff; padding: 48px; border-radius: 12px; border: 1px solid #e4e6eb; text-align: center;">
                        <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
                        <div style="font-size: 14px; font-weight: 700; color: #1c1e21;">No templates found</div>
                        <div style="font-size: 12px; color: #65676b; margin-top: 4px;">Try searching for a different keyword or change category.</div>
                    </div>
                @else
                    <div class="meta-cards-grid">
                        @foreach($templates as $key => $tpl)
                            <div
                                class="meta-card"
                                wire:click="useTemplate('{{ $tpl['category'] }}', '{{ $key }}')"
                                title="Click to use this template"
                            >
                                <!-- Chat Canvas -->
                                <div class="meta-card-canvas">
                                    <div class="meta-chat-bubble">
                                        @php
                                            $body = e($tpl['body_text']);
                                            preg_match_all('/\{\{([^}]+)\}\}/', $tpl['body_text'], $m);
                                            $placeholders = array_unique($m[1] ?? []);
                                            foreach ($placeholders as $ph) {
                                                $body = str_replace(e('{{' . $ph . '}}'), '<span class="meta-token">{{' . e($ph) . '}}</span>', $body);
                                            }
                                        @endphp

                                        <div style="white-space: pre-line;">
                                            {!! $body !!}
                                        </div>

                                        @if(!empty($tpl['footer_text']))
                                            <div style="font-size: 10px; color: #8a8d91; margin-top: 8px;">
                                                {{ $tpl['footer_text'] }}
                                            </div>
                                        @endif
                                    </div>

                                    @if(!empty($tpl['buttons']))
                                        <div>
                                            @foreach($tpl['buttons'] as $btn)
                                                <div class="meta-chat-button">
                                                    <span>{{ $btn['type'] === 'URL' ? '🔗' : '💬' }}</span>
                                                    <span>{{ $btn['text'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Footer -->
                                <div class="meta-card-footer">
                                    <div class="meta-card-name" title="{{ $tpl['title'] }}">
                                        {{ $tpl['title'] }}
                                    </div>
                                    <button
                                        type="button"
                                        class="meta-use-btn"
                                    >
                                        <span>⚡</span>
                                        <span>Use Template</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
