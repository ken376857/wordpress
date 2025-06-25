<?php

class OpenAIClient {
    private $apiKey;
    private $baseUrl;
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null) {
        $this->config = $config;
        $this->apiKey = $config['openai']['api_key'];
        $this->baseUrl = 'https://api.openai.com/v1';
        $this->logger = $logger;
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is required');
        }
    }
    
    /**
     * Generate content using custom GPT configuration
     */
    public function generateContent($prompt, $gptType = 'gpt1', $options = []) {
        try {
            if (!isset($this->config['custom_gpts'][$gptType])) {
                throw new Exception("Custom GPT type '{$gptType}' not found");
            }
            
            $gptConfig = $this->config['custom_gpts'][$gptType];
            
            if (!$gptConfig['enabled']) {
                throw new Exception("Custom GPT '{$gptType}' is disabled");
            }
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => $gptConfig['system_prompt']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];
            
            $requestBody = [
                'model' => $options['model'] ?? $this->config['openai']['model'],
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? $this->config['openai']['max_tokens'],
                'temperature' => $options['temperature'] ?? $this->config['openai']['temperature'],
                'stream' => false
            ];
            
            $response = $this->makeRequest('/chat/completions', $requestBody);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                throw new Exception('Invalid response from OpenAI API');
            }
            
            $content = $response['choices'][0]['message']['content'];
            
            $this->log('info', "Generated content using {$gptType}", [
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($content),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0
            ]);
            
            return [
                'content' => $content,
                'usage' => $response['usage'] ?? [],
                'gpt_type' => $gptType,
                'gpt_name' => $gptConfig['name'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to generate content', [
                'error' => $e->getMessage(),
                'gpt_type' => $gptType,
                'prompt' => substr($prompt, 0, 100) . '...'
            ]);
            throw $e;
        }
    }
    
    /**
     * Batch generate content for multiple prompts
     */
    public function batchGenerate($prompts, $gptTypes = [], $options = []) {
        $results = [];
        $errors = [];
        
        foreach ($prompts as $index => $prompt) {
            $gptType = $gptTypes[$index] ?? 'gpt1';
            
            try {
                $result = $this->generateContent($prompt, $gptType, $options);
                $results[] = $result;
                
                // Rate limiting - small delay between requests
                if ($index < count($prompts) - 1) {
                    usleep(500000); // 0.5 second delay
                }
                
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'prompt' => $prompt,
                    'gpt_type' => $gptType,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'success_count' => count($results),
            'error_count' => count($errors)
        ];
    }
    
    /**
     * Extract title from generated content
     */
    public function extractTitle($content) {
        // Try to find a title in the first line or markdown heading
        $lines = explode("\n", trim($content));
        $firstLine = trim($lines[0]);
        
        // Check for markdown heading
        if (preg_match('/^#+\s*(.+)$/', $firstLine, $matches)) {
            return trim($matches[1]);
        }
        
        // Check for title-like first line
        if (strlen($firstLine) > 10 && strlen($firstLine) < 100 && !preg_match('/[.!?]$/', $firstLine)) {
            return $firstLine;
        }
        
        // Generate title from content
        $words = explode(' ', strip_tags($content));
        $titleWords = array_slice($words, 0, 8);
        $title = implode(' ', $titleWords);
        
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        
        return $title ?: 'AIが生成した記事';
    }
    
    /**
     * Clean and format content for WordPress
     */
    public function formatForWordPress($content) {
        // Remove excessive whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Convert markdown headers to HTML
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        
        // Convert markdown bold and italic
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
        
        // Convert line breaks to paragraphs
        $paragraphs = explode("\n\n", $content);
        $formatted = [];
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph) && !preg_match('/^<h[1-6]>/', $paragraph)) {
                $paragraph = '<p>' . nl2br($paragraph) . '</p>';
            }
            $formatted[] = $paragraph;
        }
        
        return implode("\n\n", $formatted);
    }
    
    /**
     * Make HTTP request to OpenAI API
     */
    private function makeRequest($endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'User-Agent: ChatGPT-WordPress-Integration/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['openai']['timeout'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode} error";
            throw new Exception("OpenAI API error: {$errorMessage}");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI API');
        }
        
        return $decodedResponse;
    }
    
    /**
     * Get available custom GPTs
     */
    public function getAvailableGPTs() {
        $gpts = [];
        
        foreach ($this->config['custom_gpts'] as $key => $config) {
            if ($config['enabled']) {
                $gpts[$key] = [
                    'name' => $config['name'],
                    'description' => $config['description']
                ];
            }
        }
        
        return $gpts;
    }
    
    /**
     * Log messages
     */
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        } else {
            error_log("[{$level}] {$message} " . json_encode($context));
        }
    }
    
    /**
     * Check API rate limits
     */
    public function checkRateLimit() {
        // Simple rate limiting implementation
        $cacheKey = 'openai_rate_limit_' . date('YmdH');
        $requests = (int) $this->getCache($cacheKey, 0);
        
        if ($requests >= $this->config['security']['rate_limit']) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        $this->setCache($cacheKey, $requests + 1, 3600);
        return true;
    }
    
    /**
     * Simple cache implementation
     */
    private function getCache($key, $default = null) {
        $cacheFile = sys_get_temp_dir() . "/openai_cache_{$key}";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->config['system']['cache_duration']) {
            return unserialize(file_get_contents($cacheFile));
        }
        
        return $default;
    }
    
    private function setCache($key, $value, $ttl = 3600) {
        $cacheFile = sys_get_temp_dir() . "/openai_cache_{$key}";
        file_put_contents($cacheFile, serialize($value));
    }
}
?>