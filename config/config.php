<?php
// php/config.php

// --- DATABASE CONFIGURATION ---
// Replace with your actual database credentials
define('DB_HOST', 'your_database_address');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// --- GOOGLE OAUTH CONFIGURATION ---
// Replace with your actual Google API credentials
define('GOOGLE_CLIENT_ID', 'xxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// --- APPLICATION CONFIGURATION ---
// Set your application's root URL
define('APP_URL', 'https://example.com');

// --- CONTENT LIMITS ---
// Maximum allowed size for a note in bytes (e.g., 3.5MB)
define('MAX_NOTE_SIZE_BYTES', 3.5 * 1024 * 1024);
// Maximum allowed length for content titles (number of characters)
define('MAX_TITLE_LENGTH', 255);
// Maximum allowed size for a content description in bytes (e.g., 1KB)
define('MAX_DESCRIPTION_SIZE_BYTES', 1024);

// --- PROBLEM SET LIMITS ---
define('MAX_QUESTIONS_PER_SET', 100);
define('MAX_CHOICES_PER_QUESTION', 10);
define('MAX_TEXT_SIZE_BYTES', 10240); // 10KB for question_text, explanation, etc.
define('MAX_CHOICE_TEXT_LENGTH', 255);

// --- FLASHCARD LIMITS ---
define('MAX_FLASHCARD_WORDS_PER_SET', 100);

// --- INFORMATION LIMITS ---
define('MAX_INFORMATION_CONTENT_SIZE_BYTES', 10240); // 10KB for information content

// --- SEARCH LIMITS ---
define('MAX_SEARCH_QUERY_LENGTH', 255);

// --- RATE LIMITS ---
// Default: 30 requests per 5 seconds per user (applied in require_authentication()).
define('RATE_LIMIT_MAX_REQUESTS', 30);
define('RATE_LIMIT_WINDOW_SECONDS', 5);

// --- NOTIFICATION SETTINGS ---
// 使用する通知モジュールのクラス名 (例: App\Notifications\DiscordNotifier, App\Notifications\LogNotifier)
define('NOTIFIER_CLASS', 'App\\Notifications\\DiscordNotifier');
// Discord Webhook の URL (オプション: 未定義または空文字列の場合、Discord通知は無効)
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/xxxxxxxxxxxxxxxxx/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
