<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload rules for a lead file.
 *
 * The rules are exposed as a static array so the Filament wizard validates
 * against exactly the same definition rather than maintaining a parallel copy
 * that will inevitably drift.
 */
class StoreLeadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Create:LeadImport') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::sharedRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            'clinic_id' => ['required', 'integer', Rule::exists('clinics', 'id')],
            'file' => [
                'required',
                'file',
                'max:' . (int) config('leads.upload.max_size_kb', 51200),
                // Extension and MIME are checked separately and deliberately
                // loosely: a Meta export is UTF-16 tab-separated text carrying a
                // .csv extension, which browsers report as text/plain or as a
                // generic binary stream. A strict "mimes:csv" rule rejects every
                // genuine export.
                'extensions:' . implode(',', (array) config('leads.upload.allowed_extensions', ['csv', 'txt', 'tsv'])),
                'mimetypes:' . implode(',', (array) config('leads.upload.allowed_mime_types', ['text/plain'])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sharedMessages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to import.',
            'file.max' => 'The file is larger than the :max KB limit.',
            'file.extensions' => 'The file must be a .csv, .tsv or .txt export.',
            'file.mimetypes' => 'The file does not look like a text export. Facebook lead exports are plain text files.',
            'clinic_id.required' => 'Choose the clinic these leads belong to.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::sharedMessages();
    }
}
