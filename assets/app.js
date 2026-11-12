(() => {
    'use strict';

    const $ = (selector) => document.querySelector(selector);
    const els = {
        login: $('#login'), loginForm: $('#login-form'), name: $('#name'),
        app: $('#app'), greeting: $('#greeting'), who: $('#who'), logout: $('#logout'),
        noteForm: $('#note-form'), note: $('#note'), add: $('#add'),
        notes: $('#notes'), empty: $('#empty'), message: $('#message'),
    };

    let csrf = '';

    // Small fetch wrapper: JSON in, JSON out, CSRF header on every call.
    async function api(path, method = 'GET', body) {
        const headers = { Accept: 'application/json', 'X-CSRF-Token': csrf };
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        const res = await fetch(path, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
        if (!res.ok) {
            const err = new Error((data && data.error) || 'Something went wrong. Please try again.');
            err.status = res.status;
            throw err;
        }
        if (data && data.csrf) csrf = data.csrf;
        return data;
    }

    function say(text) { els.message.textContent = text || ''; }

    function fail(err) {
        if (err.status === 401) showLogin();
        say(err.message);
    }

    function showLogin() {
        els.app.hidden = true;
        els.login.hidden = false;
        try { els.name.value = localStorage.getItem('santa_name') || ''; } catch (e) { /* storage blocked */ }
        els.name.focus();
    }

    function showApp(name, isNew) {
        try { localStorage.setItem('santa_name', name); } catch (e) { /* storage blocked */ }
        els.greeting.textContent = isNew ? 'How do you do, ' : 'Welcome back, ';
        els.who.textContent = name;
        els.login.hidden = true;
        els.app.hidden = false;
        els.notes.textContent = '';
        loadNotes();
    }

    function refreshEmptyState() {
        els.empty.hidden = els.notes.children.length > 0;
    }

    function noteElement(note) {
        const li = document.createElement('li');
        li.className = 'note';

        const text = document.createElement('p');
        text.textContent = note.text;           // textContent: wishes are never parsed as HTML
        if (note.time) {
            const time = document.createElement('time');
            time.textContent = note.time.slice(0, 10);
            text.append(time);
        }

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'del';
        del.textContent = '✕';
        del.title = 'Delete this wish';
        del.setAttribute('aria-label', 'Delete this wish');
        del.addEventListener('click', async () => {
            del.disabled = true;
            try {
                await api('api/notes.php', 'DELETE', { id: note.id });
                li.remove();                    // only after the server confirmed
                refreshEmptyState();
                say('');
            } catch (err) {
                del.disabled = false;
                fail(err);
            }
        });

        li.append(text, del);
        return li;
    }

    async function loadNotes() {
        try {
            const data = await api('api/notes.php');
            els.notes.replaceChildren(...data.notes.map(noteElement));
            refreshEmptyState();
        } catch (err) {
            fail(err);
        }
    }

    els.loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        say('');
        try {
            const data = await api('api/user.php', 'POST', { name: els.name.value });
            showApp(data.user.name, data.isNew);
        } catch (err) {
            fail(err);
        }
    });

    els.logout.addEventListener('click', async () => {
        try { await api('api/user.php', 'DELETE'); } catch (err) { /* sign out locally anyway */ }
        say('');
        showLogin();
    });

    els.note.addEventListener('input', () => {
        els.add.disabled = els.note.value.trim() === '';
    });

    els.noteForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = els.note.value.trim();
        if (!text) return;
        els.add.disabled = true;
        say('');
        try {
            const data = await api('api/notes.php', 'POST', { text });
            els.notes.prepend(noteElement(data.note));
            els.note.value = '';
            refreshEmptyState();
        } catch (err) {
            els.add.disabled = false;
            fail(err);
        }
    });

    (async () => {
        try {
            const data = await api('api/user.php');
            if (data.user) showApp(data.user.name, false);
            else showLogin();
        } catch (err) {
            showLogin();
            fail(err);
        }
    })();
})();
