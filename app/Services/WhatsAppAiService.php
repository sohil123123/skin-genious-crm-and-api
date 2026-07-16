<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
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
     * Generate response suggestions based on the conversation context.
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

        $messagesPayload = [];
        $messagesPayload[] = [
            'role' => 'system',
            'content' => 'You are an AI assistant for a dermatology and aesthetics clinic called Ai Aesthetics. Generate a polite, brief, and helpful reply to the customer message. Keep answers conversational, helpful, and concise. Support the client\'s language.',
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
                        'max_tokens' => 150,
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
}
