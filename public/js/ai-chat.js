(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const config = window.aiChatConfig;
    if (!config) return;

    const sidebar = document.getElementById('ai-chat-sidebar');
    const sidebarToggle = document.getElementById('ai-sidebar-toggle');
    const historyList = document.getElementById('ai-chat-history');
    const messagesArea = document.getElementById('ai-chat-messages');
    const messagesList = document.getElementById('ai-messages-list');
    const emptyState = document.getElementById('ai-chat-empty');
    const form = document.getElementById('ai-chat-form');
    const textarea = document.getElementById('ai-chat-textarea');
    const sendBtn = document.getElementById('ai-send-btn');
    const newChatBtn = document.getElementById('ai-new-chat-btn');
    const newChatBtn2 = document.getElementById('ai-new-chat-btn-2');
    const clearChatBtn = document.getElementById('ai-clear-chat-btn');

    let activeConversationId = config.activeConversationId;
    let chartCounter = 0;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    if (window.marked) {
      marked.setOptions({ breaks: true, gfm: true });
    }

    /* ---------- helpers ---------- */
    function urlFor(template, id) {
      return template.replace('__ID__', id);
    }

    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    function renderMarkdown(text) {
      if (!window.marked) return `<p>${escapeHtml(text)}</p>`;
      const html = marked.parse(text || '');
      return window.DOMPurify ? DOMPurify.sanitize(html) : html;
    }

    function scrollToBottom() {
      messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function toggleEmptyState() {
      emptyState.classList.toggle('d-none', messagesList.children.length > 0);
    }

    function fetchJson(url, options) {
      return fetch(url, Object.assign({
        headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, (options && options.headers) || {}),
      }, options)).then(r => r.json().then(data => ({ ok: r.ok, data })));
    }

    /* ---------- widgets ---------- */
    function renderWidget(widget) {
      if (!widget) return null;

      const wrap = document.createElement('div');
      wrap.className = 'ai-widget';

      if (widget.type === 'stats') {
        const grid = document.createElement('div');
        grid.className = 'ai-widget-stats';
        widget.items.forEach(item => {
          const card = document.createElement('div');
          card.className = 'ai-widget-stat-card';
          card.innerHTML = `
            <div class="icon-badge bg-${item.tone}-subtle text-${item.tone} mx-auto"><i class="bi ${item.icon}"></i></div>
            <div class="label">${escapeHtml(item.label)}</div>
            <div class="value num-tabular">${escapeHtml(item.value)}</div>
          `;
          grid.appendChild(card);
        });
        wrap.appendChild(grid);
        return wrap;
      }

      if (widget.type === 'chart') {
        if (widget.title) {
          const title = document.createElement('div');
          title.className = 'ai-widget-title';
          title.textContent = widget.title;
          wrap.appendChild(title);
        }
        const chartWrap = document.createElement('div');
        chartWrap.className = 'ai-widget-chart-wrap';
        const canvas = document.createElement('canvas');
        canvas.id = `ai-widget-chart-${++chartCounter}`;
        chartWrap.appendChild(canvas);
        wrap.appendChild(chartWrap);

        requestAnimationFrame(() => {
          if (!window.Chart) return;
          const colors = ['#146C43', '#B8862E', '#2563A8', '#C23B3B', '#66716B', '#8F6822'];
          new Chart(canvas, {
            type: widget.chart_type || 'bar',
            data: {
              labels: widget.labels,
              datasets: [{
                label: widget.title || 'Value',
                data: widget.data,
                backgroundColor: widget.chart_type === 'pie' ? colors : 'rgba(20, 108, 67, 0.55)',
                borderColor: widget.chart_type === 'line' ? '#146C43' : colors,
                borderWidth: widget.chart_type === 'pie' ? 2 : 1,
                fill: widget.chart_type === 'line',
                tension: 0.35,
              }],
            },
            options: {
              maintainAspectRatio: false,
              plugins: { legend: { display: widget.chart_type === 'pie', position: 'bottom' } },
              scales: widget.chart_type === 'pie' ? {} : { y: { beginAtZero: true } },
            },
          });
        });
        return wrap;
      }

      if (widget.type === 'products') {
        return renderTableWidget(wrap, widget, ['Product', 'SKU', 'Stock', 'Reorder Level', 'Price', 'Status'], row => `
          <td><i class="bi bi-box-seam me-1 text-muted"></i>${escapeHtml(row.name)}</td>
          <td>${escapeHtml(row.sku)}</td>
          <td>${row.stock}</td>
          <td>${row.reorder_level}</td>
          <td>${escapeHtml(row.price)}</td>
          <td><span class="badge text-bg-${row.status_tone}">${escapeHtml(row.status)}</span></td>
        `);
      }

      if (widget.type === 'purchase_orders') {
        return renderTableWidget(wrap, widget, ['Supplier', 'Order Date', 'Total', 'Status'], row => `
          <td>${escapeHtml(row.supplier)}</td>
          <td>${escapeHtml(row.order_date)}</td>
          <td>${escapeHtml(row.total)}</td>
          <td><span class="badge text-bg-warning">${escapeHtml(row.status)}</span></td>
        `);
      }

      if (widget.type === 'customers') {
        return renderTableWidget(wrap, widget, ['Name', 'Phone', 'Total Spent', 'Points'], row => `
          <td>${escapeHtml(row.name)}</td>
          <td>${escapeHtml(row.phone || '—')}</td>
          <td>${escapeHtml(row.total_spent)}</td>
          <td><i class="bi bi-star-fill text-gold"></i> ${row.points_balance}</td>
        `);
      }

      return null;
    }

    function renderTableWidget(wrap, widget, columns, rowHtml) {
      if (widget.rows.length === 0) return null;

      if (widget.title) {
        const title = document.createElement('div');
        title.className = 'ai-widget-title';
        title.textContent = widget.title;
        wrap.appendChild(title);
      }

      const tableWrap = document.createElement('div');
      tableWrap.className = 'ai-widget-table-wrap table-responsive';
      tableWrap.innerHTML = `
        <table class="table mb-0">
          <thead><tr>${columns.map(c => `<th>${escapeHtml(c)}</th>`).join('')}</tr></thead>
          <tbody>${widget.rows.map(row => `<tr>${rowHtml(row)}</tr>`).join('')}</tbody>
        </table>
      `;
      wrap.appendChild(tableWrap);
      return wrap;
    }

    /* ---------- messages ---------- */
    function appendMessage(message) {
      const userRow = document.createElement('div');
      userRow.className = 'ai-message-row user';
      userRow.innerHTML = `
        <div class="ai-avatar user">${escapeHtml((document.querySelector('.pos-topbar .avatar')?.textContent || 'U').trim().slice(0, 2))}</div>
        <div class="ai-bubble-col">
          <div class="ai-bubble user"></div>
          <div class="ai-message-meta"><span>${escapeHtml(message.created_at)}</span></div>
        </div>
      `;
      userRow.querySelector('.ai-bubble').textContent = message.query;
      messagesList.appendChild(userRow);

      const aiRow = document.createElement('div');
      aiRow.className = 'ai-message-row assistant';
      aiRow.dataset.logId = message.id;
      aiRow.dataset.query = message.query;
      aiRow.innerHTML = `
        <div class="ai-avatar assistant">🤖</div>
        <div class="ai-bubble-col">
          <div class="ai-bubble assistant"></div>
          <div class="ai-message-meta">
            <span>${escapeHtml(message.created_at)}</span>
            <div class="ai-message-actions">
              <button type="button" class="ai-msg-action-btn ai-copy-btn" title="Copy"><i class="bi bi-clipboard"></i></button>
              <button type="button" class="ai-msg-action-btn ai-regenerate-btn" title="Regenerate"><i class="bi bi-arrow-repeat"></i></button>
              <button type="button" class="ai-msg-action-btn ai-like-btn" title="Like"><i class="bi bi-hand-thumbs-up"></i></button>
              <button type="button" class="ai-msg-action-btn ai-dislike-btn" title="Dislike"><i class="bi bi-hand-thumbs-down"></i></button>
            </div>
          </div>
        </div>
      `;
      aiRow.querySelector('.ai-bubble').innerHTML = renderMarkdown(message.response);
      applyFeedbackState(aiRow, message.feedback);
      messagesList.appendChild(aiRow);

      const widgetEl = renderWidget(message.widget);
      if (widgetEl) {
        aiRow.querySelector('.ai-bubble-col').appendChild(widgetEl);
      }

      wireMessageActions(aiRow, message);
      toggleEmptyState();
    }

    function applyFeedbackState(row, feedback) {
      row.querySelector('.ai-like-btn').classList.toggle('active', feedback === 'like');
      row.querySelector('.ai-like-btn').classList.toggle('like', feedback === 'like');
      row.querySelector('.ai-dislike-btn').classList.toggle('active', feedback === 'dislike');
      row.querySelector('.ai-dislike-btn').classList.toggle('dislike', feedback === 'dislike');
    }

    function wireMessageActions(row, message) {
      row.querySelector('.ai-copy-btn').addEventListener('click', () => {
        navigator.clipboard?.writeText(message.response).then(() => {
          window.posToast && window.posToast('Copied to clipboard.', 'success');
        });
      });

      row.querySelector('.ai-regenerate-btn').addEventListener('click', () => {
        sendMessage(row.dataset.query, true);
      });

      row.querySelector('.ai-like-btn').addEventListener('click', () => {
        const isActive = row.querySelector('.ai-like-btn').classList.contains('active');
        setFeedback(row, message.id, isActive ? null : 'like');
      });

      row.querySelector('.ai-dislike-btn').addEventListener('click', () => {
        const isActive = row.querySelector('.ai-dislike-btn').classList.contains('active');
        setFeedback(row, message.id, isActive ? null : 'dislike');
      });
    }

    function setFeedback(row, logId, feedback) {
      applyFeedbackState(row, feedback);
      fetchJson(urlFor(config.feedbackUrlTemplate, logId), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ feedback }),
      }).catch(() => {});
    }

    function showTypingIndicator() {
      const row = document.createElement('div');
      row.className = 'ai-message-row assistant';
      row.id = 'ai-typing-row';
      row.innerHTML = `
        <div class="ai-avatar assistant">🤖</div>
        <div class="ai-bubble-col">
          <div class="ai-bubble assistant"><span class="ai-typing-dots"><span></span><span></span><span></span></span></div>
        </div>
      `;
      messagesList.appendChild(row);
      scrollToBottom();
    }

    function hideTypingIndicator() {
      document.getElementById('ai-typing-row')?.remove();
    }

    /* ---------- sending ---------- */
    function sendMessage(text, isRegenerate) {
      text = (text || '').trim();
      if (!text) return;

      if (!isRegenerate) {
        emptyState.classList.add('d-none');
      }

      sendBtn.disabled = true;
      showTypingIndicator();

      fetchJson(config.askUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, conversation_id: activeConversationId }),
      }).then(({ ok, data }) => {
        hideTypingIndicator();
        sendBtn.disabled = textarea.value.trim().length === 0;

        if (!ok) {
          window.posToast ? window.posToast('Something went wrong. Please try again.', 'danger') : alert('Something went wrong.');
          return;
        }

        const isNewConversation = activeConversationId !== data.conversation_id;
        activeConversationId = data.conversation_id;

        appendMessage(data.message);
        scrollToBottom();

        if (isNewConversation) {
          addOrUpdateSidebarConversation(data.conversation_id, data.conversation_title);
        } else {
          updateSidebarTitle(data.conversation_id, data.conversation_title);
        }
      }).catch(() => {
        hideTypingIndicator();
        sendBtn.disabled = false;
        window.posToast ? window.posToast('Network error. Please try again.', 'danger') : alert('Network error.');
      });
    }

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = textarea.value;
      textarea.value = '';
      textarea.style.height = 'auto';
      sendBtn.disabled = true;
      sendMessage(text);
    });

    textarea.addEventListener('input', () => {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
      sendBtn.disabled = textarea.value.trim().length === 0;
    });

    textarea.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (textarea.value.trim().length > 0) {
          form.requestSubmit();
        }
      }
    });

    /* ---------- suggestion chips ---------- */
    document.querySelectorAll('.ai-chip').forEach(chip => {
      chip.addEventListener('click', () => sendMessage(chip.dataset.text));
    });

    /* ---------- sidebar: conversation switching ---------- */
    function setActiveSidebarItem(id) {
      historyList.querySelectorAll('.ai-history-item').forEach(item => {
        item.classList.toggle('active', String(item.dataset.id) === String(id));
      });
    }

    function loadConversation(id) {
      fetchJson(urlFor(config.switchUrlTemplate, id)).then(({ ok, data }) => {
        if (!ok) return;
        activeConversationId = data.conversation_id;
        messagesList.innerHTML = '';
        data.messages.forEach(appendMessage);
        toggleEmptyState();
        setActiveSidebarItem(id);
        sidebar.classList.remove('show');
        scrollToBottom();
      });
    }

    historyList.addEventListener('click', (e) => {
      const renameBtn = e.target.closest('.ai-rename-btn');
      const deleteBtn = e.target.closest('.ai-delete-btn');
      const item = e.target.closest('.ai-history-item');
      if (!item) return;
      const id = item.dataset.id;

      if (renameBtn) {
        e.stopPropagation();
        startRename(item, id);
        return;
      }

      if (deleteBtn) {
        e.stopPropagation();
        deleteConversation(item, id);
        return;
      }

      loadConversation(id);
    });

    function startRename(item, id) {
      const titleEl = item.querySelector('.ai-history-title');
      const original = titleEl.textContent;
      titleEl.setAttribute('contenteditable', 'true');
      titleEl.focus();
      document.execCommand('selectAll', false, null);

      function finish(save) {
        titleEl.removeAttribute('contenteditable');
        const newTitle = titleEl.textContent.trim();
        if (save && newTitle && newTitle !== original) {
          fetchJson(urlFor(config.renameUrlTemplate, id), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title: newTitle }),
          }).then(({ ok, data }) => {
            titleEl.textContent = ok ? data.title : original;
          });
        } else {
          titleEl.textContent = original;
        }
        titleEl.removeEventListener('blur', onBlur);
        titleEl.removeEventListener('keydown', onKeydown);
      }

      function onBlur() { finish(true); }
      function onKeydown(e) {
        if (e.key === 'Enter') { e.preventDefault(); finish(true); }
        if (e.key === 'Escape') { e.preventDefault(); finish(false); }
      }

      titleEl.addEventListener('blur', onBlur);
      titleEl.addEventListener('keydown', onKeydown);
    }

    function deleteConversation(item, id) {
      if (!confirm('Delete this conversation? This cannot be undone.')) return;

      fetchJson(urlFor(config.deleteUrlTemplate, id), { method: 'DELETE' }).then(({ ok }) => {
        if (!ok) return;
        const wasActive = String(activeConversationId) === String(id);
        item.remove();

        if (wasActive) {
          activeConversationId = null;
          messagesList.innerHTML = '';
          toggleEmptyState();
        }
      });
    }

    function addOrUpdateSidebarConversation(id, title) {
      const existing = historyList.querySelector(`.ai-history-item[data-id="${id}"]`);
      if (existing) {
        updateSidebarTitle(id, title);
        setActiveSidebarItem(id);
        return;
      }

      let todayLabel = Array.from(historyList.querySelectorAll('.ai-history-group-label')).find(el => el.textContent === 'Today');
      if (!todayLabel) {
        todayLabel = document.createElement('div');
        todayLabel.className = 'ai-history-group-label';
        todayLabel.textContent = 'Today';
        historyList.insertBefore(todayLabel, historyList.firstChild);
      }

      const noConversationsMsg = historyList.querySelector(':scope > .text-muted');
      noConversationsMsg?.remove();

      const item = document.createElement('div');
      item.className = 'ai-history-item active';
      item.dataset.id = id;
      item.innerHTML = `
        <i class="bi bi-chat-left-text ai-history-icon"></i>
        <span class="ai-history-title" data-id="${id}">${escapeHtml(title || 'New Chat')}</span>
        <div class="ai-history-actions">
          <button type="button" class="ai-history-action-btn ai-rename-btn" title="Rename"><i class="bi bi-pencil"></i></button>
          <button type="button" class="ai-history-action-btn ai-delete-btn" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      `;
      todayLabel.after(item);
      setActiveSidebarItem(id);
    }

    function updateSidebarTitle(id, title) {
      const el = historyList.querySelector(`.ai-history-item[data-id="${id}"] .ai-history-title`);
      if (el) el.textContent = title || 'New Chat';
    }

    /* ---------- new chat / clear chat ---------- */
    function startNewChat() {
      activeConversationId = null;
      messagesList.innerHTML = '';
      toggleEmptyState();
      setActiveSidebarItem(null);
      textarea.focus();
    }

    newChatBtn.addEventListener('click', startNewChat);
    newChatBtn2.addEventListener('click', startNewChat);

    clearChatBtn.addEventListener('click', () => {
      if (!activeConversationId) {
        startNewChat();
        return;
      }
      if (!confirm('Clear all messages in this chat?')) return;

      fetchJson(urlFor(config.clearUrlTemplate, activeConversationId), { method: 'DELETE' }).then(({ ok }) => {
        if (!ok) return;
        messagesList.innerHTML = '';
        toggleEmptyState();
        updateSidebarTitle(activeConversationId, 'New Chat');
      });
    });

    /* ---------- mobile sidebar toggle ---------- */
    sidebarToggle?.addEventListener('click', () => sidebar.classList.toggle('show'));

    /* ---------- initial render ---------- */
    config.initialMessages.forEach(appendMessage);
    scrollToBottom();
  });
})();
