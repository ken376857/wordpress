<?php
/**
 * ChatGPT to WordPress Auto-Draft System Configuration
 */

return [
    // OpenAI API Configuration
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => 'gpt-4',
        'max_tokens' => 2000,
        'temperature' => 0.7,
        'timeout' => 120
    ],
    
    // WordPress API Configuration
    'wordpress' => [
        'base_url' => getenv('WP_BASE_URL') ?: 'http://localhost/wordpress',
        'username' => getenv('WP_USERNAME') ?: '',
        'password' => getenv('WP_PASSWORD') ?: '',
        'default_author' => 1,
        'default_category' => 1,
        'default_status' => 'draft'
    ],
    
    // Custom GPT Configuration
    'custom_gpts' => [
        'gpt1' => [
            'name' => 'ブログ記事作成GPT',
            'description' => 'SEO最適化されたブログ記事を作成',
            'system_prompt' => 'あなたはプロのブログライターです。SEOを意識した魅力的なブログ記事を作成してください。',
            'enabled' => true
        ],
        'gpt2' => [
            'name' => 'ニュース記事作成GPT',
            'description' => 'ニュース記事を客観的に作成',
            'system_prompt' => 'あなたはプロのジャーナリストです。客観的で正確なニュース記事を作成してください。',
            'enabled' => true
        ]
    ],
    
    // System Settings
    'system' => [
        'auto_save' => true,
        'max_retries' => 3,
        'retry_delay' => 5, // seconds
        'log_level' => 'info',
        'cache_duration' => 3600, // seconds
        'batch_size' => 5
    ],
    
    // Security Settings
    'security' => [
        'rate_limit' => 60, // requests per hour
        'allowed_ips' => [], // empty array means all IPs allowed
        'api_key_required' => true,
        'csrf_protection' => true
    ]
];
?>