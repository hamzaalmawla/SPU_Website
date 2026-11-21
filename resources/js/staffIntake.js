// Staff intake link pages: name suggestions while typing, and adding or
// removing education rows. Both pages work without this script; it only
// makes them quicker to use.

const SUGGEST_DELAY_MS = 250;

export function suggestionItem(item, labels) {
    const li = document.createElement('li');
    li.setAttribute('role', 'option');
    li.setAttribute('aria-selected', 'false');

    const link = document.createElement('a');
    link.href = item.url;
    link.className = 'flex items-center gap-4 rounded-xl border border-slate-200 p-4 hover:border-spu-blue focus:border-spu-blue focus:outline-none focus:ring-4 focus:ring-spu-blue/10';

    if (item.photoUrl) {
        const img = document.createElement('img');
        img.src = item.photoUrl;
        img.alt = '';
        img.className = 'h-14 w-14 rounded-full object-cover';
        link.append(img);
    } else {
        const placeholder = document.createElement('span');
        placeholder.className = 'h-14 w-14 rounded-full bg-slate-200';
        placeholder.setAttribute('aria-hidden', 'true');
        link.append(placeholder);
    }

    const text = document.createElement('span');
    text.className = 'flex-1';
    const nameAr = document.createElement('span');
    nameAr.className = 'block font-bold';
    nameAr.textContent = item.nameAr || '';
    const nameEn = document.createElement('span');
    nameEn.className = 'block font-bold';
    nameEn.dir = 'ltr';
    nameEn.textContent = item.nameEn || '';
    const details = document.createElement('span');
    details.className = 'block text-sm text-slate-500';
    details.textContent = [item.title, item.department, item.faculty ? labels.otherFaculty.replace(':faculty', item.faculty) : null]
        .filter(Boolean)
        .join(' · ');
    text.append(nameAr, nameEn, details);

    const action = document.createElement('span');
    action.className = 'text-sm font-bold text-spu-red';
    action.textContent = labels.thisIsMe;

    link.append(text, action);
    li.append(link);

    return li;
}

function initSearch(root) {
    const input = root.querySelector('input[name="q"]');
    const list = root.querySelector('[data-suggestions]');
    const heading = root.querySelector('[data-suggestions-heading]');
    const newEntry = root.querySelector('[data-new-entry]');
    const newLink = root.querySelector('[data-new-link]');
    if (!input || !list) {
        return;
    }

    const labels = {
        otherFaculty: root.dataset.otherFaculty || '',
        thisIsMe: root.dataset.thisIsMe || '',
        noResults: root.dataset.noResults || '',
    };
    let timer = null;
    let controller = null;

    const render = (items, typed) => {
        list.replaceChildren(...items.map((item) => suggestionItem(item, labels)));
        root.querySelectorAll('[data-no-results]').forEach((node) => node.remove());

        const hasTyped = typed.length >= 3;
        if (heading) heading.hidden = !hasTyped;
        if (newEntry) newEntry.hidden = !hasTyped;
        if (newLink) {
            const url = new URL(root.dataset.newUrl, window.location.href);
            url.searchParams.set('name', typed);
            newLink.href = url.toString();
            newLink.textContent = items.length > 0 ? newLink.dataset.labelNotListed : newLink.dataset.labelNew;
        }
        if (hasTyped && items.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-slate-600';
            empty.dataset.noResults = '';
            empty.textContent = labels.noResults;
            list.after(empty);
        }
        input.setAttribute('aria-expanded', items.length > 0 ? 'true' : 'false');
    };

    const lookup = async () => {
        const typed = input.value.trim();
        if (controller) controller.abort();
        if (typed.length < 3) {
            render([], typed);
            return;
        }

        controller = new AbortController();
        try {
            const url = new URL(root.dataset.suggestUrl, window.location.href);
            url.searchParams.set('q', typed);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) return;
            const data = await response.json();
            render(Array.isArray(data.items) ? data.items : [], typed);
        } catch (error) {
            if (error.name !== 'AbortError') console.error(error);
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(lookup, SUGGEST_DELAY_MS);
    });

    // Arrow keys move between the input and the suggestions.
    root.addEventListener('keydown', (event) => {
        const links = [...list.querySelectorAll('a')];
        if (links.length === 0 || !['ArrowDown', 'ArrowUp', 'Escape'].includes(event.key)) return;

        const current = links.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            input.focus();
            return;
        }
        event.preventDefault();
        const next = event.key === 'ArrowDown'
            ? Math.min(current + 1, links.length - 1)
            : current - 1;
        if (next < 0) {
            input.focus();
        } else {
            links[next].focus();
        }
    });
}

export function renumberRow(html, index) {
    return html.replaceAll('__INDEX__', String(index));
}

function initEducations(root) {
    const list = root.querySelector('[data-education-list]');
    const template = root.querySelector('[data-education-template]');
    const add = root.querySelector('[data-add-education]');
    if (!list || !template || !add) {
        return;
    }

    let nextIndex = Number(root.dataset.nextIndex || list.children.length);
    add.hidden = false;
    add.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = renumberRow(template.innerHTML, nextIndex++);
        const row = wrapper.firstElementChild;
        list.append(row);
        row.querySelector('input:not([type="hidden"])')?.focus();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-education]');
        if (button) {
            button.closest('[data-education-row]')?.remove();
            add.focus();
        }
    });
}

if (typeof document !== 'undefined') {
    const boot = () => {
        document.querySelectorAll('[data-staff-intake-search]').forEach(initSearch);
        document.querySelectorAll('[data-educations]').forEach(initEducations);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}
