<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/OpenAIClient.php';
require_once __DIR__ . '/WordPressClient.php';
require_once __DIR__ . '/Logger.php';

class ChatGPTWordPressAPI {
    private $config;
    private $openai;
    private $wordpress;
    private $logger;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->logger = new Logger($this->config['system']['log_level']);
        
        try {
            $this->openai = new OpenAIClient($this->config, $this->logger);
            $this->wordpress = new WordPressClient($this->config, $this->logger);
        } catch (Exception $e) {
            $this->sendError('Configuration error: ' . $e->getMessage(), 500);
        }
    }
    
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? $this->getPostData()['action'] ?? null;
            
            if (!$action) {
                $this->sendError('Action parameter is required', 400);
            }
            
            $this->logger->info("API request: {$method} {$action}");
            
            switch ($action) {
                case 'generate_content':
                    $this->handleGenerateContent();
                    break;
                    
                case 'test_connection':
                    $this->handleTestConnection();
                    break;
                    
                case 'get_drafts':
                    $this->handleGetDrafts();
                    break;
                    
                case 'get_categories':
                    $this->handleGetCategories();
                    break;
                    
                case 'batch_generate':
                    $this->handleBatchGenerate();
                    break;
                    
                default:
                    $this->sendError("Unknown action: {$action}", 400);
            }
            
        } catch (Exception $e) {
            $this->logger->error('API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function handleGenerateContent() {
        $data = $this->getPostData();
        
        // Validate required fields
        $required = ['gpt_type', 'prompt'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendError("Field '{$field}' is required", 400);
            }
        }
        
        $gptType = $data['gpt_type'];
        $prompt = $data['prompt'];
        $category = (int)($data['category'] ?? $this->config['wordpress']['default_category']);
        $tags = $this->parseTags($data['tags'] ?? '');
        $postStatus = $data['post_status'] ?? $this->config['wordpress']['default_status'];
        
        // GPT options
        $gptOptions = [
            'max_tokens' => $data['max_tokens'] ?? $this->config['openai']['max_tokens'],
            'temperature' => $data['temperature'] ?? $this->config['openai']['temperature']
        ];
        
        try {
            // Generate content with OpenAI
            $this->logger->info('Generating content', [
                'gpt_type' => $gptType,
                'prompt_length' => strlen($prompt)
            ]);
            
            $gptResult = $this->openai->generateContent($prompt, $gptType, $gptOptions);
            
            // Extract title and format content
            $title = $this->openai->extractTitle($gptResult['content']);
            $formattedContent = $this->openai->formatForWordPress($gptResult['content']);
            
            // Create WordPress draft
            $this->logger->info('Creating WordPress draft', [
                'title' => $title,
                'content_length' => strlen($formattedContent)
            ]);
            
            $wpOptions = [
                'status' => $postStatus,
                'categories' => [$category],
                'tags' => $tags,
                'excerpt' => $this->generateExcerpt($gptResult['content']),
                'ai_model' => $gptOptions['max_tokens'] ?? 'gpt-4',
                'gpt_type' => $gptType,
                'original_prompt' => $prompt,
                'meta' => [
                    'ai_generated' => true,
                    'ai_gpt_type' => $gptType,
                    'ai_gpt_name' => $gptResult['gpt_name'],
                    'ai_tokens_used' => $gptResult['usage']['total_tokens'] ?? 0,
                    'ai_generation_timestamp' => $gptResult['timestamp']
                ]
            ];
            
            $wpResult = $this->wordpress->createDraft($title, $formattedContent, $wpOptions);
            
            // Success response
            $this->sendSuccess([
                'title' => $wpResult['title'],
                'content' => $formattedContent,
                'excerpt' => $wpOptions['excerpt'],
                'post_id' => $wpResult['id'],
                'url' => $wpResult['url'],
                'edit_url' => $wpResult['edit_url'],
                'status' => $wpResult['status'],
                'created_at' => $wpResult['created_at'],
                'gpt_type' => $gptType,
                'gpt_name' => $gptResult['gpt_name'],
                'tokens_used' => $gptResult['usage']['total_tokens'] ?? 0
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Content generation failed', [
                'error' => $e->getMessage(),
                'gpt_type' => $gptType
            ]);
            $this->sendError('Content generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    private function handleTestConnection() {
        try {
            // Test OpenAI connection
            $gpts = $this->openai->getAvailableGPTs();
            
            // Test WordPress connection
            $this->wordpress->testConnection();
            
            $this->sendSuccess([
                'openai_status' => 'connected',
                'wordpress_status' => 'connected',
                'available_gpts' => $gpts,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Connection test failed: ' . $e->getMessage(), 500);
        }
    }
    
    private function handleGetDrafts() {
        try {
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $drafts = $this->wordpress->getDrafts($limit, $offset);
            
            $this->sendSuccess([
                'drafts' => $drafts,
                'total' => count($drafts),
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to get drafts: ' . $e->getMessage(), 500);
        }
    }
    
    private function handleGetCategories() {
        try {
            $categories = $this->wordpress->getCategories();
            
            $this->sendSuccess([
                'categories' => $categories,
                'total' => count($categories)
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to get categories: ' . $e->getMessage(), 500);
        }
    }
    
    private function handleBatchGenerate() {
        $data = $this->getPostData();
        
        if (empty($data['prompts']) || !is_array($data['prompts'])) {
            $this->sendError('Prompts array is required', 400);
        }
        
        $prompts = $data['prompts'];
        $gptTypes = $data['gpt_types'] ?? [];
        $category = (int)($data['category'] ?? $this->config['wordpress']['default_category']);
        $tags = $this->parseTags($data['tags'] ?? '');
        $postStatus = $data['post_status'] ?? $this->config['wordpress']['default_status'];
        
        $gptOptions = [
            'max_tokens' => $data['max_tokens'] ?? $this->config['openai']['max_tokens'],
            'temperature' => $data['temperature'] ?? $this->config['openai']['temperature']
        ];
        
        try {
            // Generate content for all prompts
            $gptResults = $this->openai->batchGenerate($prompts, $gptTypes, $gptOptions);
            
            $wpResults = [];
            $errors = $gptResults['errors'];
            
            // Create WordPress posts for successful generations
            foreach ($gptResults['results'] as $index => $gptResult) {
                try {
                    $title = $this->openai->extractTitle($gptResult['content']);
                    $formattedContent = $this->openai->formatForWordPress($gptResult['content']);
                    
                    $wpOptions = [
                        'status' => $postStatus,
                        'categories' => [$category],
                        'tags' => $tags,
                        'excerpt' => $this->generateExcerpt($gptResult['content']),
                        'meta' => [
                            'ai_generated' => true,
                            'ai_gpt_type' => $gptResult['gpt_type'],
                            'ai_gpt_name' => $gptResult['gpt_name'],
                            'ai_tokens_used' => $gptResult['usage']['total_tokens'] ?? 0,
                            'ai_generation_timestamp' => $gptResult['timestamp'],
                            'ai_batch_index' => $index
                        ]
                    ];
                    
                    $wpResult = $this->wordpress->createDraft($title, $formattedContent, $wpOptions);
                    
                    $wpResults[] = array_merge($wpResult, [
                        'gpt_type' => $gptResult['gpt_type'],
                        'tokens_used' => $gptResult['usage']['total_tokens'] ?? 0
                    ]);
                    
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'gpt_result' => $gptResult,
                        'error' => 'WordPress error: ' . $e->getMessage()
                    ];
                }
            }
            
            $this->sendSuccess([
                'results' => $wpResults,
                'errors' => $errors,
                'success_count' => count($wpResults),
                'error_count' => count($errors),
                'total_prompts' => count($prompts)
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Batch generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    private function getPostData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON data', 400);
        }
        
        return $data ?? [];
    }
    
    private function parseTags($tagsString) {
        if (empty($tagsString)) {
            return [];
        }
        
        $tags = array_map('trim', explode(',', $tagsString));
        return array_filter($tags, function($tag) {
            return !empty($tag);
        });
    }
    
    private function generateExcerpt($content, $length = 150) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $excerpt = substr($text, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }
    
    private function sendSuccess($data) {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Initialize and handle request
try {
    $api = new ChatGPTWordPressAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System initialization failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>