<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use App\Enums\WhatsAppMessageType;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SendTodayAppointmentsWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $date;
    public ?string $templateName;
    public ?int $clinicId;
    public bool $forceDuplicate;
    public ?string $mode;

    /**
     * Create a new job instance.
     *
     * @param string|null $date YYYY-MM-DD date string (defaults to today unless mode is previous_day_evening)
     * @param string|null $templateName Custom WhatsApp template name
     * @param int|null $clinicId Filter by clinic ID
     * @param bool $forceDuplicate Allow resending if message was already sent
     * @param string|null $mode Slot mode: 'previous_day_evening' (for 09:00-11:30 appts) or 'same_day_morning' (for 11:30+ appts)
     */
    public function __construct(
        ?string $date = null,
        ?string $templateName = null,
        ?int $clinicId = null,
        bool $forceDuplicate = false,
        ?string $mode = null
    ) {
        $this->mode = $mode;
        if ($mode === 'previous_day_evening' && $date === null) {
            $this->date = Carbon::tomorrow()->format('Y-m-d');
        } else {
            $this->date = $date ?? Carbon::today()->format('Y-m-d');
        }
        $this->templateName = $templateName;
        $this->clinicId = $clinicId;
        $this->forceDuplicate = $forceDuplicate;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        try {
            $targetDate = Carbon::parse($this->date);

            // Determine WhatsApp template name
            $activeTemplateName = $this->templateName
                ?: Setting::getValue('whatsapp_today_appointment_reminder_template_name');

            $template = WhatsAppTemplate::where('name', $activeTemplateName)->first();

            if (!$template) {
                Log::warning("SendTodayAppointmentsWhatsAppJob: Template '{$activeTemplateName}' not found in database.");
                return;
            }

            // Query active appointments for target date (excluding cancelled)
            $query = Appointment::query()
                ->whereDate('start_datetime', $targetDate)
                ->where('status', '!=', 'cancelled')
                ->with(['client', 'therapist', 'clinic']);

            // Apply time window filter based on schedule mode:
            // 1. previous_day_evening (sent yesterday @ 18:00 for appointments between 09:00 and 11:30)
            // 2. same_day_morning (sent today @ 08:00 for appointments after 11:30)
            if ($this->mode === 'previous_day_evening') {
                $query->whereTime('start_datetime', '>=', '09:00:00')
                      ->whereTime('start_datetime', '<=', '11:30:00');
            } elseif ($this->mode === 'same_day_morning') {
                $query->whereTime('start_datetime', '>', '11:30:00');
            }

            if ($this->clinicId) {
                $query->where('clinic_id', $this->clinicId);
            }

            $appointments = $query->get();

            if ($appointments->isEmpty()) {
                Log::info("SendTodayAppointmentsWhatsAppJob: No active appointments found for date {$this->date} (mode: " . ($this->mode ?? 'all') . ").");
                return;
            }

            $sentCount = 0;
            $skippedCount = 0;

            foreach ($appointments as $appointment) {
                $client = $appointment->client;

                if (!$client || empty($client->mobile)) {
                    $skippedCount++;
                    Log::warning("SendTodayAppointmentsWhatsAppJob: Skipping appointment #{$appointment->id} - No client mobile number.");
                    continue;
                }

                // Check for duplicate send in last 36 hours unless forced
                if (!$this->forceDuplicate) {
                    $alreadySent = WhatsAppMessage::where('user_id', $client->id)
                        ->where('template_name', $template->name)
                        ->where('created_at', '>=', now()->subHours(36))
                        ->exists();

                    if ($alreadySent) {
                        $skippedCount++;
                        Log::info("SendTodayAppointmentsWhatsAppJob: Skipping client #{$client->id} ({$client->name}) - Reminder already sent recently.");
                        continue;
                    }
                }

                $clientName = $client->name ?? 'Client';
                $appointmentTime = $appointment->start_datetime
                    ? $appointment->start_datetime->format('jS F Y \a\t g:i A')
                    : 'Scheduled Time';
                $timeOnly = $appointment->start_datetime
                    ? $appointment->start_datetime->format('g:i A')
                    : 'Scheduled Time';
                $appointmentDate = $appointment->start_datetime
                    ? $appointment->start_datetime->format('jS F Y')
                    : $targetDate->format('jS F Y');
                $clinicName = $appointment->clinic?->name ?? 'Skin Genious Clinic';
                $therapistName = $appointment->therapist?->name ?? 'Therapist';

                // Extract dynamic URL button parameter from clinic's google_map_link
                $googleMapLink = $appointment->clinic?->google_map_link ?? '';
                $buttonUrlParam = $googleMapLink;
                if ($googleMapLink && str_starts_with($googleMapLink, 'https://maps.app.goo.gl/')) {
                    $suffix = ltrim(substr($googleMapLink, strlen('https://maps.app.goo.gl/')), '/');
                    if (!empty($suffix)) {
                        $buttonUrlParam = $suffix;
                    }
                }

                // Build components dynamically for template variables (supports both named and positional template placeholders)
                $components = $template->buildComponentsForSending(
                    // Body variables
                    [
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointmentTime,
                        'appointment_date' => $appointmentDate,
                        'appointment_time' => $timeOnly,
                        'clinic_name' => $clinicName,
                        'therapist_name' => $therapistName,
                        '1' => $clientName,
                        '2' => $appointmentTime,
                        '3' => $clinicName,
                        '4' => $therapistName,
                        0 => $clientName,
                        1 => $appointmentTime,
                        2 => $clinicName,
                        3 => $therapistName,
                    ],
                    // Header variables
                    [
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointmentTime,
                        '1' => $clientName,
                        '2' => $appointmentTime,
                        0 => $clientName,
                        1 => $appointmentTime,
                    ],
                    // Button URL variables (maps to clinic's google_map_link)
                    !empty($buttonUrlParam) ? [$buttonUrlParam] : []
                );

                // Check for existing conversation to link
                $conversationId = null;
                if (method_exists($client, 'whatsappConversations')) {
                    $conversation = $client->whatsappConversations()->latest()->first();
                    if ($conversation) {
                        $conversationId = $conversation->id;
                    }
                }

                // Send template message via WhatsAppService
                $messageLog = $whatsAppService->sendTemplateMessage(
                    $client->mobile,
                    $template->name,
                    $template->language ?? 'en_US',
                    $components,
                    $client->id,
                    null,
                    $conversationId
                );

                if ($messageLog && $messageLog->status === WhatsAppMessageStatus::Sent) {
                    $sentCount++;
                } else {
                    Log::warning("SendTodayAppointmentsWhatsAppJob: Failed sending template to client #{$client->id} ({$client->mobile}).");
                }
            }

            Log::info("SendTodayAppointmentsWhatsAppJob completed for date {$this->date}: {$sentCount} sent, {$skippedCount} skipped.");

        } catch (Exception $e) {
            Log::error("SendTodayAppointmentsWhatsAppJob error: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
