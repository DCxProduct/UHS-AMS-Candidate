<style>
    .uhs-sidebar-resizer {
        display: none;
        position: absolute;
        inset-block: 0;
        inset-inline-end: -4px;
        z-index: 40;
        width: 8px;
        cursor: ew-resize;
        border: 0;
        background: transparent;
        padding: 0;
    }

    .uhs-sidebar-resizer::after {
        position: absolute;
        inset-block: 0;
        inset-inline: 3px;
        content: '';
        background: rgb(148 163 184 / 45%);
        transition: background-color 150ms ease;
    }

    .uhs-sidebar-resizer:hover::after,
    .uhs-sidebar-resizer:focus-visible::after,
    .uhs-sidebar-resizing .uhs-sidebar-resizer::after {
        background: rgb(59 130 246 / 55%);
    }

    .uhs-sidebar-hover-tooltip {
        position: fixed;
        z-index: 100;
        max-width: min(28rem, calc(100vw - 1rem));
        padding: 0.35rem 0.6rem;
        border: 1px solid rgb(148 163 184 / 35%);
        border-radius: 0.375rem;
        background: rgb(15 23 42 / 96%);
        color: white;
        font-size: 0.75rem;
        line-height: 1.25rem;
        white-space: normal;
        overflow-wrap: anywhere;
        pointer-events: none;
        box-shadow: 0 6px 18px rgb(15 23 42 / 18%);
    }

    @media (min-width: 1024px) {
        .fi-main-sidebar.fi-sidebar-open .uhs-sidebar-resizer {
            display: block;
        }

        .uhs-sidebar-resizing,
        .uhs-sidebar-resizing * {
            cursor: ew-resize !important;
            user-select: none !important;
        }
    }
</style>

<script>
    (() => {
        const storageKey = 'uhs-sidebar-width';
        const minimumWidth = 240;
        const maximumWidth = 480;
        const root = document.documentElement;
        const defaultWidth = getComputedStyle(root).getPropertyValue('--sidebar-width').trim() || '20rem';

        const clampWidth = (width) => Math.min(
            Math.max(width, minimumWidth),
            Math.min(maximumWidth, Math.round(window.innerWidth * 0.42)),
        );

        const applySavedWidth = () => {
            if (window.innerWidth < 1024) {
                root.style.setProperty('--sidebar-width', defaultWidth);

                return;
            }

            const savedWidth = Number.parseInt(localStorage.getItem(storageKey) ?? '', 10);

            if (Number.isFinite(savedWidth)) {
                root.style.setProperty('--sidebar-width', `${clampWidth(savedWidth)}px`);
            } else {
                root.style.setProperty('--sidebar-width', defaultWidth);
            }
        };

        const attachSidebarLabelTooltips = (sidebar) => {
            let tooltip = document.querySelector('.uhs-sidebar-hover-tooltip');

            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'uhs-sidebar-hover-tooltip';
                tooltip.hidden = true;
                document.body.appendChild(tooltip);
            }

            sidebar.querySelectorAll('.fi-sidebar-item-btn').forEach((anchor) => {
                if (anchor.dataset.uhsTooltipAttached === 'true') {
                    return;
                }

                const label = anchor.querySelector('.fi-sidebar-item-label');
                const text = label?.textContent.trim();

                if (!text) {
                    return;
                }

                anchor.dataset.uhsTooltipAttached = 'true';
                anchor.removeAttribute('title');

                let showTimer;
                const showTooltip = () => {
                    window.clearTimeout(showTimer);

                    if (window.innerWidth < 1024 || !sidebar.classList.contains('fi-sidebar-open')) {
                        return;
                    }

                    if (label.scrollWidth <= label.clientWidth) {
                        return;
                    }

                    showTimer = window.setTimeout(() => {
                        tooltip.textContent = text;
                        tooltip.hidden = false;

                        const anchorRect = anchor.getBoundingClientRect();
                        const tooltipRect = tooltip.getBoundingClientRect();
                        const top = anchorRect.top - tooltipRect.height - 6;
                        const left = Math.min(
                            Math.max(8, anchorRect.left),
                            window.innerWidth - tooltipRect.width - 8,
                        );

                        tooltip.style.top = `${Math.max(8, top)}px`;
                        tooltip.style.left = `${left}px`;
                    }, 80);
                };

                const hideTooltip = () => {
                    window.clearTimeout(showTimer);
                    tooltip.hidden = true;
                };

                anchor.addEventListener('mouseenter', showTooltip);
                anchor.addEventListener('mouseleave', hideTooltip);
                anchor.addEventListener('focus', showTooltip);
                anchor.addEventListener('blur', hideTooltip);
            });
        };

        const attachResizer = () => {
            const sidebar = document.getElementById('fi-main-sidebar');

            if (!sidebar) {
                return;
            }

            attachSidebarLabelTooltips(sidebar);

            if (sidebar.dataset.uhsResizable === 'true') {
                return;
            }

            sidebar.dataset.uhsResizable = 'true';

            const resizer = document.createElement('button');
            resizer.type = 'button';
            resizer.className = 'uhs-sidebar-resizer';
            resizer.setAttribute('aria-label', 'Resize navigation sidebar');
            resizer.title = 'Resize navigation sidebar';
            sidebar.appendChild(resizer);

            resizer.addEventListener('dblclick', () => {
                localStorage.removeItem(storageKey);
                root.style.setProperty('--sidebar-width', defaultWidth);
            });

            resizer.addEventListener('pointerdown', (event) => {
                if (window.innerWidth < 1024 || !sidebar.classList.contains('fi-sidebar-open')) {
                    return;
                }

                event.preventDefault();

                const direction = document.documentElement.dir === 'rtl' ? -1 : 1;
                const startX = event.clientX;
                const startWidth = sidebar.getBoundingClientRect().width;

                document.body.classList.add('uhs-sidebar-resizing');
                resizer.setPointerCapture?.(event.pointerId);

                const move = (moveEvent) => {
                    const width = clampWidth(startWidth + ((moveEvent.clientX - startX) * direction));
                    document.documentElement.style.setProperty('--sidebar-width', `${width}px`);
                };

                const stop = () => {
                    const width = Math.round(sidebar.getBoundingClientRect().width);
                    localStorage.setItem(storageKey, String(clampWidth(width)));
                    document.body.classList.remove('uhs-sidebar-resizing');
                    resizer.removeEventListener('pointermove', move);
                    resizer.removeEventListener('pointerup', stop);
                    resizer.removeEventListener('pointercancel', stop);
                };

                resizer.addEventListener('pointermove', move);
                resizer.addEventListener('pointerup', stop);
                resizer.addEventListener('pointercancel', stop);
            });

            applySavedWidth();
        };

        document.addEventListener('DOMContentLoaded', attachResizer);
        document.addEventListener('livewire:navigated', attachResizer);
        window.addEventListener('resize', applySavedWidth);

        if (document.readyState !== 'loading') {
            attachResizer();
        }
    })();
</script>
