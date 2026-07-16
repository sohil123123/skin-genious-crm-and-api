<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
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
        if ($client->loyalty_points > 0) {
            $parts[] = "Loyalty Points: {$client->loyalty_points}";
        }

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
            ->get(['id', 'name', 'city', 'start_time', 'end_time', 'phone']);

        if ($clinics->isEmpty()) {
            return 'No clinic locations available.';
        }

        $lines = [];
        foreach ($clinics as $clinic) {
            $hours = '';
            if ($clinic->start_time && $clinic->end_time) {
                $hours = " | Hours: {$clinic->start_time} - {$clinic->end_time}";
            }
            $city = $clinic->city ? " ({$clinic->city})" : '';
            $phone = $clinic->phone ? " | Phone: {$clinic->phone}" : '';

            $lines[] = "- {$clinic->name}{$city}{$hours}{$phone}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build availability summary across all active clinics for today, tomorrow, and dynamically requested dates.
     */
    protected function buildAvailabilityContext(array $requestedDates = []): string
    {
        $clinics = Clinic::where('is_active', true)->get();

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
        $prompt = "You are an AI assistant for a dermatology and aesthetics clinic called **Ai Aesthetics**.\n";
        $prompt .= "You help customers with service inquiries, appointment booking, package details, and general clinic questions.\n";
        $prompt .= "Be polite, professional, brief, and helpful. Support the client's language (Hindi, English, etc.).\n\n";

        $prompt .= "=== IMPORTANT RULES ===\n";
        $prompt .= "- ONLY quote prices and services from the data below. Never invent prices.\n";
        $prompt .= "- If the customer asks about something not in the data, say: \"Let me connect you with our team for more details.\"\n";
        $prompt .= "- Do NOT confirm bookings or say 'appointment booked' yourself. You cannot create appointments.\n";
        $prompt .= "- When a customer wants to book an appointment, check the available time slots below for their preferred date and clinic, then share the clinic's phone number and address, and ask them to contact the clinic to confirm and finalize the booking.\n";
        $prompt .= "- If a client has remaining sessions in a package, mention it before suggesting new purchases.\n";
        $prompt .= "- Keep responses under 150 words.\n\n";

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
                } catch (\Exception $e) {}
            }
        }
        
        // Pattern 2: Month DD YYYY or Month DD
        if (preg_match_all('/\b' . $monthsRegex . '\s+\d{1,2}(\s+\d{2,4})?\b/i', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse($match);
                } catch (\Exception $e) {}
            }
        }
        
        // Pattern 3: YYYY-MM-DD or DD-MM-YYYY or DD/MM/YYYY
        if (preg_match_all('/\b\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4}\b/', $cleanText, $matches)) {
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse($match);
                } catch (\Exception $e) {}
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
