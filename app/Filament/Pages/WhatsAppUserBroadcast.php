<?php

namespace App\Filament\Pages;

use App\Enums\WhatsAppCampaignStatus;
use App\Models\Clinic;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppCampaignService;
use App\Services\WhatsAppService;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class WhatsAppUserBroadcast extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'User Broadcast';

    protected static ?string $title = 'User Broadcast';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.whatsapp-user-broadcast';

    /*
    |--------------------------------------------------------------------------
    | Form State
    |--------------------------------------------------------------------------
    */

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /*
    |--------------------------------------------------------------------------
    | Form Definition
    |--------------------------------------------------------------------------
    */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Audience')
                    ->icon('heroicon-o-users')
                    ->description('Select a clinic to broadcast to all its active clients.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('clinic_id')
                                ->label('Clinic')
                                ->options(fn () => Clinic::where('is_active', true)->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('recipient_count', null)),

                            Placeholder::make('recipient_count')
                                ->label('Recipients')
                                ->content(function (callable $get): HtmlString {
                                    $clinicId = $get('clinic_id');

                                    if (!$clinicId) {
                                        return new HtmlString('<span class="text-gray-400">Select a clinic first</span>');
                                    }

                                    $count = User::role('client')
                                        ->where('is_active', true)
                                        ->whereNotNull('mobile')
                                        ->where('clinic_id', $clinicId)
                                        ->count();

                                    if ($count === 0) {
                                        return new HtmlString('<span class="text-danger-500 font-semibold">No active clients found</span>');
                                    }

                                    return new HtmlString(
                                        '<span class="text-success-600 font-semibold text-lg">' . $count . '</span>'
                                        . '<span class="text-gray-500 ml-1">active clients with mobile numbers</span>'
                                    );
                                }),
                        ]),
                    ]),

                Section::make('Template')
                    ->icon('heroicon-o-document-text')
                    ->description('Select an approved WhatsApp template to send.')
                    ->schema([
                        Select::make('template_id')
                            ->label('Template')
                            ->options(fn () => WhatsAppTemplate::where('status', 'APPROVED')
                                ->get()
                                ->mapWithKeys(fn (WhatsAppTemplate $t) => [
                                    $t->id => $t->name . ' (' . strtoupper($t->header_type ?? 'none') . ' | ' . $t->language . ')',
                                ])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('header_image', null);

                                if ($state) {
                                    $template = WhatsAppTemplate::find($state);

                                    if ($template) {
                                        $placeholders = array_filter(
                                            $template->getVariablePlaceholders(),
                                            fn ($val) => trim((string) $val) !== ''
                                        );

                                        $variables = [];
                                        foreach ($placeholders as $placeholder) {
                                            $variables[$placeholder] = '';
                                        }

                                        $set('template_variables', $variables);
                                    }
                                } else {
                                    $set('template_variables', []);
                                }
                            }),

                        Placeholder::make('template_preview')
                            ->label('Template Preview')
                            ->visible(fn (callable $get) => !empty($get('template_id')))
                            ->content(function (callable $get): HtmlString {
                                $template = WhatsAppTemplate::find($get('template_id'));

                                if (!$template) {
                                    return new HtmlString('<span class="text-gray-400">Template not found</span>');
                                }

                                $html = '<div class="space-y-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">';

                                // Header info
                                if ($template->header_type && $template->header_type !== 'none') {
                                    $headerIcon = match (strtolower($template->header_type)) {
                                        'image' => '🖼️',
                                        'video' => '🎬',
                                        'document' => '📄',
                                        default => '📝',
                                    };
                                    $html .= '<div class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide">'
                                        . $headerIcon . ' Header: ' . strtoupper($template->header_type) . '</div>';

                                    if ($template->header_type === 'text' && $template->header_content) {
                                        $html .= '<div class="font-semibold text-gray-800 dark:text-gray-200">' . e($template->header_content) . '</div>';
                                    }
                                }

                                // Body
                                if ($template->body_text) {
                                    $html .= '<div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">' . e($template->body_text) . '</div>';
                                }

                                // Footer
                                if ($template->footer_text) {
                                    $html .= '<div class="text-xs text-gray-400 italic">' . e($template->footer_text) . '</div>';
                                }

                                // Buttons
                                if (!empty($template->buttons)) {
                                    $html .= '<div class="flex flex-wrap gap-2 pt-2 border-t border-gray-200 dark:border-gray-600">';
                                    foreach ($template->buttons as $btn) {
                                        $html .= '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-200">'
                                            . e($btn['text'] ?? 'Button') . '</span>';
                                    }
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                Section::make('Header Image')
                    ->icon('heroicon-o-photo')
                    ->description('Upload an image to send as the template header.')
                    ->visible(function (callable $get): bool {
                        $templateId = $get('template_id');

                        if (!$templateId) {
                            return false;
                        }

                        $template = WhatsAppTemplate::find($templateId);

                        return $template && strtolower($template->header_type ?? '') === 'image';
                    })
                    ->schema([
                        FileUpload::make('header_image')
                            ->label('Select Image')
                            ->image()
                            ->disk('public')
                            ->directory('whatsapp-broadcast')
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->required(function (callable $get): bool {
                                $templateId = $get('template_id');

                                if (!$templateId) {
                                    return false;
                                }

                                $template = WhatsAppTemplate::find($templateId);

                                return $template && strtolower($template->header_type ?? '') === 'image';
                            })
                            ->helperText('Accepted formats: JPEG, PNG. Max size: 5MB.'),
                    ]),

                Section::make('Template Variables')
                    ->icon('heroicon-o-variable')
                    ->description('Map template variables to user fields (first_name, last_name, name, mobile, email, city, state, clinic) or enter static values.')
                    ->visible(function (callable $get): bool {
                        $templateId = $get('template_id');

                        if (!$templateId) {
                            return false;
                        }

                        $template = WhatsAppTemplate::find($templateId);

                        if (!$template) {
                            return false;
                        }

                        $placeholders = array_filter(
                            $template->getVariablePlaceholders(),
                            fn ($val) => trim((string) $val) !== ''
                        );

                        return count($placeholders) > 0;
                    })
                    ->schema([
                        KeyValue::make('template_variables')
                            ->keyLabel('Variable')
                            ->valueLabel('User Field / Static Value')
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false),
                    ]),

                Section::make('Campaign Name')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Textarea::make('campaign_description')
                            ->label('Description (Optional)')
                            ->placeholder('Brief note about this broadcast')
                            ->rows(2),
                    ]),
            ])
            ->statePath('data');
    }

    /*
    |--------------------------------------------------------------------------
    | Send Broadcast Action
    |--------------------------------------------------------------------------
    */

    public function sendBroadcast(): void
    {
        $this->validate();

        $data = $this->form->getState();

        $clinicId = $data['clinic_id'];
        $templateId = $data['template_id'];
        $templateVariables = $data['template_variables'] ?? [];
        $description = $data['campaign_description'] ?? null;

        $clinic = Clinic::find($clinicId);
        $template = WhatsAppTemplate::find($templateId);

        if (!$clinic || !$template) {
            Notification::make()
                ->title('Error')
                ->body('Clinic or template not found.')
                ->danger()
                ->send();

            return;
        }

        // Check recipient count
        $recipientCount = User::role('client')
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->where('clinic_id', $clinicId)
            ->count();

        if ($recipientCount === 0) {
            Notification::make()
                ->title('No Recipients')
                ->body('No active clients found in this clinic with valid mobile numbers.')
                ->warning()
                ->send();

            return;
        }

        // Handle header image upload to Meta
        $headerVariables = [];

        if (strtolower($template->header_type ?? '') === 'image' && !empty($data['header_image'])) {
            $imagePath = $data['header_image'];
            $fullPath = Storage::disk('public')->path($imagePath);

            $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';

            $whatsAppService = app(WhatsAppService::class);
            $mediaId = $whatsAppService->uploadMedia($fullPath, $mimeType);

            if (!$mediaId) {
                Notification::make()
                    ->title('Image Upload Failed')
                    ->body('Failed to upload image to WhatsApp. Please try again.')
                    ->danger()
                    ->send();

                return;
            }

            $headerVariables = [
                'media_id' => $mediaId,
            ];

            Log::info("WhatsApp Broadcast: Image uploaded to Meta, media_id: {$mediaId}");
        }

        // Create campaign record
        $campaignName = 'Broadcast - ' . $clinic->name . ' - ' . now()->format('d M Y, h:i A');

        $campaign = WhatsAppCampaign::create([
            'name' => $campaignName,
            'description' => $description,
            'template_id' => $templateId,
            'template_variables' => $templateVariables,
            'audience_type' => 'filter',
            'audience_filter' => [
                'clinic_id' => $clinicId,
                '__header_variables' => $headerVariables,
            ],
            'status' => WhatsAppCampaignStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        // Populate recipients and execute
        $campaignService = app(WhatsAppCampaignService::class);
        $campaignService->populateRecipients($campaign);

        $result = $campaignService->executeCampaign($campaign);

        if ($result) {
            Notification::make()
                ->title('Broadcast Started 🚀')
                ->body("Sending {$template->name} template to {$recipientCount} clients of {$clinic->name}.")
                ->success()
                ->send();

            // Reset form
            $this->form->fill();

            // Redirect to campaign view
            $this->redirect(
                WhatsAppCampaignResource::getUrl('view', ['record' => $campaign->id])
            );
        } else {
            Notification::make()
                ->title('Failed to Start')
                ->body('Campaign could not be started. Please check logs.')
                ->danger()
                ->send();
        }
    }
}
