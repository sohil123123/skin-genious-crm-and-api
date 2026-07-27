<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppMessageType: string implements HasColor, HasIcon, HasLabel
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Audio = 'audio';
    case Template = 'template';
    case Reaction = 'reaction';
    case Location = 'location';
    case Contact = 'contact';
    case Sticker = 'sticker';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::Template => 'Template',
            self::Reaction => 'Reaction',
            self::Location => 'Location',
            self::Contact => 'Contact',
            self::Sticker => 'Sticker',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Text => 'gray',
            self::Image => 'success',
            self::Video => 'info',
            self::Document => 'warning',
            self::Audio => 'primary',
            self::Template => 'purple',
            self::Reaction => 'yellow',
            self::Location => 'cyan',
            self::Contact => 'blue',
            self::Sticker => 'pink',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Text => 'heroicon-o-chat-bubble-left',
            self::Image => 'heroicon-o-photo',
            self::Video => 'heroicon-o-video-camera',
            self::Document => 'heroicon-o-document',
            self::Audio => 'heroicon-o-microphone',
            self::Template => 'heroicon-o-document-text',
            self::Reaction => 'heroicon-o-face-smile',
            self::Location => 'heroicon-o-map-pin',
            self::Contact => 'heroicon-o-user',
            self::Sticker => 'heroicon-o-face-smile',
        };
    }

    /**
     * Check if this type is a media type.
     */
    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Document, self::Audio, self::Sticker]);
    }
}
