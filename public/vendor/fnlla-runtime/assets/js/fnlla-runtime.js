/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: PREAMBLE AND SHARED STATE
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

/*
  FNLLA runtime script.
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  Produced, maintained and distributed by TechAyo LTD (techayo.co.uk).
  Public runtime asset names and enhancement markers define the supported runtime contract.
*/

/*
  Runtime wrapper:
  - creates one private scope
  - exposes only the public `window.FNLLARUNTIME` API
  - keeps shared state hidden from page-level scripts
*/
(function () {
  "use strict";

  /* Public version marker exposed through the runtime API. */
  var fnllaRuntimeVersion = "1.1.0";
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
  var sliderStateMap = new WeakMap();
  var scrollspyRegistry = [];
  var stickyRegistry = [];
  var fnllaRuntimeIdCounter = 0;
  var defaultConsentCategories = ["preferences", "analytics", "marketing"];
  var mobileNavQuery = window.matchMedia ? window.matchMedia("(max-width: 880px)") : null;
  var runtimeEnhancementClass = "fnlla-runtime-js";

  /*
    Initialization registry:
    every interactive node is marked after first binding so repeated
    `FNLLARUNTIME.init(root)` calls stay safe and idempotent.
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
    scrollspy: new WeakSet(),
    sticky: new WeakSet(),
    counter: new WeakSet(),
    passwordToggle: new WeakSet(),
    stepper: new WeakSet(),
    filter: new WeakSet(),
    slider: new WeakSet(),
    consent: new WeakSet(),
    consentOpen: new WeakSet(),
    consentAccept: new WeakSet(),
    consentSave: new WeakSet(),
    consentReset: new WeakSet()
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
    scrollspyCleanupObserver: null,
    stickyScroll: false
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
    stickyOffset: "data-fnlla-sticky-offset",
    counterDuration: "data-fnlla-counter-duration",
    counterThreshold: "data-fnlla-counter-threshold",
    passwordTarget: "data-fnlla-password-target",
    stepperMin: "data-fnlla-stepper-min",
    stepperMax: "data-fnlla-stepper-max",
    stepperStep: "data-fnlla-stepper-step",
    filterValue: "data-fnlla-filter-value",
    sliderSlides: "data-fnlla-slider-slides",
    sliderScroll: "data-fnlla-slider-scroll",
    sliderAutoplay: "data-fnlla-slider-autoplay",
    sliderAutoplaySpeed: "data-fnlla-slider-autoplay-speed",
    sliderFade: "data-fnlla-slider-fade",
    sliderDots: "data-fnlla-slider-dots",
    sliderCenter: "data-fnlla-slider-center",
    sliderMarquee: "data-fnlla-slider-marquee",
    sliderMarqueeSpeed: "data-fnlla-slider-marquee-speed",
    sliderPrev: "data-fnlla-slider-prev",
    sliderNext: "data-fnlla-slider-next",
    sliderResponsive: "data-fnlla-slider-responsive",
    tooltip: "data-fnlla-tooltip",
    tooltipPosition: "data-fnlla-tooltip-position",
    consentCategory: "data-fnlla-consent-category",
    consentCookie: "data-fnlla-consent-cookie",
    consentExpiryDays: "data-fnlla-consent-expiry-days"
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
    sticky: "[data-fnlla-sticky]",
    counter: "[data-fnlla-counter], .counter",
    passwordToggle: "[data-fnlla-password-toggle]",
    stepper: "[data-fnlla-stepper]",
    stepperInput: "[data-fnlla-stepper-input]",
    stepperAction: "[data-fnlla-stepper-action]",
    filter: "[data-fnlla-filter]",
    filterControl: "[data-fnlla-filter-control]",
    filterItem: "[data-fnlla-filter-item]",
    slider: "[data-fnlla-slider]",
    popover: "[data-fnlla-popover]",
    popoverToggle: "[data-fnlla-popover-toggle]",
    popoverPanel: ".popover-panel",
    popoverClose: "[data-fnlla-popover-close]",
    tooltipTrigger: "[data-fnlla-tooltip]",
    scrollspy: "[data-fnlla-scrollspy]",
    scrollspyNav: "[data-fnlla-scrollspy-nav]",
    consent: "[data-fnlla-consent]",
    consentModal: "[data-fnlla-consent-modal]",
    consentOpen: "[data-fnlla-consent-open]",
    consentAccept: "[data-fnlla-consent-accept]",
    consentSave: "[data-fnlla-consent-save]",
    consentReset: "[data-fnlla-consent-reset]",
    consentCategory: "[data-fnlla-consent-category]"
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
  FNLLA Runtime SOURCE MODULE: DOM AND FOCUS HELPERS
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
  function createFnllaRuntimeId(prefix) {
    fnllaRuntimeIdCounter += 1;
    return prefix + "-" + fnllaRuntimeIdCounter;
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
  FNLLA Runtime SOURCE MODULE: DROPDOWN STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: NAVIGATION STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: MODAL STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: ACCORDION STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: TABS STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: FOCUS MANAGEMENT HELPERS
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
  FNLLA Runtime SOURCE MODULE: TOAST STATE HELPERS
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
      toast.id = createFnllaRuntimeId(idPrefixes.toast);
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
  FNLLA Runtime SOURCE MODULE: OFFCANVAS STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: POPOVER STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: TOOLTIP STATE HELPERS
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
      panel.id = createFnllaRuntimeId(idPrefixes.tooltip);
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
  FNLLA Runtime SOURCE MODULE: SCROLLSPY STATE HELPERS
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
  FNLLA Runtime SOURCE MODULE: CUSTOM SELECT CORE HELPERS
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
  FNLLA Runtime SOURCE MODULE: DOCUMENT TITLE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function getTitleRootElement() {
    return document.documentElement || document.querySelector("html");
  }

  function normalizeTitlePart(value) {
    if (typeof value !== "string") {
      return "";
    }

    return value.replace(/\s+/g, " ").trim();
  }

  function readDocumentTitleConfig() {
    var root = getTitleRootElement();

    return {
      site: normalizeTitlePart(root ? root.getAttribute("data-fnlla-title-site") : ""),
      page: normalizeTitlePart(root ? root.getAttribute("data-fnlla-title-page") : ""),
      section: normalizeTitlePart(root ? root.getAttribute("data-fnlla-title-section") : ""),
      suffix: normalizeTitlePart(root ? root.getAttribute("data-fnlla-title-suffix") : ""),
      home: root ? root.getAttribute("data-fnlla-title-home") === "true" : false
    };
  }

  function writeDocumentTitleConfig(config) {
    var root = getTitleRootElement();

    if (!root || !config) {
      return;
    }

    [
      ["data-fnlla-title-site", config.site],
      ["data-fnlla-title-page", config.page],
      ["data-fnlla-title-section", config.section],
      ["data-fnlla-title-suffix", config.suffix]
    ].forEach(function (entry) {
      var attributeName = entry[0];
      var value = normalizeTitlePart(entry[1]);

      if (value) {
        root.setAttribute(attributeName, value);
      } else {
        root.removeAttribute(attributeName);
      }
    });

    if (config.home) {
      root.setAttribute("data-fnlla-title-home", "true");
    } else {
      root.removeAttribute("data-fnlla-title-home");
    }
  }

  function getMergedDocumentTitleConfig(nextConfig) {
    var current = readDocumentTitleConfig();

    if (typeof nextConfig === "string") {
      current.page = normalizeTitlePart(nextConfig);
      current.home = false;
      return current;
    }

    if (!nextConfig || typeof nextConfig !== "object") {
      return current;
    }

    if (Object.prototype.hasOwnProperty.call(nextConfig, "site")) {
      current.site = normalizeTitlePart(nextConfig.site);
    }

    if (Object.prototype.hasOwnProperty.call(nextConfig, "page")) {
      current.page = normalizeTitlePart(nextConfig.page);
    }

    if (Object.prototype.hasOwnProperty.call(nextConfig, "section")) {
      current.section = normalizeTitlePart(nextConfig.section);
    }

    if (Object.prototype.hasOwnProperty.call(nextConfig, "suffix")) {
      current.suffix = normalizeTitlePart(nextConfig.suffix);
    }

    if (Object.prototype.hasOwnProperty.call(nextConfig, "home")) {
      current.home = nextConfig.home === true;
    }

    return current;
  }

  function buildDocumentTitle(config) {
    var normalizedConfig = getMergedDocumentTitleConfig(config);
    var parts = [];

    if (normalizedConfig.home && normalizedConfig.site) {
      parts.push(normalizedConfig.site);
    } else {
      parts.push(normalizedConfig.page);
      parts.push(normalizedConfig.section);
      parts.push(normalizedConfig.site);
    }

    if (normalizedConfig.suffix) {
      parts.push(normalizedConfig.suffix);
    }

    return parts
      .filter(Boolean)
      .filter(function (part, index, array) {
        return array.findIndex(function (candidate) {
          return candidate.toLowerCase() === part.toLowerCase();
        }) === index;
      })
      .join(" | ");
  }

  function syncDocumentTitle(config) {
    var nextConfig = getMergedDocumentTitleConfig(config);
    var title = buildDocumentTitle(nextConfig);

    writeDocumentTitleConfig(nextConfig);

    if (title) {
      document.title = title;
    }

    return document.title;
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: CONSENT STATE HELPERS
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function getConsentRuntimeRoot() {
    return document.querySelector(selectors.consent) || document.querySelector(selectors.consentModal) || getTitleRootElement();
  }

  function getSupportedConsentCategories(scope) {
    var categories = [];
    var seen = Object.create(null);

    getScopedMatches(scope || document, selectors.consentCategory).forEach(function (input) {
      var category = normalizeTitlePart(input.getAttribute(attributeNames.consentCategory));

      if (!category || category === "necessary" || seen[category]) {
        return;
      }

      seen[category] = true;
      categories.push(category);
    });

    return categories.length ? categories : defaultConsentCategories.slice();
  }

  function getConsentStateCategories(state) {
    var categories = getSupportedConsentCategories();
    var source = state && typeof state === "object" ? state : {};

    Object.keys(source).forEach(function (key) {
      if (key === "necessary" || key === "stored" || categories.indexOf(key) !== -1) {
        return;
      }

      categories.push(key);
    });

    return categories;
  }

  function getConsentCookieName() {
    var root = getConsentRuntimeRoot();
    var configured = root ? normalizeTitlePart(root.getAttribute(attributeNames.consentCookie)) : "";

    return configured || "fnlla-consent";
  }

  function getConsentExpiryDays() {
    var root = getConsentRuntimeRoot();
    var configured = root ? Number(root.getAttribute(attributeNames.consentExpiryDays)) : NaN;

    return Number.isFinite(configured) && configured > 0 ? configured : 180;
  }

  function getDefaultConsentState() {
    var state = {
      necessary: true,
      stored: false
    };

    getSupportedConsentCategories().forEach(function (category) {
      state[category] = false;
    });

    return state;
  }

  function cloneConsentState(state) {
    var nextState = {
      necessary: state.necessary === true,
      stored: state.stored === true
    };

    getConsentStateCategories(state).forEach(function (category) {
      nextState[category] = state && state[category] === true;
    });

    return nextState;
  }

  function normalizeConsentState(input, stored) {
    var base = getDefaultConsentState();
    var source = input && typeof input === "object" ? input : {};

    getConsentStateCategories(source).forEach(function (category) {
      base[category] = source[category] === true;
    });

    base.necessary = true;
    base.stored = stored === true;
    return base;
  }

  function readCookieValue(name) {
    var encodedName = encodeURIComponent(name) + "=";
    var match = document.cookie.split(/;\s*/).find(function (entry) {
      return entry.indexOf(encodedName) === 0;
    });

    return match ? decodeURIComponent(match.slice(encodedName.length)) : "";
  }

  function writeCookieValue(name, value, expiryDays) {
    var maxAge = Math.round(expiryDays * 24 * 60 * 60);
    var cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge + "; SameSite=Lax";

    if (window.location && window.location.protocol === "https:") {
      cookie += "; Secure";
    }

    document.cookie = cookie;
  }

  function clearCookieValue(name) {
    document.cookie = encodeURIComponent(name) + "=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax";
  }

  function getStoredConsentSnapshot() {
    var rawValue = readCookieValue(getConsentCookieName());

    if (!rawValue) {
      return getDefaultConsentState();
    }

    try {
      return normalizeConsentState(JSON.parse(rawValue), true);
    } catch (error) {
      clearCookieValue(getConsentCookieName());
      return getDefaultConsentState();
    }
  }

  function getConsentModalElement() {
    var root = getConsentRuntimeRoot();
    var selector = root ? root.getAttribute("data-fnlla-consent-settings") : "";
    var modal = selector ? resolveModalBySelector(selector) : null;

    return modal || document.querySelector(selectors.consentModal);
  }

  function updateConsentRootAttributes(state) {
    var root = document.documentElement;

    if (!root) {
      return;
    }

    root.setAttribute("data-fnlla-consent-ready", state.stored ? "true" : "false");

    getConsentStateCategories(state).forEach(function (category) {
      root.setAttribute("data-fnlla-consent-" + category, state[category] ? "granted" : "denied");
    });
  }

  function syncConsentInputs(scope, state) {
    getScopedMatches(scope || document, selectors.consentCategory).forEach(function (input) {
      var category = input.getAttribute(attributeNames.consentCategory) || "";

      if (category === "necessary") {
        input.checked = true;
        input.disabled = true;
        return;
      }

      if (!category || category === "stored") {
        return;
      }

      input.checked = state[category] === true;
    });
  }

  function syncConsentBannerVisibility(state) {
    getScopedMatches(document, selectors.consent).forEach(function (element) {
      var shouldShow = state.stored !== true;

      element.hidden = !shouldShow;
      element.classList.toggle("is-visible", shouldShow);
      element.setAttribute("aria-hidden", shouldShow ? "false" : "true");
      setElementInertState(element, !shouldShow);
    });
  }

  function dispatchConsentChange(state) {
    if (typeof window.CustomEvent !== "function") {
      return;
    }

    document.dispatchEvent(new CustomEvent("fnlla:consentchange", {
      detail: {
        state: cloneConsentState(state)
      }
    }));
  }

  function applyConsentState(state, shouldDispatch) {
    var normalized = normalizeConsentState(state, state && state.stored === true);

    updateConsentRootAttributes(normalized);
    syncConsentBannerVisibility(normalized);
    syncConsentInputs(document, normalized);

    if (shouldDispatch) {
      dispatchConsentChange(normalized);
    }

    return normalized;
  }

  function saveConsentState(state) {
    var normalized = normalizeConsentState(state, true);

    writeCookieValue(getConsentCookieName(), JSON.stringify(normalized), getConsentExpiryDays());
    applyConsentState(normalized, true);
    return cloneConsentState(normalized);
  }

  function collectConsentStateFromScope(scope) {
    var nextState = getDefaultConsentState();
    var inputs = getScopedMatches(scope || document, selectors.consentCategory);

    if (!inputs.length && scope !== document) {
      inputs = getScopedMatches(document, selectors.consentCategory);
    }

    inputs.forEach(function (input) {
      var category = input.getAttribute(attributeNames.consentCategory) || "";

      if (!category || category === "stored") {
        return;
      }

      nextState[category] = input.checked === true;
    });

    return nextState;
  }

  function syncConsentState() {
    return applyConsentState(getStoredConsentSnapshot(), false);
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: DROPDOWN INITIALIZER
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
        toggle.id = createFnllaRuntimeId(idPrefixes.dropdownToggle);
      }

      if (!menu.id) {
        menu.id = createFnllaRuntimeId(idPrefixes.dropdownMenu);
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
  FNLLA Runtime SOURCE MODULE: NAVIGATION INITIALIZER
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
  FNLLA Runtime SOURCE MODULE: TABS INITIALIZER
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
          button.id = createFnllaRuntimeId(idPrefixes.tabButton);
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
  FNLLA Runtime SOURCE MODULE: ACCORDION INITIALIZER
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
          button.id = createFnllaRuntimeId(idPrefixes.accordionButton);
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
  FNLLA Runtime SOURCE MODULE: MODAL INITIALIZER
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
          modal.id = createFnllaRuntimeId(idPrefixes.modal);
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
  FNLLA Runtime SOURCE MODULE: TOAST INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  /* Prepare toasts, open triggers and close controls inside the scope. */
  function initToasts(root) {
    getScopedMatches(root, selectors.toast).forEach(function (toast) {
      if (!initializationState.toast.has(toast)) {
        initializationState.toast.add(toast);

        if (!toast.id) {
          toast.id = createFnllaRuntimeId(idPrefixes.toast);
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
  FNLLA Runtime SOURCE MODULE: OFFCANVAS INITIALIZER
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
        offcanvas.id = createFnllaRuntimeId(idPrefixes.offcanvas);
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
  FNLLA Runtime SOURCE MODULE: POPOVER INITIALIZER
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
        trigger.id = createFnllaRuntimeId(idPrefixes.popoverToggle);
      }

      if (!panel.id) {
        panel.id = createFnllaRuntimeId(idPrefixes.popoverPanel);
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
  FNLLA Runtime SOURCE MODULE: TOOLTIP INITIALIZER
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
  FNLLA Runtime SOURCE MODULE: CUSTOM SELECT SHARED HELPERS
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
  FNLLA Runtime SOURCE MODULE: CUSTOM SELECT MENU BUILDERS
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
  FNLLA Runtime SOURCE MODULE: CUSTOM SELECT INITIALIZER
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
      toggle.id = createFnllaRuntimeId(idPrefixes.selectToggle);
      toggle.setAttribute("data-fnlla-select-toggle", "");
      toggle.setAttribute("aria-haspopup", "listbox");
      toggle.setAttribute("aria-expanded", "false");

      valueLabel.className = "select-value";
      valueLabel.id = createFnllaRuntimeId("select-value");
      toggle.appendChild(valueLabel);
      shell.appendChild(toggle);

      menu.className = "select-menu scrollbar scrollbar-thin";
      menu.id = createFnllaRuntimeId(idPrefixes.selectMenu);
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
          label.id = createFnllaRuntimeId("select-label");
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
  FNLLA Runtime SOURCE MODULE: RANGE OUTPUT INITIALIZER
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
  FNLLA Runtime SOURCE MODULE: STICKY ELEMENT INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function syncStickyElements() {
    stickyRegistry = stickyRegistry.filter(function (entry) {
      return entry && entry.element && entry.element.isConnected !== false;
    });

    stickyRegistry.forEach(function (entry) {
      var element = entry.element;
      var shouldStick = window.scrollY > entry.offset;

      element.classList.toggle("is-stuck", shouldStick);
    });
  }

  function initStickies(root) {
    getScopedMatches(root, selectors.sticky).forEach(function (element) {
      if (initializationState.sticky.has(element)) {
        return;
      }

      var offsetValue = parseFloat(element.getAttribute(attributeNames.stickyOffset) || "");
      var baseOffset = Number.isFinite(offsetValue)
        ? offsetValue
        : element.getBoundingClientRect().top + window.scrollY;

      initializationState.sticky.add(element);
      element.classList.add("fnlla-sticky");
      stickyRegistry.push({
        element: element,
        offset: Math.max(0, baseOffset)
      });
    });

    if (!runtimeBindings.stickyScroll && stickyRegistry.length) {
      window.addEventListener("scroll", syncStickyElements, { passive: true });
      window.addEventListener("resize", syncStickyElements);
      runtimeBindings.stickyScroll = true;
    }

    syncStickyElements();
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: COUNTER INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function animateCounter(element) {
    if (!element || element.dataset.fnllaCounterAnimated === "true") {
      return;
    }

    var targetValue = parseFloat(element.getAttribute("data-fnlla-counter") || element.textContent || "0");
    var durationValue = parseFloat(element.getAttribute(attributeNames.counterDuration) || "1600");
    var duration = Number.isFinite(durationValue) && durationValue > 0 ? durationValue : 1600;
    var targetText = String(element.getAttribute("data-fnlla-counter") || element.textContent || "0").trim();
    var decimalPart = targetText.split(".")[1] || "";
    var decimals = targetText.indexOf(".") >= 0 ? decimalPart.length : 0;
    var startTime = performance.now();

    if (!Number.isFinite(targetValue)) {
      return;
    }

    element.dataset.fnllaCounterAnimated = "true";

    function step(time) {
      var progress = Math.min((time - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var currentValue = targetValue * eased;

      element.textContent = decimals ? currentValue.toFixed(decimals) : String(Math.round(currentValue));

      if (progress < 1) {
        window.requestAnimationFrame(step);
        return;
      }

      element.textContent = decimals ? targetValue.toFixed(decimals) : String(targetValue);
    }

    window.requestAnimationFrame(step);
  }

  function initCounters(root) {
    getScopedMatches(root, selectors.counter).forEach(function (element) {
      if (initializationState.counter.has(element)) {
        return;
      }

      var targetValue = parseFloat(element.getAttribute("data-fnlla-counter") || element.textContent || "0");
      var thresholdValue = parseFloat(element.getAttribute(attributeNames.counterThreshold) || "0.35");
      var threshold = Number.isFinite(thresholdValue) ? Math.max(0, Math.min(1, thresholdValue)) : 0.35;

      if (!Number.isFinite(targetValue)) {
        return;
      }

      initializationState.counter.add(element);

      if (!("IntersectionObserver" in window)) {
        animateCounter(element);
        return;
      }

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }

          animateCounter(entry.target);
          observer.unobserve(entry.target);
        });
      }, { threshold: threshold });

      observer.observe(element);
    });
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: PASSWORD TOGGLE INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function resolvePasswordTarget(toggle) {
    var controlled = getControlledElement(toggle);
    var selector = toggle.getAttribute(attributeNames.passwordTarget) || "";
    var fromSelector = selector ? resolveElementReference(selector) : null;
    var wrapper = toggle.closest(".input-group, .input-group-meta, .password-field, label, .form-group");
    var fallback = wrapper ? wrapper.querySelector("input[type='password'], input[type='text']") : null;

    return controlled || fromSelector || fallback;
  }

  function initPasswordToggles(root) {
    getScopedMatches(root, selectors.passwordToggle).forEach(function (toggle) {
      if (initializationState.passwordToggle.has(toggle)) {
        return;
      }

      initializationState.passwordToggle.add(toggle);

      if (toggle.tagName !== "BUTTON") {
        toggle.setAttribute("role", "button");
        toggle.setAttribute("tabindex", toggle.hasAttribute("tabindex") ? toggle.getAttribute("tabindex") : "0");
      }

      if (!toggle.hasAttribute("aria-label")) {
        toggle.setAttribute("aria-label", "Toggle password visibility");
      }

      toggle.setAttribute("aria-pressed", "false");

      function activate(event) {
        var input = resolvePasswordTarget(toggle);
        var isKeyboard = event.type === "keydown";

        if (isKeyboard && event.key !== "Enter" && event.key !== " ") {
          return;
        }

        if (!input || !/^(password|text)$/i.test(input.getAttribute("type") || "")) {
          return;
        }

        event.preventDefault();

        var shouldReveal = (input.getAttribute("type") || "").toLowerCase() === "password";

        input.setAttribute("type", shouldReveal ? "text" : "password");
        toggle.setAttribute("aria-pressed", shouldReveal ? "true" : "false");
        toggle.classList.toggle("is-revealed", shouldReveal);
      }

      toggle.addEventListener("click", activate);
      toggle.addEventListener("keydown", activate);
    });
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: NUMERIC STEPPER INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function initSteppers(root) {
    getScopedMatches(root, selectors.stepper).forEach(function (stepper) {
      if (initializationState.stepper.has(stepper)) {
        return;
      }

      var input = stepper.querySelector(selectors.stepperInput) || stepper.querySelector("input");
      var controls = toArray(stepper.querySelectorAll(selectors.stepperAction));

      if (!input || !controls.length) {
        return;
      }

      initializationState.stepper.add(stepper);

      function getValue() {
        var parsed = parseFloat(input.value || "0");
        return Number.isFinite(parsed) ? parsed : 0;
      }

      function getMin() {
        var parsed = parseFloat(input.getAttribute(attributeNames.stepperMin) || input.min || "");
        return Number.isFinite(parsed) ? parsed : null;
      }

      function getMax() {
        var parsed = parseFloat(input.getAttribute(attributeNames.stepperMax) || input.max || "");
        return Number.isFinite(parsed) ? parsed : null;
      }

      function getStep() {
        var parsed = parseFloat(input.getAttribute(attributeNames.stepperStep) || input.step || "1");
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
      }

      function getPrecision(step) {
        var stepText = String(step);
        return stepText.indexOf(".") >= 0 ? (stepText.split(".")[1] || "").length : 0;
      }

      function clampValue(rawValue) {
        var value = rawValue;
        var min = getMin();
        var max = getMax();

        if (min !== null) {
          value = Math.max(min, value);
        }

        if (max !== null) {
          value = Math.min(max, value);
        }

        return value;
      }

      function renderValue(value) {
        var step = getStep();
        var precision = getPrecision(step);
        var nextValue = clampValue(value);

        input.value = precision ? nextValue.toFixed(precision) : String(nextValue);
        syncControls(nextValue);
      }

      function syncControls(currentValue) {
        var min = getMin();
        var max = getMax();

        controls.forEach(function (button) {
          var action = button.getAttribute("data-fnlla-stepper-action");
          var isDisabled = (action === "decrement" && min !== null && currentValue <= min)
            || (action === "increment" && max !== null && currentValue >= max);

          button.setAttribute("aria-disabled", isDisabled ? "true" : "false");

          if (button.tagName === "BUTTON") {
            button.disabled = isDisabled;
          }
        });
      }

      controls.forEach(function (button) {
        button.addEventListener("click", function (event) {
          var action = button.getAttribute("data-fnlla-stepper-action");
          var currentValue = getValue();
          var step = getStep();

          event.preventDefault();

          if (action === "increment") {
            renderValue(currentValue + step);
            return;
          }

          if (action === "decrement") {
            renderValue(currentValue - step);
          }
        });
      });

      input.addEventListener("change", function () {
        renderValue(getValue());
      });

      renderValue(getValue());
    });
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: SIMPLE FILTER INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function initFilters(root) {
    getScopedMatches(root, selectors.filter).forEach(function (container) {
      if (initializationState.filter.has(container)) {
        return;
      }

      var controls = toArray(container.querySelectorAll(selectors.filterControl));
      var items = toArray(container.querySelectorAll(selectors.filterItem));
      var activeValue = "*";

      if (!controls.length || !items.length) {
        return;
      }

      initializationState.filter.add(container);

      function sync(value) {
        activeValue = value || "*";

        controls.forEach(function (control) {
          var isActive = (control.getAttribute(attributeNames.filterValue) || "*") === activeValue;

          control.classList.toggle("is-active", isActive);
          control.setAttribute("aria-pressed", isActive ? "true" : "false");
        });

        items.forEach(function (item) {
          var itemTokens = String(item.getAttribute("data-fnlla-filter-item") || "")
            .split(/[\s,]+/)
            .filter(Boolean);
          var shouldShow = activeValue === "*" || itemTokens.indexOf(activeValue) >= 0;

          item.hidden = !shouldShow;
          item.classList.toggle("is-filtered-out", !shouldShow);
        });
      }

      controls.forEach(function (control) {
        control.addEventListener("click", function (event) {
          event.preventDefault();
          sync(control.getAttribute(attributeNames.filterValue) || "*");
        });
      });

      sync((controls[0].getAttribute(attributeNames.filterValue) || "*"));
    });
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: SIMPLE SLIDER INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function readSliderBoolean(element, attributeName) {
    var value = element.getAttribute(attributeName);

    if (value === null) {
      return false;
    }

    return value !== "false";
  }

  function readSliderNumber(element, attributeName, fallbackValue) {
    var parsed = parseFloat(element.getAttribute(attributeName) || "");
    return Number.isFinite(parsed) ? parsed : fallbackValue;
  }

  function readSliderResponsiveConfig(element) {
    var raw = element.getAttribute(attributeNames.sliderResponsive) || "";

    if (!raw) {
      return [];
    }

    try {
      var parsed = JSON.parse(raw);

      if (Array.isArray(parsed)) {
        return parsed;
      }

      return Object.keys(parsed).map(function (breakpoint) {
        return {
          breakpoint: parseFloat(breakpoint),
          settings: parsed[breakpoint]
        };
      });
    } catch (error) {
      return [];
    }
  }

  function getSliderConfig(rootElement) {
    return {
      slidesToShow: Math.max(1, readSliderNumber(rootElement, attributeNames.sliderSlides, 1)),
      slidesToScroll: Math.max(1, readSliderNumber(rootElement, attributeNames.sliderScroll, 1)),
      autoplay: readSliderBoolean(rootElement, attributeNames.sliderAutoplay),
      autoplaySpeed: Math.max(300, readSliderNumber(rootElement, attributeNames.sliderAutoplaySpeed, 3000)),
      fade: readSliderBoolean(rootElement, attributeNames.sliderFade),
      dots: readSliderBoolean(rootElement, attributeNames.sliderDots),
      centerMode: readSliderBoolean(rootElement, attributeNames.sliderCenter),
      marquee: readSliderBoolean(rootElement, attributeNames.sliderMarquee),
      marqueeSpeed: Math.max(10, readSliderNumber(rootElement, attributeNames.sliderMarqueeSpeed, 40)),
      prevArrow: rootElement.getAttribute(attributeNames.sliderPrev) || "",
      nextArrow: rootElement.getAttribute(attributeNames.sliderNext) || "",
      responsive: readSliderResponsiveConfig(rootElement)
    };
  }

  function resolveSliderControl(rootElement, selector) {
    var scope = rootElement.parentElement;

    if (!selector) {
      return null;
    }

    while (scope) {
      var match = scope.querySelector(selector);

      if (match) {
        return match;
      }

      scope = scope.parentElement;
    }

    return resolveElementReference(selector);
  }

  function getSliderItems(rootElement) {
    return toArray(rootElement.children).filter(function (child) {
      return child.nodeType === 1 && child.getAttribute("data-fnlla-slider-generated") !== "true";
    });
  }

  function FnllaSlider(rootElement) {
    this.root = rootElement;
    this.options = getSliderConfig(rootElement);
    this.items = getSliderItems(rootElement);
    this.renderItems = [];
    this.index = 0;
    this.timer = null;
    this.viewport = null;
    this.track = null;
    this.dots = [];
    this.slideMetrics = null;
    this.prevButton = null;
    this.nextButton = null;
    this.resizeHandler = null;
    this.loadHandler = null;
    this.marqueeWidth = 0;

    if (this.items.length <= 1) {
      return;
    }

    this.build();
    this.bind();
    this.update();
    this.startAutoplay();
  }

  FnllaSlider.prototype.currentSettings = function () {
    var base = {
      slidesToShow: 1,
      slidesToScroll: 1,
      fade: false,
      dots: false,
      arrows: false,
      autoplay: false,
      autoplaySpeed: 3000,
      centerMode: false,
      marquee: false,
      marqueeSpeed: 40,
      prevArrow: "",
      nextArrow: "",
      responsive: []
    };

    Object.assign(base, this.options);

    base.responsive
      .slice()
      .sort(function (left, right) {
        return right.breakpoint - left.breakpoint;
      })
      .forEach(function (entry) {
        if (window.innerWidth < entry.breakpoint) {
          Object.assign(base, entry.settings || {});
        }
      });

    base.arrows = !!(base.prevArrow || base.nextArrow);
    return base;
  };

  FnllaSlider.prototype.build = function () {
    var slider = this;

    this.root.classList.add("fnlla-slider", "fnlla-slider-ready", "slick-slider", "slick-initialized");

    if (this.options.fade) {
      this.root.classList.add("fnlla-slider-fade");
    }

    if (this.options.marquee) {
      this.root.classList.add("fnlla-slider-marquee");
    }

    this.viewport = document.createElement("div");
    this.viewport.className = "fnlla-slider-viewport slick-list";
    this.viewport.setAttribute("data-fnlla-slider-generated", "true");

    this.track = document.createElement("div");
    this.track.className = "fnlla-slider-track slick-track";
    this.track.setAttribute("data-fnlla-slider-generated", "true");

    this.items.forEach(function (item, index) {
      item.classList.add("fnlla-slide", "slick-slide");
      item.dataset.slideIndex = String(index);
      slider.track.appendChild(item);
    });

    this.renderItems = this.items.slice();

    if (this.options.marquee) {
      this.items.forEach(function (item, index) {
        var clone = item.cloneNode(true);

        clone.classList.add("fnlla-slide-clone");
        clone.dataset.slideIndex = String(index);
        clone.setAttribute("aria-hidden", "true");
        slider.track.appendChild(clone);
        slider.renderItems.push(clone);
      });
    }

    this.viewport.appendChild(this.track);
    this.root.appendChild(this.viewport);

    if (this.currentSettings().dots) {
      var dots = document.createElement("ul");

      dots.className = "fnlla-slider-dots slick-dots";
      dots.setAttribute("data-fnlla-slider-generated", "true");

      this.items.forEach(function (_, index) {
        var item = document.createElement("li");
        var button = document.createElement("button");

        button.type = "button";
        button.className = "fnlla-slider-dot";
        button.textContent = "Slide " + String(index + 1);
        button.addEventListener("click", function () {
          slider.goTo(index);
        });
        item.appendChild(button);
        dots.appendChild(item);
        slider.dots.push(item);
      });

      this.root.appendChild(dots);
    }
  };

  FnllaSlider.prototype.bind = function () {
    var slider = this;
    var settings = this.currentSettings();

    if (settings.arrows) {
      this.prevButton = resolveSliderControl(this.root, settings.prevArrow);
      this.nextButton = resolveSliderControl(this.root, settings.nextArrow);

      if (this.prevButton) {
        this.prevButton.addEventListener("click", function (event) {
          event.preventDefault();
          slider.prev();
        });
      }

      if (this.nextButton) {
        this.nextButton.addEventListener("click", function (event) {
          event.preventDefault();
          slider.next();
        });
      }
    }

    this.root.addEventListener("mouseenter", function () {
      slider.stopAutoplay();
    });
    this.root.addEventListener("mouseleave", function () {
      slider.startAutoplay();
    });

    this.resizeHandler = function () {
      slider.update();
    };
    this.loadHandler = function () {
      slider.update();
    };

    window.addEventListener("resize", this.resizeHandler);
    window.addEventListener("load", this.loadHandler, { once: true });

    this.root.querySelectorAll("img").forEach(function (image) {
      if (image.complete) {
        return;
      }

      image.addEventListener("load", function () {
        slider.update();
      }, { once: true });
      image.addEventListener("error", function () {
        slider.update();
      }, { once: true });
    });

    window.setTimeout(function () {
      slider.update();
    }, 0);
  };

  FnllaSlider.prototype.visibleStart = function (settings) {
    if (!settings.centerMode) {
      return Math.max(0, Math.min(this.index, Math.max(0, this.items.length - settings.slidesToShow)));
    }

    var offset = Math.floor(settings.slidesToShow / 2);
    return Math.max(0, Math.min(this.index - offset, Math.max(0, this.items.length - settings.slidesToShow)));
  };

  FnllaSlider.prototype.prev = function () {
    var settings = this.currentSettings();
    var step = settings.slidesToScroll || 1;
    var maxIndex = settings.centerMode ? this.items.length - 1 : Math.max(0, this.items.length - settings.slidesToShow);
    var nextIndex = this.index - step < 0 ? maxIndex : this.index - step;

    this.goTo(nextIndex);
  };

  FnllaSlider.prototype.next = function () {
    var settings = this.currentSettings();
    var step = settings.slidesToScroll || 1;
    var maxIndex = settings.centerMode ? this.items.length - 1 : Math.max(0, this.items.length - settings.slidesToShow);
    var nextIndex = this.index + step > maxIndex ? 0 : this.index + step;

    this.goTo(nextIndex);
  };

  FnllaSlider.prototype.goTo = function (index) {
    this.index = index;
    this.update();
    this.startAutoplay();
  };

  FnllaSlider.prototype.measureSlides = function (slidesToShow) {
    var sample = this.items[0];
    var sampleStyles = window.getComputedStyle(sample);
    var marginLeft = parseFloat(sampleStyles.marginLeft) || 0;
    var marginRight = parseFloat(sampleStyles.marginRight) || 0;
    var horizontalMargins = marginLeft + marginRight;
    var viewportWidth = this.viewport.clientWidth || this.root.clientWidth || 0;
    var slideWidth = Math.max(0, (viewportWidth / slidesToShow) - horizontalMargins);

    return {
      width: slideWidth,
      outerWidth: slideWidth + horizontalMargins
    };
  };

  FnllaSlider.prototype.centerOffset = function (slideOuterWidth) {
    var viewportWidth = this.viewport.clientWidth || this.root.clientWidth || 0;
    var maxOffset = Math.max(0, (this.items.length * slideOuterWidth) - viewportWidth);
    var centered = (this.index * slideOuterWidth) - ((viewportWidth - slideOuterWidth) / 2);

    return Math.max(0, Math.min(centered, maxOffset));
  };

  FnllaSlider.prototype.layoutMarquee = function () {
    var speed = this.currentSettings().marqueeSpeed || 40;
    var slider = this;

    this.renderItems.forEach(function (item) {
      item.style.width = "auto";
      item.style.flexBasis = "auto";
      item.setAttribute("aria-hidden", "false");
    });

    this.track.style.width = "max-content";

    this.marqueeWidth = this.items.reduce(function (total, item) {
      var styles = window.getComputedStyle(item);
      var marginLeft = parseFloat(styles.marginLeft) || 0;
      var marginRight = parseFloat(styles.marginRight) || 0;

      return total + item.getBoundingClientRect().width + marginLeft + marginRight;
    }, 0);

    if (!this.marqueeWidth) {
      return;
    }

    this.root.style.setProperty("--fnlla-slider-marquee-distance", this.marqueeWidth + "px");
    this.root.style.setProperty("--fnlla-slider-marquee-duration", (this.marqueeWidth / speed) + "s");
    this.root.classList.add("is-marquee-active");

    window.setTimeout(function () {
      slider.track.style.transform = "translate3d(0, 0, 0)";
    }, 0);
  };

  FnllaSlider.prototype.update = function () {
    var settings = this.currentSettings();
    var slidesToShow = Math.max(1, settings.fade ? 1 : settings.slidesToShow || 1);
    var maxIndex = settings.centerMode ? this.items.length - 1 : Math.max(0, this.items.length - slidesToShow);
    var visibleStart;
    var slider = this;

    if (this.index > maxIndex) {
      this.index = maxIndex;
    }

    if (this.index < 0) {
      this.index = 0;
    }

    this.root.classList.toggle("fnlla-slider-fade", !!settings.fade);
    this.root.classList.toggle("fnlla-slider-marquee", !!settings.marquee);

    if (settings.marquee) {
      this.layoutMarquee();
      return;
    }

    visibleStart = this.visibleStart(settings);

    if (settings.fade) {
      var tallestSlide = this.items.reduce(function (height, item) {
        return Math.max(height, item.offsetHeight);
      }, 0);

      this.viewport.style.height = (tallestSlide || this.items[this.index].offsetHeight) + "px";
      this.slideMetrics = null;
    } else {
      this.slideMetrics = this.measureSlides(slidesToShow);
    }

    this.items.forEach(function (item, index) {
      var isCurrent = index === slider.index;
      var isVisible = settings.fade
        ? isCurrent
        : index >= visibleStart && index < visibleStart + slidesToShow;

      item.classList.toggle("is-current", isCurrent);
      item.classList.toggle("slick-current", isCurrent);
      item.classList.toggle("is-active", isVisible);
      item.classList.toggle("slick-active", isVisible);
      item.classList.toggle("is-center", settings.centerMode && isCurrent);
      item.classList.toggle("slick-center", settings.centerMode && isCurrent);
      item.setAttribute("aria-hidden", isVisible ? "false" : "true");

      if (!settings.fade) {
        item.style.width = slider.slideMetrics.width + "px";
        item.style.flexBasis = slider.slideMetrics.width + "px";
      }
    });

    if (settings.fade) {
      this.track.style.transform = "none";
      this.track.style.width = "";
    } else {
      var offset = settings.centerMode
        ? this.centerOffset(this.slideMetrics.outerWidth)
        : visibleStart * this.slideMetrics.outerWidth;

      this.track.style.width = (this.slideMetrics.outerWidth * this.items.length) + "px";
      this.track.style.transform = "translate3d(" + String(-offset) + "px, 0, 0)";
    }

    this.dots.forEach(function (dot, index) {
      dot.classList.toggle("is-active", index === slider.index);
      dot.classList.toggle("slick-active", index === slider.index);
    });

    if (this.prevButton || this.nextButton) {
      var canMove = this.items.length > slidesToShow;

      if (this.prevButton) {
        this.prevButton.setAttribute("aria-disabled", canMove ? "false" : "true");
      }

      if (this.nextButton) {
        this.nextButton.setAttribute("aria-disabled", canMove ? "false" : "true");
      }
    }
  };

  FnllaSlider.prototype.stopAutoplay = function () {
    if (this.timer) {
      window.clearInterval(this.timer);
      this.timer = null;
    }
  };

  FnllaSlider.prototype.startAutoplay = function () {
    var slider = this;
    var settings = this.currentSettings();

    this.stopAutoplay();

    if (settings.marquee) {
      this.layoutMarquee();
      return;
    }

    if (!settings.autoplay || this.items.length <= 1) {
      return;
    }

    this.timer = window.setInterval(function () {
      slider.next();
    }, settings.autoplaySpeed || 3000);
  };

  function refreshSliderInstance(element) {
    var slider = element ? sliderStateMap.get(element) : null;

    if (slider && typeof slider.update === "function") {
      slider.update();
      slider.startAutoplay();
      return slider;
    }

    return null;
  }

  function initSliders(root) {
    getScopedMatches(root, selectors.slider).forEach(function (element) {
      if (initializationState.slider.has(element)) {
        refreshSliderInstance(element);
        return;
      }

      initializationState.slider.add(element);
      sliderStateMap.set(element, new FnllaSlider(element));
    });
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: SCROLLSPY INITIALIZER
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
  FNLLA Runtime SOURCE MODULE: CONSENT INITIALIZER
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

  function initConsent(root) {
    getScopedMatches(root, selectors.consent).forEach(function (element) {
      if (initializationState.consent.has(element)) {
        return;
      }

      initializationState.consent.add(element);
      element.setAttribute("aria-hidden", "true");
    });

    getScopedMatches(root, selectors.consentOpen).forEach(function (button) {
      if (initializationState.consentOpen.has(button)) {
        return;
      }

      initializationState.consentOpen.add(button);
      button.addEventListener("click", function (event) {
        var modal = getConsentModalElement();

        event.preventDefault();
        syncConsentInputs(modal || document, getStoredConsentSnapshot());

        if (modal) {
          openModal(modal, button);
        }
      });
    });

    getScopedMatches(root, selectors.consentAccept).forEach(function (button) {
      if (initializationState.consentAccept.has(button)) {
        return;
      }

      initializationState.consentAccept.add(button);
      button.addEventListener("click", function (event) {
        var mode = button.getAttribute("data-fnlla-consent-accept");
        var modal = button.closest(selectors.modal);
        var scope = modal || button.closest(selectors.consent) || document;
        var nextState = {};

        event.preventDefault();
        getSupportedConsentCategories(scope).forEach(function (category) {
          nextState[category] = mode !== "necessary";
        });

        saveConsentState(nextState);

        if (modal) {
          closeModal(modal);
        }
      });
    });

    getScopedMatches(root, selectors.consentSave).forEach(function (button) {
      if (initializationState.consentSave.has(button)) {
        return;
      }

      initializationState.consentSave.add(button);
      button.addEventListener("click", function (event) {
        var scope = button.closest(selectors.modal) || button.closest(selectors.consent) || document;
        var modal = button.closest(selectors.modal);

        event.preventDefault();
        saveConsentState(collectConsentStateFromScope(scope));

        if (modal) {
          closeModal(modal);
        }
      });
    });

    getScopedMatches(root, selectors.consentReset).forEach(function (button) {
      if (initializationState.consentReset.has(button)) {
        return;
      }

      initializationState.consentReset.add(button);
      button.addEventListener("click", function (event) {
        event.preventDefault();
        clearCookieValue(getConsentCookieName());
        syncConsentState();
      });
    });

    syncConsentState();
  }

/*
  ============================================================================
  FNLLA Runtime SOURCE MODULE: RUNTIME BINDING AND PUBLIC API
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
  function initFnllaRuntime(root) {
    var scope = normalizeRoot(root);

    cleanupDetachedScrollspyInstances();
    document.documentElement.classList.add(runtimeEnhancementClass);
    syncDocumentTitle();
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
    initStickies(scope);
    initCounters(scope);
    initPasswordToggles(scope);
    initSteppers(scope);
    initFilters(scope);
    initSliders(scope);
    initScrollspy(scope);
    initConsent(scope);
    syncNavigationMode(scope);

    return fnllaRuntimeApi;
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
    initFnllaRuntime(document);
  }

  /*
    Public API surface:
    keep this small, explicit and stable.

    These methods intentionally map to resolved runtime primitives rather than
    exposing internal state maps or event wiring details. That gives maintainers
    room to evolve internals without breaking downstream projects.
  */
  var fnllaRuntimeApi = {
    version: fnllaRuntimeVersion,
    init: initFnllaRuntime,
    getDocumentTitle: function () {
      return document.title;
    },
    getDocumentTitleConfig: function () {
      return readDocumentTitleConfig();
    },
    syncDocumentTitle: function (config) {
      syncDocumentTitle(config);
      return fnllaRuntimeApi;
    },
    setDocumentTitle: function (config) {
      syncDocumentTitle(config);
      return fnllaRuntimeApi;
    },
    setTheme: function (theme, target) {
      var themeTarget = resolveThemeTarget(target);

      if (themeTarget) {
        themeTarget.setAttribute("data-fnlla-theme", normalizeThemeName(theme));
      }

      return fnllaRuntimeApi;
    },
    showModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        openModal(modal);
      }

      return fnllaRuntimeApi;
    },
    openModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        openModal(modal);
      }

      return fnllaRuntimeApi;
    },
    hideModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        closeModal(modal);
      }

      return fnllaRuntimeApi;
    },
    closeModal: function (target) {
      var modal = resolveElementReference(target, selectors.modal);

      if (modal) {
        closeModal(modal);
      }

      return fnllaRuntimeApi;
    },
    showToast: function (target) {
      var toast = resolveElementReference(target, selectors.toast);

      if (toast) {
        showToast(toast);
      }

      return fnllaRuntimeApi;
    },
    hideToast: function (target) {
      var toast = resolveElementReference(target, selectors.toast);

      if (toast) {
        hideToast(toast);
      }

      return fnllaRuntimeApi;
    },
    showOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        openOffcanvas(offcanvas);
      }

      return fnllaRuntimeApi;
    },
    openOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        openOffcanvas(offcanvas);
      }

      return fnllaRuntimeApi;
    },
    hideOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        closeOffcanvas(offcanvas);
      }

      return fnllaRuntimeApi;
    },
    closeOffcanvas: function (target) {
      var offcanvas = resolveElementReference(target, selectors.offcanvas);

      if (offcanvas) {
        closeOffcanvas(offcanvas);
      }

      return fnllaRuntimeApi;
    },
    openDropdown: function (target) {
      var dropdown = resolveElementReference(target, selectors.dropdown);

      if (dropdown) {
        openDropdown(dropdown);
      }

      return fnllaRuntimeApi;
    },
    closeDropdown: function (target) {
      var dropdown = resolveElementReference(target, selectors.dropdown);

      if (dropdown) {
        closeDropdown(dropdown);
      }

      return fnllaRuntimeApi;
    },
    openPopover: function (target) {
      var popover = resolveElementReference(target, selectors.popover);

      if (popover) {
        openPopover(popover);
      }

      return fnllaRuntimeApi;
    },
    closePopover: function (target) {
      var popover = resolveElementReference(target, selectors.popover);

      if (popover) {
        closePopover(popover);
      }

      return fnllaRuntimeApi;
    },
    showTooltip: function (target) {
      var trigger = resolveElementReference(target, selectors.tooltipTrigger);

      if (trigger) {
        showTooltip(trigger);
      }

      return fnllaRuntimeApi;
    },
    hideTooltip: function (target) {
      var trigger = resolveElementReference(target, selectors.tooltipTrigger);

      if (trigger) {
        hideTooltip(trigger);
      }

      return fnllaRuntimeApi;
    },
    refreshScrollspy: function (target) {
      var container = resolveElementReference(target, selectors.scrollspy);
      var state = container ? scrollspyObserverMap.get(container) : null;

      if (state && typeof state.update === "function") {
        state.update();
      }

      return fnllaRuntimeApi;
    },
    syncSticky: function () {
      syncStickyElements();
      return fnllaRuntimeApi;
    },
    refreshSlider: function (target) {
      var slider = resolveElementReference(target, selectors.slider);
      var state = slider ? sliderStateMap.get(slider) : null;

      if (state && typeof state.update === "function") {
        state.update();
      }

      return fnllaRuntimeApi;
    },
    getConsentState: function () {
      return cloneConsentState(syncConsentState());
    },
    hasConsent: function (category) {
      var state = syncConsentState();

      if (category === "necessary") {
        return true;
      }

      return state[category] === true;
    },
    openConsentSettings: function () {
      var modal = getConsentModalElement();

      syncConsentInputs(modal || document, getStoredConsentSnapshot());

      if (modal) {
        openModal(modal);
      }

      return fnllaRuntimeApi;
    },
    acceptConsent: function () {
      var nextState = {};

      getSupportedConsentCategories().forEach(function (category) {
        nextState[category] = true;
      });

      saveConsentState(nextState);
      return fnllaRuntimeApi;
    },
    rejectConsent: function () {
      var nextState = {};

      getSupportedConsentCategories().forEach(function (category) {
        nextState[category] = false;
      });

      saveConsentState(nextState);
      return fnllaRuntimeApi;
    },
    saveConsent: function (state) {
      saveConsentState(state);
      return fnllaRuntimeApi;
    },
    resetConsent: function () {
      clearCookieValue(getConsentCookieName());
      syncConsentState();
      return fnllaRuntimeApi;
    }
  };

  window.FNLLARUNTIME = fnllaRuntimeApi;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoInit);
  } else {
    autoInit();
  }
})();
