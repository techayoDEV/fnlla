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
    if (window.console?.warn) console.warn('FNLLA developer command palette failed.', error);
  };

  const init = () => {
    try {
      const palette = document.querySelector('[data-developer-command-palette]');
      const openButtons = document.querySelectorAll('[data-developer-command-open]');
      const closeButtons = palette?.querySelectorAll('[data-developer-command-close]') || [];
      const input = palette?.querySelector('[data-developer-command-input]');
      const items = Array.from(palette?.querySelectorAll('[data-developer-command-item]') || []);
      const empty = palette?.querySelector('[data-developer-command-empty]');
      let activeIndex = -1;
      if (!palette || !input || items.length === 0) return;

      const setExpanded = value => {
        openButtons.forEach(button => button.setAttribute('aria-expanded', String(value)));
      };

      const visibleItems = () => items.filter(item => !item.hidden);

      const setActive = index => {
        const visible = visibleItems();
        items.forEach(item => {
          item.classList.remove('is-active');
          item.setAttribute('aria-selected', 'false');
        });
        if (visible.length === 0) {
          activeIndex = -1;
          input.removeAttribute('aria-activedescendant');
          return;
        }
        activeIndex = ((index % visible.length) + visible.length) % visible.length;
        const active = visible[activeIndex];
        active.classList.add('is-active');
        active.setAttribute('aria-selected', 'true');
        if (!active.id) {
          active.id = `developer-command-option-${items.indexOf(active)}`;
        }
        input.setAttribute('aria-activedescendant', active.id);
        active.scrollIntoView({ block: 'nearest' });
      };

      const filter = () => {
        const terms = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let visible = 0;
        items.forEach(item => {
          const haystack = item.getAttribute('data-developer-command-search') || item.textContent.toLowerCase();
          const match = terms.length === 0 || terms.every(term => haystack.indexOf(term) !== -1);
          item.hidden = !match;
          if (match) visible++;
        });
        if (empty) empty.hidden = visible > 0;
        setActive(0);
      };

      const open = () => {
        palette.hidden = false;
        document.documentElement.classList.add('has-developer-command-palette');
        setExpanded(true);
        input.value = '';
        filter();
        window.setTimeout(() => input.focus(), 0);
      };

      const close = () => {
        palette.hidden = true;
        document.documentElement.classList.remove('has-developer-command-palette');
        setExpanded(false);
      };

      openButtons.forEach(button => button.addEventListener('click', open));
      closeButtons.forEach(button => button.addEventListener('click', close));
      input.addEventListener('input', filter);
      input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          setActive(activeIndex + 1);
          return;
        }
        if (event.key === 'ArrowUp') {
          event.preventDefault();
          setActive(activeIndex - 1);
          return;
        }
        if (event.key === 'Enter') {
          const target = visibleItems()[activeIndex] || visibleItems()[0];
          if (!target) return;
          event.preventDefault();
          window.location.assign(target.href);
        }
      });
      document.addEventListener('keydown', event => {
        const target = event.target;
        const typing = target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
          event.preventDefault();
          palette.hidden ? open() : close();
          return;
        }
        if (event.key === 'Escape' && !palette.hidden) {
          event.preventDefault();
          close();
          openButtons[0]?.focus();
          return;
        }
        if (!typing && event.key === '/' && palette.hidden) {
          event.preventDefault();
          open();
        }
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
