(() => {
  const ideas = window.DATE_IDEAS || [];
  const button = document.querySelector('#shuffle-button');
  const STORAGE_MODE = 'afterdark.remember';
  const STORAGE_USED = 'afterdark.used';

  if (button) {
    const stage = document.querySelector('#stage');
    const card = document.querySelector('#date-card');
    const title = document.querySelector('#card-title');
    const resultTitle = document.querySelector('#result-title');
    const resultContent = document.querySelector('#result-content');
    const number = document.querySelector('#card-number');
    const status = document.querySelector('#shuffle-status');
    const footer = document.querySelector('footer');
    let busy = false;
    let selectedIdea = null;
    let openTimer = null;
    const revealDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 30 : 1450;
    let remember = localStorage.getItem(STORAGE_MODE) !== 'off';
    let used = readUsed();

    footer.innerHTML = `
      <label class="remember-control">
        <input type="checkbox" ${remember ? 'checked' : ''}>
        <span class="remember-switch" aria-hidden="true"></span>
        Remember picks
      </label>
      <button class="used-link" type="button">Used cards <b>0</b></button>
      <a class="admin-link" href="?admin=1" aria-label="Open private editor" title="Private editor">✦</a>
      <span class="made-for">Made for two</span>`;

    const modal = document.createElement('dialog');
    modal.className = 'used-modal';
    modal.innerHTML = `
      <div class="modal-heading">
        <div><p class="eyebrow">Our history</p><h2>Used cards</h2></div>
        <button class="modal-close" type="button" aria-label="Close">×</button>
      </div>
      <div class="used-list"></div>
      <button class="clear-history" type="button">Forget all used cards</button>`;
    document.body.appendChild(modal);

    const modeInput = footer.querySelector('input');
    const usedLink = footer.querySelector('.used-link');
    const usedList = modal.querySelector('.used-list');

    function readUsed() {
      try {
        const value = JSON.parse(localStorage.getItem(STORAGE_USED) || '[]');
        return Array.isArray(value) ? value.map(Number).filter(id => ideas.some(idea => idea.id === id)) : [];
      } catch (_) { return []; }
    }

    function saveUsed() {
      localStorage.setItem(STORAGE_USED, JSON.stringify(used));
      updateHistoryUI();
    }

    function remaining() {
      return remember ? ideas.filter(idea => !used.includes(idea.id)) : ideas;
    }

    function showIdea(idea, fromHistory = false, reveal = true) {
      title.textContent = resultTitle.textContent = idea.title;
      resultContent.innerHTML = idea.content;
      const needsMoreRoom = resultContent.textContent.trim().length > 220 || resultContent.querySelectorAll('li').length > 4;
      card.dataset.needsMoreRoom = needsMoreRoom ? 'true' : 'false';
      stage.classList.toggle('expanded-card', reveal && needsMoreRoom);
      resultContent.querySelectorAll('a').forEach(link => {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
      });
      number.textContent = String(idea.id).padStart(2, '0');
      if (reveal) {
        animateOpen();
      }
      status.textContent = fromHistory ? 'A favourite from before' : 'The cards have spoken';
    }

    function openSelected() {
      if (!selectedIdea || busy || card.classList.contains('revealed') || card.classList.contains('opening')) return;
      animateOpen();
    }

    function animateOpen() {
      clearTimeout(openTimer);
      stage.classList.toggle('expanded-card', card.dataset.needsMoreRoom === 'true');
      card.classList.remove('ready-to-open');
      card.classList.remove('revealed');
      card.classList.add('opening');
      stage.classList.add('opening');
      document.body.classList.add('card-is-open');
      status.textContent = 'Unveiling tonight…';
      openTimer = setTimeout(() => {
        card.classList.remove('opening');
        card.classList.add('revealed');
        stage.classList.remove('opening');
        status.textContent = 'The cards have spoken';
      }, revealDuration);
    }

    function updateHistoryUI() {
      const usedIdeas = used.map(id => ideas.find(idea => idea.id === id)).filter(Boolean);
      usedLink.querySelector('b').textContent = usedIdeas.length;
      usedLink.disabled = usedIdeas.length === 0;
      usedList.innerHTML = usedIdeas.length
        ? usedIdeas.map(idea => `<button type="button" data-idea-id="${idea.id}"><span>${escapeHtml(idea.title)}</span><i>Open again →</i></button>`).join('')
        : '<p class="empty-history">No cards have been used yet.</p>';
      const noneLeft = remember && remaining().length === 0 && ideas.length > 0;
      button.disabled = busy || !ideas.length || noneLeft;
      if (noneLeft) status.textContent = 'Every card has had its night';
    }

    function escapeHtml(value) {
      const element = document.createElement('span');
      element.textContent = value;
      return element.innerHTML;
    }

    button.addEventListener('click', () => {
      const pool = remaining();
      if (busy || !pool.length) return;
      busy = true;
      button.disabled = true;
      card.classList.remove('revealed');
      card.classList.remove('ready-to-open');
      card.classList.remove('opening');
      document.body.classList.remove('card-is-open');
      stage.classList.remove('expanded-card');
      stage.classList.remove('opening');
      clearTimeout(openTimer);
      stage.classList.add('shuffling');
      const phrases = ['Mixing a little trouble…', 'No peeking…', 'Almost decided…', 'Fate is choosing…'];
      let step = 0;
      status.textContent = phrases[0];
      const phraseTimer = setInterval(() => status.textContent = phrases[++step % phrases.length], 700);
      const titleTimer = setInterval(() => {
        const preview = pool[Math.floor(Math.random() * pool.length)];
        title.textContent = preview.title;
        number.textContent = String(preview.id).padStart(2, '0');
      }, 130);

      setTimeout(() => {
        clearInterval(phraseTimer);
        clearInterval(titleTimer);
        const winner = pool[Math.floor(Math.random() * pool.length)];
        selectedIdea = winner;
        if (remember && !used.includes(winner.id)) {
          used.push(winner.id);
          saveUsed();
        }
        showIdea(winner, false, false);
        stage.classList.remove('shuffling');
        setTimeout(() => {
          card.classList.add('ready-to-open');
          status.textContent = 'Tap the card to open it';
          button.querySelector('.button-label').textContent = 'Tempt fate again';
          busy = false;
          updateHistoryUI();
        }, 420);
      }, 3400);
    });

    modeInput.addEventListener('change', () => {
      remember = modeInput.checked;
      localStorage.setItem(STORAGE_MODE, remember ? 'on' : 'off');
      status.textContent = remember ? 'Past picks will stay out of the deck' : 'Every card is back in play';
      updateHistoryUI();
    });
    usedLink.addEventListener('click', () => { updateHistoryUI(); modal.showModal(); });
    modal.querySelector('.modal-close').addEventListener('click', () => modal.close());
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    usedList.addEventListener('click', event => {
      const control = event.target.closest('[data-idea-id]');
      if (!control) return;
      const idea = ideas.find(item => item.id === Number(control.dataset.ideaId));
      if (idea) { selectedIdea = idea; modal.close(); showIdea(idea, true); }
    });
    modal.querySelector('.clear-history').addEventListener('click', () => {
      used = [];
      saveUsed();
      modal.close();
      status.textContent = 'The whole deck is waiting again';
    });
    document.querySelector('#close-card').addEventListener('click', () => {
      card.classList.remove('revealed');
      card.classList.remove('opening');
      document.body.classList.remove('card-is-open');
      stage.classList.remove('expanded-card');
      stage.classList.remove('opening');
      clearTimeout(openTimer);
    });
    card.tabIndex = 0;
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', 'Open selected date-night card');
    card.addEventListener('click', event => {
      if (!event.target.closest('a,button')) openSelected();
    });
    card.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openSelected(); }
    });

    updateHistoryUI();
  }

  document.querySelectorAll('[data-editor-form]').forEach(form => {
    const editor = form.querySelector('[data-editor]');
    const hidden = form.querySelector('[data-content-input]');
    let savedRange = null;

    const rememberSelection = () => {
      const selection = window.getSelection();
      if (selection.rangeCount && editor.contains(selection.anchorNode)) {
        savedRange = selection.getRangeAt(0).cloneRange();
      }
    };
    const restoreSelection = () => {
      if (!savedRange) return false;
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(savedRange);
      return true;
    };

    editor.addEventListener('keyup', rememberSelection);
    editor.addEventListener('mouseup', rememberSelection);
    editor.addEventListener('input', () => {
      hidden.value = editor.innerHTML;
      rememberSelection();
    });
    form.querySelectorAll('[data-command]').forEach(control => {
      control.addEventListener('mousedown', event => {
        event.preventDefault();
        rememberSelection();
      });
      control.addEventListener('click', () => {
        editor.focus();
        const command = control.dataset.command;
        let value = control.dataset.value || null;
        if (command === 'createLink') {
          value = prompt('Paste a link (https://…)');
          if (!value) return;
          let url;
          try { url = new URL(value); } catch (_) { alert('Please enter a complete http or https link.'); return; }
          if (!['http:', 'https:'].includes(url.protocol)) { alert('Please enter an http or https link.'); return; }
          value = url.href;
          restoreSelection();
          const selection = window.getSelection();
          if (!selection.rangeCount || selection.getRangeAt(0).collapsed) {
            const link = document.createElement('a');
            link.href = value;
            link.textContent = value;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            const range = selection.rangeCount ? selection.getRangeAt(0) : document.createRange();
            if (!selection.rangeCount) range.selectNodeContents(editor);
            range.collapse(false);
            range.insertNode(link);
            range.setStartAfter(link);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            hidden.value = editor.innerHTML;
            rememberSelection();
            return;
          }
        }
        restoreSelection();
        document.execCommand(command, false, value);
        hidden.value = editor.innerHTML;
        rememberSelection();
      });
    });
    form.addEventListener('submit', () => hidden.value = editor.innerHTML);
  });
})();
