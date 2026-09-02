@props([
    'payload' => [],
    'record' => null,
])

@php
    $jsonString = !empty($payload) ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
@endphp

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    @if (!empty($payload) && is_array($payload))
        {{-- Parsed Parameters Table --}}
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Parsed Key Parameters
            </div>
            <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
                <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                    <thead>
                        <tr style="background-color: #f9fafb; border-bottom: 1px solid #e5e7eb; color: #4b5563; font-weight: 600;">
                            <th style="padding: 0.625rem 0.875rem; width: 35%;">Parameter Key</th>
                            <th style="padding: 0.625rem 0.875rem;">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload as $key => $value)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.625rem 0.875rem; font-weight: 600; color: #374151; vertical-align: top;">
                                    <div>{{ Str::headline($key) }}</div>
                                    <div style="font-size: 0.6875rem; font-weight: 400; color: #9ca3af; font-family: monospace; margin-top: 0.125rem;">{{ $key }}</div>
                                </td>
                                <td style="padding: 0.625rem 0.875rem; color: #111827; font-family: monospace; word-break: break-all; vertical-align: top;">
                                    @if (is_array($value))
                                        <span style="background: #e0e7ff; color: #3730a3; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">
                                            {{ json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                                        </span>
                                    @elseif (is_bool($value))
                                        <span style="background: #fef3c7; color: #92400e; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">
                                            {{ $value ? 'true' : 'false' }}
                                        </span>
                                    @elseif (is_null($value))
                                        <span style="color: #9ca3af; font-style: italic;">null</span>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Raw JSON Code View Header & Action --}}
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
                    Raw JSON Payload
                </span>
                <button
                    type="button"
                    onclick="(function(btn){ var container = btn.closest('div').parentElement; var pre = container ? container.querySelector('pre') : null; var code = pre ? (pre.textContent || pre.innerText) : ''; if (!code) return; var label = btn.querySelector('.copy-btn-text'); function setSuccess() { if (label) { var orig = label.innerText; label.innerText = 'Copied!'; label.style.color = '#059669'; setTimeout(function(){ label.innerText = orig; label.style.color = ''; }, 2000); } } if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(code).then(setSuccess).catch(function(){ fallback(code); }); } else { fallback(code); } function fallback(str) { var ta = document.createElement('textarea'); ta.value = str; ta.style.position = 'fixed'; ta.style.top = '-9999px'; ta.style.left = '-9999px'; document.body.appendChild(ta); ta.focus(); ta.select(); try { document.execCommand('copy'); setSuccess(); } catch(e) { } document.body.removeChild(ta); } })(this)"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.3125rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #374151; background: #ffffff; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);"
                >
                    <svg style="width: 14px; height: 14px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 012-2v-8a2 2 0 01-2-2h-8a2 2 0 01-2 2v8a2 2 0 012 2z"></path>
                    </svg>
                    <span class="copy-btn-text">Copy JSON</span>
                </button>
            </div>

            {{-- JSON Code Viewer Box --}}
            <pre style="background-color: #0f172a; color: #34d399; padding: 1rem; border-radius: 0.5rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; line-height: 1.6; overflow-x: auto; max-height: 350px; border: 1px solid #1e293b; margin: 0;"><code>{{ $jsonString }}</code></pre>
        </div>
    @else
        <div style="padding: 2rem; text-align: center; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 0.5rem; color: #6b7280; font-size: 0.875rem;">
            No payload data recorded for this entry.
        </div>
    @endif
</div>
