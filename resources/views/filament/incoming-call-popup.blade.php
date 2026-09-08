{{--
    Live call popup.

    Rendered once per admin page from the panel's BODY_END hook. It subscribes
    to the clinic's private call channel and draws a card when a call happens —
    while it is still ringing where the provider tells us that early, and
    otherwise the moment its log arrives.

    Written as plain JS against window.Echo rather than as a Livewire component
    on purpose: a Livewire round trip to render a popup would add latency to the
    one feature whose entire value is being on screen before the receptionist
    picks up.
--}}
@php
    /** @var \App\Models\User|null $popupUser */
    $popupUser = auth()->user();

    // A patient with a portal login must never subscribe to this. The channel
    // authorisation callback refuses them too — this just avoids the attempt.
    $canWatchCalls = $popupUser
        && ! $popupUser->hasRole(config('project.roles.client'))
        && $popupUser->can('viewAny', \App\Models\Call::class);

    // Super admins have no single clinic of their own, so they watch whichever
    // clinics exist rather than one channel.
    $clinicIds = $popupUser?->hasRole(config('project.roles.super_admin'))
        ? \App\Models\Clinic::query()->where('is_active', true)->pluck('id')->all()
        : array_filter([$popupUser?->clinic_id]);
@endphp

@if ($canWatchCalls && $clinicIds !== [])
    <div id="sgc-pop-stack" aria-live="polite" aria-atomic="false"></div>

    <script>
        (() => {
            const clinicIds = @json(array_values($clinicIds));
            const stack = document.getElementById('sgc-pop-stack');

            // Paths are built here rather than sent over the socket: the event
            // is serialised in a queue worker, where there is no panel context
            // to resolve a Filament URL from.
            const callPath = @json(rtrim(url('/calls'), '/'));
            const patientPath = @json(rtrim(url('/users'), '/'));
            const leadPath = @json(rtrim(url('/leads'), '/'));

            // Roughly the length of a ring. Past that a live call has been
            // answered or missed, and a stale card is worse than none.
            const DISMISS_AFTER_MS = 45000;

            // Someone who has asked their system to stop moving things should
            // not be handed a pulsing ring in the corner of their screen. The
            // card still arrives and still counts down; it just needs a real
            // timer, because there is no animation left to listen to.
            const stillness = window.matchMedia('(prefers-reduced-motion: reduce)');

            const seen = new Set();

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
            }

            // What the card calls itself. Direction chooses the words, liveness
            // chooses the tense: "Incoming call" while it rings, "ended" once it
            // is over. A Callyzer card is always past tense, because its log
            // only leaves the handset after the conversation.
            function stateFor(call) {
                const outgoing = call.direction === 'outgoing';

                if (call.is_live) {
                    return outgoing
                        ? { text: 'Calling out', tone: 'outgoing', live: true }
                        : { text: 'Incoming call', tone: 'incoming', live: true };
                }

                return outgoing
                    ? { text: 'Outgoing call ended', tone: 'outgoing', live: false }
                    : { text: 'Incoming call ended', tone: 'ended', live: false };
            }

            // Drawn rather than typed. The old ☎ and ↗ characters render as a
            // different shape in every font the panel might fall back to, and
            // one of them is an emoji on Windows.
            function phoneIcon(classes) {
                return `
                    <svg class="${classes}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
                    </svg>
                `;
            }

            function badgeFor(call) {
                if (call.is_ambiguous) return { text: 'Multiple matches', tone: 'warn' };
                if (call.is_patient) return { text: 'Existing patient', tone: 'ok' };
                if (call.is_lead) return { text: 'Known lead', tone: 'info' };

                return { text: 'New caller', tone: 'plain' };
            }

            // Initials give the eye something to land on before it reads. Null
            // for an unknown caller, which draws a handset instead: a "?" there
            // reads as an error rather than as a stranger.
            function initialsFor(name) {
                const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

                if (parts.length === 0) {
                    return null;
                }

                return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
            }

            function render(call) {
                // The same call reaches two subscribed channels for a super
                // admin, and a redelivery can arrive twice. Draw it once.
                if (seen.has(call.uuid)) {
                    return;
                }

                seen.add(call.uuid);

                const state = stateFor(call);
                const badge = badgeFor(call);
                const name = call.name || 'Unknown caller';
                const initials = initialsFor(call.name);

                let link = `${callPath}/${call.call_id}`;
                let linkLabel = 'Open call';

                if (call.customer_user_id) {
                    link = `${patientPath}/${call.customer_user_id}/edit`;
                    linkLabel = 'Open patient';
                } else if (call.lead_id) {
                    link = `${leadPath}/${call.lead_id}`;
                    linkLabel = 'Open lead';
                }

                const card = document.createElement('div');
                card.className = `sgc-pop sgc-pop--${state.tone}${state.live ? ' sgc-pop--live' : ''}`;
                card.setAttribute('role', 'status');

                card.innerHTML = `
                    <div class="sgc-pop__bar"></div>

                    <div class="sgc-pop__head">
                        <span class="sgc-pop__kind">
                            ${phoneIcon('sgc-pop__icon')}
                            ${escapeHtml(state.text)}
                        </span>
                        <button type="button" data-dismiss class="sgc-pop__x" aria-label="Dismiss">&times;</button>
                    </div>

                    <div class="sgc-pop__body">
                        <div class="sgc-pop__avatar">
                            ${initials ? escapeHtml(initials) : phoneIcon('sgc-pop__avatar-icon')}
                        </div>

                        <div class="sgc-pop__who">
                            <div class="sgc-pop__name">${escapeHtml(name)}</div>
                            <div class="sgc-pop__phone">${escapeHtml(call.phone || 'Number withheld')}</div>
                        </div>
                    </div>

                    <div class="sgc-pop__tags">
                        <span class="sgc-pop__badge sgc-pop__badge--${badge.tone}">${escapeHtml(badge.text)}</span>
                        ${call.exophone ? `<span class="sgc-pop__line">to ${escapeHtml(call.exophone)}</span>` : ''}
                    </div>

                    <div class="sgc-pop__actions">
                        <a href="${link}" class="sgc-pop__go">${escapeHtml(linkLabel)}</a>
                        <a href="${callPath}/${call.call_id}" class="sgc-pop__alt">Call details</a>
                    </div>

                    <div class="sgc-pop__timer"><span style="animation-duration:${DISMISS_AFTER_MS}ms"></span></div>
                `;

                let removing = false;

                const remove = () => {
                    if (removing) {
                        return;
                    }

                    removing = true;

                    // The height is frozen first so the collapse has something
                    // to animate from, and the cards below slide up rather than
                    // snapping into the gap.
                    card.style.height = card.offsetHeight + 'px';
                    card.classList.add('sgc-pop--leaving');

                    setTimeout(() => card.remove(), 260);
                };

                card.querySelector('[data-dismiss]').addEventListener('click', remove);

                const timer = card.querySelector('.sgc-pop__timer span');

                if (stillness.matches) {
                    setTimeout(remove, DISMISS_AFTER_MS);
                } else {
                    // Dismissal is driven by the draining bar rather than by a
                    // timer, so pausing the bar on hover pauses the dismissal
                    // for free — and a card that vanishes as someone reaches
                    // for it is the worst thing this component could do.
                    timer.addEventListener('animationend', remove);
                }

                stack.prepend(card);
            }

            let subscribed = false;

            function subscribe() {
                // Both entry points below can fire on the same load. Subscribing
                // twice would bind two listeners and pop every call twice.
                if (subscribed) {
                    return;
                }

                if (!window.Echo) {
                    // app.js is loaded in HEAD_END, so Echo is normally ready by
                    // the time this runs. Retrying rather than giving up covers
                    // a slow bundle on a cold cache.
                    return setTimeout(subscribe, 500);
                }

                subscribed = true;

                clinicIds.forEach((clinicId) => {
                    window.Echo.private(`clinic.${clinicId}.calls`)
                        .listen('.call-announced', (payload) => render(payload));
                });
            }

            document.addEventListener('DOMContentLoaded', subscribe);

            // Filament navigates without a full page load, so DOMContentLoaded
            // may already have fired by the time this script is parsed.
            if (document.readyState !== 'loading') {
                subscribe();
            }
        })();
    </script>

    <style>
        /*
            Self-contained on purpose. This panel registers no viteTheme(), so
            no compiled Tailwind reaches it and a utility class here would be a
            no-op. Everything the card needs is spelled out.
        */
        #sgc-pop-stack {
            position: fixed;
            top: 5rem;
            right: 1.25rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            width: 21rem;
            max-width: calc(100vw - 2.5rem);
            pointer-events: none;
        }

        .sgc-pop {
            --sgc-accent: #16a34a;
            --sgc-glow: rgba(22, 163, 74, .35);

            position: relative;
            overflow: hidden;
            pointer-events: auto;
            background: #fff;
            color: #111827;
            border: 1px solid rgba(17, 24, 39, .08);
            border-radius: .9rem;
            padding: .85rem .95rem 1rem;
            font-size: .875rem;
            box-shadow: 0 1px 2px rgba(17, 24, 39, .06), 0 12px 28px -8px rgba(17, 24, 39, .28);
            animation: sgc-pop-in .42s cubic-bezier(.16, 1, .3, 1) both;
        }

        /*
            Spelled out rather than abbreviated. These were --in / --out /
            --past, and --out collided with the exit-animation class of the same
            name: every outgoing card was told to leave the instant it arrived,
            so it flashed on screen and collapsed. A modifier that names a state
            and a modifier that names a transition must never share a word.
        */
        .sgc-pop--incoming { --sgc-accent: #16a34a; --sgc-glow: rgba(22, 163, 74, .35); }
        .sgc-pop--outgoing { --sgc-accent: #2563eb; --sgc-glow: rgba(37, 99, 235, .35); }
        .sgc-pop--ended { --sgc-accent: #9ca3af; --sgc-glow: rgba(156, 163, 175, .3); }

        /* The strip along the top, so the state reads before the words do. */
        .sgc-pop__bar {
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--sgc-accent);
        }

        /* A live call earns a travelling sheen. A finished one does not. */
        .sgc-pop--live .sgc-pop__bar::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .85), transparent);
            animation: sgc-pop-sheen 1.8s linear infinite;
        }

        .sgc-pop__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .sgc-pop__kind {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--sgc-accent);
        }

        .sgc-pop__icon {
            width: .95rem;
            height: .95rem;
            flex: 0 0 auto;
            transform-origin: 50% 70%;
        }

        /*
            A phone that shakes in bursts rather than shivering continuously.
            The still stretch between rings is what makes it read as a phone
            ringing instead of an error state demanding attention.
        */
        .sgc-pop--live .sgc-pop__icon {
            animation: sgc-pop-ring-shake 1.6s ease-in-out infinite;
        }

        .sgc-pop__avatar-icon {
            width: 1.15rem;
            height: 1.15rem;
            transform-origin: 50% 70%;
        }

        .sgc-pop--live .sgc-pop__avatar-icon {
            animation: sgc-pop-ring-shake 1.6s ease-in-out infinite;
        }

        .sgc-pop__x {
            border: 0;
            background: transparent;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.15rem;
            line-height: 1;
            padding: 0 .15rem;
            border-radius: .35rem;
            transition: color .15s, background .15s;
        }

        .sgc-pop__x:hover {
            color: #374151;
            background: rgba(17, 24, 39, .06);
        }

        .sgc-pop__body {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-top: .6rem;
        }

        .sgc-pop__avatar {
            position: relative;
            flex: 0 0 auto;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: .8125rem;
            color: #fff;
            background: var(--sgc-accent);
        }

        /* Two rings leaving the avatar, offset so one is always mid-flight. */
        .sgc-pop--live .sgc-pop__avatar::before,
        .sgc-pop--live .sgc-pop__avatar::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 999px;
            border: 2px solid var(--sgc-accent);
            animation: sgc-pop-ring 1.8s ease-out infinite;
        }

        .sgc-pop--live .sgc-pop__avatar::after {
            animation-delay: .9s;
        }

        .sgc-pop__who {
            min-width: 0;
        }

        .sgc-pop__name {
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sgc-pop__phone {
            color: #6b7280;
            font-size: .8125rem;
            font-variant-numeric: tabular-nums;
        }

        .sgc-pop__tags {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .4rem;
            margin-top: .6rem;
        }

        .sgc-pop__badge {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 999px;
            font-size: .6875rem;
            font-weight: 600;
        }

        .sgc-pop__badge--ok { background: #dcfce7; color: #166534; }
        .sgc-pop__badge--info { background: #dbeafe; color: #1e40af; }
        .sgc-pop__badge--warn { background: #fef3c7; color: #92400e; }
        .sgc-pop__badge--plain { background: #f3f4f6; color: #374151; }

        .sgc-pop__line {
            font-size: .6875rem;
            color: #9ca3af;
        }

        .sgc-pop__actions {
            display: flex;
            gap: .5rem;
            margin-top: .8rem;
        }

        .sgc-pop__go,
        .sgc-pop__alt {
            padding: .42rem .6rem;
            border-radius: .5rem;
            font-size: .8125rem;
            text-decoration: none;
            transition: transform .12s ease, filter .12s ease, background .12s ease;
        }

        .sgc-pop__go {
            flex: 1;
            text-align: center;
            font-weight: 600;
            color: #fff;
            background: var(--sgc-accent);
            box-shadow: 0 1px 2px var(--sgc-glow);
        }

        .sgc-pop__go:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }

        .sgc-pop__alt {
            color: inherit;
            border: 1px solid rgba(17, 24, 39, .12);
        }

        .sgc-pop__alt:hover {
            background: rgba(17, 24, 39, .04);
        }

        /* The countdown, and the thing that actually dismisses the card. */
        .sgc-pop__timer {
            position: absolute;
            inset: auto 0 0 0;
            height: 2px;
            background: rgba(17, 24, 39, .06);
        }

        .sgc-pop__timer span {
            display: block;
            height: 100%;
            width: 100%;
            transform-origin: left;
            background: var(--sgc-accent);
            opacity: .55;
            animation-name: sgc-pop-drain;
            animation-timing-function: linear;
            animation-fill-mode: forwards;
        }

        /*
            Hover pauses the bar, and because the bar's animationend is what
            removes the card, hovering pauses the dismissal itself. There is no
            separate timer to keep in step with it.
        */
        .sgc-pop:hover .sgc-pop__timer span {
            animation-play-state: paused;
        }

        .sgc-pop:hover {
            box-shadow: 0 1px 2px rgba(17, 24, 39, .06), 0 18px 38px -10px rgba(17, 24, 39, .34);
        }

        .sgc-pop--leaving {
            animation: sgc-pop-out .26s ease-in forwards;
        }

        @keyframes sgc-pop-in {
            from { opacity: 0; transform: translateX(1.75rem) scale(.96); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes sgc-pop-out {
            to {
                opacity: 0;
                transform: translateX(1.5rem) scale(.98);
                height: 0;
                margin-top: -.75rem;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        @keyframes sgc-pop-ring {
            0% { opacity: .7; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.9); }
        }

        @keyframes sgc-pop-ring-shake {
            0%, 55%, 100% { transform: rotate(0deg); }
            58% { transform: rotate(-15deg); }
            62% { transform: rotate(13deg); }
            66% { transform: rotate(-11deg); }
            70% { transform: rotate(9deg); }
            74% { transform: rotate(-6deg); }
            78% { transform: rotate(4deg); }
            82% { transform: rotate(0deg); }
        }

        @keyframes sgc-pop-sheen {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }

        @keyframes sgc-pop-drain {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        .dark .sgc-pop {
            background: #1f2937;
            color: #f9fafb;
            border-color: rgba(255, 255, 255, .08);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 12px 28px -8px rgba(0, 0, 0, .6);
        }

        .dark .sgc-pop__phone { color: #9ca3af; }
        .dark .sgc-pop__alt { border-color: rgba(255, 255, 255, .16); }
        .dark .sgc-pop__alt:hover { background: rgba(255, 255, 255, .06); }
        .dark .sgc-pop__x:hover { color: #e5e7eb; background: rgba(255, 255, 255, .08); }
        .dark .sgc-pop__timer { background: rgba(255, 255, 255, .08); }
        .dark .sgc-pop__badge--ok { background: rgba(22, 163, 74, .18); color: #86efac; }
        .dark .sgc-pop__badge--info { background: rgba(37, 99, 235, .18); color: #93c5fd; }
        .dark .sgc-pop__badge--warn { background: rgba(217, 119, 6, .2); color: #fcd34d; }
        .dark .sgc-pop__badge--plain { background: rgba(255, 255, 255, .08); color: #d1d5db; }

        /*
            A pulsing ring in the corner of the screen is exactly what someone
            who asked their system to stop moving things was asking it to stop.
            The card still arrives, still counts down, still dismisses — it just
            does none of it in motion.
        */
        @media (prefers-reduced-motion: reduce) {
            .sgc-pop,
            .sgc-pop--leaving,
            .sgc-pop__timer span,
            .sgc-pop--live .sgc-pop__icon,
            .sgc-pop--live .sgc-pop__avatar-icon,
            .sgc-pop--live .sgc-pop__bar::after,
            .sgc-pop--live .sgc-pop__avatar::before,
            .sgc-pop--live .sgc-pop__avatar::after {
                animation: none !important;
            }

            .sgc-pop--live .sgc-pop__avatar::before,
            .sgc-pop--live .sgc-pop__avatar::after {
                display: none;
            }

            .sgc-pop--leaving { opacity: 0; }
            .sgc-pop__go:hover { transform: none; }
        }
    </style>
@endif
