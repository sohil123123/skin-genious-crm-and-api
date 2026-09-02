<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\AppointmentUpdateRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Clinic;
use App\Models\LoyaltyPointTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserWeeklySchedule;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Availability\AvailabilityService;
use App\Services\Availability\UnavailableSlotService;
use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

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
        $lastMessageText = $lastIncoming?->text_body ?? null;
        if ($lastIncoming && !empty($lastIncoming->text_body)) {
            $requestedDates = $this->extractMentionedDates($lastIncoming->text_body);
        }

        // Build database-aware context
        $crmContext = $this->buildDatabaseContext($conversation, $requestedDates, $lastMessageText);

        $systemPrompt = $this->buildSystemPrompt($crmContext);

        $messagesPayload = [];
        $messagesPayload[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        foreach ($messages as $msg) {
            // Reactions, images and other non-text messages have no text body.
            // Sending them as blank turns makes the model invent filler replies.
            $body = trim((string) ($msg->text_body ?? ''));
            if ($body === '') {
                continue;
            }

            $role = ($msg->direction?->value ?? $msg->direction) === 'incoming' ? 'user' : 'assistant';
            $messagesPayload[] = [
                'role' => $role,
                'content' => $body,
            ];
        }

        try {
            // Build the API request payload with tool definitions
            $apiPayload = [
                'model' => $this->model,
                'messages' => $messagesPayload,
                'max_tokens' => 500,
                'temperature' => 0.7,
                'tools' => $this->getAppointmentTools(),
                'tool_choice' => 'auto',
            ];

            // Tool-call loop: keep calling OpenAI until we get a text response
            $maxIterations = 5;
            for ($i = 0; $i < $maxIterations; $i++) {
                $response = Http::retry(3, 2000)->timeout(45)->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, $apiPayload);

                if (!$response->successful()) {
                    Log::error('OpenAI generateReply error: ' . $response->body());
                    return null;
                }

                $choice = $response->json()['choices'][0] ?? null;
                if (!$choice) {
                    return null;
                }

                $assistantMessage = $choice['message'];
                $finishReason = $choice['finish_reason'] ?? 'stop';

                // If the AI wants to call tools
                if ($finishReason === 'tool_calls' && !empty($assistantMessage['tool_calls'])) {
                    // Append the assistant's tool_calls message to the payload
                    $apiPayload['messages'][] = $assistantMessage;

                    // Execute each tool call and append results
                    foreach ($assistantMessage['tool_calls'] as $toolCall) {
                        $toolName = $toolCall['function']['name'] ?? '';
                        $toolArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                        $toolCallId = $toolCall['id'] ?? '';

                        Log::info("AI Tool Call: {$toolName}", $toolArgs);

                        $toolResult = $this->executeToolCall($toolName, $toolArgs, $conversation);

                        Log::info("AI Tool Result for {$toolName}: " . substr($toolResult, 0, 500));

                        $apiPayload['messages'][] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => $toolResult,
                        ];
                    }

                    // Continue the loop to get the final text response
                    continue;
                }

                // Normal text response — sanitize double asterisks ** to single asterisk * for WhatsApp bolding
                $content = $assistantMessage['content'] ?? null;
                if ($content !== null) {
                    $content = str_replace('**', '*', $content);
                }
                return $content;
            }

            Log::warning('OpenAI tool-call loop exceeded max iterations for conversation #' . $conversation->id);
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
    protected function buildDatabaseContext(WhatsAppConversation $conversation, array $requestedDates = [], ?string $lastMessageText = null): array
    {
        $context = [];

        // 1. Identify the client from phone number
        $client = $this->findClientByPhone($conversation->phone_number);
        $context['client'] = $this->buildClientContext($client);

        // 2. Products/Services catalog
        $context['services'] = $this->buildServicesContext($lastMessageText);

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
     * Build services catalog context string, fetching ALL active services with priority matching for user query.
     */
    protected function buildServicesContext(?string $lastMessageText = null): string
    {
        $services = Product::where('is_active', true)
            ->where('type', 'service')
            ->orderBy('name')
            ->get(['name', 'sell_price', 'description']);

        if ($services->isEmpty()) {
            return 'No services currently listed.';
        }

        $matchedLines = [];
        $regularLines = [];

        // Check if user mentioned any keyword for targeted price matching
        $cleanQuery = strtolower(trim($lastMessageText ?? ''));

        foreach ($services as $service) {
            $nameLower = strtolower($service->name);
            $line = "- {$service->name}: ₹" . number_format($service->sell_price, 0);
            if ($service->description) {
                $line .= " ({$service->description})";
            }

            // Fuzzy/substring match check for misspelled terms like 'haydra', 'peel', 'carbon', etc.
            $isMatched = false;
            if (!empty($cleanQuery)) {
                if (str_contains($nameLower, $cleanQuery) || str_contains($cleanQuery, $nameLower)) {
                    $isMatched = true;
                } elseif (str_contains($cleanQuery, 'haydra') && str_contains($nameLower, 'hydra')) {
                    $isMatched = true;
                }
            }

            if ($isMatched) {
                $matchedLines[] = "  * [EXACT MATCHED SERVICE] {$service->name}: ₹" . number_format($service->sell_price, 0);
            }

            $regularLines[] = $line;
        }

        $output = '';
        if (!empty($matchedLines)) {
            $output .= "PRIORITY MATCHED SERVICES FOR CLIENT QUERY:\n" . implode("\n", $matchedLines) . "\n\nALL CLINIC SERVICES CATALOG:\n";
        }
        $output .= implode("\n", $regularLines);

        return $output;
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
            $startDt = Carbon::parse($appt->start_datetime);
            $isPast = $startDt->isPast();
            $date = $startDt->format('d M Y, h:i A');
            $therapistName = $appt->therapist
                ? "{$appt->therapist->first_name} {$appt->therapist->last_name}"
                : 'Unassigned';

            $type = $appt->type instanceof BackedEnum ? $appt->type->value : (string) $appt->type;
            $status = $appt->status instanceof BackedEnum ? $appt->status->value : (string) $appt->status;

            $timeTag = $isPast ? '[PAST]' : '[UPCOMING]';
            $lines[] = "- {$timeTag} {$date} | Type: {$type} | Status: {$status} | Therapist: {$therapistName}";
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

            $lines[] = "- Clinic Name: {$clinic->name} | Address: {$fullAddress}{$hours} | Working Days: Monday to Saturday (Closed Sundays){$phone}";
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
            if (Carbon::today()->isSunday()) {
                $todayText = 'CLOSED - Weekly Holiday (Clinic Closed Every Sunday)';
            } else {
                $todaySlots = $this->getAvailableSlots($clinic, Carbon::today());
                $todayText = !empty($todaySlots) ? implode(', ', $todaySlots) : 'Fully booked';
            }
            $periods[] = "Today (" . Carbon::today()->format('d M Y, l') . "): [" . $todayText . "]";

            // 2. Tomorrow
            if (Carbon::tomorrow()->isSunday()) {
                $tomorrowText = 'CLOSED - Weekly Holiday (Clinic Closed Every Sunday)';
            } else {
                $tomorrowSlots = $this->getAvailableSlots($clinic, Carbon::tomorrow());
                $tomorrowText = !empty($tomorrowSlots) ? implode(', ', $tomorrowSlots) : 'Fully booked';
            }
            $periods[] = "Tomorrow (" . Carbon::tomorrow()->format('d M Y, l') . "): [" . $tomorrowText . "]";

            // 3. Any specifically requested dates
            foreach ($requestedDates as $reqDate) {
                if ($reqDate->isToday() || $reqDate->isTomorrow()) {
                    continue;
                }
                if ($reqDate->isSunday()) {
                    $reqText = 'CLOSED - Weekly Holiday (Clinic Closed Every Sunday)';
                } else {
                    $reqSlots = $this->getAvailableSlots($clinic, $reqDate);
                    $reqText = !empty($reqSlots) ? implode(', ', $reqSlots) : 'Fully booked';
                }
                $periods[] = $reqDate->format('d M Y (l)') . ": [" . $reqText . "]";
            }

            $lines[] = "{$clinic->name}:\n  " . implode("\n  ", $periods);
        }

        return implode("\n\n", $lines);
    }

    /**
     * Get available 15-minute time slots for a clinic on a given date using AppointmentController::getSlots API.
     */
    protected function getAvailableSlots(Clinic $clinic, Carbon $date): array
    {
        $resJson = $this->handleCheckSlots($date->toDateString());
        $resData = json_decode($resJson, true);

        $slots = [];
        if (!empty($resData['available_slots']) && is_array($resData['available_slots'])) {
            foreach ($resData['available_slots'] as $slot) {
                if (isset($slot['time'])) {
                    $slots[] = $slot['time'];
                }
            }
        }

        return $slots;
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

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $dayAfterTomorrow = Carbon::today()->addDays(2);

        $prompt = "You are an AI assistant for a dermatology, skin, hair, and wellness clinic called **AI Aesthetics Jaipur**.\n";
        $prompt .= "The clinic is founded by Dr. Aakriti Mehra. Website: https://ai-aesthetics.in.\n";
        $prompt .= "You help clients with treatment inquiries, pricing, appointment booking, package sessions, and clinic information. Support the client's language (Hindi, English, Hinglish, etc.) naturally and conversationally.\n\n";

        $prompt .= "=== CURRENT REAL-TIME DATE & CALENDAR CONTEXT ===\n";
        $prompt .= "- **TODAY**: " . $today->format('l, d F Y') . " (Y-m-d: " . $today->format('Y-m-d') . ")\n";
        $prompt .= "- **TOMORROW**: " . $tomorrow->format('l, d F Y') . " (Y-m-d: " . $tomorrow->format('Y-m-d') . ")\n";
        $prompt .= "- **DAY AFTER TOMORROW**: " . $dayAfterTomorrow->format('l, d F Y') . " (Y-m-d: " . $dayAfterTomorrow->format('Y-m-d') . ")\n";
        $prompt .= "- **CURRENT TIME**: " . now()->format('h:i A') . "\n";
        $prompt .= "- **STRICT DATE CALCULATIONS**:\n";
        $prompt .= "  * 'Today' MUST ALWAYS map to " . $today->format('d F Y') . " (" . $today->format('Y-m-d') . ").\n";
        $prompt .= "  * 'Tomorrow' MUST ALWAYS map to " . $tomorrow->format('d F Y') . " (" . $tomorrow->format('Y-m-d') . "). NEVER confuse 'Tomorrow' with 'Day after tomorrow'.\n";
        $prompt .= "  * 'Day after tomorrow' MUST ALWAYS map to " . $dayAfterTomorrow->format('d F Y') . " (" . $dayAfterTomorrow->format('Y-m-d') . ").\n\n";

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
        $prompt .= "- **STRICT CRM PRICE CITATION**: You MUST ONLY quote exact prices directly from the CRM data listed in '=== OUR SERVICES & PRICES ===' below (e.g. Hydrafacial is ₹4,000). NEVER invent, guess, or quote any price (such as ₹6,000) that is not in the CRM data. If a client misspells a treatment name (such as 'Haydra' for 'Hydrafacial' or 'Pigmintensan' for 'Pigmentation Peel'), map it to the matching CRM service name and state its EXACT CRM price.\n";
        $prompt .= "- **SPECIFIC WEBPAGE LINKS**: When answering questions about a concern, treatment, price, or doctor, you **MUST** construct and provide the corresponding link using the WEBSITE URL PATTERNS rules above so the client can explore details directly on the website.\n";
        $prompt .= "- **CONNECTING TO TEAM**: If a client asks about something not in the data, or you say \"Let me connect you with our team for more details...\", you **MUST** include this WhatsApp chat link: {$whatsappLink}.\n";
        $prompt .= "- **SUNDAY HOLIDAY**: Sunday is a weekly holiday for AI Aesthetics Jaipur. The clinic is CLOSED every Sunday. NEVER book or reschedule appointments on Sundays. If a client asks for a Sunday appointment, warmly inform them: 'Our clinic is closed on Sundays (Weekly Holiday). We are open Monday through Saturday from 10:00 AM to 7:00 PM. Would you like to book for Monday or another working day?'\n";
        $prompt .= "- **KEYWORD BOLDING RULE**: ALWAYS bold main action words, status, dates, times, and key choices using single asterisks * (e.g. *cancel*, *reschedule*, *book*, *confirm*, *pending*, *confirmed*, *date*, *time*). Example: 'Would you like to *reschedule* or *cancel* your appointment on *25th July at 5:30 PM*? Type *confirm* to proceed.' NEVER use double asterisks ** anywhere.\n";
        $prompt .= "- **CONCISE**: Keep responses friendly, warm, clear, and under 150 words.\n\n";

        $prompt .= "=== CONVERSATION ETIQUETTE (VERY IMPORTANT) ===\n";
        $prompt .= "Clients get confused and annoyed when the assistant keeps talking after their question is already answered. Follow these rules strictly:\n";
        $prompt .= "- **ACKNOWLEDGEMENTS END THE CHAT**: If the client's last message is only an acknowledgement or a closing courtesy — such as 'ok', 'okay', 'thanks', 'thank you', 'thank you so much', 'got it', 'noted', 'done', 'sure', 'fine', 'haan', 'theek hai', 'accha', 'shukriya', 'bye', or just an emoji like 👍 🙏 ❤️ 😊 — the conversation is FINISHED. Reply with AT MOST one short, warm closing line (e.g. 'Happy to help! 😊' or 'Anytime! See you soon.'). Then STOP.\n";
        $prompt .= "- **NEVER RE-OPEN A CLOSED CHAT**: After an acknowledgement, do NOT summarize what was already discussed, do NOT repeat the appointment details, do NOT re-share links or prices, do NOT suggest new treatments, and do NOT ask a new question like 'Is there anything else?' or 'Would you also like to book...?'. The client did not ask anything — answer nothing.\n";
        $prompt .= "- **NO UNSOLICITED UPSELLING**: Never push additional treatments, packages, or offers that the client did not ask about. Only recommend a treatment when the client describes a concern or asks for a suggestion.\n";
        $prompt .= "- **ANSWER ONLY WHAT WAS ASKED**: Respond to the client's actual question. Do not pre-emptively add extra information, extra links, or extra options they did not request.\n";
        $prompt .= "- **ONE QUESTION AT A TIME**: If you need information from the client, ask for exactly one thing per message. Never send a list of questions.\n";
        $prompt .= "- **DO NOT REPEAT YOURSELF**: If you already shared a price, slot list, link, or confirmation earlier in this conversation, do not send it again unless the client explicitly asks for it again.\n";
        $prompt .= "- **AFTER A COMPLETED ACTION**: Once a booking, reschedule, or cancellation is confirmed, send ONE short confirmation message and stop. Do not follow up with suggestions or reminders.\n\n";

        $prompt .= "=== APPOINTMENT BOOKING / RESCHEDULE / CANCEL INSTRUCTIONS ===\n";
        $prompt .= "You CAN book, reschedule, and cancel CONSULT appointments directly using the provided tools.\n";
        $prompt .= "- **BOOKING WORKFLOW**:\n";
        $prompt .= "  1. Use `check_appointment_slots` to verify availability for the client's requested date.\n";
        $prompt .= "  2. Share available time slots (listing all 15-minute slot options as returned by check_appointment_slots, e.g., 10:00 AM, 10:15 AM, 10:30 AM, 10:45 AM, 11:00 AM, ...) and ask the client to pick one.\n";
        $prompt .= "  3. Once client picks a slot, confirm details: 'I will book a *Consult* appointment on *[date]* at *[time]* at *AI Aesthetics Jaipur*. Please *confirm*.'\n";
        $prompt .= "  4. After client confirms FIRST time, ask for FINAL confirmation: 'Final confirmation: *Consult* appointment on *[date]* at *[time]*. Type *confirm* to proceed.'\n";
        $prompt .= "  5. Only after the SECOND confirmation from the client, call `book_appointment`.\n";
        $prompt .= "- **RESCHEDULE WORKFLOW**:\n";
        $prompt .= "  1. FIRST, call `get_client_appointments` to fetch the client's real upcoming appointments and appointment_id values. NEVER guess an appointment_id!\n";
        $prompt .= "  2. Ask which appointment to reschedule and the new preferred date/time.\n";
        $prompt .= "  3. Use `check_appointment_slots` to verify new slot availability.\n";
        $prompt .= "  4. Confirm new details twice (same double-confirmation as booking).\n";
        $prompt .= "  5. Call `reschedule_appointment` using the exact appointment_id from `get_client_appointments` only after the second confirmation.\n";
        $prompt .= "- **CANCELLATION WORKFLOW**:\n";
        $prompt .= "  1. FIRST, call `get_client_appointments` to fetch the client's real upcoming appointments and appointment_id values. NEVER guess or invent an appointment_id!\n";
        $prompt .= "  2. Confirm with the client which appointment to cancel.\n";
        $prompt .= "  3. Call `cancel_appointment` using the exact appointment_id retrieved from `get_client_appointments`.\n";
        $prompt .= "- **IMPORTANT**: Only KNOWN CLIENTS (registered in CRM, shown in KNOWN CLIENT section above) can book/reschedule/cancel. For unknown or new clients, warmly tell them: 'Please contact our clinic for your first appointment' and share the clinic WhatsApp link.\n";
        $prompt .= "- **NEVER** list, offer, reschedule, or cancel past appointments (appointments whose date/time is in the past). Only UPCOMING future appointments can be rescheduled or cancelled.\n";
        $prompt .= "- **NEVER** fabricate appointment details. Always use the tools to verify real data.\n";
        $prompt .= "- **NEVER** book without double confirmation from the client.\n\n";

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
    | Appointment Tool Definitions & Handlers
    |--------------------------------------------------------------------------
    */

    /**
     * Get OpenAI tool definitions for appointment management.
     */
    protected function getAppointmentTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_appointment_slots',
                    'description' => 'Check available appointment time slots for a given date at AI Aesthetics Jaipur clinic. Use this before booking or rescheduling to verify slot availability.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date' => [
                                'type' => 'string',
                                'description' => 'The date to check availability for, in Y-m-d format (e.g. 2026-07-25)',
                            ],
                        ],
                        'required' => ['date'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'book_appointment',
                    'description' => 'Book a new consult appointment for the client. ONLY call this after the client has confirmed the booking details TWICE.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date' => [
                                'type' => 'string',
                                'description' => 'Appointment date in Y-m-d format (e.g. 2026-07-25)',
                            ],
                            'time' => [
                                'type' => 'string',
                                'description' => 'Appointment start time in H:i format (e.g. 14:00 for 2 PM)',
                            ],
                        ],
                        'required' => ['date', 'time'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_client_appointments',
                    'description' => 'Get the client\'s upcoming (pending or confirmed) appointments. Use this when client wants to reschedule or cancel.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'reschedule_appointment',
                    'description' => 'Reschedule an existing appointment to a new date and time. MUST retrieve exact appointment_id first via get_client_appointments.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'appointment_id' => [
                                'type' => 'integer',
                                'description' => 'The exact ID of the appointment from get_client_appointments (do not guess)',
                            ],
                            'new_date' => [
                                'type' => 'string',
                                'description' => 'New appointment date in Y-m-d format',
                            ],
                            'new_time' => [
                                'type' => 'string',
                                'description' => 'New appointment start time in H:i format (e.g. 15:00)',
                            ],
                        ],
                        'required' => ['appointment_id', 'new_date', 'new_time'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancel_appointment',
                    'description' => 'Cancel an existing appointment. MUST retrieve exact appointment_id first via get_client_appointments.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'appointment_id' => [
                                'type' => 'integer',
                                'description' => 'The exact ID of the appointment from get_client_appointments (do not guess)',
                            ],
                        ],
                        'required' => ['appointment_id'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call from the AI and return the result string.
     */
    protected function executeToolCall(string $toolName, array $arguments, WhatsAppConversation $conversation): string
    {
        try {
            return match ($toolName) {
                'check_appointment_slots' => $this->handleCheckSlots($arguments['date'] ?? ''),
                'book_appointment' => $this->handleBookAppointment(
                    $arguments['date'] ?? '',
                    $arguments['time'] ?? '',
                    $conversation
                ),
                'get_client_appointments' => $this->handleGetClientAppointments($conversation),
                'reschedule_appointment' => $this->handleRescheduleAppointment(
                    (int) ($arguments['appointment_id'] ?? 0),
                    $arguments['new_date'] ?? '',
                    $arguments['new_time'] ?? '',
                    $conversation
                ),
                'cancel_appointment' => $this->handleCancelAppointment(
                    (int) ($arguments['appointment_id'] ?? 0),
                    $conversation
                ),
                default => json_encode(['error' => 'Unknown tool: ' . $toolName]),
            };
        } catch (\Exception $e) {
            Log::error("Tool call error [{$toolName}]: " . $e->getMessage());
            return json_encode(['error' => 'An error occurred while processing your request. Please try again.']);
        }
    }

    /**
     * Helper to convert HH:MM string to total minutes.
     */
    protected function timeToMinutes(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return ($h * 60) + $m;
    }

    /**
     * Handle check_appointment_slots: return available slots using AppointmentController::getSlots API.
     */
    protected function handleCheckSlots(string $date): string
    {
        if (empty($date)) {
            return json_encode(['error' => 'Please provide a valid date.']);
        }

        try {
            $requestedDate = Carbon::parse($date);
        } catch (Exception $e) {
            return json_encode(['error' => 'Invalid date format. Please use YYYY-MM-DD format.']);
        }

        if ($requestedDate->lt(Carbon::today())) {
            return json_encode(['error' => 'Cannot check slots for a past date. Please choose today or a future date.']);
        }

        $clinic = $this->getDefaultClinic();
        if (!$clinic) {
            return json_encode(['error' => 'Clinic not found. Please contact the clinic directly.']);
        }

        if ($requestedDate->isSunday()) {
            return json_encode([
                'date' => $requestedDate->format('d M Y (l)'),
                'clinic' => $clinic->name,
                'available_slots' => [],
                'message' => 'Our clinic is closed on Sundays (Weekly Holiday). Please select a date from Monday to Saturday for your appointment.',
            ]);
        }

        $consultDuration = (int) config('project.appointment_consult_duration', 90);

        // Get clinic working hours in total minutes from midnight
        $clinicStartMin = 10 * 60; // Default 10:00 AM (600 mins)
        $clinicEndMin = 19 * 60;   // Default 07:00 PM (1140 mins)

        if ($clinic->start_time) {
            $clinicStartMin = $this->timeToMinutes($clinic->start_time);
        }
        if ($clinic->end_time) {
            $clinicEndMin = $this->timeToMinutes($clinic->end_time);
        }

        // Get all active therapists at this clinic
        $therapists = User::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'therapist'))
            ->get(['id', 'first_name', 'last_name']);

        if ($therapists->isEmpty()) {
            return json_encode(['error' => 'No therapists available at this clinic.']);
        }

        $controller = app(AppointmentController::class);
        $slotService = app(UnavailableSlotService::class);
        $dateStr = $requestedDate->format('Y-m-d');

        // Determine earliest start time in minutes (if today, start at next 15-minute mark)
        if ($requestedDate->isToday()) {
            $nowMin = ((int) now()->format('G') * 60) + (int) now()->format('i');
            $currentStartMin = max($clinicStartMin, (int) (ceil($nowMin / 15) * 15) + 15);
        } else {
            $currentStartMin = $clinicStartMin;
        }

        // Prefetch unavailable blocks for all therapists at this clinic on requested date using AppointmentController::getSlots API
        $therapistUnavailableBlocks = [];
        foreach ($therapists as $therapist) {
            $req = Request::create('/api/availability/slots', 'GET', [
                'clinic_id' => $clinic->id,
                'therapist_id' => $therapist->id,
                'from_date' => $dateStr,
                'to_date' => $dateStr,
            ]);

            $res = $controller->getSlots($req, $slotService);
            $resData = $res->getData(true);
            $therapistUnavailableBlocks[$therapist->id] = $resData['results'] ?? [];
        }

        $availableSlots = [];

        // Check slots in 15-minute intervals from start_time to (end_time - consultDuration)
        for ($slotStartMin = $currentStartMin; $slotStartMin <= ($clinicEndMin - $consultDuration); $slotStartMin += 15) {
            $slotEndMin = $slotStartMin + $consultDuration;

            $therapistsAvailableCount = 0;

            foreach ($therapists as $therapist) {
                $blocks = $therapistUnavailableBlocks[$therapist->id] ?? [];
                $isBlocked = false;

                foreach ($blocks as $block) {
                    if (($block['start_date'] ?? '') !== $dateStr) {
                        continue;
                    }
                    $bStartMin = $this->timeToMinutes($block['start_time'] ?? '00:00');
                    $bEndMin = $this->timeToMinutes($block['end_time'] ?? '23:59');

                    // Check for overlap: slot starts before block ends AND slot ends after block starts
                    if ($slotStartMin < $bEndMin && $slotEndMin > $bStartMin) {
                        $isBlocked = true;
                        break;
                    }
                }

                if (!$isBlocked) {
                    $therapistsAvailableCount++;
                }
            }

            if ($therapistsAvailableCount > 0) {
                $slotStart = $requestedDate->copy()->setTime(intdiv($slotStartMin, 60), $slotStartMin % 60, 0);
                $availableSlots[] = [
                    'time' => $slotStart->format('h:i A'),
                    'time_24h' => $slotStart->format('H:i'),
                    'therapists_available' => $therapistsAvailableCount,
                ];
            }
        }

        if (empty($availableSlots)) {
            return json_encode([
                'date' => $requestedDate->format('d M Y (l)'),
                'clinic' => $clinic->name,
                'available_slots' => [],
                'message' => 'No slots available on this date. Please try another date.',
            ]);
        }

        return json_encode([
            'date' => $requestedDate->format('d M Y (l)'),
            'clinic' => $clinic->name,
            'consultation_duration' => $consultDuration . ' minutes',
            'available_slots' => $availableSlots,
        ]);
    }

    /**
     * Handle book_appointment: create a new consult appointment using AppointmentController::store API.
     */
    protected function handleBookAppointment(string $date, string $time, WhatsAppConversation $conversation): string
    {
        // Validate client exists
        $client = $this->findClientByPhone($conversation->phone_number);
        if (!$client) {
            return json_encode(['error' => 'You are not registered in our system. Please contact our clinic for your first appointment.']);
        }

        // Validate date & time
        if (empty($date) || empty($time)) {
            return json_encode(['error' => 'Please provide both date and time for the appointment.']);
        }

        try {
            $startDateTime = Carbon::parse("{$date} {$time}");
        } catch (Exception $e) {
            return json_encode(['error' => 'Invalid date or time format.']);
        }

        if ($startDateTime->lt(now())) {
            return json_encode(['error' => 'Cannot book an appointment in the past. Please choose a future date and time.']);
        }

        if ($startDateTime->isSunday()) {
            return json_encode(['error' => 'Our clinic is closed on Sundays (Weekly Holiday). Please select a working day from Monday to Saturday to book your appointment.']);
        }

        $clinic = $this->getDefaultClinic();
        if (!$clinic) {
            return json_encode(['error' => 'Clinic not found.']);
        }

        if ($client->clinic_id !== $clinic->id) {
            return json_encode(['error' => 'Your profile is registered at a different clinic. Please contact the clinic team for assistance.']);
        }

        $consultDuration = (int) config('project.appointment_consult_duration', 90);
        $endDateTime = $startDateTime->copy()->addMinutes($consultDuration);

        // Find available therapists for this slot using AppointmentController::getSlots API
        $therapists = User::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'therapist'))
            ->get();

        $slotService = app(UnavailableSlotService::class);
        $controller = app(AppointmentController::class);
        $availabilityService = app(AvailabilityService::class);
        $dateStr = $startDateTime->format('Y-m-d');
        $slotStartMin = ($startDateTime->hour * 60) + $startDateTime->minute;
        $slotEndMin = $slotStartMin + $consultDuration;

        $availableTherapists = [];

        foreach ($therapists as $therapist) {
            $req = Request::create('/api/availability/slots', 'GET', [
                'clinic_id' => $clinic->id,
                'therapist_id' => $therapist->id,
                'from_date' => $dateStr,
                'to_date' => $dateStr,
            ]);

            $res = $controller->getSlots($req, $slotService);
            $resData = $res->getData(true);
            $unavailableBlocks = $resData['results'] ?? [];

            $isBlocked = false;
            foreach ($unavailableBlocks as $block) {
                if (($block['start_date'] ?? '') !== $dateStr) {
                    continue;
                }
                $bStartMin = $this->timeToMinutes($block['start_time'] ?? '00:00');
                $bEndMin = $this->timeToMinutes($block['end_time'] ?? '23:59');

                if ($slotStartMin < $bEndMin && $slotEndMin > $bStartMin) {
                    $isBlocked = true;
                    break;
                }
            }

            if (!$isBlocked) {
                $availableTherapists[] = $therapist;
            }
        }

        if (empty($availableTherapists)) {
            return json_encode(['error' => 'Sorry, no therapist is available for this time slot. Please try a different time or date.']);
        }

        // Pick a random available therapist
        $selectedTherapist = $availableTherapists[array_rand($availableTherapists)];

        // Set Auth user context to AI Bot user for permission & created_by tracking
        $botUserId = $this->getAiBotUserId();
        $botUser = User::find($botUserId);
        if ($botUser) {
            Auth::setUser($botUser);
        }

        try {
            $requestData = [
                'type' => 'consult',
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'therapist_id' => $selectedTherapist->id,
                'start_datetime' => $startDateTime->format('Y-m-d H:i'),
                'end_datetime' => $endDateTime->format('Y-m-d H:i'),
                'status' => 'pending',
                'notes' => 'Booked via AI WhatsApp Chatbot',
            ];

            $appointmentRequest = AppointmentRequest::create('/api/appointments', 'POST', $requestData);
            $appointmentRequest->setContainer(app());
            $appointmentRequest->setRedirector(app('redirect'));
            app()->instance('request', $appointmentRequest);
            $appointmentRequest->validateResolved();

            // Call AppointmentController::store API method
            $response = $controller->store($appointmentRequest, $availabilityService);
            $resData = $response->getData(true);

            if (isset($resData['error']) && $resData['error'] === true) {
                return json_encode(['error' => $resData['message'] ?? 'Failed to book appointment.']);
            }

            $appointmentData = $resData['results']['appointment'] ?? [];

            return json_encode([
                'success' => true,
                'message' => 'Appointment booked successfully!',
                'appointment_id' => $appointmentData['id'] ?? null,
                'type' => 'Consult',
                'date' => $startDateTime->format('d M Y'),
                'day' => $startDateTime->format('l'),
                'time' => $startDateTime->format('h:i A') . ' - ' . $endDateTime->format('h:i A'),
                'duration' => $consultDuration . ' minutes',
                'therapist' => $selectedTherapist->first_name . ' ' . $selectedTherapist->last_name,
                'clinic' => $clinic->name,
                'status' => 'Pending',
            ]);
        } catch (ValidationException $ve) {
            return json_encode(['error' => implode(', ', array_merge(...array_values($ve->errors())))]);
        } catch (Exception $e) {
            Log::error('AI Appointment booking via API failed: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to book appointment due to a system error. Please try again.']);
        }
    }

    /**
     * Handle get_client_appointments: fetch upcoming appointments.
     */
    protected function handleGetClientAppointments(WhatsAppConversation $conversation): string
    {
        $client = $this->findClientByPhone($conversation->phone_number);
        if (!$client) {
            return json_encode(['error' => 'You are not registered in our system. Please contact our clinic for your first appointment.']);
        }

        $appointments = Appointment::where('user_id', $client->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_datetime', '>', now())
            ->with(['therapist:id,first_name,last_name', 'clinic:id,name'])
            ->orderBy('start_datetime', 'asc')
            ->limit(10)
            ->get();

        if ($appointments->isEmpty()) {
            return json_encode([
                'appointments' => [],
                'message' => 'You have no upcoming appointments.',
            ]);
        }

        $appointmentList = [];
        foreach ($appointments as $appt) {
            $startDt = Carbon::parse($appt->start_datetime);
            $endDt = Carbon::parse($appt->end_datetime);

            $type = $appt->type instanceof BackedEnum ? $appt->type->value : (string) $appt->type;
            $status = $appt->status instanceof BackedEnum ? $appt->status->value : (string) $appt->status;

            $appointmentList[] = [
                'appointment_id' => $appt->id,
                'type' => ucfirst($type),
                'date' => $startDt->format('d M Y'),
                'day' => $startDt->format('l'),
                'time' => $startDt->format('h:i A') . ' - ' . $endDt->format('h:i A'),
                'status' => ucfirst($status),
                'therapist' => $appt->therapist
                    ? $appt->therapist->first_name . ' ' . $appt->therapist->last_name
                    : 'Unassigned',
                'clinic' => $appt->clinic->name ?? 'AI Aesthetics Jaipur',
            ];
        }

        return json_encode(['appointments' => $appointmentList]);
    }

    /**
     * Handle reschedule_appointment: update date/time using AppointmentController::update API.
     */
    protected function handleRescheduleAppointment(
        int $appointmentId,
        string $newDate,
        string $newTime,
        WhatsAppConversation $conversation
    ): string {
        $client = $this->findClientByPhone($conversation->phone_number);
        if (!$client) {
            return json_encode(['error' => 'You are not registered in our system. Please contact our clinic for your first appointment.']);
        }

        if ($appointmentId <= 0) {
            return json_encode(['error' => 'Invalid appointment ID.']);
        }

        $appointment = Appointment::where('id', $appointmentId)
            ->where('user_id', $client->id)
            ->first();

        if (!$appointment) {
            return json_encode(['error' => 'Appointment not found or does not belong to you.']);
        }

        $currentStatus = $appointment->status instanceof BackedEnum
            ? $appointment->status->value
            : (string) $appointment->status;

        if (!in_array($currentStatus, ['pending', 'confirmed'])) {
            return json_encode(['error' => 'Only pending or confirmed appointments can be rescheduled. This appointment is ' . $currentStatus . '.']);
        }

        if (Carbon::parse($appointment->start_datetime)->lte(now())) {
            return json_encode(['error' => 'This appointment is in the past or currently underway, so it cannot be rescheduled.']);
        }

        if (empty($newDate) || empty($newTime)) {
            return json_encode(['error' => 'Please provide both a new date and time.']);
        }

        try {
            $newStartDateTime = Carbon::parse("{$newDate} {$newTime}");
        } catch (Exception $e) {
            return json_encode(['error' => 'Invalid date or time format.']);
        }

        if ($newStartDateTime->lt(now())) {
            return json_encode(['error' => 'Cannot reschedule to a past date/time.']);
        }

        if ($newStartDateTime->isSunday()) {
            return json_encode(['error' => 'Our clinic is closed on Sundays (Weekly Holiday). Please select a working day from Monday to Saturday for your new appointment.']);
        }

        $clinic = $this->getDefaultClinic();
        if (!$clinic) {
            return json_encode(['error' => 'Clinic not found.']);
        }

        $consultDuration = (int) config('project.appointment_consult_duration', 90);
        $newEndDateTime = $newStartDateTime->copy()->addMinutes($consultDuration);

        // Find available therapists for the new slot
        $therapists = User::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'therapist'))
            ->get();

        $slotService = app(UnavailableSlotService::class);
        $controller = app(AppointmentController::class);
        $availabilityService = app(AvailabilityService::class);

        $dateStr = $newStartDateTime->format('Y-m-d');
        $slotStartMin = ($newStartDateTime->hour * 60) + $newStartDateTime->minute;
        $slotEndMin = $slotStartMin + $consultDuration;

        $availableTherapists = [];

        foreach ($therapists as $therapist) {
            $req = Request::create('/api/availability/slots', 'GET', [
                'clinic_id' => $clinic->id,
                'therapist_id' => $therapist->id,
                'from_date' => $dateStr,
                'to_date' => $dateStr,
            ]);

            $res = $controller->getSlots($req, $slotService);
            $resData = $res->getData(true);
            $unavailableBlocks = $resData['results'] ?? [];

            $isBlocked = false;
            foreach ($unavailableBlocks as $block) {
                if (($block['start_date'] ?? '') !== $dateStr) {
                    continue;
                }
                // Ignore current appointment when checking unavailability for rescheduling
                if (($block['type'] ?? '') === 'appointment' && (int) ($block['id'] ?? 0) === $appointment->id) {
                    continue;
                }
                $bStartMin = $this->timeToMinutes($block['start_time'] ?? '00:00');
                $bEndMin = $this->timeToMinutes($block['end_time'] ?? '23:59');

                if ($slotStartMin < $bEndMin && $slotEndMin > $bStartMin) {
                    $isBlocked = true;
                    break;
                }
            }

            if (!$isBlocked) {
                $availableTherapists[] = $therapist;
            }
        }

        if (empty($availableTherapists)) {
            return json_encode(['error' => 'No therapist is available for the new time slot. Please try a different time or date.']);
        }

        // Prefer original therapist if available, otherwise pick random
        $selectedTherapist = null;
        foreach ($availableTherapists as $therapist) {
            if ($therapist->id === $appointment->therapist_id) {
                $selectedTherapist = $therapist;
                break;
            }
        }
        if (!$selectedTherapist) {
            $selectedTherapist = $availableTherapists[array_rand($availableTherapists)];
        }

        // Set Auth user context
        $botUserId = $this->getAiBotUserId();
        $botUser = User::find($botUserId);
        if ($botUser) {
            Auth::setUser($botUser);
        }

        try {
            $requestData = [
                'start_datetime' => $newStartDateTime->format('Y-m-d H:i'),
                'end_datetime' => $newEndDateTime->format('Y-m-d H:i'),
                'therapist_id' => $selectedTherapist->id,
                'clinic_id' => $clinic->id,
                'notes' => trim(($appointment->notes ? $appointment->notes . ' | ' : '') . 'Rescheduled via AI WhatsApp Chatbot'),
            ];

            $updateRequest = AppointmentUpdateRequest::create('/api/appointments/' . $appointment->id, 'PUT', $requestData);
            $updateRequest->setContainer(app());
            $updateRequest->setRedirector(app('redirect'));

            $route = new Route(['PUT', 'PATCH'], 'api/appointments/{appointment}', [
                'uses' => 'App\Http\Controllers\Api\AppointmentController@update',
            ]);
            $route->parameters = ['appointment' => $appointment];
            $updateRequest->setRouteResolver(fn() => $route);

            app()->instance('request', $updateRequest);

            $updateRequest->validateResolved();

            // Call AppointmentController::update API method
            $response = $controller->update($updateRequest, $appointment, $availabilityService);
            $resData = $response->getData(true);

            if (isset($resData['error']) && $resData['error'] === true) {
                return json_encode(['error' => $resData['message'] ?? 'Failed to reschedule appointment.']);
            }

            return json_encode([
                'success' => true,
                'message' => 'Appointment rescheduled successfully!',
                'appointment_id' => $appointment->id,
                'new_date' => $newStartDateTime->format('d M Y'),
                'new_day' => $newStartDateTime->format('l'),
                'new_time' => $newStartDateTime->format('h:i A') . ' - ' . $newEndDateTime->format('h:i A'),
                'therapist' => $selectedTherapist->first_name . ' ' . $selectedTherapist->last_name,
                'clinic' => $clinic->name,
            ]);
        } catch (ValidationException $ve) {
            return json_encode(['error' => implode(', ', array_merge(...array_values($ve->errors())))]);
        } catch (Exception $e) {
            Log::error('AI Appointment reschedule via API failed: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to reschedule appointment. Please try again.']);
        }
    }

    /**
     * Handle cancel_appointment: cancel an existing appointment using AppointmentController::updateStatus API.
     */
    protected function handleCancelAppointment(int $appointmentId, WhatsAppConversation $conversation): string
    {
        $client = $this->findClientByPhone($conversation->phone_number);
        if (!$client) {
            return json_encode(['error' => 'You are not registered in our system. Please contact our clinic for your first appointment.']);
        }

        if ($appointmentId <= 0) {
            return json_encode(['error' => 'Invalid appointment ID.']);
        }

        $appointment = Appointment::where('id', $appointmentId)
            ->where('user_id', $client->id)
            ->first();

        if (!$appointment) {
            return json_encode(['error' => 'Appointment not found or does not belong to you.']);
        }

        $currentStatus = $appointment->status instanceof BackedEnum
            ? $appointment->status->value
            : (string) $appointment->status;

        if (!in_array($currentStatus, ['pending', 'confirmed'])) {
            return json_encode(['error' => 'Only pending or confirmed appointments can be cancelled. This appointment is ' . $currentStatus . '.']);
        }

        if (Carbon::parse($appointment->start_datetime)->lte(now())) {
            return json_encode(['error' => 'This appointment is in the past or currently underway, so it cannot be cancelled.']);
        }

        // Set Auth user context
        $botUserId = $this->getAiBotUserId();
        $botUser = User::find($botUserId);
        if ($botUser) {
            Auth::setUser($botUser);
        }

        try {
            $controller = app(AppointmentController::class);
            $availabilityService = app(AvailabilityService::class);

            $req = Request::create('/api/appointments/status/' . $appointmentId, 'POST', [
                'status' => 'cancelled',
            ]);
            app()->instance('request', $req);

            // Call AppointmentController::updateStatus API method
            $response = $controller->updateStatus($req, $appointmentId, $availabilityService);
            $resData = $response->getData(true);

            if (isset($resData['error']) && $resData['error'] === true) {
                return json_encode(['error' => $resData['message'] ?? 'Failed to cancel appointment.']);
            }

            // Append note about AI cancellation
            $appointment->update([
                'notes' => trim(($appointment->notes ? $appointment->notes . ' | ' : '') . 'Cancelled via AI WhatsApp Chatbot'),
                'updated_by' => $botUserId,
            ]);

            $startDt = Carbon::parse($appointment->start_datetime);

            return json_encode([
                'success' => true,
                'message' => 'Appointment cancelled successfully.',
                'appointment_id' => $appointment->id,
                'cancelled_date' => $startDt->format('d M Y'),
                'cancelled_time' => $startDt->format('h:i A'),
            ]);
        } catch (ValidationException $ve) {
            return json_encode(['error' => implode(', ', array_merge(...array_values($ve->errors())))]);
        } catch (Exception $e) {
            Log::error('AI Appointment cancellation via API failed: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to cancel appointment. Please try again.']);
        }
    }

    /**
     * Get the AI Bot system user ID for created_by/updated_by fields.
     * Creates the bot user if it doesn't exist.
     */
    protected function getAiBotUserId(): ?int
    {
        $botUser = User::firstOrCreate(
            ['email' => 'ai-bot@ai-aesthetics.in'],
            [
                'first_name' => 'AI',
                'last_name' => 'Bot',
                'mobile' => '0000000000',
                'password' => bcrypt(123456),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Assign super_admin role if not already assigned (so created_by tracking works properly)
        if (!$botUser->hasRole('super_admin')) {
            $botUser->assignRole('super_admin');
        }

        return $botUser->id;
    }

    /**
     * Get the default clinic (AI Aesthetics Jaipur).
     */
    protected function getDefaultClinic(): ?Clinic
    {
        return Clinic::where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%AI Aesthetics Jaipur%')
                    ->orWhere('name', 'like', '%AI Aesthetics%');
            })
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Reply Gating
    |--------------------------------------------------------------------------
    */

    /**
     * Bare acknowledgement words that carry no question or request on their own.
     */
    protected const ACKNOWLEDGEMENT_WORDS = [
        'ok', 'okay', 'okk', 'k', 'kk', 'oky', 'okie',
        'thanks', 'thank', 'thankyou', 'thx', 'tq', 'ty',
        'welcome', 'sure', 'fine', 'good', 'great', 'nice', 'cool', 'perfect',
        'yes', 'yeah', 'yep', 'ya', 'yup', 'yaa', 'haan', 'han', 'ha', 'hn',
        'no', 'nope', 'na', 'nahi',
        'done', 'noted', 'got', 'it', 'alright', 'right', 'super', 'awesome',
        'dhanyavad', 'shukriya', 'thik', 'theek', 'thike', 'accha', 'acha',
        'bye', 'byee', 'gm', 'gn', 'welcom',
        // Filler that only ever pads an acknowledgement, e.g. "thank you so much".
        'you', 'u', 'so', 'much', 'very', 'hai', 'ji', 'sir', 'madam', 'maam',
    ];

    /**
     * Decide whether an incoming message deserves an AI reply.
     *
     * Clients often close a conversation with a thumbs-up, a 🙏, or a bare "ok".
     * Answering those restarts the chat and confuses them, so we stay silent
     * unless the message contains actual words beyond an acknowledgement.
     */
    public function shouldReplyTo(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        // Strip emoji, pictographs, symbols, variation selectors and skin-tone modifiers.
        $stripped = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2190}-\x{2BFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F3FB}-\x{1F3FF}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u',
            '',
            $text
        ) ?? $text;

        // Drop anything that is not a letter or digit (punctuation, whitespace, "+1", "...").
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($stripped), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Emoji-only, punctuation-only or empty message — nothing was asked.
        if ($words === []) {
            return false;
        }

        // "+1" / "1" used as a thumbs-up. Other numbers ("2pm", "5") may answer
        // a slot question, so only this exact idiom is treated as silent.
        if ($words === ['1'] && !preg_match('/\p{L}/u', $stripped)) {
            return false;
        }

        // Short message made up entirely of acknowledgement words,
        // e.g. "ok", "thank you 🙏", "thank you so much".
        if (count($words) <= 5) {
            foreach ($words as $word) {
                if (!in_array($word, self::ACKNOWLEDGEMENT_WORDS, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
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

        // Relative days & Day names
        if (preg_match('/\bday after tomorrow\b/i', $cleanText)) {
            $dates[] = Carbon::today()->addDays(2);
        }

        $daysRegex = '(sunday|monday|tuesday|wednesday|thursday|friday|saturday|sun|mon|tue|wed|thu|fri|sat)';
        if (preg_match_all('/\b((this|next|coming)\s+)?' . $daysRegex . '\b/i', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $parsed = Carbon::parse($match);
                    if ($parsed->lt(Carbon::today())) {
                        $parsed->addWeek();
                    }
                    $dates[] = $parsed;
                } catch (Exception $e) {
                }
            }
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
