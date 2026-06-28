/*
  ============================================================================
  FNLLA UI SOURCE MODULE: PREAMBLE AND SHARED STATE
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

/*
  FNLLA UI runtime script.
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  Produced, maintained and distributed by TechAyo LTD (techayo.co.uk).
  Public runtime asset names and enhancement markers define the supported runtime contract.
*/

/*
  Runtime wrapper:
  - creates one private scope
  - exposes only the public `window.FNLLAUI` API
  - keeps shared state hidden from page-level scripts
*/
(function () {
  "use strict";

  /* Public version marker exposed through the runtime API. */
  var fnllaUiVersion = "1.0.4";
  var openLayerStack = [];
  var openModalStack = [];
  var openOffcanvasStack = [];
  var modalTriggerMap = new WeakMap();
  var offcanvasTriggerMap = new WeakMap();
  var overlayIsolationStateMap = new Map();
  var toastTimerMap = new WeakMap();
  var tooltipPanelMap = new WeakMap();
  var scrollspyObserverMap = new WeakMap();
  var customSelectStateMap = new WeakMap();
  var scrollspyRegistry = [];
  var fnllaUiIdCounter = 0;
  var mobileNavQuery = window.matchMedia ? window.matchMedia("(max-width: 880px)") : null;
  var runtimeEnhancementClass = "fnlla-ui-js";

  /*
    Initialization registry:
    every interactive node is marked after first binding so repeated
    `FNLLAUI.init(root)` calls stay safe and idempotent.
  */
  var initializationState = {
    dropdown: new WeakSet(),
    navToggle: new WeakSet(),
    tabs: new WeakSet(),
    accordionButton: new WeakSet(),
    modalTrigger: new WeakSet(),
    modal: new WeakSet(),
    modalClose: new WeakSet(),
    toastTrigger: new WeakSet(),
    toast: new WeakSet(),
    toastClose: new WeakSet(),
    offcanvasTrigger: new WeakSet(),
    offcanvas: new WeakSet(),
    offcanvasClose: new WeakSet(),
    popover: new WeakSet(),
    popoverTrigger: new WeakSet(),
    popoverClose: new WeakSet(),
    tooltipTrigger: new WeakSet(),
    select: new WeakSet(),
    rangeInput: new WeakSet(),
    scrollspy: new WeakSet()
  };
  /*
    Global runtime bindings:
    these are document-level listeners and watchers that should only be
    attached once no matter how many subtrees are initialized.
  */
  var runtimeBindings = {
    mediaQuery: false,
    documentClick: false,
    documentKeydown: false,
    autoInit: false,
    scrollspyCleanupObserver: null
  };
  var attributeNames = {
    accordionSingle: "data-fnlla-accordion-single",
    modalOpen: "data-fnlla-modal-open",
    toastOpen: "data-fnlla-toast-open",
    toastAutohide: "data-fnlla-toast-autohide",
    offcanvasOpen: "data-fnlla-offcanvas-open",
    rangeOutput: "data-fnlla-range-output",
    rangePrefix: "data-fnlla-range-prefix",
    rangeSuffix: "data-fnlla-range-suffix",
    tooltip: "data-fnlla-tooltip",
    tooltipPosition: "data-fnlla-tooltip-position"
  };
  /* Shared selectors used across all modules. */
  var selectors = {
    dropdown: "[data-fnlla-dropdown]",
    dropdownToggle: "[data-fnlla-dropdown-toggle]",
    dropdownMenu: ".dropdown-menu",
    navToggle: "[data-fnlla-nav-toggle]",
    tabs: "[data-fnlla-tabs]",
    tabList: "[data-fnlla-tab-list]",
    tab: "[data-fnlla-tab]",
    accordion: "[data-fnlla-accordion]",
    accordionButton: "[data-fnlla-accordion-button]",
    accordionItem: ".accordion-item",
    modalTrigger: "[data-fnlla-modal-open]",
    modal: "[data-fnlla-modal]",
    modalClose: "[data-fnlla-modal-close]",
    modalInitialFocus: "[data-fnlla-modal-initial-focus], [autofocus]",
    toastTrigger: "[data-fnlla-toast-open]",
    toast: "[data-fnlla-toast]",
    toastClose: "[data-fnlla-toast-close]",
    offcanvasTrigger: "[data-fnlla-offcanvas-open]",
    offcanvas: "[data-fnlla-offcanvas]",
    offcanvasClose: "[data-fnlla-offcanvas-close]",
    offcanvasInitialFocus: "[data-fnlla-offcanvas-initial-focus], [autofocus]",
    select: "select.select",
    selectShell: "[data-fnlla-select-shell]",
    selectNative: "[data-fnlla-select-native]",
    selectToggle: "[data-fnlla-select-toggle]",
    selectMenu: ".select-menu",
    selectOption: "[data-fnlla-select-option]",
    rangeInput: ".range-input[id]",
    popover: "[data-fnlla-popover]",
    popoverToggle: "[data-fnlla-popover-toggle]",
    popoverPanel: ".popover-panel",
    popoverClose: "[data-fnlla-popover-close]",
    tooltipTrigger: "[data-fnlla-tooltip]",
    scrollspy: "[data-fnlla-scrollspy]",
    scrollspyNav: "[data-fnlla-scrollspy-nav]"
  };
  /* Shared ID prefixes used when markup does not provide explicit IDs. */
  var idPrefixes = {
    dropdownToggle: "dropdown-toggle",
    dropdownMenu: "dropdown-menu",
    tabButton: "tab-button",
    tabPanel: "tab-panel",
    accordionButton: "accordion-button",
    accordionPanel: "accordion-panel",
    modal: "modal",
    toast: "toast",
    offcanvas: "offcanvas",
    selectToggle: "select-toggle",
    selectMenu: "select-menu",
    popoverToggle: "popover-toggle",
    popoverPanel: "popover-panel",
    tooltip: "tooltip"
  };

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: DOM AND FOCUS HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Normalize array-like DOM collections into real arrays. */
  function toArray(collection) {
    return Array.prototype.slice.call(collection || []);
  }

  /* Treat document-like roots as the global document scope. */
  function isDocumentRoot(root) {
    return !root || root === document || root === document.documentElement || root === document.body;
  }

  /* Accept documents, elements and fragments. Fall back to `document`. */
  function normalizeRoot(root) {
    if (isDocumentRoot(root)) {
      return document;
    }

    if (root && (root.nodeType === 1 || root.nodeType === 9 || root.nodeType === 11)) {
      return root;
    }

    return document;
  }

  /* Query a scope while also supporting the root node itself as a match. */
  function getScopedMatches(root, selector) {
    var scope = normalizeRoot(root);
    var matches = [];

    if (scope === document) {
      return toArray(document.querySelectorAll(selector));
    }

    if (typeof scope.matches === "function" && scope.matches(selector)) {
      matches.push(scope);
    }

    if (typeof scope.querySelectorAll === "function") {
      matches = matches.concat(toArray(scope.querySelectorAll(selector)));
    }

    return matches;
  }

  /* Generate readable unique IDs for components that need them. */
  function createFnllaUiId(prefix) {
    fnllaUiIdCounter += 1;
    return prefix + "-" + fnllaUiIdCounter;
  }

  /* Apply or clear inert state used to remove hidden regions from focus flow. */
  function setElementInertState(element, shouldBeInert) {
    if (!element) {
      return;
    }

    if (shouldBeInert) {
      element.setAttribute("inert", "");
      return;
    }

    element.removeAttribute("inert");
  }

  /* Ignore nodes hidden through HTML, ARIA or inert state. */
  function isElementVisibleForFocus(element) {
    if (!element) {
      return false;
    }

    return !element.closest("[hidden], [aria-hidden='true'], [inert]");
  }

  /* Ignore controls disabled through a parent fieldset unless the first legend owns them. */
  function isElementDisabledByFieldset(element) {
    if (!element || typeof element.closest !== "function") {
      return false;
    }

    var fieldset = element.closest("fieldset[disabled]");

    if (!fieldset) {
      return false;
    }

    var firstLegend = fieldset.querySelector("legend");

    return !firstLegend || !firstLegend.contains(element);
  }

  /* Reject disconnected or CSS-hidden nodes before sending focus to them. */
  function isElementRendered(element) {
    if (!element || element.isConnected === false) {
      return false;
    }

    if (typeof window.getComputedStyle !== "function") {
      return true;
    }

    var computed = window.getComputedStyle(element);

    if (!computed) {
      return true;
    }

    if (computed.display === "none" || computed.visibility === "hidden" || computed.visibility === "collapse") {
      return false;
    }

    return true;
  }

  /* Check whether a node can safely receive focus right now. */
  function canReceiveFocus(element) {
    if (!element || typeof element.focus !== "function") {
      return false;
    }

    if (!isElementVisibleForFocus(element)) {
      return false;
    }

    if (!isElementRendered(element)) {
      return false;
    }

    if (element.hasAttribute("disabled") || element.getAttribute("aria-disabled") === "true") {
      return false;
    }

    if (element.getAttribute("tabindex") === "-1") {
      return false;
    }

    if (isElementDisabledByFieldset(element)) {
      return false;
    }

    return true;
  }

  /* Collect all focusable descendants inside a given container. */
  function getFocusableElements(container) {
    if (!container) {
      return [];
    }

    return toArray(container.querySelectorAll("button:not([disabled]), [href], input:not([disabled]):not([type='hidden']), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])"))
      .filter(function (element) {
        return canReceiveFocus(element) && element.getAttribute("aria-disabled") !== "true";
      });
  }

  /* Resolve the single element controlled by an aria-controls trigger. */
  function getControlledElement(trigger) {
    var controlsId = trigger ? trigger.getAttribute("aria-controls") : "";

    if (controlsId) {
      return document.getElementById(controlsId);
    }

    return null;
  }

  /* Resolve a modal selector while safely ignoring invalid selectors. */
  function resolveModalBySelector(selector) {
    if (!selector) {
      return null;
    }

    try {
      return document.querySelector(selector);
    } catch (error) {
      return null;
    }
  }

  /* Resolve a selector string or direct element reference. */
  function resolveElementReference(target, selector) {
    if (!target) {
      return null;
    }

    if (typeof target === "string") {
      try {
        return document.querySelector(target);
      } catch (error) {
        return null;
      }
    }

    if (target.nodeType === 1 && (!selector || target.matches(selector))) {
      return target;
    }

    return null;
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: DROPDOWN STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Close one dropdown and optionally return focus to its toggle. */
  function closeDropdown(dropdown, options) {
    var settings = options || {};
    var toggle = dropdown.querySelector(selectors.dropdownToggle);
    var menu = dropdown.querySelector(selectors.dropdownMenu);

    dropdown.classList.remove("is-open");

    if (toggle) {
      toggle.setAttribute("aria-expanded", "false");
    }

    if (menu) {
      menu.hidden = true;
      menu.setAttribute("aria-hidden", "true");
      setElementInertState(menu, true);
    }

    if (settings.restoreFocus && canReceiveFocus(toggle)) {
      toggle.focus();
    }
  }

  /* Move focus to the first or last interactive item inside the menu. */
  function focusDropdownItem(dropdown, direction) {
    var menu = dropdown.querySelector(selectors.dropdownMenu);
    var items = getFocusableElements(menu);

    if (!items.length) {
      return;
    }

    if (direction === "last") {
      items[items.length - 1].focus();
      return;
    }

    items[0].focus();
  }

  /* Open one dropdown and optionally move focus inside it. */
  function openDropdown(dropdown, options) {
    var settings = options || {};
    var toggle = dropdown.querySelector(selectors.dropdownToggle);
    var menu = dropdown.querySelector(selectors.dropdownMenu);

    dropdown.classList.add("is-open");

    if (toggle) {
      toggle.setAttribute("aria-expanded", "true");
    }

    if (menu) {
      menu.hidden = false;
      menu.setAttribute("aria-hidden", "false");
      setElementInertState(menu, false);
    }

    if (settings.focusItem) {
      focusDropdownItem(dropdown, settings.focusItem);
    }
  }

  /* Close every dropdown except the one explicitly preserved. */
  function closeAllDropdowns(exceptDropdown) {
    toArray(document.querySelectorAll(selectors.dropdown)).forEach(function (dropdown) {
      if (dropdown !== exceptDropdown) {
        closeDropdown(dropdown);
      }
    });
  }

  /* Toggle one dropdown while ensuring peer dropdowns are closed first. */
  function toggleDropdown(dropdown) {
    var isOpen = dropdown.classList.contains("is-open");

    closeAllDropdowns(isOpen ? null : dropdown);

    if (isOpen) {
      closeDropdown(dropdown);
    } else {
      openDropdown(dropdown);
    }
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: NAVIGATION STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Synchronize visual state, ARIA state and focus behavior for one menu. */
  function syncNavTargetState(toggle, target, expanded, options) {
    var settings = options || {};

    target.classList.toggle("is-open", expanded);
    target.setAttribute("aria-hidden", expanded ? "false" : "true");
    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    setElementInertState(target, !expanded && isMobileNavigation());

    if (!expanded && settings.restoreFocus && canReceiveFocus(toggle)) {
      toggle.focus();
    }
  }

  /* Central mobile-navigation breakpoint check used by all nav logic. */
  function isMobileNavigation() {
    return mobileNavQuery ? mobileNavQuery.matches : false;
  }

  /* Reconcile navigation markup whenever the viewport mode changes. */
  function syncNavigationMode(root) {
    getScopedMatches(root, selectors.navToggle).forEach(function (toggle) {
      var target = getControlledElement(toggle);

      if (!target) {
        return;
      }

      if (isMobileNavigation()) {
        if (!target.classList.contains("is-open")) {
          target.setAttribute("aria-hidden", "true");
          setElementInertState(target, true);
        } else {
          target.setAttribute("aria-hidden", "false");
          setElementInertState(target, false);
        }
        return;
      }

      target.classList.remove("is-open");
      target.setAttribute("aria-hidden", "false");
      toggle.setAttribute("aria-expanded", "false");
      setElementInertState(target, false);
    });
  }

  /* Close every currently open mobile navigation panel. */
  function closeOpenNavigation(options) {
    var settings = options || {};

    if (!isMobileNavigation()) {
      return;
    }

    toArray(document.querySelectorAll(selectors.navToggle)).forEach(function (toggle) {
      var target = getControlledElement(toggle);

      if (!target || !target.classList.contains("is-open")) {
        return;
      }

      syncNavTargetState(toggle, target, false, {
        restoreFocus: settings.restoreFocus
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: MODAL STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Prefer an explicit initial-focus target, then fall back to first focusable. */
  function getModalInitialFocusTarget(modal) {
    if (!modal) {
      return null;
    }

    var preferredTarget = modal.querySelector(selectors.modalInitialFocus);
    if (canReceiveFocus(preferredTarget)) {
      return preferredTarget;
    }

    return getFocusableElements(modal)[0] || null;
  }

  /* Collect page branches that must be isolated while a modal is open. */
  function collectModalIsolationTargets(modal) {
    var targets = [];
    var current = modal;

    while (current && current !== document.body) {
      var parent = current.parentElement;

      if (!parent) {
        break;
      }

      toArray(parent.children).forEach(function (sibling) {
        if (sibling !== current && sibling.tagName !== "SCRIPT" && targets.indexOf(sibling) === -1) {
          targets.push(sibling);
        }
      });

      current = parent;
    }

    return targets;
  }

  /* Keep one ordered stack for every active dialog-like layer. */
  function trackOpenLayer(layer) {
    if (!layer) {
      return;
    }

    openLayerStack = openLayerStack.filter(function (item) {
      return item !== layer;
    });
    openLayerStack.push(layer);
  }

  /* Remove one layer from the active stack without disturbing the rest. */
  function untrackOpenLayer(layer) {
    openLayerStack = openLayerStack.filter(function (item) {
      return item !== layer;
    });
  }

  /* Return the top-most active dialog or panel across all overlay families. */
  function getTopOpenLayer() {
    return openLayerStack.length ? openLayerStack[openLayerStack.length - 1] : null;
  }

  /* Restore baseline accessibility state before replaying the active layers. */
  function restoreOverlayIsolationState() {
    overlayIsolationStateMap.forEach(function (state, element) {
      if (!state || !element) {
        return;
      }

      if (state.ariaHidden === null) {
        element.removeAttribute("aria-hidden");
      } else {
        element.setAttribute("aria-hidden", state.ariaHidden);
      }

      setElementInertState(element, state.wasInert);
    });

    overlayIsolationStateMap.clear();
  }

  /* Record one element's baseline state before a layer overrides it. */
  function rememberOverlayIsolationState(element) {
    if (!element || overlayIsolationStateMap.has(element)) {
      return;
    }

    overlayIsolationStateMap.set(element, {
      ariaHidden: element.getAttribute("aria-hidden"),
      wasInert: element.hasAttribute("inert")
    });
  }

  /* Apply isolation rules for one open dialog-like layer. */
  function applyLayerIsolation(layer) {
    collectModalIsolationTargets(layer).forEach(function (element) {
      rememberOverlayIsolationState(element);
      element.setAttribute("aria-hidden", "true");
      setElementInertState(element, true);
    });
  }

  /* Reveal the active layer and its ancestor branch before replaying isolation. */
  function revealLayerBranch(layer) {
    var current = layer;

    while (current && current !== document.body) {
      rememberOverlayIsolationState(current);
      current.setAttribute("aria-hidden", "false");
      setElementInertState(current, false);
      current = current.parentElement;
    }
  }

  /* Rebuild isolation from the current stack so nested layers stay consistent. */
  function syncOpenLayerIsolation() {
    restoreOverlayIsolationState();
    openLayerStack.forEach(function (layer) {
      revealLayerBranch(layer);
      applyLayerIsolation(layer);
    });
  }

  /* Keep body scroll locked whenever at least one active layer remains open. */
  function syncDocumentScrollLock() {
    if (openLayerStack.length) {
      document.body.style.overflow = "hidden";
      return;
    }

    document.body.style.removeProperty("overflow");
  }

  /* Close transient menus before elevating a blocking overlay above the page. */
  function closeTransientUi() {
    closeAllDropdowns(null);
    closeAllPopovers(null);
    closeOpenNavigation();
  }

  /* Pick the best focus destination for the currently active top layer. */
  function focusOpenLayer(layer) {
    if (!layer) {
      return;
    }

    var preferredSelector = null;

    if (layer.matches(selectors.modal)) {
      preferredSelector = selectors.modalInitialFocus;
    } else if (layer.matches(selectors.offcanvas)) {
      preferredSelector = selectors.offcanvasInitialFocus;
    }

    if (preferredSelector) {
      var preferredTarget = layer.querySelector(preferredSelector);

      if (canReceiveFocus(preferredTarget)) {
        preferredTarget.focus();
        return;
      }
    }

    var firstFocusable = getFocusableElements(layer)[0] || null;

    if (firstFocusable) {
      firstFocusable.focus();
      return;
    }

    if (!layer.hasAttribute("tabindex")) {
      layer.setAttribute("tabindex", "-1");
    }

    layer.focus();
  }

  /* Close one modal, restore isolation and return focus only when it is safe. */
  function closeModal(modal) {
    if (!modal) {
      return;
    }

    var isTracked = openModalStack.indexOf(modal) !== -1 || openLayerStack.indexOf(modal) !== -1;

    if (!isTracked && modal.hidden) {
      return;
    }

    var wasTopLayer = getTopOpenLayer() === modal;
    var trigger = modalTriggerMap.get(modal);

    modal.hidden = true;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    setElementInertState(modal, true);
    openModalStack = openModalStack.filter(function (item) {
      return item !== modal;
    });
    untrackOpenLayer(modal);
    syncOpenLayerIsolation();
    syncDocumentScrollLock();

    if (!wasTopLayer) {
      return;
    }

    var nextTopLayer = getTopOpenLayer();

    if (nextTopLayer) {
      focusOpenLayer(nextTopLayer);
      return;
    }

    if (canReceiveFocus(trigger)) {
      trigger.focus();
    }
  }

  /* Open one modal, lock body scroll and move focus inside the dialog. */
  function openModal(modal, trigger) {
    if (!modal) {
      return;
    }

    closeTransientUi();
    modal.hidden = false;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    setElementInertState(modal, false);
    modalTriggerMap.set(modal, trigger || modalTriggerMap.get(modal) || document.activeElement);

    if (openModalStack.indexOf(modal) === -1) {
      openModalStack.push(modal);
    }

    trackOpenLayer(modal);
    syncOpenLayerIsolation();
    syncDocumentScrollLock();
    focusOpenLayer(modal);
  }

  /* Close the most recently opened modal first. */
  function closeTopModal() {
    if (!openModalStack.length) {
      return;
    }

    closeModal(openModalStack[openModalStack.length - 1]);
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: ACCORDION STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Collapse one accordion item and remove it from the focus flow. */
  function closeAccordionPanel(button, panel) {
    button.setAttribute("aria-expanded", "false");
    panel.hidden = true;
    panel.setAttribute("aria-hidden", "true");
    setElementInertState(panel, true);

    var item = button.closest(selectors.accordionItem);

    if (item) {
      item.classList.remove("is-open");
    }
  }

  /* Expand one accordion item and restore its panel to the focus flow. */
  function openAccordionPanel(button, panel) {
    button.setAttribute("aria-expanded", "true");
    panel.hidden = false;
    panel.setAttribute("aria-hidden", "false");
    setElementInertState(panel, false);

    var item = button.closest(selectors.accordionItem);

    if (item) {
      item.classList.add("is-open");
    }
  }

  /* In single-open accordions, close every sibling except the current one. */
  function closeSiblingAccordionItems(group, currentButton) {
    toArray(group.querySelectorAll(selectors.accordionButton)).forEach(function (button) {
      if (button === currentButton) {
        return;
      }

      var panel = getControlledElement(button);

      if (panel) {
        closeAccordionPanel(button, panel);
      }
    });
  }

  /* Support Arrow, Home and End navigation across accordion triggers. */
  function focusAccordionTrigger(group, currentButton, direction) {
    var buttons = toArray(group.querySelectorAll(selectors.accordionButton));
    var currentIndex = buttons.indexOf(currentButton);

    if (!buttons.length || currentIndex === -1) {
      return;
    }

    if (direction === "first") {
      buttons[0].focus();
      return;
    }

    if (direction === "last") {
      buttons[buttons.length - 1].focus();
      return;
    }

    if (direction === "next") {
      buttons[(currentIndex + 1) % buttons.length].focus();
      return;
    }

    if (direction === "previous") {
      buttons[(currentIndex - 1 + buttons.length) % buttons.length].focus();
    }
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TABS STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Only treat tab buttons with a real controlled panel as valid tabs. */
  function getTabButtons(group) {
    return toArray(group.querySelectorAll(selectors.tab)).filter(function (button) {
      return !!getControlledElement(button);
    });
  }

  /* Update selected tab state and panel visibility for one tab group. */
  function activateTab(group, nextButton, options) {
    var settings = options || {};
    var buttons = getTabButtons(group);

    if (!nextButton || buttons.indexOf(nextButton) === -1) {
      return;
    }

    buttons.forEach(function (button) {
      var panel = getControlledElement(button);
      var isSelected = button === nextButton;

      button.setAttribute("aria-selected", isSelected ? "true" : "false");
      button.setAttribute("tabindex", isSelected ? "0" : "-1");

      if (!panel) {
        return;
      }

      panel.hidden = !isSelected;
      panel.setAttribute("aria-hidden", isSelected ? "false" : "true");
      setElementInertState(panel, !isSelected);
    });

    if (settings.focusButton) {
      nextButton.focus();
    }
  }

  /* Move focus and selection across the valid tab buttons in a group. */
  function focusTabButton(group, currentButton, direction) {
    var buttons = getTabButtons(group);
    var currentIndex = buttons.indexOf(currentButton);

    if (!buttons.length || currentIndex === -1) {
      return;
    }

    if (direction === "first") {
      activateTab(group, buttons[0], { focusButton: true });
      return;
    }

    if (direction === "last") {
      activateTab(group, buttons[buttons.length - 1], { focusButton: true });
      return;
    }

    if (direction === "next") {
      activateTab(group, buttons[(currentIndex + 1) % buttons.length], { focusButton: true });
      return;
    }

    if (direction === "previous") {
      activateTab(group, buttons[(currentIndex - 1 + buttons.length) % buttons.length], { focusButton: true });
    }
  }

  /* Read tablist orientation so keyboard behavior can match the axis. */
  function getTabListOrientation(group) {
    var tabList = group.querySelector(selectors.tabList);
    var orientation = tabList ? tabList.getAttribute("aria-orientation") : "";

    return orientation === "vertical" ? "vertical" : "horizontal";
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: FOCUS MANAGEMENT HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Keep Tab navigation inside the currently active overlay or dialog layer. */
  function trapFocusInModal(event) {
    if (event.key !== "Tab") {
      return;
    }

    var activeLayer = getTopOpenLayer();

    if (!activeLayer) {
      return;
    }

    var focusable = getFocusableElements(activeLayer);

    if (!focusable.length) {
      event.preventDefault();
      return;
    }

    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    var activeElement = document.activeElement;

    if (!activeLayer.contains(activeElement)) {
      event.preventDefault();

      if (event.shiftKey) {
        last.focus();
      } else {
        first.focus();
      }

      return;
    }

    if (event.shiftKey && activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TOAST STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Clear any pending auto-hide timer before changing toast state. */
  function clearToastTimer(toast) {
    var timerId = toastTimerMap.get(toast);

    if (timerId) {
      window.clearTimeout(timerId);
      toastTimerMap.delete(toast);
    }
  }

  /* Start a new auto-hide timer when the toast requests it. */
  function scheduleToastAutoHide(toast) {
    var delay = parseInt(toast.getAttribute(attributeNames.toastAutohide), 10);

    clearToastTimer(toast);

    if (!delay || delay < 0) {
      return;
    }

    toastTimerMap.set(toast, window.setTimeout(function () {
      hideToast(toast);
    }, delay));
  }

  /* Reveal one toast and arm its optional auto-hide timer. */
  function showToast(toast) {
    if (!toast) {
      return;
    }

    if (!toast.id) {
      toast.id = createFnllaUiId(idPrefixes.toast);
    }

    toast.hidden = false;
    toast.classList.add("is-visible");
    toast.setAttribute("aria-hidden", "false");
    scheduleToastAutoHide(toast);
  }

  /* Hide one toast immediately and clear any pending timer. */
  function hideToast(toast) {
    if (!toast) {
      return;
    }

    clearToastTimer(toast);
    toast.classList.remove("is-visible");
    toast.setAttribute("aria-hidden", "true");
    toast.hidden = true;
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: OFFCANVAS STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Prefer an explicit initial-focus target, then fall back to the first control. */
  function getOffcanvasInitialFocusTarget(offcanvas) {
    if (!offcanvas) {
      return null;
    }

    var preferredTarget = offcanvas.querySelector(selectors.offcanvasInitialFocus);
    if (canReceiveFocus(preferredTarget)) {
      return preferredTarget;
    }

    return getFocusableElements(offcanvas)[0] || null;
  }

  /* Close one offcanvas panel, restore body scroll and return focus if possible. */
  function closeOffcanvas(offcanvas) {
    if (!offcanvas) {
      return;
    }

    var isTracked = openOffcanvasStack.indexOf(offcanvas) !== -1 || openLayerStack.indexOf(offcanvas) !== -1;

    if (!isTracked && offcanvas.hidden) {
      return;
    }

    var wasTopLayer = getTopOpenLayer() === offcanvas;
    var trigger = offcanvasTriggerMap.get(offcanvas);

    offcanvas.hidden = true;
    offcanvas.classList.remove("is-open");
    offcanvas.setAttribute("aria-hidden", "true");
    setElementInertState(offcanvas, true);
    openOffcanvasStack = openOffcanvasStack.filter(function (item) {
      return item !== offcanvas;
    });
    untrackOpenLayer(offcanvas);
    syncOpenLayerIsolation();
    syncDocumentScrollLock();

    if (!wasTopLayer) {
      return;
    }

    var nextTopLayer = getTopOpenLayer();

    if (nextTopLayer) {
      focusOpenLayer(nextTopLayer);
      return;
    }

    if (canReceiveFocus(trigger)) {
      trigger.focus();
    }
  }

  /* Open one offcanvas panel, lock body scroll and move focus into the panel. */
  function openOffcanvas(offcanvas, trigger) {
    if (!offcanvas) {
      return;
    }

    closeTransientUi();
    offcanvas.hidden = false;
    offcanvas.classList.add("is-open");
    offcanvas.setAttribute("aria-hidden", "false");
    setElementInertState(offcanvas, false);
    offcanvasTriggerMap.set(offcanvas, trigger || offcanvasTriggerMap.get(offcanvas) || document.activeElement);

    if (openOffcanvasStack.indexOf(offcanvas) === -1) {
      openOffcanvasStack.push(offcanvas);
    }

    trackOpenLayer(offcanvas);
    syncOpenLayerIsolation();
    syncDocumentScrollLock();
    focusOpenLayer(offcanvas);
  }

  /* Close whichever blocking layer is actually on top of the stack right now. */
  function closeTopOpenLayer() {
    var topLayer = getTopOpenLayer();

    if (!topLayer) {
      return;
    }

    if (topLayer.matches(selectors.modal)) {
      closeModal(topLayer);
      return;
    }

    if (topLayer.matches(selectors.offcanvas)) {
      closeOffcanvas(topLayer);
    }
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: POPOVER STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Resolve the main popover panel inside a documented popover wrapper. */
  function getPopoverPanel(popover) {
    return popover ? popover.querySelector(selectors.popoverPanel) : null;
  }

  /* Close one popover and optionally return focus to its trigger. */
  function closePopover(popover, options) {
    var settings = options || {};
    var trigger = popover ? popover.querySelector(selectors.popoverToggle) : null;
    var panel = getPopoverPanel(popover);

    if (!popover || !panel || !trigger) {
      return;
    }

    popover.classList.remove("is-open");
    trigger.setAttribute("aria-expanded", "false");
    panel.hidden = true;
    panel.setAttribute("aria-hidden", "true");
    setElementInertState(panel, true);

    if (settings.restoreFocus && canReceiveFocus(trigger)) {
      trigger.focus();
    }
  }

  /* Open one popover and optionally move focus to the first item inside it. */
  function openPopover(popover, options) {
    var settings = options || {};
    var trigger = popover ? popover.querySelector(selectors.popoverToggle) : null;
    var panel = getPopoverPanel(popover);
    var focusable = getFocusableElements(panel);

    if (!popover || !panel || !trigger) {
      return;
    }

    closeAllPopovers(popover);
    popover.classList.add("is-open");
    trigger.setAttribute("aria-expanded", "true");
    panel.hidden = false;
    panel.setAttribute("aria-hidden", "false");
    setElementInertState(panel, false);

    if (settings.focusPanel && focusable.length) {
      focusable[0].focus();
    }
  }

  /* Close every popover except the one explicitly preserved. */
  function closeAllPopovers(exceptPopover) {
    toArray(document.querySelectorAll(selectors.popover)).forEach(function (popover) {
      if (popover !== exceptPopover) {
        closePopover(popover);
      }
    });
  }

  /* Toggle one popover while closing peer popovers first. */
  function togglePopover(popover) {
    if (!popover) {
      return;
    }

    if (popover.classList.contains("is-open")) {
      closePopover(popover);
    } else {
      openPopover(popover);
    }
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TOOLTIP STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Position one tooltip around its trigger while keeping it on-screen. */
  function positionTooltipPanel(trigger, panel) {
    var placement = trigger.getAttribute(attributeNames.tooltipPosition) || "top";
    var triggerRect = trigger.getBoundingClientRect();
    var panelRect = panel.getBoundingClientRect();
    var top = 0;
    var left = 0;

    if (placement === "bottom") {
      top = triggerRect.bottom + 10;
      left = triggerRect.left + ((triggerRect.width - panelRect.width) / 2);
    } else if (placement === "left") {
      top = triggerRect.top + ((triggerRect.height - panelRect.height) / 2);
      left = triggerRect.left - panelRect.width - 10;
    } else if (placement === "right") {
      top = triggerRect.top + ((triggerRect.height - panelRect.height) / 2);
      left = triggerRect.right + 10;
    } else {
      top = triggerRect.top - panelRect.height - 10;
      left = triggerRect.left + ((triggerRect.width - panelRect.width) / 2);
    }

    top = Math.max(8, Math.min(top, window.innerHeight - panelRect.height - 8));
    left = Math.max(8, Math.min(left, window.innerWidth - panelRect.width - 8));

    panel.style.top = top + "px";
    panel.style.left = left + "px";
  }

  /* Show one tooltip, creating its shared DOM node if needed. */
  function showTooltip(trigger) {
    var text = trigger ? trigger.getAttribute(attributeNames.tooltip) : "";
    var panel = tooltipPanelMap.get(trigger);

    if (!trigger || !text) {
      return;
    }

    if (!panel) {
      panel = document.createElement("div");
      panel.className = "tooltip-panel";
      panel.id = createFnllaUiId(idPrefixes.tooltip);
      panel.setAttribute("role", "tooltip");
      document.body.appendChild(panel);
      tooltipPanelMap.set(trigger, panel);

      if (!trigger.getAttribute("aria-describedby")) {
        trigger.setAttribute("aria-describedby", panel.id);
      }
    }

    panel.textContent = text;
    panel.hidden = false;
    positionTooltipPanel(trigger, panel);
    panel.classList.add("is-visible");
  }

  /* Hide one tooltip while keeping its DOM node reusable for next show. */
  function hideTooltip(trigger) {
    var panel = tooltipPanelMap.get(trigger);

    if (!panel) {
      return;
    }

    panel.classList.remove("is-visible");
    panel.hidden = true;
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: SCROLLSPY STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Mark one scrollspy link as current and clear the rest. */
  function activateScrollspyLink(container, targetId) {
    var nav = container ? container.querySelector(selectors.scrollspyNav) : null;

    if (!nav) {
      return;
    }

    toArray(nav.querySelectorAll("a[href^='#']")).forEach(function (link) {
      var href = link.getAttribute("href") || "";

      if (href === "#" + targetId) {
        link.setAttribute("aria-current", "location");
      } else {
        link.removeAttribute("aria-current");
      }
    });
  }

  /* Pick the section with the strongest visible presence in the viewport. */
  function getCurrentScrollspySectionId(sections) {
    var currentId = sections.length ? sections[0].id : "";
    var bestScore = -1;
    var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    var viewportAnchor = Math.max(0, Math.min(viewportHeight, viewportHeight * 0.32));
    var viewportBottom = window.scrollY + window.innerHeight;
    var documentBottom = document.documentElement.scrollHeight - 4;

    sections.forEach(function (section) {
      var rect = section.getBoundingClientRect();
      var visibleTop = Math.max(rect.top, 0);
      var visibleBottom = Math.min(rect.bottom, viewportHeight);
      var visibleHeight = Math.max(0, visibleBottom - visibleTop);
      var anchorDistance = Math.abs(rect.top - viewportAnchor);
      var anchoredBonus = Math.max(0, 240 - anchorDistance);
      var score = visibleHeight + anchoredBonus;

      if (score > bestScore) {
        bestScore = score;
        currentId = section.id;
      }
    });

    if (viewportBottom >= documentBottom && sections.length) {
      currentId = sections[sections.length - 1].id;
    }

    return currentId;
  }

  /* Refresh the highlighted scrollspy entry from the current viewport position. */
  function refreshScrollspy(container, sections) {
    var currentId = getCurrentScrollspySectionId(sections);

    if (currentId) {
      activateScrollspyLink(container, currentId);
    }
  }

  function registerScrollspyInstance(container, state) {
    if (!container || !state) {
      return;
    }

    scrollspyObserverMap.set(container, state);
    scrollspyRegistry.push(state);
  }

  function cleanupScrollspyInstance(container) {
    var state = container ? scrollspyObserverMap.get(container) : null;

    if (!state) {
      return;
    }

    window.removeEventListener("scroll", state.update, { passive: true });
    window.removeEventListener("resize", state.update);

    if (state.panel) {
      state.panel.removeEventListener("scroll", state.update, { passive: true });
    }

    initializationState.scrollspy.delete(container);
    scrollspyObserverMap.delete(container);
    scrollspyRegistry = scrollspyRegistry.filter(function (entry) {
      return entry !== state;
    });
  }

  function cleanupDetachedScrollspyInstances() {
    scrollspyRegistry.slice().forEach(function (state) {
      if (!state.container || state.container.isConnected) {
        return;
      }

      cleanupScrollspyInstance(state.container);
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: CUSTOM SELECT CORE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function closeSelectMenu(select, options) {
    var settings = options || {};
    var state = customSelectStateMap.get(select);

    if (!state) {
      return;
    }

    state.shell.classList.remove("is-open");
    state.toggle.setAttribute("aria-expanded", "false");
    state.menu.hidden = true;
    state.menu.setAttribute("aria-hidden", "true");
    setElementInertState(state.menu, true);

    if (settings.restoreFocus && canReceiveFocus(state.toggle)) {
      state.toggle.focus();
    }
  }

  function closeAllSelectMenus(exceptSelect) {
    toArray(document.querySelectorAll(selectors.selectNative)).forEach(function (select) {
      if (select !== exceptSelect) {
        closeSelectMenu(select);
      }
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: DROPDOWN INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind dropdown behavior to every documented dropdown wrapper in scope. */
  function initDropdowns(root) {
    getScopedMatches(root, selectors.dropdown).forEach(function (dropdown) {
      if (initializationState.dropdown.has(dropdown)) {
        return;
      }

      var toggle = dropdown.querySelector(selectors.dropdownToggle);
      var menu = dropdown.querySelector(selectors.dropdownMenu);

      if (!toggle || !menu) {
        return;
      }

      initializationState.dropdown.add(dropdown);

      if (!toggle.id) {
        toggle.id = createFnllaUiId(idPrefixes.dropdownToggle);
      }

      if (!menu.id) {
        menu.id = createFnllaUiId(idPrefixes.dropdownMenu);
      }

      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-controls", menu.id);
      menu.setAttribute("aria-labelledby", toggle.id);
      menu.hidden = true;
      menu.setAttribute("aria-hidden", "true");
      setElementInertState(menu, true);

      toggle.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        toggleDropdown(dropdown);
      });

      toggle.addEventListener("keydown", function (event) {
        if (event.key === "ArrowDown") {
          event.preventDefault();
          closeAllDropdowns(dropdown);
          openDropdown(dropdown, { focusItem: "first" });
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          closeAllDropdowns(dropdown);
          openDropdown(dropdown, { focusItem: "last" });
        }

        if (event.key === "Escape") {
          event.preventDefault();
          closeDropdown(dropdown, { restoreFocus: true });
          event.stopPropagation();
        }
      });

      dropdown.addEventListener("focusout", function () {
        window.setTimeout(function () {
          if (!dropdown.contains(document.activeElement)) {
            closeDropdown(dropdown);
          }
        }, 0);
      });

      menu.addEventListener("keydown", function (event) {
        var items = getFocusableElements(menu);
        var currentIndex = items.indexOf(document.activeElement);

        if (event.key === "Escape") {
          event.preventDefault();
          closeDropdown(dropdown, { restoreFocus: true });
          event.stopPropagation();
          return;
        }

        if (!items.length) {
          return;
        }

        if (event.key === "ArrowDown") {
          event.preventDefault();
          items[(currentIndex + 1 + items.length) % items.length].focus();
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          items[(currentIndex - 1 + items.length) % items.length].focus();
        }

        if (event.key === "Home") {
          event.preventDefault();
          items[0].focus();
        }

        if (event.key === "End") {
          event.preventDefault();
          items[items.length - 1].focus();
        }
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: NAVIGATION INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind mobile navigation toggle behavior to documented navbar triggers. */
  function initNavigation(root) {
    getScopedMatches(root, selectors.navToggle).forEach(function (toggle) {
      if (initializationState.navToggle.has(toggle)) {
        return;
      }

      var target = getControlledElement(toggle);

      if (!target) {
        return;
      }

      initializationState.navToggle.add(toggle);
      toggle.setAttribute("aria-expanded", "false");
      target.classList.remove("is-open");

      if (isMobileNavigation()) {
        target.setAttribute("aria-hidden", "true");
        setElementInertState(target, true);
      } else {
        target.setAttribute("aria-hidden", "false");
        setElementInertState(target, false);
      }

      toggle.addEventListener("click", function (event) {
        event.preventDefault();

        var isExpanded = toggle.getAttribute("aria-expanded") === "true";
        syncNavTargetState(toggle, target, !isExpanded);
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TABS INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind one accessible tab system for each documented tabs wrapper. */
  function initTabs(root) {
    getScopedMatches(root, selectors.tabs).forEach(function (tabs) {
      if (initializationState.tabs.has(tabs)) {
        return;
      }

      var tabList = tabs.querySelector(selectors.tabList);
      var buttons = getTabButtons(tabs);
      var selectedButton = null;
      var fallbackButton = buttons[0] || null;

      if (!tabList || !buttons.length) {
        return;
      }

      initializationState.tabs.add(tabs);
      tabList.setAttribute("role", "tablist");

      if (!tabList.hasAttribute("aria-orientation")) {
        tabList.setAttribute("aria-orientation", "horizontal");
      }

      buttons.forEach(function (button) {
        var panel = getControlledElement(button);

        if (!panel) {
          return;
        }

        if (!button.id) {
          button.id = createFnllaUiId(idPrefixes.tabButton);
        }

        button.setAttribute("role", "tab");
        panel.setAttribute("role", "tabpanel");
        panel.setAttribute("aria-labelledby", button.id);

        if (button.getAttribute("aria-selected") === "true") {
          selectedButton = button;
        }

        button.addEventListener("click", function () {
          activateTab(tabs, button);
        });

        button.addEventListener("keydown", function (event) {
          var orientation = getTabListOrientation(tabs);

          if ((orientation === "horizontal" && event.key === "ArrowRight") || (orientation === "vertical" && event.key === "ArrowDown")) {
            event.preventDefault();
            focusTabButton(tabs, button, "next");
          }

          if ((orientation === "horizontal" && event.key === "ArrowLeft") || (orientation === "vertical" && event.key === "ArrowUp")) {
            event.preventDefault();
            focusTabButton(tabs, button, "previous");
          }

          if (event.key === "Home") {
            event.preventDefault();
            focusTabButton(tabs, button, "first");
          }

          if (event.key === "End") {
            event.preventDefault();
            focusTabButton(tabs, button, "last");
          }
        });
      });

      activateTab(tabs, selectedButton || fallbackButton);
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: ACCORDION INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind accordion buttons, panels and keyboard navigation in scope. */
  function initAccordions(root) {
    getScopedMatches(root, selectors.accordion).forEach(function (accordion) {
      toArray(accordion.querySelectorAll(selectors.accordionButton)).forEach(function (button) {
        if (initializationState.accordionButton.has(button)) {
          return;
        }

        var panel = getControlledElement(button);

        if (!panel) {
          return;
        }

        initializationState.accordionButton.add(button);

        if (!button.id) {
          button.id = createFnllaUiId(idPrefixes.accordionButton);
        }

        panel.setAttribute("role", "region");
        panel.setAttribute("aria-labelledby", button.id);

        var isExpanded = button.getAttribute("aria-expanded") === "true";
        panel.hidden = !isExpanded;

        if (isExpanded) {
          openAccordionPanel(button, panel);
        } else {
          closeAccordionPanel(button, panel);
        }

        button.addEventListener("click", function () {
          var expanded = button.getAttribute("aria-expanded") === "true";

          if (accordion.hasAttribute(attributeNames.accordionSingle)) {
            closeSiblingAccordionItems(accordion, button);
          }

          if (expanded) {
            closeAccordionPanel(button, panel);
          } else {
            openAccordionPanel(button, panel);
          }
        });

        button.addEventListener("keydown", function (event) {
          if (event.key === "ArrowDown") {
            event.preventDefault();
            focusAccordionTrigger(accordion, button, "next");
          }

          if (event.key === "ArrowUp") {
            event.preventDefault();
            focusAccordionTrigger(accordion, button, "previous");
          }

          if (event.key === "Home") {
            event.preventDefault();
            focusAccordionTrigger(accordion, button, "first");
          }

          if (event.key === "End") {
            event.preventDefault();
            focusAccordionTrigger(accordion, button, "last");
          }
        });
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: MODAL INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind every modal trigger so it can resolve and open its target dialog. */
  function initModalTriggers(root) {
    getScopedMatches(root, selectors.modalTrigger).forEach(function (trigger) {
      if (initializationState.modalTrigger.has(trigger)) {
        return;
      }

      var selector = trigger.getAttribute(attributeNames.modalOpen);
      var modal = resolveModalBySelector(selector);

      if (!modal) {
        return;
      }

      initializationState.modalTrigger.add(trigger);

      trigger.addEventListener("click", function (event) {
        event.preventDefault();
        openModal(modal, trigger);
      });
    });
  }

  /* Prepare modal shells and close controls inside the current scope. */
  function initModals(root) {
    getScopedMatches(root, selectors.modal).forEach(function (modal) {
      if (!initializationState.modal.has(modal)) {
        initializationState.modal.add(modal);

        if (!modal.id) {
          modal.id = createFnllaUiId(idPrefixes.modal);
        }

        if (!modal.hasAttribute("role")) {
          modal.setAttribute("role", "dialog");
        }

        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");
        modal.hidden = true;
        setElementInertState(modal, true);

        modal.addEventListener("click", function (event) {
          var clickedClose = event.target.closest(selectors.modalClose);
          var clickedBackdrop = event.target === modal;

          if (clickedClose || clickedBackdrop) {
            closeModal(modal);
          }
        });
      }
    });

    getScopedMatches(root, selectors.modalClose).forEach(function (button) {
      if (initializationState.modalClose.has(button)) {
        return;
      }

      initializationState.modalClose.add(button);

      button.addEventListener("click", function (event) {
        var modal = event.currentTarget.closest(selectors.modal);

        if (!modal) {
          return;
        }

        event.preventDefault();
        closeModal(modal);
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TOAST INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Prepare toasts, open triggers and close controls inside the scope. */
  function initToasts(root) {
    getScopedMatches(root, selectors.toast).forEach(function (toast) {
      if (!initializationState.toast.has(toast)) {
        initializationState.toast.add(toast);

        if (!toast.id) {
          toast.id = createFnllaUiId(idPrefixes.toast);
        }

        var startsVisible = toast.classList.contains("is-visible");
        toast.setAttribute("aria-hidden", startsVisible ? "false" : "true");
        toast.hidden = !startsVisible;

        if (startsVisible) {
          scheduleToastAutoHide(toast);
        }
      }
    });

    getScopedMatches(root, selectors.toastTrigger).forEach(function (trigger) {
      if (initializationState.toastTrigger.has(trigger)) {
        return;
      }

      var selector = trigger.getAttribute(attributeNames.toastOpen);
      var toast = resolveElementReference(selector, selectors.toast);

      if (!toast) {
        return;
      }

      initializationState.toastTrigger.add(trigger);

      trigger.addEventListener("click", function (event) {
        event.preventDefault();
        showToast(toast);
      });
    });

    getScopedMatches(root, selectors.toastClose).forEach(function (button) {
      if (initializationState.toastClose.has(button)) {
        return;
      }

      initializationState.toastClose.add(button);

      button.addEventListener("click", function (event) {
        var toast = event.currentTarget.closest(selectors.toast);

        if (!toast) {
          return;
        }

        event.preventDefault();
        hideToast(toast);
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: OFFCANVAS INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind offcanvas triggers, panels and close controls in the current scope. */
  function initOffcanvas(root) {
    getScopedMatches(root, selectors.offcanvasTrigger).forEach(function (trigger) {
      if (initializationState.offcanvasTrigger.has(trigger)) {
        return;
      }

      var selector = trigger.getAttribute(attributeNames.offcanvasOpen);
      var offcanvas = resolveModalBySelector(selector);

      if (!offcanvas) {
        return;
      }

      initializationState.offcanvasTrigger.add(trigger);

      trigger.addEventListener("click", function (event) {
        event.preventDefault();
        openOffcanvas(offcanvas, trigger);
      });
    });

    getScopedMatches(root, selectors.offcanvas).forEach(function (offcanvas) {
      if (initializationState.offcanvas.has(offcanvas)) {
        return;
      }

      initializationState.offcanvas.add(offcanvas);

      if (!offcanvas.id) {
        offcanvas.id = createFnllaUiId(idPrefixes.offcanvas);
      }

      if (!offcanvas.hasAttribute("role")) {
        offcanvas.setAttribute("role", "dialog");
      }

      offcanvas.setAttribute("aria-modal", "true");
      offcanvas.setAttribute("aria-hidden", "true");
      offcanvas.hidden = true;
      setElementInertState(offcanvas, true);

      offcanvas.addEventListener("click", function (event) {
        var clickedClose = event.target.closest(selectors.offcanvasClose);
        var clickedBackdrop = event.target === offcanvas;

        if (clickedClose || clickedBackdrop) {
          closeOffcanvas(offcanvas);
        }
      });
    });

    getScopedMatches(root, selectors.offcanvasClose).forEach(function (button) {
      if (initializationState.offcanvasClose.has(button)) {
        return;
      }

      initializationState.offcanvasClose.add(button);

      button.addEventListener("click", function (event) {
        var offcanvas = event.currentTarget.closest(selectors.offcanvas);

        if (!offcanvas) {
          return;
        }

        event.preventDefault();
        closeOffcanvas(offcanvas);
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: POPOVER INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind popover toggles, panels and close controls in the current scope. */
  function initPopovers(root) {
    getScopedMatches(root, selectors.popover).forEach(function (popover) {
      if (initializationState.popover.has(popover)) {
        return;
      }

      var trigger = popover.querySelector(selectors.popoverToggle);
      var panel = getPopoverPanel(popover);

      if (!trigger || !panel) {
        return;
      }

      initializationState.popover.add(popover);

      if (!trigger.id) {
        trigger.id = createFnllaUiId(idPrefixes.popoverToggle);
      }

      if (!panel.id) {
        panel.id = createFnllaUiId(idPrefixes.popoverPanel);
      }

      trigger.setAttribute("aria-expanded", "false");
      trigger.setAttribute("aria-controls", panel.id);
      panel.setAttribute("aria-labelledby", trigger.id);
      panel.hidden = true;
      panel.setAttribute("aria-hidden", "true");
      setElementInertState(panel, true);

      trigger.addEventListener("click", function (event) {
        event.preventDefault();
        togglePopover(popover);
      });

      trigger.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          event.preventDefault();
          closePopover(popover, { restoreFocus: true });
        }
      });

      panel.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          event.preventDefault();
          closePopover(popover, { restoreFocus: true });
        }
      });
    });

    getScopedMatches(root, selectors.popoverToggle).forEach(function (trigger) {
      if (initializationState.popoverTrigger.has(trigger)) {
        return;
      }

      initializationState.popoverTrigger.add(trigger);
    });

    getScopedMatches(root, selectors.popoverClose).forEach(function (button) {
      if (initializationState.popoverClose.has(button)) {
        return;
      }

      initializationState.popoverClose.add(button);

      button.addEventListener("click", function (event) {
        var popover = event.currentTarget.closest(selectors.popover);

        if (!popover) {
          return;
        }

        event.preventDefault();
        closePopover(popover, { restoreFocus: true });
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: TOOLTIP INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind hover and focus tooltip behavior to documented tooltip triggers. */
  function initTooltips(root) {
    getScopedMatches(root, selectors.tooltipTrigger).forEach(function (trigger) {
      if (initializationState.tooltipTrigger.has(trigger)) {
        return;
      }

      if (!trigger.getAttribute(attributeNames.tooltip)) {
        return;
      }

      initializationState.tooltipTrigger.add(trigger);

      trigger.addEventListener("mouseenter", function () {
        showTooltip(trigger);
      });

      trigger.addEventListener("mouseleave", function () {
        hideTooltip(trigger);
      });

      trigger.addEventListener("focus", function () {
        showTooltip(trigger);
      });

      trigger.addEventListener("blur", function () {
        hideTooltip(trigger);
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: CUSTOM SELECT SHARED HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function isSingleSelectField(target) {
    if (!target || target.tagName !== "SELECT") {
      return false;
    }

    if (target.multiple) {
      return false;
    }

    if (!target.hasAttribute("size")) {
      return true;
    }

    return target.getAttribute("size") === "1";
  }

  function getSelectState(select) {
    return customSelectStateMap.get(select) || null;
  }

  function getAssociatedSelectLabels(select) {
    return select && select.labels ? toArray(select.labels) : [];
  }

  function getSelectOptionText(option) {
    if (!option) {
      return "";
    }

    return String(option.text || option.label || option.textContent || "").replace(/\s+/g, " ").trim();
  }

  function getSelectableSelectButtons(select) {
    var state = getSelectState(select);

    if (!state) {
      return [];
    }

    return state.optionButtons.filter(function (button) {
      return !button.disabled && button.getAttribute("aria-disabled") !== "true";
    });
  }

  function updateSelectButtonLabel(select) {
    var state = getSelectState(select);
    var selectedOption = select.options[select.selectedIndex] || null;
    var renderedLabel = getSelectOptionText(selectedOption);
    var isPlaceholder = !selectedOption || selectedOption.value === "";

    if (!state) {
      return;
    }

    state.valueLabel.textContent = renderedLabel || "\u00a0";
    state.toggle.classList.toggle("is-placeholder", isPlaceholder);
  }

  function syncSelectState(select) {
    var state = getSelectState(select);
    var describedBy = select.getAttribute("aria-describedby");
    var invalid = select.getAttribute("aria-invalid") === "true";

    if (!state) {
      return;
    }

    state.shell.classList.toggle("is-disabled", !!select.disabled);
    state.shell.classList.toggle("is-invalid", invalid);
    state.toggle.disabled = !!select.disabled;
    state.toggle.setAttribute("aria-invalid", invalid ? "true" : "false");

    if (select.required) {
      state.toggle.setAttribute("aria-required", "true");
    } else {
      state.toggle.removeAttribute("aria-required");
    }

    if (describedBy) {
      state.toggle.setAttribute("aria-describedby", describedBy);
    } else {
      state.toggle.removeAttribute("aria-describedby");
    }

    updateSelectButtonLabel(select);

    state.optionButtons.forEach(function (button) {
      var optionIndex = parseInt(button.getAttribute("data-fnlla-option-index"), 10);
      var option = select.options[optionIndex];
      var isSelected = optionIndex === select.selectedIndex;
      var isDisabled = !option || option.disabled;

      button.disabled = isDisabled;
      button.setAttribute("aria-disabled", isDisabled ? "true" : "false");
      button.setAttribute("aria-selected", isSelected ? "true" : "false");
      button.classList.toggle("is-selected", isSelected);
    });

    if (select.disabled) {
      closeSelectMenu(select);
    }
  }

  function focusSelectOption(button) {
    if (!button || typeof button.focus !== "function") {
      return;
    }

    button.focus();

    if (typeof button.scrollIntoView === "function") {
      button.scrollIntoView({ block: "nearest" });
    }
  }

  function focusSelectOptionByMode(select, mode) {
    var state = getSelectState(select);
    var buttons = getSelectableSelectButtons(select);
    var target = null;

    if (!state || !buttons.length) {
      return;
    }

    if (mode === "selected") {
      target = state.optionButtons.find(function (button) {
        return button.classList.contains("is-selected") && !button.disabled;
      }) || buttons[0];
    } else if (mode === "last") {
      target = buttons[buttons.length - 1];
    } else {
      target = buttons[0];
    }

    focusSelectOption(target);
  }

  function moveFocusedSelectOption(select, step) {
    var buttons = getSelectableSelectButtons(select);
    var currentIndex = buttons.indexOf(document.activeElement);

    if (!buttons.length) {
      return;
    }

    if (currentIndex === -1) {
      focusSelectOption(buttons[step > 0 ? 0 : buttons.length - 1]);
      return;
    }

    focusSelectOption(buttons[(currentIndex + step + buttons.length) % buttons.length]);
  }

  function handleSelectTypeahead(select, character) {
    var state = getSelectState(select);
    var buttons = getSelectableSelectButtons(select);
    var activeIndex = buttons.indexOf(document.activeElement);
    var searchValue = "";
    var startIndex = 0;
    var offset;
    var candidate;

    if (!state || !buttons.length) {
      return;
    }

    searchValue = (state.typeaheadValue + String(character || "")).toLowerCase();
    state.typeaheadValue = searchValue;

    if (state.typeaheadTimer) {
      window.clearTimeout(state.typeaheadTimer);
    }

    state.typeaheadTimer = window.setTimeout(function () {
      state.typeaheadValue = "";
      state.typeaheadTimer = 0;
    }, 260);

    startIndex = activeIndex === -1 ? 0 : (activeIndex + 1) % buttons.length;

    for (offset = 0; offset < buttons.length; offset += 1) {
      candidate = buttons[(startIndex + offset) % buttons.length];

      if ((candidate.textContent || "").trim().toLowerCase().indexOf(searchValue) === 0) {
        focusSelectOption(candidate);
        return;
      }
    }
  }

  function queueSelectObserverRefresh(select) {
    var state = getSelectState(select);

    if (!state || state.observerQueued) {
      return;
    }

    state.observerQueued = true;
    window.setTimeout(function () {
      var refreshedState = getSelectState(select);

      if (!refreshedState) {
        return;
      }

      refreshedState.observerQueued = false;
      refreshedState.rebuildMenu();
      syncSelectState(select);
    }, 0);
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: CUSTOM SELECT MENU BUILDERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function createSelectOptionButton(select, option, optionIndex) {
    var button = document.createElement("button");
    var label = document.createElement("span");
    var optionLabel = getSelectOptionText(option);
    var isSelected = optionIndex === select.selectedIndex;
    var isDisabled = !!option.disabled;

    button.type = "button";
    button.className = "select-option";
    button.setAttribute("role", "option");
    button.setAttribute("data-fnlla-select-option", "");
    button.setAttribute("data-fnlla-option-index", String(optionIndex));
    button.setAttribute("aria-selected", isSelected ? "true" : "false");
    button.setAttribute("aria-disabled", isDisabled ? "true" : "false");
    button.disabled = isDisabled;
    button.classList.toggle("is-selected", isSelected);

    label.className = "select-option-label";
    label.textContent = optionLabel || "\u00a0";
    button.appendChild(label);
    return button;
  }

  function rebuildSelectMenu(select) {
    var state = getSelectState(select);
    var optionIndex = 0;

    if (!state) {
      return;
    }

    state.optionButtons = [];
    state.menu.innerHTML = "";

    toArray(select.children).forEach(function (child) {
      if (child.tagName === "OPTION") {
        if (!child.hidden) {
          var standaloneButton = createSelectOptionButton(select, child, optionIndex);

          state.optionButtons.push(standaloneButton);
          state.menu.appendChild(standaloneButton);
        }

        optionIndex += 1;
        return;
      }

      if (child.tagName === "OPTGROUP") {
        var group = document.createElement("div");
        var groupLabel = document.createElement("p");

        group.className = "select-group";
        groupLabel.className = "select-group-label";
        groupLabel.textContent = child.label || "";
        group.appendChild(groupLabel);

        toArray(child.children).forEach(function (optionChild) {
          var groupButton;

          if (optionChild.tagName !== "OPTION") {
            return;
          }

          if (!optionChild.hidden) {
            groupButton = createSelectOptionButton(select, optionChild, optionIndex);
            state.optionButtons.push(groupButton);
            group.appendChild(groupButton);
          }

          optionIndex += 1;
        });

        if (group.childElementCount > 1) {
          state.menu.appendChild(group);
        }
      }
    });

    syncSelectState(select);
  }

  function openSelectMenu(select, focusMode) {
    var state = getSelectState(select);

    if (!state || select.disabled) {
      return;
    }

    state.rebuildMenu();
    closeAllSelectMenus(select);
    state.shell.classList.add("is-open");
    state.toggle.setAttribute("aria-expanded", "true");
    state.menu.hidden = false;
    state.menu.setAttribute("aria-hidden", "false");
    setElementInertState(state.menu, false);

    if (focusMode) {
      focusSelectOptionByMode(select, focusMode);
    }
  }

  function selectOptionByIndex(select, optionIndex) {
    var option = select.options[optionIndex];

    if (!option || option.disabled) {
      return;
    }

    if (select.selectedIndex !== optionIndex) {
      select.selectedIndex = optionIndex;
      select.dispatchEvent(new Event("input", { bubbles: true }));
      select.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
      syncSelectState(select);
    }

    closeSelectMenu(select, { restoreFocus: true });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: CUSTOM SELECT INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function initSelects(root) {
    getScopedMatches(root, selectors.select).forEach(function (select) {
      var optionObserver;
      if (initializationState.select.has(select) || !isSingleSelectField(select)) {
        return;
      }

      initializationState.select.add(select);

      var shell = document.createElement("div");
      var toggle = document.createElement("button");
      var valueLabel = document.createElement("span");
      var menu = document.createElement("div");
      var associatedLabels = getAssociatedSelectLabels(select);
      var labelIds = [];

      shell.className = "select-shell";
      shell.setAttribute("data-fnlla-select-shell", "");

      select.parentNode.insertBefore(shell, select);
      shell.appendChild(select);

      select.setAttribute("data-fnlla-select-native", "");
      select.setAttribute("aria-hidden", "true");
      select.tabIndex = -1;
      select.classList.add("select-native");

      toggle.type = "button";
      toggle.className = "select-control";
      toggle.id = createFnllaUiId(idPrefixes.selectToggle);
      toggle.setAttribute("data-fnlla-select-toggle", "");
      toggle.setAttribute("aria-haspopup", "listbox");
      toggle.setAttribute("aria-expanded", "false");

      valueLabel.className = "select-value";
      valueLabel.id = createFnllaUiId("select-value");
      toggle.appendChild(valueLabel);
      shell.appendChild(toggle);

      menu.className = "select-menu scrollbar scrollbar-thin";
      menu.id = createFnllaUiId(idPrefixes.selectMenu);
      menu.hidden = true;
      menu.setAttribute("role", "listbox");
      menu.setAttribute("aria-hidden", "true");
      toggle.setAttribute("aria-controls", menu.id);
      setElementInertState(menu, true);
      shell.appendChild(menu);

      customSelectStateMap.set(select, {
        shell: shell,
        toggle: toggle,
        valueLabel: valueLabel,
        menu: menu,
        optionButtons: [],
        observerQueued: false,
        typeaheadValue: "",
        typeaheadTimer: 0,
        rebuildMenu: function () {
          rebuildSelectMenu(select);
        }
      });

      associatedLabels.forEach(function (label) {
        if (!label.id) {
          label.id = createFnllaUiId("select-label");
        }

        labelIds.push(label.id);

        label.addEventListener("click", function (event) {
          if (select.disabled) {
            return;
          }

          event.preventDefault();
          toggle.focus();
        });
      });

      if (labelIds.length) {
        toggle.setAttribute("aria-labelledby", labelIds.join(" ") + " " + valueLabel.id);
      }

      menu.setAttribute("aria-labelledby", toggle.id);

      toggle.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (shell.classList.contains("is-open")) {
          closeSelectMenu(select);
          return;
        }

        openSelectMenu(select, "selected");
      });

      toggle.addEventListener("keydown", function (event) {
        if (event.key === "ArrowDown") {
          event.preventDefault();
          openSelectMenu(select, "selected");
          return;
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          openSelectMenu(select, "last");
          return;
        }

        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openSelectMenu(select, "selected");
          return;
        }

        if (event.key === "Home") {
          event.preventDefault();
          openSelectMenu(select, "first");
          return;
        }

        if (event.key === "End") {
          event.preventDefault();
          openSelectMenu(select, "last");
          return;
        }

        if (event.key === "Escape") {
          event.preventDefault();
          closeSelectMenu(select, { restoreFocus: true });
          event.stopPropagation();
        }
      });

      menu.addEventListener("click", function (event) {
        var optionButton = event.target.closest(selectors.selectOption);
        var optionIndex;

        if (!optionButton) {
          return;
        }

        optionIndex = parseInt(optionButton.getAttribute("data-fnlla-option-index"), 10);
        selectOptionByIndex(select, optionIndex);
      });

      menu.addEventListener("keydown", function (event) {
        var optionButton = event.target.closest(selectors.selectOption);
        var optionIndex;

        if (event.key === "Escape") {
          event.preventDefault();
          closeSelectMenu(select, { restoreFocus: true });
          event.stopPropagation();
          return;
        }

        if (event.key === "Tab") {
          closeSelectMenu(select);
          return;
        }

        if (event.key === "ArrowDown") {
          event.preventDefault();
          moveFocusedSelectOption(select, 1);
          return;
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          moveFocusedSelectOption(select, -1);
          return;
        }

        if (event.key === "Home") {
          event.preventDefault();
          focusSelectOptionByMode(select, "first");
          return;
        }

        if (event.key === "End") {
          event.preventDefault();
          focusSelectOptionByMode(select, "last");
          return;
        }

        if ((event.key === "Enter" || event.key === " ") && optionButton) {
          event.preventDefault();
          optionIndex = parseInt(optionButton.getAttribute("data-fnlla-option-index"), 10);
          selectOptionByIndex(select, optionIndex);
          return;
        }

        if (event.key && event.key.length === 1 && !event.altKey && !event.ctrlKey && !event.metaKey) {
          handleSelectTypeahead(select, event.key);
        }
      });

      shell.addEventListener("focusout", function () {
        window.setTimeout(function () {
          if (!shell.contains(document.activeElement)) {
            closeSelectMenu(select);
          }
        }, 0);
      });

      select.addEventListener("change", function () {
        syncSelectState(select);
      });
      select.addEventListener("input", function () {
        syncSelectState(select);
      });
      select.addEventListener("invalid", function () {
        syncSelectState(select);
      });

      if (select.form) {
        select.form.addEventListener("reset", function () {
          window.setTimeout(function () {
            rebuildSelectMenu(select);
            syncSelectState(select);
          }, 0);
        });
      }

      if (typeof MutationObserver === "function") {
        optionObserver = new MutationObserver(function () {
          queueSelectObserverRefresh(select);
        });
        optionObserver.observe(select, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ["disabled", "hidden", "label", "selected", "value", "aria-invalid", "aria-describedby", "required"]
        });
      }

      rebuildSelectMenu(select);
      syncSelectState(select);
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: RANGE OUTPUT INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function initRanges(root) {
    getScopedMatches(root, selectors.rangeInput).forEach(function (input) {
      if (initializationState.rangeInput.has(input)) {
        return;
      }

      var outputSelector = "[" + attributeNames.rangeOutput + "=\"" + input.id + "\"]";
      var output = document.querySelector(outputSelector);

      if (!output) {
        return;
      }

      initializationState.rangeInput.add(input);

      function syncOutput() {
        var prefix = input.getAttribute(attributeNames.rangePrefix) || "";
        var suffix = input.getAttribute(attributeNames.rangeSuffix);
        var renderedValue = prefix + String(input.value) + (suffix === null ? "" : suffix);
        var min = parseFloat(input.min || "0");
        var max = parseFloat(input.max || "100");
        var current = parseFloat(input.value || "0");
        var progress = max > min ? ((current - min) / (max - min)) * 100 : 0;

        output.textContent = renderedValue;
        input.style.setProperty("--fnlla-range-percent", progress + "%");

        if (output.tagName === "OUTPUT") {
          output.value = renderedValue;
        }
      }

      syncOutput();
      input.addEventListener("input", syncOutput);
      input.addEventListener("change", syncOutput);
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: SCROLLSPY INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Bind scrollspy behavior so nav links reflect the active document section. */
  function initScrollspy(root) {
    getScopedMatches(root, selectors.scrollspy).forEach(function (container) {
      if (initializationState.scrollspy.has(container)) {
        return;
      }

      var nav = container.querySelector(selectors.scrollspyNav);
      var panel = container.querySelector(".scrollspy-panel");
      var links = nav ? toArray(nav.querySelectorAll("a[href^='#']")) : [];
      var sections = [];

      if (!nav || !links.length) {
        return;
      }

      links.forEach(function (link) {
        var href = link.getAttribute("href") || "";
        var id = href.charAt(0) === "#" ? href.slice(1) : "";
        var section = id ? document.getElementById(id) : null;

        if (section) {
          sections.push(section);
        }

        link.addEventListener("click", function () {
          if (id) {
            activateScrollspyLink(container, id);
          }
        });
      });

      if (!sections.length) {
        return;
      }

      initializationState.scrollspy.add(container);
      refreshScrollspy(container, sections);

      var scheduled = false;
      var update = function () {
        if (scheduled) {
          return;
        }

        scheduled = true;
        window.requestAnimationFrame(function () {
          scheduled = false;
          refreshScrollspy(container, sections);
        });
      };

      window.addEventListener("scroll", update, { passive: true });
      window.addEventListener("resize", update);

      if (panel) {
        panel.addEventListener("scroll", update, { passive: true });
      }

      registerScrollspyInstance(container, {
        container: container,
        panel: panel,
        update: update
      });
    });
  }

/*
  ============================================================================
  FNLLA UI SOURCE MODULE: RUNTIME BINDING AND PUBLIC API
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /*
    Attach the global document-level listeners once for the whole runtime.

    Component initializers can run repeatedly against dynamic subtrees, but these
    top-level listeners must stay singleton. They coordinate cross-component rules
    such as "outside click closes peers" and "Escape closes the highest-priority
    open layer first", which only make sense when handled centrally.
  */
  function bindRuntimeHandlers() {
    if (!runtimeBindings.documentClick) {
      document.addEventListener("click", function (event) {
        /*
          Close floating UI when the interaction clearly moved outside it.
          Each family is handled explicitly so shared close helpers can preserve
          their own focus and state rules instead of one generic blanket reset.
        */
        toArray(document.querySelectorAll(selectors.selectNative)).forEach(function (select) {
          var state = customSelectStateMap.get(select);

          if (state && !state.shell.contains(event.target)) {
            closeSelectMenu(select);
          }
        });

        toArray(document.querySelectorAll(selectors.dropdown)).forEach(function (dropdown) {
          if (!dropdown.contains(event.target)) {
            closeDropdown(dropdown);
          }
        });

        toArray(document.querySelectorAll(selectors.popover)).forEach(function (popover) {
          if (!popover.contains(event.target)) {
            closePopover(popover);
          }
        });

        if (!isMobileNavigation()) {
          return;
        }

        toArray(document.querySelectorAll(selectors.navToggle)).forEach(function (toggle) {
          var target = getControlledElement(toggle);

          if (!target || !target.classList.contains("is-open")) {
            return;
          }

          if (!toggle.contains(event.target) && !target.contains(event.target)) {
            syncNavTargetState(toggle, target, false);
          }
        });
      });

      runtimeBindings.documentClick = true;
    }

    if (!runtimeBindings.documentKeydown) {
      document.addEventListener("keydown", function (event) {
        /*
          Focus trapping runs before Escape handling so Tab navigation stays safe
          even while an overlay is open and no explicit component-level handler
          has executed yet.
        */
        trapFocusInModal(event);

        if (event.key !== "Escape") {
          return;
        }

        /*
          Escape priority is deliberate:
          1. close the top-most blocking layer if one exists
          2. otherwise close lighter transient UI families
          3. finally collapse mobile navigation if it is open
        */
        if (getTopOpenLayer()) {
          closeTopOpenLayer();
          return;
        }

        closeAllSelectMenus(null);
        closeAllDropdowns(null);
        closeAllPopovers(null);
        closeOpenNavigation({ restoreFocus: true });
      });

      runtimeBindings.documentKeydown = true;
    }

    if (!runtimeBindings.mediaQuery && mobileNavQuery) {
      /* Keep already-bound nav markup reconciled with later viewport changes. */
      if (typeof mobileNavQuery.addEventListener === "function") {
        mobileNavQuery.addEventListener("change", function () {
          syncNavigationMode(document);
        });
      } else if (typeof mobileNavQuery.addListener === "function") {
        mobileNavQuery.addListener(function () {
          syncNavigationMode(document);
        });
      }

      runtimeBindings.mediaQuery = true;
    }

    if (!runtimeBindings.scrollspyCleanupObserver && typeof MutationObserver === "function") {
      /*
        Scrollspy instances can outlive their DOM nodes in dynamic pages.
        A lightweight observer keeps the shared registry from collecting detached
        instances that would otherwise keep stale references around.
      */
      cleanupDetachedScrollspyInstances();
      runtimeBindings.scrollspyCleanupObserver = new MutationObserver(function () {
        cleanupDetachedScrollspyInstances();
      });
      runtimeBindings.scrollspyCleanupObserver.observe(document.body || document.documentElement, {
        childList: true,
        subtree: true
      });
    }
  }

  /*
    Public initializer used for first load and any later dynamic subtree init.

    Initializer order is intentional: low-level global bindings and cleanup happen
    first, then component families are bound in a predictable sequence, and only
    at the end do we reconcile responsive navigation state for the current scope.
  */
  function initFnllaUi(root) {
    var scope = normalizeRoot(root);

    cleanupDetachedScrollspyInstances();
    document.documentElement.classList.add(runtimeEnhancementClass);
    bindRuntimeHandlers();
    initDropdowns(scope);
    initNavigation(scope);
    initTabs(scope);
    initAccordions(scope);
    initModalTriggers(scope);
    initModals(scope);
    initToasts(scope);
    initOffcanvas(scope);
    initPopovers(scope);
    initTooltips(scope);
    initSelects(scope);
    initRanges(scope);
    initScrollspy(scope);
    syncNavigationMode(scope);

    return fnllaUiApi;
  }

  /* Keep the public theme API intentionally narrow and forward-compatible. */
  function normalizeThemeName(theme) {
    return theme === "dark" ? "dark" : "default";
  }

  /*
    Resolve the node that should receive data-fnlla-theme.

    The public API accepts document-like shorthands because callers usually think
    in terms of "theme the page" rather than "theme this exact element node".
  */
  function resolveThemeTarget(target) {
    if (!target || target === document || target === document.documentElement || target === document.body) {
      return document.body;
    }

    return resolveElementReference(target);
  }

  /* Auto-start the runtime once the DOM is ready. */
  function autoInit() {
    if (runtimeBindings.autoInit) {
      return;
    }

    runtimeBindings.autoInit = true;
    initFnllaUi(document);
  }

  /*
    Public API surface:
    keep this small, explicit and stable.

    These methods intentionally map to resolved runtime primitives rather than
    exposing internal state maps or event wiring details. That gives maintainers
    room to evolve internals without breaking downstream projects.
  */
  var fnllaUiApi = {
    version: fnllaUiVersion,
    init: initFnllaUi,
    setTheme: function (theme, target) {
      var themeTarget = resolveThemeTarget(target);

      if (themeTarget) {
        themeTarget.setAttribute("data-fnlla-theme", normalizeThemeName(theme));
      }

      return fnllaUiApi;
    },
    showModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        openModal(modal);
      }

      return fnllaUiApi;
    },
    openModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        openModal(modal);
      }

      return fnllaUiApi;
    },
    hideModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        closeModal(modal);
      }

      return fnllaUiApi;
    },
    closeModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        closeModal(modal);
      }

      return fnllaUiApi;
    },
    showToast: function (target) {
      var toast = resolveElementReference(target, selectors.toast);

      if (toast) {
        showToast(toast);
      }

      return fnllaUiApi;
    },
    hideToast: function (target) {
      var toast = resolveElementReference(target, selectors.toast);

      if (toast) {
        hideToast(toast);
      }

      return fnllaUiApi;
    },
    showOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        openOffcanvas(offcanvas);
      }

      return fnllaUiApi;
    },
    openOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        openOffcanvas(offcanvas);
      }

      return fnllaUiApi;
    },
    hideOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        closeOffcanvas(offcanvas);
      }

      return fnllaUiApi;
    },
    closeOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        closeOffcanvas(offcanvas);
      }

      return fnllaUiApi;
    },
    openDropdown: function (target) {
      var dropdown = resolveElementReference(target, selectors.dropdown);

      if (dropdown) {
        openDropdown(dropdown);
      }

      return fnllaUiApi;
    },
    closeDropdown: function (target) {
      var dropdown = resolveElementReference(target, selectors.dropdown);

      if (dropdown) {
        closeDropdown(dropdown);
      }

      return fnllaUiApi;
    },
    openPopover: function (target) {
      var popover = resolveElementReference(target, selectors.popover);

      if (popover) {
        openPopover(popover);
      }

      return fnllaUiApi;
    },
    closePopover: function (target) {
      var popover = resolveElementReference(target, selectors.popover);

      if (popover) {
        closePopover(popover);
      }

      return fnllaUiApi;
    },
    showTooltip: function (target) {
      var trigger = resolveElementReference(target, selectors.tooltipTrigger);

      if (trigger) {
        showTooltip(trigger);
      }

      return fnllaUiApi;
    },
    hideTooltip: function (target) {
      var trigger = resolveElementReference(target, selectors.tooltipTrigger);

      if (trigger) {
        hideTooltip(trigger);
      }

      return fnllaUiApi;
    },
    refreshScrollspy: function (target) {
      var container = resolveElementReference(target, selectors.scrollspy);
      var state = container ? scrollspyObserverMap.get(container) : null;

      if (state && typeof state.update === "function") {
        state.update();
      }

      return fnllaUiApi;
    }
  };

  window.FNLLAUI = fnllaUiApi;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoInit);
  } else {
    autoInit();
  }
})();
