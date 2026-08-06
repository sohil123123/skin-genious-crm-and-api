<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AssessmentStatus;
use App\Models\AiActionLog;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserPackageUsage;
use App\Models\TreatmentSession;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiActionService
{
    /** Default number of days to look back for recent events */
    protected int $lookbackDays = 60;

    /** Maximum contact attempts in a 7-day window before fatigue penalty */
    protected int $maxContactAttempts = 3;

    /** Default package session overdue threshold in days */
    protected int $packageOverdueDays = 21;

    /** Maintenance due window: completed treatment X days ago */
    protected int $maintenanceDueMinDays = 28;
    protected int $maintenanceDueMaxDays = 56;

    /**
     * Generate all AI actions for today across specified clinics.
     * If no clinicIds provided, generates for all active clinics.
     */
    public function generateForToday(?array $clinicIds = null): int
    {
        $clinics = Clinic::where('is_active', true)
            ->when($clinicIds, fn ($q) => $q->whereIn('id', $clinicIds))
            ->get();

        $totalActions = 0;

        foreach ($clinics as $clinic) {
            $totalActions += $this->generateForClinic($clinic);
        }

        return $totalActions;
    }

    /**
     * Generate AI actions for a single clinic.
     */
    public function generateForClinic(Clinic $clinic): int
    {
        // Deactivate previous day's remaining active actions for this clinic
        AiActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', '<', Carbon::today())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Also deactivate today's existing actions (regeneration scenario)
        AiActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', Carbon::today())
            ->whereNull('staff_outcome')
            ->delete();

        $actions = collect();

        // ── A. Rescue Actions ──
        $actions = $actions->merge($this->findCancelledNotRebooked($clinic));
        $actions = $actions->merge($this->findNoShowNotRebooked($clinic));
        $actions = $actions->merge($this->findSameDaySlotFill($clinic));

        // ── B. Conversion Actions ──
        $actions = $actions->merge($this->findScanNoTreatment($clinic));
        $actions = $actions->merge($this->findConsultationNoTreatment($clinic));

        // ── C. Retention Actions ──
        $actions = $actions->merge($this->findPackageOverdue($clinic));
        $actions = $actions->merge($this->findTreatmentPlanDropoff($clinic));
        $actions = $actions->merge($this->findPackageNearingExhaustion($clinic));

        // ── D. Capacity Actions ──
        $actions = $actions->merge($this->findTomorrowsRiskList($clinic));
        $actions = $actions->merge($this->findMaintenanceDue($clinic));

        // Deduplicate by user_id — keep highest priority action per patient
        $deduplicated = $actions->groupBy('user_id')->map(function (Collection $group) {
            return $group->sortByDesc('priority_score')->first();
        })->values();

        // Bulk insert
        foreach ($deduplicated as $action) {
            AiActionLog::create($action);
        }

        return $deduplicated->count();
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 1: Cancelled appointments not rebooked
    // ──────────────────────────────────────────────────────────────

    protected function findCancelledNotRebooked(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $cancelledAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::Cancelled)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($cancelledAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Check if client has a future appointment already
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $daysSinceCancellation = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            // Best recall window: 3-14 days after cancellation
            if ($daysSinceCancellation < 3 || $daysSinceCancellation > 30) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.7,
                'recency' => max(0, 1 - ($daysSinceCancellation / 30)),
                'treatment_fit' => 0.8,
                'urgency' => $daysSinceCancellation <= 7 ? 0.9 : 0.6,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $typeLabel = $appointment->type instanceof AppointmentType
                ? $appointment->type->getLabel()
                : ucfirst((string) $appointment->type);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RESCUE,
                'action_trigger' => 'cancelled_not_rebooked',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '12:30 - 2:00 PM',
                'reason' => "Cancelled {$typeLabel} appointment {$daysSinceCancellation} days ago on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    ". No rebook found. Ideal recall window.",
                'suggested_message' => "Hi {$client->first_name}, we noticed your {$typeLabel} appointment was cancelled. We have some great slots available this week — would you like to reschedule?",
                'goal' => "Reschedule {$typeLabel} appointment",
                'slots_to_offer' => null,
                'avoid_notes' => 'Do not offer discount unless price was the original objection.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 2: No-show not rebooked
    // ──────────────────────────────────────────────────────────────

    protected function findNoShowNotRebooked(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $noShowAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::NoShow)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($noShowAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $daysSinceNoShow = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceNoShow < 2 || $daysSinceNoShow > 21) {
                continue;
            }

            // Check repeated no-shows — reduce priority
            $previousNoShows = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::NoShow)
                ->count();

            $intentPenalty = min(0.3, $previousNoShows * 0.1);

            $priority = $this->calculatePriority([
                'intent' => max(0.3, 0.6 - $intentPenalty),
                'recency' => max(0, 1 - ($daysSinceNoShow / 21)),
                'treatment_fit' => 0.7,
                'urgency' => $daysSinceNoShow <= 5 ? 0.8 : 0.5,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RESCUE,
                'action_trigger' => 'no_show_not_rebooked',
                'priority_score' => $priority,
                'recommended_channel' => $previousNoShows >= 2 ? 'whatsapp' : 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "No-show for appointment on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    " ({$daysSinceNoShow} days ago). " .
                    ($previousNoShows > 1 ? "Has {$previousNoShows} previous no-shows — use gentle tone." : "First no-show — likely recoverable."),
                'suggested_message' => "Hi {$client->first_name}, we missed you at your last appointment. No worries — would you like to reschedule at a time that works better for you?",
                'goal' => 'Rebook missed appointment',
                'slots_to_offer' => null,
                'avoid_notes' => $previousNoShows >= 2
                    ? 'Consider requiring confirmation or deposit for next booking.'
                    : 'Use non-judgmental tone. Do not reference the no-show directly.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 3: Same-day slot fill
    // ──────────────────────────────────────────────────────────────

    protected function findSameDaySlotFill(Clinic $clinic): Collection
    {
        // Count today's total appointment capacity vs booked
        $todayBooked = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', Carbon::today())
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->count();

        $totalSlots = $this->estimateDailySlots($clinic);
        $emptySlots = max(0, $totalSlots - $todayBooked);

        if ($emptySlots < 2) {
            return collect();
        }

        // Find patients who are overdue and could fill today
        $candidates = User::whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereHas('appointments', function ($q) {
                $q->where('status', AppointmentStatus::Completed)
                    ->where('start_datetime', '>=', Carbon::today()->subDays(60));
            })
            ->whereDoesntHave('appointments', function ($q) {
                $q->where('start_datetime', '>=', Carbon::today())
                    ->whereNotIn('status', [
                        AppointmentStatus::Cancelled,
                        AppointmentStatus::NoShow,
                    ]);
            })
            ->limit(5)
            ->get();

        $actions = collect();

        foreach ($candidates as $client) {
            $lastVisit = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Completed)
                ->orderByDesc('start_datetime')
                ->first();

            if (!$lastVisit) {
                continue;
            }

            $daysSinceVisit = (int) round(Carbon::parse($lastVisit->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceVisit < 14) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.5,
                'recency' => max(0, 1 - ($daysSinceVisit / 60)),
                'treatment_fit' => 0.6,
                'urgency' => 0.9, // Same-day is always urgent
                'slot_availability' => 1.0,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CAPACITY,
                'action_trigger' => 'same_day_slot_fill',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => 'ASAP — before 11:00 AM',
                'reason' => "{$emptySlots} empty slots today. Last visit was {$daysSinceVisit} days ago. Patient is due for follow-up.",
                'suggested_message' => "Hi {$client->first_name}! We have a slot available today — would you like to come in for your next session?",
                'goal' => 'Fill empty slot today',
                'slots_to_offer' => null,
                'avoid_notes' => 'Only contact if patient has responded well to short-notice requests before.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->setHour(17),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 4: Scan completed, no treatment
    // ──────────────────────────────────────────────────────────────

    protected function findScanNoTreatment(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $assessments = Assessment::where('clinic_id', $clinic->id)
            ->where('status', AssessmentStatus::Completed)
            ->where('created_at', '>=', $cutoffDate)
            ->with(['user', 'user.roles', 'treatmentSessions'])
            ->get();

        $actions = collect();

        foreach ($assessments as $assessment) {
            $client = $assessment->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Check if treatment sessions were completed
            $hasCompletedTreatment = $assessment->treatmentSessions()
                ->where('status', 'completed')
                ->exists();

            if ($hasCompletedTreatment) {
                continue;
            }

            // Check if there's a treatment invoice after the scan
            $hasInvoiceAfterScan = $client->invoices()
                ->where('invoice_date', '>=', $assessment->created_at)
                ->where('invoice_type', '!=', 'package')
                ->exists();

            if ($hasInvoiceAfterScan) {
                continue;
            }

            // Check no future appointment exists
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $daysSinceScan = (int) round(Carbon::parse($assessment->created_at)->diffInDays(Carbon::today()));

            if ($daysSinceScan < 3) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.85, // High — they came in and did a scan
                'recency' => max(0, 1 - ($daysSinceScan / $this->lookbackDays)),
                'treatment_fit' => 0.9,
                'urgency' => $daysSinceScan <= 14 ? 0.9 : 0.6,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CONVERSION,
                'action_trigger' => 'scan_no_treatment',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Skin scan completed {$daysSinceScan} days ago on " .
                    Carbon::parse($assessment->created_at)->format('d M Y') .
                    ". No treatment session or invoice found. Patient invested time — high conversion potential.",
                'suggested_message' => "Hi {$client->first_name}, your AI skin analysis from " .
                    Carbon::parse($assessment->created_at)->format('d M') .
                    " identified some key areas we can work on. Would you like to book your customised facial this week?",
                'goal' => 'Convert scan to treatment appointment',
                'slots_to_offer' => null,
                'avoid_notes' => 'Remind of scan findings. Do not discount unless price was discussed.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => $assessment->id,
                'expires_at' => Carbon::today()->addDays(2)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 5: Consultation completed, no treatment
    // ──────────────────────────────────────────────────────────────

    protected function findConsultationNoTreatment(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $consultAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('type', AppointmentType::Consult)
            ->where('status', AppointmentStatus::Completed)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($consultAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Check if a treatment appointment followed the consult
            $hasTreatmentAfterConsult = Appointment::where('user_id', $client->id)
                ->where('type', AppointmentType::Treatment)
                ->where('start_datetime', '>=', $appointment->start_datetime)
                ->whereIn('status', [
                    AppointmentStatus::Completed,
                    AppointmentStatus::InProgress,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::Pending,
                ])
                ->exists();

            if ($hasTreatmentAfterConsult) {
                continue;
            }

            // No future appointment of any kind
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $daysSinceConsult = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceConsult < 3) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.75,
                'recency' => max(0, 1 - ($daysSinceConsult / $this->lookbackDays)),
                'treatment_fit' => 0.85,
                'urgency' => $daysSinceConsult <= 10 ? 0.85 : 0.55,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CONVERSION,
                'action_trigger' => 'consultation_no_treatment',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "Consult completed {$daysSinceConsult} days ago on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    ". No treatment appointment followed. Patient may need a nudge.",
                'suggested_message' => "Hi {$client->first_name}, after your consultation, our doctor recommended a personalised treatment plan. Would you like to schedule your first session?",
                'goal' => 'Start treatment plan after consultation',
                'slots_to_offer' => null,
                'avoid_notes' => 'Address actual barrier — ask if they have questions about the plan.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(2)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 6: Package patient overdue
    // ──────────────────────────────────────────────────────────────

    protected function findPackageOverdue(Clinic $clinic): Collection
    {
        $activePackages = UserPackage::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->with(['user', 'user.roles', 'items', 'usages'])
            ->get();

        $actions = collect();

        foreach ($activePackages as $package) {
            $client = $package->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $remainingSessions = $package->getTotalRemainingSessions();

            if ($remainingSessions <= 0) {
                continue;
            }

            // Find last usage date
            $lastUsage = $package->usages()->latest()->first();
            $lastUsageDate = $lastUsage ? Carbon::parse($lastUsage->created_at) : Carbon::parse($package->created_at);
            $daysSinceLastUsage = (int) round($lastUsageDate->diffInDays(Carbon::today()));

            if ($daysSinceLastUsage < $this->packageOverdueDays) {
                continue;
            }

            // No future appointment
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.8, // They bought a package — strong intent
                'recency' => max(0, 1 - ($daysSinceLastUsage / 90)),
                'treatment_fit' => 1.0,
                'urgency' => $daysSinceLastUsage >= 42 ? 0.95 : 0.7,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'package_overdue',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Package '{$package->package_name}' has {$remainingSessions} sessions remaining. " .
                    "Last session was {$daysSinceLastUsage} days ago. Overdue for next session.",
                'suggested_message' => "Hi {$client->first_name}, you have {$remainingSessions} sessions remaining in your {$package->package_name} package. It's been a while since your last visit — shall we book your next session?",
                'goal' => 'Book next package session',
                'slots_to_offer' => null,
                'avoid_notes' => 'Emphasise continuity of care, not urgency. Do not pressure.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => $package->id,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(3)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 7: Treatment plan drop-off
    // ──────────────────────────────────────────────────────────────

    protected function findTreatmentPlanDropoff(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays(90);

        // Assessments with treatment sessions where only session 1 completed
        $assessments = Assessment::where('clinic_id', $clinic->id)
            ->where('status', AssessmentStatus::Completed)
            ->where('created_at', '>=', $cutoffDate)
            ->has('treatmentSessions', '>=', 2)
            ->with(['user', 'user.roles', 'treatmentSessions'])
            ->get();

        $actions = collect();

        foreach ($assessments as $assessment) {
            $client = $assessment->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $sessions = TreatmentSession::where('assessment_id', $assessment->id)->get();
            $completedSessions = $sessions->where('status', 'completed')->count();
            $totalSessions = $sessions->count();

            // Only flag if they started (≥1 completed) but didn't finish all
            if ($completedSessions === 0 || $completedSessions >= $totalSessions) {
                continue;
            }

            $lastCompletedSession = $sessions->where('status', 'completed')
                ->sortByDesc('updated_at')
                ->first();

            if (!$lastCompletedSession) {
                continue;
            }

            $daysSinceLastSession = (int) round(Carbon::parse($lastCompletedSession->updated_at)->diffInDays(Carbon::today()));

            if ($daysSinceLastSession < 14) {
                continue;
            }

            // No future appointment
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.75,
                'recency' => max(0, 1 - ($daysSinceLastSession / 90)),
                'treatment_fit' => 0.95,
                'urgency' => $daysSinceLastSession >= 30 ? 0.85 : 0.65,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $remaining = $totalSessions - $completedSessions;

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'treatment_plan_dropoff',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "Completed {$completedSessions}/{$totalSessions} treatment sessions. " .
                    "Last session was {$daysSinceLastSession} days ago. {$remaining} sessions remaining in plan.",
                'suggested_message' => "Hi {$client->first_name}, you've made great progress with {$completedSessions} sessions completed! You have {$remaining} more sessions in your plan. Shall we schedule your next one?",
                'goal' => 'Resume treatment plan',
                'slots_to_offer' => null,
                'avoid_notes' => 'Highlight results achieved so far. Do not create anxiety about drop-off.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => $assessment->id,
                'expires_at' => Carbon::today()->addDays(3)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 8: Package nearing exhaustion
    // ──────────────────────────────────────────────────────────────

    protected function findPackageNearingExhaustion(Clinic $clinic): Collection
    {
        $activePackages = UserPackage::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->with(['user', 'user.roles', 'items'])
            ->get();

        $actions = collect();

        foreach ($activePackages as $package) {
            $client = $package->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $remaining = $package->getTotalRemainingSessions();
            $total = $package->getTotalSessions();

            // Only flag if exactly 1 session remaining and total > 1
            if ($remaining !== 1 || $total <= 1) {
                continue;
            }

            $priority = $this->calculatePriority([
                'intent' => 0.6,
                'recency' => 0.8,
                'treatment_fit' => 0.85,
                'urgency' => 0.7,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'package_nearing_exhaustion',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => 'During or after final session',
                'reason' => "Package '{$package->package_name}' has only 1 session remaining out of {$total}. " .
                    "Staff should discuss renewal or next steps before the final session.",
                'suggested_message' => null,
                'goal' => 'Discuss package renewal or maintenance plan',
                'slots_to_offer' => null,
                'avoid_notes' => 'Discuss during the visit — not as a sales call. Recommend reassessment before renewal if appropriate.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => $package->id,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(7)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 9: Tomorrow's appointment risk list
    // ──────────────────────────────────────────────────────────────

    protected function findTomorrowsRiskList(Clinic $clinic): Collection
    {
        $tomorrow = Carbon::tomorrow();

        $tomorrowAppointments = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', $tomorrow)
            ->whereIn('status', [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ])
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($tomorrowAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Calculate risk signals
            $previousNoShows = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::NoShow)
                ->count();

            $previousCancellations = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Cancelled)
                ->count();

            $bookedDaysAgo = (int) round(Carbon::parse($appointment->created_at)->diffInDays(Carbon::today()));
            $isUnconfirmed = $appointment->status === AppointmentStatus::Pending;

            // Risk score
            $riskScore = 0;
            $riskSignals = [];

            if ($previousNoShows > 0) {
                $riskScore += min(30, $previousNoShows * 15);
                $riskSignals[] = "{$previousNoShows} previous no-show(s)";
            }

            if ($previousCancellations > 1) {
                $riskScore += min(20, $previousCancellations * 5);
                $riskSignals[] = "{$previousCancellations} previous cancellation(s)";
            }

            if ($bookedDaysAgo > 14) {
                $riskScore += 15;
                $riskSignals[] = "Booked {$bookedDaysAgo} days ago";
            }

            if ($isUnconfirmed) {
                $riskScore += 25;
                $riskSignals[] = "Still unconfirmed";
            }

            // Only flag if meaningful risk
            if ($riskScore < 25) {
                continue;
            }

            $priority = min(100, $riskScore);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CAPACITY,
                'action_trigger' => 'tomorrows_risk_list',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => 'This evening or early tomorrow',
                'reason' => "Tomorrow's appointment at " .
                    Carbon::parse($appointment->start_datetime)->format('g:i A') .
                    " — at-risk. Signals: " . implode('; ', $riskSignals) . ".",
                'suggested_message' => "Hi {$client->first_name}, just confirming your appointment tomorrow at " .
                    Carbon::parse($appointment->start_datetime)->format('g:i A') . ". We look forward to seeing you!",
                'goal' => 'Confirm appointment and reduce no-show risk',
                'slots_to_offer' => null,
                'avoid_notes' => 'Personal confirmation call is more effective than automated reminder for at-risk appointments.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => $tomorrow->copy()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 10: Maintenance due
    // ──────────────────────────────────────────────────────────────

    protected function findMaintenanceDue(Clinic $clinic): Collection
    {
        $minDate = Carbon::today()->subDays($this->maintenanceDueMaxDays);
        $maxDate = Carbon::today()->subDays($this->maintenanceDueMinDays);

        // Clients whose last completed appointment was 28-56 days ago
        $clientIds = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::Completed)
            ->select('user_id', DB::raw('MAX(start_datetime) as last_visit'))
            ->groupBy('user_id')
            ->havingRaw('MAX(start_datetime) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->pluck('user_id');

        if ($clientIds->isEmpty()) {
            return collect();
        }

        $clients = User::whereIn('id', $clientIds)
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->where('is_active', true)
            ->get();

        $actions = collect();

        foreach ($clients as $client) {
            // No future appointment
            $hasFutureAppointment = Appointment::where('user_id', $client->id)
                ->where('start_datetime', '>=', Carbon::now())
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])
                ->exists();

            if ($hasFutureAppointment) {
                continue;
            }

            $lastAppointment = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Completed)
                ->orderByDesc('start_datetime')
                ->first();

            if (!$lastAppointment) {
                continue;
            }

            $daysSinceVisit = (int) round(Carbon::parse($lastAppointment->start_datetime)->diffInDays(Carbon::today()));

            $priority = $this->calculatePriority([
                'intent' => 0.5,
                'recency' => max(0, 1 - ($daysSinceVisit / $this->maintenanceDueMaxDays)),
                'treatment_fit' => 0.7,
                'urgency' => 0.5,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]);

            $typeLabel = $lastAppointment->type instanceof AppointmentType
                ? $lastAppointment->type->getLabel()
                : ucfirst((string) $lastAppointment->type);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'maintenance_due',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Last visit ({$typeLabel}) was {$daysSinceVisit} days ago on " .
                    Carbon::parse($lastAppointment->start_datetime)->format('d M Y') .
                    ". Due for maintenance visit.",
                'suggested_message' => "Hi {$client->first_name}, it's been {$daysSinceVisit} days since your last session. To maintain your results, we recommend scheduling a follow-up. Would you like to book?",
                'goal' => 'Schedule maintenance appointment',
                'slots_to_offer' => null,
                'avoid_notes' => 'Position as continuity of care, not sales. Do not push if client completed full course recently.',
                'assigned_to' => null,
                'related_appointment_id' => $lastAppointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(5)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  Priority Scoring Engine
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate a priority score (0-100) from weighted factors.
     *
     * Formula: (intent × recency × treatment_fit × urgency × slot_availability) − fatigue_penalty
     * Then scaled to 0-100.
     */
    protected function calculatePriority(array $factors): int
    {
        $base = ($factors['intent'] ?? 0.5)
            * ($factors['recency'] ?? 0.5)
            * ($factors['treatment_fit'] ?? 0.5)
            * ($factors['urgency'] ?? 0.5)
            * ($factors['slot_availability'] ?? 0.5);

        $penalty = $factors['fatigue_penalty'] ?? 0;

        $score = max(0, ($base * 100) - ($penalty * 30));

        return (int) min(100, round($score));
    }

    /**
     * Calculate contact fatigue penalty (0.0 to 1.0).
     * Based on number of AI action logs created for this user in the last 7 days.
     */
    protected function getContactFatiguePenalty(int $userId): float
    {
        $recentAttempts = AiActionLog::where('user_id', $userId)
            ->where('generated_date', '>=', Carbon::today()->subDays(7))
            ->whereNotNull('staff_outcome')
            ->count();

        if ($recentAttempts >= $this->maxContactAttempts) {
            return 1.0;
        }

        return $recentAttempts / $this->maxContactAttempts;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Check if a user has the 'client' role.
     */
    protected function isClientRole(User $user): bool
    {
        return $user->roles->contains('name', 'client');
    }

    /**
     * Estimate daily appointment slots for a clinic based on operating hours.
     * Each slot = 90 minutes. Returns approximate slot count.
     */
    protected function estimateDailySlots(Clinic $clinic): int
    {
        if (!$clinic->start_time || !$clinic->end_time) {
            return 5; // Default: ~8 hrs / 1.5 hrs = 5 slots
        }

        $start = Carbon::parse($clinic->start_time);
        $end = Carbon::parse($clinic->end_time);
        $minutes = max(90, $start->diffInMinutes($end));

        return (int) floor($minutes / 90);
    }

    /**
     * Get summary statistics for a clinic on a given date.
     */
    public function getSummaryStats(int $clinicId = null, string $date = null): array
    {
        $date = $date ?? Carbon::today()->toDateString();

        $query = AiActionLog::where('generated_date', $date)
            ->where('is_active', true);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $totalActions = (clone $query)->count();
        $highPriority = (clone $query)->where('priority_score', '>=', 70)->count();
        $rescueCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_RESCUE)->count();
        $conversionCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_CONVERSION)->count();
        $retentionCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_RETENTION)->count();
        $capacityCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_CAPACITY)->count();
        $completedCount = AiActionLog::where('generated_date', $date)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereNotNull('staff_outcome')
            ->count();

        // Today's empty slots across clinics or for specific clinic
        $todayBooked = Appointment::whereDate('start_datetime', Carbon::today())
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->count();

        $clinicQuery = Clinic::where('is_active', true);
        if ($clinicId) {
            $clinicQuery->where('id', $clinicId);
        }

        $totalDailySlots = 0;
        foreach ($clinicQuery->get() as $clinic) {
            $totalDailySlots += $this->estimateDailySlots($clinic);
        }

        $emptySlots = max(0, $totalDailySlots - $todayBooked);

        // Estimated potential incremental appointments
        $estimatedPotentialMin = (int) round($highPriority * 0.15);
        $estimatedPotentialMax = (int) round($highPriority * 0.35);

        return [
            'total_actions' => $totalActions,
            'high_priority' => $highPriority,
            'rescue_count' => $rescueCount,
            'conversion_count' => $conversionCount,
            'retention_count' => $retentionCount,
            'capacity_count' => $capacityCount,
            'completed_count' => $completedCount,
            'empty_slots_today' => $emptySlots,
            'estimated_potential_min' => max(1, $estimatedPotentialMin),
            'estimated_potential_max' => max(2, $estimatedPotentialMax),
        ];
    }
}
