// ===================================================
// Admin notification bell: polls the server, shows a dropdown,
// rings + plays a short sound + browser notification on new items.
// ===================================================
(function () {
    const bell = document.getElementById('anBell');
    if (!bell) return;

    const POLL_MS = 15000;
    const API = 'notifications-api.php';
    const panel = document.getElementById('anPanel');
    const list = document.getElementById('anList');
    const badge = document.getElementById('anBadge');
    const soundBtn = document.getElementById('anSoundBtn');
    const baseTitle = document.title;

    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };

    let lastSeenId = parseInt(store.get('zaykaAdminLastNotif') || '0', 10) || 0;
    let firstLoad = true;
    let soundOn = store.get('zaykaAdminSound') !== '0';
    let audioCtx = null;

    function renderSoundBtn() {
        soundBtn.textContent = soundOn ? '🔊 Sound on' : '🔇 Sound off';
    }

    function beep() {
        if (!soundOn) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            [[880, 0], [1320, 0.16]].forEach(([freq, at]) => {
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime + at);
                g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + at + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + at + 0.25);
                o.connect(g).connect(audioCtx.destination);
                o.start(audioCtx.currentTime + at);
                o.stop(audioCtx.currentTime + at + 0.3);
            });
        } catch (e) { /* audio blocked until the admin interacts with the page */ }
    }

    function desktopNotify(item) {
        if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
        try {
            const n = new Notification(item.icon + ' ' + item.title, { body: item.message || '', tag: 'zayka-admin-' + item.id });
            n.onclick = () => { window.focus(); if (item.link) location.href = item.link; };
        } catch (e) {}
    }

    function setBadge(count) {
        badge.hidden = count <= 0;
        badge.textContent = count > 99 ? '99+' : String(count);
        document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;
    }

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    }

    function render(items) {
        list.textContent = '';
        if (!items.length) {
            list.appendChild(el('div', 'an-empty', 'No notifications yet. New orders, rider requests and tickets will appear here.'));
            return;
        }
        items.forEach(it => {
            const row = el('a', 'an-item' + (it.is_read ? '' : ' unread'));
            row.href = it.link || '#';
            row.dataset.id = it.id;
            row.appendChild(el('span', 'an-icon', it.icon));
            const body = el('div');
            body.appendChild(el('div', 'an-title', it.title));
            if (it.message) body.appendChild(el('div', 'an-msg', it.message));
            body.appendChild(el('div', 'an-when', it.when));
            row.appendChild(body);
            row.addEventListener('click', (e) => {
                if (!it.is_read) {
                    const fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('id', it.id);
                    fetch(API, { method: 'POST', body: fd, keepalive: true }).catch(() => {});
                }
                if (!it.link) e.preventDefault();
            });
            list.appendChild(row);
        });
    }

    function poll() {
        return fetch(API + '?action=list', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                setBadge(data.unread);
                render(data.items);

                const fresh = data.items.filter(i => !i.is_read && i.id > lastSeenId);
                if (fresh.length && !firstLoad) {
                    bell.classList.remove('ring');
                    void bell.offsetWidth;
                    bell.classList.add('ring');
                    beep();
                    const top = fresh[0];
                    showToast(fresh.length > 1 ? `${top.icon} ${top.title} (+${fresh.length - 1} more)` : `${top.icon} ${top.title}`, 'info');
                    fresh.slice(0, 3).forEach(desktopNotify);
                }
                if (data.latest_id > lastSeenId) {
                    lastSeenId = data.latest_id;
                    store.set('zaykaAdminLastNotif', String(lastSeenId));
                }
                firstLoad = false;
            })
            .catch(() => {});
    }

    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = panel.classList.toggle('open');
        bell.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            poll();
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        }
    });
    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== bell) {
            panel.classList.remove('open');
            bell.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') panel.classList.remove('open');
    });

    document.getElementById('anMarkAll').addEventListener('click', () => {
        const fd = new FormData();
        fd.append('action', 'mark_all');
        fetch(API, { method: 'POST', body: fd }).then(poll);
    });

    soundBtn.addEventListener('click', () => {
        soundOn = !soundOn;
        store.set('zaykaAdminSound', soundOn ? '1' : '0');
        renderSoundBtn();
        if (soundOn) beep();
    });

    renderSoundBtn();
    poll();
    setInterval(poll, POLL_MS);
})();
