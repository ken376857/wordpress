# WordPress 自動下書き保存システム

WordPressに高度な自動下書き保存機能を追加するシステムです。リアルタイムで記事の下書きを保存し、データの損失を防ぎます。

## 主な機能

### 🔄 自動保存機能
- **30秒間隔での自動保存**（設定可能）
- **入力停止時の即座保存**
- **ページ離脱時の緊急保存**
- **セッション管理による継続保存**

### 💾 下書き管理
- **独立したデータベーステーブル**での管理
- **セッションIDによる識別**
- **ユーザーIDとの関連付け**
- **自動クリーンアップ機能**（7日間）

### 🎨 ユーザーインターフェース
- **モダンなエディターUI**
- **リアルタイム保存状況表示**
- **タイピングインジケーター**
- **ワンクリック復元機能**

## システム構成

```
wordpress/
├── auto-save.js              # フロントエンド自動保存スクリプト
├── auto-save-handler.php     # バックエンド処理（単体版）
├── wp-auto-save-plugin.php   # WordPress プラグイン版
├── wp-editor.html            # スタンドアロンエディター
└── README.md                 # このファイル
```

## インストール方法

### プラグインとして使用する場合

1. **ファイルのアップロード**
   ```bash
   # WordPressのプラグインディレクトリにファイルをコピー
   cp wp-auto-save-plugin.php /path/to/wordpress/wp-content/plugins/
   cp auto-save.js /path/to/wordpress/wp-content/plugins/
   ```

2. **プラグインの有効化**
   - WordPress管理画面の「プラグイン」メニューから有効化
   - データベーステーブルが自動作成されます

3. **設定の調整**
   - 「設定」→「投稿設定」から自動保存間隔を調整可能

### スタンドアロンとして使用する場合

1. **ファイルの配置**
   ```bash
   # Webサーバーのドキュメントルートに配置
   cp *.js *.php *.html /path/to/webserver/document-root/
   ```

2. **エディターへのアクセス**
   ```
   http://your-domain.com/wp-editor.html
   ```

## 技術仕様

### データベーステーブル

```sql
CREATE TABLE wp_auto_save_drafts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    user_id bigint(20) unsigned DEFAULT 0,
    post_id bigint(20) unsigned DEFAULT 0,
    title text,
    content longtext,
    post_type varchar(20) DEFAULT 'post',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY user_id (user_id),
    KEY updated_at (updated_at)
);
```

### APIエンドポイント

#### 下書き保存
```
POST /wp-admin/admin-ajax.php
action: auto_save_draft
nonce: [セキュリティトークン]
post_id: [投稿ID]
title: [タイトル]
content: [本文]
```

#### 下書き取得
```
POST /wp-admin/admin-ajax.php
action: get_draft_content
nonce: [セキュリティトークン]
session_id: [セッションID]
post_id: [投稿ID]
```

## 設定オプション

### WordPress設定

- **auto_save_interval**: 自動保存間隔（秒）
  - デフォルト: 30秒
  - 範囲: 5-300秒

### JavaScript設定

```javascript
new AutoSave({
    endpoint: '/wp-admin/admin-ajax.php',  // 保存エンドポイント
    nonce: 'security-token',               // セキュリティトークン
    postId: 123,                          // 投稿ID
    interval: 30000,                      // 保存間隔（ミリ秒）
    action: 'auto_save_draft'             // アクション名
});
```

## セキュリティ機能

- **WordPress nonce認証**
- **入力値のサニタイゼーション**
- **SQLインジェクション対策**
- **セッション管理**
- **権限チェック**

## パフォーマンス最適化

- **タイピング中の保存停止**
- **変更検知による無駄な通信削減**
- **自動クリーンアップによるデータベース最適化**
- **インデックス最適化**

## 使用方法

### 基本的な使用

1. **エディターページを開く**
2. **タイトルと本文を入力**
3. **自動的に30秒ごとに保存される**
4. **入力停止時にも即座に保存**

### 下書きの復元

1. **「下書きを復元」ボタンをクリック**
2. **保存された下書きが自動的に読み込まれる**
3. **確認ダイアログで復元を選択**

### 手動保存

- **「手動保存」ボタン**でいつでも保存可能
- **Ctrl+S**でも保存可能（ブラウザ設定による）

## トラブルシューティング

### よくある問題

1. **自動保存が動作しない**
   - JavaScriptエラーの確認
   - ネットワーク接続の確認
   - nonce認証の確認

2. **下書きが復元されない**
   - セッションIDの確認
   - データベーステーブルの存在確認
   - ブラウザのLocalStorageの確認

3. **パフォーマンスの問題**
   - 自動保存間隔の調整
   - データベースのインデックス確認
   - 古い下書きの削除

### デバッグ方法

```javascript
// ブラウザコンソールでの確認
console.log(window.wpAutoSaveInstance);
console.log(localStorage.getItem('wp_draft_title'));
```

```php
// PHPでのデバッグ
error_log('Auto save debug: ' . print_r($_POST, true));
```

## ライセンス

GPL v2 or later

## 作成者

Ken Tanabe

## 更新履歴

- **v1.0.0** (2024-06-25): 初回リリース
  - 基本的な自動保存機能
  - WordPress プラグイン対応
  - スタンドアロンエディター
  - セキュリティ機能実装