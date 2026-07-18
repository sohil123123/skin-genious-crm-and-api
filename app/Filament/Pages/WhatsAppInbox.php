<?php

namespace App\Filament\Pages;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Models\User;
use App\Services\WhatsAppConversationService;
use App\Services\WhatsAppRetryService;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

class WhatsAppInbox extends Page
{
    use WithFileUploads;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'Chat Inbox';

    protected static ?string $navigationLabel = 'Chat Inbox';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $count = WhatsAppConversation::notArchived()->sum('unread_count');

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected string $view = 'filament.pages.whatsapp-inbox';

    // Livewire properties
    public ?int $activeConversationId = null;
    public $uploadedFile = null;
    public string $messageText = '';
    public string $searchQuery = '';
    public string $filterType = 'all'; // all, unread, starred
    public ?int $replyToMessageId = null;
    public ?int $lastIncomingMessageId = null;

    // Modal properties
    public bool $isTemplateModalOpen = false;
    public ?int $selectedTemplateIdForModal = null;
    public array $templateParameterValues = [];

    protected ?string $pollingInterval = '5s';

    public function getSelectedTemplateForModalProperty(): ?WhatsAppTemplate
    {
        if (!$this->selectedTemplateIdForModal) {
            return null;
        }
        return WhatsAppTemplate::find($this->selectedTemplateIdForModal);
    }

    public function getTemplatePlaceholdersProperty(): array
    {
        $template = $this->selectedTemplateForModal;
        return $template ? $template->getVariablePlaceholders() : [];
    }

    public function mount(): void
    {
        // Select the first conversation by default
        $first = WhatsAppConversation::notArchived()
            ->orderByDesc('last_message_at')
            ->first();

        if ($first) {
            $this->activeConversationId = $first->id;
        }
    }

    public function rendering(): void
    {
        $currentMaxIncoming = WhatsAppMessage::where('direction', WhatsAppMessageDirection::Incoming)->max('id');

        if ($this->lastIncomingMessageId !== null && $currentMaxIncoming > $this->lastIncomingMessageId) {
            $this->dispatch('play-notification-sound');
        }

        $this->lastIncomingMessageId = $currentMaxIncoming;
    }

    /*
    |--------------------------------------------------------------------------
    | Computed Properties
    |--------------------------------------------------------------------------
    */

    public function getConversationsProperty(): Collection
    {
        $query = $this->filterType === 'archived'
            ? WhatsAppConversation::where('is_archived', true)
            : WhatsAppConversation::notArchived();

        $query->with('user:id,first_name,last_name,mobile')
            ->orderByDesc('last_message_at');

        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('contact_name', 'LIKE', '%' . $this->searchQuery . '%')
                    ->orWhere('phone_number', 'LIKE', '%' . $this->searchQuery . '%')
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('first_name', 'LIKE', '%' . $this->searchQuery . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $this->searchQuery . '%')
                            ->orWhere('mobile', 'LIKE', '%' . $this->searchQuery . '%');
                    });
            });
        }

        switch ($this->filterType) {
            case 'unread':
                $query->unread();
                break;
            case 'starred':
                $query->starred();
                break;
        }

        return $query->limit(100)->get();
    }

    public function getActiveConversationProperty(): ?WhatsAppConversation
    {
        if (!$this->activeConversationId) {
            return null;
        }

        $conversation = WhatsAppConversation::with('user')->find($this->activeConversationId);

        if ($conversation && $conversation->unread_count > 0) {
            $conversation->markAsRead();
        }

        return $conversation;
    }

    public function getMessagesProperty(): Collection
    {
        if (!$this->activeConversationId) {
            return collect();
        }

        return WhatsAppMessage::where('conversation_id', $this->activeConversationId)
            ->orderBy('created_at', 'asc')
            ->limit(200)
            ->get();
    }

    public function getTemplatesProperty(): Collection
    {
        return WhatsAppTemplate::where('status', 'APPROVED')
            ->orderBy('name')
            ->get(['id', 'name', 'body_text', 'language']);
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
        $this->replyToMessageId = null;
        $this->messageText = '';

        // Mark as read
        $conversation = WhatsAppConversation::find($id);
        if ($conversation) {
            $conversation->markAsRead();
        }
    }

    public function sendMessage(): void
    {
        $conversation = $this->activeConversation;

        if (!$conversation) {
            Notification::make()->title('Error')->body('No conversation selected.')->danger()->send();
            return;
        }

        $conversationService = app(WhatsAppConversationService::class);
        $whatsAppService = app(WhatsAppService::class);

        // Check if window is open
        if (!$conversation->isWindowOpen()) {
            Notification::make()
                ->title('24h Window Closed')
                ->body('The messaging window has expired. You can only send template messages now.')
                ->warning()
                ->send();
            return;
        }

        // Handle Media Send
        if ($this->uploadedFile) {
            try {
                $originalName = $this->uploadedFile->getClientOriginalName();
                $mimeType = $this->uploadedFile->getMimeType();

                // Determine media type
                $mediaType = 'document';
                if (str_starts_with($mimeType, 'image/')) {
                    $mediaType = 'image';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $mediaType = 'video';
                } elseif (str_starts_with($mimeType, 'audio/')) {
                    $mediaType = 'audio';
                }

                // Store file locally first
                $path = $this->uploadedFile->store('whatsapp-media/outgoing', 'public');
                $filePath = storage_path('app/public/' . $path);

                // Upload to Meta to get media ID
                $mediaId = $whatsAppService->uploadMedia($filePath, $mimeType);

                if (!$mediaId) {
                    Notification::make()
                        ->title('Upload Failed')
                        ->body('Failed to upload media to Meta Cloud API.')
                        ->danger()
                        ->send();
                    return;
                }

                // Send reply
                $caption = trim($this->messageText) !== '' ? trim($this->messageText) : null;
                $contextMessageId = null;
                if ($this->replyToMessageId) {
                    $replyMsg = WhatsAppMessage::find($this->replyToMessageId);
                    $contextMessageId = $replyMsg?->message_id;
                }

                $message = $conversationService->sendMediaReply(
                    $conversation,
                    $mediaType,
                    $mediaId,
                    $caption,
                    $originalName,
                    true, // isMediaId
                    $contextMessageId
                );

                if ($message) {
                    $this->messageText = '';
                    $this->uploadedFile = null;
                    $this->replyToMessageId = null;

                    Notification::make()
                        ->title('Media Sent')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Failed to Send')
                        ->body('Failed to send media message.')
                        ->danger()
                        ->send();
                }
            } catch (\Exception $e) {
                Log::error('WhatsApp Media Send Page Exception: ' . $e->getMessage());
                Notification::make()
                    ->title('Error')
                    ->body('An error occurred: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
            return;
        }

        // Handle Text Send
        if (empty(trim($this->messageText))) {
            return;
        }

        $contextMessageId = null;
        if ($this->replyToMessageId) {
            $replyMsg = WhatsAppMessage::find($this->replyToMessageId);
            $contextMessageId = $replyMsg?->message_id;
        }

        $message = $conversationService->sendReply(
            $conversation,
            trim($this->messageText),
            $contextMessageId
        );

        if ($message) {
            $this->messageText = '';
            $this->replyToMessageId = null;
        } else {
            Notification::make()
                ->title('Failed to Send')
                ->body('The message could not be sent. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function selectTemplateForSending(int $templateId): void
    {
        $conversation = $this->activeConversation;
        if (!$conversation) {
            return;
        }

        $template = WhatsAppTemplate::find($templateId);
        if (!$template) {
            Notification::make()->title('Template not found.')->danger()->send();
            return;
        }

        $placeholders = $template->getVariablePlaceholders();

        if (empty($placeholders)) {
            // No placeholders, send immediately!
            $this->executeSendTemplate($template, []);
            return;
        }

        // Set up modal parameters
        $this->selectedTemplateIdForModal = $templateId;
        $this->templateParameterValues = [];

        // Pre-resolve values
        $user = $conversation->user;
        foreach ($placeholders as $placeholder) {
            $key = 'param_' . $placeholder;
            if ($user) {
                if (is_numeric($placeholder)) {
                    $idx = (int) $placeholder;
                    $this->templateParameterValues[$key] = match ($idx) {
                        1 => $user->first_name ?? $user->name,
                        2 => $user->clinic?->name ?? 'Skin Genious',
                        3 => $user->clinic?->phone ?? '',
                        default => '',
                    };
                } else {
                    $this->templateParameterValues[$key] = match ($placeholder) {
                        'first_name' => $user->first_name ?? '',
                        'last_name' => $user->last_name ?? '',
                        'name', 'full_name' => $user->name ?? '',
                        'clinic' => $user->clinic?->name ?? 'Skin Genious',
                        'mobile', 'phone' => $user->mobile ?? '',
                        default => '',
                    };
                }
            } else {
                $this->templateParameterValues[$key] = $template->getSampleValue($placeholder) ?? '';
            }
        }

        $this->isTemplateModalOpen = true;
        $this->dispatch('open-modal', id: 'template-vars-modal');
    }

    public function sendTemplateWithParameters(): void
    {
        if (!$this->selectedTemplateIdForModal) {
            return;
        }

        $template = WhatsAppTemplate::find($this->selectedTemplateIdForModal);
        if (!$template) {
            return;
        }

        // Reconstruct variables array by stripping param_ prefix
        $variables = [];
        foreach ($this->templateParameterValues as $key => $val) {
            $placeholder = str_replace('param_', '', $key);
            $variables[$placeholder] = $val;
        }

        $this->executeSendTemplate($template, $variables);
        $this->dispatch('close-modal', id: 'template-vars-modal');
        $this->isTemplateModalOpen = false;
        $this->selectedTemplateIdForModal = null;
        $this->templateParameterValues = [];
    }

    protected function executeSendTemplate(WhatsAppTemplate $template, array $variables): void
    {
        $conversation = $this->activeConversation;
        if (!$conversation) {
            return;
        }

        $conversationService = app(WhatsAppConversationService::class);
        $components = $template->buildComponentsForSending($variables);

        $message = $conversationService->sendTemplateMessage(
            $conversation,
            $template->name,
            $components,
            $template->language ?? 'en_US',
            $variables
        );

        if ($message) {
            $statusVal = $message->status?->value ?? $message->status;
            if ($statusVal === 'failed') {
                Notification::make()
                    ->title('Template Sending Failed ⚠️')
                    ->body($message->failed_reason ?? 'Meta API rejected the request.')
                    ->danger()
                    ->send();
            } else {
                Notification::make()
                    ->title('Template Sent')
                    ->body("Template '{$template->name}' sent successfully.")
                    ->success()
                    ->send();
            }
        } else {
            Notification::make()
                ->title('Sending Failed')
                ->body('Template message could not be sent.')
                ->danger()
                ->send();
        }
    }

    public function toggleStar(): void
    {
        $conversation = $this->activeConversation;

        if ($conversation) {
            $conversation->update(['is_starred' => !$conversation->is_starred]);
        }
    }

    public function retryMessage(int $messageId): void
    {
        $message = WhatsAppMessage::find($messageId);
        if ($message) {
            $retryService = app(WhatsAppRetryService::class);
            $retryService->retryMessage($message);

            Notification::make()
                ->title('Resending Message...')
                ->info()
                ->send();
        }
    }

    public function toggleArchiveConversation(): void
    {
        $conversation = $this->activeConversation;

        if ($conversation) {
            $isArchivedNow = !$conversation->is_archived;
            $conversation->update(['is_archived' => $isArchivedNow]);
            $this->activeConversationId = null;

            $msg = $isArchivedNow ? 'Conversation Archived' : 'Conversation Unarchived';
            Notification::make()->title($msg)->success()->send();
        }
    }

    public function setReplyTo(int $messageId): void
    {
        $this->replyToMessageId = $messageId;
    }

    public function clearReplyTo(): void
    {
        $this->replyToMessageId = null;
    }

    public function getReplyingToMessage(): ?WhatsAppMessage
    {
        if (!$this->replyToMessageId) {
            return null;
        }
        return WhatsAppMessage::find($this->replyToMessageId);
    }

    public function downloadMedia(int $messageId): ?string
    {
        $message = WhatsAppMessage::find($messageId);

        if (!$message || !$message->local_media_path) {
            Notification::make()->title('Media not available')->warning()->send();
            return null;
        }

        return asset('storage/' . $message->local_media_path);
    }

    public function setFilter(string $type): void
    {
        $this->filterType = $type;
    }

    /*
    |--------------------------------------------------------------------------
    | Header Actions
    |--------------------------------------------------------------------------
    */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newConversation')
                ->label('New Chat')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->form([
                    Select::make('user_id')
                        ->label('Select Existing User')
                        ->placeholder('Search & select user...')
                        ->searchable()
                        ->live()
                        ->options(function () {
                            return User::with('roles')
                                ->whereDoesntHave('roles', function ($q) {
                                    $q->where('name', 'super_admin');
                                })
                                ->get()
                                ->mapWithKeys(function ($user) {
                                    $roles = $user->roles->pluck('name')->map(fn($r) => str_replace('_', ' ', $r))->implode(', ');
                                    $roleStr = $roles ? " ({$roles})" : '';
                                    $mobileStr = $user->mobile ? " - {$user->mobile}" : '';
                                    return [$user->id => "{$user->name}{$roleStr}{$mobileStr}"];
                                });
                        })
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $user = User::find($state);
                                if ($user) {
                                    $set('phone_number', $user->mobile);
                                    $set('contact_name', $user->name);
                                }
                            }
                        }),
                    TextInput::make('phone_number')
                        ->label('Phone Number')
                        ->required()
                        ->placeholder('e.g. 919876543210')
                        ->tel(),
                    TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->placeholder('Optional'),
                ])
                ->action(function (array $data) {
                    $conversationService = app(WhatsAppConversationService::class);
                    $conversation = $conversationService->getOrCreateConversation(
                        $data['phone_number'],
                        $data['contact_name'] ?? null
                    );

                    $this->activeConversationId = $conversation->id;

                    Notification::make()->title('Conversation created')->success()->send();
                }),
        ];
    }
}
