<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE', ''),
        'hash_secret' => env('VNPAY_HASH_SECRET', ''),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'version' => '2.1.0',
    ],

    'bank' => [
        'name' => env('BANK_NAME', ''),
        'bin' => env('BANK_BIN', ''),
        'account_number' => env('BANK_ACCOUNT_NUMBER', ''),
        'account_name' => env('BANK_ACCOUNT_NAME', ''),
    ],

    'momo' => [
        'phone' => env('MOMO_PHONE', ''),
        'account_name' => env('MOMO_ACCOUNT_NAME', ''),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'from' => env('TWILIO_FROM', ''),
    ],

    // Thanh toán chuyển khoản tự động: SePay gọi webhook {APP_URL}/payment/sepay/webhook với header "Authorization: Apikey <khóa>".
    'sepay' => [
        'webhook_api_key' => trim((string) env('SEPAY_WEBHOOK_API_KEY', '')),
    ],

    // Chatbot AI tư vấn (Google Gemini, function calling). Không có API key thì chatbot chạy chế độ offline.
    'gemini' => [
        'api_key' => trim((string) env('GEMINI_API_KEY', '')),
        'model' => trim((string) env('GEMINI_MODEL', 'gemini-3.5-flash')),
        // Có thể liệt kê nhiều model, cách nhau bằng dấu phẩy: mỗi model có hạn mức miễn phí riêng.
        'fallback_model' => trim((string) env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash-lite,gemini-flash-lite-latest')),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
        'timeout' => (int) env('GEMINI_TIMEOUT', 40),
    ],

    // Đăng nhập Google/Facebook qua Firebase Authentication. Để trống thì nút đăng nhập mạng xã hội tự ẩn.
    'firebase' => [
        'api_key' => trim((string) env('FIREBASE_API_KEY', '')),
        'auth_domain' => trim((string) env('FIREBASE_AUTH_DOMAIN', '')),
        'project_id' => trim((string) env('FIREBASE_PROJECT_ID', '')),
        'app_id' => trim((string) env('FIREBASE_APP_ID', '')),
    ],

    'seed_password' => env('SEED_PASSWORD', '123456'),

];
