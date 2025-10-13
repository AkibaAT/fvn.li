<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the universal audit logging system that automatically
    | tracks all Eloquent model changes across the application.
    |
    */

    /**
     * Enable or disable audit logging globally
     */
    'enabled' => env('AUDIT_ENABLED', true),

    /**
     * Process audit logs asynchronously via queue jobs
     */
    'async' => env('AUDIT_ASYNC', true),

    /**
     * Always fallback to synchronous processing if async fails
     * This guarantees audit logs are never lost
     */
    'async_with_fallback' => env('AUDIT_ASYNC_WITH_FALLBACK', true),

    /**
     * Queue connection to use for async processing
     */
    'queue_connection' => env('AUDIT_QUEUE_CONNECTION', 'redis'),

    /**
     * Queue name for audit jobs
     */
    'queue_name' => env('AUDIT_QUEUE_NAME', 'audit'),

    /**
     * Batch size for processing multiple audit events together
     */
    'batch_size' => env('AUDIT_BATCH_SIZE', 50),

    /**
     * Events to exclude from audit logging
     */
    'exclude_events' => [
        'retrieved',    // Too verbose, only tracks when models are loaded
        'restored',     // Soft delete restoration (can be included if needed)
    ],

    /**
     * Fields to exclude from audit logging across all models
     */
    'exclude_fields' => [
        'password',
        'remember_token',
        'email_verified_at',
        'updated_at',   // Usually not significant for audit purposes
        'created_at',   // Creation is tracked via 'created' event
        'token',        // OAuth and API tokens
        'refresh_token',
        'access_token',
        'api_key',
        'client_secret',
        'keystore_path', // Android build keystores contain sensitive paths
        'private_notes', // User's private VN list notes
    ],

    /**
     * Models to completely exclude from audit logging
     * Use full class names
     */
    'exclude_models' => [
        'Laravel\Sanctum\PersonalAccessToken',
        'Illuminate\Notifications\DatabaseNotification',
        'App\Models\ChangeLog', // Prevent recursive logging
        'App\Models\ClickStat', // Exclude click statistics from change log
        'App\Models\UniqueDialogueText', // Exclude dialogue texts - high volume, automatic indexing
        'App\Models\DialogueLine', // Exclude dialogue lines - high volume, automatic indexing
        'App\Models\ImportState', // Exclude import state tracking - temporary operational data
    ],

    /**
     * Tables to exclude from audit logging
     * Alternative to exclude_models for non-Eloquent or external tables
     */
    'exclude_tables' => [
        'migrations',
        'jobs',
        'failed_jobs',
        'sessions',
        'cache',
        'personal_access_tokens', // Laravel Sanctum tokens
        'notifications',          // May contain sensitive notification data
        'password_reset_tokens',  // Password reset tokens
        'password_resets',        // Legacy password reset table
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],

    /**
     * Context information to automatically capture
     */
    'include_context' => [
        'user_id' => true,
        'ip_address' => env('AUDIT_LOG_IP_ADDRESSES', true),  // Privacy-configurable IP logging
        'user_agent' => true,
        'url' => true,
        'method' => true,
        'session_id' => true,  // Enable session tracking for grouping related changes
        'request_id' => true,  // Enable request tracking for tracing operation flows
        'command' => true,     // Enable command tracking for console operations
    ],

    /**
     * Retention settings for audit logs
     */
    'retention' => [
        'enabled' => true,
        'days' => env('AUDIT_RETENTION_DAYS', 2555), // ~7 years default
        'cleanup_command' => true, // Enable automatic cleanup command

        // Privacy-compliant retention policies
        'sensitive_data_retention_days' => env('AUDIT_SENSITIVE_RETENTION_DAYS', 90),
        // Shorter retention for sensitive data
        'ip_address_retention_days' => env('AUDIT_IP_RETENTION_DAYS', 365), // 1 year for IP addresses (GDPR compliant)
    ],

    /**
     * Performance settings
     */
    'performance' => [
        'max_string_length' => 1000, // Truncate very long strings
        'max_array_depth' => 5,      // Limit nested array depth
        'skip_large_payloads' => true, // Skip if serialized data > 64KB
    ],

    /**
     * Model-specific configuration overrides
     * Use full class names as keys
     */
    'model_settings' => [
        'App\Models\User' => [
            'exclude_fields' => ['password', 'remember_token', 'email_verified_at'],
            'sensitive' => true, // Mark as containing sensitive data
        ],
        'App\Models\SocialAccount' => [
            'exclude_fields' => ['token', 'refresh_token', 'provider_data'],
            'sensitive' => true, // Contains OAuth tokens and user data
        ],
        'App\Models\AndroidBuild' => [
            'exclude_fields' => ['keystore_path'],
            'sensitive' => true, // Contains sensitive build paths
        ],
        'App\Models\VnListEntry' => [
            'exclude_fields' => ['private_notes'],
            'sensitive' => false, // Only specific fields are sensitive
        ],
        'App\Models\Game' => [
            'include_relations' => ['versions'], // Track related model changes
        ],
        // Add more model-specific settings as needed
    ],

    /**
     * System user for anonymized audit logs
     * This user ID will be assigned to audit logs when user data is anonymized
     * to preserve audit trail integrity while removing personal identifiers
     */
    'system_user_id' => env('AUDIT_SYSTEM_USER_ID', 1),

    /**
     * Privacy and compliance settings
     */
    'privacy' => [
        // IP address anonymization settings
        'anonymize_ip_addresses' => env('AUDIT_ANONYMIZE_IP', true), // Enable IP anonymization
        'ip_anonymization_method' => env('AUDIT_IP_ANONYMIZATION_METHOD', 'subnet'), // 'subnet', 'hash', or 'full'

        // Data subject rights compliance
        'enable_data_export' => env('AUDIT_ENABLE_DATA_EXPORT', true), // GDPR Article 20 - Data portability
        'enable_data_deletion' => env('AUDIT_ENABLE_DATA_DELETION', true), // GDPR Article 17 - Right to erasure
    ],

    /**
     * Source detection settings
     */
    'sources' => [
        'web' => 'web',
        'api' => 'api',
        'command' => 'command',
        'cron' => 'cron',
        'system' => 'system',
        'queue' => 'queue',
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Examples for Request/Session/Command Tracking
    |--------------------------------------------------------------------------
    |
    | With request_id, session_id, and command enabled, you can trace audit logs
    | across related operations:
    |
    | // Get all changes in a specific request
    | ChangeLog::forRequest('req_a1b2c3d4e5f6g7h8')->get();
    |
    | // Get all changes in a user session
    | ChangeLog::forSession('sess_abc123def456')->get();
    |
    | // Get all changes from a specific command
    | ChangeLog::forCommand('migrate')->get();
    | ChangeLog::forCommand('import:games')->get();
    |
    | // Get all changes from artisan commands
    | ChangeLog::forScript('artisan')->get();
    |
    | // Get changes grouped by request/session/command
    | ChangeLog::groupedByRequest()->get();
    | ChangeLog::groupedBySession()->get();
    | ChangeLog::groupedByCommand()->get();
    |
    | // Get command activity for a specific time period
    | ChangeLog::betweenDates($start, $end)
    |          ->groupedByCommand()
    |          ->get()
    |          ->groupBy('command_name');
    |
    | // Get user session activity
    | ChangeLog::byUser($user)
    |          ->groupedBySession()
    |          ->get()
    |          ->groupBy('session_id');
    |
    | // Debug specific command issues
    | ChangeLog::forCommand('queue:work')
    |          ->where('source', 'command')
    |          ->orderBy('timestamp', 'desc')
    |          ->get();
    |
    |--------------------------------------------------------------------------
    | Privacy and GDPR Compliance Examples
    |--------------------------------------------------------------------------
    |
    | Privacy management commands for GDPR/CCPA compliance:
    |
    | // Export user's audit data (Data Portability - GDPR Article 20)
    | php artisan audit:privacy export --user-id=123
    | php artisan audit:privacy export --email=user@example.com
    |
    | // Delete user's audit data (Right to Erasure - GDPR Article 17)
    | php artisan audit:privacy delete --user-id=123 --force
    |
    | // Anonymize user's audit data (partial erasure)
    | php artisan audit:privacy anonymize --user-id=123
    |
    | // Generate privacy compliance report
    | php artisan audit:privacy report
    |
    | // Cleanup old audit logs based on retention policies
    | php artisan audit:cleanup --dry-run
    | php artisan audit:cleanup --force
    | php artisan audit:cleanup --sensitive-only
    | php artisan audit:cleanup --ip-only
    |
    | Programmatic data subject rights:
    |
    | // Export user data
    | $exportData = ChangeLog::exportUserData($userId);
    |
    | // Delete user audit logs
    | $deletedCount = ChangeLog::deleteUserData($userId);
    |
    | // Anonymize user audit logs
    | $anonymizedCount = ChangeLog::anonymizeUserData($userId);
    |
    | // Get compliance reports
    | $personalDataLogs = ChangeLog::getPersonalDataLogs()->count();
    | $sensitiveLogs = ChangeLog::getSensitiveModelLogs()->count();
    |
    | Environment Variables for Privacy Configuration:
    |
    | AUDIT_ENABLED=true                      # Enable/disable audit logging globally
    | AUDIT_ASYNC=true                       # Process audit logs asynchronously
    | AUDIT_ASYNC_WITH_FALLBACK=true         # Fallback to sync if async fails (guarantees logging)
    | AUDIT_QUEUE_CONNECTION=redis          # Queue connection for async processing
    |
    | Recommended overall Redis setup:
    | CACHE_DRIVER=redis                   # Use Redis for caching (db 1)
    | SESSION_DRIVER=redis                 # Use Redis for sessions (db 2)
    | QUEUE_CONNECTION=redis               # Use Redis for queues (db 0)
    | AUDIT_QUEUE_NAME=audit                # Audit jobs use 'audit' queue name
    | AUDIT_LOG_IP_ADDRESSES=true              # Enable/disable IP logging
    | AUDIT_ANONYMIZE_IP=false                 # Enable IP anonymization
    | AUDIT_IP_ANONYMIZATION_METHOD=subnet     # subnet, hash, or full
    | AUDIT_RETENTION_DAYS=2555               # General retention (7 years)
    | AUDIT_SENSITIVE_RETENTION_DAYS=90       # Sensitive data retention (3 months)
    | AUDIT_IP_RETENTION_DAYS=365             # IP address retention (1 year)
    | AUDIT_SYSTEM_USER_ID=1                  # System user for anonymized logs
    | AUDIT_ENABLE_DATA_EXPORT=true           # Enable data export features
    | AUDIT_ENABLE_DATA_DELETION=true         # Enable data deletion features
    |
    */
];
