<?php
/**
 * Setup and Installation Script for ChatGPT to WordPress System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class SystemSetup {
    private $requirements = [
        'php_version' => '7.4.0',
        'extensions' => ['curl', 'json', 'mbstring'],
        'functions' => ['file_get_contents', 'curl_init', 'json_encode']
    ];
    
    public function run() {
        echo "<h1>ChatGPT to WordPress - システムセットアップ</h1>\n";
        
        $this->checkRequirements();
        $this->checkConfiguration();
        $this->testConnections();
        $this->displaySetupInstructions();
    }
    
    private function checkRequirements() {
        echo "<h2>1. システム要件チェック</h2>\n";
        
        // PHP Version
        $phpVersion = PHP_VERSION;
        $minVersion = $this->requirements['php_version'];
        $phpOk = version_compare($phpVersion, $minVersion, '>=');
        
        echo "<p>PHP バージョン: {$phpVersion} " . 
             ($phpOk ? "✅" : "❌ (最低 {$minVersion} が必要)") . "</p>\n";
        
        // Extensions
        echo "<p>拡張モジュール:</p>\n<ul>\n";
        foreach ($this->requirements['extensions'] as $ext) {
            $loaded = extension_loaded($ext);
            echo "<li>{$ext}: " . ($loaded ? "✅" : "❌") . "</li>\n";
        }
        echo "</ul>\n";
        
        // Functions
        echo "<p>必要な関数:</p>\n<ul>\n";
        foreach ($this->requirements['functions'] as $func) {
            $exists = function_exists($func);
            echo "<li>{$func}: " . ($exists ? "✅" : "❌") . "</li>\n";
        }
        echo "</ul>\n";
        
        // Permissions
        $dirs = [__DIR__, sys_get_temp_dir()];
        echo "<p>ディレクトリ権限:</p>\n<ul>\n";
        foreach ($dirs as $dir) {
            $writable = is_writable($dir);
            echo "<li>{$dir}: " . ($writable ? "✅ 書き込み可能" : "❌ 書き込み不可") . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    private function checkConfiguration() {
        echo "<h2>2. 設定ファイルチェック</h2>\n";
        
        $configFile = __DIR__ . '/config.php';
        $envFile = __DIR__ . '/.env';
        $envExampleFile = __DIR__ . '/.env.example';
        
        echo "<p>設定ファイル:</p>\n<ul>\n";
        echo "<li>config.php: " . (file_exists($configFile) ? "✅" : "❌") . "</li>\n";
        echo "<li>.env: " . (file_exists($envFile) ? "✅" : "⚠️ オプション") . "</li>\n";
        echo "<li>.env.example: " . (file_exists($envExampleFile) ? "✅" : "❌") . "</li>\n";
        echo "</ul>\n";
        
        if (file_exists($configFile)) {
            try {
                $config = require $configFile;
                
                echo "<p>設定値チェック:</p>\n<ul>\n";
                
                // OpenAI API Key
                $openaiKey = $config['openai']['api_key'] ?? '';
                echo "<li>OpenAI API Key: " . 
                     (!empty($openaiKey) ? "✅ 設定済み" : "❌ 未設定") . "</li>\n";
                
                // WordPress URL
                $wpUrl = $config['wordpress']['base_url'] ?? '';
                echo "<li>WordPress URL: " . 
                     (!empty($wpUrl) ? "✅ {$wpUrl}" : "❌ 未設定") . "</li>\n";
                
                // WordPress Credentials
                $wpUser = $config['wordpress']['username'] ?? '';
                $wpPass = $config['wordpress']['password'] ?? '';
                echo "<li>WordPress認証: " . 
                     (!empty($wpUser) && !empty($wpPass) ? "✅ 設定済み" : "❌ 未設定") . "</li>\n";
                
                echo "</ul>\n";
                
            } catch (Exception $e) {
                echo "<p>❌ 設定ファイルエラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            }
        }
    }
    
    private function testConnections() {
        echo "<h2>3. 接続テスト</h2>\n";
        
        try {
            $config = require __DIR__ . '/config.php';
            
            // OpenAI Test
            echo "<h3>OpenAI API テスト</h3>\n";
            try {
                require_once __DIR__ . '/OpenAIClient.php';
                require_once __DIR__ . '/Logger.php';
                
                $logger = new Logger('info');
                $openai = new OpenAIClient($config, $logger);
                $gpts = $openai->getAvailableGPTs();
                
                echo "<p>✅ OpenAI接続成功</p>\n";
                echo "<p>利用可能なGPT:</p>\n<ul>\n";
                foreach ($gpts as $key => $gpt) {
                    echo "<li>{$key}: {$gpt['name']}</li>\n";
                }
                echo "</ul>\n";
                
            } catch (Exception $e) {
                echo "<p>❌ OpenAI接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            }
            
            // WordPress Test
            echo "<h3>WordPress API テスト</h3>\n";
            try {
                require_once __DIR__ . '/WordPressClient.php';
                
                $wordpress = new WordPressClient($config, $logger);
                $wordpress->testConnection();
                
                echo "<p>✅ WordPress接続成功</p>\n";
                
                // Get categories
                try {
                    $categories = $wordpress->getCategories();
                    echo "<p>利用可能なカテゴリ: " . count($categories) . "個</p>\n";
                } catch (Exception $e) {
                    echo "<p>⚠️ カテゴリ取得エラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                }
                
            } catch (Exception $e) {
                echo "<p>❌ WordPress接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ 設定読み込みエラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    private function displaySetupInstructions() {
        echo "<h2>4. セットアップ手順</h2>\n";
        
        echo "<h3>必要な設定</h3>\n";
        echo "<ol>\n";
        echo "<li><strong>OpenAI API キーの取得</strong><br>\n";
        echo "   <a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI Platform</a>でAPI keyを取得</li>\n";
        
        echo "<li><strong>WordPress設定</strong><br>\n";
        echo "   WordPressでREST APIを有効化し、アプリケーションパスワードを作成</li>\n";
        
        echo "<li><strong>設定ファイルの編集</strong><br>\n";
        echo "   <code>config.php</code>を編集して各種設定を入力</li>\n";
        
        echo "<li><strong>ファイル権限の設定</strong><br>\n";
        echo "   Webサーバーがファイルを読み書きできるよう権限を設定</li>\n";
        
        echo "</ol>\n";
        
        echo "<h3>設定例</h3>\n";
        echo "<pre><code>\n";
        echo "// config.php の設定例\n";
        echo "'openai' => [\n";
        echo "    'api_key' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxx',\n";
        echo "],\n";
        echo "'wordpress' => [\n";
        echo "    'base_url' => 'https://yoursite.com',\n";
        echo "    'username' => 'your_username',\n";
        echo "    'password' => 'your_application_password',\n";
        echo "],\n";
        echo "</code></pre>\n";
        
        echo "<h3>使用方法</h3>\n";
        echo "<ol>\n";
        echo "<li>ブラウザで <code>index.html</code> を開く</li>\n";
        echo "<li>使用したいカスタムGPTを選択</li>\n";
        echo "<li>プロンプトを入力して記事生成を実行</li>\n";
        echo "<li>生成された記事がWordPressの下書きとして自動保存</li>\n";
        echo "</ol>\n";
        
        echo "<h3>トラブルシューティング</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>CORS エラー</strong>: ローカル開発の場合はWebサーバーを使用</li>\n";
        echo "<li><strong>API エラー</strong>: ログファイルでエラー詳細を確認</li>\n";
        echo "<li><strong>権限エラー</strong>: ファイル・ディレクトリの権限を確認</li>\n";
        echo "</ul>\n";
        
        echo "<p><a href='index.html'>🚀 システムを開始</a></p>\n";
    }
}

// Add some basic styling
echo "<style>\n";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }\n";
echo "h1 { color: #333; border-bottom: 2px solid #667eea; }\n";
echo "h2 { color: #667eea; }\n";
echo "h3 { color: #555; }\n";
echo "code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }\n";
echo "pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }\n";
echo "ul, ol { margin-left: 20px; }\n";
echo "a { color: #667eea; text-decoration: none; }\n";
echo "a:hover { text-decoration: underline; }\n";
echo "</style>\n";

// Run setup
$setup = new SystemSetup();
$setup->run();
?>