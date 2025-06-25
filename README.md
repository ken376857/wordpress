# ChatGPT to WordPress - AI記事生成システム

カスタムGPTを活用してWordPressに自動的に下書きを保存するシステムです。複数のカスタムGPTから記事を生成し、WordPress REST APIを通じて自動保存します。

## ✨ 主な機能

### 🤖 カスタムGPT統合
- **複数のカスタムGPT対応**（ブログ記事作成、ニュース記事作成など）
- **柔軟なプロンプト設定**
- **トークン数・Temperature調整可能**
- **バッチ処理対応**

### 📝 自動WordPress保存
- **REST API経由での自動保存**
- **下書き・非公開・公開状態選択**
- **カテゴリ・タグ自動設定**
- **AIメタデータ付与**

### 🎨 直感的なUI
- **モダンなWebインターフェース**
- **リアルタイム進捗表示**
- **結果プレビュー**
- **エラーハンドリング**

## 📋 システム要件

- **PHP 7.4以上**
- **cURL拡張**
- **JSON拡張**
- **Webサーバー（Apache/Nginx）**
- **WordPress 5.0以上（REST API有効）**
- **OpenAI API アカウント**

## 🚀 インストール手順

### 1. ファイルのダウンロード

```bash
git clone https://github.com/ken376857/wordpress.git
cd wordpress
```

### 2. 設定ファイルの準備

```bash
cp .env.example .env
```

`.env`ファイルを編集して必要な設定を入力：

```env
OPENAI_API_KEY=sk-your-openai-api-key
WP_BASE_URL=https://yourwordpress.com
WP_USERNAME=your_username
WP_PASSWORD=your_application_password
```

### 3. WordPress設定

#### アプリケーションパスワードの作成
1. WordPress管理画面 → ユーザー → プロフィール
2. 「アプリケーションパスワード」セクション
3. 新しいパスワードを生成
4. 生成されたパスワードを`.env`に設定

#### REST API の有効化確認
```
https://yourwordpress.com/wp-json/wp/v2/posts
```
上記URLにアクセスして投稿一覧が表示されることを確認。

### 4. セットアップの実行

ブラウザで`setup.php`にアクセスしてシステムチェック：

```
http://yourserver.com/setup.php
```

### 5. システム開始

```
http://yourserver.com/index.html
```

## 🛠 設定オプション

### config.php設定

```php
return [
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-4',
        'max_tokens' => 2000,
        'temperature' => 0.7
    ],
    'wordpress' => [
        'base_url' => getenv('WP_BASE_URL'),
        'username' => getenv('WP_USERNAME'),
        'password' => getenv('WP_PASSWORD')
    ],
    'custom_gpts' => [
        'gpt1' => [
            'name' => 'ブログ記事作成GPT',
            'system_prompt' => 'SEOを意識した魅力的なブログ記事を作成してください。',
            'enabled' => true
        ]
    ]
];
```

### カスタムGPTの追加

新しいカスタムGPTを追加するには：

```php
'gpt3' => [
    'name' => '技術記事作成GPT',
    'description' => '技術的な内容を分かりやすく解説',
    'system_prompt' => '技術的な内容を初心者にも分かりやすく解説してください。',
    'enabled' => true
]
```

## 📊 API エンドポイント

### 記事生成
```
POST /api.php
Content-Type: application/json

{
  "action": "generate_content",
  "gpt_type": "gpt1",
  "prompt": "AIについてのブログ記事を作成",
  "category": 2,
  "tags": "AI,技術,ブログ",
  "max_tokens": 2000,
  "temperature": 0.7,
  "post_status": "draft"
}
```

### 接続テスト
```
GET /api.php?action=test_connection
```

### 下書き一覧取得
```
GET /api.php?action=get_drafts&limit=10&offset=0
```

### バッチ生成
```
POST /api.php
Content-Type: application/json

{
  "action": "batch_generate",
  "prompts": ["記事1のプロンプト", "記事2のプロンプト"],
  "gpt_types": ["gpt1", "gpt2"]
}
```

## 📁 ファイル構成

```
wordpress/
├── index.html              # メインUI
├── api.php                 # REST APIエンドポイント
├── config.php              # 設定ファイル
├── setup.php               # セットアップツール
├── .env.example            # 環境変数テンプレート
├── OpenAIClient.php        # OpenAI API クライアント
├── WordPressClient.php     # WordPress API クライアント
├── Logger.php              # ログ機能
└── README.md               # このファイル
```

## 🔧 使用方法

### 基本的な使用手順

1. **GPTの選択**
   - ブログ記事作成GPTまたはニュース記事作成GPTを選択

2. **プロンプトの入力**
   - 生成したい記事の内容を詳細に記述
   - プロンプト例を参考に具体的に記載

3. **設定の調整**
   - カテゴリ、タグ、投稿ステータスを設定
   - 必要に応じてトークン数やTemperatureを調整

4. **記事生成・保存**
   - 「記事を生成してWordPressに保存」ボタンをクリック
   - 進捗をリアルタイムで確認

5. **結果の確認**
   - 生成された記事のプレビューを確認
   - WordPress管理画面で編集や公開

### 高度な使用方法

#### バッチ処理
複数の記事を一度に生成：

```javascript
fetch('api.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    action: 'batch_generate',
    prompts: [
      'AIトレンド2024について',
      'リモートワークのコツ',
      '健康的な食生活ガイド'
    ],
    gpt_types: ['gpt1', 'gpt1', 'gpt2']
  })
})
```

#### カスタマイズ
独自のカスタムGPTを追加してより特化した記事生成が可能。

## 🔍 トラブルシューティング

### よくある問題

#### 1. CORS エラー
```
Access to fetch at 'api.php' from origin 'null' has been blocked
```
**解決方法**: ローカルWebサーバーを使用
```bash
php -S localhost:8000
```

#### 2. OpenAI API エラー
```
OpenAI API error: Incorrect API key provided
```
**解決方法**: 
- API keyが正しく設定されているか確認
- OpenAIアカウントの残高を確認

#### 3. WordPress接続エラー
```
WordPress API error: Invalid username or password
```
**解決方法**:
- アプリケーションパスワードを再生成
- WordPress URL が正しいか確認
- REST API が有効か確認

#### 4. 権限エラー
```
Permission denied
```
**解決方法**:
```bash
chmod 755 /path/to/wordpress/
chmod 666 /path/to/wordpress/*.php
```

### デバッグ方法

#### ログの確認
```php
// Logger を使用
$logger = new Logger('debug');
$logger->info('Debug message', ['data' => $someData]);

// ログファイルの場所
/tmp/chatgpt-wordpress-logs/app-YYYY-MM-DD.log
```

#### API レスポンスの確認
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"test_connection"}'
```

## 🔒 セキュリティ

### 推奨設定

1. **API キーの保護**
   - `.env`ファイルをWebルートから除外
   - 環境変数での管理を推奨

2. **アクセス制限**
   - 特定IPからのアクセスのみ許可
   - Basic認証の設定

3. **レート制限**
   - API呼び出し回数の制限
   - 不正利用の防止

### .htaccess 設定例

```apache
# .env ファイルへのアクセス拒否
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# ログファイルへのアクセス拒否
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

## 🧪 テスト

### システムテスト
```bash
# セットアップの実行
php setup.php

# API テスト
curl -X GET "http://localhost:8000/api.php?action=test_connection"
```

### 単体テスト
```php
// OpenAI クライアントテスト
$openai = new OpenAIClient($config);
$result = $openai->generateContent('テストプロンプト', 'gpt1');

// WordPress クライアントテスト  
$wordpress = new WordPressClient($config);
$wordpress->testConnection();
```

## 📈 パフォーマンス最適化

### 推奨設定

1. **キャッシュの活用**
   - 生成結果の一時キャッシュ
   - API レスポンスのキャッシュ

2. **バッチ処理の最適化**
   - 適切な遅延設定
   - 並列処理の制限

3. **リソース管理**
   - メモリ使用量の監視
   - タイムアウト設定の調整

## 🤝 コントリビューション

プルリクエストやイシューの報告を歓迎します。

1. フォークしてください
2. フィーチャーブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. コミット (`git commit -m 'Add some AmazingFeature'`)
4. プッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを開いてください

## 📄 ライセンス

MIT License

## 👨‍💻 作成者

**Ken Tanabe**
- GitHub: [@ken376857](https://github.com/ken376857)

## 🔄 更新履歴

### v1.0.0 (2024-06-25)
- 初回リリース
- 基本的なChatGPT → WordPress統合機能
- 2つのカスタムGPT対応
- バッチ処理機能
- セットアップツール

### 今後の予定
- [ ] より多くのカスタムGPT対応
- [ ] 画像生成機能
- [ ] スケジュール投稿
- [ ] WordPress プラグイン版
- [ ] 多言語対応