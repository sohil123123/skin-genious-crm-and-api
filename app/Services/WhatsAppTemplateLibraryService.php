<?php

namespace App\Services;

class WhatsAppTemplateLibraryService
{
    /**
     * Get all library templates grouped by category.
     */
    public static function getTemplates(): array
    {
        return [
            'AUTHENTICATION' => [
                'account_verification_otp' => [
                    'title' => 'account_verification_otp',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "*{{1}}* is your verification code. For your security, do not share this code.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '123456']
                    ],
                ],
                'delivery_code_1' => [
                    'title' => 'delivery_code_1',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your order is arriving soon. {{1}} is your verification code. Please show this to the delivery associate.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '492015']
                    ],
                ],
                'delivery_code_2' => [
                    'title' => 'delivery_code_2',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Hi {{1}}. Your {{2}} order is currently pending shipment. The estimated delivery date is {{3}}, with delivery scheduled between {{4}} and {{5}}. Please note the delivery person may ask for your delivery code ({{6}}) upon arrival.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'John'],
                        ['key' => '2', 'value' => 'Skin Genious'],
                        ['key' => '3', 'value' => '2026-07-20'],
                        ['key' => '4', 'value' => '10:00 AM'],
                        ['key' => '5', 'value' => '02:00 PM'],
                        ['key' => '6', 'value' => '859204'],
                    ],
                ],
                'delivery_code_3' => [
                    'title' => 'delivery_code_3',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your order is on its way and scheduled to arrive at {{1}}. Please show verification code ({{2}}) to receive your order.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '10:15 AM'],
                        ['key' => '2', 'value' => '392019']
                    ],
                ],
                'delivery_code_4' => [
                    'title' => 'delivery_code_4',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Please share code {{1}} with delivery agent after verifying the package.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '583920']
                    ],
                ],
                'delivery_code_5' => [
                    'title' => 'delivery_code_5',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your order of {{1}} items (Order ID: {{2}}) is out for delivery today. Please provide the code ({{3}}) to the delivery agent to receive your order.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '3'],
                        ['key' => '2', 'value' => 'ORD-99201'],
                        ['key' => '3', 'value' => '482019']
                    ],
                ],
                'delivery_code_6' => [
                    'title' => 'delivery_code_6',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your order {{1}} is on its way and scheduled to arrive {{2}}. Please show verification code ({{3}}) to receive your order.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'ORD-10492'],
                        ['key' => '2', 'value' => 'Today at 4 PM'],
                        ['key' => '3', 'value' => '992014']
                    ],
                ],
                'login_code' => [
                    'title' => 'login_code',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your login code is {{1}}. No further action is needed if you didn't request this.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '839201']
                    ],
                ],
                'data_update_code' => [
                    'title' => 'data_update_code',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your personal data will be updated, provide the account executive with the verification code: {{1}}",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => '284019']
                    ],
                ],
                'temporary_password' => [
                    'title' => 'temporary_password',
                    'category' => 'AUTHENTICATION',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your temporary password is {{1}}. Please log in and update it as soon as possible.",
                    'footer_text' => "Expires in 10 minutes.",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Copy Code', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'Xy9#kP2m']
                    ],
                ],
            ],
            'UTILITY' => [
                'account_creation_confirmation_3' => [
                    'title' => 'account_creation_confirmation_3',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Finalize account set-up\n\nHi {{1}},\n\nYour new account has been created successfully.\n\nPlease verify {{2}} to complete your profile.",
                    'footer_text' => "AI Aesthetics CRM",
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Verify account', 'url' => 'https://example.com/verify']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'John'],
                        ['key' => '2', 'value' => 'your email address']
                    ],
                ],
                'address_update' => [
                    'title' => 'address_update',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Address update\n\nHi {{1}}, your delivery address has been successfully updated to {{2}}. Contact support at {{3}} for any inquiries.",
                    'footer_text' => null,
                    'buttons' => [],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'John'],
                        ['key' => '2', 'value' => '123 Main St, Mumbai'],
                        ['key' => '3', 'value' => 'support@skingenious.in']
                    ],
                ],
                'appointment_cancellation_1' => [
                    'title' => 'appointment_cancellation_1',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your appointment was canceled\n\nHello {{1}},\n\nYour upcoming appointment with {{2}} on {{3}} at {{4}} has been canceled.\n\nLet us know if you have any questions or need to reschedule.",
                    'footer_text' => null,
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'View details', 'url' => 'https://example.com/appointments']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'Sarah'],
                        ['key' => '2', 'value' => 'Skin Genious Clinic'],
                        ['key' => '3', 'value' => '2026-07-25'],
                        ['key' => '4', 'value' => '03:00 PM']
                    ],
                ],
                'appointment_cancelled' => [
                    'title' => 'appointment_cancelled',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Appointment cancelled\n\nHi {{1}},\n\nYour appointment on {{2}} has been cancelled. We hope to see you another time.",
                    'footer_text' => null,
                    'buttons' => [],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'John'],
                        ['key' => '2', 'value' => 'July 25, 2026']
                    ],
                ],
                'appointment_confirmation_1' => [
                    'title' => 'appointment_confirmation_1',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Your appointment is booked\n\nHello {{1}},\n\nThank you for booking with {{2}}.\n\nYour appointment for {{3}} on {{4}} at {{5}} is confirmed.",
                    'footer_text' => null,
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'View details', 'url' => 'https://example.com/appointments']
                    ],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'Emily'],
                        ['key' => '2', 'value' => 'Skin Genious'],
                        ['key' => '3', 'value' => 'Skin Consultation'],
                        ['key' => '4', 'value' => 'July 22'],
                        ['key' => '5', 'value' => '11:00 AM']
                    ],
                ],
                'appointment_confirmed' => [
                    'title' => 'appointment_confirmed',
                    'category' => 'UTILITY',
                    'header_type' => 'none',
                    'header_content' => null,
                    'body_text' => "Appointment confirmed\n\nHi {{1}},\n\nYour appointment is scheduled for {{2}}.\n\nService: {{3}}\nConfirmation number: {{4}}\n\nWe're looking forward to your visit.",
                    'footer_text' => null,
                    'buttons' => [],
                    'variable_samples' => [
                        ['key' => '1', 'value' => 'Michael'],
                        ['key' => '2', 'value' => 'July 23, 2:00 PM'],
                        ['key' => '3', 'value' => 'Facial Treatment'],
                        ['key' => '4', 'value' => 'CNF-99201']
                    ],
                ],
            ],
        ];
    }
}
