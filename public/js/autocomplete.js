(function () {
    'use strict';

    function resolveElement(selectorOrElement) {
        if (typeof selectorOrElement === 'string') {
            return document.querySelector(selectorOrElement);
        }

        return selectorOrElement || null;
    }

    function normalizarResultados(payload) {
        if (!payload || payload.success !== true || !Array.isArray(payload.results)) {
            return [];
        }

        return payload.results;
    }

    function textoPrincipal(item) {
        return String(item.title || item.nombre_centro || item.label || item.value || '');
    }

    function textoMeta(item) {
        if (item.meta) {
            return String(item.meta);
        }

        return [item.codigo_centro, item.localidad, item.destino].filter(Boolean).join(' - ');
    }

    function valorInput(item) {
        return String(item.inputValue || item.label || item.value || item.nombre_centro || '');
    }

    function crearItem(item, renderItem) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'autocomplete-item';
        boton.setAttribute('role', 'option');
        boton.setAttribute('aria-selected', 'false');

        const custom = typeof renderItem === 'function' ? renderItem(item) : null;
        if (custom instanceof HTMLElement) {
            boton.appendChild(custom);
            return boton;
        }

        const title = document.createElement('span');
        title.className = 'autocomplete-title';
        title.textContent = textoPrincipal(item);

        const metaTexto = textoMeta(item);
        boton.appendChild(title);

        if (metaTexto !== '') {
            const meta = document.createElement('span');
            meta.className = 'autocomplete-meta';
            meta.textContent = metaTexto;
            boton.appendChild(meta);
        }

        return boton;
    }

    function initAutocomplete(options) {
        const input = resolveElement(options.inputSelector);
        if (!input || !options.endpoint) {
            return null;
        }

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        let wrapper = input.closest('.autocomplete-wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'autocomplete-wrapper';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
        }

        let dropdown = wrapper.querySelector('.autocomplete-dropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'autocomplete-dropdown d-none';
            dropdown.setAttribute('role', 'listbox');
            wrapper.appendChild(dropdown);
        }

        const minChars = Number.isInteger(options.minChars) ? options.minChars : 1;
        const limit = Number.isInteger(options.limit) ? options.limit : 10;
        const extraParams = options.params || {};
        let timer = null;
        let abortController = null;
        let items = [];
        let activeIndex = -1;
        let selectedItem = options.initialSelected || null;

        function setExpanded(expanded) {
            input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        function close() {
            if (abortController) {
                abortController.abort();
                abortController = null;
            }

            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            activeIndex = -1;
            setExpanded(false);
        }

        function updateActive() {
            const botones = Array.from(dropdown.querySelectorAll('.autocomplete-item'));
            botones.forEach((boton, index) => {
                const active = index === activeIndex;
                boton.classList.toggle('is-active', active);
                boton.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function select(item) {
            selectedItem = item;
            input.value = typeof options.getInputValue === 'function' ? options.getInputValue(item) : valorInput(item);
            input.setCustomValidity('');
            close();

            if (typeof options.onSelect === 'function') {
                options.onSelect(item, input);
            }
        }

        function render(results) {
            items = Array.isArray(results) ? results : [];
            dropdown.innerHTML = '';
            activeIndex = -1;

            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'autocomplete-empty';
                empty.textContent = options.emptyText || 'No hay resultados.';
                dropdown.appendChild(empty);
                dropdown.classList.remove('d-none');
                setExpanded(true);
                return;
            }

            items.forEach((item) => {
                const node = crearItem(item, options.renderItem);
                node.addEventListener('click', () => select(item));
                dropdown.appendChild(node);
            });

            dropdown.classList.remove('d-none');
            setExpanded(true);
        }

        function buildUrl(query) {
            const url = new URL(options.endpoint, window.location.href);
            url.searchParams.set(options.queryParam || 'q', query);
            url.searchParams.set('limit', String(limit));

            Object.keys(extraParams).forEach((key) => {
                url.searchParams.set(key, String(extraParams[key]));
            });

            return url.toString();
        }

        function request(query) {
            if (abortController) {
                abortController.abort();
            }

            abortController = new AbortController();
            fetch(buildUrl(query), {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Respuesta no valida');
                    }

                    return response.json();
                })
                .then((payload) => {
                    render(normalizarResultados(payload));
                })
                .catch((error) => {
                    if (error.name !== 'AbortError') {
                        render([]);
                    }
                });
        }

        function schedule() {
            const query = input.value.trim();
            window.clearTimeout(timer);

            if (query.length < minChars) {
                close();
                return;
            }

            timer = window.setTimeout(() => request(query), options.delay || 180);
        }

        input.addEventListener('input', () => {
            selectedItem = null;
            input.setCustomValidity('');

            if (typeof options.onInput === 'function') {
                options.onInput(input);
            }

            schedule();
        });

        input.addEventListener('focus', () => {
            schedule();
        });

        input.addEventListener('keydown', (event) => {
            const botones = Array.from(dropdown.querySelectorAll('.autocomplete-item'));
            if (dropdown.classList.contains('d-none') || botones.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = Math.min(botones.length - 1, activeIndex + 1);
                updateActive();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
                updateActive();
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                botones[activeIndex].click();
            } else if (event.key === 'Escape') {
                close();
            }
        });

        dropdown.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                close();
            }
        });

        if (options.formSelector && options.requireSelection) {
            const form = resolveElement(options.formSelector);
            if (form) {
                form.addEventListener('submit', (event) => {
                    const hasText = input.value.trim() !== '';
                    const valid = typeof options.isValidSelection === 'function'
                        ? options.isValidSelection(selectedItem, input)
                        : selectedItem !== null;

                    if (hasText && !valid) {
                        event.preventDefault();
                        input.setCustomValidity(options.invalidMessage || 'Selecciona una opcion de la lista.');
                        input.reportValidity();
                    }
                });
            }
        }

        return {
            close,
            getSelected: () => selectedItem,
            setSelected: (item) => {
                selectedItem = item;
            },
        };
    }

    window.AppAutocomplete = {
        init: initAutocomplete,
    };
})();
