import { renderAsync } from 'docx-preview';
import '../css/word-document-preview.css';

const preview = document.querySelector('[data-word-preview]');

if (preview) {
    const loading = preview.querySelector('[data-preview-loading]');
    const error = preview.querySelector('[data-preview-error]');
    const content = preview.querySelector('[data-preview-content]');
    const retry = preview.querySelector('[data-preview-retry]');
    let currentRequest;
    let currentAttempt = 0;

    // Uploaded documents are read-only: their links, forms and embedded HTML cannot navigate the viewer.
    content.addEventListener('click', (event) => {
        if (event.target.closest('a, area, form')) {
            event.preventDefault();
        }
    }, true);
    content.addEventListener('submit', (event) => event.preventDefault(), true);

    function sanitizeDocument(container) {
        container.querySelectorAll('script, iframe, object, embed, form, input, textarea, button, select, link, meta, base, audio, video, source')
            .forEach((element) => element.remove());

        for (const element of container.querySelectorAll('*')) {
            for (const attribute of [...element.attributes]) {
                const name = attribute.name.toLowerCase();
                if (name.startsWith('on') || ['srcdoc', 'srcset', 'action', 'formaction', 'target'].includes(name)) {
                    element.removeAttribute(attribute.name);
                } else if (['src', 'href', 'xlink:href'].includes(name)) {
                    const isEmbeddedImage = ['img', 'image'].includes(element.localName)
                        && /^(data:image\/|blob:)/i.test(attribute.value);
                    if (! isEmbeddedImage) {
                        element.removeAttribute(attribute.name);
                    }
                }
            }
        }
    }

    async function loadDocument() {
        const attempt = ++currentAttempt;
        currentRequest?.abort();
        const controller = new AbortController();
        currentRequest = controller;
        let deadline;

        preview.setAttribute('aria-busy', 'true');
        loading.hidden = false;
        error.hidden = true;
        content.hidden = true;
        content.replaceChildren();

        try {
            const documentUrl = new URL(preview.dataset.documentUrl, window.location.href);
            if (documentUrl.origin !== window.location.origin) {
                throw new Error('Invalid document origin');
            }

            const timeout = new Promise((_, reject) => {
                deadline = window.setTimeout(() => {
                    controller.abort();
                    reject(new Error('Word preview timed out'));
                }, 30000);
            });

            const render = async () => {
                const response = await fetch(documentUrl, {
                    credentials: 'same-origin',
                    redirect: 'error',
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (! response.ok || response.headers.get('Content-Type')?.includes('text/html')) {
                    throw new Error('Unable to load the document');
                }

                const bytes = await response.arrayBuffer();
                const signature = new Uint8Array(bytes, 0, Math.min(bytes.byteLength, 4));
                if (signature.length < 4 || signature[0] !== 0x50 || signature[1] !== 0x4b
                    || signature[2] !== 0x03 || signature[3] !== 0x04) {
                    throw new Error('The document is not a DOCX archive');
                }

                const rendered = document.createElement('div');
                await renderAsync(bytes, rendered, null, {
                    className: 'docx',
                    inWrapper: true,
                    breakPages: true,
                    experimental: false,
                    renderAltChunks: false,
                    useBase64URL: true,
                });
                if (! rendered.querySelector('section.docx')) {
                    throw new Error('The document has no preview pages');
                }
                sanitizeDocument(rendered);
                return rendered;
            };

            const rendered = await Promise.race([render(), timeout]);
            if (attempt !== currentAttempt) {
                return;
            }

            content.replaceChildren(...rendered.childNodes);
            loading.hidden = true;
            content.hidden = false;
        } catch {
            if (attempt === currentAttempt) {
                loading.hidden = true;
                error.hidden = false;
                error.querySelector('[data-preview-error-message]').textContent = preview.dataset.errorMessage;
            }
        } finally {
            window.clearTimeout(deadline);
            if (attempt === currentAttempt) {
                preview.setAttribute('aria-busy', 'false');
            }
        }
    }

    retry.addEventListener('click', loadDocument);
    window.addEventListener('pagehide', () => currentRequest?.abort(), { once: true });
    loadDocument();
}
