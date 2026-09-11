(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer panel navigation failed.', error);
  };
  const init = () => {
    try {
      const workspace = document.querySelector('.developer-workspace');
      const button = workspace?.querySelector('[data-panel-menu]');
      const navigation = document.getElementById('developer-panel-navigation');
      if (!workspace || !button || !navigation) return;
      const mobile = window.matchMedia ? window.matchMedia('(max-width: 760px)') : { matches: false };
      const setOpen = open => {
        button.setAttribute('aria-expanded', String(open));
        navigation.hidden = mobile.matches && !open;
      };
      workspace.classList.add('is-panel-enhanced');
      button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
      workspace.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobile.matches && button.getAttribute('aria-expanded') === 'true') {
          setOpen(false);
          button.focus();
        }
      });
      if (typeof mobile.addEventListener === 'function') {
        mobile.addEventListener('change', () => setOpen(false));
      } else if (typeof mobile.addListener === 'function') {
        mobile.addListener(() => setOpen(false));
      }
      setOpen(false);
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer sidebar disclosure failed.', error);
  };

  const init = () => {
    try {
      const toggles = Array.from(document.querySelectorAll('[data-developer-sidebar-toggle]'));
      if (toggles.length === 0) return;

      const storageKey = 'fnlla.developer.sidebar.expanded';
      const readState = () => {
        try {
          const stored = window.localStorage?.getItem(storageKey);
          return stored ? JSON.parse(stored) : {};
        } catch (_error) {
          return {};
        }
      };
      const writeState = state => {
        try {
          window.localStorage?.setItem(storageKey, JSON.stringify(state));
        } catch (_error) {
          // Some privacy modes block localStorage; disclosure still works for the current page.
        }
      };
      const state = readState();

      toggles.forEach(toggle => {
        const key = toggle.getAttribute('data-developer-sidebar-key') || '';
        const targetId = toggle.getAttribute('aria-controls') || '';
        const target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;

        const branch = toggle.closest('.developer-panel-sidebar-branch');
        const isActiveBranch = branch?.classList.contains('is-active') === true;
        const hasStoredValue = key !== '' && Object.prototype.hasOwnProperty.call(state, key);
        const expanded = isActiveBranch || (hasStoredValue ? state[key] === true : toggle.getAttribute('aria-expanded') === 'true');

        toggle.setAttribute('aria-expanded', String(expanded));
        target.hidden = !expanded;

        toggle.addEventListener('click', () => {
          const next = toggle.getAttribute('aria-expanded') !== 'true';
          toggle.setAttribute('aria-expanded', String(next));
          target.hidden = !next;
          if (key !== '') {
            state[key] = next;
            writeState(state);
          }
        });
      });
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer confirmation failed.', error);
  };

  const destructiveWord = /\b(delete|remove|deactivate|discard|clear)\b/i;
  let dialog = null;
  let activeResolve = null;

  const textFrom = element => [
    element?.getAttribute?.('data-developer-confirm'),
    element?.getAttribute?.('data-developer-confirm-title'),
    element?.getAttribute?.('data-developer-confirm-action'),
    element?.getAttribute?.('aria-label'),
    element?.getAttribute?.('name'),
    element?.getAttribute?.('value'),
    element?.textContent,
  ].filter(Boolean).join(' ');

  const needsConfirm = (form, submitter) => {
    if (!form) return false;
    if (form.hasAttribute('data-developer-confirm') || submitter?.hasAttribute?.('data-developer-confirm')) return true;
    const action = form.getAttribute('action') || '';
    if (destructiveWord.test(action)) return true;
    if (submitter && destructiveWord.test(textFrom(submitter))) return true;
    return false;
  };

  const optionFrom = (form, submitter, name, fallback) => (
    submitter?.getAttribute?.(`data-developer-confirm-${name}`)
    || form?.getAttribute?.(`data-developer-confirm-${name}`)
    || fallback
  );

  const formForSubmitter = submitter => {
    if (!(submitter instanceof HTMLElement)) return null;
    const explicitFormId = submitter.getAttribute('form');
    if (explicitFormId) {
      const explicitForm = document.getElementById(explicitFormId);
      if (explicitForm instanceof HTMLFormElement) return explicitForm;
    }
    return submitter.form instanceof HTMLFormElement ? submitter.form : submitter.closest('form');
  };

  const isSubmitter = element => {
    if (!(element instanceof HTMLElement)) return false;
    const tagName = element.tagName.toLowerCase();
    if (tagName !== 'button' && tagName !== 'input') return false;
    const type = (element.getAttribute('type') || (tagName === 'button' ? 'submit' : '')).toLowerCase();
    return type === 'submit' || type === 'image';
  };

  const ensureDialog = () => {
    if (dialog || !document.body) return dialog;
    dialog = document.createElement('div');
    dialog.className = 'developer-confirm-dialog';
    dialog.hidden = true;
    dialog.style.position = 'fixed';
    dialog.style.inset = '0';
    dialog.style.zIndex = '2400';
    dialog.style.alignItems = 'center';
    dialog.style.justifyContent = 'center';
    dialog.style.boxSizing = 'border-box';
    dialog.style.width = '100vw';
    dialog.style.height = '100vh';
    dialog.style.minHeight = '100dvh';
    dialog.style.margin = '0';
    dialog.style.display = 'none';
    dialog.innerHTML = `
      <div class="developer-confirm-backdrop" data-developer-confirm-cancel></div>
      <section class="developer-confirm-panel" role="dialog" aria-modal="true" aria-labelledby="developer-confirm-title" aria-describedby="developer-confirm-message">
        <div class="developer-confirm-icon" aria-hidden="true">!</div>
        <div class="developer-confirm-copy">
          <p class="feature-kicker">Confirm destructive action</p>
          <h2 class="developer-dashboard-section-title" id="developer-confirm-title">Delete item?</h2>
          <p class="content-text mb-0" id="developer-confirm-message">This action cannot be undone.</p>
        </div>
        <div class="developer-confirm-actions">
          <button class="btn btn-ghost btn-sm" type="button" data-developer-confirm-cancel>Cancel</button>
          <button class="btn btn-primary btn-sm developer-confirm-danger" type="button" data-developer-confirm-accept>Delete</button>
        </div>
      </section>
    `;
    document.body.insertBefore(dialog, document.body.firstChild);
    dialog.addEventListener('click', event => {
      if (event.target?.matches?.('[data-developer-confirm-cancel]')) {
        event.preventDefault();
        close(false);
      }
      if (event.target?.matches?.('[data-developer-confirm-accept]')) {
        event.preventDefault();
        close(true);
      }
    });
    dialog.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(false);
      }
    });
    return dialog;
  };

  const close = confirmed => {
    if (!dialog) return;
    dialog.style.display = 'none';
    dialog.hidden = true;
    document.documentElement.classList.remove('is-developer-confirm-open');
    const resolve = activeResolve;
    activeResolve = null;
    resolve?.(confirmed);
  };

  const ask = ({ title, message, action }) => new Promise(resolve => {
    const element = ensureDialog();
    if (!element) {
      resolve(false);
      return;
    }
    activeResolve?.(false);
    activeResolve = resolve;
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    element.querySelector('#developer-confirm-title').textContent = title;
    element.querySelector('#developer-confirm-message').textContent = message;
    element.querySelector('[data-developer-confirm-accept]').textContent = action;
    document.documentElement.classList.add('is-developer-confirm-open');
    element.hidden = false;
    element.style.display = 'flex';
    window.scrollTo(scrollX, scrollY);
    window.requestAnimationFrame(() => {
      const cancelButton = element.querySelector('[data-developer-confirm-cancel]');
      try {
        cancelButton?.focus({ preventScroll: true });
      } catch {
        cancelButton?.focus();
        window.scrollTo(scrollX, scrollY);
      }
    });
  });

  const confirmAndSubmit = (form, submitter, event) => {
    if (!form || !needsConfirm(form, submitter)) return false;
    if (form.getAttribute('data-developer-confirm-accepted') === 'true') {
      form.removeAttribute('data-developer-confirm-accepted');
      return false;
    }

    event?.preventDefault?.();
    event?.stopImmediatePropagation?.();
    ask({
      title: optionFrom(form, submitter, 'title', 'Delete this item?'),
      message: optionFrom(form, submitter, 'message', 'This destructive action cannot be undone. Confirm before FNLLA continues.'),
      action: optionFrom(form, submitter, 'action', destructiveWord.test(textFrom(submitter)) ? (submitter?.textContent || 'Delete').trim() : 'Confirm'),
    }).then(confirmed => {
      if (!confirmed) return;
      form.setAttribute('data-developer-confirm-accepted', 'true');
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
      } else {
        form.submit();
      }
    });
    return true;
  };

  document.addEventListener('click', event => {
    try {
      const submitter = event.target?.closest?.('button, input');
      if (!isSubmitter(submitter)) return;
      confirmAndSubmit(formForSubmitter(submitter), submitter, event);
    } catch (error) {
      warn(error);
    }
  }, true);

  document.addEventListener('submit', event => {
    try {
      const form = event.target instanceof HTMLFormElement ? event.target : event.target?.closest?.('form');
      if (!form) return;
      const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
      confirmAndSubmit(form, submitter, event);
    } catch (error) {
      warn(error);
    }
  }, true);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA runtime/service controls failed.', error);
  };

  const init = () => {
    try {
      document.querySelectorAll('[data-developer-runtime-form]').forEach(form => {
        if (form.hasAttribute('data-developer-runtime-form-ready')) return;
        form.setAttribute('data-developer-runtime-form-ready', 'true');
        const radios = Array.from(form.querySelectorAll('input[name="runtime_environment"]'));
        const diagnostics = Array.from(form.querySelectorAll('[data-developer-runtime-diagnostic]'));
        const sync = () => {
          const selected = radios.find(radio => radio.checked)?.value || 'development';
          const production = selected === 'production';
          diagnostics.forEach(label => {
            const input = label.querySelector('input[type="checkbox"]');
            const note = label.querySelector('small');
            if (note && !note.dataset.developerOriginalText) {
              note.dataset.developerOriginalText = note.textContent || '';
            }
            label.classList.toggle('is-forced-off', production);
            if (input) {
              input.disabled = production;
              if (production) input.checked = false;
            }
            if (note) {
              note.textContent = production ? 'Forced off while Production is selected.' : note.dataset.developerOriginalText;
            }
          });
        };
        radios.forEach(radio => radio.addEventListener('change', sync));
        sync();
      });

      document.querySelectorAll('select[name="developer_control_status"]').forEach(select => {
        if (select.hasAttribute('data-developer-service-control-ready')) return;
        select.setAttribute('data-developer-service-control-ready', 'true');
        const form = select.closest('form');
        const cards = Array.from(form?.querySelectorAll('[data-developer-service-scenario]') || []);
        const sync = () => {
          cards.forEach(card => card.classList.toggle('is-active', card.getAttribute('data-developer-service-scenario') === select.value));
        };
        select.addEventListener('change', sync);
        sync();
      });
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  document.addEventListener('fnlla:developer-panel-refresh', init);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer workspace plan failed.', error);
  };

  const init = () => {
    try {
      document.querySelectorAll('[data-developer-kanban-plan]').forEach(plan => {
        if (plan.hasAttribute('data-developer-kanban-plan-ready')) return;
        plan.setAttribute('data-developer-kanban-plan-ready', 'true');
        const buttons = Array.from(plan.querySelectorAll('[data-developer-kanban-plan-tab]'));
        const panels = Array.from(plan.querySelectorAll('[data-developer-kanban-plan-panel]'));
        if (buttons.length === 0 || panels.length === 0) return;

        const activate = name => {
          buttons.forEach(button => {
            const active = button.getAttribute('data-developer-kanban-plan-tab') === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', String(active));
            button.setAttribute('aria-pressed', String(active));
          });
          panels.forEach(panel => {
            panel.hidden = panel.getAttribute('data-developer-kanban-plan-panel') !== name;
          });
        };

        buttons.forEach(button => {
          button.addEventListener('click', () => activate(button.getAttribute('data-developer-kanban-plan-tab') || 'timeline'));
        });

        const active = buttons.find(button => button.classList.contains('is-active')) || buttons[0];
        activate(active.getAttribute('data-developer-kanban-plan-tab') || 'timeline');
      });

    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  document.addEventListener('fnlla:developer-panel-refresh', init);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA private task composer failed.', error);
  };

  const init = () => {
    try {
      let builderCount = 0;
      document.querySelectorAll('[data-private-todo-subtasks]').forEach(builder => {
        if (builder.hasAttribute('data-private-todo-subtasks-ready')) return;
        builder.setAttribute('data-private-todo-subtasks-ready', 'true');
        const list = builder.querySelector('[data-private-todo-subtask-list]');
        const addButton = builder.querySelector('[data-private-todo-subtask-add]');
        if (!list || !addButton) return;
        const rawId = builder.getAttribute('data-private-todo-subtasks-id') || `developer-private-todo-subtasks-${builderCount++}`;
        const idPrefix = rawId.replace(/[^A-Za-z0-9_-]/g, '-');

        const nextIndex = () => {
          let highest = -1;
          list.querySelectorAll('input[name^="developer_private_todo_subtasks["]').forEach(input => {
            const match = input.name.match(/\[(\d+)\]/);
            if (match) highest = Math.max(highest, Number(match[1]));
          });
          return highest + 1;
        };

        const createRow = index => {
          const row = document.createElement('div');
          row.className = 'developer-private-todo-subtask-row';

          const toggle = document.createElement('label');
          toggle.className = 'developer-private-todo-subtask-toggle';
          toggle.setAttribute('for', `${idPrefix}-done-${index}`);

          const checkbox = document.createElement('input');
          checkbox.id = `${idPrefix}-done-${index}`;
          checkbox.type = 'checkbox';
          checkbox.name = 'developer_private_todo_subtasks_done[]';
          checkbox.value = String(index);

          const mark = document.createElement('span');
          mark.setAttribute('aria-hidden', 'true');
          toggle.append(checkbox, mark);

          const label = document.createElement('label');
          label.className = 'visually-hidden';
          label.setAttribute('for', `${idPrefix}-${index}`);
          label.textContent = `Subtask ${index + 1}`;

          const input = document.createElement('input');
          input.className = 'input';
          input.id = `${idPrefix}-${index}`;
          input.name = `developer_private_todo_subtasks[${index}]`;
          input.type = 'text';
          input.maxLength = 140;
          input.placeholder = 'Optional step';

          row.append(toggle, label, input);
          return row;
        };

        addButton.addEventListener('click', () => {
          const row = createRow(nextIndex());
          list.append(row);
          row.querySelector('input[type="text"]')?.focus();
        });
      });
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  document.addEventListener('fnlla:developer-panel-refresh', init);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer documentation search failed.', error);
  };

  const init = () => {
    try {
      const input = document.querySelector('[data-developer-docs-search]');
      const cards = Array.from(document.querySelectorAll('[data-developer-docs-card]'));
      const empty = document.querySelector('[data-developer-docs-empty]');
      const count = document.querySelector('[data-developer-docs-count]');
      if (!input || cards.length === 0) return;
      if (input.hasAttribute('data-developer-docs-ready')) return;
      input.setAttribute('data-developer-docs-ready', 'true');

      const filter = () => {
        const terms = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let visible = 0;
        cards.forEach(card => {
          const haystack = `${card.getAttribute('data-developer-docs-search-text') || ''} ${card.textContent || ''}`.toLowerCase();
          const match = terms.length === 0 || terms.every(term => haystack.includes(term));
          card.hidden = !match;
          if (match) {
            visible++;
            if (terms.length > 0) card.open = true;
          }
        });
        if (empty) empty.hidden = visible > 0;
        if (count) count.textContent = `${visible} ${visible === 1 ? 'document' : 'documents'}`;
      };

      input.addEventListener('input', filter);
      filter();
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  document.addEventListener('fnlla:developer-panel-refresh', init);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer documentation navigation failed.', error);
  };

  const init = () => {
    try {
      const nav = document.querySelector('[data-developer-docs-nav]');
      if (!nav || nav.hasAttribute('data-developer-docs-nav-ready')) return;
      const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
      const sections = links.map(link => {
        const id = (link.getAttribute('href') || '').slice(1);
        return { id, link, section: id !== '' ? document.getElementById(id) : null };
      }).filter(item => item.section);
      if (sections.length === 0) return;

      nav.setAttribute('data-developer-docs-nav-ready', 'true');
      let lockedUntil = 0;

      const setActive = id => {
        let active = sections.find(item => item.id === id) || sections[0];
        sections.forEach(item => {
          const current = item === active;
          if (current) {
            item.link.setAttribute('aria-current', 'location');
          } else {
            item.link.removeAttribute('aria-current');
          }
        });
      };

      const activeFromScroll = () => {
        if (Date.now() < lockedUntil) return;
        const offset = Math.max(96, Math.round(window.innerHeight * 0.18));
        let active = sections[0];
        for (const item of sections) {
          const rect = item.section.getBoundingClientRect();
          if (rect.top <= offset) active = item;
          if (rect.top <= offset && rect.bottom > offset) {
            active = item;
            break;
          }
        }
        setActive(active.id);
      };

      let frame = null;
      const schedule = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(() => {
          frame = null;
          activeFromScroll();
        });
      };

      links.forEach(link => {
        link.addEventListener('click', () => {
          const id = (link.getAttribute('href') || '').slice(1);
          if (id !== '') {
            lockedUntil = Date.now() + 700;
            setActive(id);
          }
        });
      });

      window.addEventListener('scroll', schedule, { passive: true });
      window.addEventListener('resize', schedule);
      window.addEventListener('hashchange', () => {
        const id = window.location.hash.slice(1);
        if (id !== '') {
          lockedUntil = Date.now() + 700;
          setActive(id);
        }
      });

      const initial = window.location.hash.slice(1);
      if (initial !== '' && sections.some(item => item.id === initial)) {
        lockedUntil = Date.now() + 700;
        setActive(initial);
      } else {
        activeFromScroll();
      }
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  document.addEventListener('fnlla:developer-panel-refresh', init);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer AJAX action failed.', error);
  };

  const frameSelector = '[data-developer-panel-frame]';

  const runInlineScripts = root => {
    root.querySelectorAll('script:not([src])').forEach(script => {
      const replacement = document.createElement('script');
      Array.from(script.attributes).forEach(attribute => {
        replacement.setAttribute(attribute.name, attribute.value);
      });
      replacement.textContent = script.textContent;
      script.replaceWith(replacement);
    });
  };

  const sameOriginPath = url => {
    try {
      const parsed = new URL(url, window.location.href);
      if (parsed.origin !== window.location.origin) return '';
      return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch (_error) {
      return '';
    }
  };

  const formDataFrom = (form, submitter) => {
    try {
      return submitter ? new FormData(form, submitter) : new FormData(form);
    } catch (_error) {
      const data = new FormData(form);
      if (submitter?.name) data.append(submitter.name, submitter.value || '');
      return data;
    }
  };

  const replaceFrame = (html, responseUrl) => {
    const parser = new DOMParser();
    const nextDocument = parser.parseFromString(html, 'text/html');
    const currentFrame = document.querySelector(frameSelector);
    const nextFrame = nextDocument.querySelector(frameSelector);
    if (!currentFrame || !nextFrame) return false;

    currentFrame.innerHTML = nextFrame.innerHTML;
    if (nextDocument.title) document.title = nextDocument.title;
    runInlineScripts(currentFrame);
    document.dispatchEvent(new CustomEvent('fnlla:developer-panel-refresh', { detail: { frame: currentFrame } }));

    const nextPath = sameOriginPath(responseUrl);
    if (nextPath && nextPath !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
      window.history.pushState({}, '', nextPath);
    }

    return true;
  };

  document.addEventListener('submit', async event => {
    const form = event.target instanceof HTMLFormElement ? event.target : event.target?.closest?.('form');
    if (!form || !form.matches('[data-developer-ajax]')) return;
    if (!window.fetch || !window.FormData || !window.DOMParser) return;
    if ((form.method || 'get').toLowerCase() !== 'post') return;

    event.preventDefault();
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const frame = document.querySelector(frameSelector);
    const data = formDataFrom(form, submitter);
    const action = submitter?.getAttribute('formaction') || form.action || window.location.href;
    const method = submitter?.getAttribute('formmethod') || form.method || 'post';

    form.classList.add('is-ajax-pending');
    form.setAttribute('aria-busy', 'true');
    frame?.setAttribute('aria-busy', 'true');
    if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
      submitter.disabled = true;
    }

    try {
      const response = await window.fetch(action, {
        method: method.toUpperCase(),
        body: data,
        credentials: 'same-origin',
        headers: {
          Accept: 'text/html,application/xhtml+xml',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const html = await response.text();
      if (!response.ok || !replaceFrame(html, response.url || action)) {
        form.submit();
      }
    } catch (error) {
      warn(error);
      form.submit();
    } finally {
      form.classList.remove('is-ajax-pending');
      form.removeAttribute('aria-busy');
      frame?.removeAttribute('aria-busy');
      if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
        submitter.disabled = false;
      }
    }
  }, true);
})();

(() => {
  const warn = error => {
    if (window.console?.warn) console.warn('FNLLA developer panel tooltip failed.', error);
  };
  const tooltipTargets = '[data-fnlla-tooltip], .developer-info-tip[aria-label]';
  const tooltipReadyAttribute = 'data-fnlla-tooltip-ready';
  let tooltip = null;
  let activeTarget = null;
  let positionFrame = null;
  const tooltipId = 'developer-tooltip';
  const margin = 10;
  const gap = 10;

  const ensureTooltip = () => {
    if (tooltip) return tooltip;
    if (!document.body) return null;
    tooltip = document.createElement('div');
    tooltip.id = tooltipId;
    tooltip.className = 'developer-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);
    return tooltip;
  };

  const isElement = value => value instanceof Element;

  const elementFrom = value => {
    if (isElement(value)) return value;
    return value?.parentElement || null;
  };

  const infoTipText = target => {
    if (!target?.classList?.contains('developer-info-tip')) return '';
    for (const child of target.children) {
      if (child.tagName?.toLowerCase() === 'span') return (child.textContent || '').trim();
    }
    return '';
  };

  const tooltipText = target => (
    target?.getAttribute('data-fnlla-tooltip')
    || infoTipText(target)
    || target?.getAttribute('aria-label')
    || target?.getAttribute('title')
    || ''
  ).trim();

  const prepareTarget = target => {
    if (!target) return false;
    const ready = tooltipText(target) !== '';
    target.toggleAttribute(tooltipReadyAttribute, ready);
    if (target.classList?.contains('developer-info-tip')) {
      target.classList.toggle('is-fnlla-tooltip-enhanced', ready);
    }
    return ready;
  };

  const refreshTargets = root => {
    const start = elementFrom(root) || document;
    if (start !== document && start.matches?.(tooltipTargets)) prepareTarget(start);
    start.querySelectorAll?.(tooltipTargets).forEach(prepareTarget);
  };

  const targetFromEvent = event => {
    const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
    for (const node of path) {
      if (!isElement(node)) continue;
      const target = node.matches(tooltipTargets) ? node : node.closest?.(tooltipTargets);
      if (target) return target;
    }
    return elementFrom(event.target)?.closest?.(tooltipTargets) || null;
  };

  const restoreDescription = target => {
    if (!target || target.getAttribute('data-fnlla-tooltip-describedby-active') !== 'true') return;
    const previous = target.getAttribute('data-fnlla-tooltip-previous-describedby');
    if (previous === null || previous === '') {
      target.removeAttribute('aria-describedby');
    } else {
      target.setAttribute('aria-describedby', previous);
    }
    target.removeAttribute('data-fnlla-tooltip-describedby-active');
    target.removeAttribute('data-fnlla-tooltip-previous-describedby');
  };

  const describeTarget = target => {
    const existing = target.getAttribute('aria-describedby') || '';
    if (existing.split(/\s+/).includes(tooltipId)) return;
    target.setAttribute('data-fnlla-tooltip-previous-describedby', existing);
    target.setAttribute('data-fnlla-tooltip-describedby-active', 'true');
    target.setAttribute('aria-describedby', `${existing} ${tooltipId}`.trim());
  };

  const placementOrder = preferred => {
    const normalized = ['top', 'bottom', 'left', 'right'].includes(preferred) ? preferred : 'top';
    const opposite = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' }[normalized];
    return [normalized, opposite, 'top', 'bottom', 'right', 'left'].filter((value, index, values) => values.indexOf(value) === index);
  };

  const coordinates = (placement, targetRect, tipRect) => {
    if (placement === 'bottom') {
      return {
        top: targetRect.bottom + gap,
        left: targetRect.left + (targetRect.width / 2) - (tipRect.width / 2),
      };
    }
    if (placement === 'left') {
      return {
        top: targetRect.top + (targetRect.height / 2) - (tipRect.height / 2),
        left: targetRect.left - tipRect.width - gap,
      };
    }
    if (placement === 'right') {
      return {
        top: targetRect.top + (targetRect.height / 2) - (tipRect.height / 2),
        left: targetRect.right + gap,
      };
    }
    return {
      top: targetRect.top - tipRect.height - gap,
      left: targetRect.left + (targetRect.width / 2) - (tipRect.width / 2),
    };
  };

  const fitsViewport = (point, tipRect) => point.top >= margin
    && point.left >= margin
    && point.top + tipRect.height <= window.innerHeight - margin
    && point.left + tipRect.width <= window.innerWidth - margin;

  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

  const positionTooltip = () => {
    positionFrame = null;
    if (!tooltip || !activeTarget || tooltip.hidden || !activeTarget.isConnected) {
      hideTooltip();
      return;
    }

    const text = tooltipText(activeTarget);
    if (text === '') {
      hideTooltip();
      return;
    }

    const targetRect = activeTarget.getBoundingClientRect();
    if (targetRect.bottom < 0 || targetRect.top > window.innerHeight || targetRect.right < 0 || targetRect.left > window.innerWidth) {
      hideTooltip();
      return;
    }

    tooltip.textContent = text;
    tooltip.style.visibility = 'hidden';
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';

    const tipRect = tooltip.getBoundingClientRect();
    const preferred = activeTarget.getAttribute('data-fnlla-tooltip-position') || 'top';
    let selectedPlacement = 'top';
    let selectedPoint = coordinates(selectedPlacement, targetRect, tipRect);

    for (const placement of placementOrder(preferred)) {
      const point = coordinates(placement, targetRect, tipRect);
      if (fitsViewport(point, tipRect)) {
        selectedPlacement = placement;
        selectedPoint = point;
        break;
      }
    }

    tooltip.setAttribute('data-placement', selectedPlacement);
    tooltip.style.left = `${clamp(selectedPoint.left, margin, window.innerWidth - tipRect.width - margin)}px`;
    tooltip.style.top = `${clamp(selectedPoint.top, margin, window.innerHeight - tipRect.height - margin)}px`;
    tooltip.style.visibility = 'visible';
  };

  const schedulePosition = () => {
    if (!activeTarget || !tooltip || tooltip.hidden || positionFrame !== null) return;
    positionFrame = window.requestAnimationFrame(positionTooltip);
  };

  const hideTooltip = () => {
    if (positionFrame !== null) {
      window.cancelAnimationFrame(positionFrame);
      positionFrame = null;
    }
    if (!tooltip) return;
    restoreDescription(activeTarget);
    tooltip.hidden = true;
    tooltip.style.visibility = '';
    activeTarget = null;
  };

  const showTooltip = target => {
    const text = tooltipText(target);
    if (!target || text === '' || !prepareTarget(target)) {
      if (activeTarget === target) hideTooltip();
      return;
    }

    const tip = ensureTooltip();
    if (!tip) return;
    if (activeTarget === target && !tip.hidden) {
      tip.textContent = text;
      schedulePosition();
      return;
    }
    if (activeTarget && activeTarget !== target) restoreDescription(activeTarget);
    activeTarget = target;
    tip.textContent = text;
    tip.hidden = false;
    describeTarget(target);
    positionTooltip();
  };

  const handleEnter = event => {
    const target = targetFromEvent(event);
    if (target) showTooltip(target);
  };

  const handleMove = event => {
    const target = targetFromEvent(event);
    if (!target) return;
    if (target !== activeTarget) {
      showTooltip(target);
    } else {
      schedulePosition();
    }
  };

  const handleLeave = event => {
    const target = targetFromEvent(event);
    if (!target || target !== activeTarget) return;
    const related = elementFrom(event.relatedTarget);
    if (related && target.contains(related)) return;
    hideTooltip();
  };

  const init = () => {
    try {
      refreshTargets(document);
      document.addEventListener('pointerover', handleEnter, true);
      document.addEventListener('mouseover', handleEnter, true);
      document.addEventListener('pointermove', handleMove, true);
      document.addEventListener('pointerout', handleLeave, true);
      document.addEventListener('mouseout', handleLeave, true);
      document.addEventListener('focusin', handleEnter, true);
      document.addEventListener('focusout', event => {
        const target = targetFromEvent(event);
        if (target && target === activeTarget) hideTooltip();
      }, true);
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape') hideTooltip();
      });
      document.addEventListener('click', event => {
        if (!targetFromEvent(event)) hideTooltip();
      });
      if (window.MutationObserver) {
        new MutationObserver(mutations => {
          mutations.forEach(mutation => {
            if (mutation.type === 'attributes') refreshTargets(mutation.target);
            mutation.addedNodes?.forEach(refreshTargets);
          });
          if (activeTarget) {
            if (!prepareTarget(activeTarget)) {
              hideTooltip();
            } else {
              schedulePosition();
            }
          }
        }).observe(document.documentElement, {
          subtree: true,
          childList: true,
          attributes: true,
          attributeFilter: ['data-fnlla-tooltip', 'aria-label', 'title'],
        });
      }
      window.addEventListener('scroll', schedulePosition, true);
      window.addEventListener('resize', schedulePosition);
    } catch (error) {
      warn(error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
