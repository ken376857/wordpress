<?php

class WordPressClient {
    private $baseUrl;
    private $username;
    private $password;
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null) {
        $this->config = $config;
        $this->baseUrl = rtrim($config['wordpress']['base_url'], '/');
        $this->username = $config['wordpress']['username'];
        $this->password = $config['wordpress']['password'];
        $this->logger = $logger;
        
        if (empty($this->username) || empty($this->password)) {
            throw new Exception('WordPress credentials are required');
        }
    }
    
    /**
     * Create a new draft post
     */
    public function createDraft($title, $content, $options = []) {
        try {
            $postData = [
                'title' => $title,
                'content' => $content,
                'status' => $options['status'] ?? $this->config['wordpress']['default_status'],
                'author' => $options['author'] ?? $this->config['wordpress']['default_author'],
                'categories' => $options['categories'] ?? [$this->config['wordpress']['default_category']],
                'tags' => $options['tags'] ?? [],
                'excerpt' => $options['excerpt'] ?? $this->generateExcerpt($content),
                'meta' => array_merge([
                    'ai_generated' => true,
                    'ai_model' => $options['ai_model'] ?? 'gpt-4',
                    'ai_gpt_type' => $options['gpt_type'] ?? 'unknown',
                    'generation_timestamp' => date('Y-m-d H:i:s'),
                    'original_prompt' => $options['original_prompt'] ?? ''
                ], $options['meta'] ?? [])
            ];
            
            $response = $this->makeRequest('POST', '/wp-json/wp/v2/posts', $postData);
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Failed to create WordPress post');
            }
            
            $this->log('info', 'Created WordPress draft', [
                'post_id' => $response['id'],
                'title' => $title,
                'content_length' => strlen($content)
            ]);
            
            return [
                'id' => $response['id'],
                'title' => $response['title']['rendered'],
                'url' => $response['link'],
                'edit_url' => $this->getEditUrl($response['id']),
                'status' => $response['status'],
                'created_at' => $response['date']
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to create WordPress draft', [
                'error' => $e->getMessage(),
                'title' => $title
            ]);
            throw $e;
        }
    }
    
    /**
     * Update an existing post
     */
    public function updatePost($postId, $title = null, $content = null, $options = []) {
        try {
            $postData = [];
            
            if ($title !== null) {
                $postData['title'] = $title;
            }
            
            if ($content !== null) {
                $postData['content'] = $content;
            }
            
            if (isset($options['status'])) {
                $postData['status'] = $options['status'];
            }
            
            if (isset($options['categories'])) {
                $postData['categories'] = $options['categories'];
            }
            
            if (isset($options['tags'])) {
                $postData['tags'] = $options['tags'];
            }
            
            if (isset($options['excerpt'])) {
                $postData['excerpt'] = $options['excerpt'];
            }
            
            if (isset($options['meta'])) {
                $postData['meta'] = $options['meta'];
            }
            
            $response = $this->makeRequest('POST', "/wp-json/wp/v2/posts/{$postId}", $postData);
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Failed to update WordPress post');
            }
            
            $this->log('info', 'Updated WordPress post', [
                'post_id' => $postId,
                'title' => $title
            ]);
            
            return [
                'id' => $response['id'],
                'title' => $response['title']['rendered'],
                'url' => $response['link'],
                'status' => $response['status'],
                'updated_at' => $response['modified']
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to update WordPress post', [
                'error' => $e->getMessage(),
                'post_id' => $postId
            ]);
            throw $e;
        }
    }
    
    /**
     * Get post by ID
     */
    public function getPost($postId) {
        try {
            $response = $this->makeRequest('GET', "/wp-json/wp/v2/posts/{$postId}");
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Post not found');
            }
            
            return [
                'id' => $response['id'],
                'title' => $response['title']['rendered'],
                'content' => $response['content']['rendered'],
                'excerpt' => $response['excerpt']['rendered'],
                'status' => $response['status'],
                'url' => $response['link'],
                'created_at' => $response['date'],
                'updated_at' => $response['modified'],
                'author' => $response['author'],
                'categories' => $response['categories'],
                'tags' => $response['tags'],
                'meta' => $response['meta'] ?? []
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to get WordPress post', [
                'error' => $e->getMessage(),
                'post_id' => $postId
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all drafts
     */
    public function getDrafts($limit = 10, $offset = 0) {
        try {
            $params = [
                'status' => 'draft',
                'per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'modified',
                'order' => 'desc'
            ];
            
            $response = $this->makeRequest('GET', '/wp-json/wp/v2/posts', null, $params);
            
            if (!is_array($response)) {
                throw new Exception('Invalid response format');
            }
            
            $drafts = [];
            foreach ($response as $post) {
                $drafts[] = [
                    'id' => $post['id'],
                    'title' => $post['title']['rendered'],
                    'excerpt' => $post['excerpt']['rendered'],
                    'status' => $post['status'],
                    'url' => $post['link'],
                    'edit_url' => $this->getEditUrl($post['id']),
                    'created_at' => $post['date'],
                    'updated_at' => $post['modified'],
                    'is_ai_generated' => $post['meta']['ai_generated'] ?? false
                ];
            }
            
            return $drafts;
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to get WordPress drafts', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete a post
     */
    public function deletePost($postId, $force = false) {
        try {
            $params = $force ? ['force' => true] : [];
            $response = $this->makeRequest('DELETE', "/wp-json/wp/v2/posts/{$postId}", null, $params);
            
            if (!$response) {
                throw new Exception('Failed to delete post');
            }
            
            $this->log('info', 'Deleted WordPress post', [
                'post_id' => $postId,
                'force' => $force
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to delete WordPress post', [
                'error' => $e->getMessage(),
                'post_id' => $postId
            ]);
            throw $e;
        }
    }
    
    /**
     * Get categories
     */
    public function getCategories() {
        try {
            $response = $this->makeRequest('GET', '/wp-json/wp/v2/categories');
            
            if (!is_array($response)) {
                throw new Exception('Invalid response format');
            }
            
            $categories = [];
            foreach ($response as $category) {
                $categories[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'description' => $category['description']
                ];
            }
            
            return $categories;
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to get WordPress categories', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create a new category
     */
    public function createCategory($name, $description = '', $parent = 0) {
        try {
            $categoryData = [
                'name' => $name,
                'description' => $description,
                'parent' => $parent
            ];
            
            $response = $this->makeRequest('POST', '/wp-json/wp/v2/categories', $categoryData);
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Failed to create category');
            }
            
            return [
                'id' => $response['id'],
                'name' => $response['name'],
                'slug' => $response['slug']
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to create WordPress category', [
                'error' => $e->getMessage(),
                'name' => $name
            ]);
            throw $e;
        }
    }
    
    /**
     * Test WordPress connection
     */
    public function testConnection() {
        try {
            $response = $this->makeRequest('GET', '/wp-json/wp/v2/posts', null, ['per_page' => 1]);
            
            if (!is_array($response)) {
                throw new Exception('Invalid response format');
            }
            
            $this->log('info', 'WordPress connection test successful');
            return true;
            
        } catch (Exception $e) {
            $this->log('error', 'WordPress connection test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Generate excerpt from content
     */
    private function generateExcerpt($content, $length = 150) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        
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
    
    /**
     * Get edit URL for a post
     */
    private function getEditUrl($postId) {
        return $this->baseUrl . "/wp-admin/post.php?post={$postId}&action=edit";
    }
    
    /**
     * Make HTTP request to WordPress REST API
     */
    private function makeRequest($method, $endpoint, $data = null, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            'Content-Type: application/json',
            'User-Agent: ChatGPT-WordPress-Integration/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
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
            $errorMessage = $errorData['message'] ?? "HTTP {$httpCode} error";
            throw new Exception("WordPress API error: {$errorMessage}");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from WordPress API');
        }
        
        return $decodedResponse;
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
}
?>