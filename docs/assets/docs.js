/*
  ============================================================================
  Documentation-only behavior for FNLLA PHP docs.
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

(function () {
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var storageKey = "fnlla-php-docs-theme";
  var themeColors = {
    default: "#18352f",
    dark: "#0d1723"
  };

  function normalizeTheme(theme) {
    return theme === "dark" ? "dark" : "default";
  }

  function readStoredTheme() {
    try {
      return normalizeTheme(window.localStorage.getItem(storageKey));
    } catch (error) {
      return "default";
    }
  }

  function storeTheme(theme) {
    try {
      window.localStorage.setItem(storageKey, normalizeTheme(theme));
    } catch (error) {
      return;
    }
  }

  function applyTheme(theme) {
    var normalizedTheme = normalizeTheme(theme);
    var themeToggle = document.querySelector("[data-doc-theme-toggle]");
    var themeMeta = document.querySelector('meta[name="theme-color"]');

    if (window.FNLLAUI && typeof window.FNLLAUI.setTheme === "function") {
      window.FNLLAUI.setTheme(normalizedTheme);
    } else if (document.body) {
      document.body.setAttribute("data-fnlla-theme", normalizedTheme);
    }

    if (themeToggle) {
      themeToggle.checked = normalizedTheme === "dark";
    }

    if (themeMeta) {
      themeMeta.setAttribute("content", themeColors[normalizedTheme]);
    }
  }

  function initThemeToggle() {
    var themeToggle = document.querySelector("[data-doc-theme-toggle]");
    applyTheme(readStoredTheme());

    if (!themeToggle) {
      return;
    }

    themeToggle.addEventListener("change", function () {
      var nextTheme = themeToggle.checked ? "dark" : "default";
      applyTheme(nextTheme);
      storeTheme(nextTheme);
    });
  }

  function initDocsNav() {
    var nav = document.querySelector(".doc-nav");

    if (!nav) {
      return;
    }

    var toggle = nav.querySelector("[data-doc-nav-toggle]");
    var panel = nav.querySelector("[data-doc-nav-panel]");
    var mobileQuery = window.matchMedia ? window.matchMedia("(max-width: 47.9375rem)") : null;

    if (!toggle || !panel || !mobileQuery) {
      return;
    }

    function syncNavState(isOpen) {
      nav.classList.toggle("is-open", isOpen);
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      panel.hidden = !isOpen;
    }

    function syncNavMode() {
      if (mobileQuery.matches) {
        syncNavState(false);
        return;
      }

      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      panel.hidden = false;
    }

    toggle.addEventListener("click", function () {
      if (!mobileQuery.matches) {
        return;
      }

      syncNavState(panel.hidden);
    });

    if (typeof mobileQuery.addEventListener === "function") {
      mobileQuery.addEventListener("change", syncNavMode);
    } else if (typeof mobileQuery.addListener === "function") {
      mobileQuery.addListener(syncNavMode);
    }

    syncNavMode();
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function wrapToken(className, text) {
    return '<span class="' + className + '">' + escapeHtml(text) + "</span>";
  }

  function highlightTextSegment(text, className) {
    if (!text) {
      return "";
    }

    if (!text.trim()) {
      return escapeHtml(text);
    }

    var leadingWhitespaceMatch = text.match(/^\s*/);
    var trailingWhitespaceMatch = text.match(/\s*$/);
    var leadingWhitespace = leadingWhitespaceMatch ? leadingWhitespaceMatch[0] : "";
    var trailingWhitespace = trailingWhitespaceMatch ? trailingWhitespaceMatch[0] : "";
    var core = text.slice(leadingWhitespace.length, text.length - trailingWhitespace.length);
    return escapeHtml(leadingWhitespace)
      + (core ? wrapToken(className || "doc-code-content", core) : "")
      + escapeHtml(trailingWhitespace);
  }

  function renderHtmlAttributes(attributesText) {
    var pattern = /([^\s=/>]+)(\s*=\s*)?(".*?"|'.*?'|[^\s>]+)?/g;
    var result = "";
    var lastIndex = 0;
    var match;

    while ((match = pattern.exec(attributesText))) {
      result += escapeHtml(attributesText.slice(lastIndex, match.index));
      result += wrapToken("doc-code-attr", match[1]);

      if (match[2]) {
        var separator = match[2];
        var equalsIndex = separator.indexOf("=");
        result += escapeHtml(separator.slice(0, equalsIndex));
        result += wrapToken("doc-code-punctuation", "=");
        result += escapeHtml(separator.slice(equalsIndex + 1));
      }

      if (match[3]) {
        result += wrapToken("doc-code-string", match[3]);
      }

      lastIndex = pattern.lastIndex;
    }

    result += escapeHtml(attributesText.slice(lastIndex));
    return result;
  }

  function renderHtmlTag(tagText) {
    if (/^<!DOCTYPE/i.test(tagText)) {
      return wrapToken("doc-code-doctype", tagText);
    }

    if (/^<!--/.test(tagText)) {
      return wrapToken("doc-code-comment", tagText);
    }

    var closingTagMatch = tagText.match(/^<\/\s*([^\s>]+)\s*>$/);

    if (closingTagMatch) {
      return wrapToken("doc-code-punctuation", "</")
        + wrapToken("doc-code-tag", closingTagMatch[1])
        + wrapToken("doc-code-punctuation", ">");
    }

    var openingTagMatch = tagText.match(/^<\s*([^\s/>]+)([\s\S]*?)(\/?)>$/);

    if (!openingTagMatch) {
      return wrapToken("doc-code-content", tagText);
    }

    return wrapToken("doc-code-punctuation", "<")
      + wrapToken("doc-code-tag", openingTagMatch[1])
      + renderHtmlAttributes(openingTagMatch[2] || "")
      + (openingTagMatch[3] ? wrapToken("doc-code-punctuation", "/") : "")
      + wrapToken("doc-code-punctuation", ">");
  }

  function renderHtmlCode(text) {
    var tagPattern = /<!--[\s\S]*?-->|<!DOCTYPE[^>]*>|<\/?[^>\n]+>/g;
    var result = "";
    var lastIndex = 0;
    var match;

    while ((match = tagPattern.exec(text))) {
      result += highlightTextSegment(text.slice(lastIndex, match.index), "doc-code-content");
      result += renderHtmlTag(match[0]);
      lastIndex = tagPattern.lastIndex;
    }

    result += highlightTextSegment(text.slice(lastIndex), "doc-code-content");
    return result;
  }

  function renderCssValue(valueText) {
    var cssValueTokenPattern = /\/\*[\s\S]*?\*\/|var(?=\()|#[0-9a-fA-F]{3,8}\b|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|--[\w-]+|\b-?\d+(?:\.\d+)?(?:%|[a-z]+)?\b|[A-Za-z_-][\w-]*(?=\()|\b(?:auto|inherit|initial|unset|none|solid|dashed|relative|absolute|fixed|sticky|block|inline|inline-block|flex|grid|center|between|start|end|repeat|minmax|max-content|min-content|cover|contain|transparent|currentColor)\b|[(),/:]/g;
    var result = "";
    var lastIndex = 0;
    var match;

    if (!valueText) {
      return "";
    }

    while ((match = cssValueTokenPattern.exec(valueText))) {
      var token = match[0];
      var className = "doc-code-content";

      result += escapeHtml(valueText.slice(lastIndex, match.index));

      if (/^(?:\/\*)/.test(token)) {
        className = "doc-code-comment";
      } else if (/^var$/.test(token)) {
        className = "doc-code-function";
      } else if (/^#/.test(token) || /^['"]/.test(token)) {
        className = "doc-code-string";
      } else if (/^--/.test(token)) {
        className = "doc-code-variable";
      } else if (/^-?\d/.test(token)) {
        className = "doc-code-number";
      } else if (/^[A-Za-z_-][\w-]*(?=\()/.test(token)) {
        className = "doc-code-function";
      } else if (/^(?:auto|inherit|initial|unset|none|solid|dashed|relative|absolute|fixed|sticky|block|inline|inline-block|flex|grid|center|between|start|end|repeat|minmax|max-content|min-content|cover|contain|transparent|currentColor)$/.test(token)) {
        className = "doc-code-keyword";
      } else {
        className = "doc-code-operator";
      }

      result += wrapToken(className, token);
      lastIndex = cssValueTokenPattern.lastIndex;
    }

    result += escapeHtml(valueText.slice(lastIndex));
    return result;
  }

  function renderCssCode(text) {
    return text.split("\n").map(function (line) {
      if (!line.trim()) {
        return "";
      }

      if (/^\s*\/\*/.test(line)) {
        return highlightTextSegment(line, "doc-code-comment");
      }

      var closingBraceMatch = line.match(/^(\s*)(})(\s*;?\s*)$/);

      if (closingBraceMatch) {
        return escapeHtml(closingBraceMatch[1])
          + wrapToken("doc-code-punctuation", closingBraceMatch[2])
          + escapeHtml(closingBraceMatch[3]);
      }

      var propertyMatch = line.match(/^(\s*)(--[\w-]+|[\w-]+)(\s*:\s*)(.*?)(;?\s*)$/);

      if (propertyMatch) {
        var separator = propertyMatch[3];
        var colonIndex = separator.indexOf(":");
        var propertyClassName = /^--/.test(propertyMatch[2]) ? "doc-code-variable" : "doc-code-property";
        return escapeHtml(propertyMatch[1])
          + wrapToken(propertyClassName, propertyMatch[2])
          + escapeHtml(separator.slice(0, colonIndex))
          + wrapToken("doc-code-punctuation", ":")
          + escapeHtml(separator.slice(colonIndex + 1))
          + renderCssValue(propertyMatch[4])
          + (propertyMatch[5].indexOf(";") !== -1 ? wrapToken("doc-code-punctuation", ";") : "")
          + escapeHtml(propertyMatch[5].replace(";", ""));
      }

      var openingBraceMatch = line.match(/^(\s*)(.+?)(\s*)(\{)(\s*)$/);

      if (openingBraceMatch) {
        return escapeHtml(openingBraceMatch[1])
          + wrapToken("doc-code-selector", openingBraceMatch[2])
          + escapeHtml(openingBraceMatch[3])
          + wrapToken("doc-code-punctuation", openingBraceMatch[4])
          + escapeHtml(openingBraceMatch[5]);
      }

      return highlightTextSegment(line, "doc-code-content");
    }).join("\n");
  }

  function renderCommandCode(text) {
    return text.split("\n").map(function (line) {
      var commandTokenPattern;

      if (!line.trim()) {
        return "";
      }

      var indentMatch = line.match(/^\s*/);
      var indent = indentMatch ? indentMatch[0] : "";
      var core = line.trim();

      if (/^#/.test(core)) {
        return escapeHtml(indent) + wrapToken("doc-code-comment", core);
      }

      commandTokenPattern = /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|--?[\w.-]+|(?:\.\.?[\\/]|\/)?[\w./\\:-]+|[|&><]/g;

      var result = escapeHtml(indent);
      var lastIndex = 0;
      var match;
      var tokenIndex = 0;

      while ((match = commandTokenPattern.exec(core))) {
        var token = match[0];
        var className = "doc-code-content";

        result += escapeHtml(core.slice(lastIndex, match.index));

        if (tokenIndex === 0) {
          className = "doc-code-command";
        } else if (/^['"]/.test(token)) {
          className = "doc-code-string";
        } else if (/^--?[\w.-]+$/.test(token)) {
          className = "doc-code-property";
        } else if (isPathLikeCodeText(token)) {
          className = "doc-code-path";
        } else {
          className = "doc-code-operator";
        }

        result += wrapToken(className, token);
        lastIndex = commandTokenPattern.lastIndex;
        tokenIndex++;
      }

      result += escapeHtml(core.slice(lastIndex));
      return result;
    }).join("\n");
  }

  function renderTreeCode(text) {
    return text.split("\n").map(function (line) {
      if (!line.trim()) {
        return "";
      }

      var indentMatch = line.match(/^\s*/);
      var indent = indentMatch ? indentMatch[0] : "";
      var core = line.trim();
      var className = /\/$/.test(core) ? "doc-code-path" : "doc-code-content";
      return escapeHtml(indent) + wrapToken(className, core);
    }).join("\n");
  }

  function isKnownDotfile(text) {
    return /^\.(?:env(?:\.example)?|git|github|gitignore|gitattributes|editorconfig|htaccess|npmrc|nvmrc|prettierignore|prettierrc|eslintignore|eslintrc(?:\.[\w-]+)?)$/i.test(text);
  }

  function isPathLikeCodeText(text) {
    return /^(?:\.\.?[\\/]|\/|[A-Za-z]:[\\/])[\w .\-\\/]+$/.test(text)
      || /^(?:[\w.-]+[\\/])+[\w.-]+\/?$/.test(text)
      || /^[\w.-]+\.(?:css|js|mjs|html|md|svg|php|json|txt|yml|yaml|xml|sql|ps1|sh|cmd|env|lock)$/i.test(text)
      || isKnownDotfile(text);
  }

  function renderPhpSegment(text) {
    var phpKeywordPattern = /^(?:__halt_compiler|abstract|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|enum|exit|extends|false|final|finally|fn|for|foreach|from|function|global|if|implements|include|include_once|instanceof|interface|isset|list|match|namespace|new|null|parent|private|protected|public|readonly|require|require_once|return|self|static|switch|throw|trait|true|try|use|var|while|yield)\b/;
    var phpTokenPattern = /<\?(?:php|=)?|\?>|\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\$[A-Za-z_\x80-\xff][\w\x80-\xff]*|\b\d+(?:\.\d+)?\b|[A-Za-z_\x80-\xff][\w\x80-\xff]*(?=\s*\()|\?->|->|::|=>|===|!==|==|!=|<=|>=|&&|\|\||\?\?|[()[\]{}.,;:+\-*\/%=&|!<>?:]/g;
    var result = "";
    var lastIndex = 0;
    var match;

    while ((match = phpTokenPattern.exec(text))) {
      var token = match[0];
      var className = "doc-code-content";

      result += escapeHtml(text.slice(lastIndex, match.index));

      if (/^<\?/.test(token) || token === "?>") {
        className = "doc-code-operator";
      } else if (/^(?:\/\*|\/\/|#)/.test(token)) {
        className = "doc-code-comment";
      } else if (/^['"]/.test(token)) {
        className = "doc-code-string";
      } else if (/^\d/.test(token)) {
        className = "doc-code-number";
      } else if (/^\$/.test(token)) {
        className = "doc-code-variable";
      } else if (phpKeywordPattern.test(token)) {
        className = "doc-code-keyword";
      } else if (/^[A-Za-z_\x80-\xff]/.test(token)) {
        className = "doc-code-function";
      } else {
        className = "doc-code-operator";
      }

      result += wrapToken(className, token);
      lastIndex = phpTokenPattern.lastIndex;
    }

    result += escapeHtml(text.slice(lastIndex));
    return result;
  }

  function renderPhpCode(text) {
    var phpBlockPattern = /<\?(?:php|=)?[\s\S]*?(?:\?>|$)/g;
    var hasPhpTags = /<\?/.test(text);
    var result = "";
    var lastIndex = 0;
    var match;

    if (!hasPhpTags) {
      return renderPhpSegment(text);
    }

    while ((match = phpBlockPattern.exec(text))) {
      result += renderHtmlCode(text.slice(lastIndex, match.index));
      result += renderPhpSegment(match[0]);
      lastIndex = phpBlockPattern.lastIndex;
    }

    result += renderHtmlCode(text.slice(lastIndex));
    return result;
  }

  function renderJavascriptCode(text) {
    var javascriptKeywordPattern = /^(?:await|async|break|case|catch|class|const|continue|default|delete|do|else|export|extends|false|finally|for|function|if|import|in|instanceof|let|new|null|return|super|switch|this|throw|true|try|typeof|undefined|var|void|while|yield)\b/;
    var javascriptTokenPattern = /\/\*[\s\S]*?\*\/|\/\/[^\n]*|`(?:\\.|[^`\\])*`|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b\d+(?:\.\d+)?\b|[A-Za-z_$][\w$]*(?=\s*\()|=>|===|!==|==|!=|<=|>=|&&|\|\||\?\?|\.\.\.|[()[\]{}.,;:+\-*\/%=&|!<>?:]/g;
    var result = "";
    var lastIndex = 0;
    var match;

    while ((match = javascriptTokenPattern.exec(text))) {
      var token = match[0];
      var className = "doc-code-content";

      result += escapeHtml(text.slice(lastIndex, match.index));

      if (/^(?:\/\*|\/\/)/.test(token)) {
        className = "doc-code-comment";
      } else if (/^['"`]/.test(token)) {
        className = "doc-code-string";
      } else if (/^\d/.test(token)) {
        className = "doc-code-number";
      } else if (javascriptKeywordPattern.test(token)) {
        className = "doc-code-keyword";
      } else if (/^[A-Za-z_$]/.test(token)) {
        className = "doc-code-function";
      } else {
        className = "doc-code-operator";
      }

      result += wrapToken(className, token);
      lastIndex = javascriptTokenPattern.lastIndex;
    }

    result += escapeHtml(text.slice(lastIndex));
    return result;
  }

  function renderJsonCode(text) {
    var jsonTokenPattern = /"(?:\\.|[^"\\])*"(?=\s*:)|"(?:\\.|[^"\\])*"|\b-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b|\b(?:true|false|null)\b|[{}\[\]:,]/g;
    var result = "";
    var lastIndex = 0;
    var match;

    while ((match = jsonTokenPattern.exec(text))) {
      var token = match[0];
      var className = "doc-code-content";

      result += escapeHtml(text.slice(lastIndex, match.index));

      if (/^"/.test(token) && /^\s*:/.test(text.slice(jsonTokenPattern.lastIndex))) {
        className = "doc-code-attr";
      } else if (/^"/.test(token)) {
        className = "doc-code-string";
      } else if (/^-?\d/.test(token)) {
        className = "doc-code-number";
      } else if (/^(?:true|false|null)$/.test(token)) {
        className = "doc-code-keyword";
      } else {
        className = "doc-code-operator";
      }

      result += wrapToken(className, token);
      lastIndex = jsonTokenPattern.lastIndex;
    }

    result += escapeHtml(text.slice(lastIndex));
    return result;
  }

  function renderPlainCode(text) {
    return text.split("\n").map(function (line) {
      if (!line) {
        return "";
      }

      var indentMatch = line.match(/^\s*/);
      var indent = indentMatch ? indentMatch[0] : "";
      var core = line.trim();

      if (core && isPathLikeCodeText(core)) {
        return escapeHtml(indent) + wrapToken("doc-code-path", core);
      }

      return highlightTextSegment(line, "doc-code-content");
    }).join("\n");
  }

  function detectPreCodeKind(codeElement, sourceText) {
    var className = codeElement.className || "";

    if (/\blanguage-html\b/i.test(className)) {
      return "html";
    }

    if (/\blanguage-css\b/i.test(className)) {
      return "css";
    }

    if (/\blanguage-php\b/i.test(className)) {
      return "php";
    }

    if (/\blanguage-(?:js|javascript)\b/i.test(className)) {
      return "javascript";
    }

    if (/\blanguage-json\b/i.test(className)) {
      return "json";
    }

    if (/\blanguage-text\b/i.test(className)) {
      if (/\n/.test(sourceText) && /(^|\n)\s*[\w.-]+\/\s*$/m.test(sourceText)) {
        return "tree";
      }

      return "plain";
    }

    if (/\blanguage-(?:bash|sh|shell|powershell|ps1|cmd)\b/i.test(className)) {
      return "command";
    }

    if (/(^|\n)\s*<\/?[a-z][^>\n]*>/i.test(sourceText) || /<!DOCTYPE/i.test(sourceText)) {
      return "html";
    }

    if ((/(^|\n)\s*[@[.#:\w-][^{\n]*\{\s*$/m.test(sourceText) || /(^|\n)\s*--[\w-]+\s*:/m.test(sourceText)) && /\}/.test(sourceText)) {
      return "css";
    }

    if (/(?:<\?(?:php|=)?|\$\w+|->|::|\b(?:public|protected|private|static|final|abstract)\s+function\b|\bnamespace\s+[\w\\]+;|\buse\s+[\w\\]+;)/.test(sourceText)) {
      return "php";
    }

    if (/^\s*[\[{]/.test(sourceText) && /"\s*:/.test(sourceText)) {
      return "json";
    }

    if (/(^|\n)\s*(?:const|let|var|function|export|import)\b/.test(sourceText) || /\bwindow\.[A-Za-z_$][\w$]*\b/.test(sourceText)) {
      return "javascript";
    }

    if (/(^|\n)\s*(node|npm|npx|pnpm|yarn|php|curl|git|rg|Test-Path)\b/m.test(sourceText)) {
      return "command";
    }

    if (/\n/.test(sourceText) && /(^|\n)\s*[\w.-]+\/\s*$/m.test(sourceText)) {
      return "tree";
    }

    return "plain";
  }

  function renderInlineCode(sourceText) {
    if (/^--[\w-]+$/.test(sourceText)) {
      return wrapToken("doc-code-variable", sourceText);
    }

    if (isPathLikeCodeText(sourceText)) {
      return wrapToken("doc-code-path", sourceText);
    }

    if (/^(?:\.[\w-]+|\[[^\]]+\]|#[\w-]+)$/.test(sourceText)) {
      return wrapToken("doc-code-selector", sourceText);
    }

    if (/^[\w$.-]+\([^)]*\)$/.test(sourceText)) {
      return wrapToken("doc-code-function", sourceText);
    }

    return escapeHtml(sourceText);
  }

  function clearPreCodeKindClasses(preElement) {
    preElement.classList.remove("is-html", "is-css", "is-php", "is-javascript", "is-json", "is-command", "is-tree", "is-plain");
  }

  function highlightCodeElement(codeElement) {
    if (!codeElement) {
      return;
    }

    var sourceText = codeElement.textContent.replace(/\r\n/g, "\n");
    var sourceKey = sourceText;
    var inPre = codeElement.parentElement && codeElement.parentElement.tagName === "PRE";

    if (!sourceText.trim()) {
      codeElement.dataset.docHighlightSource = sourceKey;
      codeElement.dataset.docHighlightKind = inPre ? "plain" : "inline";
      return;
    }

    if (codeElement.dataset.docHighlightSource === sourceKey) {
      return;
    }

    if (inPre) {
      var preElement = codeElement.parentElement;
      var detectedKind = detectPreCodeKind(codeElement, sourceText);
      var renderedHtml = "";

      if (detectedKind === "html") {
        renderedHtml = renderHtmlCode(sourceText);
      } else if (detectedKind === "css") {
        renderedHtml = renderCssCode(sourceText);
      } else if (detectedKind === "php") {
        renderedHtml = renderPhpCode(sourceText);
      } else if (detectedKind === "javascript") {
        renderedHtml = renderJavascriptCode(sourceText);
      } else if (detectedKind === "json") {
        renderedHtml = renderJsonCode(sourceText);
      } else if (detectedKind === "command") {
        renderedHtml = renderCommandCode(sourceText);
      } else if (detectedKind === "tree") {
        renderedHtml = renderTreeCode(sourceText);
      } else {
        renderedHtml = renderPlainCode(sourceText);
      }

      clearPreCodeKindClasses(preElement);
      preElement.classList.add("doc-code-block", "is-" + detectedKind);
      codeElement.innerHTML = renderedHtml;
      codeElement.dataset.docHighlightKind = detectedKind;
      codeElement.dataset.docHighlightSource = sourceKey;
      return;
    }

    codeElement.innerHTML = renderInlineCode(sourceText);
    codeElement.dataset.docHighlightKind = "inline";
    codeElement.dataset.docHighlightSource = sourceKey;
  }

  function highlightCodeWithin(root) {
    if (!root || root.nodeType !== 1) {
      return;
    }

    if (root.matches && root.matches("code")) {
      highlightCodeElement(root);
    }

    if (!root.querySelectorAll) {
      return;
    }

    Array.prototype.forEach.call(root.querySelectorAll("code"), function (codeElement) {
      highlightCodeElement(codeElement);
    });
  }

  function initTocLinks() {
    var links = document.querySelectorAll(".doc-toc-list a[href^='#'], .doc-guide-toc-link[href^='#']");

    if (!links.length) {
      return;
    }

    Array.prototype.forEach.call(links, function (link) {
      link.addEventListener("click", function (event) {
        var targetId = link.getAttribute("href").slice(1);
        var target = document.getElementById(targetId);

        if (!target) {
          return;
        }

        event.preventDefault();
        target.scrollIntoView({
          behavior: reduceMotion ? "auto" : "smooth",
          block: "start"
        });

        if (window.history && typeof window.history.pushState === "function") {
          window.history.pushState(null, "", "#" + targetId);
        } else {
          window.location.hash = "#" + targetId;
        }
      });
    });
  }

  initThemeToggle();
  initDocsNav();
  highlightCodeWithin(document.body);
  initTocLinks();
})();
