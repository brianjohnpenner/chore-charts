(function () {
  const DB_NAME = "chore-chart-db";
  const DB_VERSION = 1;
  const STORE_NAME = "app-state";
  const CHART_KEY = "active-chart";
  const FILE_HANDLE_KEY = "json-file-handle";
  const INSTALL_PROMPT_DISMISSED_KEY = "chore-chart-install-dismissed";
  const ICON_FAVORITES_KEY = "chore-chart-icon-favorites";
  const CHART_VERSION = 2;
  const DAY_KEYS = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"];
  const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  const DAY_COLORS = ["#ded8ef", "#cfe0f8", "#fde6ca", "#f5c9cd", "#d2e2e6", "#dcefd7", "#fff2c7"];
  const ROW_TYPES = ["icon", "regular", "empty"];
  let pendingInstallPromptEvent = null;

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    pendingInstallPromptEvent = event;
    window.dispatchEvent(new CustomEvent("chorechart:installable"));
  });

  function openChartDatabase() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME);
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function readStoredValue(key) {
    return openChartDatabase().then((db) => new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE_NAME, "readonly");
      const request = transaction.objectStore(STORE_NAME).get(key);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
      transaction.oncomplete = () => db.close();
      transaction.onerror = () => {
        db.close();
        reject(transaction.error);
      };
    }));
  }

  function writeStoredValue(key, value) {
    return openChartDatabase().then((db) => new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE_NAME, "readwrite");
      const request = transaction.objectStore(STORE_NAME).put(value, key);
      request.onerror = () => reject(request.error);
      transaction.oncomplete = () => {
        db.close();
        resolve();
      };
      transaction.onerror = () => {
        db.close();
        reject(transaction.error);
      };
    }));
  }

  async function loadStoredChart() {
    try {
      const stored = await readStoredValue(CHART_KEY);
      if (stored) return normalizeChart(stored);

      const chart = defaultChart();
      await saveStoredChart(chart);
      return chart;
    } catch (error) {
      console.error("Could not load chart data from IndexedDB.", error);
      return defaultChart();
    }
  }

  async function saveStoredChart(chart) {
    await writeStoredValue(CHART_KEY, normalizeChart(clone(chart)));
  }

  async function loadFileHandle() {
    try {
      return await readStoredValue(FILE_HANDLE_KEY);
    } catch (error) {
      console.error("Could not load JSON file handle.", error);
      return null;
    }
  }

  async function saveFileHandle(handle) {
    try {
      await writeStoredValue(FILE_HANDLE_KEY, handle);
    } catch (error) {
      console.error("Could not remember JSON file handle.", error);
    }
  }

  function debounce(callback, delay = 400) {
    let timeout;
    return (...args) => {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => callback(...args), delay);
    };
  }

  function readLocalFlag(key) {
    try {
      return window.localStorage.getItem(key) === "1";
    } catch (error) {
      return false;
    }
  }

  function writeLocalFlag(key) {
    try {
      window.localStorage.setItem(key, "1");
    } catch (error) {
      // Local storage can be unavailable in private or restricted browsing.
    }
  }

  function readLocalJson(key, fallback) {
    try {
      const value = window.localStorage.getItem(key);
      return value ? JSON.parse(value) : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function writeLocalJson(key, value) {
    try {
      window.localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      // Local storage can be unavailable in private or restricted browsing.
    }
  }

  function isStandaloneApp() {
    return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  }

  function isIosSafari() {
    const userAgent = window.navigator.userAgent || "";
    const isIos = /iPad|iPhone|iPod/.test(userAgent) || (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
    const isSafari = /Safari/.test(userAgent) && !/CriOS|FxiOS|EdgiOS|OPiOS|Chrome/.test(userAgent);
    return isIos && isSafari;
  }

  function uid(prefix) {
    return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
  }

  function slugify(value) {
    const slug = String(value || "child")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
    return slug || "child";
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function chartJson(chart) {
    return JSON.stringify(normalizeChart(clone(chart)), null, 2);
  }

  function filePickerTypes() {
    return [{
      description: "Chore chart JSON",
      accept: { "application/json": [".json"] }
    }];
  }

  async function verifyFilePermission(handle, mode = "readwrite") {
    if (!handle || !handle.queryPermission || !handle.requestPermission) return false;
    if (await handle.queryPermission({ mode }) === "granted") return true;
    return await handle.requestPermission({ mode }) === "granted";
  }

  async function writeJsonFile(handle, chart) {
    const writable = await handle.createWritable();
    await writable.write(new Blob([chartJson(chart)], { type: "application/json" }));
    await writable.close();
  }

  function days() {
    return DAY_KEYS.map((key, index) => ({
      key,
      label: DAY_LABELS[index],
      color: DAY_COLORS[index]
    }));
  }

  function daySelection(defaultValue = true) {
    return Object.fromEntries(DAY_KEYS.map((key) => [key, defaultValue]));
  }

  function choreRow(type, label = "", icon = "wash-machine", paid = false, selectedDays = daySelection(true), showIcon = type === "icon", showLabel = type !== "icon") {
    return {
      id: uid(type),
      type,
      label,
      icon,
      showIcon,
      showLabel,
      paid,
      days: selectedDays
    };
  }

  function weeklyRow(type, label = "", paid = false, icon = "", showIcon = type === "icon", showLabel = type !== "icon") {
    return {
      id: uid(`weekly-${type}`),
      type,
      label,
      icon,
      showIcon,
      showLabel,
      paid
    };
  }

  function defaultSection(id, name) {
    const rows = [
      choreRow("regular", "Laundry", "wash-machine", false, daySelection(true), true),
      choreRow("regular", "Make bed", "bed", false, daySelection(true), true),
      choreRow("regular", "Brush teeth", "brush", false, daySelection(true), true)
    ];

    if (name === "Morning") {
      rows.push(choreRow("regular", "Feed cat", "cat"));
    }

    return { id, name, rows };
  }

  function defaultWeeklyChores() {
    return {
      title: "Weekly Chores",
      rows: [
        weeklyRow("regular", "Clean bedroom", true),
        weeklyRow("regular", "Put away laundry", false),
        weeklyRow("empty"),
        weeklyRow("empty"),
        weeklyRow("empty")
      ]
    };
  }

  function defaultChildChart(childName = "Jack", existingIds = []) {
    let id = slugify(childName);
    if (existingIds.includes(id)) {
      let i = 2;
      while (existingIds.includes(`${id}-${i}`)) i += 1;
      id = `${id}-${i}`;
    }

    return {
      id,
      childName,
      orientation: "landscape",
      paperSize: "letter",
      days: days(),
      sections: [
        defaultSection("morning", "Morning"),
        defaultSection("daytime", "Daytime"),
        defaultSection("before-bed", "Before Bed")
      ],
      weeklyChores: defaultWeeklyChores()
    };
  }

  function defaultChart() {
    const child = defaultChildChart("Jack");
    return {
      version: CHART_VERSION,
      activeChildId: child.id,
      children: [child]
    };
  }

  function validDays(candidate) {
    return Array.isArray(candidate) &&
      candidate.length === DAY_KEYS.length &&
      candidate.every((day, index) => day && day.key === DAY_KEYS[index]);
  }

  function normalizeDays(selection, defaultValue = true) {
    const source = selection && typeof selection === "object" ? selection : {};
    return Object.fromEntries(DAY_KEYS.map((key) => [key, source[key] !== undefined ? Boolean(source[key]) : defaultValue]));
  }

  function normalizeIconChoice(icon) {
    const name = String(icon || "");
    if (window.ChoreChartIcons && window.ChoreChartIcons.iconExists(name)) return name;

    return "home";
  }

  function rowIconIsValid(row) {
    if (!row || !row.icon) return false;
    return window.ChoreChartIcons.iconExists(row.icon);
  }

  function rowHasIcon(row) {
    return row && (row.type === "icon" || Boolean(row.showIcon));
  }

  function rowShowsIconOnly(row) {
    return row.type === "icon" || (row.type === "regular" && rowHasIcon(row) && row.showLabel === false);
  }

  function rowShowsLabel(row) {
    return row.type === "regular" && row.showLabel !== false && Boolean(String(row.label || "").trim());
  }

  function rowShowsInlineIcon(row) {
    return row.type === "regular" && rowHasIcon(row) && row.showLabel !== false;
  }

  function inferRowType(row) {
    const hasIcon = rowHasIcon(row);
    const hasLabel = Boolean(String(row && row.label || "").trim());

    if (hasIcon && !hasLabel) return "icon";
    if (hasLabel) return "regular";
    return "empty";
  }

  function labelFromIcon(icon) {
    const name = String(icon || "home");
    if (window.ChoreChartIcons && Array.isArray(window.ChoreChartIcons.tablerOptions)) {
      const option = window.ChoreChartIcons.tablerOptions.find((item) => item.icon === name);
      if (option && option.label) return option.label;
    }

    return name.replace(/-/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
  }

  function normalizeShowLabel(row, savedType) {
    if (row && row.showLabel !== undefined) return row.showLabel !== false;
    return savedType !== "icon";
  }

  function normalizeChoreRow(row) {
    const savedType = ROW_TYPES.includes(row && row.type) ? row.type : "regular";
    const showIcon = savedType === "icon" || Boolean(row.showIcon);
    const icon = normalizeIconChoice(row.icon || "home");
    const normalized = {
      id: row.id || uid(savedType),
      type: savedType,
      label: savedType === "empty" ? "" : String(row.label || ""),
      icon: showIcon ? icon : "",
      showIcon,
      showLabel: normalizeShowLabel(row, savedType),
      paid: Boolean(row.paid),
      days: normalizeDays(row.days, true)
    };

    normalized.type = inferRowType(normalized);
    if (normalized.type === "empty") normalized.label = "";

    if (savedType === "icon" && !String(row.label || "").trim()) {
      normalized.label = labelFromIcon(icon);
      normalized.showLabel = false;
      normalized.type = inferRowType(normalized);
    }

    return {
      ...normalized
    };
  }

  function normalizeWeeklyRow(row) {
    const savedType = ROW_TYPES.includes(row && row.type) ? row.type : "regular";
    const showIcon = savedType === "icon" || Boolean(row.showIcon);
    const icon = normalizeIconChoice(row.icon || "home");
    const normalized = {
      id: row.id || uid(`weekly-${savedType}`),
      type: savedType,
      label: savedType === "empty" ? "" : String(row.label || ""),
      icon: showIcon ? icon : "",
      showIcon,
      showLabel: normalizeShowLabel(row, savedType),
      paid: Boolean(row.paid)
    };

    normalized.type = inferRowType(normalized);
    if (normalized.type === "empty") normalized.label = "";

    if (savedType === "icon" && !String(row.label || "").trim()) {
      normalized.label = labelFromIcon(icon);
      normalized.showLabel = false;
      normalized.type = inferRowType(normalized);
    }

    return {
      ...normalized
    };
  }

  function migrateLegacyIconRow(row) {
    const firstDay = row.cells && row.cells[DAY_KEYS[0]];
    if (!Array.isArray(firstDay)) return [];
    return firstDay.map((slot) => {
      const icon = normalizeIconChoice(slot && slot.icon);
      return choreRow("icon", "", icon, Boolean(slot && slot.paid), daySelection(true), true);
    });
  }

  function migrateLegacyTextRow(row) {
    const firstValue = row.cells && row.cells[DAY_KEYS[0]];
    const label = firstValue && typeof firstValue === "object" ? firstValue.label : "";
    const paid = firstValue && typeof firstValue === "object" ? Boolean(firstValue.paid) : false;
    return label ? [choreRow("regular", label, "room", paid)] : [];
  }

  function migrateLegacySection(section) {
    const rows = [];
    if (Array.isArray(section.rows)) {
      section.rows.forEach((row) => {
        if (row.type === "icons") rows.push(...migrateLegacyIconRow(row));
        if (row.type === "text") rows.push(...migrateLegacyTextRow(row));
        if (row.type === "write-in") rows.push(choreRow("empty", "", "room", Boolean(row.paid)));
      });
    }
    return {
      id: section.id || uid("section"),
      name: section.name || "Section",
      rows
    };
  }

  function migrateLegacyWeekly(weekly) {
    return {
      title: weekly && weekly.title ? weekly.title : "Weekly Chores",
      rows: Array.isArray(weekly && weekly.rows)
        ? weekly.rows.map((row) => normalizeWeeklyRow({
            id: row.id,
            type: row.type === "write-in" ? "empty" : "regular",
            label: row.label,
            paid: row.paid
          }))
        : defaultWeeklyChores().rows
    };
  }

  function migrateLegacyChart(chart) {
    const children = chart.children.map((child) => ({
      id: child.id || slugify(child.childName),
      childName: child.childName || "Child",
      orientation: child.orientation || "landscape",
      paperSize: child.paperSize || "letter",
      days: validDays(child.days) ? child.days : days(),
      sections: Array.isArray(child.sections) ? child.sections.map(migrateLegacySection) : [],
      weeklyChores: migrateLegacyWeekly(child.weeklyChores)
    }));

    return {
      version: CHART_VERSION,
      activeChildId: children.some((child) => child.id === chart.activeChildId) ? chart.activeChildId : children[0].id,
      children
    };
  }

  function normalizeChart(chart) {
    if (!chart || !Array.isArray(chart.children) || chart.children.length === 0) return defaultChart();
    if (chart.version === 1) return normalizeChart(migrateLegacyChart(chart));
    if (chart.version !== CHART_VERSION) return defaultChart();

    chart.children.forEach((child) => {
      child.id = child.id || slugify(child.childName);
      child.childName = child.childName || "Child";
      child.paperSize = child.paperSize || "letter";
      child.orientation = child.orientation || "landscape";
      child.days = validDays(child.days) ? child.days : days();
      child.sections = Array.isArray(child.sections) ? child.sections : [];
      child.sections.forEach((section) => {
        section.id = section.id || uid("section");
        section.name = section.name || "Section";
        section.rows = Array.isArray(section.rows) ? section.rows.map(normalizeChoreRow) : [];
      });
      child.weeklyChores = child.weeklyChores || defaultWeeklyChores();
      child.weeklyChores.title = child.weeklyChores.title || "Weekly Chores";
      child.weeklyChores.rows = Array.isArray(child.weeklyChores.rows)
        ? child.weeklyChores.rows.map(normalizeWeeklyRow)
        : defaultWeeklyChores().rows;
    });

    if (!chart.children.some((child) => child.id === chart.activeChildId)) {
      chart.activeChildId = chart.children[0].id;
    }

    return chart;
  }

  function validateChart(chart) {
    if (!chart || chart.version !== CHART_VERSION) return `Imported file must be version ${CHART_VERSION}.`;
    if (!Array.isArray(chart.children) || chart.children.length === 0) return "Imported file must include at least one child chart.";
    if (!chart.children.some((child) => child.id === chart.activeChildId)) return "Active child must point to an existing child chart.";

    for (const child of chart.children) {
      if (!child.id || !child.childName) return "Every child chart needs an id and childName.";
      if (!validDays(child.days)) return `${child.childName} must use Sunday-first days.`;
      if (!Array.isArray(child.sections)) return `${child.childName} must include sections.`;
      if (!child.weeklyChores || !Array.isArray(child.weeklyChores.rows)) return `${child.childName} must include weekly chores.`;

      for (const section of child.sections) {
        if (!Array.isArray(section.rows)) return `${section.name || "A section"} has invalid rows.`;
        for (const row of section.rows) {
          if (!ROW_TYPES.includes(row.type)) return "Daily rows must be icon, regular, or empty.";
          if (!row.days || DAY_KEYS.some((key) => typeof row.days[key] !== "boolean")) return "Every daily row needs Sunday-first day checkboxes.";
          if (row.type === "icon" && !rowIconIsValid(row)) return `Unknown icon: ${row.icon}`;
        }
      }

      for (const row of child.weeklyChores.rows) {
        if (!ROW_TYPES.includes(row.type)) return "Weekly rows must be icon, regular, or empty.";
        if ((row.type === "icon" || row.showIcon) && !rowIconIsValid(row)) return `Unknown icon: ${row.icon}`;
      }
    }

    return "";
  }

  function moveItem(list, index, direction) {
    const next = index + direction;
    if (next < 0 || next >= list.length) return;
    const [item] = list.splice(index, 1);
    list.splice(next, 0, item);
  }

  document.addEventListener("alpine:init", () => {
    Alpine.data("choreChartApp", () => ({
      chart: defaultChart(),
      fileHandle: null,
      importError: "",
      dataStatus: "",
      shareCopyStatus: "",
      shareJsonText: "",
      installDismissed: false,
      installMode: "",
      installPromptEvent: null,
      installPromptVisible: false,
      favoriteIconValues: [],
      saveChartDebounced: null,
      storageReady: false,
      printMode: "selected",
      viewMode: new URLSearchParams(window.location.search).get("view") === "preview" ? "preview" : "edit",

      async init() {
        this.saveChartDebounced = debounce(async (chart) => {
          try {
            await saveStoredChart(chart);
            this.dataStatus = "Saved in this browser.";
          } catch (error) {
            console.error("Could not save chart data to IndexedDB.", error);
            this.importError = "Could not save changes in this browser.";
          }
        });

        this.installDismissed = readLocalFlag(INSTALL_PROMPT_DISMISSED_KEY);
        this.favoriteIconValues = readLocalJson(ICON_FAVORITES_KEY, []);
        this.setupInstallPrompt();

        this.chart = await loadStoredChart();
        this.fileHandle = await loadFileHandle();
        this.storageReady = true;

        this.$watch("chart", (chart) => {
          if (!this.storageReady) return;
          this.saveChartDebounced(chart);
        });
      },

      get activeChild() {
        return this.chart.children.find((child) => child.id === this.chart.activeChildId) || this.chart.children[0];
      },

      get iconOptions() {
        return window.ChoreChartIcons.iconOptions;
      },

      get rowTypes() {
        return ROW_TYPES;
      },

      get paidCoin() {
        return window.ChoreChartIcons.uiIcon("coin");
      },

      get showInstallPrompt() {
        return this.installPromptVisible && !isStandaloneApp();
      },

      get installPromptMessage() {
        if (this.installMode === "ios") {
          return "Use Safari's Share menu, then Add to Home Screen.";
        }

        return "Save this chart builder to your device for quick access and offline use.";
      },

      get installPromptActionLabel() {
        return this.installMode === "ios" ? "Got it" : "Install";
      },

      uiIcon(name) {
        return window.ChoreChartIcons.uiIcon(name);
      },

      setupInstallPrompt() {
        const applyPendingInstallPrompt = () => {
          if (!pendingInstallPromptEvent) return;
          this.installPromptEvent = pendingInstallPromptEvent;
          this.installMode = "native";
          this.refreshInstallPrompt();
        };

        window.addEventListener("chorechart:installable", applyPendingInstallPrompt);

        window.addEventListener("appinstalled", () => {
          this.clearInstallPrompt(true);
        });

        applyPendingInstallPrompt();

        if (isIosSafari()) {
          this.installMode = "ios";
          this.refreshInstallPrompt();
        }
      },

      refreshInstallPrompt() {
        this.installPromptVisible = !this.installDismissed && !isStandaloneApp() && Boolean(this.installPromptEvent || this.installMode === "ios");
      },

      clearInstallPrompt(persist = false) {
        this.installPromptEvent = null;
        this.installPromptVisible = false;
        if (persist) {
          this.installDismissed = true;
          writeLocalFlag(INSTALL_PROMPT_DISMISSED_KEY);
        }
      },

      dismissInstallPrompt() {
        this.clearInstallPrompt(true);
      },

      async installApp() {
        if (this.installMode === "ios" || !this.installPromptEvent) {
          this.dismissInstallPrompt();
          return;
        }

        const promptEvent = this.installPromptEvent;
        this.clearInstallPrompt(true);

        try {
          await promptEvent.prompt();
        } catch (error) {
          console.error("Could not show install prompt.", error);
        }
      },

      iconValue(row) {
        return window.ChoreChartIcons.iconValue(row);
      },

      rowIconChoice(row) {
        return rowHasIcon(row) ? normalizeIconChoice(row.icon || "home") : "";
      },

      setRowIconChoice(row, option) {
        const value = typeof option === "string" ? option : this.iconValue(option);
        if (!value) {
          row.icon = "";
          row.showIcon = false;
          row.type = inferRowType(row);
          return;
        }

        row.icon = normalizeIconChoice(value);
        row.showIcon = true;
        row.type = inferRowType(row);
      },

      syncRowFromFields(row) {
        row.label = String(row.label || "");
        if (row.showLabel === undefined) row.showLabel = true;
        row.type = inferRowType(row);
      },

      normalizeRowForType(row) {
        if (!ROW_TYPES.includes(row.type)) row.type = "regular";
        row.days = normalizeDays(row.days, true);
        row.showIcon = rowHasIcon(row);
        row.icon = row.showIcon ? normalizeIconChoice(row.icon || "home") : "";
        row.type = inferRowType(row);
        if (row.type === "empty") row.label = "";
      },

      iconOptionByValue(value) {
        return this.allIconOptions.find((option) => option.icon === value) || null;
      },

      get allIconOptions() {
        return [
          ...window.ChoreChartIcons.tablerOptions
        ];
      },

      get favoriteIconOptions() {
        return this.favoriteIconValues
          .map((value) => this.iconOptionByValue(value))
          .filter(Boolean);
      },

      filteredIconOptions(query = "") {
        const terms = String(query || "").trim().toLowerCase().split(/\s+/).filter(Boolean);
        const source = terms.length ? this.allIconOptions : [
          ...this.favoriteIconOptions,
          ...window.ChoreChartIcons.iconOptions
        ];
        const seen = new Set();
        const filtered = source.map((option, index) => ({ option, index, score: this.iconSearchScore(option, terms) })).filter((result) => {
          const option = result.option;
          const value = this.iconValue(option);
          if (seen.has(value)) return false;
          seen.add(value);
          return !terms.length || result.score < Number.POSITIVE_INFINITY;
        }).sort((a, b) => a.score - b.score || a.index - b.index).map((result) => result.option);

        return filtered.slice(0, 80);
      },

      iconSearchScore(option, terms) {
        if (!terms.length) return 0;
        const icon = option.icon.toLowerCase();
        const label = option.label.toLowerCase();
        const tags = Array.isArray(option.tags) ? option.tags.map((tag) => tag.toLowerCase()) : [];
        const iconWords = icon.replace(/-/g, " ").split(/\s+/);
        const labelWords = label.split(/\s+/);
        const categoryWords = String(option.category || "").toLowerCase().split(/\s+/).filter(Boolean);
        const words = [...iconWords, ...labelWords, ...categoryWords, ...tags];
        const searchable = option.search || words.join(" ");
        let score = 0;

        for (const term of terms) {
          const exactTagIndex = tags.findIndex((tag) => tag === term);
          const prefixTagIndex = tags.findIndex((tag) => tag.startsWith(term));

          if (icon === term || label === term) {
            score += 0;
          } else if (icon.startsWith(term) || label.startsWith(term)) {
            score += 1;
          } else if (iconWords.some((word) => word === term) || labelWords.some((word) => word === term)) {
            score += 2;
          } else if (exactTagIndex >= 0) {
            score += 3 + exactTagIndex / 10;
          } else if (iconWords.some((word) => word.startsWith(term)) || labelWords.some((word) => word.startsWith(term))) {
            score += 4;
          } else if (prefixTagIndex >= 0) {
            score += 6 + prefixTagIndex / 10;
          } else if (categoryWords.some((word) => word === term || word.startsWith(term))) {
            score += 8;
          } else if (searchable.includes(term)) {
            score += 12;
          } else {
            return Number.POSITIVE_INFINITY;
          }
        }

        if (icon.endsWith("-off")) score += 8;
        if (/-\d+$/.test(icon)) score += 1;

        return score;
      },

      favoriteIconCount() {
        return this.favoriteIconOptions.length;
      },

      isFavoriteIcon(option) {
        return this.favoriteIconValues.includes(this.iconValue(option));
      },

      toggleFavoriteIcon(option) {
        const value = this.iconValue(option);
        this.favoriteIconValues = this.isFavoriteIcon(option)
          ? this.favoriteIconValues.filter((favorite) => favorite !== value)
          : [value, ...this.favoriteIconValues].slice(0, 48);
        writeLocalJson(ICON_FAVORITES_KEY, this.favoriteIconValues);
      },

      renderIcon(row) {
        return window.ChoreChartIcons.renderIcon(row);
      },

      rowShowsLabel(row) {
        return row.type === "regular" && row.showLabel !== false && Boolean(String(row.label || "").trim());
      },

      rowShowsIconOnly(row) {
        return row.type === "icon" || (row.type === "regular" && rowHasIcon(row) && row.showLabel === false);
      },

      rowShowsInlineIcon(row) {
        return row.type === "regular" && rowHasIcon(row) && row.showLabel !== false;
      },

      sectionIconGroups(section) {
        const icons = section.rows.filter((row) => this.rowShowsIconOnly(row));
        const groups = [];
        for (let i = 0; i < icons.length; i += 3) {
          groups.push({ id: `${section.id}-icons-${i}`, rows: icons.slice(i, i + 3) });
        }
        return groups;
      },

      sectionDetailRows(section) {
        return section.rows.filter((row) => !this.rowShowsIconOnly(row));
      },

      printIconCells(group, child = this.activeChild) {
        return child.days.flatMap((day, dayIndex) => group.rows
          .filter((row) => row && row.days[day.key])
          .map((row, slotIndex) => ({
            key: `${group.id}-${day.key}-${row.id}`,
            color: day.color,
            gridColumn: dayIndex * 3 + slotIndex + 1,
            row,
            visible: true
          })));
      },

      printDayCells(row, child = this.activeChild) {
        return child.days.map((day, dayIndex) => ({
          key: `${row.id}-${day.key}`,
          color: day.color,
          gridColumn: `${dayIndex * 3 + 1} / span 3`,
          row,
          visible: Boolean(row.days[day.key])
        }));
      },

      weeklyCellGridColumn(index) {
        return `${(index % 3) * 7 + 1} / span 7`;
      },

      addChild() {
        const name = window.prompt("Child name", "New Child");
        if (!name) return;
        const child = defaultChildChart(name, this.chart.children.map((item) => item.id));
        this.chart.children.push(child);
        this.chart.activeChildId = child.id;
      },

      duplicateChild() {
        const source = clone(this.activeChild);
        const base = `${source.childName} Copy`;
        source.id = slugify(base);
        let i = 2;
        const existing = this.chart.children.map((child) => child.id);
        while (existing.includes(source.id)) {
          source.id = `${slugify(base)}-${i}`;
          i += 1;
        }
        source.childName = base;
        this.chart.children.push(source);
        this.chart.activeChildId = source.id;
      },

      deleteChild() {
        if (this.chart.children.length === 1) return;
        if (!window.confirm(`Delete ${this.activeChild.childName}'s chart?`)) return;
        const index = this.chart.children.findIndex((child) => child.id === this.chart.activeChildId);
        this.chart.children.splice(index, 1);
        this.chart.activeChildId = this.chart.children[Math.max(0, index - 1)].id;
      },

      addSection() {
        const name = window.prompt("Section name", "New Section");
        if (!name) return;
        this.activeChild.sections.push({ id: uid(slugify(name)), name, rows: [] });
      },

      deleteSection(index) {
        if (!window.confirm("Delete this section?")) return;
        this.activeChild.sections.splice(index, 1);
      },

      moveSection(index, direction) {
        moveItem(this.activeChild.sections, index, direction);
      },

      sortItems(list, itemId, position) {
        const index = list.findIndex((item) => item.id === itemId);
        if (index < 0 || position < 0 || position >= list.length || index === position) return;
        const [item] = list.splice(index, 1);
        list.splice(position, 0, item);
      },

      sortSections(itemId, position) {
        this.sortItems(this.activeChild.sections, itemId, position);
      },

      sortRows(rows, itemId, position) {
        this.sortItems(rows, itemId, position);
      },

      addRow(section) {
        section.rows.push(choreRow("empty", "", "", false, daySelection(true), false));
      },

      deleteRow(section, index) {
        section.rows.splice(index, 1);
      },

      moveRow(rows, index, direction) {
        moveItem(rows, index, direction);
      },

      addWeeklyRow() {
        this.activeChild.weeklyChores.rows.push(weeklyRow("empty", "", false, "", false));
      },

      deleteWeeklyRow(index) {
        this.activeChild.weeklyChores.rows.splice(index, 1);
      },

      moveWeeklyRow(index, direction) {
        moveItem(this.activeChild.weeklyChores.rows, index, direction);
      },

      normalizeWeeklyRow(row) {
        row.showIcon = rowHasIcon(row);
        row.icon = row.showIcon ? normalizeIconChoice(row.icon || "home") : "";
        row.type = inferRowType(row);
        if (row.type === "empty") row.label = "";
      },

      suggestedFileName() {
        return `${slugify(this.activeChild.childName)}-chore-chart.json`;
      },

      downloadJson() {
        const blob = new Blob([chartJson(this.chart)], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = this.suggestedFileName();
        link.click();
        URL.revokeObjectURL(url);
        this.dataStatus = "Downloaded JSON file.";
      },

      refreshShareJson() {
        this.shareJsonText = chartJson(this.chart);
      },

      openShareDialog() {
        this.importError = "";
        this.shareCopyStatus = "";
        this.refreshShareJson();

        if (this.$refs.shareDialog && this.$refs.shareDialog.showModal) {
          this.$refs.shareDialog.showModal();
          return;
        }

        this.copyShareJson();
      },

      async copyShareJson() {
        this.refreshShareJson();

        try {
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(this.shareJsonText);
          } else {
            this.$nextTick(() => {
              this.$refs.shareJsonTextArea.focus();
              this.$refs.shareJsonTextArea.select();
              document.execCommand("copy");
            });
          }

          this.shareCopyStatus = "Copied JSON.";
          this.dataStatus = "Copied JSON to clipboard.";
        } catch (error) {
          console.error("Could not copy JSON.", error);
          this.shareCopyStatus = "Could not copy JSON.";
        }
      },

      async shareJson() {
        this.importError = "";
        this.refreshShareJson();

        if (!navigator.share) {
          await this.copyShareJson();
          return;
        }

        const file = new File([this.shareJsonText], this.suggestedFileName(), { type: "application/json" });
        const filePayload = {
          title: "Chore chart JSON",
          text: `${this.activeChild.childName}'s chore chart JSON`,
          files: [file]
        };

        try {
          if (!navigator.canShare || navigator.canShare({ files: [file] })) {
            await navigator.share(filePayload);
          } else {
            await navigator.share({
              title: filePayload.title,
              text: this.shareJsonText
            });
          }

          this.dataStatus = "Shared JSON.";
          if (this.$refs.shareDialog && this.$refs.shareDialog.open) {
            this.$refs.shareDialog.close();
          }
        } catch (error) {
          if (error && error.name === "AbortError") return;
          console.error("Could not share JSON.", error);
          this.shareCopyStatus = "Could not share JSON.";
        }
      },

      async saveJson() {
        this.importError = "";
        if (!this.fileHandle || !(await verifyFilePermission(this.fileHandle, "readwrite"))) {
          await this.saveJsonAs();
          return;
        }

        try {
          await writeJsonFile(this.fileHandle, this.chart);
          await saveStoredChart(this.chart);
          this.dataStatus = `Saved ${this.fileHandle.name || "JSON file"}.`;
        } catch (error) {
          console.error("Could not save JSON file.", error);
          this.importError = "Could not save JSON file.";
        }
      },

      async saveJsonAs() {
        this.importError = "";
        if (!window.showSaveFilePicker) {
          this.downloadJson();
          return;
        }

        try {
          const handle = await window.showSaveFilePicker({
            suggestedName: this.suggestedFileName(),
            types: filePickerTypes()
          });
          await writeJsonFile(handle, this.chart);
          this.fileHandle = handle;
          await saveFileHandle(handle);
          await saveStoredChart(this.chart);
          this.dataStatus = `Saved ${handle.name || "JSON file"}.`;
        } catch (error) {
          if (error && error.name === "AbortError") return;
          console.error("Could not save JSON file.", error);
          this.importError = "Could not save JSON file.";
        }
      },

      async openJsonFile() {
        this.importError = "";
        if (!window.showOpenFilePicker) {
          this.$refs.jsonFileInput.click();
          return;
        }

        try {
          const [handle] = await window.showOpenFilePicker({
            multiple: false,
            types: filePickerTypes()
          });
          const file = await handle.getFile();
          await this.applyImportedJson(await file.text());
          this.fileHandle = handle;
          await saveFileHandle(handle);
          this.dataStatus = `Opened ${handle.name || "JSON file"}.`;
        } catch (error) {
          if (error && error.name === "AbortError") return;
          console.error("Could not open JSON file.", error);
          this.importError = "Could not open JSON file.";
        }
      },

      async applyImportedJson(contents) {
        const parsed = normalizeChart(JSON.parse(contents));
        const error = validateChart(parsed);
        if (error) {
          this.importError = error;
          return false;
        }
        this.chart = parsed;
        await saveStoredChart(parsed);
        this.dataStatus = "Imported JSON file.";
        return true;
      },

      importJson(event) {
        const file = event.target.files[0];
        this.importError = "";
        if (!file) return;
        const reader = new FileReader();
        reader.onload = async () => {
          try {
            await this.applyImportedJson(reader.result);
          } catch (error) {
            this.importError = "Imported file is not valid JSON.";
          } finally {
            event.target.value = "";
          }
        };
        reader.readAsText(file);
      },

      resetToDefaults() {
        if (!window.confirm("Reset all saved chore charts?")) return;
        this.chart = defaultChart();
        saveStoredChart(this.chart)
          .then(() => {
            this.dataStatus = "Reset to default chart.";
          })
          .catch((error) => {
            console.error("Could not reset chart data in IndexedDB.", error);
            this.importError = "Could not reset saved chart data.";
          });
      },

      printChart(mode = "selected") {
        this.printMode = mode;
        window.setTimeout(() => window.print());
      }
    }));
  });

  window.ChoreChartDefaults = {
    defaultChart,
    defaultChildChart,
    validateChart,
    loadStoredChart,
    saveStoredChart
  };

  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("service-worker.js").catch((error) => {
        console.error("Could not register service worker.", error);
      });
    });
  }
})();
