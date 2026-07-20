<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\LoyaltyPointTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppAiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = Setting::getValue('openai_api_key', config('services.openai.key', env('OPENAI_API_KEY')));
        $this->model = Setting::getValue('openai_model', 'gpt-4o-mini');
    }

    /**
     * Check if OpenAI AI features are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate response suggestions based on the conversation context and CRM data.
     */
    public function generateReply(WhatsAppConversation $conversation): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Fetch recent messages
        $messages = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        // Extract any mentioned dates from the last incoming message
        $requestedDates = [];
        $lastIncoming = $messages->last(fn($msg) => ($msg->direction?->value ?? $msg->direction) === 'incoming');
        if ($lastIncoming && !empty($lastIncoming->text_body)) {
            $requestedDates = $this->extractMentionedDates($lastIncoming->text_body);
        }

        // Build database-aware context
        $crmContext = $this->buildDatabaseContext($conversation, $requestedDates);

        $systemPrompt = $this->buildSystemPrompt($crmContext);

        $messagesPayload = [];
        $messagesPayload[] = [
            'role' => 'system',
            'content' => $systemPrompt,
            //  'content' => 'You are an AI assistant for a dermatology and aesthetics clinic called Ai Aesthetics. Generate a polite, brief, and helpful reply to the customer message. Keep answers conversational, helpful, and concise. Support the client\'s language.',
        ];

        foreach ($messages as $msg) {
            $role = ($msg->direction?->value ?? $msg->direction) === 'incoming' ? 'user' : 'assistant';
            $messagesPayload[] = [
                'role' => $role,
                'content' => $msg->text_body ?? '',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => $this->model,
                        'messages' => $messagesPayload,
                        'max_tokens' => 300,
                        'temperature' => 0.7,
                    ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? null;
            }

            Log::error('OpenAI generateReply error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI generateReply exception: ' . $e->getMessage());
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CRM Database Context Builder
    |--------------------------------------------------------------------------
    */

    /**
     * Build structured CRM context from the database for AI prompt injection.
     */
    protected function buildDatabaseContext(WhatsAppConversation $conversation, array $requestedDates = []): array
    {
        $context = [];

        // 1. Identify the client from phone number
        $client = $this->findClientByPhone($conversation->phone_number);
        $context['client'] = $this->buildClientContext($client);

        // 2. Products/Services catalog
        $context['services'] = $this->buildServicesContext();

        // 3. Client's purchased packages (if client exists)
        $context['packages'] = $client ? $this->buildPackagesContext($client) : null;

        // 4. Client's appointment history (if client exists)
        $context['appointment_history'] = $client ? $this->buildAppointmentHistoryContext($client) : null;

        // 5. Clinic list for availability
        $context['clinics'] = $this->buildClinicsContext();

        // 6. Availability summary across clinics for today, tomorrow, and any requested dates
        $context['availability'] = $this->buildAvailabilityContext($requestedDates);

        return $context;
    }

    /**
     * Find a client user by phone number (with 'client' role).
     */
    protected function findClientByPhone(?string $phoneNumber): ?User
    {
        if (empty($phoneNumber)) {
            return null;
        }

        // Normalize phone: strip country code prefix for Indian numbers
        $normalizedPhone = $phoneNumber;
        if (str_starts_with($phoneNumber, '91') && strlen($phoneNumber) === 12) {
            $normalizedPhone = substr($phoneNumber, 2);
        }

        return User::where(function ($query) use ($phoneNumber, $normalizedPhone) {
            $query->where('mobile', $phoneNumber)
                ->orWhere('mobile', $normalizedPhone)
                ->orWhere('mobile', '91' . $normalizedPhone)
                ->orWhere('mobile', '+91' . $normalizedPhone);
        })
            ->whereHas('roles', fn($q) => $q->where('name', 'client'))
            ->first();
    }

    /**
     * Build client profile context string.
     */
    protected function buildClientContext(?User $client): ?string
    {
        if (!$client) {
            return null;
        }

        $parts = [];
        $parts[] = "Name: {$client->first_name} {$client->last_name}";

        if ($client->gender) {
            $parts[] = "Gender: {$client->gender}";
        }
        if ($client->skin_type) {
            $parts[] = "Skin Type: {$client->skin_type}";
        }
        // Loyalty Points Details
        $currentBalance = $client->getLoyaltyBalance();
        $minRedeem = Setting::getLoyaltyMinRedeem();
        $ratePercent = Setting::getLoyaltyRate();

        $lastEarnTx = LoyaltyPointTransaction::where('user_id', $client->id)
            ->where('type', 'earn')
            ->latest()
            ->first();

        $loyaltyDetails = "Loyalty Program: ENABLED | Available Balance: {$currentBalance} points (1 pt = ₹1)";
        if ($lastEarnTx) {
            $earnedDate = Carbon::parse($lastEarnTx->created_at)->format('d M Y');
            $loyaltyDetails .= " | Last Earned: +{$lastEarnTx->points} pts on {$earnedDate}";
        } else {
            $loyaltyDetails .= " | Last Earned: No points earned yet";
        }

        if ($currentBalance >= $minRedeem) {
            $loyaltyDetails .= " | Redemption Status: Eligible to redeem (Minimum threshold of {$minRedeem} pts met)";
        } else {
            $needed = $minRedeem - $currentBalance;
            $loyaltyDetails .= " | Redemption Status: Minimum {$minRedeem} pts required to redeem (Need {$needed} more pts)";
        }

        $parts[] = $loyaltyDetails;

        // Medical conditions
        $conditions = [];
        if ($client->has_diabetes)
            $conditions[] = 'Diabetes';
        if ($client->has_high_bp)
            $conditions[] = 'High BP';
        if ($client->has_thyroid)
            $conditions[] = 'Thyroid';
        if ($client->has_pcos)
            $conditions[] = 'PCOS';
        if ($client->has_asthma)
            $conditions[] = 'Asthma';
        if ($client->allergies)
            $conditions[] = "Allergies: {$client->allergies}";

        if (!empty($conditions)) {
            $parts[] = "Medical Notes: " . implode(', ', $conditions);
        }

        // Aesthetic goals
        $goals = [];
        if ($client->goal_youthful)
            $goals[] = 'Youthful look';
        if ($client->goal_attractive)
            $goals[] = 'More attractive';
        if ($client->goal_slim_face)
            $goals[] = 'Slimmer face';
        if ($client->goal_soft_features)
            $goals[] = 'Softer features';
        if ($client->goal_less_tired)
            $goals[] = 'Less tired';
        if ($client->goal_less_saggy)
            $goals[] = 'Less saggy';

        if (!empty($goals)) {
            $parts[] = "Aesthetic Goals: " . implode(', ', $goals);
        }

        return implode(" | ", $parts);
    }

    /**
     * Build services catalog context string.
     */
    protected function buildServicesContext(): string
    {
        $services = Product::where('is_active', true)
            ->where('type', 'service')
            ->orderBy('name')
            ->limit(25)
            ->get(['name', 'sell_price', 'description']);

        if ($services->isEmpty()) {
            return 'No services currently listed.';
        }

        $lines = [];
        foreach ($services as $service) {
            $line = "- {$service->name}: ₹" . number_format($service->sell_price, 0);
            if ($service->description) {
                $line .= " ({$service->description})";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Build client's active packages context string.
     */
    protected function buildPackagesContext(User $client): ?string
    {
        $packages = UserPackage::where('user_id', $client->id)
            ->where('is_active', true)
            ->with(['items.service:id,name'])
            ->limit(5)
            ->get();

        if ($packages->isEmpty()) {
            return null;
        }

        $lines = [];
        foreach ($packages as $pkg) {
            // Calculate sessions inline to avoid stdClass method errors
            $items = $pkg->items ?? collect();
            $totalSessions = $items->sum('quantity');
            $usedSessions = $items->sum('used_sessions');
            $remaining = max(0, $totalSessions - $usedSessions);

            $expiry = 'No expiry';
            if ($pkg->expired_at) {
                $expiry = $pkg->expired_at instanceof \DateTimeInterface
                    ? $pkg->expired_at->format('d M Y')
                    : Carbon::parse($pkg->expired_at)->format('d M Y');
            }

            $serviceNames = $items->map(fn($item) => $item->service?->name ?? 'Unknown')->implode(', ');

            $lines[] = "- {$pkg->package_name}: Services [{$serviceNames}], Sessions: {$usedSessions}/{$totalSessions} used, {$remaining} remaining, Expires: {$expiry}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build client's recent appointment history context string.
     */
    protected function buildAppointmentHistoryContext(User $client): ?string
    {
        $appointments = Appointment::where('user_id', $client->id)
            ->with(['therapist:id,first_name,last_name'])
            ->orderByDesc('start_datetime')
            ->limit(5)
            ->get();

        if ($appointments->isEmpty()) {
            return null;
        }

        $lines = [];
        foreach ($appointments as $appt) {
            $date = Carbon::parse($appt->start_datetime)->format('d M Y, h:i A');
            $therapistName = $appt->therapist
                ? "{$appt->therapist->first_name} {$appt->therapist->last_name}"
                : 'Unassigned';

            $type = $appt->type instanceof \BackedEnum ? $appt->type->value : (string) $appt->type;
            $status = $appt->status instanceof \BackedEnum ? $appt->status->value : (string) $appt->status;

            $lines[] = "- {$date} | Type: {$type} | Status: {$status} | Therapist: {$therapistName}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build clinics list context string.
     */
    protected function buildClinicsContext(): string
    {
        $clinics = Clinic::where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%Jaipur%');
            })
            ->get(['id', 'name', 'address_line1', 'address_line2', 'pincode', 'city', 'state', 'start_time', 'end_time', 'phone']);

        if ($clinics->isEmpty()) {
            return 'No clinic locations available.';
        }

        $lines = [];
        foreach ($clinics as $clinic) {
            $addrParts = [];
            if ($clinic->address_line1) {
                $addrParts[] = $clinic->address_line1;
            }
            if ($clinic->address_line2) {
                $addrParts[] = $clinic->address_line2;
            }
            if ($clinic->city) {
                $addrParts[] = $clinic->city;
            }
            if ($clinic->state) {
                $addrParts[] = $clinic->state;
            }
            if ($clinic->pincode) {
                $addrParts[] = $clinic->pincode;
            }
            $fullAddress = implode(', ', $addrParts);

            $hours = '';
            if ($clinic->start_time && $clinic->end_time) {
                $hours = " | Hours: {$clinic->start_time} - {$clinic->end_time}";
            }
            $phone = $clinic->phone ? " | Phone: {$clinic->phone}" : '';

            $lines[] = "- Clinic Name: {$clinic->name} | Address: {$fullAddress}{$hours}{$phone}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build availability summary across all active clinics for today, tomorrow, and dynamically requested dates.
     */
    protected function buildAvailabilityContext(array $requestedDates = []): string
    {
        $clinics = Clinic::where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%AI Aesthetics Jaipur%')
                    ->orWhere('name', 'like', '%AI Aesthetics%');
            })
            ->get();

        if ($clinics->isEmpty()) {
            return 'No availability data.';
        }

        $lines = [];

        foreach ($clinics as $clinic) {
            $periods = [];

            // 1. Today
            $todaySlots = $this->getAvailableSlots($clinic, Carbon::today());
            $todayText = !empty($todaySlots) ? implode(', ', $todaySlots) : 'Fully booked';
            $periods[] = "Today (" . Carbon::today()->format('d M Y') . "): [" . $todayText . "]";

            // 2. Tomorrow
            $tomorrowSlots = $this->getAvailableSlots($clinic, Carbon::tomorrow());
            $tomorrowText = !empty($tomorrowSlots) ? implode(', ', $tomorrowSlots) : 'Fully booked';
            $periods[] = "Tomorrow (" . Carbon::tomorrow()->format('d M Y') . "): [" . $tomorrowText . "]";

            // 3. Any specifically requested dates
            foreach ($requestedDates as $reqDate) {
                if ($reqDate->isToday() || $reqDate->isTomorrow()) {
                    continue;
                }
                $reqSlots = $this->getAvailableSlots($clinic, $reqDate);
                $reqText = !empty($reqSlots) ? implode(', ', $reqSlots) : 'Fully booked';
                $periods[] = $reqDate->format('d M Y') . ": [" . $reqText . "]";
            }

            $lines[] = "{$clinic->name}:\n  " . implode("\n  ", $periods);
        }

        return implode("\n\n", $lines);
    }

    /**
     * Get available 1-hour time slots for a clinic on a given date.
     */
    protected function getAvailableSlots(Clinic $clinic, Carbon $date): array
    {
        $startHour = 10; // Default 10 AM
        $endHour = 19;   // Default 7 PM

        if ($clinic->start_time) {
            $startHour = (int) Carbon::parse($clinic->start_time)->format('G');
        }
        if ($clinic->end_time) {
            $endHour = (int) Carbon::parse($clinic->end_time)->format('G');
        }

        // Get booked appointment times for this clinic on the given date
        $bookedSlots = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->pluck('start_datetime')
            ->map(fn($dt) => (int) Carbon::parse($dt)->format('G'))
            ->toArray();

        $available = [];
        $currentHour = $date->isToday() ? max($startHour, (int) now()->format('G') + 1) : $startHour;

        for ($hour = $currentHour; $hour < $endHour; $hour++) {
            if (!in_array($hour, $bookedSlots)) {
                $available[] = Carbon::createFromTime($hour, 0)->format('h:i A');
            }
        }

        return $available;
    }

    /*
    |--------------------------------------------------------------------------
    | System Prompt Builder
    |--------------------------------------------------------------------------
    */

    /**
     * Build the full system prompt with CRM context injected.
     */
    protected function buildSystemPrompt(array $context): string
    {
        $clinic = Clinic::where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%AI Aesthetics Jaipur%')
                    ->orWhere('name', 'like', '%AI Aesthetics%');
            })
            ->first(['phone']);

        $whatsappLink = 'https://wa.me/918169308873'; // Default fallback
        if ($clinic && $clinic->phone) {
            $cleanPhone = preg_replace('/\D/', '', $clinic->phone);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = substr($cleanPhone, 1);
            }
            if (strlen($cleanPhone) === 10) {
                $cleanPhone = '91' . $cleanPhone;
            }
            $whatsappLink = 'https://wa.me/' . $cleanPhone;
        }

        $prompt = "You are an AI assistant for a dermatology, skin, hair, and wellness clinic called **AI Aesthetics Jaipur**.\n";
        $prompt .= "The clinic is founded by Dr. Aakriti Mehra. Website: https://ai-aesthetics.in.\n";
        $prompt .= "You help clients with treatment inquiries, pricing, appointment booking, package sessions, and clinic information. Support the client's language (Hindi, English, Hinglish, etc.) naturally and conversationally.\n\n";

        $prompt .= "=== CLINIC KNOWLEDGE (FROM WEBSITE https://ai-aesthetics.in) ===\n";
        $prompt .= "- **AI Skin Analysis**: Our signature starting point. Multi-light skin imaging maps pigmentation, hydration, pores, texture, and redness. Decisions are always doctor-led (AI supports, but does not replace clinical judgment).\n";
        $prompt .= "- **Advanced Facials**: AI Customized Facial, AI Facial Express, Express Clean-Up, HydraFacial, Carbon Facial, Sensitive Skin Recovery Facial, Post-Travel Recovery Facial, Vampire PRP, Salmon Facial, HIFU Skin Lift.\n";
        $prompt .= "- **Laser Hair Removal**: Available for face, full body, underarms, bikini, beard shaping, designed for Indian skin.\n";
        $prompt .= "- **Chemical Peels**: Glow Peel, Acne Peel, Acne Marks Peel, Pigmentation Peel, Detan Peel, Yellow Peel, Cosmelan (melasma & deep pigmentation).\n";
        $prompt .= "- **IV Wellness Drips**: Immunity IV, Glow IV, Pre-Bridal IV, NAD+ Therapy.\n";
        $prompt .= "- **Doctor-led Aesthetic Care**: Botox, Fillers, Skin Boosters (Profhilo, etc.), Threads (scheduled on Dr. Aakriti visit days).\n";
        $prompt .= "- **WEBSITE PHILOSOPHY & EXPECTATIONS (https://ai-aesthetics.in)**: When clients ask about expectations, suitability, or how much improvement they can get for any concern (such as pigmentation, acne, etc.), always align with our website's principles: explain that improvement depends on the depth and type of the concern, caution that Indian skin requires a careful/staged approach (avoiding aggressive treatments that can trigger rebound issues or irritation), and recommend starting with an AI Skin Analysis to map the skin before choosing a treatment.\n\n";

        $prompt .= "=== WEBSITE URL PATTERNS (https://ai-aesthetics.in) ===\n";
        $prompt .= "Construct and provide a direct link to the relevant page on our website depending on the client's question:\n";
        $prompt .= "- General Treatments: https://ai-aesthetics.in/jaipur/[treatment-slug] (where treatment-slug can be: chemical-peel, laser-hair-removal, facials, hydrafacial, carbon-facial, iv-drip-therapy, hair-treatments, ai-skin-analysis)\n";
        $prompt .= "- Skin Concerns: https://ai-aesthetics.in/jaipur/[concern-slug] (where concern-slug can be: pigmentation-treatment, melasma-treatment, dark-spots-treatment, post-acne-pigmentation-treatment, acne-treatment, acne-scar-treatment, tan-removal-treatment, uneven-skin-tone-treatment)\n";
        $prompt .= "- Specific Chemical Peels: Append the name as an anchor to chemical-peel page, e.g., https://ai-aesthetics.in/jaipur/chemical-peel#[peel-slug] (where peel-slug is: glow-peel, acne-peel, acne-marks-peel, pigmentation-peel, detan-peel, yellow-peel, cosmelan-treatment, enzymatic-peel)\n";
        $prompt .= "- General Pages: About Dr. Aakriti is https://ai-aesthetics.in/dr-aakriti-mehra, Pricing is https://ai-aesthetics.in/jaipur/pricing, Location is https://ai-aesthetics.in/jaipur/c-scheme\n\n";

        $loyaltyRate = Setting::getLoyaltyRate();
        $minRedeemPoints = Setting::getLoyaltyMinRedeem();

        $prompt .= "=== LOYALTY POINTS PROGRAM INFORMATION ===\n";
        $prompt .= "- **Program Status**: ENABLED at AI Aesthetics Jaipur.\n";
        $prompt .= "- **Earning Rule**: Clients earn {$loyaltyRate}% back in loyalty points on all eligible treatment and service payments.\n";
        $prompt .= "- **Redemption Value**: 1 Loyalty Point = ₹1 discount on invoice payments.\n";
        $prompt .= "- **Minimum Threshold**: A minimum balance of {$minRedeemPoints} points is required before points can be redeemed.\n";
        $prompt .= "- **LOYALTY INQUIRIES INSTRUCTIONS**:\n";
        $prompt .= "  * When a KNOWN CLIENT (in CRM) asks about loyalty points, check their available points balance, their last earned points (+pts & date), and tell them if they are eligible to redeem or how many points they need to reach {$minRedeemPoints} pts.\n";
        $prompt .= "  * When an UNKNOWN/NEW CLIENT asks about loyalty points, explain that our loyalty program is active ({$loyaltyRate}% earning back, 1 pt = ₹1), and warmly inform them that they have 0 points currently but will automatically start earning points upon their first registered visit/payment.\n";
        $prompt .= "  * **STRICT PRIVACY / OWN POINTS ONLY**: ONLY share loyalty points data for the specific client currently chatting (given in KNOWN CLIENT). NEVER disclose, confirm, or share loyalty point balances, transactions, or account details of ANY other user, friend, relative, or phone number under any circumstances. If asked about another person's points, politely decline due to privacy policy.\n\n";

        $prompt .= "=== CRITICAL RULES ===\n";
        $prompt .= "- **NO CODING ANSWERS**: Never provide any programming, coding-level, databases, API, webhook, development, or code-related responses. If asked technical questions or code-related prompts, politely refuse and steer back to skin services.\n";
        $prompt .= "- **CLINIC IDENTITY**: Always refer to the clinic only as \"AI Aesthetics Jaipur\".\n";
        $prompt .= "- **PRIVACY RESTRICTION**: Strictly provide loyalty point info ONLY for the active client chatting. NEVER share or reveal any other user's loyalty points, balance, or CRM information.\n";
        $prompt .= "- **PRICE CITATION**: Only quote prices from the CRM data listed below. If a price is not listed, refer them to the team or tell them to check the website/contact the clinic.\n";
        $prompt .= "- **BOOKING RULE**: Do not say 'appointment booked' yourself. Check the CLINIC LOCATIONS section below, share the specific clinic's name, phone number, and address from that dynamic data, and ask the client to contact them directly to book.\n";
        $prompt .= "- **SPECIFIC WEBPAGE LINKS**: When answering questions about a concern, treatment, price, or doctor, you **MUST** construct and provide the corresponding link using the WEBSITE URL PATTERNS rules above so the client can explore details directly on the website.\n";
        $prompt .= "- **CONNECTING TO TEAM**: If a client asks about something not in the data, or you say \"Let me connect you with our team for more details...\", you **MUST** include this WhatsApp chat link: {$whatsappLink}.\n";
        $prompt .= "- **CONCISE**: Keep responses friendly, warm, clear, and under 150 words.\n\n";

        // Client context
        if (!empty($context['client'])) {
            $prompt .= "=== KNOWN CLIENT ===\n";
            $prompt .= $context['client'] . "\n\n";
        } else {
            $prompt .= "=== CLIENT STATUS ===\n";
            $prompt .= "This is a new/unregistered customer. Be welcoming and offer to help.\n\n";
        }

        // Services catalog
        $prompt .= "=== OUR SERVICES & PRICES ===\n";
        $prompt .= $context['services'] . "\n\n";

        // Client's packages
        if (!empty($context['packages'])) {
            $prompt .= "=== CLIENT'S ACTIVE PACKAGES ===\n";
            $prompt .= $context['packages'] . "\n\n";
        }

        // Client's appointment history
        if (!empty($context['appointment_history'])) {
            $prompt .= "=== CLIENT'S RECENT APPOINTMENTS ===\n";
            $prompt .= $context['appointment_history'] . "\n\n";
        }

        // Clinic locations
        $prompt .= "=== CLINIC LOCATIONS ===\n";
        $prompt .= $context['clinics'] . "\n\n";

        // Availability
        $prompt .= "=== APPOINTMENT AVAILABILITY ===\n";
        $prompt .= $context['availability'] . "\n";

        return $prompt;
    }

    /*
    |--------------------------------------------------------------------------
    | Other AI Utility Methods (Unchanged)
    |--------------------------------------------------------------------------
    */

    /**
     * Detect the language of an incoming message.
     */
    public function detectLanguage(string $text): ?string
    {
        if (!$this->isConfigured() || empty($text)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => $this->model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Identify the language of the user text. Reply with ONLY the name of the language (e.g. English, Hindi, Spanish).',
                            ],
                            [
                                'role' => 'user',
                                'content' => $text,
                            ],
                        ],
                        'max_tokens' => 10,
                        'temperature' => 0.3,
                    ]);

            if ($response->successful()) {
                return trim($response->json()['choices'][0]['message']['content'] ?? '');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI detectLanguage exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Summarize the conversation history.
     */
    public function summarizeConversation(WhatsAppConversation $conversation): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $messages = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'No messages in conversation.';
        }

        $dialogue = '';
        foreach ($messages as $msg) {
            $sender = ($msg->direction?->value ?? $msg->direction) === 'incoming' ? 'Customer' : 'Clinic';
            $dialogue .= "{$sender}: {$msg->text_body}\n";
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => $this->model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Summarize the following customer conversation log in 2-3 brief bullet points listing main concern, actions taken, and next steps.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $dialogue,
                            ],
                        ],
                        'max_tokens' => 100,
                        'temperature' => 0.5,
                    ]);

            if ($response->successful()) {
                return trim($response->json()['choices'][0]['message']['content'] ?? '');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI summarizeConversation exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Analyze sentiment of a message.
     */
    public function analyzeSentiment(string $text): ?string
    {
        if (!$this->isConfigured() || empty($text)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => $this->model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Determine the sentiment of the user text. Reply with exactly one word: positive, negative, or neutral.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $text,
                            ],
                        ],
                        'max_tokens' => 10,
                        'temperature' => 0.2,
                    ]);

            if ($response->successful()) {
                return strtolower(trim($response->json()['choices'][0]['message']['content'] ?? 'neutral'));
            }

            return 'neutral';
        } catch (\Exception $e) {
            Log::error('OpenAI analyzeSentiment exception: ' . $e->getMessage());
            return 'neutral';
        }
    }

    /**
     * Parse any specific date mentioned in the user's message text.
     */
    protected function extractMentionedDates(string $text): array
    {
        $dates = [];

        // Remove ordinal suffixes like 1st, 2nd, 3rd, 4th, etc.
        $cleanText = preg_replace('/(\d+)(st|nd|rd|th)/i', '$1', $text);

        // Months match
        $monthsRegex = '(january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|october|oct|november|nov|december|dec)';

        // Pattern 1: DD Month YYYY or DD Month
        if (preg_match_all('/\b\d{1,2}\s+' . $monthsRegex . '(\s+\d{2,4})?\b/i', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse($match);
                } catch (\Exception $e) {
                }
            }
        }

        // Pattern 2: Month DD YYYY or Month DD
        if (preg_match_all('/\b' . $monthsRegex . '\s+\d{1,2}(\s+\d{2,4})?\b/i', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse($match);
                } catch (\Exception $e) {
                }
            }
        }

        // Pattern 3: YYYY-MM-DD or DD-MM-YYYY or DD/MM/YYYY
        if (preg_match_all('/\b\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4}\b/', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse($match);
                } catch (\Exception $e) {
                }
            }
        }

        // Relative days
        if (preg_match('/\bday after tomorrow\b/i', $cleanText)) {
            $dates[] = Carbon::today()->addDays(2);
        }

        // Filter valid unique future/today dates to keep it relevant
        $uniqueDates = [];
        foreach ($dates as $d) {
            $formatted = $d->format('Y-m-d');
            if (!isset($uniqueDates[$formatted])) {
                $uniqueDates[$formatted] = $d;
            }
        }

        return array_values($uniqueDates);
    }
}
