class AutoSave {
    constructor(options = {}) {
        this.interval = options.interval || 30000;
        this.endpoint = options.endpoint || '/wp-admin/admin-ajax.php';
        this.action = options.action || 'auto_save_draft';
        this.nonce = options.nonce || '';
        this.postId = options.postId || 0;
        this.timer = null;
        this.lastSavedContent = '';
        this.isTyping = false;
        this.typingTimer = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startAutoSave();
    }
    
    bindEvents() {
        const titleField = document.getElementById('title');
        const contentField = document.getElementById('content');
        
        if (titleField) {
            titleField.addEventListener('input', () => this.onContentChange());
            titleField.addEventListener('keydown', () => this.onTyping());
        }
        
        if (contentField) {
            contentField.addEventListener('input', () => this.onContentChange());
            contentField.addEventListener('keydown', () => this.onTyping());
        }
        
        window.addEventListener('beforeunload', () => this.saveNow());
    }
    
    onTyping() {
        this.isTyping = true;
        clearTimeout(this.typingTimer);
        
        this.typingTimer = setTimeout(() => {
            this.isTyping = false;
        }, 1000);
    }
    
    onContentChange() {
        if (!this.isTyping) {
            this.saveNow();
        }
    }
    
    startAutoSave() {
        this.timer = setInterval(() => {
            if (!this.isTyping) {
                this.saveNow();
            }
        }, this.interval);
    }
    
    stopAutoSave() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
    
    getCurrentContent() {
        const title = document.getElementById('title')?.value || '';
        const content = document.getElementById('content')?.value || '';
        return { title, content };
    }
    
    hasContentChanged() {
        const currentContent = JSON.stringify(this.getCurrentContent());
        return currentContent !== this.lastSavedContent;
    }
    
    async saveNow() {
        if (!this.hasContentChanged()) {
            return;
        }
        
        const content = this.getCurrentContent();
        
        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: this.action,
                    nonce: this.nonce,
                    post_id: this.postId,
                    title: content.title,
                    content: content.content,
                    timestamp: Date.now()
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.lastSavedContent = JSON.stringify(content);
                this.updateSaveStatus('保存されました', 'success');
                
                if (result.data.post_id && !this.postId) {
                    this.postId = result.data.post_id;
                }
            } else {
                this.updateSaveStatus('保存に失敗しました', 'error');
                console.error('Auto-save failed:', result.data);
            }
        } catch (error) {
            this.updateSaveStatus('保存エラー', 'error');
            console.error('Auto-save error:', error);
        }
    }
    
    updateSaveStatus(message, type) {
        let statusElement = document.getElementById('auto-save-status');
        
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.id = 'auto-save-status';
            statusElement.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 9999;
                transition: opacity 0.3s ease;
            `;
            document.body.appendChild(statusElement);
        }
        
        statusElement.textContent = message;
        statusElement.className = `auto-save-status ${type}`;
        
        if (type === 'success') {
            statusElement.style.cssText += 'background: #4caf50; color: white;';
        } else if (type === 'error') {
            statusElement.style.cssText += 'background: #f44336; color: white;';
        }
        
        statusElement.style.opacity = '1';
        
        setTimeout(() => {
            statusElement.style.opacity = '0';
        }, 3000);
    }
    
    destroy() {
        this.stopAutoSave();
        
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }
        
        const statusElement = document.getElementById('auto-save-status');
        if (statusElement) {
            statusElement.remove();
        }
    }
}

window.AutoSave = AutoSave;