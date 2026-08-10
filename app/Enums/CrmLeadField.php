<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The set of first-class CRM fields a CSV column can be mapped onto.
 *
 * This enum is the single source of truth for column mapping: the automatic
 * mapper matches headers against aliases(), the mapping UI builds its dropdown
 * from cases(), and row validation builds its rules from rules(). Adding a new
 * mappable field means adding one case here and nothing else.
 *
 * Aliases cover two populations. The first is the exact header set Meta emits
 * for this account (full_name, phone, id, created_time, ad_name, ...), which is
 * what makes a real export map with zero clicks. The second is the looser
 * vocabulary a human uses when assembling a sheet by hand ("Customer Name",
 * "Mobile Number", "WhatsApp"), so ad-hoc files still auto-map.
 */
enum CrmLeadField: string implements HasLabel
{
    case FullName = 'full_name';
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case Phone = 'phone';
    case Email = 'email';
    case City = 'city';
    case State = 'state';
    case Pincode = 'pincode';
    case Status = 'status';
    case Source = 'source';
    case Notes = 'notes';

    case FbLeadId = 'fb_lead_id';
    case FbCreatedTime = 'fb_created_time';
    case CampaignName = 'campaign_name';
    case CampaignId = 'campaign_id';
    case AdsetName = 'adset_name';
    case AdsetId = 'adset_id';
    case AdName = 'ad_name';
    case AdId = 'ad_id';
    case FormName = 'form_name';
    case FormId = 'form_id';
    case PageName = 'page_name';
    case Platform = 'platform';
    case IsOrganic = 'is_organic';
    case FbLeadStatus = 'fb_lead_status';

    public function getLabel(): string
    {
        return match ($this) {
            self::FullName => 'Full Name',
            self::FirstName => 'First Name',
            self::LastName => 'Last Name',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::City => 'City',
            self::State => 'State',
            self::Pincode => 'Pincode',
            self::Status => 'Lead Status',
            self::Source => 'Source',
            self::Notes => 'Notes',
            self::FbLeadId => 'Facebook Lead ID',
            self::FbCreatedTime => 'Submitted At',
            self::CampaignName => 'Campaign',
            self::CampaignId => 'Campaign ID',
            self::AdsetName => 'Ad Set',
            self::AdsetId => 'Ad Set ID',
            self::AdName => 'Ad',
            self::AdId => 'Ad ID',
            self::FormName => 'Form',
            self::FormId => 'Form ID',
            self::PageName => 'Page Name',
            self::Platform => 'Platform',
            self::IsOrganic => 'Organic',
            self::FbLeadStatus => 'Facebook Lead Status',
        };
    }

    /**
     * The group this field is shown under in the mapping dropdown.
     */
    public function group(): string
    {
        return match ($this) {
            self::FullName, self::FirstName, self::LastName, self::Phone, self::Email,
            self::City, self::State, self::Pincode => 'Contact',

            self::Status, self::Source, self::Notes => 'Pipeline',

            default => 'Facebook',
        };
    }

    /**
     * Header spellings that should automatically resolve to this field.
     *
     * Compared after normalisation (lowercased, punctuation stripped,
     * underscores collapsed to spaces), so "Phone Number" and "phone_number"
     * are the same entry.
     */
    public function aliases(): array
    {
        return match ($this) {
            self::FullName => [
                'full name', 'name', 'customer name', 'your name', 'lead name',
                'client name', 'patient name', 'contact name', 'first and last name',
                'naam', 'full name as per id',
            ],
            self::FirstName => ['first name', 'firstname', 'given name', 'fname'],
            self::LastName => ['last name', 'lastname', 'surname', 'family name', 'lname'],
            self::Phone => [
                'phone', 'phone number', 'mobile', 'mobile number', 'contact number',
                'whatsapp', 'whatsapp number', 'whatsapp no', 'contact', 'cell',
                'cell number', 'telephone', 'tel', 'mobile no', 'phone no', 'number',
            ],
            self::Email => [
                'email', 'email address', 'e mail', 'mail', 'email id', 'emailid',
                'your email', 'work email',
            ],
            self::City => ['city', 'location', 'town', 'your city', 'which city', 'city name'],
            self::State => ['state', 'province', 'region'],
            self::Pincode => ['pincode', 'pin code', 'postal code', 'zip', 'zip code', 'postcode'],
            self::Status => ['status', 'lead stage', 'stage', 'pipeline status'],
            self::Source => ['source', 'lead source', 'channel'],
            self::Notes => ['notes', 'note', 'remarks', 'comment', 'comments', 'message'],

            self::FbLeadId => ['id', 'lead id', 'leadid', 'facebook lead id', 'fb lead id', 'meta lead id'],
            self::FbCreatedTime => [
                'created time', 'createdtime', 'created at', 'submitted at',
                'submission time', 'date', 'lead created time', 'timestamp',
            ],
            self::CampaignName => ['campaign name', 'campaign'],
            self::CampaignId => ['campaign id', 'campaignid'],
            self::AdsetName => ['adset name', 'ad set name', 'adset', 'ad set'],
            self::AdsetId => ['adset id', 'ad set id', 'adsetid'],
            self::AdName => ['ad name', 'ad', 'creative name'],
            self::AdId => ['ad id', 'adid'],
            self::FormName => ['form name', 'form', 'lead form', 'lead form name'],
            self::FormId => ['form id', 'formid', 'lead form id'],
            self::PageName => ['page name', 'page', 'facebook page'],
            self::Platform => ['platform', 'publisher platform', 'source platform'],
            self::IsOrganic => ['is organic', 'organic'],
            self::FbLeadStatus => ['lead status', 'leadstatus'],
        };
    }

    /**
     * Validation rules applied to the normalised value of this field.
     *
     * Note that email is nullable: the lead forms in this account do not collect
     * an email address at all, so requiring one would fail every real export.
     */
    public function rules(): array
    {
        return match ($this) {
            self::FullName => ['nullable', 'string', 'max:255'],
            self::FirstName, self::LastName => ['nullable', 'string', 'max:255'],
            self::Phone => ['required', 'string', 'max:20'],
            self::Email => ['nullable', 'email:rfc', 'max:255'],
            self::City, self::State => ['nullable', 'string', 'max:255'],
            self::Pincode => ['nullable', 'string', 'max:20'],
            self::Status => ['nullable', 'string', 'max:30'],
            self::Source => ['nullable', 'string', 'max:30'],
            self::Notes => ['nullable', 'string'],
            self::FbLeadId, self::CampaignId, self::AdsetId, self::AdId, self::FormId => ['nullable', 'string', 'max:64'],
            self::FbCreatedTime => ['nullable', 'date'],
            self::CampaignName, self::AdsetName, self::AdName, self::FormName, self::PageName => ['nullable', 'string', 'max:255'],
            self::Platform => ['nullable', 'string', 'max:20'],
            self::IsOrganic => ['nullable', 'boolean'],
            self::FbLeadStatus => ['nullable', 'string', 'max:30'],
        };
    }

    /**
     * Whether a row is unusable without this field.
     *
     * Only the phone number qualifies. A lead with no way to contact them is
     * worthless, whereas a lead with no name is merely inconvenient.
     */
    public function isRequired(): bool
    {
        return $this === self::Phone;
    }

    /**
     * The normalisation pipeline this field's raw value passes through.
     *
     * Consumed by ValueNormalizerService; keeping it on the enum means a new
     * field declares its own handling instead of growing a match() elsewhere.
     */
    public function normalizer(): string
    {
        return match ($this) {
            self::Phone => 'phone',
            self::Email => 'email',
            self::FullName, self::FirstName, self::LastName => 'name',
            self::FbCreatedTime => 'datetime',
            self::IsOrganic => 'boolean',
            self::FbLeadId, self::CampaignId, self::AdsetId, self::AdId, self::FormId => 'identifier',
            self::Notes => 'text',
            default => 'string',
        };
    }

    /**
     * Fields that may be used as duplicate match keys.
     *
     * @return array<int, self>
     */
    public static function duplicateMatchFields(): array
    {
        return [self::FbLeadId, self::Phone, self::Email];
    }

    /**
     * All required fields, used by the preview screen's readiness check.
     *
     * @return array<int, self>
     */
    public static function required(): array
    {
        return array_values(array_filter(self::cases(), fn (self $field): bool => $field->isRequired()));
    }

    /**
     * Options for the mapping dropdown, grouped for readability.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $grouped = [];

        foreach (self::cases() as $field) {
            $grouped[$field->group()][$field->value] = $field->getLabel();
        }

        return $grouped;
    }

    /**
     * Build a flat alias => field lookup for the automatic mapper.
     *
     * @return array<string, self>
     */
    public static function aliasLookup(): array
    {
        static $lookup = null;

        if ($lookup !== null) {
            return $lookup;
        }

        $lookup = [];

        foreach (self::cases() as $field) {
            // The field's own value is always a valid alias, which is what makes
            // Meta's snake_cased headers (full_name, campaign_name) map exactly.
            $lookup[str_replace('_', ' ', $field->value)] = $field;

            foreach ($field->aliases() as $alias) {
                $lookup[$alias] = $field;
            }
        }

        return $lookup;
    }
}
