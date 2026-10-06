(function () {
    const iconMap = new Map([
        ['👨‍👩‍👧', 'fa-users'],
        ['👨‍🎓', 'fa-user-graduate'],
        ['👨‍🏫', 'fa-chalkboard-user'],
        ['✉️', 'fa-envelope'],
        ['⚠️', 'fa-triangle-exclamation'],
        ['⚙️', 'fa-gear'],
        ['✏️', 'fa-pen-to-square'],
        ['🗑️', 'fa-trash'],
        ['✅', 'fa-circle-check'],
        ['❌', 'fa-circle-xmark'],
        ['☀️', 'fa-sun'],
        ['⬇️', 'fa-download'],
        ['🖨️', 'fa-print'],
        ['🛠️', 'fa-screwdriver-wrench'],
        ['🏫', 'fa-school'],
        ['📊', 'fa-chart-pie'],
        ['👥', 'fa-users'],
        ['📚', 'fa-book-open'],
        ['📄', 'fa-file-lines'],
        ['📈', 'fa-chart-line'],
        ['📉', 'fa-arrow-trend-down'],
        ['📋', 'fa-clipboard-list'],
        ['📅', 'fa-calendar-days'],
        ['💰', 'fa-sack-dollar'],
        ['💬', 'fa-comments'],
        ['💾', 'fa-floppy-disk'],
        ['📝', 'fa-pen-to-square'],
        ['📤', 'fa-paper-plane'],
        ['📢', 'fa-bullhorn'],
        ['🔔', 'fa-bell'],
        ['🔍', 'fa-magnifying-glass'],
        ['⚙', 'fa-gear'],
        ['🔄', 'fa-rotate'],
        ['🚪', 'fa-right-from-bracket'],
        ['🌐', 'fa-globe'],
        ['🌍', 'fa-globe'],
        ['🔒', 'fa-lock'],
        ['🔐', 'fa-lock'],
        ['🔑', 'fa-key'],
        ['✉', 'fa-envelope'],
        ['✏', 'fa-pen-to-square'],
        ['🗑', 'fa-trash'],
        ['👁', 'fa-eye'],
        ['🙈', 'fa-eye-slash'],
        ['👤', 'fa-user'],
        ['👶', 'fa-child'],
        ['⚠', 'fa-triangle-exclamation'],
        ['⏳', 'fa-hourglass-half'],
        ['🕐', 'fa-clock'],
        ['📁', 'fa-folder-open'],
        ['📥', 'fa-download'],
        ['⬇', 'fa-download'],
        ['🖨', 'fa-print'],
        ['💳', 'fa-credit-card'],
        ['📍', 'fa-location-dot'],
        ['🧾', 'fa-receipt'],
        ['➕', 'fa-plus'],
        ['🔗', 'fa-link'],
        ['🔬', 'fa-microscope'],
        ['📖', 'fa-book'],
        ['💼', 'fa-briefcase'],
        ['🎓', 'fa-graduation-cap'],
        ['🎯', 'fa-bullseye'],
        ['🏆', 'fa-trophy'],
        ['❓', 'fa-circle-question'],
        ['💡', 'fa-lightbulb'],
        ['⚡', 'fa-bolt'],
        ['🚀', 'fa-rocket'],
        ['🛠', 'fa-screwdriver-wrench'],
        ['🤝', 'fa-handshake'],
        ['🎭', 'fa-masks-theater'],
        ['🌴', 'fa-umbrella-beach'],
        ['📌', 'fa-thumbtack'],
        ['🔴', 'fa-circle'],
        ['🟢', 'fa-circle'],
        ['🟠', 'fa-circle'],
        ['🟡', 'fa-circle'],
        ['🟣', 'fa-circle'],
        ['⭕', 'fa-circle'],
        ['📱', 'fa-mobile-screen-button'],
        ['💱', 'fa-money-bill-transfer'],
        ['🪙', 'fa-coins'],
        ['💵', 'fa-money-bill-wave'],
        ['🏦', 'fa-building-columns'],
        ['📭', 'fa-inbox'],
        ['🎉', 'fa-circle-check'],
        ['💯', 'fa-percent'],
        ['📜', 'fa-scroll'],
        ['👉', 'fa-hand-point-right'],
        ['🧱', 'fa-cubes-stacked'],
        ['☁️', 'fa-cloud'],
        ['⛔', 'fa-ban'],
        ['🔚', 'fa-right-from-bracket'],
        ['☰', 'fa-bars'],
        ['☀', 'fa-sun'],
        ['🌙', 'fa-moon'],
        ['✓', 'fa-check'],
        ['✕', 'fa-xmark'],
        ['➤', 'fa-paper-plane'],
        ['←', 'fa-arrow-left'],
        ['→', 'fa-arrow-right'],
        ['↔', 'fa-right-left'],
        ['★', 'fa-star'],
    ]);

    const icons = [...iconMap.keys()].sort((a, b) => b.length - a.length);
    const skipTags = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'PRE', 'CODE', 'OPTION', 'SELECT', 'INPUT']);

    function shouldSkip(node) {
        let el = node.parentElement;
        while (el) {
            if (skipTags.has(el.tagName) || el.closest?.('[data-no-fa-convert]')) return true;
            el = el.parentElement;
        }
        return false;
    }

    function makeIcon(iconClass, source) {
        const icon = document.createElement('i');
        icon.className = `fa-solid ${iconClass} fa-fw fa-icon`;
        icon.setAttribute('aria-hidden', 'true');
        icon.dataset.originalIcon = source;
        if (/^[🔴🟢🟠🟡🟣]$/.test(source)) {
            icon.classList.add(`fa-dot-${source.codePointAt(0).toString(16)}`);
        }
        return icon;
    }

    function convertTextNode(node) {
        const value = node.nodeValue;
        if (!value || shouldSkip(node) || !icons.some((icon) => value.includes(icon))) return;

        const fragment = document.createDocumentFragment();
        let index = 0;

        while (index < value.length) {
            const match = icons.find((icon) => value.startsWith(icon, index));
            if (match) {
                fragment.appendChild(makeIcon(iconMap.get(match), match));
                index += match.length;
                continue;
            }

            let next = value.length;
            for (const icon of icons) {
                const found = value.indexOf(icon, index + 1);
                if (found !== -1 && found < next) next = found;
            }
            fragment.appendChild(document.createTextNode(value.slice(index, next)));
            index = next;
        }

        node.parentNode.replaceChild(fragment, node);
    }

    function convert(root = document.body) {
        if (!root || skipTags.has(root.tagName)) return;
        if (root.nodeType === Node.TEXT_NODE) {
            convertTextNode(root);
            return;
        }
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(convertTextNode);
    }

    function start() {
        convert();
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'characterData') {
                    convertTextNode(mutation.target);
                    continue;
                }
                mutation.addedNodes.forEach((node) => convert(node));
            }
        });
        observer.observe(document.body, {
            childList: true,
            characterData: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
